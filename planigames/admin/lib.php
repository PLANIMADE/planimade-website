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
    'contacts'    => 'Kontakt-Anfragen',
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

/* ---------------- Ungelesen-Zähler (Badges) ---------------- */
const PG_MAIL_UNREAD_FILE = __DIR__ . '/../data/mail_unread.json';
function pg_contacts_unread(){
  $n = 0;
  foreach ((array) pg_load_json(PG_DATA_DIR . '/contacts.json') as $r) if (empty($r['read'])) $n++;
  return $n;
}
function pg_mail_unread_cached(){
  $c = pg_load_json(PG_MAIL_UNREAD_FILE);
  return is_array($c) ? max(0, (int) ($c['count'] ?? 0)) : 0;
}
function pg_mail_unread_store($count){
  pg_save_json(PG_MAIL_UNREAD_FILE, ['count' => max(0, (int) $count), 'time' => time()]);
}

/* ---------------- Schnellantwort-Vorlagen (Textbausteine) ---------------- */
const PG_TEMPLATES_FILE = __DIR__ . '/../data/templates.json';
function pg_templates_load(){
  $t = pg_load_json(PG_TEMPLATES_FILE);
  return is_array($t) ? array_values(array_filter($t, fn($x) => is_array($x))) : [];
}
function pg_templates_save($list){ return pg_save_json(PG_TEMPLATES_FILE, array_values($list)); }

/* ---------------- Globale Suche ---------------- */
// Rekursiv alle Text-/Zahlenwerte durchsuchen; Treffer mit Pfad + Ausschnitt sammeln
function pg_search_walk($node, $q, $path, &$out){
  if (is_array($node)) {
    foreach ($node as $k => $v) { if (count($out) > 200) return; pg_search_walk($v, $q, array_merge($path, [$k]), $out); }
  } elseif (is_string($node) || is_numeric($node)) {
    $s = (string) $node;
    if ($s !== '' && mb_stripos($s, $q) !== false) $out[] = ['path' => $path, 'snip' => pg_search_snippet($s, $q)];
  }
}
function pg_search_snippet($s, $q){
  $s = trim(preg_replace('/\s+/', ' ', $s));
  $pos = mb_stripos($s, $q);
  if ($pos === false) return mb_substr($s, 0, 90);
  $start = max(0, $pos - 32);
  $snip = ($start > 0 ? '…' : '') . mb_substr($s, $start, 90);
  if (mb_strlen($s) > $start + 90) $snip .= '…';
  return $snip;
}
// Lesbarer Pfad: Zahlen-Indizes als „#n", String-Schlüssel mit › getrennt
function pg_search_pathlabel($path){
  $parts = [];
  foreach ($path as $p) $parts[] = (is_int($p) || ctype_digit((string) $p)) ? '#' . ((int) $p + 1) : (string) $p;
  return implode(' › ', $parts);
}
// Treffer-Ausschnitt mit hervorgehobenem Suchbegriff (HTML-sicher)
function pg_search_highlight($snip, $q){
  return preg_replace('/(' . preg_quote($q, '/') . ')/iu', '<mark>$1</mark>', pg_h($snip));
}

/* ---------------- Kontakt-Status (offen / beantwortet / erledigt) ---------------- */
function pg_contact_statuses(){
  return ['offen' => 'Offen', 'beantwortet' => 'Beantwortet', 'erledigt' => 'Erledigt'];
}
function pg_contact_status($r){
  $s = $r['status'] ?? '';
  return array_key_exists($s, pg_contact_statuses()) ? $s : 'offen';
}

/* ---------------- Login-Schutz (Brute-Force-Bremse) ---------------- */
const PG_LOGIN_FILE = __DIR__ . '/../data/login_attempts.json';
const PG_LOGIN_MAX  = 6;      // erlaubte Fehlversuche
const PG_LOGIN_WIN  = 900;    // Zeitfenster / Sperre in Sekunden (15 Min)
function pg_client_ip(){ return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }
function pg_ip_hash(){ return substr(hash_hmac('sha256', pg_client_ip(), pg_app_secret()), 0, 16); }
// Gibt verbleibende Sperrzeit in Sekunden zurück (0 = erlaubt)
function pg_login_locked(){
  $all = pg_load_json(PG_LOGIN_FILE); $now = time(); $ipk = pg_ip_hash();
  $fails = array_filter((array) ($all[$ipk] ?? []), fn($t) => $t > $now - PG_LOGIN_WIN);
  if (count($fails) >= PG_LOGIN_MAX) return PG_LOGIN_WIN - ($now - min($fails));
  return 0;
}
function pg_login_record_fail(){
  $all = pg_load_json(PG_LOGIN_FILE); if (!is_array($all)) $all = [];
  $now = time(); $ipk = pg_ip_hash();
  $all[$ipk][] = $now;
  foreach ($all as $k => $ts) {                       // Alteinträge aufräumen
    $all[$k] = array_values(array_filter((array) $ts, fn($t) => $t > $now - 3600));
    if (!$all[$k]) unset($all[$k]);
  }
  pg_save_json(PG_LOGIN_FILE, $all);
}
function pg_login_clear(){
  $all = pg_load_json(PG_LOGIN_FILE); $ipk = pg_ip_hash();
  if (is_array($all) && isset($all[$ipk])) { unset($all[$ipk]); pg_save_json(PG_LOGIN_FILE, $all); }
}

/* ---------------- 2-Faktor-Authentifizierung (TOTP, RFC 6238) ---------------- */
const PG_B32_ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
function pg_base32_encode($data){
  $out = ''; $bits = 0; $val = 0;
  for ($i = 0, $n = strlen($data); $i < $n; $i++) {
    $val = ($val << 8) | ord($data[$i]); $bits += 8;
    while ($bits >= 5) { $out .= PG_B32_ALPHA[($val >> ($bits - 5)) & 31]; $bits -= 5; }
  }
  if ($bits > 0) $out .= PG_B32_ALPHA[($val << (5 - $bits)) & 31];
  return $out;
}
function pg_base32_decode($b32){
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string) $b32));
  $out = ''; $bits = 0; $val = 0;
  for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
    $val = ($val << 5) | strpos(PG_B32_ALPHA, $b32[$i]); $bits += 5;
    if ($bits >= 8) { $out .= chr(($val >> ($bits - 8)) & 255); $bits -= 8; }
  }
  return $out;
}
function pg_totp_secret(){ return pg_base32_encode(random_bytes(20)); }
function pg_totp_code($secret, $slice = null){
  if ($slice === null) $slice = (int) floor(time() / 30);
  $key = pg_base32_decode($secret);
  $bin = "\0\0\0\0" . pack('N', $slice);            // 8-Byte Big-Endian Counter
  $hash = hash_hmac('sha1', $bin, $key, true);
  $off = ord($hash[19]) & 0xf;
  $num = ((ord($hash[$off]) & 0x7f) << 24) | ((ord($hash[$off + 1]) & 0xff) << 16)
       | ((ord($hash[$off + 2]) & 0xff) << 8) | (ord($hash[$off + 3]) & 0xff);
  return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
}
function pg_totp_verify($secret, $code, $window = 1){
  $code = preg_replace('/\D/', '', (string) $code);
  if (strlen($code) !== 6 || $secret === '') return false;
  $slice = (int) floor(time() / 30);
  for ($i = -$window; $i <= $window; $i++) if (hash_equals(pg_totp_code($secret, $slice + $i), $code)) return true;
  return false;
}
function pg_otpauth_uri($secret, $account, $issuer = 'PLANIGAMES'){
  return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
       . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
// Einmal-Wiederherstellungscodes (Klartext zum Anzeigen; gespeichert wird der Hash)
function pg_backup_codes_gen($n = 8){
  $c = []; for ($i = 0; $i < $n; $i++) $c[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
  return $c;
}
function pg_backup_codes_hash($codes){ return array_map(fn($c) => password_hash(preg_replace('/\D/', '', $c), PASSWORD_DEFAULT), $codes); }
// Prüft Backup-Code gegen Hash-Liste; bei Treffer wird er aus $user entfernt (verbraucht)
function pg_backup_code_consume(&$user, $input){
  $input = preg_replace('/\D/', '', (string) $input);
  if (strlen($input) < 6) return false;
  $hashes = (array) ($user['totp_backup'] ?? []);
  foreach ($hashes as $i => $h) {
    if (is_string($h) && password_verify($input, $h)) { unset($hashes[$i]); $user['totp_backup'] = array_values($hashes); return true; }
  }
  return false;
}
function pg_user_has_2fa($u){ return !empty($u['totp_enabled']) && !empty($u['totp']); }

/* ---------------- Aktivitätsprotokoll ---------------- */
const PG_ACTIVITY_FILE = __DIR__ . '/../data/activity.json';
function pg_log_activity($action, $detail = ''){
  $log = pg_load_json(PG_ACTIVITY_FILE); if (!is_array($log)) $log = [];
  $log[] = ['time' => date('c'), 'user' => pg_current_email() ?: '—', 'action' => $action, 'detail' => (string) $detail, 'ip' => pg_ip_hash()];
  if (count($log) > 300) $log = array_slice($log, -300);
  pg_save_json(PG_ACTIVITY_FILE, $log);
}

/* ---------------- Papierkorb (gelöschte Einträge) ---------------- */
const PG_TRASH_FILE = __DIR__ . '/../data/trash.json';
// Welche Collection hat eine slug-basierte Liste, die in den Papierkorb wandert?
function pg_trash_field($collection){
  return ['games' => 'games', 'patchnotes' => 'posts'][$collection] ?? null;
}
function pg_trash_load(){ $t = pg_load_json(PG_TRASH_FILE); return is_array($t) ? $t : []; }
function pg_trash_add($collection, $field, $item){
  $t = pg_trash_load();
  $title = $item['title'] ?? ($item['name'] ?? ($item['slug'] ?? '(ohne Titel)'));
  $t[] = ['collection' => $collection, 'field' => $field, 'title' => $title, 'slug' => $item['slug'] ?? '',
          'deletedAt' => date('c'), 'by' => pg_current_email() ?: '—', 'item' => $item];
  if (count($t) > 60) $t = array_slice($t, -60);
  pg_save_json(PG_TRASH_FILE, $t);
}
// Diff beim Speichern: aus dem alten Stand entfernte slug-Einträge sichern.
function pg_trash_capture($collection, $field, $oldData, $newData){
  $newSlugs = [];
  foreach (($newData[$field] ?? []) as $it) if (!empty($it['slug'])) $newSlugs[$it['slug']] = true;
  foreach (($oldData[$field] ?? []) as $it) {
    $sl = $it['slug'] ?? '';
    if ($sl !== '' && empty($newSlugs[$sl])) pg_trash_add($collection, $field, $it);
  }
}

/* ---------------- Backup (Export/Import aller Inhalte) ---------------- */
// Alle sicherungswürdigen Dateien im data-Ordner (Inhalte + Einstellungen)
function pg_backup_filenames(){
  $out = [];
  if (is_dir(PG_DATA_DIR)) {
    foreach (scandir(PG_DATA_DIR) as $f) {
      if ($f === '.' || $f === '..') continue;
      if (pg_backup_allowed($f) && is_file(PG_DATA_DIR . '/' . $f)) $out[] = $f;
    }
  }
  sort($out);
  return $out;
}
// Nur erlaubte Dateinamen sichern/wiederherstellen (kein Pfad-Traversal)
function pg_backup_allowed($name){
  if ($name !== basename($name)) return false;
  return (bool) preg_match('/^[A-Za-z0-9_.\-]+\.json$/', $name) || $name === 'secret.php';
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
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid rgba(255,255,255,.1);border-radius:18px;overflow:hidden">'
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
// Bestätigungs-Token (Double-Opt-In)
function pg_confirm_token($email){
  return hash_hmac('sha256', strtolower(trim($email)) . '|confirm', pg_app_secret());
}
function pg_confirm_link($email){
  return pg_site_url() . '/subscribe.php?action=confirm&e=' . rawurlencode($email) . '&t=' . pg_confirm_token($email);
}
// Bestätigungs-Mail (Double-Opt-In) an einen neuen Abonnenten
function pg_send_confirm_mail($email){
  $body = '<h1 style="margin:14px 0 0;font-size:24px;color:#fff">Fast geschafft! ✨</h1>'
    . '<p style="color:#d7d7df;line-height:1.7;margin:14px 0 0">Bitte bestätige deine Anmeldung zum <b>PLANIGAMES</b>-Newsletter mit einem Klick:</p>'
    . '<div style="padding:22px 0 4px">' . pg_mail_button('Anmeldung bestätigen →', pg_confirm_link($email)) . '</div>'
    . '<p style="color:#7b7b86;font-size:12px;line-height:1.6;margin:8px 0 0">Du hast das nicht angefordert? Dann ignoriere diese Mail einfach – ohne Bestätigung passiert nichts.</p>';
  return pg_send_mail($email, 'Bitte bestätige deine Newsletter-Anmeldung', pg_mail_shell($body, 'Bestätige deine Newsletter-Anmeldung'));
}
// Willkommens-Mail nach erfolgreicher Bestätigung
function pg_send_welcome_mail($email){
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $nl = $s['newsletter'] ?? [];
  $msg = trim((string) ($nl['welcomeMessage'] ?? '')) ?: "Willkommen an Bord! 🧡\n\nDu bist jetzt dabei und erfährst als Erste*r von Demos, Release-Terminen und großen Updates. Bis bald im Chaos!";
  $body = '<h1 style="margin:14px 0 0;font-size:24px;color:#fff">Willkommen bei PLANIGAMES! 🎮</h1>'
    . '<div style="color:#d7d7df;line-height:1.7;font-size:15px;margin:14px 0 0">' . nl2br(pg_h($msg)) . '</div>'
    . '<p style="color:#6f6f7a;font-size:12px;margin:22px 0 0">Du bekommst diese Mail, weil du den PLANIGAMES-Newsletter bestätigt hast. '
    . '<a href="' . pg_h(pg_unsub_link($email)) . '" style="color:#ff8a2b">Abmelden</a></p>';
  return pg_send_mail($email, 'Willkommen beim PLANIGAMES-Newsletter 🧡', pg_mail_shell($body, 'Willkommen!'));
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

  // Textspalte der Signatur (Name, Rolle, Kontakt, Socials)
  $lines = '';
  if ($name !== '') $lines .= '<div style="color:#ffffff;font-weight:700;font-size:15px">' . pg_h($name) . '</div>';
  if ($role !== '') $lines .= '<div style="color:#9a9aa6;font-size:13px;margin-top:2px">' . pg_h($role) . '</div>';

  $contact = [];
  if ($email)   $contact[] = '<a href="mailto:' . pg_h($email) . '" style="color:#ff8a2b;text-decoration:none">' . pg_h($email) . '</a>';
  if ($siteTxt) $contact[] = '<a href="' . pg_h($siteUrl) . '" style="color:#ff8a2b;text-decoration:none">' . pg_h($siteTxt) . '</a>';
  if ($phone)   $contact[] = '<span style="color:#9a9aa6">' . pg_h($phone) . '</span>';
  if ($contact) $lines .= '<div style="font-size:13px;margin-top:11px">' . implode(' &nbsp;·&nbsp; ', $contact) . '</div>';

  $soc = [];
  foreach ($socials as $x) $soc[] = '<a href="' . pg_h($x['url']) . '" style="color:#9a9aa6;text-decoration:none">' . pg_h($x['label']) . '</a>';
  if ($soc) $lines .= '<div style="font-size:12px;margin-top:9px;color:#6f6f7a">' . implode(' &nbsp;&nbsp; ', $soc) . '</div>';

  // Kein Logo in der Signatur – das Logo steht bereits im Mail-Kopf (keine Doppelung).
  $out = '<div style="margin-top:28px">';
  if ($closing !== '') $out .= '<div style="color:#c7c7d1;font-size:14px;line-height:1.6;margin-bottom:16px">' . nl2br(pg_h($closing)) . '</div>';
  $out .= '<div style="width:42px;height:2px;background:linear-gradient(110deg,#e6a015,#ff7d1a);margin:0 0 16px"></div>';
  $out .= $lines;
  $out .= '</div>';
  return $out;
}

/* Fertiges HTML für eine persönliche Mail (Verfassen/Antworten) inkl. Signatur. */
function pg_mail_build_personal($message){
  $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . nl2br(pg_h($message)) . '</div>' . pg_mail_signature();
  return pg_mail_shell($body, mb_substr($message, 0, 90));
}
/* Fertiges HTML für eine Newsletter-Mail inkl. Abmeldelink. */
function pg_mail_build_newsletter($message, $unsubLink){
  $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . nl2br(pg_h($message)) . '</div>'
    . '<p style="color:#6f6f7a;font-size:12px;margin:22px 0 0">Du erhältst diese Mail, weil du den PLANIGAMES-Newsletter abonniert hast. '
    . '<a href="' . pg_h($unsubLink) . '" style="color:#ff8a2b">Abmelden</a></p>';
  return pg_mail_shell($body, mb_substr($message, 0, 90));
}

/* Bestätigte Abonnenten-E-Mails (Legacy ohne Flag gelten als bestätigt). */
function pg_confirmed_emails($subs){
  $out = [];
  foreach ((array) $subs as $r) {
    $e = trim($r['email'] ?? '');
    $confirmed = !array_key_exists('confirmed', (array) $r) || !empty($r['confirmed']);
    if ($confirmed && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
  }
  return array_values(array_unique($out));
}

/* Einen Devlog-Beitrag als gebrandeten Newsletter an alle Abonnenten senden.
   Gibt die Anzahl zugestellter Mails zurück. */
function pg_newsletter_send_post($post){
  $emails = pg_confirmed_emails(pg_load_json(PG_DATA_DIR . '/subscribers.json'));
  if (!$emails) return 0;
  $title   = trim($post['title'] ?? 'Neuer Beitrag');
  $excerpt = trim($post['excerpt'] ?? '');
  $url     = pg_site_url() . '/devlog.php?slug=' . rawurlencode($post['slug'] ?? '');
  @set_time_limit(120);
  $sent = 0;
  foreach ($emails as $to) {
    $unsub = pg_unsub_link($to);
    $body = '<h1 style="margin:14px 0 0;font-size:24px;color:#fff">' . pg_h($title) . '</h1>'
      . ($excerpt !== '' ? '<p style="color:#d7d7df;line-height:1.7;font-size:15px;margin:14px 0 0">' . pg_h($excerpt) . '</p>' : '')
      . '<div style="padding:22px 0 4px">' . pg_mail_button('Zum Beitrag lesen →', $url) . '</div>'
      . '<p style="color:#6f6f7a;font-size:12px;margin:18px 0 0">Du erhältst diese Mail, weil du den PLANIGAMES-Newsletter abonniert hast. '
      . '<a href="' . pg_h($unsub) . '" style="color:#ff8a2b">Abmelden</a></p>';
    $html = pg_mail_shell($body, $title);
    if (pg_send_mail($to, $title, $html)) $sent++;
    usleep(120000);
  }
  return $sent;
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
     . '<link rel="stylesheet" href="assets/admin.css?v=23"></head><body>';
}
function pg_view_foot(){
  echo '<script src="assets/admin.js?v=23"></script></body></html>';
}
function pg_view_topbar($SCHEMA, $active){
  $studio = pg_load_json(PG_DATA_DIR . '/studio.json');
  $logo = trim((string) ($studio['logo'] ?? ''));
  $brand = $logo !== ''
    ? '<img src="' . pg_h($logo) . '" alt="PLANIGAMES" class="tb-logo">'
    : '<span class="diamond"></span> PLANI<span class="grad">GAMES</span>';
  echo '<header class="topbar">';
  echo '<a class="tb-brand" href="index.php">' . $brand . '</a>';
  // Direkter E-Mail-Postfach-Knopf (immer sichtbar, auch mobil)
  if (pg_can('mail')) {
    $mu = pg_mail_unread_cached();
    echo '<a class="tb-mailicon' . ($active === 'mail' ? ' on' : '') . '" href="mail.php" '
       . 'title="E-Mail-Postfach" aria-label="E-Mail-Postfach">📮'
       . ($mu ? '<span class="nbadge">' . $mu . '</span>' : '') . '</a>';
  }
  echo '<button type="button" class="tb-burger" id="tb-burger" aria-label="Menü" aria-expanded="false"><span></span><span></span><span></span></button>';
  echo '<nav class="tb-nav" id="tb-nav">';

  // Inhalte (Content-Sammlungen) – direkte Links
  foreach ($SCHEMA as $key => $coll) {
    if (!pg_can($key)) continue;
    $cls = $key === $active ? ' class="on"' : '';
    echo '<a' . $cls . ' href="index.php?collection=' . pg_h($key) . '">' . pg_h($coll['label']) . '</a>';
  }

  // Gruppe „Community" – Abos & Kontakt (Mails haben eigenes Icon oben)
  $community = [];
  if (pg_can('subscribers')) $community[] = ['index.php?view=subscribers', '📬 Newsletter-Abos', 0];
  if (pg_can('contacts'))    $community[] = ['index.php?view=contacts', '✉️ Kontakt-Anfragen', pg_contacts_unread()];
  pg_nav_group('Community', $community, false);

  // Gruppe „System" – Medien, Statistik, Papierkorb + Owner-Werkzeuge
  $system = [];
  $system[] = ['index.php?view=search', '🔎 Suche', 0];
  $system[] = ['index.php?view=media', '🖼️ Medien-Bibliothek', 0];
  $system[] = ['index.php?view=stats', '📊 Statistik', 0];
  if (pg_can('mail') || pg_can('contacts')) $system[] = ['index.php?view=templates', '⚡ Schnellantworten', 0];
  $system[] = ['index.php?view=trash', '🗑️ Papierkorb', count(pg_trash_load())];
  $system[] = ['index.php?view=account', '🔒 Konto & 2FA', 0];
  if (pg_is_owner()) {
    $system[] = ['index.php?view=users', '🔑 Zugänge & Rollen', 0];
    $system[] = ['index.php?view=activity', '📋 Protokoll', 0];
    $system[] = ['index.php?view=backup', '💾 Backup', 0];
  }
  pg_nav_group('System', $system, $active === 'media');

  echo '<span class="tb-mobile-extra">';
  if (pg_logged_in()) echo '<span class="tb-user">' . pg_h(pg_current_email()) . '</span>';
  echo '<a href="../index.html" target="_blank">↗ Seite ansehen</a><a href="index.php?action=logout">Abmelden</a></span>';
  echo '</nav>';
  echo '<span class="tb-right">';
  if (pg_logged_in()) echo '<span class="tb-user" title="' . pg_h(pg_current_email()) . '">' . pg_h(pg_current_email()) . '</span>';
  echo '<a href="../index.html" target="_blank">↗ Seite</a><a href="index.php?action=logout">Abmelden</a></span>';
  echo '</header>';
}

/* Aufklappbare Navi-Gruppe (Desktop: Dropdown, Mobil: aufgeklappte Liste im Burger-Menü).
   $items = [ [href, 'Emoji Beschriftung', ungelesen-Anzahl], … ] */
function pg_nav_group($label, $items, $active = false){
  if (!$items) return;
  $sum = 0;
  foreach ($items as $it) $sum += (int) ($it[2] ?? 0);
  echo '<div class="tb-group' . ($active ? ' on' : '') . '" data-tb-group>';
  echo '<button type="button" class="tb-group-btn" aria-expanded="false">'
     . pg_h($label)
     . ($sum ? '<span class="nbadge">' . $sum . '</span>' : '')
     . '<span class="tb-caret" aria-hidden="true">▾</span></button>';
  echo '<div class="tb-menu">';
  foreach ($items as $it) {
    $href = $it[0]; $text = $it[1]; $badge = (int) ($it[2] ?? 0);
    echo '<a href="' . pg_h($href) . '">' . $text
       . ($badge ? '<span class="nbadge">' . $badge . '</span>' : '') . '</a>';
  }
  echo '</div></div>';
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
    if (($f['widget'] ?? '') === 'heading') continue;
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
  $resp = ['path' => '/media/' . $name];
  $opt = pg_optimize_image(PG_MEDIA_DIR . '/' . $name, $ext);
  if ($opt) $resp['optimized'] = $opt;
  echo json_encode($resp);
}

/* Bild beim Upload verkleinern/komprimieren (nur wenn GD verfügbar).
   - skaliert auf max. 2000 px lange Kante herunter
   - korrigiert EXIF-Drehung (JPEG)
   - re-encodet mit moderater Qualität; behält Transparenz bei PNG/WebP
   Gibt ['before'=>Bytes,'after'=>Bytes] zurück, sonst null (unverändert). */
function pg_optimize_image($path, $ext){
  if (!function_exists('imagecreatetruecolor')) return null;
  $ext = strtolower($ext);
  $maxEdge = 2000; $quality = 82;
  $before = (int) @filesize($path);
  switch ($ext) {
    case 'jpg': case 'jpeg': $img = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null; break;
    case 'png':  $img = function_exists('imagecreatefrompng')  ? @imagecreatefrompng($path)  : null; break;
    case 'webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; break;
    default: return null; // svg/gif/avif/Videos unangetastet lassen
  }
  if (!$img) return null;
  $w = imagesx($img); $h = imagesy($img);
  if ($w < 1 || $h < 1) { imagedestroy($img); return null; }
  // EXIF-Orientierung (nur JPEG)
  $orient = 0;
  if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('exif_read_data')) {
    $ex = @exif_read_data($path);
    $orient = (int) ($ex['Orientation'] ?? 0);
  }
  $scale = min(1, $maxEdge / $w, $maxEdge / $h);
  $needResize = $scale < 1;
  $needRotate = in_array($orient, [3, 6, 8], true);
  $isJpeg = ($ext === 'jpg' || $ext === 'jpeg');
  // Nichts zu tun und Datei nicht übermäßig groß → Original behalten
  if (!$needResize && !$needRotate && !($isJpeg && $before > 600 * 1024)) { imagedestroy($img); return null; }
  $nw = $needResize ? max(1, (int) round($w * $scale)) : $w;
  $nh = $needResize ? max(1, (int) round($h * $scale)) : $h;
  $dst = imagecreatetruecolor($nw, $nh);
  if ($ext === 'png' || $ext === 'webp') {
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocatealpha($dst, 0, 0, 0, 127));
  }
  imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
  imagedestroy($img);
  if ($needRotate && function_exists('imagerotate')) {
    $deg = $orient === 3 ? 180 : ($orient === 6 ? -90 : 90);
    $rot = @imagerotate($dst, $deg, 0);
    if ($rot) { imagedestroy($dst); $dst = $rot; }
  }
  $tmp = $path . '.opt';
  $ok = $ext === 'png' ? @imagepng($dst, $tmp, 6) : ($ext === 'webp' ? @imagewebp($dst, $tmp, $quality) : @imagejpeg($dst, $tmp, $quality));
  imagedestroy($dst);
  if ($ok && is_file($tmp)) {
    $after = (int) filesize($tmp);
    if ($needResize || $needRotate || ($after > 0 && $after < $before)) { @rename($tmp, $path); return ['before' => $before, 'after' => $after]; }
    @unlink($tmp);
  }
  return null;
}

/* =====================================================================
   FORMULAR-RENDERING (aus dem Schema)
   $prefix  = Name-Präfix für die Inputs, z. B. "d[games][0]"
   $pathKey = Schema-Pfad ohne Indizes, z. B. "games" (für eindeutige Tokens)
   ===================================================================== */

function pg_render_fields($fields, $values, $prefix, $pathKey){
  $out = '';
  foreach ($fields as $f) {
    // Abschnitts-Überschrift (rein optisch, kein Datenfeld)
    if (($f['widget'] ?? '') === 'heading') {
      $hh = !empty($f['hint']) ? '<span class="fg-hint">' . pg_h($f['hint']) . '</span>' : '';
      $out .= '<h3 class="field-group">' . pg_h($f['label']) . $hh . '</h3>';
      continue;
    }
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
    // Einklappbare Sektion (standardmäßig zugeklappt) → bessere Übersicht
    return '<div class="field objectfield folded" data-objfold>'
         . '<button type="button" class="obj-head" data-objtoggle aria-expanded="false">'
         . '<span class="obj-title">' . $label . '</span>'
         . '<span class="obj-caret" aria-hidden="true">▾</span></button>'
         . '<div class="object-body">' . $hint . $inner . '</div></div>';
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

  $h = '<div class="field listfield" data-list data-name="' . pg_h($name) . '" data-token="' . pg_h($token) . '">';
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
       . '<button type="button" data-dup title="duplizieren">⧉</button>'
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

  $h = '<div class="field blocksfield" data-blocks data-name="' . pg_h($name) . '" data-token="' . pg_h($token) . '">';
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
       . '<button type="button" data-dup title="duplizieren">⧉</button>'
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
    if (($f['widget'] ?? '') === 'heading') continue; // keine Daten
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
