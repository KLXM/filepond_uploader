# KLXM FilePond Uploader for REDAXO

**A modern file uploader for REDAXO with chunk upload and seamless media pool integration.**

![Screenshot](https://github.com/KLXM/filepond_uploader/blob/assets/screenshot.png?raw=true)

Alternative: [uppy](https://github.com/FriendsOfREDAXO/uppy)

## Key Features

*   **Chunk upload as a core feature:**
    *   Reliable upload of large files in small parts (chunks)
    *   Configurable chunk size (default: 5 MB)
    *   Progress display for individual chunks and the full file
    *   Automatic merging of chunks after upload

*   **Replace existing media pool files:**
    *   Dedicated FilePond panel on the media detail page
    *   Uses the same chunk upload flow for large replacement files
    *   Keeps the existing REDAXO media filename unchanged

*   **Delayed upload mode:**
    *   Select and arrange files before uploading
    *   Separates file selection from the upload process
    *   User-friendly upload button appears automatically
    *   Remove unwanted files before upload
    *   Ideal for editors working with many files

*   **Modern interface:**
    *   Drag and drop for easy uploads
    *   Live preview of images during upload
    *   Responsive design for all screen sizes

*   **Automatic image optimization:**
    *   **Client-side resizing** of large images before upload (optional, disabled by default)
    *   Faster upload through reduced file size
    *   Lower server load - ideal for shared hosting
    *   Automatic EXIF orientation correction (important for smartphone photos)
    *   Configurable compression quality for JPEG/PNG/WebP
    *   Preserve original dimensions for GIF files
    *   **Optional:** Additional server-side image processing
    *   **Important:** If both options are disabled, original files are uploaded (recommended for professional photography)

*   **Accessibility and legal safety:**
    *   ALT text requirement for images is configurable (enabled by default)
    *   Automatically creates meta fields if they do not exist
    *   Optional prompt for copyright and description metadata

*   **YForm integration:**
    *   Dedicated YForm value field with automatic cleanup of unused media
    *   Multi-upload support with dynamic preview
    *   Easy configuration through familiar YForm interfaces

*   **Multilingual support:**
    *   Available in German (DE) and English (EN)
    *   Easily extendable for additional languages

*   **Secure API:**
    *   Token-based authentication for external access
    *   Support for YCOM user authentication
    *   Validation of file types and file sizes

*   **Info Center integration:**
    *   Upload widget directly in the REDAXO Info Center dashboard
    *   Quick access without opening the media pool
    *   Category selection via rex_media_category_select
    *   Automatic positioning after the TimeTracker widget
    *   Respects all FilePond configuration and user permissions

*   **Media widget integration:**
    *   Seamless integration with REX_MEDIA and REX_MEDIALIST widgets
    *   Direct upload into form fields
    *   Thumbnail preview for better overview
    *   Bulk insert for media lists
    *   Multilingual user interface

*   **Maintenance tools:**
    *   Easy cleanup of temporary files and chunks
    *   Logging of all upload operations
    *   Admin interface for system maintenance

*   **Alt Text Checker for accessibility:**
    *   Finds all images without alt text in the media pool
    *   Statistics dashboard with completion percentage
    *   Accordion preview with larger image view for better descriptions
    *   Inline editing directly in the table
    *   Supports multilingual alt texts (metainfo_lang_fields)
    *   Mark decorative images (negative list for images without mandatory alt text)
    *   **AI alt text generation** for automatic descriptions at the click of a button
    *   Supports **Google Gemini** and **Cloudflare Workers AI** providers
    *   Configurable prompt profiles: **Accessibility (default)**, **Short/neutral**, **SEO-focused**
    *   Optional AI result cache with configurable TTL to reduce repeated requests
    *   Multilingual generation in one request with fallback language and optional blocked-language list
    *   Fast keyboard navigation with the Tab key
    *   Bulk save of all changes
    *   Filter by filename and category
    *   Integrated as a media pool subpage
    *   Dedicated permission: `filepond_uploader[alt_checker]`

## Installation

1.  **Install the addon:** Install the addon filepond_uploader via the REDAXO installer.
2.  **Activate the addon:** Activate the addon in the backend under AddOns.
3.  **Configure:** Adjust settings under FilePond Uploader > Settings.
4.  **Done:** The uploader is now ready to use.

## Quick Start

### Replace existing media files

On the media detail page in the REDAXO media pool, FilePond can add an additional panel named `Replace file in media pool`.

- Select exactly one file with a matching extension.
- Large files also work here because the existing chunk upload flow is reused.
- The existing media pool filename stays unchanged; only the file content is replaced.
- After a successful replacement, the detail page reloads automatically.

The feature can be toggled separately in the addon settings via `Replace existing media pool files with FilePond`.

### Info Center Upload Widget

The FilePond addon provides a practical upload widget in the REDAXO backend Info Center. It allows quick uploads directly from the dashboard without switching to the media pool.

#### Info Center Widget Features

**Quick access:**
- Upload functionality directly in Info Center
- No switch to media pool required
- Compact display without leaving the dashboard

**Full FilePond integration:**
- Uses all configured FilePond settings
- Drag and drop uploads in Info Center
- Chunk upload for large files
- Image optimization and metadata input

**Category selection:**
- Dropdown for target category
- Uses standard rex_media_category_select
- Respects category permissions

**Intelligent positioning:**
- Automatically appears after the TimeTracker widget
- Visible only for logged-in users
- Robust detection of available addons

#### Activation

The Info Center widget is enabled automatically if these conditions are met:

1. **Info Center addon installed:** The REDAXO Info Center addon must be active
2. **FilePond Uploader active:** This addon must be active
3. **User logged in:** Widget is only shown for logged-in backend users

> **Note:** The widget is automatically positioned between TimeTracker and other widgets (priority 0.5). No additional configuration is required.

#### Widget Functionality

**Upload form:**
- Same structure as the media pool upload page
- Category selection with all available media pool categories
- Automatic refresh when category changes
- Respects all FilePond settings (types, size limits, etc.)

**Metadata input:**
- Full integration of metadata dialogs
- Supports multilingual fields (MetaInfo Lang Fields)
- Validation according to configured rules
- Alt text and copyright prompts as usual

**Usability:**
- Drag and drop directly in the widget
- Live preview of uploaded files
- Progress indicator and chunk upload
- Seamless integration into REDAXO UI

#### Deactivation

If the widget is not desired, it can be removed by disabling the Info Center addon or by customizing boot.php.

### Media Widget Integration

The FilePond addon provides seamless integration with REDAXO's standard media widgets (REX_MEDIA and REX_MEDIALIST). After upload, files can be inserted directly into form fields.

#### Usage

1. **Open a media widget:** Click the open icon next to a REX_MEDIA or REX_MEDIALIST field
2. **Upload mode detected:** FilePond detects widget context and shows an info banner
3. **Upload files:** Use drag and drop or file selection
4. **Direct insert:** After successful upload, insert buttons are shown

#### Media Widget Integration Features

**For REX_MEDIA (single media):**
- Upload -> insert -> window closes automatically
- Image preview with 80x80 thumbnails
- File type icons for non-image files

**For REX_MEDIALIST (media lists):**
- Upload -> window stays open for more uploads
- Per-file insert
- Add all button for multiple files
- Duplicate protection prevents duplicate entries
- Visual feedback with Added status

#### Preview System

The addon shows automatic previews for uploaded content:

**Images (jpg, png, gif, webp, etc.):**
- 80x80 thumbnail preview
- Proportional scaling with object-fit
- Fallback to file type icon if preview fails

**Other file types:**
- Color-coded icons by type
- PDF (red), Word (blue), Excel (green), etc.
- File extension label under icon

#### Multilingual UI

The media widget integration supports full multilingual UI:

**German:**
- Upload-Auswahl
- Die ausgewählten Elemente können in die Liste übernommen werden.

**English:**
- Upload Selection
- The selected items can be added to the list.

> **Note:** This feature works automatically with all existing REX_MEDIA and REX_MEDIALIST fields and requires no additional setup.

### Usage as YForm Field Type

```php
$yform->setValueField('filepond', [
    'name' => 'bilder',
    'label' => 'Bildergalerie',
    'category' => 1,
    'allowed_types' => 'image/*,application/pdf',
    'allowed_filesize' => 10,
    'allowed_max_files' => 5,
    'required' => 0,
    'notice' => 'Please upload your files here.',
    'empty_value' => 'Please select at least one file.',
    'skip_meta' => 0,
    'chunk_enabled' => 1,
    'chunk_size' => 5,
    'delayed_upload' => 0,
    'title_required' => 1,
    'alt_required' => 1,
    'max_pixel' => 2100,
    'image_quality' => 90,
    'client_resize' => 0,
    'ai_enabled' => 0,
    'ai_target_field' => 'med_alt',
]);
```

Supported YForm value options: name, label, category, allowed_types, allowed_filesize, allowed_max_files, required, notice, empty_value, skip_meta, chunk_enabled, chunk_size, delayed_upload, title_required, alt_required, max_pixel, image_quality, client_resize, ai_enabled, ai_target_field.

### Option: delayed_upload

The delayed_upload option controls when files are actually uploaded and linked to the form:

| Value | Behavior | Typical use case |
|------|-----------|------------------|
| `0`  | Files are uploaded **immediately after selection**. | Default behavior, e.g. image galleries |
| `1`  | Files are uploaded only when the user clicks the **upload button**. | Collect and upload multiple files together |
| `2`  | After upload, the **YForm is submitted automatically**. | Quick-upload forms; ensure client-side required checks for other fields |

---

> **Note:**
> The filepond value field is a convenient way to use the uploader in YForm.
> Alternatively, a regular input field with required data attributes can be used.
> In that case, automatic cleanup of unused media is not available.

### Usage in Modules

#### Input

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
    data-filepond-ai-enabled="true"
    data-filepond-ai-target-field="med_alt"
>
```

### Usage in YForm (Frontend)

Complete frontend form example that also works for guests (without login).

```php
<?php
// 1. Start session
rex_login::startSession();

// 2. Optional token fallback for guests (if no backend/ycom login is available)
$apiToken = (string) rex_config::get('filepond_uploader', 'api_token', '');
if ('' !== trim($apiToken)) {
    rex_set_session('filepond_token', trim($apiToken));
}

// 3. Include FilePond assets
if (rex::isFrontend()) {
    echo filepond_helper::getStyles();
    echo filepond_helper::getScripts();
}

// 4. Configure YForm instance
$yform = new rex_yform();
$yform->setObjectparams('form_name', 'upload-form');
$yform->setObjectparams('form_action', rex_getUrl(rex_article::getCurrentId()));
$yform->setObjectparams('form_ytemplate', 'bootstrap');
$yform->setObjectparams('form_showformafterupdate', 0);
$yform->setObjectparams('real_field_names', true);

// 5. Add FilePond field (named options)
$yform->setValueField('filepond', [
    'name' => 'attachment',
    'label' => 'Datei-Upload',
    'category' => 0,
    'allowed_types' => 'image/*,application/pdf',
    'allowed_filesize' => 50,
    'allowed_max_files' => 5,
    'required' => 0,
    'notice' => 'Bitte laden Sie Ihre Dateien hier hoch.',
    'empty_value' => 'Bitte wählen Sie mindestens eine Datei aus.',
    'skip_meta' => 0,
    'chunk_enabled' => 1,
    'chunk_size' => 5,
    'delayed_upload' => 0,
    'title_required' => 1,
    'alt_required' => 1,
    'max_pixel' => 2100,
    'image_quality' => 90,
    'client_resize' => 0,
    'ai_enabled' => 0,
    'ai_target_field' => 'med_alt',
]);

// 6. Save to DB
$yform->setActionField('db', ['rex_my_yform_table']);

// 7. Success message
$yform->setActionField('html', ['<div class="alert alert-success">Vielen Dank! Der Upload war erfolgreich.</div>']);

echo $yform->getForm();
?>
```

**Notes:**
- FilePond initializes automatically after DOM ready. No manual init script is required.
- If you inject form HTML dynamically (AJAX/PJAX), trigger re-init via `document.dispatchEvent(new Event('filepond:init'));`.
- The metadata field mapping is handled automatically via backend API; no `data-filepond-metainfo-lang` attribute is required.

**Notes on data-filepond-types:**
- MIME types are preferred: image/*, video/*, application/pdf
- File extensions are converted automatically: .pdf, .doc, .docx
- Both formats can be mixed: image/*, .pdf, .doc

#### Output

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

        if (class_exists('\\FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper')) {
            $titles = \\FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper::getFieldValues(
                $media,
                'med_title_lang'
            );

            $currentTitle = $titles[rex_clang::getCurrentId()] ?? '';
            echo '<p>Title: ' . rex_escape($currentTitle) . '</p>';

            $descriptions = \\FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper::getFieldValues(
                $media,
                'med_description_lang'
            );
            $currentDescription = $descriptions[rex_clang::getCurrentId()] ?? '';
            echo '<p>Description: ' . rex_escape($currentDescription) . '</p>';
        }
    }
}
?>
```

### Add Uploads to Emails

To include uploads in emails via YForm forms, an action is available.

Pipe notation:

```php
action|filepond2email|label_filepond
```

PHP notation:

```php
$yform->setActionField('filepond2email',['label_filepond']);
```

Replace label_filepond with the field name of your filepond field, for example uploads.

## Chunk Upload for Large Files

Chunk upload is the core of the FilePond uploader and enables reliable uploads of large files even on slow internet connections.

### How It Works

1. **File splitting:** Large files are split into small chunks client-side.
2. **Chunked upload:** Each chunk is uploaded individually with progress display.
3. **Server merge:** After upload completion, chunks are merged server-side into one file.
4. **Automatic cleanup:** Temporary files are removed after successful upload.

### Benefits

- **Improved reliability:** On network errors, only failed chunks need retrying.
- **Large files:** Helps overcome server upload limits.
- **Better performance:** More efficient use of server resources.
- **User-friendly:** Clear progress display for chunk and total upload.

### Configuration

In the backend you can configure:

- **Enable/disable chunk upload:** Global setting for all upload fields.
- **Chunk size:** Size of each chunk in MB (default: 5 MB).
- **Cleanup temporary files:** Manual cleanup of old temporary files.

## Helper Class

The addon includes a helper class for easy inclusion of CSS and JavaScript files.

```php
<?php
echo filepond_helper::getScripts();
echo filepond_helper::getStyles();
?>
```

## Configuration

### Data Attributes

The following data attributes can be used for configuration:

| Attribute                     | Description                            | Default |
| ---------------------------- | -------------------------------------- | ------- |
| `data-filepond-cat`          | Media pool category ID                 | `0` |
| `data-filepond-types`        | Allowed file types (MIME or extensions, comma-separated) | `image/*` |
| `data-filepond-maxfiles`     | Maximum number of files                | `30` |
| `data-filepond-maxsize`      | Maximum file size in MB                | `10` |
| `data-filepond-lang`         | Language (`de_de` / `en_gb`)           | `de_de` |
| `data-filepond-skip-meta`    | Disable metadata input                 | `false` |
| `data-filepond-chunk-enabled`| Enable chunk upload                    | `true` |
| `data-filepond-chunk-size`   | Chunk size in MB                       | `5` |
| `data-filepond-delayed-upload` | Delayed upload mode                  | `false` |
| `data-filepond-delayed-type` | Upload mode type (1=button, 2=submit) | `1` when delayed-upload is active |
| `data-filepond-title-required` | Title required                       | `false` |
| `data-filepond-alt-required` | Alt text required for images          | `true` |
| `data-filepond-max-pixel`    | Max image size in pixels for client resize | `2100` |
| `data-filepond-image-quality` | JPEG/WebP compression quality (10-100) | `90` |
| `data-filepond-client-resize` | Enable client-side image resize (`true`/`false`) | `false` |
| `data-filepond-ai-enabled` | Enable AI suggestion button in upload metadata dialog | `false` |
| `data-filepond-ai-target-field` | Metadata field used as AI target (for example `med_alt`) | `med_alt` |
| `data-filepond-opener-field`  | Opener input field for media widget integration | - |

#### Special Metadata Attributes

**data-filepond-title-required**
Controls whether the simple title field is required:

```html
<input data-filepond-title-required="true" data-widget="filepond" ...>
<input data-filepond-title-required="false" data-widget="filepond" ...>
```

**data-filepond-alt-required**
Controls whether `med_alt` is required for image uploads:

```html
<input data-filepond-alt-required="true" data-widget="filepond" ...>
<input data-filepond-alt-required="false" data-widget="filepond" ...>
```

**data-filepond-ai-enabled**
Enables the AI suggestion button in the upload metadata dialog:

```html
<input data-filepond-ai-enabled="true" data-widget="filepond" ...>
<input data-filepond-ai-enabled="false" data-widget="filepond" ...>
```

**data-filepond-ai-target-field**
Defines the metadata field that receives the AI suggestion:

```html
<input data-filepond-ai-target-field="med_alt" data-widget="filepond" ...>
```

Practical AI example for image uploads:

```html
<input
    type="hidden"
    name="REX_INPUT_VALUE[1]"
    value="REX_VALUE[1]"
    data-widget="filepond"
    data-filepond-types="image/*"
    data-filepond-title-required="true"
    data-filepond-alt-required="true"
    data-filepond-ai-enabled="true"
    data-filepond-ai-target-field="med_alt"
>
```

> **Note:** `data-filepond-title-lang-required` and `data-filepond-metainfo-lang` are legacy documentation attributes and are no longer required for current frontend integration.

### Allowed File Types (MIME Types)

#### Basic Syntax

The addon supports both MIME types and file extensions. MIME types are preferred because they are safer and less ambiguous.

Recommended:

```
data-filepond-types="mime/type"
```

Alternative:

```
data-filepond-types=".extension"
```

Both formats can be mixed.

#### Standard MIME Types

*   **Images:** `image/*` or `image/jpeg, image/png, image/gif, image/webp`
*   **Videos:** `video/*` or `video/mp4, video/webm, video/quicktime`
*   **Audio:** `audio/*` or `audio/mpeg, audio/wav, audio/ogg`
*   **PDFs:** `application/pdf`
*   **Microsoft Word:** `application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document`
*   **Microsoft Excel:** `application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
*   **Microsoft PowerPoint:** `application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation`

#### Examples

```html
data-filepond-types="image/*"
data-filepond-types="image/*, application/pdf"
data-filepond-types="image/*, .pdf"
data-filepond-types="image/*, video/*, application/pdf"
data-filepond-types="image/*, video/*, .pdf"
data-filepond-types="application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, text/plain"
data-filepond-types=".pdf, .doc, .docx, .txt"
data-filepond-types="application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-powerpoint, application/vnd.openxmlformats-officedocument.presentationml.presentation"
data-filepond-types="image/*, video/*, application/pdf, .doc, .docx, .txt"
```

#### Supported File Extensions

Automatically mapped extensions include:

*   **Images:** `.jpg, .jpeg, .png, .gif, .webp, .avif, .svg, .bmp, .tiff, .tif, .ico`
*   **Videos:** `.mp4, .webm, .ogg, .ogv, .avi, .mov, .wmv, .flv, .mkv`
*   **Audio:** `.mp3, .wav, .ogg, .oga, .flac, .m4a, .aac`
*   **Documents:** `.pdf, .doc, .docx, .xls, .xlsx, .ppt, .pptx, .odt, .ods, .odp`
*   **Text:** `.txt, .csv, .rtf, .html, .htm, .xml, .json`
*   **Archives:** `.zip, .rar, .7z, .tar, .gz, .bz2`

## Session Configuration for Individual Overrides

> **Note:** If you use YForm/YOrm, call rex_login::startSession() before YForm/YOrm initialization.

Start session in frontend:

```php
rex_login::startSession();
```

### Pass API Token

```php
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));
```

### Disable Metadata Prompt

```php
rex_set_session('filepond_no_meta', true);
```

### Configure Title as Required

```php
rex_set_session('filepond_title_required', true);
```

### Module Example

```php
<?php
rex_login::startSession();
rex_set_session('filepond_token', rex_config::get('filepond_uploader', 'api_token'));
rex_set_session('filepond_no_meta', true);
rex_set_session('filepond_title_required', true);

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
        data-filepond-chunk-enabled="true"
        data-filepond-chunk-size="5242880"
    >
</form>
```

## Frontend Initialization and Tips

```js
// Usually not needed: filepond_widget.js initializes automatically.
// Only for dynamically injected markup:
document.dispatchEvent(new Event('filepond:init'));
```

### jQuery Variant

```js
// Optional in jQuery/PJAX contexts
jQuery(document).trigger('rex:ready');
```

## Customizing FilePond Styles

The addon provides customizable styles for different parts of the FilePond interface.

### Customize Upload Button

```css
:root {
    --filepond-upload-btn-color: #4285f4;
    --filepond-upload-btn-hover-color: #3367d6;
    --filepond-upload-btn-text-color: #fff;
    --filepond-upload-btn-border-radius: 4px;
    --filepond-upload-btn-padding: 10px 16px;
    --filepond-upload-btn-font-size: 14px;
    --filepond-upload-btn-font-weight: 500;
    --filepond-upload-btn-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    --filepond-upload-btn-shadow-hover: 0 4px 8px rgba(0, 0, 0, 0.2);
}
```

Examples for different button styles:

```css
:root {
    --filepond-upload-btn-color: #dc3545;
    --filepond-upload-btn-hover-color: #c82333;
}

:root {
    --filepond-upload-btn-color: #28a745;
    --filepond-upload-btn-hover-color: #218838;
}

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

### Customize Thumbnail Borders

```css
[data-filepond-item-state='processing-complete'] {
    border: 3px solid #28a745 !important;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.3) !important;
    border-radius: 0.5em !important;
}

[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    border: 3px solid #dc3545 !important;
    box-shadow: 0 0 8px rgba(220, 53, 69, 0.3) !important;
    border-radius: 0.5em !important;
}
```

Disable glow animation:

```css
[data-filepond-item-state='processing-complete'],
[data-filepond-item-state*='error'],
[data-filepond-item-state*='invalid'] {
    animation: none !important;
}
```

### Theme-specific Customization

```css
.dark-theme .filepond-upload-btn {
    --filepond-upload-btn-color: #3d4852;
    --filepond-upload-btn-hover-color: #2d3748;
    --filepond-upload-btn-text-color: #f7fafc;
}

.minimal-theme .filepond-upload-btn {
    --filepond-upload-btn-color: transparent;
    --filepond-upload-btn-hover-color: rgba(0, 0, 0, 0.05);
    --filepond-upload-btn-text-color: #2196F3;
    --filepond-upload-btn-shadow: none;
    --filepond-upload-btn-shadow-hover: none;
    border: 1px solid currentColor;
}
```

### Include Custom CSS

```html
<link rel="stylesheet" href="path/to/filepond-custom-styles.css">
<link rel="stylesheet" href="path/to/my-overrides.css">
```

```php
<?php
echo filepond_helper::getStyles();
rex_view::addCssFile($this->getAssetsUrl('css/my-filepond-overrides.css'));
?>
```

## Image Optimization

Images can be optimized automatically - client-side in the browser, server-side after upload, or both. Both options are independent.

### Client-side Image Processing (default: enabled)

Client-side processing uses FilePond plugins to optimize images directly in the browser.

Benefits:
- Faster upload due to smaller files
- Lower server load
- Ideal for shared hosting
- Immediate preview of optimized images

### Server-side Image Processing (default: disabled)

Server-side processing optimizes images after upload on the server.

Benefits:
- Works with older browsers
- Additional validation layer
- Consistent processing independent of client

### Combinations

| Client-side | Server-side | Use case |
|:-----------:|:-----------:|---------|
| yes | no | Default, ideal for shared hosting |
| no | yes | Strong server resources, older browsers |
| yes | yes | Cascading optimization |
| no | no | Keep original files |

### EXIF Orientation

Client-side EXIF correction runs automatically via FilePond EXIF Orientation plugin when client-side processing is enabled.

## Multilingual Support and MetaInfo Lang Fields

The addon supports multilingual metadata through integration with MetaInfo Lang Fields.

### Requirements

1. MetaInfo addon
2. MetaInfo Lang Fields addon

### Recommended Fields

Single-language fields:
- title
- med_alt
- med_copyright

Multilingual fields:
- med_title_lang
- med_description_lang
- med_keywords_lang

### Validation

- med_title_lang: always required
- med_alt: required for images by default, can be disabled in settings, and can be marked decorative
- title: optional unless configured as required

## Metadata

Each uploaded file can include:

Standard metadata:
1. title
2. med_alt
3. med_copyright
4. med_description

Multilingual metadata:
1. med_title_lang
2. med_description_lang
3. med_keywords_lang

Configurable required fields:
- title: optional by default, configurable as required
- med_title_lang: always required
- med_alt: required for images by default (setting: Alt text for images as required field)

## Alt Text Checker

The Alt Text Checker helps improve accessibility by listing images without alt text and enabling quick editing.

### Access

Available under Media Pool -> Alt Text Checker for:
- Admins
- Users with permission filepond_uploader[alt_checker]

## AI Alt Text Generation

The addon supports automatic AI alt text generation. Two providers are supported:

### Area-specific Activation

You can enable or disable AI buttons separately in settings:
- AI button in upload metadata dialog
- AI button on media detail page

Path: FilePond Uploader -> Settings -> AI Alt Text Generation

Both area toggles are in addition to the global toggle Enable AI generation.

### Google Gemini (recommended)

Google Gemini provides excellent image analysis with strong multilingual quality.

### Cloudflare Workers AI

Cloudflare provides an alternative with a generous free quota.

### Usage in Alt Text Checker

After setup, the checker shows:
- Magic wand button per image
- AI generate all button for bulk generation
- Multilingual generation for multilingual fields

Not supported: SVG files

### Usage in Upload Metadata Dialog and Media Detail Page

- The magic button appears in each enabled area at the configured target field.
- Configure target via Target field for AI suggestion (default: med_alt).
- Multilingual target fields such as med_alt_en are detected automatically.

## Delayed Upload Mode

Delayed upload separates file selection from upload execution. Upload starts only after clicking the upload button.

## Credits

*   **KLXM Crossmedia GmbH:** [klxm.de](https://klxm.de) - Advertising agency from the Lower Rhine region
*   **Developer:** [Thomas Skerbis](https://github.com/skerbis)
*   **Vendor:** FilePond - [pqina.nl/filepond](https://pqina.nl/filepond/)
*   **License:** MIT

We believe in giving back to the community and actively contribute to the REDAXO ecosystem.

## Support

*   **GitHub Issues:** For bug reports and feature requests
*   **REDAXO Slack:** For community support and discussions
*   **[www.redaxo.org](https://www.redaxo.org):** Official REDAXO website
*   **[Addon homepage](https://github.com/KLXM/filepond_uploader/tree/main):** Current information and updates
