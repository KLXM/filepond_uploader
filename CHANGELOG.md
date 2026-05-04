# Changelog

## Unreleased

### 🐛 Bugfixes
- **MetaInfo Lang Fields Speicherung korrigiert**: `processAdditionalMetaInfoFields()` verarbeitet jetzt zusätzlich `med_description_lang` und `med_keywords_lang`. Dadurch werden mehrsprachige Beschreibungen und Keywords beim Upload korrekt in den `*_lang` Feldern persistiert.


## 2.4.1 (2026.05.04)

fixed https://github.com/KLXM/filepond_uploader/issues/102, danke @milorad-micko

## Version 2.4.0 (2026-04-27)

### 🎉 Neue Features
- **YCom-Medienberechtigungen beim Upload setzen** (optional): Auf der Upload-Seite erscheint für berechtigte Backend-User ein ausklappbares Panel, in dem `ycom_auth_type`, `ycom_group_type` und `ycom_groups` als Defaults für die laufende Backend-Sitzung gesetzt werden können. Jede neu hochgeladene Datei (inkl. Chunk-Upload) erhält die gewählten Werte automatisch in `rex_media`.
  - Voraussetzung: Plugin `ycom/media_auth` aktiv.
  - Per Schalter **„YCom-Medienberechtigungen beim Upload setzen“** in den Einstellungen aktivierbar (Standard: aus).
  - Eigene Backend-Permission `filepond_uploader[ycom_media_auth]` (Admins haben automatisch Zugriff).
  - Status-Badge im Panel-Header zeigt, ob aktuell „öffentlich“ oder „nur eingeloggte“ als Default greift; Reset-Button setzt die Sitzungs-Defaults zurück.
  - Gruppen-Felder werden nur eingeblendet, wenn `ycom/group` verfügbar ist und ein konkreter Gruppentyp gewählt wurde.

### 🐛 Bugfixes
- **Defaults landeten nicht in `rex_media`**: Beim Upload über den Medienpool oder die Upload-Seite werden die YCom-Auth-Defaults jetzt zusätzlich direkt in der Upload-FormData mitgeschickt (POST-Werte `ycom_auth_type`, `ycom_group_type`, `ycom_groups[]`). Der Server bevorzugt diese POST-Werte und greift nur als Fallback auf die Session zurück – damit greift das Feature auch dann zuverlässig, wenn die asynchrone Session-Synchronisation noch nicht durchgelaufen ist oder der Autoload-Cache nach Erstinstallation veraltet ist.
- **API-Endpoint defensiv registriert**: `?rex-api-call=filepond_ycom_auth` wird in `boot.php` jetzt explizit via `rex_api_function::register()` registriert, damit er auch ohne vorherigen Cache-Refresh sofort auflöst.
- **Diagnose-Logging**: `applyYcomMediaAuthDefaults()` schreibt informative Log-Einträge ins FilePond-Logfile, wenn Defaults bewusst übersprungen werden (Feature aus, kein Backend-Login, fehlende Permission) bzw. erfolgreich angewendet werden – inkl. Quelle (`POST` oder `SESSION`).

## Version 2.3.5 (2026-04-21)

### 🐛 Bugfixes (PHP 8.5 Kompatibilität)
- **`imagedestroy()` entfernt**: In `filepond_ai_alt_generator.php` wurden veraltete `imagedestroy()`-Aufrufe entfernt, die in PHP 8.5 Deprecated-Notices erzeugen und die JSON-API-Antwort zerstören.
- **`curl_close()` entfernt**: In `filepond_ai_provider_openai_compatible.php` wurden beide `curl_close()`-Aufrufe entfernt (ebenfalls PHP 8.5 deprecated).
- **`max_completion_tokens` statt `max_tokens`**: Der OpenAI-kompatible Provider sendet jetzt `max_completion_tokens` statt dem veralteten `max_tokens`, das neuere Modelle (z.B. gpt-5-mini) ablehnen.
- **`temperature` entfernt**: Der OpenAI-kompatible Provider sendet keinen expliziten `temperature`-Wert mehr – neuere Modelle akzeptieren nur den API-Default (1.0).

## Version 2.3.4 (2026-04-21)

### 🐛 Bugfixes
- **Metainfo-Feldbezeichnungen**: Im Metadaten-Modal werden jetzt die in MetaInfo konfigurierten Feldtitel korrekt angezeigt statt der rohen Spaltennamen (z.B. `med_copyright_link`). Ursache war ein Fallback in `getFieldTranslation()`, der bei unbekannten Feldern den Spaltennamen statt `null` zurückgab, wodurch `field.label` nie zum Einsatz kam.
- **MetaInfo-Labels aus DB**: Die API-Methode `getMetaInfoFields()` liest `name`, `title` und `type_label` jetzt in einer einzigen JOIN-Query statt in N+1 Einzelabfragen. Labels werden korrekt per `rex_i18n::msg()` übersetzt wenn sie mit `translate:` beginnen, andernfalls direkt aus der DB verwendet.
- **Humanisierter Fallback**: Felder ohne konfigurierten MetaInfo-Titel zeigen jetzt einen lesbaren Namen (z.B. `Copyright Link`) statt dem rohen Spaltennamen.

## Version 2.3.3 (2026-03-05)

### ✨ Verbesserungen
- **Multiupload als Medienpool-Unterseite**: Der FilePond-Upload kann optional als zusätzliche Unterseite "Multiupload" im Medienpool eingebunden werden (Einstellungen → Medienpool-Integration). Die Seite erscheint direkt nach der normalen Upload-Seite und erfordert die Berechtigung `filepond_uploader[upload]`.

## Version 2.3.1 - 2.3.2 (2026-02-27)

### 🐛 Bugfixes
- **OpenAI UX vereinfacht**: Bei OpenAI-kompatiblen Einstellungen ist die Base URL jetzt optional. Wenn das Feld leer bleibt, wird automatisch die offizielle OpenAI API verwendet.
- **Verbindungstest abgesichert**: Der Test-Button ist nur aktiv, wenn für den gewählten Provider bereits eine gespeicherte Konfiguration vorliegt.
- **Testergebnis sichtbar**: Erfolg-/Fehlermeldungen des Verbindungstests bleiben nun korrekt sichtbar und werden nicht mehr direkt überschrieben.
- **Vision-Modelle gefiltert**: Beim OpenAI-kompatiblen Verbindungstest werden nur noch visuell/vision-fähige Modelle in der Modellliste ausgegeben.
- **Dependencies aktualisiert**: NPM-Abhängigkeiten wurden aktualisiert und die FilePond-Assets neu gebaut (inkl. aktualisiertem `package-lock.json`).
- **Automatische Mediapool-Erweiterung**: Konfigurierte MIME-Types werden zur Laufzeit automatisch im Mediapool freigeschaltet – keine manuelle Pflege der `allowed_mime_types` mehr nötig.

## Version 2.3.0 (2026-02-25)

### 🎉 Neue Features
- **Automatische Mediapool-Erweiterung**: Konfigurierte MIME-Types werden zur Laufzeit automatisch im Mediapool freigeschaltet – keine manuelle Pflege der `allowed_mime_types` mehr nötig.
- **Dateitypen-Auswahl als Accordion**: Übersichtliche Auswahl der erlaubten Dateitypen in 8 Gruppen (Bilder, Dokumente, Archive, Video, Audio, Office, OpenDocument, Fonts) mit Badge-Zähler pro Gruppe.
- **Wildcard-Unterstützung**: `image/*`, `video/*`, `audio/*` deaktivieren automatisch die Einzel-Checkboxen der jeweiligen Gruppe.
- **Eigene MIME-Types**: Freitext-Feld für benutzerdefinierte MIME-Types oder Dateiendungen.
- Unterstützung für Wildcards (`image/*`, `video/*`) und Dateiendungen (`.pdf`, `.docx`) bei der automatischen Mediapool-Freischaltung.

## Version 2.2.4 (2026-02-15)

### 🐛 Bugfixes
- Blob-Bilder korrekt behandelt.

## Version 2.2.3 (2026-02-14)

### 🐛 Bugfixes
- Frontend-Upload Fix.
- Komma-Fehler behoben.

## Version 2.2.2 (2026-02-12)

### ✨ Verbesserungen
- Rexstan Level 8 Kompatibilität.
- PHPStan-Fixes und Code-Qualität verbessert.
- AltTextChecker.php aktualisiert.
- YForm Value-Typ Syntax-Fix.

## Version 2.2.1 (2026-02-10)

### 🐛 Bugfixes
- Syntax-Fehler behoben (doppelte schließende Klammer vor catch-Block).
- Fritz's Fehler korrigiert.

## Version 2.2.0 (2026-02-08)

### 🎉 Neue Features
- **AI-Provider Registry**: Erweiterbare Provider-Architektur für AI Alt-Text-Generierung.
- **OpenWebUI Support**: Neuer AI-Provider für OpenWebUI-Kompatibilität.
- YForm Value-Typ Verbesserungen.

## Version 2.0.6 (2026-01-30)

### 🎉 Neue Features
- **Optionale Accessibility-Statistiken** im Alt-Text-Checker.
- **AI-Generierung in Medienpool-Detailansicht**: AI Alt-Text direkt beim Bearbeiten einer Datei generieren.

### 🐛 Bugfixes
- Doppeltes Modal-Problem behoben.
- Fehlerhafter Fallback entfernt.
- Settings-Handling für Alt-Checker und Stats verbessert.
- Abhängigkeit von `med_description` entfernt.

## Version 2.0.5 (2026-01-28)

### ✨ Verbesserungen
- **Konfigurierbare AI-Token-Limits**.
- Bessere Upload-Performance und Workflow.
- Alt-Checks verbessert.

## Version 2.0.4 (2026-01-25)

### 🐛 Bugfixes
- Kritische Bugfixes für Bildverarbeitung.
- `form-horizontal` Klasse entfernt.
- Alt-Checker: Nur registrieren wenn `med_alt` Feld existiert.

## Version 2.0.3 (2026-01-22)

### 🐛 Bugfixes
- Alt-Text-Checker Kategorie-Filter korrigiert.
- Kritische Bugfixes für Bildverarbeitung.
- Clientseitige Bildverkleinerung standardmäßig deaktiviert.

## Version 2.0.2 (2026-01-20)

### 🎉 Neue Features
- **Bulk Resize Feature**: Nachträgliche Bildoptimierung für bestehende Medien.
- Performance-Optimierung für Shared Hosting.
- Verbesserte Fehlerbehandlung und Debugging.

## Version 2.0.1 (2026-01-15)

### 🎉 Neue Features
- **AI Alt-Text Generierung mit Google Gemini**: Automatische Alt-Text-Erzeugung für Bilder.
- **Gemini Modell-Auswahl**: 2.5 Flash als Standard.
- **Alt-Text-Checker für Barrierefreiheit**: Prüfung fehlender Alt-Texte.
- **Multilang-Unterstützung**: AI generiert Alt-Texte für alle Sprachen.
- SVG von AI-Generierung ausgeschlossen.
- Konfigurierbarer Sort-Order für Bulk Resize und Alt-Text-Checker.

### 🐛 Bugfixes
- `maxOutputTokens` erhöht, keine abgeschnittenen Sätze mehr.
- Bessere Fehlerdiagnose beim API-Test.
- `updatedate` und `updateuser` bleiben beim Resize/Alt-Update erhalten.

## Version 1.15.0-beta (2025-12-xx)

### 🎉 Neue Features
- Clientseitiges Resize.
- Bulk Resize Feature für nachträgliche Bildoptimierung.

## Version 1.13.3 (2025-12-xx)

### 🎉 Neue Features
- **Info Center Widget**: FilePond Upload-Widget für Info Center AddOn.

## Version 1.13.2 (2025-12-xx)

### 🐛 Bugfixes
- OutputFilter-Fix.

## Version 1.13.1 (2025-12-xx)

### ✨ Verbesserungen
- CSS-Refactoring: Inline-Styles durch CSS-Klassen mit Dark-Theme-Support ersetzt.
- CSS-Variablen für bessere Wartbarkeit.

## Version 1.13.0 (2025-11-xx)

### 🎉 Neue Features
- **Umfassende Media Widget Integration** für Medienpool.
- **Mehrsprachige MetaInfo-Integration** mit verbesserter UI.
- Video-Vorschau in MetaInfo-Dialogen.

### 🔒 Security
- Kritische Sicherheitslücken behoben (XSS und Injection).
- Robuste Fehlerbehandlung für EXIF-Orientierungskorrektur.

## Version 1.12.1 (2025-11-xx)

### 🐛 Bugfixes
- Memory-Leaks bei Bild-Rotation behoben.
- Fehler-Logging für `imagerotate()` Fehler.
- EXIF-Orientierungskorrektur standardmäßig aktiviert.

## Version 1.12.0 (2025-10-xx)

### 🎉 Neue Features
- **EXIF-Orientierungskorrektur**: Automatische Korrektur der Bildausrichtung.

## Version 1.11.3 (2025-10-xx)

### 🐛 Bugfixes
- Unterstützung für Dateiendungen im `allowed_types` Parameter.

## Version 1.11.2 (2025-10-xx)

### 🐛 Bugfixes
- `delayed-type=1` als Standard wenn Delayed Upload aktiviert.
- Fehlende Übersetzung für Upload-Button.
- Fehlender Upload-Button im Delayed-Upload-Modus für Medienpool.

## Version 1.11.1 (2025-09-xx)

### 🐛 Bugfixes
- Error-Response Status auf HTTP_FORBIDDEN geändert.

## Version 1.11.0 (2025-09-xx)

### 🎉 Neue Features
- **Delayed Upload**: Upload erst beim Formular-Submit (Beitrag von @godsdog).

## Version 1.10.0 (2025-08-xx)

### ✨ Verbesserungen
- FilePond-Thumbnail-Gradients durch einfache Borders ersetzt.
- Glow-Animation für FilePond-Borders.
- Retry-Button-Sichtbarkeit verbessert.

## Version 1.9.0 (2025-07-xx)

### ✨ Verbesserungen
- UI- und Optik-Verbesserungen.
- Cancel-Fix.

## Version 1.8.0 (2025-06-xx)

### 🎉 Neue Features
- **Dekorative Bilder**: Möglichkeit, Bilder als dekorativ zu markieren (kein Alt-Text nötig).
- **Neuer Upload-Modus**.
- Direkte `skipMeta`-Parameter-Übergabe als Alternative zur Session-Variable.

## Version 1.7.4 (2025-05-xx)

### 🐛 Bugfixes
- API-Fixes.

## Version 1.7.3 (2025-05-xx)

### 🎉 Neue Features
- **Chunk-Uploads**: Große Dateien werden in Teilen hochgeladen.

## Version 1.7.1 (2025-04-xx)

### 🐛 Bugfixes
- Dateinamen-Fix.
- FilePond Value wird aus dem E-Mail-Valuepool gezogen (Beitrag von @dtpop).

## Version 1.7.0 (2025-04-xx)

### 🎉 Neue Features
- **YForm Action für E-Mail-Versand**: Hochgeladene Dateien als E-Mail-Anhang.

## Version 1.5.0 (2025-03-xx)

### 🎉 Neue Features
- Chunk-Upload-Support.
- Skip-Meta-Modus.
- Diverses UI-Refactoring.

## Version 1.4.1 (2025-02-xx)

### 🐛 Bugfixes
- Widget-Verbesserungen.
- YForm-Template-Fixes.

## Version 1.2.1 (2025-01-xx)

### ✨ Verbesserungen
- API und Meta per Session aktivieren/deaktivieren.

## Version 1.0.0 (2024-xx-xx)

### 🎉 Initiales Release
- FilePond Upload-Widget für REDAXO.
- YForm Value-Typ `filepond`.
- Medienpool-Integration mit MetaInfo-Modal.
- Dark-Mode-Unterstützung.
- Lokale Assets (DSGVO-konform, kein CDN).
- Konfigurierbare Dateitypen, Größenlimits und Kategorien.
