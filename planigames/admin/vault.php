<?php
/**
 * PLANIGAMES — Blueprint Vault (UE5-Blueprint-Bibliothek + KI-Generator).
 *
 * Server-Version des Standalone-Prototyps: Bibliothek liegt zentral in
 * data/blueprint_vault.php (alle Admins sehen denselben Stand), der
 * Anthropic-Zugriff läuft über einen PHP-Proxy – der API-Key bleibt
 * ausschließlich auf dem Server (data/vault_config.php).
 *
 * Speicherformat: JSON hinter einer "<?php exit;"-Schutzzeile. Die Datei
 * liegt als .php in data/ und ist damit schon durch die bestehende
 * data/.htaccess-Regel (alle .php verboten) gegen HTTP-Zugriff gesperrt –
 * und gibt selbst bei direktem Aufruf nichts aus.
 */
require __DIR__ . '/lib.php';
$SCHEMA = require __DIR__ . '/schema.php';
pg_session_boot();
if (pg_logged_in() && !pg_current_user()) { $_SESSION = []; session_destroy(); }

define('PG_VAULT_FILE',   PG_DATA_DIR . '/blueprint_vault.php');
define('PG_VAULT_BACKUP', PG_DATA_DIR . '/blueprint_vault.backup.php');
define('PG_VAULT_GUARD',  "<?php exit; ?>\n");
define('PG_VAULT_MODELS', ['claude-sonnet-4-6', 'claude-opus-4-8']);

/* System-Prompt der Generierung – unverändert aus dem Prototyp, jetzt NUR server-seitig. */
define('PG_VAULT_SYSTEM_PROMPT', <<<'PGVAULTSYS'
Du bist ein Experte für Unreal Engine 5.6 Blueprint-Clipboard-Serialisierung (K2Node-Format). Du generierst Paste-Code, den der Nutzer direkt per Strg+V in den Event Graph eines Blueprints einfügt oder auf blueprintue.com teilt. Der Code MUSS beim Einfügen fehlerfrei funktionieren.

HARTE REGELN:
1. Nur real existierende Engine-Referenzen: K2Node-Klassen aus /Script/BlueprintGraph.* und Funktionen über exakte Pfade wie /Script/CoreUObject.Class'/Script/Engine.Actor' oder /Script/Engine.KismetMathLibrary. Niemals Nodes oder Funktionsnamen erfinden. Im Zweifel eine einfachere, garantiert existierende Funktion nehmen.
2. UE5-Floats: PinType.PinCategory="real", PinType.PinSubCategory="double". Niemals PinCategory="float".
3. Jede PinId und jede NodeGuid: exakt 32 Hex-Zeichen (0-9, A-F), eindeutig innerhalb des gesamten Codes.
4. Jede Verbindung MUSS auf BEIDEN Pins eingetragen sein: LinkedTo=(NodeName PinId,) auf jeder Seite. Einseitige Links erzeugen kaputte Graphen.
5. KEINE K2Node_Timeline (Timelines sind nicht über Paste-Code teilbar). Stattdessen Event Tick + Interp-Funktionen (VInterpTo, VInterpTo_Constant, FInterpTo, Lerp).
6. Projekt-Variablen des Nutzers: VariableReference=(MemberName="Name",bSelfContext=True) OHNE MemberGuid. Der Nutzer legt diese Variablen vorher exakt gleichnamig an. Deutsche Variablennamen ohne Umlaute bevorzugen (Hoehe statt Höhe).
7. Referenzen auf projekteigene Blueprints/Assets NUR, wenn der Nutzer den exakten Asset-Pfad genannt hat (z.B. /Game/Custom/Wizard_character/BP_Wizard.BP_Wizard_C). Sonst ausschließlich Engine-Klassen.
8. Bei replizierter Gameplay-Logik: relevante Pfade hinter einem HasAuthority-Check (AActor::HasAuthority als pure Funktion + K2Node_IfThenElse). Kein Macro "Switch Has Authority" verwenden (GraphGuid nicht portabel).
9. Sinnvolle NodePosX/NodePosY: Exec-Fluss von links nach rechts (~220-300px Abstand), Pure-Nodes leicht unterhalb ihrer Verbraucher, getrennte Event-Stränge vertikal ~300px auseinander.
10. Vollständige Pin-Boilerplate wie im echten UE-Export: alle PinType.*-Felder, PersistentGuid=00000000000000000000000000000000, bHidden, bNotConnectable, bDefaultValueIsReadOnly, bDefaultValueIsIgnored, bAdvancedView, bOrphanedPin. Pure CallFunction-Nodes bekommen bIsPureFunc=True. Hidden self-Pins mitliefern.
11. Struct-Vector-Pins: PinSubCategoryObject="/Script/CoreUObject.ScriptStruct'/Script/CoreUObject.Vector'", Defaults "0, 0, 0".

ANTWORTFORMAT — exakt diese drei Marker, nichts davor, nichts danach, keine Markdown-Codeblöcke:
###VARIABLEN###
Name | Typ | Default | Kurznotiz
(eine Variable pro Zeile; falls keine nötig, schreibe: keine)
###SETUP###
Kurze Stichpunkte: Komponenten, Class Defaults (z.B. Replicates), Collision-Settings etc.
###CODE###
Begin Object ... End Object (kompletter roher Paste-Code)
PGVAULTSYS);

/* ---------------- Konfiguration (Key NUR server-seitig) ---------------- */
function pg_vault_config(){
  $file = PG_DATA_DIR . '/vault_config.php';
  $c = is_file($file) ? (include $file) : [];
  return (is_array($c) ? $c : []) + [
    'api_key'    => '',      // Anthropic API-Key (sk-ant-…)
    'stream'     => true,    // true = SSE-Streaming, false = kompletter Request mit Spinner
    'max_tokens' => 32000,
    'timeout'    => 300,     // Sekunden für den Anthropic-Request
  ];
}

/* ---------------- Speicher (atomar, mit flock + Backup) ---------------- */
function pg_vault_now(){ return (int) round(microtime(true) * 1000); }   // ms wie Date.now()

function pg_vault_write($items){
  if (is_file(PG_VAULT_FILE)) @copy(PG_VAULT_FILE, PG_VAULT_BACKUP);     // letzte Version behalten
  $json = json_encode(array_values($items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  $tmp = PG_VAULT_FILE . '.tmp';
  $fh = @fopen($tmp, 'wb');
  if (!$fh) return false;
  flock($fh, LOCK_EX);
  fwrite($fh, PG_VAULT_GUARD . $json);
  fflush($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  return rename($tmp, PG_VAULT_FILE);
}

function pg_vault_load(){
  if (!is_file(PG_VAULT_FILE)) {
    // Allererster Start: Seed einmalig einspielen
    $seed = require __DIR__ . '/vault_seed.php';
    $now = pg_vault_now();
    foreach ($seed as &$e) { $e['createdAt'] = $now; $e['updatedAt'] = $now; }
    unset($e);
    pg_vault_write($seed);
    return $seed;
  }
  $raw = (string) @file_get_contents(PG_VAULT_FILE);
  $nl = strpos($raw, "\n");
  $data = json_decode($nl === false ? $raw : substr($raw, $nl + 1), true);
  return is_array($data) ? $data : [];
}

function pg_vault_uid(){ return base_convert((string) pg_vault_now(), 10, 36) . bin2hex(random_bytes(3)); }

/* Eintrag normalisieren – name und code sind Pflicht, Rest tolerant. */
function pg_vault_clean($e){
  if (!is_array($e)) return null;
  $name = trim((string) ($e['name'] ?? ''));
  $code = trim((string) ($e['code'] ?? ''));
  if ($name === '' || $code === '') return null;
  $vars = [];
  foreach ((array) ($e['variables'] ?? []) as $v) {
    if (!is_array($v)) continue;
    $vars[] = [
      'name' => mb_substr((string) ($v['name'] ?? '?'), 0, 120),
      'type' => mb_substr((string) ($v['type'] ?? '?'), 0, 120),
      'def'  => mb_substr((string) ($v['def']  ?? '–'), 0, 200),
      'note' => mb_substr((string) ($v['note'] ?? ''), 0, 500),
    ];
  }
  return [
    'id'          => (string) ($e['id'] ?? ''),
    'name'        => mb_substr($name, 0, 200),
    'category'    => mb_substr(trim((string) ($e['category'] ?? 'Sonstiges')), 0, 60) ?: 'Sonstiges',
    'description' => (string) ($e['description'] ?? ''),
    'variables'   => $vars,
    'setup'       => (string) ($e['setup'] ?? ''),
    'code'        => $code,                       // Paste-Codes sind legitim mehrere 100 KB groß
    'createdAt'   => (int) ($e['createdAt'] ?? 0),
    'updatedAt'   => (int) ($e['updatedAt'] ?? 0),
  ];
}

/* ---------------- API-Helfer ---------------- */
function pg_vault_json($data, $status = 200){
  http_response_code($status);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
function pg_vault_input(){
  $j = json_decode((string) file_get_contents('php://input'), true);
  return is_array($j) ? $j : [];
}
function pg_vault_csrf_check(){
  $tok = (string) ($_SERVER['HTTP_X_VAULT_CSRF'] ?? '');
  if ($tok === '' || !hash_equals(pg_csrf(), $tok)) pg_vault_json(['error' => 'Sitzung abgelaufen – Seite neu laden.'], 403);
}

/* ================================================================
   API-Actions (alle nur mit Admin-Session, Schreibendes mit CSRF)
   ================================================================ */
$action = $_GET['action'] ?? '';
if ($action !== '') {
  if (!pg_logged_in()) pg_vault_json(['error' => 'Nicht eingeloggt'], 401);

  switch ($action) {

    case 'list':
      pg_vault_json(['items' => pg_vault_load()]);

    case 'export': {
      $items = pg_vault_load();
      header('Content-Type: application/json; charset=UTF-8');
      header('Content-Disposition: attachment; filename="blueprint-vault-' . date('Ymd') . '.json"');
      echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      exit;
    }

    case 'save': {
      pg_vault_csrf_check();
      $e = pg_vault_clean(pg_vault_input()['entry'] ?? null);
      if (!$e) pg_vault_json(['error' => 'Name und Paste-Code sind Pflicht.'], 400);
      $items = pg_vault_load();
      $now = pg_vault_now();
      if ($e['id'] !== '') {
        $found = false;
        foreach ($items as &$it) {
          if (($it['id'] ?? '') === $e['id']) {
            $e['createdAt'] = (int) ($it['createdAt'] ?? $now);
            $e['updatedAt'] = $now;
            $it = $e; $found = true; break;
          }
        }
        unset($it);
        if (!$found) pg_vault_json(['error' => 'Eintrag nicht gefunden (evtl. von jemand anderem gelöscht). Seite neu laden.'], 409);
      } else {
        $e['id'] = pg_vault_uid();
        $e['createdAt'] = $now;
        $e['updatedAt'] = $now;
        $items[] = $e;
      }
      if (!pg_vault_write($items)) pg_vault_json(['error' => 'Konnte nicht speichern (Schreibrechte am Ordner data/ prüfen).'], 500);
      pg_vault_json(['items' => $items, 'id' => $e['id']]);
    }

    case 'delete': {
      pg_vault_csrf_check();
      $id = (string) (pg_vault_input()['id'] ?? '');
      if ($id === '') pg_vault_json(['error' => 'id fehlt'], 400);
      $items = array_values(array_filter(pg_vault_load(), fn($x) => ($x['id'] ?? '') !== $id));
      if (!pg_vault_write($items)) pg_vault_json(['error' => 'Konnte nicht speichern.'], 500);
      pg_vault_json(['items' => $items]);
    }

    case 'import': {
      // Akzeptiert Export-Dateien des Standalone-Prototyps (JSON-Array) 1:1.
      pg_vault_csrf_check();
      $data = pg_vault_input()['data'] ?? null;
      if (!is_array($data) || array_keys($data) !== range(0, count($data) - 1)) {
        pg_vault_json(['error' => 'Keine gültige Export-Datei (JSON-Array erwartet).'], 400);
      }
      $items = pg_vault_load();
      $existing = [];
      foreach ($items as $it) $existing[(string) ($it['id'] ?? '')] = true;
      $now = pg_vault_now(); $added = 0;
      foreach ($data as $raw) {
        $e = pg_vault_clean($raw);
        if (!$e) continue;
        if ($e['id'] === '' || isset($existing[$e['id']])) $e['id'] = pg_vault_uid();
        if (!$e['createdAt']) $e['createdAt'] = $now;
        if (!$e['updatedAt']) $e['updatedAt'] = $now;
        $existing[$e['id']] = true;
        $items[] = $e; $added++;
      }
      if (!pg_vault_write($items)) pg_vault_json(['error' => 'Konnte nicht speichern.'], 500);
      pg_vault_json(['items' => $items, 'added' => $added]);
    }

    case 'wipe': {
      pg_vault_csrf_check();
      if (!pg_vault_write([])) pg_vault_json(['error' => 'Konnte nicht speichern.'], 500);
      pg_vault_json(['items' => []]);
    }

    case 'generate': {
      pg_vault_csrf_check();
      $cfg = pg_vault_config();
      $in = pg_vault_input();
      $prompt = trim((string) ($in['prompt'] ?? ''));
      $model = (string) ($in['model'] ?? '');
      if ($cfg['api_key'] === '') pg_vault_json(['error' => 'Auf dem Server ist kein API-Key hinterlegt (data/vault_config.php).'], 500);
      if ($prompt === '') pg_vault_json(['error' => 'Prompt fehlt.'], 400);
      if (!in_array($model, PG_VAULT_MODELS, true)) $model = PG_VAULT_MODELS[0];
      if (!function_exists('curl_init')) pg_vault_json(['error' => 'curl-Extension fehlt auf dem Server.'], 500);

      $payload = [
        'model'       => $model,
        'max_tokens'  => (int) $cfg['max_tokens'],
        'temperature' => 0.2,
        'system'      => PG_VAULT_SYSTEM_PROMPT,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
      ];
      @set_time_limit(0);

      $headers = [
        'content-type: application/json',
        'x-api-key: ' . $cfg['api_key'],
        'anthropic-version: 2023-06-01',
      ];

      if (!empty($cfg['stream'])) {
        /* ---- SSE-Passthrough: Anthropic-Stream 1:1 an den Browser ---- */
        $payload['stream'] = true;
        while (ob_get_level()) ob_end_clean();
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $status = 0; $errBuf = '';
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
          CURLOPT_POST           => true,
          CURLOPT_HTTPHEADER     => $headers,
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
          CURLOPT_TIMEOUT        => (int) $cfg['timeout'],
          CURLOPT_CONNECTTIMEOUT => 20,
          CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$status) {
            if (!$status) { $code = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE); if ($code) $status = $code; }
            return strlen($h);
          },
          CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$status, &$errBuf) {
            if ($status !== 200) { $errBuf .= $chunk; return strlen($chunk); }   // Fehler-Body sammeln
            echo $chunk;
            flush();
            if (connection_aborted()) return -1;    // Browser hat abgebrochen -> Upstream-Request beenden
            return strlen($chunk);
          },
        ]);
        curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
        // Fehler als SSE-Event im Format, das der Client ohnehin versteht (type:"error")
        if ($status !== 0 && $status !== 200) {
          $j = json_decode($errBuf, true);
          $msg = $j['error']['message'] ?? ('HTTP ' . $status);
          echo 'data: ' . json_encode(['type' => 'error', 'error' => ['message' => $msg]], JSON_UNESCAPED_UNICODE) . "\n\n";
          flush();
        } elseif ($curlErr && !connection_aborted()) {
          echo 'data: ' . json_encode(['type' => 'error', 'error' => ['message' => 'Verbindungsfehler: ' . $curlErr]], JSON_UNESCAPED_UNICODE) . "\n\n";
          flush();
        }
        exit;
      }

      /* ---- Non-Streaming-Fallback: kompletter Request, Antwort als JSON ---- */
      $ch = curl_init('https://api.anthropic.com/v1/messages');
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) $cfg['timeout'],
        CURLOPT_CONNECTTIMEOUT => 20,
      ]);
      $resp = curl_exec($ch);
      $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $curlErr = curl_error($ch);
      curl_close($ch);
      if ($resp === false) pg_vault_json(['error' => 'Verbindungsfehler: ' . $curlErr], 502);
      $j = json_decode((string) $resp, true);
      if ($status !== 200) {
        $msg = $j['error']['message'] ?? ('HTTP ' . $status);
        pg_vault_json(['error' => $msg], ($status >= 400 && $status < 600) ? $status : 502);
      }
      $full = '';
      foreach ((array) ($j['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $full .= (string) ($block['text'] ?? '');
      }
      pg_vault_json(['text' => $full]);
    }

    default:
      pg_vault_json(['error' => 'Unbekannte Action'], 404);
  }
}

/* ================================================================
   Seite (nur eingeloggt) – Design & UI 1:1 aus dem Prototyp
   ================================================================ */
if (!pg_logged_in()) { header('Location: index.php'); exit; }

$vaultCfg = pg_vault_config();
$vaultHasKey = $vaultCfg['api_key'] !== '';
$vaultStream = !empty($vaultCfg['stream']);
$vaultCsrf = pg_csrf();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Blueprint Vault · PLANIGAMES Admin</title>
<style>
:root{
  --bg:#141416;
  --graph:#1b1b1f;
  --node:#232329;
  --node-border:#35353d;
  --node-border-soft:#2c2c33;
  --ink:#e8e8ec;
  --ink-dim:#8b8b96;
  --ink-faint:#5c5c66;
  --select:#ff9a1f;
  --select-soft:rgba(255,154,31,.14);
  --danger:#c74440;
  --mono:ui-monospace,"JetBrains Mono","Cascadia Code",Consolas,"Liberation Mono",monospace;
  --sans:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  --r:6px;
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{
  font-family:var(--sans);
  background:var(--bg);
  color:var(--ink);
  font-size:15px;
  line-height:1.5;
}
::selection{background:var(--select);color:#141416}

/* ---------- Toolbar ---------- */
.toolbar{
  display:flex;align-items:center;gap:24px;flex-wrap:wrap;
  padding:14px 24px;
  background:var(--bg);
  border-bottom:1px solid var(--node-border-soft);
  position:sticky;top:0;z-index:40;
}
.wordmark{display:flex;align-items:center;gap:10px;user-select:none}
.wordmark svg{width:22px;height:22px;flex:none}
.wordmark .wm-text{
  font-weight:800;font-size:15px;letter-spacing:.14em;text-transform:uppercase;
}
.wordmark .wm-text em{font-style:normal;color:var(--select)}
.tabs{display:flex;gap:4px;margin-left:auto}
.tab{
  appearance:none;border:1px solid transparent;background:transparent;color:var(--ink-dim);
  font-family:var(--sans);font-size:13.5px;font-weight:600;letter-spacing:.02em;
  padding:7px 14px;border-radius:var(--r);cursor:pointer;
  text-decoration:none;display:inline-block;
}
.tab:hover{color:var(--ink)}
.tab.active{background:var(--node);border-color:var(--node-border);color:var(--ink)}
.tab:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{
  outline:2px solid var(--select);outline-offset:2px;
}

/* ---------- Graph area ---------- */
.graph{
  min-height:calc(100vh - 61px);
  background-color:var(--graph);
  background-image:
    linear-gradient(rgba(255,255,255,.032) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.032) 1px, transparent 1px),
    linear-gradient(rgba(255,255,255,.055) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.055) 1px, transparent 1px);
  background-size:24px 24px,24px 24px,120px 120px,120px 120px;
  padding:28px 24px 80px;
}
.view{max-width:1180px;margin:0 auto}
.view.hidden{display:none}

/* ---------- Library controls ---------- */
.lib-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:22px}
.search{
  flex:1;min-width:220px;
  background:var(--node);border:1px solid var(--node-border);border-radius:var(--r);
  color:var(--ink);padding:9px 12px;font-family:var(--sans);font-size:14px;
}
.search::placeholder{color:var(--ink-faint)}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{
  appearance:none;cursor:pointer;
  background:var(--node);border:1px solid var(--node-border);border-radius:999px;
  color:var(--ink-dim);font-size:12.5px;font-weight:600;padding:5px 12px;
  display:inline-flex;align-items:center;gap:7px;font-family:var(--sans);
}
.chip .dot{width:8px;height:8px;border-radius:2px;background:var(--dot,#4a4a52)}
.chip:hover{color:var(--ink)}
.chip.active{border-color:var(--select);color:var(--ink);background:var(--select-soft)}

/* ---------- Node cards ---------- */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.node-card{
  background:var(--node);
  border:1px solid var(--node-border);
  border-radius:var(--r);
  overflow:hidden;
  display:flex;flex-direction:column;
  transition:box-shadow .12s ease, border-color .12s ease, transform .12s ease;
}
.node-card:hover{
  border-color:var(--select);
  box-shadow:0 0 0 1px var(--select), 0 8px 24px rgba(0,0,0,.35);
  transform:translateY(-1px);
}
.node-head{
  --cat:#4a4a52;
  background:linear-gradient(180deg,rgba(255,255,255,.14),rgba(0,0,0,.18)),var(--cat);
  padding:8px 12px;
  display:flex;align-items:center;gap:9px;
  font-weight:700;font-size:14px;letter-spacing:.01em;
  text-shadow:0 1px 2px rgba(0,0,0,.4);
}
.node-head svg{width:12px;height:12px;flex:none;filter:drop-shadow(0 1px 1px rgba(0,0,0,.4))}
.node-head .cat-label{
  margin-left:auto;font-size:10.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
  opacity:.85;
}
.node-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:10px;flex:1}
.node-desc{color:var(--ink-dim);font-size:13.5px;min-height:2.7em;
  display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.node-meta{display:flex;gap:14px;align-items:center;font-family:var(--mono);font-size:11.5px;color:var(--ink-faint)}
.node-meta .pin{display:inline-flex;align-items:center;gap:5px}
.node-meta .pin i{width:8px;height:8px;border-radius:50%;background:#f7c948;display:inline-block}
.node-actions{display:flex;gap:8px;margin-top:auto}

/* ---------- Buttons ---------- */
.btn{
  appearance:none;cursor:pointer;font-family:var(--sans);
  border-radius:var(--r);font-size:13.5px;font-weight:650;
  padding:8px 14px;border:1px solid var(--node-border);
  background:#2b2b32;color:var(--ink);
  transition:background .1s ease,border-color .1s ease;
}
.btn:hover{background:#33333b}
.btn.primary{background:var(--select);border-color:var(--select);color:#141416}
.btn.primary:hover{background:#ffab42}
.btn.ghost{background:transparent}
.btn.ghost:hover{background:#2b2b32}
.btn.danger{color:#ff8a86;border-color:#573234}
.btn.danger:hover{background:#3a2426}
.btn.wide{width:100%}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn.small{padding:5px 10px;font-size:12.5px}

/* ---------- Empty state ---------- */
.empty{
  border:1px dashed var(--node-border);border-radius:var(--r);
  padding:48px 24px;text-align:center;color:var(--ink-dim);
  background:rgba(20,20,22,.5);
}
.empty h3{color:var(--ink);font-size:16px;margin-bottom:6px}
.empty .btn{margin-top:16px}

/* ---------- Modal ---------- */
.modal-wrap{
  position:fixed;inset:0;z-index:60;display:flex;align-items:flex-start;justify-content:center;
  padding:5vh 16px 16px;overflow-y:auto;
  background:rgba(10,10,12,.72);
}
.modal-wrap.hidden{display:none}
.modal{
  width:100%;max-width:860px;
  background:var(--node);border:1px solid var(--node-border);border-radius:8px;
  overflow:hidden;
  animation:pop .14s ease;
}
@keyframes pop{from{transform:scale(.985);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{
  --cat:#4a4a52;
  background:linear-gradient(180deg,rgba(255,255,255,.14),rgba(0,0,0,.18)),var(--cat);
  padding:12px 18px;display:flex;align-items:center;gap:10px;
  font-weight:750;font-size:16px;text-shadow:0 1px 2px rgba(0,0,0,.4);
}
.modal-head svg{width:13px;height:13px}
.modal-head .x{
  margin-left:auto;appearance:none;border:0;background:rgba(0,0,0,.25);color:#fff;
  width:28px;height:28px;border-radius:5px;cursor:pointer;font-size:15px;line-height:1;
}
.modal-head .x:hover{background:rgba(0,0,0,.45)}
.modal-body{padding:18px;display:flex;flex-direction:column;gap:18px}
.section-label{
  font-family:var(--mono);font-size:11px;letter-spacing:.12em;text-transform:uppercase;
  color:var(--ink-faint);margin-bottom:8px;
}
.var-list{display:flex;flex-direction:column;gap:6px}
.var-row{
  display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;
  font-family:var(--mono);font-size:12.5px;
  background:#1d1d22;border:1px solid var(--node-border-soft);border-radius:5px;
  padding:7px 10px;
}
.var-row .vdot{width:9px;height:9px;border-radius:50%;flex:none;align-self:center}
.var-row .vname{font-weight:700}
.var-row .vtype{color:var(--ink-dim)}
.var-row .vdefault{color:var(--ink-faint)}
.var-row .vnote{color:var(--ink-dim);flex-basis:100%;font-family:var(--sans);font-size:12.5px;padding-left:19px}
.setup-text{color:var(--ink-dim);font-size:13.5px;white-space:pre-wrap}
.codebox{position:relative}
.codebox pre{
  background:#121215;border:1px solid var(--node-border-soft);border-radius:5px;
  padding:12px;max-height:300px;overflow:auto;
  font-family:var(--mono);font-size:11px;line-height:1.55;color:#b9c4a8;
  white-space:pre;
}
.codebox .copy-float{position:absolute;top:8px;right:8px}
.modal-foot{display:flex;gap:8px;flex-wrap:wrap;padding:0 18px 18px}
.modal-foot .spacer{flex:1}

/* ---------- Forms ---------- */
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:12.5px;font-weight:650;color:var(--ink-dim)}
.field input[type=text],.field input[type=password],.field select,.field textarea{
  background:#1d1d22;border:1px solid var(--node-border);border-radius:5px;
  color:var(--ink);padding:9px 11px;font-family:var(--sans);font-size:14px;width:100%;
}
.field textarea{resize:vertical;min-height:80px}
.field textarea.code{font-family:var(--mono);font-size:11.5px;min-height:180px;white-space:pre;color:#b9c4a8}
.field .hint{font-size:12px;color:var(--ink-faint)}
.form-row{display:grid;grid-template-columns:1fr 220px;gap:14px}
@media (max-width:640px){.form-row{grid-template-columns:1fr}}

/* ---------- Generator ---------- */
.gen-layout{display:flex;flex-direction:column;gap:18px;max-width:860px;margin:0 auto}
.panel{
  background:var(--node);border:1px solid var(--node-border);border-radius:var(--r);
  padding:18px;display:flex;flex-direction:column;gap:14px;
}
.panel h2{font-size:15px;letter-spacing:.02em}
.gen-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.gen-bar select{
  background:#1d1d22;border:1px solid var(--node-border);border-radius:5px;
  color:var(--ink);padding:8px 10px;font-size:13px;
}
.stream-out{
  background:#121215;border:1px solid var(--node-border-soft);border-radius:5px;
  padding:12px;min-height:120px;max-height:340px;overflow:auto;
  font-family:var(--mono);font-size:11px;line-height:1.55;color:#9aa38c;white-space:pre-wrap;
}
.stream-out.idle{color:var(--ink-faint);font-family:var(--sans);font-size:13px}
.status-line{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--ink-dim);min-height:20px}
.spinner{
  width:13px;height:13px;border-radius:50%;flex:none;
  border:2px solid var(--node-border);border-top-color:var(--select);
  animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.notice{
  border:1px solid var(--node-border);border-left:3px solid var(--select);
  background:#1d1d22;border-radius:5px;padding:11px 13px;
  font-size:13px;color:var(--ink-dim);
}
.notice a{color:var(--select)}
.notice.warn{border-left-color:var(--danger)}

/* ---------- Settings ---------- */
.settings-layout{display:flex;flex-direction:column;gap:18px;max-width:640px;margin:0 auto}
.key-row{display:flex;gap:8px}
.key-row input{flex:1}

/* ---------- Toast ---------- */
.toast{
  position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(8px);
  background:#26262d;border:1px solid var(--node-border);border-left:3px solid var(--select);
  color:var(--ink);padding:10px 18px;border-radius:6px;font-size:13.5px;font-weight:600;
  z-index:80;opacity:0;pointer-events:none;transition:opacity .15s ease,transform .15s ease;
  box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.err{border-left-color:var(--danger)}

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.001s !important;transition-duration:.001s !important}
}
</style>
</head>
<body>

<header class="toolbar">
  <div class="wordmark">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <rect x="1.5" y="4" width="21" height="16" rx="3" fill="#232329" stroke="#ff9a1f" stroke-width="1.6"/>
      <rect x="1.5" y="4" width="21" height="5.5" rx="3" fill="#ff9a1f"/>
      <path d="M6 14h5M6 17h8" stroke="#8b8b96" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
    <span class="wm-text">Blueprint <em>Vault</em></span>
  </div>
  <nav class="tabs" role="tablist">
    <a class="tab" href="index.php" title="Zurück zum Dashboard">← Admin</a>
    <button class="tab active" data-tab="lib">Bibliothek</button>
    <button class="tab" data-tab="gen">Generator</button>
    <button class="tab" data-tab="settings">Einstellungen</button>
  </nav>
</header>

<main class="graph">

  <!-- ============ BIBLIOTHEK ============ -->
  <section class="view" id="view-lib">
    <div class="lib-controls">
      <input class="search" id="search" type="text" placeholder="Suchen… (Name, Beschreibung, Variablen)">
      <div class="chips" id="chips"></div>
      <button class="btn primary" id="btn-new">+ Neuer Eintrag</button>
    </div>
    <div class="grid" id="grid"></div>
    <div class="empty" id="empty" style="display:none">
      <h3>Noch keine Blueprints hier</h3>
      <p>Leg einen Eintrag manuell an oder lass dir einen im Generator bauen.</p>
      <button class="btn primary" id="btn-empty-new">+ Neuer Eintrag</button>
    </div>
  </section>

  <!-- ============ GENERATOR ============ -->
  <section class="view hidden" id="view-gen">
    <div class="gen-layout">
      <div class="notice warn" id="gen-no-key" style="display:none">
        Auf dem Server ist noch kein Anthropic API-Key hinterlegt. Kopiere
        <strong>admin/vault_config.example.php</strong> nach <strong>data/vault_config.php</strong>
        und trag dort den Key ein. Die Bibliothek funktioniert auch ohne.
      </div>
      <div class="panel">
        <h2>Blueprint generieren</h2>
        <div class="field">
          <label for="gen-prompt">Was soll der Blueprint machen?</label>
          <textarea id="gen-prompt" placeholder="z.B.: Eine Druckplatte, die eine Tür öffnet solange ein Spieler draufsteht. Multiplayer-tauglich, Tür fährt per Interp nach oben."></textarea>
          <div class="hint">Je konkreter, desto besser. Eigene Blueprint-Klassen nur mit exaktem Asset-Pfad erwähnen (z.B. /Game/Custom/Wizard_character/BP_Wizard), sonst bleibt der Code auf Engine-Klassen.</div>
        </div>
        <div class="gen-bar">
          <select id="gen-model" aria-label="Modell">
            <option value="claude-sonnet-4-6">Sonnet 4.6 (schnell)</option>
            <option value="claude-opus-4-8">Opus 4.8 (gründlich)</option>
          </select>
          <button class="btn primary" id="btn-generate">Generieren</button>
          <button class="btn ghost" id="btn-gen-cancel" style="display:none">Abbrechen</button>
        </div>
        <div class="status-line" id="gen-status"></div>
        <div class="stream-out idle" id="gen-out"><?php echo $vaultStream ? 'Der generierte Code erscheint hier live.' : 'Der generierte Code erscheint hier, sobald er fertig ist.'; ?></div>
      </div>
      <div class="panel" id="gen-result" style="display:none">
        <h2>Ergebnis speichern</h2>
        <div class="form-row">
          <div class="field">
            <label for="gen-name">Name</label>
            <input type="text" id="gen-name">
          </div>
          <div class="field">
            <label for="gen-cat">Kategorie</label>
            <select id="gen-cat"></select>
          </div>
        </div>
        <div id="gen-preview"></div>
        <button class="btn primary wide" id="btn-gen-save">In Bibliothek speichern</button>
      </div>
    </div>
  </section>

  <!-- ============ EINSTELLUNGEN ============ -->
  <section class="view hidden" id="view-settings">
    <div class="settings-layout">
      <div class="panel">
        <h2>Anthropic API</h2>
        <div class="notice">
          Der API-Key liegt sicher auf dem Server (<strong>data/vault_config.php</strong>) und
          taucht nie im Browser auf. Status:
          <?php echo $vaultHasKey ? '<strong style="color:#9ee06a">Key hinterlegt ✓</strong>' : '<strong style="color:#ff8a86">kein Key hinterlegt</strong>'; ?> ·
          Modus: <strong><?php echo $vaultStream ? 'Streaming (live)' : 'Komplett-Antwort (Spinner)'; ?></strong>
        </div>
        <div class="field">
          <label for="set-model">Standard-Modell</label>
          <select id="set-model">
            <option value="claude-sonnet-4-6">Sonnet 4.6</option>
            <option value="claude-opus-4-8">Opus 4.8</option>
          </select>
        </div>
        <button class="btn primary" id="btn-save-settings">Einstellungen speichern</button>
        <div class="notice">
          Key erstellen: <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a> ·
          API-Doku: <a href="https://docs.claude.com" target="_blank" rel="noopener">docs.claude.com</a>.
          Die API wird nach Nutzung abgerechnet, unabhängig vom Claude.ai-Abo.
        </div>
      </div>
      <div class="panel">
        <h2>Daten</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn" id="btn-export">Bibliothek exportieren (JSON)</button>
          <button class="btn" id="btn-import">Importieren</button>
          <input type="file" id="import-file" accept=".json,application/json" style="display:none">
        </div>
        <div class="hint">Die Bibliothek liegt zentral auf dem Server – alle Admins sehen denselben Stand, von jedem Gerät. Export-Dateien aus der alten Standalone-Version lassen sich hier importieren.</div>
        <button class="btn danger" id="btn-wipe">Alle Einträge löschen</button>
      </div>
    </div>
  </section>

</main>

<!-- ============ DETAIL MODAL ============ -->
<div class="modal-wrap hidden" id="detail-wrap">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-head" id="detail-head">
      <svg viewBox="0 0 12 12" fill="#fff" aria-hidden="true"><path d="M1 1h6l4 5-4 5H1z"/></svg>
      <span id="detail-title"></span>
      <button class="x" id="detail-close" aria-label="Schließen">✕</button>
    </div>
    <div class="modal-body">
      <div id="detail-desc" class="setup-text"></div>
      <div>
        <div class="section-label">Variablen vorher anlegen</div>
        <div class="var-list" id="detail-vars"></div>
      </div>
      <div id="detail-setup-block">
        <div class="section-label">Setup</div>
        <div class="setup-text" id="detail-setup"></div>
      </div>
      <div>
        <div class="section-label">Paste-Code</div>
        <div class="codebox">
          <pre id="detail-code"></pre>
          <button class="btn small primary copy-float" id="detail-copy-float">Kopieren</button>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn primary" id="detail-copy">Code kopieren</button>
      <span class="spacer"></span>
      <button class="btn" id="detail-edit">Bearbeiten</button>
      <button class="btn danger" id="detail-delete">Löschen</button>
    </div>
  </div>
</div>

<!-- ============ FORM MODAL ============ -->
<div class="modal-wrap hidden" id="form-wrap">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-head" id="form-head">
      <svg viewBox="0 0 12 12" fill="#fff" aria-hidden="true"><path d="M1 1h6l4 5-4 5H1z"/></svg>
      <span id="form-title">Neuer Eintrag</span>
      <button class="x" id="form-close" aria-label="Schließen">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="field">
          <label for="f-name">Name</label>
          <input type="text" id="f-name" placeholder="z.B. Aufzug (Multiplayer)">
        </div>
        <div class="field">
          <label for="f-cat">Kategorie</label>
          <select id="f-cat"></select>
        </div>
      </div>
      <div class="field">
        <label for="f-desc">Beschreibung</label>
        <textarea id="f-desc" placeholder="Was macht der Blueprint?"></textarea>
      </div>
      <div class="field">
        <label for="f-vars">Variablen (eine pro Zeile: Name | Typ | Default | Notiz)</label>
        <textarea id="f-vars" placeholder="StartLoc | Vector | – | wird in BeginPlay gesetzt&#10;Hoehe | Float | 400 | Fahrhöhe in Units"></textarea>
      </div>
      <div class="field">
        <label for="f-setup">Setup-Notizen</label>
        <textarea id="f-setup" placeholder="Komponenten, Class Defaults, Collision…"></textarea>
      </div>
      <div class="field">
        <label for="f-code">Paste-Code</label>
        <textarea id="f-code" class="code" placeholder="Begin Object Class=/Script/BlueprintGraph…" spellcheck="false"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn primary" id="form-save">Speichern</button>
      <span class="spacer"></span>
      <button class="btn ghost" id="form-cancel">Abbrechen</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
"use strict";

/* ================= Server-Kontext ================= */
const CSRF = <?php echo json_encode($vaultCsrf); ?>;
const VAULT_STREAM = <?php echo $vaultStream ? 'true' : 'false'; ?>;
const VAULT_HAS_KEY = <?php echo $vaultHasKey ? 'true' : 'false'; ?>;

/* ================= Konstanten ================= */
const CATS = {
  "Bewegung":    "#7a3a3a",
  "Multiplayer": "#2f5c8f",
  "KI":          "#3e7a4a",
  "UI":          "#6b4a8f",
  "Gameplay":    "#8f7a2f",
  "Sonstiges":   "#4a4a52"
};
const PIN_COLORS = [
  [/vector/i,        "#f7c948"],
  [/rotator/i,       "#9a8fef"],
  [/transform/i,     "#ef8532"],
  [/float|real|double/i, "#9ee06a"],
  [/bool/i,          "#b3302e"],
  [/int|byte/i,      "#35c7b8"],
  [/string/i,        "#e05fd0"],
  [/name|text/i,     "#e0a0d0"],
  [/object|actor|class|component/i, "#3f8fd0"]
];
const LS_SET = "bpvault_settings";

/* ================= State ================= */
let library = [];
let settings = { model:"claude-sonnet-4-6" };
let activeCat = "Alle";
let searchTerm = "";
let editId = null;
let genParsed = null;
let genAbort = null;

/* ================= Utils ================= */
const $ = id => document.getElementById(id);
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const catColor = c => CATS[c] || CATS["Sonstiges"];
const pinColor = t => { for (const [re,col] of PIN_COLORS) if (re.test(t||"")) return col; return "#c9c9d1"; };

let toastTimer = null;
function toast(msg, isErr){
  const t = $("toast");
  t.textContent = msg;
  t.classList.toggle("err", !!isErr);
  t.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>t.classList.remove("show"), 2600);
}

function copyText(txt){
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(txt).then(()=>true).catch(()=>fallbackCopy(txt));
  }
  return Promise.resolve(fallbackCopy(txt));
}
function fallbackCopy(txt){
  const ta = document.createElement("textarea");
  ta.value = txt; ta.style.position="fixed"; ta.style.opacity="0";
  document.body.appendChild(ta); ta.select();
  let ok = false;
  try { ok = document.execCommand("copy"); } catch(e){}
  document.body.removeChild(ta);
  return ok;
}

/* ================= Server-API =================
   Die Bibliothek liegt auf dem Server (data/) – nicht mehr im Browser.
   Jede Schreib-Aktion liefert die komplette aktuelle Liste zurück. */
async function api(action, body){
  const opts = { headers: { "X-Vault-Csrf": CSRF } };
  if (body !== undefined){
    opts.method = "POST";
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const r = await fetch("vault.php?action=" + action, opts);
  let j = null;
  try { j = await r.json(); } catch(e){}
  if (!r.ok) throw new Error((j && j.error) || ("HTTP " + r.status));
  return j || {};
}

async function loadLibrary(){
  const j = await api("list");
  library = j.items || [];
}

/* ================= Einstellungen (nur UI-Präferenz, lokal) ================= */
function loadSettings(){
  try { const raw = localStorage.getItem(LS_SET); if (raw) Object.assign(settings, JSON.parse(raw)); } catch(e){}
}
function saveSettings(){ localStorage.setItem(LS_SET, JSON.stringify(settings)); }

/* ================= Tabs ================= */
document.querySelectorAll("button.tab").forEach(btn=>{
  btn.addEventListener("click", ()=>{
    document.querySelectorAll("button.tab").forEach(b=>b.classList.toggle("active", b===btn));
    ["lib","gen","settings"].forEach(v=>$("view-"+v).classList.toggle("hidden", btn.dataset.tab!==v));
    if (btn.dataset.tab==="gen") $("gen-no-key").style.display = VAULT_HAS_KEY ? "none" : "";
  });
});

/* ================= Bibliothek ================= */
function buildChips(){
  const wrap = $("chips");
  wrap.innerHTML = "";
  ["Alle", ...Object.keys(CATS)].forEach(cat=>{
    const b = document.createElement("button");
    b.className = "chip" + (cat===activeCat ? " active":"");
    if (cat!=="Alle") b.style.setProperty("--dot", CATS[cat]);
    b.innerHTML = (cat!=="Alle" ? '<span class="dot"></span>' : "") + esc(cat);
    b.addEventListener("click", ()=>{ activeCat = cat; buildChips(); renderGrid(); });
    wrap.appendChild(b);
  });
}

function matches(bp){
  if (activeCat!=="Alle" && bp.category!==activeCat) return false;
  if (!searchTerm) return true;
  const hay = (bp.name+" "+bp.description+" "+(bp.variables||[]).map(v=>v.name+" "+v.type).join(" ")).toLowerCase();
  return hay.includes(searchTerm);
}

function renderGrid(){
  const grid = $("grid");
  grid.innerHTML = "";
  const items = library.filter(matches).sort((a,b)=>(b.updatedAt||0)-(a.updatedAt||0));
  $("empty").style.display = library.length===0 ? "" : "none";
  if (library.length>0 && items.length===0){
    grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><h3>Nichts gefunden</h3><p>Andere Suche oder Kategorie probieren.</p></div>';
    return;
  }
  items.forEach(bp=>{
    const card = document.createElement("article");
    card.className = "node-card";
    card.innerHTML = `
      <div class="node-head" style="--cat:${catColor(bp.category)}">
        <svg viewBox="0 0 12 12" fill="#fff" aria-hidden="true"><path d="M1 1h6l4 5-4 5H1z"/></svg>
        <span>${esc(bp.name)}</span>
        <span class="cat-label">${esc(bp.category)}</span>
      </div>
      <div class="node-body">
        <div class="node-desc">${esc(bp.description||"")}</div>
        <div class="node-meta">
          <span class="pin"><i></i>${(bp.variables||[]).length} Variablen</span>
          <span>${new Date(bp.updatedAt||bp.createdAt||Date.now()).toLocaleDateString("de-DE")}</span>
        </div>
        <div class="node-actions">
          <button class="btn primary" data-act="copy">Kopieren</button>
          <button class="btn" data-act="open">Öffnen</button>
        </div>
      </div>`;
    card.querySelector('[data-act=copy]').addEventListener("click", async ()=>{
      const ok = await copyText(bp.code||"");
      toast(ok ? "Kopiert – ab in den Event Graph" : "Kopieren fehlgeschlagen", !ok);
    });
    card.querySelector('[data-act=open]').addEventListener("click", ()=>openDetail(bp.id));
    grid.appendChild(card);
  });
}

$("search").addEventListener("input", e=>{ searchTerm = e.target.value.trim().toLowerCase(); renderGrid(); });
$("btn-new").addEventListener("click", ()=>openForm(null));
$("btn-empty-new").addEventListener("click", ()=>openForm(null));

/* ================= Detail ================= */
let detailId = null;
function openDetail(id){
  const bp = library.find(x=>x.id===id);
  if (!bp) return;
  detailId = id;
  $("detail-head").style.setProperty("--cat", catColor(bp.category));
  $("detail-title").textContent = bp.name;
  $("detail-desc").textContent = bp.description || "";
  const vl = $("detail-vars");
  vl.innerHTML = "";
  const vars = bp.variables||[];
  if (!vars.length) vl.innerHTML = '<div class="var-row"><span class="vname" style="color:var(--ink-faint)">keine nötig</span></div>';
  vars.forEach(v=>{
    const row = document.createElement("div");
    row.className = "var-row";
    row.innerHTML = `<span class="vdot" style="background:${pinColor(v.type)}"></span>
      <span class="vname">${esc(v.name)}</span>
      <span class="vtype">${esc(v.type)}</span>
      <span class="vdefault">Default: ${esc(v.def||"–")}</span>
      ${v.note ? `<span class="vnote">${esc(v.note)}</span>` : ""}`;
    vl.appendChild(row);
  });
  $("detail-setup-block").style.display = bp.setup ? "" : "none";
  $("detail-setup").textContent = bp.setup || "";
  $("detail-code").textContent = bp.code || "";
  $("detail-wrap").classList.remove("hidden");
}
function closeDetail(){ $("detail-wrap").classList.add("hidden"); detailId = null; }
$("detail-close").addEventListener("click", closeDetail);
$("detail-wrap").addEventListener("click", e=>{ if (e.target===$("detail-wrap")) closeDetail(); });
async function detailCopy(){
  const bp = library.find(x=>x.id===detailId);
  if (!bp) return;
  const ok = await copyText(bp.code||"");
  toast(ok ? "Kopiert – ab in den Event Graph" : "Kopieren fehlgeschlagen", !ok);
}
$("detail-copy").addEventListener("click", detailCopy);
$("detail-copy-float").addEventListener("click", detailCopy);
$("detail-edit").addEventListener("click", ()=>{ const id = detailId; closeDetail(); openForm(id); });
$("detail-delete").addEventListener("click", async ()=>{
  const bp = library.find(x=>x.id===detailId);
  if (!bp) return;
  if (!confirm(`"${bp.name}" wirklich löschen?`)) return;
  try {
    const j = await api("delete", { id: detailId });
    library = j.items || [];
    closeDetail(); renderGrid();
    toast("Gelöscht");
  } catch(err){
    toast("Löschen fehlgeschlagen: " + err.message, true);
  }
});

/* ================= Formular ================= */
function fillCatSelect(sel, val){
  sel.innerHTML = Object.keys(CATS).map(c=>`<option${c===val?" selected":""}>${esc(c)}</option>`).join("");
}
function openForm(id){
  editId = id;
  const bp = id ? library.find(x=>x.id===id) : null;
  $("form-title").textContent = bp ? "Eintrag bearbeiten" : "Neuer Eintrag";
  $("form-head").style.setProperty("--cat", catColor(bp ? bp.category : "Sonstiges"));
  fillCatSelect($("f-cat"), bp ? bp.category : "Gameplay");
  $("f-name").value = bp ? bp.name : "";
  $("f-desc").value = bp ? (bp.description||"") : "";
  $("f-vars").value = bp ? (bp.variables||[]).map(v=>[v.name,v.type,v.def||"–",v.note||""].join(" | ").replace(/ \| $/,"")).join("\n") : "";
  $("f-setup").value = bp ? (bp.setup||"") : "";
  $("f-code").value = bp ? (bp.code||"") : "";
  $("form-wrap").classList.remove("hidden");
  $("f-name").focus();
}
function closeForm(){ $("form-wrap").classList.add("hidden"); editId = null; }
$("form-close").addEventListener("click", closeForm);
$("form-cancel").addEventListener("click", closeForm);
$("form-wrap").addEventListener("click", e=>{ if (e.target===$("form-wrap")) closeForm(); });

function parseVarLines(txt){
  return txt.split("\n").map(l=>l.trim()).filter(l=>l && l.toLowerCase()!=="keine").map(l=>{
    const p = l.split("|").map(s=>s.trim());
    return { name:p[0]||"?", type:p[1]||"?", def:p[2]||"–", note:p[3]||"" };
  });
}

$("form-save").addEventListener("click", async ()=>{
  const name = $("f-name").value.trim();
  const code = $("f-code").value.trim();
  if (!name){ toast("Name fehlt", true); return; }
  if (!code){ toast("Paste-Code fehlt", true); return; }
  const entry = {
    id: editId || "",
    name,
    category: $("f-cat").value,
    description: $("f-desc").value.trim(),
    variables: parseVarLines($("f-vars").value),
    setup: $("f-setup").value.trim(),
    code
  };
  try {
    const j = await api("save", { entry });
    library = j.items || [];
    closeForm(); renderGrid();
    toast("Gespeichert");
  } catch(err){
    toast("Speichern fehlgeschlagen: " + err.message, true);
  }
});

/* ================= Generator ================= */
function setGenBusy(busy){
  $("btn-generate").disabled = busy;
  $("btn-gen-cancel").style.display = busy ? "" : "none";
  // Beim Beenden nur den Spinner räumen – gesetzte Fehler-/Fertig-Meldungen stehen lassen
  if (busy) $("gen-status").innerHTML = '<span class="spinner"></span><span>Generiere… (kann bei großen Graphen etwas dauern)</span>';
  else if ($("gen-status").querySelector(".spinner")) $("gen-status").textContent = "";
}

$("btn-generate").addEventListener("click", async ()=>{
  const prompt = $("gen-prompt").value.trim();
  if (!VAULT_HAS_KEY){ toast("Kein API-Key auf dem Server – siehe Einstellungen", true); return; }
  if (!prompt){ toast("Beschreib erst, was der Blueprint machen soll", true); return; }

  const out = $("gen-out");
  out.classList.remove("idle");
  out.textContent = "";
  $("gen-result").style.display = "none";
  genParsed = null;
  setGenBusy(true);
  genAbort = new AbortController();

  let full = "";
  try {
    const resp = await fetch("vault.php?action=generate", {
      method: "POST",
      signal: genAbort.signal,
      headers: {
        "Content-Type": "application/json",
        "X-Vault-Csrf": CSRF
      },
      body: JSON.stringify({ prompt, model: $("gen-model").value })
    });

    if (!resp.ok){
      let msg = "HTTP " + resp.status;
      try { const err = await resp.json(); msg = err.error || msg; } catch(e){}
      throw new Error(msg);
    }

    if (VAULT_STREAM){
      const reader = resp.body.getReader();
      const decoder = new TextDecoder();
      let buf = "";
      while (true){
        const { done, value } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream:true });
        const lines = buf.split("\n");
        buf = lines.pop();
        for (const line of lines){
          if (!line.startsWith("data:")) continue;
          const payload = line.slice(5).trim();
          if (!payload || payload === "[DONE]") continue;
          let ev;
          try { ev = JSON.parse(payload); } catch(e){ continue; }
          if (ev.type === "content_block_delta" && ev.delta && ev.delta.text){
            full += ev.delta.text;
            out.textContent = full;
            out.scrollTop = out.scrollHeight;
          }
          if (ev.type === "error"){
            throw new Error((ev.error && ev.error.message) || "Stream-Fehler");
          }
        }
      }
    } else {
      const j = await resp.json();
      if (j.error) throw new Error(j.error);
      full = j.text || "";
      out.textContent = full;
      out.scrollTop = out.scrollHeight;
    }

    genParsed = parseGenOutput(full, prompt);
    showGenResult(prompt);
    $("gen-status").textContent = "Fertig. Prüfen, benennen, speichern.";
  } catch(err){
    if (err.name === "AbortError"){
      $("gen-status").textContent = "Abgebrochen.";
    } else {
      $("gen-status").textContent = "Fehler: " + err.message;
      toast("Generierung fehlgeschlagen", true);
    }
  } finally {
    setGenBusy(false);
    if ($("gen-status").textContent === "") $("gen-status").textContent = "";
    genAbort = null;
  }
});

$("btn-gen-cancel").addEventListener("click", ()=>{ if (genAbort) genAbort.abort(); });

function parseGenOutput(text, prompt){
  const clean = text.replace(/```[a-z]*\n?/gi, "").replace(/```/g, "");
  const iVars = clean.indexOf("###VARIABLEN###");
  const iSetup = clean.indexOf("###SETUP###");
  const iCode = clean.indexOf("###CODE###");
  let vars = [], setup = "", code = "";
  if (iVars>=0 && iSetup>iVars && iCode>iSetup){
    vars = parseVarLines(clean.slice(iVars+15, iSetup));
    setup = clean.slice(iSetup+11, iCode).trim();
    code = clean.slice(iCode+10).trim();
  } else {
    const iBegin = clean.indexOf("Begin Object");
    code = iBegin>=0 ? clean.slice(iBegin).trim() : clean.trim();
    setup = iBegin>0 ? clean.slice(0, iBegin).trim() : "";
  }
  return { variables: vars, setup, code, description: prompt };
}

function showGenResult(prompt){
  if (!genParsed || !genParsed.code){ toast("Kein Code im Ergebnis gefunden", true); return; }
  fillCatSelect($("gen-cat"), "Gameplay");
  $("gen-name").value = prompt.split(/[.,\n]/)[0].slice(0, 60);
  const pv = $("gen-preview");
  const varRows = (genParsed.variables||[]).map(v=>`
    <div class="var-row">
      <span class="vdot" style="background:${pinColor(v.type)}"></span>
      <span class="vname">${esc(v.name)}</span>
      <span class="vtype">${esc(v.type)}</span>
      <span class="vdefault">Default: ${esc(v.def||"–")}</span>
      ${v.note ? `<span class="vnote">${esc(v.note)}</span>` : ""}
    </div>`).join("") || '<div class="var-row"><span class="vname" style="color:var(--ink-faint)">keine nötig</span></div>';
  pv.innerHTML = `
    <div>
      <div class="section-label">Variablen vorher anlegen</div>
      <div class="var-list">${varRows}</div>
    </div>
    ${genParsed.setup ? `<div style="margin-top:14px"><div class="section-label">Setup</div><div class="setup-text">${esc(genParsed.setup)}</div></div>` : ""}
    <div style="margin-top:14px">
      <div class="section-label">Paste-Code (${genParsed.code.length.toLocaleString("de-DE")} Zeichen)</div>
      <div class="codebox">
        <pre>${esc(genParsed.code)}</pre>
        <button class="btn small primary copy-float" id="gen-copy">Kopieren</button>
      </div>
    </div>`;
  $("gen-result").style.display = "";
  $("gen-copy").addEventListener("click", async ()=>{
    const ok = await copyText(genParsed.code);
    toast(ok ? "Kopiert" : "Kopieren fehlgeschlagen", !ok);
  });
  $("gen-result").scrollIntoView({ behavior:"smooth", block:"start" });
}

$("btn-gen-save").addEventListener("click", async ()=>{
  if (!genParsed || !genParsed.code) return;
  const name = $("gen-name").value.trim() || "Unbenannter Blueprint";
  const entry = {
    id: "",
    name,
    category: $("gen-cat").value,
    description: genParsed.description || "",
    variables: genParsed.variables || [],
    setup: genParsed.setup || "",
    code: genParsed.code
  };
  try {
    const j = await api("save", { entry });
    library = j.items || [];
    renderGrid();
    toast("Gespeichert: " + name);
  } catch(err){
    toast("Speichern fehlgeschlagen: " + err.message, true);
  }
});

/* ================= Einstellungen ================= */
function renderSettings(){
  $("set-model").value = settings.model || "claude-sonnet-4-6";
  $("gen-model").value = settings.model || "claude-sonnet-4-6";
}
$("btn-save-settings").addEventListener("click", ()=>{
  settings.model = $("set-model").value;
  saveSettings();
  $("gen-model").value = settings.model;
  toast("Einstellungen gespeichert");
});

$("btn-export").addEventListener("click", ()=>{
  window.location.href = "vault.php?action=export";
  toast("Export gestartet");
});
$("btn-import").addEventListener("click", ()=>$("import-file").click());
$("import-file").addEventListener("change", e=>{
  const file = e.target.files[0];
  if (!file) return;
  const fr = new FileReader();
  fr.onload = async ()=>{
    try {
      const data = JSON.parse(fr.result);
      if (!Array.isArray(data)) throw new Error("kein Array");
      const j = await api("import", { data });
      library = j.items || [];
      renderGrid();
      toast(`Importiert: ${j.added} Einträge`);
    } catch(err){
      toast("Import fehlgeschlagen: " + (err.message==="kein Array" ? "keine gültige Export-Datei" : err.message), true);
    }
    e.target.value = "";
  };
  fr.readAsText(file);
});
$("btn-wipe").addEventListener("click", async ()=>{
  if (!confirm("Wirklich ALLE Einträge löschen? Das betrifft alle Admins und kann nicht rückgängig gemacht werden.")) return;
  try {
    const j = await api("wipe", {});
    library = j.items || [];
    renderGrid();
    toast("Bibliothek geleert");
  } catch(err){
    toast("Fehlgeschlagen: " + err.message, true);
  }
});

/* ================= Global ================= */
document.addEventListener("keydown", e=>{
  if (e.key === "Escape"){
    if (!$("form-wrap").classList.contains("hidden")) closeForm();
    else if (!$("detail-wrap").classList.contains("hidden")) closeDetail();
  }
});

/* ================= Init ================= */
(async ()=>{
  loadSettings();
  buildChips();
  renderSettings();
  $("gen-no-key").style.display = VAULT_HAS_KEY ? "none" : "";
  try {
    await loadLibrary();
  } catch(err){
    toast("Bibliothek konnte nicht geladen werden: " + err.message, true);
  }
  renderGrid();
})();
</script>
</body>
</html>
