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

/* ---- Login ---- */
if (!pg_logged_in()) {
  $err = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $lock = pg_login_locked();
    if ($lock > 0) {
      $err = 'Zu viele Fehlversuche. Bitte in ' . ceil($lock / 60) . ' Minute(n) erneut versuchen.';
    } else {
      $u = pg_user_check(trim($_POST['email'] ?? ''), $_POST['pw'] ?? '');
      if ($u) {
        pg_login_clear(); session_regenerate_id(true); pg_login_user($u);
        pg_log_activity('login', $u['email'] ?? '');
        header('Location: index.php'); exit;
      }
      pg_login_record_fail();
      $err = 'E-Mail oder Passwort falsch.';
      usleep(400000);
    }
  }
  pg_view_head('Login');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  echo '<h1>Anmelden</h1>';
  if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="email" name="email" placeholder="E-Mail-Adresse" required autofocus>';
  echo '<input type="password" name="pw" placeholder="Passwort" required>';
  echo '<button class="btn-primary" type="submit">Einloggen</button></form>';
  echo '<p class="muted" style="margin-top:1rem;font-size:.85rem">Per Einladung hier? Nutze den Link aus deiner E-Mail.</p>';
  echo '</div></div>'; pg_view_foot(); exit;
}

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
  $bundle = ['_meta' => [
    'app' => 'PLANIGAMES', 'type' => 'backup', 'version' => 1,
    'created' => date('c'),
    'host' => preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? ''),
  ], 'files' => []];
  foreach (pg_backup_filenames() as $f) {
    $bundle['files'][$f] = file_get_contents(PG_DATA_DIR . '/' . $f);
  }
  $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $fname = 'planigames-backup-' . date('Y-m-d-Hi') . '.json';
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fname . '"');
  header('Content-Length: ' . strlen($json));
  echo $json; exit;
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
if (($_GET['view'] ?? '') === 'stats') {
  if (!pg_logged_in()) { header('Location: index.php'); exit; }
  $s = pg_load_json(PG_DATA_DIR . '/stats.json');
  $days = $s['days'] ?? [];
  $pages = $s['pages'] ?? [];
  ksort($days);
  $today = $days[date('Y-m-d')] ?? 0;
  $last14 = array_slice($days, -14, null, true);
  $maxDay = $last14 ? max($last14) : 1;
  arsort($pages);
  pg_view_head('Statistik');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor wide">';
  echo '<div class="editor-head"><div><h1>📊 Statistik</h1>'
     . '<p class="muted">Aggregierte Seitenaufrufe – ganz ohne Cookies, ohne IP-Speicherung, ohne externe Dienste.</p></div></div>';
  echo '<div class="stat-cards">';
  echo '<div class="stat-card"><span class="stat-num">' . (int) ($s['total'] ?? 0) . '</span><span class="stat-lbl">Aufrufe gesamt</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . (int) $today . '</span><span class="stat-lbl">Heute</span></div>';
  echo '<div class="stat-card"><span class="stat-num">' . array_sum($last14) . '</span><span class="stat-lbl">Letzte 14 Tage</span></div>';
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
    echo '<h2 class="sub-h" style="margin-top:1.6rem">Beliebteste Seiten</h2><table class="subs"><thead><tr><th>Seite</th><th>Aufrufe</th></tr></thead><tbody>';
    $i = 0; foreach ($pages as $p => $n) { if ($i++ >= 15) break; echo '<tr><td>' . pg_h($p) . '</td><td>' . (int) $n . '</td></tr>'; }
    echo '</tbody></table>';
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

  echo '<form method="post" class="editor" id="editor">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="hidden" name="collection" value="' . pg_h($key) . '">';
  echo '<input type="hidden" name="lang" value="' . pg_h($lang) . '">';
  $previewUrl = [
    'studio' => '../index.html', 'team' => '../index.html#team-section',
    'games' => '../games.html', 'patchnotes' => '../devlog.php', 'legal' => '../rechtliches.html',
  ][$key] ?? '../index.html';
  echo '<div class="editor-head"><div><h1>' . pg_h($coll['icon']) . ' ' . pg_h($coll['label'])
     . ' <span class="lang-pill">' . strtoupper($lang) . '</span></h1>'
     . '<p class="muted">Änderungen werden direkt auf dem Server gespeichert.</p></div>'
     . '<div class="editor-actions"><a class="btn-preview" href="' . pg_h($previewUrl) . '" target="_blank" rel="noopener">↗ Vorschau</a>'
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
echo '<h1>Hallo 👋 Was möchtest du bearbeiten?</h1>';

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
