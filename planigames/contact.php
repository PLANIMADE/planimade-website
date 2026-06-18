<?php
/**
 * PLANIGAMES — Kontaktformular (All-Inkl, ohne Datenbank).
 * Speichert Anfragen in data/contacts.json (per data/.htaccess geschützt)
 * und schickt eine gebrandete Benachrichtigung ans Studio.
 */
require __DIR__ . '/admin/lib.php';
header('Content-Type: application/json; charset=utf-8');

$DATA   = __DIR__ . '/data';
$studio = is_file($DATA . '/studio.json') ? json_decode(file_get_contents($DATA . '/studio.json'), true) : [];
$cfg    = $studio['contact'] ?? [];

if (isset($cfg['enabled']) && !$cfg['enabled']) {
  http_response_code(403); echo json_encode(['error' => 'Das Kontaktformular ist derzeit deaktiviert.']); exit;
}

// Honeypot – Bots füllen das versteckte Feld aus
if (!empty($_POST['website'])) { echo json_encode(['ok' => true, 'message' => $cfg['successMessage'] ?? 'Danke!']); exit; }

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
  http_response_code(422);
  echo json_encode(['error' => 'Bitte fülle Name, eine gültige E-Mail und eine Nachricht aus.']); exit;
}
$name    = mb_substr($name, 0, 120);
$email   = mb_substr($email, 0, 180);
$subject = mb_substr($subject, 0, 160);
$message = mb_substr($message, 0, 5000);

// In data/contacts.json ablegen
$file = $DATA . '/contacts.json';
$fp = @fopen($file, 'c+');
if ($fp !== false) {
  flock($fp, LOCK_EX);
  $list = json_decode(stream_get_contents($fp) ?: '[]', true);
  if (!is_array($list)) $list = [];
  $list[] = [
    'name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message,
    'date' => date('c'), 'read' => false,
  ];
  ftruncate($fp, 0); rewind($fp);
  fwrite($fp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  flock($fp, LOCK_UN); fclose($fp);
}

// Gebrandete Benachrichtigung ans Studio (Antworten geht direkt an den Absender)
$notify = ($cfg['notifyEmail'] ?? '') ?: ($studio['email'] ?? '');
if ($notify && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
  $rows = function ($label, $val) {
    return '<tr><td style="padding:6px 14px 6px 0;color:#9a9aa6;font-size:13px;white-space:nowrap;vertical-align:top">' . pg_h($label) . '</td>'
         . '<td style="padding:6px 0;color:#ececf0;font-size:14px">' . $val . '</td></tr>';
  };
  $body = '<h1 style="margin:14px 0 0;font-size:22px;color:#fff">Neue Kontaktanfrage 📨</h1>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 6px">'
    . $rows('Name', pg_h($name))
    . $rows('E-Mail', '<a href="mailto:' . pg_h($email) . '" style="color:#ff8a2b;text-decoration:none">' . pg_h($email) . '</a>')
    . ($subject !== '' ? $rows('Betreff', pg_h($subject)) : '')
    . '</table>'
    . '<div style="margin-top:14px;padding:16px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;color:#d7d7df;font-size:15px;line-height:1.6">'
    . nl2br(pg_h($message)) . '</div>'
    . '<p style="color:#7b7b86;font-size:12px;margin:16px 0 0">Direkt auf diese E-Mail antworten – die Antwort geht an ' . pg_h($email) . '.</p>';
  $html = pg_mail_shell($body, 'Neue Kontaktanfrage von ' . $name);
  pg_send_mail($notify, 'Neue Kontaktanfrage' . ($subject !== '' ? ': ' . $subject : ''), $html, $email);
}

echo json_encode(['ok' => true, 'message' => $cfg['successMessage'] ?? 'Danke für deine Nachricht! Wir melden uns bald. 🧡']);
