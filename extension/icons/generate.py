#!/usr/bin/env python3
"""
Generate the extension's toolbar icons at 16/48/128 px.

Design: Socrates brand blue circle, a white "play" triangle (video), and a
small speech-bubble circle stamped at the triangle's tip (review / critique).
Kept simple so it reads at 16px.

Run:
    cd "Evolven Socrates Extension/icons"
    python3 generate.py
"""

from PIL import Image, ImageDraw, ImageFilter

# Socrates brand blue (from public/favicon.svg)
BRAND = (0, 116, 200, 255)
WHITE = (255, 255, 255, 255)
BUBBLE_STROKE = (0, 116, 200, 255)


def draw_icon(size: int) -> Image.Image:
    # Render at 4x and downsample for clean anti-aliased edges.
    scale = 4
    s = size * scale
    img = Image.new("RGBA", (s, s), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Circle background
    pad = int(s * 0.02)
    draw.ellipse([pad, pad, s - pad, s - pad], fill=BRAND)

    # Play triangle (centered, slightly left so the bubble sits to the right)
    cx = s * 0.44
    cy = s * 0.52
    tri_size = s * 0.34
    triangle = [
        (cx - tri_size * 0.5, cy - tri_size * 0.6),  # top-left
        (cx - tri_size * 0.5, cy + tri_size * 0.6),  # bottom-left
        (cx + tri_size * 0.65, cy),                  # right point
    ]
    draw.polygon(triangle, fill=WHITE)

    # Speech-bubble circle at the triangle's tip
    bx = cx + tri_size * 0.65
    by = cy
    br = s * 0.16
    draw.ellipse(
        [bx - br, by - br, bx + br, by + br],
        fill=WHITE,
        outline=BUBBLE_STROKE,
        width=max(2, int(s * 0.012)),
    )

    # Three dots in the bubble (…) to say "notes"
    dot_r = max(1, int(s * 0.018))
    for i, offset in enumerate([-br * 0.38, 0, br * 0.38]):
        draw.ellipse(
            [bx + offset - dot_r, by - dot_r,
             bx + offset + dot_r, by + dot_r],
            fill=BRAND,
        )

    # Downsample for anti-aliasing
    return img.resize((size, size), Image.LANCZOS)


def main() -> None:
    for size in (16, 48, 128):
        icon = draw_icon(size)
        out = f"icon{size}.png"
        icon.save(out, "PNG", optimize=True)
        print(f"wrote {out}")


if __name__ == "__main__":
    main()
