<?php
if (!function_exists('pg_h')) { http_response_code(404); exit; }   // nur über index.php nutzbar
require_once __DIR__ . '/view-community.php';
/**
 * „Warteliste anschreiben“ – Nachricht verfassen, Empfänger wählen, optional Keys verteilen.
 * Versand läuft in Etappen über index.php?action=wl_step (Fortschritt im Dialog).
 */
function pg_wl_mail_view($SCHEMA){
  $games = [];
  foreach (pg_games_data('de')['games'] as $g) if (is_array($g) && !empty($g['slug'])) $games[strtolower($g['slug'])] = (string) ($g['title'] ?? $g['slug']);
  $scope = strtolower(preg_replace('/[^a-z0-9\-]/i', '', (string) ($_GET['scope'] ?? '')));
  $counts = [];
  foreach (array_keys($games) as $s) $counts[$s] = count(pg_wl_ranked_scope($s));
  if (!isset($games[$scope])) { arsort($counts); $scope = (string) (array_key_first(array_filter($counts)) ?? array_key_first($games) ?? ''); }
  $title = $games[$scope] ?? $scope;
  $all = pg_wl_recipients($scope, 'all'); $new = pg_wl_recipients($scope, 'new');
  $isOwner = pg_is_owner();
  $platforms = [];
  foreach (pg_keys_load() as $k) if (strtolower((string) ($k['game'] ?? '')) === $scope && ($k['status'] ?? 'available') === 'available') { $pf = (string) ($k['platform'] ?? 'Steam'); $platforms[$pf] = ($platforms[$pf] ?? 0) + 1; }
  $keysOn = !empty($_GET['keys']) && $platforms && $isOwner;
  $sigs = pg_mail_signatures(); $sigDef = pg_mail_signature_default();
  $last = null; foreach (array_reverse(pg_wl_campaigns(), true) as $cid => $c) if (($c['scope'] ?? '') === $scope) { $last = $c + ['id' => $cid]; break; }

  $GLOBALS['pg_crumb_group'] = 'Community';
  pg_view_head('Warteliste anschreiben');
  pg_view_topbar($SCHEMA, null);
  if (isset($_GET['wlsent'])) {
    $n = (int) $_GET['wlsent'];
    echo '<div class="flash ok">✓ ' . $n . ' Mail' . ($n === 1 ? '' : 's') . ' gesendet' . (!empty($_GET['wlskip']) ? ' · ' . (int) $_GET['wlskip'] . ' übersprungen (keine Keys mehr)' : '') . (!empty($_GET['wlpause']) ? ' · Versand pausiert – unten fortsetzen.' : '.') . '</div>';
    if (!empty($_GET['wlfail'])) echo '<div class="flash err">' . (int) $_GET['wlfail'] . ' Mails konnten nicht zugestellt werden (SMTP prüfen).</div>';
  }
  echo '<div class="cm wlm">';
  echo pg_page_head('Warteliste anschreiben', 'Jede Mail geht einzeln raus – niemand sieht die Adressen der anderen. Ein Abmelde-Link steht automatisch in jeder Mail.',
    '<a class="btn btn-sm" href="index.php?view=waitlist&amp;scope=' . rawurlencode($scope) . '">' . pg_icon('back') . 'Zur Warteliste</a>');
  if (!$games) { echo '<div class="ck-card kb-empty">' . pg_icon('game') . '<b>Noch kein Spiel angelegt.</b></div></div>'; pg_view_foot(); return; }
  echo '<div class="g-seg cm-tabs">';
  foreach ($games as $s => $t) echo '<a class="' . ($s === $scope ? 'on' : '') . '" href="index.php?view=waitlist_mail&amp;scope=' . rawurlencode($s) . (!empty($_GET['keys']) ? '&amp;keys=1' : '') . '">' . pg_h($t) . '<span class="n">' . $counts[$s] . '</span></a>';
  echo '</div>';
  echo '<p class="wlm-legal">' . pg_icon('shield') . '<span><b>Kurz zum Rechtlichen (keine Rechtsberatung):</b> Wer sich auf die Beta-Warteliste gesetzt hat, wartet auf Infos zur Beta – Mails zu Beta-Zugang, Keys und Terminen passen dazu. Reine Werbung ohne Einwilligung ist in Deutschland heikel. '
     . 'Nutze dafür den Button „Weiter informiert bleiben?“: Wer klickt, landet bestätigt im Newsletter.</span></p>';
  if ($last && empty($last['finished'])) {
    $done = count($last['sent'] ?? []) + count($last['failed'] ?? []) + count($last['skipped'] ?? []);
    echo '<div class="wlm-resume ck-card">' . pg_icon('alert') . '<div><b>Ein Versand wurde nicht abgeschlossen</b><span>„' . pg_h($last['subject']) . '“ · ' . $done . ' von ' . count($last['recipients']) . ' erledigt.</span></div>'
       . '<button type="button" class="btn btn-primary btn-sm" data-wl-resume="' . pg_h($last['id']) . '">' . pg_icon('send') . 'Fortsetzen</button></div>';
  }

  echo '<form class="wlm-grid" data-wl-form data-scope="' . pg_h($scope) . '">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="scope" value="' . pg_h($scope) . '">';
  // ---- Linke Spalte: Verfassen ----
  echo '<div class="wlm-main">';
  echo '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('list') . 'Empfänger · ' . pg_h($title) . '</span></div><div class="wlm-rcpt">'
     . '<label class="wlm-opt"><input type="radio" name="mode" value="all"' . ($all ? ' checked' : ' disabled') . '><span><b>Alle auf der Warteliste</b><small>' . count($all) . ' Personen</small></span></label>'
     . '<label class="wlm-opt"><input type="radio" name="mode" value="new"' . (!$new ? ' disabled' : '') . '><span><b>Nur, wer noch nicht angeschrieben wurde</b><small>' . count($new) . ' Personen</small></span></label>'
     . '<label class="wlm-opt"><input type="radio" name="mode" value="top"' . (!$all ? ' disabled' : '') . '><span><b>Die ersten <input type="number" name="n" min="1" max="' . max(1, count($all)) . '" value="' . min(10, max(1, count($all))) . '" class="wlm-n"> laut Rangliste</b><small>Platz 1 = meiste Empfehlungen</small></span></label>'
     . '</div></section>';
  echo '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('mail') . 'Nachricht</span></div><div class="wlm-msg">'
     . '<label class="ml">Betreff</label><input type="text" name="subject" required maxlength="160" placeholder="z. B. Deine Beta-Einladung für ' . pg_h($title) . '" class="wlm-in">'
     . '<label class="ml">Text</label>' . pg_mx_editor('', true)
     . '<p class="hint">Platzhalter: <code>{platz}</code> = Platz auf der Warteliste · <code>{spiel}</code> = Spielname' . ($platforms ? ' · <code>{key}</code> = persönlicher Key' : '') . '. „Spiel“ in der Leiste fügt eine Spiel-Karte mit Wishlist-Button ein.</p>'
     . '</div></section>';
  echo '<section class="ck-card"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('settings') . 'Extras</span></div><div class="wlm-extras">'
     . '<label class="wlm-check"><input type="checkbox" name="card" value="1" checked><span><b>Spiel-Karte unter die Nachricht</b><small>Cover, Kurztext und großer Wishlist-Button von ' . pg_h($title) . '.</small></span></label>'
     . '<label class="wlm-check"><input type="checkbox" name="optin" value="1" checked><span><b>„Weiter informiert bleiben?“-Button</b><small>Wer klickt, wird bestätigt in den Newsletter eingetragen (gilt als Einwilligung).</small></span></label>';
  if ($isOwner) {
    if ($platforms) {
      echo '<label class="wlm-check"><input type="checkbox" name="keys" value="1"' . ($keysOn ? ' checked' : '') . ' data-wl-keys><span><b>Jedem Empfänger einen eigenen Key schicken</b><small>Wird automatisch vergeben und unter „Keys“ als vergeben markiert.</small></span></label>'
         . '<div class="wlm-plat"><label class="ml">Plattform</label><select name="platform">';
      foreach ($platforms as $pf => $n) echo '<option value="' . pg_h($pf) . '">' . pg_h($pf) . ' · ' . $n . ' frei</option>';
      echo '</select></div>';
    } else echo '<p class="hint wlm-nokeys">' . pg_icon('key') . 'Keine freien Keys für ' . pg_h($title) . '. <a href="index.php?view=keys&amp;game=' . rawurlencode($scope) . '">Keys hinzufügen</a></p>';
  }
  echo '<label class="ml">Absender &amp; Signatur</label><select name="sig" class="wlm-in">';
  echo '<option value="">PLANIGAMES (Studio) · ohne Signatur</option>';
  foreach ($sigs as $sg) echo '<option value="' . pg_h($sg['id'] ?? '') . '"' . (($sg['id'] ?? '') === $sigDef ? ' selected' : '') . '>' . pg_h(trim(($sg['name'] ?? '') . (!empty($sg['email']) ? ' · ' . $sg['email'] : ''))) . '</option>';
  echo '</select></div></section>';
  echo '<div class="wlm-actions"><button type="button" class="btn btn-sm" data-wl-test>' . pg_icon('send') . 'Testmail an mich</button><span class="sp"></span>'
     . '<span class="wlm-sum" data-wl-sum></span><button type="button" class="btn btn-primary" data-wl-send>' . pg_icon('send') . 'Jetzt senden</button></div>';
  echo '</div>';
  // ---- Rechte Spalte: Vorschau ----
  echo '<aside class="ck-card wlm-prev"><div class="ck-card-h"><span class="ck-card-t">' . pg_icon('eye') . 'So kommt die Mail an</span><span class="st-sub">Beispiel: Platz 1</span></div>'
     . '<iframe title="Vorschau" data-wl-frame sandbox=""></iframe></aside>';
  echo '</form>';
  echo '<script type="application/json" id="wl-data">' . json_encode(['all' => count($all), 'new' => count($new), 'keys' => $platforms, 'title' => $title], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
  echo '</div>';
  pg_view_foot();
}
