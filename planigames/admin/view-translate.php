<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
/**
 * Übersetzungs-Ansicht – für alle Bereiche gleich (Spiele, Studio, Team, Devlog, Rechtliches).
 * Links steht der deutsche Text (die Vorlage), rechts das Englische. Jede Zeile zeigt, ob die
 * Übersetzung zum aktuellen Deutsch passt. Gespeichert wird über index.php?action=tr_save.
 */
if (!defined('PG_TR_VIEW')) define('PG_TR_VIEW', 1);

function pg_tr_status_info($st, $stale = false){
  return [
    'ok'        => ['aktuell', 'Passt zum aktuellen deutschen Text.'],
    'outdated'  => ['veraltet', $stale ? 'Passt nicht zum Deutschen (z. B. anderer Name oder andere Zahlen). Die Website zeigt solange den deutschen Text.'
                                       : 'Der deutsche Text wurde geändert, nachdem das übersetzt wurde. Die Website zeigt solange den deutschen Text.'],
    'unchecked' => ['ungeprüft', 'Stammt aus der Zeit vor dem Abgleich – bitte kurz prüfen.'],
    'missing'   => ['fehlt', 'Noch keine Übersetzung – die Website zeigt solange den deutschen Text.'],
  ][$st];
}

/* $opts: back (Rücksprung nach dem Speichern), form_id, hide_head (Kopf weglassen) */
function pg_tr_view($SCHEMA, $key, $scope = '', $opts = []){
  $GLOBALS['pg_tr_view_active'] = true;                  // auf dieser Seite kein Hintergrund-Abgleich
  $st = pg_tr_state($SCHEMA, $key, $scope);
  $c = $st['count'];
  $auto = !empty(pg_tr_settings()['auto']);
  $todo = $c['outdated'] + $c['missing'] + $c['unchecked'];
  $run = $c['outdated'] + $c['missing'];
  $pct = $c['total'] ? (int) floor($c['ok'] / $c['total'] * 100) : 100;
  $fid = $opts['form_id'] ?? 'trf';
  $back = $opts['back'] ?? ('index.php?collection=' . rawurlencode($key) . '&lang=en');
  $w = fn($n) => $c['total'] ? round($n / $c['total'] * 100, 2) : 0;

  $h = '';
  if (isset($_GET['trdone'])) {
    $n = (int) $_GET['trdone'];
    $fail = (string) ($_GET['trfail'] ?? '');
    if ($n > 0 || $fail === '') $h .= '<div class="flash ok">' . ($n > 0 ? '✓ ' . $n . ' Text' . ($n === 1 ? '' : 'e') . ' aktualisiert.' : '✓ Fertig – nichts mehr zu tun.') . '</div>';
    $msg = ['unavail' => 'Der Übersetzungsdienst ist gerade nicht erreichbar (evtl. Tageskontingent erreicht). Bereits Übersetztes ist gespeichert – später einfach erneut starten.',
            'busy' => 'Gerade läuft schon ein Abgleich (z. B. in einem anderen Tab). Bitte gleich noch einmal versuchen.',
            'err' => 'Beim Übersetzen ist ein Fehler aufgetreten. Bereits Übersetztes ist gespeichert.'][$fail] ?? '';
    if ($msg !== '') $h .= '<div class="flash err">' . pg_h($msg) . '</div>';
  }
  $h .= '<form method="post" action="index.php?action=tr_save" id="' . pg_h($fid) . '" class="tr" data-tr data-key="' . pg_h($key) . '" data-scope="' . pg_h($scope) . '" data-run="' . $run . '">'
     . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
     . '<input type="hidden" name="collection" value="' . pg_h($key) . '">'
     . '<input type="hidden" name="scope" value="' . pg_h($scope) . '">'
     . '<input type="hidden" name="back" value="' . pg_h($back) . '">';

  // ---- Kopf: Stand + Aktionen ----
  $h .= '<section class="ck-card tr-head">'
      . '<div class="tr-head-top">'
      . '<div class="tr-meter" style="--p:' . $pct . '"><b>' . $pct . '<small>%</small></b><span>aktuell</span></div>'
      . '<div class="tr-intro"><h2>' . pg_icon('globe') . 'Englische Fassung</h2>'
      . '<p><b>Deutsch ist die Vorlage.</b> Ändert sich ein deutscher Text, wird die Übersetzung als <em>veraltet</em> markiert – '
      . 'die Website zeigt dann so lange den deutschen Text, bis das Englische nachgezogen ist. Bilder, Farben, Links und der Aufbau gelten für beide Sprachen.</p></div>'
      . '</div>'
      . '<div class="tr-bar" aria-hidden="true"><i class="ok" style="width:' . $w($c['ok']) . '%"></i><i class="outdated" style="width:' . $w($c['outdated']) . '%"></i>'
      . '<i class="unchecked" style="width:' . $w($c['unchecked']) . '%"></i><i class="missing" style="width:' . $w($c['missing']) . '%"></i></div>'
      . '<div class="tr-legend">'
      . '<span class="tr-chip st-ok"><i></i>' . $c['ok'] . ' aktuell</span>'
      . '<span class="tr-chip st-outdated"><i></i>' . $c['outdated'] . ' veraltet</span>'
      . ($c['unchecked'] ? '<span class="tr-chip st-unchecked"><i></i>' . $c['unchecked'] . ' ungeprüft</span>' : '')
      . '<span class="tr-chip st-missing"><i></i>' . $c['missing'] . ' fehlen</span>'
      . '</div>'
      . '<div class="tr-acts">'
      . '<button type="button" class="btn-primary btn-sm" data-tr-run="auto"' . ($run ? '' : ' disabled') . '>' . pg_icon('refresh') . 'Veraltete &amp; fehlende übersetzen' . ($run ? ' (' . $run . ')' : '') . '</button>'
      . '<button type="button" class="btn btn-sm" data-tr-run="force"' . ($c['total'] ? '' : ' disabled') . '>' . pg_icon('spark') . 'Alles neu aus dem Deutschen übersetzen</button>'
      . (($c['unchecked'] + $c['outdated']) ? '<button type="button" class="btn btn-sm" data-tr-run="confirm">' . pg_icon('check') . 'Alle als geprüft markieren</button>' : '')
      . '<label class="tr-auto" title="Nach jedem Speichern auf Deutsch werden geänderte Texte automatisch neu übersetzt.">'
      . '<input type="checkbox" data-tr-auto' . ($auto ? ' checked' : '') . '><span class="sw"></span>Automatisch nachziehen, wenn sich Deutsch ändert</label>'
      . '</div></section>';

  if (!$st['leaves']) {
    $h .= '<div class="ck-card tr-empty"><p>Hier gibt es noch keine Texte zum Übersetzen. Sobald es deutsche Texte gibt, erscheinen sie hier.</p></div></form>';
    return $h;
  }

  // ---- Filter ----
  $h .= '<div class="tr-tools"><div class="g-seg" role="tablist">'
      . '<button type="button" data-trf="todo"' . ($todo ? ' class="on"' : '') . '>Zu erledigen <span class="n">' . $todo . '</span></button>'
      . '<button type="button" data-trf="all"' . ($todo ? '' : ' class="on"') . '>Alle Texte <span class="n">' . $c['total'] . '</span></button>'
      . '</div><span class="tr-tip">' . pg_icon('help') . 'Tipp: Ein Text passt schon? Dann auf <b>✓ Passt</b> klicken.</span></div>';
  $h .= '<p class="tr-none" data-tr-none' . ($todo ? ' hidden' : '') . '>' . pg_icon('check') . 'Alles aktuell – alle englischen Texte passen zum Deutschen.</p>';

  // ---- Texte, gruppiert (Eintrag → Abschnitt) ----
  $items = []; foreach ($st['leaves'] as $l) $items[$l['item']][$l['grp']][] = $l;
  $multi = count($items) > 1 || (count($items) === 1 && array_key_first($items) !== '' && $scope === '');
  foreach ($items as $item => $groups) {
    $h .= '<div class="tr-itemwrap" data-tr-item>';
    if ($multi) $h .= '<h2 class="tr-item">' . pg_h($item !== '' ? $item : 'Allgemein') . '</h2>';
    foreach ($groups as $grp => $rows) {
      $open = 0; foreach ($rows as $l) if ($l['status'] !== 'ok') $open++;
      $h .= '<section class="ck-card tr-group" data-tr-group><h3>' . pg_h($grp) . ($open ? ' <span class="tr-gn">' . $open . '</span>' : '') . '</h3>';
      foreach ($rows as $l) {
        [$sl, $sh] = pg_tr_status_info($l['status'], $l['stale']);
        $ip = $l['ip'];
        $long = $l['w'] !== 'string' || mb_strlen($l['de']) > 90;
        $rowsN = max(2, min(14, (int) ceil(mb_strlen($l['de']) / 70) + substr_count($l['de'], "\n")));
        $input = $long
          ? '<textarea name="tr[' . pg_h($ip) . ']" rows="' . $rowsN . '" data-tr-input lang="en" spellcheck="true">' . pg_h($l['en']) . '</textarea>'
          : '<input type="text" name="tr[' . pg_h($ip) . ']" value="' . pg_h($l['en']) . '" data-tr-input lang="en" spellcheck="true">';
        $h .= '<div class="tr-row" data-tr-row data-ip="' . pg_h($ip) . '" data-status="' . $l['status'] . '">'
            . '<div class="tr-de"><div class="tr-lbl"><span class="tr-flag">DE</span>' . pg_h($l['label']) . '</div>'
            . '<div class="tr-detext' . ($l['w'] === 'markdown' ? ' md' : '') . '">' . pg_h($l['de']) . '</div></div>'
            . '<div class="tr-en"><div class="tr-lbl"><span class="tr-flag en">EN</span>'
            . '<span class="tr-chip st-' . $l['status'] . '" data-tr-chip title="' . pg_h($sh) . '"><i></i>' . $sl . '</span>'
            . '<span class="tr-racts">'
            . '<button type="button" class="tr-mini" data-tr-one title="Diesen Text neu aus dem Deutschen übersetzen">' . pg_icon('refresh') . '<span>Neu übersetzen</span></button>'
            . ($l['status'] !== 'ok' && $l['status'] !== 'missing' ? '<button type="button" class="tr-mini" data-tr-ok title="Die Übersetzung passt – als aktuell markieren">' . pg_icon('check') . '<span>Passt</span></button>' : '')
            . '</span></div>'
            . $input
            . '<input type="hidden" name="trh[' . pg_h($ip) . ']" value="' . pg_h($l['h']) . '" data-tr-hash>'
            . ($l['status'] !== 'ok' ? '<p class="tr-why">' . pg_h($sh) . '</p>' : '')
            . '</div></div>';
      }
      $h .= '</section>';
    }
    $h .= '</div>';
  }

  // ---- Speicherleiste ----
  $h .= '<div class="tr-savebar" data-tr-savebar><span data-tr-dirty>Keine ungespeicherten Änderungen</span>'
      . '<button class="btn-primary btn-sm" type="submit">' . pg_icon('check') . 'Englisch speichern</button></div>';
  $h .= '</form>';
  return $h;
}
