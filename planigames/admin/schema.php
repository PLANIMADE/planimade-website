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

// Spiele-Auswahl (Slug => Titel) dynamisch aus games.json, damit das
// Zuordnen von Patchnotes per Dropdown funktioniert statt freiem Slug-Tippen.
$pg_game_options = ['' => '— Allgemeine News (kein Spiel) —'];
$pg_games_file = __DIR__ . '/../data/games.json';
if (is_file($pg_games_file)) {
  $pg_games = json_decode((string) file_get_contents($pg_games_file), true);
  foreach (($pg_games['games'] ?? []) as $pg_g) {
    if (!empty($pg_g['slug'])) $pg_game_options[$pg_g['slug']] = $pg_g['title'] ?? $pg_g['slug'];
  }
}

return [

  // ================= STUDIO / STARTSEITE =================
  'studio' => [
    'label' => 'Studio & Startseite',
    'icon'  => '★',
    'file'  => __DIR__ . '/../data/studio.json',
    'fields' => [
      ['name'=>'name','label'=>'Studio-Name','widget'=>'string'],
      ['name'=>'logo','label'=>'Logo (optional)','widget'=>'image','hint'=>'Transparentes PNG/SVG, Höhe ~80 px (z. B. 240×80). Ersetzt den Schriftzug in Kopf-/Fußzeile. Leer = Wortmarke.'],
      ['name'=>'favicon','label'=>'Favicon (Browser-Tab-Icon)','widget'=>'image','hint'=>'Quadratisch, z. B. 64×64 oder 192×192 px (PNG/SVG/ICO). Separat vom Logo.'],
      ['name'=>'tagline','label'=>'Untertitel / Tagline','widget'=>'string'],
      ['name'=>'founded','label'=>'Gegründet (Jahr)','widget'=>'string'],
      ['name'=>'heroLine1','label'=>'Hero – Zeile 1','widget'=>'string','hint'=>'Große Überschrift, erste Zeile.'],
      ['name'=>'heroLine2','label'=>'Hero – Zeile 2 (farbig)','widget'=>'string','hint'=>'Zweite Zeile, wird im Gold/Orange-Verlauf dargestellt.'],
      ['name'=>'intro','label'=>'Hero – Einleitungstext','widget'=>'text'],
      ['name'=>'marquee','label'=>'Laufband-Phrasen','widget'=>'text','hint'=>'Das durchlaufende Band unter dem Hero. Eine Phrase pro Zeile. Leer = Standard. Beispiel:'."\n".'Original Worlds'."\n".'Handcrafted Magic'."\n".'Indie & Proud'],
      ['name'=>'heroCountdown','label'=>'Release-Countdown (Hero)','widget'=>'object','hint'=>'Zeigt einen Live-Countdown im Hero – z. B. bis Demo oder Release.','fields'=>[
        ['name'=>'enabled','label'=>'Countdown anzeigen','widget'=>'boolean','default'=>false],
        ['name'=>'label','label'=>'Beschriftung','widget'=>'string','default'=>'Bis zum Release','hint'=>'z. B. „Bis zur Demo" oder „Release in".'],
        ['name'=>'date','label'=>'Zieldatum & Uhrzeit','widget'=>'datetime','hint'=>'Wann läuft der Countdown ab?'],
        ['name'=>'doneText','label'=>'Text nach Ablauf','widget'=>'string','default'=>'Jetzt verfügbar! 🎉'],
      ]],
      ['name'=>'heroBackground','label'=>'Hero – Hintergrundbild (optional)','widget'=>'image','hint'=>'Querformat, ca. 2000×1200 px. Liegt hinter der Startseiten-Überschrift.'],
      ['name'=>'heroVideo','label'=>'Hero – Hintergrund-Video (optional, mp4)','widget'=>'file','hint'=>'Läuft automatisch, stumm, als Loop. Überschreibt das Bild.'],
      ['name'=>'aboutTitle','label'=>'Über uns – Überschrift','widget'=>'string'],
      ['name'=>'aboutBody','label'=>'Über uns – Text','widget'=>'markdown'],
      ['name'=>'pillars','label'=>'Werte / Stärken (Kacheln)','widget'=>'list','summary'=>'title','fields'=>[
        ['name'=>'icon','label'=>'Icon / Emoji','widget'=>'string','hint'=>'z. B. 🪄'],
        ['name'=>'title','label'=>'Titel','widget'=>'string'],
        ['name'=>'text','label'=>'Text','widget'=>'text'],
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
      ['name'=>'background','label'=>'Hintergrund & Atmosphäre','widget'=>'object','hint'=>'Steuert die Animation im Seitenhintergrund.','fields'=>[
        ['name'=>'effect','label'=>'Effekt','widget'=>'select','default'=>'particles','options'=>[
          'particles'=>'Partikel + Glow (Standard)','particles_only'=>'Nur Partikel','glow'=>'Nur Glow-Lichter','off'=>'Aus / minimal',
        ]],
        ['name'=>'density','label'=>'Partikel-Menge','widget'=>'select','default'=>'med','options'=>[
          'low'=>'Wenig','med'=>'Mittel','high'=>'Viel',
        ]],
        ['name'=>'speed','label'=>'Tempo','widget'=>'select','default'=>'normal','options'=>[
          'calm'=>'Ruhig','normal'=>'Normal','lively'=>'Lebhaft',
        ]],
        ['name'=>'color','label'=>'Partikel-Farbe','widget'=>'color','default'=>'#ff7d1a','hint'=>'Standard: Orange. Für eine andere Stimmung anpassbar.'],
      ]],
      ['name'=>'community','label'=>'Community-Banner (Discord)','widget'=>'object','hint'=>'Auffälliger Aufruf auf der Startseite, der zur Community führt.','fields'=>[
        ['name'=>'enabled','label'=>'Banner anzeigen','widget'=>'boolean','default'=>true],
        ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Komm in unsere Community'],
        ['name'=>'text','label'=>'Text','widget'=>'text','default'=>'Sei beim Chaos dabei: Devlogs aus erster Hand, Playtests, Abstimmungen und ein Haufen wackeliger Magier warten auf dich.'],
        ['name'=>'buttonLabel','label'=>'Button-Text','widget'=>'string','default'=>'Discord beitreten'],
        ['name'=>'url','label'=>'Discord-Einladungslink','widget'=>'string','hint'=>'z. B. https://discord.gg/…'],
      ]],
      ['name'=>'presskit','label'=>'Press Kit','widget'=>'object','hint'=>'Eigene Seite (presse.html) für Presse & Creator. Logos, Fakten, Downloads.','fields'=>[
        ['name'=>'enabled','label'=>'Press Kit aktiv','widget'=>'boolean','default'=>true],
        ['name'=>'intro','label'=>'Einleitung','widget'=>'markdown','hint'=>'Kurzvorstellung für Presse & Content-Creator.'],
        ['name'=>'contactEmail','label'=>'Presse-Kontakt (E-Mail)','widget'=>'string','hint'=>'Leer = Studio-Kontakt-E-Mail.'],
        ['name'=>'facts','label'=>'Fakten (Fact Sheet)','widget'=>'list','summary'=>'label','fields'=>[
          ['name'=>'label','label'=>'Bezeichnung','widget'=>'string','hint'=>'z. B. Studio, Standort, Release'],
          ['name'=>'value','label'=>'Wert','widget'=>'string'],
        ]],
        ['name'=>'downloads','label'=>'Download-Pakete','widget'=>'list','summary'=>'label','fields'=>[
          ['name'=>'label','label'=>'Titel','widget'=>'string','hint'=>'z. B. Logo-Paket, Screenshots'],
          ['name'=>'note','label'=>'Beschreibung','widget'=>'string'],
          ['name'=>'file','label'=>'Datei (ZIP/Bild)','widget'=>'file'],
        ]],
      ]],
      ['name'=>'cookie','label'=>'Cookie-Hinweis','widget'=>'object','hint'=>'DSGVO-Banner. Blendet eingebettete YouTube-Trailer bis zur Zustimmung aus.','fields'=>[
        ['name'=>'enabled','label'=>'Cookie-Banner anzeigen','widget'=>'boolean','default'=>true],
        ['name'=>'message','label'=>'Hinweistext','widget'=>'text','default'=>'Wir nutzen nur, was die Seite zum Laufen braucht. Für eingebettete YouTube-Trailer brauchen wir deine Zustimmung – diese laden externe Inhalte von Google.'],
        ['name'=>'acceptLabel','label'=>'Button „Akzeptieren"','widget'=>'string','default'=>'Alle akzeptieren'],
        ['name'=>'declineLabel','label'=>'Button „Ablehnen"','widget'=>'string','default'=>'Nur Notwendige'],
      ]],
      ['name'=>'contact','label'=>'Kontaktformular','widget'=>'object','hint'=>'Eigene Seite (kontakt.html). Nachrichten landen im Admin unter „Kontakt" und werden dir per E-Mail geschickt.','fields'=>[
        ['name'=>'enabled','label'=>'Kontaktformular aktiv','widget'=>'boolean','default'=>true],
        ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Sag Hallo 👋'],
        ['name'=>'intro','label'=>'Einleitungstext','widget'=>'text','default'=>'Fragen, Feedback, Presse oder einfach nur Hallo sagen? Schreib uns – wir melden uns so schnell wie möglich.'],
        ['name'=>'buttonLabel','label'=>'Button-Text','widget'=>'string','default'=>'Nachricht senden'],
        ['name'=>'successMessage','label'=>'Danke-Nachricht','widget'=>'string','default'=>'Danke für deine Nachricht! Wir melden uns bald. 🧡'],
        ['name'=>'notifyEmail','label'=>'Benachrichtigung an (E-Mail)','widget'=>'string','hint'=>'Wohin neue Anfragen geschickt werden. Leer = Studio-Kontakt-E-Mail.'],
      ]],
      ['name'=>'footerNote','label'=>'Footer – Kurzbeschreibung','widget'=>'text'],
      ['name'=>'email','label'=>'Kontakt-E-Mail','widget'=>'string'],
      ['name'=>'socials','label'=>'Social Links','widget'=>'list','summary'=>'label','fields'=>[
        ['name'=>'label','label'=>'Label','widget'=>'string','hint'=>'z. B. Discord'],
        ['name'=>'url','label'=>'URL','widget'=>'string'],
      ]],
    ],
  ],

  // ================= TEAM =================
  'team' => [
    'label' => 'Team',
    'icon'  => '👥',
    'file'  => __DIR__ . '/../data/team.json',
    'fields' => [
      ['name'=>'members','label'=>'Teammitglieder','widget'=>'list','summary'=>'name','fields'=>[
        ['name'=>'name','label'=>'Name','widget'=>'string'],
        ['name'=>'role','label'=>'Rolle','widget'=>'string','hint'=>'z. B. Founder · Code & Design'],
        ['name'=>'photo','label'=>'Foto','widget'=>'image','hint'=>'Quadratisch, ca. 400×400 px (JPG/PNG). Ohne Foto wird das Emoji gezeigt.'],
        ['name'=>'photoAlt','label'=>'Foto – Alt-Text','widget'=>'string','hint'=>'Bildbeschreibung für SEO & Screenreader. Leer = Name.'],
        ['name'=>'emoji','label'=>'Emoji (falls kein Foto)','widget'=>'string','default'=>'🧙'],
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
        ['name'=>'slug','label'=>'Slug (URL)','widget'=>'string','slug'=>true,'hint'=>'Kleinbuchstaben & Bindestriche. Leer = automatisch aus Titel. Adresse: game.php?slug=…'],
        ['name'=>'tagline','label'=>'Kurzbeschreibung / Tagline','widget'=>'text'],
        ['name'=>'status','label'=>'Status','widget'=>'select','default'=>'development','options'=>[
          'development'=>'In Entwicklung','announced'=>'Angekündigt','demo'=>'Demo verfügbar','early'=>'Early Access','released'=>'Veröffentlicht',
        ]],
        ['name'=>'accent','label'=>'Akzentfarbe','widget'=>'color','default'=>'#ff7d1a','hint'=>'Hauptfarbe der Game-Welt (HEX).'],
        ['name'=>'accent2','label'=>'Zweitfarbe','widget'=>'color','default'=>'#e6a015','hint'=>'Zweite HEX-Farbe für Verläufe.'],
        ['name'=>'featured','label'=>'Hervorgehoben (Startseite)','widget'=>'boolean'],
        ['name'=>'cover','label'=>'Cover-Bild','widget'=>'image','hint'=>'Querformat 16:10, ca. 1600×1000 px (JPG/WebP). Für Karten & Startseite.'],
        ['name'=>'coverAlt','label'=>'Cover – Alt-Text','widget'=>'string','hint'=>'Bildbeschreibung für SEO & Screenreader. Leer = Spieltitel.'],
        ['name'=>'logo','label'=>'Logo (transparent)','widget'=>'image','hint'=>'Transparentes PNG, ca. 800×400 px – wird im Hero statt des Titels gezeigt.'],
        ['name'=>'logoScale','label'=>'Logo-Größe (%)','widget'=>'number','default'=>100,'hint'=>'100 = Standard. Wirkt auf Hero & Startseiten-Card. z. B. 60 (kleiner) oder 160 (größer).'],
        ['name'=>'wishlistUrl','label'=>'Wishlist / Store-Link','widget'=>'string','hint'=>'z. B. Steam-Seite.'],
        ['name'=>'blocks','label'=>'Seiten-Blöcke (Website-Builder)','widget'=>'blocks','types'=>[
          'hero'=>['label'=>'Hero (Kopfbereich)','fields'=>[
            ['name'=>'tagline','label'=>'Tagline (überschreibt Standard)','widget'=>'text'],
            ['name'=>'background','label'=>'Hintergrundbild','widget'=>'image','hint'=>'Querformat, ca. 2000×1200 px (JPG/WebP).'],
            ['name'=>'video','label'=>'Hintergrund-Video (mp4)','widget'=>'file'],
            ['name'=>'poster','label'=>'Video-Vorschaubild','widget'=>'image','hint'=>'Querformat 16:9, ca. 1600×900 px.'],
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
            ['name'=>'images','label'=>'Bilder','widget'=>'list','summary'=>'alt','hint'=>'Screenshots im Querformat 16:9, ca. 1920×1080 px.','fields'=>[
              ['name'=>'image','label'=>'Bild','widget'=>'image'],
              ['name'=>'alt','label'=>'Alt-Text (Bildbeschreibung)','widget'=>'string','hint'=>'Kurze Beschreibung für SEO & Screenreader, z. B. „Zwei Magier kämpfen in einem Lava-Dungeon".'],
            ]],
          ]],
          'trailer'=>['label'=>'Trailer / Video','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'youtube','label'=>'YouTube-Link','widget'=>'string','hint'=>'Voller YouTube-Link. Alternativ Datei unten.'],
            ['name'=>'file','label'=>'Video-Datei (mp4)','widget'=>'file'],
            ['name'=>'poster','label'=>'Vorschaubild','widget'=>'image','hint'=>'Querformat 16:9, ca. 1600×900 px.'],
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
          'faq'=>['label'=>'FAQ (Fragen & Antworten)','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'items','label'=>'Fragen','widget'=>'list','summary'=>'question','fields'=>[
              ['name'=>'question','label'=>'Frage','widget'=>'string'],
              ['name'=>'answer','label'=>'Antwort','widget'=>'markdown'],
            ]],
          ]],
          'countdown'=>['label'=>'Release-Countdown','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','hint'=>'z. B. Release in'],
            ['name'=>'label','label'=>'Unterzeile','widget'=>'string'],
            ['name'=>'date','label'=>'Zieldatum/-zeit','widget'=>'datetime','hint'=>'Zeitpunkt, auf den heruntergezählt wird.'],
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
        ['name'=>'draft','label'=>'Entwurf (nicht öffentlich)','widget'=>'boolean','default'=>false,'hint'=>'An = nur du siehst ihn, nicht auf der Website.'],
        ['name'=>'publishAt','label'=>'Geplant ab (optional)','widget'=>'datetime','hint'=>'Erscheint erst ab diesem Zeitpunkt. Leer = sofort (sofern kein Entwurf).'],
        ['name'=>'notify','label'=>'Beim Veröffentlichen an Newsletter senden','widget'=>'boolean','default'=>false,'hint'=>'Schickt den Beitrag einmalig an alle Abonnenten, sobald er gespeichert & öffentlich ist.'],
        ['name'=>'game','label'=>'Spiel','widget'=>'select','options'=>$pg_game_options,'hint'=>'Ordnet den Beitrag einem Spiel zu – erscheint dann unten auf dessen Spielseite.'],
        ['name'=>'version','label'=>'Version','widget'=>'string','hint'=>'z. B. 0.3.0 – erscheint als Badge.'],
        ['name'=>'tags','label'=>'Tags','widget'=>'list','field'=>['name'=>'tag','label'=>'Tag','widget'=>'string']],
        ['name'=>'cover','label'=>'Titelbild','widget'=>'image','hint'=>'Querformat 16:9, ca. 1600×900 px.'],
        ['name'=>'coverAlt','label'=>'Titelbild – Alt-Text','widget'=>'string','hint'=>'Bildbeschreibung für SEO & Screenreader. Leer = Titel.'],
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
