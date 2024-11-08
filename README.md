# filepond Uploader für REDAXO

Ein modernes Upload-System für REDAXO basierend auf dem [FilePond](https://pqina.nl/filepond/) Framework. Der Uploader wurde mit Fokus auf Barrierefreiheit, UX und rechtliche Anforderungen entwickelt.


🚨 Achtung noch nicht für den produktiven Einsatz. Hier wird noch debugt und optimiert. 

## Features

- 🎭 Moderne Drag & Drop Oberfläche 
- 👁️ Live-Vorschau während des Uploads
- ♿️ Barrierefreiheit: Erzwungene Alt-Texte / / Meta wird angelegt, wenn nicht vorhanden
- ⚖️ Rechtssicherheit Copyright-Abfrage / Meta wird angelegt, wenn nicht vorhanden
- 🌍 Mehrsprachig (DE/EN)
- 📦 Nahtlose Medienpool-Integration
- 📋 YForm-Value mit automatischer Löschung der Medien, wenn nicht verwendet
- ⚡ Asynchrone Uploads
- 📱 Responsive Design
- 🛡️ Validierung von Dateitypen und -größen

## Installation

1. Im REDAXO Installer das AddOn "filepond_uploader" installieren
2. Im Backend unter "AddOns" aktivieren
3. Fertig!

## Quick Start

### Als YForm Feldtyp

Im Table Manager ein neues Feld anlegen:

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
    data-filepond-types="mime/type, .extension"
    data-filepond-maxsize="10"
    data-filepond-lang="de"
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

## Konfiguration

### Attribute

| Attribut | Beschreibung | Standard |
|----------|--------------|-----------|
| data-filepond-cat | Medienpool Kategorie ID | 1 |
| data-filepond-maxfiles | Max. Anzahl Dateien | 10 |
| data-filepond-types | Erlaubte Dateitypen | image/* |
| data-filepond-maxsize | Max. Dateigröße (MB) | 10 |
| data-filepond-lang | Sprache (de/en) | en |

### Erlaubte Dateitypen

#### Grunsätzliche Syntax

`data-filepond-types="mime/type, .extension"`

- Bilder: `image/*`
- Videos: `video/*` 
- PDFs: `.pdf`
- Dokumente: `.doc,.docx,.txt`
- Mehrere: `image/*,video/*,.pdf`

```html
<!-- Alle Bildtypen -->
data-filepond-types="image/*"

<!-- Spezifische Bildformate -->
data-filepond-types="image/jpeg, image/png, image/gif, image/webp"

<!-- Mit Dateiendungen -->
data-filepond-types=".jpg, .jpeg, .png, .gif, .webp"
```

```html
<!-- Office Dokumente -->
data-filepond-types=".doc, .docx, .xls, .xlsx, .ppt, .pptx"

<!-- PDF -->
data-filepond-types="application/pdf, .pdf"

<!-- Text -->
data-filepond-types="text/plain, .txt"
```


```html
<!-- Office Dokumente -->
data-filepond-types=".doc, .docx, .xls, .xlsx, .ppt, .pptx"

<!-- PDF -->
data-filepond-types="application/pdf, .pdf"

<!-- Text -->
data-filepond-types="text/plain, .txt"
```

```html
<!-- Bilder und PDFs -->
data-filepond-types="image/*, application/pdf"

<!-- Nur bestimmte Bildtypen und Dokumente -->
data-filepond-types="image/jpeg, image/png, .pdf, .doc, .docx"

<!-- Medienformate -->
data-filepond-types="image/*, video/*, audio/*"
```


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

## Tipps & Tricks

- Maximale Dateigröße wird auch serverseitig geprüft
- Copyright-Feld ist optional, Title und Alt-Text Pflicht
- Uploads landen automatisch im Medienpool
- Metadaten werden im Medienpool gespeichert
- Videos werden direkt im Upload-Dialog previewt

## Credits

- KLXM Crossmedia GmbH - [klxm.de](https://klxm.de)
- FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
- Lizenz: MIT

## Support

- GitHub Issues
- REDAXO Slack
- [www.redaxo.org](https://www.redaxo.org)

