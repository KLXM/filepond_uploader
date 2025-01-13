# filepond Uploader für REDAXO

**Ein moderner Datei-Uploader für REDAXO, der auf dem FilePond Framework basiert.**

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Dieser Uploader wurde mit Blick auf Benutzerfreundlichkeit (UX), Barrierefreiheit und rechtliche Anforderungen entwickelt. Er bietet eine moderne Drag-and-Drop-Oberfläche und integriert sich nahtlos in den REDAXO-Medienpool.

## Hauptmerkmale

*   **Moderne Oberfläche:**
    *   Drag & Drop für einfaches Hochladen von Dateien.
    *   Live-Vorschau der Bilder während des Uploads.
    *   Responsives Design für alle Bildschirmgrößen.

*   **Barrierefreiheit:**
    *   Erzwingt das Setzen von Alt-Texten für Bilder.
    *   Legt automatisch ein Metafeld an, falls es noch nicht existiert.

*   **Rechtliche Sicherheit:**
    *   Optionale Abfrage des Copyrights für Bilder.

*   **Mehrsprachigkeit:**
    *   Verfügbar in Deutsch (DE) und Englisch (EN).

*   **Nahtlose Integration:**
    *   Direkte Speicherung von Dateien im REDAXO-Medienpool.
    *   YForm-Value-Feld mit automatischer Löschung nicht verwendeter Medien.
    *   Asynchrone Uploads für eine flüssige Benutzererfahrung.

*   **Validierung und Sicherheit:**
    *   Überprüfung von Dateitypen und -größen.
    *   Sichere API-Token-basierte Authentifizierung (auch mit YCOM).

*   **Automatische Bildoptimierung:**
    *   Verkleinerung großer Bilder (außer GIFs) zur Optimierung der Webseitenperformance.

## Installation

1.  **AddOn installieren:** Gehe im REDAXO-Installer zum AddOn-Bereich und installiere das AddOn "filepond\_uploader".
2.  **AddOn aktivieren:** Aktiviere das AddOn im Backend unter "AddOns".
3.  **Fertig:** Der Uploader ist nun einsatzbereit!

## Schnellstart

### Verwendung als YForm-Feldtyp

```php
$yform->setValueField('filepond', [
    'name' => 'bilder',
    'label' => 'Bildergalerie',
    'max_files' => 5,
    'allowed_types' => 'image/*',
    'max_size' => 10,
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

### Integration mit Medialisten

```html
<input
    type="hidden"
    name="REX_INPUT_MEDIALIST[1]"
    value="REX_MEDIALIST[1]"
    data-widget="filepond"
    ...
>
```

## Helper-Klasse

Das AddOn enthält eine Helper-Klasse, die das Einbinden von CSS- und JavaScript-Dateien vereinfacht.

### Basisverwendung

```php
// Im Template oder Modul
<?php
echo filepond_helper::getScripts();
echo filepond_helper::getStyles();
?>
```

### Methoden

#### `getScripts()`

Gibt alle benötigten JavaScript-Dateien zurück:

```php
/**
 * Gibt die JavaScript-Dateien zurück
 * @return string HTML-String im Frontend, leerer String im Backend (nach dem Hinzufügen der Scripte via rex_view)
 */
public static function getScripts(): string
```

**Enthaltene Dateien:**

*   Validierungs-Plugins (Dateityp und -größe)
*   Image Preview Plugin
*   FilePond Core
*   Modal- und Widget-Skripte

#### `getStyles()`

Gibt alle benötigten CSS-Dateien zurück:

```php
/**
 * Gibt die CSS-Dateien zurück
 * @return string HTML-String im Frontend, leerer String im Backend (nach dem Hinzufügen der Stile via rex_view)
 */
public static function getStyles(): string
```

**Enthaltene Dateien:**

*   FilePond Core CSS
*   Image Preview Plugin CSS
*   Widget-Stile

### Verwendung im Frontend

```php
// In einem Template
<!DOCTYPE html>
<html>
<head>
    <?= filepond_helper::getStyles() ?>
</head>
<body>
    <!-- Content -->
    <?= filepond_helper::getScripts() ?>
</body>
</html>
```

## Konfiguration

### Data-Attribute

Folgende `data`-Attribute können zur Konfiguration verwendet werden:

| Attribut                | Beschreibung                            | Standardwert |
| ----------------------- | --------------------------------------- | ------------ |
| `data-filepond-cat`     | Medienpool Kategorie ID                | `0`          |
| `data-filepond-types`   | Erlaubte Dateitypen                    | `image/*`    |
| `data-filepond-maxfiles` | Maximale Anzahl an Dateien             | `30`         |
| `data-filepond-maxsize` | Maximale Dateigröße in MB              | `10`         |
| `data-filepond-lang`    | Sprache (`de_de` / `en_gb`)            | `de_de`      |
| `data-filepond-skip-meta` | Meta-Eingabe deaktivieren | `false` |

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

<!-- OpenOffice/LibreOffice -->
data-filepond-types="application/vnd.oasis.opendocument.text, application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation"

<!-- Office und PDF kombiniert -->
data-filepond-types="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation, application/vnd.oasis.opendocument.text, application/vnd.oasis.opendocument.spreadsheet, application/vnd.oasis.opendocument.presentation, application/pdf"
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
*   Die Qualität bleibt erhalten.
*   GIF-Dateien werden nicht verändert.
*   Die Originaldatei wird durch die optimierte Version ersetzt.

Standardmäßig ist die maximale Größe 1200 Pixel (Breite oder Höhe). Dieser Wert kann in den Einstellungen oder via dem `data-filepond-maxpixels` Attribut angepasst werden.

## Metadaten

Folgende Metadaten müssen für jede hochgeladene Datei erfasst werden:

1.  **Titel:** Wird im Medienpool zur Verwaltung der Datei verwendet.
2.  **Alt-Text:** Beschreibt den Bildinhalt für Screenreader (wichtig für Barrierefreiheit und SEO), gespeichert in `med_alt`.
3.  **Copyright:** Information zu Bildrechten und Urhebern, gespeichert in `med_copyright` (optional).

## Events

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
```

## Assets aktualisieren

```cli
npm install
npm run build
```

## Hinweise

*   Die maximale Dateigröße wird auch serverseitig überprüft.
*   Das Copyright-Feld ist optional, Titel und Alt-Text sind Pflicht.
*   ALT-Text ist verpflichtend
*   Uploads landen automatisch im Medienpool.
*   Metadaten werden im Medienpool gespeichert.
*   Videos können direkt im Upload-Dialog betrachtet werden.
*   Bilder werden automatisch auf die maximale Größe optimiert.

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
