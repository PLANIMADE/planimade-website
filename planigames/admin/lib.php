<?php
/**
 * PLANIGAMES Admin — Kernfunktionen (Flat-File CMS für All-Inkl).
 * Auth, CSRF, JSON-I/O, schema-getriebenes Rendern & Normalisieren.
 */

const PG_DATA_DIR    = __DIR__ . '/../data';
const PG_MEDIA_DIR   = __DIR__ . '/../media';
const PG_USERS_FILE  = __DIR__ . '/../data/users.json';
const PG_INVITES_FILE= __DIR__ . '/../data/invites.json';
const PG_UPLOAD_EXT  = ['jpg','jpeg','png','webp','gif','svg','avif','mp4','webm','ogg','mov'];

function pg_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pg_session_boot(){
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
      'httponly' => true,
      'samesite' => 'Lax',
      'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('PGADMIN');
    session_start();
  }
}

/* ---------------- Benutzer & Auth ---------------- */
function pg_users_load(){ $u = pg_load_json(PG_USERS_FILE); return is_array($u) ? $u : []; }
function pg_users_save($u){ return pg_save_json(PG_USERS_FILE, array_values($u)); }
function pg_users_exist(){ return count(pg_users_load()) > 0; }
function pg_user_find($email){
  foreach (pg_users_load() as $u) if (isset($u['email']) && strcasecmp($u['email'], $email) === 0) return $u;
  return null;
}
function pg_user_add($email, $pw, $role = 'editor', $name = ''){
  $users = pg_users_load();
  $users[] = ['email'=>$email, 'name'=>$name, 'role'=>$role,
              'hash'=>password_hash($pw, PASSWORD_DEFAULT), 'created'=>date('c')];
  pg_users_save($users);
}
function pg_user_delete($email){
  pg_users_save(array_filter(pg_users_load(), fn($u) => strcasecmp($u['email'] ?? '', $email) !== 0));
}
function pg_user_check($email, $pw){
  $u = pg_user_find($email);
  return ($u && !empty($u['hash']) && password_verify($pw, $u['hash'])) ? $u : null;
}
function pg_owner_count(){ return count(array_filter(pg_users_load(), fn($u) => ($u['role'] ?? '') === 'owner')); }

function pg_user_update($email, array $patch){
  $users = pg_users_load();
  foreach ($users as &$u) if (strcasecmp($u['email'] ?? '', $email) === 0) $u = array_merge($u, $patch);
  unset($u);
  pg_users_save($users);
}
function pg_login_user($u){ $_SESSION['pg_user'] = $u['email']; }
function pg_logged_in(){ return !empty($_SESSION['pg_user']); }
function pg_current_email(){ return $_SESSION['pg_user'] ?? ''; }
function pg_current_user(){ return pg_logged_in() ? pg_user_find(pg_current_email()) : null; }
function pg_current_role(){ $u = pg_current_user(); return $u['role'] ?? ''; }
function pg_is_owner(){ return pg_current_role() === 'owner'; }

/* Bereiche, die ein Editor (optional eingeschränkt) bearbeiten darf */
function pg_areas_map(){
  return [
    'studio'      => 'Studio & Startseite',
    'team'        => 'Team',
    'games'       => 'Spiele',
    'patchnotes'  => 'Devlog & Patch Notes',
    'legal'       => 'Rechtliches',
    'subscribers' => 'Newsletter-Abos',
    'mail'        => 'E-Mail-Postfach',
  ];
}
function pg_user_areas($u){
  if (($u['role'] ?? '') === 'owner') return array_keys(pg_areas_map());
  // Kein "areas"-Eintrag = voller Editor-Zugriff (Standard)
  if (!isset($u['areas']) || !is_array($u['areas'])) return array_keys(pg_areas_map());
  return array_values(array_intersect($u['areas'], array_keys(pg_areas_map())));
}
function pg_can($area){
  $u = pg_current_user();
  if (!$u) return false;
  if (($u['role'] ?? '') === 'owner') return true;
  return in_array($area, pg_user_areas($u), true);
}

/* ---------------- Einladungen ---------------- */
function pg_invites_load(){ $i = pg_load_json(PG_INVITES_FILE); return is_array($i) ? $i : []; }
function pg_invites_save($i){ return pg_save_json(PG_INVITES_FILE, array_values($i)); }
function pg_invite_create($email, $role = 'editor'){
  $token = bin2hex(random_bytes(24));
  $inv = array_filter(pg_invites_load(), fn($x) => strcasecmp($x['email'] ?? '', $email) !== 0);
  $inv[] = ['email'=>$email, 'role'=>$role, 'token'=>$token, 'created'=>date('c'), 'expires'=>time() + 7 * 86400];
  pg_invites_save($inv);
  return $token;
}
function pg_invite_find($token){
  if (!$token) return null;
  foreach (pg_invites_load() as $x) {
    if (isset($x['token']) && hash_equals($x['token'], (string)$token)) {
      return (($x['expires'] ?? 0) >= time()) ? $x : null;
    }
  }
  return null;
}
function pg_invite_delete($token){
  pg_invites_save(array_filter(pg_invites_load(), fn($x) => !hash_equals($x['token'] ?? '', (string)$token)));
}

function pg_base_url(){
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
  $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')), '/');
  if ($dir === '.' || $dir === '') $dir = '';            // kein dangling Host (z. B. CLI)
  return $scheme . '://' . $host . $dir;
}
// Absolute URL zum Seiten-Wurzelverzeichnis (eine Ebene über /admin)
function pg_site_url(){
  $base = pg_base_url();
  // /admin abschneiden -> Seitenwurzel
  return preg_replace('#/admin/?$#', '', $base) ?: $base;
}

/* ---------------- Gebrandete E-Mails ---------------- */
// Liefert die absolute URL des hochgeladenen Studio-Logos – oder '' (dann Wortmarke)
function pg_mail_logo_url(){
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $logo = trim((string)($s['logo'] ?? ''));
  if ($logo === '') return '';
  if (preg_match('#^https?://#i', $logo)) return $logo;
  return pg_site_url() . '/' . ltrim($logo, '/');
}
// Kopfzeile der Mail: echtes Logo (falls hochgeladen), sonst die Wortmarke
function pg_mail_logo_header(){
  $logo = pg_mail_logo_url();
  if ($logo !== '') {
    return '<img src="' . pg_h($logo) . '" alt="PLANIGAMES" height="40" style="height:40px;width:auto;max-width:240px;display:block;border:0;outline:none">';
  }
  return '<div style="font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:15px;color:#fff">'
    . '<span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#e6a015,#ff7d1a);border-radius:2px;transform:rotate(45deg)"></span>'
    . '&nbsp;&nbsp;PLANI<span style="color:#ff8a2b">GAMES</span></div>';
}
/* Gebrandeter Rahmen für alle E-Mails (dunkel, Orange-Akzent, mit Logo).
   $body = fertiges HTML für den Inhaltsbereich. */
function pg_mail_shell($body, $preview = ''){
  $pre = $preview ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . pg_h($preview) . '</div>' : '';
  $year = date('Y');
  return '<!doctype html><html><body style="margin:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#ececf0">'
    . $pre
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050505;padding:32px 16px"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#111114;border:1px solid rgba(255,255,255,.1);border-radius:18px;overflow:hidden">'
    . '<tr><td style="height:4px;background:linear-gradient(110deg,#e6a015,#ff7d1a 70%,#ff9d4d)"></td></tr>'
    . '<tr><td style="padding:30px 34px 0">' . pg_mail_logo_header() . '</td></tr>'
    . '<tr><td style="padding:8px 34px 6px">' . $body . '</td></tr>'
    . '<tr><td style="padding:18px 34px 30px"><p style="color:#6f6f7a;font-size:12px;margin:0;border-top:1px solid rgba(255,255,255,.08);padding-top:16px">'
    . 'PLANIGAMES · Indie Game Studio · © ' . $year . '</p></td></tr>'
    . '</table></td></tr></table></body></html>';
}
// Akzent-Button als Tabelle (mailclient-sicher)
function pg_mail_button($label, $url){
  return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0"><tr><td style="border-radius:999px;background:linear-gradient(110deg,#ff7d1a,#ff9d4d)">'
    . '<a href="' . pg_h($url) . '" style="display:inline-block;color:#1a0d00;text-decoration:none;font-weight:700;padding:14px 30px;border-radius:999px">' . pg_h($label) . '</a>'
    . '</td></tr></table>';
}

/* Gebrandete HTML-Einladungsmail */
function pg_send_invite_mail($to, $link, $roleLabel, $inviter = ''){
  $html = pg_invite_html($link, $roleLabel, $inviter);
  return pg_send_mail($to, 'Einladung ins PLANIGAMES-Dashboard', $html);
}

function pg_invite_html($link, $roleLabel, $inviter = ''){
  $L = pg_h($link);
  $who = $inviter ? (' von ' . pg_h($inviter)) : '';
  $body = '<h1 style="margin:14px 0 0;font-size:24px;color:#fff">Du bist eingeladen' . $who . ' 🎮</h1>'
    . '<p style="color:#b6b6c0;line-height:1.6;margin:14px 0 0">Du wurdest eingeladen, am <b>PLANIGAMES</b>-Dashboard mitzuarbeiten – als <b>' . pg_h($roleLabel) . '</b>. '
    . 'Klicke auf den Button, um dein Konto zu erstellen und ein Passwort zu vergeben.</p>'
    . '<div style="padding:22px 0 4px">' . pg_mail_button('Konto erstellen →', $link) . '</div>'
    . '<p style="color:#7b7b86;font-size:12px;line-height:1.6;margin:6px 0 0">Funktioniert der Button nicht? Kopiere diesen Link:<br>'
    . '<span style="color:#ff8a2b;word-break:break-all">' . $L . '</span></p>'
    . '<p style="color:#7b7b86;font-size:12px;line-height:1.6;margin:14px 0 0">Der Link ist 7 Tage gültig. Du kennst PLANIGAMES nicht? Dann ignoriere diese E-Mail einfach.</p>';
  return pg_mail_shell($body, 'Deine Einladung ins PLANIGAMES-Dashboard');
}

/* Persistentes App-Geheimnis (für Unsubscribe-Tokens etc.) – einmalig erzeugt. */
function pg_app_secret(){
  static $sec = null;
  if ($sec !== null) return $sec;
  $file = PG_DATA_DIR . '/secret.php';
  if (is_file($file)) {
    $val = include $file;
    if (is_string($val) && strlen($val) >= 32) return $sec = $val;
  }
  $sec = bin2hex(random_bytes(32));
  @file_put_contents($file, "<?php return '" . $sec . "';\n", LOCK_EX);
  return $sec;
}
// Abmelde-Token für eine E-Mail (stabil pro Adresse)
function pg_unsub_token($email){
  return hash_hmac('sha256', strtolower(trim($email)), pg_app_secret());
}
function pg_unsub_link($email){
  return pg_site_url() . '/subscribe.php?action=unsubscribe&e=' . rawurlencode($email) . '&t=' . pg_unsub_token($email);
}

/* =================================================================
   E-MAIL-ENGINE — Versand (SMTP/mail) & Empfang (IMAP)
   Konfiguration liegt in data/mail.json (NICHT im Git, enthält Passwort).
   ================================================================= */
const PG_MAIL_FILE = __DIR__ . '/../data/mail.json';

function pg_mail_config(){
  $c = pg_load_json(PG_MAIL_FILE);
  $c = is_array($c) ? $c : [];
  return $c + [
    'from_name'  => 'PLANIGAMES',
    'from_email' => '',
    // SMTP (Versand)
    'smtp_host'  => '', 'smtp_port' => 587, 'smtp_secure' => 'tls',
    'smtp_user'  => '', 'smtp_pass' => '',
    // IMAP (Empfang)
    'imap_host'  => '', 'imap_port' => 993, 'imap_secure' => 'ssl',
    'imap_user'  => '', 'imap_pass' => '',
    // Signatur (gebrandeter Block unter Einzelmails)
    'sig_enabled' => true,
    'sig_name'    => '',
    'sig_role'    => '',
    'sig_phone'   => '',
    'signature'   => '',   // optionale Schlusszeile (z. B. "Bis bald im Chaos")
  ];
}

/* Gebrandete E-Mail-Signatur aus der Mail-Config + Studio-Kontaktdaten.
   Erscheint unter persönlichen Mails (Verfassen / Antworten), nicht im Newsletter. */
function pg_mail_signature(){
  $c = pg_mail_config();
  if (($c['sig_enabled'] ?? true) === false) return '';
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $name    = trim((string)($c['sig_name'] ?? ''));
  $role    = trim((string)($c['sig_role'] ?? ''));
  $phone   = trim((string)($c['sig_phone'] ?? ''));
  $closing = trim((string)($c['signature'] ?? ''));
  $email   = trim((string)($s['email'] ?? ''));
  $siteUrl = pg_site_url();
  $siteTxt = preg_replace('#^https?://#', '', $siteUrl);
  $socials = array_filter(is_array($s['socials'] ?? null) ? $s['socials'] : [],
             fn($x) => !empty($x['url']) && $x['url'] !== '#' && !empty($x['label']));

  if ($name === '' && $role === '' && $closing === '' && $email === '') return '';

  $out = '<div style="margin-top:28px">';
  if ($closing !== '') $out .= '<div style="color:#c7c7d1;font-size:14px;line-height:1.6;margin-bottom:16px">' . nl2br(pg_h($closing)) . '</div>';
  $out .= '<div style="width:42px;height:2px;background:linear-gradient(110deg,#e6a015,#ff7d1a);margin:0 0 14px"></div>';
  if ($name !== '') $out .= '<div style="color:#ffffff;font-weight:700;font-size:15px">' . pg_h($name) . '</div>';
  if ($role !== '') $out .= '<div style="color:#9a9aa6;font-size:13px;margin-top:2px">' . pg_h($role) . '</div>';

  $contact = [];
  if ($email)   $contact[] = '<a href="mailto:' . pg_h($email) . '" style="color:#ff8a2b;text-decoration:none">' . pg_h($email) . '</a>';
  if ($siteTxt) $contact[] = '<a href="' . pg_h($siteUrl) . '" style="color:#ff8a2b;text-decoration:none">' . pg_h($siteTxt) . '</a>';
  if ($phone)   $contact[] = '<span style="color:#9a9aa6">' . pg_h($phone) . '</span>';
  if ($contact) $out .= '<div style="font-size:13px;margin-top:11px">' . implode(' &nbsp;·&nbsp; ', $contact) . '</div>';

  $soc = [];
  foreach ($socials as $x) $soc[] = '<a href="' . pg_h($x['url']) . '" style="color:#9a9aa6;text-decoration:none">' . pg_h($x['label']) . '</a>';
  if ($soc) $out .= '<div style="font-size:12px;margin-top:9px;color:#6f6f7a">' . implode(' &nbsp;&nbsp; ', $soc) . '</div>';

  $out .= '</div>';
  return $out;
}
function pg_mail_config_save(array $c){
  // Bestehende Passwörter behalten, wenn das Formular sie leer lässt
  $old = pg_mail_config();
  foreach (['smtp_pass','imap_pass'] as $k) {
    if (($c[$k] ?? '') === '') $c[$k] = $old[$k] ?? '';
  }
  return pg_save_json(PG_MAIL_FILE, $c);
}
function pg_mail_configured_send(){ $c = pg_mail_config(); return $c['smtp_host'] !== '' && $c['smtp_user'] !== ''; }
function pg_mail_configured_recv(){ $c = pg_mail_config(); return $c['imap_host'] !== '' && $c['imap_user'] !== ''; }

// Absender bestimmen (From-Email aus Mail-Config, sonst Studio-Kontakt, sonst no-reply@host)
function pg_mail_from(){
  $c = pg_mail_config();
  $email = trim($c['from_email'] ?: $c['smtp_user']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $s = pg_load_json(PG_DATA_DIR . '/studio.json');
    $email = trim((string)($s['email'] ?? ''));
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.de');
    $email = 'no-reply@' . $host;
  }
  return [trim($c['from_name']) ?: 'PLANIGAMES', $email];
}

/* Zentrale Versandfunktion: nutzt SMTP, wenn konfiguriert, sonst mail().
   $html = vollständiges HTML (z. B. via pg_mail_shell). Gibt true/false zurück. */
function pg_send_mail($to, $subject, $html, $replyTo = ''){
  [$fromName, $fromEmail] = pg_mail_from();
  if (pg_mail_configured_send()) {
    return pg_smtp_send($to, $subject, $html, $fromName, $fromEmail, $replyTo);
  }
  // Fallback: PHP mail()
  $fn = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
  $headers = "From: {$fn} <{$fromEmail}>\r\n"
    . ($replyTo ? "Reply-To: {$replyTo}\r\n" : "Reply-To: {$fromEmail}\r\n")
    . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  return @mail($to, $subj, $html, $headers);
}

/* Minimaler SMTP-Client (fsockopen) – ohne Abhängigkeiten, für All-Inkl geeignet. */
function pg_smtp_send($to, $subject, $html, $fromName, $fromEmail, $replyTo = ''){
  $c = pg_mail_config();
  $host = $c['smtp_host']; $port = (int)$c['smtp_port'] ?: 587;
  $secure = $c['smtp_secure']; // 'ssl' | 'tls' | ''
  $transport = $secure === 'ssl' ? 'ssl://' : '';
  $errno = 0; $errstr = '';
  $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT, stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
  if (!$fp) { error_log("SMTP connect failed: $errstr ($errno)"); return false; }
  stream_set_timeout($fp, 20);

  $read = function() use ($fp){
    $data = '';
    while (($line = fgets($fp, 512)) !== false) {
      $data .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
  };
  $cmd = function($c) use ($fp, $read){ fwrite($fp, $c . "\r\n"); return $read(); };
  $code = fn($r) => (int)substr(trim($r), 0, 3);

  $ok = true;
  $read(); // Greeting
  $ehlo = $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.de'));
  if ($secure === 'tls') {
    if ($code($cmd('STARTTLS')) !== 220) { fclose($fp); return false; }
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) { fclose($fp); return false; }
    $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.de'));
  }
  // AUTH LOGIN
  if ($c['smtp_user'] !== '') {
    if ($code($cmd('AUTH LOGIN')) !== 334) { fclose($fp); return false; }
    if ($code($cmd(base64_encode($c['smtp_user']))) !== 334) { fclose($fp); return false; }
    if ($code($cmd(base64_encode($c['smtp_pass']))) !== 235) { fclose($fp); return false; }
  }
  if ($code($cmd('MAIL FROM:<' . $fromEmail . '>')) !== 250) $ok = false;
  if ($code($cmd('RCPT TO:<' . $to . '>')) >= 400) $ok = false;
  if ($code($cmd('DATA')) !== 354) $ok = false;

  $fn = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $date = date('r');
  $msgid = '<' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.\-]/i','',$_SERVER['HTTP_HOST'] ?? 'planigames.de') . '>';
  $head = "Date: $date\r\nFrom: $fn <$fromEmail>\r\n"
    . ($replyTo ? "Reply-To: $replyTo\r\n" : '')
    . "To: <$to>\r\nSubject: $subj\r\nMessage-ID: $msgid\r\n"
    . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
  $body = chunk_split(base64_encode($html));
  // Punkt-Stuffing nicht nötig bei base64
  $r = $cmd($head . $body . "\r\n.");
  if ($code($r) !== 250) $ok = false;
  $cmd('QUIT');
  fclose($fp);
  return $ok;
}

/* ---- IMAP-Empfang (PHP imap-Extension; auf All-Inkl verfügbar) ---- */
function pg_imap_available(){ return function_exists('imap_open'); }
function pg_imap_mailbox($folder = 'INBOX'){
  $c = pg_mail_config();
  $sec = $c['imap_secure'] === 'ssl' ? '/imap/ssl/novalidate-cert' : ($c['imap_secure'] === 'tls' ? '/imap/tls/novalidate-cert' : '/imap/notls');
  return '{' . $c['imap_host'] . ':' . ((int)$c['imap_port'] ?: 993) . $sec . '}' . $folder;
}
function pg_imap_connect($folder = 'INBOX'){
  if (!pg_imap_available() || !pg_mail_configured_recv()) return null;
  $c = pg_mail_config();
  $mbox = @imap_open(pg_imap_mailbox($folder), $c['imap_user'], $c['imap_pass'], 0, 1);
  return $mbox ?: null;
}
// Liste der letzten $limit Mails als einfache Arrays
function pg_imap_list($mbox, $limit = 30, $offset = 0){
  $total = imap_num_msg($mbox);
  if ($total < 1) return ['total'=>0, 'items'=>[]];
  $end = $total - $offset;
  $start = max(1, $end - $limit + 1);
  $items = [];
  for ($i = $end; $i >= $start; $i--) {
    $o = imap_headerinfo($mbox, $i);
    if (!$o) continue;
    $from = isset($o->from[0]) ? $o->from[0] : null;
    $items[] = [
      'num'     => $i,
      'uid'     => imap_uid($mbox, $i),
      'subject' => pg_imap_decode($o->subject ?? '(kein Betreff)'),
      'from'    => $from ? pg_imap_decode(($from->personal ?? '') ?: ($from->mailbox . '@' . $from->host)) : '',
      'from_email' => $from ? ($from->mailbox . '@' . $from->host) : '',
      'date'    => isset($o->udate) ? (int)$o->udate : 0,
      'seen'    => isset($o->Unseen) ? trim($o->Unseen) === '' : true,
    ];
  }
  return ['total'=>$total, 'items'=>$items];
}
function pg_imap_decode($s){
  $s = (string)$s; $out = '';
  foreach (imap_mime_header_decode($s) as $p) {
    $cs = strtoupper($p->charset);
    $out .= ($cs && $cs !== 'DEFAULT' && $cs !== 'UTF-8') ? @mb_convert_encoding($p->text, 'UTF-8', $cs) : $p->text;
  }
  return $out;
}
// Volltext einer Mail (bevorzugt HTML, sonst Text) anhand der UID
function pg_imap_body($mbox, $uid){
  $structure = imap_fetchstructure($mbox, $uid, FT_UID);
  $html = pg_imap_part($mbox, $uid, $structure, 'HTML');
  if ($html !== '') return ['html'=>true, 'body'=>$html];
  $text = pg_imap_part($mbox, $uid, $structure, 'PLAIN');
  return ['html'=>false, 'body'=>$text];
}
function pg_imap_part($mbox, $uid, $structure, $want, $prefix = ''){
  $decode = function($data, $enc){
    if ($enc == 3) return base64_decode($data);
    if ($enc == 4) return quoted_printable_decode($data);
    return $data;
  };
  $charset = function($part){
    if (!empty($part->parameters)) foreach ($part->parameters as $p) if (strtoupper($p->attribute) === 'CHARSET') return $p->value;
    if (!empty($part->dparameters)) foreach ($part->dparameters as $p) if (strtoupper($p->attribute) === 'CHARSET') return $p->value;
    return 'UTF-8';
  };
  $subtype = strtoupper($structure->subtype ?? '');
  if (empty($structure->parts)) {
    if (($structure->type ?? 0) == 0 && $subtype === $want) {
      $raw = imap_fetchbody($mbox, $uid, $prefix ?: '1', FT_UID);
      $data = $decode($raw, $structure->encoding ?? 0);
      $cs = strtoupper($charset($structure));
      return ($cs && $cs !== 'UTF-8') ? @mb_convert_encoding($data, 'UTF-8', $cs) : $data;
    }
    return '';
  }
  foreach ($structure->parts as $i => $part) {
    $no = $prefix === '' ? (string)($i + 1) : $prefix . '.' . ($i + 1);
    if (!empty($part->parts)) {
      $r = pg_imap_part($mbox, $uid, $part, $want, $no);
      if ($r !== '') return $r;
    } elseif (($part->type ?? 0) == 0 && strtoupper($part->subtype ?? '') === $want) {
      $raw = imap_fetchbody($mbox, $uid, $no, FT_UID);
      $data = $decode($raw, $part->encoding ?? 0);
      $cs = strtoupper($charset($part));
      return ($cs && $cs !== 'UTF-8') ? @mb_convert_encoding($data, 'UTF-8', $cs) : $data;
    }
  }
  return '';
}

/* =================== VIEW-HELFER (von index.php & mail.php genutzt) =================== */
function pg_view_head($title){
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1">'
     . '<meta name="robots" content="noindex"><title>' . pg_h($title) . ' · PLANIGAMES Admin</title>'
     . '<link rel="stylesheet" href="assets/admin.css?v=4"></head><body>';
}
function pg_view_foot(){
  echo '<script src="assets/admin.js?v=4"></script></body></html>';
}
function pg_view_topbar($SCHEMA, $active){
  echo '<header class="topbar"><a class="tb-brand" href="index.php"><span class="diamond"></span> PLANI<span class="grad">GAMES</span></a>';
  echo '<nav class="tb-nav">';
  foreach ($SCHEMA as $key => $coll) {
    if (!pg_can($key)) continue;
    $cls = $key === $active ? ' class="on"' : '';
    echo '<a' . $cls . ' href="index.php?collection=' . pg_h($key) . '">' . pg_h($coll['label']) . '</a>';
  }
  echo '<a' . ($active === 'media' ? ' class="on"' : '') . ' href="index.php?view=media">Medien</a>';
  if (pg_can('subscribers')) echo '<a href="index.php?view=subscribers">Abos</a>';
  if (pg_can('mail')) echo '<a' . ($active === 'mail' ? ' class="on"' : '') . ' href="mail.php">✉ Mails</a>';
  if (pg_is_owner()) echo '<a href="index.php?view=users">Zugänge</a>';
  echo '</nav>';
  echo '<span class="tb-right">';
  if (pg_logged_in()) echo '<span class="tb-user" title="' . pg_h(pg_current_email()) . '">' . pg_h(pg_current_email()) . '</span>';
  echo '<a href="../index.html" target="_blank">↗ Seite</a><a href="index.php?action=logout">Abmelden</a></span>';
  echo '</header>';
}

/* ---------------- Mehrsprachigkeit ---------------- */
// Pfad zur Sprachvariante: 'de' = Original, 'en' = …​.en.json
function pg_lang_file($file, $lang){
  return $lang === 'en' ? preg_replace('/\.json$/', '.en.json', $file) : $file;
}

// HTTP-GET (curl bevorzugt, sonst file_get_contents)
function pg_http_get($url){
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>true,
      CURLOPT_USERAGENT=>'PLANIGAMES-CMS', CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $r = curl_exec($ch); curl_close($ch);
    return $r ?: null;
  }
  $ctx = stream_context_create(['http'=>['timeout'=>12, 'header'=>"User-Agent: PLANIGAMES-CMS\r\n"]]);
  $r = @file_get_contents($url, false, $ctx);
  return $r ?: null;
}

// Kontakt-E-Mail erhöht das kostenlose MyMemory-Kontingent (anonym ~5k, mit Mail ~50k Wörter/Tag)
function pg_translate_contact(){
  static $e = null;
  if ($e === null) { $s = pg_load_json(PG_DATA_DIR . '/studio.json'); $e = $s['email'] ?? ''; }
  return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}

// Einzeltext via MyMemory (kein API-Key nötig) übersetzen; bei Fehlern Original behalten
function pg_translate_text($text, $from = 'de', $to = 'en'){
  $text = (string)$text;
  if (trim($text) === '') return $text;
  $email = pg_translate_contact();
  $out = '';
  foreach (pg_chunk_text($text, 480) as $part) {
    if (trim($part) === '') { $out .= $part; continue; }
    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($part) . '&langpair=' . $from . '|' . $to
         . ($email ? '&de=' . rawurlencode($email) : '');
    $tr = null;
    for ($try = 0; $try < 2 && $tr === null; $try++) {
      if ($try) usleep(900000); // bei Fehler kurz warten und erneut versuchen
      $res = pg_http_get($url);
      $j = $res ? json_decode($res, true) : null;
      $cand = $j['responseData']['translatedText'] ?? null;
      if ($cand && (int)($j['responseStatus'] ?? 0) === 200) $tr = html_entity_decode($cand, ENT_QUOTES, 'UTF-8');
    }
    $out .= $tr !== null ? $tr : $part; // Fallback: Original behalten
    usleep(120000); // freundlich zum kostenlosen Dienst
  }
  // Markdown reparieren: Leerzeichen direkt innerhalb von **fett** / *kursiv* entfernen
  $out = preg_replace_callback('/\*\*\s*(.+?)\s*\*\*/s', fn($m) => '**' . trim($m[1]) . '**', $out);
  $out = preg_replace_callback('/(?<!\*)\*(?!\*)\s*(.+?)\s*(?<!\*)\*(?!\*)/s', fn($m) => '*' . trim($m[1]) . '*', $out);
  return $out;
}

// Lange Texte an Absätzen/Sätzen in <= $max-Stücke teilen
function pg_chunk_text($text, $max = 480){
  if (mb_strlen($text) <= $max) return [$text];
  $chunks = [];
  foreach (preg_split('/(\n{2,})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $para) {
    if (mb_strlen($para) <= $max) { $chunks[] = $para; continue; }
    $buf = '';
    foreach (preg_split('/(?<=[.!?]) /', $para) as $sent) {
      if (mb_strlen($buf . ' ' . $sent) > $max && $buf !== '') { $chunks[] = $buf; $buf = $sent; }
      else { $buf = $buf === '' ? $sent : $buf . ' ' . $sent; }
    }
    if ($buf !== '') $chunks[] = $buf;
  }
  return $chunks;
}

// Komplette Datenstruktur gemäß Schema übersetzen (nur Textfelder)
function pg_translate_data($fields, $data){
  $out = is_array($data) ? $data : [];
  foreach ($fields as $f) {
    if (!array_key_exists($f['name'], $out)) continue;
    $out[$f['name']] = pg_translate_value($f, $out[$f['name']]);
  }
  return $out;
}
function pg_translate_value($f, $val){
  $w = $f['widget'];
  if (in_array($w, ['select','date','image','file','color','number','boolean'], true)) return $val;
  if (!empty($f['slug'])) return $val;
  if (preg_match('/url$/i', $f['name']) || in_array($f['name'], ['email','accent','accent2','game','slug','icon','emoji'], true)) return $val;
  switch ($w) {
    case 'string': case 'text': case 'markdown':
      return pg_translate_text((string)$val);
    case 'object':
      return pg_translate_data($f['fields'], is_array($val) ? $val : []);
    case 'list':
      if (!is_array($val)) return $val;
      if (isset($f['field'])) {
        if (in_array($f['field']['widget'], ['image','file'], true)) return $val;
        return array_map(fn($v) => pg_translate_text((string)$v), $val);
      }
      return array_map(fn($it) => pg_translate_data($f['fields'], is_array($it) ? $it : []), $val);
    case 'blocks':
      if (!is_array($val)) return $val;
      return array_map(function($it) use ($f){
        $type = $it['type'] ?? '';
        if (!isset($f['types'][$type])) return $it;
        return ['type'=>$type] + pg_translate_data($f['types'][$type]['fields'], $it);
      }, $val);
  }
  return $val;
}

/* ---------------- CSRF ---------------- */
function pg_csrf(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(18)); return $_SESSION['csrf']; }
function pg_csrf_check(){
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Ungültiges Sicherheits-Token. Bitte Seite neu laden.'); }
}

/* ---------------- JSON I/O ---------------- */
function pg_load_json($file){
  if (!is_file($file)) return [];
  $j = json_decode(file_get_contents($file), true);
  return is_array($j) ? $j : [];
}
function pg_save_json($file, $data){
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $tmp  = $file . '.tmp';
  if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
  return rename($tmp, $file);
}

function pg_slugify($s){
  $s = mb_strtolower(trim((string)$s), 'UTF-8');
  $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','&'=>'-und-']);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  return trim($s, '-');
}

/* ---------------- Datei-Upload ---------------- */
function pg_handle_upload(){
  header('Content-Type: application/json');
  if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload fehlgeschlagen (evtl. zu groß).']); return;
  }
  $f = $_FILES['file'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, PG_UPLOAD_EXT, true)) { echo json_encode(['error' => 'Dateityp nicht erlaubt: .' . $ext]); return; }
  $base = pg_slugify(pathinfo($f['name'], PATHINFO_FILENAME)) ?: 'datei';
  if (!is_dir(PG_MEDIA_DIR)) @mkdir(PG_MEDIA_DIR, 0775, true);
  $name = $base . '.' . $ext;
  $i = 1;
  while (is_file(PG_MEDIA_DIR . '/' . $name)) { $name = $base . '-' . (++$i) . '.' . $ext; }
  if (!move_uploaded_file($f['tmp_name'], PG_MEDIA_DIR . '/' . $name)) {
    echo json_encode(['error' => 'Konnte Datei nicht speichern (Schreibrechte am Ordner media/ prüfen).']); return;
  }
  echo json_encode(['path' => '/media/' . $name]);
}

/* =====================================================================
   FORMULAR-RENDERING (aus dem Schema)
   $prefix  = Name-Präfix für die Inputs, z. B. "d[games][0]"
   $pathKey = Schema-Pfad ohne Indizes, z. B. "games" (für eindeutige Tokens)
   ===================================================================== */

function pg_render_fields($fields, $values, $prefix, $pathKey){
  $out = '';
  foreach ($fields as $f) {
    $val = $values[$f['name']] ?? ($f['default'] ?? null);
    $out .= pg_render_field($f, $val, $prefix . '[' . $f['name'] . ']', $pathKey . '.' . $f['name']);
  }
  return $out;
}

function pg_render_field($f, $val, $name, $pathKey){
  $w = $f['widget'];
  $label = pg_h($f['label'] ?? $f['name']);
  $hint = !empty($f['hint']) ? '<p class="hint">' . pg_h($f['hint']) . '</p>' : '';

  // Listen, Blöcke & Objekt-Gruppen rendern ihre eigene Hülle
  if ($w === 'list')   return pg_render_list($f, is_array($val) ? $val : [], $name, $pathKey);
  if ($w === 'blocks') return pg_render_blocks($f, is_array($val) ? $val : [], $name, $pathKey);
  if ($w === 'object') {
    $inner = pg_render_fields($f['fields'], is_array($val) ? $val : [], $name, $pathKey);
    return '<div class="field objectfield"><label class="flabel">' . $label . '</label>'
         . $hint . '<div class="object-body">' . $inner . '</div></div>';
  }

  $field = '';
  switch ($w) {
    case 'text':
      $field = '<textarea name="' . pg_h($name) . '" rows="3">' . pg_h($val) . '</textarea>';
      break;
    case 'markdown':
      $field = '<textarea name="' . pg_h($name) . '" rows="8" class="md">' . pg_h($val) . '</textarea>'
             . '<p class="hint">Markdown erlaubt: **fett**, ## Überschrift, - Liste, [Link](url).</p>';
      break;
    case 'number':
      $field = '<input type="number" name="' . pg_h($name) . '" value="' . pg_h($val) . '">';
      break;
    case 'boolean':
      $on = !empty($val) && $val !== 'false' ? ' checked' : '';
      $field = '<input type="hidden" name="' . pg_h($name) . '" value="0">'
             . '<label class="switch"><input type="checkbox" name="' . pg_h($name) . '" value="1"' . $on . '><span>An / Aus</span></label>';
      break;
    case 'select':
      $opts = '';
      foreach ($f['options'] as $k => $lbl) {
        $opts .= '<option value="' . pg_h($k) . '"' . ((string)$val === (string)$k ? ' selected' : '') . '>' . pg_h($lbl) . '</option>';
      }
      $field = '<select name="' . pg_h($name) . '">' . $opts . '</select>';
      break;
    case 'color':
      $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val) ? $val : ($f['default'] ?? '#ff7d1a');
      $field = '<span class="colorrow"><input type="color" value="' . pg_h($hex) . '" oninput="this.nextElementSibling.value=this.value">'
             . '<input type="text" name="' . pg_h($name) . '" value="' . pg_h($val) . '" class="colorhex" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value"></span>';
      break;
    case 'date':
      $field = '<input type="date" name="' . pg_h($name) . '" value="' . pg_h($val) . '">';
      break;
    case 'datetime':
      $field = '<input type="datetime-local" name="' . pg_h($name) . '" value="' . pg_h($val) . '">';
      break;
    case 'image':
    case 'file':
      $accept = $w === 'image' ? 'image/*' : 'image/*,video/*';
      $prev = $val ? '<a href="' . pg_h($val) . '" target="_blank" class="media-prev">' . pg_h($val) . '</a>' : '';
      $field = '<div class="media" data-media>'
             . '<input type="text" name="' . pg_h($name) . '" value="' . pg_h($val) . '" placeholder="/media/… oder URL" data-media-input>'
             . '<label class="btn-up">Hochladen<input type="file" accept="' . $accept . '" hidden data-media-file></label>'
             . '<span class="media-status">' . $prev . '</span></div>';
      break;
    case 'string':
    default:
      $field = '<input type="text" name="' . pg_h($name) . '" value="' . pg_h($val) . '">';
  }

  return '<div class="field"><label class="flabel">' . $label . '</label>' . $field . $hint . '</div>';
}

/* ---- Liste: entweder Objektliste ("fields") oder Einzelwert-Liste ("field") ---- */
function pg_render_list($f, $items, $name, $pathKey){
  $token = '@@' . md5($pathKey) . '@@';
  $isSimple = isset($f['field']);
  $rows = '';
  foreach ($items as $i => $item) {
    $rows .= pg_render_list_item($f, $item, $name . '[' . $i . ']', $pathKey, $i, $isSimple);
  }
  // Vorlage (mit Token als Index) für neue Einträge
  $tpl = pg_render_list_item($f, $isSimple ? '' : [], $name . '[' . $token . ']', $pathKey, $token, $isSimple);

  $h = '<div class="field listfield" data-list data-token="' . pg_h($token) . '">';
  $h .= '<div class="listhead"><label class="flabel">' . pg_h($f['label']) . '</label>'
      . '<button type="button" class="btn-add" data-add>+ ' . pg_h($f['label_singular'] ?? 'Eintrag') . '</button></div>';
  if (!empty($f['hint'])) $h .= '<p class="hint">' . pg_h($f['hint']) . '</p>';
  $h .= '<div class="list-items" data-items>' . $rows . '</div>';
  $h .= '<template data-tpl>' . $tpl . '</template>';
  $h .= '</div>';
  return $h;
}

function pg_render_list_item($f, $item, $prefix, $pathKey, $idx, $isSimple){
  $summaryField = $f['summary'] ?? null;
  $inner = $isSimple
    ? pg_render_field($f['field'], is_array($item) ? '' : $item, $prefix, $pathKey)
    : pg_render_fields($f['fields'], is_array($item) ? $item : [], $prefix, $pathKey);
  $sum = '';
  if (!$isSimple && $summaryField && is_array($item) && isset($item[$summaryField])) $sum = pg_h($item[$summaryField]);
  return '<div class="item" data-item>'
       . '<div class="item-bar"><span class="grip" data-grip>⠿</span>'
       . '<span class="item-sum" data-sum>' . $sum . '</span>'
       . '<span class="item-tools">'
       . '<button type="button" data-up title="nach oben">↑</button>'
       . '<button type="button" data-down title="nach unten">↓</button>'
       . '<button type="button" data-del title="löschen">✕</button>'
       . '<button type="button" data-fold title="ein-/ausklappen">▾</button></span></div>'
       . '<div class="item-body">' . $inner . '</div></div>';
}

/* ---- Blöcke (Liste mit Typen) ---- */
function pg_render_blocks($f, $items, $name, $pathKey){
  $token = '@@' . md5($pathKey) . '@@';
  $rows = '';
  foreach ($items as $i => $item) {
    $type = $item['type'] ?? '';
    if (!isset($f['types'][$type])) continue;
    $rows .= pg_render_block_item($f, $type, $item, $name . '[' . $i . ']', $pathKey, $i);
  }
  // Eine Vorlage pro Typ
  $tpls = '';
  foreach ($f['types'] as $tname => $tdef) {
    $tpls .= '<template data-block-tpl="' . pg_h($tname) . '">'
           . pg_render_block_item($f, $tname, ['type'=>$tname], $name . '[' . $token . ']', $pathKey, $token)
           . '</template>';
  }
  // Dropdown zum Hinzufügen
  $opts = '';
  foreach ($f['types'] as $tname => $tdef) $opts .= '<option value="' . pg_h($tname) . '">' . pg_h($tdef['label']) . '</option>';

  $h = '<div class="field blocksfield" data-blocks data-token="' . pg_h($token) . '">';
  $h .= '<div class="listhead"><label class="flabel">' . pg_h($f['label']) . '</label>'
      . '<span class="addblock"><select data-block-select>' . $opts . '</select>'
      . '<button type="button" class="btn-add" data-add-block>+ Block</button></span></div>';
  $h .= '<div class="list-items" data-items>' . $rows . '</div>';
  $h .= $tpls;
  $h .= '</div>';
  return $h;
}

function pg_render_block_item($f, $type, $item, $prefix, $pathKey, $idx){
  $tdef = $f['types'][$type];
  $inner = pg_render_fields($tdef['fields'], is_array($item) ? $item : [], $prefix, $pathKey . '.' . $type);
  return '<div class="item block" data-item>'
       . '<input type="hidden" name="' . pg_h($prefix) . '[type]" value="' . pg_h($type) . '">'
       . '<div class="item-bar"><span class="grip" data-grip>⠿</span>'
       . '<span class="item-sum block-label">▣ ' . pg_h($tdef['label']) . '</span>'
       . '<span class="item-tools">'
       . '<button type="button" data-up title="nach oben">↑</button>'
       . '<button type="button" data-down title="nach unten">↓</button>'
       . '<button type="button" data-del title="löschen">✕</button>'
       . '<button type="button" data-fold title="ein-/ausklappen">▾</button></span></div>'
       . '<div class="item-body">' . $inner . '</div></div>';
}

/* =====================================================================
   NORMALISIEREN (POST -> saubere, getypte Daten gemäß Schema)
   ===================================================================== */

function pg_normalize_fields($fields, $raw){
  $raw = is_array($raw) ? $raw : [];
  $out = [];
  foreach ($fields as $f) {
    $out[$f['name']] = pg_normalize_field($f, $raw[$f['name']] ?? null, $raw);
  }
  return $out;
}

function pg_normalize_field($f, $val, $siblingRaw = []){
  switch ($f['widget']) {
    case 'boolean':
      return !empty($val) && $val !== '0' && $val !== 'false';
    case 'number':
      if ($val === null || $val === '') return $f['default'] ?? 0;
      return (0 + $val) == (int)$val ? (int)$val : (float)$val;
    case 'list':
      $items = is_array($val) ? array_values($val) : [];
      if (isset($f['field'])) { // Einzelwert-Liste
        $res = [];
        foreach ($items as $it) {
          $v = is_array($it) ? '' : trim((string)$it);
          if ($v !== '') $res[] = $v;
        }
        return $res;
      }
      $res = [];
      foreach ($items as $it) $res[] = pg_normalize_fields($f['fields'], $it);
      return $res;
    case 'blocks':
      $items = is_array($val) ? array_values($val) : [];
      $res = [];
      foreach ($items as $it) {
        $type = $it['type'] ?? '';
        if (!isset($f['types'][$type])) continue;
        $res[] = ['type' => $type] + pg_normalize_fields($f['types'][$type]['fields'], $it);
      }
      return $res;
    case 'object':
      return pg_normalize_fields($f['fields'], is_array($val) ? $val : []);
    default: // alle Text-/String-Widgets
      $s = is_array($val) ? '' : trim((string)$val);
      if (!empty($f['slug'])) {
        if ($s === '') $s = pg_slugify($siblingRaw['title'] ?? '');
        else $s = pg_slugify($s);
      }
      return $s;
  }
}
