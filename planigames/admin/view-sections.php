<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
require_once __DIR__ . '/view-games.php';                       // gemeinsame Bausteine (Karten)
/**
 * Bereiche mit Reitern (Studio & Startseite, Rechtliches): links die Abschnitte, rechts der
 * gewählte Abschnitt in Karten. Alle Abschnitte stehen im selben Formular – gespeichert wird
 * wie bisher der ganze Bereich (index.php, „Speichern“), es geht also nichts verloren.
 */

function pg_sections_icon($label){
  $map = ['impressum' => 'legal', 'datenschutz' => 'lock', 'identit' => 'studio', 'hero' => 'image', 'startseite' => 'image', 'über uns' => 'team', 'newsletter' => 'mega', 'hintergrund' => 'palette',
          'community' => 'chat', 'maskottchen' => 'spark', 'unterstützen' => 'star', 'press' => 'download', 'cookie' => 'shield', 'kontakt' => 'mail',
          'footer' => 'link'];
  $l = mb_strtolower($label);
  foreach ($map as $k => $ic) if (strpos($l, $k) !== false) return $ic;
  return 'settings';
}
// Abschnitte aus dem Schema: Überschriften = Reiter; Abschnitte nur aus Modulen → jedes Modul ein eigener Reiter
function pg_sections_build($fields){
  $tabs = []; $cur = null;
  foreach ($fields as $f) {
    if (($f['widget'] ?? '') === 'heading') { $cur = count($tabs); $tabs[] = ['label' => (string) $f['label'], 'hint' => (string) ($f['hint'] ?? ''), 'fields' => []]; continue; }
    if ($cur === null) { $cur = count($tabs); $tabs[] = ['label' => '', 'hint' => '', 'fields' => []]; }
    $tabs[$cur]['fields'][] = $f;
  }
  $out = [];
  $noHeadings = !array_filter($fields, fn($f) => ($f['widget'] ?? '') === 'heading');
  foreach ($tabs as $t) {
    $objs = array_filter($t['fields'], fn($f) => ($f['widget'] ?? '') === 'object');
    if (($noHeadings && count($t['fields']) <= 6) || (count($objs) === count($t['fields']) && count($objs) > 1)) {
      foreach ($t['fields'] as $f) $out[] = ['label' => (string) ($f['label'] ?? $f['name']), 'hint' => (string) ($f['hint'] ?? ''), 'fields' => [$f], 'module' => ($f['widget'] ?? '') === 'object', 'group' => $t['label']];
    } else $out[] = $t + ['module' => false, 'group' => ''];
  }
  foreach ($out as $k => $t) { $out[$k]['id'] = pg_slugify($t['label'] !== '' ? $t['label'] : 'allgemein') ?: 'tab' . $k; $out[$k]['icon'] = pg_sections_icon($t['label']); }
  return $out;
}
// Inhalt eines Abschnitts: einfache Felder gesammelt in einer Karte, Listen & Objekte als eigene Karten
function pg_sections_panel($key, $tab, $data){
  $h = ''; $buf = '';
  // einfache Felder zuerst (eine Karte), danach Module/Listen als eigene Karten
  $simple = array_values(array_filter($tab['fields'], fn($f) => !in_array($f['widget'] ?? '', ['object', 'list'], true) || (($f['widget'] ?? '') === 'list' && isset($f['field']))));
  $complex = array_values(array_filter($tab['fields'], fn($f) => !in_array($f, $simple, true)));
  $tab['fields'] = array_merge($simple, $complex);
  $flush = function () use (&$buf, &$h, $tab) {
    if ($buf === '') return;
    $h .= pg_gv_card($tab['icon'], $tab['module'] ? 'Einstellungen' : ($tab['label'] !== '' ? $tab['label'] : 'Allgemein'), $buf, '', $tab['module'] ? '' : $tab['hint']);
    $buf = '';
  };
  foreach ($tab['fields'] as $f) {
    $n = $f['name']; $w = $f['widget'] ?? 'string';
    $val = array_key_exists($n, $data) ? $data[$n] : ($f['default'] ?? null);
    if ($w === 'object') {
      $flush();
      $on = null;
      foreach ($f['fields'] ?? [] as $sf) if (($sf['name'] ?? '') === 'enabled') $on = !empty(is_array($val) ? ($val['enabled'] ?? ($sf['default'] ?? false)) : false);
      $chip = $on === null ? '' : '<span class="chip ' . ($on ? 'st-rel' : '') . '" style="margin-left:auto"><i></i>' . ($on ? 'aktiv' : 'aus') . '</span>';
      $h .= pg_gv_card($tab['icon'], (string) ($f['label'] ?? $n), pg_render_fields($f['fields'] ?? [], is_array($val) ? $val : [], 'd[' . $n . ']', $key . '.' . $n), $chip, (string) ($f['hint'] ?? ''));
    } elseif ($w === 'list' && !isset($f['field'])) {
      $flush();
      $h .= pg_gv_card('list', (string) ($f['label'] ?? $n), '<div class="gfield" data-gfield="' . pg_h($n) . '">' . pg_render_field($f, $val, 'd[' . $n . ']', $key . '.' . $n) . '</div>', '', '');
    } elseif ($w === 'markdown' && count($tab['fields']) === 1) {
      $h .= pg_gv_card($tab['icon'], (string) ($f['label'] ?? $n), '<div class="gfield sx-doc" data-gfield="' . pg_h($n) . '">' . pg_render_field(['label' => '', 'hint' => ''] + $f, $val, 'd[' . $n . ']', $key . '.' . $n) . '</div>', '', (string) ($f['hint'] ?? ''));
    } else {
      $buf .= '<div class="gfield" data-gfield="' . pg_h($n) . '">' . pg_render_field($f, $val, 'd[' . $n . ']', $key . '.' . $n) . '</div>';
    }
  }
  $flush();
  return $h;
}

function pg_sections_view($SCHEMA, $key){
  $coll = $SCHEMA[$key];
  $data = pg_load_json($coll['file']);
  $tabs = pg_sections_build($coll['fields']);
  $want = preg_replace('/[^a-z0-9\-]/', '', (string) ($_GET['tab'] ?? ''));
  $active = $tabs[0]['id'] ?? '';
  foreach ($tabs as $t) if ($t['id'] === $want) $active = $want;
  $preview = ['studio' => '../index.html?lang=de', 'legal' => '../rechtliches.html?lang=de'][$key] ?? '../index.html?lang=de';
  $trc = pg_tr_state($SCHEMA, $key)['count'];
  $trOpen = $trc['outdated'] + $trc['missing'] + $trc['unchecked'];
  $trPct = $trc['total'] ? (int) floor($trc['ok'] / $trc['total'] * 100) : 100;

  $GLOBALS['pg_crumb_group'] = 'Inhalte';
  pg_view_head($coll['label']);
  pg_view_topbar($SCHEMA, $key);
  if (isset($_GET['saved'])) echo '<div class="flash ok">✓ Gespeichert. Änderungen sind live auf der Website.</div>';
  if (isset($_GET['error'])) echo '<div class="flash err">Konnte nicht speichern – Schreibrechte am Ordner data/ prüfen.</div>';

  echo '<form method="post" class="sx" id="editor" data-autosave data-preview="' . pg_h($preview) . '" data-sections>'
     . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="collection" value="' . pg_h($key) . '"><input type="hidden" name="lang" value="de">'
     . '<input type="hidden" name="sx_tab" value="' . pg_h($active) . '" data-sx-input>';
  echo '<div class="sx-head"><div><h1>' . pg_h($coll['label']) . '</h1><p>' . ($key === 'legal' ? 'Pflichtangaben deiner Website – Änderungen sind sofort live.' : 'Alles, was auf der Startseite und seitenübergreifend erscheint – Änderungen sind sofort live, Englisch zieht automatisch nach.') . '</p></div>'
     . '<div class="gd-acts editor-actions"><span class="save-status" data-save-status></span>'
     . pg_lang_switch('index.php?collection=' . $key, 'index.php?collection=' . $key . '&lang=en', 'de', $trPct, $trOpen)
     . '<a class="btn btn-sm" href="' . pg_h($preview) . '" target="_blank" rel="noopener">' . pg_icon('ext') . 'Ansehen</a>'
     . '<button type="button" class="btn btn-sm hide-m" data-split-toggle title="Live-Vorschau neben dem Editor">' . pg_icon('eye') . 'Vorschau</button>'
     . '<button class="btn btn-primary btn-sm" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button></div></div>';

  // ---- Navigation ----
  echo '<div class="sx-grid"><nav class="ck-card sx-nav" aria-label="Abschnitte">';
  $group = null;
  foreach ($tabs as $t) {
    if ($t['module'] && $t['group'] !== $group) { $group = $t['group']; echo '<div class="sx-nav-g">' . pg_h($group !== '' ? $group : 'Module') . '</div>'; }
    elseif (!$t['module']) $group = null;
    $state = '';
    if ($t['module']) {
      $f = $t['fields'][0]; $val = $data[$f['name']] ?? [];
      foreach ($f['fields'] ?? [] as $sf) if (($sf['name'] ?? '') === 'enabled') $state = !empty(is_array($val) ? ($val['enabled'] ?? ($sf['default'] ?? false)) : false) ? 'on' : 'off';
    }
    echo '<button type="button" class="sx-tab' . ($t['id'] === $active ? ' on' : '') . '" data-sx="' . pg_h($t['id']) . '">' . pg_icon($t['icon']) . '<span>' . pg_h($t['label'] !== '' ? $t['label'] : 'Allgemein') . '</span>'
       . ($state !== '' ? '<i class="sx-dot ' . $state . '" title="' . ($state === 'on' ? 'aktiv' : 'ausgeschaltet') . '"></i>' : '') . '</button>';
  }
  echo '</nav><div class="sx-body">';
  foreach ($tabs as $t) {
    echo '<section class="sx-panel' . ($t['id'] === $active ? ' on' : '') . '" data-sx-panel="' . pg_h($t['id']) . '">'
       . pg_sections_panel($key, $t, $data) . '</section>';
  }
  echo '<div class="editor-foot g-foot"><span class="hint">Strg/⌘ + S speichert · Änderungen sind sofort live.</span><button class="btn btn-primary" type="submit" name="save" value="1">' . pg_icon('check') . 'Speichern</button></div>';
  echo '</div></div></form>';
  pg_view_foot();
}
