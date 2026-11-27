<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
/**
 * Statistik – privatsphärefreundlich (ohne Cookies, ohne IP). Der Zeitraum oben gilt für
 * Kennzahlen und Verlauf; Seiten, Quellen und Regionen werden seit Beginn der Zählung summiert.
 * Diagrammfarbe #f06f15 ist auf dem dunklen Hintergrund geprüft (Helligkeit, Sättigung, Kontrast).
 */
function pg_stats_page_label($key, $games, $posts){
  $k = (string) $key;
  $fixed = ['home' => 'Startseite', 'games' => 'Spiele-Übersicht', 'devlog' => 'Devlog', 'kontakt' => 'Kontakt', 'presse' => 'Press Kit',
            'rechtliches' => 'Rechtliches', '404' => 'Seite nicht gefunden', 'unbekannt' => 'Unbekannt', 'page' => 'Sonstige Seite'];
  if (isset($fixed[$k])) return $fixed[$k];
  if (preg_match('/^(game|lore|devlog):(.+)$/i', $k, $m)) {
    $s = strtolower($m[2]);
    if ($m[1] === 'game') return $games[$s] ?? $m[2];
    if ($m[1] === 'lore') return 'Lore · ' . ($games[$s] ?? $m[2]);
    return 'Devlog · ' . ($posts[$s] ?? $m[2]);
  }
  return $k;
}
function pg_stats_region_label($code){
  $c = strtoupper((string) $code);
  $map = ['DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'US' => 'USA', 'GB' => 'Großbritannien', 'FR' => 'Frankreich', 'NL' => 'Niederlande',
          'PL' => 'Polen', 'IT' => 'Italien', 'ES' => 'Spanien', 'SE' => 'Schweden', 'DK' => 'Dänemark', 'NO' => 'Norwegen', 'FI' => 'Finnland', 'BE' => 'Belgien',
          'CZ' => 'Tschechien', 'CA' => 'Kanada', 'AU' => 'Australien', 'BR' => 'Brasilien', 'RU' => 'Russland', 'UA' => 'Ukraine', 'TR' => 'Türkei', 'JP' => 'Japan',
          'KR' => 'Südkorea', 'CN' => 'China', 'IN' => 'Indien', 'PT' => 'Portugal', 'HU' => 'Ungarn', 'LU' => 'Luxemburg', 'IE' => 'Irland',
          'EN' => 'Englisch (Sprache)'];
  return ($map[$c] ?? '') !== '' ? $map[$c] : $c;
}
// Balkenliste (eine Farbe, Wert am Balkenende) – zugleich die Tabellenansicht der Werte
function pg_stats_bars($rows, $empty, $max = 10){
  if (!$rows) return '<p class="st-empty">' . pg_h($empty) . '</p>';
  $rows = array_slice($rows, 0, $max, true);
  $top = max(1, max($rows)); $sum = array_sum($rows);
  $h = '<ol class="st-bars">';
  foreach ($rows as $label => $n) {
    $h .= '<li><div class="st-bar-row"><span class="st-bar-l">' . pg_h($label) . '</span><span class="st-bar-v">' . number_format((int) $n, 0, ',', '.')
        . '<small>' . ($sum ? round($n / $sum * 100) : 0) . ' %</small></span></div>'
        . '<span class="st-bar" aria-hidden="true"><i style="width:' . max(1.5, round($n / $top * 100, 1)) . '%"></i></span></li>';
  }
  return $h . '</ol>';
}

function pg_stats_view($SCHEMA){
  $s = pg_load_json(PG_DATA_DIR . '/stats.json');
  $range = (int) ($_GET['range'] ?? 30);
  if (!in_array($range, [7, 14, 30, 90], true)) $range = 30;
  $daysMap = (array) ($s['days'] ?? []);
  $cur = pg_stats_series($daysMap, $range);
  $both = pg_stats_series($daysMap, $range * 2);
  $prev = array_slice($both, 0, $range, true);
  $sumCur = array_sum($cur); $sumPrev = array_sum($prev);
  $delta = $sumPrev > 0 ? (int) round(($sumCur - $sumPrev) / $sumPrev * 100) : null;
  $today = (int) ($daysMap[date('Y-m-d')] ?? 0);
  $total = (int) ($s['total'] ?? 0);
  $from = date('Y-m-d', strtotime('-' . ($range - 1) . ' day'));
  $inRange = fn($list) => count(array_filter((array) $list, fn($r) => is_array($r) && substr((string) ($r['date'] ?? ''), 0, 10) >= $from));

  // Ziele
  $canSubs = pg_can('subscribers'); $canCont = pg_can('contacts');
  $subs = $canSubs ? (array) pg_load_json(PG_DATA_DIR . '/subscribers.json') : [];
  $wl = $canSubs ? array_filter((array) pg_load_json(PG_DATA_DIR . '/waitlist.json'), fn($e) => is_array($e) && !empty($e['email'])) : [];
  $cont = $canCont ? (array) pg_load_json(PG_DATA_DIR . '/contacts.json') : [];
  $signRange = $inRange($subs) + $inRange($wl);
  $quote = $sumCur > 0 ? round($signRange / $sumCur * 100, 1) : 0;

  // Namen für Seiten
  $games = []; foreach ((array) (pg_load_json(PG_DATA_DIR . '/games.json')['games'] ?? []) as $g) if (is_array($g) && !empty($g['slug'])) $games[strtolower($g['slug'])] = (string) ($g['title'] ?? $g['slug']);
  $posts = []; foreach ((array) (pg_load_json(PG_DATA_DIR . '/patchnotes.json')['posts'] ?? []) as $p) if (is_array($p) && !empty($p['slug'])) $posts[strtolower($p['slug'])] = (string) ($p['title'] ?? $p['slug']);
  $pages = []; foreach ((array) ($s['pages'] ?? []) as $k => $n) { $l = pg_stats_page_label($k, $games, $posts); $pages[$l] = ($pages[$l] ?? 0) + (int) $n; }
  arsort($pages);
  $refs = array_map('intval', (array) ($s['refs'] ?? [])); arsort($refs);
  $regions = []; foreach ((array) ($s['regions'] ?? []) as $k => $n) { $l = pg_stats_region_label($k); $regions[$l] = ($regions[$l] ?? 0) + (int) $n; }
  arsort($regions);

  $fmt = fn($n) => number_format((float) $n, 0, ',', '.');
  $GLOBALS['pg_crumb_group'] = 'Planung & Werkzeuge';
  pg_view_head('Statistik');
  pg_view_topbar($SCHEMA, null);

  echo '<div class="st">';
  echo '<div class="sx-head"><div><h1>Statistik</h1><p>Aufrufe deiner Website – ohne Cookies, ohne IP-Speicherung, ohne externe Dienste.</p></div>'
     . '<div class="gd-acts"><a class="btn btn-sm" href="index.php?action=stats_csv">' . pg_icon('download') . 'CSV-Export</a></div></div>';

  // ---- Zeitraum (gilt für alles bis zur Trennlinie) ----
  echo '<div class="st-filter"><span>' . pg_icon('cal') . 'Zeitraum</span><div class="g-seg" role="tablist" aria-label="Zeitraum">';
  foreach ([7 => '7 Tage', 14 => '14 Tage', 30 => '30 Tage', 90 => '90 Tage'] as $r => $l)
    echo '<a role="tab" class="' . ($r === $range ? 'on' : '') . '" href="index.php?view=stats&amp;range=' . $r . '"' . ($r === $range ? ' aria-selected="true"' : '') . '>' . $l . '</a>';
  echo '</div><small>' . pg_h(date('d.m.Y', strtotime($from))) . ' – heute</small></div>';

  // ---- Kennzahlen ----
  $tiles = [
    ['Aufrufe', $fmt($sumCur), $delta === null ? '' : ($delta >= 0 ? '+' : '') . $delta . ' %', $delta === null || $delta >= 0 ? 'up' : 'down', 'gegenüber den ' . $range . ' Tagen davor'],
    ['Ø pro Tag', number_format($range ? $sumCur / $range : 0, 1, ',', '.'), '', 'up', 'im Zeitraum'],
    ['Heute', $fmt($today), '', 'up', 'seit Mitternacht'],
    ['Gesamt', $fmt($total), '', 'up', 'seit Beginn der Zählung'],
  ];
  echo '<div class="st-tiles">';
  foreach ($tiles as [$l, $v, $d, $dir, $sub]) echo '<div class="ck-card st-tile"><span class="st-tile-l">' . pg_h($l) . '</span><span class="st-tile-v">' . pg_h($v)
     . ($d !== '' ? '<span class="ck-delta ' . $dir . '">' . pg_h($d) . '</span>' : '') . '</span><span class="st-tile-s">' . pg_h($sub) . '</span></div>';
  echo '</div>';

  // ---- Verlauf ----
  $data = []; foreach ($cur as $d => $n) $data[] = ['d' => $d, 'v' => (int) $n];
  echo '<section class="ck-card st-chartcard"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('chart') . 'Aufrufe pro Tag</span><span class="st-sub">letzte ' . $range . ' Tage</span></div>';
  if (!$sumCur && !$total) echo '<p class="st-empty">Noch keine Daten. Sobald die Website besucht wird, erscheinen hier die Aufrufe.</p>';
  echo '<div class="st-chart" data-st-chart tabindex="0" role="img" aria-label="Aufrufe pro Tag, letzte ' . $range . ' Tage: insgesamt ' . $fmt($sumCur) . '">'
     . '<script type="application/json">' . json_encode($data) . '</script></div>'
     . '<details class="st-table"><summary>Als Tabelle anzeigen</summary><table><thead><tr><th>Datum</th><th>Aufrufe</th></tr></thead><tbody>';
  foreach (array_reverse($cur, true) as $d => $n) echo '<tr><td>' . pg_h(date('d.m.Y', strtotime($d))) . '</td><td>' . $fmt($n) . '</td></tr>';
  echo '</tbody></table></details></section>';

  // ---- Ziele ----
  if ($canSubs || $canCont) {
    echo '<div class="st-goals">';
    if ($canSubs) {
      echo '<div class="ck-card st-tile"><span class="st-tile-l">Beta-Warteliste</span><span class="st-tile-v">' . $fmt(count($wl)) . '<span class="ck-delta up">+' . $inRange($wl) . '</span></span><span class="st-tile-s">neu im Zeitraum: ' . $inRange($wl) . '</span></div>';
      echo '<div class="ck-card st-tile"><span class="st-tile-l">Newsletter</span><span class="st-tile-v">' . $fmt(count($subs)) . '<span class="ck-delta up">+' . $inRange($subs) . '</span></span><span class="st-tile-s">neu im Zeitraum: ' . $inRange($subs) . '</span></div>';
      echo '<div class="ck-card st-tile"><span class="st-tile-l">Anmeldequote</span><span class="st-tile-v">' . number_format($quote, 1, ',', '.') . ' %</span><span class="st-tile-s">Anmeldungen je Aufruf im Zeitraum</span></div>';
    }
    if ($canCont) echo '<div class="ck-card st-tile"><span class="st-tile-l">Kontaktanfragen</span><span class="st-tile-v">' . $fmt($inRange($cont)) . '</span><span class="st-tile-s">im Zeitraum · ' . $fmt(count($cont)) . ' insgesamt</span></div>';
    echo '</div>';
  }

  // ---- Seit Beginn der Zählung ----
  echo '<div class="st-sep"><span>Seit Beginn der Zählung</span></div>';
  echo '<div class="st-cols">'
     . '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('ext') . 'Beliebteste Seiten</span></div>' . pg_stats_bars($pages, 'Noch keine Daten.') . '</section>'
     . '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('link') . 'Woher die Besucher kommen</span></div>' . pg_stats_bars($refs, 'Bisher nur Direktaufrufe – Verweise erscheinen, sobald jemand über einen Link kommt.') . '</section>'
     . '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('globe') . 'Regionen</span><span class="st-sub">aus der Browsersprache geschätzt</span></div>' . pg_stats_bars($regions, 'Noch keine Daten.') . '</section>'
     . '</div></div>';
  pg_view_foot();
}
