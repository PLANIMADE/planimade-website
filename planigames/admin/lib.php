<?php
/**
 * PLANIGAMES Admin — Kernfunktionen (Flat-File CMS für All-Inkl).
 * Auth, CSRF, JSON-I/O, schema-getriebenes Rendern & Normalisieren.
 */

const PG_DATA_DIR    = __DIR__ . '/../data';
const PG_MEDIA_DIR   = __DIR__ . '/../media';
const PG_USERS_FILE  = __DIR__ . '/../data/users.json';
const PG_INVITES_FILE= __DIR__ . '/../data/invites.json';
const PG_UPLOAD_EXT  = ['jpg','jpeg','png','webp','gif','svg','avif','mp4','webm','ogg','mov','zip','pdf'];
const PG_ADMIN_V     = '98';   // Cache-Bust für admin.css/admin.js – bei jeder Änderung hochzählen

function pg_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pg_de_month($n){
  $m = ['', 'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  return $m[(int)$n] ?? '';
}
/* "vor X" – relative Zeitangabe */
function pg_time_ago($ts){
  $s = time() - (int)$ts;
  if ($s < 60)    return 'gerade eben';
  if ($s < 3600)  return 'vor ' . floor($s / 60) . ' Min';
  if ($s < 86400) return 'vor ' . floor($s / 3600) . ' Std';
  if ($s < 172800) return 'gestern';
  if ($s < 604800) return 'vor ' . floor($s / 86400) . ' Tagen';
  return date('d.m.Y', (int)$ts);
}
/* Begrüßung nach Tageszeit (fürs Dashboard) */
function pg_greeting(){
  $h = (int) date('G');
  if ($h < 5)  return 'Noch wach';
  if ($h < 11) return 'Guten Morgen';
  if ($h < 17) return 'Hallo';
  if ($h < 22) return 'Guten Abend';
  return 'Gute Nacht';
}

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
// Ungelesene je Postfach (Hauptpostfach oben in der Datei, persönliche unter „mb“)
function pg_mail_unread_row($mb){
  $c = pg_load_json(PG_MAIL_UNREAD_FILE);
  $r = $mb === 'main' ? (is_array($c) ? $c : []) : (array) ($c['mb'][$mb] ?? []);
  return ['count' => max(0, (int) ($r['count'] ?? 0)), 'time' => (int) ($r['time'] ?? 0), 'fail' => (int) ($r['fail'] ?? 0)];
}
// Verbindung fehlgeschlagen (z. B. falsches Passwort): 5 Minuten Pause, damit der Mailserver
// uns nicht wegen vieler Fehlversuche sperrt – das Postfach selbst zeigt den Fehler sofort.
function pg_mail_unread_fail($mb){
  $c = pg_load_json(PG_MAIL_UNREAD_FILE); $c = is_array($c) ? $c : [];
  if ($mb === 'main') $c['fail'] = time();
  else { if (!is_array($c['mb'] ?? null)) $c['mb'] = []; $c['mb'][$mb] = (array) ($c['mb'][$mb] ?? []); $c['mb'][$mb]['fail'] = time(); }
  pg_save_json(PG_MAIL_UNREAD_FILE, $c);
}
function pg_mail_backoff($mb){ $f = pg_mail_unread_row($mb)['fail']; return $f && time() - $f < 300; }
function pg_mail_unread_cached($mb = null){ return pg_mail_unread_row($mb ?? pg_mb_key())['count']; }
function pg_mail_unread_store($count, $mb = null){
  $mb = $mb ?? pg_mb_key();
  $c = pg_load_json(PG_MAIL_UNREAD_FILE); $c = is_array($c) ? $c : [];
  if ($mb === 'main') { $c['count'] = max(0, (int) $count); $c['time'] = time(); unset($c['fail']); }
  else { if (!is_array($c['mb'] ?? null)) $c['mb'] = []; $c['mb'][$mb] = ['count' => max(0, (int) $count), 'time' => time()]; }
  pg_save_json(PG_MAIL_UNREAD_FILE, $c);
}
// Summe über alle Postfächer, die der Admin sieht (Seitenleiste, Glocke, Cockpit)
function pg_mail_unread_total(){
  $t = 0; foreach (array_keys(pg_mailboxes_visible()) as $id) $t += pg_mail_unread_cached($id);
  return $t;
}
// Live vom IMAP (gedrosselt je Postfach) – Glocke zeigt neue Mails ohne Seiten-Reload
function pg_mail_unread_live($maxAge = 20){
  $total = 0;
  foreach (array_keys(pg_mailboxes_visible()) as $id) {
    $total += pg_mailbox_with($id, function () use ($id, $maxAge) {
      $row = pg_mail_unread_row($id);
      if (time() - $row['time'] < $maxAge || pg_mail_backoff($id)) return $row['count'];
      if (!pg_imap_available() || !pg_mail_configured_recv()) return $row['count'];
      $mbox = pg_imap_connect('INBOX');
      if (!$mbox) { pg_mail_unread_fail($id); return $row['count']; }
      $unseen = @imap_search($mbox, 'UNSEEN');
      @imap_close($mbox);
      $n = is_array($unseen) ? count($unseen) : 0;
      pg_mail_unread_store($n, $id);
      return $n;
    });
  }
  return $total;
}

/* ---- Editor (Formatierung) – gemeinsam für Verfassen & Newsletter ---- */
function pg_mx_editor($html, $withTpl){
  $b = function ($cmd, $ic, $title) { return '<button type="button" class="rte-b" data-cmd="' . $cmd . '" title="' . pg_h($title) . '" aria-label="' . pg_h($title) . '">' . pg_icon($ic) . '</button>'; };
  $h = '<div class="rte" data-rte><div class="rte-bar" role="toolbar" aria-label="Formatierung">'
     . $b('bold', 'bold', 'Fett (Strg+B)') . $b('italic', 'italic', 'Kursiv (Strg+I)') . $b('underline', 'underline', 'Unterstrichen (Strg+U)')
     . '<span class="rte-sep"></span>' . $b('h3', 'heading', 'Zwischenüberschrift') . $b('insertUnorderedList', 'ul', 'Aufzählung') . $b('insertOrderedList', 'ol', 'Nummerierte Liste') . $b('quote', 'quote', 'Zitat')
     . '<span class="rte-sep"></span>' . $b('link', 'link', 'Link einfügen') . $b('clear', 'undo', 'Formatierung entfernen');
  // Spiel-Karte (Cover, Name, Wishlist-Button) einfügen
  $games = [];
  foreach (pg_games_data('de')['games'] as $g) if (is_array($g) && !empty($g['slug']))
    $games[] = ['slug' => (string) $g['slug'], 'title' => (string) ($g['title'] ?? $g['slug']), 'tagline' => (string) ($g['tagline'] ?? ''),
                'cover' => ($c = explode('#', (string) ($g['cover'] ?? ''))[0]) !== '' ? '../' . ltrim($c, '/') : '',
                'accent' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($g['accent'] ?? '')) ? $g['accent'] : '#ff7d1a',
                'wish' => (string) ($g['wishlistUrl'] ?? '')];
  if ($games) $h .= '<span class="rte-sep"></span><span class="rte-gamewrap"><button type="button" class="rte-b rte-game" data-game-menu title="Spiel-Karte mit Wishlist-Button einfügen" aria-label="Spiel einfügen">' . pg_icon('game') . '<span>Spiel</span></button>'
                  . '<div class="rte-gamemenu" data-game-list hidden></div></span><script type="application/json" data-games-data>' . json_encode($games, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
  if ($withTpl) {
    $tpls = pg_templates_load();
    if ($tpls) {
      $h .= '<span class="rte-sp"></span><select class="rte-tpl" data-tpl-select aria-label="Schnellantwort einfügen"><option value="">⚡ Vorlage einfügen …</option>';
      foreach ($tpls as $ti => $t) $h .= '<option value="' . $ti . '">' . pg_h($t['title'] ?: ('Vorlage ' . ($ti + 1))) . '</option>';
      $h .= '</select><script type="application/json" data-tpl-data>' . json_encode(array_map(fn($t) => (string) ($t['body'] ?? ''), $tpls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
    }
  }
  $h .= '</div><div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Nachricht" data-rte-area data-placeholder="Deine Nachricht …">' . $html . '</div>'
      . '<input type="hidden" name="message_html" value="" data-rte-input data-mail-input>'
      . '<noscript><textarea name="message" rows="10" placeholder="Deine Nachricht …"></textarea></noscript></div>';
  return $h;
}

/* ---------------- Postfach-Ordnerleiste (Postfach, Kontaktformular, Schnellantworten teilen sie) ---------------- */
function pg_mx_nav($active){
  $onMail = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'mail.php';
  $mail = pg_mailbox_access();
  $fl = function ($href, $ic, $label, $on, $n = 0, $hot = false) {
    return '<a class="mx-folder' . ($on ? ' on' : '') . '" href="' . pg_h($href) . '">' . pg_icon($ic) . '<span>' . pg_h($label) . '</span>'
         . ($n ? '<span class="n' . ($hot ? ' hot' : '') . '">' . (int) $n . '</span>' : '') . '</a>';
  };
  $h = '<nav class="ck-card mx-nav" aria-label="Postfach-Ordner">';
  $boxes = $mail ? pg_mailboxes_visible() : [];
  // aktuelles Postfach: auf mail.php das aktive, sonst das zuletzt benutzte (ohne etwas umzustellen)
  $cur = $onMail ? pg_mb_key() : (string) ($_SESSION['pg_mb'] ?? 'main');
  if ($boxes && !isset($boxes[$cur])) $cur = isset($boxes['main']) ? 'main' : (string) array_key_first($boxes);
  if (count($boxes) > 1) {                          // mehrere Postfächer → Umschalter oben
    $h .= '<div class="mx-boxes" role="group" aria-label="Postfach wählen">';
    foreach ($boxes as $id => $b) {
      $u = pg_mail_unread_cached($id);
      $on = $id === $cur && in_array($active, ['inbox', 'sent', 'compose'], true);
      $h .= '<a class="mx-box' . ($on ? ' on' : '') . '" href="mail.php?tab=inbox&amp;mb=' . rawurlencode($id) . '" title="' . pg_h($b['email']) . '"' . ($on ? ' aria-current="true"' : '') . '>'
          . '<span class="mx-box-av' . ($id === 'main' ? ' is-main' : '') . '">' . ($id === 'main' ? pg_icon('inbox') : pg_h(mb_strtoupper(mb_substr($b['label'], 0, 1)))) . '</span>'
          . '<span class="mx-box-tx"><b>' . pg_h($b['label']) . '</b><small>' . pg_h($b['email'] !== '' ? $b['email'] : 'noch nicht eingerichtet') . '</small></span>'
          . ($u ? '<span class="n hot">' . $u . '</span>' : '') . '</a>';
    }
    $h .= '</div><div class="mx-nav-sep"></div>';
  }
  $h .= '<div class="mx-folders">';                // Ordner (am Handy eigene, wischbare Zeile unter dem Umschalter)
  if ($mail) {
    $unread = pg_mail_unread_cached($cur);
    $h .= '<a class="btn btn-primary mx-new" href="mail.php?tab=compose">' . pg_icon('edit') . 'Verfassen</a>'
        . $fl('mail.php?tab=inbox', 'inbox', 'Posteingang', $active === 'inbox', $unread, $unread > 0)
        . $fl('mail.php?tab=sent', 'send', 'Gesendet', $active === 'sent', count(array_filter(pg_mail_sent_load(), fn($r) => (string) ($r['mb'] ?? 'main') === $cur)))
        . (pg_can('mail') && pg_can('subscribers') ? $fl('mail.php?tab=newsletter', 'mega', 'Newsletter', $active === 'newsletter') : '')
        . '<div class="mx-nav-sep"></div>';
  }
  if (pg_can('contacts')) { $cu = pg_contacts_unread(); $h .= $fl('index.php?view=contacts', 'chat', 'Kontaktformular', $active === 'contacts', $cu, $cu > 0); }
  if (pg_can('mail') || pg_can('contacts')) $h .= $fl('index.php?view=templates', 'zap', 'Schnellantworten', $active === 'templates');
  if ($mail) $h .= $onMail ? '<button type="button" class="mx-folder" data-open-sigs>' . pg_icon('user') . '<span>Signaturen</span></button>'
                           : $fl('mail.php?tab=inbox&sigs=1', 'user', 'Signaturen', false);
  if ($mail && pg_is_owner()) $h .= $fl('mail.php?tab=settings', 'settings', 'Einstellungen', $active === 'settings');
  return $h . '</div></nav>';
}

/* ---------------- Neue Nachrichten fürs Cockpit (Postfach + Kontaktformular) ----------------
   Das Cockpit zeigt sofort den zuletzt bekannten Stand (data/mail_preview.json) und holt
   im Hintergrund frisch vom Mailserver (action=ck_inbox) – die Seite wartet nie auf IMAP. */
const PG_MAIL_PREVIEW_FILE = __DIR__ . '/../data/mail_preview.json';
function pg_inbox_preview_cached($mb = null){
  $mb = $mb ?? pg_mb_key();
  $c = pg_load_json(PG_MAIL_PREVIEW_FILE);
  $r = $mb === 'main' ? (is_array($c) ? $c : []) : (array) ($c['mb'][$mb] ?? []);
  return ['time' => (int) ($r['time'] ?? 0), 'items' => array_values(array_filter((array) ($r['items'] ?? []), 'is_array'))];
}
function pg_inbox_preview_store($items, $mb = null){
  $mb = $mb ?? pg_mb_key();
  $keep = [];
  foreach (array_slice((array) $items, 0, 10) as $it) $keep[] = array_intersect_key((array) $it, array_flip(['uid', 'subject', 'from', 'from_email', 'date', 'seen']));
  $c = pg_load_json(PG_MAIL_PREVIEW_FILE); $c = is_array($c) ? $c : [];
  if ($mb === 'main') { $c['time'] = time(); $c['items'] = $keep; }
  else { if (!is_array($c['mb'] ?? null)) $c['mb'] = []; $c['mb'][$mb] = ['time' => time(), 'items' => $keep]; }
  pg_save_json(PG_MAIL_PREVIEW_FILE, $c);
}
// alle sichtbaren Postfächer zusammen (jede Mail weiß, aus welchem Postfach sie kommt)
function pg_inbox_preview_all($live = false, $maxAge = 60){
  $vis = pg_mailboxes_visible(); $items = []; $stale = false;
  foreach ($vis as $id => $box) {
    $c = pg_inbox_preview_cached($id);
    if (time() - $c['time'] >= $maxAge && !pg_mail_backoff($id) && pg_imap_available() && pg_mailbox_with($id, 'pg_mail_configured_recv')) {
      if (!$live) $stale = true;
      else {
        $fresh = pg_mailbox_with($id, function () use ($id) {
          $mbox = pg_imap_connect('INBOX'); if (!$mbox) { pg_mail_unread_fail($id); return null; }
          $list = pg_imap_list($mbox, 10, 0);
          $unseen = @imap_search($mbox, 'UNSEEN');
          @imap_close($mbox);
          pg_inbox_preview_store($list['items'], $id);
          pg_mail_unread_store(is_array($unseen) ? count($unseen) : 0, $id);
          return $list['items'];
        });
        if (is_array($fresh)) $c['items'] = $fresh;
      }
    }
    foreach ($c['items'] as $it) $items[] = $it + ['mb' => $id, 'mb_label' => count($vis) > 1 ? $box['label'] : ''];
  }
  return ['items' => $items, 'stale' => $stale];
}
// Gemeinsame Liste: ungelesene Mails & neue Kontaktanfragen zuerst, dann die letzten gelesenen (gedimmt)
function pg_ck_messages($mailItems, $max = 6){
  $rows = [];
  if (pg_mailbox_access()) foreach ((array) $mailItems as $m) {
    $rows[] = ['src' => 'mail', 'name' => (string) ($m['from'] ?? ''), 'email' => (string) ($m['from_email'] ?? ''), 'subject' => (string) ($m['subject'] ?? ''),
               'text' => '', 'time' => (int) ($m['date'] ?? 0), 'unread' => empty($m['seen']), 'box' => (string) ($m['mb_label'] ?? ''),
               'href' => 'mail.php?tab=inbox&mb=' . rawurlencode((string) ($m['mb'] ?? 'main')) . '&uid=' . (int) ($m['uid'] ?? 0)];
  }
  if (pg_can('contacts')) foreach ((array) pg_load_json(PG_DATA_DIR . '/contacts.json') as $i => $r) {
    if (!is_array($r)) continue;
    $rows[] = ['src' => 'contact', 'name' => (string) ($r['name'] ?? ''), 'email' => (string) ($r['email'] ?? ''), 'subject' => (string) ($r['subject'] ?? 'Kontaktanfrage'),
               'text' => (string) ($r['message'] ?? ''), 'time' => strtotime((string) ($r['date'] ?? '')) ?: 0, 'unread' => empty($r['read']), 'href' => 'index.php?view=contacts&sel=' . $i];
  }
  usort($rows, fn($a, $b) => ($b['unread'] <=> $a['unread']) ?: ($b['time'] <=> $a['time']));
  $unread = array_values(array_filter($rows, fn($r) => $r['unread']));
  $show = array_slice($unread, 0, $max);
  if (count($show) < 3) $show = array_merge($show, array_slice(array_values(array_filter($rows, fn($r) => !$r['unread'])), 0, 3 - count($show)));
  if (!$show) return '<div class="ck-msg-empty">' . pg_icon('check') . '<b>Keine Nachrichten.</b><span>Neue Mails und Kontaktanfragen erscheinen hier sofort.</span></div>';
  $h = '<ul class="ck-msgs">';
  foreach ($show as $r) {
    $nm = trim($r['name']) !== '' ? $r['name'] : ($r['email'] !== '' ? $r['email'] : 'Unbekannt');
    $ini = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $nm) ?: '?', 0, 1));
    $hue = hexdec(substr(md5(strtolower($r['email'] ?: $nm)), 0, 2)) / 255 * 360;
    $h .= '<li><a class="ck-msg' . ($r['unread'] ? ' is-new' : '') . '" href="' . pg_h($r['href']) . '">'
        . '<span class="ck-av" style="--h:' . (int) $hue . '">' . pg_h($ini) . '</span>'
        . '<span class="ck-msg-tx"><span class="ck-msg-top"><b>' . pg_h(mb_strimwidth($nm, 0, 40, '…')) . '</b>'
        . '<span class="ck-src ' . $r['src'] . '">' . ($r['src'] === 'mail' ? pg_icon('mail') . (($r['box'] ?? '') !== '' ? pg_h($r['box']) : 'Postfach') : pg_icon('inbox') . 'Kontaktformular') . '</span>'
        . '<span class="ck-msg-time">' . ($r['time'] ? pg_h(pg_time_ago($r['time'])) : '') . '</span></span>'
        . '<span class="ck-msg-sub">' . pg_h(mb_strimwidth($r['subject'] !== '' ? $r['subject'] : '(kein Betreff)', 0, 90, '…')) . '</span>'
        . ($r['text'] !== '' ? '<span class="ck-msg-pre">' . pg_h(mb_strimwidth(preg_replace('/\s+/u', ' ', $r['text']), 0, 140, '…')) . '</span>' : '')
        . '</span>' . ($r['unread'] ? '<i class="ck-dot" title="ungelesen"></i>' : '') . '</a></li>';
  }
  return $h . '</ul>';
}

/* ---------------- Benachrichtigungen (Allrounder) ---------------- */
// neue Newsletter-Anmeldungen der letzten $days Tage
function pg_subscribers_recent($days = 7){
  $n = 0; $cut = time() - $days * 86400;
  foreach ((array) pg_load_json(PG_DATA_DIR . '/subscribers.json') as $r) {
    $t = strtotime((string) ($r['date'] ?? '')); if ($t && $t >= $cut) $n++;
  }
  return $n;
}
// noch nicht freigegebene Devlog-Kommentare
function pg_comments_pending(){
  $c = pg_load_json(PG_DATA_DIR . '/comments.json');
  if (!is_array($c)) return 0;
  $n = 0; foreach ($c as $x) if (is_array($x) && empty($x['approved'])) $n++;
  return $n;
}
// noch nicht freigegebene Vorschläge
function pg_suggestions_pending(){
  $c = pg_load_json(PG_DATA_DIR . '/suggestions.json');
  if (!is_array($c)) return 0;
  $n = 0; foreach ($c as $x) if (is_array($x) && empty($x['approved'])) $n++;
  return $n;
}

/* ---------------- Key-Verteilung (Presse/Streamer-Keys) ---------------- */
const PG_KEYS_FILE = __DIR__ . '/../data/keys.json';
function pg_keys_load(){ $k = pg_load_json(PG_KEYS_FILE); return is_array($k) ? $k : []; }
function pg_keys_save($k){ return pg_save_json(PG_KEYS_FILE, array_values($k)); }
function pg_keys_stats(){
  $s = ['available' => 0, 'assigned' => 0, 'redeemed' => 0, 'total' => 0];
  foreach (pg_keys_load() as $k) { $st = $k['status'] ?? 'available'; if (isset($s[$st])) $s[$st]++; $s['total']++; }
  return $s;
}

// Sammelt alle Zähler; $total = handlungsrelevante (Mail + Kontakt + Kommentare)
function pg_notifications($liveMail = false){
  $mail     = pg_mailbox_access() ? ($liveMail ? pg_mail_unread_live() : pg_mail_unread_total()) : 0;
  $contacts = pg_can('contacts') ? pg_contacts_unread() : 0;
  $comments = pg_comments_pending();
  $subs     = pg_can('subscribers') ? pg_subscribers_recent(7) : 0;
  return ['mail' => $mail, 'contacts' => $contacts, 'comments' => $comments, 'subs' => $subs,
          'total' => $mail + $contacts + $comments];
}
// Eine Zeile im Benachrichtigungs-Panel
function pg_notif_row($href, $icon, $label, $count, $note, $key = ''){
  return '<a class="tn-row" href="' . pg_h($href) . '"' . ($key ? ' data-nrow="' . pg_h($key) . '"' : '') . '>'
       . '<span class="tn-ico">' . $icon . '</span>'
       . '<span class="tn-body"><span class="tn-label">' . pg_h($label) . '</span><span class="tn-note" data-nnote>' . pg_h($note) . '</span></span>'
       . '<span class="nbadge" data-ncount' . ($count ? '' : ' hidden') . '>' . ($count ?: '') . '</span></a>';
}
// Notiztexte je Bereich (auch vom Echtzeit-Endpoint genutzt)
function pg_notif_notes($n){
  return [
    'mail'     => $n['mail'] ? $n['mail'] . ' ungelesen' : 'Keine neuen Mails',
    'contacts' => $n['contacts'] ? $n['contacts'] . ' neu' : 'Alles gelesen',
    'subs'     => $n['subs'] ? $n['subs'] . ' neue Anmeldung' . ($n['subs'] === 1 ? '' : 'en') . ' (7 Tage)' : 'Keine neuen Anmeldungen',
    'comments' => $n['comments'] ? $n['comments'] . ' zu prüfen' : 'Nichts zu moderieren',
  ];
}
// Glocken-Menü (ersetzt das frühere Mail-Icon); aktualisiert sich per Polling
function pg_notif_bell(){
  $n = pg_notifications();
  $note = pg_notif_notes($n);
  $h = '<div class="tb-notif" data-tb-group>';
  $h .= '<button type="button" class="tb-group-btn tb-notif-btn" data-notif-bell aria-label="Benachrichtigungen" aria-expanded="false">' . pg_icon('bell')
      . '<span class="nbadge" data-notif-total' . ($n['total'] ? '' : ' hidden') . '>' . ($n['total'] ?: '') . '</span></button>';
  $h .= '<div class="tb-notif-panel"><div class="tn-head">Benachrichtigungen</div>';
  if (pg_mailbox_access()) $h .= pg_notif_row('mail.php', pg_icon('mail'), 'E-Mail-Postfach', $n['mail'], $note['mail'], 'mail');
  if (pg_can('contacts')) $h .= pg_notif_row('index.php?view=contacts', pg_icon('inbox'), 'Kontakt-Anfragen', $n['contacts'], $note['contacts'], 'contacts');
  if (pg_can('subscribers')) $h .= pg_notif_row('index.php?view=subscribers', pg_icon('mega'), 'Newsletter', 0, $note['subs'], 'subs');
  $h .= pg_notif_row('index.php?view=comments', pg_icon('chat'), 'Kommentare', $n['comments'], $note['comments'], 'comments');
  $h .= '</div></div>';
  return $h;
}

/* ---------------- Schnellantwort-Vorlagen (Textbausteine) ---------------- */
const PG_TEMPLATES_FILE = __DIR__ . '/../data/templates.json';
function pg_templates_load(){
  $t = pg_load_json(PG_TEMPLATES_FILE);
  return is_array($t) ? array_values(array_filter($t, fn($x) => is_array($x))) : [];
}
function pg_templates_save($list){ return pg_save_json(PG_TEMPLATES_FILE, array_values($list)); }

/* ---------------- Planungs-Board (Kanban) ---------------- */
const PG_BOARD_FILE = __DIR__ . '/../data/board.json';
function pg_board_default_columns(){
  // feste IDs, damit Spalten auch ohne gespeicherte Datei zwischen Requests übereinstimmen
  return [
    ['id' => 'col_ideen',    'title' => '💡 Ideen',     'cards' => []],
    ['id' => 'col_geplant',  'title' => '📋 Geplant',   'cards' => []],
    ['id' => 'col_arbeit',   'title' => '🔨 In Arbeit', 'cards' => []],
    ['id' => 'col_erledigt', 'title' => '✅ Erledigt',  'cards' => []],
  ];
}
// Mehrere Boards (pro Spiel/Thema). Migriert das alte Einzel-Board automatisch.
function pg_board_load(){
  $b = pg_load_json(PG_BOARD_FILE);
  if (!is_array($b)) $b = [];
  if (isset($b['columns']) && !isset($b['boards'])) { // altes Format -> wrappen
    $b = ['boards' => [['id' => 'board_main', 'name' => 'Allgemein', 'game' => '', 'columns' => $b['columns']]]];
  }
  if (empty($b['boards']) || !is_array($b['boards'])) {
    $b = ['boards' => [['id' => 'board_main', 'name' => 'Allgemein', 'game' => '', 'columns' => pg_board_default_columns()]]];
  }
  return $b;
}
function pg_board_save($b){ return pg_save_json(PG_BOARD_FILE, $b); }
function pg_board_id($prefix){ return $prefix . '_' . bin2hex(random_bytes(4)); }
// Index eines Boards anhand der ID (oder 0 als Fallback)
function pg_board_index(&$b, $id){
  foreach ($b['boards'] as $i => $bd) if (($bd['id'] ?? '') === $id) return $i;
  return 0;
}
function pg_board_colors(){
  return ['' => 'Keine', '#ff7d1a' => 'Orange', '#5bc0eb' => 'Blau', '#5fd07f' => 'Grün',
          '#c678dd' => 'Lila', '#e06c75' => 'Rot', '#e6c07b' => 'Gelb'];
}
function pg_board_priorities(){
  return ['' => 'Keine', 'high' => '🔴 Hoch', 'med' => '🟡 Mittel', 'low' => '🟢 Niedrig'];
}
// mögliche Zuständige = alle Admin-Zugänge (E-Mail => Anzeigename)
function pg_board_assignees(){
  $out = ['' => '— niemand —'];
  foreach (pg_users_load() as $u) {
    $em = $u['email'] ?? ''; if ($em === '') continue;
    $out[$em] = ($u['name'] ?? '') !== '' ? $u['name'] : $em;
  }
  return $out;
}
function pg_board_initials($name){
  $name = trim((string) $name); if ($name === '') return '?';
  $parts = preg_split('/[\s@.]+/', $name);
  $a = mb_substr($parts[0] ?? '', 0, 1);
  $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
  return mb_strtoupper($a . $b);
}

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
function pg_totp_verify($secret, $code, $window = 4, $skew = 0){
  // window=4 → ±2 Minuten Toleranz; $skew = gespeicherte Uhr-Abweichung (in 30s-Schritten)
  $code = preg_replace('/\D/', '', (string) $code);
  if (strlen($code) !== 6 || $secret === '') return false;
  $slice = (int) floor(time() / 30) + (int) $skew;
  for ($i = -$window; $i <= $window; $i++) if (hash_equals(pg_totp_code($secret, $slice + $i), $code)) return true;
  return false;
}
/* Findet die Uhr-Abweichung (in 30s-Schritten) zwischen Server und App.
   Großes Fenster (~±10 Min) für die Erst-Einrichtung, damit 2FA auch bei
   falsch gehender Server- oder Handy-Uhr eingerichtet werden kann. */
function pg_totp_find_skew($secret, $code, $window = 20){
  $code = preg_replace('/\D/', '', (string) $code);
  if (strlen($code) !== 6 || $secret === '') return null;
  $slice = (int) floor(time() / 30);
  for ($i = -$window; $i <= $window; $i++) if (hash_equals(pg_totp_code($secret, $slice + $i), $code)) return $i;
  return null;
}
function pg_otpauth_uri($secret, $account, $issuer = 'PLANIGAMES'){
  // Label mit LITERALEM Doppelpunkt (issuer:account) – maximal kompatibel.
  // Manche Authenticator-Apps lehnen einen url-kodierten ":" (%3A) ab.
  return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
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

/* ---------------- Vertraute Geräte (2FA überspringen) ----------------
   Nur nach erfolgreicher 2FA-Anmeldung setzbar. Das Cookie ersetzt NICHT
   das Passwort – es überspringt nur den zweiten Faktor auf diesem Gerät. */
const PG_TRUST_COOKIE = 'pg_trust';
function pg_trusted_prune($list){
  $now = time();
  return array_values(array_filter((array) $list, fn($d) => is_array($d) && (int) ($d['exp'] ?? 0) > $now));
}
// Gerät merken: Zufallstoken erzeugen, Hash beim User speichern, Cookie setzen.
function pg_trusted_remember($email, $days = 30){
  $u = pg_user_find($email); if (!$u) return;
  $token = bin2hex(random_bytes(32));
  $exp = time() + $days * 86400;
  $list = pg_trusted_prune($u['trusted'] ?? []);
  $list[] = ['hash' => hash('sha256', $token), 'exp' => $exp, 'created' => date('c'),
             'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120)];
  if (count($list) > 10) $list = array_slice($list, -10);   // höchstens 10 Geräte je Konto
  pg_user_update($email, ['trusted' => $list]);
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie(PG_TRUST_COOKIE, $email . ':' . $token, [
    'expires' => $exp, 'path' => '/admin/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
}
// Ist DIESES Gerät für genau diesen User vertraut? (Cookie gültig & nicht abgelaufen)
function pg_trusted_current($u){
  if (empty($u) || !is_array($u)) return false;
  $raw = (string) ($_COOKIE[PG_TRUST_COOKIE] ?? '');
  $pos = strrpos($raw, ':'); if ($pos === false) return false;
  $email = substr($raw, 0, $pos); $token = substr($raw, $pos + 1);
  if ($token === '' || strcasecmp($email, $u['email'] ?? '') !== 0) return false;
  $hash = hash('sha256', $token);
  foreach (pg_trusted_prune($u['trusted'] ?? []) as $d) {
    if (hash_equals((string) ($d['hash'] ?? ''), $hash)) return true;
  }
  return false;
}
// Vertrauen dieses Geräts widerrufen (Cookie löschen; der gespeicherte Hash läuft ohnehin ab).
function pg_trusted_forget_current(){
  setcookie(PG_TRUST_COOKIE, '', ['expires' => time() - 3600, 'path' => '/admin/']);
}

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
  return ['games' => 'games', 'patchnotes' => 'posts', 'team' => 'members'][$collection] ?? null;
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
  $newSlugs = []; $newTitles = [];
  foreach (($newData[$field] ?? []) as $it) {
    if (!is_array($it)) continue;
    if (!empty($it['slug'])) $newSlugs[$it['slug']] = true;
    $t = mb_strtolower(trim((string) ($it['title'] ?? '')));
    if ($t !== '') $newTitles[$t] = true;
  }
  foreach (($oldData[$field] ?? []) as $it) {
    if (!is_array($it)) continue;
    $sl = $it['slug'] ?? '';
    $t = mb_strtolower(trim((string) ($it['title'] ?? '')));
    // nur wirklich entfernte Einträge sichern – nicht solche, deren Adresse/Slug sich nur geändert hat
    if ($sl !== '' && empty($newSlugs[$sl]) && ($t === '' || empty($newTitles[$t]))) pg_trash_add($collection, $field, $it);
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
  if ($name === 'backups') return false;
  return (bool) preg_match('/^[A-Za-z0-9_.\-]+\.json$/', $name) || $name === 'secret.php';
}

/* ---------------- Automatische Backups (cron-frei) ---------------- */
const PG_BACKUP_DIR = __DIR__ . '/../data/backups';
const PG_BACKUP_KEEP_AUTO = 14;       // wie viele tägliche Auto-Backups behalten
const PG_BACKUP_KEEP_MANUAL = 10;     // wie viele manuelle Schnappschüsse behalten
const PG_BACKUP_INTERVAL = 82800;     // ~23 h – damit ein täglicher Besuch reicht
// Backup-Bündel (alle erlaubten data-Dateien) als Array bauen
function pg_backup_build(){
  $bundle = ['_meta' => ['app' => 'PLANIGAMES', 'type' => 'backup', 'version' => 1, 'created' => date('c'),
    'host' => preg_replace('/[^a-z0-9.\-:]/i', '', $_SERVER['HTTP_HOST'] ?? 'auto')], 'files' => []];
  foreach (pg_backup_filenames() as $f) $bundle['files'][$f] = file_get_contents(PG_DATA_DIR . '/' . $f);
  return $bundle;
}
// gespeicherte Backups auflisten (neueste zuerst)
function pg_backup_list(){
  $out = [];
  if (is_dir(PG_BACKUP_DIR)) {
    foreach (scandir(PG_BACKUP_DIR) as $f) {
      if (substr($f, -5) !== '.json') continue;
      $p = PG_BACKUP_DIR . '/' . $f;
      $kind = strncmp($f, 'auto-', 5) === 0 ? 'auto' : (strncmp($f, 'safety-', 7) === 0 ? 'safety' : 'manual');
      $out[] = ['name' => $f, 'size' => (int) @filesize($p), 'time' => (int) @filemtime($p), 'auto' => $kind === 'auto', 'kind' => $kind];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
  }
  return $out;
}
function pg_backup_last_auto_time(){
  foreach (pg_backup_list() as $b) if ($b['auto']) return $b['time'];
  return 0;
}
function pg_backup_name_ok($name){ return $name === basename($name) && (bool) preg_match('/^(auto|manual|safety)-[0-9\-]+\.json$/', $name); }
// Snapshot schreiben + rotieren; gibt Dateinamen zurück
function pg_backup_write($prefix = 'auto'){
  if (!is_dir(PG_BACKUP_DIR)) @mkdir(PG_BACKUP_DIR, 0775, true);
  $ht = PG_BACKUP_DIR . '/.htaccess';
  if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nOptions -Indexes\n");
  $name = ($prefix === 'manual' ? 'manual-' . date('Y-m-d-His') : ($prefix === 'safety' ? 'safety-' . date('Y-m-d-His') : 'auto-' . date('Y-m-d'))) . '.json';
  $json = json_encode(pg_backup_build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $ok = @file_put_contents(PG_BACKUP_DIR . '/' . $name, $json) !== false;
  pg_backup_rotate();
  return $ok ? $name : false;
}
// pro Typ nur die jeweils neuesten behalten
function pg_backup_rotate(){
  $auto = []; $manual = []; $safety = [];
  foreach (pg_backup_list() as $b) { if ($b['kind'] === 'auto') $auto[] = $b; elseif ($b['kind'] === 'safety') $safety[] = $b; else $manual[] = $b; }
  foreach (array_slice($auto, PG_BACKUP_KEEP_AUTO) as $b) @unlink(PG_BACKUP_DIR . '/' . $b['name']);
  foreach (array_slice($manual, PG_BACKUP_KEEP_MANUAL) as $b) @unlink(PG_BACKUP_DIR . '/' . $b['name']);
  foreach (array_slice($safety, 8) as $b) @unlink(PG_BACKUP_DIR . '/' . $b['name']);   // Sicherungen vor Umzügen: 8 behalten
}
// beim Admin-Aufruf: fällig? dann sichern (täglicher Dateiname => idempotent bei Parallelzugriff)
function pg_backup_auto_maybe(){
  if (pg_backup_last_auto_time() < time() - PG_BACKUP_INTERVAL) pg_backup_write('auto');
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

/* ---------------- Mehrere Postfächer ----------------
   Das bisherige Postfach (Einstellungen oben in mail.json) ist das Hauptpostfach (z. B. hello@).
   Persönliche Postfächer stehen in mail.json unter „mailboxes“ und sind nur für die eingetragenen
   Admins sichtbar. pg_mail_config() liefert immer die Daten des gerade aktiven Postfachs. */
function pg_mail_config(){
  $c = pg_mail_config_main();
  $id = (string) ($GLOBALS['pg_mb_active'] ?? 'main');
  if ($id === 'main' || !($mb = pg_mailbox_find($id))) return $c;
  $c['from_email'] = (string) $mb['email'];
  $c['from_name'] = trim((string) ($mb['from_name'] ?? '')) !== '' ? (string) $mb['from_name']
                  : (trim((string) ($mb['label'] ?? '')) !== '' ? $mb['label'] . ' | ' . (trim($c['from_name']) ?: 'PLANIGAMES') : $c['from_name']);
  $c['imap_user'] = (string) (($mb['imap_user'] ?? '') ?: $mb['email']); $c['imap_pass'] = (string) ($mb['pass'] ?? '');
  $c['smtp_user'] = (string) (($mb['smtp_user'] ?? '') ?: $mb['email']); $c['smtp_pass'] = (string) ($mb['pass'] ?? '');
  if (empty($mb['same_server'])) foreach (['imap_host', 'imap_port', 'imap_secure', 'smtp_host', 'smtp_port', 'smtp_secure'] as $k) if (isset($mb[$k]) && $mb[$k] !== '') $c[$k] = $mb[$k];
  return $c;
}
function pg_mailboxes_all(){
  $raw = pg_load_json(PG_MAIL_FILE);
  return array_values(array_filter((array) ($raw['mailboxes'] ?? []), fn($m) => is_array($m) && !empty($m['id']) && filter_var($m['email'] ?? '', FILTER_VALIDATE_EMAIL)));
}
function pg_mailbox_find($id){ foreach (pg_mailboxes_all() as $m) if ($m['id'] === $id) return $m; return null; }
function pg_mailboxes_save($list){
  $raw = pg_load_json(PG_MAIL_FILE); $raw = is_array($raw) ? $raw : [];
  $raw['mailboxes'] = array_values($list);
  return pg_save_json(PG_MAIL_FILE, $raw);
}
// Postfächer, die der angemeldete Admin sehen darf: Hauptpostfach (Recht „E-Mail-Postfach“) + eigene
function pg_mailboxes_visible($fresh = false){
  static $cache = null;
  if ($cache !== null && !$fresh) return $cache;
  $out = [];
  if (!pg_logged_in() || !pg_current_user()) return $cache = $out;
  if (pg_can('mail')) {
    $main = pg_mail_config_main();
    $out['main'] = ['id' => 'main', 'label' => 'Hauptpostfach', 'email' => trim((string) ($main['from_email'] ?: $main['imap_user']))];
  }
  $me = strtolower(pg_current_email());
  foreach (pg_mailboxes_all() as $m)
    if ($me !== '' && in_array($me, array_map('strtolower', (array) ($m['owners'] ?? [])), true))
      $out[$m['id']] = ['id' => $m['id'], 'label' => trim((string) ($m['label'] ?? '')) ?: (string) $m['email'], 'email' => (string) $m['email']];
  return $cache = $out;
}
// darf der Admin überhaupt ein Postfach sehen? (Hauptpostfach oder ein eigenes)
function pg_mailbox_access(){ return (bool) pg_mailboxes_visible(); }
// aktives Postfach festlegen (?mb=… oder zuletzt benutztes)
function pg_mailbox_activate($want = null){
  $vis = pg_mailboxes_visible();
  $id = $want !== null && $want !== '' ? $want : (string) ($_SESSION['pg_mb'] ?? 'main');
  if (!isset($vis[$id])) $id = isset($vis['main']) ? 'main' : (string) (array_key_first($vis) ?? 'main');
  $_SESSION['pg_mb'] = $id; $GLOBALS['pg_mb_active'] = $id;
  return $id;
}
function pg_mb_key(){ return (string) ($GLOBALS['pg_mb_active'] ?? 'main'); }
// kurz in einem anderen Postfach arbeiten (z. B. Ungelesene zählen) und danach zurück
function pg_mailbox_with($id, callable $fn){
  $prev = $GLOBALS['pg_mb_active'] ?? 'main';
  $GLOBALS['pg_mb_active'] = $id;
  try { return $fn(); } finally { $GLOBALS['pg_mb_active'] = $prev; }
}

function pg_mail_config_main(){
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

/* ---- Signaturen: beliebig viele (Studio + pro Person), Standard pro Admin ----
   Gespeichert in mail.json unter „signatures“. Ältere Installationen haben genau eine
   Signatur in sig_* – die erscheint automatisch als „PLANIGAMES“ (ohne etwas umzuschreiben). */
function pg_mail_signatures(){
  $c = pg_mail_config();
  if (isset($c['signatures']) && is_array($c['signatures'])) {
    $out = [];
    foreach ($c['signatures'] as $x) if (is_array($x) && !empty($x['id'])) $out[] = $x + ['label' => '', 'name' => '', 'role' => '', 'phone' => '', 'email' => '', 'closing' => ''];
    return $out;
  }
  $legacy = ['id' => 'studio', 'label' => 'PLANIGAMES', 'name' => trim((string) ($c['sig_name'] ?? '')), 'role' => trim((string) ($c['sig_role'] ?? '')),
             'phone' => trim((string) ($c['sig_phone'] ?? '')), 'email' => '', 'closing' => trim((string) ($c['signature'] ?? ''))];
  return ($legacy['name'] . $legacy['role'] . $legacy['phone'] . $legacy['closing']) !== '' ? [$legacy] : [];
}
function pg_mail_signature_find($id){
  foreach (pg_mail_signatures() as $x) if ((string) $x['id'] === (string) $id) return $x;
  return null;
}
// Standard-Signatur für den angemeldeten Admin ('' = keine)
function pg_mail_signature_default(){
  $sigs = pg_mail_signatures();
  if (!$sigs) return '';
  $u = pg_current_user();
  if ($u && array_key_exists('mail_sig', $u)) {
    if ((string) $u['mail_sig'] === '') return '';
    if (pg_mail_signature_find($u['mail_sig'])) return (string) $u['mail_sig'];
  }
  // noch nichts gewählt: Profil mit derselben E-Mail bzw. demselben Namen wie die Anmeldung
  if ($u) {
    $ue = strtolower(trim((string) ($u['email'] ?? ''))); $un = mb_strtolower(trim((string) ($u['name'] ?? '')));
    foreach ($sigs as $x) if ($ue !== '' && strtolower(trim((string) $x['email'])) === $ue) return (string) $x['id'];
    foreach ($sigs as $x) if ($un !== '' && (string) $x['id'] !== 'studio' && mb_strtolower(trim((string) $x['name'])) === $un) return (string) $x['id'];
  }
  $c = pg_mail_config();
  if (($c['sig_enabled'] ?? true) === false && !isset($c['signatures'])) return '';
  if (!empty($c['sig_default']) && pg_mail_signature_find($c['sig_default'])) return (string) $c['sig_default'];
  return (string) $sigs[0]['id'];
}
// Signaturen speichern (nur dieser Teil von mail.json wird verändert)
function pg_mail_signatures_save(array $list){
  $clean = []; $seen = [];
  foreach ($list as $x) {
    if (!is_array($x)) continue;
    $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($x['id'] ?? '')) ?: 'sig_' . bin2hex(random_bytes(3));
    if (isset($seen[$id])) $id .= '_' . bin2hex(random_bytes(2));
    $seen[$id] = true;
    $row = ['id' => $id];
    foreach (['label' => 40, 'name' => 80, 'role' => 120, 'phone' => 40, 'email' => 120, 'closing' => 200] as $k => $max) $row[$k] = mb_substr(trim((string) ($x[$k] ?? '')), 0, $max);
    if ($row['email'] !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) $row['email'] = '';
    if ($row['label'] === '') $row['label'] = $row['name'] !== '' ? explode(' ', $row['name'])[0] : 'Signatur';
    if ($row['name'] . $row['role'] . $row['closing'] === '') continue;
    $clean[] = $row;
  }
  $raw = pg_load_json(PG_MAIL_FILE); if (!is_array($raw)) $raw = [];
  $raw['signatures'] = $clean;
  return pg_save_json(PG_MAIL_FILE, $raw) ? $clean : false;
}
// Standard-Signatur des angemeldeten Admins merken (users.json, Feld „mail_sig“)
function pg_mail_signature_set_default($id){
  $me = pg_current_email(); if (!$me) return false;
  $users = pg_users_load(); $hit = false;
  foreach ($users as $k => $u) if (strtolower((string) ($u['email'] ?? '')) === strtolower($me)) { $users[$k]['mail_sig'] = (string) $id; $hit = true; }
  return $hit && pg_users_save($users);
}

/* Absender eines Profils: [Name, Adresse, Antwort-Adresse, persönlich?]
   - Studio-Profil (oder ohne eigene Adresse): Studio-Adresse
   - Person mit Adresse: „Name | PLANIGAMES“ <persönliche Adresse>, Antworten gehen an sie */
function pg_mail_sender($sig){
  [$studioName, $studioEmail] = pg_mail_from();
  if (!is_array($sig)) $sig = $sig === '' || $sig === null ? null : pg_mail_signature_find($sig);
  if (!$sig || (string) ($sig['id'] ?? '') === 'studio') return [$studioName, $studioEmail, $studioEmail, false];
  $name = trim((string) ($sig['name'] ?? ''));
  $label = $name !== '' ? $name . ' | ' . $studioName : $studioName;
  $email = trim((string) ($sig['email'] ?? ''));
  if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) return [$label, $email, $email, true];
  return [$label, $studioEmail, $studioEmail, false];
}

/* Signatur eines persönlichen Postfachs: fest eingestellt (sig = ID, „-“ = keine) oder automatisch passend –
   gleiche E-Mail-Adresse, Name einer eingetragenen Person, Vorname = Name im Umschalter bzw. Adresse.
   Rückgabe: Signatur-ID, '' (keine) oder null (nichts Passendes → Standard des Admins). */
function pg_mailbox_signature($mb){
  if (!is_array($mb)) return null;
  $want = (string) ($mb['sig'] ?? '');
  if ($want === '-') return '';
  if ($want !== '' && pg_mail_signature_find($want)) return $want;
  $sigs = array_values(array_filter(pg_mail_signatures(), fn($x) => (string) ($x['id'] ?? '') !== 'studio'));
  if (!$sigs) return null;
  $email = strtolower(trim((string) ($mb['email'] ?? '')));
  foreach ($sigs as $x) if ($email !== '' && strtolower(trim((string) ($x['email'] ?? ''))) === $email) return (string) $x['id'];
  $owners = array_map('strtolower', (array) ($mb['owners'] ?? []));
  $names = [];
  foreach (pg_users_load() as $u) if (in_array(strtolower((string) ($u['email'] ?? '')), $owners, true) && trim((string) ($u['name'] ?? '')) !== '') $names[] = mb_strtolower(trim((string) $u['name']));
  foreach ($sigs as $x) if (in_array(mb_strtolower(trim((string) ($x['name'] ?? ''))), $names, true)) return (string) $x['id'];
  $first = fn($x) => pg_slugify(explode(' ', trim((string) ($x['name'] ?? '')))[0] ?? '');
  foreach ([pg_slugify((string) ($mb['label'] ?? '')), pg_slugify((string) strstr($email, '@', true))] as $key)
    if ($key !== '') foreach ($sigs as $x) if ($first($x) === $key) return (string) $x['id'];
  return null;
}
// Vorauswahl „Von“/Signatur beim Verfassen im aktiven Postfach
function pg_mail_signature_default_active(){
  if (pg_mb_key() !== 'main' && ($mb = pg_mailbox_find(pg_mb_key()))) { $s = pg_mailbox_signature($mb); if ($s !== null) return $s; }
  return pg_mail_signature_default();
}

/* Absender beim Verfassen im aktiven Postfach: im persönlichen Postfach kommt jede Mail
   von dessen Adresse (sonst lehnt der Mailserver sie ab) – die Signatur bestimmt nur den Namen. */
function pg_mail_sender_active($sig){
  if (pg_mb_key() === 'main') return pg_mail_sender($sig);
  [$mbName, $mbEmail] = pg_mail_from();
  if (!is_array($sig)) $sig = $sig === '' || $sig === null ? null : pg_mail_signature_find($sig);
  $studio = trim((string) pg_mail_config_main()['from_name']) ?: 'PLANIGAMES';
  $person = $sig && (string) ($sig['id'] ?? '') !== 'studio' ? trim((string) ($sig['name'] ?? '')) : '';
  return [$person !== '' ? $person . ' | ' . $studio : $mbName, $mbEmail, $mbEmail, true];
}
// Einträge für „Von“ (Verfassen & Signaturen-Dialog): [['id'=>…, 'text'=>…], …]
function pg_mail_from_options($sigs = null){
  $sigs = $sigs ?? pg_mail_signatures();
  [$stN, $stE] = pg_mail_from();
  $opts = array_filter($sigs, fn($x) => (string) ($x['id'] ?? '') === 'studio') ? [] : [['id' => '', 'text' => $stN . ' · ' . $stE]];
  foreach ($sigs as $x) {
    [$sn, $se] = pg_mail_sender_active($x);
    $isStudio = (string) $x['id'] === 'studio';
    $opts[] = ['id' => (string) $x['id'], 'text' => (!$isStudio && ($x['name'] ?? '') !== '' ? $x['name'] : (($x['label'] ?? '') ?: $sn)) . ' · ' . $se];
  }
  return $opts;
}

/* Gebrandete Signatur (HTML) – $sig = Signatur-Array, ID oder null (= Standard des Admins). */
function pg_mail_signature($sig = null){
  if ($sig === null) $sig = pg_mail_signature_default();
  if (!is_array($sig)) $sig = $sig === '' ? null : pg_mail_signature_find($sig);
  if (!$sig) return '';
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $name    = trim((string) ($sig['name'] ?? ''));
  $role    = trim((string) ($sig['role'] ?? ''));
  $phone   = trim((string) ($sig['phone'] ?? ''));
  $closing = trim((string) ($sig['closing'] ?? ''));
  $email   = trim((string) ($sig['email'] ?? '')) ?: trim((string) ($s['email'] ?? ''));
  if (pg_mb_key() !== 'main' && ($mbx = pg_mailbox_find(pg_mb_key()))) $email = (string) $mbx['email'];   // persönliches Postfach: dessen Adresse
  $siteUrl = pg_site_url();
  $siteTxt = preg_replace('#^https?://#', '', $siteUrl);
  $socials = array_filter(is_array($s['socials'] ?? null) ? $s['socials'] : [],
             fn($x) => !empty($x['url']) && $x['url'] !== '#' && !empty($x['label']));
  if ($name === '' && $role === '' && $closing === '') return '';
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
  // Kein Logo in der Signatur – das Logo steht bereits im Mail-Kopf.
  $out = '<div style="margin-top:28px">';
  if ($closing !== '') $out .= '<div style="color:#c7c7d1;font-size:14px;line-height:1.6;margin-bottom:16px">' . nl2br(pg_h($closing)) . '</div>';
  $out .= '<div style="width:42px;height:2px;background:linear-gradient(110deg,#e6a015,#ff7d1a);margin:0 0 16px"></div>' . $lines . '</div>';
  return $out;
}

/* ---- Formatierte Nachrichten: nur erlaubte Auszeichnungen, Stile werden fest gesetzt ---- */
function pg_mail_sanitize_html($html){
  $html = (string) $html;
  if (trim($html) === '') return '';
  if (!class_exists('DOMDocument')) return nl2br(pg_h(strip_tags($html)));
  $doc = new DOMDocument();
  $prev = libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="pg-mail-root">' . $html . '</div></body></html>', LIBXML_NONET);
  libxml_clear_errors(); libxml_use_internal_errors($prev);
  $root = $doc->getElementById('pg-mail-root');
  if (!$root) return '';
  // Text ohne Absatz (z. B. erste Zeile im Editor) in Absätze fassen
  $out = ''; $inline = '';
  $flush = function () use (&$out, &$inline) {
    if (trim(strip_tags($inline, '<br>')) !== '' && trim(preg_replace('#<br>#', '', $inline)) !== '') $out .= '<p style="margin:0 0 12px">' . trim($inline) . '</p>';
    $inline = '';
  };
  foreach ($root->childNodes as $ch) {
    $h = pg_mail_clean_node($ch);
    if (preg_match('#^<(p|h3|ul|ol|blockquote|hr)[\s>]#', $h)) { $flush(); $out .= $h; } else $inline .= $h;
  }
  $flush();
  // leere Absätze am Ende entfernen
  return preg_replace('#(<p style="[^"]*"><br></p>\s*)+$#', '', trim($out));
}
function pg_mail_clean_children($node){
  $out = '';
  foreach ($node->childNodes as $ch) $out .= pg_mail_clean_node($ch);
  return $out;
}
function pg_mail_clean_node($n){
  if ($n->nodeType === XML_TEXT_NODE || $n->nodeType === XML_CDATA_SECTION_NODE) return htmlspecialchars($n->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if ($n->nodeType !== XML_ELEMENT_NODE) return '';
  $tag = strtolower($n->nodeName);
  if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'head', 'title', 'meta', 'link', 'svg', 'math', 'template', 'noscript', 'form', 'input', 'button', 'select', 'textarea', 'img', 'video', 'audio', 'canvas'], true)) return '';
  $inner = pg_mail_clean_children($n);
  $st = [
    'p' => 'margin:0 0 12px', 'h3' => 'margin:18px 0 8px;font-size:18px;line-height:1.35;color:#ffffff',
    'ul' => 'margin:0 0 12px;padding-left:22px', 'ol' => 'margin:0 0 12px;padding-left:22px', 'li' => 'margin:0 0 4px',
    'blockquote' => 'margin:0 0 12px;padding:4px 0 4px 14px;border-left:3px solid #ff7d1a;color:#b6b6c0',
  ];
  switch ($tag) {
    case 'b': case 'strong': return $inner === '' ? '' : '<strong>' . $inner . '</strong>';
    case 'i': case 'em': return $inner === '' ? '' : '<em>' . $inner . '</em>';
    case 'u': return $inner === '' ? '' : '<u>' . $inner . '</u>';
    case 's': case 'strike': case 'del': return $inner === '' ? '' : '<s>' . $inner . '</s>';
    case 'br': return '<br>';
    case 'hr': return '<hr style="border:0;border-top:1px solid rgba(255,255,255,.14);margin:18px 0">';
    case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
      return trim(strip_tags($inner)) === '' ? '' : '<h3 style="' . $st['h3'] . '">' . $inner . '</h3>';
    case 'ul': case 'ol': case 'li': case 'blockquote':
      return '<' . $tag . ' style="' . $st[$tag] . '">' . $inner . '</' . $tag . '>';
    case 'a':
      $href = trim((string) $n->getAttribute('href'));
      if (!preg_match('#^(https?://|mailto:)#i', $href)) return $inner;
      return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" style="color:#ff8a2b">' . $inner . '</a>';
    case 'p': case 'div':
      // Block mit Block-Inhalt nicht verschachteln
      if (preg_match('#<(p|ul|ol|h3|blockquote|hr)[\s>]#', $inner)) return $inner;
      return '<p style="' . $st['p'] . '">' . ($inner === '' ? '<br>' : $inner) . '</p>';
    default:
      return $inner;   // span, font, … → nur Inhalt behalten
  }
}
// Text-Fassung aus (beliebigem) HTML – für den Text-Teil jeder Mail (besser für Spamfilter)
function pg_mail_html_to_text($html){
  $html = (string) $html;
  if (trim($html) === '') return '';
  if (!class_exists('DOMDocument')) return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  $doc = new DOMDocument();
  $prev = libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8">' . (stripos($html, '<body') === false ? '<html><body>' . $html . '</body></html>' : $html), LIBXML_NONET);
  libxml_clear_errors(); libxml_use_internal_errors($prev);
  $walk = function ($n) use (&$walk) {
    if ($n->nodeType === XML_TEXT_NODE) return preg_replace('/\s+/u', ' ', $n->nodeValue);
    if ($n->nodeType !== XML_ELEMENT_NODE) return '';
    $tag = strtolower($n->nodeName);
    if (in_array($tag, ['script', 'style', 'head', 'title'], true)) return '';
    if (preg_match('/display\s*:\s*none/i', (string) $n->getAttribute('style'))) return '';
    $in = '';
    foreach ($n->childNodes as $c) $in .= $walk($c);
    switch ($tag) {
      case 'br': return "\n";
      case 'li': return "\n• " . trim($in);
      case 'a':
        $href = trim((string) $n->getAttribute('href')); $t = trim($in);
        if ($href === '' || stripos($href, 'mailto:') === 0) return $t;
        return $t === '' || $t === $href ? $href : $t . ' (' . $href . ')';
      case 'p': case 'div': case 'h1': case 'h2': case 'h3': case 'h4': case 'tr': case 'table': case 'ul': case 'ol': case 'section':
        return "\n" . trim($in) . "\n";
      case 'blockquote': return "\n> " . str_replace("\n", "\n> ", trim($in)) . "\n";
      case 'td': return ' ' . $in . ' ';
      case 'hr': return "\n----\n";
      default: return $in;
    }
  };
  $body = $doc->getElementsByTagName('body')->item(0) ?: $doc->documentElement;
  $t = $walk($body);
  $t = preg_replace("/[ \t]+/u", ' ', $t);
  $t = preg_replace("/ *\n */u", "\n", $t);
  $t = preg_replace("/\n{3,}/u", "\n\n", $t);
  return trim($t);
}
/* Nachrichtenteil als HTML: formatiertes HTML (Editor) oder Klartext (Altbestand/Vorlagen) */
function pg_mail_message_html($message, $isHtml = false){
  $out = $isHtml ? pg_mail_sanitize_html($message) : nl2br(pg_h($message));
  return pg_mail_expand_games($out);                              // [[spiel:slug]] → Spiel-Karte mit Wishlist-Button
}

/* ---------------- Spiel-Karte für E-Mails (Cover, Name, Kurztext, Wishlist-Button) ----------------
   Im Editor steht ein Platzhalter [[spiel:slug]]; beim Bauen der Mail wird daraus eine
   mailtaugliche Karte (Tabellen-Layout, absolute Bild-Links, kein SVG – Gmail & Outlook zeigen sie korrekt). */
function pg_mail_game_info($slug){
  foreach (pg_games_data('de')['games'] as $g) if (is_array($g) && strtolower((string) ($g['slug'] ?? '')) === strtolower((string) $slug)) return $g;
  return null;
}
function pg_mail_abs_url($p){
  $p = trim(explode('#', (string) $p)[0]);
  if ($p === '') return '';
  if (preg_match('#^https?://#i', $p)) return $p;
  return pg_site_url() . '/' . ltrim($p, '/');
}
function pg_mail_game_card($slug){
  $g = pg_mail_game_info($slug);
  if (!$g) return '';
  $site = pg_site_url();
  $title = (string) ($g['title'] ?? $slug);
  $page = $site . '/game.php?slug=' . rawurlencode((string) $g['slug']);
  $wish = trim((string) ($g['wishlistUrl'] ?? ''));
  $isSteam = $wish !== '' && stripos($wish, 'steampowered.com') !== false;
  $btnUrl = $wish !== '' ? $wish : $page;
  $btnLabel = $isSteam ? 'Jetzt auf Steam wunschlisten' : ($wish !== '' ? 'Jetzt auf die Wunschliste' : 'Zum Spiel');
  $acc = preg_match('/^#[0-9a-f]{6}$/i', (string) ($g['accent'] ?? '')) ? strtolower($g['accent']) : '#ff7d1a';
  [$r, $gg, $b] = sscanf($acc, '#%02x%02x%02x');
  $lum = (0.2126 * $r + 0.7152 * $gg + 0.0722 * $b) / 255;
  $ink = $lum > 0.55 ? '#14100c' : '#ffffff';
  $cover = pg_mail_abs_url($g['cover'] ?? '');
  $logo = pg_mail_abs_url($g['logo'] ?? '');
  if ($logo !== '' && preg_match('/\.svg(\?|$)/i', $logo)) $logo = '';   // SVG zeigen viele Mailprogramme nicht an
  $tag = trim((string) ($g['tagline'] ?? ''));
  $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border-collapse:separate;border-radius:16px;overflow:hidden;background:#0b0b0e;border:1px solid #26262c">';
  if ($cover !== '') $h .= '<tr><td style="padding:0;line-height:0"><a href="' . pg_h($page) . '" style="text-decoration:none"><img src="' . pg_h($cover) . '" width="600" alt="' . pg_h($title) . '" style="display:block;width:100%;max-width:600px;height:auto;border:0"></a></td></tr>';
  $h .= '<tr><td style="padding:20px 22px 22px;font-family:Arial,Helvetica,sans-serif">'
      . ($logo !== '' ? '<img src="' . pg_h($logo) . '" height="44" alt="' . pg_h($title) . '" style="display:block;height:44px;width:auto;max-width:260px;border:0;margin:0 0 10px">'
                      : '<div style="font-size:22px;line-height:1.2;font-weight:800;color:#ffffff;letter-spacing:.02em;margin:0 0 6px">' . pg_h($title) . '</div>')
      . ($tag !== '' ? '<div style="color:#b9b9c4;font-size:14px;line-height:1.6;margin:0 0 16px">' . pg_h($tag) . '</div>' : '<div style="height:10px"></div>')
      . '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate"><tr><td style="border-radius:999px;background:' . $acc . '">'
      . '<a href="' . pg_h($btnUrl) . '" style="display:inline-block;padding:13px 26px;color:' . $ink . ';font-weight:700;font-size:15px;text-decoration:none;border-radius:999px;font-family:Arial,Helvetica,sans-serif">' . pg_h($btnLabel) . ' →</a>'
      . '</td></tr></table>'
      . '<div style="margin-top:12px;font-size:12px"><a href="' . pg_h($page) . '" style="color:#8b8b97;text-decoration:none">Mehr zu ' . pg_h($title) . ' auf ' . pg_h(preg_replace('#^https?://#', '', $site)) . ' →</a></div>'
      . '</td></tr></table>';
  return $h;
}
function pg_mail_expand_games($html){
  if (strpos((string) $html, '[[spiel:') === false) return $html;
  // ganzer Absatz mit nur dem Platzhalter → Karte statt Absatz
  $html = preg_replace_callback('#<p[^>]*>\s*\[\[spiel:([a-z0-9\-]+)\]\]\s*</p>#i', fn($m) => pg_mail_game_card($m[1]), $html);
  return preg_replace_callback('#\[\[spiel:([a-z0-9\-]+)\]\]#i', fn($m) => pg_mail_game_card($m[1]), $html);
}
/* Fertiges HTML für eine persönliche Mail (Verfassen/Antworten) inkl. Signatur.
   $opts: html (bool, Nachricht ist HTML aus dem Editor), sig (Signatur-ID, '' = keine, null = Standard) */
function pg_mail_build_personal($message, $opts = []){
  $msg = pg_mail_message_html($message, !empty($opts['html']));
  $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . $msg . '</div>' . pg_mail_signature(array_key_exists('sig', $opts) ? $opts['sig'] : null);
  return pg_mail_shell($body, mb_substr(pg_mail_html_to_text($msg), 0, 90));
}
/* Fertiges HTML für eine Newsletter-Mail inkl. Abmeldelink. */
function pg_mail_build_newsletter($message, $unsubLink, $opts = []){
  $msg = pg_mail_message_html($message, !empty($opts['html']));
  $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . $msg . '</div>'
    . '<p style="color:#6f6f7a;font-size:12px;margin:22px 0 0">Du erhältst diese Mail, weil du den PLANIGAMES-Newsletter abonniert hast. '
    . '<a href="' . pg_h($unsubLink) . '" style="color:#ff8a2b">Abmelden</a></p>';
  return pg_mail_shell($body, mb_substr(pg_mail_html_to_text($msg), 0, 90));
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
  // Bestehende Passwörter behalten, wenn das Formular sie leer lässt (gilt fürs Hauptpostfach)
  $old = pg_mail_config_main();
  foreach (['smtp_pass','imap_pass'] as $k) {
    if (($c[$k] ?? '') === '') $c[$k] = $old[$k] ?? '';
  }
  // Alles, was das Formular nicht kennt (z. B. Signaturen), unverändert übernehmen
  $raw = pg_load_json(PG_MAIL_FILE);
  return pg_save_json(PG_MAIL_FILE, $c + (is_array($raw) ? $raw : []));
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
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'planigames.com');
    $email = 'no-reply@' . $host;
  }
  return [trim($c['from_name']) ?: 'PLANIGAMES', $email];
}

/* ---- Postausgang / Gesendet (lokal protokollieren, was aus dem Dashboard rausging) ---- */
const PG_MAIL_SENT_FILE = __DIR__ . '/../data/mail_sent.json';
function pg_mail_sent_load(){ $l = pg_load_json(PG_MAIL_SENT_FILE); return is_array($l) ? $l : []; }
function pg_mail_sent_log($to, $subject, $html, $atts = [], $from = ''){
  $list = pg_mail_sent_load();
  $id = bin2hex(random_bytes(5));
  $row = [
    'id'      => $id,
    'to'      => mb_substr((string) $to, 0, 200),
    'subject' => mb_substr((string) $subject, 0, 300),
    'html'    => (string) $html,
    'date'    => date('c'),
    'by'      => (string) (pg_current_email() ?: ''),
  ];
  if ($from !== '') $row['from'] = mb_substr((string) $from, 0, 200);
  if (pg_mb_key() !== 'main') $row['mb'] = pg_mb_key();
  // Anhänge nur als Liste (Name + Größe) merken – die Dateien selbst liegen nicht im Protokoll
  if ($atts) $row['atts'] = array_map(fn($a) => ['name' => (string) ($a['name'] ?? ''), 'size' => (int) strlen((string) ($a['data'] ?? ''))], $atts);
  array_unshift($list, $row);
  if (count($list) > 200) $list = array_slice($list, 0, 200);   // nicht unbegrenzt wachsen lassen
  pg_save_json(PG_MAIL_SENT_FILE, $list);
  return $id;
}

/* MIME-Aufbau: Text + HTML (multipart/alternative), mit Anhängen als multipart/mixed.
   $atts = [['name'=>…, 'mime'=>…, 'data'=>Binärdaten], …] */
function pg_mail_mime($html, $text, $atts = []){
  $eol = "\r\n";
  $b1 = 'pg-alt-' . bin2hex(random_bytes(9));
  $alt = '--' . $b1 . $eol . 'Content-Type: text/plain; charset=UTF-8' . $eol . 'Content-Transfer-Encoding: base64' . $eol . $eol
       . chunk_split(base64_encode((string) $text), 76, $eol)
       . '--' . $b1 . $eol . 'Content-Type: text/html; charset=UTF-8' . $eol . 'Content-Transfer-Encoding: base64' . $eol . $eol
       . chunk_split(base64_encode((string) $html), 76, $eol)
       . '--' . $b1 . '--' . $eol;
  if (!$atts) return ['multipart/alternative; boundary="' . $b1 . '"', $alt];
  $b2 = 'pg-mix-' . bin2hex(random_bytes(9));
  $body = '--' . $b2 . $eol . 'Content-Type: multipart/alternative; boundary="' . $b1 . '"' . $eol . $eol . $alt;
  foreach ($atts as $at) {
    $name = str_replace(["\r", "\n", '"', '\\'], '', (string) ($at['name'] ?? 'anhang')) ?: 'anhang';
    $mime = preg_match('#^[a-z0-9.+\-]+/[a-z0-9.+\-]+$#i', (string) ($at['mime'] ?? '')) ? $at['mime'] : 'application/octet-stream';
    $ascii = preg_match('/^[\x20-\x7e]+$/', $name) ? $name : '=?UTF-8?B?' . base64_encode($name) . '?=';
    $body .= '--' . $b2 . $eol . 'Content-Type: ' . $mime . '; name="' . $ascii . '"' . $eol
           . 'Content-Transfer-Encoding: base64' . $eol
           . 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name) . $eol . $eol
           . chunk_split(base64_encode((string) $at['data']), 76, $eol);
  }
  $body .= '--' . $b2 . '--' . $eol;
  return ['multipart/mixed; boundary="' . $b2 . '"', $body];
}

/* Zentrale Versandfunktion: nutzt SMTP, wenn konfiguriert, sonst mail().
   $html = vollständiges HTML (z. B. via pg_mail_shell). Jede Mail bekommt automatisch einen Text-Teil.
   $opts: text (eigener Text-Teil), attachments (s. pg_mail_mime), headers (z. B. In-Reply-To). */
function pg_send_mail($to, $subject, $html, $replyTo = '', $opts = []){
  [$fromName, $fromEmail] = pg_mail_from();
  $studioEmail = $fromEmail;
  if (!empty($opts['from_name'])) $fromName = str_replace(["\r", "\n"], ' ', (string) $opts['from_name']);
  if (!empty($opts['from_email']) && filter_var($opts['from_email'], FILTER_VALIDATE_EMAIL)) $fromEmail = (string) $opts['from_email'];
  $GLOBALS['pg_mail_last'] = ['from' => $fromEmail, 'fallback' => false];
  $text = isset($opts['text']) ? (string) $opts['text'] : pg_mail_html_to_text($html);
  [$ctype, $body] = pg_mail_mime($html, $text, $opts['attachments'] ?? []);
  $extra = '';
  foreach ((array) ($opts['headers'] ?? []) as $k => $v) {
    $k = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $k); $v = str_replace(["\r", "\n"], ' ', (string) $v);
    if ($k !== '' && trim($v) !== '') $extra .= $k . ': ' . $v . "\r\n";
  }
  if (pg_mail_configured_send()) {
    $ok = pg_smtp_send($to, $subject, $ctype, $body, $fromName, $fromEmail, $replyTo, $extra);
    // Persönliche Adresse vom Mailserver abgelehnt? → über die Studio-Adresse senden, Antworten an die Person
    if (!$ok && strcasecmp($fromEmail, $studioEmail) !== 0) {
      $ok = pg_smtp_send($to, $subject, $ctype, $body, $fromName, $studioEmail, $replyTo ?: $fromEmail, $extra);
      if ($ok) $GLOBALS['pg_mail_last'] = ['from' => $studioEmail, 'fallback' => true];
    }
    return $ok;
  }
  // Fallback: PHP mail()
  $fn = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
  $headers = "From: {$fn} <{$fromEmail}>\r\n"
    . ($replyTo ? "Reply-To: {$replyTo}\r\n" : "Reply-To: {$fromEmail}\r\n")
    . $extra . "MIME-Version: 1.0\r\nContent-Type: {$ctype}";
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  return @mail($to, $subj, $body, $headers);
}

/* Minimaler SMTP-Client (fsockopen) – ohne Abhängigkeiten, für All-Inkl geeignet. */
function pg_smtp_send($to, $subject, $ctype, $mimeBody, $fromName, $fromEmail, $replyTo = '', $extraHeaders = ''){
  $c = pg_mail_config();
  $host = $c['smtp_host']; $port = (int)$c['smtp_port'] ?: 587;
  $secure = $c['smtp_secure']; // 'ssl' | 'tls' | ''
  $transport = $secure === 'ssl' ? 'ssl://' : '';
  $errno = 0; $errstr = '';
  $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT, stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
  if (!$fp) { error_log("SMTP connect failed: $errstr ($errno)"); return false; }
  stream_set_timeout($fp, 30);

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
  $ehlo = $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.com'));
  if ($secure === 'tls') {
    if ($code($cmd('STARTTLS')) !== 220) { fclose($fp); return false; }
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) { fclose($fp); return false; }
    $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.com'));
  }
  // AUTH LOGIN
  if ($c['smtp_user'] !== '') {
    if ($code($cmd('AUTH LOGIN')) !== 334) { fclose($fp); return false; }
    if ($code($cmd(base64_encode($c['smtp_user']))) !== 334) { fclose($fp); return false; }
    if ($code($cmd(base64_encode($c['smtp_pass']))) !== 235) { fclose($fp); return false; }
  }
  // Bei Ablehnung sofort sauber abbrechen (kein halber Versand)
  $abort = function () use ($cmd, $fp) { $cmd('RSET'); $cmd('QUIT'); fclose($fp); return false; };
  if ($code($cmd('MAIL FROM:<' . $fromEmail . '>')) !== 250) return $abort();
  if ($code($cmd('RCPT TO:<' . $to . '>')) >= 400) return $abort();
  if ($code($cmd('DATA')) !== 354) return $abort();

  $fn = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $date = date('r');
  // Message-ID-Domain an die Absender-Adresse koppeln (bessere DMARC/DKIM-Ausrichtung → weniger Spam)
  $midDomain = preg_replace('/[^a-z0-9.\-]/i', '', substr((string) strrchr($fromEmail, '@'), 1) ?: ($_SERVER['HTTP_HOST'] ?? 'planigames.com'));
  $msgid = '<' . bin2hex(random_bytes(12)) . '@' . $midDomain . '>';
  $head = "Date: $date\r\nFrom: $fn <$fromEmail>\r\n"
    . ($replyTo ? "Reply-To: $replyTo\r\n" : '')
    . "To: <$to>\r\nSubject: $subj\r\nMessage-ID: $msgid\r\n" . $extraHeaders
    . "MIME-Version: 1.0\r\nContent-Type: $ctype\r\n\r\n";
  // MIME-Teile sind base64-kodiert → keine Zeile beginnt mit „.“ (kein Punkt-Stuffing nötig)
  $r = $cmd($head . $mimeBody . "\r\n.");
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
  static $clean = false;   // IMAP-Hinweise am Ende abholen, sonst landen sie als PHP-Notice im Fehlerlog
  if (!$clean) {
    register_shutdown_function(function () { @imap_errors(); @imap_alerts(); }); $clean = true;
    @imap_timeout(IMAP_OPENTIMEOUT, 12); @imap_timeout(IMAP_READTIMEOUT, 20); @imap_timeout(IMAP_WRITETIMEOUT, 20);   // hängender Server blockiert nicht ewig
  }
  $c = pg_mail_config();
  $mbox = @imap_open(pg_imap_mailbox($folder), $c['imap_user'], $c['imap_pass'], 0, 1);
  return $mbox ?: null;
}
/* ---- Verbindung testen (für die Einstellungen) – prüft das aktive Postfach, sendet nichts ---- */
function pg_imap_test(){
  if (!pg_imap_available()) return [null, 'Die IMAP-Erweiterung ist auf diesem Server nicht aktiv (Empfang im Dashboard nicht möglich).'];
  if (!pg_mail_configured_recv()) return [false, 'Kein IMAP-Server eingetragen.'];
  $c = pg_mail_config();
  @imap_timeout(IMAP_OPENTIMEOUT, 12); @imap_timeout(IMAP_READTIMEOUT, 20);
  $m = @imap_open(pg_imap_mailbox('INBOX'), $c['imap_user'], $c['imap_pass'], OP_READONLY, 1);
  $err = (string) imap_last_error(); @imap_errors(); @imap_alerts();
  if (!$m) return [false, 'Anmeldung fehlgeschlagen' . ($err !== '' ? ' (' . mb_substr($err, 0, 160) . ')' : '') . '.'];
  $n = (int) @imap_num_msg($m);
  imap_close($m); @imap_errors(); @imap_alerts();
  if (pg_mail_backoff(pg_mb_key())) pg_mail_unread_store(pg_mail_unread_row(pg_mb_key())['count']);   // Pause nach Fehlversuchen aufheben
  return [true, 'Verbunden · ' . $n . ' Mail' . ($n === 1 ? '' : 's') . ' im Posteingang.'];
}
function pg_smtp_test(){
  $c = pg_mail_config();
  if ($c['smtp_host'] === '' || $c['smtp_user'] === '') return [false, 'Kein SMTP-Server eingetragen.'];
  $secure = $c['smtp_secure']; $port = (int) $c['smtp_port'] ?: 587;
  $errno = 0; $errstr = '';
  $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : '') . $c['smtp_host'] . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
  if (!$fp) return [false, 'Server nicht erreichbar' . ($errstr !== '' ? ' (' . $errstr . ')' : '') . ' – Server & Port prüfen.'];
  stream_set_timeout($fp, 15);
  $read = function () use ($fp) { $d = ''; while (($l = fgets($fp, 512)) !== false) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
  $cmd = function ($x) use ($fp, $read) { fwrite($fp, $x . "\r\n"); return $read(); };
  $code = fn($r) => (int) substr(trim($r), 0, 3);
  $fail = function ($m) use ($fp) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); return [false, $m]; };
  if ($code($read()) !== 220) return $fail('Unerwartete Antwort vom Mailserver.');
  $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.com'));
  if ($secure === 'tls') {
    if ($code($cmd('STARTTLS')) !== 220) return $fail('STARTTLS wird nicht angeboten – Verschlüsselung/Port prüfen.');
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) return $fail('Verschlüsselte Verbindung fehlgeschlagen.');
    $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'planigames.com'));
  }
  if ($code($cmd('AUTH LOGIN')) !== 334 || $code($cmd(base64_encode($c['smtp_user']))) !== 334 || $code($cmd(base64_encode($c['smtp_pass']))) !== 235)
    return $fail('Anmeldung abgelehnt – Benutzername oder Passwort stimmt nicht.');
  $cmd('QUIT'); @fclose($fp);
  return [true, 'Anmeldung erfolgreich – Versand möglich.'];
}

/* ---- Persönliches Postfach anlegen/ändern (nur Owner) – gibt [true, Postfach] oder [false, Fehler] zurück ---- */
function pg_mailbox_upsert(array $in){
  $list = pg_mailboxes_all();
  $id = preg_replace('/[^a-z0-9_]/', '', (string) ($in['id'] ?? ''));
  $idx = null;
  foreach ($list as $i => $m) if ($id !== '' && $m['id'] === $id) $idx = $i;
  $old = $idx !== null ? $list[$idx] : [];
  $email = strtolower(trim((string) ($in['email'] ?? '')));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Bitte eine gültige E-Mail-Adresse eingeben.'];
  $main = pg_mail_config_main();
  foreach ([$main['from_email'], $main['imap_user']] as $mainMail)
    if (strcasecmp(trim((string) $mainMail), $email) === 0) return [false, $email . ' ist schon das Hauptpostfach.'];
  foreach ($list as $i => $m) if ($i !== $idx && strcasecmp($m['email'], $email) === 0) return [false, 'Für ' . $email . ' gibt es schon ein Postfach.'];
  $pass = (string) ($in['pass'] ?? '');
  if ($pass === '') $pass = (string) ($old['pass'] ?? '');
  if ($pass === '') return [false, 'Bitte das Passwort des Postfachs eingeben (steht im KAS bei der E-Mail-Adresse).'];
  $known = array_map(fn($u) => strtolower((string) ($u['email'] ?? '')), pg_users_load());
  $owners = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim((string) $e)), (array) ($in['owners'] ?? [])), fn($e) => in_array($e, $known, true))));
  if (!$owners) return [false, 'Bitte mindestens eine Person auswählen, die dieses Postfach sieht.'];
  $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 40);
  if ($label === '') $label = mb_convert_case(preg_replace('/[._\-]+/', ' ', strstr($email, '@', true)), MB_CASE_TITLE);
  $sig = (string) ($in['sig'] ?? ($old['sig'] ?? ''));
  if ($sig !== '' && $sig !== '-' && !pg_mail_signature_find($sig)) $sig = '';
  $mb = ['id' => $old['id'] ?? ('mb_' . bin2hex(random_bytes(4))), 'label' => $label, 'email' => $email,
         'from_name' => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string) ($in['from_name'] ?? ''))), 0, 80),
         'pass' => $pass, 'owners' => $owners, 'same_server' => !empty($in['same_server']), 'sig' => $sig];
  if (!$mb['same_server']) {
    foreach (['imap' => [993, 'ssl'], 'smtp' => [587, 'tls']] as $k => [$defPort, $defSec]) {
      $mb[$k . '_host'] = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($in[$k . '_host'] ?? ''));
      $mb[$k . '_port'] = max(1, min(65535, (int) ($in[$k . '_port'] ?? $defPort) ?: $defPort));
      $mb[$k . '_secure'] = in_array($in[$k . '_secure'] ?? $defSec, ['ssl', 'tls', ''], true) ? (string) ($in[$k . '_secure'] ?? $defSec) : $defSec;
    }
    if ($mb['imap_host'] === '' || $mb['smtp_host'] === '') return [false, 'Bitte IMAP- und SMTP-Server eintragen (oder „gleicher Server wie das Hauptpostfach“ wählen).'];
  }
  $mb += array_diff_key($old, $mb);           // unbekannte Felder behalten
  if ($idx === null) $list[] = $mb; else $list[$idx] = $mb;
  if (!pg_mailboxes_save($list)) return [false, 'Konnte nicht speichern (Schreibrechte data/ prüfen).'];
  if (pg_mail_backoff($mb['id'])) pg_mail_unread_store(pg_mail_unread_row($mb['id'])['count'], $mb['id']);   // neue Zugangsdaten sofort wieder versuchen
  pg_mailboxes_visible(true);
  return [true, $mb];
}
function pg_mailbox_delete($id){
  $list = pg_mailboxes_all(); $keep = array_values(array_filter($list, fn($m) => $m['id'] !== $id));
  if (count($keep) === count($list)) return false;
  if (!pg_mailboxes_save($keep)) return false;
  foreach ([PG_MAIL_UNREAD_FILE, PG_MAIL_PREVIEW_FILE] as $f) {          // Zwischenspeicher aufräumen
    $c = pg_load_json($f);
    if (is_array($c) && isset($c['mb'][$id])) { unset($c['mb'][$id]); pg_save_json($f, $c); }
  }
  pg_mailboxes_visible(true);
  return true;
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
      // ungelesen = „U“ (alt & ungelesen) oder „N“ bei Recent (gerade eingetroffen & ungelesen)
      'seen'    => !((isset($o->Unseen) && $o->Unseen === 'U') || (isset($o->Recent) && $o->Recent === 'N')),
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
    if (strtolower((string) ($part->disposition ?? '')) === 'attachment' && empty($part->parts)) continue;   // angehängte .txt/.html sind kein Inhalt
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
/* =====================================================================
   App-Shell: Seitenleiste · Kopfzeile · Tab-Leiste (mobil) · Befehlspalette
   Alle Admin-Seiten bekommen sie automatisch über pg_view_topbar().
   ===================================================================== */
function pg_icon($id, $cls = ''){
  return '<svg class="ic' . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#i-' . $id . '"/></svg>';
}
// Linien-Icons (einmal pro Seite, referenziert per <use>)
function pg_icon_sprite(){
  $p = [
    'cockpit' => '<rect x="3" y="3" width="7.5" height="9" rx="2"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="2"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="2"/><rect x="3" y="15" width="7.5" height="6" rx="2"/>',
    'game' => '<path d="M6 11h4M8 9v4M15 12h.01M18 10h.01"/><path d="M17.3 5H6.7a4 4 0 0 0-4 3.6l-.9 7.1A3 3 0 0 0 4.8 19c1 0 1.8-.6 2.2-1.5L8 15h8l1 2.5c.4.9 1.2 1.5 2.2 1.5a3 3 0 0 0 3-3.3l-.9-7.1A4 4 0 0 0 17.3 5z"/>',
    'studio' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5M10 20v-6h4v6"/>',
    'devlog' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
    'team' => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14c2 .7 3 2.6 3 6"/>',
    'legal' => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="m5 7-3 7a3.5 3.5 0 0 0 6 0zM19 7l-3 7a3.5 3.5 0 0 0 6 0z"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
    'inbox' => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5z"/>',
    'bulb' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
    'list' => '<path d="M10 6h10M10 12h10M10 18h10"/><path d="m3.5 6 1.2 1.2L7 5M3.5 12l1.2 1.2L7 11M3.5 18l1.2 1.2L7 17"/>',
    'mega' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6l-5 4H4a1 1 0 0 0-1 1z"/><path d="M15 9a3 3 0 0 1 0 6M18 6a7 7 0 0 1 0 12"/>',
    'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12z"/>',
    'zap' => '<path d="M13 2 3 14h8l-1 8 10-12h-8z"/>',
    'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M15 8l2 2"/>',
    'kanban' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 7v7M12 7v4M16 7v10"/>',
    'cal' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'vault' => '<rect x="3" y="4" width="8" height="6.5" rx="1.6"/><rect x="13" y="13.5" width="8" height="6.5" rx="1.6"/><path d="M11 7.2h2.5a2 2 0 0 1 2 2v4.3"/>',
    'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
    'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'lock' => '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'shield' => '<path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'archive' => '<rect x="3" y="4" width="18" height="5" rx="1.5"/><path d="M5 9v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>',
    'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/>',
    'settings' => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
    'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'ext' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
    'chev' => '<path d="m9 6 6 6-6 6"/>',
    'back' => '<path d="m15 6-6 6 6 6"/>',
    'down' => '<path d="m6 9 6 6 6-6"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
    'check' => '<path d="m5 12 5 5 9-10"/>',
    'alert' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
    'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
    'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'spark' => '<path d="m12 3 1.8 4.7 4.7 1.8-4.7 1.8L12 16l-1.8-4.7-4.7-1.8 4.7-1.8z"/><path d="m19 15 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
    'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
    'upload' => '<path d="M12 21V9M7 14l5-5 5 5M5 3h14"/>',
    'send' => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
    'clip' => '<path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/>',
    'bold' => '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>',
    'italic' => '<path d="M19 4h-9M14 20H5M15 4 9 20"/>',
    'underline' => '<path d="M7 4v7a5 5 0 0 0 10 0V4M5 21h14"/>',
    'heading' => '<path d="M6 4v16M18 4v16M6 12h12"/>',
    'ul' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" class="ic-fill"/><circle cx="4.5" cy="12" r="1" class="ic-fill"/><circle cx="4.5" cy="18" r="1" class="ic-fill"/>',
    'ol' => '<path d="M10 6h10M10 12h10M10 18h10M4 4.5h1.5V9M4 9h3M4 14.5c.5-.6 1-.9 1.7-.9.8 0 1.3.5 1.3 1.2 0 1.2-3 2.2-3 3.7h3"/>',
    'quote' => '<path d="M6 17h3l2-4V7H5v6h3zM14 17h3l2-4V7h-6v6h3z"/>',
    'move' => '<path d="M12 3v18M3 12h18M12 3l-3 3M12 3l3 3M12 21l-3-3M12 21l3-3M3 12l3-3M3 12l3 3M21 12l-3-3M21 12l-3 3"/>',
    'undo' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
    'grip' => '<circle cx="9" cy="6" r="1.3" class="ic-fill"/><circle cx="15" cy="6" r="1.3" class="ic-fill"/><circle cx="9" cy="12" r="1.3" class="ic-fill"/><circle cx="15" cy="12" r="1.3" class="ic-fill"/><circle cx="9" cy="18" r="1.3" class="ic-fill"/><circle cx="15" cy="18" r="1.3" class="ic-fill"/>',
    'play' => '<path d="M7 4.5v15l12-7.5z"/>',
    'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h13"/>',
    'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h8.5a3.5 3.5 0 0 0 0-7h-9a3.5 3.5 0 0 1 0-7H16"/>',
    'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"/>',
    'server' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
    'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.7-.8 1.7-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.8-1.7 1.7-1.7H16a5 5 0 0 0 5-5c0-4-4-7.2-9-7.2z"/><circle cx="7.5" cy="11" r="1" class="ic-fill"/><circle cx="10.5" cy="7" r="1" class="ic-fill"/><circle cx="15" cy="7.5" r="1" class="ic-fill"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
    'edit' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    'copy' => '<rect x="8" y="8" width="13" height="13" rx="2.5"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
    'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
    'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
    'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
    'radio' => '<circle cx="12" cy="12" r="2"/><path d="M8.5 15.5a5 5 0 0 1 0-7M15.5 8.5a5 5 0 0 1 0 7M5.6 18.4a9 9 0 0 1 0-12.8M18.4 5.6a9 9 0 0 1 0 12.8"/>',
  ];
  $h = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
  foreach ($p as $id => $d) $h .= '<symbol id="i-' . $id . '" viewBox="0 0 24 24">' . $d . '</symbol>';
  return $h . '</defs></svg>';
}

// Aktuelle Route aus der URL ableiten (kein Parameter an den 20 Aufrufstellen nötig)
function pg_route(){
  $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
  if ($script === 'mail.php')  return 'mail';
  if ($script === 'vault.php') return 'vault';
  if (!empty($_GET['collection'])) return 'c:' . preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['collection']);
  if (!empty($_GET['view']))       return 'v:' . preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['view']);
  return 'cockpit';
}

// Navigation: [ [Gruppenname, [ [route, href, icon, label, zahl, hervorheben], … ] ], … ]
function pg_nav_model($SCHEMA){
  $collIcons = ['studio' => 'studio', 'games' => 'game', 'patchnotes' => 'devlog', 'team' => 'team', 'legal' => 'legal'];
  $order = array_values(array_unique(array_merge(['studio', 'games', 'patchnotes', 'team', 'legal'], array_keys($SCHEMA))));
  $inhalte = [];
  foreach ($order as $key) {
    if (!isset($SCHEMA[$key]) || !pg_can($key)) continue;
    $n = 0;
    if ($key === 'games') $n = count((array) (pg_load_json(PG_DATA_DIR . '/games.json')['games'] ?? []));
    $inhalte[] = ['c:' . $key, 'index.php?collection=' . rawurlencode($key), $collIcons[$key] ?? 'devlog', $SCHEMA[$key]['label'], $n, false];
  }
  $com = [];
  if (pg_mailbox_access())   $com[] = ['mail', 'mail.php', 'mail', 'Postfach', pg_mail_unread_total(), true];
  if (pg_can('contacts'))    $com[] = ['v:contacts', 'index.php?view=contacts', 'inbox', 'Kontakt-Anfragen', pg_contacts_unread(), true];
  if (pg_can('contacts'))    $com[] = ['v:suggestions', 'index.php?view=suggestions', 'bulb', 'Vorschläge', pg_suggestions_pending(), true];
  if (pg_can('subscribers')) $com[] = ['v:waitlist', 'index.php?view=waitlist', 'list', 'Beta-Warteliste', 0, false];
  if (pg_can('subscribers')) $com[] = ['v:subscribers', 'index.php?view=subscribers', 'mega', 'Newsletter', 0, false];
  if (pg_can('contacts'))    $com[] = ['v:comments', 'index.php?view=comments', 'chat', 'Kommentare', pg_comments_pending(), true];
  if (pg_can('mail') || pg_can('contacts')) $com[] = ['v:templates', 'index.php?view=templates', 'zap', 'Schnellantworten', 0, false];
  $ops = [];
  if (pg_is_owner()) $ops[] = ['v:keys', 'index.php?view=keys', 'key', 'Keys & Presse', 0, false];
  if (pg_can('studio')) $ops[] = ['v:presskit', 'index.php?view=presskit', 'download', 'Press Kit', 0, false];
  $tools = [
    ['v:board', 'index.php?view=board', 'kanban', 'Planungs-Board', 0, false],
    ['v:media', 'index.php?view=media', 'image', 'Medien', 0, false],
    ['v:stats', 'index.php?view=stats', 'chart', 'Statistik', 0, false],
    ['v:seo', 'index.php?view=seo', 'globe', 'SEO-Assistent', 0, false],
  ];
  $sys = [['v:account', 'index.php?view=account', 'lock', 'Konto & 2FA', 0, false]];
  if (pg_is_owner()) {
    $sys[] = ['v:users', 'index.php?view=users', 'shield', 'Zugänge & Rollen', 0, false];
    $sys[] = ['v:activity', 'index.php?view=activity', 'clock', 'Protokoll', 0, false];
    $sys[] = ['v:backup', 'index.php?view=backup', 'archive', 'Backups', 0, false];
  }
  $sys[] = ['v:trash', 'index.php?view=trash', 'trash', 'Papierkorb', count(pg_trash_load()), false];
  return array_values(array_filter([
    ['', [['cockpit', 'index.php', 'cockpit', 'Cockpit', 0, false]]],
    ['Inhalte', $inhalte], ['Community', $com], ['Spielbetrieb', $ops],
    ['Planung & Werkzeuge', $tools], ['System', $sys],
  ], fn($g) => !empty($g[1])));
}

// PLANIGAMES-Logo (Studio-Einstellungen) für das Admin – relativer Pfad oder ''
function pg_brand_logo(){
  static $l = null;
  if ($l !== null) return $l;
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $logo = trim(explode('#', (string) ($s['logo'] ?? ''))[0]);
  if ($logo === '') return $l = '';
  return $l = preg_match('#^https?://#i', $logo) ? $logo : '../' . ltrim($logo, '/');
}
function pg_brand_mark(){
  $logo = pg_brand_logo();
  return $logo !== '' ? '<span class="brand-mark is-img"><img src="' . pg_h($logo) . '" alt=""></span>' : '<span class="brand-mark"><i></i></span>';
}
function pg_view_head($title){
  $GLOBALS['pg_page_title'] = (string) $title;
  // Admin als installierbare App (nur Backend): Manifest + Apple-Tags + App-Icon
  $s = pg_load_json(PG_DATA_DIR . '/studio.json');
  $appIcon = trim((string) ($s['pwa']['appIcon'] ?? '')) ?: '../assets/icon-180.png';
  $appName = (trim((string) ($s['pwa']['appName'] ?? '')) ?: (trim((string) ($s['name'] ?? '')) ?: 'PLANIGAMES')) . ' Admin';
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
     . '<meta name="robots" content="noindex"><title>' . pg_h($title) . ' · PLANIGAMES Admin</title>'
     . '<meta name="theme-color" content="#08080a">'
     . (pg_brand_logo() !== '' ? '<link rel="icon" href="' . pg_h(pg_brand_logo()) . '">' : '')
     . '<meta name="csrf-token" content="' . pg_h(pg_csrf()) . '">'
     . '<link rel="manifest" href="manifest.php">'
     . '<link rel="apple-touch-icon" href="' . pg_h($appIcon) . '">'
     . '<meta name="apple-mobile-web-app-capable" content="yes">'
     . '<meta name="mobile-web-app-capable" content="yes">'
     . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
     . '<meta name="apple-mobile-web-app-title" content="' . pg_h($appName) . '">'
     . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
     . '<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">'
     . '<link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">'
     . '<link rel="stylesheet" href="assets/admin.css?v=' . PG_ADMIN_V . '"></head><body>'
     . pg_icon_sprite();
}

function pg_view_topbar($SCHEMA, $active){
  $GLOBALS['pg_shell_open'] = true;
  $route = pg_route();
  $nav = pg_nav_model($SCHEMA);
  // aktiven Eintrag + Gruppe für die Brotkrumen finden
  $crumbGroup = 'Übersicht';
  foreach ($nav as [$gl, $items]) foreach ($items as $it) if ($it[0] === $route) $crumbGroup = $gl !== '' ? $gl : 'Übersicht';
  if (!empty($GLOBALS['pg_crumb_group'])) $crumbGroup = (string) $GLOBALS['pg_crumb_group'];
  $title = $GLOBALS['pg_page_title'] ?? 'Admin';
  $studio = pg_load_json(PG_DATA_DIR . '/studio.json');
  $u = pg_current_user() ?: [];
  $uname = trim((string) ($u['name'] ?? '')) ?: strtok(pg_current_email(), '@');
  $ini = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($uname)) ?: [], 0, 2)))) ?: 'PG';
  $role = (($u['role'] ?? '') === 'owner') ? 'Owner' : 'Editor';
  $twofa = $u && pg_user_has_2fa($u);

  echo '<div class="pg-app">';
  // ---- Seitenleiste ----
  echo '<aside class="side" id="side" aria-label="Hauptnavigation">';
  echo '<a class="brand" href="index.php">' . pg_brand_mark() . '<span><span class="brand-name">PLANI<b>GAMES</b></span><span class="brand-sub">Studio-Cockpit</span></span></a>';
  echo '<button type="button" class="side-search" data-open-palette>' . pg_icon('search') . 'Suchen &amp; springen<kbd>⌘K</kbd></button>';
  foreach ($nav as [$gl, $items]) {
    echo '<div class="nav-group">' . ($gl !== '' ? '<div class="nav-label">' . pg_h($gl) . '</div>' : '');
    foreach ($items as [$r, $href, $ic, $label, $n, $hot]) {
      $on = $r === $route;
      echo '<a class="nav-item' . ($on ? ' active' : '') . '" href="' . pg_h($href) . '"' . ($on ? ' aria-current="page"' : '') . '>'
         . pg_icon($ic) . '<span class="nav-t">' . pg_h($label) . '</span>'
         . ($n ? '<span class="nav-count' . ($hot ? ' hot' : '') . '">' . (int) $n . '</span>' : '') . '</a>';
    }
    echo '</div>';
  }
  echo '<div class="side-foot">';
  echo '<a class="side-link" href="../index.html" target="_blank" rel="noopener">' . pg_icon('ext') . 'Website ansehen</a>';
  echo '<div class="me"><span class="avatar">' . pg_h($ini) . '</span><span class="me-txt"><span class="me-name">' . pg_h($uname) . '</span>'
     . '<span class="me-role">' . ($twofa ? '<span class="dot-ok"></span>' : '<span class="dot-warn"></span>') . pg_h($role) . ' · ' . ($twofa ? '2FA aktiv' : '2FA aus') . '</span></span>'
     . '<a class="icon-btn sm" href="index.php?action=logout" title="Abmelden" aria-label="Abmelden">' . pg_icon('logout') . '</a></div>';
  echo '</div></aside><div class="scrim" id="scrim"></div>';
  // ---- Hauptbereich mit Kopfzeile ----
  echo '<div class="main"><header class="topbar">';
  echo '<button type="button" class="icon-btn only-m" id="menuBtn" aria-label="Menü öffnen">' . pg_icon('menu') . '</button>';
  echo '<div class="crumbs"><span class="hide-m">' . pg_h($crumbGroup) . '</span>' . pg_icon('chev', 'hide-m') . '<b>' . pg_h($title) . '</b></div>';
  echo '<div class="top-actions">';
  echo '<form class="top-search" action="index.php" method="get" role="search"><input type="hidden" name="view" value="search">'
     . pg_icon('search') . '<input name="q" placeholder="Suchen, springen, erledigen …" autocomplete="off" aria-label="Suchen" data-open-palette-input><kbd>⌘K</kbd></form>';
  echo '<button type="button" class="icon-btn only-m" data-open-palette aria-label="Suchen">' . pg_icon('search') . '</button>';
  echo '<a class="btn btn-ghost btn-sm hide-m" href="../index.html" target="_blank" rel="noopener">' . pg_icon('ext') . 'Website</a>';
  echo pg_notif_bell();
  echo '</div></header><main class="page" id="page">';
}

function pg_view_foot(){
  if (!empty($GLOBALS['pg_shell_open'])) {
    $GLOBALS['pg_shell_open'] = false;
    echo '</main></div>';
    // ---- mobile Tab-Leiste ----
    $route = pg_route();
    $tabs = [['cockpit', 'index.php', 'cockpit', 'Cockpit', 0]];
    if (pg_can('games')) $tabs[] = ['c:games', 'index.php?collection=games', 'game', 'Spiele', 0];
    if (pg_mailbox_access()) $tabs[] = ['mail', 'mail.php', 'mail', 'Postfach', pg_mail_unread_total()];
    $tabs[] = ['v:board', 'index.php?view=board', 'kanban', 'Board', 0];
    echo '<nav class="tabbar" aria-label="Schnellnavigation">';
    foreach ($tabs as [$r, $href, $ic, $label, $n]) {
      echo '<a class="tab-item' . ($r === $route ? ' active' : '') . '" href="' . pg_h($href) . '">' . pg_icon($ic) . '<span>' . pg_h($label) . '</span>'
         . ($n ? '<span class="tdot">' . (int) $n . '</span>' : '') . '</a>';
    }
    echo '<button type="button" class="tab-item" id="tabMore">' . pg_icon('menu') . '<span>Mehr</span></button></nav>';
    // ---- Befehlspalette (Strg/⌘ + K) ----
    $items = [];
    foreach (pg_nav_model($GLOBALS['SCHEMA'] ?? []) as [$gl, $its]) foreach ($its as $it) $items[] = ['s' => 'Seiten', 'i' => $it[2], 'l' => $it[3], 'h' => $it[1], 'k' => $gl];
    if (pg_can('games')) foreach ((array) (pg_load_json(PG_DATA_DIR . '/games.json')['games'] ?? []) as $gi => $g) {
      if (!is_array($g) || trim((string) ($g['title'] ?? '')) === '') continue;
      $items[] = ['s' => 'Spiele', 'i' => 'game', 'l' => (string) $g['title'], 'h' => 'index.php?collection=games&game=' . rawurlencode((string) ($g['slug'] ?? $gi)), 'k' => '/' . ($g['slug'] ?? '')];
    }
    if (pg_mailbox_access()) $items[] = ['s' => 'Aktionen', 'i' => 'mail', 'l' => 'Neue E-Mail schreiben', 'h' => 'mail.php?tab=compose', 'k' => ''];
    if (pg_can('patchnotes')) $items[] = ['s' => 'Aktionen', 'i' => 'devlog', 'l' => 'Devlog-Beitrag schreiben', 'h' => 'index.php?collection=patchnotes&new=1', 'k' => ''];
    if (pg_is_owner()) $items[] = ['s' => 'Aktionen', 'i' => 'archive', 'l' => 'Backup erstellen', 'h' => 'index.php?view=backup', 'k' => ''];
    $items[] = ['s' => 'Aktionen', 'i' => 'ext', 'l' => 'Website ansehen', 'h' => '../index.html', 'k' => ''];
    $items[] = ['s' => 'Aktionen', 'i' => 'logout', 'l' => 'Abmelden', 'h' => 'index.php?action=logout', 'k' => ''];
    echo '<div class="pal-back" id="pal" hidden><div class="pal" role="dialog" aria-modal="true" aria-label="Suchen und springen">'
       . '<div class="pal-in">' . pg_icon('search') . '<input id="palInput" placeholder="Seite, Spiel oder Aktion … – oder Suchbegriff" autocomplete="off"><kbd>Esc</kbd></div>'
       . '<ul class="pal-list" id="palList"></ul>'
       . '<div class="pal-foot"><span>↑↓ auswählen</span><span>↵ öffnen</span><span>Esc schließen</span></div></div></div>';
    echo '<script>window.PG_PAL=' . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    // ---- Englisch im Hintergrund nachziehen (Deutsch ist die Vorlage) ----
    if (isset($GLOBALS['SCHEMA'])) {
      $auto = !empty(pg_tr_settings()['auto']);
      $pend = ($auto && empty($GLOBALS['pg_tr_view_active'])) ? pg_tr_pending_all($GLOBALS['SCHEMA']) : [];
      echo '<script type="application/json" id="pg-tr-cfg">' . json_encode(['auto' => $auto, 'pending' => (object) $pend]) . '</script>';
    }
    echo '</div>';
  }
  echo '<script src="https://cdn.jsdelivr.net/npm/marked@15.0.12/marked.min.js"></script>';   // feste Version (wie bisher geladen)
  echo '<script src="assets/admin.js?v=' . PG_ADMIN_V . '"></script></body></html>';
}

/* ---------------- Sprach-Umschalter (DE = Vorlage, EN = Übersetzung) ---------------- */
function pg_lang_switch($deHref, $enHref, $lang, $pct = 100, $open = 0){
  return '<div class="g-seg g-lang" role="tablist" aria-label="Sprache der Inhalte">'
       . '<a class="' . ($lang === 'de' ? 'on' : '') . '" href="' . pg_h($deHref) . '" title="Deutsch – die Vorlage für alle Sprachen">DE</a>'
       . '<a class="' . ($lang === 'en' ? 'on' : '') . '" href="' . pg_h($enHref) . '" title="' . pg_h($open ? $open . ' englische Text' . ($open === 1 ? '' : 'e') . ' veraltet oder fehlen' : 'Englisch passt zum Deutschen') . '">EN '
       . ($open ? '<span class="n warn">' . (int) $open . '</span>' : '<span class="n ok">✓</span>') . '</a></div>';
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

// pg_translate_try()/pg_translate_text() und der Übersetzungs-Abgleich: Abschnitt „ÜBERSETZUNG – Deutsch ist die Hauptseite“ am Ende

/* ---------------- CSRF ---------------- */
function pg_csrf(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(18)); return $_SESSION['csrf']; }
function pg_wants_json(){
  return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '') || stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json') !== false;
}
function pg_csrf_check(){
  $sent = (string) ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
  if (hash_equals($_SESSION['csrf'] ?? '', $sent) && $sent !== '') return;
  http_response_code(400);
  $msg = 'Ungültiges Sicherheits-Token. Bitte Seite neu laden.';
  if (pg_wants_json()) { header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE)); }
  exit($msg);
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

/* ---------------- Linien-Icons der Website (Auswahl im Admin) ----------------
   Gleicher Icon-Satz wie in assets/app.js (PG_IC). Gespeichert wird der Name (z. B. „wand“). */
function pg_site_icons(){
  return [
    'wand' => ['Zauberstab', '<path d="M4 20 15 9M14 4v3M18.5 5.5l-2 2M20 10h-3M9.5 5.5l2 2"/>'],
    'spark' => ['Funkeln', '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>'],
    'star' => ['Stern', '<path d="M12 3.5l2.6 5.3 5.9.9-4.25 4.1 1 5.8L12 16.9l-5.25 2.7 1-5.8L3.5 9.7l5.9-.9z"/>'],
    'heart' => ['Herz', '<path d="M12 20s-7.5-4.6-7.5-10A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 7.5 3c0 5.4-7.5 10-7.5 10z"/>'],
    'pad' => ['Gamepad', '<rect x="2.5" y="7" width="19" height="11" rx="5.5"/><path d="M7 10.5v4M5 12.5h4M15.5 11.5h.01M18 13.5h.01"/>'],
    'users' => ['Team', '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3.2 3-5 5.5-5s4.7 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.6 14.2c2.5-.4 4.3 1.2 4.9 4"/>'],
    'palette' => ['Farbpalette', '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-.8 1.8-1.8 0-1.2-1-1.6-1-2.7 0-1 .9-1.7 2-1.7H17a4 4 0 0 0 4-4C21 6.5 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="14.5" cy="7" r="1"/>'],
    'brush' => ['Pinsel', '<path d="M18.5 3.5 10 12l2 2 8.5-8.5a1.4 1.4 0 0 0-2-2z"/><path d="M10 12c-2.5 0-4 1.5-4 3.5 0 1.6-.8 2.9-2.5 3.5 4.5 1.5 9.5-.5 8.5-5z"/>'],
    'bolt' => ['Blitz', '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'],
    'rocket' => ['Rakete', '<path d="M9 15 5.5 11.5C7.5 6.5 12 4 19.5 4.5 20 12 17.5 16.5 12.5 18.5z"/><path d="M6.5 14.5 4 20l5.5-2.5"/><circle cx="14.5" cy="9.5" r="1.6"/>'],
    'shield' => ['Schild', '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6z"/>'],
    'swords' => ['Schwerter', '<path d="M14.5 17.5 3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4M19 21l2-2"/><path d="M9.5 17.5 21 6V3h-3L6.5 14.5M11 19l-6-6M8 16l-4 4M5 21l-2-2"/>'],
    'castle' => ['Burg', '<path d="M4 21V5h3v3h2V5h2v3h2V5h2v3h2V5h3v16zM10 21v-4a2 2 0 0 1 4 0v4"/>'],
    'crown' => ['Krone', '<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z"/>'],
    'gem' => ['Kristall', '<path d="M6 3h12l3 6-9 12L3 9z"/><path d="M3 9h18M9 3l-1.5 6L12 21l4.5-12L15 3"/>'],
    'flame' => ['Flamme', '<path d="M12 21c-4 0-6.5-2.6-6.5-6.2 0-3.6 2.6-5.6 3.5-8.8 1.9 1.4 2.8 3.2 3 4.9C13.3 9.5 14 7.6 13.8 5c3 2.2 4.7 5.6 4.7 9.4 0 3.8-2.6 6.6-6.5 6.6z"/>'],
    'skull' => ['Totenkopf', '<path d="M12 3C7.6 3 4.5 6.1 4.5 10.2c0 2.5 1.2 4.4 3 5.5V19h9v-3.3c1.8-1.1 3-3 3-5.5C19.5 6.1 16.4 3 12 3z"/><circle cx="9" cy="11" r="1.6"/><circle cx="15" cy="11" r="1.6"/><path d="M10 19v2M14 19v2"/>'],
    'ghost' => ['Geist', '<path d="M5 21V10a7 7 0 0 1 14 0v11l-2.5-2-2.3 2-2.2-2-2.2 2-2.3-2z"/><path d="M9.5 10h.01M14.5 10h.01"/>'],
    'target' => ['Ziel', '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><path d="M12 12h.01"/>'],
    'trophy' => ['Pokal', '<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/>'],
    'globe' => ['Welt', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>'],
    'map' => ['Karte', '<path d="M9 4 3.5 6v14L9 18l6 2 5.5-2V4L15 6zM9 4v14M15 6v14"/>'],
    'compass' => ['Kompass', '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>'],
    'mountain' => ['Berg', '<path d="M2.5 20 9.5 8l4 6.5 2.5-3.5 5.5 9z"/>'],
    'tree' => ['Baum', '<path d="M12 3 6 11h3l-4 6h14l-4-6h3zM12 17v4"/>'],
    'moon' => ['Mond', '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/>'],
    'sun' => ['Sonne', '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>'],
    'music' => ['Musik', '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>'],
    'headphones' => ['Kopfhörer', '<path d="M4 18v-5a8 8 0 0 1 16 0v5"/><rect x="3" y="14" width="4.5" height="6.5" rx="1.8"/><rect x="16.5" y="14" width="4.5" height="6.5" rx="1.8"/>'],
    'puzzle' => ['Puzzle', '<path d="M5 8h3.5a2 2 0 1 1 4 0H16v3.5a2 2 0 1 1 0 4V19h-3.5a2 2 0 1 0-4 0H5v-3.5a2 2 0 1 0 0-4z"/>'],
    'dice' => ['Würfel', '<rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="M8.5 8.5h.01M15.5 8.5h.01M12 12h.01M8.5 15.5h.01M15.5 15.5h.01"/>'],
    'clock' => ['Uhr', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
    'chat' => ['Sprechblase', '<path d="M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5h-8.5L6 20v-3.5H5A1.5 1.5 0 0 1 3.5 15V7A1.5 1.5 0 0 1 5 5.5z"/>'],
    'eye' => ['Auge', '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>'],
    'lock' => ['Schloss', '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>'],
    'key' => ['Schlüssel', '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M16 7l2.5 2.5M14 9l2 2"/>'],
    'gift' => ['Geschenk', '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8.5h14V12M12 8v12.5M12 8c-1.5-3.5-5.5-3.5-5.5-1S9 8 12 8zm0 0c1.5-3.5 5.5-3.5 5.5-1S15 8 12 8z"/>'],
    'code' => ['Code', '<path d="m8 7-5 5 5 5M16 7l5 5-5 5M13.5 4.5l-3 15"/>'],
    'camera' => ['Kamera', '<path d="M4 7.5h3l1.8-2.5h6.4L17 7.5h3a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8.5a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>'],
    'film' => ['Film', '<rect x="3.5" y="4" width="17" height="16" rx="2"/><path d="M7.5 4v16M16.5 4v16M3.5 8h4M3.5 12h4M3.5 16h4M16.5 8h4M16.5 12h4M16.5 16h4"/>'],
    'book' => ['Buch', '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v14H6.5A1.5 1.5 0 0 0 5 18.5zM5 18.5A1.5 1.5 0 0 0 6.5 20H19v-3"/>'],
    'flag' => ['Flagge', '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>'],
    'check' => ['Haken', '<path d="M5 12.5 10 17l9-10"/>'],
    'screen' => ['Bildschirm', '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/>'],
    'tag' => ['Etikett', '<path d="M3 12V4.5A1.5 1.5 0 0 1 4.5 3H12l9 9-9 9z"/><circle cx="8" cy="8" r="1.4"/>'],
    'cal' => ['Kalender', '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>'],
    'news' => ['Zeitung', '<path d="M4 5h12v14H6a2 2 0 0 1-2-2zM16 9h4v8a2 2 0 0 1-2 2h-2M7.5 9h5M7.5 12.5h5M7.5 16h3"/>'],
    'atom' => ['Physik', '<circle cx="12" cy="12" r="1.4"/><ellipse cx="12" cy="12" rx="9" ry="3.6"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(120 12 12)"/>'],
    'smile' => ['Lächeln', '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5c1 1.3 2.2 2 3.5 2s2.5-.7 3.5-2M9 9.5h.01M15 9.5h.01"/>'],
    'bulb' => ['Idee', '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0 0 12 3z"/>'],
    'pin' => ['Ort', '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>'],
    'box' => ['Paket', '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>'],
  ];
}
// Name eines Icons – ältere Einträge mit Emoji (z. B. 🪄) werden dem passenden Icon zugeordnet
function pg_site_icon_key($v){
  static $map = ['🪄' => 'wand', '✨' => 'spark', '💫' => 'spark', '⭐' => 'star', '🌟' => 'star', '❤' => 'heart', '💜' => 'heart', '🧡' => 'heart', '💛' => 'heart', '💚' => 'heart', '💙' => 'heart', '🖤' => 'heart', '🤍' => 'heart', '💖' => 'heart', '💕' => 'heart', '♥' => 'heart', '🎮' => 'pad', '🕹' => 'pad', '🌀' => 'pad', '👾' => 'pad', '🤝' => 'users', '👥' => 'users', '👫' => 'users', '🧑‍🤝‍🧑' => 'users', '👪' => 'users', '🫂' => 'users', '🎨' => 'palette', '🖌' => 'brush', '✏' => 'brush', '🖍' => 'brush', '⚡' => 'bolt', '🚀' => 'rocket', '🛡' => 'shield', '⚔' => 'swords', '🗡' => 'swords', '🔫' => 'target', '🏰' => 'castle', '🏯' => 'castle', '👑' => 'crown', '💎' => 'gem', '🔮' => 'gem', '🔥' => 'flame', '💀' => 'skull', '☠' => 'skull', '🧟' => 'skull', '🧟‍♂' => 'skull', '🧟‍♀' => 'skull', '👻' => 'ghost', '🎯' => 'target', '🏆' => 'trophy', '🥇' => 'trophy', '🏅' => 'trophy', '🌍' => 'globe', '🌎' => 'globe', '🌏' => 'globe', '🌐' => 'globe', '🗺' => 'map', '🧭' => 'compass', '⛰' => 'mountain', '🏔' => 'mountain', '🌲' => 'tree', '🌳' => 'tree', '🌙' => 'moon', '☀' => 'sun', '🎵' => 'music', '🎶' => 'music', '🎧' => 'headphones', '🧩' => 'puzzle', '🎲' => 'dice', '⏱' => 'clock', '⏰' => 'clock', '🕒' => 'clock', '⌛' => 'clock', '⏳' => 'clock', '💬' => 'chat', '🗨' => 'chat', '👀' => 'eye', '👁' => 'eye', '🔒' => 'lock', '🔐' => 'lock', '🔑' => 'key', '🗝' => 'key', '🎁' => 'gift', '💻' => 'code', '⌨' => 'code', '🖥' => 'screen', '📷' => 'camera', '📸' => 'camera', '🎬' => 'film', '🎥' => 'film', '📖' => 'book', '📚' => 'book', '📜' => 'book', '🚩' => 'flag', '🏁' => 'flag', '✅' => 'check', '✔' => 'check', '🏷' => 'tag', '📅' => 'cal', '📆' => 'cal', '📰' => 'news', '📝' => 'news', '🧙' => 'wand', '🧙‍♂' => 'wand', '🧙‍♀' => 'wand', '🤹' => 'atom', '🤹‍♂' => 'atom', '🤹‍♀' => 'atom', '⚛' => 'atom', '😂' => 'smile', '😄' => 'smile', '😊' => 'smile', '🙂' => 'smile', '😎' => 'smile', '🤪' => 'smile', '💡' => 'bulb', '📍' => 'pin', '📦' => 'box', '🪟' => 'windows', '🍎' => 'apple', '🐧' => 'terminal', '🤖' => 'android'];
  $v = trim((string) $v);
  $all = pg_site_icons();
  if (isset($all[$v])) return $v;
  $k = str_replace(["\u{FE0E}", "\u{FE0F}"], '', $v);
  if (isset($map[$k])) return $map[$k];
  $first = mb_substr($k, 0, 1);
  return $map[$first] ?? 'spark';
}

/* Ältere Daten an neue Felder angleichen (beim Anzeigen im Admin; gespeichert wird erst beim nächsten Speichern):
   „Bilder für Über uns“ (Liste) → drei feste Bildplätze */
function pg_studio_migrate($data){
  if (!is_array($data)) return $data;
  $have = trim((string) ($data['aboutImage1'] ?? '')) . trim((string) ($data['aboutImage2'] ?? '')) . trim((string) ($data['aboutImage3'] ?? ''));
  if ($have === '' && !empty($data['aboutImages']) && is_array($data['aboutImages'])) {
    $imgs = [];
    foreach ($data['aboutImages'] as $x) { $p = is_array($x) ? (string) ($x['image'] ?? '') : (string) $x; if (trim($p) !== '') $imgs[] = $p; }
    foreach (array_slice($imgs, 0, 3) as $i => $p) $data['aboutImage' . ($i + 1)] = $p;
  }
  return $data;
}

/* ---------------- Datei-Upload ---------------- */
/* Datei aus einem normalen (Nicht-AJAX) Formular nach media/ speichern.
   Gibt den /media-Pfad zurück, '' wenn nichts/ungültig. Bei $imagesOnly nur Bilder. */
function pg_store_upload($key, $imagesOnly = true){
  if (empty($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
  $f = $_FILES[$key];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $imgExt = ['jpg','jpeg','png','webp','gif','svg','avif'];
  $allowed = $imagesOnly ? $imgExt : PG_UPLOAD_EXT;
  if (!in_array($ext, $allowed, true)) return '';
  $base = pg_slugify(pathinfo($f['name'], PATHINFO_FILENAME)) ?: 'bild';
  if (!is_dir(PG_MEDIA_DIR)) @mkdir(PG_MEDIA_DIR, 0775, true);
  $name = $base . '.' . $ext; $i = 1;
  while (is_file(PG_MEDIA_DIR . '/' . $name)) { $name = $base . '-' . (++$i) . '.' . $ext; }
  if (!move_uploaded_file($f['tmp_name'], PG_MEDIA_DIR . '/' . $name)) return '';
  pg_optimize_image(PG_MEDIA_DIR . '/' . $name, $ext);
  pg_make_webp(PG_MEDIA_DIR . '/' . $name);
  return '/media/' . $name;
}
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
  if (pg_make_webp(PG_MEDIA_DIR . '/' . $name)) $resp['webp'] = true;
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

/* ---------------- Schnellere Bilder: WebP-Kopien ----------------
   Neben foto.jpg / logo.png entsteht automatisch foto.jpg.webp / logo.png.webp.
   Die Adresse auf der Website bleibt gleich: Browser, die WebP können (alle aktuellen),
   bekommen per .htaccess automatisch die kleinere Datei, alle anderen das Original.
   Lohnt sich WebP bei einem Bild nicht (nicht kleiner), bleibt eine leere Merk-Datei
   liegen – die wird nie ausgeliefert, verhindert aber unnötige neue Versuche.
   Kann der Server kein WebP, passiert einfach nichts. */
function pg_webp_supported(){
  return function_exists('imagewebp') && function_exists('imagecreatetruecolor');
}
// Automatisch erzeugte Kopie/Merk-Datei (Original liegt daneben)? → in Listen ausblenden
function pg_media_is_webp_copy($name){
  return (bool) preg_match('/\.(jpe?g|png)\.webp$/i', (string) $name) && is_file(PG_MEDIA_DIR . '/' . substr($name, 0, -5));
}
function pg_make_webp($path){
  if (!pg_webp_supported() || !is_file($path)) return false;
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return false;
  // Merk-Datei „lohnt sich nicht / geht nicht" – wird nie ausgeliefert, verhindert ständige neue Versuche
  $stamp = function() use ($path) { @touch($path . '.webp', max(time(), (int) @filemtime($path))); };
  $skip = function() use ($path, $stamp) { @file_put_contents($path . '.webp', ''); $stamp(); return false; };
  $info = @getimagesize($path);
  if (!$info || $info[0] < 1 || $info[1] < 1) return $skip();
  // genug Arbeitsspeicher? (sehr große Fotos auf kleinen Servern auslassen)
  $lim = trim((string) ini_get('memory_limit'));
  $limB = (int) $lim * (['g' => 1073741824, 'm' => 1048576, 'k' => 1024][strtolower(substr($lim, -1))] ?? 1);
  if ($info[0] * $info[1] > 25000000 || ($limB > 0 && memory_get_usage() + $info[0] * $info[1] * 6 + 16777216 > $limB)) return $skip();
  $img = $ext === 'png'
    ? (function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null)
    : (function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null);
  if (!$img) return $skip();
  if ($ext === 'png') {
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    imagealphablending($img, false); imagesavealpha($img, true);
  } elseif (function_exists('exif_read_data')) {
    // Handy-Fotos: Drehung aus den EXIF-Angaben übernehmen (die WebP-Kopie hat keine EXIF-Drehung)
    $ex = @exif_read_data($path);
    $o = (int) (is_array($ex) ? ($ex['Orientation'] ?? 0) : 0);
    if (in_array($o, [3, 6, 8], true) && function_exists('imagerotate')) {
      $rot = @imagerotate($img, $o === 3 ? 180 : ($o === 6 ? -90 : 90), 0);
      if ($rot) { imagedestroy($img); $img = $rot; }
    }
  }
  // Logos & Freisteller (PNG mit Transparenz) verlustfrei – Kanten bleiben exakt;
  // Fotos, Screenshots und Bilder ohne Transparenz in hoher Qualität (viel kleiner)
  if ($ext === 'png' && pg_png_has_alpha($img)) $q = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 92;
  else $q = $ext === 'png' ? 88 : 80;
  $tmp = $path . '.webp.tmp';
  $ok = @imagewebp($img, $tmp, $q);
  imagedestroy($img);
  $orig = (int) @filesize($path);
  $new = ($ok && is_file($tmp)) ? (int) @filesize($tmp) : 0;
  if ($new > 0 && $new < $orig * 0.95 && @rename($tmp, $path . '.webp')) { $stamp(); return true; }
  @unlink($tmp);
  return $skip();
}
// Hat ein (PNG-)Bild durchsichtige Stellen? Stichproben im Raster + kompletter Rand
function pg_png_has_alpha($img){
  $w = imagesx($img); $h = imagesy($img);
  $a = function($x, $y) use ($img) { return ((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0; };
  for ($x = 0; $x < $w; $x++) if ($a($x, 0) || $a($x, $h - 1)) return true;
  for ($y = 0; $y < $h; $y++) if ($a(0, $y) || $a($w - 1, $y)) return true;
  $step = max(1, (int) floor(sqrt($w * $h / 40000)));
  for ($y = 0; $y < $h; $y += $step) for ($x = 0; $x < $w; $x += $step) if ($a($x, $y)) return true;
  return false;
}
// JPG/PNG-Bilder ohne aktuelle WebP-Kopie (fehlt oder älter als das Bild)
function pg_webp_todo(){
  $out = [];
  if (!is_dir(PG_MEDIA_DIR)) return $out;
  foreach (scandir(PG_MEDIA_DIR) as $f) {
    if ($f[0] === '.' || !preg_match('/\.(jpe?g|png)$/i', $f)) continue;
    $p = PG_MEDIA_DIR . '/' . $f;
    if (!is_file($p)) continue;
    if (is_file($p . '.webp') && (int) @filemtime($p . '.webp') >= (int) @filemtime($p)) continue;
    $out[] = $f;
  }
  return $out;
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
  // „showIf“ => [Feld, 'wert1,wert2']: Feld nur zeigen, wenn das Auswahlfeld daneben passt (Wert wird trotzdem gespeichert)
  if (!empty($f['showIf']) && is_array($f['showIf'])) {
    $g = $f; unset($g['showIf']);
    return '<div class="showif" data-show-if="' . pg_h($f['showIf'][0]) . '" data-show-in="' . pg_h($f['showIf'][1]) . '">' . pg_render_field($g, $val, $name, $pathKey) . '</div>';
  }
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
      // Swatch zeigt Wert, sonst Default, sonst Orange – der Textwert (gespeichert) darf aber leer bleiben.
      $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val) ? $val
           : (preg_match('/^#[0-9a-fA-F]{6}$/', (string)($f['default'] ?? '')) ? $f['default'] : '#ff7d1a');
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
      $accept = $w === 'image' ? 'image/*' : 'image/*,video/*,.zip,application/zip,.pdf,application/pdf';
      $prev = $val ? '<a href="' . pg_h($val) . '" target="_blank" class="media-prev">' . pg_h($val) . '</a>' : '';
      $thumb = '';
      if ($w === 'image') {                                     // kleine Vorschau des gewählten Bildes
        $src = explode('#', (string) $val)[0];
        $thumb = '<span class="mf-thumb" data-media-thumb>' . ($src !== '' ? '<img src="' . pg_h(preg_match('#^(https?:)?//|^data:#', $src) ? $src : '../' . ltrim($src, '/')) . '" alt="" loading="lazy">' : pg_icon('image')) . '</span>';
      }
      $field = '<div class="media' . ($w === 'image' ? ' has-thumb' : '') . '" data-media>' . $thumb
             . '<input type="text" name="' . pg_h($name) . '" value="' . pg_h($val) . '" placeholder="/media/… oder URL" data-media-input>'
             . '<button type="button" class="btn-up" data-media-pick>Bibliothek</button>'
             . ($w === 'image' ? '<button type="button" class="btn-up" data-media-focus title="Bildausschnitt / Fokuspunkt">🎯 Fokus</button>' : '')
             . '<label class="btn-up">Hochladen<input type="file" accept="' . $accept . '" hidden data-media-file></label>'
             . '<span class="media-status">' . $prev . '</span></div>';
      break;
    case 'icon':
      // Raster mit den Linien-Icons der Website; ältere Emoji-Einträge zeigen das passende Icon als ausgewählt
      $cur = pg_site_icon_key((string) $val);
      $all = pg_site_icons();
      $btns = '';
      foreach ($all as $k => $ic) {
        $on = $k === $cur;
        $btns .= '<button type="button" class="ip-btn' . ($on ? ' on' : '') . '" data-icon-key="' . pg_h($k) . '" title="' . pg_h($ic[0]) . '" aria-label="' . pg_h($ic[0]) . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
               . '<svg viewBox="0 0 24 24" aria-hidden="true">' . $ic[1] . '</svg></button>';
      }
      $note = ($val !== '' && !isset($all[(string) $val])) ? '<p class="hint">Bisher: ' . pg_h($val) . ' – wird als Icon „' . pg_h($all[$cur][0] ?? '') . '“ gezeigt. Einfach ein anderes Icon anklicken, um zu wechseln.</p>' : '';
      $field = '<div class="iconpick" data-iconpick><input type="hidden" name="' . pg_h($name) . '" value="' . pg_h($val) . '" data-iconpick-input><div class="ip-grid">' . $btns . '</div></div>' . $note;
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
  // Kurze Wortlisten (z. B. Tags) als Chips statt als einzelne Karten
  if ($isSimple && ($f['field']['widget'] ?? 'string') === 'string') {
    $h = '<div class="field tagfield" data-tags data-name="' . pg_h($name) . '"><label class="flabel">' . pg_h($f['label']) . '</label><div class="tags-box">';
    foreach (array_values($items) as $i => $v) {
      if (is_array($v) || trim((string) $v) === '') continue;
      $h .= '<span class="tag-chip"><span>' . pg_h($v) . '</span><input type="hidden" name="' . pg_h($name) . '[' . $i . ']" value="' . pg_h($v) . '">'
          . '<button type="button" data-tag-del aria-label="' . pg_h($v) . ' entfernen">' . pg_icon('x') . '</button></span>';
    }
    $h .= '<input type="text" class="tag-in" placeholder="' . pg_h(($f['field']['label'] ?? 'Eintrag') . ' hinzufügen …') . '" data-tag-in aria-label="' . pg_h($f['label']) . ' hinzufügen"></div>'
        . '<p class="hint">' . pg_h(!empty($f['hint']) ? $f['hint'] : 'Enter oder Komma fügt hinzu · ✕ entfernt.') . '</p></div>';
    return $h;
  }
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

function pg_block_icon($type){
  return ['hero' => 'image', 'richtext' => 'devlog', 'features' => 'star', 'gallery' => 'image', 'trailer' => 'play',
          'quotes' => 'chat', 'stats' => 'chart', 'roadmap' => 'route', 'faq' => 'help', 'countdown' => 'clock',
          'cta' => 'mega', 'download' => 'download', 'requirements' => 'server', 'storewidget' => 'ext',
          'suggestions' => 'bulb', 'waitlist' => 'list', 'lore' => 'book', 'spacer' => 'move'][$type] ?? 'cockpit';
}
// Kurzinfo eines Blocks für die eingeklappte Zeile (Überschrift o. Ä.)
function pg_block_meta($type, $item){
  if (!is_array($item)) return '';
  foreach (['heading', 'kicker', 'tagline', 'label', 'text', 'body'] as $k) {
    $v = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($item[$k] ?? ''))));
    if ($v !== '') return mb_strimwidth($v, 0, 70, '…');
  }
  foreach (['items', 'images', 'builds', 'scenes'] as $k) if (!empty($item[$k]) && is_array($item[$k])) return count($item[$k]) . ' Einträge';
  return '';
}
function pg_render_block_item($f, $type, $item, $prefix, $pathKey, $idx){
  $tdef = $f['types'][$type];
  $inner = pg_render_fields($tdef['fields'], is_array($item) ? $item : [], $prefix, $pathKey . '.' . $type);
  $hidden = is_array($item) && !empty($item['hidden']);
  $meta = pg_block_meta($type, $item);
  return '<div class="item block' . ($hidden ? ' is-hidden' : '') . '" data-item data-block-type="' . pg_h($type) . '">'
       . '<input type="hidden" name="' . pg_h($prefix) . '[type]" value="' . pg_h($type) . '">'
       . '<input type="hidden" name="' . pg_h($prefix) . '[hidden]" value="' . ($hidden ? '1' : '0') . '" data-hidden-input>'
       . '<div class="item-bar"><span class="grip" data-grip>⠿</span>'
       . '<span class="blk-ic">' . pg_icon(pg_block_icon($type)) . '</span>'
       . '<span class="item-sum block-label"><b>' . pg_h($tdef['label']) . '</b>'
       . '<small data-block-meta>' . ($hidden ? 'ausgeblendet · ' : '') . pg_h($meta) . '</small></span>'
       . '<button type="button" class="vis-switch' . ($hidden ? '' : ' on') . '" data-block-vis title="Auf der Website zeigen / ausblenden (ohne zu löschen)" aria-pressed="' . ($hidden ? 'false' : 'true') . '"><span></span></button>'
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
      // Leer = „nicht gesetzt“ (z. B. Logo-Position → Standard). Früher wurde daraus 0 –
      // dadurch sprang das Logo in die Ecke. Felder mit Standardwert bekommen weiterhin den Standard.
      if ($val === null || (is_string($val) && trim($val) === '')) return $f['default'] ?? '';
      if (is_array($val) || !is_numeric(str_replace(',', '.', (string) $val))) return $f['default'] ?? '';
      $num = 0 + str_replace(',', '.', (string) $val);
      return (float) $num == (int) $num ? (int) $num : (float) $num;
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
        $blk = ['type' => $type] + pg_normalize_fields($f['types'][$type]['fields'], $it);
        if (!empty($it['hidden']) && $it['hidden'] !== '0' && $it['hidden'] !== 'false') $blk['hidden'] = true;
        $res[] = $blk;
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

/* =====================================================================
   Cockpit-Helfer
   ===================================================================== */
// Mini-Verlaufskurve (SVG) für Kennzahlen
function pg_spark_svg(array $vals, $color){
  $vals = array_values(array_map('floatval', $vals));
  if (count($vals) < 2) $vals = array_pad($vals, 2, 0.0);
  $w = 160; $h = 38; $max = max($vals); $min = min($vals); $rng = ($max - $min) ?: 1;
  $pts = [];
  foreach ($vals as $i => $v) $pts[] = sprintf('%.1f %.1f', $i * ($w / (count($vals) - 1)), $h - 3 - (($v - $min) / $rng) * ($h - 8));
  $d = 'M' . implode(' L', $pts);
  $id = 'sp' . substr(md5($color . implode(',', $vals)), 0, 8);
  return '<svg class="ck-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
       . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $color . '" stop-opacity=".32"/>'
       . '<stop offset="1" stop-color="' . $color . '" stop-opacity="0"/></linearGradient></defs>'
       . '<path d="' . $d . ' L ' . $w . ' ' . $h . ' L 0 ' . $h . ' Z" fill="url(#' . $id . ')"/>'
       . '<path d="' . $d . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
}
// Einträge pro Tag (lückenlos, letzte $days Tage) – für Verlaufskurven
function pg_daily_counts($items, $days, $field = 'date'){
  $out = [];
  for ($i = $days - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i day"))] = 0;
  foreach ((array) $items as $r) {
    if (!is_array($r)) continue;
    $d = substr((string) ($r[$field] ?? ''), 0, 10);
    if (isset($out[$d])) $out[$d]++;
  }
  return $out;
}
// Lückenlose Tageswerte aus stats.json (fehlende Tage = 0)
function pg_stats_series($daysMap, $days){
  $out = [];
  for ($i = $days - 1; $i >= 0; $i--) { $k = date('Y-m-d', strtotime("-$i day")); $out[$k] = (int) ($daysMap[$k] ?? 0); }
  return $out;
}
/* Selbsttest „Interne Daten geschützt?": legt eine harmlose Kanarien-Datei in data/ an
   und ruft sie von außen ab. Erreichbar (200) = Schutz fehlt. Ergebnis 6 h gecacht.
   Rückgabe: ['time'=>…, 'exposed'=>true|false|null]  (null = nicht prüfbar) */
function pg_selfcheck_data($force = false){
  $f = PG_DATA_DIR . '/selfcheck.json';
  $c = pg_load_json($f);
  // Ergebnis zwischenspeichern: „geschützt“ 6 h, „nicht prüfbar“ 1 h, „offen“ nur 5 min –
  // so verschwindet die Warnung kurz nach dem Hochladen der .htaccess von selbst.
  $ttl = ($c['exposed'] ?? null) === true ? 300 : ((($c['exposed'] ?? null) === false) ? 6 * 3600 : 3600);
  if (!$force && is_array($c) && isset($c['time'], $c['checked']) && time() - (int) $c['time'] < $ttl) return $c;
  $res = ['time' => time(), 'checked' => true, 'exposed' => null, 'canary' => 'Diese Datei darf per HTTP NICHT erreichbar sein.'];
  pg_save_json($f, $res);
  if (function_exists('curl_init')) {
    $ch = curl_init(pg_site_url() . '/data/selfcheck.json?' . time());
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3,
      CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'PLANIGAMES-Selfcheck']);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($body) && strpos($body, 'NICHT erreichbar') !== false) $res['exposed'] = true;
    elseif (in_array($code, [401, 403, 404], true)) $res['exposed'] = false;
  }
  pg_save_json($f, $res);
  return $res;
}
// Medien-Dateien über einer Größe (für „Bilder optimieren"-Hinweis)
function pg_media_large($bytes = 3145728){
  $out = [];
  if (!is_dir(PG_MEDIA_DIR)) return $out;
  foreach (scandir(PG_MEDIA_DIR) as $f) {
    if ($f[0] === '.' || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $f) || pg_media_is_webp_copy($f)) continue;
    $s = (int) @filesize(PG_MEDIA_DIR . '/' . $f);
    // hat das Bild eine kleine WebP-Kopie, bekommen Besucher ohnehin die (→ kein Hinweis nötig)
    $w = (int) @filesize(PG_MEDIA_DIR . '/' . $f . '.webp');
    if ($s > $bytes && !($w > 0 && $w <= $bytes)) $out[$f] = $s;
  }
  arsort($out);
  return $out;
}

/* =====================================================================
   SPIELE – Einzel-Bearbeitung, Reihenfolge, Web-Adresse (Slug) umziehen
   Grundsatz: Es wird immer nur das betroffene Spiel geändert; alle anderen
   Spiele und unbekannte Felder bleiben unangetastet.
   ===================================================================== */
const PG_GAMES_FILE     = __DIR__ . '/../data/games.json';
const PG_REDIRECTS_FILE = __DIR__ . '/../data/redirects.json';
const PG_SLUG_RX        = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

// Felder eines einzelnen Spiels (Listeneintrag „games“ im Schema)
function pg_game_fields($SCHEMA){
  foreach (($SCHEMA['games']['fields'] ?? []) as $f) if (($f['name'] ?? '') === 'games') return $f['fields'] ?? [];
  return [];
}
// Ganze Spiele-Datei (DE oder EN) – andere Schlüssel bleiben erhalten
function pg_games_data($lang = 'de'){
  $file = $lang === 'en' ? pg_lang_file(PG_GAMES_FILE, 'en') : PG_GAMES_FILE;
  $d = pg_load_json($file);
  if (!isset($d['games']) || !is_array($d['games'])) $d['games'] = [];
  $d['games'] = array_values($d['games']);
  return $d;
}
function pg_games_save($d, $lang = 'de'){
  $d['games'] = array_values($d['games']);
  return pg_save_json($lang === 'en' ? pg_lang_file(PG_GAMES_FILE, 'en') : PG_GAMES_FILE, $d);
}
function pg_game_idx($games, $slug){
  $slug = strtolower(trim((string) $slug));
  if ($slug === '') return -1;
  foreach ($games as $i => $g) if (is_array($g) && strtolower((string) ($g['slug'] ?? '')) === $slug) return $i;
  return -1;
}
// Spiel über Position + Slug finden (robust, falls zwei Spiele denselben Slug hätten)
function pg_game_locate($games, $idx, $slug){
  $idx = (int) $idx;
  if (isset($games[$idx]) && is_array($games[$idx]) && strtolower((string) ($games[$idx]['slug'] ?? '')) === strtolower((string) $slug)) return $idx;
  return pg_game_idx($games, $slug);
}
// Passender Eintrag in der englischen Datei: gleicher Slug – sonst (Altbestand) gleiche Position,
// sofern der Eintrag dort zu keinem anderen deutschen Spiel gehört.
function pg_game_en_idx($enGames, $deGames, $slug, $deIdx){
  $i = pg_game_idx($enGames, $slug);
  if ($i >= 0) return $i;
  if (isset($enGames[$deIdx]) && is_array($enGames[$deIdx])) {
    $s = (string) ($enGames[$deIdx]['slug'] ?? '');
    if ($s === '' || pg_game_idx($deGames, $s) < 0) return $deIdx;
  }
  return -1;
}
function pg_game_unique_slug($games, $base, $except = -1){
  $base = pg_slugify($base) ?: 'spiel';
  $s = $base; $n = 1;
  while (true) {
    $hit = pg_game_idx($games, $s);
    if ($hit < 0 || $hit === $except) return $s;
    $s = $base . '-' . (++$n);
  }
}
// EN-Liste in die Reihenfolge der deutschen Spiele bringen (verwaiste EN-Einträge bleiben hinten erhalten)
function pg_games_en_sorted($enGames, $deGames){
  $used = []; $out = [];
  foreach ($deGames as $di => $g) {
    if (!is_array($g)) continue;
    $ei = pg_game_en_idx($enGames, $deGames, $g['slug'] ?? '', $di);
    if ($ei >= 0 && empty($used[$ei])) { $used[$ei] = true; $out[] = $enGames[$ei]; }
  }
  foreach ($enGames as $ei => $e) if (empty($used[$ei])) $out[] = $e;
  return $out;
}

/* ---- Weiterleitungen alter Web-Adressen (öffentlich lesbar: data/redirects.json) ---- */
function pg_redirects_load(){ $r = pg_load_json(PG_REDIRECTS_FILE); return is_array($r) ? $r : []; }
function pg_redirect_add($type, $old, $new){
  $r = pg_redirects_load();
  $map = is_array($r[$type] ?? null) ? $r[$type] : [];
  foreach ($map as $k => $v) if ($v === $old) $map[$k] = $new;   // Ketten auflösen: a→old wird a→new
  unset($map[$new]);                                              // neue Adresse ist live → keine Weiterleitung
  if ($old !== $new) $map[$old] = $new;
  $r[$type] = $map;
  return pg_save_json(PG_REDIRECTS_FILE, $r);
}
function pg_redirect_clear($type, $slug){
  $r = pg_redirects_load();
  if (!isset($r[$type][$slug])) return true;
  unset($r[$type][$slug]);
  return pg_save_json(PG_REDIRECTS_FILE, $r);
}

/* ---- Logo ohne durchsichtigen Rand ----
   „logoBox“ = sichtbarer Bereich der Logo-Datei: x0,y0,x1,y1 (Anteile 0..1), Seitenverhältnis, Prüfsumme der
   Datei-Adresse. Gemessen im Browser (Admin), gespeichert pro Spiel. Website & Cockpit zeigen damit nur den
   sichtbaren Teil (Ausschnitt) – die Datei selbst bleibt unverändert. Passt die Prüfsumme nicht (anderes
   Logo), wird der Wert ignoriert. */
function pg_logo_src($g){ return trim(explode('#', (string) (is_array($g) ? ($g['logo'] ?? '') : $g))[0]); }
function pg_logo_box_parse($raw, $src){
  $v = explode(',', (string) $raw);
  if (count($v) !== 6 || $src === '' || $v[5] !== pg_tr_hash($src)) return null;
  foreach (array_slice($v, 0, 5) as $n) if (!is_numeric($n)) return null;
  [$x0, $y0, $x1, $y1, $ar] = array_map('floatval', array_slice($v, 0, 5));
  if ($x0 < 0 || $y0 < 0 || $x1 > 1.0001 || $y1 > 1.0001 || $x1 - $x0 < 0.01 || $y1 - $y0 < 0.01 || $ar <= 0 || $ar > 100) return null;
  return ['x0' => $x0, 'y0' => $y0, 'x1' => min(1.0, $x1), 'y1' => min(1.0, $y1), 'ar' => $ar];
}
function pg_logo_box_fmt($b, $src){
  return implode(',', array_map(fn($n) => number_format((float) $n, 4, '.', ''), [$b['x0'], $b['y0'], $b['x1'], $b['y1'], $b['ar']])) . ',' . pg_tr_hash($src);
}
// Ausschnitt-HTML (Hülle mit Seitenverhältnis des sichtbaren Teils, Bild darin verschoben)
function pg_logo_crop_html($src, $box, $alt, $cls = ''){
  $fw = $box['x1'] - $box['x0']; $fh = $box['y1'] - $box['y0']; $va = $box['ar'] * $fw / $fh;
  $src2 = preg_match('#^(https?:)?//|^data:#', $src) ? $src : '../' . ltrim($src, '/');
  return '<span class="pg-crop ' . pg_h($cls) . '" role="img" aria-label="' . pg_h($alt) . '" style="--va:' . number_format($va, 4, '.', '') . '">'
       . '<img src="' . pg_h($src2) . '" alt="" loading="lazy" style="width:' . number_format(100 / $fw, 4, '.', '') . '%;left:' . number_format(-100 * $box['x0'] / $fw, 4, '.', '')
       . '%;top:' . number_format(-100 * $box['y0'] / $fh, 4, '.', '') . '%"></span>';
}
// Gemessenen Ausschnitt speichern – nur, wenn das Spiel noch genau dieses Logo hat
function pg_game_logobox_set($slug, $logo, $raw){
  $src = pg_logo_src((string) $logo);
  $box = pg_logo_box_parse($raw, $src);
  if (!$box) return ['ok' => false, 'error' => 'invalid'];
  $lk = @fopen(PG_DATA_DIR . '/.games-logobox.lock', 'c');
  if ($lk) flock($lk, LOCK_EX);
  try {
    $d = pg_games_data('de');
    $i = pg_game_idx($d['games'], (string) $slug);
    if ($i < 0) return ['ok' => false, 'error' => 'not_found'];
    if (pg_logo_src($d['games'][$i]) !== $src) return ['ok' => false, 'error' => 'logo_changed'];
    $val = pg_logo_box_fmt($box, $src);
    if (($d['games'][$i]['logoBox'] ?? '') === $val) return ['ok' => true, 'box' => $val, 'unchanged' => true];
    $d['games'][$i]['logoBox'] = $val;
    return pg_games_save($d, 'de') ? ['ok' => true, 'box' => $val] : ['ok' => false, 'error' => 'write'];
  } finally { if ($lk) { flock($lk, LOCK_UN); fclose($lk); } }
}

/* ---- Einzelnes Spiel speichern ---- */
function pg_game_save_de($SCHEMA, $idx, $orig, $raw){
  $d = pg_games_data('de');
  $i = pg_game_locate($d['games'], $idx, $orig);
  if ($i < 0) return [false, 'Dieses Spiel wurde inzwischen gelöscht oder umbenannt. Bitte die Seite neu laden.'];
  $old = $d['games'][$i];
  $raw = is_array($raw) ? $raw : [];
  $raw['slug'] = (string) ($old['slug'] ?? '');                   // Web-Adresse nur über den eigenen Dialog
  $new = pg_normalize_fields(pg_game_fields($SCHEMA), $raw);
  if (($old['slug'] ?? '') !== '') $new['slug'] = $old['slug'];
  $d['games'][$i] = $new + array_diff_key($old, $new);            // unbekannte Felder behalten
  return [pg_games_save($d, 'de'), $d['games'][$i]];
}
/* ---- Reihenfolge (Drag & Drop in der Spieleliste) ---- */
function pg_games_reorder($slugs){
  $d = pg_games_data('de');
  $rest = $d['games']; $out = [];
  foreach ((array) $slugs as $s) {
    $k = pg_game_idx($rest, $s);
    if ($k >= 0) { $out[] = $rest[$k]; unset($rest[$k]); $rest = array_values($rest); }
  }
  foreach ($rest as $g) $out[] = $g;                              // nie ein Spiel verlieren
  if (count($out) !== count($d['games'])) return false;
  $d['games'] = $out;
  $ok = pg_games_save($d, 'de');
  $enFile = pg_lang_file(PG_GAMES_FILE, 'en');
  if ($ok && is_file($enFile)) { $en = pg_games_data('en'); $en['games'] = pg_games_en_sorted($en['games'], $out); pg_games_save($en, 'en'); }
  return $ok;
}

/* ---- Neu / Duplizieren / Löschen (→ Papierkorb) ---- */
function pg_game_create($SCHEMA, $title){
  $title = trim((string) $title);
  if ($title === '') return [false, 'Bitte einen Titel eingeben.'];
  $d = pg_games_data('de');
  $slug = pg_game_unique_slug($d['games'], $title);
  $g = pg_normalize_fields(pg_game_fields($SCHEMA), ['title' => $title, 'slug' => $slug, 'status' => 'development']);
  $g['slug'] = $slug;
  $g['blocks'] = [['type' => 'hero', 'tagline' => '', 'background' => '', 'backgroundMobile' => '', 'video' => '', 'poster' => '', 'ctaLabel' => 'Auf Steam wishlisten', 'trailerUrl' => ''],
                  ['type' => 'richtext', 'kicker' => '', 'heading' => 'Über das Spiel', 'body' => '']];
  $d['games'][] = $g;
  if (!pg_games_save($d, 'de')) return [false, 'Konnte nicht speichern (Schreibrechte data/ prüfen).'];
  pg_redirect_clear('games', $slug);
  pg_log_activity('Spiel angelegt', $title);
  return [true, $slug];
}
function pg_game_duplicate($idx, $orig){
  $d = pg_games_data('de');
  $i = pg_game_locate($d['games'], $idx, $orig);
  if ($i < 0) return [false, 'Spiel nicht gefunden.'];
  $copy = $d['games'][$i];
  $copy['title'] = trim((string) ($copy['title'] ?? 'Spiel')) . ' (Kopie)';
  $copy['slug'] = pg_game_unique_slug($d['games'], ($d['games'][$i]['slug'] ?? 'spiel') . '-kopie');
  $copy['featured'] = false;
  array_splice($d['games'], $i + 1, 0, [$copy]);
  if (!pg_games_save($d, 'de')) return [false, 'Konnte nicht speichern.'];
  pg_redirect_clear('games', $copy['slug']);
  // Englisch des Originals mitkopieren – samt Herkunft, damit nichts neu übersetzt werden muss
  $enFile = pg_lang_file(PG_GAMES_FILE, 'en');
  if (is_file($enFile)) {
    $en = pg_games_data('en');
    $ei = pg_game_en_idx($en['games'], $d['games'], (string) ($d['games'][$i]['slug'] ?? ''), $i);
    if ($ei >= 0) {
      $enCopy = $en['games'][$ei]; $enCopy['slug'] = $copy['slug'];
      $en['games'][] = $enCopy;
      $pre = 'games.@' . ($d['games'][$i]['slug'] ?? '') . '.';
      $src = is_array($en['_src'] ?? null) ? $en['_src'] : [];
      foreach ($src as $k => $v) if (strpos($k, $pre) === 0) $src['games.@' . $copy['slug'] . '.' . substr($k, strlen($pre))] = $v;
      ksort($src);
      if ($src) $en['_src'] = $src;
      $en['games'] = pg_games_en_sorted($en['games'], $d['games']);
      pg_games_save($en, 'en');
    }
  }
  pg_log_activity('Spiel dupliziert', $copy['title']);
  return [true, $copy['slug']];
}
function pg_game_delete($idx, $orig){
  $d = pg_games_data('de');
  $i = pg_game_locate($d['games'], $idx, $orig);
  if ($i < 0) return [false, 'Spiel nicht gefunden.'];
  $g = $d['games'][$i];
  pg_trash_add('games', 'games', $g);                             // wiederherstellbar über den Papierkorb
  array_splice($d['games'], $i, 1);
  if (!pg_games_save($d, 'de')) return [false, 'Konnte nicht speichern.'];
  pg_log_activity('Spiel in den Papierkorb', (string) ($g['title'] ?? $orig));
  return [true, (string) ($g['title'] ?? $orig)];
}

/* ---- Was hängt alles an einer Spiel-Adresse? (für den Umzugs-Dialog) ---- */
function pg_game_refs($slug){
  $lo = strtolower((string) $slug);
  $eq = fn($v) => strtolower(trim((string) $v)) === $lo;
  $c = ['waitlist' => 0, 'suggestions' => 0, 'board' => 0, 'posts' => 0, 'views' => 0, 'en' => false];
  foreach ((array) pg_load_json(PG_DATA_DIR . '/waitlist.json') as $e) if (is_array($e) && $eq($e['scope'] ?? '')) $c['waitlist']++;
  foreach ((array) pg_load_json(PG_DATA_DIR . '/suggestions.json') as $e) if (is_array($e) && $eq($e['scope'] ?? '')) $c['suggestions']++;
  foreach ((array) (pg_load_json(PG_BOARD_FILE)['boards'] ?? []) as $bd) {
    if (!is_array($bd)) continue;
    if ($eq($bd['game'] ?? '')) $c['board']++;
    foreach ((array) ($bd['columns'] ?? []) as $col) foreach ((array) ($col['cards'] ?? []) as $card) if (is_array($card) && $eq($card['game'] ?? '')) $c['board']++;
  }
  foreach ((array) (pg_load_json(PG_DATA_DIR . '/patchnotes.json')['posts'] ?? []) as $p) if (is_array($p) && $eq($p['game'] ?? '')) $c['posts']++;
  foreach ((array) (pg_load_json(PG_DATA_DIR . '/stats.json')['pages'] ?? []) as $k => $v) {
    if (preg_match('/^(game|lore):(.+)$/i', (string) $k, $m) && strtolower($m[2]) === $lo) $c['views'] += (int) $v;
  }
  $en = pg_games_data('en'); $de = pg_games_data('de');
  $c['en'] = pg_game_en_idx($en['games'], $de['games'], $slug, max(0, pg_game_idx($de['games'], $slug))) >= 0;
  return $c;
}

/* ---- Web-Adresse ändern: zieht ALLE Verweise mit um + dauerhafte Weiterleitung ---- */
function pg_game_rename($idx, $orig, $newRaw){
  $new = pg_slugify($newRaw);
  if ($new === '' || !preg_match(PG_SLUG_RX, $new)) return [false, 'Bitte nur Kleinbuchstaben, Zahlen und Bindestriche verwenden (z. B. frontliners).'];
  $d = pg_games_data('de');
  $i = pg_game_locate($d['games'], $idx, $orig);
  if ($i < 0) return [false, 'Spiel nicht gefunden – bitte Seite neu laden.'];
  $old = (string) ($d['games'][$i]['slug'] ?? '');
  if ($old === $new) return [false, 'Das ist bereits die aktuelle Adresse.'];
  if (pg_game_idx($d['games'], $new) >= 0) return [false, 'Diese Adresse benutzt schon ein anderes Spiel.'];
  $lo = strtolower($old);
  $eq = fn($v) => $lo !== '' && strtolower(trim((string) $v)) === $lo;

  // 0) Sicherheitskopie ALLER Daten vor dem Umzug (Backups → „Sicherung vor Umzug“)
  $snap = pg_backup_write('safety');
  if ($snap === false) return [false, 'Sicherheitskopie konnte nicht angelegt werden – Umzug abgebrochen, nichts wurde geändert.'];

  $c = ['waitlist' => 0, 'suggestions' => 0, 'board' => 0, 'posts' => 0, 'views' => 0, 'en' => 0];
  // 1) Englische Fassung (vor dem DE-Umbau zuordnen)
  $enFile = pg_lang_file(PG_GAMES_FILE, 'en');
  if (is_file($enFile) && $old !== '') {
    $en = pg_games_data('en');
    $ei = pg_game_en_idx($en['games'], $d['games'], $old, $i);
    if ($ei >= 0) { $en['games'][$ei]['slug'] = $new; if (pg_games_save($en, 'en')) $c['en'] = 1; }
    pg_tr_rename_src($enFile, 'games', $old, $new);                // Übersetzungs-Herkunft zieht mit um
  }
  // 2) Das Spiel selbst
  $d['games'][$i]['slug'] = $new;
  if (!pg_games_save($d, 'de')) return [false, 'Konnte games.json nicht speichern – bitte Schreibrechte prüfen. Sicherheitskopie: ' . $snap];
  if ($old === '') { pg_log_activity('Web-Adresse gesetzt', (string) ($d['games'][$i]['title'] ?? '') . ': /' . $new); return [true, ['old' => $old, 'new' => $new, 'counts' => $c, 'backup' => $snap]]; }
  // 3) Warteliste & Vorschläge (Feld „scope“)
  foreach (['waitlist' => 'waitlist.json', 'suggestions' => 'suggestions.json'] as $k => $fn) {
    $file = PG_DATA_DIR . '/' . $fn;
    if (!is_file($file)) continue;
    $list = pg_load_json($file); $n = 0;
    foreach ($list as $j => $e) if (is_array($e) && $eq($e['scope'] ?? '')) { $list[$j]['scope'] = $new; $n++; }
    if ($n && pg_save_json($file, $list)) $c[$k] = $n;
  }
  // 4) Planungs-Board (Boards + Karten)
  if (is_file(PG_BOARD_FILE)) {
    $b = pg_load_json(PG_BOARD_FILE); $n = 0;
    if (isset($b['boards']) && is_array($b['boards'])) {
      foreach ($b['boards'] as $bi => $bd) {
        if (!is_array($bd)) continue;
        if ($eq($bd['game'] ?? '')) { $b['boards'][$bi]['game'] = $new; $n++; }
        foreach ((array) ($bd['columns'] ?? []) as $ci => $col) foreach ((array) ($col['cards'] ?? []) as $ki => $card)
          if (is_array($card) && $eq($card['game'] ?? '')) { $b['boards'][$bi]['columns'][$ci]['cards'][$ki]['game'] = $new; $n++; }
      }
    }
    if ($n && pg_save_json(PG_BOARD_FILE, $b)) $c['board'] = $n;
  }
  // 5) Devlog-Beiträge, die dem Spiel zugeordnet sind (DE + EN; auch Tag = alte Adresse)
  foreach (['de', 'en'] as $lg) {
    $file = pg_lang_file(PG_DATA_DIR . '/patchnotes.json', $lg);
    if (!is_file($file)) continue;
    $p = pg_load_json($file); $n = 0;
    foreach ((array) ($p['posts'] ?? []) as $j => $post) {
      if (!is_array($post)) continue;
      if ($eq($post['game'] ?? '')) { $p['posts'][$j]['game'] = $new; $n++; }
      if (is_array($post['tags'] ?? null)) foreach ($post['tags'] as $t => $tag) if ($eq($tag)) { $p['posts'][$j]['tags'][$t] = $new; }
    }
    if ($n && pg_save_json($file, $p) && $lg === 'de') $c['posts'] = $n;
  }
  // 5b) Keys, die dem Spiel zugeordnet sind
  if (is_file(PG_KEYS_FILE)) {
    $ks = pg_keys_load(); $n = 0;
    foreach ($ks as $j => $k) if (is_array($k) && $eq($k['game'] ?? '')) { $ks[$j]['game'] = $new; $n++; }
    if ($n) pg_keys_save($ks);
  }
  // 6) Statistik: Aufrufe der Spiel- und Lore-Seite übernehmen (mit Sperre wie stats.php)
  $sf = PG_DATA_DIR . '/stats.json';
  if (is_file($sf) && ($fp = @fopen($sf, 'c+'))) {
    flock($fp, LOCK_EX);
    $s = json_decode(stream_get_contents($fp) ?: '{}', true);
    if (is_array($s) && is_array($s['pages'] ?? null)) {
      $moved = 0;
      foreach ($s['pages'] as $k => $v) {
        if (!preg_match('/^(game|lore):(.+)$/i', (string) $k, $m) || strtolower($m[2]) !== $lo) continue;
        $nk = strtolower($m[1]) . ':' . $new;
        $s['pages'][$nk] = (int) ($s['pages'][$nk] ?? 0) + (int) $v;
        unset($s['pages'][$k]); $moved += (int) $v;
      }
      if ($moved) { ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); $c['views'] = $moved; }
    }
    flock($fp, LOCK_UN); fclose($fp);
  }
  // 7) Alte Adresse leitet dauerhaft weiter
  pg_redirect_add('games', $old, $new);
  pg_log_activity('Web-Adresse geändert', (string) ($d['games'][$i]['title'] ?? '') . ': /' . $old . ' → /' . $new);
  return [true, ['old' => $old, 'new' => $new, 'counts' => $c, 'backup' => $snap]];
}

/* =====================================================================
   ÜBERSETZUNG – EN an die deutsche Struktur ausrichten
   Listen mit Slug → über den Slug, Blöcke → über Typ + Vorkommen,
   sonst über die Position. Nur freie Texte sind übersetzbar.
   ===================================================================== */
function pg_i18n_translatable($f){
  $w = $f['widget'] ?? '';
  if (!in_array($w, ['string', 'text', 'markdown'], true)) return false;
  if (!empty($f['slug']) || (isset($f['i18n']) && $f['i18n'] === false)) return false;   // Eigennamen (Spielname, Personen) bleiben gleich
  $n = $f['name'] ?? '';
  if (preg_match('/(url|e?mail)$/i', $n)) return false;
  return !in_array($n, ['email', 'accent', 'accent2', 'game', 'slug', 'icon', 'emoji', 'youtube', 'itchEmbed', 'steamAppId', 'version'], true);
}
// Englische Werte in der Struktur der deutschen Daten (nur übersetzbare Felder + Identität)
function pg_i18n_align($fields, $de, $en){
  $de = is_array($de) ? $de : []; $en = is_array($en) ? $en : [];
  $out = [];
  foreach ($fields as $f) {
    if (($f['widget'] ?? '') === 'heading' || empty($f['name'])) continue;
    $n = $f['name'];
    if (!array_key_exists($n, $de)) continue;
    $v = pg_i18n_align_value($f, $de[$n], $en[$n] ?? null);
    if ($v !== null) $out[$n] = $v;
  }
  return $out;
}
function pg_i18n_align_value($f, $dv, $ev){
  switch ($f['widget'] ?? '') {
    case 'object':
      $r = pg_i18n_align($f['fields'] ?? [], $dv, $ev);
      return $r ? $r : null;
    case 'list':
      if (!is_array($dv)) return null;
      $dv = array_values($dv); $ev = is_array($ev) ? array_values($ev) : [];
      if (isset($f['field'])) {
        if (!pg_i18n_translatable($f['field'])) return null;
        return array_map(fn($k) => is_scalar($ev[$k] ?? null) ? (string) $ev[$k] : '', array_keys($dv));
      }
      $deSlugs = [];
      foreach ($dv as $it) if (is_array($it) && !empty($it['slug'])) $deSlugs[strtolower((string) $it['slug'])] = true;
      $res = [];
      foreach ($dv as $k => $it) {
        $match = null;
        if (is_array($it) && !empty($it['slug'])) {
          foreach ($ev as $e) if (is_array($e) && strtolower((string) ($e['slug'] ?? '')) === strtolower((string) $it['slug'])) { $match = $e; break; }
          if ($match === null && is_array($ev[$k] ?? null) && (empty($ev[$k]['slug']) || empty($deSlugs[strtolower((string) $ev[$k]['slug'])]))) $match = $ev[$k];
          $res[] = ['slug' => (string) $it['slug']] + pg_i18n_align($f['fields'] ?? [], $it, $match);
        } else {
          $res[] = pg_i18n_align($f['fields'] ?? [], $it, is_array($ev[$k] ?? null) ? $ev[$k] : null);
        }
      }
      return $res;
    case 'blocks':
      if (!is_array($dv)) return null;
      $byType = [];
      foreach ((is_array($ev) ? $ev : []) as $e) if (is_array($e) && !empty($e['type'])) $byType[$e['type']][] = $e;
      $seen = []; $res = [];
      foreach (array_values($dv) as $it) {
        $t = is_array($it) ? (string) ($it['type'] ?? '') : '';
        if (!isset($f['types'][$t])) continue;
        $k = $seen[$t] = ($seen[$t] ?? 0) + 1;
        $res[] = ['type' => $t] + pg_i18n_align($f['types'][$t]['fields'] ?? [], $it, $byType[$t][$k - 1] ?? null);
      }
      return $res;
    default:
      if (!pg_i18n_translatable($f)) return null;
      return is_scalar($ev) ? (string) $ev : '';
  }
}

/* =====================================================================
   POSTFACH – Anhänge empfangen, Antworten im Verlauf
   ===================================================================== */
// Dateiname eines MIME-Teils (RFC 2047 & RFC 2231, auch mehrteilig „filename*0*“)
function pg_imap_part_filename($part){
  $params = [];
  foreach (['dparameters', 'parameters'] as $k) if (!empty($part->{$k}) && is_array($part->{$k})) foreach ($part->{$k} as $p) $params[] = [strtolower((string) $p->attribute), (string) $p->value];
  $pick = function ($base) use ($params) {
    $parts = [];
    foreach ($params as [$a, $v]) {
      if ($a === $base) return pg_imap_decode($v);
      if ($a === $base . '*') { $parts = [0 => $v]; $star = true; break; }
      if (preg_match('/^' . $base . '\*(\d+)\*?$/', $a, $m)) $parts[(int) $m[1]] = $v;
    }
    if (!$parts) return '';
    ksort($parts);
    $v = implode('', $parts);
    if (preg_match("/^([a-z0-9\-_]+)'[^']*'(.*)$/i", $v, $m)) {         // charset'lang'%XX
      $dec = rawurldecode($m[2]);
      return strtoupper($m[1]) === 'UTF-8' ? $dec : (string) @mb_convert_encoding($dec, 'UTF-8', $m[1]);
    }
    return rawurldecode($v);
  };
  $n = $pick('filename');
  if ($n === '') $n = $pick('name');
  return trim(str_replace(["\r", "\n", "\0", '/', '\\'], ['', '', '', '_', '_'], $n));
}
function pg_imap_part_mime($part){
  $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'model', 'other'];
  return ($types[(int) ($part->type ?? 3)] ?? 'application') . '/' . strtolower((string) ($part->subtype ?? 'octet-stream'));
}
// Anhänge einer Mail: [['part'=>'2', 'name'=>…, 'mime'=>…, 'size'=>Bytes, 'enc'=>…], …]
function pg_imap_attachments($mbox, $uid){
  $st = @imap_fetchstructure($mbox, $uid, FT_UID);
  $out = [];
  if ($st) pg_imap_walk_attachments($st, '', $out);
  return $out;
}
function pg_imap_walk_attachments($part, $no, &$out){
  if (!empty($part->parts)) {
    foreach ($part->parts as $i => $p) pg_imap_walk_attachments($p, $no === '' ? (string) ($i + 1) : $no . '.' . ($i + 1), $out);
    return;
  }
  $no = $no === '' ? '1' : $no;
  $name = pg_imap_part_filename($part);
  $disp = strtolower((string) ($part->disposition ?? ''));
  if ($name === '' && $disp !== 'attachment') return;                 // normaler Textteil
  $mime = pg_imap_part_mime($part);
  if ($name === '') $name = 'anhang-' . str_replace('.', '-', $no) . '.' . preg_replace('/[^a-z0-9]/', '', strtolower((string) ($part->subtype ?? 'bin')));
  $bytes = (int) ($part->bytes ?? 0);
  $out[] = ['part' => $no, 'name' => $name, 'mime' => $mime, 'enc' => (int) ($part->encoding ?? 0),
            'size' => (int) ((int) ($part->encoding ?? 0) === 3 ? round($bytes * 0.74) : $bytes)];
}
function pg_imap_decode_part($raw, $enc){
  if ((int) $enc === 3) return base64_decode(preg_replace('/\s+/', '', (string) $raw));
  if ((int) $enc === 4) return quoted_printable_decode((string) $raw);
  return (string) $raw;
}
// Inhalt eines Anhangs (ohne die Mail als gelesen zu markieren)
function pg_imap_attachment_data($mbox, $uid, $partNo){
  foreach (pg_imap_attachments($mbox, $uid) as $a) {
    if ($a['part'] !== $partNo) continue;
    $raw = @imap_fetchbody($mbox, $uid, $partNo, FT_UID | FT_PEEK);
    if ($raw === false) return null;
    return $a + ['data' => pg_imap_decode_part($raw, $a['enc'])];
  }
  return null;
}
// Message-ID + References für Antworten im selben Verlauf
function pg_imap_thread_headers($mbox, $uid){
  $no = @imap_msgno($mbox, $uid);
  if (!$no) return [];
  $h = @imap_headerinfo($mbox, $no);
  $mid = trim((string) ($h->message_id ?? ''));
  if ($mid === '' || !preg_match('/^<[^<>\s]+>$/', $mid)) return [];
  $refs = trim(preg_replace('/\s+/', ' ', (string) ($h->references ?? '')));
  return ['In-Reply-To' => $mid, 'References' => trim($refs . ' ' . $mid)];
}
// Lesbare Dateigröße
function pg_fmt_bytes($b){
  $b = (int) $b;
  if ($b >= 1048576) return str_replace('.', ',', (string) round($b / 1048576, 1)) . ' MB';
  return max(1, (int) round($b / 1024)) . ' KB';
}

// php.ini-Größenangaben („20M“, „1G“) in Bytes
function pg_ini_bytes($v){
  $v = trim((string) $v);
  if ($v === '' || $v === '-1') return 0;
  $n = (float) $v;
  switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; /* weiter */ case 'm': $n *= 1024; /* weiter */ case 'k': $n *= 1024; }
  return (int) $n;
}

/* =====================================================================
   ÜBERSETZUNG – Deutsch ist die Hauptseite
   Jeder englische Text merkt sich in „_src“ (in der .en.json) einen Fingerabdruck
   des deutschen Textes, aus dem er entstanden ist. Ändert sich das Deutsche, ist die
   Übersetzung „veraltet“: Die Website zeigt dann das aktuelle Deutsch, und das Admin
   zieht das Englische (auf Wunsch automatisch) nach.
   Pfade sind stabil gegen Umsortieren:
     Listen mit Web-Adresse → @slug · Seiten-Blöcke → typ#n (n = Vorkommen) · sonst Position
   Website (assets/app.js) und seo.php berechnen Pfade & Fingerabdrücke identisch.
   Status je Text: ok (passt zum Deutschen) · outdated (Deutsch ist neuer) ·
                   unchecked (Altbestand ohne Herkunft) · missing (noch kein Englisch)
   ===================================================================== */
const PG_I18N_FILE = PG_DATA_DIR . '/i18n.json';
const PG_TR_STALE  = 'stale';            // Markierung „sicher veraltet“ (z. B. anderer Spielname im Englischen)

function pg_tr_hash($s){
  $s = trim((string) $s); $h = 0x811c9dc5;
  for ($i = 0, $n = strlen($s); $i < $n; $i++) { $h ^= ord($s[$i]); $h = ($h * 0x01000193) & 0xffffffff; }
  return sprintf('%08x', $h);
}
function pg_tr_settings(){ $s = pg_load_json(PG_I18N_FILE); return (is_array($s) ? $s : []) + ['auto' => true]; }
function pg_tr_settings_save($s){ return pg_save_json(PG_I18N_FILE, $s + pg_tr_settings()); }

// Wie werden Einträge einer Liste identifiziert? (wie mergeLang in app.js)
function pg_tr_list_mode($list){
  $slug = false; $type = false;
  foreach ((array) $list as $it) { if (is_array($it) && !empty($it['slug'])) $slug = true; if (is_array($it) && !empty($it['type'])) $type = true; }
  return $slug ? 'slug' : ($type ? 'type' : 'index');
}

/* Alle übersetzbaren deutschen Texte einer Sammlung.
   ip = Identitätspfad (für _src & Website) · sp = Strukturpfad (Position in den ausgerichteten Daten)
   item = Eintrag mit Web-Adresse (Spiel/Beitrag) · grp = Abschnitt · label = Feldbezeichnung */
function pg_tr_leaves($fields, $de, $ip = '', $sp = '', $item = '', $grp = '', $lbl = '', $top = true, &$out = []){
  $de = is_array($de) ? $de : [];
  foreach ($fields as $f) {
    $w = $f['widget'] ?? '';
    if ($w === 'heading') { if ($top) $grp = (string) ($f['label'] ?? ''); continue; }
    if (empty($f['name']) || !array_key_exists($f['name'], $de)) continue;
    $n = $f['name']; $v = $de[$n];
    $ipn = $ip === '' ? $n : "$ip.$n"; $spn = $sp === '' ? $n : "$sp.$n";
    $fl = (string) ($f['label'] ?? $n);
    switch ($w) {
      case 'object':
        if ($top) pg_tr_leaves($f['fields'] ?? [], $v, $ipn, $spn, $item, $grp !== '' && $item !== '' ? $grp : $fl, '', false, $out);
        else pg_tr_leaves($f['fields'] ?? [], $v, $ipn, $spn, $item, $grp, $lbl . $fl . ' · ', false, $out);
        break;
      case 'list':
        if (!is_array($v)) break;
        $v = array_values($v);
        if (isset($f['field'])) {
          if (!pg_i18n_translatable($f['field'])) break;
          foreach ($v as $k => $x) if (is_scalar($x) && preg_match('/\p{L}/u', (string) $x))
            $out[] = ['ip' => "$ipn.$k", 'sp' => "$spn.$k", 'de' => (string) $x, 'label' => $lbl . ($f['field']['label'] ?? $fl) . ' ' . ($k + 1),
                      'w' => $f['field']['widget'] ?? 'string', 'grp' => $grp !== '' ? $grp : $fl, 'item' => $item];
          break;
        }
        $mode = pg_tr_list_mode($v); $occ = [];
        $sing = (string) ($f['label_singular'] ?? $fl);
        foreach ($v as $k => $it) {
          if (!is_array($it)) continue;
          if ($mode === 'slug' && !empty($it['slug'])) $seg = '@' . $it['slug'];
          elseif ($mode === 'type' && !empty($it['type'])) { $t = (string) $it['type']; $occ[$t] = ($occ[$t] ?? 0) + 1; $seg = $t . '#' . $occ[$t]; }
          else $seg = (string) $k;
          $sum = !empty($f['summary']) && is_scalar($it[$f['summary']] ?? null) ? trim((string) $it[$f['summary']]) : '';
          $name = $sing . ' ' . ($k + 1) . ($sum !== '' ? ' · ' . mb_strimwidth($sum, 0, 60, '…') : '');
          if ($mode === 'slug' && $top && $item === '')                       // Spiel / Beitrag: eigener Eintrag
            pg_tr_leaves($f['fields'] ?? [], $it, "$ipn.$seg", "$spn.$k", $sum !== '' ? $sum : (string) ($it['slug'] ?? $name), '', '', true, $out);
          elseif ($top)
            pg_tr_leaves($f['fields'] ?? [], $it, "$ipn.$seg", "$spn.$k", $item, ($grp !== '' && $item !== '' ? $grp . ' › ' : '') . $name, '', false, $out);
          else
            pg_tr_leaves($f['fields'] ?? [], $it, "$ipn.$seg", "$spn.$k", $item, $grp, $lbl . $sing . ' ' . ($k + 1) . ' · ', false, $out);
        }
        break;
      case 'blocks':
        if (!is_array($v)) break;
        $occ = [];
        foreach (array_values($v) as $k => $it) {
          $t = is_array($it) ? (string) ($it['type'] ?? '') : '';
          if ($t === '') continue;
          $occ[$t] = ($occ[$t] ?? 0) + 1;
          if (!isset($f['types'][$t])) continue;
          $meta = pg_block_meta($t, $it);
          pg_tr_leaves($f['types'][$t]['fields'] ?? [], $it, "$ipn.$t#{$occ[$t]}", "$spn.$k", $item,
            'Block: ' . $f['types'][$t]['label'] . ($meta !== '' ? ' · ' . mb_strimwidth($meta, 0, 60, '…') : '') . (!empty($it['hidden']) ? ' (ausgeblendet)' : ''), '', false, $out);
        }
        break;
      default:
        if (pg_i18n_translatable($f) && is_scalar($v) && preg_match('/\p{L}/u', (string) $v))   // ohne Buchstaben (z. B. „2025“) = sprachneutral
          $out[] = ['ip' => $ipn, 'sp' => $spn, 'de' => (string) $v, 'label' => $lbl . $fl, 'w' => $w, 'grp' => $grp !== '' ? $grp : 'Allgemein', 'item' => $item];
    }
  }
  return $out;
}
function pg_arr_get($a, $path){
  foreach (explode('.', $path) as $k) { if (!is_array($a) || !array_key_exists($k, $a)) return null; $a = $a[$k]; }
  return $a;
}
function pg_arr_set(&$a, $path, $val){
  $ref = &$a;
  foreach (explode('.', $path) as $k) { if (!is_array($ref)) $ref = []; if (!array_key_exists($k, $ref)) $ref[$k] = []; $ref = &$ref[$k]; }
  $ref = $val;
}

/* Rohstand laden (ohne Umstellung): Deutsch, Englisch (roh + ausgerichtet), Herkunft, alle Texte mit Status */
function pg_tr_load($SCHEMA, $key){
  $coll = $SCHEMA[$key];
  $de = pg_load_json($coll['file']);
  $enRaw = pg_load_json(pg_lang_file($coll['file'], 'en'));
  $aligned = pg_i18n_align($coll['fields'], $de, $enRaw);
  $src = is_array($enRaw['_src'] ?? null) ? $enRaw['_src'] : [];
  $leaves = pg_tr_leaves($coll['fields'], $de);
  foreach ($leaves as &$l) {
    $en = pg_arr_get($aligned, $l['sp']);
    $l['en'] = is_scalar($en) ? (string) $en : '';
    $l['h'] = pg_tr_hash($l['de']);
    $s = $src[$l['ip']] ?? null;
    $l['status'] = trim($l['en']) === '' ? 'missing' : ($s === null ? 'unchecked' : ($s === $l['h'] ? 'ok' : 'outdated'));
    $l['stale'] = $s === PG_TR_STALE;                                   // bei der Umstellung als unpassend erkannt
  }
  unset($l);
  return ['de' => $de, 'enRaw' => $enRaw, 'aligned' => $aligned, 'src' => $src, 'leaves' => $leaves];
}
/* Stand für Ansichten & Berichte (stellt Altbestand vorher einmalig um), optional nur ein Bereich (z. B. games.@frontliners) */
function pg_tr_state($SCHEMA, $key, $scope = ''){
  pg_tr_migrate($SCHEMA, $key);
  $st = pg_tr_load($SCHEMA, $key);
  if ($scope !== '') $st['leaves'] = array_values(array_filter($st['leaves'], fn($l) => strpos($l['ip'] . '.', $scope . '.') === 0));
  $c = ['total' => 0, 'ok' => 0, 'outdated' => 0, 'unchecked' => 0, 'missing' => 0];
  foreach ($st['leaves'] as $l) { $c['total']++; $c[$l['status']]++; }
  $st['count'] = $c;
  return $st;
}

/* Lesen-Ändern-Schreiben unter Sperre (verhindert, dass sich zwei Speichervorgänge überschreiben) */
function pg_tr_apply($SCHEMA, $key, callable $fn){
  $lock = @fopen(PG_DATA_DIR . '/.i18n.lock', 'c');
  if ($lock) flock($lock, LOCK_EX);
  try {
    $st = pg_tr_load($SCHEMA, $key);
    $aligned = $st['aligned']; $src = $st['src'];
    $fn($st, $aligned, $src);
    $ok = pg_tr_write($SCHEMA, $key, $st, $aligned, $src);
  } finally {
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
  }
  return $ok;
}
/* Englische Datei schreiben: Struktur aus Deutsch (nur Texte), verwaiste Spiele/Beiträge bleiben erhalten */
function pg_tr_write($SCHEMA, $key, $st, $aligned, $src){
  $coll = $SCHEMA[$key];
  $out = $aligned;
  foreach ($coll['fields'] as $f) {
    if (($f['widget'] ?? '') !== 'list' || empty($f['name']) || isset($f['field'])) continue;
    $n = $f['name'];
    $deSlugs = [];
    foreach ((array) ($st['de'][$n] ?? []) as $it) if (is_array($it) && !empty($it['slug'])) $deSlugs[strtolower((string) $it['slug'])] = true;
    if (!$deSlugs) continue;
    foreach ((array) ($st['enRaw'][$n] ?? []) as $it)                 // z. B. ein Spiel im Papierkorb – sein Englisch bleibt
      if (is_array($it) && !empty($it['slug']) && empty($deSlugs[strtolower((string) $it['slug'])])) $out[$n][] = $it;
  }
  $valid = [];
  foreach ($st['leaves'] as $l) $valid[$l['ip']] = true;
  foreach ($src as $k => $v) {                                          // Herkunft verwaister Spiele/Beiträge mit aufheben
    if (isset($valid[$k])) continue;
    if (!preg_match('/^[^.]+\.@([^.]+)\./', $k, $m)) { unset($src[$k]); continue; }
    $alive = false;
    foreach ($out as $lst) if (is_array($lst)) foreach ($lst as $it) if (is_array($it) && strtolower((string) ($it['slug'] ?? '')) === strtolower($m[1])) { $alive = true; break 2; }
    if (!$alive) unset($src[$k]);
  }
  ksort($src);
  $out['_src'] = $src ? $src : new stdClass();
  $file = pg_lang_file($coll['file'], 'en');
  $cmp = $st['enRaw'];
  if (($cmp['_src'] ?? null) === []) $cmp['_src'] = new stdClass();
  if (is_file($file) && json_encode($out) === json_encode($cmp)) return true;          // nichts geändert
  return pg_save_json($file, $out);
}

/* Ist ein alter englischer Text plausibel eine Übersetzung des aktuellen Deutschs?
   Fehlen Eigennamen (z. B. FRONTLINERS) oder Zahlen, weicht die Länge stark ab oder ist es
   unübersetztes Deutsch, gilt er als veraltet. */
function pg_tr_plausible($de, $en, $w = 'string'){
  $de = trim((string) $de); $en = trim((string) $en);
  if ($en === '') return false;
  $low = mb_strtolower($en, 'UTF-8'); $lowDe = mb_strtolower($de, 'UTF-8');
  $caps = '/(?<![\p{L}\p{N}])[A-ZÄÖÜ][A-ZÄÖÜ0-9]{2,}(?![\p{L}\p{N}])/u';
  preg_match_all($caps, $de, $m);
  foreach (array_unique($m[0]) as $tok) if (mb_strpos($low, mb_strtolower($tok, 'UTF-8')) === false) return false;
  preg_match_all($caps, $en, $m);                                       // Name im Englischen, den es im Deutschen nicht gibt (z. B. WARCATS)
  foreach (array_unique($m[0]) as $tok) if (mb_strpos($lowDe, mb_strtolower($tok, 'UTF-8')) === false) return false;
  $sent = fn($x) => preg_match_all('/[.!?…]+(?=\s|$)/u', $x);            // Satzanzahl bleibt beim Übersetzen fast immer gleich
  if (max($sent($de), $sent($en)) >= 2 && $sent($de) !== $sent($en)) return false;
  preg_match_all('/\d+/', $de, $m); preg_match_all('/\d+/', $en, $m2);
  if (array_diff(array_unique($m[0]), $m2[0])) return false;
  $ld = mb_strlen($de); $le = mb_strlen($en);
  if ($ld >= 60 && ($le < $ld * 0.45 || $le > $ld * 2.2)) return false;
  if ($w !== 'string' && abs(count(preg_split('/\n\s*\n/', $de)) - count(preg_split('/\n\s*\n/', $en))) >= 2) return false;
  if ($en === $de && preg_match_all('/\p{L}{2,}/u', $de) >= 3
      && preg_match('/(?<!\p{L})(und|der|die|das|ist|nicht|mit|für|ein|eine|wir|du|dein|deine|auf|zu|von|jetzt|neu)(?!\p{L})/iu', $de)) return false;
  return true;
}
/* Einmalige Umstellung einer alten .en.json (ohne „_src“): vorher Sicherheitskopie aller Daten.
   Plausible Übersetzungen gelten als aktuell, offensichtlich veraltete werden markiert. */
function pg_tr_migrate($SCHEMA, $key){
  static $done = [], $backup = false;
  if (isset($done[$key])) return; $done[$key] = true;
  $file = pg_lang_file($SCHEMA[$key]['file'], 'en');
  if (!is_file($file)) return;
  $raw = pg_load_json($file);
  if (array_key_exists('_src', $raw)) return;
  if (!$backup) { $backup = true; if (pg_backup_write('safety') === false) return; }   // ohne Sicherung keine Umstellung
  $stale = 0;
  pg_tr_apply($SCHEMA, $key, function ($st, &$aligned, &$src) use (&$stale) {
    foreach ($st['leaves'] as $l) {
      if (trim($l['en']) === '' || isset($src[$l['ip']])) continue;
      if (pg_tr_plausible($l['de'], $l['en'], $l['w'])) $src[$l['ip']] = $l['h'];
      else { $src[$l['ip']] = PG_TR_STALE; $stale++; }
    }
  });
  pg_log_activity('Übersetzung umgestellt', $SCHEMA[$key]['label'] . ': Deutsch ist jetzt die Vorlage' . ($stale ? ' · ' . $stale . ' veraltete Texte erkannt' : ''));
}

/* Englische Texte aus dem Formular speichern.
   Geänderte oder als „passt“ bestätigte Texte gelten als Übersetzung des deutschen Stands,
   den der Bearbeiter gesehen hat ($hashes) – ist Deutsch inzwischen neuer, bleiben sie „veraltet“. */
function pg_tr_save($SCHEMA, $key, $posted, $hashes = [], $okMarks = []){
  $posted = is_array($posted) ? $posted : []; $hashes = is_array($hashes) ? $hashes : []; $okMarks = is_array($okMarks) ? $okMarks : [];
  $changed = 0;
  $ok = pg_tr_apply($SCHEMA, $key, function ($st, &$aligned, &$src) use ($posted, $hashes, $okMarks, &$changed) {
    foreach ($st['leaves'] as $l) {
      $ip = $l['ip'];
      if (!array_key_exists($ip, $posted) && empty($okMarks[$ip])) continue;
      $h = (isset($hashes[$ip]) && preg_match('/^[0-9a-f]{8}$/', (string) $hashes[$ip])) ? (string) $hashes[$ip] : $l['h'];
      $cur = trim($l['en']);
      $new = array_key_exists($ip, $posted) ? trim(str_replace("\r", '', (string) $posted[$ip])) : $cur;
      if ($new === '') { if ($cur !== '' || isset($src[$ip])) $changed++; pg_arr_set($aligned, $l['sp'], ''); unset($src[$ip]); continue; }
      if ($new !== $cur) { pg_arr_set($aligned, $l['sp'], $new); $src[$ip] = $h; $changed++; }
      elseif (!empty($okMarks[$ip]) && ($src[$ip] ?? null) !== $h) { $src[$ip] = $h; $changed++; }
    }
  });
  return [$ok, $changed];
}

/* ---------------- Maschinelle Übersetzung (MyMemory, kein API-Key) ----------------
   pg_translate_try: Übersetzung oder null bei Fehler/Kontingent – nie ein halb übersetzter Text.
   Markdown bleibt erhalten: Zeilen werden einzeln übersetzt, Listen-/Überschriften-Zeichen bleiben stehen. */
function pg_translate_try($text, $from = 'de', $to = 'en'){
  $text = str_replace("\r\n", "\n", (string) $text);
  if (trim($text) === '') return $text;
  $out = [];
  foreach (explode("\n", $text) as $line) {
    if (trim($line) === '') { $out[] = $line; continue; }
    preg_match('/^(\s*(?:[-*+]|\d+[.)]|#{1,6}|>)\s+)?(.*)$/su', $line, $m);
    $tr = pg_translate_line($m[2] ?? $line, $from, $to);
    if ($tr === null) return null;
    $out[] = ($m[1] ?? '') . $tr;
  }
  $res = implode("\n", $out);
  return preg_replace_callback('/\*\*\s*(.+?)\s*\*\*/s', fn($x) => '**' . trim($x[1]) . '**', $res);
}
function pg_translate_line($s, $from, $to){
  $t = trim($s);
  if ($t === '' || !preg_match('/\p{L}/u', $t)) return $s;                                 // nur Zahlen/Zeichen
  // Links & Adressen nicht übersetzen: durch Platzhalter ersetzen und danach zurücksetzen
  $keep = [];
  $t = preg_replace_callback('~\]\(([^)\s]+)\)|\bhttps?://\S+|\b[\w.+-]+@[\w-]+\.[\w.]+\b~u', function ($m) use (&$keep) {
    $keep[] = $m[0]; return '⟦' . count($keep) . '⟧';
  }, $t);
  $restore = function ($r) use ($keep) {
    if ($r === null) return null;
    foreach ($keep as $i => $v) {
      $tok = '⟦' . ($i + 1) . '⟧';
      if (mb_strpos($r, $tok) === false) return null;                                     // Platzhalter verloren → lieber nicht übernehmen
      if (strncmp($v, '](', 2) === 0) $r = preg_replace('/\s+' . preg_quote($tok, '/') . '/u', $tok, $r);   // „[Text ](…)“ → „[Text](…)“
      $r = str_replace($tok, $v, $r);
    }
    return $r;
  };
  if ($keep && trim(preg_replace('/⟦\d+⟧/u', '', $t)) === '') return $s;
  if (mb_strlen($t) <= 40 && preg_match('/^[\p{Lu}0-9][\p{Lu}0-9\-_.:!?&+\/]*$/u', $t)) return $s;    // ein Name/Kürzel in Großbuchstaben (z. B. FRONTLINERS)
  if (getenv('PG_TRANSLATE_MOCK')) return $restore('EN: ' . $t);                         // nur Testumgebung
  // in Stücke ≤ 450 Zeichen an Satzgrenzen teilen
  $parts = []; $buf = '';
  foreach (preg_split('/(?<=[.!?…])\s+/u', $t) as $sent) {
    if ($buf !== '' && mb_strlen($buf . ' ' . $sent) > 450) { $parts[] = $buf; $buf = $sent; }
    else $buf = $buf === '' ? $sent : $buf . ' ' . $sent;
  }
  if ($buf !== '') $parts[] = $buf;
  $email = pg_translate_contact(); $res = [];
  foreach ($parts as $part) {
    $piece = mb_strlen($part) > 480 ? mb_substr($part, 0, 480) : $part;
    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($piece) . '&langpair=' . $from . '|' . $to . ($email ? '&de=' . rawurlencode($email) : '');
    $tr = null;
    for ($try = 0; $try < 2 && $tr === null; $try++) {
      if ($try) usleep(800000);
      $raw = pg_http_get($url);
      $j = $raw ? json_decode($raw, true) : null;
      $cand = is_array($j) ? ($j['responseData']['translatedText'] ?? null) : null;
      if (!is_string($cand) || trim($cand) === '' || (int) ($j['responseStatus'] ?? 0) !== 200 || !empty($j['quotaFinished'])) continue;
      if (preg_match('/^(MYMEMORY WARNING|QUERY LENGTH LIMIT|INVALID )/i', $cand)) return null;   // Kontingent/Fehler: nicht weiter versuchen
      $tr = html_entity_decode($cand, ENT_QUOTES, 'UTF-8');
    }
    if ($tr === null) return null;
    $res[] = trim($tr);
    usleep(100000);                                                                      // freundlich zum kostenlosen Dienst
  }
  return $restore(implode(' ', $res));
}
// Kompatibel für ältere Aufrufer: bei Fehler den Originaltext zurückgeben
function pg_translate_text($text, $from = 'de', $to = 'en'){
  $r = pg_translate_try($text, $from, $to);
  return $r === null ? (string) $text : $r;
}

/* Abgleich in Etappen (Zeitbudget je Aufruf). Übersetzt wird ohne Sperre, eingetragen wird
   unter Sperre und nur, wenn das Deutsche sich zwischenzeitlich nicht geändert hat.
   auto    = veraltete + fehlende
   force   = genau die übergebenen Texte neu aus dem Deutschen
   confirm = ungeprüfte & veraltete als „passt“ bestätigen (ohne Übersetzung) */
function pg_tr_sync($SCHEMA, $key, $scope = '', $mode = 'auto', $ips = [], $budget = 15){
  @set_time_limit($budget + 40);
  $t0 = microtime(true);
  $st = pg_tr_state($SCHEMA, $key, $scope);
  $want = array_flip(array_map('strval', (array) $ips));
  $todo = [];
  foreach ($st['leaves'] as $l) {
    if ($mode === 'force') { if (isset($want[$l['ip']])) $todo[] = $l; }
    elseif ($mode === 'confirm') { if ($l['status'] === 'unchecked' || $l['status'] === 'outdated') $todo[] = $l; }
    elseif ($l['status'] === 'outdated' || $l['status'] === 'missing') $todo[] = $l;
  }
  $res = []; $failed = 0;
  foreach ($todo as $l) {
    if ($mode === 'confirm') { $res[$l['ip']] = [null, $l['h']]; continue; }
    if (microtime(true) - $t0 > $budget) break;
    $en = pg_translate_try($l['de']);
    if ($en === null) { $failed++; if ($failed >= 2) break; continue; }
    $res[$l['ip']] = [$en, $l['h']];
  }
  if ($res) pg_tr_apply($SCHEMA, $key, function ($st2, &$aligned, &$src) use ($res) {
    foreach ($st2['leaves'] as $l) {
      if (!isset($res[$l['ip']])) continue;
      [$en, $h] = $res[$l['ip']];
      if ($h !== $l['h']) continue;                                     // Deutsch hat sich inzwischen geändert
      if ($en !== null) pg_arr_set($aligned, $l['sp'], $en);
      elseif (trim($l['en']) === '') continue;
      $src[$l['ip']] = $h;
    }
  });
  $after = pg_tr_state($SCHEMA, $key, $scope);
  $texts = [];
  foreach ($after['leaves'] as $l) if (isset($res[$l['ip']])) $texts[$l['ip']] = ['en' => $l['en'], 'status' => $l['status'], 'h' => $l['h']];
  return ['ok' => true, 'done' => count($res), 'failed' => $failed, 'unavailable' => $failed >= 2 || ($failed > 0 && !$res),
          'count' => $after['count'], 'pending' => $after['count']['outdated'] + $after['count']['missing'], 'texts' => $texts];
}

/* Überblick je Sammlung (nur Bereiche mit übersetzbarem Text, die der Nutzer bearbeiten darf) */
function pg_tr_report($SCHEMA){
  $rows = [];
  foreach ($SCHEMA as $k => $coll) {
    if (empty($coll['file']) || !pg_can($k)) continue;
    $c = pg_tr_state($SCHEMA, $k)['count'];
    if ($c['total'] === 0) continue;
    $rows[$k] = ['label' => $coll['label'], 'icon' => $coll['icon'] ?? '', 'pct' => (int) floor($c['ok'] / $c['total'] * 100)] + $c;
  }
  return $rows;
}
/* Was muss im Hintergrund nachgezogen werden? (zwischengespeichert, solange sich keine Datei ändert) */
function pg_tr_pending_all($SCHEMA){
  $sig = '';
  foreach ($SCHEMA as $k => $coll) {
    if (empty($coll['file'])) continue;
    foreach ([$coll['file'], pg_lang_file($coll['file'], 'en')] as $f) $sig .= $k . (is_file($f) ? filemtime($f) . ':' . filesize($f) : '-') . '|';
  }
  $sig = md5($sig . pg_current_email());
  if (($_SESSION['pg_tr_pend']['sig'] ?? '') === $sig) return $_SESSION['pg_tr_pend']['rows'];
  $rows = [];
  foreach (pg_tr_report($SCHEMA) as $k => $r) if ($r['outdated'] + $r['missing'] > 0) $rows[$k] = $r['outdated'] + $r['missing'];
  $_SESSION['pg_tr_pend'] = ['sig' => $sig, 'rows' => $rows];
  return $rows;
}
// Slug-Umzug: Herkunftsangaben mitnehmen (games.@alt.… → games.@neu.…)
function pg_tr_rename_src($file, $listKey, $old, $new){
  $en = pg_load_json($file);
  if (!is_array($en['_src'] ?? null) || !$en['_src']) return true;
  $pre = $listKey . '.@' . $old . '.'; $out = [];
  foreach ($en['_src'] as $k => $v) $out[strpos($k, $pre) === 0 ? $listKey . '.@' . $new . '.' . substr($k, strlen($pre)) : $k] = $v;
  ksort($out);
  $en['_src'] = $out;
  return pg_save_json($file, $en);
}

/* =====================================================================
   EINTRÄGE (Devlog-Beiträge, Teammitglieder): Einzel-Speichern, Anlegen,
   Duplizieren, Löschen, Sortieren – nur der betroffene Eintrag wird geändert.
   Beiträge sind über ihre Adresse (Slug) erkennbar, Teammitglieder über die
   Position (+ Fingerabdruck gegen Verwechslung). Die englische Fassung zieht mit.
   ===================================================================== */
function pg_item_list_key($key){ return ['patchnotes' => 'posts', 'team' => 'members'][$key] ?? null; }
function pg_item_fields_of($SCHEMA, $key){
  $list = pg_item_list_key($key);
  foreach (($SCHEMA[$key]['fields'] ?? []) as $f) if (($f['name'] ?? '') === $list) return $f['fields'] ?? [];
  return [];
}
function pg_item_fingerprint($it){ return substr(md5(json_encode($it)), 0, 12); }
function pg_item_find($items, $key, $idx, $orig, $fp = ''){
  $idx = (int) $idx;
  if ($key === 'patchnotes') {
    if (isset($items[$idx]) && is_array($items[$idx]) && strtolower((string) ($items[$idx]['slug'] ?? '')) === strtolower((string) $orig)) return $idx;
    foreach ($items as $i => $it) if (is_array($it) && $orig !== '' && strtolower((string) ($it['slug'] ?? '')) === strtolower((string) $orig)) return $i;
    return -1;
  }
  if (!isset($items[$idx]) || !is_array($items[$idx]) || (string) $idx !== (string) $orig) return -1;
  return ($fp === '' || pg_item_fingerprint($items[$idx]) === $fp) ? $idx : -1;
}
function pg_item_unique_slug($items, $base, $except = -1){
  $base = pg_slugify($base) ?: 'beitrag';
  $taken = [];
  foreach ($items as $i => $it) if ($i !== $except && is_array($it) && !empty($it['slug'])) $taken[strtolower((string) $it['slug'])] = true;
  $s = $base; $n = 1;
  while (!empty($taken[$s])) $s = $base . '-' . (++$n);
  return $s;
}
function pg_item_data($SCHEMA, $key){
  $d = pg_load_json($SCHEMA[$key]['file']);
  $list = pg_item_list_key($key);
  $d[$list] = array_values(is_array($d[$list] ?? null) ? $d[$list] : []);
  return $d;
}
// Englische Fassung neu zuordnen, wenn sich Positionen ändern ($map[alt] = neu | null = entfernt)
function pg_item_en_reindex($SCHEMA, $key, $map){
  $list = pg_item_list_key($key);
  $file = pg_lang_file($SCHEMA[$key]['file'], 'en');
  if (!is_file($file)) return true;
  $en = pg_load_json($file);
  $old = array_values(is_array($en[$list] ?? null) ? $en[$list] : []);
  $new = [];
  foreach ($map as $o => $n) if ($n !== null && isset($old[$o])) $new[$n] = $old[$o];
  $out = [];
  $max = $new ? max(array_keys($new)) : -1;
  for ($k = 0; $k <= $max; $k++) $out[] = $new[$k] ?? [];
  $en[$list] = $out;
  if (is_array($en['_src'] ?? null)) {
    $src = [];
    foreach ($en['_src'] as $k => $v) {
      if (preg_match('/^' . preg_quote($list, '/') . '\.(\d+)(\..*)$/', $k, $m)) {
        $o = (int) $m[1];
        if (array_key_exists($o, $map)) { if ($map[$o] !== null) $src[$list . '.' . $map[$o] . $m[2]] = $v; }
      } else $src[$k] = $v;
    }
    ksort($src);
    $en['_src'] = $src ? $src : new stdClass();
  }
  return pg_save_json($file, $en);
}
// Englisch eines Eintrags kopieren (Duplizieren): $from/$to = Slug (Beiträge) bzw. Position (Team)
function pg_item_en_copy($SCHEMA, $key, $from, $to){
  $list = pg_item_list_key($key);
  $file = pg_lang_file($SCHEMA[$key]['file'], 'en');
  if (!is_file($file)) return;
  $en = pg_load_json($file);
  $rows = array_values(is_array($en[$list] ?? null) ? $en[$list] : []);
  if ($key === 'patchnotes') {
    foreach ($rows as $r) if (is_array($r) && strtolower((string) ($r['slug'] ?? '')) === strtolower((string) $from)) { $c = $r; $c['slug'] = $to; $rows[] = $c; break; }
    $pre = $list . '.@' . $from . '.'; $npre = $list . '.@' . $to . '.';
  } else {
    if (!isset($rows[(int) $from])) return;
    while (count($rows) < (int) $to) $rows[] = [];
    $rows[(int) $to] = $rows[(int) $from];
    $pre = $list . '.' . (int) $from . '.'; $npre = $list . '.' . (int) $to . '.';
  }
  $en[$list] = $rows;
  if (is_array($en['_src'] ?? null)) {
    foreach ($en['_src'] as $k => $v) if (strpos($k, $pre) === 0) $en['_src'][$npre . substr($k, strlen($pre))] = $v;
    ksort($en['_src']);
  }
  pg_save_json($file, $en);
}

/* Einen Eintrag speichern. $explicit = Klick auf „Speichern“ (nur dann geht ein Newsletter raus). */
function pg_item_save($SCHEMA, $key, $idx, $orig, $fp, $raw, $explicit = true){
  $list = pg_item_list_key($key);
  $d = pg_item_data($SCHEMA, $key);
  $i = pg_item_find($d[$list], $key, $idx, $orig, $fp);
  if ($i < 0) return ['ok' => false, 'error' => 'Dieser Eintrag wurde inzwischen an anderer Stelle geändert oder gelöscht. Bitte die Seite neu laden – deine Eingaben bleiben im Browser stehen.'];
  $old = $d[$list][$i];
  $new = pg_normalize_fields(pg_item_fields_of($SCHEMA, $key), is_array($raw) ? $raw : []);
  $sent = 0; $note = '';
  if ($key === 'patchnotes') {
    if (($new['slug'] ?? '') === '') $new['slug'] = pg_slugify($new['title'] ?? '') ?: 'beitrag';
    $new['slug'] = pg_item_unique_slug($d[$list], $new['slug'], $i);
    $new['notified'] = (string) ($old['notified'] ?? '');                 // Versand-Status bleibt (nicht im Formular)
    $public = empty($new['draft']) && (empty($new['publishAt']) || strtotime((string) $new['publishAt']) <= time());
    if (!empty($new['notify']) && $public && $new['notified'] === '') {
      if ($explicit) { $n = pg_newsletter_send_post($new); if ($n > 0) { $new['notified'] = date('c'); $sent = $n; } }
      else $note = 'Der Newsletter zu diesem Beitrag geht erst beim Klick auf „Speichern“ raus.';
    }
  }
  $d[$list][$i] = $new + array_diff_key($old, $new);                       // unbekannte Felder behalten
  if (!pg_save_json($SCHEMA[$key]['file'], $d)) return ['ok' => false, 'error' => 'Konnte nicht speichern – bitte Schreibrechte am Ordner data/ prüfen.'];
  if ($key === 'patchnotes' && ($old['slug'] ?? '') !== '' && $old['slug'] !== $new['slug']) {
    // Adresse geändert: englische Fassung & Übersetzungs-Herkunft ziehen mit
    $file = pg_lang_file($SCHEMA[$key]['file'], 'en');
    if (is_file($file)) {
      $en = pg_load_json($file);
      foreach ((array) ($en[$list] ?? []) as $k => $r) if (is_array($r) && ($r['slug'] ?? '') === $old['slug']) $en[$list][$k]['slug'] = $new['slug'];
      pg_save_json($file, $en);
      pg_tr_rename_src($file, $list, $old['slug'], $new['slug']);
    }
  }
  $it = $d[$list][$i];
  return ['ok' => true, 'item' => $it, 'idx' => $i, 'key' => $key === 'patchnotes' ? (string) $it['slug'] : (string) $i,
          'fp' => pg_item_fingerprint($it), 'sent' => $sent, 'note' => $note, 'title' => (string) ($it['title'] ?? $it['name'] ?? '')];
}
function pg_item_create($SCHEMA, $key, $title, $game = ''){
  $title = trim((string) $title);
  if ($title === '') return [false, 'Bitte einen ' . ($key === 'patchnotes' ? 'Titel' : 'Namen') . ' eingeben.'];
  $list = pg_item_list_key($key);
  $d = pg_item_data($SCHEMA, $key);
  $fields = pg_item_fields_of($SCHEMA, $key);
  if ($key === 'patchnotes') {
    $games = [];
    foreach ((array) (pg_load_json(PG_GAMES_FILE)['games'] ?? []) as $g) if (is_array($g) && !empty($g['slug'])) $games[] = $g['slug'];
    $it = pg_normalize_fields($fields, ['title' => $title, 'date' => date('Y-m-d'), 'draft' => '1', 'game' => in_array($game, $games, true) ? $game : '']);
    $it['slug'] = pg_item_unique_slug($d[$list], $title);
    array_unshift($d[$list], $it);                                           // neueste zuerst
    // Positionen verschieben sich – Beiträge werden aber über ihre Adresse zugeordnet: nichts weiter nötig
    $res = $it['slug'];
  } else {
    $it = pg_normalize_fields($fields, ['name' => $title, 'emoji' => '🙂']);
    $d[$list][] = $it;                                                       // hinten anfügen: Positionen bleiben
    $res = (string) (count($d[$list]) - 1);
  }
  if (!pg_save_json($SCHEMA[$key]['file'], $d)) return [false, 'Konnte nicht speichern (Schreibrechte data/ prüfen).'];
  pg_log_activity($key === 'patchnotes' ? 'Beitrag angelegt' : 'Teammitglied angelegt', $title);
  return [true, $res];
}
function pg_item_duplicate($SCHEMA, $key, $idx, $orig, $fp){
  $list = pg_item_list_key($key);
  $d = pg_item_data($SCHEMA, $key);
  $i = pg_item_find($d[$list], $key, $idx, $orig, $fp);
  if ($i < 0) return [false, 'Eintrag nicht gefunden – bitte Seite neu laden.'];
  $c = $d[$list][$i];
  if ($key === 'patchnotes') {
    $c['title'] = trim((string) ($c['title'] ?? 'Beitrag')) . ' (Kopie)';
    $c['slug'] = pg_item_unique_slug($d[$list], ($d[$list][$i]['slug'] ?? 'beitrag') . '-kopie');
    $c['draft'] = true; $c['notify'] = false; $c['notified'] = '';
    array_splice($d[$list], $i + 1, 0, [$c]);
    $res = $c['slug'];
  } else {
    $c['name'] = trim((string) ($c['name'] ?? 'Mitglied')) . ' (Kopie)';
    $d[$list][] = $c;
    $res = (string) (count($d[$list]) - 1);
  }
  if (!pg_save_json($SCHEMA[$key]['file'], $d)) return [false, 'Konnte nicht speichern.'];
  pg_item_en_copy($SCHEMA, $key, $key === 'patchnotes' ? ($d[$list][$i]['slug'] ?? '') : $i, $res);
  pg_log_activity('Dupliziert', $SCHEMA[$key]['label'] . ' · ' . ($c['title'] ?? $c['name'] ?? ''));
  return [true, $res];
}
function pg_item_delete($SCHEMA, $key, $idx, $orig, $fp){
  $list = pg_item_list_key($key);
  $d = pg_item_data($SCHEMA, $key);
  $i = pg_item_find($d[$list], $key, $idx, $orig, $fp);
  if ($i < 0) return [false, 'Eintrag nicht gefunden – bitte Seite neu laden.'];
  $it = $d[$list][$i];
  pg_trash_add($key, $list, $it);
  array_splice($d[$list], $i, 1);
  if (!pg_save_json($SCHEMA[$key]['file'], $d)) return [false, 'Konnte nicht speichern.'];
  if ($key === 'team') {                                                      // Team: Positionen der Übersetzung nachziehen
    $map = [];
    for ($o = 0; $o <= count($d[$list]); $o++) $map[$o] = $o < $i ? $o : ($o === $i ? null : $o - 1);
    pg_item_en_reindex($SCHEMA, $key, $map);
  }
  $t = (string) ($it['title'] ?? $it['name'] ?? '');
  pg_log_activity('In den Papierkorb', $SCHEMA[$key]['label'] . ' · ' . $t);
  return [true, $t];
}
function pg_items_reorder($SCHEMA, $key, $order){
  $list = pg_item_list_key($key);
  $d = pg_item_data($SCHEMA, $key);
  $n = count($d[$list]);
  $order = array_map('intval', (array) $order);
  $check = $order; sort($check);
  if ($check !== range(0, $n - 1)) return false;                              // nur echte Umsortierung, nie etwas verlieren
  $d[$list] = array_map(fn($o) => $d[$list][$o], $order);
  if (!pg_save_json($SCHEMA[$key]['file'], $d)) return false;
  $map = [];
  foreach ($order as $new => $old) $map[$old] = $new;
  pg_item_en_reindex($SCHEMA, $key, $map);
  return true;
}

/* =====================================================================
   WARTELISTE ANSCHREIBEN – jede Mail einzeln (niemand sieht andere Adressen),
   mit Abmelde-Link, optional eigenem Key und Newsletter-Einwilligung.
   Versand in Etappen (Kampagne in data/wl_campaigns.json), damit nichts
   doppelt rausgeht und ein Abbruch einfach fortgesetzt werden kann.
   ===================================================================== */
const PG_WL_CAMPAIGNS = PG_DATA_DIR . '/wl_campaigns.json';
function pg_wl_leave_token($email, $scope){ return hash_hmac('sha256', strtolower(trim((string) $email)) . '|' . strtolower((string) $scope) . '|wl-leave', pg_app_secret()); }
function pg_wl_leave_link($email, $scope){
  return pg_site_url() . '/waitlist.php?action=leave&e=' . rawurlencode($email) . '&s=' . rawurlencode($scope) . '&t=' . pg_wl_leave_token($email, $scope);
}
function pg_optin_token($email){ return hash_hmac('sha256', strtolower(trim((string) $email)) . '|optin', pg_app_secret()); }
function pg_optin_link($email, $src){
  return pg_site_url() . '/subscribe.php?action=optin&e=' . rawurlencode($email) . '&src=' . rawurlencode(mb_substr($src, 0, 60)) . '&t=' . pg_optin_token($email);
}
function pg_key_redeem_url($platform, $key){
  return stripos((string) $platform, 'steam') !== false ? 'https://store.steampowered.com/account/registerkey?key=' . rawurlencode($key) : '';
}
// Rangliste eines Spiels (wie auf der Website) + Platz
function pg_wl_ranked_scope($scope){
  $list = pg_load_json(PG_DATA_DIR . '/waitlist.json');
  $rows = array_values(array_filter(is_array($list) ? $list : [], fn($e) => is_array($e) && !empty($e['email']) && pg_adm_wl_scope_lib($e['scope'] ?? '') === $scope));
  usort($rows, function ($a, $b) use ($rows) {
    $cnt = function ($code) use ($rows) { if ($code === '') return 0; $n = 0; foreach ($rows as $e) if (($e['ref'] ?? '') === $code) $n++; return $n; };
    $ra = $cnt($a['code'] ?? ''); $rb = $cnt($b['code'] ?? '');
    return $ra !== $rb ? $rb <=> $ra : strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
  });
  return $rows;
}
function pg_adm_wl_scope_lib($raw){ return preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $raw)); }
// Empfänger: all | new (noch nie angeschrieben) | top (die ersten $n laut Rangliste)
function pg_wl_recipients($scope, $mode, $n = 0){
  $out = []; $pos = 0;
  foreach (pg_wl_ranked_scope($scope) as $e) {
    $pos++;
    if ($mode === 'new' && !empty($e['mailed'])) continue;
    if (!filter_var($e['email'], FILTER_VALIDATE_EMAIL)) continue;
    $out[] = ['email' => strtolower(trim($e['email'])), 'rank' => $pos];
    if ($mode === 'top' && count($out) >= max(1, (int) $n)) break;
  }
  return $out;
}
function pg_wl_keys_available($scope, $platform = ''){
  $n = 0;
  foreach (pg_keys_load() as $k) if (strtolower((string) ($k['game'] ?? '')) === $scope && ($k['status'] ?? 'available') === 'available' && ($platform === '' || strcasecmp((string) ($k['platform'] ?? ''), $platform) === 0)) $n++;
  return $n;
}
// Einen freien Key fest vergeben (unter Sperre – nie zweimal derselbe Key)
function pg_wl_claim_key($scope, $platform, $email){
  $fp = @fopen(PG_DATA_DIR . '/.keys.lock', 'c'); if ($fp) flock($fp, LOCK_EX);
  $keys = pg_keys_load(); $got = null;
  foreach ($keys as $i => $k) {
    if (strtolower((string) ($k['game'] ?? '')) !== $scope || ($k['status'] ?? 'available') !== 'available') continue;
    if ($platform !== '' && strcasecmp((string) ($k['platform'] ?? ''), $platform) !== 0) continue;
    $keys[$i]['status'] = 'assigned'; $keys[$i]['recipient'] = 'Warteliste'; $keys[$i]['email'] = $email;
    $keys[$i]['note'] = 'per Warteliste-Mail'; $keys[$i]['assignedAt'] = date('c');
    $got = $keys[$i]; break;
  }
  if ($got) pg_keys_save($keys);
  if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
  return $got;
}
function pg_wl_release_key($id){                                  // Versand fehlgeschlagen → Key wieder freigeben
  $keys = pg_keys_load();
  foreach ($keys as $i => $k) if (($k['id'] ?? '') === $id) { $keys[$i]['status'] = 'available'; $keys[$i]['recipient'] = ''; $keys[$i]['email'] = ''; $keys[$i]['note'] = ''; unset($keys[$i]['assignedAt']); }
  pg_keys_save($keys);
}
// Versand im Wartelisten-Eintrag vermerken
function pg_wl_mark_mailed($scope, $email, $subject, $keyCode){
  $file = PG_DATA_DIR . '/waitlist.json';
  $list = pg_load_json($file); if (!is_array($list)) return;
  foreach ($list as $i => $e) {
    if (!is_array($e) || strtolower(trim((string) ($e['email'] ?? ''))) !== $email || pg_adm_wl_scope_lib($e['scope'] ?? '') !== $scope) continue;
    $m = is_array($e['mailed'] ?? null) ? $e['mailed'] : [];
    $m[] = ['date' => date('c'), 'subject' => mb_substr($subject, 0, 120)] + ($keyCode !== '' ? ['key' => true] : []);
    $list[$i]['mailed'] = array_slice($m, -10);
  }
  pg_save_json($file, $list);
}
// Die fertige Mail für EINEN Empfänger
function pg_wl_mail_html($msgHtml, $scope, $email, $rank, $key, $opts){
  $game = pg_mail_game_info($scope);
  $title = $game ? (string) ($game['title'] ?? $scope) : $scope;
  $repl = ['{platz}' => (string) $rank, '{spiel}' => $title, '{email}' => $email, '{key}' => $key ? (string) $key['key'] : ''];
  $msg = strtr(pg_mail_message_html($msgHtml, true), array_map('pg_h', $repl));
  $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . $msg . '</div>';
  if ($key) {
    $redeem = pg_key_redeem_url($key['platform'] ?? '', (string) $key['key']);
    $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border-collapse:separate;border-radius:14px;background:#17171c;border:1px dashed #ff8a2b"><tr><td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif">'
      . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#ff8a2b;font-weight:700">Dein ' . pg_h((string) ($key['platform'] ?? 'Steam')) . '-Key für ' . pg_h($title) . '</div>'
      . '<div style="font-family:Consolas,Menlo,monospace;font-size:20px;color:#ffffff;letter-spacing:.06em;margin:8px 0 6px">' . pg_h((string) $key['key']) . '</div>'
      . ($redeem !== '' ? '<a href="' . pg_h($redeem) . '" style="color:#ff8a2b;font-size:13px;text-decoration:none">Direkt auf Steam aktivieren →</a>' : '<span style="color:#8b8b97;font-size:12px">Bitte nicht weitergeben – der Key ist nur für dich.</span>')
      . '</td></tr></table>';
  }
  if (!empty($opts['card']) && $game && strpos((string) $msgHtml, '[[spiel:') === false) $body .= pg_mail_game_card($scope);
  if (!empty($opts['optin'])) {
    $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border-collapse:separate;border-radius:14px;background:#111114;border:1px solid #26262c"><tr><td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif">'
      . '<div style="font-size:15px;color:#ffffff;font-weight:700">Weiter auf dem Laufenden bleiben?</div>'
      . '<div style="font-size:13px;color:#b9b9c4;line-height:1.6;margin:4px 0 12px">Mit einem Klick bekommst du unseren Newsletter – neue Spiele, Devlogs und Termine. Jederzeit abbestellbar.</div>'
      . '<a href="' . pg_h(pg_optin_link($email, 'Warteliste ' . $title)) . '" style="display:inline-block;padding:10px 20px;border-radius:999px;border:1px solid #ff8a2b;color:#ff8a2b;font-weight:700;font-size:14px;text-decoration:none">Ja, Newsletter abonnieren</a>'
      . '</td></tr></table>';
  }
  $body .= pg_mail_signature(array_key_exists('sig', $opts) ? $opts['sig'] : null);
  $body .= '<p style="color:#6f6f7a;font-size:12px;margin:22px 0 0">Du erhältst diese Mail, weil du dich für die Beta-Warteliste von ' . pg_h($title) . ' eingetragen hast. '
         . '<a href="' . pg_h(pg_wl_leave_link($email, $scope)) . '" style="color:#ff8a2b">Von der Warteliste abmelden</a></p>';
  return pg_mail_shell($body, mb_substr(pg_mail_html_to_text($msg), 0, 90));
}
function pg_wl_campaigns(){ $c = pg_load_json(PG_WL_CAMPAIGNS); return is_array($c) ? $c : []; }
function pg_wl_campaigns_save($all){ return pg_save_json(PG_WL_CAMPAIGNS, array_slice($all, -30, null, true)); }
// Etappe senden: bis zu $budget Sekunden; gibt Fortschritt zurück
function pg_wl_campaign_step($id, $budget = 12){
  @set_time_limit($budget + 40);
  $lk = @fopen(PG_DATA_DIR . '/.wl-campaign.lock', 'c');
  if ($lk && !flock($lk, LOCK_EX | LOCK_NB)) return ['ok' => true, 'busy' => true];
  $all = pg_wl_campaigns();
  if (!isset($all[$id])) { if ($lk) fclose($lk); return ['ok' => false, 'error' => 'Versand nicht gefunden.']; }
  $c = $all[$id]; $t0 = microtime(true);
  [$fName, $fEmail, $fReply] = pg_mail_sender($c['opts']['sig'] ?? null);
  foreach ($c['recipients'] as $r) {
    if (microtime(true) - $t0 > $budget) break;
    $em = $r['email'];
    if (isset($c['sent'][$em]) || isset($c['failed'][$em]) || isset($c['skipped'][$em])) continue;
    $key = null;
    if (!empty($c['opts']['keys'])) {
      $key = pg_wl_claim_key($c['scope'], (string) ($c['opts']['platform'] ?? ''), $em);
      if (!$key) { $c['skipped'][$em] = 'kein Key mehr frei'; continue; }
    }
    $html = pg_wl_mail_html($c['html'], $c['scope'], $em, $r['rank'], $key, $c['opts']);
    $subject = strtr($c['subject'], ['{platz}' => (string) $r['rank'], '{spiel}' => (string) ($c['title'] ?? '')]);
    $ok = pg_send_mail($em, $subject, $html, $fReply, ['from_name' => $fName, 'from_email' => $fEmail]);
    if ($ok) { $c['sent'][$em] = date('c'); pg_wl_mark_mailed($c['scope'], $em, $subject, $key ? (string) $key['key'] : ''); }
    else { $c['failed'][$em] = date('c'); if ($key) pg_wl_release_key($key['id']); }
    $all[$id] = $c; pg_wl_campaigns_save($all);             // nach jeder Mail sichern → kein Doppelversand
    usleep(120000);
  }
  $done = count($c['sent'] ?? []) + count($c['failed'] ?? []) + count($c['skipped'] ?? []);
  $left = count($c['recipients']) - $done;
  if ($left <= 0 && empty($c['finished'])) {
    $c['finished'] = date('c'); $all[$id] = $c; pg_wl_campaigns_save($all);
    pg_mail_sent_log('📣 Warteliste ' . ($c['title'] ?? $c['scope']) . ' · ' . count($c['sent'] ?? []) . ' Empfänger', $c['subject'],
      pg_wl_mail_html($c['html'], $c['scope'], 'beispiel@warteliste.de', 1, !empty($c['opts']['keys']) ? ['key' => 'XXXXX-XXXXX-XXXXX', 'platform' => $c['opts']['platform'] ?: 'Steam'] : null, $c['opts']));
    pg_log_activity('Warteliste angeschrieben', ($c['title'] ?? $c['scope']) . ' · ' . count($c['sent'] ?? []) . ' Mails' . (!empty($c['opts']['keys']) ? ' mit Key' : ''));
  }
  if ($lk) { flock($lk, LOCK_UN); fclose($lk); }
  return ['ok' => true, 'sent' => count($c['sent'] ?? []), 'failed' => count($c['failed'] ?? []), 'skipped' => count($c['skipped'] ?? []), 'left' => max(0, $left), 'total' => count($c['recipients'])];
}
