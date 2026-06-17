<?php
/**
 * PLANIGAMES — Inhalts-Schema (Single Source of Truth fürs PHP-Admin).
 *
 * Jede "collection" ist eine JSON-Datei mit einer Liste von Feldern.
 * Aus diesem Schema baut lib.php automatisch die Formulare und prüft
 * beim Speichern die Daten. Neue Felder/Block-Typen NUR hier ergänzen
 * (und im Frontend-Renderer assets/app.js, falls es ein neuer Block ist).
 *
 * Widgets: string, text, markdown, number, boolean, select, image, file,
 *          date, list (mit "fields" ODER "field"), blocks (mit "types").
 */

return [

  // ================= STUDIO / STARTSEITE =================
  'studio' => [
    'label' => 'Studio & Startseite',
    'icon'  => '★',
    'file'  => __DIR__ . '/../data/studio.json',
    'fields' => [
      ['name'=>'name','label'=>'Studio-Name','widget'=>'string'],
      ['name'=>'logo','label'=>'Logo (optional)','widget'=>'image','hint'=>'Transparentes PNG/SVG. Ersetzt den Schriftzug in Kopf-/Fußzeile. Leer = Wortmarke.'],
      ['name'=>'tagline','label'=>'Untertitel / Tagline','widget'=>'string'],
      ['name'=>'founded','label'=>'Gegründet (Jahr)','widget'=>'string'],
      ['name'=>'heroLine1','label'=>'Hero – Zeile 1','widget'=>'string','hint'=>'Große Überschrift, erste Zeile.'],
      ['name'=>'heroLine2','label'=>'Hero – Zeile 2 (farbig)','widget'=>'string','hint'=>'Zweite Zeile, wird im Gold/Orange-Verlauf dargestellt.'],
      ['name'=>'intro','label'=>'Hero – Einleitungstext','widget'=>'text'],
      ['name'=>'aboutTitle','label'=>'Über uns – Überschrift','widget'=>'string'],
      ['name'=>'aboutBody','label'=>'Über uns – Text','widget'=>'markdown'],
      ['name'=>'pillars','label'=>'Werte / Stärken (Kacheln)','widget'=>'list','summary'=>'title','fields'=>[
        ['name'=>'icon','label'=>'Icon / Emoji','widget'=>'string','hint'=>'z. B. 🪄'],
        ['name'=>'title','label'=>'Titel','widget'=>'string'],
        ['name'=>'text','label'=>'Text','widget'=>'text'],
      ]],
      ['name'=>'team','label'=>'Team','widget'=>'list','summary'=>'name','fields'=>[
        ['name'=>'name','label'=>'Name','widget'=>'string'],
        ['name'=>'role','label'=>'Rolle','widget'=>'string'],
        ['name'=>'photo','label'=>'Foto','widget'=>'image'],
        ['name'=>'emoji','label'=>'Emoji (falls kein Foto)','widget'=>'string','default'=>'🧙'],
      ]],
      ['name'=>'newsletter','label'=>'Newsletter-Anmeldung','widget'=>'object','hint'=>'Steuert das Anmeldeformular (Startseite & Footer).','fields'=>[
        ['name'=>'enabled','label'=>'Newsletter aktiv','widget'=>'boolean','default'=>true],
        ['name'=>'heading','label'=>'Überschrift (Startseite)','widget'=>'string'],
        ['name'=>'text','label'=>'Text','widget'=>'text'],
        ['name'=>'buttonLabel','label'=>'Button-Text','widget'=>'string','default'=>'Auf die Warteliste'],
        ['name'=>'placeholder','label'=>'Platzhalter im Eingabefeld','widget'=>'string','default'=>'deine@mail.de'],
        ['name'=>'successMessage','label'=>'Danke-Nachricht','widget'=>'string','default'=>'Danke! Du bist dabei. 🧡'],
        ['name'=>'notifyEmail','label'=>'Benachrichtigung an (E-Mail)','widget'=>'string','hint'=>'Bei jeder Anmeldung eine Mail hierhin. Leer = an die Kontakt-E-Mail.'],
      ]],
      ['name'=>'footerNote','label'=>'Footer – Kurzbeschreibung','widget'=>'text'],
      ['name'=>'email','label'=>'Kontakt-E-Mail','widget'=>'string'],
      ['name'=>'socials','label'=>'Social Links','widget'=>'list','summary'=>'label','fields'=>[
        ['name'=>'label','label'=>'Label','widget'=>'string','hint'=>'z. B. Discord'],
        ['name'=>'url','label'=>'URL','widget'=>'string'],
      ]],
    ],
  ],

  // ================= SPIELE =================
  'games' => [
    'label' => 'Spiele',
    'icon'  => '🎮',
    'file'  => __DIR__ . '/../data/games.json',
    'fields' => [
      ['name'=>'games','label'=>'Spiele','widget'=>'list','summary'=>'title','fields'=>[
        ['name'=>'title','label'=>'Titel','widget'=>'string'],
        ['name'=>'slug','label'=>'Slug (URL)','widget'=>'string','slug'=>true,'hint'=>'Kleinbuchstaben & Bindestriche. Leer = automatisch aus Titel. Adresse: game.html?slug=…'],
        ['name'=>'tagline','label'=>'Kurzbeschreibung / Tagline','widget'=>'text'],
        ['name'=>'status','label'=>'Status','widget'=>'select','default'=>'development','options'=>[
          'development'=>'In Entwicklung','announced'=>'Angekündigt','demo'=>'Demo verfügbar','early'=>'Early Access','released'=>'Veröffentlicht',
        ]],
        ['name'=>'accent','label'=>'Akzentfarbe','widget'=>'color','default'=>'#ff7d1a','hint'=>'Hauptfarbe der Game-Welt (HEX).'],
        ['name'=>'accent2','label'=>'Zweitfarbe','widget'=>'color','default'=>'#e6a015','hint'=>'Zweite HEX-Farbe für Verläufe.'],
        ['name'=>'featured','label'=>'Hervorgehoben (Startseite)','widget'=>'boolean'],
        ['name'=>'cover','label'=>'Cover-Bild','widget'=>'image','hint'=>'Querformat 16:10. Für Karten & Startseite.'],
        ['name'=>'logo','label'=>'Logo (transparent)','widget'=>'image','hint'=>'PNG – wird im Hero statt des Titels gezeigt.'],
        ['name'=>'wishlistUrl','label'=>'Wishlist / Store-Link','widget'=>'string','hint'=>'z. B. Steam-Seite.'],
        ['name'=>'blocks','label'=>'Seiten-Blöcke (Website-Builder)','widget'=>'blocks','types'=>[
          'hero'=>['label'=>'Hero (Kopfbereich)','fields'=>[
            ['name'=>'tagline','label'=>'Tagline (überschreibt Standard)','widget'=>'text'],
            ['name'=>'background','label'=>'Hintergrundbild','widget'=>'image'],
            ['name'=>'video','label'=>'Hintergrund-Video (mp4)','widget'=>'file'],
            ['name'=>'poster','label'=>'Video-Vorschaubild','widget'=>'image'],
            ['name'=>'ctaLabel','label'=>'Button-Text','widget'=>'string','default'=>'Auf Steam wishlisten'],
            ['name'=>'trailerUrl','label'=>'Trailer-Link (Button)','widget'=>'string'],
          ]],
          'richtext'=>['label'=>'Textabschnitt','fields'=>[
            ['name'=>'kicker','label'=>'Kicker (kleine Zeile)','widget'=>'string'],
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'body','label'=>'Text','widget'=>'markdown'],
          ]],
          'features'=>['label'=>'Feature-Kacheln','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'items','label'=>'Kacheln','widget'=>'list','summary'=>'title','fields'=>[
              ['name'=>'icon','label'=>'Icon / Emoji','widget'=>'string'],
              ['name'=>'title','label'=>'Titel','widget'=>'string'],
              ['name'=>'text','label'=>'Text','widget'=>'text'],
            ]],
          ]],
          'gallery'=>['label'=>'Bildergalerie','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'images','label'=>'Bilder','widget'=>'list','field'=>['name'=>'image','label'=>'Bild','widget'=>'image']],
          ]],
          'trailer'=>['label'=>'Trailer / Video','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'youtube','label'=>'YouTube-Link','widget'=>'string','hint'=>'Voller YouTube-Link. Alternativ Datei unten.'],
            ['name'=>'file','label'=>'Video-Datei (mp4)','widget'=>'file'],
            ['name'=>'poster','label'=>'Vorschaubild','widget'=>'image'],
          ]],
          'quotes'=>['label'=>'Zitate / Reviews','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'items','label'=>'Zitate','widget'=>'list','summary'=>'author','fields'=>[
              ['name'=>'text','label'=>'Zitat','widget'=>'text'],
              ['name'=>'author','label'=>'Autor / Quelle','widget'=>'string'],
              ['name'=>'source','label'=>'Medium / Detail','widget'=>'string'],
            ]],
          ]],
          'stats'=>['label'=>'Kennzahlen','fields'=>[
            ['name'=>'items','label'=>'Zahlen','widget'=>'list','summary'=>'value','fields'=>[
              ['name'=>'value','label'=>'Wert','widget'=>'string','hint'=>'z. B. 1–4 oder 2025'],
              ['name'=>'label','label'=>'Bezeichnung','widget'=>'string'],
            ]],
          ]],
          'roadmap'=>['label'=>'Roadmap / Zeitstrahl','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'items','label'=>'Meilensteine','widget'=>'list','summary'=>'title','fields'=>[
              ['name'=>'status','label'=>'Status','widget'=>'select','default'=>'planned','options'=>[
                'done'=>'Erledigt','active'=>'In Arbeit','planned'=>'Geplant',
              ]],
              ['name'=>'date','label'=>'Zeitpunkt','widget'=>'string','hint'=>'z. B. 2025 Q1'],
              ['name'=>'title','label'=>'Titel','widget'=>'string'],
              ['name'=>'text','label'=>'Beschreibung','widget'=>'text'],
            ]],
          ]],
          'cta'=>['label'=>'Call-to-Action (Banner)','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'text','label'=>'Text','widget'=>'text'],
            ['name'=>'label','label'=>'Button-Text','widget'=>'string','default'=>'Jetzt wishlisten'],
            ['name'=>'url','label'=>'Button-Link','widget'=>'string'],
          ]],
          'spacer'=>['label'=>'Abstand / Leerraum','fields'=>[
            ['name'=>'size','label'=>'Höhe (px)','widget'=>'number','default'=>48],
          ]],
        ]],
      ]],
    ],
  ],

  // ================= DEVLOG / PATCH NOTES =================
  'patchnotes' => [
    'label' => 'Devlog & Patch Notes',
    'icon'  => '📝',
    'file'  => __DIR__ . '/../data/patchnotes.json',
    'fields' => [
      ['name'=>'posts','label'=>'Beiträge','widget'=>'list','summary'=>'title','fields'=>[
        ['name'=>'title','label'=>'Titel','widget'=>'string'],
        ['name'=>'slug','label'=>'Slug (URL)','widget'=>'string','slug'=>true,'hint'=>'Leer = automatisch aus Titel.'],
        ['name'=>'date','label'=>'Datum','widget'=>'date'],
        ['name'=>'game','label'=>'Spiel (Slug)','widget'=>'string','hint'=>'Slug des Spiels, z. B. wobbly-wizards. Leer = allgemeine News.'],
        ['name'=>'version','label'=>'Version','widget'=>'string','hint'=>'z. B. 0.3.0 – erscheint als Badge.'],
        ['name'=>'tags','label'=>'Tags','widget'=>'list','field'=>['name'=>'tag','label'=>'Tag','widget'=>'string']],
        ['name'=>'cover','label'=>'Titelbild','widget'=>'image'],
        ['name'=>'excerpt','label'=>'Kurzfassung','widget'=>'text','hint'=>'Teaser für die Übersicht.'],
        ['name'=>'body','label'=>'Inhalt','widget'=>'markdown'],
      ]],
    ],
  ],

  // ================= RECHTLICHES =================
  'legal' => [
    'label' => 'Rechtliches',
    'icon'  => '⚖️',
    'file'  => __DIR__ . '/../data/legal.json',
    'fields' => [
      ['name'=>'impressum','label'=>'Impressum','widget'=>'markdown','hint'=>'Pflichtangaben nach § 5 TMG. Platzhalter in [ ] ersetzen. Im Zweifel rechtlich prüfen lassen.'],
      ['name'=>'datenschutz','label'=>'Datenschutzerklärung','widget'=>'markdown','hint'=>'Datenschutzhinweise (DSGVO). Platzhalter ersetzen und an genutzte Dienste anpassen.'],
    ],
  ],

];
