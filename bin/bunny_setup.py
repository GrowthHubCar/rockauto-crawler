"""bunny_setup.py — provision BunnyCDN for image hosting from your ACCOUNT API key.

Reads BUNNY_API_KEY from .bunny.env (or env), then idempotently ensures:
  1. a Storage Zone  (holds our permanent image copies)
  2. a Pull Zone     (origin = that storage zone; serves at <name>.b-cdn.net)
and writes the resolved BUNNY_STORAGE_ZONE / _KEY / _HOST / _PULL_HOST back into
.bunny.env so bin/bunny_upload.py can run. Safe to re-run: zones are matched by
name and reused, never duplicated.

  python bin/bunny_setup.py

Optional overrides (env or .bunny.env):
  BUNNY_STORAGE_ZONE   desired zone name (default supremeautos-parts; also the
                       pull zone name -> <name>.b-cdn.net). Must be globally unique.
  BUNNY_REGION         DE | NY | LA | SG   (default DE / Frankfurt)
"""
from __future__ import annotations

import json
import os
import sys

import requests

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "bin"))
import bunny  # noqa: E402

API = "https://api.bunny.net"


def _load() -> dict:
    vals = {}
    if os.path.exists(bunny.ENV_FILE):
        with open(bunny.ENV_FILE, encoding="utf-8") as fh:
            vals = bunny.parse_env(fh.read())
    for k in ("BUNNY_API_KEY", "BUNNY_STORAGE_ZONE", "BUNNY_REGION"):
        if os.environ.get(k):
            vals[k] = os.environ[k]
    return vals


def set_env(path: str, updates: dict) -> None:
    """Update/insert KEY=VALUE lines, preserving comments and other keys."""
    lines = []
    if os.path.exists(path):
        with open(path, encoding="utf-8") as fh:
            lines = fh.read().splitlines()
    seen = set()
    out = []
    for ln in lines:
        s = ln.strip()
        if s and not s.startswith("#") and "=" in s:
            k = s.split("=", 1)[0].strip()
            if k in updates:
                out.append(f"{k}={updates[k]}")
                seen.add(k)
                continue
        out.append(ln)
    for k, v in updates.items():
        if k not in seen:
            out.append(f"{k}={v}")
    with open(path, "w", encoding="utf-8") as fh:
        fh.write("\n".join(out) + "\n")


def _hdr(key: str) -> dict:
    return {"AccessKey": key, "Accept": "application/json", "Content-Type": "application/json"}


def _list(sess, key, kind):
    r = sess.get(f"{API}/{kind}", headers=_hdr(key),
                 params={"page": 1, "perPage": 1000}, timeout=30)
    r.raise_for_status()
    data = r.json()
    return data.get("Items", data) if isinstance(data, dict) else data


def _create(sess, key, kind, base, body_fn):
    """POST with name-collision fallback (append -1, -2 ... on 'taken')."""
    for attempt in range(6):
        nm = base if attempt == 0 else f"{base}-{attempt}"
        r = sess.post(f"{API}/{kind}", headers=_hdr(key),
                      data=json.dumps(body_fn(nm)), timeout=30)
        if r.status_code in (200, 201):
            return r.json()
        low = r.text.lower()
        if r.status_code in (400, 409) and any(w in low for w in ("taken", "exist", "unavailable", "already")):
            continue
        raise SystemExit(f"{kind} create failed {r.status_code}: {r.text[:300]}")
    raise SystemExit(f"no available {kind} name from base '{base}'")


def ensure_storage(sess, key, name, region):
    for z in _list(sess, key, "storagezone"):
        if z.get("Name") == name:
            if not z.get("Password"):  # list may omit the key; fetch detail
                d = sess.get(f"{API}/storagezone/{z['Id']}", headers=_hdr(key), timeout=30)
                if d.status_code == 200:
                    z = d.json()
            return z
    return _create(sess, key, "storagezone", name,
                   lambda nm: {"Name": nm, "Region": region})


def ensure_pull(sess, key, name, storage_id):
    for z in _list(sess, key, "pullzone"):
        if z.get("Name") == name:
            return z
    return _create(sess, key, "pullzone", name,
                   lambda nm: {"Name": nm, "StorageZoneId": storage_id, "OriginType": 2, "Type": 0})


def _pull_host(pz):
    for h in pz.get("Hostnames") or []:
        val = h.get("Value") if isinstance(h, dict) else None
        if val and val.endswith("b-cdn.net"):
            return val
    hn = pz.get("Hostnames") or []
    if hn and isinstance(hn[0], dict) and hn[0].get("Value"):
        return hn[0]["Value"]
    return f"{pz['Name']}.b-cdn.net"


def main() -> int:
    v = _load()
    key = v.get("BUNNY_API_KEY")
    if not key or key == "your-account-api-key":
        raise SystemExit("Set BUNNY_API_KEY in .bunny.env first (Bunny dashboard -> Account Settings -> API).")
    sname = v.get("BUNNY_STORAGE_ZONE") or "supremeautos-parts"
    region = (v.get("BUNNY_REGION") or "DE").upper()

    sess = requests.Session()
    sz = ensure_storage(sess, key, sname, region)
    sid, spw = sz["Id"], sz["Password"]
    shost = sz.get("StorageHostname") or "storage.bunnycdn.com"
    print(f"storage zone: {sz['Name']} (id {sid}) region {sz.get('Region')} host {shost}")

    pz = ensure_pull(sess, key, sz["Name"], sid)
    phost = _pull_host(pz)
    print(f"pull zone   : {pz['Name']} (id {pz['Id']}) host {phost}")

    set_env(bunny.ENV_FILE, {
        "BUNNY_STORAGE_ZONE": sz["Name"],
        "BUNNY_STORAGE_KEY": spw,
        "BUNNY_STORAGE_HOST": shost,
        "BUNNY_PULL_HOST": phost,
    })
    print("wrote storage/pull config to .bunny.env -> ready for  python bin/bunny_upload.py --warm")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
