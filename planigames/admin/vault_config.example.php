<?php
/**
 * Blueprint Vault — Server-Konfiguration (VORLAGE).
 *
 * Einrichtung:
 *   1. Diese Datei nach  data/vault_config.php  kopieren (per FTP).
 *   2. Dort den echten Anthropic API-Key eintragen (sk-ant-…).
 *
 * Die echte Datei liegt in data/ und ist dort per .htaccess gegen
 * HTTP-Zugriff gesperrt. Sie kommt NIE ins Git/Repo und der Key
 * taucht nirgendwo im Browser auf.
 */
return [
  // Anthropic API-Key (console.anthropic.com) – NUR in data/vault_config.php eintragen!
  'api_key'        => '',

  // Google Gemini API-Key (aistudio.google.com/apikey) – optional.
  // Mindestens EINER der beiden Keys muss gesetzt sein, damit der Generator läuft.
  'gemini_api_key' => '',

  // Optional: eigene Gemini-Modellliste, falls Google die IDs ändert.
  // Leer lassen = eingebaute Standard-Modelle (Gemini 3 Pro, Gemini 2.5 Flash).
  // Beispiel: 'gemini_models' => ['gemini-3-pro-preview' => 'Gemini 3 Pro'],
  'gemini_models'  => [],

  // true  = Streaming: der Code läuft live in die Ausgabe (SSE-Passthrough).
  // false = Komplett-Antwort mit Spinner – nutzen, falls der Hoster Streaming
  //         puffert oder mit Timeouts abbricht.
  'stream'         => true,

  // Maximale Antwortlänge (Tokens) für die Generierung.
  'max_tokens'     => 32000,

  // Timeout für den KI-Request in Sekunden.
  'timeout'        => 300,
];
