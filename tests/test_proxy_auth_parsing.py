"""Authenticated proxy lists must survive parsing.

WHY THIS EXISTS: every FREE-TIER and TRIAL proxy provider (Webshare, IPRoyal, Rayobyte,
ProxyScrape, Smartproxy...) hands out AUTHENTICATED endpoints. The original _parse_line
returned only "host:port" — it silently discarded user:pass, so every proxy from a trial
list would have failed the health check with 407 and the pool would have read as "0 healthy",
which is indistinguishable from "provider is blocked". That misread already cost this project
one full night on the free-proxy-list path.

Two wire formats are in the wild and both must work:
  user:pass@host:port     (URL form — what you paste into a browser/curl)
  host:port:user:pass     (download form — Webshare/IPRoyal "Download list" default)
"""
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "scraper"))

from proxy_manager import ProxyManager  # noqa: E402

P = ProxyManager._parse_line


def test_bare_ip_port_still_works():
    assert P("1.2.3.4:8080") == "1.2.3.4:8080"
    assert P("proxy.example.com:3128") == "proxy.example.com:3128"


def test_scheme_is_stripped():
    assert P("http://1.2.3.4:8080") == "1.2.3.4:8080"


def test_url_form_credentials_are_preserved():
    assert P("user:pass@1.2.3.4:8080") == "user:pass@1.2.3.4:8080"
    assert P("http://user:pass@1.2.3.4:8080") == "user:pass@1.2.3.4:8080"


def test_download_form_is_converted_to_url_form():
    """host:port:user:pass is what Webshare/IPRoyal actually give you."""
    assert P("1.2.3.4:8080:user:pass") == "user:pass@1.2.3.4:8080"


def test_credentials_survive_norm_so_pool_keys_keep_auth():
    """_norm builds the pool key. If it dropped auth the credential loss would just
    move one function later."""
    assert ProxyManager._norm("http://user:pass@1.2.3.4:8080") == "user:pass@1.2.3.4:8080"


def test_garbage_still_rejected():
    for bad in ("", "#comment", "not a proxy", "1.2.3.4", "1.2.3.4:99999", "1.2.3.4:abc"):
        assert P(bad) is None, bad


if __name__ == "__main__":
    fails = []
    for name, fn in sorted(globals().items()):
        if name.startswith("test_") and callable(fn):
            try:
                fn()
                print(f"  [PASS] {name}")
            except AssertionError as e:
                print(f"  [FAIL] {name}: {e}")
                fails.append(name)
    print(f"\n{len(fails)} failure(s)" if fails else "\nall pass")
    raise SystemExit(1 if fails else 0)
