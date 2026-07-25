"""imgclean.py — strip RockAuto's bottom "RockAuto.com" watermark from part photos.

RockAuto stamps the watermark flush to the image's BOTTOM edge. Empirically (see
the analysis behind commit): its height scales with the image (~4% of height,
up to ~14px on a 300px thumbnail, ~5px on a 145px one) and its horizontal span
widens as the image narrows — bottom-right on wide photos, near-full-width on
tall/narrow ones. Because the only invariant is "flush to the bottom", the one
removal that works on 100% of images regardless of horizontal placement is a
full-width bottom crop tall enough to clear the stamp. A few px of product at the
very bottom is sacrificed by design (approved trade-off).

Idempotent: cleaned files carry a JFIF comment MARKER, so re-running the batch —
or re-downloading an already-cleaned image — never crops twice.
"""
from __future__ import annotations

import io

from PIL import Image

MARKER = b"sp-wm-v1"  # JFIF comment stamped on cleaned files


def crop_px(h: int) -> int:
    """Bottom rows to remove for an image of height `h`.

    max(16, 6%*h): the 6% tracks the watermark's proportional height with a
    comfortable margin; the 16px floor covers short images whose 6% would dip
    under the ~5-8px stamp they still carry."""
    return max(16, round(h * 0.06))


def _already_clean(im: Image.Image) -> bool:
    c = im.info.get("comment")
    if isinstance(c, str):
        c = c.encode("latin-1", "ignore")
    return c == MARKER


def clean_image(im: Image.Image) -> Image.Image:
    """Return a watermark-cropped copy (full-width bottom crop)."""
    w, h = im.size
    ch = crop_px(h)
    if ch >= h:  # pathological tiny image — leave it be
        return im
    return im.crop((0, 0, w, h - ch))


def clean_bytes(data: bytes) -> bytes:
    """Crop the watermark from encoded image bytes, returning cleaned JPEG bytes.

    Idempotent (returns input unchanged if already marked) and defensive: any
    decode/encode failure returns the original bytes so a bad image never breaks
    the crawl."""
    try:
        with Image.open(io.BytesIO(data)) as im:
            if _already_clean(im):
                return data
            out = clean_image(im.convert("RGB"))
            buf = io.BytesIO()
            out.save(buf, format="JPEG", quality=90, comment=MARKER)
            return buf.getvalue()
    except Exception:  # noqa: BLE001
        return data


def clean_file(path: str) -> bool:
    """Crop the watermark from a file in place, idempotently. True if it changed."""
    try:
        with Image.open(path) as im:
            if _already_clean(im):
                return False
            out = clean_image(im.convert("RGB"))
        out.save(path, format="JPEG", quality=90, comment=MARKER)
        return True
    except Exception:  # noqa: BLE001
        return False


def _selftest() -> bool:
    from PIL import ImageDraw

    ok = True

    def chk(label, cond):
        nonlocal ok
        ok = ok and cond
        print(f"  [{'PASS' if cond else 'FAIL'}] {label}")

    chk("crop_px floors at 16", crop_px(120) == 16)
    chk("crop_px 300 -> 18", crop_px(300) == 18)

    im = Image.new("RGB", (300, 300), "white")
    ImageDraw.Draw(im).rectangle([180, 289, 298, 299], fill=(120, 120, 120))
    chk("clean_image removes 18px", clean_image(im).size == (300, 282))

    raw = io.BytesIO()
    im.save(raw, format="JPEG")
    raw = raw.getvalue()
    c1 = clean_bytes(raw)
    im1 = Image.open(io.BytesIO(c1))
    chk("cleaned carries MARKER", _already_clean(im1))
    chk("cleaned height 282", im1.size == (300, 282))
    chk("idempotent: 2nd pass is a no-op", clean_bytes(c1) == c1)

    print("PASS" if ok else "FAIL")
    return ok


if __name__ == "__main__":
    raise SystemExit(0 if _selftest() else 1)
