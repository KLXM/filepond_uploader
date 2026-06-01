# Frontend Demo

Datei: `frontend_demo.php`

Ziel:
- Voll funktionsfähige Referenz für den Frontend-Einsatz von `filepond_uploader`
- Korrekte Asset-Einbindung über `filepond_helper::getStyles()` und `filepond_helper::getScripts()`
- Kompatible `data-filepond-*` Attribute wie im YForm-Template

Verwendung:
1. Inhalt von `frontend_demo.php` in dein Frontend-Template/Modul übernehmen.
2. Prüfen, dass REDAXO geladen ist (kein statisches HTML außerhalb von REDAXO).
3. Optional Werte für `data-filepond-*` projektspezifisch anpassen.

Hinweis zu lokalen Test-URLs:
- Eine URL wie `https://localhost:8443/fond-ai/` funktioniert nur, wenn die Seite durch REDAXO/PHP gerendert wird.
- Bei reinem HTML ohne PHP fehlen i. d. R. die Addon-Assets und API-Calls.