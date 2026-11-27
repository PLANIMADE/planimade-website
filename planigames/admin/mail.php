<?php
/**
 * PLANIGAMES — Postfach (Empfang via IMAP, Versand via SMTP oder mail()).
 * Ordner · Liste · Lesebereich, Verfassen mit Formatierung, Anhängen & Signaturen.
 * Gebrandete Mails (mit Studio-Logo) – jede Mail mit Text- und HTML-Teil.
 */
require __DIR__ . '/lib.php';
$SCHEMA = require __DIR__ . '/schema.php';
pg_session_boot();
if (pg_logged_in() && !pg_current_user()) { $_SESSION = []; session_destroy(); }

if (!pg_logged_in()) { header('Location: index.php'); exit; }
if (!pg_mailbox_access()) { header('Location: index.php'); exit; }   // Hauptpostfach oder ein eigenes Postfach

$action = $_GET['action'] ?? '';
$tab    = $_GET['tab'] ?? 'inbox';
$tabsOk = ['inbox', 'sent', 'compose'];
if (pg_can('mail') && pg_can('subscribers')) $tabsOk[] = 'newsletter';
if (pg_is_owner()) $tabsOk[] = 'settings';
if (!in_array($tab, $tabsOk, true)) $tab = 'inbox';
$flash  = '';
// Aktives Postfach: ?mb=… (bzw. beim Senden das Feld „mb“), sonst das zuletzt benutzte
$mbId  = pg_mailbox_activate((string) ($_POST['mb'] ?? $_GET['mb'] ?? ''));
$mbBox = pg_mailboxes_visible()[$mbId] ?? ['id' => 'main', 'label' => 'Hauptpostfach', 'email' => ''];
$mbq   = '&mb=' . rawurlencode($mbId);           // an alle Links hängen, die eine bestimmte Mail meinen
const PG_MAIL_ATT_MAX = 10 * 1048576;   // Anhänge gesamt höchstens 10 MB
const PG_MAIL_ATT_BLOCK = ['exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'js', 'jse', 'vbs', 'vbe', 'ps1', 'jar', 'lnk', 'reg', 'hta', 'cpl', 'pif', 'wsf', 'php', 'phtml'];
$folder = preg_replace('/[^A-Za-z0-9._\-]/', '', $_GET['folder'] ?? 'INBOX') ?: 'INBOX';

/* ---- Live-Vorschau: rendert die echte Mail-HTML (Verfassen/Newsletter) ---- */
if ($action === 'preview') {
  header('Content-Type: text/html; charset=UTF-8');
  header("Content-Security-Policy: default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; font-src https: data:");
  $mode = ($_POST['mode'] ?? $_GET['mode'] ?? 'compose');
  $message = (string) ($_POST['message'] ?? $_GET['message'] ?? '');
  $isHtml = !empty($_POST['html']);
  if (trim(strip_tags($message)) === '') { $isHtml = false; $message = $mode === 'newsletter'
    ? "Hier steht dein Newsletter …\n\nSchreib links los – die Vorschau aktualisiert sich live."
    : "Hier steht deine Nachricht …\n\nSchreib links los – die Vorschau aktualisiert sich live."; }
  echo $mode === 'newsletter'
    ? pg_mail_build_newsletter($message, pg_unsub_link('beispiel@abonnent.de'), ['html' => $isHtml])
    : pg_mail_build_personal($message, ['html' => $isHtml, 'sig' => isset($_POST['sig']) ? (empty($_POST['with_sig']) && isset($_POST['with_sig_sent']) ? '' : (string) $_POST['sig']) : null]);
  exit;
}

/* ---- Mail-Body für die IFrame-Vorschau (isoliert, kein Skript) ---- */
if ($action === 'body') {
  $uid = (int) ($_GET['uid'] ?? 0);
  header('Content-Type: text/html; charset=UTF-8');
  header("Content-Security-Policy: default-src 'none'; img-src https: http: data: cid:; style-src 'unsafe-inline'; font-src https: data:");
  $mbox = pg_imap_connect($folder);
  if (!$mbox || !$uid) { echo '<p style="font-family:sans-serif;color:#888;padding:1rem">Nachricht nicht verfügbar.</p>'; exit; }
  $msg = pg_imap_body($mbox, $uid);
  imap_close($mbox);
  if ($msg['html']) {
    // einfache Härtung: <script>/Event-Handler entfernen (Anzeige zusätzlich im Sandbox-IFrame ohne Skripte)
    $b = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $msg['body']);
    $b = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $b);
    echo '<base target="_blank"><div style="font-family:system-ui,Arial,sans-serif;color:#111">' . $b . '</div>';
  } else {
    echo '<div style="font-family:system-ui,Arial,sans-serif;white-space:pre-wrap;word-break:break-word;color:#111;padding:.5rem">'
       . pg_h($msg['body']) . '</div>';
  }
  exit;
}

/* ---- Anhang herunterladen (immer als Download, nie im Admin anzeigen) ---- */
if ($action === 'attachment') {
  $uid = (int) ($_GET['uid'] ?? 0);
  $part = preg_replace('/[^0-9.]/', '', (string) ($_GET['part'] ?? ''));
  $mbox = $uid && $part !== '' ? pg_imap_connect($folder) : null;
  $a = $mbox ? pg_imap_attachment_data($mbox, $uid, $part) : null;
  if ($mbox) imap_close($mbox);
  if (!$a) { http_response_code(404); exit('Anhang nicht gefunden.'); }
  $name = $a['name'];
  $ascii = preg_replace('/[^\x20-\x7e]|["\\\\]/', '_', strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']));
  header('Content-Type: application/octet-stream');
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
  header('Content-Length: ' . strlen($a['data']));
  header('Cache-Control: private, no-store');
  echo $a['data'];
  exit;
}

/* ---- Gesendete Mail im IFrame anzeigen (aus dem lokalen Postausgang) ---- */
if ($action === 'sentbody') {
  header('Content-Type: text/html; charset=UTF-8');
  header("Content-Security-Policy: default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; font-src https: data:");
  $id = preg_replace('/[^a-f0-9]/', '', $_GET['id'] ?? '');
  $row = null;
  foreach (pg_mail_sent_load() as $e) { if (($e['id'] ?? '') === $id) { $row = $e; break; } }
  if ($row && !isset(pg_mailboxes_visible()[(string) ($row['mb'] ?? 'main')])) $row = null;   // fremdes Postfach
  if (!$row) { echo '<p style="font-family:sans-serif;color:#888;padding:1rem">Nachricht nicht verfügbar.</p>'; exit; }
  $b = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $row['html']);
  echo '<base target="_blank">' . $b;
  exit;
}

/* ---- Signaturen speichern (Verwalten-Dialog) ---- */
if ($action === 'sigs_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  pg_csrf_check();
  $in = json_decode((string) ($_POST['sigs'] ?? '[]'), true);
  if (!is_array($in)) { echo json_encode(['ok' => false, 'error' => 'Ungültige Daten.']); exit; }
  $saved = pg_mail_signatures_save($in);
  if ($saved === false) { echo json_encode(['ok' => false, 'error' => 'Konnte nicht speichern (Schreibrechte data/ prüfen).']); exit; }
  // „Mein Standard“: Position in der Liste (IDs neuer Signaturen entstehen erst beim Speichern)
  if (isset($_POST['mine'])) {
    $mi = (int) $_POST['mine'];
    pg_mail_signature_set_default($mi < 0 ? '' : (string) ($saved[$mi]['id'] ?? ''));
  }
  pg_log_activity('Signaturen gespeichert', count($saved) . ' Signatur(en)');
  echo json_encode(['ok' => true, 'sigs' => $saved, 'default' => pg_mail_signature_default(), 'options' => pg_mail_from_options($saved)], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---- Einstellungen speichern (nur Owner) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  pg_csrf_check();
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  $c = [
    'from_name'  => trim($_POST['from_name'] ?? 'PLANIGAMES'),
    'from_email' => trim($_POST['from_email'] ?? ''),
    'smtp_host'  => trim($_POST['smtp_host'] ?? ''),
    'smtp_port'  => (int) ($_POST['smtp_port'] ?? 587),
    'smtp_secure'=> in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl', ''], true) ? $_POST['smtp_secure'] : 'tls',
    'smtp_user'  => trim($_POST['smtp_user'] ?? ''),
    'smtp_pass'  => (string) ($_POST['smtp_pass'] ?? ''),
    'imap_host'  => trim($_POST['imap_host'] ?? ''),
    'imap_port'  => (int) ($_POST['imap_port'] ?? 993),
    'imap_secure'=> in_array($_POST['imap_secure'] ?? 'ssl', ['ssl', 'tls', ''], true) ? $_POST['imap_secure'] : 'ssl',
    'imap_user'  => trim($_POST['imap_user'] ?? ''),
    'imap_pass'  => (string) ($_POST['imap_pass'] ?? ''),
  ];
  // Signaturen & persönliche Postfächer werden separat verwaltet – hier bewusst NICHT überschreiben
  $ok = pg_mail_config_save($c);
  if ($ok) {
    if (pg_mail_backoff('main')) pg_mail_unread_store(pg_mail_unread_row('main')['count'], 'main');   // neue Zugangsdaten sofort wieder versuchen
    pg_log_activity('Postfach-Einstellungen gespeichert', $c['from_email']); header('Location: mail.php?tab=settings&saved=1'); exit;
  }
  $flash = '<div class="flash err">Konnte nicht speichern (Schreibrechte data/ prüfen).</div>';
  $tab = 'settings';
}

/* ---- Verbindung testen (Hauptpostfach oder persönliches Postfach, nur Owner) ---- */
if ($action === 'mb_test') {
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"ok":false}'); }
  pg_csrf_check();
  if (!pg_is_owner()) { http_response_code(403); exit('{"ok":false,"error":"Keine Berechtigung."}'); }
  $tid = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['id'] ?? 'main')) ?: 'main';
  if ($tid !== 'main' && !pg_mailbox_find($tid)) exit(json_encode(['ok' => false, 'error' => 'Postfach nicht gefunden.']));
  session_write_close();                                    // der Test darf andere Admin-Seiten nicht blockieren
  [$ri, $rs] = pg_mailbox_with($tid, fn() => [pg_imap_test(), pg_smtp_test()]);
  echo json_encode(['ok' => true, 'imap' => ['ok' => $ri[0], 'msg' => $ri[1]], 'smtp' => ['ok' => $rs[0], 'msg' => $rs[1]]], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---- Persönliche Postfächer anlegen / ändern / entfernen (nur Owner) ---- */
$mbErr = ''; $mbDraft = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mb_save'])) {
  pg_csrf_check();
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  [$okMb, $res] = pg_mailbox_upsert($_POST);
  if ($okMb) {
    pg_log_activity(empty($_POST['id']) ? 'Postfach hinzugefügt' : 'Postfach geändert', $res['email']);
    header('Location: mail.php?tab=settings&mbsaved=' . rawurlencode($res['id']) . '#postfaecher'); exit;
  }
  $mbErr = $res; $mbDraft = $_POST; $tab = 'settings';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mb_del'])) {
  pg_csrf_check();
  if (!pg_is_owner()) { http_response_code(403); exit('Keine Berechtigung.'); }
  $del = pg_mailbox_find(preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['id'] ?? '')));
  if ($del && pg_mailbox_delete($del['id'])) { pg_log_activity('Postfach entfernt', $del['email']); header('Location: mail.php?tab=settings&mbdel=1#postfaecher'); exit; }
  $flash = '<div class="flash err">Postfach konnte nicht entfernt werden.</div>'; $tab = 'settings';
}

/* ---- Mail senden (mit Formatierung, Anhängen, Signatur) ---- */
$draft = null;   // bei Fehlern: Eingaben zurück ins Formular
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_mail'])) {
  pg_csrf_check();
  $to = trim($_POST['to'] ?? '');
  $subject = trim($_POST['subject'] ?? '');
  $msgHtml = pg_mail_sanitize_html((string) ($_POST['message_html'] ?? ''));
  if (trim(strip_tags($msgHtml)) === '' && trim((string) ($_POST['message'] ?? '')) !== '') $msgHtml = nl2br(pg_h(trim($_POST['message'])));   // ohne JavaScript
  $sig = (string) ($_POST['sig'] ?? '');
  $withSig = !isset($_POST['with_sig_sent']) || !empty($_POST['with_sig']);   // ohne JavaScript: Signatur an
  $replyUid = (int) ($_POST['reply_uid'] ?? 0); $fwdUid = (int) ($_POST['fwd_uid'] ?? 0);
  $draft = ['to' => $to, 'subject' => $subject, 'html' => $msgHtml, 'sig' => $sig, 'with_sig' => $withSig, 'reply_uid' => $replyUid, 'fwd_uid' => $fwdUid];
  $err = '';
  if (isset($_POST['mb']) && (string) $_POST['mb'] !== $mbId) $err = 'Dieses Postfach steht dir nicht mehr zur Verfügung – bitte oben ein Postfach wählen und erneut senden.';
  elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) $err = 'Bitte eine gültige Empfänger-Adresse eingeben.';
  elseif ($subject === '' || trim(strip_tags($msgHtml)) === '') $err = 'Betreff und Nachricht dürfen nicht leer sein.';
  // Anhänge prüfen
  $atts = []; $total = 0;
  if ($err === '' && !empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
    foreach ($_FILES['attachments']['name'] as $k => $nm) {
      $e = $_FILES['attachments']['error'][$k] ?? UPLOAD_ERR_NO_FILE;
      if ($e === UPLOAD_ERR_NO_FILE || $nm === '') continue;
      if ($e !== UPLOAD_ERR_OK) { $err = 'Der Anhang „' . $nm . '“ konnte nicht hochgeladen werden (evtl. zu groß).'; break; }
      $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
      if (in_array($ext, PG_MAIL_ATT_BLOCK, true)) { $err = 'Dateien vom Typ .' . $ext . ' werden aus Sicherheitsgründen nicht verschickt.'; break; }
      $sz = (int) ($_FILES['attachments']['size'][$k] ?? 0); $total += $sz;
      if ($total > PG_MAIL_ATT_MAX) { $err = 'Anhänge sind zusammen größer als 10 MB.'; break; }
      $data = @file_get_contents($_FILES['attachments']['tmp_name'][$k]);
      if ($data === false) { $err = 'Anhang „' . $nm . '“ konnte nicht gelesen werden.'; break; }
      $mime = function_exists('mime_content_type') ? (@mime_content_type($_FILES['attachments']['tmp_name'][$k]) ?: 'application/octet-stream') : 'application/octet-stream';
      $atts[] = ['name' => mb_substr(basename(str_replace('\\', '/', $nm)), 0, 120), 'mime' => $mime, 'data' => $data];
    }
  }
  // Weiterleiten: ausgewählte Anhänge der Original-Mail mitschicken
  $hdrs = [];
  if ($err === '' && ($fwdUid || $replyUid) && pg_imap_available() && pg_mail_configured_recv()) {
    $mb = pg_imap_connect($folder);
    if ($mb) {
      if ($replyUid) $hdrs = pg_imap_thread_headers($mb, $replyUid);
      if ($fwdUid) foreach ((array) ($_POST['fwd_parts'] ?? []) as $pn) {
        $pn = preg_replace('/[^0-9.]/', '', (string) $pn);
        $a = $pn !== '' ? pg_imap_attachment_data($mb, $fwdUid, $pn) : null;
        if (!$a) continue;
        $total += strlen($a['data']);
        if ($total > PG_MAIL_ATT_MAX) { $err = 'Anhänge sind zusammen größer als 10 MB.'; break; }
        $atts[] = ['name' => $a['name'], 'mime' => $a['mime'], 'data' => $a['data']];
      }
      if ($err === '' && $replyUid) @imap_setflag_full($mb, (string) $replyUid, '\\Answered', ST_UID);
      imap_close($mb);
    }
  }
  if ($err === '') {
    $html = pg_mail_build_personal($msgHtml, ['html' => true, 'sig' => $withSig ? $sig : '']);
    [$fName, $fEmail, $fReply] = pg_mail_sender_active($sig);
    $ok = pg_send_mail($to, $subject, $html, $fReply, ['attachments' => $atts, 'headers' => $hdrs, 'from_name' => $fName, 'from_email' => $fEmail]);
    if ($ok) {
      $sid = pg_mail_sent_log($to, $subject, $html, $atts, $fName . ' <' . ($GLOBALS['pg_mail_last']['from'] ?? $fEmail) . '>');
      if (!empty($GLOBALS['pg_mail_last']['fallback'])) $_SESSION['pg_mail_note'] = 'Der Mailserver hat ' . $fEmail . ' als Absender nicht zugelassen – die Mail ging über die Studio-Adresse raus, Antworten landen trotzdem bei ' . $fEmail . '.';
      pg_log_activity('E-Mail gesendet', $to . ($atts ? ' · ' . (count($atts) === 1 ? '1 Anhang' : count($atts) . ' Anhänge') : ''));
      header('Location: mail.php?tab=sent&id=' . $sid . '&sent=1');   // kein doppeltes Senden beim Neuladen
      exit;
    }
    $err = 'Versand fehlgeschlagen. Bitte SMTP-Einstellungen prüfen.';
  }
  $flash = '<div class="flash err">' . pg_h($err) . ($atts || !empty($_FILES['attachments']['name'][0]) ? ' Bitte Anhänge ggf. erneut auswählen.' : '') . '</div>';
  $tab = 'compose';
}

/* ---- Newsletter an alle Abonnenten ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
  pg_csrf_check();
  if (!pg_can('subscribers')) { http_response_code(403); exit('Keine Berechtigung.'); }
  $subject = trim($_POST['subject'] ?? '');
  $msgHtml = pg_mail_sanitize_html((string) ($_POST['message_html'] ?? ''));
  if (trim(strip_tags($msgHtml)) === '' && trim((string) ($_POST['message'] ?? '')) !== '') $msgHtml = nl2br(pg_h(trim($_POST['message'])));
  $emails = pg_confirmed_emails(pg_load_json(PG_DATA_DIR . '/subscribers.json'));
  if ($subject === '' || trim(strip_tags($msgHtml)) === '') {
    $flash = '<div class="flash err">Betreff und Nachricht dürfen nicht leer sein.</div>';
    $draft = ['subject' => $subject, 'html' => $msgHtml];
  } elseif (!$emails) {
    $flash = '<div class="flash err">Noch keine Abonnenten vorhanden.</div>';
  } else {
    @set_time_limit(300);
    $sent = 0; $fail = 0;
    pg_mailbox_with('main', function () use ($emails, $subject, $msgHtml, &$sent, &$fail) {   // Newsletter immer übers Hauptpostfach
      foreach ($emails as $to) {
        $html = pg_mail_build_newsletter($msgHtml, pg_unsub_link($to), ['html' => true]);
        if (pg_send_mail($to, $subject, $html)) $sent++; else $fail++;
        usleep(120000); // freundlich zum Mailserver
      }
      if ($sent > 0) pg_mail_sent_log('📣 Newsletter · ' . $sent . ' Empfänger', $subject, pg_mail_build_newsletter($msgHtml, '#', ['html' => true]));
    });
    pg_log_activity('Newsletter verschickt', $sent . ' Abonnenten');
    $flash = '<div class="flash ' . ($fail ? 'err' : 'ok') . '">✓ Newsletter verschickt: ' . $sent . ' zugestellt'
           . ($fail ? ', ' . $fail . ' fehlgeschlagen' : '') . '.</div>';
  }
  $tab = 'newsletter';
}

/* ====================== ANSICHT ====================== */
$titles = ['inbox' => 'Posteingang', 'sent' => 'Gesendet', 'compose' => 'Neue E-Mail', 'newsletter' => 'Newsletter', 'settings' => 'Postfach-Einstellungen'];
$multi = count(pg_mailboxes_visible()) > 1;
$GLOBALS['pg_crumb_group'] = 'Postfach';
pg_view_head($titles[$tab] . ($multi && in_array($tab, ['inbox', 'sent', 'compose'], true) ? ' · ' . $mbBox['label'] : ''));
pg_view_topbar($SCHEMA, 'mail');
echo $flash;
if ($tab === 'settings' && isset($_GET['saved'])) echo '<div class="flash ok">✓ Einstellungen gespeichert.</div>';
if ($tab === 'settings' && isset($_GET['mbsaved'])) echo '<div class="flash ok">✓ Postfach gespeichert – die Verbindung wird gleich geprüft.</div>';
if ($tab === 'settings' && isset($_GET['mbdel'])) echo '<div class="flash ok">✓ Postfach entfernt. Die Mails bleiben auf dem Mailserver.</div>';
if (isset($_GET['sent'])) echo '<div class="flash ok">✓ Nachricht gesendet' . (pg_mail_configured_send() ? ' (per SMTP).' : ' (über den Server).') . '</div>';
if (!empty($_SESSION['pg_mail_note'])) { echo '<div class="flash ok">' . pg_h($_SESSION['pg_mail_note']) . '</div>'; unset($_SESSION['pg_mail_note']); }

$sel = ($tab === 'inbox' && (int) ($_GET['uid'] ?? 0)) || ($tab === 'sent' && preg_replace('/[^a-f0-9]/', '', $_GET['id'] ?? '') !== '');
$wide = in_array($tab, ['compose', 'newsletter', 'settings'], true);
echo '<div class="mx' . ($sel ? ' has-sel' : '') . ($wide ? ' mx-wide' : '') . '" data-mx>';

/* ---- Ordner (gemeinsame Leiste, auch auf „Kontaktformular“ & „Schnellantworten“) ---- */
echo pg_mx_nav($tab);

/* ---------------- POSTEINGANG ---------------- */
if ($tab === 'inbox') {
  $uid = (int) ($_GET['uid'] ?? 0);
  $page = max(1, (int) ($_GET['page'] ?? 1));
  $listHtml = ''; $readHtml = '';
  if (!pg_imap_available()) {
    $listHtml = '<div class="mx-note">Die <b>IMAP-Erweiterung</b> ist auf diesem Server nicht aktiv – der Empfang im Dashboard braucht sie. Verfassen &amp; Senden funktioniert unabhängig davon.</div>';
  } elseif (!pg_mail_configured_recv()) {
    $listHtml = '<div class="mx-note">Noch kein Postfach verbunden.' . (pg_is_owner() ? ' Hinterlege unter <a href="mail.php?tab=settings">Einstellungen</a> die IMAP-Zugangsdaten.' : ' Der Owner kann es unter „Einstellungen“ verbinden.') . '</div>';
  } else {
    $mbox = pg_imap_connect($folder);
    if (!$mbox) {
      $listHtml = '<div class="mx-note err">Verbindung zu ' . pg_h($mbBox['email'] ?: 'diesem Postfach') . ' fehlgeschlagen. '
                . (pg_is_owner() ? 'Bitte Zugangsdaten unter <a href="mail.php?tab=settings' . ($mbId !== 'main' ? '#postfaecher' : '') . '">Einstellungen</a> prüfen.' : 'Der Owner kann die Zugangsdaten unter „Einstellungen“ prüfen.') . '</div>';
    } else {
      $per = 40;
      if ($uid) @imap_setflag_full($mbox, (string) $uid, '\\Seen', ST_UID);   // geöffnete Mail zählt sofort als gelesen
      $list = pg_imap_list($mbox, $per, ($page - 1) * $per);
      $unseen = @imap_search($mbox, 'UNSEEN');
      pg_mail_unread_store(is_array($unseen) ? count($unseen) : 0);
      if ($page === 1 && $folder === 'INBOX') pg_inbox_preview_store($list['items']);   // fürs Cockpit („Neue Nachrichten“)
      // Lesebereich
      if ($uid) {
        $hdr = @imap_fetch_overview($mbox, (string) $uid, FT_UID);
        $h = $hdr[0] ?? null;
        if ($h) {
          $from = pg_imap_decode($h->from ?? '');
          $fromEmail = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : trim($from);
          $fromName = trim(preg_replace('/<[^>]+>/', '', $from), " \"'") ?: $fromEmail;
          $subj = pg_imap_decode($h->subject ?? '(kein Betreff)');
          $atts = pg_imap_attachments($mbox, $uid);
          $ini = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', preg_replace('/[^\p{L} ]/u', '', $fromName)) ?: [], 0, 2)))) ?: '✉';
          $reSub = preg_match('/^re:/i', $subj) ? $subj : 'Re: ' . $subj;
          $fwSub = preg_match('/^(fwd?|wg):/i', $subj) ? $subj : 'Fwd: ' . $subj;
          $readHtml .= '<div class="mx-read-h"><a class="btn btn-ghost btn-sm mx-back" href="mail.php?tab=inbox' . $mbq . ($page > 1 ? '&page=' . $page : '') . '">' . pg_icon('back') . 'Zurück</a>'
            . '<h2>' . pg_h($subj) . '</h2>'
            . '<div class="mx-from"><span class="avatar">' . pg_h($ini) . '</span><div><b>' . pg_h($fromName) . '</b><span>' . pg_h($fromEmail) . ' · ' . pg_h(date('d.m.Y · H:i', strtotime($h->date ?? 'now') ?: time())) . '</span></div>'
            . '<div class="mx-acts"><a class="btn btn-primary btn-sm" href="mail.php?tab=compose' . $mbq . '&reply=' . $uid . '&folder=' . urlencode($folder) . '">' . pg_icon('undo') . 'Antworten</a>'
            . '<a class="btn btn-sm" href="mail.php?tab=compose' . $mbq . '&fwd=' . $uid . '&folder=' . urlencode($folder) . '">' . pg_icon('send') . 'Weiterleiten</a></div></div></div>';
          $readHtml .= '<iframe class="mx-frame" src="mail.php?action=body' . $mbq . '&folder=' . urlencode($folder) . '&uid=' . $uid . '" sandbox="allow-popups allow-popups-to-escape-sandbox" title="Nachricht"></iframe>';
          if ($atts) {
            $readHtml .= '<div class="mx-atts"><div class="mx-atts-h">' . pg_icon('clip') . (count($atts) === 1 ? '1 Anhang' : count($atts) . ' Anhänge') . '</div><div class="mx-atts-g">';
            foreach ($atts as $a) {
              $ext = strtoupper(pathinfo($a['name'], PATHINFO_EXTENSION) ?: 'DATEI');
              $readHtml .= '<a class="mx-att" href="mail.php?action=attachment' . $mbq . '&folder=' . urlencode($folder) . '&uid=' . $uid . '&part=' . urlencode($a['part']) . '" title="Herunterladen">'
                . '<span class="mx-att-ic t-' . pg_h(strtolower(preg_replace('/[^a-z]/i', '', $ext))) . '">' . pg_h(mb_substr($ext, 0, 4)) . '</span>'
                . '<span class="mx-att-tx"><b>' . pg_h($a['name']) . '</b><small>' . pg_fmt_bytes($a['size']) . '</small></span>' . pg_icon('download') . '</a>';
            }
            $readHtml .= '</div></div>';
          }
        } else {
          $readHtml = '<div class="mx-empty">' . pg_icon('alert') . '<b>Nachricht nicht gefunden.</b></div>';
        }
      }
      imap_close($mbox);
      // Liste
      $listHtml .= '<div class="mx-list-h"><label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="Mails durchsuchen…" data-mx-search aria-label="Mails durchsuchen"></label>'
        . '<a class="icon-btn" href="mail.php?tab=inbox' . $mbq . '" title="Aktualisieren" aria-label="Aktualisieren">' . pg_icon('refresh') . '</a></div>';
      if (!$list['items']) {
        $listHtml .= '<div class="mx-empty">' . pg_icon('inbox') . '<b>Posteingang ist leer.</b></div>';
      } else {
        $listHtml .= '<ul class="mx-rows" data-mx-rows>';
        foreach ($list['items'] as $it) {
          $d = $it['date'] ?: 0;
          $when = $d ? (date('Y-m-d', $d) === date('Y-m-d') ? date('H:i', $d) : (date('Y', $d) === date('Y') ? date('d.m.', $d) : date('d.m.y', $d))) : '';
          $listHtml .= '<li><a class="mx-row' . ($it['seen'] ? '' : ' unread') . ($uid === (int) $it['uid'] ? ' on' : '') . '" href="mail.php?tab=inbox' . $mbq . '&uid=' . (int) $it['uid'] . ($page > 1 ? '&page=' . $page : '') . '"'
            . ' data-q="' . pg_h(mb_strtolower($it['from'] . ' ' . $it['from_email'] . ' ' . $it['subject'])) . '">'
            . '<span class="ud"></span><span class="mf">' . pg_h($it['from'] ?: $it['from_email']) . '</span><span class="mt">' . pg_h($when) . '</span>'
            . '<span class="ms">' . pg_h($it['subject']) . '</span></a></li>';
        }
        $listHtml .= '</ul>';
        $more = $list['total'] > $page * $per;
        if ($page > 1 || $more) $listHtml .= '<div class="mx-pager">' . ($page > 1 ? '<a class="btn btn-sm btn-ghost" href="mail.php?tab=inbox' . $mbq . '&page=' . ($page - 1) . '">' . pg_icon('back') . 'Neuere</a>' : '<span></span>')
          . '<span>' . (int) $list['total'] . ' Nachrichten</span>' . ($more ? '<a class="btn btn-sm btn-ghost" href="mail.php?tab=inbox' . $mbq . '&page=' . ($page + 1) . '">Ältere' . pg_icon('chev') . '</a>' : '<span></span>') . '</div>';
      }
    }
  }
  echo '<section class="ck-card mx-list">' . $listHtml . '</section>';
  echo '<article class="ck-card mx-read">' . ($readHtml !== '' ? $readHtml : '<div class="mx-empty">' . pg_icon('mail') . '<b>Wähle eine Mail aus.</b><span>Anhänge lassen sich direkt hier herunterladen.</span></div>') . '</article>';
}

/* ---------------- GESENDET ---------------- */
if ($tab === 'sent') {
  $list = array_values(array_filter(pg_mail_sent_load(), fn($r) => (string) ($r['mb'] ?? 'main') === $mbId));
  $id = preg_replace('/[^a-f0-9]/', '', $_GET['id'] ?? '');
  $listHtml = '<div class="mx-list-h"><label class="g-inp">' . pg_icon('search') . '<input type="search" placeholder="Gesendete durchsuchen…" data-mx-search aria-label="Gesendete durchsuchen"></label></div>';
  if (!$list) {
    $listHtml .= '<div class="mx-empty">' . pg_icon('send') . '<b>Noch nichts gesendet.</b><span>Verfasste Mails &amp; Newsletter erscheinen hier.</span></div>';
  } else {
    $listHtml .= '<ul class="mx-rows" data-mx-rows>';
    foreach ($list as $it) {
      $d = strtotime((string) ($it['date'] ?? '')) ?: 0;
      $when = $d ? (date('Y-m-d', $d) === date('Y-m-d') ? date('H:i', $d) : date('d.m.y', $d)) : '';
      $listHtml .= '<li><a class="mx-row' . (($it['id'] ?? '') === $id ? ' on' : '') . '" href="mail.php?tab=sent' . $mbq . '&id=' . urlencode($it['id'] ?? '') . '" data-q="' . pg_h(mb_strtolower(($it['to'] ?? '') . ' ' . ($it['subject'] ?? ''))) . '">'
        . '<span class="ud"></span><span class="mf">' . pg_h($it['to'] ?? '') . '</span><span class="mt">' . pg_h($when) . '</span>'
        . '<span class="ms">' . (!empty($it['atts']) ? pg_icon('clip') : '') . pg_h($it['subject'] ?? '') . '</span></a></li>';
    }
    $listHtml .= '</ul>';
  }
  $readHtml = '';
  if ($id !== '') {
    $row = null;
    foreach ($list as $e) { if (($e['id'] ?? '') === $id) { $row = $e; break; } }
    if (!$row) $readHtml = '<div class="mx-empty">' . pg_icon('alert') . '<b>Nachricht nicht gefunden.</b></div>';
    else {
      $isNl = strpos((string) $row['to'], 'Newsletter') !== false;
      $by = (string) ($row['by'] ?? '');
      $readHtml .= '<div class="mx-read-h"><a class="btn btn-ghost btn-sm mx-back" href="mail.php?tab=sent' . $mbq . '">' . pg_icon('back') . 'Zurück</a>'
        . '<h2>' . pg_h($row['subject'] ?: '(kein Betreff)') . '</h2>'
        . '<div class="mx-from"><span class="avatar">' . pg_icon('send') . '</span><div><b>An: ' . pg_h($row['to']) . '</b><span>' . (!empty($row['from']) ? 'Von: ' . pg_h($row['from']) . ' · ' : '') . pg_h(date('d.m.Y · H:i', strtotime($row['date'] ?? 'now') ?: time())) . ($by !== '' ? ' · gesendet von ' . pg_h($by) : '') . '</span></div>'
        . (!$isNl && filter_var($row['to'], FILTER_VALIDATE_EMAIL) ? '<div class="mx-acts"><a class="btn btn-sm" href="mail.php?tab=compose' . $mbq . '&to=' . urlencode($row['to']) . '">' . pg_icon('edit') . 'Neue Mail an diese Adresse</a></div>' : '')
        . '</div></div>';
      $readHtml .= '<iframe class="mx-frame dark" src="mail.php?action=sentbody&id=' . urlencode($row['id']) . '" sandbox="allow-popups allow-popups-to-escape-sandbox" title="Gesendete Nachricht"></iframe>';
      if (!empty($row['atts'])) {
        $readHtml .= '<div class="mx-atts"><div class="mx-atts-h">' . pg_icon('clip') . (count($row['atts']) === 1 ? '1 Anhang' : count($row['atts']) . ' Anhänge') . ' mitgeschickt</div><div class="mx-atts-g">';
        foreach ($row['atts'] as $a) {
          $ext = strtoupper(pathinfo((string) $a['name'], PATHINFO_EXTENSION) ?: 'DATEI');
          $readHtml .= '<div class="mx-att static"><span class="mx-att-ic t-' . pg_h(strtolower(preg_replace('/[^a-z]/i', '', $ext))) . '">' . pg_h(mb_substr($ext, 0, 4)) . '</span><span class="mx-att-tx"><b>' . pg_h($a['name']) . '</b><small>' . pg_fmt_bytes($a['size'] ?? 0) . '</small></span></div>';
        }
        $readHtml .= '</div></div>';
      }
    }
  }
  echo '<section class="ck-card mx-list">' . $listHtml . '</section>';
  echo '<article class="ck-card mx-read">' . ($readHtml !== '' ? $readHtml : '<div class="mx-empty">' . pg_icon('send') . '<b>Wähle eine gesendete Mail aus.</b></div>') . '</article>';
}

/* Klartext (z. B. Antwort-Vorlage aus den Kontakt-Anfragen) → Editor-HTML; „> “-Zeilen werden ein Zitat */
function pg_mx_text_to_html($t){
  $t = str_replace("\r", '', (string) $t);
  $out = ''; $quote = []; $para = [];
  $flushP = function () use (&$out, &$para) { if ($para) { $out .= '<p>' . implode('<br>', array_map('pg_h', $para)) . '</p>'; $para = []; } };
  $flushQ = function () use (&$out, &$quote) { if ($quote) { $out .= '<blockquote>' . implode('<br>', array_map('pg_h', $quote)) . '</blockquote>'; $quote = []; } };
  foreach (explode("\n", $t) as $line) {
    if (preg_match('/^>\s?(.*)$/', $line, $m)) { $flushP(); $quote[] = $m[1]; continue; }
    $flushQ();
    if (trim($line) === '') { $flushP(); continue; }
    $para[] = $line;
  }
  $flushP(); $flushQ();
  return (preg_match('/^\s*\n/', $t) ? '<p><br></p>' : '') . $out;
}

/* ---- Editor: pg_mx_editor() steht in lib.php (auch für „Warteliste anschreiben“) ---- */

/* ---------------- VERFASSEN ---------------- */
if ($tab === 'compose') {
  $to = (string) ($draft['to'] ?? ($_GET['to'] ?? ''));
  $subject = (string) ($draft['subject'] ?? ($_GET['subject'] ?? ''));
  $html = (string) ($draft['html'] ?? '');
  if ($html === '' && !empty($_GET['body'])) $html = pg_mx_text_to_html((string) $_GET['body']);
  $replyUid = (int) ($draft['reply_uid'] ?? ($_GET['reply'] ?? 0));
  $fwdUid = (int) ($draft['fwd_uid'] ?? ($_GET['fwd'] ?? 0));
  $fwdAtts = [];
  // Antworten / Weiterleiten: Empfänger, Betreff und zitierte Original-Nachricht vorbereiten
  if (($replyUid || $fwdUid) && pg_imap_available() && pg_mail_configured_recv() && ($mb = pg_imap_connect($folder))) {
    $ou = $replyUid ?: $fwdUid;
    $ov = (@imap_fetch_overview($mb, (string) $ou, FT_UID))[0] ?? null;
    if ($ov) {
      $from = pg_imap_decode($ov->from ?? '');
      $fromEmail = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : trim($from);
      $osub = pg_imap_decode($ov->subject ?? '');
      if (!$draft) {
        if ($replyUid) { $to = $fromEmail; $subject = preg_match('/^re:/i', $osub) ? $osub : 'Re: ' . $osub; }
        else $subject = preg_match('/^(fwd?|wg):/i', $osub) ? $osub : 'Fwd: ' . $osub;
        $body = pg_imap_body($mb, $ou);
        $txt = $body['html'] ? pg_mail_html_to_text($body['body']) : (string) $body['body'];
        $txt = mb_substr(trim($txt), 0, 6000);
        $lead = $replyUid ? 'Am ' . date('d.m.Y \u\m H:i', strtotime($ov->date ?? 'now') ?: time()) . ' schrieb ' . pg_h($from) . ':'
                          : '---------- Weitergeleitete Nachricht ----------<br>Von: ' . pg_h($from) . '<br>Betreff: ' . pg_h($osub);
        $html = '<p><br></p><p>' . $lead . '</p><blockquote>' . nl2br(pg_h($txt)) . '</blockquote>';
      }
      if ($fwdUid) $fwdAtts = pg_imap_attachments($mb, $fwdUid);
    }
    imap_close($mb);
  }
  $sigs = pg_mail_signatures();
  $sigSel = $draft !== null ? (string) ($draft['sig'] ?? '') : pg_mail_signature_default_active();   // persönliches Postfach → dessen Signatur
  $withSigOn = $draft !== null ? !empty($draft['with_sig']) : !($mbId !== 'main' && $sigSel === '');   // „Keine Signatur“ im Postfach → aus
  $maxUp = min(PG_MAIL_ATT_MAX, (int) (pg_ini_bytes(ini_get('upload_max_filesize')) ?: PG_MAIL_ATT_MAX), (int) (pg_ini_bytes(ini_get('post_max_size')) ?: PG_MAIL_ATT_MAX));
  echo '<form method="post" enctype="multipart/form-data" class="ck-card mx-compose" data-composer data-mail-compose data-mode="compose" data-html data-max="' . $maxUp . '">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="send_mail" value="1"><input type="hidden" name="mb" value="' . pg_h($mbId) . '">'
     . '<input type="hidden" name="reply_uid" value="' . $replyUid . '"><input type="hidden" name="fwd_uid" value="' . $fwdUid . '">';
  echo '<div class="cmp-h"><h2>' . ($replyUid ? 'Antworten' : ($fwdUid ? 'Weiterleiten' : 'Neue E-Mail')) . '</h2><span class="cmp-draft" data-draft-status></span>'
     . '<button type="button" class="btn btn-sm btn-ghost hide-m" data-preview-toggle>' . pg_icon('eye') . 'Vorschau</button></div>';
  echo '<div class="cmp-fields">'
     . '<label class="cmp-row"><span>An</span><input type="email" name="to" value="' . pg_h($to) . '" placeholder="empfaenger@beispiel.de" required autocomplete="email"></label>'
     . '<label class="cmp-row"><span>Betreff</span><input type="text" name="subject" value="' . pg_h($subject) . '" required></label>'
     . '<div class="cmp-row"><span>Von</span><select name="sig" data-sig-select title="Absender &amp; Signatur">';
  foreach (pg_mail_from_options($sigs) as $o) echo '<option value="' . pg_h($o['id']) . '"' . ($sigSel === $o['id'] ? ' selected' : '') . '>' . pg_h($o['text']) . '</option>';
  echo '</select><label class="cmp-sigon" title="Signatur unter die Nachricht setzen"><input type="hidden" name="with_sig_sent" value="1"><input type="checkbox" name="with_sig" value="1"' . ($withSigOn ? ' checked' : '') . ' data-sig-on> Signatur</label>'
     . '<button type="button" class="btn btn-sm btn-ghost" data-open-sigs>Verwalten…</button></div></div>';
  echo pg_mx_editor($html, true);
  // Anhänge
  echo '<div class="cmp-atts" data-atts><label class="cmp-drop" data-drop>' . pg_icon('clip') . '<span><b>Dateien anhängen</b> – hierher ziehen oder klicken · zusammen bis ' . pg_fmt_bytes($maxUp) . '</span>'
     . '<input type="file" name="attachments[]" multiple hidden data-att-input></label><ul class="cmp-att-list" data-att-list></ul>';
  if ($fwdAtts) {
    echo '<div class="cmp-fwd"><b>Anhänge der Original-Mail mitschicken:</b>';
    foreach ($fwdAtts as $a) echo '<label class="cmp-fwd-a"><input type="checkbox" name="fwd_parts[]" value="' . pg_h($a['part']) . '" checked data-fwd-size="' . (int) $a['size'] . '"> ' . pg_h($a['name']) . ' <small>' . pg_fmt_bytes($a['size']) . '</small></label>';
    echo '</div>';
  }
  echo '</div>';
  echo '<div class="cmp-foot"><span class="hint">' . ($mbId !== 'main' ? 'Geht über dein Postfach <b>' . pg_h($mbBox['email']) . '</b> raus. ' : '')
     . (pg_mail_configured_send() ? 'Versand per SMTP · im PLANIGAMES-Design, mit Text-Fassung für alle Mailprogramme.' : 'Versand über die Server-Funktion mail() – für zuverlässige Zustellung SMTP einrichten.') . '</span>'
     . '<button class="btn btn-primary" type="submit">' . pg_icon('send') . 'Senden</button></div>';
  echo '</form>';
  echo '<aside class="ck-card mx-preview" data-preview-pane><div class="mx-preview-h">' . pg_icon('eye') . 'So kommt die Mail an</div><iframe class="mx-preview-f" data-mail-frame title="E-Mail-Vorschau"></iframe></aside>';
}

/* ---------------- NEWSLETTER ---------------- */
if ($tab === 'newsletter') {
  $subs = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  $count = count(pg_confirmed_emails(is_array($subs) ? $subs : []));
  echo '<form method="post" class="ck-card mx-compose" data-composer data-mail-compose data-mode="newsletter" data-html onsubmit="return confirm(\'Newsletter jetzt an alle ' . $count . ' bestätigten Abonnenten senden?\')">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="send_newsletter" value="1">';
  echo '<div class="cmp-h"><h2>Newsletter</h2><span class="cmp-draft" data-draft-status></span><button type="button" class="btn btn-sm btn-ghost hide-m" data-preview-toggle>' . pg_icon('eye') . 'Vorschau</button></div>';
  echo '<div class="mx-note">Geht an <b>' . $count . '</b> bestätigte Abonnent' . ($count === 1 ? '' : 'en') . '. Jede Mail enthält automatisch einen Abmeldelink. '
     . (pg_mailbox_with('main', 'pg_mail_configured_send') ? 'Versand per SMTP über das Hauptpostfach.' : 'Versand über mail() – für große Listen SMTP empfohlen.') . '</div>';
  echo '<div class="cmp-fields"><label class="cmp-row"><span>Betreff</span><input type="text" name="subject" value="' . pg_h((string) ($draft['subject'] ?? '')) . '" required></label></div>';
  echo pg_mx_editor((string) ($draft['html'] ?? ''), false);
  echo '<div class="cmp-foot"><a class="btn btn-ghost" href="index.php?view=subscribers">' . pg_icon('list') . 'Abonnenten</a><span class="sp"></span>'
     . '<button class="btn btn-primary" type="submit"' . ($count ? '' : ' disabled') . '>' . pg_icon('mega') . 'An ' . $count . ' senden</button></div>';
  echo '</form>';
  echo '<aside class="ck-card mx-preview" data-preview-pane><div class="mx-preview-h">' . pg_icon('eye') . 'So kommt der Newsletter an</div><iframe class="mx-preview-f" data-mail-frame title="Newsletter-Vorschau"></iframe></aside>';
}

/* ---------------- EINSTELLUNGEN (nur Owner) ---------------- */
/* Formular für ein persönliches Postfach (Dialog „Hinzufügen“ bzw. „Bearbeiten“) */
function pg_mbx_dialog($m, $users, $draft = null){
  $isNew = empty($m['id']);
  $dlg = 'dlg-mb-' . ($isNew ? 'new' : $m['id']);
  $val = fn($k, $def = '') => pg_h((string) ($draft[$k] ?? $m[$k] ?? $def));
  $owners = array_map('strtolower', (array) ($draft['owners'] ?? $m['owners'] ?? []));
  $same = $draft !== null ? !empty($draft['same_server']) : ($isNew || !empty($m['same_server']));
  $h = '<div class="pg-modal-back" id="' . pg_h($dlg) . '" hidden' . ($draft !== null ? ' data-autoopen' : '') . '>'
     . '<form class="pg-modal mbx-form" method="post" action="mail.php?tab=settings#postfaecher" role="dialog" aria-modal="true" aria-labelledby="' . pg_h($dlg) . '-t">'
     . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="mb_save" value="1"><input type="hidden" name="id" value="' . pg_h($m['id'] ?? '') . '">'
     . '<div class="pg-modal-h"><div><h3 id="' . pg_h($dlg) . '-t">' . ($isNew ? 'Persönliches Postfach hinzufügen' : 'Postfach bearbeiten') . '</h3>'
     . '<p>' . ($isNew ? 'Die Adresse legst du vorher im KAS von All-Inkl an – hier verbindest du sie nur.' : pg_h($m['email'])) . '</p></div>'
     . '<button type="button" class="icon-btn" data-close aria-label="Schließen">' . pg_icon('x') . '</button></div><div class="pg-modal-b">';
  if ($draft !== null && !empty($GLOBALS['mbErr'])) $h .= '<div class="mx-note err mbx-err">' . pg_h($GLOBALS['mbErr']) . '</div>';
  $h .= '<div class="mailgrid">'
     . '<div class="field"><label class="flabel" for="' . $dlg . '-mail">E-Mail-Adresse</label><input id="' . $dlg . '-mail" type="email" name="email" value="' . $val('email') . '" placeholder="dominic@planigames.com" required autocomplete="off"' . ($isNew ? ' data-autofocus' : '') . '></div>'
     . '<div class="field"><label class="flabel" for="' . $dlg . '-pw">Passwort</label><input id="' . $dlg . '-pw" type="password" name="pass" value="" placeholder="' . ($isNew ? 'Passwort des Postfachs' : '•••••• gespeichert – leer = behalten') . '"' . ($isNew ? ' required' : '') . ' autocomplete="new-password"></div>'
     . '<div class="field"><label class="flabel" for="' . $dlg . '-lb">Name im Umschalter</label><input id="' . $dlg . '-lb" type="text" name="label" value="' . $val('label') . '" placeholder="z. B. Dominic" maxlength="40"></div>'
     . '<div class="field"><label class="flabel" for="' . $dlg . '-fn">Absender-Name <small>optional</small></label><input id="' . $dlg . '-fn" type="text" name="from_name" value="' . $val('from_name') . '" placeholder="z. B. Dominic | PLANIGAMES" maxlength="80"></div>'
     . '</div><div class="mbx-sub">Wer sieht dieses Postfach?</div><div class="mbx-owners">';
  foreach ($users as $u) {
    $e = strtolower((string) ($u['email'] ?? '')); if ($e === '') continue;
    $h .= '<label class="wlm-check"><input type="checkbox" name="owners[]" value="' . pg_h($e) . '"' . (in_array($e, $owners, true) ? ' checked' : '') . '>'
        . '<span><b>' . pg_h(trim((string) ($u['name'] ?? '')) ?: $e) . '</b><small>' . pg_h($e) . ' · ' . (($u['role'] ?? '') === 'owner' ? 'Owner' : 'Editor') . '</small></span></label>';
  }
  $sigs = pg_mail_signatures();
  $sigCur = (string) ($draft['sig'] ?? ($m['sig'] ?? ''));
  $auto = $isNew ? null : pg_mailbox_signature(['sig' => ''] + $m);
  $autoTxt = '';
  if ($auto) foreach ($sigs as $x) if ((string) $x['id'] === $auto) $autoTxt = ' – jetzt: ' . (trim((string) ($x['name'] ?? '')) ?: (string) ($x['label'] ?? ''));
  $h .= '</div><p class="hint">Nur diese Personen sehen das Postfach im Dashboard. Das Hauptpostfach sehen alle mit dem Recht „E-Mail-Postfach“.</p>'
     . '<div class="field mbx-sig"><label class="flabel" for="' . $dlg . '-sig">Signatur in diesem Postfach</label><select id="' . $dlg . '-sig" name="sig">'
     . '<option value=""' . ($sigCur === '' ? ' selected' : '') . '>Automatisch passend' . pg_h($autoTxt) . '</option>';
  foreach ($sigs as $x) $h .= '<option value="' . pg_h($x['id']) . '"' . ($sigCur === (string) $x['id'] ? ' selected' : '') . '>' . pg_h(trim((string) ($x['name'] ?? '')) ?: (string) ($x['label'] ?? '')) . ((string) $x['id'] === 'studio' ? ' (Studio)' : '') . '</option>';
  $h .= '<option value="-"' . ($sigCur === '-' ? ' selected' : '') . '>Keine Signatur</option></select>'
     . '<p class="hint">„Automatisch“ nimmt die Signatur mit derselben E-Mail-Adresse bzw. dem Namen der Person. Die Adresse in der Signatur ist immer die dieses Postfachs.</p></div>'
     . '<label class="wlm-check mbx-same"><input type="checkbox" name="same_server" value="1"' . ($same ? ' checked' : '') . '><span><b>Gleicher Mailserver wie das Hauptpostfach</b>'
     . '<small>Bei All-Inkl liegen alle Adressen auf demselben Server – einfach angehakt lassen. Benutzername ist die E-Mail-Adresse.</small></span></label>'
     . '<div class="mbx-srv"><div class="mailgrid">';
  foreach (['imap' => ['Empfang (IMAP)', 993, 'ssl', ['ssl' => 'SSL (993)', 'tls' => 'STARTTLS (143)', '' => 'keine']], 'smtp' => ['Versand (SMTP)', 587, 'tls', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'keine']]] as $k => [$lbl, $port, $sec, $opts]) {
    $cur = (string) ($draft[$k . '_secure'] ?? $m[$k . '_secure'] ?? $sec);
    $h .= '<div class="field"><label class="flabel">' . $lbl . ' – Server</label><input type="text" name="' . $k . '_host" value="' . $val($k . '_host') . '" placeholder="w01xxxxx.kasserver.com"></div>'
        . '<div class="field mbx-port"><label class="flabel">Port</label><div class="mbx-port-row"><input type="number" name="' . $k . '_port" value="' . $val($k . '_port', (string) $port) . '"><select name="' . $k . '_secure">';
    foreach ($opts as $ov => $ol) $h .= '<option value="' . $ov . '"' . ($cur === (string) $ov ? ' selected' : '') . '>' . $ol . '</option>';
    $h .= '</select></div></div>';
  }
  $h .= '</div></div></div><div class="pg-modal-f">'
     . ($isNew ? '' : '<span class="hint">Mails bleiben immer auf dem Mailserver.</span>') . '<span class="sp"></span>'
     . '<button type="button" class="btn btn-ghost" data-close>Abbrechen</button><button class="btn btn-primary">' . pg_icon('check') . ($isNew ? 'Hinzufügen' : 'Speichern') . '</button></div></form></div>';
  return $h;
}

if ($tab === 'settings') {
  $c = pg_mail_config_main();
  $v = fn($k) => pg_h($c[$k] ?? '');
  $mainMail = trim((string) ($c['from_email'] ?: $c['imap_user']));
  echo '<section class="ck-card mx-settings mx-set-main">';
  echo '<div class="cmp-h"><h2>Hauptpostfach</h2>' . ($mainMail !== '' ? '<span class="mbx-head-mail">' . pg_h($mainMail) . '</span>' : '') . '</div>';
  echo '<div class="mx-note">Die Studio-Adresse (z. B. hello@…) – für Newsletter, Warteliste und alle, die das Recht „E-Mail-Postfach“ haben. '
     . 'All-Inkl-Postfächer findest du im KAS unter „E-Mail“. Typische Werte: Server <code>w01xxxxx.kasserver.com</code>, SMTP-Port 587 (TLS) oder 465 (SSL), IMAP-Port 993 (SSL). Benutzername = vollständige E-Mail-Adresse.</div>';
  echo '<form method="post" class="mx-set-form">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<h3>Absender</h3><div class="mailgrid">';
  echo '<div class="field"><label class="flabel">Absender-Name</label><input type="text" name="from_name" value="' . $v('from_name') . '" placeholder="PLANIGAMES"></div>';
  echo '<div class="field"><label class="flabel">Absender-Adresse</label><input type="text" name="from_email" value="' . $v('from_email') . '" placeholder="hello@planigames.com"></div></div>';
  echo '<h3>Versand (SMTP)</h3>';
  echo '<div class="field"><label class="flabel">SMTP-Server</label><input type="text" name="smtp_host" value="' . $v('smtp_host') . '" placeholder="w01xxxxx.kasserver.com"></div>';
  echo '<div class="mailgrid"><div class="field"><label class="flabel">Port</label><input type="number" name="smtp_port" value="' . $v('smtp_port') . '"></div>';
  echo '<div class="field"><label class="flabel">Verschlüsselung</label><select name="smtp_secure">';
  foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'keine'] as $k => $lbl) echo '<option value="' . $k . '"' . ($c['smtp_secure'] === $k ? ' selected' : '') . '>' . $lbl . '</option>';
  echo '</select></div></div>';
  echo '<div class="field"><label class="flabel">Benutzer (E-Mail)</label><input type="text" name="smtp_user" value="' . $v('smtp_user') . '" placeholder="hello@planigames.com"></div>';
  echo '<div class="field"><label class="flabel">Passwort</label><input type="password" name="smtp_pass" value="" placeholder="' . ($c['smtp_pass'] ? '•••••• (gespeichert – leer lassen zum Behalten)' : 'Postfach-Passwort') . '" autocomplete="new-password"></div>';
  echo '<h3>Empfang (IMAP)</h3>';
  if (!pg_imap_available()) echo '<p class="hint" style="color:#ffb37a">Hinweis: Die PHP-IMAP-Erweiterung ist hier nicht aktiv. Auf All-Inkl ist sie i. d. R. verfügbar.</p>';
  echo '<div class="field"><label class="flabel">IMAP-Server</label><input type="text" name="imap_host" value="' . $v('imap_host') . '" placeholder="w01xxxxx.kasserver.com"></div>';
  echo '<div class="mailgrid"><div class="field"><label class="flabel">Port</label><input type="number" name="imap_port" value="' . $v('imap_port') . '"></div>';
  echo '<div class="field"><label class="flabel">Verschlüsselung</label><select name="imap_secure">';
  foreach (['ssl' => 'SSL (993)', 'tls' => 'STARTTLS (143)', '' => 'keine'] as $k => $lbl) echo '<option value="' . $k . '"' . ($c['imap_secure'] === $k ? ' selected' : '') . '>' . $lbl . '</option>';
  echo '</select></div></div>';
  echo '<div class="field"><label class="flabel">Benutzer (E-Mail)</label><input type="text" name="imap_user" value="' . $v('imap_user') . '" placeholder="hello@planigames.com"></div>';
  echo '<div class="field"><label class="flabel">Passwort</label><input type="password" name="imap_pass" value="" placeholder="' . ($c['imap_pass'] ? '•••••• (gespeichert – leer lassen zum Behalten)' : 'Postfach-Passwort') . '" autocomplete="new-password"></div>';
  echo '<h3>Signaturen</h3><p class="hint">Mehrere Signaturen (Studio + pro Person) – beim Schreiben auswählbar, jede*r Admin wählt den eigenen Standard.</p>'
     . '<button type="button" class="btn" data-open-sigs>' . pg_icon('user') . 'Signaturen verwalten</button>';
  echo '<div class="mbx-test" data-mb-status="main" hidden></div>';
  echo '<div class="cmp-foot"><button type="button" class="btn" data-mb-test="main" title="Prüft die gespeicherten Zugangsdaten – es wird nichts gesendet">' . pg_icon('refresh') . 'Verbindung testen</button><span class="sp"></span>'
     . '<button class="btn btn-primary" name="save_settings" value="1">' . pg_icon('check') . 'Einstellungen speichern</button></div>';
  echo '</form></section>';

  // ---- Persönliche Postfächer ----
  $boxes = pg_mailboxes_all();
  $users = pg_users_load();
  $names = [];
  foreach ($users as $u) $names[strtolower((string) ($u['email'] ?? ''))] = trim((string) ($u['name'] ?? '')) ?: (string) ($u['email'] ?? '');
  $justSaved = preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['mbsaved'] ?? ''));
  echo '<section class="ck-card mx-settings mx-set-boxes" id="postfaecher">';
  echo '<div class="cmp-h"><h2>Persönliche Postfächer</h2>' . ($boxes ? '<button type="button" class="btn btn-primary btn-sm" data-open-dialog="dlg-mb-new">' . pg_icon('plus') . 'Postfach</button>' : '') . '</div>';
  echo '<p class="mbx-intro">Eigene Adressen fürs Team, z. B. <b>dominic@planigames.com</b>. Jedes Postfach sehen nur die Personen, die du auswählst – im Postfach wird dann oben zwischen Hauptpostfach und eigenem Postfach umgeschaltet.</p>';
  if ($mbErr !== '' && empty($mbDraft)) echo '<div class="mx-note err">' . pg_h($mbErr) . '</div>';
  if (!$boxes) {
    echo '<div class="mbx-empty">' . pg_icon('inbox') . '<b>Noch keine persönlichen Postfächer</b><span>Lege die Adresse im KAS an und verbinde sie hier mit Adresse und Passwort.</span>'
       . '<button type="button" class="btn btn-primary btn-sm" data-open-dialog="dlg-mb-new">' . pg_icon('plus') . 'Postfach hinzufügen</button></div>';
  } else {
    echo '<ul class="mbx-list">';
    foreach ($boxes as $m) {
      $who = array_map(fn($e) => $names[strtolower($e)] ?? $e, (array) ($m['owners'] ?? []));
      $sgId = pg_mailbox_signature($m); $sgTxt = $sgId === '' ? 'keine Signatur' : ($sgId === null ? 'Signatur wie Hauptpostfach' : '');
      if ($sgId) foreach (pg_mail_signatures() as $x) if ((string) $x['id'] === $sgId) $sgTxt = 'Signatur: ' . (trim((string) ($x['name'] ?? '')) ?: (string) ($x['label'] ?? ''));
      echo '<li class="mbx-row"' . ($justSaved === $m['id'] ? ' data-mb-autotest="' . pg_h($m['id']) . '"' : '') . '>'
         . '<span class="mx-box-av">' . pg_h(mb_strtoupper(mb_substr((string) ($m['label'] ?: $m['email']), 0, 1))) . '</span>'
         . '<div class="mbx-tx"><b>' . pg_h($m['label'] ?: $m['email']) . '</b><span>' . pg_h($m['email']) . '</span>'
         . '<small>' . pg_icon('user') . 'Sichtbar für ' . pg_h($who ? implode(', ', $who) : 'niemanden') . (empty($m['same_server']) ? ' · eigener Mailserver' : '') . '</small>'
         . '<small>' . pg_icon('edit') . pg_h($sgTxt) . '</small></div>'
         . '<div class="mbx-acts"><button type="button" class="btn btn-sm" data-mb-test="' . pg_h($m['id']) . '">' . pg_icon('refresh') . 'Testen</button>'
         . '<button type="button" class="icon-btn" data-open-dialog="dlg-mb-' . pg_h($m['id']) . '" title="Bearbeiten" aria-label="Bearbeiten">' . pg_icon('edit') . '</button>'
         . '<form method="post" action="mail.php?tab=settings" onsubmit="return confirm(\'Postfach „' . pg_h(addslashes($m['email'])) . '“ aus dem Dashboard entfernen? Die Mails bleiben auf dem Mailserver.\')">'
         . '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '"><input type="hidden" name="id" value="' . pg_h($m['id']) . '">'
         . '<button class="icon-btn" name="mb_del" value="1" title="Entfernen" aria-label="Entfernen">' . pg_icon('trash') . '</button></form></div>'
         . '<div class="mbx-test" data-mb-status="' . pg_h($m['id']) . '" hidden></div></li>';
    }
    echo '</ul>';
  }
  echo '</section>';
  // Dialoge (Hinzufügen + je Postfach Bearbeiten); bei Fehlern mit den Eingaben wieder offen
  $draftId = $mbDraft !== null ? preg_replace('/[^a-z0-9_]/', '', (string) ($mbDraft['id'] ?? '')) : null;
  echo pg_mbx_dialog([], $users, $mbDraft !== null && $draftId === '' ? $mbDraft : null);
  foreach ($boxes as $m) echo pg_mbx_dialog($m, $users, $mbDraft !== null && $draftId === $m['id'] ? $mbDraft : null);
}
echo '</div>';   // .mx

/* ---- Dialog: Signaturen verwalten ---- */
$sigs = pg_mail_signatures();
$mine = pg_mail_signature_default();
$team = array_values(array_filter(array_map(fn($m) => is_array($m) ? ['name' => (string) ($m['name'] ?? ''), 'role' => (string) ($m['role'] ?? '')] : null,
          (array) (pg_load_json(PG_DATA_DIR . '/team.json')['members'] ?? [])), fn($m) => $m && $m['name'] !== ''));
echo '<div class="pg-modal-back" id="dlg-sigs" hidden><div class="pg-modal lg" role="dialog" aria-modal="true" aria-labelledby="dlg-sigs-t">'
   . '<div class="pg-modal-h"><div><h3 id="dlg-sigs-t">Absender &amp; Signaturen</h3><p>Jedes Profil legt fest, von welcher Adresse eine Mail kommt und welche Signatur darunter steht. Website &amp; Social-Links kommen aus den Studio-Einstellungen.</p></div>'
   . '<button type="button" class="icon-btn" data-close aria-label="Schließen">' . pg_icon('x') . '</button></div>'
   . '<div class="pg-modal-b"><div class="sig-list" data-sig-list></div>'
   . '<div class="sig-add"><button type="button" class="btn btn-sm" data-sig-add>' . pg_icon('plus') . 'Signatur</button>'
   . ($team ? '<button type="button" class="btn btn-sm btn-ghost" data-sig-team>' . pg_icon('user') . 'Aus Team-Profilen übernehmen</button>' : '') . '</div></div>'
   . '<div class="pg-modal-f"><span class="hint">„Mein Standard“ gilt nur für dich.</span><span class="sp"></span><button type="button" class="btn btn-ghost" data-close>Abbrechen</button>'
   . '<button type="button" class="btn btn-primary" data-sig-save>' . pg_icon('check') . 'Speichern</button></div></div></div>';
[$stN0, $stE0] = pg_mailbox_with('main', 'pg_mail_from');
echo '<script type="application/json" id="pg-sigs-data">' . json_encode(['sigs' => $sigs, 'mine' => $mine, 'team' => $team, 'studio' => ['name' => $stN0, 'email' => $stE0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
pg_view_foot();
