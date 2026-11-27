<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
/**
 * PLANIGAMES — Spiele-Verwaltung (Master-Detail).
 * Eingebunden von index.php bei ?collection=games.
 * Links die Spieleliste (Suche, Filter, Reihenfolge per Ziehen), rechts der Editor
 * für genau EIN Spiel mit Reitern. Gespeichert wird immer nur dieses eine Spiel.
 */

/* Kleines Formular-Feld (Wrapper), damit Felder per Sprungmarke gefunden werden */
function pg_gv_field($n, $html){ return '<div class="gfield" data-gfield="' . pg_h($n) . '">' . $html . '</div>'; }

/* Kopf einer Karte */
function pg_gv_card($icon, $title, $body, $extra = '', $hint = ''){
  return '<section class="ck-card g-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon($icon) . pg_h($title) . '</span>' . $extra . '</div>'
       . ($hint !== '' ? '<p class="g-card-hint">' . pg_h($hint) . '</p>' : '')
       . '<div class="g-card-b">' . $body . '</div></section>';
}

/* Englisch-Modus: nur übersetzbare Texte, jeweils mit deutschem Original darunter */
function pg_gv_i18n_leaf($label, $name, $enVal, $deVal, $widget, &$stat){
  $enVal = is_scalar($enVal) ? (string) $enVal : '';
  $stat['t']++;
  $miss = trim($enVal) === '';
  if ($miss) $stat['m']++;
  $deShow = trim(preg_replace('/\s+/', ' ', (string) $deVal));
  $input = $widget === 'string'
    ? '<input type="text" name="' . pg_h($name) . '" value="' . pg_h($enVal) . '" placeholder="' . pg_h(mb_strimwidth($deShow, 0, 90, '…')) . '">'
    : '<textarea name="' . pg_h($name) . '" rows="' . ($widget === 'markdown' ? 7 : 3) . '"' . ($widget === 'markdown' ? ' class="md"' : '') . '>' . pg_h($enVal) . '</textarea>';
  return '<div class="field i18n-field' . ($miss ? ' missing' : '') . '"><label class="flabel">' . pg_h($label)
       . ($miss ? ' <span class="i18n-miss">fehlt</span>' : '') . '</label>' . $input
       . '<div class="de-ref"><span>DE</span><p>' . pg_h(mb_strimwidth($deShow, 0, 320, '…')) . '</p></div></div>';
}
function pg_gv_i18n_fields($fields, $de, $en, $prefix, &$stat){
  $h = ''; $de = is_array($de) ? $de : []; $en = is_array($en) ? $en : [];
  foreach ($fields as $f) {
    if (($f['widget'] ?? '') === 'heading' || empty($f['name'])) continue;
    $n = $f['name'];
    if (!array_key_exists($n, $de)) continue;
    $dv = $de[$n]; $ev = $en[$n] ?? null; $nm = $prefix . '[' . $n . ']';
    switch ($f['widget']) {
      case 'object':
        $inner = pg_gv_i18n_fields($f['fields'] ?? [], $dv, $ev, $nm, $stat);
        if ($inner !== '') $h .= '<div class="i18n-group"><div class="i18n-gh">' . pg_h($f['label'] ?? $n) . '</div>' . $inner . '</div>';
        break;
      case 'list':
        if (!is_array($dv)) break;
        $dv = array_values($dv); $ev = is_array($ev) ? array_values($ev) : [];
        if (isset($f['field'])) {
          if (!pg_i18n_translatable($f['field'])) break;
          foreach ($dv as $k => $v) if (trim((string) $v) !== '') $h .= pg_gv_i18n_leaf(($f['field']['label'] ?? $n) . ' ' . ($k + 1), $nm . '[' . $k . ']', $ev[$k] ?? '', $v, $f['field']['widget'] ?? 'string', $stat);
          break;
        }
        foreach ($dv as $k => $it) {
          $inner = pg_gv_i18n_fields($f['fields'] ?? [], $it, $ev[$k] ?? [], $nm . '[' . $k . ']', $stat);
          if ($inner === '') continue;
          $sum = is_array($it) && !empty($f['summary']) ? trim((string) ($it[$f['summary']] ?? '')) : '';
          $h .= '<div class="i18n-group"><div class="i18n-gh">' . pg_h(($f['label_singular'] ?? $f['label'] ?? $n) . ' ' . ($k + 1))
              . ($sum !== '' ? ' <span>· ' . pg_h(mb_strimwidth($sum, 0, 60, '…')) . '</span>' : '') . '</div>' . $inner . '</div>';
        }
        break;
      case 'blocks':
        if (!is_array($dv)) break;
        $ev = is_array($ev) ? array_values($ev) : []; $ai = 0;
        foreach (array_values($dv) as $k => $it) {
          $t = is_array($it) ? (string) ($it['type'] ?? '') : '';
          if (!isset($f['types'][$t])) continue;
          $eit = $ev[$ai++] ?? [];
          $inner = pg_gv_i18n_fields($f['types'][$t]['fields'] ?? [], $it, $eit, $nm . '[' . $k . ']', $stat);
          if ($inner === '') continue;
          $meta = pg_block_meta($t, $it);
          $h .= '<div class="i18n-group i18n-block"><div class="i18n-gh">' . pg_icon(pg_block_icon($t)) . pg_h($f['types'][$t]['label'])
              . ($meta !== '' ? ' <span>· ' . pg_h($meta) . '</span>' : '') . (!empty($it['hidden']) ? ' <span class="chip">ausgeblendet</span>' : '') . '</div>' . $inner . '</div>';
        }
        break;
      default:
        if (!pg_i18n_translatable($f) || trim((string) $dv) === '') break;
        $h .= pg_gv_i18n_leaf($f['label'] ?? $n, $nm, $ev, $dv, $f['widget'], $stat);
    }
  }
  return $h;
}

function pg_games_view($SCHEMA, $lang){
  $de = pg_games_data('de');
  $games = $de['games'];
  $fields = pg_game_fields($SCHEMA);
  $sel = trim((string) ($_GET['game'] ?? ''));
  $i = $sel !== '' ? pg_game_idx($games, $sel) : -1;
  // Alte Adresse (umbenannt)? → zum neuen Spiel springen
  if ($sel !== '' && $i < 0) {
    $to = (string) (pg_redirects_load()['games'][strtolower($sel)] ?? '');
    if ($to !== '' && pg_game_idx($games, $to) >= 0) {
      $tabQ = preg_replace('/[^a-z]/', '', (string) ($_GET['tab'] ?? ''));
      header('Location: index.php?collection=games&game=' . rawurlencode($to) . ($lang === 'en' ? '&lang=en' : '') . ($tabQ !== '' ? '&tab=' . $tabQ : '')); exit;
    }
  }
  $explicit = $i >= 0;
  if ($i < 0 && $games) $i = 0;
  $g = ($i >= 0 && is_array($games[$i] ?? null)) ? $games[$i] : null;
  $slug = $g ? (string) ($g['slug'] ?? '') : '';
  $lslug = strtolower($slug);
  $tab = preg_replace('/[^a-z]/', '', (string) ($_GET['tab'] ?? ''));
  if (!in_array($tab, ['overview', 'page', 'look', 'community', 'settings'], true)) $tab = 'overview';
  $host = preg_replace('#^https?://#', '', pg_site_url());

  // ---- vorhandene Daten (nur lesen) ----
  $wl = array_values(array_filter((array) pg_load_json(PG_DATA_DIR . '/waitlist.json'), fn($e) => is_array($e) && !empty($e['email'])));
  $sugg = array_values(array_filter((array) pg_load_json(PG_DATA_DIR . '/suggestions.json'), 'is_array'));
  $pages = (array) (pg_load_json(PG_DATA_DIR . '/stats.json')['pages'] ?? []);
  $scopeOf = fn($e) => strtolower(trim((string) ($e['scope'] ?? '')));
  $wlBy = []; foreach ($wl as $e) $wlBy[$scopeOf($e)][] = $e;
  $sgBy = []; foreach ($sugg as $e) $sgBy[$scopeOf($e)][] = $e;
  $viewsOf = function ($s) use ($pages) {
    $n = 0; $s = strtolower((string) $s);
    foreach ($pages as $k => $v) if (preg_match('/^(game|lore):(.+)$/i', (string) $k, $m) && strtolower($m[2]) === $s) $n += (int) $v;
    return $n;
  };
  $stLbl = ['development' => ['In Entwicklung', 'st-dev'], 'announced' => ['Angekündigt', 'st-ann'], 'demo' => ['Demo verfügbar', 'st-demo'],
            'early' => ['Early Access', 'st-early'], 'released' => ['Veröffentlicht', 'st-rel']];

  // EN-Stand dieses Spiels
  $enData = pg_games_data('en');
  $enGame = $g ? (($ei = pg_game_en_idx($enData['games'], $games, $slug, $i)) >= 0 ? $enData['games'][$ei] : []) : [];
  $enAligned = $g ? pg_i18n_align($fields, $g, $enGame) : [];
  [$tT, $tM] = $g ? pg_i18n_count($fields, $g, $enAligned) : [0, 0];
  $enPct = $tT > 0 ? (int) floor(($tT - $tM) / $tT * 100) : 100;

  $GLOBALS['pg_crumb_group'] = 'Spiele';
  pg_view_head($g ? (string) ($g['title'] ?? 'Spiel') : 'Spiele');
  pg_view_topbar($SCHEMA, 'games');

  // Meldungen (werden als Toast gezeigt)
  if (isset($_GET['saved']))      echo '<div class="flash ok">✓ Gespeichert (' . strtoupper($lang) . '). Die Website ist aktuell.</div>';
  if (isset($_GET['created']))    echo '<div class="flash ok">✓ Spiel angelegt – jetzt Inhalte ergänzen.</div>';
  if (isset($_GET['duplicated'])) echo '<div class="flash ok">✓ Kopie angelegt.</div>';
  if (isset($_GET['deleted']))    echo '<div class="flash ok">✓ „' . pg_h($_GET['deleted']) . '“ liegt im Papierkorb und kann dort wiederhergestellt werden.</div>';
  if (isset($_GET['translated'])) echo '<div class="flash ok">🌐 ' . (int) $_GET['translated'] . ' fehlende Texte automatisch übersetzt – bitte kurz gegenlesen.</div>';
  if (isset($_GET['error']))      echo '<div class="flash err">Konnte nicht speichern/übersetzen – Schreibrechte bzw. Internet-Zugriff prüfen.</div>';
  if (isset($_GET['gerr']))       echo '<div class="flash err">' . pg_h($_GET['gerr']) . '</div>';
  if (isset($_GET['renamed'])) {
    $mc = array_map('intval', explode(',', (string) ($_GET['mc'] ?? '')));
    $parts = [];
    if (!empty($mc[0])) $parts[] = $mc[0] . ' Wartelisten-Einträge';
    if (!empty($mc[1])) $parts[] = $mc[1] . ' Vorschläge';
    if (!empty($mc[2])) $parts[] = $mc[2] . ' Board-Verweise';
    if (!empty($mc[3])) $parts[] = $mc[3] . ' Devlog-Beiträge';
    if (!empty($mc[4])) $parts[] = $mc[4] . ' Aufrufe';
    echo '<div class="flash ok">✓ Adresse geändert: /' . pg_h($_GET['renamed']) . ' → /' . pg_h($slug) . ($parts ? ' · übernommen: ' . pg_h(implode(', ', $parts)) : '') . ' · alte Adresse leitet weiter.</div>';
  }

  echo '<div class="gm ' . ($explicit ? 'has-sel' : 'no-sel') . '">';

  /* ================= Liste ================= */
  $statusCount = [];
  foreach ($games as $x) if (is_array($x)) { $st = (string) ($x['status'] ?? 'development'); $statusCount[$st] = ($statusCount[$st] ?? 0) + 1; }
  echo '<aside class="ck-card gm-list"><div class="gm-list-h"><h2>Spiele</h2><span class="gm-n">' . count($games) . '</span>'
     . '<button type="button" class="btn btn-primary btn-sm" data-open-dialog="dlg-newgame">' . pg_icon('plus') . 'Neu</button></div>';
  if (count($games) > 3) {
    echo '<label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="Spiel suchen…" data-game-search aria-label="Spiele durchsuchen"></label>';
    echo '<div class="g-seg" data-game-filter><button type="button" class="on" data-gf="">Alle<span class="n">' . count($games) . '</span></button>';
    $short = ['development' => 'Entwicklung', 'announced' => 'Angekündigt', 'demo' => 'Demo', 'early' => 'Early Access', 'released' => 'Veröffentlicht'];
    foreach ($stLbl as $k => [$l]) if (!empty($statusCount[$k])) echo '<button type="button" data-gf="' . pg_h($k) . '" title="' . pg_h($l) . '">' . pg_h($short[$k] ?? $l) . '<span class="n">' . $statusCount[$k] . '</span></button>';
    echo '</div>';
  }
  echo '<ul class="gm-rows" data-game-rows>';
  foreach ($games as $gi => $x) {
    if (!is_array($x)) continue;
    $xs = (string) ($x['slug'] ?? ''); $xst = $stLbl[$x['status'] ?? 'development'] ?? $stLbl['development'];
    [$cp] = explode('#', (string) ($x['cover'] ?? '') . '#');
    $fp = preg_match('/#focus=(\d+),(\d+)/', (string) ($x['cover'] ?? ''), $m) ? $m[1] . '% ' . $m[2] . '%' : '50% 50%';
    $acc = preg_match('/^#[0-9a-f]{6}$/i', (string) ($x['accent'] ?? '')) ? $x['accent'] : '#ff7d1a';
    $nW = count($wlBy[strtolower($xs)] ?? []);
    echo '<li class="gm-row' . ($gi === $i ? ' on' : '') . '" style="--acc:' . pg_h($acc) . '" data-slug="' . pg_h($xs) . '" data-status="' . pg_h($x['status'] ?? 'development') . '" data-q="' . pg_h(mb_strtolower(($x['title'] ?? '') . ' ' . $xs)) . '">'
       . '<a class="gm-row-a" href="index.php?collection=games&amp;game=' . rawurlencode($xs) . ($lang === 'en' ? '&amp;lang=en' : '') . '">'
       . '<span class="gm-thumb">' . ($cp !== '' ? '<img src="' . pg_h($cp) . '" alt="" loading="lazy" style="object-position:' . $fp . '">' : '') . '</span>'
       . '<span class="gm-row-tx"><span class="gm-row-t">' . pg_h($x['title'] ?? $xs) . (!empty($x['featured']) ? ' <span class="gm-star" title="Auf der Startseite hervorgehoben">★</span>' : '') . '</span>'
       . '<span class="gm-row-m"><span class="chip ' . $xst[1] . '"><i></i>' . pg_h($xst[0]) . '</span>'
       . ($nW ? '<span class="gm-wl" title="Warteliste">' . pg_icon('list') . number_format($nW, 0, ',', '.') . '</span>' : '') . '</span></span></a>'
       . '<span class="gm-grip" data-game-grip title="Ziehen, um die Reihenfolge zu ändern" aria-hidden="true">' . pg_icon('grip') . '</span></li>';
  }
  echo '</ul>';
  if (!$games) echo '<p class="gm-empty">Noch keine Spiele angelegt.</p>';
  if (count($games) > 1) echo '<p class="gm-hint">' . pg_icon('grip') . 'Ziehen, um die Reihenfolge auf der Website zu ändern</p>';
  echo '</aside>';

  /* ================= Detail ================= */
  echo '<div class="gd">';
  if (!$g) {
    echo '<div class="ck-card gd-empty">' . pg_icon('game') . '<h2>Noch kein Spiel</h2><p>Lege dein erstes Spiel an – Seite, Warteliste und Vorschlagsbox sind in wenigen Minuten startklar.</p>'
       . '<button type="button" class="btn btn-primary" data-open-dialog="dlg-newgame">' . pg_icon('plus') . 'Neues Spiel</button></div></div></div>';
    pg_gv_dialogs($games, null, -1, $host);
    pg_view_foot();
    return;
  }

  $acc = preg_match('/^#[0-9a-f]{6}$/i', (string) ($g['accent'] ?? '')) ? $g['accent'] : '#ff7d1a';
  [$cp] = explode('#', (string) ($g['cover'] ?? '') . '#');
  $fp = preg_match('/#focus=(\d+),(\d+)/', (string) ($g['cover'] ?? ''), $m) ? $m[1] . '% ' . $m[2] . '%' : '50% 50%';
  $stt = $stLbl[$g['status'] ?? 'development'] ?? $stLbl['development'];
  $preview = '../game.php?slug=' . rawurlencode($slug) . ($lang === 'en' ? '&lang=en' : '');
  $base = 'index.php?collection=games&game=' . rawurlencode($slug);

  echo '<a class="btn btn-ghost btn-sm gd-back" href="index.php?collection=games">' . pg_icon('back') . 'Alle Spiele</a>';
  echo '<form method="post" class="gd-form" id="editor" data-autosave data-preview="' . pg_h($preview) . '" data-game-editor data-lang="' . $lang . '">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
     . '<input type="hidden" name="collection" value="games"><input type="hidden" name="lang" value="' . $lang . '">'
     . '<input type="hidden" name="game_orig" value="' . pg_h($slug) . '"><input type="hidden" name="game_idx" value="' . (int) $i . '">'
     . '<input type="hidden" name="game_tab" value="' . pg_h($tab) . '" data-tab-input>';
  if ($lang === 'en') echo '<input type="hidden" name="game_sig" value="' . pg_h(pg_i18n_signature($fields, $g)) . '">';

  // ---- Kopfbereich ----
  echo '<div class="gd-hero" style="--acc:' . pg_h($acc) . '">'
     . ($cp !== '' ? '<img class="cv" src="' . pg_h($cp) . '" alt="" style="object-position:' . $fp . '">' : '')
     . '<div class="gd-hero-in"><div class="gd-id">'
     . (!empty($g['logo']) ? '<img class="lg" src="' . pg_h(explode('#', (string) $g['logo'])[0]) . '" alt="' . pg_h($g['title'] ?? '') . '"><h2 class="sm">' . pg_h($g['title'] ?? '') . '</h2>'
                           : '<h2>' . pg_h($g['title'] ?? $slug) . '</h2>')
     . '<div class="gd-pills"><span class="slug-pill" title="Web-Adresse">' . pg_icon('link') . '<span>' . pg_h($host) . '/game?slug=<b>' . pg_h($slug) . '</b></span></span>'
     . '<span class="chip ' . $stt[1] . '"><i></i>' . pg_h($stt[0]) . '</span>'
     . (!empty($g['featured']) ? '<span class="chip st-feat">★ Startseite</span>' : '') . '</div></div>'
     . '<div class="gd-acts editor-actions"><span class="save-status" data-save-status></span>'
     . '<div class="g-seg g-lang"><a class="' . ($lang === 'de' ? 'on' : '') . '" href="' . pg_h($base) . '">DE</a>'
     . '<a class="' . ($lang === 'en' ? 'on' : '') . '" href="' . pg_h($base . '&lang=en') . '">EN <span class="n' . ($enPct < 95 ? ' warn' : '') . '">' . $enPct . '%</span></a></div>'
     . '<a class="btn btn-sm" href="' . pg_h($preview) . '" target="_blank" rel="noopener">' . pg_icon('ext') . 'Ansehen</a>'
     . '<button type="button" class="btn btn-sm hide-m" data-split-toggle title="Live-Vorschau neben dem Editor">' . pg_icon('eye') . 'Vorschau</button>'
     . '<button class="btn btn-primary btn-sm" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button></div>'
     . '</div></div>';

  if ($lang === 'en') {
    /* ================= Englisch: nur Texte, mit deutschem Original ================= */
    $stat = ['t' => 0, 'm' => 0];
    // nach den Abschnitten des Schemas gruppieren (Grunddaten, Cover, Seiten-Blöcke, Lore …)
    $sections = []; $cur = ['label' => '', 'fields' => []];
    foreach ($fields as $f) {
      if (($f['widget'] ?? '') === 'heading') { if ($cur['fields']) $sections[] = $cur; $cur = ['label' => (string) $f['label'], 'fields' => []]; continue; }
      $cur['fields'][] = $f;
    }
    if ($cur['fields']) $sections[] = $cur;
    $html = '';
    foreach ($sections as $sec) {
      $inner = pg_gv_i18n_fields($sec['fields'], $g, $enAligned, 'g', $stat);
      if ($inner === '') continue;
      $kinds = array_map(fn($f) => $f['widget'] ?? '', $sec['fields']);
      if (in_array('blocks', $kinds, true)) $html .= '<h3 class="i18n-sec">' . pg_h($sec['label']) . '</h3>' . $inner;
      elseif (array_diff($kinds, ['object']) === []) $html .= $inner;
      else $html .= '<div class="i18n-group"><div class="i18n-gh">' . pg_h($sec['label'] ?: 'Allgemein') . '</div>' . $inner . '</div>';
    }
    echo '<div class="ck-card g-en-head"><div><b>Englische Fassung</b><span>' . ($stat['t'] - $stat['m']) . ' von ' . $stat['t'] . ' Texten übersetzt. '
       . 'Leere Felder zeigen auf der Website automatisch den deutschen Text. Bilder, Farben, Links & Aufbau gelten für beide Sprachen.</span></div>'
       . ($stat['m'] > 0 ? '<button class="btn btn-sm" type="submit" form="frm-game-tr">' . pg_icon('globe') . 'Fehlende automatisch übersetzen</button>' : '<span class="chip st-rel"><i></i>vollständig</span>')
       . '</div>';
    echo $html !== '' ? '<div class="g-en">' . $html . '</div>' : '<div class="ck-card gd-empty"><p>Für dieses Spiel gibt es noch keine Texte zum Übersetzen.</p></div>';
    echo '</form>';
  } else {
    /* ================= Deutsch: Reiter ================= */
    $cards = []; $ci = -1; $fine = '';
    $blocksN = is_array($g['blocks'] ?? null) ? count($g['blocks']) : 0;
    foreach ($fields as $f) {
      if (($f['widget'] ?? '') === 'heading') { $cards[] = ['tab' => $f['tab'] ?? 'settings', 'title' => $f['label'], 'hint' => $f['hint'] ?? '', 'html' => '']; $ci = count($cards) - 1; continue; }
      if ($ci < 0) { $cards[] = ['tab' => 'settings', 'title' => 'Allgemein', 'hint' => '', 'html' => '']; $ci = 0; }
      $n = $f['name']; $val = array_key_exists($n, $g) ? $g[$n] : ($f['default'] ?? null); $nm = 'g[' . $n . ']';
      if ($n === 'slug') {
        $cards[$ci]['html'] .= pg_gv_field('slug', '<div class="field"><label class="flabel">Web-Adresse</label><div class="g-slug-box">' . pg_icon('lock')
          . '<code>' . pg_h($host) . '/game?slug=<b>' . pg_h($slug) . '</b></code><button type="button" class="btn btn-sm" data-open-dialog="dlg-slug">Ändern…</button></div>'
          . '<p class="hint">Geschützt, damit sie nicht versehentlich geändert wird. Beim Ändern ziehen Warteliste, Vorschläge, Board, Devlog, Statistik & die englische Fassung automatisch mit um – die alte Adresse leitet weiter.</p></div>');
        continue;
      }
      if ($n === 'logoScale' || $n === 'logoLayout') { $fine .= pg_render_field($f, $val, $nm, 'games.' . $n); continue; }
      if ($f['widget'] === 'object' && $n === 'lore') { $cards[$ci]['html'] .= pg_gv_field($n, pg_render_fields($f['fields'] ?? [], is_array($val) ? $val : [], $nm, 'games.' . $n)); continue; }
      $cards[$ci]['html'] .= pg_gv_field($n, pg_render_field($f, $val, $nm, 'games.' . $n));
      if ($n === 'particleColor') {
        $cards[$ci]['html'] .= '<div class="g-btnprev"><span data-btn-prev>Auf Steam wishlisten</span><small>Live-Vorschau des Buttons · die Schriftfarbe passt sich automatisch an</small></div>';
      }
      if ($n === 'logo') {
        $lay = in_array($g['logoLayout'] ?? '', ['left', 'center', 'split'], true) ? $g['logoLayout'] : 'left';
        $layTxt = ['left' => 'Links über dem Text', 'center' => 'Mittig – alles zentriert', 'split' => 'Logo rechts neben dem Text'][$lay];
        $cards[$ci]['html'] .= '<div class="g-logo-row"><div class="g-logo-prev" data-logo-prev>' . (!empty($g['logo']) ? '<img src="' . pg_h(explode('#', (string) $g['logo'])[0]) . '" alt="">' : '<span>kein Logo</span>') . '</div>'
          . '<div class="g-logo-tx"><b data-logo-state>' . pg_h($layTxt) . '</b>'
          . '<span>Größe <span data-logo-scale>' . (int) ($g['logoScale'] ?? 100) . '</span> % · steht immer im Textblock, auf dem Handy mittig</span>'
          . '<button type="button" class="btn btn-primary btn-sm" data-open-placer>' . pg_icon('palette') . 'Kopfbereich gestalten</button></div></div>'
          . '<details class="g-fine"><summary>Anordnung &amp; Größe direkt einstellen</summary>@@FINE@@</details>';
      }
    }
    foreach ($cards as $k => $c) $cards[$k]['html'] = str_replace('@@FINE@@', $fine, $c['html']);
    $panel = function ($tab) use ($cards) {
      $h = '';
      foreach ($cards as $c) if ($c['tab'] === $tab) $h .= pg_gv_card($tab === 'look' ? 'palette' : ($tab === 'page' ? 'cockpit' : 'settings'), $c['title'], $c['html'], '', $c['hint']);
      return $h;
    };

    // ---- Kennzahlen & Checks ----
    $wlG = $wlBy[$lslug] ?? []; $sgG = $sgBy[$lslug] ?? [];
    $sgOpen = count(array_filter($sgG, fn($s) => empty($s['approved'])));
    $cut = date('Y-m-d', strtotime('-6 day'));
    $wl7 = count(array_filter($wlG, fn($e) => substr((string) ($e['date'] ?? ''), 0, 10) >= $cut));
    $views = $viewsOf($slug);
    $types = array_map(fn($b) => is_array($b) ? ($b['type'] ?? '') : '', is_array($g['blocks'] ?? null) ? $g['blocks'] : []);
    $hasTrailer = in_array('trailer', $types, true);
    foreach ((array) ($g['blocks'] ?? []) as $b) if (is_array($b) && ($b['type'] ?? '') === 'hero' && !empty($b['video'])) $hasTrailer = true;
    $checks = [
      [!empty($g['cover']), 'Cover-Bild', 'Für Karten, Startseite & Teilen-Vorschau', 'look', 'cover'],
      [!empty($g['logo']), 'Logo (transparent)', 'Erscheint im Kopfbereich statt des Titels', 'look', 'logo'],
      [trim((string) ($g['tagline'] ?? '')) !== '', 'Kurzbeschreibung', 'Für Karten, Google & Teilen-Vorschau', 'settings', 'tagline'],
      [!empty($g['wishlistUrl']), 'Store-/Wishlist-Link', 'Haupt-Button im Kopfbereich', 'settings', 'wishlistUrl'],
      [$hasTrailer, 'Trailer', 'Erhöht die Verweildauer deutlich', 'page', 'blocks'],
      [in_array('waitlist', $types, true) || in_array('suggestions', $types, true), 'Warteliste oder Vorschlagsbox', 'Sammelt Interessenten & Ideen', 'page', 'blocks'],
      [trim((string) ($g['coverAlt'] ?? '')) !== '', 'Alt-Text fürs Cover', 'Barrierefreiheit & Google-Bildersuche', 'look', 'coverAlt'],
      [$enPct >= 95, 'Englische Fassung', $enPct >= 95 ? 'vollständig' : (100 - $enPct) . ' % der Texte fehlen noch', 'en', ''],
    ];
    $chk = '<ul class="g-check">';
    foreach ($checks as [$ok, $t, $m, $tb, $fld]) {
      $fix = $ok ? '' : ($tb === 'en' ? '<a class="btn btn-sm" href="' . pg_h($base . '&lang=en') . '">Übersetzen</a>'
                                      : '<button type="button" class="btn btn-sm" data-goto-tab="' . $tb . '" data-goto-field="' . $fld . '">Ergänzen</button>');
      $chk .= '<li><span class="' . ($ok ? 'ok' : 'no') . '">' . pg_icon($ok ? 'check' : 'alert') . '</span><div><b>' . pg_h($t) . '</b><small>' . pg_h($m) . '</small></div>' . $fix . '</li>';
    }
    $chk .= '</ul>';
    usort($sgG, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
    $sList = function ($list, $max) {
      if (!$list) return '<p class="g-empty-s">Noch keine Vorschläge.</p>';
      $h = '<ul class="g-slist">';
      foreach (array_slice($list, 0, $max) as $s) {
        $h .= '<li><div class="votes"><b>' . (int) ($s['votes'] ?? 0) . '</b><span>Stimmen</span></div><div class="g-s-tx"><div>' . pg_h(mb_strimwidth((string) ($s['text'] ?? ''), 0, 140, '…')) . '</div>'
            . '<small>' . (empty($s['approved']) ? '<span class="g-pend">wartet auf Freigabe</span>' : 'freigegeben') . (!empty($s['date']) ? ' · ' . pg_h(pg_time_ago(strtotime((string) $s['date']) ?: time())) : '') . '</small></div></li>';
      }
      return $h . '</ul>';
    };

    $tabs = [['overview', 'Übersicht', ''], ['page', 'Seiten-Aufbau', $blocksN ?: ''], ['look', 'Darstellung', ''],
             ['community', 'Community', $sgOpen ?: ''], ['settings', 'Einstellungen', '']];
    echo '<div class="gd-tabs" role="tablist">';
    foreach ($tabs as [$k, $l, $n]) echo '<button type="button" role="tab" class="' . ($tab === $k ? 'on' : '') . '" data-tab="' . $k . '">' . pg_h($l) . ($n !== '' ? '<span class="tn' . ($k === 'community' ? ' hot' : '') . '">' . (int) $n . '</span>' : '') . '</button>';
    echo '</div>';

    // Übersicht
    echo '<div class="gd-panel' . ($tab === 'overview' ? ' on' : '') . '" data-panel="overview">';
    echo '<div class="g-stat3"><div class="ck-card g-mini"><b>' . number_format(count($wlG), 0, ',', '.') . '</b><span>auf der Warteliste' . ($wl7 ? ' · +' . $wl7 . ' diese Woche' : '') . '</span></div>'
       . '<div class="ck-card g-mini"><b>' . count($sgG) . '</b><span>Vorschläge' . ($sgOpen ? ' · ' . $sgOpen . ' offen' : '') . '</span></div>'
       . '<div class="ck-card g-mini"><b>' . number_format($views, 0, ',', '.') . '</b><span>Seitenaufrufe gesamt</span></div></div>';
    echo '<div class="g-grid2">' . pg_gv_card('check', 'Seiten-Check', $chk)
       . pg_gv_card('bulb', 'Neueste Vorschläge', $sList($sgG, 4), '<button type="button" class="ck-more" data-tab="community">Alle' . pg_icon('chev') . '</button>') . '</div>';
    echo '</div>';

    // Seiten-Aufbau
    echo '<div class="gd-panel' . ($tab === 'page' ? ' on' : '') . '" data-panel="page">' . $panel('page') . '</div>';
    // Darstellung
    echo '<div class="gd-panel' . ($tab === 'look' ? ' on' : '') . '" data-panel="look"><div class="g-look">' . $panel('look') . '</div></div>';

    // Community
    $spark = pg_spark_svg(array_values(pg_daily_counts($wlG, 21)), '#3ddc97');
    $wlCard = '<div class="g-big">' . number_format(count($wlG), 0, ',', '.') . ($wl7 ? '<span class="ck-delta up">+' . $wl7 . ' diese Woche</span>' : '') . '</div>' . $spark
      . '<div class="g-acts">' . (pg_can('subscribers') ? '<a class="btn btn-sm" href="index.php?view=waitlist">' . pg_icon('list') . 'Warteliste öffnen</a><a class="btn btn-sm btn-ghost" href="index.php?action=waitlist_csv">' . pg_icon('download') . 'CSV</a>' : '') . '</div>';
    $posts = [];
    foreach ((array) (pg_load_json(PG_DATA_DIR . '/patchnotes.json')['posts'] ?? []) as $p) if (is_array($p) && strtolower(trim((string) ($p['game'] ?? ''))) === $lslug) $posts[] = $p;
    usort($posts, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
    $pHtml = $posts ? '<ul class="g-plist">' . implode('', array_map(fn($p) => '<li><span>' . pg_h((string) ($p['date'] ?? '')) . '</span><b>' . pg_h((string) ($p['title'] ?? '')) . '</b>' . (!empty($p['draft']) ? '<span class="chip">Entwurf</span>' : '') . '</li>', array_slice($posts, 0, 6))) . '</ul>'
                    : '<p class="g-empty-s">Noch keine Devlog-Beiträge diesem Spiel zugeordnet.</p>';
    $cards2 = [];
    foreach ((array) (pg_board_load()['boards'] ?? []) as $bd) foreach ((array) ($bd['columns'] ?? []) as $col) foreach ((array) ($col['cards'] ?? []) as $card)
      if (is_array($card) && empty($card['archived']) && strtolower((string) ($card['game'] ?? '')) === $lslug) $cards2[] = $card + ['_col' => (string) ($col['name'] ?? $col['title'] ?? '')];
    $bHtml = $cards2 ? '<ul class="g-plist">' . implode('', array_map(fn($c) => '<li><span>' . pg_h($c['_col']) . '</span><b>' . pg_h((string) ($c['title'] ?? '')) . '</b>' . (!empty($c['due']) ? '<span class="chip">📅 ' . pg_h($c['due']) . '</span>' : '') . '</li>', array_slice($cards2, 0, 6))) . '</ul>'
                     : '<p class="g-empty-s">Keine offenen Karten für dieses Spiel.</p>';
    echo '<div class="gd-panel' . ($tab === 'community' ? ' on' : '') . '" data-panel="community"><div class="g-grid2">'
       . pg_gv_card('list', 'Beta-Warteliste', $wlCard)
       . pg_gv_card('bulb', 'Vorschläge', $sList(array_values(array_merge(array_filter($sgG, fn($s) => empty($s['approved'])), array_filter($sgG, fn($s) => !empty($s['approved'])))), 8),
           pg_can('contacts') ? '<a class="ck-more" href="index.php?view=suggestions">Prüfen' . pg_icon('chev') . '</a>' : '')
       . pg_gv_card('devlog', 'Devlog-Beiträge', $pHtml, pg_can('patchnotes') ? '<a class="ck-more" href="index.php?collection=patchnotes">Devlog' . pg_icon('chev') . '</a>' : '')
       . pg_gv_card('kanban', 'Planungs-Board', $bHtml, '<a class="ck-more" href="index.php?view=board">Board' . pg_icon('chev') . '</a>')
       . '</div></div>';

    // Einstellungen
    $serp = '<div class="g-serp"><div class="u">' . pg_h($host) . ' › game › ' . pg_h($slug) . '</div><div class="t" data-serp-title>' . pg_h(($g['title'] ?? '') . ' — PLANIGAMES') . '</div>'
          . '<div class="d" data-serp-desc>' . pg_h(mb_strimwidth(trim((string) ($g['tagline'] ?? '')), 0, 158, ' …')) . '</div></div>'
          . '<p class="hint">Titel & Beschreibung kommen automatisch aus Spielname und Kurzbeschreibung.</p>';
    $danger = '<div class="g-danger"><div><b>Duplizieren</b><span>Legt eine Kopie mit allen Blöcken an (z. B. als Vorlage).</span></div><button class="btn btn-sm" type="submit" form="frm-game-dup">' . pg_icon('copy') . 'Duplizieren</button></div>'
            . '<div class="g-danger"><div><b>In den Papierkorb</b><span>Die Seite verschwindet von der Website. Warteliste & Vorschläge bleiben gespeichert; wiederherstellbar im Papierkorb.</span></div><button class="btn btn-sm g-btn-danger" type="submit" form="frm-game-del">' . pg_icon('trash') . 'Löschen</button></div>';
    echo '<div class="gd-panel' . ($tab === 'settings' ? ' on' : '') . '" data-panel="settings"><div class="g-grid2 g-settings">'
       . '<div class="g-col">' . $panel('settings') . '</div><div class="g-col">' . pg_gv_card('search', 'So erscheint die Seite bei Google', $serp) . pg_gv_card('alert', 'Aktionen', $danger) . '</div>'
       . '</div></div>';

    echo '<div class="editor-foot g-foot"><span class="hint">Strg/⌘ + S speichert · Änderungen sind sofort live.</span><button class="btn btn-primary" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button></div>';
    echo '</form>';
  }
  echo '</div></div>';   // .gd, .gm

  // Hilfsformulare (außerhalb des Editors – Formulare dürfen nicht verschachtelt sein)
  $hidden = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="idx" value="' . (int) $i . '"><input type="hidden" name="orig" value="' . pg_h($slug) . '">';
  echo '<form id="frm-game-dup" method="post" action="index.php?action=game_duplicate" hidden>' . $hidden . '</form>';
  echo '<form id="frm-game-del" method="post" action="index.php?action=game_delete" hidden data-confirm="„' . pg_h($g['title'] ?? $slug) . '“ in den Papierkorb legen? Die Spielseite ist dann nicht mehr erreichbar. Warteliste & Vorschläge bleiben gespeichert.">' . $hidden . '</form>';
  echo '<form id="frm-game-tr" method="post" action="index.php?action=game_translate" hidden data-confirm="Fehlende englische Texte automatisch übersetzen? Vorhandene Übersetzungen bleiben unverändert. Das kann einen Moment dauern.">' . $hidden . '</form>';
  pg_gv_dialogs($games, $g, $i, $host);

  // Daten für die Logo-Platzierung (echte Texte der Spielseite)
  echo '<script>window.PG_GAME=' . json_encode(['title' => (string) ($g['title'] ?? ''), 'status' => $stt[0], 'statusKey' => (string) ($g['status'] ?? 'development')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
  pg_view_foot();
}

/* Dialoge: Neues Spiel, Web-Adresse ändern */
function pg_gv_dialogs($games, $g, $i, $host){
  $csrf = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<div class="pg-modal-back" id="dlg-newgame" hidden><form class="pg-modal" method="post" action="index.php?action=game_create" role="dialog" aria-modal="true" aria-labelledby="dlg-newgame-t">' . $csrf
     . '<div class="pg-modal-h"><div><h3 id="dlg-newgame-t">Neues Spiel</h3><p>Startet mit Kopfbereich und Textabschnitt – alles Weitere ergänzt du im Editor.</p></div><button type="button" class="icon-btn" data-close aria-label="Schließen">' . pg_icon('x') . '</button></div>'
     . '<div class="pg-modal-b"><div class="field"><label class="flabel" for="ng-title">Titel</label><input id="ng-title" name="title" required maxlength="80" autocomplete="off" data-autofocus data-slug-source></div>'
     . '<p class="hint">Web-Adresse: ' . pg_h($host) . '/game?slug=<b data-slug-preview>…</b> (später änderbar)</p></div>'
     . '<div class="pg-modal-f"><span class="sp"></span><button type="button" class="btn btn-ghost" data-close>Abbrechen</button><button class="btn btn-primary">' . pg_icon('plus') . 'Anlegen</button></div></form></div>';
  if (!$g) return;
  $slug = (string) ($g['slug'] ?? '');
  $taken = [];
  foreach ($games as $k => $x) if ($k !== $i && is_array($x) && !empty($x['slug'])) $taken[] = strtolower((string) $x['slug']);
  $suggest = pg_slugify($g['title'] ?? '');
  if ($suggest === '' || in_array($suggest, $taken, true)) $suggest = $slug;
  $r = pg_game_refs($slug);
  $li = function ($n, $t) { return '<li>' . pg_icon('check') . ($n !== '' ? '<b>' . pg_h($n) . '</b>' : '') . pg_h($t) . '</li>'; };
  $mig = $li(number_format($r['waitlist'], 0, ',', '.'), 'Wartelisten-Einträge inkl. Platzierung & Empfehlungen')
       . $li((string) $r['suggestions'], 'Vorschläge inkl. Stimmen')
       . $li((string) $r['board'], 'Verweise im Planungs-Board')
       . $li((string) $r['posts'], 'zugeordnete Devlog-Beiträge')
       . $li(number_format($r['views'], 0, ',', '.'), 'Seitenaufrufe in der Statistik')
       . ($r['en'] ? $li('', 'englische Fassung & Lore-Seite') : '');
  echo '<div class="pg-modal-back" id="dlg-slug" hidden><form class="pg-modal" method="post" action="index.php?action=game_rename" role="dialog" aria-modal="true" aria-labelledby="dlg-slug-t" data-slug-form'
     . ' data-taken="' . pg_h(json_encode($taken)) . '" data-current="' . pg_h($slug) . '">' . $csrf
     . '<input type="hidden" name="idx" value="' . (int) $i . '"><input type="hidden" name="orig" value="' . pg_h($slug) . '">'
     . '<div class="pg-modal-h"><div><h3 id="dlg-slug-t">Web-Adresse ändern</h3><p>Alles, was am Spiel hängt, zieht automatisch mit um.</p></div><button type="button" class="icon-btn" data-close aria-label="Schließen">' . pg_icon('x') . '</button></div>'
     . '<div class="pg-modal-b"><div class="field"><label class="flabel" for="sl-in">Neue Adresse</label>'
     . '<div class="slug-in"><span>' . pg_h($host) . '/game?slug=</span><input id="sl-in" name="slug" value="' . pg_h($suggest) . '" spellcheck="false" autocomplete="off" data-slug-input data-autofocus></div>'
     . '<div class="slug-err" data-slug-err></div></div>'
     . '<div class="mig"><div class="mig-h">' . pg_icon('check') . 'Wird automatisch mitgenommen</div><ul>' . $mig . '</ul>'
     . '<div class="redir">' . pg_icon('link') . '<div>Die alte Adresse <b class="mono">/game?slug=' . pg_h($slug) . '</b> leitet <b>dauerhaft weiter</b> (301). Geteilte Links, Google-Einträge und Links in verschickten Mails funktionieren weiter.</div></div>'
     . '<p class="mig-note">' . pg_icon('archive') . 'Vorher wird automatisch eine Sicherheitskopie aller Daten angelegt (unter „Backups“).</p></div></div>'
     . '<div class="pg-modal-f"><span class="sp"></span><button type="button" class="btn btn-ghost" data-close>Abbrechen</button><button class="btn btn-primary" data-slug-save>' . pg_icon('check') . 'Adresse ändern</button></div></form></div>';
}
