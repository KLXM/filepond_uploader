# 🌊 YForm FilePond Upload

Ein schickes Upload-AddOn für REDAXO 5 mit YForm-Integration. Basierend auf dem super coolen [FilePond](https://pqina.nl/filepond/).

## 🎯 Features

- 🖼️ Bildupload mit Vorschau
- 📝 Metadaten direkt beim Upload (Titel, Alt-Text, Copyright)
- 🎯 Drag & Drop Upload
- 🔄 Sortierung per Drag & Drop
- 📱 Responsives Design
- 🎨 Hübsche Benutzeroberfläche
- 🤝 Direkte Integration in den Medienpool
- 💪 3 Einsatzmöglichkeiten (YForm, Modul, Upload-Seite)

## 🚀 Installation

1. Im REDAXO-Installer das AddOn `yform_filepond` herunterladen
2. AddOn installieren und aktivieren
3. That's it! 🎉

## 📋 Verwendung

### 1️⃣ Als YForm-Feld

```php
$yform->setValueField('filepond', [
    'name'    => 'bilder',              // Feldname
    'label'   => 'Bildergalerie',       // Label
    'category'=> 1,                     // Medienpool Kategorie ID
    'allowed_types' => 'image/*,.pdf',  // Erlaubte Dateitypen
    'allowed_filesize' => '10',         // Max. Dateigröße in MB
    'allowed_max_files' => '5',         // Max. Anzahl Dateien
    'required'=> false,                 // Pflichtfeld
    'notice'  => 'Bilder und PDFs bis 10MB erlaubt'
]);
```

### 2️⃣ Als Modul-Input

```php
<input 
    type="text" 
    name="REX_INPUT_VALUE[1]" 
    value="REX_VALUE[1]" 
    data-widget="filepond"
    data-filepond-cat="1"
    data-filepond-maxfiles="5"
    data-filepond-types="image/*"
    data-filepond-maxsize="10"
>
```

### 3️⃣ Als Upload-Seite

Die Upload-Seite findest du im REDAXO-Backend unter "FilePond Upload". Hier kannst du:
- Dateien direkt in eine bestimmte Kategorie hochladen
- Metadaten direkt beim Upload pflegen
- Alles super komfortabel per Drag & Drop erledigen

## 🎨 Styling Anpassungen

Das AddOn bringt schon schickes Styling mit, aber du kannst natürlich alles anpassen:

```css
/* Vorschaubilder größer machen */
.filepond--image-preview {
    height: 200px !important;
}

/* Andere Spaltenanzahl */
.filepond--list-scroller {
    grid-template-columns: repeat(4, 1fr) !important;
}
```

## 🛠️ Beispiele

### Bildergalerie in YForm

```php
// In der YForm Tabellen-Definition
$yform->setValueField('filepond', [
    'name' => 'galerie',
    'label' => '📸 Bildergalerie',
    'category' => 1,
    'allowed_types' => 'image/*',
    'notice' => 'Zieh deine schönsten Bilder einfach hier rein!'
]);

// Im Template
$bilder = explode(',', $this->getValue('galerie'));
foreach($bilder as $bild) {
    echo '<img src="'.rex_url::media($bild).'" alt="...">';
}
```

### PDF-Upload im Modul

```php
// Im Modul-Input
<input 
    type="text" 
    name="REX_INPUT_VALUE[1]" 
    value="REX_VALUE[1]" 
    data-widget="filepond"
    data-filepond-cat="2"
    data-filepond-types=".pdf"
    data-filepond-maxsize="20"
>

// Im Modul-Output
<?php
$pdfs = explode(',', "REX_VALUE[1]");
if(!empty($pdfs)) {
    echo '<div class="downloads">';
    foreach($pdfs as $pdf) {
        $media = rex_media::get($pdf);
        if($media) {
            echo '<a href="'.rex_url::media($pdf).'" class="download-btn">';
            echo '<span>'.$media->getTitle().'</span>';
            echo '</a>';
        }
    }
    echo '</div>';
}
?>
```

## 🤓 Tipps & Tricks

1. **Metadaten vorbelegen**: Du kannst die Metadaten-Felder beim Upload vorbelegen:
   ```javascript
   pond.setOptions({
       fileMetadataDefaults: {
           copyright: '© Meine Firma 2024'
       }
   });
   ```

2. **Kategorien dynamisch**: Die Medienpool-Kategorie kannst du auch dynamisch setzen:
   ```php
   $category_id = rex_request('category', 'int', 1);
   ```

3. **Upload-Feedback**: FilePond zeigt standardmäßig schönes Feedback an, du kannst aber auch eigene Nachrichten einbauen:
   ```javascript
   pond.on('processfile', (error, file) => {
       if(!error) {
           new NotificationMessage('success', 'Datei erfolgreich hochgeladen!');
       }
   });
   ```

## 🐛 Probleme?

Falls was nicht klappt:
1. Cache löschen
2. Nochmal Cache löschen
3. Immer noch Probleme? → [GitHub Issues](https://github.com/deinaccount/yform_filepond/issues)

## 💝 Credits

- [FilePond](https://pqina.nl/filepond/) fürs coole Upload-Widget
- Der REDAXO-Community fürs Testen und Feedback
- Dir fürs Lesen bis hierhin! 🎉

## 📝 Lizenz

MIT Lizenz - Mach damit was du willst! 🤘
