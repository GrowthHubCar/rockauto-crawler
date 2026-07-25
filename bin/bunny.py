"""bunny.py — tiny BunnyCDN Storage client + config loader.

Reads config from .bunny.env (KEY=VALUE, gitignored) or the environment:
  BUNNY_STORAGE_ZONE   storage zone name
  BUNNY_STORAGE_KEY    storage zone password / AccessKey
  BUNNY_STORAGE_HOST   storage endpoint host (region), e.g. storage.bunnycdn.com
  BUNNY_PULL_HOST      pull zone hostname, e.g. supremeautos.b-cdn.net

Storage API: PUT https://{host}/{zone}/{path}  with header AccessKey: {key}.
Files are served from the pull zone at  https://{pull_host}/{path}.
We store each part photo under its stable rel path (e.g. 111/854059-FRO__ra_m.jpg),
so the pull URL mirrors the local /RockAuto/assets/parts/<rel> layout.
"""
from __future__ import annotations

import os
from typing import NamedTuple

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV_FILE = os.path.join(ROOT, ".bunny.env")

_KEYS = ("BUNNY_STORAGE_ZONE", "BUNNY_STORAGE_KEY", "BUNNY_STORAGE_HOST", "BUNNY_PULL_HOST")


class Cfg(NamedTuple):
    zone: str
    key: str
    host: str
    pull: str


def parse_env(text: str) -> dict:
    out = {}
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        out[k.strip()] = v.strip()
    return out


def load_cfg() -> Cfg:
    vals = {}
    if os.path.exists(ENV_FILE):
        with open(ENV_FILE, encoding="utf-8") as fh:
            vals.update(parse_env(fh.read()))
    for k in _KEYS:  # environment overrides the file
        if os.environ.get(k):
            vals[k] = os.environ[k]
    missing = [k for k in _KEYS if not vals.get(k)]
    if missing:
        raise SystemExit(
            f"Bunny config incomplete — missing {missing}. "
            f"Fill {ENV_FILE} (copy .bunny.env.example) or set env vars.")
    host = vals["BUNNY_STORAGE_HOST"].replace("https://", "").replace("http://", "").strip("/")
    pull = vals["BUNNY_PULL_HOST"].replace("https://", "").replace("http://", "").strip("/")
    return Cfg(vals["BUNNY_STORAGE_ZONE"].strip("/"), vals["BUNNY_STORAGE_KEY"], host, pull)


def storage_url(cfg: Cfg, rel: str) -> str:
    return f"https://{cfg.host}/{cfg.zone}/{rel.lstrip('/')}"


def pull_url(cfg: Cfg, rel: str) -> str:
    return f"https://{cfg.pull}/{rel.lstrip('/')}"


def put(session, cfg: Cfg, rel: str, data: bytes, timeout: int = 30) -> int:
    """Upload bytes to storage. Returns HTTP status (201 = created/overwritten)."""
    r = session.put(storage_url(cfg, rel), data=data,
                    headers={"AccessKey": cfg.key,
                             "Content-Type": "application/octet-stream"},
                    timeout=timeout)
    return r.status_code


def warm(session, cfg: Cfg, rel: str, timeout: int = 30) -> int:
    """Request the pull URL so the CDN edge caches it. Returns HTTP status."""
    r = session.get(pull_url(cfg, rel), timeout=timeout)
    return r.status_code


def _selftest() -> bool:
    ok = True

    def chk(label, cond):
        nonlocal ok
        ok = ok and cond
        print(f"  [{'PASS' if cond else 'FAIL'}] {label}")

    d = parse_env("# c\nBUNNY_STORAGE_ZONE=sp\n BUNNY_PULL_HOST = sp.b-cdn.net \nJUNK\n")
    chk("parse_env strips + skips", d["BUNNY_STORAGE_ZONE"] == "sp" and d["BUNNY_PULL_HOST"] == "sp.b-cdn.net")
    cfg = Cfg("sp", "k", "storage.bunnycdn.com", "sp.b-cdn.net")
    chk("storage_url", storage_url(cfg, "111/x.jpg") == "https://storage.bunnycdn.com/sp/111/x.jpg")
    chk("pull_url", pull_url(cfg, "/111/x.jpg") == "https://sp.b-cdn.net/111/x.jpg")
    print("PASS" if ok else "FAIL")
    return ok


if __name__ == "__main__":
    raise SystemExit(0 if _selftest() else 1)
