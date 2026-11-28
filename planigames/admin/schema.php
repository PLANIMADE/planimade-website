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

// Knopf-Ziele (Startseiten-Durchlauf, Kopfbereich der Spielseite)
$pg_btn_main = [
  'game' => 'Zum Spiel (Spielseite)', 'wishlist' => 'Steam-Wishlist (Link aus „Einstellungen“)', 'trailer' => 'Trailer ansehen',
  'playtest' => 'Playtest / Warteliste', 'demo' => 'Demo spielen', 'devlog' => 'Devlog des Spiels', 'lore' => 'Welt- / Lore-Seite',
  'custom' => 'Eigener Link', 'none' => 'Kein Knopf',
];
$pg_btn_second = ['auto' => 'Automatisch (Wishlist → Trailer → Playtest)'] + $pg_btn_main;
$pg_btn_hero = ['auto' => 'Automatisch (wie bisher)'] + array_diff_key($pg_btn_main, ['game' => 1]);
// Spiele ohne „allgemein“-Eintrag (Press Kit, Kopfzeilen-Knopf)
$pg_game_only = array_diff_key($pg_game_options, ['' => 1]);
$pg_game_pick = ['' => '— Aktuelles Projekt —'] + $pg_game_only;

return [

  // ================= STUDIO / STARTSEITE =================
  'studio' => [
    'label' => 'Studio & Startseite',
    'icon'  => '★',
    'file'  => __DIR__ . '/../data/studio.json',
    'fields' => [
      ['widget'=>'heading','label'=>'Studio-Identität','hint'=>'Name, Logo & Grunddaten.'],
      ['name'=>'name','label'=>'Studio-Name','widget'=>'string','i18n'=>false],
      ['name'=>'logo','label'=>'Logo (optional)','widget'=>'image','hint'=>'Transparentes PNG/SVG, Höhe ~80 px (z. B. 240×80). Ersetzt den Schriftzug in Kopf-/Fußzeile. Leer = Wortmarke.'],
      ['name'=>'favicon','label'=>'Favicon (Browser-Tab-Icon)','widget'=>'image','hint'=>'Quadratisch, z. B. 64×64 oder 192×192 px (PNG/SVG/ICO). Separat vom Logo.'],
      ['name'=>'pwa','label'=>'Admin-App-Symbol (Homescreen)','widget'=>'object','hint'=>'Nur fürs Admin/Backend: Ihr könnt das Admin am Handy „zum Home-Bildschirm hinzufügen" und es öffnet im Vollbild wie eine App – mit diesem Symbol. Für normale Besucher gibt es das nicht.','fields'=>[
        ['name'=>'appIcon','label'=>'App-Icon','widget'=>'image','hint'=>'Quadratisch, mind. 512×512 px (PNG). Am besten mit etwas Rand (Sicherheitszone), sonst beschneidet Android die Ecken. Leer = Standard-Symbol.'],
        ['name'=>'appName','label'=>'App-Name (unter dem Icon)','widget'=>'string','i18n'=>false,'default'=>'PLANIGAMES','hint'=>'Im Homescreen erscheint „… Admin".'],
      ]],
      ['name'=>'tagline','label'=>'Untertitel / Tagline','widget'=>'string'],
      ['name'=>'founded','label'=>'Gegründet (Jahr)','widget'=>'string'],
      ['widget'=>'heading','label'=>'Startseite','hint'=>'Oben läuft ein Durchlauf durch eure Spiele (Bild, Logo, Satz und Buttons kommen aus den Spielen). Diese Texte erscheinen darunter.'],
      ['name'=>'heroLine1','label'=>'Claim – Zeile 1','widget'=>'string','hint'=>'Überschrift im Bereich „Über uns“, erste Zeile (z. B. „Unsere Ideen.“).'],
      ['name'=>'heroLine2','label'=>'Claim – Zeile 2 (farbig)','widget'=>'string','hint'=>'Zweite Zeile im Orange-Verlauf (z. B. „Eure Abenteuer.“).'],
      ['name'=>'intro','label'=>'Einleitung über den Spielkarten','widget'=>'text','hint'=>'Ein Satz über eure Spiele – steht im Abschnitt „Unsere Spiele“.'],
      ['name'=>'marquee','label'=>'Laufband-Phrasen','widget'=>'text','hint'=>'Das durchlaufende Band unter dem Spiele-Durchlauf. Eine Phrase pro Zeile. Leer = Standard. Beispiel:'."\n".'Original Worlds'."\n".'Handcrafted Magic'."\n".'Indie & Proud'],
      ['name'=>'heroCountdown','label'=>'Release-Countdown (Startseite oben)','widget'=>'object','hint'=>'Zeigt einen Live-Countdown im Spiele-Durchlauf – z. B. bis Demo oder Release.','fields'=>[
        ['name'=>'enabled','label'=>'Countdown anzeigen','widget'=>'boolean','default'=>false],
        ['name'=>'label','label'=>'Beschriftung','widget'=>'string','default'=>'Bis zum Release','hint'=>'z. B. „Bis zur Demo" oder „Release in".'],
        ['name'=>'date','label'=>'Zieldatum & Uhrzeit','widget'=>'datetime','hint'=>'Wann läuft der Countdown ab?'],
        ['name'=>'doneText','label'=>'Text nach Ablauf','widget'=>'string','default'=>'Jetzt verfügbar! 🎉'],
      ]],
      ['name'=>'heroBackground','label'=>'Bild des aktuellen Projekts (Startseite)','widget'=>'image','hint'=>'Querformat, ca. 2000×1200 px. Erscheint im Durchlauf beim aktuellen Projekt – außer dort ist im Spiel ein eigenes „Bild im Startseiten-Durchlauf“ gesetzt.'],
      ['name'=>'heroVideo','label'=>'Video des aktuellen Projekts (optional, mp4)','widget'=>'file','hint'=>'Läuft im Durchlauf beim aktuellen Projekt automatisch, stumm, als Loop. Das Bild darüber dient als Vorschaubild.'],
      ['widget'=>'heading','label'=>'Über uns','hint'=>'Studio-Vorstellung, Kennzahlen, Bilder & Werte.'],
      ['name'=>'aboutTitle','label'=>'Über uns – Überschrift','widget'=>'string'],
      ['name'=>'aboutBody','label'=>'Über uns – Text','widget'=>'markdown'],
      ['name'=>'aboutImage1','label'=>'Bild 1 – groß links','widget'=>'image','hint'=>'Hochformat, z. B. 1200 × 1600 px. Mit „🎯 Fokus“ legst du fest, welcher Bildteil sichtbar bleibt.'],
      ['name'=>'aboutImage2','label'=>'Bild 2 – oben rechts','widget'=>'image','hint'=>'Quadratisch bis leicht quer, z. B. 1200 × 1000 px.'],
      ['name'=>'aboutImage3','label'=>'Bild 3 – unten rechts','widget'=>'image','hint'=>'Quadratisch bis leicht quer, z. B. 1200 × 1000 px. Es werden genau diese Bilder gezeigt – ohne Bild steht der Text allein (mit 1–2 Bildern passt sich die Anordnung an).'],
      ['name'=>'pillars','label'=>'Werte / Stärken (Kacheln)','widget'=>'list','summary'=>'title','fields'=>[
        ['name'=>'icon','label'=>'Icon','widget'=>'icon','hint'=>'Anklicken zum Auswählen.'],
        ['name'=>'title','label'=>'Titel','widget'=>'string'],
        ['name'=>'text','label'=>'Text','widget'=>'text'],
      ]],
      ['widget'=>'heading','label'=>'Bereiche, Banner & Funktionen','hint'=>'Optionale Module – zum Bearbeiten aufklappen.'],
      ['name'=>'headerCta','label'=>'Knopf oben rechts (Kopfzeile)','widget'=>'object','hint'=>'Der farbige Knopf oben rechts auf jeder Seite (am Handy im Menü). Standard: Playtest des aktuellen Projekts.','fields'=>[
        ['name'=>'mode','label'=>'Wohin führt der Knopf?','widget'=>'select','default'=>'auto','options'=>[
          'auto'=>'Automatisch: Playtest des aktuellen Projekts (sonst „Spiele entdecken“)',
          'wishlist'=>'Steam-Wishlist eines Spiels','playtest'=>'Playtest / Warteliste eines Spiels','game'=>'Spielseite eines Spiels',
          'trailer'=>'Trailer eines Spiels','custom'=>'Eigener Link','off'=>'Kein Knopf',
        ]],
        ['name'=>'game','label'=>'Spiel','widget'=>'select','options'=>$pg_game_pick,'showIf'=>['mode','wishlist,playtest,game,trailer'],'hint'=>'Für Wishlist, Playtest, Spielseite und Trailer. „Aktuelles Projekt“ = das Spiel mit dem Häkchen „Aktuelles Projekt“.'],
        ['name'=>'label','label'=>'Text auf dem Knopf','widget'=>'string','hint'=>'z. B. „FRONTLINERS wishlisten“. Leer = passender Standardtext.'],
        ['name'=>'url','label'=>'Eigener Link','widget'=>'string','showIf'=>['mode','custom'],'hint'=>'z. B. https://store.steampowered.com/app/…'],
      ]],
      ['name'=>'newsletter','label'=>'Newsletter-Anmeldung','widget'=>'object','hint'=>'Steuert den Newsletter-Block über dem Footer (Startseite, Spiele, Devlog, Kontakt, Presse).','fields'=>[
        ['name'=>'enabled','label'=>'Newsletter aktiv','widget'=>'boolean','default'=>true],
        ['name'=>'heading','label'=>'Überschrift (Startseite)','widget'=>'string'],
        ['name'=>'text','label'=>'Text','widget'=>'text'],
        ['name'=>'buttonLabel','label'=>'Button-Text','widget'=>'string','default'=>'Auf die Warteliste'],
        ['name'=>'placeholder','label'=>'Platzhalter im Eingabefeld','widget'=>'string','default'=>'deine@mail.de'],
        ['name'=>'successMessage','label'=>'Danke-Nachricht','widget'=>'string','default'=>'Danke! Du bist dabei. 🧡'],
        ['name'=>'notifyEmail','label'=>'Benachrichtigung an (E-Mail)','widget'=>'string','hint'=>'Bei jeder Anmeldung eine Mail hierhin. Leer = an die Kontakt-E-Mail.'],
        ['name'=>'doubleOptIn','label'=>'Double-Opt-In (Bestätigungslink)','widget'=>'boolean','default'=>true,'hint'=>'Empfohlen (DSGVO): Neue Abonnenten müssen ihre Anmeldung per E-Mail-Link bestätigen.'],
        ['name'=>'welcomeMessage','label'=>'Willkommens-Nachricht','widget'=>'text','hint'=>'Wird nach der Bestätigung per Mail verschickt. Leer = Standardtext.'],
      ]],
      ['name'=>'background','label'=>'Hintergrund & Atmosphäre','widget'=>'object','hint'=>'Steuert die Animation im Seitenhintergrund. Im neuen Website-Design wirkt „Aus / minimal“ am ruhigsten (so wie in der Design-Vorschau).','fields'=>[
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
        ['name'=>'cursorSparkles','label'=>'Magie-Funken am Mauszeiger','widget'=>'boolean','default'=>false,'hint'=>'Feiner Funken-/Glitzerschweif, der dem Mauszeiger folgt (nur am PC mit Maus).'],
      ]],
      ['name'=>'community','label'=>'Community-Banner (Discord)','widget'=>'object','hint'=>'Sobald unten ein Einladungslink steht, erscheint Discord im „Folge uns“-Bereich (mit dieser Überschrift und diesem Text) und im Footer.','fields'=>[
        ['name'=>'enabled','label'=>'Banner anzeigen','widget'=>'boolean','default'=>true],
        ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Komm in unsere Community'],
        ['name'=>'text','label'=>'Text','widget'=>'text','default'=>'Sei beim Chaos dabei: Devlogs aus erster Hand, Playtests, Abstimmungen und ein Haufen wackeliger Magier warten auf dich.'],
        ['name'=>'buttonLabel','label'=>'Button-Text','widget'=>'string','default'=>'Discord beitreten','hint'=>'Beschriftung des Discord-Eintrags im „Folge uns“-Bereich.'],
        ['name'=>'url','label'=>'Discord-Einladungslink','widget'=>'string','hint'=>'z. B. https://discord.gg/… – ohne Link (oder mit einem Link auf die eigene Website) wird Discord nirgends angezeigt.'],
      ]],
      // Vorschlagsbox & Beta-Warteliste gibt es ausschließlich als Spiel-Seiten-Blöcke
      // (im Spiel-Editor unter „Block hinzufügen"), nicht mehr auf der Startseite.
      ['name'=>'mascot','label'=>'Maskottchen','widget'=>'object','hint'=>'Kleiner animierter Begleiter unten in der Ecke (klickbar). Komplett ein-/ausschaltbar. Alle Effekte funktionieren mit Emoji und mit eigenem PNG.','fields'=>[
        ['name'=>'enabled','label'=>'Maskottchen anzeigen','widget'=>'boolean','default'=>false],
        ['name'=>'emoji','label'=>'Emoji','widget'=>'string','default'=>'🧙','hint'=>'Wird genutzt, wenn kein Bild gesetzt ist.'],
        ['name'=>'size','label'=>'Größe (px)','widget'=>'number','default'=>96,'hint'=>'Breite des Maskottchens in Pixeln (Standard 96, größer = auffälliger).'],
        ['name'=>'image','label'=>'Bild (optional)','widget'=>'image','hint'=>'Transparentes PNG, ca. 200×200 px. Überschreibt das Emoji.'],
        ['name'=>'eyes','label'=>'Wackelaugen (Overlay)','widget'=>'object','hint'=>'Legt bewegliche Augen über dein PNG, die dem Mauszeiger folgen. Nutze „🪄 Augen platzieren", um sie genau auf dein Bild zu setzen.','fields'=>[
          ['name'=>'enabled','label'=>'Augen anzeigen','widget'=>'boolean','default'=>false],
          ['name'=>'size','label'=>'Augengröße (% der Breite)','widget'=>'number','default'=>22],
          ['name'=>'lx','label'=>'Linkes Auge · X (%)','widget'=>'number','default'=>35],
          ['name'=>'ly','label'=>'Linkes Auge · Y (%)','widget'=>'number','default'=>42],
          ['name'=>'rx','label'=>'Rechtes Auge · X (%)','widget'=>'number','default'=>65],
          ['name'=>'ry','label'=>'Rechtes Auge · Y (%)','widget'=>'number','default'=>42],
        ]],
        ['name'=>'message','label'=>'Sprechblase (Standard)','widget'=>'string','hint'=>'Wird gezeigt, wenn keine wechselnden Sprüche gesetzt sind. z. B. „Psst… wishliste Wobbly Wizards!"'],
        ['name'=>'messages','label'=>'Wechselnde Sprüche','widget'=>'list','summary'=>'text','label_singular'=>'Spruch','hint'=>'Erscheinen abwechselnd beim Drüberfahren. Überschreibt den Standard-Spruch.','fields'=>[
          ['name'=>'text','label'=>'Spruch','widget'=>'string'],
        ]],
        ['name'=>'context','label'=>'Seiten-spezifische Sprüche','widget'=>'list','summary'=>'page','label_singular'=>'Regel','hint'=>'Eigene Sprüche je nach Seite – übersteuern dort die wechselnden Sprüche.','fields'=>[
          ['name'=>'page','label'=>'Seite','widget'=>'select','options'=>['home'=>'Startseite','games'=>'Spiele-Übersicht','game'=>'Spiel-Detailseite','devlog'=>'Devlog','contact'=>'Kontakt','presskit'=>'Presskit']],
          ['name'=>'text','label'=>'Spruch','widget'=>'string'],
        ]],
        ['name'=>'follow','label'=>'Blick folgt der Maus','widget'=>'boolean','default'=>true,'hint'=>'Maskottchen neigt sich leicht zum Mauszeiger – wirkt lebendig (Emoji & PNG).'],
        ['name'=>'idle','label'=>'Einschlafen bei Inaktivität','widget'=>'boolean','default'=>true,'hint'=>'Döst nach ~22 s ein (💤) und wacht beim Scrollen/Bewegen wieder auf.'],
        ['name'=>'draggable','label'=>'Verschiebbar','widget'=>'boolean','default'=>true,'hint'=>'Besucher können das Maskottchen ziehen; die Position wird im Browser gemerkt.'],
        ['name'=>'closable','label'=>'Schließen erlauben','widget'=>'boolean','default'=>true,'hint'=>'Zeigt beim Drüberfahren ein ✕, mit dem Besucher es ausblenden können.'],
      ]],
      ['name'=>'tipjar','label'=>'Unterstützen-Button (Ko-fi / Tip-Jar)','widget'=>'object','hint'=>'Button „Unterstützen“ oben in der Kopfzeile (am Handy im Menü) und im Footer – führt zu deiner Support-Seite.','fields'=>[
        ['name'=>'enabled','label'=>'Button anzeigen','widget'=>'boolean','default'=>false],
        ['name'=>'label','label'=>'Beschriftung','widget'=>'string','default'=>'Unterstützen'],
        ['name'=>'url','label'=>'Link','widget'=>'string','hint'=>'z. B. https://ko-fi.com/deinstudio'],
      ]],
      ['name'=>'presskit','label'=>'Press Kit','widget'=>'object','page'=>'presskit','hint'=>'Eigene Seite (presse.html) für Presse & Creator. Bearbeitet wird es im Admin unter „Spielbetrieb → Press Kit“.','fields'=>[
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
          ['name'=>'file','label'=>'Datei (ZIP/Bild/PDF)','widget'=>'file'],
        ]],
        ['name'=>'gamesMode','label'=>'Welche Spiele erscheinen?','widget'=>'select','default'=>'auto','options'=>[
          'auto'=>'Automatisch: alle Spiele mit Logo oder Titelbild','custom'=>'Nur die unten ausgewählten Spiele','none'=>'Keine Spiele zeigen',
        ]],
        ['name'=>'games','label'=>'Ausgewählte Spiele','widget'=>'list','showIf'=>['gamesMode','custom'],'summary'=>'game','label_singular'=>'Spiel','hint'=>'Die Reihenfolge hier ist die Reihenfolge im Press Kit.','fields'=>[
          ['name'=>'game','label'=>'Spiel','widget'=>'select','options'=>$pg_game_only],
          ['name'=>'text','label'=>'Kurztext für die Presse (optional)','widget'=>'text','hint'=>'Leer = der Satz aus dem Kopfbereich des Spiels.'],
          ['name'=>'logo','label'=>'Logo zum Herunterladen anbieten','widget'=>'boolean','default'=>true],
          ['name'=>'cover','label'=>'Titelbild zum Herunterladen anbieten','widget'=>'boolean','default'=>true],
          ['name'=>'trailer','label'=>'Trailer-Link (optional)','widget'=>'string','i18n'=>false,'hint'=>'z. B. YouTube-Link – erscheint als Knopf „Trailer“.'],
          ['name'=>'files','label'=>'Weitere Dateien (Screenshots, Pressemitteilung …)','widget'=>'list','summary'=>'label','label_singular'=>'Datei','fields'=>[
            ['name'=>'label','label'=>'Bezeichnung','widget'=>'string','hint'=>'z. B. „Screenshot Burgbelagerung“ oder „Pressemitteilung (PDF)“'],
            ['name'=>'file','label'=>'Datei','widget'=>'file'],
          ]],
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
      ['widget'=>'heading','label'=>'Footer & Kontaktdaten','hint'=>'Erscheint in der Fußzeile aller Seiten.'],
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
        ['name'=>'name','label'=>'Name','widget'=>'string','i18n'=>false],
        ['name'=>'role','label'=>'Rolle','widget'=>'string','hint'=>'z. B. Founder · Code & Design'],
        ['name'=>'photo','label'=>'Foto','widget'=>'image','hint'=>'Quadratisch, ca. 400×400 px (JPG/PNG). Ohne Foto wird das Emoji gezeigt.'],
        ['name'=>'photoAlt','label'=>'Foto – Alt-Text','widget'=>'string','hint'=>'Bildbeschreibung für SEO & Screenreader. Leer = Name.'],
        ['name'=>'emoji','label'=>'Emoji (falls kein Foto)','widget'=>'string','default'=>'🧙'],
        ['name'=>'tagline','label'=>'Kurzzeile','widget'=>'string','hint'=>'Ein knackiger Einzeiler, z. B. „Macht Magie aus Code."'],
        ['name'=>'bio','label'=>'Über mich','widget'=>'markdown','hint'=>'Längerer Steckbrief – erscheint im Profil beim Anklicken.'],
        ['name'=>'location','label'=>'Ort (optional)','widget'=>'string','hint'=>'z. B. Köln, DE'],
        ['name'=>'funFact','label'=>'Fun Fact (optional)','widget'=>'string','hint'=>'z. B. „Hat 200+ Stunden in Wackel-Physik versenkt."'],
        ['name'=>'socials','label'=>'Profile / Links','widget'=>'list','summary'=>'label','label_singular'=>'Link','fields'=>[
          ['name'=>'label','label'=>'Label','widget'=>'string','hint'=>'z. B. Bluesky, X, Mastodon, Website'],
          ['name'=>'url','label'=>'URL','widget'=>'string'],
        ]],
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
        // „tab“ ordnet die Abschnitte den Reitern der Spiele-Verwaltung zu (settings · look · page)
        ['widget'=>'heading','label'=>'Grunddaten','tab'=>'settings','hint'=>'Name, Kurzbeschreibung, Status & Store-Link.'],
        ['name'=>'title','label'=>'Titel','widget'=>'string','i18n'=>false,'hint'=>'Der Spielname ist in allen Sprachen gleich.'],
        ['name'=>'slug','label'=>'Web-Adresse (Slug)','widget'=>'string','slug'=>true,'hint'=>'Kleinbuchstaben & Bindestriche. Wird über „Ändern…“ geändert – dabei ziehen Warteliste, Vorschläge, Board, Devlog & Statistik automatisch mit um.'],
        ['name'=>'tagline','label'=>'Kurzbeschreibung / Tagline','widget'=>'text'],
        ['name'=>'status','label'=>'Status','widget'=>'select','default'=>'development','options'=>[
          'development'=>'In Entwicklung','announced'=>'Angekündigt','demo'=>'Demo verfügbar','early'=>'Early Access','released'=>'Veröffentlicht','paused'=>'Pausiert',
        ]],
        ['name'=>'pauseNote','label'=>'Hinweis bei „Pausiert“','widget'=>'text','hint'=>'Erscheint auf der Spielseite bei der Roadmap, solange der Status „Pausiert“ ist. Leer = Standardtext.'],
        ['name'=>'featured','label'=>'Aktuelles Projekt (Startseite)','widget'=>'boolean','hint'=>'Das erste so markierte Spiel startet den Durchlauf auf der Startseite und trägt das Schild „Aktuelles Projekt“.'],
        ['name'=>'wishlistUrl','label'=>'Wishlist / Store-Link','widget'=>'string','hint'=>'z. B. Steam-Seite. Erscheint als Haupt-Button im Kopfbereich.'],
        ['widget'=>'heading','label'=>'Farben der Spielwelt','tab'=>'look','hint'=>'Buttons, Verläufe & Partikel der Spielseite.'],
        ['name'=>'accent','label'=>'Akzentfarbe','widget'=>'color','default'=>'#ff7d1a','hint'=>'Hauptfarbe der Game-Welt (HEX). Buttons & Verläufe.'],
        ['name'=>'accent2','label'=>'Zweitfarbe','widget'=>'color','default'=>'#e6a015','hint'=>'Zweite HEX-Farbe für Verläufe (Button-Ende). Wird hell → Schrift automatisch dunkel.'],
        ['name'=>'particleColor','label'=>'Partikelfarbe (Hintergrund)','widget'=>'color','default'=>'','hint'=>'Farbe der schwebenden Hintergrund-Partikel auf dieser Spielseite. Leer = Akzentfarbe.'],
        ['widget'=>'heading','label'=>'Cover & Logo','tab'=>'look','hint'=>'Bilder für Karten, Startseite und den Kopfbereich der Spielseite.'],
        ['name'=>'cover','label'=>'Cover-Bild','widget'=>'image','hint'=>'Querformat 16:10, ca. 1600×1000 px (JPG/WebP). Für Karten & Startseite.'],
        ['name'=>'coverAlt','label'=>'Cover – Alt-Text','widget'=>'string','hint'=>'Bildbeschreibung für SEO & Screenreader. Leer = Spieltitel.'],
        ['name'=>'logo','label'=>'Logo (transparent)','widget'=>'image','hint'=>'Transparentes PNG/SVG – wird im Kopfbereich statt des Titels gezeigt. Durchsichtiger Rand in der Datei wird automatisch ignoriert – das Logo steht immer bündig am Text.'],
        ['name'=>'logoScale','label'=>'Logo-Größe (%)','widget'=>'number','default'=>100,'hint'=>'100 = Standard. Wirkt auf Kopfbereich & Startseiten-Karte. Am einfachsten über „Kopfbereich gestalten“.'],
        ['name'=>'logoLayout','label'=>'Anordnung im Kopfbereich','widget'=>'select','default'=>'left','options'=>[
          'left'=>'Links über dem Text (Standard)','center'=>'Mittig – alles zentriert','split'=>'Logo rechts neben dem Text',
        ],'hint'=>'Das Logo steht immer im Textblock – auf jedem Bildschirm gleich zum Text, ohne etwas zu überdecken. Am Handy steht das Bild oben, Logo und Text darunter.'],
        ['name'=>'logoGap','label'=>'Abstand ums Logo (px)','widget'=>'number','default'=>20,'hint'=>'Abstand zwischen Status-Badge, Logo und Text darunter (bei „Links“ und „Mittig“). Am Handy automatisch etwas kleiner. Am einfachsten über „Kopfbereich gestalten“.'],
        ['widget'=>'heading','label'=>'Startseite & Spielkarten','tab'=>'look','hint'=>'Wie das Spiel im Durchlauf oben auf der Startseite und auf den Spielkarten erscheint. Alles freiwillig – leere Felder werden sinnvoll ergänzt.'],
        ['name'=>'genre','label'=>'Genre-Zeile','widget'=>'string','hint'=>'Kurz, z. B. „Multiplayer-Shooter · PC“. Steht klein neben dem Status.'],
        ['name'=>'summary','label'=>'Kurzbeschreibung (Startseite & Karten)','widget'=>'text','hint'=>'1–2 Sätze. Leer = der erste Satz aus dem Textabschnitt „Über das Spiel“.'],
        ['name'=>'homeImage','label'=>'Bild im Startseiten-Durchlauf','widget'=>'image','hint'=>'Querformat, ca. 1920×1080 px. Leer = Cover-Bild (beim aktuellen Projekt: das Hero-Hintergrundbild aus „Studio & Startseite“).'],
        ['widget'=>'heading','label'=>'Durchlauf: Video & Knöpfe','tab'=>'look','hint'=>'Was im Spiele-Durchlauf oben auf der Startseite läuft und welche Knöpfe man dort anklicken kann.'],
        ['name'=>'homeMedia','label'=>'Hintergrund im Durchlauf','widget'=>'select','default'=>'image','options'=>['image'=>'Bild','video'=>'Video (läuft automatisch, stumm, in Schleife)'],'hint'=>'Bei „Video“ läuft das Video unten; das Bild oben dient als Vorschaubild, bis es geladen ist.'],
        ['name'=>'homeVideo','label'=>'Video im Durchlauf (MP4/WebM)','widget'=>'file','showIf'=>['homeMedia','video'],'hint'=>'Querformat 16:9, am besten 10–20 Sekunden und unter ca. 15 MB.'],
        ['name'=>'slideBtn1','label'=>'Knopf 1 (Hauptknopf)','widget'=>'select','default'=>'game','options'=>$pg_btn_main],
        ['name'=>'slideBtn1Label','label'=>'Knopf 1 – eigener Text (optional)','widget'=>'string','hint'=>'Leer = passender Standardtext, z. B. „Zum Spiel“ oder „Auf Steam wishlisten“.'],
        ['name'=>'slideBtn1Url','label'=>'Knopf 1 – eigener Link','widget'=>'string','showIf'=>['slideBtn1','custom'],'hint'=>'z. B. https://store.steampowered.com/app/…'],
        ['name'=>'slideBtn2','label'=>'Knopf 2','widget'=>'select','default'=>'auto','options'=>$pg_btn_second,'hint'=>'Gibt es das gewählte Ziel nicht (z. B. noch kein Wishlist-Link), entfällt der Knopf.'],
        ['name'=>'slideBtn2Label','label'=>'Knopf 2 – eigener Text (optional)','widget'=>'string'],
        ['name'=>'slideBtn2Url','label'=>'Knopf 2 – eigener Link','widget'=>'string','showIf'=>['slideBtn2','custom']],
        ['widget'=>'heading','label'=>'Steckbrief (Spielseite)','tab'=>'look','hint'=>'Leiste unten im Kopfbereich der Spielseite – bis zu 4 Zeilen, z. B. Genre · Spieler · Plattform · Release. Leer = die Kennzahlen aus dem Block „Kennzahlen“.'],
        ['name'=>'facts','label'=>'Steckbrief-Zeilen','widget'=>'list','summary'=>'label','label_singular'=>'Zeile','fields'=>[
          ['name'=>'label','label'=>'Bezeichnung','widget'=>'string','hint'=>'z. B. Genre, Spieler, Plattform, Release'],
          ['name'=>'value','label'=>'Wert','widget'=>'string','hint'=>'z. B. Multiplayer-Shooter, bis zu 60, PC, 2027'],
        ]],
        ['widget'=>'heading','label'=>'Seiten-Blöcke','tab'=>'page','hint'=>'Inhalte der Spielseite – die Website zeigt sie genau in dieser Reihenfolge (der Kopfbereich steht immer oben, Devlog und „Weitere Spiele“ kommen ans Ende). Direkt aufeinanderfolgende Blöcke derselben Art werden zu einem Abschnitt zusammengefasst, z. B. Trailer + Galerie oder Warteliste + Vorschläge.'],
        ['name'=>'blocks','label'=>'Seiten-Blöcke (Website-Builder)','widget'=>'blocks','types'=>[
          'hero'=>['label'=>'Hero (Kopfbereich)','fields'=>[
            ['name'=>'tagline','label'=>'Tagline (überschreibt Standard)','widget'=>'text'],
            ['name'=>'background','label'=>'Hintergrundbild','widget'=>'image','hint'=>'Querformat, ca. 2000×1200 px (JPG/WebP).'],
            ['name'=>'backgroundMobile','label'=>'Hintergrundbild – Mobil (optional)','widget'=>'image','hint'=>'Hochformat für Handys, ca. 1080×1600 px. Leer = das normale Hintergrundbild wird verwendet.'],
            ['name'=>'video','label'=>'Hintergrund-Video (mp4)','widget'=>'file'],
            ['name'=>'poster','label'=>'Video-Vorschaubild','widget'=>'image','hint'=>'Querformat 16:9, ca. 1600×900 px.'],
            ['name'=>'ctaLabel','label'=>'Button-Text','widget'=>'string','default'=>'Auf Steam wishlisten'],
            ['name'=>'trailerUrl','label'=>'Trailer-Link (Button)','widget'=>'string'],
            ['name'=>'btn1','label'=>'Knopf 1 im Kopfbereich','widget'=>'select','default'=>'auto','options'=>$pg_btn_hero,'hint'=>'Automatisch = Wishlist (falls Link vorhanden), sonst Playtest/Warteliste oder Demo.'],
            ['name'=>'btn1Label','label'=>'Knopf 1 – eigener Text (optional)','widget'=>'string'],
            ['name'=>'btn1Url','label'=>'Knopf 1 – eigener Link','widget'=>'string','showIf'=>['btn1','custom']],
            ['name'=>'btn2','label'=>'Knopf 2 im Kopfbereich','widget'=>'select','default'=>'auto','options'=>$pg_btn_hero,'hint'=>'Automatisch = Trailer (falls vorhanden), sonst der nächste passende Knopf.'],
            ['name'=>'btn2Label','label'=>'Knopf 2 – eigener Text (optional)','widget'=>'string'],
            ['name'=>'btn2Url','label'=>'Knopf 2 – eigener Link','widget'=>'string','showIf'=>['btn2','custom']],
          ]],
          'richtext'=>['label'=>'Textabschnitt','fields'=>[
            ['name'=>'kicker','label'=>'Kicker (kleine Zeile)','widget'=>'string'],
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'body','label'=>'Text','widget'=>'markdown'],
          ]],
          'features'=>['label'=>'Feature-Kacheln','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'items','label'=>'Kacheln','widget'=>'list','summary'=>'title','fields'=>[
              ['name'=>'icon','label'=>'Icon','widget'=>'icon','hint'=>'Anklicken zum Auswählen.'],
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
          'download'=>['label'=>'Demo & Downloads','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Jetzt spielen'],
            ['name'=>'text','label'=>'Beschreibung','widget'=>'text'],
            ['name'=>'embedUrl','label'=>'Spielbare Web-Demo (Einbettungs-URL)','widget'=>'string','hint'=>'z. B. itch.io-Embed oder Link zu einem HTML5-Build. Wird als spielbares Fenster eingebettet (lädt erst nach Zustimmung).'],
            ['name'=>'embedHeight','label'=>'Höhe des Demo-Fensters (px)','widget'=>'number','default'=>540],
            ['name'=>'builds','label'=>'Download-Pakete','widget'=>'list','summary'=>'label','label_singular'=>'Download','fields'=>[
              ['name'=>'label','label'=>'Titel','widget'=>'string','hint'=>'z. B. Windows (64-bit)'],
              ['name'=>'platform','label'=>'Plattform','widget'=>'select','default'=>'windows','options'=>[
                'windows'=>'🪟 Windows','mac'=>'🍎 macOS','linux'=>'🐧 Linux','android'=>'🤖 Android','web'=>'🌐 Web','other'=>'📦 Sonstiges',
              ]],
              ['name'=>'version','label'=>'Version','widget'=>'string','hint'=>'z. B. v0.3.1'],
              ['name'=>'size','label'=>'Dateigröße','widget'=>'string','hint'=>'z. B. 240 MB'],
              ['name'=>'file','label'=>'Datei (ZIP/Build)','widget'=>'file','hint'=>'Direkt hochladen – oder unten einen externen Link angeben.'],
              ['name'=>'url','label'=>'Externer Link','widget'=>'string','hint'=>'z. B. Steam/itch.io. Überschreibt die Datei.'],
            ]],
          ]],
          'requirements'=>['label'=>'Systemanforderungen','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Systemanforderungen'],
            ['name'=>'note','label'=>'Hinweis (optional)','widget'=>'string','hint'=>'z. B. „Stand der Demo – ändert sich evtl. bis Release."'],
            ['name'=>'min','label'=>'Minimal','widget'=>'object','fields'=>[
              ['name'=>'os','label'=>'Betriebssystem','widget'=>'string','hint'=>'z. B. Windows 10 (64-bit)'],
              ['name'=>'cpu','label'=>'Prozessor','widget'=>'string'],
              ['name'=>'ram','label'=>'Arbeitsspeicher','widget'=>'string','hint'=>'z. B. 4 GB RAM'],
              ['name'=>'gpu','label'=>'Grafikkarte','widget'=>'string'],
              ['name'=>'storage','label'=>'Speicherplatz','widget'=>'string','hint'=>'z. B. 2 GB'],
            ]],
            ['name'=>'rec','label'=>'Empfohlen','widget'=>'object','fields'=>[
              ['name'=>'os','label'=>'Betriebssystem','widget'=>'string'],
              ['name'=>'cpu','label'=>'Prozessor','widget'=>'string'],
              ['name'=>'ram','label'=>'Arbeitsspeicher','widget'=>'string'],
              ['name'=>'gpu','label'=>'Grafikkarte','widget'=>'string'],
              ['name'=>'storage','label'=>'Speicherplatz','widget'=>'string'],
            ]],
          ]],
          'storewidget'=>['label'=>'Steam-/itch.io-Widget','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Auf Steam ansehen'],
            ['name'=>'platform','label'=>'Plattform','widget'=>'select','default'=>'steam','options'=>['steam'=>'Steam','itch'=>'itch.io']],
            ['name'=>'steamAppId','label'=>'Steam App-ID','widget'=>'string','hint'=>'Nur die Zahl aus der Steam-URL: store.steampowered.com/app/ＡＰＰＩＤ/…'],
            ['name'=>'itchEmbed','label'=>'itch.io Widget-URL','widget'=>'string','hint'=>'Auf itch.io unter „Distribute → Widget" die iframe-Adresse kopieren.'],
            ['name'=>'height','label'=>'Höhe (px, nur itch.io)','widget'=>'number','default'=>167],
          ]],
          'suggestions'=>['label'=>'Vorschlagsbox (Community-Voting)','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Was sollen wir als Nächstes bauen?'],
            ['name'=>'intro','label'=>'Einleitung','widget'=>'text','hint'=>'Kurzer Text über dem Eingabefeld.'],
          ],'hint'=>'Besucher schlagen Ideen für DIESES Spiel vor und stimmen ab. Freigabe & Status unter „💡 Vorschläge" im Menü.'],
          'waitlist'=>['label'=>'Beta-Warteliste (Empfehlungslink)','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','default'=>'Sei bei der Beta dabei'],
            ['name'=>'intro','label'=>'Einleitung','widget'=>'text'],
            ['name'=>'successText','label'=>'Erfolgs-Text','widget'=>'string','default'=>'Du bist dabei! 🎉 Teile deinen Link, um aufzusteigen.'],
          ],'hint'=>'Eigene Warteliste & eigenes Empfehlungs-Ranking nur für DIESES Spiel. Anmeldungen unter „📋 Beta-Warteliste".'],
          'lore'=>['label'=>'Welt-Teaser („Welt erkunden"-Link)','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string','hint'=>'Leer = Lore-Titel des Spiels.'],
            ['name'=>'text','label'=>'Text','widget'=>'text','hint'=>'Leer = Lore-Einleitung des Spiels.'],
            ['name'=>'label','label'=>'Button-Text','widget'=>'string','default'=>'Welt erkunden'],
            ['name'=>'image','label'=>'Hintergrundbild (optional)','widget'=>'image','hint'=>'Leer = Lore-Hero bzw. Cover des Spiels.'],
          ],'hint'=>'Verlinkt auf die Story-/Lore-Seite DIESES Spiels. Erscheint nur, wenn unten bei „Welt / Lore" aktiviert.'],
          'spacer'=>['label'=>'Abstand / Leerraum','fields'=>[
            ['name'=>'size','label'=>'Höhe (px)','widget'=>'number','default'=>48],
          ]],
        ]],
        ['widget'=>'heading','label'=>'Welt / Lore','tab'=>'page','hint'=>'Eigene Story-Seite für dieses Spiel (lore.html?slug=…).'],
        ['name'=>'lore','label'=>'Welt / Lore (eigene Story-Seite für dieses Spiel)','widget'=>'object',
          'hint'=>'Ausfüllen und oben den Block „Welt-Teaser" einsetzen, um darauf zu verlinken. Adresse: lore.html?slug=<Slug>.','fields'=>[
          ['name'=>'enabled','label'=>'Story-/Lore-Seite für dieses Spiel aktivieren','widget'=>'boolean','default'=>false],
          ['name'=>'title','label'=>'Seitentitel','widget'=>'string','hint'=>'Leer = „Die Welt von <Spieltitel>".'],
          ['name'=>'intro','label'=>'Einleitung','widget'=>'text'],
          ['name'=>'hero','label'=>'Hero-Bild (oben)','widget'=>'image','hint'=>'Großes Stimmungsbild, Querformat ca. 2000×1100 px.'],
          ['name'=>'scenes','label'=>'Kapitel / Szenen','widget'=>'list','summary'=>'heading','label_singular'=>'Szene',
            'hint'=>'Beim Scrollen erscheint Szene für Szene. Bild abwechselnd links/rechts oder als Vollbild.','fields'=>[
            ['name'=>'heading','label'=>'Überschrift','widget'=>'string'],
            ['name'=>'text','label'=>'Text','widget'=>'markdown'],
            ['name'=>'image','label'=>'Bild','widget'=>'image'],
            ['name'=>'align','label'=>'Bild-Anordnung','widget'=>'select','default'=>'auto','options'=>[
              'auto'=>'Automatisch abwechselnd','left'=>'Bild links','right'=>'Bild rechts','full'=>'Vollbild-Hintergrund',
            ]],
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
