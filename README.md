# filepond Uploader für REDAXO

Ein modernes und barrierefreies Upload-System für REDAXO, basierend auf dem [FilePond](https://pqina.nl/filepond/) JavaScript Framework. Das AddOn wurde mit dem Fokus auf Zugänglichkeit und rechtliche Anforderungen entwickelt.

🚨 Achtung noch nicht für den produktiven Einsatz. Hier wird noch debugt und optimiert. 


## Features

- 🖼️ Moderne Drag & Drop Oberfläche
- 🔍 Live-Bildvorschau während des Uploads
- ♿️ Verpflichtende Metadaten für Barrierefreiheit
- ⚖️ Copyright-Management für rechtliche Sicherheit
- 🌐 Mehrsprachig (Deutsch/Englisch)
- 📁 Automatische Medienpool-Integration
- ⚡️ Asynchrone Uploads
- 🎨 Responsive Design
- 🛡️ Validierung von Dateitypen und -größen

## Metadaten und Barrierefreiheit

Das AddOn erzwingt die Eingabe von drei wichtigen Metadaten für jedes Bild:

1. **Titel**
   - Dient der strukturierten Verwaltung im Medienpool
   - Hilft bei der Orientierung und Organisation

2. **Alt-Text (Alternativtext)**
   - Essentiell für Screenreader und Barrierefreiheit
   - Beschreibt den Bildinhalt für sehbehinderte Menschen
   - Wichtig für SEO und Suchmaschinen-Optimierung

3. **Copyright**
   - Sichert die rechtliche Verwendung der Bilder
   - Dokumentiert Bildrechte und Urheber
   - Wichtig für die rechtssichere Verwendung

Ohne diese Metadaten wird der Upload abgebrochen. Dies stellt sicher, dass alle Medien von Anfang an barrierefrei und rechtlich korrekt eingebunden werden.

## Anwendung als Modul

### Eingabe

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
    data-filepond-lang="de"
>
```

### Ausgabe

```php
<?php
$files = explode(',', "REX_VALUE[1]");
foreach($files as $file) {
    $media = rex_media::get($file);
    if($media) {
        echo '<img 
            src="'.$media->getUrl().'" 
            alt="'.$media->getValue('med_alt').'"
            title="'.$media->getValue('title').'"
        >';
    }
}
?>
```

## Anwendung im YForm Table Manager

Der filepondUploader steht im Table Manager als eigener Feldtyp zur Verfügung. Einfach ein neues Feld anlegen und als Typ "filepondUploader" auswählen.

Folgende Optionen stehen zur Verfügung:
- Maximale Anzahl Dateien
- Erlaubte Dateitypen
- Maximale Dateigröße
- Medienpool-Kategorie
- Sprache

## Eigene Entwicklungen

Für eigene Entwicklungen stehen zwei Varianten zur Verfügung:

### PIPE-Notation in YForm

```
fileponduploader|name|Label|[Optionen]
```

Beispiel:
```
fileponduploader|images|Bilder|{"max_files":"5","allowed_types":"image/*","max_size":"10","category":"1"}
```

### PHP-Notation für YForm

```php
$yform->setValueField('fileponduploader', [
    'name' => 'images',
    'label' => 'Bilder',
    'max_files' => 5,
    'allowed_types' => 'image/*',
    'max_size' => 10,
    'category' => 1,
    'language' => 'de'
]);
```

## Konfigurationsmöglichkeiten

### Attribute

| Attribut | Beschreibung | Standard |
|----------|--------------|-----------|
| data-filepond-cat | Medienpool Kategorie ID | 1 |
| data-filepond-maxfiles | Maximale Anzahl Dateien | 10 |
| data-filepond-types | Erlaubte Dateitypen | image/* |
| data-filepond-maxsize | Maximale Dateigröße in MB | 10 |
| data-filepond-lang | Sprache (de/en) | en |

### Sprache

Die Sprachausgabe kann über das Attribut `data-filepond-lang` gesteuert werden:
- `de`: Deutsch
- `en`: Englisch (Standard)

## AddOn Credits 
- KLXM Crossmedia GmbH: https://klxm.de
- Lizenz: MIT


## filepond Credits

- FilePond: https://pqina.nl/filepond/
- Lizenz: MIT
