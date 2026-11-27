#!/usr/bin/env python3
"""Baut die interaktive Vorschau (eine Seite + media/) aus prototype.html.

  python3 build-vorschau.py --media <Ordner mit den Original-Medien von planigames.com/media>
                            --fonts <Ordner mit clash-500.woff2 / clash-600.woff2 (Clash Display, fontshare.com)>
                            --out   <Zielordner>

Ergebnis: <out>/planigames-probedesign.html ohne äußeres HTML-Gerüst (das liefert die Vorschau),
Clash Display eingebettet (die Vorschau lädt extern nur Google Fonts), Bilder verkleinert als WebP in
<out>/media/. Braucht Pillow.
Der Prototyp selbst bleibt unverändert (lädt Bilder weiter von planigames.com).
"""
import argparse, base64, json, os, re
from PIL import Image

ap = argparse.ArgumentParser()
ap.add_argument('--media', required=True); ap.add_argument('--fonts'); ap.add_argument('--out', required=True)
a = ap.parse_args()
SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'prototype.html')
MED, OUT = a.media, a.out
# Originalpfad → (neuer Name, max. Breite, Qualität) ; None = SVG (nur DOCTYPE entfernen)
IMG = {
  '/media/first-test-00-19-00-free-3840x2160-3.png': ('frontliners-cover.webp', 1920, 78),
  '/media/3e1f59ea-b160-46fb-b3eb-6531a9654e26.jpg': ('fok-cover.webp', 1920, 78),
  '/media/website-3.png': ('ww-cover.webp', 1920, 78),
  '/media/frontliners-logo-weiss.svg': ('frontliners-logo.svg', None, None),
  '/media/fok-logo-tempv01.png': ('fok-logo.webp', 1000, 88),
  '/media/wobblywizards-logo.png': ('ww-logo.webp', 960, 88),
  '/media/logo-6.png': ('studio-logo.webp', 256, 90),
  '/media/1000000825.jpg': ('team-dominic.webp', 760, 80),
  '/media/img-8077.jpeg': ('team-niklas.webp', 760, 80),
  '/media/vincent.png': ('team-vincent.webp', 760, 80),
  '/media/triumph-im-wuestenaussenposten.png': ('devlog-2.webp', 1920, 78),
  '/media/chatgpt-bild-4-okt-2026-04-38-41.png': ('devlog-1.webp', 1400, 78),
  '/media/20260525175316-1.jpg': ('ww-1.webp', 1400, 78),
  '/media/20260525180256-1.jpg': ('ww-2.webp', 1000, 78),
  '/media/20260525182444-1.jpg': ('ww-3.webp', 1000, 78),
  '/media/20260525174744-1.jpg': ('ww-4.webp', 1000, 78),
  '/media/maskottchen.png': ('maskottchen.webp', 520, 88),
}
s = open(SRC, encoding='utf-8').read()
used = set(re.findall(r'/media/[A-Za-z0-9._\-]+', s))
missing = sorted(used - set(IMG))
assert not missing, f'Bilder ohne Eintrag in IMG: {missing}'

os.makedirs(OUT + '/media', exist_ok=True)
mp, total = {}, 0
for src in sorted(used):
    name, maxw, q = IMG[src]
    sp, dp = MED + src[len('/media'):], OUT + '/media/' + name
    if maxw is None:
        t = re.sub(r'<!DOCTYPE[^>]*>\s*', '', open(sp, encoding='utf-8').read(), flags=re.I)
        assert not re.search(r'<!(DOCTYPE|ENTITY)', t, re.I)
        open(dp, 'w', encoding='utf-8').write(t)
    else:
        im = Image.open(sp)
        im = im.convert('RGBA') if im.mode in ('RGBA', 'LA', 'P') else im.convert('RGB')
        if im.width > maxw: im = im.resize((maxw, round(im.height * maxw / im.width)), Image.LANCZOS)
        im.save(dp, 'WEBP', quality=q, method=6)
    mp[src] = 'media/' + name
    total += os.path.getsize(dp)
    print(f'{name:24s} {os.path.getsize(dp)//1024:5d} KB')
print('Bilder gesamt', total // 1024, 'KB')

# äußeres Gerüst entfernen (liefert die Vorschau selbst); <header> usw. bleiben unberührt
for pat in (r'<!doctype[^>]*>\s*', r'<html\b[^>]*>\s*', r'</html>\s*', r'<head>\s*', r'</head>\s*', r'<body\b[^>]*>\s*', r'</body>\s*',
            r'<meta charset="[^"]*">\s*', r'<meta name="viewport"[^>]*>\s*'):
    s = re.sub(pat, '', s, flags=re.I)
bad = re.findall(r'<\s*/?\s*(?:!doctype|html|head|body)(?=[\s>])', s, re.I)
assert not bad, bad
# Clash Display (fontshare) einbetten – die Vorschau darf extern nur Google Fonts laden
fs = re.search(r'<link href="https://api\.fontshare\.com/v2/css\?f\[\]=clash-display@([0-9,]+)[^"]*" rel="stylesheet">\s*', s)
if fs:
    assert a.fonts, 'Clash Display wird genutzt: --fonts <Ordner> angeben'
    faces = ''.join("@font-face{font-family:'Clash Display';font-style:normal;font-weight:%s;font-display:swap;src:url(data:font/woff2;base64,%s) format('woff2')}\n" % (w, base64.b64encode(open(os.path.join(a.fonts, f'clash-{w}.woff2'), 'rb').read()).decode()) for w in fs.group(1).split(','))
    s = s.replace(fs.group(0), '').replace('<style>\n', '<style>\n' + faces, 1)
links = re.findall(r'<link[^>]+rel="stylesheet"[^>]*>', s)
assert all('fonts.googleapis.com' in l for l in links), links
assert 'const MEDIA_MAP = null;' in s
s = s.replace('const MEDIA_MAP = null;', 'const MEDIA_MAP = ' + json.dumps(mp, ensure_ascii=False) + ';', 1)
s = s.replace('<script>\n', '<script>\ndocument.documentElement.lang = "de";\n', 1)
open(OUT + '/planigames-probedesign.html', 'w', encoding='utf-8').write(s)
print('Seite', len(s.encode()) // 1024, 'KB')
json.dump(mp, open(OUT + '/media-map.json', 'w'), indent=1)
