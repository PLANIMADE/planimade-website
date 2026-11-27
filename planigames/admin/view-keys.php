<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
require_once __DIR__ . '/view-community.php';                   // Seitenkopf & Kacheln
/**
 * Keys pro Spiel: hinzufügen (doppelte werden übersprungen), vergeben, als eingelöst markieren,
 * an die Warteliste verteilen. Verarbeitung in index.php (keys_add, key_assign, …).
 */
function pg_keys_view($SCHEMA){
  $keys = pg_keys_load();
  $games = [];
  foreach (pg_games_data('de')['games'] as $g) if (is_array($g) && !empty($g['slug'])) $games[strtolower($g['slug'])] = (string) ($g['title'] ?? $g['slug']);
  $per = []; $noGame = 0;
  foreach ($keys as $k) { $gs = strtolower((string) ($k['game'] ?? '')); if ($gs === '' || !isset($games[$gs])) $noGame++; else $per[$gs] = ($per[$gs] ?? 0) + 1; }
  $tab = strtolower(preg_replace('/[^a-z0-9\-_]/i', '', (string) ($_GET['game'] ?? '')));
  if ($tab === '' || ($tab !== '_none' && !isset($games[$tab]))) $tab = $noGame && !$per ? '_none' : (array_key_first($per) ?? (array_key_first($games) ?? '_none'));
  $inTab = fn($k) => $tab === '_none' ? (($gs = strtolower((string) ($k['game'] ?? ''))) === '' || !isset($games[$gs])) : strtolower((string) ($k['game'] ?? '')) === $tab;
  $rows = array_values(array_filter($keys, $inTab));
  $cnt = ['available' => 0, 'assigned' => 0, 'redeemed' => 0];
  foreach ($rows as $k) { $st = $k['status'] ?? 'available'; if (isset($cnt[$st])) $cnt[$st]++; }
  $filter = in_array($_GET['filter'] ?? '', ['available', 'assigned', 'redeemed'], true) ? $_GET['filter'] : '';
  $stLabels = ['available' => 'Verfügbar', 'assigned' => 'Vergeben', 'redeemed' => 'Eingelöst'];
  $stCls = ['available' => 'st-offen', 'assigned' => 'st-beantwortet', 'redeemed' => 'st-erledigt'];
  $ret = 'game=' . rawurlencode($tab) . ($filter !== '' ? '&filter=' . $filter : '');
  $csrf = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="ret" value="' . pg_h($ret) . '">';
  $gameOpts = function ($sel) use ($games) {
    $h = '';
    foreach ($games as $s => $t) $h .= '<option value="' . pg_h($s) . '"' . ($s === $sel ? ' selected' : '') . '>' . pg_h($t) . '</option>';
    return $h;
  };
  $GLOBALS['pg_crumb_group'] = 'Spielbetrieb';
  pg_view_head('Keys');
  pg_view_topbar($SCHEMA, null);
  if (isset($_GET['added'])) echo '<div class="flash ok">✓ ' . (int) $_GET['added'] . ' Key' . ((int) $_GET['added'] === 1 ? '' : 's') . ' hinzugefügt' . (!empty($_GET['dup']) ? ' · ' . (int) $_GET['dup'] . ' doppelte übersprungen' : '') . '.</div>';
  if (isset($_GET['moved'])) echo '<div class="flash ok">✓ ' . (int) $_GET['moved'] . ' Keys zugeordnet.</div>';
  echo '<div class="cm">';
  $tabTitle = $tab === '_none' ? 'ohne Spiel' : ($games[$tab] ?? $tab);
  echo pg_page_head('Keys', 'Steam-/itch-Keys pro Spiel – an Presse &amp; Streamer vergeben oder direkt an die Warteliste verteilen.',
    ($keys ? '<a class="btn btn-sm" href="index.php?action=keys_export">' . pg_icon('download') . 'CSV</a>' : '')
    . ($tab !== '_none' && $cnt['available'] && pg_can('mail') && pg_can('subscribers') ? '<a class="btn btn-primary btn-sm" href="index.php?view=waitlist_mail&amp;scope=' . rawurlencode($tab) . '&amp;keys=1">' . pg_icon('send') . 'An Warteliste verteilen</a>' : ''));
  // Reiter pro Spiel
  echo '<div class="g-seg cm-tabs">';
  foreach ($games as $s => $t) echo '<a class="' . ($s === $tab ? 'on' : '') . '" href="index.php?view=keys&amp;game=' . rawurlencode($s) . '">' . pg_h($t) . '<span class="n">' . ($per[$s] ?? 0) . '</span></a>';
  if ($noGame) echo '<a class="' . ($tab === '_none' ? 'on' : '') . '" href="index.php?view=keys&amp;game=_none">Ohne Spiel<span class="n warn">' . $noGame . '</span></a>';
  echo '</div>';
  echo '<div class="st-tiles">' . pg_cm_tile('Verfügbar', (string) $cnt['available'], $tabTitle) . pg_cm_tile('Vergeben', (string) $cnt['assigned'], 'noch nicht eingelöst')
     . pg_cm_tile('Eingelöst', (string) $cnt['redeemed'], 'erledigt') . pg_cm_tile('Gesamt', (string) count($rows), 'Keys in diesem Spiel') . '</div>';

  echo '<div class="ky-grid">';
  // ---- Hinzufügen ----
  echo '<section class="ck-card ky-add"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('plus') . 'Keys hinzufügen</span></div>'
     . '<form method="post" class="ky-form">' . $csrf
     . '<label class="ml">Spiel</label><select name="game" required>' . ($games ? $gameOpts($tab !== '_none' ? $tab : '') : '<option value="">– zuerst ein Spiel anlegen –</option>') . '</select>'
     . '<label class="ml">Plattform</label><input type="text" name="platform" value="Steam" list="ky-platforms"><datalist id="ky-platforms"><option value="Steam"><option value="itch.io"><option value="Epic"><option value="GOG"><option value="Xbox"><option value="PlayStation"></datalist>'
     . '<label class="ml">Keys – einer pro Zeile</label><textarea name="bulk" rows="7" placeholder="XXXXX-XXXXX-XXXXX&#10;YYYYY-YYYYY-YYYYY" required spellcheck="false"></textarea>'
     . '<p class="hint">Doppelte Keys werden automatisch übersprungen.</p>'
     . '<button class="btn btn-primary btn-sm" name="keys_add" value="1">' . pg_icon('plus') . 'Hinzufügen</button></form>';
  if ($tab === '_none' && $rows && $games)
    echo '<form method="post" class="ky-form ky-move">' . $csrf . '<label class="ml">Alle ' . count($rows) . ' Keys ohne Spiel zuordnen</label><select name="game">' . $gameOpts('') . '</select>'
       . '<button class="btn btn-sm" name="keys_set_game" value="1">' . pg_icon('game') . 'Zuordnen</button></form>';
  echo '</section>';
  // ---- Liste ----
  echo '<section class="ck-card"><div class="cm-tbar"><div class="g-seg">'
     . '<a class="' . ($filter === '' ? 'on' : '') . '" href="index.php?view=keys&amp;game=' . rawurlencode($tab) . '">Alle<span class="n">' . count($rows) . '</span></a>';
  foreach ($stLabels as $k => $l) echo '<a class="' . ($filter === $k ? 'on' : '') . '" href="index.php?view=keys&amp;game=' . rawurlencode($tab) . '&amp;filter=' . $k . '">' . $l . '<span class="n">' . $cnt[$k] . '</span></a>';
  echo '</div><label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="Key oder Empfänger …" data-dt-search aria-label="Keys durchsuchen"></label></div>';
  $show = array_reverse(array_values(array_filter($rows, fn($k) => $filter === '' || ($k['status'] ?? 'available') === $filter)));
  if (!$show) echo '<p class="st-empty">' . ($rows ? 'Keine Keys mit diesem Status.' : 'Noch keine Keys für ' . pg_h($tabTitle) . ' – links einfügen.') . '</p>';
  else {
    echo '<div class="dt-wrap"><table class="dt" data-dt><thead><tr><th>Key</th><th>Plattform</th><th>Status</th><th>Empfänger</th><th></th></tr></thead><tbody>';
    foreach ($show as $k) {
      $kst = $k['status'] ?? 'available'; $id = pg_h($k['id'] ?? '');
      $who = trim((string) ($k['recipient'] ?? ''));
      echo '<tr data-q="' . pg_h(mb_strtolower(($k['key'] ?? '') . ' ' . $who . ' ' . ($k['email'] ?? ''))) . '">'
         . '<td><button type="button" class="ky-code" data-copy="' . pg_h($k['key'] ?? '') . '" title="Kopieren">' . pg_h($k['key'] ?? '') . pg_icon('copy') . '</button></td>'
         . '<td class="muted">' . pg_h($k['platform'] ?? '') . '</td><td><span class="cst ' . $stCls[$kst] . '">' . $stLabels[$kst] . '</span></td>'
         . '<td><details class="kb-pop ky-who"><summary class="ky-whobtn">' . ($who !== '' ? '<b>' . pg_h($who) . '</b>' . (!empty($k['email']) ? '<span>' . pg_h($k['email']) . '</span>' : '') : '<span class="muted">– zuweisen –</span>') . '</summary>'
         . '<div class="kb-pop-body"><form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '">'
         . '<label class="ml">Empfänger</label><input type="text" name="recipient" value="' . pg_h($who) . '" placeholder="Name / Outlet">'
         . '<label class="ml">E-Mail</label><input type="text" name="email" value="' . pg_h($k['email'] ?? '') . '" placeholder="optional">'
         . '<label class="ml">Notiz</label><input type="text" name="note" value="' . pg_h($k['note'] ?? '') . '">'
         . ($games ? '<label class="ml">Spiel</label><select name="game">' . $gameOpts(strtolower((string) ($k['game'] ?? ''))) . '</select>' : '')
         . '<div class="kb-pop-acts"><button class="btn-primary btn-sm" name="key_assign" value="1">Speichern</button></div></form></div></details></td>'
         . '<td class="ky-acts">'
         . ($kst !== 'redeemed' ? '<form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="key_redeem" value="1" title="Als eingelöst markieren" aria-label="Als eingelöst markieren">' . pg_icon('check') . '</button></form>' : '')
         . ($kst !== 'available' ? '<form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="key_reset" value="1" title="Wieder verfügbar machen" aria-label="Zurücksetzen">' . pg_icon('undo') . '</button></form>' : '')
         . '<form method="post" onsubmit="return confirm(\'Key löschen?\')">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="key_del" value="1" title="Löschen" aria-label="Löschen">' . pg_icon('trash') . '</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
  }
  echo '</section></div></div>';
  pg_view_foot();
}
