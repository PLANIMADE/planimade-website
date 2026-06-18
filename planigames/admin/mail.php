<?php
/**
 * PLANIGAMES — E-Mail-Dashboard (Posteingang via IMAP, Versand via SMTP).
 * Gebrandete Mails (mit Studio-Logo), direkt im Admin lesen & schreiben.
 */
require __DIR__ . '/lib.php';
$SCHEMA = require __DIR__ . '/schema.php';
pg_session_boot();
if (pg_logged_in() && !pg_current_user()) { $_SESSION = []; session_destroy(); }

if (!pg_logged_in()) { header('Location: index.php'); exit; }
if (!pg_can('mail')) { header('Location: index.php'); exit; }

$action = $_GET['action'] ?? '';
$tab    = $_GET['tab'] ?? 'inbox';
$flash  = '';

/* ---- Mail-Body für die IFrame-Vorschau (isoliert, kein Skript) ---- */
if ($action === 'body') {
  $uid = (int)($_GET['uid'] ?? 0);
  $folder = preg_replace('/[^A-Za-z0-9._\-]/', '', $_GET['folder'] ?? 'INBOX') ?: 'INBOX';
  header('Content-Type: text/html; charset=UTF-8');
  header("Content-Security-Policy: default-src 'none'; img-src https: http: data: cid:; style-src 'unsafe-inline'; font-src https: data:");
  $mbox = pg_imap_connect($folder);
  if (!$mbox || !$uid) { echo '<p style="font-family:sans-serif;color:#888;padding:1rem">Nachricht nicht verfügbar.</p>'; exit; }
  $msg = pg_imap_body($mbox, $uid);
  imap_close($mbox);
  if ($msg['html']) {
    // einfache Härtung: <script>/Event-Handler entfernen
    $b = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $msg['body']);
    $b = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $b);
    echo '<base target="_blank"><div style="font-family:system-ui,Arial,sans-serif;color:#111">' . $b . '</div>';
  } else {
    echo '<div style="font-family:system-ui,Arial,sans-serif;white-space:pre-wrap;word-break:break-word;color:#111;padding:.5rem">'
       . pg_h($msg['body']) . '</div>';
  }
  exit;
}

/* ---- Einstellungen speichern ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  pg_csrf_check();
  $c = [
    'from_name'  => trim($_POST['from_name'] ?? 'PLANIGAMES'),
    'from_email' => trim($_POST['from_email'] ?? ''),
    'smtp_host'  => trim($_POST['smtp_host'] ?? ''),
    'smtp_port'  => (int)($_POST['smtp_port'] ?? 587),
    'smtp_secure'=> in_array($_POST['smtp_secure'] ?? 'tls', ['tls','ssl',''], true) ? $_POST['smtp_secure'] : 'tls',
    'smtp_user'  => trim($_POST['smtp_user'] ?? ''),
    'smtp_pass'  => (string)($_POST['smtp_pass'] ?? ''),
    'imap_host'  => trim($_POST['imap_host'] ?? ''),
    'imap_port'  => (int)($_POST['imap_port'] ?? 993),
    'imap_secure'=> in_array($_POST['imap_secure'] ?? 'ssl', ['ssl','tls',''], true) ? $_POST['imap_secure'] : 'ssl',
    'imap_user'  => trim($_POST['imap_user'] ?? ''),
    'imap_pass'  => (string)($_POST['imap_pass'] ?? ''),
    'sig_enabled'=> !empty($_POST['sig_enabled']),
    'sig_logo'   => !empty($_POST['sig_logo']),
    'sig_name'   => trim($_POST['sig_name'] ?? ''),
    'sig_role'   => trim($_POST['sig_role'] ?? ''),
    'sig_phone'  => trim($_POST['sig_phone'] ?? ''),
    'signature'  => trim($_POST['signature'] ?? ''),
  ];
  $ok = pg_mail_config_save($c);
  $flash = $ok ? '<div class="flash ok">✓ Einstellungen gespeichert.</div>' : '<div class="flash err">Konnte nicht speichern (Schreibrechte data/ prüfen).</div>';
  $tab = 'settings';
}

/* ---- Mail senden ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_mail'])) {
  pg_csrf_check();
  $to = trim($_POST['to'] ?? '');
  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $flash = '<div class="flash err">Bitte eine gültige Empfänger-Adresse eingeben.</div>'; $tab = 'compose';
  } elseif ($subject === '' || $message === '') {
    $flash = '<div class="flash err">Betreff und Nachricht dürfen nicht leer sein.</div>'; $tab = 'compose';
  } else {
    $bodyHtml = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . nl2br(pg_h($message)) . '</div>' . pg_mail_signature();
    $html = pg_mail_shell($bodyHtml, mb_substr($message, 0, 90));
    $ok = pg_send_mail($to, $subject, $html, pg_mail_from()[1]);
    if ($ok) {
      $how = pg_mail_configured_send() ? 'per SMTP' : 'über den Server (mail())';
      $flash = '<div class="flash ok">✓ Nachricht an ' . pg_h($to) . ' gesendet (' . $how . ').</div>'; $tab = 'compose';
    } else {
      $flash = '<div class="flash err">Versand fehlgeschlagen. Bitte SMTP-Einstellungen prüfen.</div>'; $tab = 'compose';
    }
  }
}

/* ---- Newsletter an alle Abonnenten ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
  pg_csrf_check();
  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $subs = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  $emails = array_values(array_filter(array_map(fn($r) => trim($r['email'] ?? ''), is_array($subs) ? $subs : []),
            fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
  if ($subject === '' || $message === '') {
    $flash = '<div class="flash err">Betreff und Nachricht dürfen nicht leer sein.</div>';
  } elseif (!$emails) {
    $flash = '<div class="flash err">Noch keine Abonnenten vorhanden.</div>';
  } else {
    @set_time_limit(120);
    $sent = 0; $fail = 0;
    foreach ($emails as $to) {
      $unsub = pg_unsub_link($to);
      $body = '<div style="color:#d7d7df;line-height:1.7;font-size:15px;padding:6px 0">' . nl2br(pg_h($message)) . '</div>'
        . '<p style="color:#6f6f7a;font-size:12px;margin:22px 0 0">Du erhältst diese Mail, weil du den PLANIGAMES-Newsletter abonniert hast. '
        . '<a href="' . pg_h($unsub) . '" style="color:#ff8a2b">Abmelden</a></p>';
      $html = pg_mail_shell($body, mb_substr($message, 0, 90));
      if (pg_send_mail($to, $subject, $html)) $sent++; else $fail++;
      usleep(120000); // freundlich zum Mailserver
    }
    $flash = '<div class="flash ' . ($fail ? 'err' : 'ok') . '">✓ Newsletter verschickt: ' . $sent . ' zugestellt'
           . ($fail ? ', ' . $fail . ' fehlgeschlagen' : '') . '.</div>';
  }
  $tab = 'newsletter';
}

/* ====================== ANSICHT ====================== */
pg_view_head('E-Mails');
pg_view_topbar($SCHEMA, 'mail');
echo $flash;
echo '<div class="editor mailwrap">';

// Tab-Leiste
$tabs = ['inbox'=>'📥 Posteingang', 'compose'=>'✍️ Verfassen', 'newsletter'=>'📣 Newsletter', 'settings'=>'⚙️ Einstellungen'];
echo '<div class="mailtabs">';
foreach ($tabs as $k => $lbl) {
  echo '<a href="mail.php?tab=' . $k . '"' . ($tab === $k ? ' class="on"' : '') . '>' . $lbl . '</a>';
}
echo '</div>';

/* ---------------- POSTEINGANG ---------------- */
if ($tab === 'inbox') {
  if (!pg_imap_available()) {
    echo '<div class="mailnote">Die <b>IMAP-Erweiterung</b> ist auf diesem Server nicht aktiv. Bei All-Inkl lässt sich der Posteingang trotzdem über dein E-Mail-Postfach lesen – '
       . 'der Empfang im Dashboard benötigt aber die PHP-IMAP-Funktion. Verfassen &amp; Senden funktioniert unabhängig davon.</div>';
  } elseif (!pg_mail_configured_recv()) {
    echo '<div class="mailnote">Noch kein Postfach verbunden. Hinterlege unter <a href="mail.php?tab=settings">⚙️ Einstellungen</a> die IMAP-Zugangsdaten deines All-Inkl-Postfachs.</div>';
  } else {
    $uid = (int)($_GET['uid'] ?? 0);
    $folder = preg_replace('/[^A-Za-z0-9._\-]/', '', $_GET['folder'] ?? 'INBOX') ?: 'INBOX';
    $mbox = pg_imap_connect($folder);
    if (!$mbox) {
      echo '<div class="flash err">Verbindung zum Postfach fehlgeschlagen. Bitte IMAP-Zugangsdaten prüfen.</div>';
    } elseif ($uid) {
      // Einzelansicht
      $hdr = imap_fetch_overview($mbox, (string)$uid, FT_UID);
      $h = $hdr[0] ?? null;
      echo '<a class="mailback" href="mail.php?tab=inbox">← Zurück zum Posteingang</a>';
      if ($h) {
        echo '<div class="mailview">';
        echo '<h1 class="mailsubj">' . pg_h(pg_imap_decode($h->subject ?? '(kein Betreff)')) . '</h1>';
        echo '<div class="mailmeta"><b>' . pg_h(pg_imap_decode($h->from ?? '')) . '</b>'
           . '<span>' . pg_h(date('d.m.Y · H:i', strtotime($h->date ?? 'now'))) . '</span></div>';
        // Antworten-Button
        $replyTo = '';
        if (preg_match('/<([^>]+)>/', $h->from ?? '', $m)) $replyTo = $m[1]; else $replyTo = trim($h->from ?? '');
        $resub = preg_match('/^re:/i', $h->subject ?? '') ? $h->subject : 'Re: ' . pg_imap_decode($h->subject ?? '');
        echo '<a class="btn-add" href="mail.php?tab=compose&to=' . urlencode($replyTo) . '&subject=' . urlencode($resub) . '">↩︎ Antworten</a>';
        echo '<iframe class="mailframe" src="mail.php?action=body&folder=' . urlencode($folder) . '&uid=' . $uid . '" sandbox="allow-popups allow-popups-to-escape-sandbox"></iframe>';
        echo '</div>';
        // als gelesen markieren
        @imap_setflag_full($mbox, (string)$uid, '\\Seen', ST_UID);
      } else {
        echo '<div class="flash err">Nachricht nicht gefunden.</div>';
      }
      imap_close($mbox);
    } else {
      // Liste
      $list = pg_imap_list($mbox, 40);
      imap_close($mbox);
      echo '<p class="muted" style="margin:.2rem 0 1rem">' . (int)$list['total'] . ' Nachricht' . ($list['total'] === 1 ? '' : 'en') . ' im Postfach · neueste zuerst</p>';
      if (!$list['items']) {
        echo '<div class="mailnote">Posteingang ist leer.</div>';
      } else {
        echo '<div class="maillist">';
        foreach ($list['items'] as $it) {
          $cls = $it['seen'] ? '' : ' unread';
          echo '<a class="mailrow' . $cls . '" href="mail.php?tab=inbox&uid=' . $it['uid'] . '">'
             . '<span class="mr-from">' . pg_h($it['from'] ?: $it['from_email']) . '</span>'
             . '<span class="mr-subj">' . pg_h($it['subject']) . '</span>'
             . '<span class="mr-date">' . pg_h($it['date'] ? date('d.m.y', $it['date']) : '') . '</span>'
             . '</a>';
        }
        echo '</div>';
      }
    }
  }
}

/* ---------------- VERFASSEN ---------------- */
if ($tab === 'compose') {
  $to = pg_h($_GET['to'] ?? '');
  $subject = pg_h($_GET['subject'] ?? '');
  $sendInfo = pg_mail_configured_send()
    ? 'Versand über deinen SMTP-Server. Die Mail wird im gebrandeten PLANIGAMES-Layout (mit deinem Logo) verschickt.'
    : 'Noch kein SMTP hinterlegt – es wird die Server-Funktion <code>mail()</code> genutzt. Für zuverlässige Zustellung empfiehlt sich SMTP unter <a href="mail.php?tab=settings">Einstellungen</a>.';
  echo '<div class="mailnote">' . $sendInfo . '</div>';
  echo '<form method="post" class="fields" style="margin-top:1rem">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<div class="field"><label class="flabel">An (E-Mail)</label><input type="text" name="to" value="' . $to . '" placeholder="empfaenger@beispiel.de" required></div>';
  echo '<div class="field"><label class="flabel">Betreff</label><input type="text" name="subject" value="' . $subject . '" required></div>';
  echo '<div class="field"><label class="flabel">Nachricht</label><textarea name="message" rows="11" required placeholder="Deine Nachricht …"></textarea>'
     . '<p class="hint">Reiner Text – Zeilenumbrüche bleiben erhalten. Die Mail wird automatisch ins PLANIGAMES-Design eingebettet.</p></div>';
  echo '<div class="editor-foot"><button class="btn-primary" name="send_mail" value="1">Senden →</button></div>';
  echo '</form>';
}

/* ---------------- NEWSLETTER ---------------- */
if ($tab === 'newsletter') {
  $subs = pg_load_json(PG_DATA_DIR . '/subscribers.json');
  $count = is_array($subs) ? count($subs) : 0;
  echo '<div class="mailnote">Sende eine gebrandete Nachricht an alle <b>' . $count . '</b> Newsletter-Abonnent' . ($count === 1 ? 'en' : 'en') . '. '
     . 'Jede Mail enthält automatisch einen Abmeldelink. ' . (pg_mail_configured_send() ? 'Versand per SMTP.' : 'Versand über die Server-Funktion mail() – für große Listen SMTP empfohlen.') . '</div>';
  echo '<form method="post" class="fields" style="margin-top:1rem" onsubmit="return confirm(\'Newsletter jetzt an alle ' . $count . ' Abonnenten senden?\')">';
  echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';
  echo '<div class="field"><label class="flabel">Betreff</label><input type="text" name="subject" required></div>';
  echo '<div class="field"><label class="flabel">Nachricht</label><textarea name="message" rows="11" required placeholder="Neuigkeiten, Devlogs, Release-Termine …"></textarea>'
     . '<p class="hint">Reiner Text. Wird automatisch ins PLANIGAMES-Design (mit Logo &amp; Abmeldelink) eingebettet.</p></div>';
  echo '<div class="editor-foot">';
  echo '<a class="btn-add" href="index.php?view=subscribers">Abonnenten ansehen</a>';
  echo '<button class="btn-primary"' . ($count ? '' : ' disabled') . ' name="send_newsletter" value="1">An ' . $count . ' Abonnenten senden →</button></div>';
  echo '</form>';
}

/* ---------------- EINSTELLUNGEN ---------------- */
if ($tab === 'settings') {
  if (!pg_is_owner()) {
    echo '<div class="mailnote">Nur der Owner darf die Postfach-Verbindung konfigurieren.</div>';
  } else {
    $c = pg_mail_config();
    $v = fn($k) => pg_h($c[$k] ?? '');
    echo '<div class="mailnote">All-Inkl-Postfächer findest du im KAS unter „E-Mail“. Typische Werte: Server <code>w01xxxxx.kasserver.com</code>, '
       . 'SMTP-Port 587 (TLS) oder 465 (SSL), IMAP-Port 993 (SSL). Benutzername = vollständige E-Mail-Adresse.</div>';
    echo '<form method="post" class="fields" style="margin-top:1rem">';
    echo '<input type="hidden" name="csrf" value="' . pg_h(pg_csrf()) . '">';

    echo '<h2 class="sub-h" style="margin-top:.5rem">Absender</h2>';
    echo '<div class="field"><label class="flabel">Absender-Name</label><input type="text" name="from_name" value="' . $v('from_name') . '" placeholder="PLANIGAMES"></div>';
    echo '<div class="field"><label class="flabel">Absender-Adresse</label><input type="text" name="from_email" value="' . $v('from_email') . '" placeholder="hallo@planigames.de"></div>';

    echo '<h2 class="sub-h">Versand (SMTP)</h2>';
    echo '<div class="field"><label class="flabel">SMTP-Server</label><input type="text" name="smtp_host" value="' . $v('smtp_host') . '" placeholder="w01xxxxx.kasserver.com"></div>';
    echo '<div class="mailgrid">';
    echo '<div class="field"><label class="flabel">Port</label><input type="number" name="smtp_port" value="' . $v('smtp_port') . '"></div>';
    echo '<div class="field"><label class="flabel">Verschlüsselung</label><select name="smtp_secure">';
    foreach (['tls'=>'STARTTLS (587)','ssl'=>'SSL (465)',''=>'keine'] as $k=>$lbl) echo '<option value="' . $k . '"' . ($c['smtp_secure']===$k?' selected':'') . '>' . $lbl . '</option>';
    echo '</select></div></div>';
    echo '<div class="field"><label class="flabel">Benutzer (E-Mail)</label><input type="text" name="smtp_user" value="' . $v('smtp_user') . '" placeholder="hallo@planigames.de"></div>';
    echo '<div class="field"><label class="flabel">Passwort</label><input type="password" name="smtp_pass" value="" placeholder="' . ($c['smtp_pass']?'•••••• (gespeichert – leer lassen zum Behalten)':'Postfach-Passwort') . '" autocomplete="new-password"></div>';

    echo '<h2 class="sub-h">Empfang (IMAP)</h2>';
    if (!pg_imap_available()) echo '<p class="hint" style="color:#ffb37a">Hinweis: Die PHP-IMAP-Erweiterung ist hier nicht aktiv. Auf All-Inkl ist sie i. d. R. verfügbar.</p>';
    echo '<div class="field"><label class="flabel">IMAP-Server</label><input type="text" name="imap_host" value="' . $v('imap_host') . '" placeholder="w01xxxxx.kasserver.com"></div>';
    echo '<div class="mailgrid">';
    echo '<div class="field"><label class="flabel">Port</label><input type="number" name="imap_port" value="' . $v('imap_port') . '"></div>';
    echo '<div class="field"><label class="flabel">Verschlüsselung</label><select name="imap_secure">';
    foreach (['ssl'=>'SSL (993)','tls'=>'STARTTLS (143)',''=>'keine'] as $k=>$lbl) echo '<option value="' . $k . '"' . ($c['imap_secure']===$k?' selected':'') . '>' . $lbl . '</option>';
    echo '</select></div></div>';
    echo '<div class="field"><label class="flabel">Benutzer (E-Mail)</label><input type="text" name="imap_user" value="' . $v('imap_user') . '" placeholder="hallo@planigames.de"></div>';
    echo '<div class="field"><label class="flabel">Passwort</label><input type="password" name="imap_pass" value="" placeholder="' . ($c['imap_pass']?'•••••• (gespeichert – leer lassen zum Behalten)':'Postfach-Passwort') . '" autocomplete="new-password"></div>';

    echo '<h2 class="sub-h">Signatur</h2>';
    echo '<p class="hint" style="margin-bottom:.6rem">Erscheint als gebrandeter Block unter deinen persönlichen Mails (Verfassen &amp; Antworten). '
       . 'E-Mail, Website &amp; Social-Links werden automatisch aus den Studio-Einstellungen ergänzt.</p>';
    $sigOn = ($c['sig_enabled'] ?? true) ? ' checked' : '';
    $logoOn = ($c['sig_logo'] ?? true) ? ' checked' : '';
    echo '<div class="field"><input type="hidden" name="sig_enabled" value="0">'
       . '<label class="switch"><input type="checkbox" name="sig_enabled" value="1"' . $sigOn . '><span>Signatur anhängen</span></label></div>';
    echo '<div class="field"><input type="hidden" name="sig_logo" value="0">'
       . '<label class="switch"><input type="checkbox" name="sig_logo" value="1"' . $logoOn . '><span>Logo in der Signatur anzeigen</span></label>'
       . '<p class="hint">Zeigt dein hochgeladenes Studio-Logo links neben Name &amp; Rolle.</p></div>';
    echo '<div class="mailgrid">';
    echo '<div class="field"><label class="flabel">Name</label><input type="text" name="sig_name" value="' . $v('sig_name') . '" placeholder="Dominic Majewski"></div>';
    echo '<div class="field"><label class="flabel">Rolle / Titel</label><input type="text" name="sig_role" value="' . $v('sig_role') . '" placeholder="Founder · PLANIGAMES"></div>';
    echo '</div>';
    echo '<div class="field"><label class="flabel">Telefon (optional)</label><input type="text" name="sig_phone" value="' . $v('sig_phone') . '" placeholder="+49 …"></div>';
    echo '<div class="field"><label class="flabel">Schlusszeile (optional)</label><input type="text" name="signature" value="' . $v('signature') . '" placeholder="Bis bald im Chaos,">'
       . '<p class="hint">Kurze Grußzeile über deinem Namen, z. B. „Beste Grüße" oder „Bis bald im Chaos".</p></div>';

    echo '<div class="editor-foot"><button class="btn-primary" name="save_settings" value="1">Einstellungen speichern</button></div>';
    echo '</form>';
  }
}

echo '</div>';
pg_view_foot();
