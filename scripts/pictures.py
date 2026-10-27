"""The Hooli icon, and every picture made from it.

Usage: python3 scripts/pictures.py   (run from the site root; needs Pillow and fontTools)

The icon is a big capital H, cut from Newsreader like the wordmark, in cream on a sage tile. This script writes:
  resources/views/partials/icon-tile.blade.php   the icon inline, for the page
  public/favicon.svg, favicon.ico, apple-touch-icon.png, images/icon-192.png, images/icon-512.png
  public/images/og.jpg, og-fr.jpg, og-et.jpg      the share picture in each language, 1200x630
  public/images/logo/                            the logo files: icon, H alone, icon with the wordmark, as SVG and PNG
"""
import pathlib

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from PIL import Image, ImageDraw, ImageFont

ROOT = pathlib.Path('.')
PUBLIC = ROOT / 'public'
LOGO = PUBLIC / 'images/logo'
NEWSREADER = ROOT / 'scripts/fonts/Newsreader.ttf'
FIGTREE = ROOT / 'scripts/fonts/Figtree.ttf'
SCALE = 4  # drawn at four times the size, then reduced, for smooth edges

# The palette (resources/css/app.css).
CREAM = '#FCF8F2'
MIST = '#E3EADD'
SAGE = '#7E9A80'
SAGE_DEEP = '#3D5A46'
INK = '#24302A'
INK_2 = '#56625A'

# The H: Newsreader at its largest optical size, a touch heavier than the headings so it holds up small.
H_AXES = {'wght': 480, 'opsz': 72}
# The wordmark: Newsreader as the page sets its headings.
WORD_AXES = {'wght': 400, 'opsz': 48}
H_HEIGHT = .58  # the H's height, as a share of the tile
TILE_RADIUS = .225


def instance(axes: dict) -> TTFont:
    return instantiateVariableFont(TTFont(NEWSREADER), axes)


def glyph_path(font: TTFont, char: str, size: float, x: float, baseline: float) -> tuple[str, float]:
    """One glyph's outline as SVG path data, set at `size` from (x, baseline); returns the path and its advance."""
    glyphs = font.getGlyphSet()
    name = font.getBestCmap()[ord(char)]
    scale = size / font['head'].unitsPerEm
    pen = SVGPathPen(glyphs, ntos=lambda n: f'{n:.2f}')
    glyphs[name].draw(TransformPen(pen, (scale, 0, 0, -scale, x, baseline)))
    return pen.getCommands(), glyphs[name].width * scale


def word_path(font: TTFont, size: float, x: float, baseline: float, tracking: float = -.01) -> tuple[str, float]:
    """'Hooli' as one path, slightly tightened as the headings are; returns the path and its advance."""
    paths, cursor = [], x
    for char in 'Hooli':
        path, advance = glyph_path(font, char, size, cursor, baseline)
        paths.append(path)
        cursor += advance + tracking * size
    return ''.join(paths), cursor - x - tracking * size


# --- The icon: the H centred on a sage tile, on a 100-unit grid ---------------------------------------------------

def h_on_grid(font: TTFont, height: float = H_HEIGHT * 100) -> str:
    """The H's path, its ink centred in a 100x100 box and `height` tall."""
    glyphs = font.getGlyphSet()
    bounds = BoundsPen(glyphs)
    glyphs[font.getBestCmap()[ord('H')]].draw(bounds)
    x0, y0, x1, y1 = bounds.bounds
    size = height / (y1 - y0) * font['head'].unitsPerEm
    k = size / font['head'].unitsPerEm
    path, _ = glyph_path(font, 'H', size, 50 - (x0 + x1) / 2 * k, 50 + height / 2 + y0 * k)
    return path


def svg_icon(h: str) -> str:
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            f'<rect width="100" height="100" rx="{TILE_RADIUS * 100:g}" fill="{SAGE}"/><path d="{h}" fill="{CREAM}"/></svg>\n')


def h_font(size: float) -> ImageFont.FreeTypeFont:
    face = ImageFont.truetype(str(NEWSREADER), round(size))
    face.set_variation_by_axes([H_AXES['wght'], H_AXES['opsz']])
    return face


def draw_h(draw: ImageDraw.ImageDraw, cx: float, cy: float, height: float, colour: str):
    """The H with its ink centred on (cx, cy), `height` tall."""
    x0, y0, x1, y1 = h_font(1000).getbbox('H', anchor='ls')
    face = h_font(1000 * height / (y1 - y0))
    x0, y0, x1, y1 = face.getbbox('H', anchor='ls')
    draw.text((cx - (x0 + x1) / 2, cy - (y0 + y1) / 2), 'H', font=face, fill=colour, anchor='ls')


def draw_tile(draw: ImageDraw.ImageDraw, x: float, y: float, size: float, rounded: bool = True):
    draw.rounded_rectangle((x, y, x + size - 1, y + size - 1), radius=round(size * TILE_RADIUS) if rounded else 0, fill=SAGE)
    draw_h(draw, x + size / 2, y + size / 2, size * H_HEIGHT, CREAM)


def icon(size: int, rounded: bool = True) -> Image.Image:
    """iOS rounds its own corners, so the apple-touch copy stays square."""
    big = size * SCALE
    image = Image.new('RGBA', (big, big), (0, 0, 0, 0))
    draw_tile(ImageDraw.Draw(image), 0, 0, big, rounded)
    return image.resize((size, size), Image.LANCZOS)


# --- The logo: the icon beside the wordmark -----------------------------------------------------------------------

def svg_logo(h: str, font: TTFont, word: str) -> str:
    """The tile half again as tall as the wordmark's capitals, centred on them."""
    size, pad = 100, 8
    cap = font['OS/2'].sCapHeight / font['head'].unitsPerEm * size
    tile = cap * 1.5
    baseline = pad + tile / 2 + cap / 2
    word_x = pad + tile + size * .2
    path, advance = word_path(font, size, word_x, baseline)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {word_x + advance + pad:.0f} {tile + pad * 2:.0f}">'
            f'<g transform="translate({pad} {pad}) scale({tile / 100:.4f})"><rect width="100" height="100" rx="{TILE_RADIUS * 100:g}" fill="{SAGE}"/>'
            f'<path d="{h}" fill="{CREAM}"/></g><path d="{path}" fill="{word}"/></svg>\n')


# --- The page's inline icon ---------------------------------------------------------------------------------------

def blade_icon(h: str) -> str:
    return ('{{-- The Hooli icon: a big serif H on a sage tile. Drawn by scripts/pictures.py; change it there. --}}\n'
            '<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false" class="{{ $class ?? \'\' }}">'
            f'<rect width="100" height="100" rx="{TILE_RADIUS * 100:g}" class="fill-sage"/><path d="{h}" class="fill-cream"/></svg>\n')


# --- The share picture --------------------------------------------------------------------------------------------

def text_font(size: int) -> ImageFont.FreeTypeFont:
    face = ImageFont.truetype(str(FIGTREE), size * SCALE)
    face.set_variation_by_axes([400])
    return face


def heading_font(size: int) -> ImageFont.FreeTypeFont:
    face = ImageFont.truetype(str(NEWSREADER), size * SCALE)
    face.set_variation_by_axes([WORD_AXES['wght'], WORD_AXES['opsz']])
    return face


# The share picture's words, in each of the site's languages: the headline in three lines, then two lines of text.
# English is og.jpg; the others og-{locale}.jpg, as the page's layout expects.
SHARE_WORDS = {
    'en': (['Care for your', 'hormones, one', 'scan at a time.'],
           ['Scan everyday products and medicines.', 'Calm, evidence-based answers. Join the waitlist.']),
    'fr': (['Prenez soin de vos', 'hormones, un scan', 'à la fois.'],
           ['Scannez produits du quotidien et médicaments.', 'Des réponses calmes et sourcées. Liste d’attente ouverte.']),
    'et': (['Hooli oma', 'hormoonidest –', 'skaneeri ja saa aru.'],
           ['Skaneeri igapäevaseid tooteid ja ravimeid.', 'Rahulikud, tõenduspõhised vastused. Liitu ootenimekirjaga.']),
}


def share_picture(path: pathlib.Path, headline_lines: list[str], text_lines: list[str]):
    s = SCALE
    image = Image.new('RGB', (1200 * s, 630 * s), MIST)
    draw = ImageDraw.Draw(image)

    # The H, very large and faint, running off the right edge: the picture's quiet side.
    draw_h(draw, 1110 * s, 330 * s, 470 * s, '#CCD8C6')

    draw_tile(draw, 72 * s, 62 * s, 52 * s)
    draw.text((140 * s, 104 * s), 'Hooli', font=heading_font(46), fill=INK, anchor='ls')

    headline = heading_font(72)
    for i, line in enumerate(headline_lines):
        draw.text((72 * s, (264 + i * 80) * s), line, font=headline, fill=INK, anchor='ls')
    for i, line in enumerate(text_lines):
        draw.text((72 * s, (520 + i * 40) * s), line, font=text_font(28), fill=INK_2, anchor='ls')

    path.parent.mkdir(parents=True, exist_ok=True)
    image.resize((1200, 630), Image.LANCZOS).save(path, quality=88, optimize=True, progressive=True)


def main():
    h = h_on_grid(instance(H_AXES))
    word_font = instance(WORD_AXES)

    (ROOT / 'resources/views/partials/icon-tile.blade.php').write_text(blade_icon(h))

    (PUBLIC / 'images').mkdir(parents=True, exist_ok=True)
    (PUBLIC / 'favicon.svg').write_text(svg_icon(h))
    icon(48).save(PUBLIC / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
    icon(180, rounded=False).save(PUBLIC / 'apple-touch-icon.png')
    for size in (192, 512):
        icon(size).save(PUBLIC / f'images/icon-{size}.png')

    for locale, (headline_lines, text_lines) in SHARE_WORDS.items():
        share_picture(PUBLIC / ('images/og.jpg' if locale == 'en' else f'images/og-{locale}.jpg'), headline_lines, text_lines)

    LOGO.mkdir(parents=True, exist_ok=True)
    (LOGO / 'hooli-icon.svg').write_text(svg_icon(h))
    icon(1024).save(LOGO / 'hooli-icon-1024.png')
    icon(1024, rounded=False).save(LOGO / 'hooli-icon-1024-square.png')
    (LOGO / 'hooli-h.svg').write_text(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="18 18 64 64"><path d="{h}" fill="{SAGE_DEEP}"/></svg>\n')
    (LOGO / 'hooli-logo.svg').write_text(svg_logo(h, word_font, INK))
    (LOGO / 'hooli-logo-on-dark.svg').write_text(svg_logo(h, word_font, CREAM))
    print('Wrote the icon, favicons, share picture and logo files')


if __name__ == '__main__':
    main()
