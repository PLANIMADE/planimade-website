<?php
/**
 * PLANIGAMES — PHP Admin (Flat-File CMS für All-Inkl / KAS).
 * Login -> Dashboard -> Editor. Speichert direkt in ../data/*.json,
 * Uploads nach ../media/. Keine Datenbank, keine externen Dienste.
 */
require __DIR__ . '/lib.php';
$SCHEMA = require __DIR__ . '/schema.php';
pg_session_boot();

// Falls der eingeloggte Zugang inzwischen entfernt wurde: ausloggen
if (pg_logged_in() && !pg_current_user()) { $_SESSION = []; session_destroy(); }

$action = $_GET['action'] ?? '';

/* ---- Upload-Endpoint (nur eingeloggt) ---- */
if ($action === 'upload') {
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  pg_csrf_check();
  pg_handle_upload();
  exit;
}

/* ---- Benachrichtigungen: Echtzeit-Zähler (AJAX-Polling) ---- */
if ($action === 'notif_counts') {
  header('Content-Type: application/json');
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  $n = pg_notifications();
  echo json_encode(['counts' => $n, 'notes' => pg_notif_notes($n)], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---- Medien-Liste (für „Aus Bibliothek wählen") ---- */
if ($action === 'medialist') {
  header('Content-Type: application/json');
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  $out = [];
  if (is_dir(PG_MEDIA_DIR)) {
    foreach (scandir(PG_MEDIA_DIR) as $f) {
      if ($f === '.' || $f === '..' || $f[0] === '.' || is_dir(PG_MEDIA_DIR . '/' . $f)) continue;
      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
      $out[] = ['path' => '/media/' . $f, 'name' => $f,
        'img' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'], true),
        'mtime' => @filemtime(PG_MEDIA_DIR . '/' . $f) ?: 0];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']); // neueste zuerst
  }
  echo json_encode(['files' => $out], JSON_UNESCAPED_SLASHES);
  exit;
}

/* ---- Board: Reihenfolge per Drag&Drop speichern (AJAX) ---- */
if ($action === 'board_save') {
  header('Content-Type: application/json');
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  pg_csrf_check();
  $order = json_decode($_POST['order'] ?? '[]', true);
  if (!is_array($order)) { echo '{"error":"ungültig"}'; exit; }
  $board = pg_board_load();
  $bi = pg_board_index($board, (string) ($_POST['board'] ?? ''));
  $cols = $board['boards'][$bi]['columns'] ?? [];
  // Karten nach ID auffindbar machen + Ursprungsspalte merken (für archivierte Karten, die nicht im DOM sind)
  $byId = []; $origCol = [];
  foreach ($cols as $c) foreach (($c['cards'] ?? []) as $card) if (!empty($card['id'])) { $byId[$card['id']] = $card; $origCol[$card['id']] = $c['id']; }
  $titles = [];
  foreach ($cols as $c) $titles[$c['id']] = $c['title'] ?? '';
  $newCols = []; $idx = [];
  foreach ($order as $col) {
    $cid = $col['col'] ?? '';
    if (!isset($titles[$cid])) continue;
    $cards = [];
    foreach (($col['cards'] ?? []) as $cardId) if (isset($byId[$cardId])) { $cards[] = $byId[$cardId]; unset($byId[$cardId]); }
    $idx[$cid] = count($newCols);
    $newCols[] = ['id' => $cid, 'title' => $titles[$cid], 'cards' => $cards];
  }
  // übrig gebliebene (z. B. archivierte) Karten zurück in ihre Ursprungsspalte
  foreach ($byId as $cardId => $card) {
    $oc = $origCol[$cardId] ?? null;
    if ($oc !== null && isset($idx[$oc])) $newCols[$idx[$oc]]['cards'][] = $card;
    elseif ($newCols) $newCols[0]['cards'][] = $card;
  }
  if ($newCols) { $board['boards'][$bi]['columns'] = $newCols; pg_board_save($board); }
  echo '{"ok":true}'; exit;
}

/* ---- Board: Checklisten-Punkt umschalten (AJAX) ---- */
if ($action === 'board_toggle') {
  header('Content-Type: application/json');
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  pg_csrf_check();
  $cardId = (string) ($_POST['card'] ?? '');
  $i = (int) ($_POST['i'] ?? -1);
  $board = pg_board_load();
  $done = 0; $total = 0; $found = false;
  foreach ($board['boards'] as &$bd) foreach ($bd['columns'] as &$c) foreach ($c['cards'] as &$card) {
    if (($card['id'] ?? '') === $cardId && isset($card['checklist'][$i])) {
      $card['checklist'][$i]['done'] = empty($card['checklist'][$i]['done']);
      foreach ($card['checklist'] as $it) { $total++; if (!empty($it['done'])) $done++; }
      $found = true;
    }
  }
  unset($bd, $c, $card);
  if ($found) { pg_board_save($board); echo json_encode(['ok' => true, 'done' => $done, 'total' => $total]); }
  else echo '{"error":"nicht gefunden"}';
  exit;
}

/* ---- Logout ---- */
if ($action === 'logout') { if (pg_logged_in()) pg_log_activity('Abgemeldet'); $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

/* ---- Registrierung per Einladungs-Link ---- */
if ($action === 'register') {
  $token = $_GET['token'] ?? $_POST['token'] ?? '';
  $inv = pg_invite_find($token);
  $err = '';
  if (!$inv) {
    pg_view_head('Einladung');
    echo '<div class="auth"><div class="auth-card">';
    echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
    echo '<h1>Einladung ungültig</h1>';
    echo '<p class="muted">Dieser Einladungslink ist ungültig oder abgelaufen. Bitte den Owner um eine neue Einladung.</p>';
    echo '<a class="btn-primary" href="index.php" style="display:inline-block;margin-top:1rem;text-decoration:none">Zum Login</a>';
    echo '</div></div>'; pg_view_foot(); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $p1 = $_POST['pw'] ?? ''; $p2 = $_POST['pw2'] ?? '';
    if (pg_user_find($inv['email'])) { $err = 'Für diese E-Mail gibt es bereits ein Konto. Bitte einloggen.'; }
    elseif (strlen($p1) < 8) $err = 'Bitte mindestens 8 Zeichen.';
    elseif ($p1 !== $p2) $err = 'Die Passwörter stimmen nicht überein.';
    else {
      pg_user_add($inv['email'], $p1, $inv['role'] ?? 'editor', $name);
      pg_invite_delete($token);
      session_regenerate_id(true);
      pg_login_user(['email'=>$inv['email'], 'role'=>$inv['role'] ?? 'editor']);
      header('Location: index.php'); exit;
    }
  }
  pg_view_head('Registrieren');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  echo '<h1>Konto erstellen</h1>';
  echo '<p class="muted">Du wurdest als Teammitglied eingeladen. Lege dein Passwort fest für:</p>';
  echo '<p style="margin:.3rem 0 0;font-weight:600">' . pg_h($inv['email']) . '</p>';
  if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="hidden" name="token" value="' . pg_h($token) . '">';
  echo '<input type="text" name="name" placeholder="Dein Name" autofocus>';
  echo '<input type="password" name="pw" placeholder="Passwort (min. 8 Zeichen)" required>';
  echo '<input type="password" name="pw2" placeholder="Passwort wiederholen" required>';
  echo '<button class="btn-primary" type="submit">Konto erstellen &amp; loslegen</button></form>';
  echo '</div></div>'; pg_view_foot(); exit;
}

/* ---- Ersteinrichtung: ersten Owner anlegen ---- */
if (!pg_users_exist()) {
  $err = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $p1 = $_POST['pw'] ?? ''; $p2 = $_POST['pw2'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Bitte gib eine gültige E-Mail-Adresse ein.';
    elseif (strlen($p1) < 8) $err = 'Bitte mindestens 8 Zeichen.';
    elseif ($p1 !== $p2) $err = 'Die Passwörter stimmen nicht überein.';
    else {
      pg_user_add($email, $p1, 'owner', $name);
      session_regenerate_id(true);
      pg_login_user(['email'=>$email, 'role'=>'owner']);
      header('Location: index.php'); exit;
    }
  }
  pg_view_head('Einrichten');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  echo '<h1>Willkommen! Erstelle dein Konto</h1>';
  echo '<p class="muted">Das ist das Owner-Konto. Du kannst später weitere Teammitglieder per E-Mail einladen.</p>';
  if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="text" name="name" placeholder="Dein Name" autofocus>';
  echo '<input type="email" name="email" placeholder="E-Mail-Adresse" required>';
  echo '<input type="password" name="pw" placeholder="Passwort (min. 8 Zeichen)" required>';
  echo '<input type="password" name="pw2" placeholder="Passwort wiederholen" required>';
  echo '<button class="btn-primary" type="submit">Konto anlegen &amp; starten</button></form>';
  echo '</div></div>'; pg_view_foot(); exit;
}

/* ---- Login (mit optionalem 2-Faktor-Schritt) ---- */
if (!pg_logged_in()) {
  if (isset($_GET['cancel'])) { unset($_SESSION['pg_2fa']); header('Location: index.php'); exit; }
  $err = '';
  // 2FA-Wartezustand abgelaufen? (5 Minuten)
  if (!empty($_SESSION['pg_2fa']) && ($_SESSION['pg_2fa']['time'] ?? 0) < time() - 300) unset($_SESSION['pg_2fa']);
  $stage = !empty($_SESSION['pg_2fa']['email']) ? '2fa' : 'login';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $lock = pg_login_locked();
    if ($lock > 0) {
      $err = 'Zu viele Fehlversuche. Bitte in ' . ceil($lock / 60) . ' Minute(n) erneut versuchen.';
    } elseif (isset($_POST['twofa'])) {
      // Zweiter Schritt: TOTP- oder Backup-Code prüfen
      $pend = $_SESSION['pg_2fa'] ?? null;
      $u = $pend ? pg_user_find($pend['email']) : null;
      if (!$u || !pg_user_has_2fa($u)) { unset($_SESSION['pg_2fa']); $stage = 'login'; $err = 'Sitzung abgelaufen. Bitte erneut anmelden.'; }
      else {
        $code = (string) ($_POST['code'] ?? '');
        $okCode = pg_totp_verify($u['totp'], $code);
        $okBackup = false;
        if (!$okCode) { $okBackup = pg_backup_code_consume($u, $code); if ($okBackup) pg_user_update($u['email'], ['totp_backup' => $u['totp_backup']]); }
        if ($okCode || $okBackup) {
          pg_login_clear(); unset($_SESSION['pg_2fa']); session_regenerate_id(true); pg_login_user($u);
          pg_log_activity('login', ($u['email'] ?? '') . ($okBackup ? ' · Backup-Code' : ' · 2FA'));
          header('Location: index.php'); exit;
        }
        pg_login_record_fail(); $stage = '2fa'; $err = 'Code ungültig. Bitte erneut versuchen.'; usleep(400000);
      }
    } else {
      // Erster Schritt: E-Mail + Passwort
      $u = pg_user_check(trim($_POST['email'] ?? ''), $_POST['pw'] ?? '');
      if ($u && pg_user_has_2fa($u)) {
        $_SESSION['pg_2fa'] = ['email' => $u['email'], 'time' => time()]; $stage = '2fa';
      } elseif ($u) {
        pg_login_clear(); session_regenerate_id(true); pg_login_user($u);
        pg_log_activity('login', $u['email'] ?? '');
        header('Location: index.php'); exit;
      } else {
        pg_login_record_fail(); $err = 'E-Mail oder Passwort falsch.'; usleep(400000);
      }
    }
  }

  pg_view_head('Login');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  if ($stage === '2fa') {
    echo '<h1>Bestätigung</h1>';
    echo '<p class="muted" style="margin:-.4rem 0 1rem;font-size:.88rem">Gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>';
    if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="twofa" value="1">';
    echo '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 \-]*" maxlength="9" placeholder="123 456" required autofocus class="otp-input">';
    echo '<button class="btn-primary" type="submit">Anmelden</button></form>';
    echo '<p class="muted" style="margin-top:1rem;font-size:.82rem">Kein Zugriff auf die App? Gib einen deiner <b>Wiederherstellungscodes</b> ein.</p>';
    echo '<p style="margin-top:.6rem"><a class="muted" style="font-size:.82rem" href="index.php?cancel=1">← Andere Anmeldung</a></p>';
  } else {
    echo '<h1>Anmelden</h1>';
    if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
    echo '<input type="email" name="email" placeholder="E-Mail-Adresse" required autofocus>';
    echo '<input type="password" name="pw" placeholder="Passwort" required>';
    echo '<button class="btn-primary" type="submit">Einloggen</button></form>';
    echo '<p class="muted" style="margin-top:1rem;font-size:.85rem">Per Einladung hier? Nutze den Link aus deiner E-Mail.</p>';
  }
  echo '</div></div>'; pg_view_foot(); exit;
}

// Ab hier: eingeloggt. Tägliches Auto-Backup anstoßen (cron-frei, idempotent).
if (pg_is_owner()) pg_backup_auto_maybe();

/* ---- Speichern ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  pg_csrf_check();
  $key = $_POST['collection'] ?? '';
  if (!isset($SCHEMA[$key])) { http_response_code(404); exit('Unbekannter Bereich.'); }
  if (!pg_can($key)) { http_response_code(403); exit('Keine Berechtigung für diesen Bereich.'); }
  $lang = ($_POST['lang'] ?? 'de') === 'en' ? 'en' : 'de';
  $data = pg_normalize_fields($SCHEMA[$key]['fields'], $_POST['d'] ?? []);

  // Auto-Newsletter: veröffentlichte Patchnotes mit "notify" einmalig versenden (nur DE-Basis)
  $sentCount = 0;
  if ($key === 'patchnotes' && $lang === 'de' && isset($data['posts']) && is_array($data['posts'])) {
    $old = pg_load_json($SCHEMA[$key]['file']);
    $oldNotified = [];
    foreach (($old['posts'] ?? []) as $op) if (!empty($op['slug'])) $oldNotified[$op['slug']] = $op['notified'] ?? '';
    foreach ($data['posts'] as &$post) {
      $slug = $post['slug'] ?? '';
      $already = $oldNotified[$slug] ?? '';
      $post['notified'] = $already;   // bisherigen Status erhalten (nicht im Schema -> sonst verloren)
      $isPublic = empty($post['draft']) && (empty($post['publishAt']) || strtotime($post['publishAt']) <= time());
      if (!empty($post['notify']) && $isPublic && $already === '') {
        $n = pg_newsletter_send_post($post);
        if ($n > 0) { $post['notified'] = date('c'); $sentCount += $n; }
      }
    }
    unset($post);
  }

  // Papierkorb: entfernte Einträge (nur DE-Basis) vor dem Überschreiben sichern
  $trashField = pg_trash_field($key);
  if ($trashField && $lang === 'de') {
    pg_trash_capture($key, $trashField, pg_load_json($SCHEMA[$key]['file']), $data);
  }

  $ok = pg_save_json(pg_lang_file($SCHEMA[$key]['file'], $lang), $data);
  if ($ok) pg_log_activity('Gespeichert', $SCHEMA[$key]['label'] . ' (' . strtoupper($lang) . ')' . ($sentCount ? ' · Newsletter an ' . $sentCount : ''));
  // Autosave/AJAX: JSON zurückgeben statt Redirect
  if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'sent' => $sentCount, 'time' => date('H:i')]);
    exit;
  }
  $extra = $sentCount > 0 ? '&sent=' . $sentCount : '';
  header('Location: index.php?collection=' . urlencode($key) . '&lang=' . $lang . ($ok ? '&saved=1' : '&error=1') . $extra);
  exit;
}

/* ---- Automatisch ins Englische übersetzen ---- */
if ($action === 'translate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  pg_csrf_check();
  $key = $_POST['collection'] ?? '';
  if (!isset($SCHEMA[$key]) || !pg_can($key)) { http_response_code(403); exit('Keine Berechtigung.'); }
  @set_time_limit(180);
  $de = pg_load_json($SCHEMA[$key]['file']);
  $en = pg_translate_data($SCHEMA[$key]['fields'], $de);
  $ok = pg_save_json(pg_lang_file($SCHEMA[$key]['file'], 'en'), $en);
  header('Location: index.php?collection=' . urlencode($key) . '&lang=en&' . ($ok ? 'translated=1' : 'error=1'));
  exit;
}

/* ---- Backup: Export (alle Inhalte als eine Datei) ---- */
if ($action === 'backup_export') {
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  $json = json_encode(pg_backup_build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $fname = 'planigames-backup-' . date('Y-m-d-Hi') . '.json';
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fname . '"');
  header('Content-Length: ' . strlen($json));
  echo $json; exit;
}

/* ---- Backup: gespeicherten Snapshot herunterladen ---- */
if ($action === 'backup_download') {
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  $name = (string) ($_GET['file'] ?? '');
  $path = PG_BACKUP_DIR . '/' . $name;
  if (!pg_backup_name_ok($name) || !is_file($path)) { http_response_code(404); exit('Nicht gefunden.'); }
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="planigames-' . $name . '"');
  header('Content-Length: ' . filesize($path));
  readfile($path); exit;
}

/* ---- Backup: jetzt sichern / Snapshot wiederherstellen / löschen ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['backup_now']) || isset($_POST['restore_backup']) || isset($_POST['delete_backup']))) {
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  pg_csrf_check();
  if (isset($_POST['backup_now'])) {
    $name = pg_backup_write('manual');
    $_SESSION['pg_backup_msg'] = $name ? ['ok', '✓ Snapshot gespeichert: ' . $name] : ['err', 'Konnte nicht speichern (Schreibrechte am Ordner data/backups prüfen).'];
  } else {
    $name = (string) ($_POST['file'] ?? '');
    $path = PG_BACKUP_DIR . '/' . $name;
    if (!pg_backup_name_ok($name) || !is_file($path)) {
      $_SESSION['pg_backup_msg'] = ['err', 'Backup nicht gefunden.'];
    } elseif (isset($_POST['delete_backup'])) {
      @unlink($path);
      $_SESSION['pg_backup_msg'] = ['ok', 'Snapshot gelöscht.'];
    } else { // restore_backup
      $data = json_decode((string) file_get_contents($path), true);
      $n = 0;
      if (is_array($data) && isset($data['files']) && is_array($data['files'])) {
        foreach ($data['files'] as $fn => $content) {
          $base = basename((string) $fn);
          if (pg_backup_allowed($base) && is_string($content) && @file_put_contents(PG_DATA_DIR . '/' . $base, $content) !== false) $n++;
        }
      }
      if ($n > 0) pg_log_activity('Backup wiederhergestellt', $name . ' (' . $n . ' Dateien)');
      $_SESSION['pg_backup_msg'] = $n > 0 ? ['ok', '✓ Wiederhergestellt aus ' . $name . ': ' . $n . ' Dateien.'] : ['err', 'Backup enthielt keine gültigen Inhalte.'];
    }
  }
  header('Location: index.php?view=backup'); exit;
}

/* ---- Backup: Import (Inhalte aus einer Backup-Datei wiederherstellen) ---- */
if ($action === 'backup_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  pg_csrf_check();
  $msg = null;
  if (empty($_FILES['backup']['tmp_name']) || !is_uploaded_file($_FILES['backup']['tmp_name'])) {
    $msg = ['err', 'Keine Datei empfangen. Bitte eine Backup-Datei auswählen.'];
  } else {
    $data = json_decode((string) file_get_contents($_FILES['backup']['tmp_name']), true);
    if (!is_array($data) || (($data['_meta']['app'] ?? '') !== 'PLANIGAMES') || !isset($data['files']) || !is_array($data['files'])) {
      $msg = ['err', 'Das ist keine gültige PLANIGAMES-Backup-Datei.'];
    } else {
      $n = 0;
      foreach ($data['files'] as $name => $content) {
        $base = basename((string) $name);
        if (!pg_backup_allowed($base) || !is_string($content)) continue;
        if (@file_put_contents(PG_DATA_DIR . '/' . $base, $content) !== false) $n++;
      }
      if ($n > 0) pg_log_activity('Backup eingespielt', $n . ' Dateien');
      $msg = $n > 0
        ? ['ok', '✓ Backup eingespielt: ' . $n . ' Datei' . ($n === 1 ? '' : 'en') . ' wiederhergestellt.']
        : ['err', 'Die Datei enthielt keine gültigen Inhalte.'];
    }
  }
  $_SESSION['pg_backup_msg'] = $msg;
  header('Location: index.php?view=backup'); exit;
}

/* ---- Newsletter-Abos: CSV-Export ---- */
if ($action === 'subscribers_csv') {
  if (!pg_can('subscribers')) { http_response_code(403); exit('Keine Berechtigung.'); }
  $list = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="newsletter-abos.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['email', 'date', 'source']);
  foreach ($list as $r) fputcsv($out, [$r['email'] ?? '', $r['date'] ?? '', $r['source'] ?? '']);
  fclose($out); exit;
}

/* ---- Newsletter-Abos: leeren ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_subscribers'])) {
  pg_csrf_check();
  if (!pg_can('subscribers')) { http_response_code(403); exit('Keine Berechtigung.'); }
  pg_save_json(PG_DATA_DIR . '/subscribers.json', []);
  header('Location: index.php?view=subscribers&cleared=1'); exit;
}

/* ---- Newsletter-Abos: Ansicht ---- */
if (($_GET['view'] ?? '') === 'subscribers') {
  if (!pg_can('subscribers')) { header('Location: index.php'); exit; }
  $list = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  $confirmedN = count(pg_confirmed_emails($list));
  $pendingN = count($list) - $confirmedN;
  $list = array_reverse($list);
  pg_view_head('Newsletter-Abos');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Liste geleert.</div>';
  echo '<div class="editor-head"><div><h1>📬 Newsletter-Abos</h1>'
     . '<p class="muted">' . count($list) . ' Anmeldung' . (count($list) === 1 ? '' : 'en') . ' · <b>' . $confirmedN . '</b> bestätigt'
     . ($pendingN > 0 ? ' · ' . $pendingN . ' ausstehend' : '') . '. Nur Bestätigte erhalten den Newsletter.</p></div>';
  if ($list) echo '<a class="btn-primary" href="index.php?action=subscribers_csv">CSV exportieren</a>';
  echo '</div>';
  if (!$list) {
    echo '<p class="muted">Noch keine Anmeldungen.</p>';
  } else {
    echo '<table class="subs"><thead><tr><th>E-Mail</th><th>Status</th><th>Datum</th><th>Quelle</th></tr></thead><tbody>';
    foreach ($list as $r) {
      $isConf = !array_key_exists('confirmed', $r) || !empty($r['confirmed']);
      $status = $isConf ? '<span style="color:#9ff0b5">✓ bestätigt</span>' : '<span style="color:#ffb37a">⏳ ausstehend</span>';
      echo '<tr><td>' . pg_h($r['email'] ?? '') . '</td><td>' . $status . '</td><td>'
         . pg_h(substr($r['date'] ?? '', 0, 10)) . '</td><td>' . pg_h($r['source'] ?? '') . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<form method="post" class="clear-form" onsubmit="return confirm(\'Wirklich ALLE Abos löschen? (Vorher exportieren!)\')">'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
       . '<button class="btn-danger" name="clear_subscribers" value="1">Alle Abos löschen</button></form>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Kontakt-Anfragen: einzeln löschen / alle leeren ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_contact']) || isset($_POST['clear_contacts']))) {
  pg_csrf_check();
  if (!pg_can('contacts')) { http_response_code(403); exit('Keine Berechtigung.'); }
  $list = pg_load_json(PG_DATA_DIR . '/contacts.json');
  if (isset($_POST['clear_contacts'])) {
    $list = [];
  } else {
    $i = (int) ($_POST['idx'] ?? -1);
    if (isset($list[$i])) array_splice($list, $i, 1);
  }
  pg_save_json(PG_DATA_DIR . '/contacts.json', array_values($list));
  header('Location: index.php?view=contacts' . (isset($_POST['clear_contacts']) ? '&cleared=1' : '&deleted=1')); exit;
}

/* ---- Kontakt-Anfrage: Status setzen (offen / beantwortet / erledigt) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_contact_status'])) {
  pg_csrf_check();
  if (!pg_can('contacts')) { http_response_code(403); exit('Keine Berechtigung.'); }
  $list = pg_load_json(PG_DATA_DIR . '/contacts.json');
  $i = (int) ($_POST['idx'] ?? -1);
  $st = (string) ($_POST['status'] ?? '');
  if (isset($list[$i]) && array_key_exists($st, pg_contact_statuses())) {
    $list[$i]['status'] = $st;
    pg_save_json(PG_DATA_DIR . '/contacts.json', $list);
  }
  $fl = trim((string) ($_POST['filter'] ?? ''));
  header('Location: index.php?view=contacts' . ($fl !== '' ? '&filter=' . urlencode($fl) : '') . '#c' . $i); exit;
}

/* ---- Devlog-Kommentare: Moderation ---- */
if (($_GET['view'] ?? '') === 'comments' || isset($_POST['approve_comment']) || isset($_POST['unapprove_comment']) || isset($_POST['delete_comment'])) {
  if (!pg_can('contacts')) { header('Location: index.php'); exit; }
  $cfile = PG_DATA_DIR . '/comments.json';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $list = pg_load_json($cfile); if (!is_array($list)) $list = [];
    $id = (string) ($_POST['id'] ?? '');
    if (isset($_POST['delete_comment'])) {
      $list = array_values(array_filter($list, fn($c) => ($c['id'] ?? '') !== $id));
    } else {
      $set = isset($_POST['approve_comment']);
      foreach ($list as &$c) if (($c['id'] ?? '') === $id) $c['approved'] = $set;
      unset($c);
    }
    pg_save_json($cfile, $list);
    header('Location: index.php?view=comments'); exit;
  }
  $list = pg_load_json($cfile); if (!is_array($list)) $list = [];
  usort($list, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
  $pending = array_filter($list, fn($c) => empty($c['approved']));
  $approved = array_filter($list, fn($c) => !empty($c['approved']));
  pg_view_head('Kommentare');
  pg_view_topbar($SCHEMA, null);
  $csrf = pg_h(pg_csrf());
  $renderC = function ($c, $isPending) use ($csrf) {
    $h = '<div class="contact-card' . ($isPending ? ' st-offen' : '') . '">';
    $h .= '<div class="contact-head"><div><span class="contact-name">' . pg_h($c['name'] ?? '') . '</span> '
        . '<span class="muted" style="font-size:.82rem">zu <code>' . pg_h($c['slug'] ?? '') . '</code></span></div>'
        . '<span class="contact-date">' . pg_h(substr($c['date'] ?? '', 0, 16)) . '</span></div>';
    $h .= '<div class="contact-msg">' . nl2br(pg_h($c['body'] ?? '')) . '</div>';
    $h .= '<div class="contact-actions"><form method="post" style="margin:0"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($c['id'] ?? '') . '">';
    $h .= $isPending
      ? '<button class="btn-add" name="approve_comment" value="1">✓ Freigeben</button>'
      : '<button class="btn-add" name="unapprove_comment" value="1">↩︎ Verbergen</button>';
    $h .= '</form><form method="post" style="margin:0" onsubmit="return confirm(\'Kommentar löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($c['id'] ?? '') . '">'
        . '<button class="btn-danger sm" name="delete_comment" value="1">Löschen</button></form></div></div>';
    return $h;
  };
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>💬 Devlog-Kommentare</h1><p class="muted">'
     . count($pending) . ' zu prüfen · ' . count($approved) . ' freigegeben.</p></div></div>';
  echo '<h2 class="sub-h">Zu prüfen</h2>';
  if (!$pending) echo '<div class="mailnote">Nichts zu moderieren. 🎉</div>';
  else { echo '<div class="contacts">'; foreach ($pending as $c) echo $renderC($c, true); echo '</div>'; }
  if ($approved) {
    echo '<h2 class="sub-h">Freigegeben</h2><div class="contacts">';
    foreach ($approved as $c) echo $renderC($c, false);
    echo '</div>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Key-Verteilung (Presse/Streamer) ---- */
if ($action === 'keys_export') {
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="planigames-keys-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Key', 'Plattform', 'Status', 'Empfänger', 'E-Mail', 'Notiz', 'Datum']);
  foreach (pg_keys_load() as $k) fputcsv($out, [$k['key'] ?? '', $k['platform'] ?? '', $k['status'] ?? '', $k['recipient'] ?? '', $k['email'] ?? '', $k['note'] ?? '', substr($k['date'] ?? '', 0, 10)]);
  fclose($out); exit;
}
$pg_keys_posts = ['keys_add', 'key_assign', 'key_redeem', 'key_reset', 'key_del'];
if (($_GET['view'] ?? '') === 'keys' || array_intersect($pg_keys_posts, array_keys($_POST))) {
  if (!pg_is_owner()) { header('Location: index.php'); exit; }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $keys = pg_keys_load();
    $id = (string) ($_POST['id'] ?? '');
    if (isset($_POST['keys_add'])) {
      $platform = trim((string) ($_POST['platform'] ?? '')) ?: 'Steam';
      $added = 0;
      foreach (preg_split('/\r?\n/', (string) ($_POST['bulk'] ?? '')) as $line) {
        $code = trim($line); if ($code === '') continue;
        $keys[] = ['id' => bin2hex(random_bytes(5)), 'key' => mb_substr($code, 0, 80), 'platform' => $platform,
          'status' => 'available', 'recipient' => '', 'email' => '', 'note' => '', 'date' => date('c')];
        $added++;
      }
      if ($added) { pg_keys_save($keys); pg_log_activity('Keys hinzugefügt', $added . '× ' . $platform); }
    } elseif (isset($_POST['key_assign'])) {
      foreach ($keys as &$k) if (($k['id'] ?? '') === $id) {
        $k['recipient'] = trim((string) ($_POST['recipient'] ?? '')); $k['email'] = trim((string) ($_POST['email'] ?? '')); $k['note'] = trim((string) ($_POST['note'] ?? ''));
        $k['status'] = $k['recipient'] !== '' ? 'assigned' : 'available';
      }
      unset($k); pg_keys_save($keys);
    } elseif (isset($_POST['key_redeem'])) {
      foreach ($keys as &$k) if (($k['id'] ?? '') === $id) $k['status'] = 'redeemed'; unset($k); pg_keys_save($keys);
    } elseif (isset($_POST['key_reset'])) {
      foreach ($keys as &$k) if (($k['id'] ?? '') === $id) { $k['status'] = 'available'; $k['recipient'] = ''; $k['email'] = ''; } unset($k); pg_keys_save($keys);
    } elseif (isset($_POST['key_del'])) {
      $keys = array_values(array_filter($keys, fn($k) => ($k['id'] ?? '') !== $id)); pg_keys_save($keys);
    }
    header('Location: index.php?view=keys' . (isset($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : '')); exit;
  }
  $keys = pg_keys_load();
  $st = pg_keys_stats();
  $filter = (string) ($_GET['filter'] ?? '');
  $stLabels = ['available' => 'Verfügbar', 'assigned' => 'Vergeben', 'redeemed' => 'Eingelöst'];
  pg_view_head('Key-Verteilung');
  pg_view_topbar($SCHEMA, null);
  $csrf = pg_h(pg_csrf());
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>🔑 Key-Verteilung</h1><p class="muted">Steam-/itch-Keys an Presse &amp; Streamer vergeben und die Einlösung verfolgen.</p></div>'
     . ($keys ? '<a class="btn-add" href="index.php?action=keys_export">⬇ CSV</a>' : '') . '</div>';
  echo '<div class="stat-cards">';
  echo '<div class="stat-card"><span class="stat-num">' . $st['available'] . '</span><span class="stat-lbl">Verfügbar</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . $st['assigned'] . '</span><span class="stat-lbl">Vergeben</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . $st['redeemed'] . '</span><span class="stat-lbl">Eingelöst</span></div>';
  echo '</div>';
  // Keys hinzufügen
  echo '<h2 class="sub-h">Keys hinzufügen</h2>';
  echo '<form method="post" class="key-add"><input type="hidden" name="csrf" value="' . $csrf . '">';
  echo '<input type="text" name="platform" placeholder="Plattform (z. B. Steam)" value="Steam">';
  echo '<textarea name="bulk" rows="3" placeholder="Ein Key pro Zeile …" required></textarea>';
  echo '<button class="btn-primary" name="keys_add" value="1">＋ Hinzufügen</button></form>';
  if ($keys) {
    // Filter
    echo '<div class="cfilter" style="margin-top:1.4rem"><span class="kb-filter-lbl">Filter:</span>';
    $tabs = ['' => 'Alle (' . $st['total'] . ')'] + ['available' => 'Verfügbar (' . $st['available'] . ')', 'assigned' => 'Vergeben (' . $st['assigned'] . ')', 'redeemed' => 'Eingelöst (' . $st['redeemed'] . ')'];
    foreach ($tabs as $k => $lbl) echo '<a class="cfilter-tab' . ($k === $filter ? ' on' : '') . '" href="index.php?view=keys' . ($k ? '&filter=' . $k : '') . '">' . pg_h($lbl) . '</a>';
    echo '</div>';
    echo '<table class="subs"><thead><tr><th>Key</th><th>Plattform</th><th>Status</th><th>Empfänger</th><th></th></tr></thead><tbody>';
    foreach (array_reverse($keys) as $k) {
      $kst = $k['status'] ?? 'available';
      if ($filter !== '' && $kst !== $filter) continue;
      $badge = '<span class="cstatus st-' . ($kst === 'redeemed' ? 'erledigt' : ($kst === 'assigned' ? 'beantwortet' : 'offen')) . '">' . $stLabels[$kst] . '</span>';
      echo '<tr><td><code class="keycode" data-copy="' . pg_h($k['key']) . '" title="Kopieren">' . pg_h($k['key']) . '</code></td>'
         . '<td>' . pg_h($k['platform'] ?? '') . '</td><td>' . $badge . '</td>'
         . '<td><details class="key-edit"><summary>' . ($k['recipient'] !== '' ? pg_h($k['recipient']) : '<span class="muted">— zuweisen —</span>') . '</summary>'
         . '<form method="post" class="key-form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($k['id']) . '">'
         . '<input type="text" name="recipient" value="' . pg_h($k['recipient'] ?? '') . '" placeholder="Name / Outlet">'
         . '<input type="text" name="email" value="' . pg_h($k['email'] ?? '') . '" placeholder="E-Mail (optional)">'
         . '<input type="text" name="note" value="' . pg_h($k['note'] ?? '') . '" placeholder="Notiz">'
         . '<button class="btn-primary" name="key_assign" value="1">Speichern</button></form></details></td>'
         . '<td style="white-space:nowrap">'
         . ($kst !== 'redeemed' ? '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($k['id']) . '"><button class="btn-add" name="key_redeem" value="1" title="Als eingelöst markieren">✓</button></form> ' : '')
         . ($kst !== 'available' ? '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($k['id']) . '"><button class="btn-add" name="key_reset" value="1" title="Zurücksetzen">↺</button></form> ' : '')
         . '<form method="post" style="display:inline" onsubmit="return confirm(\'Key löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($k['id']) . '"><button class="btn-danger sm" name="key_del" value="1">✕</button></form>'
         . '</td></tr>';
    }
    echo '</tbody></table>';
  } else {
    echo '<div class="mailnote" style="margin-top:1.2rem">Noch keine Keys. Füge oben welche hinzu (einen pro Zeile).</div>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Vorschlagsbox: Moderation ---- */
if (($_GET['view'] ?? '') === 'suggestions' || isset($_POST['approve_sugg']) || isset($_POST['del_sugg']) || isset($_POST['sugg_status'])) {
  if (!pg_can('contacts')) { header('Location: index.php'); exit; }
  $sfile = PG_DATA_DIR . '/suggestions.json';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $list = pg_load_json($sfile); if (!is_array($list)) $list = [];
    $id = (string) ($_POST['id'] ?? '');
    if (isset($_POST['del_sugg'])) {
      $list = array_values(array_filter($list, fn($s) => ($s['id'] ?? '') !== $id));
    } elseif (isset($_POST['approve_sugg'])) {
      foreach ($list as &$s) if (($s['id'] ?? '') === $id) $s['approved'] = true; unset($s);
    } elseif (isset($_POST['sugg_status'])) {
      $st = (string) ($_POST['status'] ?? 'open');
      if (in_array($st, ['open', 'planned', 'done'], true)) { foreach ($list as &$s) if (($s['id'] ?? '') === $id) $s['status'] = $st; unset($s); }
    }
    pg_save_json($sfile, $list);
    header('Location: index.php?view=suggestions'); exit;
  }
  $list = pg_load_json($sfile); if (!is_array($list)) $list = [];
  $pending = array_filter($list, fn($s) => empty($s['approved']));
  $approved = array_filter($list, fn($s) => !empty($s['approved']));
  usort($approved, fn($a, $b) => (int) ($b['votes'] ?? 0) <=> (int) ($a['votes'] ?? 0));
  $stLabels = ['open' => 'Offen', 'planned' => 'Geplant', 'done' => 'Umgesetzt'];
  pg_view_head('Vorschläge');
  pg_view_topbar($SCHEMA, null);
  $csrf = pg_h(pg_csrf());
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>💡 Vorschläge</h1><p class="muted">' . count($pending) . ' zu prüfen · ' . count($approved) . ' veröffentlicht. Freigegebene erscheinen in der Vorschlagsbox auf der Startseite.</p></div></div>';
  echo '<h2 class="sub-h">Zu prüfen</h2>';
  if (!$pending) echo '<div class="mailnote">Nichts zu prüfen. 🎉</div>';
  else {
    echo '<div class="contacts">';
    foreach ($pending as $s) {
      echo '<div class="contact-card st-offen"><div class="contact-msg">' . pg_h($s['text'] ?? '') . '</div>'
         . '<div class="contact-actions"><form method="post" style="margin:0"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($s['id'] ?? '') . '"><button class="btn-add" name="approve_sugg" value="1">✓ Freigeben</button></form>'
         . '<form method="post" style="margin:0" onsubmit="return confirm(\'Löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($s['id'] ?? '') . '"><button class="btn-danger sm" name="del_sugg" value="1">Löschen</button></form></div></div>';
    }
    echo '</div>';
  }
  if ($approved) {
    echo '<h2 class="sub-h">Veröffentlicht (nach Stimmen)</h2><table class="subs"><thead><tr><th>Vorschlag</th><th>Stimmen</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($approved as $s) {
      echo '<tr><td>' . pg_h($s['text'] ?? '') . '</td><td>' . (int) ($s['votes'] ?? 0) . '</td>'
         . '<td><form method="post" style="margin:0;display:flex;gap:.3rem"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($s['id'] ?? '') . '"><select name="status" onchange="this.form.querySelector(\'[name=sugg_status]\').click()">';
      foreach ($stLabels as $k => $lbl) echo '<option value="' . $k . '"' . (($s['status'] ?? 'open') === $k ? ' selected' : '') . '>' . $lbl . '</option>';
      echo '</select><button type="submit" name="sugg_status" value="1" hidden></button></form></td>'
         . '<td><form method="post" style="margin:0" onsubmit="return confirm(\'Löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . pg_h($s['id'] ?? '') . '"><button class="btn-danger sm" name="del_sugg" value="1">✕</button></form></td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Schnellantwort-Vorlagen: speichern ---- */
$pg_tpl_field = ['name'=>'templates','label'=>'Schnellantwort-Vorlagen','widget'=>'list','summary'=>'title','label_singular'=>'Vorlage',
  'hint'=>'Textbausteine für wiederkehrende Antworten. Im E-Mail-Postfach unter „Verfassen" einfügbar.','fields'=>[
    ['name'=>'title','label'=>'Titel','widget'=>'string','hint'=>'Kurzname, z. B. „Danke für Feedback".'],
    ['name'=>'body','label'=>'Text','widget'=>'text'],
  ]];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_templates'])) {
  pg_csrf_check();
  if (!(pg_can('mail') || pg_can('contacts'))) { http_response_code(403); exit('Keine Berechtigung.'); }
  $norm = pg_normalize_field($pg_tpl_field, $_POST['d']['templates'] ?? []);
  $norm = array_values(array_filter($norm, fn($t) => trim(($t['title'] ?? '') . ($t['body'] ?? '')) !== ''));
  pg_templates_save($norm);
  header('Location: index.php?view=templates&saved=1'); exit;
}

/* ---- Schnellantwort-Vorlagen: Ansicht ---- */
if (($_GET['view'] ?? '') === 'templates') {
  if (!(pg_can('mail') || pg_can('contacts'))) { header('Location: index.php'); exit; }
  $tpls = pg_templates_load();
  pg_view_head('Schnellantwort-Vorlagen');
  pg_view_topbar($SCHEMA, null);
  echo '<form method="post" class="editor" id="editor">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  if (isset($_GET['saved'])) echo '<div class="flash ok">✓ Vorlagen gespeichert.</div>';
  echo '<div class="editor-head"><div><h1>⚡ Schnellantwort-Vorlagen</h1>'
     . '<p class="muted">Textbausteine für wiederkehrende Antworten – beim Verfassen einer Mail per Klick einfügbar.</p></div>'
     . '<div class="editor-actions"><button class="btn-primary" name="save_templates" value="1">Speichern</button></div></div>';
  echo '<div class="fields">';
  echo pg_render_fields([$pg_tpl_field], ['templates' => $tpls], 'd', 'templates');
  echo '</div>';
  echo '<div class="editor-foot"><button class="btn-primary" name="save_templates" value="1">Speichern</button></div>';
  echo '</form>';
  pg_view_foot();
  exit;
}

/* ---- Planungs-Board (Kanban, mehrere Boards) ---- */
$pg_board_posts = ['board_add_card', 'board_edit_card', 'board_del_card', 'board_add_col', 'board_rename_col', 'board_del_col', 'board_archive_card', 'board_unarchive_card', 'board_add', 'board_rename', 'board_del'];
if (($_GET['view'] ?? '') === 'board' || array_intersect($pg_board_posts, array_keys($_POST))) {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $board = pg_board_load();
  $mkLabels = function ($s) {
    $out = [];
    foreach (explode(',', (string) $s) as $l) { $l = trim($l); if ($l !== '' && !in_array($l, $out, true)) $out[] = mb_substr($l, 0, 24); }
    return array_slice($out, 0, 8);
  };
  // aktuelles Board bestimmen
  $bi = pg_board_index($board, (string) ($_GET['board'] ?? $_POST['board'] ?? ''));
  $curId = $board['boards'][$bi]['id'];

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    // --- Board-Verwaltung ---
    if (isset($_POST['board_add'])) {
      $nb = ['id' => pg_board_id('board'), 'name' => trim((string) ($_POST['name'] ?? '')) ?: 'Neues Board', 'game' => trim((string) ($_POST['game'] ?? '')), 'columns' => pg_board_default_columns()];
      $board['boards'][] = $nb; pg_board_save($board);
      header('Location: index.php?view=board&board=' . urlencode($nb['id'])); exit;
    }
    if (isset($_POST['board_rename'])) {
      $name = trim((string) ($_POST['name'] ?? ''));
      if ($name !== '') { $board['boards'][$bi]['name'] = $name; $board['boards'][$bi]['game'] = trim((string) ($_POST['game'] ?? '')); pg_board_save($board); }
      header('Location: index.php?view=board&board=' . urlencode($curId)); exit;
    }
    if (isset($_POST['board_del'])) {
      if (count($board['boards']) > 1) { array_splice($board['boards'], $bi, 1); pg_board_save($board); }
      header('Location: index.php?view=board'); exit;
    }
    // --- Karten / Spalten im aktuellen Board ---
    $colId = $_POST['col'] ?? '';
    $cardId = $_POST['card'] ?? '';
    $ci = -1;
    foreach ($board['boards'][$bi]['columns'] as $k => $c) if ($c['id'] === $colId) $ci = $k;
    if (isset($_POST['board_add_card']) && $ci >= 0) {
      $title = trim((string) ($_POST['title'] ?? ''));
      if ($title !== '') {
        $board['boards'][$bi]['columns'][$ci]['cards'][] = ['id' => pg_board_id('card'), 'title' => $title, 'text' => '', 'color' => '',
          'due' => '', 'game' => $board['boards'][$bi]['game'] ?? '', 'priority' => '', 'assignee' => '', 'labels' => [], 'checklist' => [], 'comments' => [], 'archived' => false, 'created' => date('c')];
        pg_board_save($board);
      }
    } elseif (isset($_POST['board_edit_card'])) {
      $chk = [];
      $texts = (array) ($_POST['chk_text'] ?? []);
      $dones = (array) ($_POST['chk_done'] ?? []);
      foreach ($texts as $i => $tx) { $tx = trim((string) $tx); if ($tx === '') continue; $chk[] = ['text' => mb_substr($tx, 0, 120), 'done' => isset($dones[$i])]; }
      foreach (preg_split('/\r?\n/', (string) ($_POST['chk_new'] ?? '')) as $ln) { $ln = trim($ln); if ($ln !== '') $chk[] = ['text' => mb_substr($ln, 0, 120), 'done' => false]; }
      $newCmt = trim((string) ($_POST['comment_new'] ?? ''));
      foreach ($board['boards'][$bi]['columns'] as &$c) foreach ($c['cards'] as &$card) {
        if (($card['id'] ?? '') === $cardId) {
          $card['title'] = trim((string) ($_POST['title'] ?? $card['title']));
          $card['text']  = trim((string) ($_POST['text'] ?? ''));
          $col = (string) ($_POST['color'] ?? '');
          $card['color'] = array_key_exists($col, pg_board_colors()) ? $col : '';
          $card['due']   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['due'] ?? '')) ? $_POST['due'] : '';
          $card['game']  = trim((string) ($_POST['game'] ?? ''));
          $pr = (string) ($_POST['priority'] ?? '');
          $card['priority'] = array_key_exists($pr, pg_board_priorities()) ? $pr : '';
          $as = (string) ($_POST['assignee'] ?? '');
          $card['assignee'] = array_key_exists($as, pg_board_assignees()) ? $as : '';
          $card['labels'] = $mkLabels($_POST['labels'] ?? '');
          $card['checklist'] = $chk;
          if ($newCmt !== '') {
            $card['comments'] = (array) ($card['comments'] ?? []);
            $card['comments'][] = ['author' => pg_current_email(), 'text' => mb_substr($newCmt, 0, 1000), 'date' => date('c')];
          }
        }
      }
      unset($c, $card);
      pg_board_save($board);
    } elseif (isset($_POST['board_archive_card']) || isset($_POST['board_unarchive_card'])) {
      $arch = isset($_POST['board_archive_card']);
      foreach ($board['boards'][$bi]['columns'] as &$c) foreach ($c['cards'] as &$card) if (($card['id'] ?? '') === $cardId) $card['archived'] = $arch;
      unset($c, $card);
      pg_board_save($board);
    } elseif (isset($_POST['board_del_card'])) {
      foreach ($board['boards'][$bi]['columns'] as &$c) $c['cards'] = array_values(array_filter($c['cards'], fn($x) => ($x['id'] ?? '') !== $cardId));
      unset($c);
      pg_board_save($board);
    } elseif (isset($_POST['board_add_col'])) {
      $board['boards'][$bi]['columns'][] = ['id' => pg_board_id('col'), 'title' => trim((string) ($_POST['title'] ?? '')) ?: 'Neue Spalte', 'cards' => []];
      pg_board_save($board);
    } elseif (isset($_POST['board_rename_col']) && $ci >= 0) {
      $title = trim((string) ($_POST['title'] ?? ''));
      if ($title !== '') { $board['boards'][$bi]['columns'][$ci]['title'] = $title; pg_board_save($board); }
    } elseif (isset($_POST['board_del_col']) && $ci >= 0) {
      array_splice($board['boards'][$bi]['columns'], $ci, 1);
      pg_board_save($board);
    }
    $back = 'index.php?view=board&board=' . urlencode($curId) . (isset($_POST['ret_archive']) ? '&archive=1' : '');
    header('Location: ' . $back); exit;
  }

  // Spiele für Verknüpfung
  $gameMap = [];
  foreach ((array) (pg_load_json($SCHEMA['games']['file'] ?? '')['games'] ?? []) as $g) {
    if (!empty($g['slug'])) $gameMap[$g['slug']] = $g['title'] ?? $g['slug'];
  }
  $colors = pg_board_colors();
  $prios = pg_board_priorities();
  $assignees = pg_board_assignees();
  $csrf = pg_h(pg_csrf());
  $archiveView = !empty($_GET['archive']);
  $boardview = ($_GET['boardview'] ?? '') === 'calendar' ? 'calendar' : 'columns';
  $cols = $board['boards'][$bi]['columns'];

  // Karten-Renderer (für Board & Archiv)
  $renderCard = function ($card) use ($csrf, $colors, $prios, $assignees, $gameMap, $archiveView, $curId) {
    $cc = $card['color'] ?? '';
    $style = $cc !== '' ? ' style="--cc:' . pg_h($cc) . '"' : '';
    $labels = (array) ($card['labels'] ?? []);
    $chk = (array) ($card['checklist'] ?? []);
    $cmts = (array) ($card['comments'] ?? []);
    $done = 0; foreach ($chk as $it) if (!empty($it['done'])) $done++;
    $total = count($chk);
    $prio = $card['priority'] ?? '';
    $assignee = $card['assignee'] ?? '';
    $h = '<div class="kb-card' . ($cc !== '' ? ' has-color' : '') . '" data-card-id="' . pg_h($card['id']) . '"' . $style
       . ' data-labels="' . pg_h(implode('|', $labels)) . '" data-assignee="' . pg_h($assignee) . '" data-prio="' . pg_h($prio) . '">';
    // Kopf: Grip + (Avatar) + Bearbeiten-Panel
    $h .= '<div class="kb-card-top">' . (!$archiveView ? '<span class="kb-grip" data-kb-grip title="Ziehen">⠿</span>' : '');
    if ($assignee !== '') $h .= '<span class="kb-avatar" title="' . pg_h($assignees[$assignee] ?? $assignee) . '">' . pg_h(pg_board_initials($assignees[$assignee] ?? $assignee)) . '</span>';
    $h .= '<details class="kb-cardedit"><summary title="Bearbeiten">✎</summary>'
        . '<form method="post" class="kb-cardform"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="card" value="' . pg_h($card['id']) . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
        . ($archiveView ? '<input type="hidden" name="ret_archive" value="1">' : '')
        . '<div class="kb-modal-head"><span>Karte bearbeiten</span><button type="button" class="kb-modal-x" data-kb-close aria-label="Schließen">✕</button></div>'
        . '<label class="ml">Titel</label><input type="text" name="title" value="' . pg_h($card['title'] ?? '') . '" required>'
        . '<label class="ml">Notiz</label><textarea name="text" rows="2">' . pg_h($card['text'] ?? '') . '</textarea>'
        . '<div class="kb-form-row"><div><label class="ml">Priorität</label><select name="priority">';
    foreach ($prios as $val => $lbl) $h .= '<option value="' . pg_h($val) . '"' . ($val === $prio ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
    $h .= '</select></div><div><label class="ml">Zuständig</label><select name="assignee">';
    foreach ($assignees as $val => $lbl) $h .= '<option value="' . pg_h($val) . '"' . ($val === $assignee ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
    $h .= '</select></div></div>'
        . '<label class="ml">Verknüpftes Spiel</label><select name="game"><option value="">— kein Spiel —</option>';
    foreach ($gameMap as $slug => $title) $h .= '<option value="' . pg_h($slug) . '"' . (($card['game'] ?? '') === $slug ? ' selected' : '') . '>' . pg_h($title) . '</option>';
    $h .= '</select>'
        . '<label class="ml">Labels (Komma-getrennt)</label><input type="text" name="labels" value="' . pg_h(implode(', ', $labels)) . '" placeholder="z. B. Bug, Art, Wichtig">'
        . '<div class="kb-form-row"><div><label class="ml">Farbe</label><select name="color">';
    foreach ($colors as $val => $lbl) $h .= '<option value="' . pg_h($val) . '"' . ($val === $cc ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
    $h .= '</select></div><div><label class="ml">Fällig am</label><input type="date" name="due" value="' . pg_h($card['due'] ?? '') . '"></div></div>';
    // Checklisten-Editor
    $h .= '<label class="ml">Checkliste</label><div class="kb-chkedit">';
    foreach ($chk as $i => $it) {
      $h .= '<label class="kb-chk-erow"><input type="checkbox" name="chk_done[' . $i . ']"' . (!empty($it['done']) ? ' checked' : '') . '>'
          . '<input type="text" name="chk_text[' . $i . ']" value="' . pg_h($it['text'] ?? '') . '"></label>';
    }
    $h .= '</div><textarea name="chk_new" rows="2" placeholder="Neue Punkte – eine pro Zeile"></textarea>';
    // Kommentare
    $h .= '<label class="ml">Kommentare</label>';
    if ($cmts) {
      $h .= '<div class="kb-comments">';
      foreach ($cmts as $cm) {
        $h .= '<div class="kb-cmt"><div class="kb-cmt-head"><b>' . pg_h($cm['author'] ?? '') . '</b><span>' . pg_h(date('d.m. H:i', strtotime((string) ($cm['date'] ?? '')))) . '</span></div>'
            . '<div class="kb-cmt-body">' . nl2br(pg_h($cm['text'] ?? '')) . '</div></div>';
      }
      $h .= '</div>';
    }
    $h .= '<textarea name="comment_new" rows="2" placeholder="Kommentar hinzufügen…"></textarea>';
    $h .= '<div class="kb-cardform-foot"><button class="btn-primary" name="board_edit_card" value="1">Speichern</button>';
    $h .= $archiveView ? '<button class="btn-add" name="board_unarchive_card" value="1">↩︎ Wiederherstellen</button>'
                       : '<button class="btn-add" name="board_archive_card" value="1">🗄️ Archivieren</button>';
    $h .= '<button class="btn-danger sm" name="board_del_card" value="1" onclick="return confirm(\'Karte endgültig löschen?\')">Löschen</button>';
    $h .= '</div></form></details></div>';
    // Vorderseite
    $h .= '<div class="kb-card-title">' . pg_h($card['title'] ?? '') . '</div>';
    if ($prio !== '' || $labels) {
      $h .= '<div class="kb-labels">';
      if ($prio !== '') $h .= '<span class="kb-prio kb-prio-' . $prio . '">' . pg_h($prios[$prio]) . '</span>';
      foreach ($labels as $l) $h .= '<span class="kb-label">' . pg_h($l) . '</span>';
      $h .= '</div>';
    }
    if (trim((string) ($card['text'] ?? '')) !== '') $h .= '<div class="kb-card-text">' . nl2br(pg_h($card['text'])) . '</div>';
    if ($total) {
      $pct = round($done / $total * 100);
      $h .= '<div class="kb-chk"><div class="kb-chk-bar"><span style="width:' . $pct . '%"></span></div><span class="kb-chk-count">' . $done . '/' . $total . '</span></div>';
      $h .= '<ul class="kb-chk-list">';
      foreach ($chk as $i => $it) {
        $h .= '<li><button type="button" class="kb-chk-item' . (!empty($it['done']) ? ' done' : '') . '" data-chk-card="' . pg_h($card['id']) . '" data-chk-i="' . $i . '">'
            . '<span class="kb-chk-box">' . (!empty($it['done']) ? '✓' : '') . '</span>' . pg_h($it['text'] ?? '') . '</button></li>';
      }
      $h .= '</ul>';
    }
    $metas = '';
    if (!empty($card['game']) && isset($gameMap[$card['game']])) $metas .= '<span class="kb-game">🎮 ' . pg_h($gameMap[$card['game']]) . '</span>';
    if ($cmts) $metas .= '<span class="kb-cmt-n">💬 ' . count($cmts) . '</span>';
    if (!empty($card['due'])) {
      $overdue = strtotime($card['due']) < strtotime(date('Y-m-d'));
      $metas .= '<span class="kb-due' . ($overdue ? ' over' : '') . '">📅 ' . pg_h($card['due']) . '</span>';
    }
    if ($metas !== '') $h .= '<div class="kb-card-meta">' . $metas . '</div>';
    $h .= '</div>';
    return $h;
  };

  pg_view_head($archiveView ? 'Board-Archiv' : 'Planungs-Board');
  pg_view_topbar($SCHEMA, null);

  // Archiv-Ansicht (für aktuelles Board)
  if ($archiveView) {
    $arch = [];
    foreach ($cols as $c) foreach ($c['cards'] as $card) if (!empty($card['archived'])) $arch[] = $card;
    echo '<div class="editor wide">';
    echo '<div class="editor-head"><div><h1>🗄️ Board-Archiv</h1><p class="muted">' . count($arch) . ' archivierte Karte' . (count($arch) === 1 ? '' : 'n') . ' in „' . pg_h($board['boards'][$bi]['name']) . '".</p></div>'
       . '<div class="editor-actions"><a class="btn-add" href="index.php?view=board&board=' . urlencode($curId) . '">← Zum Board</a></div></div>';
    if (!$arch) echo '<div class="mailnote">Noch nichts archiviert.</div>';
    else { echo '<div class="kb-archive">'; foreach ($arch as $card) echo $renderCard($card); echo '</div>'; }
    echo '</div>';
    pg_view_foot();
    exit;
  }

  // Labels für die Filterleiste + Archiv-Zähler
  $allLabels = []; $archCount = 0;
  foreach ($cols as $c) foreach ($c['cards'] as $card) {
    if (!empty($card['archived'])) { $archCount++; continue; }
    foreach ((array) ($card['labels'] ?? []) as $l) $allLabels[$l] = true;
  }
  ksort($allLabels);
  $meEmail = pg_current_email();

  echo '<div class="board-wrap">';
  // Board-Umschalter (Tabs)
  echo '<div class="kb-boards">';
  foreach ($board['boards'] as $bd) {
    $on = ($bd['id'] === $curId) ? ' on' : '';
    $gm = !empty($bd['game']) && isset($gameMap[$bd['game']]) ? '🎮 ' : '';
    echo '<a class="kb-board-tab' . $on . '" href="index.php?view=board&board=' . urlencode($bd['id']) . '">' . $gm . pg_h($bd['name']) . '</a>';
  }
  echo '<details class="kb-board-add"><summary class="kb-board-tab" title="Neues Board">＋</summary><div class="kb-board-pop">'
     . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><label class="ml">Board-Name</label>'
     . '<input type="text" name="name" placeholder="z. B. Wobbly Wizards" required><label class="ml">Spiel (optional)</label><select name="game"><option value="">— keins —</option>';
  foreach ($gameMap as $slug => $title) echo '<option value="' . pg_h($slug) . '">' . pg_h($title) . '</option>';
  echo '</select><button class="btn-primary" name="board_add" value="1" style="margin-top:.5rem">Board anlegen</button></form></div></details>';
  echo '</div>';

  $colOn = $boardview === 'columns' ? ' on' : '';
  $calOn = $boardview === 'calendar' ? ' on' : '';
  echo '<div class="editor-head" style="max-width:none;margin:.3rem 1.2rem .4rem"><div><h1>🗂️ ' . pg_h($board['boards'][$bi]['name']) . '</h1>'
     . '<p class="muted">' . ($boardview === 'calendar' ? 'Karten nach Fälligkeitsdatum.' : 'Karten per Anfasser zwischen Spalten ziehen.') . '</p></div>'
     . '<div class="editor-actions">'
     . '<div class="kb-viewtoggle"><a class="kb-vt' . $colOn . '" href="index.php?view=board&board=' . urlencode($curId) . '">▦ Spalten</a>'
     . '<a class="kb-vt' . $calOn . '" href="index.php?view=board&board=' . urlencode($curId) . '&boardview=calendar">📅 Kalender</a></div>'
     . '<a class="btn-add" href="index.php?view=board&board=' . urlencode($curId) . '&archive=1">🗄️ Archiv' . ($archCount ? ' (' . $archCount . ')' : '') . '</a>'
     . '<details class="kb-board-edit"><summary class="btn-add">⚙ Board</summary><div class="kb-board-pop">'
     . '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
     . '<label class="ml">Board-Name</label><input type="text" name="name" value="' . pg_h($board['boards'][$bi]['name']) . '" required>'
     . '<label class="ml">Spiel</label><select name="game"><option value="">— keins —</option>';
  foreach ($gameMap as $slug => $title) echo '<option value="' . pg_h($slug) . '"' . (($board['boards'][$bi]['game'] ?? '') === $slug ? ' selected' : '') . '>' . pg_h($title) . '</option>';
  echo '</select><div style="display:flex;gap:.5rem;margin-top:.5rem"><button class="btn-primary" name="board_rename" value="1">Speichern</button>';
  if (count($board['boards']) > 1) echo '<button class="btn-danger sm" name="board_del" value="1" onclick="return confirm(\'Dieses Board samt Karten löschen?\')">Board löschen</button>';
  echo '</div></form></div></details></div></div>';

  if ($boardview === 'calendar') {
    // --- Kalender-Ansicht: Karten nach Fälligkeitsdatum ---
    $ym = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ym'] ?? '')) ? $_GET['ym'] : date('Y-m');
    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    if (!$first) $first = new DateTime('first day of this month');
    $first->setTime(0, 0, 0);
    $prev = (clone $first)->modify('-1 month')->format('Y-m');
    $next = (clone $first)->modify('+1 month')->format('Y-m');
    $today = date('Y-m-d');
    // Karten nach Datum gruppieren
    $byDate = []; $undated = []; $overdueBefore = [];
    foreach ($cols as $c) foreach ($c['cards'] as $card) {
      if (!empty($card['archived'])) continue;
      $d = $card['due'] ?? '';
      if ($d === '') { $undated[] = $card; continue; }
      $byDate[$d][] = $card;
      if ($d < $today) $overdueBefore[] = $card;
    }
    $monthName = pg_de_month((int) $first->format('n')) . ' ' . $first->format('Y');
    echo '<div class="kb-cal-wrap"><div class="kb-cal-nav">'
       . '<a class="btn-add" href="index.php?view=board&board=' . urlencode($curId) . '&boardview=calendar&ym=' . $prev . '">←</a>'
       . '<span class="kb-cal-title">' . pg_h($monthName) . '</span>'
       . '<a class="btn-add" href="index.php?view=board&board=' . urlencode($curId) . '&boardview=calendar&ym=' . $next . '">→</a>'
       . '<a class="btn-add" href="index.php?view=board&board=' . urlencode($curId) . '&boardview=calendar" style="margin-left:.5rem">Heute</a></div>';
    echo '<div class="kb-cal">';
    foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wd) echo '<div class="kb-cal-wd">' . $wd . '</div>';
    $startDow = ((int) $first->format('N')) - 1; // 0=Mo
    $daysInMonth = (int) $first->format('t');
    for ($i = 0; $i < $startDow; $i++) echo '<div class="kb-cal-cell empty"></div>';
    for ($day = 1; $day <= $daysInMonth; $day++) {
      $ds = $first->format('Y-m') . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
      $isToday = $ds === $today ? ' today' : '';
      echo '<div class="kb-cal-cell' . $isToday . '"><span class="kb-cal-day">' . $day . '</span>';
      foreach (($byDate[$ds] ?? []) as $card) {
        $cc = $card['color'] ?? '';
        $over = $ds < $today ? ' over' : '';
        echo '<a class="kb-cal-card' . $over . '" href="index.php?view=board&board=' . urlencode($curId) . '#card-' . pg_h($card['id']) . '"'
           . ($cc !== '' ? ' style="--cc:' . pg_h($cc) . '"' : '') . '>' . pg_h($card['title'] ?? '') . '</a>';
      }
      echo '</div>';
    }
    echo '</div>'; // .kb-cal
    if ($overdueBefore || $undated) {
      echo '<div class="kb-cal-side">';
      if ($overdueBefore) {
        echo '<div class="kb-cal-box"><h3>⚠️ Überfällig (' . count($overdueBefore) . ')</h3>';
        foreach ($overdueBefore as $card) echo '<a class="kb-cal-card over" href="index.php?view=board&board=' . urlencode($curId) . '">' . pg_h($card['due']) . ' · ' . pg_h($card['title'] ?? '') . '</a>';
        echo '</div>';
      }
      if ($undated) {
        echo '<div class="kb-cal-box"><h3>Ohne Datum (' . count($undated) . ')</h3>';
        foreach ($undated as $card) echo '<a class="kb-cal-card" href="index.php?view=board&board=' . urlencode($curId) . '">' . pg_h($card['title'] ?? '') . '</a>';
        echo '</div>';
      }
      echo '</div>';
    }
    echo '</div>'; // .kb-cal-wrap
    echo '</div>'; // .board-wrap
    pg_view_foot();
    exit;
  }

  // Label-/Zuständig-Filter
  echo '<div class="kb-filter" data-kb-filter><span class="kb-filter-lbl">Filter:</span>'
     . '<button class="kb-filter-tag on" data-label="" data-mine="">Alle</button>';
  if ($meEmail !== '') echo '<button class="kb-filter-tag" data-mine="' . pg_h($meEmail) . '">👤 Meine Karten</button>';
  foreach (array_keys($allLabels) as $l) echo '<button class="kb-filter-tag" data-label="' . pg_h($l) . '">' . pg_h($l) . '</button>';
  echo '</div>';

  echo '<div class="board" data-board data-board-id="' . pg_h($curId) . '">';
  foreach ($cols as $c) {
    $cid = pg_h($c['id']);
    $visible = array_filter($c['cards'], fn($x) => empty($x['archived']));
    echo '<div class="kb-col" data-col-id="' . $cid . '">';
    echo '<div class="kb-col-head"><details class="kb-coledit"><summary>' . pg_h($c['title']) . ' <span class="kb-count">' . count($visible) . '</span></summary>'
       . '<div class="kb-coledit-body">'
       . '<form method="post" class="kb-inline"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<input type="text" name="title" value="' . pg_h($c['title']) . '" required><button class="btn-add" name="board_rename_col" value="1">Umbenennen</button></form>'
       . '<form method="post" onsubmit="return confirm(\'Spalte samt Karten löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<button class="btn-danger sm" name="board_del_col" value="1">Spalte löschen</button></form>'
       . '</div></details></div>';
    echo '<div class="kb-cards" data-kb-cards>';
    foreach ($c['cards'] as $card) { if (!empty($card['archived'])) continue; echo $renderCard($card); }
    echo '</div>';
    echo '<form method="post" class="kb-add"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '"><input type="hidden" name="col" value="' . $cid . '">'
       . '<input type="text" name="title" placeholder="+ Karte hinzufügen" autocomplete="off"><button class="btn-add" name="board_add_card" value="1">+</button></form>';
    echo '</div>';
  }
  echo '<div class="kb-col kb-col-new"><form method="post" class="kb-addcol"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="board" value="' . pg_h($curId) . '">'
     . '<input type="text" name="title" placeholder="Neue Spalte …" required><button class="btn-add" name="board_add_col" value="1">+ Spalte</button></form></div>';
  echo '</div></div>';
  pg_view_foot();
  exit;
}

/* ---- Konto & 2-Faktor-Authentifizierung ---- */
if (($_GET['view'] ?? '') === 'account' || isset($_POST['start_2fa']) || isset($_POST['confirm_2fa'])
    || isset($_POST['disable_2fa']) || isset($_POST['regen_backup'])) {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $me = pg_current_user();
  $flash = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    if (isset($_POST['start_2fa']) && !pg_user_has_2fa($me)) {
      $_SESSION['pg_totp_setup'] = pg_totp_secret();
      header('Location: index.php?view=account#twofa'); exit;
    } elseif (isset($_POST['cancel_setup'])) {
      unset($_SESSION['pg_totp_setup']);
      header('Location: index.php?view=account#twofa'); exit;
    } elseif (isset($_POST['confirm_2fa']) && !empty($_SESSION['pg_totp_setup'])) {
      $sec = $_SESSION['pg_totp_setup'];
      if (pg_totp_verify($sec, $_POST['code'] ?? '')) {
        $codes = pg_backup_codes_gen(8);
        pg_user_update($me['email'], ['totp' => $sec, 'totp_enabled' => true, 'totp_backup' => pg_backup_codes_hash($codes)]);
        unset($_SESSION['pg_totp_setup']);
        $_SESSION['pg_backup_show'] = $codes;
        pg_log_activity('2FA aktiviert');
        header('Location: index.php?view=account&enabled=1#twofa'); exit;
      }
      $flash = '<div class="flash err">Code stimmt nicht. Stimmt die Uhrzeit auf dem Gerät? Bitte erneut versuchen.</div>';
    } elseif (isset($_POST['disable_2fa']) && pg_user_has_2fa($me)) {
      if (pg_totp_verify($me['totp'], $_POST['code'] ?? '') || pg_backup_code_consume($me, $_POST['code'] ?? '')) {
        pg_user_update($me['email'], ['totp' => '', 'totp_enabled' => false, 'totp_backup' => []]);
        pg_log_activity('2FA deaktiviert');
        header('Location: index.php?view=account&disabled=1#twofa'); exit;
      }
      $flash = '<div class="flash err">Code stimmt nicht – 2FA bleibt aktiv.</div>';
    } elseif (isset($_POST['regen_backup']) && pg_user_has_2fa($me)) {
      if (pg_totp_verify($me['totp'], $_POST['code'] ?? '')) {
        $codes = pg_backup_codes_gen(8);
        pg_user_update($me['email'], ['totp_backup' => pg_backup_codes_hash($codes)]);
        $_SESSION['pg_backup_show'] = $codes;
        pg_log_activity('2FA Backup-Codes erneuert');
        header('Location: index.php?view=account#twofa'); exit;
      }
      $flash = '<div class="flash err">Code stimmt nicht.</div>';
    }
  }
  $me = pg_current_user(); // nach evtl. Änderung neu laden
  $backupShow = $_SESSION['pg_backup_show'] ?? null; unset($_SESSION['pg_backup_show']);
  pg_view_head('Konto');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor">';
  if (isset($_GET['enabled']))  echo '<div class="flash ok">✓ Zwei-Faktor-Authentifizierung aktiviert.</div>';
  if (isset($_GET['disabled'])) echo '<div class="flash ok">✓ Zwei-Faktor-Authentifizierung deaktiviert.</div>';
  echo $flash;
  echo '<div class="editor-head"><div><h1>🔒 Konto &amp; Sicherheit</h1>'
     . '<p class="muted">' . pg_h($me['email'] ?? '') . ' · ' . pg_h(ucfirst($me['role'] ?? '')) . '</p></div></div>';
  echo '<div class="fields"><div id="twofa" class="objectfield" style="padding:1.3rem 1.4rem">';
  echo '<h2 class="sub-h" style="margin-top:0">Zwei-Faktor-Authentifizierung (2FA)</h2>';
  $csrf = pg_h(pg_csrf());

  // Frisch erzeugte Backup-Codes (nur einmal anzeigen)
  if ($backupShow) {
    echo '<div class="bk-codes"><p><b>🔑 Deine Wiederherstellungscodes</b> – jetzt sicher speichern! '
       . 'Jeder Code funktioniert einmal, falls du keinen Zugriff auf die App hast.</p><div class="bk-grid">';
    foreach ($backupShow as $c) echo '<code>' . pg_h($c) . '</code>';
    echo '</div></div>';
  }

  if (pg_user_has_2fa($me)) {
    $remain = count($me['totp_backup'] ?? []);
    echo '<p>Status: <span class="cstatus st-erledigt">✓ Aktiv</span></p>';
    echo '<p class="muted">Beim Login wird zusätzlich ein 6-stelliger Code aus deiner App verlangt. '
       . 'Verbleibende Wiederherstellungscodes: <b>' . $remain . '</b>.</p>';
    echo '<div class="acc-actions">';
    echo '<form method="post" class="codeform"><input type="hidden" name="csrf" value="' . $csrf . '">'
       . '<input type="text" name="code" inputmode="numeric" placeholder="App-Code" class="otp-input sm" required>'
       . '<button class="btn-add" name="regen_backup" value="1">Backup-Codes neu erzeugen</button></form>';
    echo '<form method="post" class="codeform" onsubmit="return confirm(\'2FA wirklich deaktivieren?\')"><input type="hidden" name="csrf" value="' . $csrf . '">'
       . '<input type="text" name="code" inputmode="numeric" placeholder="App- oder Backup-Code" class="otp-input sm" required>'
       . '<button class="btn-danger sm" name="disable_2fa" value="1">2FA deaktivieren</button></form>';
    echo '</div>';
  } elseif (!empty($_SESSION['pg_totp_setup'])) {
    $sec = $_SESSION['pg_totp_setup'];
    $uri = pg_otpauth_uri($sec, $me['email'] ?? 'admin');
    echo '<ol class="setup-steps">';
    echo '<li>Öffne deine Authenticator-App (Google Authenticator, Authy, 1Password …) und scanne den QR-Code:';
    echo '<div class="qr-box" data-otpauth="' . pg_h($uri) . '"><div class="qr-loading">QR wird geladen …</div></div>';
    echo '<p class="muted" style="font-size:.82rem">Kein Scanner? Schlüssel manuell eingeben:<br><code class="totp-key">' . pg_h(trim(chunk_split($sec, 4, ' '))) . '</code></p></li>';
    echo '<li>Gib zur Bestätigung den aktuell angezeigten 6-stelligen Code ein:';
    echo '<form method="post" class="codeform" style="margin-top:.6rem"><input type="hidden" name="csrf" value="' . $csrf . '">'
       . '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123 456" class="otp-input sm" required autofocus>'
       . '<button class="btn-primary" name="confirm_2fa" value="1">Aktivieren</button>'
       . '<button class="btn-add" name="cancel_setup" value="1" formnovalidate>Abbrechen</button></form></li>';
    echo '</ol>';
    echo '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>';
    echo '<script>(function(){var b=document.querySelector(".qr-box");if(!b||!window.QRCode)return;b.innerHTML="";new QRCode(b,{text:b.getAttribute("data-otpauth"),width:180,height:180,colorDark:"#0a0a0b",colorLight:"#ffffff"});})();</script>';
  } else {
    echo '<p class="muted">Schütze deinen Zugang zusätzlich mit einer Authenticator-App. Nach dem Passwort wird dann ein '
       . 'einmaliger 6-stelliger Code abgefragt.</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '">'
       . '<button class="btn-primary" name="start_2fa" value="1">2FA einrichten</button></form>';
  }
  echo '</div></div></div>';
  pg_view_foot();
  exit;
}

/* ---- Globale Suche ---- */
if (($_GET['view'] ?? '') === 'search') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $q = trim((string) ($_GET['q'] ?? ''));
  pg_view_head('Suche');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>🔎 Suche</h1>'
     . '<p class="muted">Durchsucht alle Inhalte, Kontakt-Anfragen, Vorlagen &amp; Medien.</p></div></div>';
  echo '<form method="get" class="searchbar"><input type="hidden" name="view" value="search">'
     . '<input type="search" name="q" value="' . pg_h($q) . '" placeholder="Suchbegriff … (mind. 2 Zeichen)" autofocus>'
     . '<button class="btn-primary" type="submit">Suchen</button></form>';
  if ($q !== '' && mb_strlen($q) >= 2) {
    $groups = [];
    // 1) Inhalts-Sammlungen
    foreach ($SCHEMA as $key => $coll) {
      if (!pg_can($key)) continue;
      $matches = [];
      pg_search_walk(pg_load_json($coll['file']), $q, [], $matches);
      foreach ($matches as $m) {
        $groups[$coll['label']][] = ['icon' => $coll['icon'], 'href' => 'index.php?collection=' . pg_h($key),
          'label' => pg_search_pathlabel($m['path']), 'snip' => $m['snip']];
      }
    }
    // 2) Kontakt-Anfragen
    if (pg_can('contacts')) {
      foreach (pg_load_json(PG_DATA_DIR . '/contacts.json') as $r) {
        $hay = trim(($r['name'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['subject'] ?? '') . ' ' . ($r['message'] ?? ''));
        if ($hay !== '' && mb_stripos($hay, $q) !== false) {
          $groups['Kontakt-Anfragen'][] = ['icon' => '✉️', 'href' => 'index.php?view=contacts',
            'label' => $r['name'] ?? 'Anfrage', 'snip' => pg_search_snippet($hay, $q)];
        }
      }
    }
    // 3) Schnellantwort-Vorlagen
    if (pg_can('mail') || pg_can('contacts')) {
      foreach (pg_templates_load() as $t) {
        $hay = trim(($t['title'] ?? '') . ' ' . ($t['body'] ?? ''));
        if ($hay !== '' && mb_stripos($hay, $q) !== false) {
          $groups['Schnellantworten'][] = ['icon' => '⚡', 'href' => 'index.php?view=templates',
            'label' => $t['title'] ?? 'Vorlage', 'snip' => pg_search_snippet($hay, $q)];
        }
      }
    }
    // 4) Medien-Dateinamen
    if (is_dir(PG_MEDIA_DIR)) {
      foreach (scandir(PG_MEDIA_DIR) as $f) {
        if ($f[0] === '.' || is_dir(PG_MEDIA_DIR . '/' . $f)) continue;
        if (mb_stripos($f, $q) !== false) {
          $groups['Medien'][] = ['icon' => '🖼️', 'href' => 'index.php?view=media', 'label' => $f, 'snip' => '/media/' . $f];
        }
      }
    }
    $total = array_sum(array_map('count', $groups));
    echo '<p class="muted" style="margin:.2rem 0 1rem">' . $total . ' Treffer für „<b>' . pg_h($q) . '</b>".</p>';
    if (!$total) {
      echo '<div class="mailnote">Nichts gefunden. Tipp: anderer Begriff oder kürzer suchen.</div>';
    } else {
      foreach ($groups as $area => $hits) {
        echo '<h2 class="sub-h">' . pg_h($area) . ' <span class="muted" style="font-weight:400">(' . count($hits) . ')</span></h2>';
        echo '<div class="searchres">';
        foreach (array_slice($hits, 0, 30) as $h) {
          echo '<a class="sr-item" href="' . pg_h($h['href']) . '">'
             . '<span class="sr-ico">' . pg_h($h['icon']) . '</span>'
             . '<span class="sr-body"><span class="sr-label">' . pg_h($h['label']) . '</span>'
             . '<span class="sr-snip">' . pg_search_highlight($h['snip'], $q) . '</span></span></a>';
        }
        echo '</div>';
      }
    }
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Kontakt-Anfragen: Ansicht ---- */
if (($_GET['view'] ?? '') === 'contacts') {
  if (!pg_can('contacts')) { header('Location: index.php'); exit; }
  $list = pg_load_json(PG_DATA_DIR . '/contacts.json');
  // Beim Ansehen alle als gelesen markieren (für die Ungelesen-Badge)
  $hadUnread = false;
  foreach ($list as &$r) { if (empty($r['read'])) { $r['read'] = true; $hadUnread = true; } }
  unset($r);
  if ($hadUnread) pg_save_json(PG_DATA_DIR . '/contacts.json', $list);
  // Status-Filter
  $statuses = pg_contact_statuses();
  $filter = (string) ($_GET['filter'] ?? '');
  if (!array_key_exists($filter, $statuses)) $filter = '';
  // Zählung je Status
  $counts = ['' => count($list)];
  foreach ($statuses as $k => $_) $counts[$k] = 0;
  foreach ($list as $r) $counts[pg_contact_status($r)]++;
  // Newest first, but keep original index for delete
  $indexed = [];
  foreach ($list as $i => $r) { if ($filter === '' || pg_contact_status($r) === $filter) $indexed[] = [$i, $r]; }
  $indexed = array_reverse($indexed);
  pg_view_head('Kontakt-Anfragen');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Alle Anfragen gelöscht.</div>';
  if (isset($_GET['deleted'])) echo '<div class="flash ok">✓ Anfrage gelöscht.</div>';
  echo '<div class="editor-head"><div><h1>✉️ Kontakt-Anfragen</h1>'
     . '<p class="muted">' . count($list) . ' Nachricht' . (count($list) === 1 ? '' : 'en') . ' über das Kontaktformular.</p></div></div>';
  // Filter-Leiste
  if ($list) {
    echo '<div class="cfilter">';
    $tabs = ['' => 'Alle'] + $statuses;
    foreach ($tabs as $k => $lbl) {
      $on = $k === $filter ? ' on' : '';
      $href = 'index.php?view=contacts' . ($k !== '' ? '&filter=' . urlencode($k) : '');
      echo '<a class="cfilter-tab' . $on . ($k !== '' ? ' st-' . $k : '') . '" href="' . pg_h($href) . '">'
         . pg_h($lbl) . ' <span class="cfilter-n">' . (int) ($counts[$k] ?? 0) . '</span></a>';
    }
    echo '</div>';
  }
  if (!$indexed) {
    echo '<div class="mailnote">' . ($list ? 'Keine Anfragen mit diesem Status.' : 'Noch keine Anfragen. Sie erscheinen hier, sobald jemand das Kontaktformular auf <code>kontakt.html</code> nutzt.') . '</div>';
  } else {
    echo '<div class="contacts">';
    foreach ($indexed as [$i, $r]) {
      $subj = trim($r['subject'] ?? '');
      $cst = pg_contact_status($r);
      echo '<div class="contact-card st-' . $cst . '" id="c' . $i . '">';
      echo '<div class="contact-head"><div><span class="contact-name">' . pg_h($r['name'] ?? '') . '</span> '
         . '<a class="contact-mail" href="mailto:' . pg_h($r['email'] ?? '') . '">' . pg_h($r['email'] ?? '') . '</a></div>'
         . '<div class="contact-headr"><span class="cstatus st-' . $cst . '">' . pg_h($statuses[$cst]) . '</span>'
         . '<span class="contact-date">' . pg_h(substr($r['date'] ?? '', 0, 16)) . '</span></div></div>';
      if ($subj !== '') echo '<div class="contact-subj">' . pg_h($subj) . '</div>';
      echo '<div class="contact-msg">' . nl2br(pg_h($r['message'] ?? '')) . '</div>';
      $replySubj = rawurlencode('Re: ' . ($subj ?: 'Deine Anfrage'));
      $quote = "\n\n\n— Deine Nachricht vom " . substr($r['date'] ?? '', 0, 10) . " —\n"
             . preg_replace('/^/m', '> ', (string) ($r['message'] ?? ''));
      $replyHref = pg_can('mail')
        ? 'mail.php?tab=compose&to=' . rawurlencode($r['email'] ?? '') . '&subject=' . $replySubj . '&body=' . rawurlencode($quote)
        : 'mailto:' . pg_h($r['email'] ?? '') . '?subject=' . $replySubj;
      echo '<div class="contact-actions">'
         . '<a class="btn-add" href="' . pg_h($replyHref) . '">↩︎ Antworten</a>';
      // Status-Umschalter
      echo '<span class="cstatus-set">';
      foreach ($statuses as $k => $lbl) {
        if ($k === $cst) continue;
        echo '<form method="post" style="margin:0"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
           . '<input type="hidden" name="idx" value="' . $i . '"><input type="hidden" name="status" value="' . pg_h($k) . '">'
           . '<input type="hidden" name="filter" value="' . pg_h($filter) . '">'
           . '<button class="btn-status st-' . $k . '" name="set_contact_status" value="1">→ ' . pg_h($lbl) . '</button></form>';
      }
      echo '</span>';
      echo '<form method="post" onsubmit="return confirm(\'Diese Anfrage löschen?\')" style="margin:0">'
         . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
         . '<input type="hidden" name="idx" value="' . $i . '">'
         . '<button class="btn-danger sm" name="delete_contact" value="1">Löschen</button></form>'
         . '</div></div>';
    }
    echo '</div>';
    echo '<form method="post" class="clear-form" onsubmit="return confirm(\'Wirklich ALLE Anfragen löschen?\')">'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
       . '<button class="btn-danger" name="clear_contacts" value="1">Alle Anfragen löschen</button></form>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Medien-Datei löschen ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
  pg_csrf_check();
  $name = basename($_POST['file'] ?? '');
  $path = PG_MEDIA_DIR . '/' . $name;
  // Geschützte Dateien (z. B. das OG-Bild) nicht löschbar
  $protected = ['og.jpg'];
  if ($name !== '' && !in_array($name, $protected, true) && is_file($path)) @unlink($path);
  header('Location: index.php?view=media&deleted=1'); exit;
}

/* ---- Medien-Bibliothek (alle eingeloggten) ---- */
if (($_GET['view'] ?? '') === 'media') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $files = [];
  if (is_dir(PG_MEDIA_DIR)) {
    foreach (scandir(PG_MEDIA_DIR) as $f) {
      if ($f === '.' || $f === '..' || $f[0] === '.') continue;
      $p = PG_MEDIA_DIR . '/' . $f;
      if (!is_file($p)) continue;
      $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
      if (!in_array($ext, PG_UPLOAD_EXT, true)) continue;
      $files[] = ['name' => $f, 'ext' => $ext, 'size' => filesize($p), 'time' => filemtime($p)];
    }
  }
  usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
  $isImg = fn($e) => in_array($e, ['jpg','jpeg','png','webp','gif','svg','avif'], true);
  $isVid = fn($e) => in_array($e, ['mp4','webm','ogg','mov'], true);
  $fmtSize = function($b){ if ($b < 1024) return $b . ' B'; if ($b < 1048576) return round($b/1024) . ' KB'; return round($b/1048576, 1) . ' MB'; };

  pg_view_head('Medien');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  if (isset($_GET['deleted'])) echo '<div class="flash ok">✓ Datei gelöscht.</div>';
  echo '<div class="editor-head"><div><h1>🖼️ Medien-Bibliothek</h1>'
     . '<p class="muted">' . count($files) . ' Datei' . (count($files) === 1 ? '' : 'en') . ' im Ordner <code>/media</code>. Pfad kopieren und in beliebige Bild-/Datei-Felder einsetzen.</p></div></div>';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<div class="media-upload"><label class="btn-up">📤 Datei hochladen<input type="file" id="lib-upload" accept="image/*,video/*" hidden></label>'
     . '<span class="muted" id="lib-upload-status">JPG, PNG, WebP, SVG, GIF, MP4, WebM … bis ca. 30 MB.</span></div>';
  if (!$files) {
    echo '<p class="muted">Noch keine Dateien hochgeladen.</p>';
  } else {
    echo '<div class="media-grid">';
    foreach ($files as $f) {
      $url = '../media/' . rawurlencode($f['name']);
      $path = '/media/' . $f['name'];
      echo '<div class="media-tile">';
      echo '<div class="media-thumb">';
      if ($isImg($f['ext'])) echo '<img src="' . pg_h($url) . '" alt="" loading="lazy">';
      elseif ($isVid($f['ext'])) echo '<video src="' . pg_h($url) . '" muted></video>';
      else echo '<span class="ext">' . pg_h($f['ext']) . '</span>';
      echo '</div>';
      echo '<div class="media-meta"><span class="media-name">' . pg_h($f['name']) . '</span>'
         . '<span class="media-size">' . $fmtSize($f['size']) . '</span>';
      echo '<div class="media-row">';
      echo '<button type="button" data-copy="' . pg_h($path) . '">Pfad kopieren</button>';
      echo '<form method="post" onsubmit="return confirm(\'Diese Datei wirklich löschen? Sie könnte auf der Website verwendet werden.\')" style="flex:1;display:flex">'
         . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
         . '<input type="hidden" name="file" value="' . pg_h($f['name']) . '">'
         . '<button class="del" type="submit" name="delete_media" value="1" style="width:100%">Löschen</button></form>';
      echo '</div></div></div>';
    }
    echo '</div>';
  }
  echo '<div class="media-copied" id="media-copied">Pfad kopiert ✓</div>';
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Backup & Wiederherstellung (nur Owner) ---- */
if (($_GET['view'] ?? '') === 'backup') {
  if (!pg_is_owner()) { header('Location: index.php'); exit; }
  $files = pg_backup_filenames();
  pg_view_head('Backup');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor">';
  if (!empty($_SESSION['pg_backup_msg'])) {
    [$type, $text] = $_SESSION['pg_backup_msg']; unset($_SESSION['pg_backup_msg']);
    echo '<div class="flash ' . ($type === 'ok' ? 'ok' : 'err') . '">' . pg_h($text) . '</div>';
  }
  echo '<div class="editor-head"><div><h1>💾 Backup &amp; Wiederherstellung</h1>'
     . '<p class="muted">Sichere alle deine Inhalte &amp; Einstellungen als eine Datei – und spiele sie bei Bedarf wieder ein.</p></div>'
     . '<a class="btn-primary" href="index.php?action=backup_export">📥 Backup exportieren</a></div>';
  echo '<div class="mailnote">Die Backup-Datei enthält <b>' . count($files) . ' Datei' . (count($files) === 1 ? '' : 'en') . '</b>: '
     . 'alle Inhalte (Studio, Spiele, Devlog, Team, Rechtliches), die Newsletter-Abos sowie deine Login- und Mail-Einstellungen.<br>'
     . '<b>Tipp:</b> Lade vor jedem Hochladen zu All-Inkl einmal ein Backup herunter – dann bist du komplett abgesichert. '
     . 'Bewahre die Datei sicher auf, sie enthält Zugangsdaten.</div>';

  // Automatische Snapshots auf dem Server
  $snaps = pg_backup_list();
  $lastAuto = pg_backup_last_auto_time();
  echo '<h2 class="sub-h" style="border-top:1px solid var(--line);padding-top:1.2rem;margin-top:1.6rem">🗄️ Automatische Backups</h2>';
  echo '<p class="hint" style="margin:0 0 .8rem">Das System legt <b>einmal täglich</b> automatisch einen Snapshot auf dem Server an (im Ordner <code>data/backups/</code>, nicht öffentlich abrufbar). '
     . 'Letztes Auto-Backup: <b>' . ($lastAuto ? pg_h(date('d.m.Y H:i', $lastAuto)) : 'noch keins') . '</b>. '
     . 'Es werden die letzten ' . PG_BACKUP_KEEP_AUTO . ' Tage + ' . PG_BACKUP_KEEP_MANUAL . ' manuelle Snapshots behalten.</p>';
  echo '<form method="post" style="margin:0 0 1rem"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
     . '<button class="btn-add" name="backup_now" value="1">＋ Jetzt Snapshot sichern</button></form>';
  if (!$snaps) {
    echo '<div class="mailnote">Noch keine Snapshots vorhanden – beim nächsten Tagesbesuch wird automatisch einer angelegt, oder sichere jetzt manuell.</div>';
  } else {
    echo '<table class="subs"><thead><tr><th>Snapshot</th><th>Typ</th><th>Größe</th><th>Erstellt</th><th></th></tr></thead><tbody>';
    foreach ($snaps as $b) {
      $csrf = pg_h(pg_csrf());
      echo '<tr><td><code>' . pg_h($b['name']) . '</code></td>'
         . '<td>' . ($b['auto'] ? 'Automatisch' : 'Manuell') . '</td>'
         . '<td>' . number_format($b['size'] / 1024, 0, ',', '.') . ' KB</td>'
         . '<td>' . pg_h(date('d.m.Y H:i', $b['time'])) . '</td>'
         . '<td style="white-space:nowrap"><a class="btn-add" href="index.php?action=backup_download&file=' . urlencode($b['name']) . '">↓</a> '
         . '<form method="post" style="display:inline" onsubmit="return confirm(\'Diesen Snapshot einspielen? Aktuelle Inhalte werden überschrieben.\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="file" value="' . pg_h($b['name']) . '"><button class="btn-add" name="restore_backup" value="1">↺ Einspielen</button></form> '
         . '<form method="post" style="display:inline" onsubmit="return confirm(\'Snapshot löschen?\')"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="file" value="' . pg_h($b['name']) . '"><button class="btn-danger sm" name="delete_backup" value="1">✕</button></form>'
         . '</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '<h2 class="sub-h" style="border-top:1px solid var(--line);padding-top:1.2rem;margin-top:1.6rem">Wiederherstellen</h2>';
  echo '<p class="hint" style="margin:0 0 .8rem">Lädt eine zuvor exportierte Backup-Datei hoch und <b>überschreibt</b> die aktuellen Inhalte mit dem Stand aus der Datei.</p>';
  echo '<form method="post" action="index.php?action=backup_import" enctype="multipart/form-data" '
     . 'onsubmit="return confirm(\'Backup wirklich einspielen? Die aktuellen Inhalte werden mit dem Stand aus der Datei überschrieben.\')">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<div class="media-upload"><label class="btn-up">Datei wählen'
     . '<input type="file" name="backup" accept="application/json,.json" required '
     . 'onchange="this.closest(\'form\').querySelector(\'[data-impname]\').textContent=(this.files[0]||{}).name||\'Keine Datei gewählt\'"></label>'
     . '<span class="muted" data-impname>Keine Datei gewählt</span></div>';
  echo '<div class="editor-foot"><button class="btn-danger" type="submit">📤 Backup einspielen</button></div>';
  echo '</form>';
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Aktivitätsprotokoll (nur Owner) ---- */
if (($_GET['view'] ?? '') === 'activity') {
  if (!pg_is_owner()) { header('Location: index.php'); exit; }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_activity'])) {
    pg_csrf_check(); pg_save_json(PG_ACTIVITY_FILE, []); header('Location: index.php?view=activity&cleared=1'); exit;
  }
  $log = array_reverse(pg_load_json(PG_ACTIVITY_FILE));
  pg_view_head('Protokoll');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Protokoll geleert.</div>';
  echo '<div class="editor-head"><div><h1>📋 Aktivitätsprotokoll</h1>'
     . '<p class="muted">Die letzten Aktionen im Dashboard (max. 300). IP-Adressen werden anonymisiert gespeichert.</p></div></div>';
  if (!$log) {
    echo '<div class="mailnote">Noch keine Aktivitäten aufgezeichnet.</div>';
  } else {
    echo '<table class="subs"><thead><tr><th>Zeitpunkt</th><th>Benutzer</th><th>Aktion</th><th>Details</th></tr></thead><tbody>';
    foreach ($log as $e) {
      echo '<tr><td>' . pg_h(str_replace('T', ' ', substr($e['time'] ?? '', 0, 16))) . '</td>'
         . '<td>' . pg_h($e['user'] ?? '') . '</td>'
         . '<td>' . pg_h($e['action'] ?? '') . '</td>'
         . '<td>' . pg_h($e['detail'] ?? '') . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<form method="post" class="clear-form" onsubmit="return confirm(\'Protokoll wirklich leeren?\')">'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
       . '<button class="btn-danger" name="clear_activity" value="1">Protokoll leeren</button></form>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Papierkorb (gelöschte Einträge) ---- */
if (($_GET['view'] ?? '') === 'trash') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $t = pg_trash_load();
    if (isset($_POST['empty_trash'])) {
      pg_save_json(PG_TRASH_FILE, []); header('Location: index.php?view=trash&emptied=1'); exit;
    }
    $i = (int) ($_POST['idx'] ?? -1);
    if (isset($t[$i])) {
      $entry = $t[$i];
      if (isset($_POST['restore'])) {
        $coll = $entry['collection']; $field = $entry['field'];
        if (isset($SCHEMA[$coll]) && pg_can($coll)) {
          $cur = pg_load_json($SCHEMA[$coll]['file']);
          if (!isset($cur[$field]) || !is_array($cur[$field])) $cur[$field] = [];
          $cur[$field][] = $entry['item'];
          pg_save_json($SCHEMA[$coll]['file'], $cur);
          pg_log_activity('Wiederhergestellt', $entry['title'] . ' (' . $coll . ')');
        }
      }
      array_splice($t, $i, 1);
      pg_save_json(PG_TRASH_FILE, $t);
      header('Location: index.php?view=trash&' . (isset($_POST['restore']) ? 'restored=1' : 'deleted=1')); exit;
    }
    header('Location: index.php?view=trash'); exit;
  }
  $trash = pg_trash_load();
  $indexed = [];
  foreach ($trash as $i => $e) $indexed[] = [$i, $e];
  $indexed = array_reverse($indexed);
  pg_view_head('Papierkorb');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  if (isset($_GET['restored'])) echo '<div class="flash ok">✓ Eintrag wiederhergestellt.</div>';
  if (isset($_GET['deleted'])) echo '<div class="flash ok">✓ Endgültig gelöscht.</div>';
  if (isset($_GET['emptied'])) echo '<div class="flash ok">✓ Papierkorb geleert.</div>';
  echo '<div class="editor-head"><div><h1>🗑️ Papierkorb</h1>'
     . '<p class="muted">Gelöschte Spiele &amp; Devlog-Beiträge der letzten Zeit – wiederherstellbar. Wiederherstellung fügt den Eintrag (deutsche Fassung) wieder hinzu.</p></div></div>';
  if (!$indexed) {
    echo '<div class="mailnote">Der Papierkorb ist leer. Hier landen gelöschte Spiele und Devlog-Beiträge automatisch.</div>';
  } else {
    echo '<table class="subs"><thead><tr><th>Eintrag</th><th>Bereich</th><th>Gelöscht</th><th></th></tr></thead><tbody>';
    foreach ($indexed as [$i, $e]) {
      $collLabel = $SCHEMA[$e['collection']]['label'] ?? $e['collection'];
      echo '<tr><td><b>' . pg_h($e['title']) . '</b></td><td>' . pg_h($collLabel) . '</td>'
         . '<td>' . pg_h(str_replace('T', ' ', substr($e['deletedAt'] ?? '', 0, 16))) . '</td><td style="white-space:nowrap">'
         . '<form method="post" style="display:inline">'
         . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="idx" value="' . $i . '">'
         . '<button class="btn-add" name="restore" value="1">↩︎ Wiederherstellen</button></form> '
         . '<form method="post" style="display:inline" onsubmit="return confirm(\'Endgültig löschen? Das kann nicht rückgängig gemacht werden.\')">'
         . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="idx" value="' . $i . '">'
         . '<button class="btn-danger sm" name="delete_perm" value="1">Löschen</button></form>'
         . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<form method="post" class="clear-form" onsubmit="return confirm(\'Papierkorb komplett leeren? Alle Einträge gehen endgültig verloren.\')">'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
       . '<button class="btn-danger" name="empty_trash" value="1">Papierkorb leeren</button></form>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Statistik (datenschutzfreundlich) ---- */
/* ---- SEO-Assistent (ganze Seite prüfen) ---- */
if (($_GET['view'] ?? '') === 'seo') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $studio = pg_load_json(PG_DATA_DIR . '/studio.json');
  $games = pg_load_json(PG_DATA_DIR . '/games.json')['games'] ?? [];
  $posts = pg_load_json(PG_DATA_DIR . '/patchnotes.json')['posts'] ?? [];
  $team  = pg_load_json(PG_DATA_DIR . '/team.json')['members'] ?? [];
  $legal = pg_load_json(PG_DATA_DIR . '/legal.json');
  $checks = [];
  $add = function ($status, $label, $msg, $fix = '') use (&$checks) { $checks[] = compact('status', 'label', 'msg', 'fix'); };
  $has = fn($v) => trim((string) $v) !== '';

  // Grundlagen
  $add($has($studio['name'] ?? '') ? 'ok' : 'fail', 'Studio-Name', $has($studio['name'] ?? '') ? 'Gesetzt.' : 'Fehlt – wichtig für Titel & Marke.', 'index.php?collection=studio');
  $add($has($studio['tagline'] ?? '') || $has($studio['intro'] ?? '') ? 'ok' : 'warn', 'Tagline / Intro', $has($studio['tagline'] ?? '') || $has($studio['intro'] ?? '') ? 'Vorhanden.' : 'Kurzbeschreibung hilft Suchmaschinen & Social-Previews.', 'index.php?collection=studio');
  $add($has($studio['logo'] ?? '') ? 'ok' : 'warn', 'Logo', $has($studio['logo'] ?? '') ? 'Eigenes Logo hinterlegt.' : 'Kein Logo – es wird die Schrift-Wortmarke genutzt.', 'index.php?collection=studio');
  $add($has($studio['favicon'] ?? '') ? 'ok' : 'warn', 'Favicon', $has($studio['favicon'] ?? '') ? 'Gesetzt.' : 'Browser-Tab-Icon fehlt.', 'index.php?collection=studio');
  $add($has($studio['email'] ?? '') ? 'ok' : 'warn', 'Kontakt-E-Mail', $has($studio['email'] ?? '') ? 'Gesetzt.' : 'Für Presse & Kontakt empfehlenswert.', 'index.php?collection=studio');
  $add(count(array_filter((array) ($studio['socials'] ?? []), fn($x) => $has($x['url'] ?? '') && ($x['url'] ?? '') !== '#')) ? 'ok' : 'warn', 'Social-Links', 'Verlinkte Profile stärken Sichtbarkeit & „sameAs"-Daten.', 'index.php?collection=studio');
  // Technik
  $add(is_file(PG_MEDIA_DIR . '/og.jpg') ? 'ok' : 'fail', 'OG-Vorschaubild', is_file(PG_MEDIA_DIR . '/og.jpg') ? 'media/og.jpg vorhanden (Social-Preview).' : 'media/og.jpg fehlt – Links sehen beim Teilen schlecht aus.', 'index.php?view=media');
  $add(is_file(__DIR__ . '/../sitemap.php') ? 'ok' : 'warn', 'Sitemap', 'sitemap.xml wird dynamisch erzeugt.', '');
  $add(is_file(__DIR__ . '/../robots.txt') ? 'ok' : 'warn', 'robots.txt', 'Vorhanden.', '');
  // Recht (DE-Pflicht)
  $add($has($legal['impressum'] ?? '') ? 'ok' : 'fail', 'Impressum', $has($legal['impressum'] ?? '') ? 'Gefüllt.' : 'In Deutschland Pflicht!', 'index.php?collection=legal');
  $add($has($legal['datenschutz'] ?? '') ? 'ok' : 'fail', 'Datenschutzerklärung', $has($legal['datenschutz'] ?? '') ? 'Gefüllt.' : 'In Deutschland Pflicht!', 'index.php?collection=legal');

  // Spiele
  $gNoCover = 0; $gNoAlt = 0; $gNoTag = 0; $galleryNoAlt = 0;
  foreach ($games as $g) {
    if (!$has($g['cover'] ?? '')) $gNoCover++;
    elseif (!$has($g['coverAlt'] ?? '')) $gNoAlt++;
    if (!$has($g['tagline'] ?? '')) $gNoTag++;
    foreach ((array) ($g['blocks'] ?? []) as $bl) {
      if (($bl['type'] ?? '') === 'gallery') foreach ((array) ($bl['images'] ?? []) as $im) if ($has($im['image'] ?? '') && !$has($im['alt'] ?? '')) $galleryNoAlt++;
    }
  }
  if ($games) {
    $add($gNoCover ? 'warn' : 'ok', 'Spiel-Cover', $gNoCover ? $gNoCover . ' Spiel(e) ohne Cover-Bild.' : 'Alle Spiele haben ein Cover.', 'index.php?collection=games');
    $add($gNoAlt ? 'warn' : 'ok', 'Cover-Alt-Texte (Spiele)', $gNoAlt ? $gNoAlt . ' Spiel-Cover ohne Alt-Text (SEO & Barrierefreiheit).' : 'Alle Cover haben Alt-Texte.', 'index.php?collection=games');
    $add($gNoTag ? 'warn' : 'ok', 'Spiel-Taglines', $gNoTag ? $gNoTag . ' Spiel(e) ohne Kurzbeschreibung.' : 'Alle Spiele beschrieben.', 'index.php?collection=games');
    $add($galleryNoAlt ? 'warn' : 'ok', 'Galerie-Alt-Texte', $galleryNoAlt ? $galleryNoAlt . ' Galerie-Bild(er) ohne Alt-Text.' : 'Galerie-Bilder beschriftet.', 'index.php?collection=games');
  }
  // Devlog
  $pNoExcerpt = 0; $pNoAlt = 0;
  foreach ($posts as $p) {
    if (!$has($p['excerpt'] ?? '')) $pNoExcerpt++;
    if ($has($p['cover'] ?? '') && !$has($p['coverAlt'] ?? '')) $pNoAlt++;
  }
  if ($posts) {
    $add($pNoExcerpt ? 'warn' : 'ok', 'Devlog-Kurzfassungen', $pNoExcerpt ? $pNoExcerpt . ' Beitrag/Beiträge ohne Kurzfassung (Teaser/Meta-Description).' : 'Alle Beiträge haben eine Kurzfassung.', 'index.php?collection=patchnotes');
    $add($pNoAlt ? 'warn' : 'ok', 'Devlog-Titelbild-Alt', $pNoAlt ? $pNoAlt . ' Titelbild(er) ohne Alt-Text.' : 'Titelbilder beschriftet.', 'index.php?collection=patchnotes');
  }
  // Team
  $tNoAlt = 0; foreach ($team as $m) if ($has($m['photo'] ?? '') && !$has($m['photoAlt'] ?? '')) $tNoAlt++;
  if ($team) $add($tNoAlt ? 'warn' : 'ok', 'Team-Foto-Alt-Texte', $tNoAlt ? $tNoAlt . ' Foto(s) ohne Alt-Text.' : 'Team-Fotos beschriftet.', 'index.php?collection=team');

  $okN = count(array_filter($checks, fn($c) => $c['status'] === 'ok'));
  $warnN = count(array_filter($checks, fn($c) => $c['status'] === 'warn'));
  $failN = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
  $score = count($checks) ? (int) round(($okN + 0.5 * $warnN) / count($checks) * 100) : 100;

  pg_view_head('SEO-Assistent');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>🔍 SEO-Assistent</h1>'
     . '<p class="muted">Prüft deine ganze Seite auf Suchmaschinen- &amp; Social-Optimierung.</p></div></div>';
  $scoreColor = $score >= 80 ? '#5fd07f' : ($score >= 50 ? '#e6c07b' : '#e06c75');
  echo '<div class="seo-score"><div class="seo-ring" style="--p:' . $score . ';--c:' . $scoreColor . '"><span>' . $score . '</span></div>'
     . '<div><div class="seo-score-lbl">SEO-Punktzahl</div>'
     . '<div class="muted">' . $okN . ' ok · ' . $warnN . ' Hinweis' . ($warnN === 1 ? '' : 'e') . ' · ' . $failN . ' kritisch</div></div></div>';
  echo '<div class="seo-list">';
  $icon = ['ok' => '✓', 'warn' => '!', 'fail' => '✕'];
  foreach ($checks as $c) {
    echo '<div class="seo-item seo-' . $c['status'] . '">'
       . '<span class="seo-dot">' . $icon[$c['status']] . '</span>'
       . '<span class="seo-body"><span class="seo-label">' . pg_h($c['label']) . '</span>'
       . '<span class="seo-msg">' . pg_h($c['msg']) . '</span></span>'
       . ($c['fix'] ? '<a class="seo-fix" href="' . pg_h($c['fix']) . '">Beheben →</a>' : '')
       . '</div>';
  }
  echo '</div></div>';
  pg_view_foot();
  exit;
}

/* ---- Statistik: CSV-Export ---- */
if ($action === 'stats_csv') {
  if (!pg_logged_in()) { http_response_code(403); exit('Keine Berechtigung.'); }
  $s = pg_load_json(PG_DATA_DIR . '/stats.json');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="planigames-statistik-' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Typ', 'Schlüssel', 'Wert']);
  fputcsv($out, ['gesamt', 'total', (int) ($s['total'] ?? 0)]);
  foreach (['days' => 'tag', 'pages' => 'seite', 'refs' => 'verweis', 'regions' => 'region'] as $k => $lbl) {
    $arr = $s[$k] ?? [];
    if ($k === 'days') ksort($arr); else arsort($arr);
    foreach ($arr as $key => $val) fputcsv($out, [$lbl, $key, (int) $val]);
  }
  fclose($out); exit;
}

if (($_GET['view'] ?? '') === 'stats') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $s = pg_load_json(PG_DATA_DIR . '/stats.json');
  $days = $s['days'] ?? [];
  $pages = $s['pages'] ?? [];
  $refs = $s['refs'] ?? [];
  $regions = $s['regions'] ?? [];
  ksort($days);
  $today = $days[date('Y-m-d')] ?? 0;
  $last14 = array_slice($days, -14, null, true);
  $maxDay = $last14 ? max($last14) : 1;
  arsort($pages); arsort($refs); arsort($regions);
  $total = (int) ($s['total'] ?? 0);
  $subCount = pg_can('subscribers') ? count(pg_load_json(PG_DATA_DIR . '/subscribers.json')) : 0;
  $msgCount = pg_can('contacts') ? count(pg_load_json(PG_DATA_DIR . '/contacts.json')) : 0;
  $conv = $total > 0 ? round($subCount / $total * 100, 1) : 0;
  pg_view_head('Statistik');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>📊 Statistik</h1>'
     . '<p class="muted">Aggregierte Aufrufe – ohne Cookies, ohne IP-Speicherung, ohne externe Dienste.</p></div>'
     . '<a class="btn-add" href="index.php?action=stats_csv">⬇ CSV-Export</a></div>';
  echo '<div class="stat-cards">';
  echo '<div class="stat-card"><span class="stat-num">' . $total . '</span><span class="stat-lbl">Aufrufe gesamt</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . (int) $today . '</span><span class="stat-lbl">Heute</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . array_sum($last14) . '</span><span class="stat-lbl">Letzte 14 Tage</span></div>';
  echo '</div>';
  echo '<h2 class="sub-h" style="margin-top:1.6rem">🎯 Ziele</h2><div class="stat-cards">';
  echo '<div class="stat-card"><span class="stat-num">' . $subCount . '</span><span class="stat-lbl">Newsletter-Anmeldungen</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . $conv . '%</span><span class="stat-lbl">Anmeldequote</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . $msgCount . '</span><span class="stat-lbl">Kontakt-Nachrichten</span></div>';
  echo '</div>';
  if (!$days) {
    echo '<div class="mailnote" style="margin-top:1.2rem">Noch keine Daten. Sobald die Website besucht wird, erscheinen hier die Aufrufe.</div>';
  } else {
    echo '<h2 class="sub-h" style="margin-top:1.6rem">Letzte 14 Tage</h2><div class="stat-bars">';
    foreach ($last14 as $d => $n) {
      $h = max(4, round($n / $maxDay * 100));
      echo '<div class="stat-bar" title="' . pg_h($d) . ': ' . (int) $n . '"><span class="sb-fill" style="height:' . $h . '%"></span>'
         . '<span class="sb-val">' . (int) $n . '</span><span class="sb-day">' . pg_h(substr($d, 5)) . '</span></div>';
    }
    echo '</div>';
    $tbl = function ($title, $arr, $col, $empty) {
      $h = '<div class="stat-tbl"><h2 class="sub-h">' . $title . '</h2>';
      if (!$arr) return $h . '<p class="hint">' . $empty . '</p></div>';
      $h .= '<table class="subs"><thead><tr><th>' . $col . '</th><th>Aufrufe</th></tr></thead><tbody>';
      $i = 0; foreach ($arr as $k => $n) { if ($i++ >= 12) break; $h .= '<tr><td>' . pg_h($k) . '</td><td>' . (int) $n . '</td></tr>'; }
      return $h . '</tbody></table></div>';
    };
    echo '<div class="stat-cols">';
    echo $tbl('Beliebteste Seiten', $pages, 'Seite', 'Keine Daten.');
    echo $tbl('Verweis-Quellen', $refs, 'Domain', 'Bisher nur Direktaufrufe – externe Verweise erscheinen hier, sobald jemand über einen Link kommt.');
    echo '</div><div class="stat-cols">';
    echo $tbl('Region (aus Browsersprache geschätzt)', $regions, 'Region', 'Noch keine Daten.');
    echo '<div class="stat-tbl"></div>';
    echo '</div>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Team & Zugänge (nur Owner) ---- */
if (($_GET['view'] ?? '') === 'users') {
  if (!pg_is_owner()) { header('Location: index.php'); exit; }
  $flash = ''; $inviteLink = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    if (isset($_POST['invite'])) {
      $email = trim($_POST['email'] ?? '');
      $role = ($_POST['role'] ?? 'editor') === 'owner' ? 'owner' : 'editor';
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $flash = '<div class="flash err">Bitte gültige E-Mail eingeben.</div>'; }
      elseif (pg_user_find($email)) { $flash = '<div class="flash err">Diese Person hat bereits ein Konto.</div>'; }
      else {
        $token = pg_invite_create($email, $role);
        $inviteLink = pg_base_url() . '/index.php?action=register&token=' . $token;
        $sent = pg_send_invite_mail($email, $inviteLink, $role === 'owner' ? 'Owner' : 'Editor', pg_current_email());
        pg_log_activity('Einladung erstellt', $email . ' (' . $role . ')');
        $flash = '<div class="flash ok">✓ Einladung erstellt' . ($sent ? ' und per E-Mail verschickt' : ' (E-Mail konnte nicht versendet werden – Link unten kopieren)') . '.</div>';
      }
    } elseif (isset($_POST['updateuser'])) {
      $email = $_POST['email'] ?? '';
      $u = pg_user_find($email);
      if ($u) {
        $newRole = ($_POST['role'] ?? 'editor') === 'owner' ? 'owner' : 'editor';
        // Letzten Owner nicht herabstufen
        if (($u['role'] ?? '') === 'owner' && $newRole !== 'owner' && pg_owner_count() <= 1) {
          $flash = '<div class="flash err">Der letzte Owner kann nicht herabgestuft werden.</div>';
        } else {
          $areas = $newRole === 'owner' ? [] : array_values(array_intersect((array)($_POST['areas'] ?? []), array_keys(pg_areas_map())));
          pg_user_update($email, ['role' => $newRole, 'areas' => $areas]);
          pg_log_activity('Zugang geändert', $email . ' → ' . $newRole);
          $flash = '<div class="flash ok">Berechtigungen aktualisiert.</div>';
        }
      }
    } elseif (isset($_POST['revoke'])) {
      pg_invite_delete($_POST['token'] ?? ''); $flash = '<div class="flash ok">Einladung zurückgezogen.</div>';
    } elseif (isset($_POST['deluser'])) {
      $email = $_POST['email'] ?? '';
      if (strcasecmp($email, pg_current_email()) === 0) $flash = '<div class="flash err">Du kannst dich nicht selbst entfernen.</div>';
      elseif (($u = pg_user_find($email)) && ($u['role'] ?? '') === 'owner' && pg_owner_count() <= 1) $flash = '<div class="flash err">Der letzte Owner kann nicht entfernt werden.</div>';
      else { pg_user_delete($email); pg_log_activity('Zugang entfernt', $email); $flash = '<div class="flash ok">Zugang entfernt.</div>'; }
    }
  }
  pg_view_head('Zugänge');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor">';
  echo '<div class="editor-head"><div><h1>🔑 Zugänge &amp; Rollen</h1>'
     . '<p class="muted">Wer darf das Dashboard bearbeiten? Lade Leute per E-Mail ein.</p></div></div>';
  echo $flash;
  if ($inviteLink) echo '<div class="field"><label class="flabel">Einladungslink (kopieren &amp; senden)</label>'
     . '<input type="text" readonly onclick="this.select()" value="' . pg_h($inviteLink) . '"></div>';

  // Rollen-Erklärung
  echo '<div class="roles-note"><b>Owner</b> – darf alles: Inhalte bearbeiten <i>und</i> Mitglieder einladen/verwalten. '
     . '<b>Editor</b> – darf Inhalte bearbeiten. Pro Editor legst du unten fest, <i>welche Bereiche</i> er bearbeiten darf.</div>';

  // Aktive Mitglieder
  echo '<h2 class="sub-h">Mitglieder</h2>';
  foreach (pg_users_load() as $u) {
    $isSelf = strcasecmp($u['email'], pg_current_email()) === 0;
    $isOwner = ($u['role'] ?? '') === 'owner';
    $areas = pg_user_areas($u);
    echo '<div class="member">';
    echo '<div class="member-head"><div><div class="member-name">' . pg_h($u['name'] ?: '—')
       . ($isSelf ? ' <span class="muted">(du)</span>' : '') . '</div>'
       . '<div class="muted" style="font-size:.85rem">' . pg_h($u['email']) . '</div></div>'
       . '<span class="' . ($isOwner ? 'role-owner' : 'role-editor') . '">' . ($isOwner ? 'Owner' : 'Editor') . '</span></div>';
    if ($isSelf) {
      echo '<p class="muted" style="font-size:.83rem;margin:.6rem 0 0">Dein eigenes Konto – Rolle hier nicht änderbar.</p>';
    } else {
      echo '<form method="post" class="member-form"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
         . '<input type="hidden" name="email" value="' . pg_h($u['email']) . '">';
      echo '<label class="ml">Rolle</label>'
         . '<select name="role" onchange="this.closest(\'form\').querySelector(\'[data-areas]\').style.display=this.value===\'owner\'?\'none\':\'\'">'
         . '<option value="editor"' . ($isOwner ? '' : ' selected') . '>Editor</option>'
         . '<option value="owner"' . ($isOwner ? ' selected' : '') . '>Owner</option></select>';
      echo '<div data-areas class="areas"' . ($isOwner ? ' style="display:none"' : '') . '><span class="ml">Darf bearbeiten:</span>';
      foreach (pg_areas_map() as $k => $lbl) {
        echo '<label class="chk"><input type="checkbox" name="areas[]" value="' . pg_h($k) . '"'
           . (in_array($k, $areas, true) ? ' checked' : '') . '> ' . pg_h($lbl) . '</label>';
      }
      echo '</div>';
      echo '<div class="member-actions"><button class="btn-add" name="updateuser" value="1">Speichern</button>'
         . '<button class="btn-danger sm" name="deluser" value="1" onclick="return confirm(\'Zugang wirklich entfernen?\')">Entfernen</button></div>';
      echo '</form>';
    }
    echo '</div>';
  }

  // Einladen
  echo '<h2 class="sub-h">Neues Mitglied einladen</h2>';
  echo '<form method="post" class="invite-form"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="email" name="email" placeholder="email@beispiel.de" required>';
  echo '<select name="role"><option value="editor">Editor (Inhalte bearbeiten)</option><option value="owner">Owner (darf auch einladen)</option></select>';
  echo '<button class="btn-primary" name="invite" value="1">Einladen</button></form>';

  // Offene Einladungen
  $inv = pg_invites_load();
  if ($inv) {
    echo '<h2 class="sub-h">Offene Einladungen</h2><table class="subs"><thead><tr><th>E-Mail</th><th>Rolle</th><th>Link</th><th></th></tr></thead><tbody>';
    foreach ($inv as $x) {
      $link = pg_base_url() . '/index.php?action=register&token=' . $x['token'];
      echo '<tr><td>' . pg_h($x['email']) . '</td><td>' . pg_h($x['role']) . '</td>'
         . '<td><input type="text" readonly onclick="this.select()" value="' . pg_h($link) . '" style="font-size:.75rem"></td>'
         . '<td><form method="post" style="margin:0"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
         . '<input type="hidden" name="token" value="' . pg_h($x['token']) . '">'
         . '<button class="btn-danger sm" name="revoke" value="1">Zurückziehen</button></form></td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</div>';
  pg_view_foot();
  exit;
}

/* ---- Editor ---- */
if (isset($_GET['collection']) && isset($SCHEMA[$_GET['collection']])) {
  $key = $_GET['collection'];
  if (!pg_can($key)) { header('Location: index.php'); exit; }
  $coll = $SCHEMA[$key];
  $lang = ($_GET['lang'] ?? 'de') === 'en' ? 'en' : 'de';
  $enFile = pg_lang_file($coll['file'], 'en');
  $enExists = is_file($enFile);
  // EN-Datei laden falls vorhanden, sonst Deutsch als Startvorlage übernehmen
  $data = ($lang === 'en' && $enExists) ? pg_load_json($enFile) : pg_load_json($coll['file']);

  pg_view_head($coll['label']);
  pg_view_topbar($SCHEMA, $key);
  if (isset($_GET['saved']))      echo '<div class="flash ok">✓ Gespeichert (' . strtoupper($lang) . '). Änderungen sind live auf der Website.</div>';
  if (isset($_GET['sent']))       echo '<div class="flash ok">📣 Newsletter verschickt: ' . (int)$_GET['sent'] . ' Abonnent' . ((int)$_GET['sent'] === 1 ? '' : 'en') . ' benachrichtigt.</div>';
  if (isset($_GET['translated'])) echo '<div class="flash ok">🌐 Automatisch übersetzt. Bitte gegenlesen und ggf. anpassen, dann speichern reicht – ist bereits gesichert.</div>';
  if (isset($_GET['error']))      echo '<div class="flash err">Konnte nicht speichern/übersetzen – Schreibrechte am Ordner data/ bzw. Internet-Zugriff prüfen.</div>';

  // Sprach-Umschalter
  echo '<div class="editor lang-bar">';
  echo '<div class="lang-toggle">'
     . '<a class="' . ($lang === 'de' ? 'on' : '') . '" href="index.php?collection=' . pg_h($key) . '&lang=de">🇩🇪 Deutsch</a>'
     . '<a class="' . ($lang === 'en' ? 'on' : '') . '" href="index.php?collection=' . pg_h($key) . '&lang=en">🇬🇧 English' . ($enExists ? '' : ' <span class="muted">(neu)</span>') . '</a>'
     . '</div>';
  if ($lang === 'en') {
    echo '<form method="post" action="index.php?action=translate" class="tr-form" '
       . 'onsubmit="return confirm(\'Englische Felder automatisch aus dem Deutschen füllen? Vorhandene englische Inhalte werden überschrieben. Das kann einen Moment dauern.\')">'
       . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">'
       . '<input type="hidden" name="collection" value="' . pg_h($key) . '">'
       . '<button class="btn-translate" type="submit">🌐 Automatisch aus dem Deutschen übersetzen</button></form>';
  }
  echo '</div>';
  if ($lang === 'en') echo '<div class="editor lang-hint-wrap"><p class="muted lang-hint">Tipp: Leere englische Felder fallen auf der Website automatisch auf Deutsch zurück. Nicht-Textfelder (Bilder, Farben, Links) gelten für beide Sprachen.</p></div>';

  $previewUrl = [
    'studio' => '../index.html', 'team' => '../index.html#team-section',
    'games' => '../games.html', 'patchnotes' => '../devlog.php', 'legal' => '../rechtliches.html',
  ][$key] ?? '../index.html';
  echo '<form method="post" class="editor" id="editor" data-autosave data-preview="' . pg_h($previewUrl) . '">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="hidden" name="collection" value="' . pg_h($key) . '">';
  echo '<input type="hidden" name="lang" value="' . pg_h($lang) . '">';
  echo '<div class="editor-head"><div><h1>' . pg_h($coll['icon']) . ' ' . pg_h($coll['label'])
     . ' <span class="lang-pill">' . strtoupper($lang) . '</span></h1>'
     . '<p class="muted">Änderungen werden direkt auf dem Server gespeichert.</p></div>'
     . '<div class="editor-actions"><span class="save-status" data-save-status></span>'
     . '<button type="button" class="btn-add" data-split-toggle title="Live-Vorschau neben dem Editor">⊟ Vorschau</button>'
     . '<a class="btn-preview" href="' . pg_h($previewUrl) . '" target="_blank" rel="noopener">↗ Tab</a>'
     . '<button class="btn-primary" type="submit" name="save" value="1">Speichern</button></div></div>';
  echo '<div class="fields">';
  echo pg_render_fields($coll['fields'], $data, 'd', $key);
  echo '</div>';
  echo '<div class="editor-foot"><button class="btn-primary" type="submit" name="save" value="1">Speichern</button></div>';
  echo '</form>';
  pg_view_foot();
  exit;
}

/* ---- Dashboard ---- */
pg_view_head('Dashboard');
pg_view_topbar($SCHEMA, null);
echo '<div class="dash">';
$me = pg_current_user();
$firstName = trim((string) ($me['name'] ?? ''));
if ($firstName !== '') $firstName = ', ' . explode(' ', $firstName)[0];
echo '<h1>' . pg_h(pg_greeting()) . $firstName . ' 👋</h1>';

// Zuletzt bearbeitet (nach Datei-Änderungszeit)
$recent = [];
foreach ($SCHEMA as $rk => $rc) {
  if (!pg_can($rk) || empty($rc['file']) || !is_file($rc['file'])) continue;
  $recent[] = ['key' => $rk, 'icon' => $rc['icon'], 'label' => $rc['label'], 'mt' => filemtime($rc['file'])];
}
usort($recent, fn($a, $b) => $b['mt'] - $a['mt']);
$recent = array_slice($recent, 0, 4);
if ($recent) {
  echo '<div class="dash-recent"><span class="dr-lbl">Zuletzt bearbeitet:</span>';
  foreach ($recent as $r) {
    echo '<a class="dr-chip" href="index.php?collection=' . pg_h($r['key']) . '">' . $r['icon'] . ' ' . pg_h($r['label'])
       . '<span class="dr-ago">' . pg_h(pg_time_ago($r['mt'])) . '</span></a>';
  }
  echo '</div>';
}

// Globale Suche
echo '<form method="get" class="searchbar dash-search"><input type="hidden" name="view" value="search">'
   . '<span class="sb-ico">🔎</span>'
   . '<input type="search" name="q" placeholder="Alle Inhalte durchsuchen … (Spiele, Devlog, Anfragen, Medien)">'
   . '<button class="btn-primary" type="submit">Suchen</button></form>';

/* ── Überblick: Postfach-Vorschau + Statistik ── */
$ov = '';
// Postfach-Vorschau (letzte Kontakt-Anfragen)
if (pg_can('contacts')) {
  $clist = pg_load_json(PG_DATA_DIR . '/contacts.json');
  $latest = array_slice(array_reverse($clist), 0, 4);
  $cUnread = pg_contacts_unread();
  $mUnread = pg_can('mail') ? pg_mail_unread_cached() : 0;
  $head = '<div class="dp-head"><span class="dp-title">📮 Postfach</span>'
        . ($cUnread ? '<span class="nbadge">' . $cUnread . '</span>' : '')
        . '<a class="dp-more" href="index.php?view=contacts">Alle Anfragen →</a></div>';
  $body = '';
  if (!$latest) {
    $body = '<div class="dp-empty">Noch keine Nachrichten. Anfragen über das Kontaktformular erscheinen hier.</div>';
  } else {
    foreach ($latest as $r) {
      $unread = empty($r['read']);
      $snip = trim(preg_replace('/\s+/', ' ', (string) ($r['message'] ?? '')));
      if (mb_strlen($snip) > 90) $snip = mb_substr($snip, 0, 90) . '…';
      $body .= '<a class="dp-item' . ($unread ? ' unread' : '') . '" href="index.php?view=contacts">'
             . '<span class="dp-row"><span class="dp-name">' . ($unread ? '<span class="dp-dot"></span>' : '') . pg_h($r['name'] ?? 'Unbekannt') . '</span>'
             . '<span class="dp-date">' . pg_h(substr($r['date'] ?? '', 0, 10)) . '</span></span>'
             . '<span class="dp-snip">' . pg_h($snip) . '</span></a>';
    }
  }
  if (pg_can('mail')) {
    $body .= '<a class="dp-foot" href="mail.php">📬 E-Mail-Postfach öffnen'
           . ($mUnread ? ' <span class="nbadge">' . $mUnread . '</span>' : '') . '</a>';
  }
  $ov .= '<section class="dash-panel">' . $head . $body . '</section>';
}
// Statistik-Vorschau
$st = pg_load_json(PG_DATA_DIR . '/stats.json');
$days = $st['days'] ?? [];
ksort($days);
$today = $days[date('Y-m-d')] ?? 0;
$last7 = array_sum(array_slice($days, -7, null, true));
$spark = array_slice($days, -14, null, true);
$sparkMax = $spark ? max($spark) : 1;
$sh = '<div class="dp-head"><span class="dp-title">📊 Statistik</span>'
    . '<a class="dp-more" href="index.php?view=stats">Details →</a></div>';
$sb = '<div class="dp-stats">'
    . '<div class="dp-stat"><span class="dp-num">' . (int) $today . '</span><span class="dp-lbl">Heute</span></div>'
    . '<div class="dp-stat"><span class="dp-num">' . (int) $last7 . '</span><span class="dp-lbl">7 Tage</span></div>'
    . '<div class="dp-stat"><span class="dp-num">' . (int) ($st['total'] ?? 0) . '</span><span class="dp-lbl">Gesamt</span></div>'
    . '</div>';
if ($spark) {
  $sb .= '<div class="dp-spark" title="Aufrufe der letzten 14 Tage">';
  foreach ($spark as $d => $n) {
    $hgt = max(6, round($n / $sparkMax * 100));
    $sb .= '<span class="dp-bar" title="' . pg_h($d) . ': ' . (int) $n . '" style="height:' . $hgt . '%"></span>';
  }
  $sb .= '</div>';
} else {
  $sb .= '<div class="dp-empty">Noch keine Aufrufe erfasst.</div>';
}
$ov .= '<section class="dash-panel">' . $sh . $sb . '</section>';

// Newsletter-Wachstum (Sparkline der letzten 14 Tage)
if (pg_can('subscribers')) {
  $subs = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  $subTotal = is_array($subs) ? count($subs) : 0;
  $subDays = [];
  for ($i = 13; $i >= 0; $i--) $subDays[date('Y-m-d', strtotime("-$i day"))] = 0;
  $sub7 = 0;
  foreach ((array) $subs as $r) {
    $d = substr((string) ($r['date'] ?? ''), 0, 10);
    if ($d !== '' && isset($subDays[$d])) $subDays[$d]++;
    if ($d !== '' && $d >= date('Y-m-d', strtotime('-6 day'))) $sub7++;
  }
  $subMax = $subDays ? max(max($subDays), 1) : 1;
  $nh = '<div class="dp-head"><span class="dp-title">📬 Newsletter</span>'
      . '<a class="dp-more" href="index.php?view=subscribers">Abos →</a></div>';
  $nb = '<div class="dp-stats">'
      . '<div class="dp-stat"><span class="dp-num">' . $subTotal . '</span><span class="dp-lbl">Gesamt</span></div>'
      . '<div class="dp-stat"><span class="dp-num">+' . $sub7 . '</span><span class="dp-lbl">7 Tage</span></div></div>';
  if (array_sum($subDays) > 0) {
    $nb .= '<div class="dp-spark" title="Neue Abos der letzten 14 Tage">';
    foreach ($subDays as $d => $n) $nb .= '<span class="dp-bar" title="' . pg_h($d) . ': ' . (int) $n . '" style="height:' . max(6, round($n / $subMax * 100)) . '%"></span>';
    $nb .= '</div>';
  } else {
    $nb .= '<div class="dp-empty">Noch keine Anmeldungen in den letzten 14 Tagen.</div>';
  }
  $ov .= '<section class="dash-panel">' . $nh . $nb . '</section>';
}
echo '<div class="dash-grid">' . $ov . '</div>';

// kleine Helfer-Funktion: eine Karte ausgeben
$card = function ($href, $ico, $title, $desc, $badge = 0) {
  return '<a class="card" href="' . pg_h($href) . '">'
       . '<span class="card-ico">' . $ico . '</span>'
       . '<span class="card-title">' . $title . ($badge ? '<span class="nbadge">' . $badge . '</span>' : '') . '</span>'
       . '<span class="card-desc">' . $desc . '</span></a>';
};

/* ── Abschnitt 1: Inhalte ── */
$inhalte = '';
foreach ($SCHEMA as $key => $coll) {
  if (!pg_can($key)) continue;
  $desc = [
    'studio' => 'Startseite, Über-uns, Kontakt &amp; Footer.',
    'team' => 'Teammitglieder mit Foto, Name &amp; Rolle.',
    'games' => 'Spiele anlegen und ihre Seiten mit Blöcken bauen.',
    'patchnotes' => 'Devlog-Einträge, Ankündigungen &amp; Patch Notes.',
    'legal' => 'Impressum &amp; Datenschutzerklärung bearbeiten.',
  ][$key] ?? '';
  $inhalte .= $card('index.php?collection=' . $key, pg_h($coll['icon']), pg_h($coll['label']), $desc);
}
$inhalte .= $card('index.php?view=media', '🖼️', 'Medien-Bibliothek', 'Bilder &amp; Videos hochladen, Pfade kopieren, aufräumen.');
if ($inhalte !== '') {
  echo '<h2 class="dash-section">📝 Inhalte</h2><div class="cards">' . $inhalte . '</div>';
}

/* ── Abschnitt 2: Community & Kontakt ── */
$community = '';
if (pg_can('subscribers')) {
  $subCount = count(pg_load_json(PG_DATA_DIR . '/subscribers.json'));
  $community .= $card('index.php?view=subscribers', '📬', 'Newsletter-Abos',
    $subCount . ' Anmeldung' . ($subCount === 1 ? '' : 'en') . ' · ansehen &amp; exportieren.');
}
if (pg_can('contacts')) {
  $cCount = count(pg_load_json(PG_DATA_DIR . '/contacts.json'));
  $community .= $card('index.php?view=contacts', '✉️', 'Kontakt-Anfragen',
    $cCount . ' Nachricht' . ($cCount === 1 ? '' : 'en') . ' über das Kontaktformular.', pg_contacts_unread());
}
if (pg_can('mail')) {
  $community .= $card('mail.php', '📮', 'E-Mail-Postfach',
    'Mails im PLANIGAMES-Design senden &amp; empfangen.', pg_mail_unread_cached());
}
if (pg_can('mail') || pg_can('contacts')) {
  $tplCount = count(pg_templates_load());
  $community .= $card('index.php?view=templates', '⚡', 'Schnellantworten',
    $tplCount . ' Vorlage' . ($tplCount === 1 ? '' : 'n') . ' für wiederkehrende Antworten.');
}
if ($community !== '') {
  echo '<h2 class="dash-section">📣 Community &amp; Kontakt</h2><div class="cards">' . $community . '</div>';
}

/* ── Abschnitt 3: System & Verwaltung ── */
$system = '';
$system .= $card('index.php?view=board', '🗂️', 'Planungs-Board', 'Ideen &amp; Aufgaben planen (Kanban).');
$system .= $card('index.php?view=stats', '📊', 'Statistik', 'Datenschutzfreundliche Seitenaufrufe ansehen.');
$system .= $card('index.php?view=trash', '🗑️', 'Papierkorb',
  'Gelöschte Spiele &amp; Beiträge wiederherstellen.', count(pg_trash_load()));
if (pg_is_owner()) {
  $system .= $card('index.php?view=users', '🔑', 'Zugänge &amp; Rollen',
    'Login-Zugänge per E-Mail einladen &amp; Rechte vergeben.');
  $system .= $card('index.php?view=backup', '💾', 'Backup &amp; Wiederherstellung',
    'Alle Inhalte als Datei sichern &amp; wieder einspielen.');
  $system .= $card('index.php?view=activity', '📋', 'Aktivitätsprotokoll',
    'Wer hat wann was im Dashboard gemacht?');
}
echo '<h2 class="dash-section">⚙️ System &amp; Verwaltung</h2><div class="cards">' . $system . '</div>';

echo '<div class="dash-links"><a href="../index.html" target="_blank">↗ Website ansehen</a> '
   . '<a href="index.php?action=logout">Abmelden</a></div>';
echo '</div>';
pg_view_foot();


/* VIEW-HELFER liegen jetzt in lib.php (von index.php & mail.php gemeinsam genutzt). */
