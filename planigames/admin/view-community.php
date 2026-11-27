<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
/**
 * Community-Seiten im neuen Stil: Beta-Warteliste, Newsletter-Abos, Vorschläge, Kommentare.
 * Nur die Darstellung – Speichern/Löschen läuft unverändert über index.php.
 */

/* Seitenkopf (einheitlich) */
function pg_page_head($title, $sub, $acts = ''){
  return '<div class="sx-head"><div><h1>' . pg_h($title) . '</h1>' . ($sub !== '' ? '<p>' . $sub . '</p>' : '') . '</div>'
       . ($acts !== '' ? '<div class="gd-acts">' . $acts . '</div>' : '') . '</div>';
}
function pg_cm_tile($label, $value, $sub = '', $delta = ''){
  return '<div class="ck-card st-tile"><span class="st-tile-l">' . pg_h($label) . '</span><span class="st-tile-v">' . pg_h($value)
       . ($delta !== '' ? '<span class="ck-delta up">' . pg_h($delta) . '</span>' : '') . '</span>' . ($sub !== '' ? '<span class="st-tile-s">' . pg_h($sub) . '</span>' : '') . '</div>';
}
function pg_cm_scope_plain($sc){ return trim(preg_replace('/^\X\s+/u', '', pg_scope_label($sc))); }   // ohne Emoji davor

/* ================= Beta-Warteliste ================= */
function pg_waitlist_view($SCHEMA){
  $list = pg_load_json(PG_DATA_DIR . '/waitlist.json'); if (!is_array($list)) $list = [];
  $groups = pg_adm_wl_groups($list);
  $total = array_sum(array_map('count', $groups));
  $want = pg_adm_wl_scope($_GET['scope'] ?? '');
  if (!isset($groups[$want])) { $want = array_key_first($groups) ?? ''; foreach ($groups as $sc => $r) if ($sc !== '') { $want = $sc; break; } }
  $ranked = $groups[$want] ?? [];
  $cut = date('Y-m-d', strtotime('-6 day'));
  $week = count(array_filter($ranked, fn($r) => substr((string) ($r['date'] ?? ''), 0, 10) >= $cut));
  $refsTotal = count(array_filter($ranked, fn($r) => ($r['ref'] ?? '') !== ''));
  $byCode = []; foreach ($ranked as $r) if (!empty($r['code'])) $byCode[$r['code']] = $r['email'] ?? '';

  $GLOBALS['pg_crumb_group'] = 'Community';
  pg_view_head('Beta-Warteliste');
  pg_view_topbar($SCHEMA, null);
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Warteliste geleert.</div>';
  echo '<div class="cm">';
  echo pg_page_head('Beta-Warteliste', $total . ' Anmeldung' . ($total === 1 ? '' : 'en') . ' · jedes Spiel hat eine eigene Warteliste mit Rangliste (Empfehlungen, dann Anmeldedatum).',
    ($total ? '<a class="btn btn-sm" href="index.php?action=waitlist_csv">' . pg_icon('download') . 'CSV</a>' : '')
    . ($total && pg_can('mail') ? '<a class="btn btn-primary btn-sm" href="index.php?view=waitlist_mail&amp;scope=' . rawurlencode($want) . '">' . pg_icon('send') . 'Warteliste anschreiben</a>' : ''));
  if (!$total) {
    echo '<div class="ck-card kb-empty">' . pg_icon('list') . '<b>Noch keine Anmeldungen.</b><span>Füge auf einer Spielseite den Block „Beta-Warteliste“ ein – Anmeldungen erscheinen dann hier.</span></div></div>';
    pg_view_foot();
    return;
  }
  echo '<div class="g-seg cm-tabs" role="tablist">';
  foreach ($groups as $sc => $rows) echo '<a role="tab" class="' . ($sc === $want ? 'on' : '') . '" href="index.php?view=waitlist&amp;scope=' . rawurlencode($sc) . '">' . pg_h(pg_cm_scope_plain($sc)) . '<span class="n">' . count($rows) . '</span></a>';
  echo '</div>';
  echo '<div class="st-tiles">' . pg_cm_tile('Anmeldungen', number_format(count($ranked), 0, ',', '.'), pg_cm_scope_plain($want), $week ? '+' . $week : '')
     . pg_cm_tile('Diese Woche', (string) $week, 'neue Anmeldungen')
     . pg_cm_tile('Über Empfehlungen', (string) $refsTotal, count($ranked) ? round($refsTotal / count($ranked) * 100) . ' % aller Anmeldungen' : '')
     . pg_cm_tile('Platz 1', $ranked ? (string) pg_adm_wl_referrals($ranked, $ranked[0]['code'] ?? '') : '0', $ranked ? 'Empfehlungen · ' . mb_strimwidth((string) ($ranked[0]['email'] ?? ''), 0, 26, '…') : '') . '</div>';
  echo '<section class="ck-card"><div class="cm-tbar"><label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="E-Mail suchen …" data-dt-search aria-label="Warteliste durchsuchen"></label>'
     . '<span class="cm-note">' . pg_icon('help') . 'Platz = Anzahl Empfehlungen, bei Gleichstand zählt das Anmeldedatum.</span></div>'
     . '<div class="dt-wrap"><table class="dt" data-dt><thead><tr><th class="num">Platz</th><th>E-Mail</th><th class="num">Empfehlungen</th><th>Angemeldet</th><th>Geworben von</th>'
     . (pg_can('mail') ? '<th>Zuletzt angeschrieben</th>' : '') . '</tr></thead><tbody>';
  $pos = 0;
  foreach ($ranked as $r) {
    $pos++;
    $refs = pg_adm_wl_referrals($ranked, $r['code'] ?? '');
    $by = ($r['ref'] ?? '') !== '' ? ($byCode[$r['ref']] ?? '–') : '–';
    $last = '';
    if (!empty($r['mailed']) && is_array($r['mailed'])) { $lm = end($r['mailed']); $last = is_array($lm) ? date('d.m.Y', strtotime((string) ($lm['date'] ?? '')) ?: time()) . (!empty($lm['key']) ? ' · Key' : '') : ''; }
    echo '<tr data-q="' . pg_h(mb_strtolower(($r['email'] ?? '') . ' ' . $by)) . '"><td class="num"><span class="cm-rank' . ($pos <= 3 ? ' top' : '') . '">' . $pos . '</span></td>'
       . '<td>' . pg_h($r['email'] ?? '') . '</td><td class="num"><b>' . $refs . '</b></td><td>' . pg_h(($r['date'] ?? '') !== '' ? date('d.m.Y', strtotime((string) $r['date']) ?: time()) : '') . '</td>'
       . '<td class="muted">' . pg_h($by) . '</td>' . (pg_can('mail') ? '<td class="muted">' . pg_h($last !== '' ? $last : '–') . '</td>' : '') . '</tr>';
  }
  echo '</tbody></table></div></section>';
  echo '<form method="post" class="cm-danger" onsubmit="return confirm(\'Wirklich die GESAMTE Warteliste (alle Spiele) löschen? Vorher als CSV sichern!\')"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
     . '<span>Gesamte Warteliste aller Spiele löschen – vorher als CSV sichern.</span><button class="btn btn-sm g-btn-danger" name="clear_waitlist" value="1">' . pg_icon('trash') . 'Alles löschen</button></form>';
  echo '</div>';
  pg_view_foot();
}

/* ================= Newsletter-Abos ================= */
function pg_subscribers_view($SCHEMA){
  $list = array_values(array_filter((array) pg_load_json(PG_DATA_DIR . '/subscribers.json'), 'is_array'));
  $conf = fn($r) => !array_key_exists('confirmed', $r) || !empty($r['confirmed']);
  $confirmedN = count(array_filter($list, $conf));
  $pendingN = count($list) - $confirmedN;
  $cut = date('Y-m-d', strtotime('-6 day'));
  $week = count(array_filter($list, fn($r) => substr((string) ($r['date'] ?? ''), 0, 10) >= $cut));
  $f = in_array($_GET['f'] ?? '', ['ok', 'pending'], true) ? $_GET['f'] : '';
  $rows = array_reverse(array_values(array_filter($list, fn($r) => $f === '' || ($f === 'ok' ? $conf($r) : !$conf($r)))));
  $GLOBALS['pg_crumb_group'] = 'Community';
  pg_view_head('Newsletter-Abos');
  pg_view_topbar($SCHEMA, null);
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Liste geleert.</div>';
  echo '<div class="cm">';
  echo pg_page_head('Newsletter-Abos', 'Nur bestätigte Adressen (Double-Opt-In) erhalten den Newsletter.',
    ($list ? '<a class="btn btn-sm" href="index.php?action=subscribers_csv">' . pg_icon('download') . 'CSV</a>' : '')
    . (pg_can('mail') ? '<a class="btn btn-primary btn-sm" href="mail.php?tab=newsletter">' . pg_icon('mega') . 'Newsletter schreiben</a>' : ''));
  echo '<div class="st-tiles">' . pg_cm_tile('Abos', number_format(count($list), 0, ',', '.'), 'insgesamt', $week ? '+' . $week : '')
     . pg_cm_tile('Bestätigt', (string) $confirmedN, 'erhalten den Newsletter') . pg_cm_tile('Ausstehend', (string) $pendingN, 'Bestätigungs-Link noch nicht geklickt')
     . pg_cm_tile('Diese Woche', (string) $week, 'neue Anmeldungen') . '</div>';
  if (!$list) { echo '<div class="ck-card kb-empty">' . pg_icon('mega') . '<b>Noch keine Anmeldungen.</b><span>Das Anmeldeformular sitzt auf der Startseite und im Footer.</span></div></div>'; pg_view_foot(); return; }
  echo '<section class="ck-card"><div class="cm-tbar"><div class="g-seg">'
     . '<a class="' . ($f === '' ? 'on' : '') . '" href="index.php?view=subscribers">Alle<span class="n">' . count($list) . '</span></a>'
     . '<a class="' . ($f === 'ok' ? 'on' : '') . '" href="index.php?view=subscribers&amp;f=ok">Bestätigt<span class="n">' . $confirmedN . '</span></a>'
     . '<a class="' . ($f === 'pending' ? 'on' : '') . '" href="index.php?view=subscribers&amp;f=pending">Ausstehend<span class="n">' . $pendingN . '</span></a></div>'
     . '<label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="E-Mail suchen …" data-dt-search aria-label="Abos durchsuchen"></label></div>'
     . '<div class="dt-wrap"><table class="dt" data-dt><thead><tr><th>E-Mail</th><th>Status</th><th>Angemeldet</th><th>Quelle</th></tr></thead><tbody>';
  foreach ($rows as $r) {
    $ok = $conf($r);
    echo '<tr data-q="' . pg_h(mb_strtolower((string) ($r['email'] ?? ''))) . '"><td>' . pg_h($r['email'] ?? '') . '</td>'
       . '<td><span class="cst ' . ($ok ? 'st-erledigt' : 'st-offen') . '">' . ($ok ? 'bestätigt' : 'ausstehend') . '</span></td>'
       . '<td>' . pg_h(($r['date'] ?? '') !== '' ? date('d.m.Y', strtotime((string) $r['date']) ?: time()) : '') . '</td><td class="muted">' . pg_h($r['source'] ?? '') . '</td></tr>';
  }
  echo '</tbody></table></div></section>';
  echo '<form method="post" class="cm-danger" onsubmit="return confirm(\'Wirklich ALLE Abos löschen? Vorher als CSV sichern!\')"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
     . '<span>Alle Abos löschen – vorher als CSV sichern.</span><button class="btn btn-sm g-btn-danger" name="clear_subscribers" value="1">' . pg_icon('trash') . 'Alles löschen</button></form>';
  echo '</div>';
  pg_view_foot();
}

/* ================= Vorschläge ================= */
function pg_suggestions_view($SCHEMA){
  $list = array_values(array_filter((array) pg_load_json(PG_DATA_DIR . '/suggestions.json'), 'is_array'));
  $scopes = [];
  foreach ($list as $s) { $sc = pg_adm_wl_scope($s['scope'] ?? ''); $scopes[$sc] = ($scopes[$sc] ?? 0) + 1; }
  uksort($scopes, fn($a, $b) => $a === '' ? -1 : ($b === '' ? 1 : strcmp($a, $b)));
  $sc = isset($_GET['scope']) ? pg_adm_wl_scope($_GET['scope']) : null;
  if ($sc !== null && !isset($scopes[$sc])) $sc = null;
  $in = fn($s) => $sc === null || pg_adm_wl_scope($s['scope'] ?? '') === $sc;
  $pending = array_values(array_filter($list, fn($s) => empty($s['approved']) && $in($s)));
  $approved = array_values(array_filter($list, fn($s) => !empty($s['approved']) && $in($s)));
  usort($approved, fn($a, $b) => (int) ($b['votes'] ?? 0) <=> (int) ($a['votes'] ?? 0));
  usort($pending, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
  $stLabels = ['open' => 'Offen', 'planned' => 'Geplant', 'done' => 'Umgesetzt'];
  $ret = $sc !== null ? 'scope=' . rawurlencode($sc) : '';
  $csrf = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="ret" value="' . pg_h($ret) . '">';
  $GLOBALS['pg_crumb_group'] = 'Community';
  pg_view_head('Vorschläge');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="cm">';
  echo pg_page_head('Vorschläge', count(array_filter($list, fn($s) => empty($s['approved']))) . ' warten auf Freigabe · freigegebene erscheinen öffentlich und können Stimmen sammeln.');
  if (!$list) { echo '<div class="ck-card kb-empty">' . pg_icon('bulb') . '<b>Noch keine Vorschläge.</b><span>Die Vorschlagsbox fügst du als Block auf einer Spielseite ein.</span></div></div>'; pg_view_foot(); return; }
  echo '<div class="g-seg cm-tabs"><a class="' . ($sc === null ? 'on' : '') . '" href="index.php?view=suggestions">Alle<span class="n">' . count($list) . '</span></a>';
  foreach ($scopes as $k => $n) echo '<a class="' . ($sc === $k ? 'on' : '') . '" href="index.php?view=suggestions&amp;scope=' . rawurlencode($k) . '">' . pg_h(pg_cm_scope_plain($k)) . '<span class="n">' . $n . '</span></a>';
  echo '</div>';
  // ---- Zu prüfen ----
  echo '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('alert') . 'Zu prüfen</span><span class="ck-count' . ($pending ? '' : ' zero') . '">' . count($pending) . '</span></div>';
  if (!$pending) echo '<p class="st-empty">Nichts zu prüfen. 🎉</p>';
  else {
    echo '<ul class="sg-list">';
    foreach ($pending as $s) {
      $id = pg_h($s['id'] ?? '');
      echo '<li class="sg-item pend"><div class="sg-tx"><p>' . pg_h($s['text'] ?? '') . '</p><span class="sg-meta"><span class="cm-chip">' . pg_h(pg_cm_scope_plain($s['scope'] ?? '')) . '</span>'
         . (!empty($s['date']) ? pg_h(pg_time_ago(strtotime((string) $s['date']) ?: time())) : '') . '</span></div>'
         . '<div class="sg-acts"><form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="btn btn-primary btn-sm" name="approve_sugg" value="1">' . pg_icon('check') . 'Freigeben</button></form>'
         . '<form method="post" onsubmit="return confirm(\'Vorschlag löschen?\')">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="del_sugg" value="1" title="Löschen" aria-label="Löschen">' . pg_icon('trash') . '</button></form></div></li>';
    }
    echo '</ul>';
  }
  echo '</section>';
  // ---- Veröffentlicht ----
  echo '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('star') . 'Veröffentlicht – nach Stimmen</span><span class="ck-count zero">' . count($approved) . '</span></div>';
  if (!$approved) echo '<p class="st-empty">Noch nichts veröffentlicht.</p>';
  else {
    echo '<ul class="sg-list">';
    foreach ($approved as $s) {
      $id = pg_h($s['id'] ?? ''); $cur = $s['status'] ?? 'open';
      echo '<li class="sg-item"><div class="sg-votes"><b>' . (int) ($s['votes'] ?? 0) . '</b><span>Stimmen</span></div><div class="sg-tx"><p>' . pg_h($s['text'] ?? '') . '</p><span class="sg-meta"><span class="cm-chip">' . pg_h(pg_cm_scope_plain($s['scope'] ?? '')) . '</span></span></div>'
         . '<div class="sg-acts"><div class="g-seg sg-status">';
      foreach ($stLabels as $k => $lbl) echo $k === $cur ? '<span class="on sg-' . $k . '">' . $lbl . '</span>'
        : '<form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="status" value="' . $k . '"><button name="sugg_status" value="1">' . $lbl . '</button></form>';
      echo '</div><form method="post" onsubmit="return confirm(\'Vorschlag löschen?\')">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="del_sugg" value="1" title="Löschen" aria-label="Löschen">' . pg_icon('trash') . '</button></form></div></li>';
    }
    echo '</ul>';
  }
  echo '</section></div>';
  pg_view_foot();
}

/* ================= Devlog-Kommentare ================= */
function pg_comments_view($SCHEMA){
  $list = array_values(array_filter((array) pg_load_json(PG_DATA_DIR . '/comments.json'), 'is_array'));
  usort($list, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
  $tab = ($_GET['tab'] ?? '') === 'ok' ? 'ok' : 'pending';
  $pending = array_values(array_filter($list, fn($c) => empty($c['approved'])));
  $approved = array_values(array_filter($list, fn($c) => !empty($c['approved'])));
  if ($tab === 'pending' && !$pending && $approved) $tab = 'ok';
  $posts = []; foreach ((array) (pg_load_json(PG_DATA_DIR . '/patchnotes.json')['posts'] ?? []) as $p) if (is_array($p) && !empty($p['slug'])) $posts[strtolower($p['slug'])] = (string) ($p['title'] ?? $p['slug']);
  $csrf = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  $GLOBALS['pg_crumb_group'] = 'Community';
  pg_view_head('Kommentare');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="cm">';
  echo pg_page_head('Devlog-Kommentare', 'Neue Kommentare erscheinen erst nach deiner Freigabe auf der Website.');
  echo '<div class="g-seg cm-tabs"><a class="' . ($tab === 'pending' ? 'on' : '') . '" href="index.php?view=comments">Zu prüfen<span class="n' . ($pending ? ' warn' : '') . '">' . count($pending) . '</span></a>'
     . '<a class="' . ($tab === 'ok' ? 'on' : '') . '" href="index.php?view=comments&amp;tab=ok">Freigegeben<span class="n">' . count($approved) . '</span></a></div>';
  $rows = $tab === 'ok' ? $approved : $pending;
  if (!$rows) echo '<div class="ck-card kb-empty">' . pg_icon('chat') . '<b>' . ($tab === 'ok' ? 'Noch nichts freigegeben.' : 'Nichts zu moderieren. 🎉') . '</b></div>';
  else {
    echo '<div class="cmt-list">';
    foreach ($rows as $c) {
      $nm = trim((string) ($c['name'] ?? '')) ?: 'Anonym';
      $slug = strtolower((string) ($c['slug'] ?? ''));
      $id = pg_h($c['id'] ?? '');
      echo '<article class="ck-card cmt' . ($tab === 'pending' ? ' pend' : '') . '"><div class="cmt-h"><span class="ck-av" style="--h:' . (int) (hexdec(substr(md5(strtolower($nm)), 0, 2)) / 255 * 360) . '">' . pg_h(mb_strtoupper(mb_substr($nm, 0, 1))) . '</span>'
         . '<div><b>' . pg_h($nm) . '</b><span>zu <a href="index.php?collection=patchnotes&amp;item=' . rawurlencode($slug) . '">' . pg_h($posts[$slug] ?? $slug) . '</a> · ' . pg_h(!empty($c['date']) ? pg_time_ago(strtotime((string) $c['date']) ?: time()) : '') . '</span></div></div>'
         . '<p class="cmt-body">' . nl2br(pg_h($c['body'] ?? '')) . '</p>'
         . '<div class="cmt-acts"><form method="post">' . $csrf . '<input type="hidden" name="id" value="' . $id . '">'
         . ($tab === 'pending' ? '<button class="btn btn-primary btn-sm" name="approve_comment" value="1">' . pg_icon('check') . 'Freigeben</button>'
                               : '<button class="btn btn-sm" name="unapprove_comment" value="1">' . pg_icon('eye') . 'Verbergen</button>')
         . '</form><form method="post" onsubmit="return confirm(\'Kommentar löschen?\')">' . $csrf . '<input type="hidden" name="id" value="' . $id . '"><button class="icon-btn" name="delete_comment" value="1" title="Löschen" aria-label="Löschen">' . pg_icon('trash') . '</button></form></div></article>';
    }
    echo '</div>';
  }
  echo '</div>';
  pg_view_foot();
}
