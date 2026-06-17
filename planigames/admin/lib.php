<?php
/**
 * PLANIGAMES Admin — Kernfunktionen (Flat-File CMS für All-Inkl).
 * Auth, CSRF, JSON-I/O, schema-getriebenes Rendern & Normalisieren.
 */

const PG_DATA_DIR  = __DIR__ . '/../data';
const PG_MEDIA_DIR = __DIR__ . '/../media';
const PG_AUTH_FILE = __DIR__ . '/../data/auth.php';
const PG_UPLOAD_EXT = ['jpg','jpeg','png','webp','gif','svg','avif','mp4','webm','ogg','mov'];

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

/* ---------------- Auth ---------------- */
function pg_auth_get(){ return is_file(PG_AUTH_FILE) ? include PG_AUTH_FILE : null; }
function pg_auth_is_setup(){ $a = pg_auth_get(); return is_array($a) && !empty($a['hash']); }
function pg_auth_set_password($pw){
  $php = "<?php\n// Automatisch erzeugt. NICHT löschen – enthält den Passwort-Hash fürs Admin.\nreturn "
       . var_export(['hash' => password_hash($pw, PASSWORD_DEFAULT)], true) . ";\n";
  file_put_contents(PG_AUTH_FILE, $php, LOCK_EX);
}
function pg_auth_check($pw){ $a = pg_auth_get(); return is_array($a) && !empty($a['hash']) && password_verify($pw, $a['hash']); }
function pg_logged_in(){ return !empty($_SESSION['pg_ok']); }

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

  // Listen & Blöcke rendern ihre eigene Hülle
  if ($w === 'list')   return pg_render_list($f, is_array($val) ? $val : [], $name, $pathKey);
  if ($w === 'blocks') return pg_render_blocks($f, is_array($val) ? $val : [], $name, $pathKey);

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
    default: // alle Text-/String-Widgets
      $s = is_array($val) ? '' : trim((string)$val);
      if (!empty($f['slug'])) {
        if ($s === '') $s = pg_slugify($siblingRaw['title'] ?? '');
        else $s = pg_slugify($s);
      }
      return $s;
  }
}
