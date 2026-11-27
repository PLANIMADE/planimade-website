<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
require_once __DIR__ . '/view-games.php';                       // gemeinsame Bausteine (Karten)
/**
 * Liste + Detail für Sammlungen mit Einträgen (Devlog-Beiträge, Teammitglieder) – im Stil der Spiele-Verwaltung.
 * Links die Einträge (Suche, Filter, Neu; Team per Ziehen sortierbar), rechts genau EIN Eintrag.
 * Gespeichert wird nur dieser Eintrag – alle anderen bleiben unangetastet (index.php, Zweig „item_orig“).
 */

function pg_items_cfg($key){
  if ($key === 'patchnotes') return [
    'list' => 'posts', 'id' => 'slug', 'title' => 'title', 'one' => 'Beitrag', 'many' => 'Beiträge', 'icon' => 'devlog',
    'new' => 'Neuer Beitrag', 'empty' => 'Noch keine Beiträge – schreib deinen ersten Devlog.',
    'groups' => [
      ['main', 'devlog', 'Beitrag', ['title', 'excerpt', 'body'], ''],
      ['side', 'cal', 'Veröffentlichung', ['date', 'draft', 'publishAt', 'notify'], ''],
      ['side', 'game', 'Zuordnung', ['game', 'version', 'tags'], ''],
      ['side', 'image', 'Titelbild', ['cover', 'coverAlt'], 'Erscheint in der Übersicht, im Beitrag und beim Teilen.'],
      ['side', 'link', 'Web-Adresse', ['slug'], ''],
    ],
    'reorder' => false,
  ];
  if ($key === 'team') return [
    'list' => 'members', 'id' => 'index', 'title' => 'name', 'one' => 'Mitglied', 'many' => 'Team', 'icon' => 'team',
    'new' => 'Neues Mitglied', 'empty' => 'Noch niemand im Team – leg das erste Profil an.',
    'groups' => [
      ['main', 'user', 'Profil', ['name', 'role', 'tagline', 'bio'], ''],
      ['main', 'link', 'Profile & Links', ['socials'], 'Erscheinen als Symbole im Profil auf der Website.'],
      ['side', 'image', 'Foto', ['photo', 'photoAlt', 'emoji'], ''],
      ['side', 'spark', 'Details', ['location', 'funFact'], ''],
    ],
    'reorder' => true,
  ];
  return null;
}
// Felder der Einträge (aus dem Schema)
function pg_items_fields($SCHEMA, $key, $list){
  foreach (($SCHEMA[$key]['fields'] ?? []) as $f) if (($f['name'] ?? '') === $list) return $f['fields'] ?? [];
  return [];
}
// Fingerabdruck eines Eintrags (schützt Einträge ohne Adresse vor Verwechslung beim Speichern)
function pg_item_fp($it){ return substr(md5(json_encode($it)), 0, 12); }
// Eintrag finden: über die Adresse (Slug) bzw. die Position + Fingerabdruck
function pg_item_locate($items, $cfg, $idx, $orig, $fp = ''){
  $idx = (int) $idx;
  if ($cfg['id'] === 'slug') {
    if (isset($items[$idx]) && is_array($items[$idx]) && strtolower((string) ($items[$idx]['slug'] ?? '')) === strtolower((string) $orig)) return $idx;
    foreach ($items as $i => $it) if (is_array($it) && $orig !== '' && strtolower((string) ($it['slug'] ?? '')) === strtolower((string) $orig)) return $i;
    return -1;
  }
  if (!isset($items[$idx]) || !is_array($items[$idx])) return -1;
  return ($fp === '' || pg_item_fp($items[$idx]) === $fp) ? $idx : -1;
}
function pg_post_state($p){
  if (!empty($p['draft'])) return ['Entwurf', 'st-dev'];
  if (!empty($p['publishAt']) && strtotime((string) $p['publishAt']) > time()) return ['Geplant', 'st-ann'];
  return ['Veröffentlicht', 'st-rel'];
}

function pg_items_view($SCHEMA, $key, $lang){
  $cfg = pg_items_cfg($key);
  $coll = $SCHEMA[$key];
  $fields = pg_items_fields($SCHEMA, $key, $cfg['list']);
  $byName = []; foreach ($fields as $f) if (!empty($f['name'])) $byName[$f['name']] = $f;
  $data = pg_load_json($coll['file']);
  $items = array_values(is_array($data[$cfg['list']] ?? null) ? $data[$cfg['list']] : []);
  $isPost = $key === 'patchnotes';
  $gameTitle = [];
  foreach ((array) (pg_load_json(PG_DATA_DIR . '/games.json')['games'] ?? []) as $g) if (is_array($g) && !empty($g['slug'])) $gameTitle[strtolower($g['slug'])] = (string) ($g['title'] ?? $g['slug']);

  // Auswahl
  $sel = (string) ($_GET['item'] ?? '');
  $i = -1;
  if ($sel !== '') {
    if ($cfg['id'] === 'slug') { foreach ($items as $k => $it) if (is_array($it) && strtolower((string) ($it['slug'] ?? '')) === strtolower($sel)) { $i = $k; break; } }
    elseif (ctype_digit($sel) && isset($items[(int) $sel])) $i = (int) $sel;
  }
  $explicit = $i >= 0;
  // Reihenfolge in der Liste: Beiträge nach Datum (neueste oben), Team wie auf der Website
  $order = array_keys($items);
  if ($isPost) usort($order, fn($a, $b) => strcmp((string) ($items[$b]['date'] ?? ''), (string) ($items[$a]['date'] ?? '')) ?: ($b <=> $a));
  if ($i < 0 && $order) $i = $order[0];
  $it = ($i >= 0 && is_array($items[$i] ?? null)) ? $items[$i] : null;
  $itemKey = $it ? ($cfg['id'] === 'slug' ? (string) ($it['slug'] ?? '') : (string) $i) : '';
  $base = 'index.php?collection=' . $key . ($it ? '&item=' . rawurlencode($itemKey) : '');
  $scope = $it ? $cfg['list'] . '.' . ($cfg['id'] === 'slug' ? '@' . $itemKey : $itemKey) : '';

  // EN-Stand dieses Eintrags
  $trc = $scope !== '' ? pg_tr_state($SCHEMA, $key, $scope)['count'] : ['total' => 0, 'ok' => 0, 'outdated' => 0, 'missing' => 0, 'unchecked' => 0];
  $enOpen = $trc['outdated'] + $trc['missing'] + $trc['unchecked'];
  $enPct = $trc['total'] ? (int) floor($trc['ok'] / $trc['total'] * 100) : 100;

  $GLOBALS['pg_crumb_group'] = 'Inhalte';
  pg_view_head($it ? (string) ($it[$cfg['title']] ?? $cfg['one']) . ' · ' . $coll['label'] : $coll['label']);
  pg_view_topbar($SCHEMA, $key);
  if (isset($_GET['saved']))   echo '<div class="flash ok">✓ Gespeichert. Die Website ist aktuell.</div>';
  if (isset($_GET['created'])) echo '<div class="flash ok">✓ ' . pg_h($cfg['one']) . ' angelegt – jetzt Inhalte ergänzen.</div>';
  if (isset($_GET['duplicated'])) echo '<div class="flash ok">✓ Kopie angelegt.</div>';
  if (isset($_GET['deleted'])) echo '<div class="flash ok">✓ „' . pg_h($_GET['deleted']) . '“ liegt im Papierkorb (wiederherstellbar).</div>';
  if (isset($_GET['sent']))    echo '<div class="flash ok">📣 Newsletter verschickt: ' . (int) $_GET['sent'] . ' Abonnent' . ((int) $_GET['sent'] === 1 ? '' : 'en') . ' benachrichtigt.</div>';
  if (isset($_GET['ierr']))    echo '<div class="flash err">' . pg_h($_GET['ierr']) . '</div>';

  echo '<div class="gm it-wrap ' . ($explicit ? 'has-sel' : 'no-sel') . '">';

  /* ================= Liste ================= */
  echo '<aside class="ck-card gm-list it-list"><div class="gm-list-h"><h2>' . pg_h($cfg['many']) . '</h2><span class="gm-n">' . count(array_filter($items, 'is_array')) . '</span>'
     . '<button type="button" class="btn btn-primary btn-sm" data-open-dialog="dlg-newitem">' . pg_icon('plus') . 'Neu</button></div>';
  if (count($items) > 4) echo '<label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="' . pg_h($cfg['many']) . ' durchsuchen…" data-it-search aria-label="Einträge durchsuchen"></label>';
  if ($isPost && $items) {
    $cnt = ['' => 0, 'Veröffentlicht' => 0, 'Entwurf' => 0, 'Geplant' => 0];
    foreach ($items as $p) if (is_array($p)) { $cnt['']++; $cnt[pg_post_state($p)[0]]++; }
    echo '<div class="g-seg" data-it-filter><button type="button" class="on" data-if="">Alle<span class="n">' . $cnt[''] . '</span></button>';
    foreach (['Veröffentlicht' => 'Live', 'Entwurf' => 'Entwürfe', 'Geplant' => 'Geplant'] as $k => $l) if ($cnt[$k]) echo '<button type="button" data-if="' . pg_h($k) . '">' . $l . '<span class="n">' . $cnt[$k] . '</span></button>';
    echo '</div>';
  }
  echo '<ul class="gm-rows it-rows"' . ($cfg['reorder'] ? ' data-it-reorder' : '') . '>';
  foreach ($order as $k) {
    $x = $items[$k];
    if (!is_array($x)) continue;
    $xk = $cfg['id'] === 'slug' ? (string) ($x['slug'] ?? '') : (string) $k;
    $href = 'index.php?collection=' . $key . '&amp;item=' . rawurlencode($xk) . ($lang === 'en' ? '&amp;lang=en' : '');
    if ($isPost) {
      $stt = pg_post_state($x);
      [$cp] = explode('#', (string) ($x['cover'] ?? '') . '#');
      $gm = !empty($x['game']) ? ($gameTitle[strtolower((string) $x['game'])] ?? $x['game']) : '';
      $d = !empty($x['date']) ? date('d.m.Y', strtotime((string) $x['date']) ?: time()) : '';
      echo '<li class="gm-row' . ($k === $i ? ' on' : '') . '" data-state="' . pg_h($stt[0]) . '" data-q="' . pg_h(mb_strtolower(($x['title'] ?? '') . ' ' . $gm . ' ' . ($x['version'] ?? ''))) . '">'
         . '<a class="gm-row-a" href="' . $href . '"><span class="gm-thumb">' . ($cp !== '' ? '<img src="' . pg_h($cp) . '" alt="" loading="lazy">' : pg_icon('devlog')) . '</span>'
         . '<span class="gm-row-tx"><span class="gm-row-t">' . pg_h(($x['title'] ?? '') !== '' ? $x['title'] : '(ohne Titel)') . '</span>'
         . '<span class="gm-row-m"><span class="chip ' . $stt[1] . '"><i></i>' . pg_h($stt[0]) . '</span><span class="it-meta">' . pg_h(trim($d . ($gm !== '' ? ' · ' . $gm : ''), ' ·')) . '</span></span></span></a></li>';
    } else {
      $ph = (string) ($x['photo'] ?? '');
      echo '<li class="gm-row' . ($k === $i ? ' on' : '') . '" data-key="' . (int) $k . '" data-q="' . pg_h(mb_strtolower(($x['name'] ?? '') . ' ' . ($x['role'] ?? ''))) . '">'
         . '<a class="gm-row-a" href="' . $href . '"><span class="it-av">' . ($ph !== '' ? '<img src="' . pg_h(explode('#', $ph)[0]) . '" alt="" loading="lazy">' : pg_h((string) ($x['emoji'] ?? '🙂'))) . '</span>'
         . '<span class="gm-row-tx"><span class="gm-row-t">' . pg_h(($x['name'] ?? '') !== '' ? $x['name'] : '(ohne Namen)') . '</span>'
         . '<span class="gm-row-m"><span class="it-meta">' . pg_h((string) ($x['role'] ?? '')) . '</span></span></span></a>'
         . ($cfg['reorder'] ? '<span class="gm-grip" data-it-grip title="Ziehen, um die Reihenfolge zu ändern" aria-hidden="true">' . pg_icon('grip') . '</span>' : '') . '</li>';
    }
  }
  echo '</ul>';
  if (!$items) echo '<p class="gm-empty">' . pg_h($cfg['empty']) . '</p>';
  if ($cfg['reorder'] && count($items) > 1) echo '<p class="gm-hint">' . pg_icon('grip') . 'Ziehen, um die Reihenfolge auf der Website zu ändern</p>';
  echo '</aside>';

  /* ================= Detail ================= */
  echo '<div class="gd it-detail">';
  if (!$it) {
    echo '<div class="ck-card gd-empty">' . pg_icon($cfg['icon']) . '<h2>' . pg_h($cfg['empty']) . '</h2>'
       . '<button type="button" class="btn btn-primary" data-open-dialog="dlg-newitem">' . pg_icon('plus') . pg_h($cfg['new']) . '</button></div></div></div>';
    pg_items_dialogs($key, $cfg, $gameTitle);
    pg_view_foot();
    return;
  }
  $preview = $isPost ? '../devlog.php?slug=' . rawurlencode($itemKey) . '&lang=' . $lang : '../index.html?lang=' . $lang . '#team-section';
  echo '<a class="btn btn-ghost btn-sm gd-back" href="index.php?collection=' . $key . '">' . pg_icon('back') . 'Alle ' . pg_h($cfg['many']) . '</a>';
  if ($lang === 'en') echo '<div class="gd-form gd-en">';
  else {
    echo '<form method="post" class="gd-form" id="editor" data-autosave data-preview="' . pg_h($preview) . '" data-item-editor>'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="collection" value="' . pg_h($key) . '"><input type="hidden" name="lang" value="de">'
       . '<input type="hidden" name="item_orig" value="' . pg_h($itemKey) . '"><input type="hidden" name="item_idx" value="' . (int) $i . '">'
       . '<input type="hidden" name="item_fp" value="' . pg_item_fp($it) . '" data-item-fp>';
  }

  // ---- Kopf ----
  $acts = '<div class="gd-acts editor-actions"><span class="save-status" data-save-status></span>'
        . pg_lang_switch($base, $base . '&lang=en', $lang, $enPct, $enOpen)
        . '<a class="btn btn-sm" href="' . pg_h($preview) . '" target="_blank" rel="noopener">' . pg_icon('ext') . 'Ansehen' . ($lang === 'en' ? ' (EN)' : '') . '</a>'
        . ($lang === 'en' ? '<button class="btn btn-primary btn-sm" type="submit" form="trf">' . pg_icon('check') . 'Englisch speichern</button>'
                          : '<button type="button" class="btn btn-sm hide-m" data-split-toggle title="Live-Vorschau neben dem Editor">' . pg_icon('eye') . 'Vorschau</button>'
                          . '<button class="btn btn-primary btn-sm" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button>')
        . '</div>';
  if ($isPost) {
    [$cp] = explode('#', (string) ($it['cover'] ?? '') . '#');
    $stt = pg_post_state($it);
    $gm = !empty($it['game']) ? ($gameTitle[strtolower((string) $it['game'])] ?? $it['game']) : '';
    echo '<div class="gd-hero it-hero">' . ($cp !== '' ? '<img class="cv" src="' . pg_h($cp) . '" alt="">' : '')
       . '<div class="gd-hero-in"><div class="gd-id"><h2>' . pg_h(($it['title'] ?? '') !== '' ? $it['title'] : '(ohne Titel)') . '</h2>'
       . '<div class="gd-pills"><span class="chip ' . $stt[1] . '"><i></i>' . pg_h($stt[0]) . ($stt[0] === 'Geplant' ? ' · ' . pg_h(date('d.m. H:i', strtotime((string) $it['publishAt']))) : '') . '</span>'
       . (!empty($it['date']) ? '<span class="slug-pill">' . pg_icon('cal') . '<span>' . pg_h(date('d.m.Y', strtotime((string) $it['date']) ?: time())) . '</span></span>' : '')
       . ($gm !== '' ? '<span class="slug-pill it-pill">' . pg_icon('game') . '<span>' . pg_h($gm) . '</span></span>' : '')
       . (!empty($it['version']) ? '<span class="slug-pill it-pill"><span>v' . pg_h(ltrim((string) $it['version'], 'vV')) . '</span></span>' : '')
       . (!empty($it['notified']) ? '<span class="chip st-rel" title="Newsletter verschickt am ' . pg_h(date('d.m.Y', strtotime((string) $it['notified']))) . '"><i></i>Newsletter gesendet</span>' : '')
       . '</div></div>' . $acts . '</div></div>';
  } else {
    $ph = (string) ($it['photo'] ?? '');
    echo '<div class="gd-hero it-hero it-person"><div class="gd-hero-in"><div class="it-who"><span class="it-av lg">' . ($ph !== '' ? '<img src="' . pg_h(explode('#', $ph)[0]) . '" alt="">' : pg_h((string) ($it['emoji'] ?? '🙂'))) . '</span>'
       . '<div class="gd-id"><h2>' . pg_h(($it['name'] ?? '') !== '' ? $it['name'] : '(ohne Namen)') . '</h2>'
       . '<div class="gd-pills">' . (($it['role'] ?? '') !== '' ? '<span class="slug-pill it-pill"><span>' . pg_h($it['role']) . '</span></span>' : '')
       . (($it['location'] ?? '') !== '' ? '<span class="slug-pill it-pill">' . pg_icon('route') . '<span>' . pg_h($it['location']) . '</span></span>' : '') . '</div></div></div>'
       . $acts . '</div></div>';
  }

  if ($lang === 'en') {
    require_once __DIR__ . '/view-translate.php';
    echo pg_tr_view($SCHEMA, $key, $scope, ['back' => $base . '&lang=en', 'form_id' => 'trf']);
    echo '</div>';
  } else {
    // ---- Karten: Hauptspalte + Seitenspalte ----
    $cols = ['main' => '', 'side' => ''];
    $used = [];
    foreach ($cfg['groups'] as [$col, $ic, $title, $names, $hint]) {
      $h = '';
      foreach ($names as $n) {
        if (!isset($byName[$n])) continue;
        $used[$n] = true;
        $f = $byName[$n];
        $val = array_key_exists($n, $it) ? $it[$n] : ($f['default'] ?? null);
        if ($n === 'slug' && $isPost) {
          $f['hint'] = 'Teil der Web-Adresse: devlog.php?slug=… – leer = automatisch aus dem Titel. Beim Ändern zieht die englische Fassung mit.';
        }
        $h .= '<div class="gfield" data-gfield="' . pg_h($n) . '">' . pg_render_field($f, $val, 'it[' . $n . ']', $key . '.' . $cfg['list'] . '.' . $n) . '</div>';
      }
      if ($h !== '') $cols[$col] .= pg_gv_card($ic, $title, $h, '', $hint);
    }
    // Felder, die keiner Gruppe zugeordnet sind (z. B. neu im Schema), nicht verlieren
    $rest = '';
    foreach ($fields as $f) if (!empty($f['name']) && empty($used[$f['name']]) && ($f['widget'] ?? '') !== 'heading')
      $rest .= pg_render_field($f, $it[$f['name']] ?? ($f['default'] ?? null), 'it[' . $f['name'] . ']', $key . '.' . $cfg['list'] . '.' . $f['name']);
    if ($rest !== '') $cols['side'] .= pg_gv_card('settings', 'Weitere Felder', $rest);
    echo '<div class="it-grid"><div class="it-main">' . $cols['main'] . '</div><div class="it-side">' . $cols['side']
       . pg_gv_card('alert', 'Aktionen', '<div class="g-danger"><div><b>Duplizieren</b><span>Legt eine Kopie an (z. B. als Vorlage).</span></div><button class="btn btn-sm" type="submit" form="frm-item-dup">' . pg_icon('copy') . 'Duplizieren</button></div>'
           . '<div class="g-danger"><div><b>In den Papierkorb</b><span>Verschwindet von der Website; wiederherstellbar im Papierkorb.</span></div><button class="btn btn-sm g-btn-danger" type="submit" form="frm-item-del">' . pg_icon('trash') . 'Löschen</button></div>')
       . '</div></div>';
    echo '<div class="editor-foot g-foot"><span class="hint">Strg/⌘ + S speichert · Änderungen sind sofort live · Englisch zieht automatisch nach.</span><button class="btn btn-primary" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button></div>';
    echo '</form>';
  }
  echo '</div></div>';   // .gd, .gm

  $hid = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="collection" value="' . pg_h($key) . '">'
       . '<input type="hidden" name="idx" value="' . (int) $i . '"><input type="hidden" name="orig" value="' . pg_h($itemKey) . '"><input type="hidden" name="fp" value="' . pg_item_fp($it) . '">';
  $nm = (string) ($it[$cfg['title']] ?? $cfg['one']);
  echo '<form id="frm-item-dup" method="post" action="index.php?action=item_duplicate" hidden>' . $hid . '</form>';
  echo '<form id="frm-item-del" method="post" action="index.php?action=item_delete" hidden data-confirm="„' . pg_h($nm) . '“ in den Papierkorb legen? Es verschwindet von der Website und lässt sich im Papierkorb wiederherstellen.">' . $hid . '</form>';
  pg_items_dialogs($key, $cfg, $gameTitle);
  pg_view_foot();
}

function pg_items_dialogs($key, $cfg, $gameTitle){
  $csrf = '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="collection" value="' . pg_h($key) . '">';
  $extra = '';
  if ($key === 'patchnotes' && $gameTitle) {
    $extra = '<div class="field"><label class="flabel" for="ni-game">Spiel (optional)</label><select id="ni-game" name="game"><option value="">– kein Spiel –</option>';
    foreach ($gameTitle as $s => $t) $extra .= '<option value="' . pg_h($s) . '">' . pg_h($t) . '</option>';
    $extra .= '</select></div>';
  }
  echo '<div class="pg-modal-back" id="dlg-newitem" hidden' . (isset($_GET['new']) ? ' data-autoopen' : '') . '><form class="pg-modal" method="post" action="index.php?action=item_create" role="dialog" aria-modal="true" aria-labelledby="dlg-ni-t">' . $csrf
     . '<div class="pg-modal-h"><div><h3 id="dlg-ni-t">' . pg_h($cfg['new']) . '</h3><p>' . ($key === 'patchnotes' ? 'Wird als Entwurf angelegt – erst sichtbar, wenn du ihn veröffentlichst.' : 'Erscheint nach dem Anlegen im Team-Bereich der Website.') . '</p></div>'
     . '<button type="button" class="icon-btn" data-close aria-label="Schließen">' . pg_icon('x') . '</button></div>'
     . '<div class="pg-modal-b"><div class="field"><label class="flabel" for="ni-title">' . ($key === 'patchnotes' ? 'Titel' : 'Name') . '</label>'
     . '<input id="ni-title" name="title" required maxlength="140" autocomplete="off" data-autofocus placeholder="' . ($key === 'patchnotes' ? 'z. B. Devlog #7 – Neue Karten' : 'Vor- und Nachname') . '"></div>' . $extra . '</div>'
     . '<div class="pg-modal-f"><span class="sp"></span><button type="button" class="btn btn-ghost" data-close>Abbrechen</button><button class="btn btn-primary">' . pg_icon('plus') . 'Anlegen</button></div></form></div>';
}
