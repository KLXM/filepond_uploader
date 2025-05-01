# FilePond Uploader für REDAXO

**Ein moderner Datei-Uploader für REDAXO mit Chunk-Upload und nahtloser Medienpool-Integration.**

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/dino.png?raw=true)

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Dieser Uploader wurde mit Blick auf Benutzerfreundlichkeit (UX), Barrierefreiheit und rechtliche Anforderungen entwickelt. Er bietet eine moderne Drag-and-Drop-Oberfläche und integriert sich nahtlos in den REDAXO-Medienpool.

## Hauptmerkmale

*   **Chunk-Upload als Kernfeature:**
    *   Zuverlässiges Hochladen großer Dateien in kleinen Teilen (Chunks)
    *   Einstellbare Chunk-Größe (Standard: 5MB)
    *   Fortschrittsanzeige für einzelne Chunks und die Gesamtdatei
    *   Automatisches Zusammenführen der Chunks nach dem Upload

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
    'category' => 1
]);
```

> **Hinweis:** Das `filepond`-Value-Feld in YForm ist eine bequeme Möglichkeit, den Uploader zu verwenden. Alternativ kann ein normales Input-Feld mit den notwendigen `data`-Attributen versehen werden. In diesem Fall entfällt die automatische Löschung nicht verwendeter Medien.

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

## Alternative Stile für den Upload-Button

Wenn du kein Bootstrap oder FontAwesome verwendest, kannst du diese alternativen CSS-Stile für den vom Widget bereitgestellten Upload-Button verwenden. Füge diese Stile deinem CSS hinzu, um das Erscheinungsbild des Buttons anzupassen:

### Einfacher, flacher Button mit SVG-Icon

```css
/* Einfacher, flacher Button ohne Bootstrap */
.filepond-upload-btn {
    background-color: #4285f4;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 10px 16px;
    font-size: 14px;
    cursor: pointer;
    box-shadow: none;
    display: inline-flex;
    align-items: center;
    transition: background-color 0.3s;
}

.filepond-upload-btn:hover {
    background-color: #3367d6;
}

/* Entferne das FontAwesome-Icon und ersetze es durch ein SVG */
.filepond-upload-btn .fa-upload {
    display: none;
}

.filepond-upload-btn::before {
    content: "";
    display: inline-block;
    width: 18px;
    height: 18px;
    margin-right: 8px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z'/%3E%3C/svg%3E");
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}
```

### Minimalistischer Button mit Pfeil

```css
/* Minimalistischer Button-Stil */
.filepond-upload-btn {
    background-color: transparent;
    color: #2196F3;
    border: 2px solid #2196F3;
    border-radius: 3px;
    padding: 8px 16px;
    font-size: 14px;
    cursor: pointer;
    position: relative;
    transition: all 0.3s;
    box-shadow: none;
}

.filepond-upload-btn:hover {
    background-color: rgba(33, 150, 243, 0.1);
}

/* Entferne das FontAwesome-Icon und ersetze es durch einen Pfeil */
.filepond-upload-btn .fa-upload {
    display: none;
}

.filepond-upload-btn::after {
    content: "↑";
    margin-left: 8px;
    font-size: 18px;
    line-height: 1;
}
```

### Material Design inspirierter Button

```css
/* Material Design Button */
.filepond-upload-btn {
    background-color: #2196F3;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    transition: background-color 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}

.filepond-upload-btn:hover {
    background-color: #0d8aee;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

/* Entferne das FontAwesome-Icon und ersetze es durch ein SVG */
.filepond-upload-btn .fa-upload {
    display: none;
}

.filepond-upload-btn::before {
    content: "";
    display: inline-block;
    width: 18px;
    height: 18px;
    margin-right: 8px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z'/%3E%3C/svg%3E");
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

/* Ripple-Effekt für Material Design */
.filepond-upload-btn::after {
    content: "";
    display: block;
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    pointer-events: none;
    background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
    background-repeat: no-repeat;
    background-position: 50%;
    transform: scale(10, 10);
    opacity: 0;
    transition: transform .5s, opacity 1s;
}

.filepond-upload-btn:active::after {
    transform: scale(0, 0);
    opacity: .3;
    transition: 0s;
}
```

### Schlichter Text-Button mit Unterstrich

```css
/* Schlichter Text-Button mit Unterstrich */
.filepond-upload-btn {
    background-color: transparent;
    color: #0366d6;
    border: none;
    border-radius: 0;
    padding: 8px 0;
    margin-top: 8px;
    font-size: 14px;
    cursor: pointer;
    position: relative;
    transition: all 0.3s;
    box-shadow: none;
    border-bottom: 1px solid currentColor;
}

.filepond-upload-btn:hover {
    color: #0076ff;
}

/* Entfernen des FontAwesome-Icons und ersetzen durch ein einfaches Plus-Zeichen */
.filepond-upload-btn .fa-upload {
    display: none;
}

.filepond-upload-btn::before {
    content: "+";
    margin-right: 6px;
    font-size: 16px;
}
```

Du kannst einen dieser Stile in deine CSS-Datei einfügen oder direkt im HTML-Head-Bereich platzieren. Die Stile überschreiben das Bootstrap-Design und das FontAwesome-Icon durch eigene Gestaltung, ohne dass du Änderungen am HTML-Code vornehmen musst.

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
