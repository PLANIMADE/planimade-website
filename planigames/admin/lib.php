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
    'games'       => 'Spiele',
    'patchnotes'  => 'Devlog & Patch Notes',
    'legal'       => 'Rechtliches',
    'subscribers' => 'Newsletter-Abos',
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
  return $scheme . '://' . $host . $dir;
}

/* Gebrandete HTML-Einladungsmail */
function pg_send_invite_mail($to, $link, $roleLabel, $inviter = ''){
  $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.de');
  $html = pg_invite_html($link, $roleLabel, $inviter);
  $headers = "From: PLANIGAMES <no-reply@{$host}>\r\nReply-To: no-reply@{$host}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
  return @mail($to, 'Einladung ins PLANIGAMES-Dashboard', $html, $headers);
}

function pg_invite_html($link, $roleLabel, $inviter = ''){
  $L = pg_h($link);
  $who = $inviter ? (' von ' . pg_h($inviter)) : '';
  return '<!doctype html><html><body style="margin:0;background:#050505;font-family:Arial,Helvetica,sans-serif;color:#ececf0">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050505;padding:32px 16px"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#111114;border:1px solid rgba(255,255,255,.1);border-radius:18px;overflow:hidden">'
    . '<tr><td style="padding:34px 34px 0"><div style="font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:15px">'
    . '<span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#e6a015,#ff7d1a);border-radius:2px;transform:rotate(45deg)"></span>&nbsp;&nbsp;PLANI<span style="color:#ff8a2b">GAMES</span></div></td></tr>'
    . '<tr><td style="padding:22px 34px 0"><h1 style="margin:0;font-size:24px;color:#fff">Du bist eingeladen' . $who . ' 🎮</h1>'
    . '<p style="color:#b6b6c0;line-height:1.6;margin:14px 0 0">Du wurdest eingeladen, am <b>PLANIGAMES</b>-Dashboard mitzuarbeiten – als <b>' . pg_h($roleLabel) . '</b>. '
    . 'Klicke auf den Button, um dein Konto zu erstellen und ein Passwort zu vergeben.</p></td></tr>'
    . '<tr><td style="padding:26px 34px 6px"><a href="' . $L . '" style="display:inline-block;background:linear-gradient(110deg,#ff7d1a,#ff9d4d);color:#1a0d00;text-decoration:none;font-weight:700;padding:14px 30px;border-radius:999px">Konto erstellen →</a></td></tr>'
    . '<tr><td style="padding:8px 34px 0"><p style="color:#7b7b86;font-size:12px;line-height:1.6;margin:0">Funktioniert der Button nicht? Kopiere diesen Link:<br><span style="color:#ff8a2b;word-break:break-all">' . $L . '</span></p></td></tr>'
    . '<tr><td style="padding:26px 34px 30px"><p style="color:#7b7b86;font-size:12px;margin:0;border-top:1px solid rgba(255,255,255,.08);padding-top:16px">Der Link ist 7 Tage gültig. Du kennst PLANIGAMES nicht? Dann ignoriere diese E-Mail einfach.</p></td></tr>'
    . '</table></td></tr></table></body></html>';
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
    $res = pg_http_get($url);
    $j = $res ? json_decode($res, true) : null;
    $tr = $j['responseData']['translatedText'] ?? null;
    $status = $j['responseStatus'] ?? 0;
    if ($tr && ((int)$status === 200)) $out .= html_entity_decode($tr, ENT_QUOTES, 'UTF-8');
    else $out .= $part; // Fallback: Original behalten
    usleep(120000); // freundlich zum kostenlosen Dienst
  }
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
