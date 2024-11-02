Ich erstelle die README.md für das REDAXO-AddOn "filepond":

```markdown
# Filepond für REDAXO

Ein schickes Datei-Upload-Widget mit Drag & Drop, Bildervorschau und Metadaten-Verwaltung für REDAXO. Basierend auf dem leistungsstarken [FilePond](https://pqina.nl/filepond/) JavaScript Framework.

## Features

- 🖼️ Moderne Drag & Drop Oberfläche
- 🔍 Live-Bildvorschau während des Uploads
- 📝 Metadaten-Dialog für Titel, Alt-Text und Copyright
- 🌐 Mehrsprachig (Deutsch/Englisch)
- 🎯 Einfache Integration in Module, YForm und eigene Skripte
- 📁 Automatische Medienpool-Integration
- ⚡️ Asynchrone Uploads
- 🎨 Responsive Design
- 🛡️ Validierung von Dateitypen und -größen

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
        echo '<img src="'.$media->getUrl().'" alt="'.$media->getValue('med_description').'">';
    }
}
?>
```

## Anwendung auf der Uploadseite

Die Upload-Seite im Backend bietet die gleiche Funktionalität wie das Modul. Die Medien werden direkt in den Medienpool hochgeladen und kategorisiert.

## Anwendung im REDAXO Table-Manager von YForm

Filepond als eigener Feldtyp im Table Manager:

```
filepond|name|label|allowed_types|allowed_filesize|allowed_max_files|category|required|notice
```

Beispiel:
```
filepond|images|Bilder|image/*|10|5|1|0|Bilder hochladen
```

## Anwendung als PIPE-Notation

```
text|images|Bilder|
filepond|images|Bilder
validate|empty|images|Bitte wählen Sie mindestens ein Bild aus
```

## Anwendung als PHP Notation

```php
$yform->setValueField('filepond', [
    'name' => 'images',
    'label' => 'Bilder',
    'allowed_types' => 'image/*',
    'allowed_filesize' => '10',
    'allowed_max_files' => '5',
    'category' => '1',
    'required' => false,
    'notice' => 'Bilder hochladen'
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

### YForm Definitionen

| Option | Beschreibung | Standard |
|--------|--------------|-----------|
| name | Feldname | - |
| label | Bezeichnung | - |
| allowed_types | Erlaubte Dateitypen | image/* |
| allowed_filesize | Maximale Dateigröße in MB | 10 |
| allowed_max_files | Maximale Anzahl Dateien | 10 |
| category | Medienpool Kategorie ID | 1 |
| required | Pflichtfeld | false |
| notice | Hinweistext | - |

### Sprache

Die Sprachausgabe kann über das Attribut `data-filepond-lang` gesteuert werden:
- `de`: Deutsch
- `en`: Englisch (Standard)

Weitere Sprachen können in der filepond_widget.js hinzugefügt werden.
```

Soll ich noch etwas ergänzen oder anpassen?
