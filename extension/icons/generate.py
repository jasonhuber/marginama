#!/usr/bin/env python3
"""
Generate the Marginama extension's toolbar icons at 16/48/128 px.

Design: dark rounded-square card (#111114) matching the website brand mark,
with a cyan (#06b6d4) "§" section-mark — the project's sigil, also used in
the website header and the extension's shadow-DOM panel.

Run:
    cd extension/icons
    python3 generate.py
"""

from PIL import Image, ImageDraw, ImageFont
import os

# Marginama palette
BG = (17, 17, 20, 255)        # #111114
BORDER = (31, 31, 35, 255)    # #1f1f23
ACCENT = (6, 182, 212, 255)   # #06b6d4

# Character that IS the brand mark. Render the glyph in the heaviest
# weight available so it remains legible at 16x16.
MARK = "\u00a7"  # §

# Font fallback order. We want something bold, geometric, and guaranteed
# to carry the § glyph. macOS ships all of these.
FONT_CANDIDATES = [
    "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
    "/System/Library/Fonts/Supplemental/Arial.ttf",
    "/System/Library/Fonts/HelveticaNeue.ttc",
    "/System/Library/Fonts/Helvetica.ttc",
    "/Library/Fonts/Arial Bold.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
]


def load_font(pt: int) -> ImageFont.FreeTypeFont:
    for path in FONT_CANDIDATES:
        if os.path.isfile(path):
            try:
                return ImageFont.truetype(path, pt)
            except Exception:
                continue
    # Last-resort default (bitmap, ugly but won't crash).
    return ImageFont.load_default()


def rounded_rect(draw: ImageDraw.ImageDraw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def draw_icon(size: int) -> Image.Image:
    # Render at 4x then downsample for crisp anti-aliased edges.
    scale = 4
    s = size * scale
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Card background with a subtle hairline border.
    pad = max(1, int(s * 0.02))
    radius = int(s * 0.22)
    rounded_rect(
        draw,
        [pad, pad, s - pad, s - pad],
        radius=radius,
        fill=BG,
        outline=BORDER,
        width=max(1, int(s * 0.012)),
    )

    # Pick a glyph size proportional to the card. § is relatively narrow,
    # so we can push closer to full height than for, say, "M".
    pt = int(s * 0.72)
    font = load_font(pt)

    # Measure + center.
    bbox = draw.textbbox((0, 0), MARK, font=font)
    glyph_w = bbox[2] - bbox[0]
    glyph_h = bbox[3] - bbox[1]
    # Account for glyph's top-side bearing.
    x = (s - glyph_w) // 2 - bbox[0]
    y = (s - glyph_h) // 2 - bbox[1] - int(s * 0.02)

    draw.text((x, y), MARK, font=font, fill=ACCENT)

    # Downsample with LANCZOS for crisp final raster.
    return img.resize((size, size), Image.LANCZOS)


def main() -> None:
    for size in (16, 48, 128):
        icon = draw_icon(size)
        out = f"icon{size}.png"
        icon.save(out, "PNG", optimize=True)
        print(f"wrote {out}")


if __name__ == "__main__":
    main()
