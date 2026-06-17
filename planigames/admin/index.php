<?php
/**
 * PLANIGAMES — PHP Admin (Flat-File CMS für All-Inkl / KAS).
 * Login -> Dashboard -> Editor. Speichert direkt in ../data/*.json,
 * Uploads nach ../media/. Keine Datenbank, keine externen Dienste.
 */
require __DIR__ . '/lib.php';
$SCHEMA = require __DIR__ . '/schema.php';
pg_session_boot();

$action = $_GET['action'] ?? '';

/* ---- Upload-Endpoint (nur eingeloggt) ---- */
if ($action === 'upload') {
  if (!pg_logged_in()) { http_response_code(403); exit('{"error":"Nicht eingeloggt"}'); }
  pg_csrf_check();
  pg_handle_upload();
  exit;
}

/* ---- Logout ---- */
if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

/* ---- Ersteinrichtung: Passwort anlegen ---- */
if (!pg_auth_is_setup()) {
  $err = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    $p1 = $_POST['pw'] ?? ''; $p2 = $_POST['pw2'] ?? '';
    if (strlen($p1) < 8) $err = 'Bitte mindestens 8 Zeichen.';
    elseif ($p1 !== $p2) $err = 'Die Passwörter stimmen nicht überein.';
    else {
      pg_auth_set_password($p1);
      $_SESSION['pg_ok'] = true;
      header('Location: index.php'); exit;
    }
  }
  pg_view_head('Einrichten');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  echo '<h1>Willkommen! Erstelle dein Passwort</h1>';
  echo '<p class="muted">Dieses Passwort schützt dein Dashboard. Merke es dir gut – es wird verschlüsselt gespeichert.</p>';
  if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="password" name="pw" placeholder="Passwort (min. 8 Zeichen)" required autofocus>';
  echo '<input type="password" name="pw2" placeholder="Passwort wiederholen" required>';
  echo '<button class="btn-primary" type="submit">Passwort setzen &amp; starten</button></form>';
  echo '</div></div>';
  pg_view_foot();
  exit;
}

/* ---- Login ---- */
if (!pg_logged_in()) {
  $err = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pg_csrf_check();
    if (pg_auth_check($_POST['pw'] ?? '')) { $_SESSION['pg_ok'] = true; session_regenerate_id(true); header('Location: index.php'); exit; }
    $err = 'Falsches Passwort.';
    usleep(400000);
  }
  pg_view_head('Login');
  echo '<div class="auth"><div class="auth-card">';
  echo '<div class="brand"><span class="diamond"></span> PLANI<span class="grad">GAMES</span> · Admin</div>';
  echo '<h1>Anmelden</h1>';
  if ($err) echo '<p class="err">' . pg_h($err) . '</p>';
  echo '<form method="post"><input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="password" name="pw" placeholder="Passwort" required autofocus>';
  echo '<button class="btn-primary" type="submit">Einloggen</button></form>';
  echo '</div></div>';
  pg_view_foot();
  exit;
}

/* ---- Speichern ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  pg_csrf_check();
  $key = $_POST['collection'] ?? '';
  if (!isset($SCHEMA[$key])) { http_response_code(404); exit('Unbekannter Bereich.'); }
  $data = pg_normalize_fields($SCHEMA[$key]['fields'], $_POST['d'] ?? []);
  $ok = pg_save_json($SCHEMA[$key]['file'], $data);
  header('Location: index.php?collection=' . urlencode($key) . ($ok ? '&saved=1' : '&error=1'));
  exit;
}

/* ---- Editor ---- */
if (isset($_GET['collection']) && isset($SCHEMA[$_GET['collection']])) {
  $key = $_GET['collection'];
  $coll = $SCHEMA[$key];
  $data = pg_load_json($coll['file']);
  pg_view_head($coll['label']);
  pg_view_topbar($SCHEMA, $key);
  if (isset($_GET['saved']))  echo '<div class="flash ok">✓ Gespeichert. Änderungen sind live auf der Website.</div>';
  if (isset($_GET['error']))  echo '<div class="flash err">Konnte nicht speichern – Schreibrechte am Ordner data/ prüfen.</div>';
  echo '<form method="post" class="editor" id="editor">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<input type="hidden" name="collection" value="' . pg_h($key) . '">';
  echo '<div class="editor-head"><div><h1>' . pg_h($coll['icon']) . ' ' . pg_h($coll['label']) . '</h1>'
     . '<p class="muted">Änderungen werden direkt auf dem Server gespeichert.</p></div>'
     . '<button class="btn-primary" type="submit" name="save" value="1">Speichern</button></div>';
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
echo '<div class="cards">';
foreach ($SCHEMA as $key => $coll) {
  $desc = [
    'studio' => 'Startseite, Über-uns, Team, Kontakt &amp; Footer.',
    'games' => 'Spiele anlegen und ihre Seiten mit Blöcken bauen.',
    'patchnotes' => 'Devlog-Einträge, Ankündigungen &amp; Patch Notes.',
  ][$key] ?? '';
  echo '<a class="card" href="index.php?collection=' . pg_h($key) . '">'
     . '<span class="card-ico">' . pg_h($coll['icon']) . '</span>'
     . '<span class="card-title">' . pg_h($coll['label']) . '</span>'
     . '<span class="card-desc">' . $desc . '</span></a>';
}
echo '</div>';
echo '<div class="dash-links"><a href="../index.html" target="_blank">↗ Website ansehen</a> '
   . '<a href="index.php?action=logout">Abmelden</a></div>';
echo '</div>';
pg_view_foot();


/* =================== VIEW-HELFER =================== */
function pg_view_head($title){
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1">'
     . '<meta name="robots" content="noindex"><title>' . pg_h($title) . ' · PLANIGAMES Admin</title>'
     . '<link rel="stylesheet" href="assets/admin.css"></head><body>';
}
function pg_view_foot(){
  echo '<script src="assets/admin.js"></script></body></html>';
}
function pg_view_topbar($SCHEMA, $active){
  echo '<header class="topbar"><a class="tb-brand" href="index.php"><span class="diamond"></span> PLANI<span class="grad">GAMES</span></a>';
  echo '<nav class="tb-nav">';
  foreach ($SCHEMA as $key => $coll) {
    $cls = $key === $active ? ' class="on"' : '';
    echo '<a' . $cls . ' href="index.php?collection=' . pg_h($key) . '">' . pg_h($coll['label']) . '</a>';
  }
  echo '</nav>';
  echo '<span class="tb-right"><a href="../index.html" target="_blank">↗ Seite</a><a href="index.php?action=logout">Abmelden</a></span>';
  echo '</header>';
}
