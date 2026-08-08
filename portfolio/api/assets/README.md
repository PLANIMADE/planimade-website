# Schriften für serverseitig erzeugte Bilder

Diese TrueType-Dateien braucht PHP (GD), um Text in die Social-Vorschaubilder
zu rendern – `woff2` aus dem Frontend kann GD nicht lesen.

- `Archivo-Bold.ttf` und `Archivo-Medium.ttf`
- Familie: **Archivo** von Omnibus-Type
- Lizenz: **SIL Open Font License 1.1** – Weitergabe zusammen mit dieser
  Anwendung ist ausdrücklich erlaubt.
- Quelle: <https://fonts.google.com/specimen/Archivo>

Fehlen die Dateien, funktioniert alles Übrige weiter; es entstehen dann nur
keine eigenen Vorschaubilder pro Projekt (der Systemcheck weist darauf hin).
