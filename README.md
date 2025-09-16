# FilePond Uploader für REDAXO

**Ein moderner Datei-Uploader für REDAXO mit Chunk-Upload und nahtloser Medienpool-Integration.**

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Dieser Uploader wurde mit Blick auf Benutzerfreundlichkeit (UX), Barrierefreiheit und rechtliche Anforderungen entwickelt. Er bietet eine moderne Drag-and-Drop-Oberfläche und integriert sich nahtlos in den REDAXO-Medienpool.

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
    *   Verkleinerung großer Bilder auf konfigurierbare Maximalgröße
    *   Einstellbare Kompressionsqualität für JPEG/PNG/WebP
    *   Beibehaltung der Originaldimensionen für GIF-Dateien
    *   Optionale Erstellung von Thumbnails

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

*   **Wartungswerkzeuge:**
    *   Einfache Bereinigung temporärer Dateien und Chunks
    *   Protokollierung aller Upload-Vorgänge
    *   Admin-Interface zur Systemwartung

## Installation

1.  **AddOn installieren:** Installiere das AddOn "filepond_uploader" über den REDAXO-Installer.
2.  **AddOn aktivieren:** Aktiviere das AddOn im Backend unter "AddOns".
3.  **Konfigurieren:** Passe die Einstellungen unter "FilePond Uploader > Einstellungen" an deine Bedürfnisse an.
4.  **Fertig:** Der Uploader ist nun einsatzbereit!

## Schnellstart

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
>
```

#### Ausgabe

```php
<?php
$files = explode(',', 'REX_VALUE[1]');
foreach($files as $file) {
    if($media = rex_media::get($file)) {
        echo '<img
            src="'.$media->getUrl().'"
            alt="'.$media->getValue('med_alt').'"
            title="'.$media->getValue('title').'"
        >';
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
| `data-filepond-types`        | Erlaubte Dateitypen                     | `image/*`    |
| `data-filepond-maxfiles`     | Maximale Anzahl an Dateien              | `30`         |
| `data-filepond-maxsize`      | Maximale Dateigröße in MB               | `10`         |
| `data-filepond-lang`         | Sprache (`de_de` / `en_gb`)             | `de_de`      |
| `data-filepond-skip-meta`    | Meta-Eingabe deaktivieren               | `false`      |
| `data-filepond-chunk-enabled`| Chunk-Upload aktivieren                 | `true`       |
| `data-filepond-chunk-size`   | Chunk-Größe in MB                       | `5`          |

### Erlaubte Dateitypen (MIME-Types)

#### Grundlegende Syntax

`data-filepond-types="mime/type"`

*   **Bilder:** `image/*`
*   **Videos:** `video/*`
*   **PDFs:** `application/pdf`
*   **Medienformate (Bilder, Videos, Audio):** `image/*, video/*, audio/*`

**Beispiele:**

```html
<!-- Alle Bildtypen -->
data-filepond-types="image/*"

<!-- Bilder und PDFs -->
data-filepond-types="image/*, application/pdf"

<!-- Microsoft Office -->
data-filepond-types="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation"
```

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

### Modulbeispiel

```php
<?php
rex_login::startSession();
// Session-Token für API-Zugriff setzen (für Frontend)
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));

// Optional: Meta-Eingabe deaktivieren
rex_set_session('filepond_no_meta', true);

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

Bilder werden automatisch optimiert, wenn sie eine konfigurierte maximale Pixelgröße überschreiten:

*   Große Bilder werden proportional verkleinert.
*   Die Qualität ist konfigurierbar (10-100).
*   GIF-Dateien werden nicht verändert.
*   Die Originaldatei wird durch die optimierte Version ersetzt.

Standardmäßig ist die maximale Größe 1200 Pixel (Breite oder Höhe). Dieser Wert und die Kompressionsqualität können in den Einstellungen angepasst werden.

## Metadaten

Folgende Metadaten können für jede hochgeladene Datei erfasst werden:

1.  **Titel:** Wird im Medienpool zur Verwaltung der Datei verwendet.
2.  **Alt-Text:** Beschreibt den Bildinhalt für Screenreader (wichtig für Barrierefreiheit und SEO), gespeichert in `med_alt`.
3.  **Copyright:** Information zu Bildrechten und Urhebern, gespeichert in `med_copyright`.
4.  **Beschreibung:** Ausführlichere Beschreibung des Medieninhalts, gespeichert in `med_description`.

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

*   **KLXM Crossmedia GmbH:** [klxm.de](https://klxm.de)
*   **Entwickler:** [Thomas Skerbis](https://github.com/skerbis)
*   **Vendor:** FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
*   **Lizenz:** MIT

## Support

*   **GitHub Issues:** Für Fehlermeldungen und Feature-Anfragen.
*   **REDAXO Slack:** Für Community-Support und Diskussionen.
*   **[www.redaxo.org](https://www.redaxo.org):** Offizielle REDAXO-Website.
*   **[AddOn Homepage](https://github.com/KLXM/filepond_uploader/tree/main):** Für aktuelle Informationen und Updates.
