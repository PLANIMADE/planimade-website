<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
/**
 * Planungs-Board (Kanban) – Darstellung. Die Spalten verteilen sich über die verfügbare Breite
 * und brechen bei Bedarf in eine neue Reihe um: kein seitliches Scrollen. Formularfelder und
 * Aktionen sind dieselben wie zuvor (Verarbeitung in index.php).
 */

function pg_board_card_html($card, $c){
  [$csrf, $colors, $prios, $assignees, $gameMap, $archiveView, $curId] = $c;
  $cc = (string) ($card['color'] ?? '');
  $labels = (array) ($card['labels'] ?? []);
  $chk = (array) ($card['checklist'] ?? []);
  $cmts = (array) ($card['comments'] ?? []);
  $done = 0; foreach ($chk as $it) if (!empty($it['done'])) $done++;
  $total = count($chk);
  $prio = (string) ($card['priority'] ?? '');
  $assignee = (string) ($card['assignee'] ?? '');
  $cimg = trim((string) ($card['image'] ?? ''));
  $h = '<article class="kb-card' . ($cc !== '' ? ' has-color' : '') . '" id="card-' . pg_h($card['id']) . '" data-card-id="' . pg_h($card['id']) . '"' . ($cc !== '' ? ' style="--cc:' . pg_h($cc) . '"' : '')
     . ' data-labels="' . pg_h(implode('|', $labels)) . '" data-assignee="' . pg_h($assignee) . '" data-prio="' . pg_h($prio) . '">';

  // ---- Bearbeiten (Dialog) ----
  $form = '<form method="post" class="kb-cardform" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="card" value="' . pg_h($card['id']) . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
        . ($archiveView ? '<input type="hidden" name="ret_archive" value="1">' : '')
        . '<div class="kb-modal-head"><input type="text" name="title" value="' . pg_h($card['title'] ?? '') . '" required class="kb-title-in" aria-label="Titel">'
        . '<button type="button" class="icon-btn" data-kb-close aria-label="Schließen">' . pg_icon('x') . '</button></div>'
        . '<div class="kb-modal-grid"><div class="kb-modal-main">'
        . '<label class="ml">Notiz</label><textarea name="text" rows="4" placeholder="Details, Links, Ideen …">' . pg_h($card['text'] ?? '') . '</textarea>'
        . '<label class="ml">Checkliste' . ($total ? ' <span class="kb-mini">' . $done . '/' . $total . '</span>' : '') . '</label><div class="kb-chkedit">';
  foreach ($chk as $i => $it) {
    $form .= '<label class="kb-chk-erow"><input type="checkbox" name="chk_done[' . $i . ']"' . (!empty($it['done']) ? ' checked' : '') . '>'
           . '<input type="text" name="chk_text[' . $i . ']" value="' . pg_h($it['text'] ?? '') . '"></label>';
  }
  $form .= '</div><textarea name="chk_new" rows="2" placeholder="Neue Punkte – einer pro Zeile"></textarea>'
         . '<label class="ml">Kommentare' . ($cmts ? ' <span class="kb-mini">' . count($cmts) . '</span>' : '') . '</label>';
  if ($cmts) {
    $form .= '<div class="kb-comments">';
    foreach ($cmts as $cm) $form .= '<div class="kb-cmt"><div class="kb-cmt-head"><b>' . pg_h($cm['author'] ?? '') . '</b><span>' . pg_h(date('d.m. H:i', strtotime((string) ($cm['date'] ?? '')) ?: time())) . '</span></div>'
                                  . '<div class="kb-cmt-body">' . nl2br(pg_h($cm['text'] ?? '')) . '</div></div>';
    $form .= '</div>';
  }
  $form .= '<textarea name="comment_new" rows="2" placeholder="Kommentar schreiben …"></textarea></div>'
         . '<div class="kb-modal-side">'
         . '<label class="ml">Spiel</label><select name="game"><option value="">– kein Spiel –</option>';
  foreach ($gameMap as $slug => $title) $form .= '<option value="' . pg_h($slug) . '"' . (($card['game'] ?? '') === $slug ? ' selected' : '') . '>' . pg_h($title) . '</option>';
  $form .= '</select><label class="ml">Zuständig</label><select name="assignee">';
  foreach ($assignees as $val => $lbl) $form .= '<option value="' . pg_h($val) . '"' . ($val === $assignee ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
  $form .= '</select><label class="ml">Priorität</label><select name="priority">';
  foreach ($prios as $val => $lbl) $form .= '<option value="' . pg_h($val) . '"' . ($val === $prio ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
  $form .= '</select><label class="ml">Fällig am</label><input type="date" name="due" value="' . pg_h($card['due'] ?? '') . '">'
         . '<label class="ml">Farbe</label><select name="color">';
  foreach ($colors as $val => $lbl) $form .= '<option value="' . pg_h($val) . '"' . ($val === $cc ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
  $form .= '</select><label class="ml">Labels</label><input type="text" name="labels" value="' . pg_h(implode(', ', $labels)) . '" placeholder="z. B. Bug, Art, Wichtig">'
         . '<label class="ml">Bild</label><div class="kb-imgfield">'
         . ($cimg !== '' ? '<img class="kb-img-prev" src="' . pg_h($cimg) . '" alt=""><label class="kb-img-rm"><input type="checkbox" name="img_remove" value="1"> Bild entfernen</label>' : '')
         . '<label class="btn btn-sm kb-img-pick">' . pg_icon('image') . ($cimg !== '' ? 'Bild ersetzen' : 'Bild anhängen') . '<input type="file" name="cardimg" accept="image/*" hidden></label></div>'
         . '</div></div>'
         . '<div class="kb-cardform-foot"><button class="btn-danger sm" name="board_del_card" value="1" onclick="return confirm(\'Karte endgültig löschen?\')">' . pg_icon('trash') . 'Löschen</button>'
         . ($archiveView ? '<button class="btn btn-sm" name="board_unarchive_card" value="1">' . pg_icon('undo') . 'Wiederherstellen</button>'
                         : '<button class="btn btn-sm" name="board_archive_card" value="1">' . pg_icon('archive') . 'Archivieren</button>')
         . '<span class="sp"></span><button type="button" class="btn btn-ghost btn-sm" data-kb-close>Abbrechen</button><button class="btn-primary btn-sm" name="board_edit_card" value="1">' . pg_icon('check') . 'Speichern</button></div></form>';

  // ---- Vorderseite ----
  $h .= '<div class="kb-card-top">' . (!$archiveView ? '<span class="kb-grip" data-kb-grip title="Ziehen, um die Karte zu verschieben">' . pg_icon('grip') . '</span>' : '')
      . '<div class="kb-tags">' . ($prio !== '' ? '<span class="kb-prio kb-prio-' . pg_h($prio) . '">' . pg_h($prios[$prio] ?? $prio) . '</span>' : '');
  foreach ($labels as $l) $h .= '<span class="kb-label">' . pg_h($l) . '</span>';
  $h .= '</div>' . ($assignee !== '' ? '<span class="kb-avatar" title="' . pg_h($assignees[$assignee] ?? $assignee) . '">' . pg_h(pg_board_initials($assignees[$assignee] ?? $assignee)) . '</span>' : '')
      . '<details class="kb-cardedit"><summary title="Karte öffnen / bearbeiten">' . pg_icon('edit') . '</summary>' . $form . '</details></div>';
  if ($cimg !== '') $h .= '<img class="kb-card-img" src="' . pg_h($cimg) . '" alt="" loading="lazy">';
  $h .= '<h4 class="kb-card-title">' . pg_h($card['title'] ?? '') . '</h4>';
  if (trim((string) ($card['text'] ?? '')) !== '') $h .= '<p class="kb-card-text">' . nl2br(pg_h($card['text'])) . '</p>';
  if ($total) {
    $pct = (int) round($done / $total * 100);
    $h .= '<div class="kb-chk"><div class="kb-chk-bar"><span style="width:' . $pct . '%"></span></div><span class="kb-chk-count">' . $done . '/' . $total . '</span></div><ul class="kb-chk-list">';
    foreach ($chk as $i => $it) {
      $h .= '<li><button type="button" class="kb-chk-item' . (!empty($it['done']) ? ' done' : '') . '" data-chk-card="' . pg_h($card['id']) . '" data-chk-i="' . $i . '">'
          . '<span class="kb-chk-box">' . (!empty($it['done']) ? '✓' : '') . '</span><span>' . pg_h($it['text'] ?? '') . '</span></button></li>';
    }
    $h .= '</ul>';
  }
  $metas = '';
  if (!empty($card['game']) && isset($gameMap[$card['game']])) $metas .= '<span class="kb-meta">' . pg_icon('game') . pg_h($gameMap[$card['game']]) . '</span>';
  if (!empty($card['due'])) {
    $today = date('Y-m-d');
    $over = $card['due'] < $today; $soon = !$over && $card['due'] <= date('Y-m-d', strtotime('+1 day'));
    $metas .= '<span class="kb-meta kb-due' . ($over ? ' over' : ($soon ? ' soon' : '')) . '">' . pg_icon('cal') . pg_h(date('d.m.', strtotime($card['due']) ?: time())) . ($over ? ' · überfällig' : '') . '</span>';
  }
  if ($cmts) $metas .= '<span class="kb-meta">' . pg_icon('chat') . count($cmts) . '</span>';
  if ($metas !== '') $h .= '<div class="kb-card-meta">' . $metas . '</div>';
  return $h . '</article>';
}

function pg_board_view($SCHEMA, $board, $bi, $curId){
  $gameMap = [];
  foreach ((array) (pg_load_json($SCHEMA['games']['file'] ?? '')['games'] ?? []) as $g) if (is_array($g) && !empty($g['slug'])) $gameMap[$g['slug']] = $g['title'] ?? $g['slug'];
  $csrf = pg_h(pg_csrf());
  $archiveView = !empty($_GET['archive']);
  $boardview = ($_GET['boardview'] ?? '') === 'calendar' ? 'calendar' : 'columns';
  $bd = $board['boards'][$bi];
  $cols = $bd['columns'];
  $ctx = [$csrf, pg_board_colors(), pg_board_priorities(), pg_board_assignees(), $gameMap, $archiveView, $curId];
  $base = 'index.php?view=board&board=' . urlencode($curId);

  $GLOBALS['pg_crumb_group'] = 'Planung & Werkzeuge';
  pg_view_head($archiveView ? 'Board-Archiv' : 'Planungs-Board');
  pg_view_topbar($SCHEMA, null);

  $archCount = 0; $open = 0; $allLabels = [];
  foreach ($cols as $c) foreach ($c['cards'] as $card) {
    if (!empty($card['archived'])) { $archCount++; continue; }
    $open++;
    foreach ((array) ($card['labels'] ?? []) as $l) $allLabels[$l] = true;
  }
  ksort($allLabels);

  echo '<div class="kb-page">';
  // ---- Kopf ----
  echo '<div class="kb-head"><div class="kb-title"><h1>' . pg_h($bd['name']) . '</h1>'
     . '<p>' . ($archiveView ? $archCount . ' archivierte Karte' . ($archCount === 1 ? '' : 'n') : $open . ' offene Karte' . ($open === 1 ? '' : 'n') . ' · ' . count($cols) . ' Spalten')
     . (!empty($bd['game']) && isset($gameMap[$bd['game']]) ? ' · <span class="kb-hgame">' . pg_icon('game') . pg_h($gameMap[$bd['game']]) . '</span>' : '') . '</p></div>'
     . '<div class="kb-head-acts">';
  if (!$archiveView) {
    echo '<div class="g-seg"><a class="' . ($boardview === 'columns' ? 'on' : '') . '" href="' . pg_h($base) . '">' . pg_icon('kanban') . 'Spalten</a>'
       . '<a class="' . ($boardview === 'calendar' ? 'on' : '') . '" href="' . pg_h($base . '&boardview=calendar') . '">' . pg_icon('cal') . 'Kalender</a></div>'
       . '<a class="btn btn-sm" href="' . pg_h($base . '&archive=1') . '">' . pg_icon('archive') . 'Archiv' . ($archCount ? ' <span class="kb-mini">' . $archCount . '</span>' : '') . '</a>';
  } else echo '<a class="btn btn-sm" href="' . pg_h($base) . '">' . pg_icon('back') . 'Zum Board</a>';
  echo '<details class="kb-pop"><summary class="btn btn-sm">' . pg_icon('settings') . 'Board</summary><div class="kb-pop-body">'
     . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
     . '<label class="ml">Board-Name</label><input type="text" name="name" value="' . pg_h($bd['name']) . '" required>'
     . '<label class="ml">Spiel</label><select name="game"><option value="">– keins –</option>';
  foreach ($gameMap as $slug => $title) echo '<option value="' . pg_h($slug) . '"' . (($bd['game'] ?? '') === $slug ? ' selected' : '') . '>' . pg_h($title) . '</option>';
  echo '</select><div class="kb-pop-acts"><button class="btn-primary btn-sm" name="board_rename" value="1">Speichern</button>';
  if (count($board['boards']) > 1) echo '<button class="btn-danger sm" name="board_del" value="1" onclick="return confirm(\'Dieses Board samt Karten löschen?\')">Board löschen</button>';
  echo '</div></form></div></details></div></div>';

  // ---- Boards (Umschalter) ----
  echo '<div class="kb-boards">';
  foreach ($board['boards'] as $b) {
    $n = 0; foreach ($b['columns'] as $c) foreach ($c['cards'] as $card) if (empty($card['archived'])) $n++;
    echo '<a class="kb-board-tab' . ($b['id'] === $curId ? ' on' : '') . '" href="index.php?view=board&amp;board=' . urlencode($b['id']) . '">'
       . (!empty($b['game']) && isset($gameMap[$b['game']]) ? pg_icon('game') : pg_icon('kanban')) . '<span>' . pg_h($b['name']) . '</span><span class="n">' . $n . '</span></a>';
  }
  echo '<details class="kb-pop kb-board-add"><summary class="kb-board-tab add" title="Neues Board">' . pg_icon('plus') . '<span>Neues Board</span></summary><div class="kb-pop-body">'
     . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><label class="ml">Board-Name</label>'
     . '<input type="text" name="name" placeholder="z. B. Wobbly Wizards" required><label class="ml">Spiel (optional)</label><select name="game"><option value="">– keins –</option>';
  foreach ($gameMap as $slug => $title) echo '<option value="' . pg_h($slug) . '">' . pg_h($title) . '</option>';
  echo '</select><div class="kb-pop-acts"><button class="btn-primary btn-sm" name="board_add" value="1">Board anlegen</button></div></form></div></details></div>';

  // ---- Archiv ----
  if ($archiveView) {
    $arch = [];
    foreach ($cols as $c) foreach ($c['cards'] as $card) if (!empty($card['archived'])) $arch[] = $card;
    if (!$arch) echo '<div class="ck-card kb-empty">' . pg_icon('archive') . '<b>Noch nichts archiviert.</b><span>Erledigte Karten lassen sich über „Archivieren“ aus dem Board nehmen – hier bleiben sie erhalten.</span></div>';
    else { echo '<div class="kb-archive">'; foreach ($arch as $card) echo pg_board_card_html($card, $ctx); echo '</div>'; }
    echo '</div>';
    pg_view_foot();
    return;
  }

  // ---- Kalender ----
  if ($boardview === 'calendar') {
    $ym = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ym'] ?? '')) ? $_GET['ym'] : date('Y-m');
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01') ?: new DateTime('first day of this month');
    $first->setTime(0, 0, 0);
    $prev = (clone $first)->modify('-1 month')->format('Y-m');
    $next = (clone $first)->modify('+1 month')->format('Y-m');
    $today = date('Y-m-d');
    $byDate = []; $undated = []; $overdue = [];
    foreach ($cols as $c) foreach ($c['cards'] as $card) {
      if (!empty($card['archived'])) continue;
      $d = (string) ($card['due'] ?? '');
      if ($d === '') { $undated[] = $card; continue; }
      $byDate[$d][] = $card;
      if ($d < $today) $overdue[] = $card;
    }
    $cal = $base . '&boardview=calendar';
    echo '<div class="kb-cal-wrap"><section class="ck-card kb-calcard"><div class="kb-cal-nav">'
       . '<a class="icon-btn" href="' . pg_h($cal . '&ym=' . $prev) . '" aria-label="Vorheriger Monat">' . pg_icon('back') . '</a>'
       . '<span class="kb-cal-title">' . pg_h(pg_de_month((int) $first->format('n')) . ' ' . $first->format('Y')) . '</span>'
       . '<a class="icon-btn" href="' . pg_h($cal . '&ym=' . $next) . '" aria-label="Nächster Monat">' . pg_icon('chev') . '</a>'
       . '<a class="btn btn-sm" href="' . pg_h($cal) . '">Heute</a></div><div class="kb-cal">';
    foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wd) echo '<div class="kb-cal-wd">' . $wd . '</div>';
    $startDow = ((int) $first->format('N')) - 1;
    for ($i = 0; $i < $startDow; $i++) echo '<div class="kb-cal-cell empty"></div>';
    for ($day = 1, $dim = (int) $first->format('t'); $day <= $dim; $day++) {
      $ds = $first->format('Y-m') . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
      echo '<div class="kb-cal-cell' . ($ds === $today ? ' today' : '') . '"><span class="kb-cal-day">' . $day . '</span>';
      foreach (($byDate[$ds] ?? []) as $card) {
        $cc = (string) ($card['color'] ?? '');
        echo '<a class="kb-cal-card' . ($ds < $today ? ' over' : '') . '" href="' . pg_h($base) . '#card-' . pg_h($card['id']) . '"' . ($cc !== '' ? ' style="--cc:' . pg_h($cc) . '"' : '') . '>' . pg_h($card['title'] ?? '') . '</a>';
      }
      echo '</div>';
    }
    echo '</div></section>';
    echo '<aside class="kb-cal-side">';
    echo '<section class="ck-card kb-cal-box"><h3>' . pg_icon('alert') . 'Überfällig <span class="kb-mini">' . count($overdue) . '</span></h3>';
    if (!$overdue) echo '<p class="kb-cal-none">Nichts überfällig.</p>';
    foreach ($overdue as $card) echo '<a class="kb-cal-card over" href="' . pg_h($base) . '#card-' . pg_h($card['id']) . '"><b>' . pg_h(date('d.m.', strtotime($card['due']) ?: time())) . '</b> ' . pg_h($card['title'] ?? '') . '</a>';
    echo '</section><section class="ck-card kb-cal-box"><h3>' . pg_icon('list') . 'Ohne Datum <span class="kb-mini">' . count($undated) . '</span></h3>';
    if (!$undated) echo '<p class="kb-cal-none">Alle Karten haben ein Datum.</p>';
    foreach ($undated as $card) echo '<a class="kb-cal-card" href="' . pg_h($base) . '#card-' . pg_h($card['id']) . '">' . pg_h($card['title'] ?? '') . '</a>';
    echo '</section></aside></div></div>';
    pg_view_foot();
    return;
  }

  // ---- Filter ----
  $me = pg_current_email();
  echo '<div class="kb-filter" data-kb-filter><span class="kb-filter-lbl">' . pg_icon('filter') . 'Filter</span>'
     . '<button type="button" class="kb-filter-tag on" data-label="" data-mine="">Alle</button>';
  if ($me !== '') echo '<button type="button" class="kb-filter-tag" data-mine="' . pg_h($me) . '">' . pg_icon('user') . 'Meine Karten</button>';
  foreach (array_keys($allLabels) as $l) echo '<button type="button" class="kb-filter-tag" data-label="' . pg_h($l) . '">' . pg_h($l) . '</button>';
  echo '<details class="kb-pop kb-addcolpop"><summary class="btn btn-sm">' . pg_icon('plus') . 'Spalte</summary><div class="kb-pop-body">'
     . '<form method="post" class="kb-addcol"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
     . '<label class="ml">Neue Spalte</label><input type="text" name="title" placeholder="z. B. Testen" required><div class="kb-pop-acts"><button class="btn-primary btn-sm" name="board_add_col" value="1">Spalte anlegen</button></div></form></div></details>';
  echo '</div>';

  // ---- Spalten (passen sich der Breite an – kein seitliches Scrollen) ----
  echo '<div class="board" data-board data-board-id="' . pg_h($curId) . '" style="--cols:' . max(1, count($cols)) . '">';
  foreach ($cols as $c) {
    $cid = pg_h($c['id']);
    $visible = array_filter($c['cards'], fn($x) => empty($x['archived']));
    echo '<section class="kb-col" data-col-id="' . $cid . '"><header class="kb-col-head"><h3>' . pg_h($c['title']) . '</h3><span class="kb-count">' . count($visible) . '</span>'
       . '<details class="kb-pop kb-colmenu"><summary class="icon-btn" title="Spalte bearbeiten">' . pg_icon('settings') . '</summary><div class="kb-pop-body">'
       . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<label class="ml">Spaltenname</label><input type="text" name="title" value="' . pg_h($c['title']) . '" required><div class="kb-pop-acts"><button class="btn-primary btn-sm" name="board_rename_col" value="1">Umbenennen</button></div></form>'
       . '<form method="post" onsubmit="return confirm(\'Spalte samt Karten löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<button class="btn-danger sm" name="board_del_col" value="1">' . pg_icon('trash') . 'Spalte löschen</button></form></div></details></header>';
    echo '<div class="kb-cards" data-kb-cards>';
    foreach ($c['cards'] as $card) { if (!empty($card['archived'])) continue; echo pg_board_card_html($card, $ctx); }
    echo '</div>';
    echo '<form method="post" class="kb-add" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<input type="text" name="title" placeholder="Karte hinzufügen …" autocomplete="off" aria-label="Neue Karte in ' . pg_h($c['title']) . '">'
       . '<label class="kb-add-img" title="Bild anhängen (optional)">' . pg_icon('clip') . '<input type="file" name="cardimg" accept="image/*" hidden></label>'
       . '<button class="kb-add-go" name="board_add_card" value="1" aria-label="Karte hinzufügen">' . pg_icon('plus') . '</button></form>';
    echo '</section>';
  }
  echo '</div></div>';
  pg_view_foot();
}
