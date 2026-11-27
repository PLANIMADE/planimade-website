#!/usr/bin/env python3
"""Baut die interaktive Vorschau (eine Seite + media/) aus prototype.html.

  python3 build-vorschau.py --media <Ordner mit den Original-Medien von planigames.com/media>
                            --fonts <Ordner mit clash-600.woff2 und clash-700.woff2>
                            --out   <Zielordner>

Ergebnis: <out>/planigames-probedesign.html ohne äußeres HTML-Gerüst (das liefert die Vorschau),
Schrift Clash Display eingebettet, Bilder verkleinert als WebP in <out>/media/.
Braucht Pillow. Der Prototyp selbst bleibt unverändert (lädt Bilder weiter von planigames.com).
"""
import argparse, base64, json, os, re
from PIL import Image
ap = argparse.ArgumentParser()
ap.add_argument('--media', required=True); ap.add_argument('--fonts', required=True); ap.add_argument('--out', required=True)
a = ap.parse_args()
SRC = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'prototype.html')
MED, FONTS, OUT = a.media, a.fonts, a.out
# Originalpfad → (neuer Name, max. Breite, Qualität) ; None = unverändert kopieren
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
  '/media/triumph-im-wuestenaussenposten.png': ('devlog-2.webp', 1400, 78),
  '/media/chatgpt-bild-4-okt-2026-04-38-41.png': ('devlog-1.webp', 1400, 78),
  '/media/20260525175316-1.jpg': ('ww-1.webp', 1400, 78),
  '/media/20260525180256-1.jpg': ('ww-2.webp', 1000, 78),
  '/media/20260525182444-1.jpg': ('ww-3.webp', 1000, 78),
  '/media/20260525174744-1.jpg': ('ww-4.webp', 1000, 78),
}
os.makedirs(OUT + '/media', exist_ok=True)
mp = {}
total = 0
for src, (name, maxw, q) in IMG.items():
    sp = MED + src[len('/media'):]
    dp = OUT + '/media/' + name
    if maxw is None:
        t = open(sp, encoding='utf-8').read()
        t = re.sub(r'<!DOCTYPE[^>]*>\s*', '', t, flags=re.I)
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

s = open(SRC, encoding='utf-8').read()
# äußeres Gerüst entfernen (liefert die Vorschau selbst)
s = s.replace('<!doctype html>\n', '').replace('<html lang="de">\n', '').replace('<head>\n', '')
s = s.replace('<meta charset="utf-8">\n', '').replace('<meta name="viewport" content="width=device-width,initial-scale=1">\n', '')
s = s.replace('</head>\n<body>\n', '').replace('</body>\n</html>\n', '')
# Clash Display einbetten (nur Google Fonts darf extern laden)
fs = '<link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">\n'
assert fs in s
s = s.replace(fs, '')
faces = ''
for w in (600, 700):
    b64 = base64.b64encode(open(f'{FONTS}/clash-{w}.woff2', 'rb').read()).decode()
    faces += f"@font-face{{font-family:'Clash Display';font-style:normal;font-weight:{w};font-display:swap;src:url(data:font/woff2;base64,{b64}) format('woff2')}}\n"
s = s.replace('<style>\n', '<style>\n' + faces, 1)
# Bilder neben der Seite
assert 'const MEDIA_MAP = null;' in s
s = s.replace('const MEDIA_MAP = null;', 'const MEDIA_MAP = ' + json.dumps(mp, ensure_ascii=False) + ';', 1)
s = s.replace('<script>\n', '<script>\ndocument.documentElement.lang = "de";\n', 1)
bad = re.findall(r'<\s*/?\s*(?:!doctype|html|head|body)(?=[\s>])', s, re.I)
assert not bad, bad
assert 'fontshare' not in s
open(OUT + '/planigames-probedesign.html', 'w', encoding='utf-8').write(s)
print('Seite', len(s.encode()) // 1024, 'KB')
json.dump(mp, open(OUT + '/media-map.json', 'w'), indent=1)
