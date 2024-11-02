<?php
$csrf = rex_csrf_token::factory('filepond_uploader');

// Ausgewählte Kategorie
$selectedCategory = rex_request('category_id', 'int', 0);

$selMedia = new rex_media_category_select($checkPerm = true);
$selMedia->setId('rex-mediapool-category');
$selMedia->setName('category_id');
$selMedia->setSize(1);
$selMedia->setSelected($selectedCategory);
$selMedia->setAttribute('class', 'selectpicker');
$selMedia->setAttribute('data-live-search', 'true');
if (rex::requireUser()->getComplexPerm('media')->hasAll()) {
    $selMedia->addOption(rex_i18n::msg('pool_kats_no'), '0');
}

$content = '';
$success = '';
$error = '';

$content .= '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post" class="form-horizontal">
        ' . $csrf->getHiddenField() . '
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="panel-title">Filepond Upload</div>
            </div>
            
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-2 control-label">Kategorie</label>
                    <div class="col-sm-10">
                        '.$selMedia->get().'
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label">Dateien</label>
                    <div class="col-sm-10">
                        <input type="file" name="filepond" class="filepond" multiple />
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Metadata Form Template -->
<template id="metadata-form-template">
    <form class="metadata-form">
        <div class="row">
            <div class="col-md-5">
                <!-- Bildvorschau Container -->
                <div class="image-preview-container">
                    <img src="" alt="" class="img-responsive">
                </div>
                <!-- Dateiinfo -->
                <div class="file-info small text-muted"></div>
            </div>
            <div class="col-md-7">
                <div class="form-group">
                    <label for="title">Titel:</label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                <div class="form-group">
                    <label for="alt">Alt-Text:</label>
                    <input type="text" class="form-control" name="alt" required>
                    <small class="form-text text-muted">Alternativtext für Screenreader und SEO</small>
                </div>
                <div class="form-group">
                    <label for="copyright">Copyright:</label>
                    <input type="text" class="form-control" name="copyright">
                </div>
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-save">Speichern</button>
                    <br><small class="form-text text-muted">Wenn nicht gespeichert wird, wird diese Datei nicht hochgeladen</small>
                </div>
            </div>
        </div>
    </form>
</template>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // FilePond initialisieren
    FilePond.registerPlugin(
        FilePondPluginFileValidateType,
        FilePondPluginFileValidateSize,
        FilePondPluginImagePreview
    );

    const input = document.querySelector("input[type=\'file\']");
    const categorySelect = document.querySelector("#rex-mediapool-category");
    const metadataTemplate = document.querySelector("#metadata-form-template");

    // Create metadata dialog
    const createMetadataDialog = (file) => {
        return new Promise((resolve) => {
            const dialog = document.createElement("div");
            dialog.className = "modal fade";
            dialog.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Metadaten für ${file.name}</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            ${metadataTemplate.innerHTML}
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(dialog);
            const $dialog = $(dialog);

            // Bild in Vorschau laden
            const previewImage = async () => {
                const imgContainer = dialog.querySelector(".image-preview-container img");
                const fileInfo = dialog.querySelector(".file-info");
                
                try {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        imgContainer.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    fileInfo.innerHTML = `
                        <strong>Datei:</strong> ${file.name}<br>
                        <strong>Größe:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                    `;
                } catch (error) {
                    console.error("Error loading preview:", error);
                    imgContainer.src = "";
                }
            };

            previewImage();

            // Handle form submit
            dialog.querySelector("form").addEventListener("submit", (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const metadata = {
                    title: formData.get("title"),
                    alt: formData.get("alt"),
                    copyright: formData.get("copyright")
                };
                resolve(metadata);
                $dialog.modal("hide");
            });

            $dialog.modal("show");

            // Cleanup on close
            $dialog.on("hidden.bs.modal", () => {
                document.body.removeChild(dialog);
            });
        });
    };

    const pond = FilePond.create(input, {
        server: {
            url: "index.php",
            process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                try {
                    const fileMetadata = await createMetadataDialog(file);
                    
                    const formData = new FormData();
                    formData.append(fieldName, file);
                    formData.append("rex-api-call", "filepond_uploader");
                    formData.append("func", "upload");
                    formData.append("category_id", categorySelect.value);
                    formData.append("metadata", JSON.stringify(fileMetadata));

                    const response = await fetch("index.php", {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        console.error("Upload error:", result);
                        error(result.error || "Upload failed");
                        return;
                    }

                    load(result);
                } catch (err) {
                    console.error("Upload error:", err);
                    error(err.message || "Upload failed");
                }
            }
        },
        labelIdle: \'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>\',
        allowMultiple: true,
        maxFileSize: "10MB",
        acceptedFileTypes: ["image/*", "application/pdf"]
    });

    // Bei Kategorie-Wechsel die aktuelle Kategorie im Upload mit übergeben
    categorySelect.addEventListener("change", function() {
        pond.server.process.ondata = (formData) => {
            formData.append("rex-api-call", "filepond_uploader");
            formData.append("func", "upload");
            formData.append("category_id", categorySelect.value);
            return formData;
        };
    });
});
</script>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', 'Filepond Upload');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');