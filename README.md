# KLXM FilePond Uploader für REDAXO

**Ein moderner Datei-Uploader für REDAXO mit Chunk-Upload und nahtloser Medienpool-Integration.**

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Alternative: [uppy](https://github.com/FriendsOfREDAXO/uppy)

## Hauptmerkmale

*   **Chunk-Upload als Kernfeature:**
    *   Zuverlässiges Hochladen großer Dateien in kleinen Teilen (Chunks)
    *   Einstellbare Chunk-Größe (Standard: 5MB)
    *   Fortschrittsanzeige für einzelne Chunks und die Gesamtdatei
    *   Automatisches Zusammenführen der Chunks nach dem Upload

*   **Verzögerter Upload-Modus:**
    *   Auswahl und Anordnung von Dateien vor dem Upload
    *   Trennung von Dateiauswahl und Upload-Prozess
    *   Benutzerfreundlicher Upload-Button erscheint automatisch
    *   Löschen unerwünschter Dateien vor dem Upload
    *   Ideal für Redakteure mit vielen Dateien

*   **Moderne Oberfläche:**
    *   Drag & Drop für einfaches Hochladen von Dateien
    *   Live-Vorschau der Bilder während des Uploads
    *   Responsives Design für alle Bildschirmgrößen

*   **Automatische Bildoptimierung:**
    *   **Clientseitige Verkleinerung** großer Bilder vor dem Upload (optional, standardmäßig deaktiviert)
    *   Schnellerer Upload durch reduzierte Dateigröße
    *   Weniger Serverlast – ideal für Shared Hosting
    *   Automatische EXIF-Orientierungskorrektur (wichtig für Smartphone-Fotos)
    *   Einstellbare Kompressionsqualität für JPEG/PNG/WebP
    *   Beibehaltung der Originaldimensionen für GIF-Dateien
    *   **Optional:** Zusätzliche serverseitige Bildverarbeitung aktivierbar
    *   **Wichtig:** Beide Optionen deaktiviert = Original-Dateien werden hochgeladen (empfohlen für professionelle Fotografie)

*   **Barrierefreiheit & rechtliche Sicherheit:**
    *   Erzwingt das Setzen von Alt-Texten für Bilder
    *   Legt automatisch Metafelder an, falls sie noch nicht existieren
    *   Optionale Abfrage des Copyrights und der Beschreibung für Mediendateien

*   **YForm-Integration:**
    *   Spezielles YForm-Value-Feld mit automatischer Löschung nicht verwendeter Medien
    *   Multi-Upload-Unterstützung mit dynamischer Vorschau
    *   Einfache Konfiguration über bekannte YForm-Schnittstellen

*   **Mehrsprachigkeit:**
    *   Verfügbar in Deutsch (DE) und Englisch (EN)
    *   Einfach erweiterbar für weitere Sprachen

*   **Sichere API:**
    *   Token-basierte Authentifizierung für externe Zugriffe
    *   Unterstützung für YCOM-Benutzerauthentifizierung
    *   Validierung von Dateitypen und -größen

*   **Info Center Integration:**
    *   Upload-Widget direkt im REDAXO Info Center Dashboard
    *   Schneller Zugriff ohne Medienpool zu öffnen
    *   Kategorie-Auswahl mit rex_media_category_select
    *   Automatische Positionierung nach TimeTracker Widget
    *   Respektiert alle FilePond-Konfigurationen und Benutzerberechtigungen

*   **Media Widget Integration:**
    *   Nahtlose Integration mit REX_MEDIA und REX_MEDIALIST Widgets
    *   Direkter Upload von Dateien in Formularfelder
    *   Bildvorschau mit Thumbnails für bessere Übersicht
    *   Bulk-Übernahme für Medienlisten
    *   Mehrsprachige Benutzeroberfläche

*   **Wartungswerkzeuge:**
    *   Einfache Bereinigung temporärer Dateien und Chunks
    *   Protokollierung aller Upload-Vorgänge
    *   Admin-Interface zur Systemwartung

*   **Alt-Text-Checker für Barrierefreiheit:**
    *   Findet alle Bilder ohne Alt-Text im Medienpool
    *   Statistik-Dashboard mit Vollständigkeits-Prozent
    *   Akkordeon-Vorschau: Große Bildansicht zum besseren Beschreiben
    *   Inline-Bearbeitung direkt in der Tabelle
    *   Unterstützt mehrsprachige Alt-Texte (metainfo_lang_fields)
    *   Dekorative Bilder markieren (Negativ-Liste für Bilder ohne Alt-Text-Pflicht)
    *   **AI Alt-Text-Generierung** - automatische Beschreibungen per Knopfdruck
    *   Unterstützt **Google Gemini** und **Cloudflare Workers AI** als Provider
    *   Schnelle Navigation mit Tab-Taste
    *   Bulk-Speichern aller Änderungen
    *   Filter nach Dateiname und Kategorie
    *   Als Unterseite im Medienpool integriert
    *   Eigene Berechtigung: `filepond_uploader[alt_checker]`

## Installation

1.  **AddOn installieren:** Installiere das AddOn "filepond_uploader" über den REDAXO-Installer.
2.  **AddOn aktivieren:** Aktiviere das AddOn im Backend unter "AddOns".
3.  **Konfigurieren:** Passe die Einstellungen unter "FilePond Uploader > Einstellungen" an deine Bedürfnisse an.
4.  **Fertig:** Der Uploader ist nun einsatzbereit!

## Schnellstart

### Info Center Upload Widget

Das FilePond AddOn bietet ein praktisches Upload-Widget im Info Center des REDAXO-Backends. Dieses Widget ermöglicht das schnelle Hochladen von Dateien direkt aus dem Dashboard heraus, ohne den Medienpool öffnen zu müssen.

#### Features des Info Center Widgets

**Schneller Zugriff:**
- Upload-Funktionalität direkt im Info Center verfügbar
- Kein Wechsel zum Medienpool erforderlich
- Kompakte Darstellung ohne Dashboard zu verlassen

**Vollständige FilePond-Integration:**
- Alle konfigurierten FilePond-Einstellungen werden übernommen
- Drag & Drop Upload direkt im Info Center
- Chunk-Upload für große Dateien
- Bildoptimierung und Metadaten-Eingabe

**Kategorie-Auswahl:**
- Dropdown zur Auswahl der Zielkategorie
- Verwendet den Standard rex_media_category_select
- Respektiert Benutzerberechtigungen für Kategorien

**Intelligente Positionierung:**
- Erscheint automatisch nach dem TimeTracker Widget
- Nur für angemeldete Benutzer sichtbar
- Robuste Erkennung verfügbarer AddOns

#### Aktivierung

Das Info Center Widget wird automatisch aktiviert, wenn folgende Bedingungen erfüllt sind:

1. **Info Center AddOn installiert:** Das REDAXO Info Center AddOn muss aktiviert sein
2. **FilePond Uploader aktiv:** Dieses AddOn muss aktiviert sein
3. **Benutzer angemeldet:** Widget erscheint nur für angemeldete Backend-Benutzer

> **Hinweis:** Das Widget wird automatisch zwischen TimeTracker und anderen Widgets positioniert (Priorität 0.5). Es sind keine weiteren Konfigurationen erforderlich.

#### Widget-Funktionalität

**Upload-Formular:**
- Identische Struktur wie die Medienpool-Upload-Seite
- Kategorie-Auswahl mit allen verfügbaren Medienpool-Kategorien
- Automatische Aktualisierung bei Kategorie-Wechsel
- Respektiert alle FilePond-Konfigurationen (Dateitypen, Größenlimits, etc.)

**Metadaten-Eingabe:**
- Vollständige Integration der Metadaten-Dialoge
- Unterstützung für mehrsprachige Felder (MetaInfo Lang Fields)
- Validierung nach konfigurierten Regeln
- Alt-Text und Copyright-Abfrage wie gewohnt

**Benutzerfreundlichkeit:**
- Drag & Drop direkt im Widget
- Live-Vorschau hochgeladener Dateien
- Fortschrittsanzeige und Chunk-Upload
- Nahtlose Integration in die REDAXO-Oberfläche

#### Deaktivierung

Falls das Info Center Widget nicht gewünscht ist, kann es durch Deaktivierung des Info Center AddOns oder durch Customizing in der `boot.php` entfernt werden.

### Media Widget Integration

Das FilePond AddOn bietet eine nahtlose Integration mit REDAXO's Standard Media Widgets (REX_MEDIA und REX_MEDIALIST). Nach dem Upload können Dateien direkt in Formularfelder übernommen werden.

#### Verwendung

1. **Öffne ein Media Widget:** Klicke in einem beliebigen Formular auf das "Öffnen" Icon neben einem REX_MEDIA oder REX_MEDIALIST Feld
2. **Upload-Modus aktiviert:** FilePond erkennt automatisch den Widget-Kontext und zeigt einen Info-Banner
3. **Dateien hochladen:** Nutze die gewohnte Drag&Drop oder Auswahl-Funktionalität
4. **Direktübernahme:** Nach erfolgreichem Upload erscheinen Buttons zur direkten Übernahme

#### Features der Media Widget Integration

**Für REX_MEDIA (Einzelmedien):**
- Upload → Übernahme → Fenster schließt sich automatisch
- Bildvorschau mit 80x80px Thumbnails
- Dateitypspezifische Icons für Nicht-Bild-Dateien

**Für REX_MEDIALIST (Medienlisten):**
- Upload → Fenster bleibt offen für weitere Uploads
- Einzelne Übernahme pro Datei möglich
- "Alle übernehmen" Button bei mehreren Dateien
- Duplikat-Schutz verhindert doppelte Einträge
- Visuelles Feedback mit "Hinzugefügt" Status

#### Bildvorschau-System

Das AddOn zeigt automatische Vorschauen für hochgeladene Inhalte:

**Bilder (jpg, png, gif, webp, etc.):**
- 80x80px Thumbnail-Vorschau
- Proportionale Skalierung mit object-fit
- Fallback auf Dateitype-Icon bei Fehlern

**Andere Dateitypen:**
- Farbcodierte Icons nach Dateityp
- PDF (rot), Word (blau), Excel (grün), etc.
- Dateiendung als Label unter dem Icon

#### Mehrsprachige Benutzeroberfläche

Die Media Widget Integration unterstützt vollständige Mehrsprachigkeit:

**Deutsch:**
- "Upload-Auswahl"
- "Die ausgewählten Elemente können in die Liste übernommen werden."

**English:**
- "Upload Selection"
- "The selected items can be added to the list."

> **Hinweis:** Die Media Widget Integration ist ein Add-On Feature und erfordert keine zusätzliche Konfiguration. Sie funktioniert automatisch mit allen bestehenden REX_MEDIA und REX_MEDIALIST Feldern.

### Verwendung als YForm-Feldtyp

```php
$yform->setValueField('filepond', [
    'name' => 'bilder',
    'label' => 'Bildergalerie',
    'allowed_max_files' => 5,
    'allowed_types' => 'image/*',
    'allowed_filesize' => 10,
    'category' => 1,
    'delayed_upload' => 0
]);
```

### Option: `delayed_upload`

Mit der Option `delayed_upload` wird gesteuert, wann die Dateien tatsächlich hochgeladen und mit dem Formular verknüpft werden:

| Wert | Verhalten | Typischer Einsatzzweck |
|------|-----------|-------------------------|
| `0`  | Dateien werden **sofort beim Auswählen** hochgeladen. | Standardverhalten, z. B. Bildergalerien |
| `1`  | Dateien werden erst hochgeladen, wenn der Nutzer den **Upload-Button** klickt. | Wenn mehrere Dateien gesammelt und gemeinsam hochgeladen werden sollen (z. B. Bewerbungsunterlagen) |
| `2`  | Nach dem Upload wird die **YForm sofort automatisch abgesendet**. | Schnell-Upload-Formulare. Hier sollten alle übrigen Felder des Formulars **clientseitig auf `required` geprüft werden**, da die Dateien sonst bereits hochgeladen werden, auch wenn die Formularvalidierung fehlschlägt. |

---

> **Hinweis:**  
> Das `filepond`-Value-Feld in YForm ist eine bequeme Möglichkeit, den Uploader zu verwenden.  
> Alternativ kann ein normales Input-Feld mit den notwendigen `data`-Attributen versehen werden.  
> In diesem Fall entfällt jedoch die automatische Löschung nicht verwendeter Medien.

### Verwendung in Modulen

#### Eingabe

```html
<input
    type="hidden"
    name="REX_INPUT_VALUE[1]"
    value="REX_VALUE[1]"
    data-widget="filepond"
    data-filepond-cat="1"
    data-filepond-maxfiles="5"
    data-filepond-types="image/*"
    data-filepond-maxsize="10"
    data-filepond-lang="de_de"
    data-filepond-chunk-enabled="true"
    data-filepond-chunk-size="5242880"
    data-filepond-title-required="true"
    data-filepond-metainfo-lang="true"
>
```

**Hinweis zu den neuen Attributen:**
- `data-filepond-title-required="true"`: Macht das title Feld im Metadaten-Dialog zu einem Pflichtfeld
- `data-filepond-metainfo-lang="true"`: Aktiviert die automatische Erkennung mehrsprachiger MetaInfo-Felder

**Hinweis zu `data-filepond-types`:**
- MIME-Types werden bevorzugt: `image/*`, `video/*`, `application/pdf`
- Dateiendungen werden automatisch konvertiert: `.pdf`, `.doc`, `.docx`
- Beide Formate können gemischt werden: `image/*, .pdf, .doc`

#### Ausgabe

```php
<?php
$files = explode(',', 'REX_VALUE[1]');
foreach($files as $file) {
    if($media = rex_media::get($file)) {
        // Standard-Metadaten
        echo '<img
            src="'.$media->getUrl().'"
            alt="'.$media->getValue('med_alt').'"
            title="'.$media->getValue('title').'"
        >';
        
        // Mehrsprachige Metadaten (falls MetaInfo Lang Fields verwendet wird)
        if (class_exists('\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper')) {
            $titles = \FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::getFieldValues(
                $media, 
                'med_title_lang'
            );
            
            // Titel für aktuelle Sprache
            $currentTitle = $titles[rex_clang::getCurrentId()] ?? '';
            echo '<p>Titel: ' . rex_escape($currentTitle) . '</p>';
            
            // Beschreibung für aktuelle Sprache
            $descriptions = \FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::getFieldValues(
                $media, 
                'med_description_lang'
            );
            $currentDescription = $descriptions[rex_clang::getCurrentId()] ?? '';
            echo '<p>Beschreibung: ' . rex_escape($currentDescription) . '</p>';
        }
    }
}
?>
```

### Uploads zu E-Mails hinzufügen
Für die Übernahme der Uploads in E-Mails über YForm Formulare steht eine Action zur Verfügung, die in ein Formular eingebaut werden kann.
In der Pipe Notation schreibt man:

```php
action|filepond2email|label_filepond
```

In der PHP Notation schreibt man:

```php
$yform->setActionField('filepond2email',['label_filepond']);
```

`label_filepond` ist zu ersetzen durch den Feldnamen, den das filepond Feld hat, also z.B. `uploads`

## Chunk-Upload für große Dateien

Der Chunk-Upload ist das Herzstück des FilePond-Uploaders und ermöglicht das zuverlässige Hochladen großer Dateien auch bei langsameren Internetverbindungen.

### Funktionsweise

1. **Datei-Aufteilung:** Große Dateien werden clientseitig in kleine Teile (Chunks) aufgeteilt.
2. **Chunk-weiser Upload:** Jeder Chunk wird einzeln hochgeladen, mit individueller Fortschrittsanzeige.
3. **Serverseitige Zusammenführung:** Nach Abschluss des Uploads werden alle Chunks zu einer vollständigen Datei zusammengefügt.
4. **Automatische Bereinigung:** Temporäre Dateien werden nach erfolgreichem Upload automatisch entfernt.

### Vorteile

- **Verbesserte Zuverlässigkeit:** Bei Netzwerkproblemen müssen nur fehlgeschlagene Chunks erneut hochgeladen werden.
- **Große Dateien:** Überwindung von Server-Limits für maximale Upload-Größen.
- **Bessere Performance:** Serverseitige Ressourcen werden effizienter genutzt.
- **Benutzerfreundlichkeit:** Klare Fortschrittsanzeige für jeden Chunk und die Gesamtdatei.

### Konfiguration

Im Backend können Sie folgende Chunk-Upload-Einstellungen anpassen:

- **Chunk-Upload aktivieren/deaktivieren:** Globale Einstellung für alle Upload-Felder.
- **Chunk-Größe:** Die Größe jedes Chunks in MB (Standard: 5MB).
- **Temporäre Dateien aufräumen:** Manuelle Bereinigung alter temporärer Dateien.

## Helper-Klasse

Das AddOn enthält eine Helper-Klasse, die das Einbinden von CSS- und JavaScript-Dateien vereinfacht.

```php
// Im Template oder Modul
<?php
echo filepond_helper::getScripts();
echo filepond_helper::getStyles();
?>
```

## Konfiguration

### Data-Attribute

Folgende `data`-Attribute können zur Konfiguration verwendet werden:

| Attribut                     | Beschreibung                            | Standardwert |
| ---------------------------- | --------------------------------------- | ------------ |
| `data-filepond-cat`          | Medienpool Kategorie ID                 | `0`          |
| `data-filepond-types`        | Erlaubte Dateitypen (MIME-Types oder Dateiendungen, kommagetrennt) | `image/*`    |
| `data-filepond-maxfiles`     | Maximale Anzahl an Dateien              | `30`         |
| `data-filepond-maxsize`      | Maximale Dateigröße in MB               | `10`         |
| `data-filepond-lang`         | Sprache (`de_de` / `en_gb`)             | `de_de`      |
| `data-filepond-skip-meta`    | Meta-Eingabe deaktivieren               | `false`      |
| `data-filepond-chunk-enabled`| Chunk-Upload aktivieren                 | `true`       |
| `data-filepond-chunk-size`   | Chunk-Größe in MB                       | `5`          |
| `data-filepond-delayed-upload` | Verzögerter Upload-Modus              | `false`      |
| `data-filepond-delayed-type` | Upload-Modus-Typ (1=Button, 2=Submit) | `1` wenn delayed-upload aktiv |
| `data-filepond-title-required` | Titel-Feld als Pflichtfeld           | `false`      |
| `data-filepond-title-lang-required` | Mehrsprachiger Titel als Pflichtfeld (deprecated) | `true` |
| `data-filepond-metainfo-lang` | MetaInfo Lang Fields Integration aktivieren | `false` |
| `data-filepond-max-pixel`    |  Maximale Bildgröße in Pixeln für clientseitige Verkleinerung | `2100` |
| `data-filepond-image-quality` | JPEG/WebP Kompressionsqualität (10-100) | `90` |
| `data-filepond-client-resize` | *Clientseitige Bildverkleinerung aktivieren (`true`/`false`) | `false` |
| `data-filepond-opener-field`  | Opener Input Field für Media Widget Integration | - |


#### Spezielle Attribute für Metadaten

**`data-filepond-title-required`**
Steuert, ob das einfache `title` Feld (für interne Verwaltung) als Pflichtfeld behandelt wird:
```html
<!-- Titel als Pflichtfeld -->
<input data-filepond-title-required="true" data-widget="filepond" ...>

<!-- Titel optional (Standard) -->
<input data-filepond-title-required="false" data-widget="filepond" ...>
```

**`data-filepond-metainfo-lang`**
Aktiviert die automatische Erkennung und Integration von MetaInfo Lang Fields:
```html
<!-- MetaInfo Lang Fields aktivieren -->
<input data-filepond-metainfo-lang="true" data-widget="filepond" ...>
```

> **Hinweis:** Das Attribut `data-filepond-title-lang-required` ist deprecated. Mehrsprachige Titel (`med_title_lang`) sind automatisch Pflichtfelder und können nicht deaktiviert werden. Verwenden Sie stattdessen `data-filepond-title-required` für das einfache Titel-Feld.

### Erlaubte Dateitypen (MIME-Types)

#### Grundlegende Syntax

Das Addon unterstützt **sowohl MIME-Types als auch Dateiendungen**. MIME-Types werden jedoch bevorzugt, da sie sicherer und eindeutiger sind.

**Empfohlene Verwendung (MIME-Types):**
```
data-filepond-types="mime/type"
```

**Alternativ (Dateiendungen):**
```
data-filepond-types=".extension"
```

**Wichtig:** Dateiendungen werden automatisch in MIME-Types konvertiert. Beide Formate können gemischt werden.

#### Standard MIME-Types

*   **Bilder:** `image/*` oder `image/jpeg, image/png, image/gif, image/webp`
*   **Videos:** `video/*` oder `video/mp4, video/webm, video/quicktime`
*   **Audio:** `audio/*` oder `audio/mpeg, audio/wav, audio/ogg`
*   **PDFs:** `application/pdf`
*   **Microsoft Word:** `application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document`
*   **Microsoft Excel:** `application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
*   **Microsoft PowerPoint:** `application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation`

#### Beispiele

**Nur Bilder (empfohlen):**
```html
data-filepond-types="image/*"
```

**Bilder und PDFs (MIME-Types - empfohlen):**
```html
data-filepond-types="image/*, application/pdf"
```

**Bilder und PDFs (gemischt - funktioniert auch):**
```html
data-filepond-types="image/*, .pdf"
```

**Bilder, Videos und PDFs (MIME-Types - empfohlen):**
```html
data-filepond-types="image/*, video/*, application/pdf"
```

**Bilder, Videos und PDFs (Dateiendungen - wird automatisch konvertiert):**
```html
data-filepond-types="image/*, video/*, .pdf"
```

**Dokumente (MIME-Types - empfohlen):**
```html
data-filepond-types="application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, text/plain"
```

**Dokumente (Dateiendungen - wird automatisch konvertiert):**
```html
data-filepond-types=".pdf, .doc, .docx, .txt"
```

**Microsoft Office (MIME-Types - empfohlen):**
```html
data-filepond-types="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation"
```

**Medien und Dokumente gemischt (beide Formate):**
```html
data-filepond-types="image/*, video/*, application/pdf, .doc, .docx, .txt"
```

#### Unterstützte Dateiendungen

Das Addon konvertiert automatisch folgende Dateiendungen zu den entsprechenden MIME-Types:

*   **Bilder:** `.jpg, .jpeg, .png, .gif, .webp, .avif, .svg, .bmp, .tiff, .tif, .ico`
*   **Videos:** `.mp4, .webm, .ogg, .ogv, .avi, .mov, .wmv, .flv, .mkv`
*   **Audio:** `.mp3, .wav, .ogg, .oga, .flac, .m4a, .aac`
*   **Dokumente:** `.pdf, .doc, .docx, .xls, .xlsx, .ppt, .pptx, .odt, .ods, .odp`
*   **Text:** `.txt, .csv, .rtf, .html, .htm, .xml, .json`
*   **Archive:** `.zip, .rar, .7z, .tar, .gz, .bz2`

> **Hinweis:** MIME-Types sind die bevorzugte Methode, da sie eindeutiger sind und weniger Fehleranfälligkeit haben. Dateiendungen werden nur aus Kompatibilitätsgründen unterstützt und automatisch in MIME-Types konvertiert.

## Session-Konfiguration für individuelle Anpassungen

> **Hinweis:** Bei Verwendung von YForm/Yorm muss `rex_login::startSession()` vor Yform/YOrm aufgerufen werden.

Im Frontend sollte die Session gestartet werden:

```php
rex_login::startSession();
```

Die Werte sollten zurückgesetzt werden, wenn sie nicht mehr benötigt werden.

### API-Token übergeben

```php
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));
```

Dadurch wird der API-Token übergeben, um Datei-Uploads auch außerhalb von YCOM im Frontend zu ermöglichen.

### Meta-Abfrage deaktivieren

```php
rex_set_session('filepond_no_meta', true);
```

Dadurch lässt sich die Meta-Abfrage (Titel, Alt-Text, Copyright) deaktivieren (boolescher Wert: `true` / `false`).

### Titel-Pflichtfeld konfigurieren

```php
rex_set_session('filepond_title_required', true);
```

Dadurch wird das einfache title Feld als Pflichtfeld markiert (boolescher Wert: `true` / `false`).

### MetaInfo Lang Fields aktivieren

```php
rex_set_session('filepond_metainfo_lang', true);
```

Dadurch wird die automatische Erkennung mehrsprachiger MetaInfo-Felder aktiviert (boolescher Wert: `true` / `false`).

### Modulbeispiel

```php
<?php
rex_login::startSession();
// Session-Token für API-Zugriff setzen (für Frontend)
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));

// Optional: Meta-Eingabe deaktivieren
rex_set_session('filepond_no_meta', true);

// Optional: Titel als Pflichtfeld
rex_set_session('filepond_title_required', true);

// Optional: MetaInfo Lang Fields aktivieren
rex_set_session('filepond_metainfo_lang', true);

// Filepond Assets einbinden (besser im Template ablegen)
if (rex::isFrontend()) {
    echo filepond_helper::getStyles();
    echo filepond_helper::getScripts();
}
?>

<form class="uploadform" method="post" enctype="multipart/form-data">
    <input
        type="hidden"
        name="REX_INPUT_MEDIALIST[1]"
        value="REX_MEDIALIST[1]"
        data-widget="filepond"
        data-filepond-cat="1"
        data-filepond-types="image/*,video/*,application/pdf"
        data-filepond-maxfiles="3"
        data-filepond-maxsize="10"
        data-filepond-lang="de_de"
        data-filepond-skip-meta="<?= rex_session('filepond_no_meta', 'boolean', false) ? 'true' : 'false' ?>"
        data-filepond-title-required="<?= rex_session('filepond_title_required', 'boolean', false) ? 'true' : 'false' ?>"
        data-filepond-metainfo-lang="<?= rex_session('filepond_metainfo_lang', 'boolean', false) ? 'true' : 'false' ?>"
        data-filepond-chunk-enabled="true"
        data-filepond-chunk-size="5242880"
    >
</form>
```

## Initialisierung im Frontend und Tipps

```js
document.addEventListener('DOMContentLoaded', function() {
  // Dieser Code wird ausgeführt, nachdem das HTML vollständig geladen wurde.
  initFilePond();
});
```

### JQuery-Variante
Falls JQuery im Einsatz ist, rex:ready im Frontend triggern.

```js
document.addEventListener('DOMContentLoaded', function() {
  // Dieser Code wird ausgeführt, nachdem das HTML vollständig geladen wurde.
  $('body').trigger('rex:ready', [$('body')]);
});
```

### Stylefix für das Frontend 
Falls das Panel nicht richtig dargestellt wird, kann es helfen, den Stil anzupassen:

```css
.filepond--panel-root {
    border: 1px solid var(--fp-border);
    background-color: #eedede;
    min-height: 150px;
}
```

## Anpassung der FilePond-Stile

Das AddOn enthält anpassbare Stile für verschiedene Bereiche der FilePond-Oberfläche. Diese können über CSS-Variablen und eigene CSS-Regeln individuell angepasst werden.

### Upload-Button anpassen

Der Upload-Button im verzögerten Upload-Modus kann über CSS-Variablen vollständig angepasst werden:

```css
:root {
    --filepond-upload-btn-color: #4285f4;         /* Hintergrundfarbe */
    --filepond-upload-btn-hover-color: #3367d6;   /* Hover-Farbe */
    --filepond-upload-btn-text-color: #fff;       /* Textfarbe */
    --filepond-upload-btn-border-radius: 4px;     /* Eckenradius */
    --filepond-upload-btn-padding: 10px 16px;     /* Innenabstand */
    --filepond-upload-btn-font-size: 14px;        /* Schriftgröße */
    --filepond-upload-btn-font-weight: 500;       /* Schriftstärke */
    --filepond-upload-btn-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);           /* Schatten */
    --filepond-upload-btn-shadow-hover: 0 4px 8px rgba(0, 0, 0, 0.2);     /* Hover-Schatten */
}
```

#### Beispiele für verschiedene Button-Stile

**Roter Warn-Button:**
```css
:root {
    --filepond-upload-btn-color: #dc3545;
    --filepond-upload-btn-hover-color: #c82333;
}
```

**Grüner Success-Button:**
```css
:root {
    --filepond-upload-btn-color: #28a745;
    --filepond-upload-btn-hover-color: #218838;
}
```

**Minimalistischer Button:**
```css
:root {
    --filepond-upload-btn-color: transparent;
    --filepond-upload-btn-hover-color: rgba(0, 0, 0, 0.05);
    --filepond-upload-btn-text-color: #007bff;
    --filepond-upload-btn-shadow: none;
    --filepond-upload-btn-shadow-hover: none;
}

.filepond-upload-btn {
    border: 2px solid currentColor !important;
}
```

### Thumbnail-Rahmen anpassen

Die Rahmen bei Upload-Status (Erfolg/Fehler) können ebenfalls individuell angepasst werden:

```css
/* Erfolgreicher Upload - grüner Rahmen */
[data-filepond-item-state='processing-complete'] {
    border: 3px solid #28a745 !important;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.3) !important;
    border-radius: 0.5em !important;
}

/* Fehler beim Upload - roter Rahmen */
[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    border: 3px solid #dc3545 !important;
    box-shadow: 0 0 8px rgba(220, 53, 69, 0.3) !important;
    border-radius: 0.5em !important;
}
```

#### Glow-Animation deaktivieren

Falls die pulsierenden Glow-Effekte nicht gewünscht sind:

```css
[data-filepond-item-state='processing-complete'],
[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    animation: none !important;
}
```

#### Alternative Rahmen-Stile

**Dickere Rahmen:**
```css
[data-filepond-item-state='processing-complete'] {
    border: 5px solid #28a745 !important;
}

[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    border: 5px solid #dc3545 !important;
}
```

**Gestrichelte Rahmen:**
```css
[data-filepond-item-state='processing-complete'] {
    border: 3px dashed #28a745 !important;
}

[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    border: 3px dashed #dc3545 !important;
}
```

**Abgerundete Ecken anpassen:**
```css
[data-filepond-item-state='processing-complete'],
[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    border-radius: 15px !important; /* Stark abgerundete Ecken */
}
```

### Theme-spezifische Anpassungen

Das AddOn unterstützt vordefinierte Themes:

**Dark Theme:**
```css
.dark-theme .filepond-upload-btn {
    --filepond-upload-btn-color: #3d4852;
    --filepond-upload-btn-hover-color: #2d3748;
    --filepond-upload-btn-text-color: #f7fafc;
}
```

**Minimal Theme:**
```css
.minimal-theme .filepond-upload-btn {
    --filepond-upload-btn-color: transparent;
    --filepond-upload-btn-hover-color: rgba(0, 0, 0, 0.05);
    --filepond-upload-btn-text-color: #2196F3;
    --filepond-upload-btn-shadow: none;
    --filepond-upload-btn-shadow-hover: none;
    border: 1px solid currentColor;
}
```

### Eigene CSS-Datei einbinden

Die Anpassungen sollten in einer eigenen CSS-Datei gespeichert und **nach** den FilePond-Styles geladen werden:

```html
<!-- Nach den FilePond-Styles -->
<link rel="stylesheet" href="path/to/filepond-custom-styles.css">
<link rel="stylesheet" href="path/to/meine-anpassungen.css">
```

**Im REDAXO-Template:**
```php
<?php
// Standard FilePond-Styles laden
echo filepond_helper::getStyles();

// Eigene Anpassungen laden
rex_view::addCssFile($this->getAssetsUrl('css/meine-filepond-anpassungen.css'));
?>
```

### Vollständige Stil-Überschreibung

Für umfassende Änderungen können die originalen Stile komplett überschrieben werden:

```css
/* Komplett eigener Upload-Button */
.filepond-upload-btn {
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4) !important;
    color: white !important;
    border: none !important;
    border-radius: 25px !important;
    padding: 15px 30px !important;
    font-weight: bold !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    transition: all 0.3s ease !important;
}

.filepond-upload-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3) !important;
}
```

> **Tipp:** Verwende die Browser-Entwicklertools (F12), um die CSS-Selektoren zu identifizieren und deine Änderungen in Echtzeit zu testen, bevor du sie in deine CSS-Datei überträgst.

## Bildoptimierung

Bilder können automatisch optimiert werden – entweder **clientseitig im Browser**, **serverseitig nach dem Upload** oder **beides kombiniert**. Beide Optionen sind unabhängig voneinander aktivierbar.

### Clientseitige Bildverarbeitung (Standard: aktiviert)

Die clientseitige Verarbeitung nutzt FilePond-Plugins, um Bilder direkt im Browser zu optimieren:

*   **Automatische Verkleinerung** großer Bilder auf die konfigurierte Maximalgröße
*   **EXIF-Orientierungskorrektur** für Smartphone-Fotos
*   **Qualitätskompression** für JPEG/PNG/WebP
*   **Kein Upscaling** – kleine Bilder bleiben unverändert
*   **GIF-Dateien** werden nicht verändert

**Vorteile:**
*   ✅ Schnellerer Upload durch kleinere Dateien
*   ✅ Weniger Serverlast und Speicherverbrauch
*   ✅ **Ideal für Shared Hosting** mit limitierten Server-Ressourcen
*   ✅ Sofortige Vorschau der optimierten Bilder

**Deaktivierung:**
1. Navigiere zu **REDAXO > AddOns > FilePond Uploader > Einstellungen**
2. Deaktiviere die Option **"Clientseitige Bildverkleinerung aktivieren"**

### Serverseitige Bildverarbeitung (Standard: deaktiviert)

Die serverseitige Verarbeitung optimiert Bilder nach dem Upload auf dem Server.

**Vorteile:**
*   ✅ Funktioniert auch bei älteren Browsern ohne Canvas-Unterstützung
*   ✅ Zusätzliche Sicherheitsstufe für Bildvalidierung
*   ✅ Einheitliche Verarbeitung unabhängig vom Client
*   ✅ **Ideal bei ausreichenden Server-Ressourcen**

**Aktivierung:**
1. Navigiere zu **REDAXO > AddOns > FilePond Uploader > Einstellungen**
2. Aktiviere die Option **"Serverseitige Bildverarbeitung aktivieren"**

### Kombinationsmöglichkeiten

| Clientseitig | Serverseitig | Anwendungsfall |
|:------------:|:------------:|----------------|
| ✅ | ❌ | **Standard** – Ideal für Shared Hosting |
| ❌ | ✅ | Server mit guten Ressourcen, ältere Browser |
| ✅ | ✅ | Kaskadierende Optimierung (siehe unten) |
| ❌ | ❌ | Keine Bildoptimierung (Originalbilder behalten) |

### Konfiguration

Die folgenden **globalen Einstellungen** gelten standardmäßig für beide Verarbeitungsmethoden:

| Einstellung | Standard | Beschreibung |
|-------------|----------|--------------|
| **Maximale Bildgröße** | 2100 px | Maximale Breite/Höhe in Pixeln |
| **Bildqualität** | 90 | JPEG/WebP/PNG-Kompression (10-100) |

### Erweiterte Einstellungen für kombinierte Verarbeitung

Wenn **beide Verarbeitungsmethoden aktiviert** sind, erscheinen zusätzliche Einstellungen, um die clientseitige Vorverarbeitung separat zu konfigurieren:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Clientseitige max. Bildgröße** | Maximale Größe für die Vorverkleinerung im Browser |
| **Clientseitige Bildqualität** | Qualität für die clientseitige Kompression |

**Typischer Workflow:**
1. **Client:** Verkleinert z.B. von 6000px auf 3000px mit 95% Qualität → schnellerer Upload
2. **Server:** Optimiert auf finale 1600px mit 85% Qualität via ImageMagick → beste Qualität

Wenn die clientseitigen Werte leer gelassen werden, werden die globalen Einstellungen verwendet.

### EXIF-Orientierung korrigieren

Die EXIF-Orientierungskorrektur erfolgt **automatisch clientseitig** durch das FilePond EXIF-Orientation Plugin (wenn clientseitige Verarbeitung aktiviert ist). Dies ist besonders wichtig für Fotos von Smartphones.

**Funktionsweise:**

*   Erkennt EXIF-Orientierungsinformationen in JPEG-Bildern
*   Dreht und spiegelt Bilder automatisch in die korrekte Ausrichtung
*   Verhindert, dass Hochformat-Bilder als Querformat erscheinen
*   Die Korrektur erfolgt **vor dem Upload** im Browser

**Serverseitige EXIF-Korrektur (optional):**

Falls die serverseitige Bildverarbeitung aktiviert ist, kann zusätzlich eine serverseitige EXIF-Korrektur erfolgen:

1. Navigiere zu **REDAXO > AddOns > FilePond Uploader > Einstellungen**
2. Aktiviere die Option **"EXIF-Orientierung automatisch korrigieren"**
3. Speichere die Einstellungen

**Hinweise:**

*   Die clientseitige Korrektur ist immer aktiv und benötigt keine Konfiguration
*   Die serverseitige Funktion benötigt die PHP-EXIF-Erweiterung
*   Nur JPEG-Bilder werden verarbeitet, da andere Formate selten EXIF-Daten enthalten

## Mehrsprachigkeit und MetaInfo Lang Fields Integration

Das FilePond AddOn bietet umfassende Unterstützung für mehrsprachige Metadaten durch die Integration mit dem **MetaInfo Lang Fields** AddOn von Friends of REDAXO.

### Voraussetzungen

Für die Verwendung mehrsprachiger Metafelder sind folgende AddOns erforderlich:

1. **MetaInfo AddOn** (Standard REDAXO AddOn)
2. **MetaInfo Lang Fields AddOn** von Friends of REDAXO

> **Hinweis:** Das System funktioniert automatisch mit allen in REDAXO konfigurierten Sprachen (rex_clang).

### Installation und Einrichtung

#### 1. MetaInfo Lang Fields AddOn installieren

```bash
# Via Composer (empfohlen)
composer require friendsofredaxo/metainfo_lang_fields

# Oder über den REDAXO Installer
```

#### 2. Mehrsprachige Metafelder anlegen

Erstelle in **REDAXO > AddOns > MetaInfo > Medien** neue Felder mit mehrsprachigen Feldtypen:

**Beispiel: Mehrsprachiger Titel**
- **Feldname:** `med_title_lang`
- **Feldtyp:** `lang_text` oder `lang_text_all`
- **Bezeichnung:** `Titel (mehrsprachig)`

**Beispiel: Mehrsprachige Beschreibung**
- **Feldname:** `med_description_lang` 
- **Feldtyp:** `lang_textarea` oder `lang_textarea_all`
- **Bezeichnung:** `Beschreibung (mehrsprachig)`

#### 3. Automatische Erkennung

Das FilePond AddOn erkennt automatisch:
- Alle verfügbaren MetaInfo-Felder
- Welche Felder mehrsprachig konfiguriert sind
- Alle konfigurierten Sprachen in REDAXO

### Verfügbare mehrsprachige Feldtypen

| Feldtyp | Beschreibung | Verwendung |
|---------|-------------|------------|
| `lang_text` | Einzeiliges Textfeld für die aktuelle Sprache | Titel, Keywords |
| `lang_text_all` | Einzeiliges Textfeld für alle Sprachen | Titel, Alt-Texte |
| `lang_textarea` | Mehrzeiliges Textfeld für die aktuelle Sprache | Beschreibungen |
| `lang_textarea_all` | Mehrzeiliges Textfeld für alle Sprachen | Beschreibungen |

### Empfohlene Metafelder

#### Standard-Felder (einsprachig)
Diese Felder bleiben einsprachig und werden automatisch erkannt:
- `title` - Titel für interne Verwaltung
- `med_alt` - Alt-Text für Barrierefreiheit  
- `med_copyright` - Copyright-Informationen

#### Mehrsprachige Felder
Erstelle diese Felder als mehrsprachige Varianten:

**Mehrsprachiger Titel (`med_title_lang`):**
```
Feldname: med_title_lang
Feldtyp: lang_text_all
Bezeichnung: Titel (mehrsprachig)
Priorität: 1
```

**Mehrsprachige Beschreibung (`med_description_lang`):**
```
Feldname: med_description_lang  
Feldtyp: lang_textarea_all
Bezeichnung: Beschreibung (mehrsprachig)
Priorität: 4
```

**Mehrsprachige Keywords (`med_keywords_lang`):**
```
Feldname: med_keywords_lang
Feldtyp: lang_text_all
Bezeichnung: Schlüsselwörter (mehrsprachig)
Priorität: 5
```

### Benutzeroberfläche

#### Upload-Dialog
Bei mehrsprachigen Feldern wird eine benutzerfreundliche Oberfläche angezeigt:

1. **Hauptsprache sichtbar:** Die erste/Standard-Sprache wird immer angezeigt
2. **Weitere Sprachen über Globus-Icon:** Klick auf das Globus-Symbol öffnet weitere Sprachen
3. **Sprachspezifische Validierung:** Verschiedene Validierungsregeln pro Sprache
4. **Alt-Text mit Dekorativ-Option:** Checkbox für dekorative Bilder pro Sprache

#### Feld-Hierarchie im Dialog
Die Felder werden in folgender Reihenfolge angezeigt:
1. `title` (einfacher Titel für interne Verwaltung)
2. `med_title_lang` (mehrsprachiger Titel) - **Pflichtfeld**
3. `med_alt` (Alt-Text für Bilder) - **Pflichtfeld bei Bildern**
4. `med_copyright` (Copyright-Information)
5. `med_description` oder `med_description_lang` (Beschreibung)
6. Weitere Felder alphabetisch sortiert

### Validierung und Pflichtfelder

#### Automatische Validierung
- **`med_title_lang`:** Immer Pflichtfeld bei mehrsprachigen Titeln
- **`med_alt`:** Pflichtfeld bei Bildern (kann per "dekorativ" deaktiviert werden)
- **`title`:** Optional (kann in Settings/YForm als Pflichtfeld konfiguriert werden)

#### Dekorative Bilder
Für Alt-Texte gibt es eine "Dekoratives Bild" Checkbox:
- Deaktiviert die Alt-Text-Pflicht für das jeweilige Sprachfeld
- Funktioniert sprachspezifisch
- Orientiert sich an Accessibility-Standards

### Konfiguration

#### In YForm-Feldern
Mehrsprachige Metafelder werden automatisch erkannt und angezeigt. Keine zusätzliche Konfiguration erforderlich.

#### Titel-Feld als Pflichtfeld
Das einfache `title` Feld kann optional als Pflichtfeld konfiguriert werden:

**Global (Upload-Seite):**
In den AddOn-Einstellungen unter "Titel-Feld auf Upload-Seite als Pflichtfeld"

**Pro YForm-Feld:**
```php
$yform->setValueField('filepond', [
    'name' => 'upload',
    'label' => 'Dateien',
    'title_required' => 1  // Titel als Pflichtfeld
]);
```

**In Modulen/Templates:**
```html
<input 
    data-widget="filepond" 
    data-filepond-title-required="true"
    ...
>
```

### Datenformat und Speicherung

#### Mehrsprachige Daten
Mehrsprachige Felder werden im MetaInfo Lang Fields Format gespeichert:

```json
[
    {"clang_id": 1, "value": "Deutscher Titel"},
    {"clang_id": 2, "value": "English Title"}
]
```

#### Abrufen mehrsprachiger Daten

**In Templates/Modulen:**
```php
<?php
$media = rex_media::get('dateiname.jpg');

// MetaInfo Lang Fields Helper verwenden
if (class_exists('\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper')) {
    $titles = \FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::getFieldValues(
        $media, 
        'med_title_lang'
    );
    
    // Titel für aktuelle Sprache
    $currentTitle = $titles[rex_clang::getCurrentId()] ?? '';
    
    // Titel für spezifische Sprache (z.B. Deutsch)
    $germanTitle = $titles[1] ?? '';
}

// Traditionelles Abrufen über getValue
$titleData = $media->getValue('med_title_lang');
// $titleData enthält JSON-String mit allen Sprachen
?>
```

**In YForm/YOrm:**
```php
// Bei YOrm-Models werden mehrsprachige Felder automatisch aufgelöst
$dataset = \rex_yform_manager_dataset::get(1, 'my_table');
$files = explode(',', $dataset->getValue('upload_field'));

foreach ($files as $filename) {
    if ($media = rex_media::get($filename)) {
        $titleData = $media->getValue('med_title_lang');
        
        if ($titleData) {
            $titles = json_decode($titleData, true);
            foreach ($titles as $langData) {
                echo "Sprache {$langData['clang_id']}: {$langData['value']}<br>";
            }
        }
    }
}
```

### API und JavaScript

#### Automatische Felderkennung
Das AddOn stellt eine API bereit, die automatisch alle verfügbaren Metafelder erkennt:

```javascript
// API-Aufruf für Felderkennung
fetch('/redaxo/index.php?rex-api-call=filepond_auto_metainfo&action=get_fields')
  .then(response => response.json())
  .then(data => {
      data.fields.forEach(field => {
          console.log(`Feld: ${field.name}, Mehrsprachig: ${field.multilingual}`);
          if (field.multilingual) {
              console.log('Verfügbare Sprachen:', field.languages);
          }
      });
  });
```

#### Erweiterte Metadaten speichern
```javascript
// Mehrsprachige Metadaten speichern
const metadata = {
    'title': 'Einfacher Titel',
    'med_title_lang': {
        'de': 'Deutscher Titel',
        'en': 'English Title'
    },
    'med_description_lang': {
        'de': 'Deutsche Beschreibung',
        'en': 'English Description'  
    }
};

fetch('/redaxo/index.php?rex-api-call=filepond_auto_metainfo&action=save_metadata', {
    method: 'POST',
    body: new FormData()
});
```

### Troubleshooting

#### Felder werden nicht erkannt
1. Prüfe ob MetaInfo Lang Fields AddOn installiert und aktiviert ist
2. Stelle sicher, dass die Felder mit `lang_*` Feldtypen angelegt sind
3. Cache leeren: REDAXO > System > Cache löschen

#### Mehrsprachige Eingabe funktioniert nicht
1. Prüfe die Browser-Konsole auf JavaScript-Fehler
2. Stelle sicher, dass mehrere Sprachen in REDAXO konfiguriert sind
3. Prüfe die AJAX-Antworten der MetaInfo-API

#### Daten werden nicht gespeichert
1. Prüfe die Berechtigung für Medienpool-Zugriff
2. Kontrolliere die API-Token-Konfiguration
3. Prüfe die PHP-Error-Logs

### Compatibility

Das AddOn ist kompatibel mit:
- **MetaInfo Lang Fields v1.0+** von Friends of REDAXO
- **REDAXO 5.15+** mit konfigurierten Sprachen
- Alle gängigen FilePond-Konfigurationen

Die Mehrsprachigkeit ist vollständig rückwärtskompatibel - bestehende einsprachige Installationen funktionieren weiterhin ohne Änderungen.

## Metadaten

Folgende Metadaten können für jede hochgeladene Datei erfasst werden:

**Standard-Metadaten (einsprachig):**
1.  **Titel:** Wird im Medienpool zur Verwaltung der Datei verwendet (gespeichert in `title`).
2.  **Alt-Text:** Beschreibt den Bildinhalt für Screenreader (wichtig für Barrierefreiheit und SEO), gespeichert in `med_alt`.
3.  **Copyright:** Information zu Bildrechten und Urhebern, gespeichert in `med_copyright`.
4.  **Beschreibung:** Ausführlichere Beschreibung des Medieninhalts, gespeichert in `med_description`.

**Mehrsprachige Metadaten (mit MetaInfo Lang Fields):**
1.  **Mehrsprachiger Titel:** Titel in allen konfigurierten Sprachen, gespeichert in `med_title_lang` (Pflichtfeld).
2.  **Mehrsprachige Beschreibung:** Beschreibung in allen Sprachen, gespeichert in `med_description_lang`.
3.  **Mehrsprachige Keywords:** Schlüsselwörter pro Sprache, gespeichert in `med_keywords_lang`.

**Konfigurierbare Pflichtfelder:**
- `title` (einfacher Titel): Optional als Pflichtfeld konfigurierbar über Settings oder data-Attribut
- `med_title_lang` (mehrsprachiger Titel): Immer Pflichtfeld, nicht deaktivierbar
- `med_alt` (Alt-Text): Pflichtfeld bei Bildern, kann pro Sprache als "dekorativ" markiert werden

> **Hinweis:** Die Felder werden automatisch in der Datenbank angelegt, falls sie noch nicht existieren. Bei mehrsprachigen Feldern muss das MetaInfo Lang Fields AddOn installiert sein.

## Events und JavaScript-API

Wichtige JavaScript-Events für eigene Entwicklungen:

```js
// Upload erfolgreich
pond.on('processfile', (error, file) => {
    if(!error) {
        console.log('Datei hochgeladen:', file.serverId);
    }
});

// Datei gelöscht
pond.on('removefile', (error, file) => {
    console.log('Datei entfernt:', file.serverId);
});

// Chunk-Upload-Fortschritt (nur wenn Chunk-Upload aktiviert ist)
pond.on('processfileProgress', (file, progress) => {
    console.log(`Chunk-Fortschritt für ${file.filename}: ${Math.floor(progress * 100)}%`);
});
```

## Wartung

Als Administrator können Sie temporäre Dateien und Chunks über die Einstellungsseite bereinigen. Dies ist besonders nützlich, wenn:

- Uploads abgebrochen wurden
- Temporäre Dateien nicht automatisch gelöscht wurden
- Sie Speicherplatz freigeben möchten

Die Wartungsfunktion löscht:
- Alte Chunk-Verzeichnisse (älter als 24 Stunden)
- Temporäre Metadaten-Dateien
- Nicht mehr benötigte temporäre Dateien

## Bulk Resize - Bildgrößen optimieren

Mit dem Bulk Resize Feature können bestehende Bilder im Medienpool nachträglich verkleinert werden. Dies ist besonders nützlich, wenn:

- Große Bilder ohne Optimierung hochgeladen wurden
- Die maximale Bildgröße nachträglich angepasst werden soll
- Speicherplatz eingespart werden soll

### Zugriff

Die Bulk Resize Funktion ist verfügbar unter **FilePond Uploader → Bulk Resize** für:
- Administratoren
- Nutzer mit der Berechtigung `filepond_uploader[bulk_resize]`

### Funktionsumfang

**Filter & Suche:**
- Filterung nach Dateiname (Teilsuche)
- Filterung nach Medienkategorie
- Einstellbare Zielgröße (max. Breite/Höhe in Pixel)
- Einstellbare Kompressionsqualität (10-100%)

**Verarbeitung:**
- Parallele Verarbeitung von bis zu 3 Bildern gleichzeitig
- Live-Fortschrittsanzeige mit Prozent-Balken
- Echtzeit-Statistiken (verarbeitet, erfolgreich, übersprungen, gespart)
- Verarbeitungsprotokoll mit Zeitstempeln
- Abbruch-Funktion jederzeit möglich

**Unterstützte Formate:**
- Standard (GD): JPG, JPEG, PNG, GIF, WebP
- Mit ImageMagick zusätzlich: PSD, BMP
- Automatische EXIF-Orientierungskorrektur

**Bildverarbeitung:**
- Proportionale Skalierung (Seitenverhältnis bleibt erhalten)
- Qualitätserhaltende Kompression
- Automatische Aktualisierung der Datenbankeinträge
- Media-Cache wird automatisch geleert

### Beispiel-Workflow

1. Öffne **FilePond Uploader → Bulk Resize**
2. Setze die gewünschte Maximalgröße (z.B. 2100px)
3. Optional: Filtere nach Kategorie oder Dateiname
4. Klicke auf **Bilder suchen**
5. Wähle die zu verarbeitenden Bilder aus (oder "Alle auswählen")
6. Klicke auf **Resize starten**
7. Warte auf die Fertigstellung und prüfe die Ersparnis

## Alt-Text-Checker für Barrierefreiheit

Der Alt-Text-Checker hilft dabei, die Barrierefreiheit der Website zu verbessern, indem er alle Bilder ohne Alt-Text auflistet und eine schnelle Bearbeitung ermöglicht.

### Zugriff

Die Funktion ist als Unterseite im **Medienpool → Alt-Text-Checker** verfügbar für:
- Administratoren
- Nutzer mit der Berechtigung `filepond_uploader[alt_checker]`

### Funktionsumfang

**Statistik-Dashboard:**
- Gesamtanzahl der Bilder im Medienpool
- Anzahl mit/ohne Alt-Text
- Prozentuale Vollständigkeit mit Fortschrittsbalken

**Inline-Bearbeitung:**
- Alt-Text direkt in der Tabelle eingeben
- Enter-Taste zum schnellen Speichern
- Tab-Taste zur Navigation zum nächsten Bild
- Visuelle Rückmeldung bei Änderungen (gelb) und Speicherung (grün)

**Akkordeon-Vorschau:**
- Klick auf Pfeil/Thumbnail öffnet große Bildansicht
- Erleichtert das Beschreiben komplexer Bilder
- Link zum Öffnen im Medienpool für weitere Details

**Dekorative Bilder:**
- Button zum Markieren als "dekorativ" (Auge-Symbol)
- Für Bilder die keinen Alt-Text benötigen (z.B. reine Dekoration)
- Werden in einer Negativ-Liste gespeichert (`med_alt` bleibt leer)
- WCAG 2.1 konform: Dekorative Bilder dürfen leeren alt-Text haben
- Zählen in der Statistik als "erledigt"

**Filter & Suche:**
- Filterung nach Dateiname
- Filterung nach Medienkategorie
- Vorschau-Thumbnails mit Klick zum Medienpool

**Workflow:**
1. Öffne **Medienpool → Alt-Text-Checker**
2. Prüfe die Statistik - wie viele Bilder fehlen?
3. Optional: Filtere nach Kategorie
4. Klicke auf den Pfeil um die große Vorschau zu öffnen
5. Gib Alt-Texte ein (Tab zum Wechseln, Enter zum Speichern)
6. Oder: Markiere dekorative Bilder mit dem Auge-Button
7. Oder: Nutze den AI-Button (Zauberstab) für automatische Generierung
8. Oder: Bearbeite mehrere und klicke "Alle speichern"

> **Hinweis:** Das MetaInfo-Feld `med_alt` muss existieren. Falls nicht, wird ein Hinweis mit Link zur MetaInfo-Konfiguration angezeigt.
>
> **Tipp:** Die Liste der dekorativen Bilder wird in der Addon-Konfiguration gespeichert und kann bei Bedarf zurückgesetzt werden.

## AI Alt-Text-Generierung

Das AddOn unterstützt die automatische Generierung von Alt-Texten mittels KI. Zwei Provider stehen zur Auswahl:

### Google Gemini (empfohlen)

Google Gemini bietet exzellente Bildanalyse mit hervorragender Mehrsprachigkeit.

**Kostenlos:** Bis zu 1500 Requests pro Tag (Free Tier)

**Einrichtung:**
1. Gehe zu [Google AI Studio](https://aistudio.google.com/apikey)
2. Erstelle einen neuen API-Key (kostenlos mit Google-Account)
3. In REDAXO: **FilePond Uploader → Einstellungen → AI Alt-Text**
4. Wähle Provider: **Google Gemini**
5. Füge den API-Key ein
6. Wähle ein Modell (empfohlen: **Gemini 2.5 Flash**)
7. Aktiviere "AI-Generierung aktivieren"
8. Teste die Verbindung

**Verfügbare Modelle:**
| Modell | Kosten | Beschreibung |
|--------|--------|--------------|
| Gemini 2.5 Flash | Kostenlos | Beste Balance aus Qualität und Geschwindigkeit ⭐ |
| Gemini 2.5 Flash-Lite | Kostenlos | Schneller, etwas kürzer |
| Gemini 2.0 Flash | Kostenlos | Älteres Modell |
| Gemini 2.5 Pro | Bezahlt | Höchste Qualität |

**Rate Limits (Free Tier):**
- 20 Requests pro Minute (RPM)
- 1500 Requests pro Tag (RPD)
- 250.000 Tokens pro Minute
- Reset: Täglich um 9:00 Uhr MEZ

### Cloudflare Workers AI

Cloudflare bietet eine Alternative mit großzügigem kostenlosen Kontingent.

**Kostenlos:** 10.000 Neurons pro Tag (~100-200 Bildanalysen)

**Einrichtung:**
1. Erstelle einen [Cloudflare-Account](https://dash.cloudflare.com/) (kostenlos)
2. Gehe zu **Profil → API Tokens → Create Token**
3. Wähle **Custom Token → Get Started**
4. Konfiguriere:
   - **Token name:** `FilePond AI` (oder beliebig)
   - **Permissions:** Account → Workers AI → Read
   - **Account Resources:** Deinen Account auswählen
5. Klicke **Continue to summary → Create Token**
6. Kopiere den Token (wird nur einmal angezeigt!)
7. Hole deine **Account ID:**
   - Gehe zu [Cloudflare Dashboard](https://dash.cloudflare.com/)
   - Wähle **Workers & Pages**
   - Account ID steht in der rechten Sidebar
8. In REDAXO: **FilePond Uploader → Einstellungen → AI Alt-Text**
9. Wähle Provider: **Cloudflare Workers AI**
10. Füge Token und Account ID ein
11. Aktiviere "AI-Generierung aktivieren"
12. Teste die Verbindung

**Hinweis:** Das LLaVA-Modell von Cloudflare ist etwas kleiner als Gemini und liefert kürzere Beschreibungen. Bei Screenshots mit Text kann es zu Fehlern kommen.

### Nutzung im Alt-Text-Checker

Nach der Einrichtung erscheint im Alt-Text-Checker:
- **Zauberstab-Button** (✨) bei jedem Bild für einzelne Generierung
- **"AI für alle generieren"** Button für Bulk-Generierung aller leeren Alt-Texte
- Bei mehrsprachigen Feldern werden alle Sprachen automatisch generiert
- Token-Verbrauch wird nach jeder Generierung angezeigt (nur Gemini)

**Nicht unterstützt:** SVG-Dateien (können von der AI nicht analysiert werden)

## Hinweise

*   Die maximale Dateigröße wird serverseitig überprüft.
*   Das Copyright-Feld und die Beschreibung sind optional, Titel und Alt-Text sind Pflicht.
*   Uploads landen automatisch im Medienpool.
*   Metadaten werden im Medienpool gespeichert.
*   Videos können direkt im Upload-Dialog betrachtet werden.
*   Bilder werden automatisch auf die maximale Größe optimiert.
*   Chunk-Upload funktioniert auch bei langsameren Internetverbindungen zuverlässig.

## Verzögerter Upload-Modus

Der verzögerte Upload-Modus trennt den Prozess der Dateiauswahl vom eigentlichen Upload-Vorgang. Dateien werden erst hochgeladen, wenn der Benutzer auf den "Dateien hochladen"-Button klickt.

### Vorteile

- **Bessere Kontrolle:** Vorschau und Sichtung vor dem Upload
- **Datei-Management:** Löschen unerwünschter Dateien vor dem Upload
- **Neuordnung:** Sortieren der Dateien vor dem Upload
- **Effizientes Arbeiten:** Besonders nützlich für große Dateimengen

### Aktivierung im Backend

Der verzögerte Upload-Modus kann global in den FilePond-Einstellungen aktiviert werden:

1. Navigiere zu **REDAXO > AddOns > FilePond Uploader > Einstellungen**
2. Aktiviere die Option **"Verzögerter Upload-Modus"**
3. Speichere die Einstellungen

### Aktivierung in YForm-Feldern

Für YForm-Felder kann der verzögerte Upload-Modus individuell aktiviert werden:

```php
$yform->setValueField('filepond', [
    'name' => 'bilder',
    'label' => 'Bildergalerie',
    'allowed_max_files' => 5,
    'allowed_types' => 'image/*',
    'delayed_upload' => 1  // Verzögerter Upload aktivieren
]);
```

### Aktivierung via HTML-Attribut

Bei direkter Einbindung kann der verzögerte Upload-Modus über ein Attribut aktiviert werden:

```html
<input
    type="hidden"
    name="REX_INPUT_VALUE[1]"
    value="REX_VALUE[1]"
    data-widget="filepond"
    data-filepond-cat="1"
    data-filepond-delayed-upload="true"
>
```

**Hinweis:** Das Attribut `data-filepond-delayed-type` steuert den Upload-Modus:
- `1`: Upload-Button wird angezeigt (Standard wenn `data-filepond-delayed-upload="true"`)
- `2`: Upload erfolgt beim Formular-Submit (muss explizit gesetzt werden)

Das Attribut `data-filepond-delayed-type` muss nur gesetzt werden, wenn der Submit-Modus (`2`) gewünscht ist. Beim Aktivieren von `data-filepond-delayed-upload="true"` wird automatisch der Upload-Button-Modus (`1`) verwendet.

### Anpassung des Upload-Buttons

Der Upload-Button wird automatisch unter dem FilePond-Element angezeigt, wenn der verzögerte Upload-Modus aktiviert ist. Die Optik kann über CSS-Variablen angepasst werden:

```css
:root {
    --filepond-upload-btn-color: #4285f4;         /* Hintergrundfarbe */
    --filepond-upload-btn-hover-color: #3367d6;   /* Hover-Farbe */
    --filepond-upload-btn-text-color: #fff;       /* Textfarbe */
    --filepond-upload-btn-border-radius: 4px;     /* Eckenradius */
    --filepond-upload-btn-padding: 10px 16px;     /* Innenabstand */
    --filepond-upload-btn-font-size: 14px;        /* Schriftgröße */
    --filepond-upload-btn-font-weight: 500;       /* Schriftstärke */
}
```

> **Hinweis:** Bei aktiviertem verzögerten Upload-Modus können Benutzer die Dateien vor dem Upload neu anordnen, löschen und in Ruhe auswählen. Die tatsächliche Upload-Verarbeitung beginnt erst nach dem Klick auf den Button.

## Credits

*   **KLXM Crossmedia GmbH:** [klxm.de](https://klxm.de) - Die Werbeagentur vom Niederrhein
*   **Entwickler:** [Thomas Skerbis](https://github.com/skerbis)
*   **Vendor:** FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
*   **Lizenz:** MIT

We believe in giving back to the community. You'll find various open source projects in our repositories that we've developed and maintain. We're also proud contributors to the REDAXO CMS ecosystem, actively participating in its development and community.

## Support

*   **GitHub Issues:** Für Fehlermeldungen und Feature-Anfragen.
*   **REDAXO Slack:** Für Community-Support und Diskussionen.
*   **[www.redaxo.org](https://www.redaxo.org):** Offizielle REDAXO-Website.
*   **[AddOn Homepage](https://github.com/KLXM/filepond_uploader/tree/main):** Für aktuelle Informationen und Updates.
