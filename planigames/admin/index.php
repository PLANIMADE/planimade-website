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
if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

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
    $u = pg_user_check(trim($_POST['email'] ?? ''), $_POST['pw'] ?? '');
    if ($u) { session_regenerate_id(true); pg_login_user($u); header('Location: index.php'); exit; }
    $err = 'E-Mail oder Passwort falsch.';
    usleep(400000);
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
  $ok = pg_save_json(pg_lang_file($SCHEMA[$key]['file'], $lang), $data);
  header('Location: index.php?collection=' . urlencode($key) . '&lang=' . $lang . ($ok ? '&saved=1' : '&error=1'));
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
  $list = array_reverse($list);
  pg_view_head('Newsletter-Abos');
  pg_view_topbar($SCHEMA, null);
  echo '<div class="editor">';
  if (isset($_GET['cleared'])) echo '<div class="flash ok">✓ Liste geleert.</div>';
  echo '<div class="editor-head"><div><h1>📬 Newsletter-Abos</h1>'
     . '<p class="muted">' . count($list) . ' Anmeldung' . (count($list) === 1 ? '' : 'en') . '. Werden bei jeder Anmeldung auf der Website ergänzt.</p></div>';
  if ($list) echo '<a class="btn-primary" href="index.php?action=subscribers_csv">CSV exportieren</a>';
  echo '</div>';
  if (!$list) {
    echo '<p class="muted">Noch keine Anmeldungen.</p>';
  } else {
    echo '<table class="subs"><thead><tr><th>E-Mail</th><th>Datum</th><th>Quelle</th></tr></thead><tbody>';
    foreach ($list as $r) {
      echo '<tr><td>' . pg_h($r['email'] ?? '') . '</td><td>'
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
          $flash = '<div class="flash ok">Berechtigungen aktualisiert.</div>';
        }
      }
    } elseif (isset($_POST['revoke'])) {
      pg_invite_delete($_POST['token'] ?? ''); $flash = '<div class="flash ok">Einladung zurückgezogen.</div>';
    } elseif (isset($_POST['deluser'])) {
      $email = $_POST['email'] ?? '';
      if (strcasecmp($email, pg_current_email()) === 0) $flash = '<div class="flash err">Du kannst dich nicht selbst entfernen.</div>';
      elseif (($u = pg_user_find($email)) && ($u['role'] ?? '') === 'owner' && pg_owner_count() <= 1) $flash = '<div class="flash err">Der letzte Owner kann nicht entfernt werden.</div>';
      else { pg_user_delete($email); $flash = '<div class="flash ok">Zugang entfernt.</div>'; }
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
  echo '<div class="editor-head"><div><h1>' . pg_h($coll['icon']) . ' ' . pg_h($coll['label'])
     . ' <span class="lang-pill">' . strtoupper($lang) . '</span></h1>'
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
  if (!pg_can($key)) continue;
  $desc = [
    'studio' => 'Startseite, Über-uns, Kontakt &amp; Footer.',
    'team' => 'Teammitglieder mit Foto, Name &amp; Rolle.',
    'games' => 'Spiele anlegen und ihre Seiten mit Blöcken bauen.',
    'patchnotes' => 'Devlog-Einträge, Ankündigungen &amp; Patch Notes.',
    'legal' => 'Impressum &amp; Datenschutzerklärung bearbeiten.',
  ][$key] ?? '';
  echo '<a class="card" href="index.php?collection=' . pg_h($key) . '">'
     . '<span class="card-ico">' . pg_h($coll['icon']) . '</span>'
     . '<span class="card-title">' . pg_h($coll['label']) . '</span>'
     . '<span class="card-desc">' . $desc . '</span></a>';
}
if (pg_can('subscribers')) {
  $subCount = count(pg_load_json(PG_DATA_DIR . '/subscribers.json'));
  echo '<a class="card" href="index.php?view=subscribers">'
     . '<span class="card-ico">📬</span><span class="card-title">Newsletter-Abos</span>'
     . '<span class="card-desc">' . $subCount . ' Anmeldung' . ($subCount === 1 ? '' : 'en') . ' · ansehen &amp; exportieren.</span></a>';
}
if (pg_is_owner()) {
  echo '<a class="card" href="index.php?view=users">'
     . '<span class="card-ico">🔑</span><span class="card-title">Zugänge &amp; Rollen</span>'
     . '<span class="card-desc">Login-Zugänge per E-Mail einladen &amp; Rechte vergeben.</span></a>';
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
     . '<link rel="stylesheet" href="assets/admin.css?v=2"></head><body>';
}
function pg_view_foot(){
  echo '<script src="assets/admin.js?v=2"></script></body></html>';
}
function pg_view_topbar($SCHEMA, $active){
  echo '<header class="topbar"><a class="tb-brand" href="index.php"><span class="diamond"></span> PLANI<span class="grad">GAMES</span></a>';
  echo '<nav class="tb-nav">';
  foreach ($SCHEMA as $key => $coll) {
    if (!pg_can($key)) continue;
    $cls = $key === $active ? ' class="on"' : '';
    echo '<a' . $cls . ' href="index.php?collection=' . pg_h($key) . '">' . pg_h($coll['label']) . '</a>';
  }
  if (pg_can('subscribers')) echo '<a href="index.php?view=subscribers">Abos</a>';
  if (pg_is_owner()) echo '<a href="index.php?view=users">Zugänge</a>';
  echo '</nav>';
  echo '<span class="tb-right">';
  if (pg_logged_in()) echo '<span class="tb-user" title="' . pg_h(pg_current_email()) . '">' . pg_h(pg_current_email()) . '</span>';
  echo '<a href="../index.html" target="_blank">↗ Seite</a><a href="index.php?action=logout">Abmelden</a></span>';
  echo '</header>';
}
