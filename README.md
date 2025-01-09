# filepond Uploader für REDAXO

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Ein modernes Upload-System für REDAXO basierend auf dem [FilePond](https://pqina.nl/filepond/) Framework. Der Uploader wurde mit Fokus auf Barrierefreiheit, UX und rechtliche Anforderungen entwickelt.

## Features

- 🎭 Moderne Drag & Drop Oberfläche 
- 👁️ Live-Vorschau während des Uploads
- ♿️ Barrierefreiheit: Erzwungene Alt-Texte / / Metafeld wird angelegt, wenn nicht vorhanden
- ⚖️ Rechtssicherheit Copyright-Abfrage / optional
- 🌍 Mehrsprachig (DE/EN)
- 📦 Nahtlose Medienpool-Integration
- 📋 YForm-Value mit automatischer Löschung der Medien, wenn nicht verwendet
- ⚡ Asynchrone Uploads
- 📱 Responsive Design
- 🛡️ Validierung von Dateitypen und -größen
- 🔒 Abgesichert via API_Token und Benutzerprüfung, auch YCOM
- 🖼️ Automatische Bildverkleinerung für große Bilder (außer .gif)

## Installation

1. Im REDAXO Installer das AddOn "filepond_uploader" installieren
2. Im Backend unter "AddOns" aktivieren
3. Fertig!

## Quick Start

### Als YForm Feldtyp

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
> Das YForm-Value ist nur eine Möglichkeit in YForm, es kann auch ein normales Input per JSON mit Attributen ausgezeichnet werden, dadurch entfällt das automatische Löschen.  

### Als Modul

#### Eingabe
```php
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

### Medialist-Value Integration

```php
<input 
    type="hidden" 
    name="REX_INPUT_MEDIALIST[1]" 
    value="REX_MEDIALIST[1]" 
    data-widget="filepond"
    ...
>
```

## Helper Class

Das AddOn stellt eine Helper-Klasse bereit, die das Einbinden der benötigten Assets (JavaScript und CSS) im Frontend vereinfacht. 

### Basis-Verwendung

```php
// Im Template oder Modul
<?php
echo filepond_helper::getScripts();
echo filepond_helper::getStyles();
?>
```

### Methoden

#### getScripts()

Lädt alle benötigten JavaScript-Dateien:

```php
/**
 * Get JavaScript files
 * @return string Returns HTML string in frontend, empty string in backend after adding scripts via rex_view
 */
public static function getScripts(): string
```

Eingebundene Dateien:
- Validierungs-Plugins (Dateityp und -größe)
- Image Preview Plugin
- FilePond Core
- Modal und Widget Scripts

#### getStyles()

Lädt alle benötigten CSS-Dateien:

```php
/**
 * Get CSS files
 * @return string Returns HTML string in frontend, empty string in backend after adding styles via rex_view
 */
public static function getStyles(): string
```

Eingebundene Dateien:
- FilePond Core CSS
- Image Preview Plugin CSS
- Widget Styles

### Verwendung im Frontend

Im Frontend werden die Assets als HTML-String zurückgegeben:

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

### Attribute

| Attribut | Beschreibung | Standard |
|----------|--------------|-----------|
| data-filepond-cat | Medienpool Kategorie ID | 1 |
| data-filepond-maxfiles | Max. Anzahl Dateien | 10 |
| data-filepond-types | Erlaubte Dateitypen | image/'*',video/'*',application/pdf |
| data-filepond-maxsize | Max. Dateigröße (MB) | 10 |
| data-filepond-lang | Sprache (de/en) | de_de |

### Erlaubte Dateitypen

#### Grundsätzliche Syntax

`data-filepond-types="mime/type"`

- Bilder: `image/*`
- Videos: `video/*` 
- PDFs: `application/pdf`
- Medienformate: `image/*, video/*, audio/*`

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

## Session-Konfiguration für individuelle Änderungen 

> Hinweis: Bei Verwendung von Yform/Yorm vor Yform/YOrm ausführen. 

Im Frontend sollte die Session gestartet werden

```
rex_login::startSession();

```
Wenn die Werte nicht mehr gebraucht werden, sollten sie zurückgesetzt werden. 


### API-Token übergeben 
```php
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));

```
Hiermit kann der API-Token übergeben werden. Damit ist es möglich auch ußerhalb von YCOM im Frontend Datei-Uploads zu erlauben. 

### Meta-Abfrage deaktivieren

```php
rex_set_session('filepond_no_meta', true);
```
Hiermit lässt sich die Meta-Abfrage deaktivieren. Bool: true/false

### Modulbeispiel

```php
<?php
rex_login::startSession();
// Session-Token für API-Zugriff setzen (für Frontend)
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));

// Optional: Meta-Eingabe deaktivieren
rex_set_session('filepond_no_meta', true);

// Filepond Assets einbinden , besser im Template ablegen
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

## Intitialisierung im Frontend und Tipps

```js
document.addEventListener('DOMContentLoaded', function() {
  // Dieser Code wird ausgeführt, nachdem das HTML vollständig geladen wurde.
  initFilePond();
});

```

Falls das Panel nicht schön gestaltet dargestellt wird, hilft es diesen Stil anzupassen

Hier ein hässliches Beispiel: 

```
.filepond--panel-root {
    border: 1px solid var(--fp-border);
    background-color: #eedede;
    min-height: 150px;

}
```




## Bildoptimierung

Bilder werden automatisch optimiert, wenn sie die konfigurierte maximale Pixelgröße überschreiten:

- Große Bilder werden proportional verkleinert
- Die Qualität bleibt erhalten
- GIF-Dateien werden nicht verändert
- Die Originaldatei wird durch die optimierte Version ersetzt

Der Standardwert ist 1200 Pixel (Breite oder Höhe). Dies kann über die Einstellungen oder das data-filepond-maxpixels Attribut angepasst werden.

## Metadaten

Für jede Datei müssen folgende Metadaten erfasst werden:

### 1. Titel
- Dient der Verwaltung im Medienpool
- Hilft bei der Organisation

### 2. Alt-Text  
- Beschreibt den Inhalt für Screenreader
- Wichtig für Barrierefreiheit & SEO
- Wird in `med_alt` gespeichert

### 3. Copyright
- Erfasst Bildrechte und Urheber
- Rechtssichere Verwendung
- Wird in `med_copyright` gespeichert

## Events

Wichtige JavaScript Events für eigene Entwicklungen:

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

- Maximale Dateigröße wird auch serverseitig geprüft
- Copyright-Feld ist optional, Title und Alt-Text Pflicht
- ALT-Text ist und bleibt Pflicht. Wer es nicht will, darf einen PR liefern um es abschalten zu können.
- Uploads landen automatisch im Medienpool
- Metadaten werden im Medienpool gespeichert
- Videos werden direkt im Upload-Dialog previewt
- Bilder werden automatisch auf die konfigurierte Maximalgröße optimiert

## Credits

- KLXM Crossmedia GmbH: [klxm.de](https://klxm.de)
- Developed by: [Thomas Skerbis](https://github.com/skerbis)
- Vendor: FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
- Lizenz: MIT

## Support

- GitHub Issues
- REDAXO Slack
- [www.redaxo.org](https://www.redaxo.org)
- [AddOn Homepage](https://github.com/KLXM/filepond_uploader/tree/main)
