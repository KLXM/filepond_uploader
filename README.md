# filepond Uploader für REDAXO

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screen1.png?raw=true)

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
- 🖼️ Automatische Bildoptimierung für große Bilder

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

### Es kann auch in ein Medialist-Value schreiben. 

```php
<input 
    type="hidden" 
    name="REX_INPUT_MEDIALIST[1]" 
    value="REX_MEDIALIST[1]" 
    data-widget="filepond"
…
>
```

## Konfiguration

### Attribute

| Attribut | Beschreibung | Standard |
|----------|--------------|-----------|
| data-filepond-cat | Medienpool Kategorie ID | 1 |
| data-filepond-maxfiles | Max. Anzahl Dateien | 10 |
| data-filepond-types | Erlaubte Dateitypen | image/* |
| data-filepond-maxsize | Max. Dateigröße (MB) | 10 |
| data-filepond-lang | Sprache (de/en) | de_de |
| data-filepond-maxpixels | Max. Bildgröße (Pixel) | 1200 |

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

<!-- Office und PDF -->
data-filepond-types="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/pdf"
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

- KLXM Crossmedia GmbH - [klxm.de](https://klxm.de)
- FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
- Lizenz: MIT

## Support

- GitHub Issues
- REDAXO Slack
- [www.redaxo.org](https://www.redaxo.org)
