$(document).on('rex:ready', function() {
   // Debug Helper
   const debug = {
       log: function(message, data = null) {
           console.log('FilePond Debug:', message, data || '');
       }
   };

   // Translations
   const translations = {
       de: {
           labelIdle: 'Dateien hierher ziehen oder <span class="filepond--label-action">durchsuchen</span>',
           metaTitle: 'Metadaten für',
           titleLabel: 'Titel:',
           altLabel: 'Alt-Text:',
           altNotice: 'Alternativtext für Screenreader und SEO',
           copyrightLabel: 'Copyright:',
           fileInfo: 'Datei',
           fileSize: 'Größe',
           saveBtn: 'Speichern',
           cancelBtn: 'Abbrechen'
       },
       en: {
           labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>',
           metaTitle: 'Metadata for',
           titleLabel: 'Title:',
           altLabel: 'Alt Text:',
           altNotice: 'Alternative text for screen readers and SEO',
           copyrightLabel: 'Copyright:',
           fileInfo: 'File',
           fileSize: 'Size',
           saveBtn: 'Save',
           cancelBtn: 'Cancel'
       }
   };

   FilePond.registerPlugin(
       FilePondPluginFileValidateType,
       FilePondPluginFileValidateSize,
       FilePondPluginImagePreview
   );

   $('input[data-widget="filepond"]').each(function() {
       const input = $(this);
       const lang = input.data('filepondLang') || 'en'; // Default zu Englisch
       const t = translations[lang] || translations['en']; // Fallback zu Englisch
       
       // Originalwert mit Anführungszeichen
       let rawValue = input.val();
       debug.log('Raw input value:', rawValue);
       
       // Wert für die Verarbeitung (mit Anführungszeichen, da die API sie auch zurückgibt)
       let initialValue = rawValue.trim();
       debug.log('Cleaned input value:', initialValue);
       
       input.hide();

       const fileInput = $('<input type="file" multiple/>');
       fileInput.insertAfter(input);

       // Create metadata dialog
       const createMetadataDialog = (file, existingMetadata = null) => {
           return new Promise((resolve, reject) => {  // reject hinzugefügt
               const dialog = $(`
                   <div class="modal fade">
                       <div class="modal-dialog modal-lg">
                           <div class="modal-content">
                               <div class="modal-header">
                                   <h4 class="modal-title">${t.metaTitle} ${file.name || file.filename}</h4>
                                   <button type="button" class="close" data-dismiss="modal">&times;</button>
                               </div>
                               <div class="modal-body">
                                   <form class="metadata-form">
                                       <div class="row">
                                           <div class="col-md-5">
                                               <div class="image-preview-container">
                                                   <img src="" alt="" class="img-responsive">
                                               </div>
                                               <div class="file-info small text-muted"></div>
                                           </div>
                                           <div class="col-md-7">
                                               <div class="form-group">
                                                   <label for="title">${t.titleLabel}</label>
                                                   <input type="text" class="form-control" name="title" required>
                                               </div>
                                               <div class="form-group">
                                                   <label for="alt">${t.altLabel}</label>
                                                   <input type="text" class="form-control" name="alt" required>
                                                   <small class="form-text text-muted">${t.altNotice}</small>
                                               </div>
                                               <div class="form-group">
                                                   <label for="copyright">${t.copyrightLabel}</label>
                                                   <input type="text" class="form-control" name="copyright">
                                               </div>
                                               <div class="form-group mt-4">
                                                   <button type="submit" class="btn btn-save">${t.saveBtn}</button>
                                                   <button type="button" class="btn btn-abort" data-dismiss="modal">${t.cancelBtn}</button>
                                               </div>
                                           </div>
                                       </div>
                                   </form>
                               </div>
                           </div>
                       </div>
                   </div>
               `);

               // Bild in Vorschau laden
               const previewImage = async () => {
                   const imgContainer = dialog.find('.image-preview-container img');
                   const fileInfo = dialog.find('.file-info');
                   
                   try {
                       // Für neue Uploads
                       if (file instanceof File) {
                           const reader = new FileReader();
                           reader.onload = (e) => {
                               imgContainer.attr('src', e.target.result);
                           };
                           reader.readAsDataURL(file);
                           
                           fileInfo.html(`
                               <strong>${t.fileInfo}:</strong> ${file.name}<br>
                               <strong>${t.fileSize}:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
                           `);
                       }
                       // Für existierende Dateien
                       else {
                           imgContainer.attr('src', '/media/' + file.source);
                           
                           const mediaFile = await fetch('/media/' + file.source);
                           const size = mediaFile.headers.get('content-length');
                           fileInfo.html(`
                               <strong>${t.fileInfo}:</strong> ${file.source}<br>
                               <strong>${t.fileSize}:</strong> ${(size / 1024 / 1024).toFixed(2)} MB
                           `);
                       }
                   } catch (error) {
                       console.error('Error loading preview:', error);
                       imgContainer.attr('src', '');
                   }
               };

               previewImage();

               // Fill existing metadata if available
               if (existingMetadata) {
                   dialog.find('[name="title"]').val(existingMetadata.title || '');
                   dialog.find('[name="alt"]').val(existingMetadata.alt || '');
                   dialog.find('[name="copyright"]').val(existingMetadata.copyright || '');
               }

               // Handle form submit
               dialog.find('form').on('submit', function(e) {
                   e.preventDefault();
                   const metadata = {
                       title: dialog.find('[name="title"]').val(),
                       alt: dialog.find('[name="alt"]').val(),
                       copyright: dialog.find('[name="copyright"]').val()
                   };
                   dialog.data('submitted', true);
                   resolve(metadata);
                   dialog.modal('hide');
               });

               // Bei Modal-Schließen oder Abbrechen Upload abbrechen
               dialog.on('hidden.bs.modal', function() {
                   if (!dialog.data('submitted')) {
                       reject(new Error('Metadata input cancelled'));
                   }
                   dialog.remove();
               });

               dialog.find('.btn-abort').on('click', function() {
                   dialog.modal('hide');
               });

               dialog.modal('show');
           });
       };

       // Existierende Dateien vorbereiten
       let existingFiles = [];
       if (initialValue) {
           existingFiles = initialValue.split(',')
               .filter(Boolean)
               .map(filename => {
                   const file = filename.trim().replace(/^"|"$/g, '');
                   debug.log('Processing existing file:', file);

                   const mediaUrl = '/media/' + file;
                   debug.log('Generated media URL:', mediaUrl);

                   return {
                       source: file,
                       options: {
                           type: 'local',
                           metadata: {
                               poster: mediaUrl
                           }
                       }
                   };
               });
           debug.log('Prepared existing files:', existingFiles);
       }

       // FilePond initialisieren
       const pond = FilePond.create(fileInput[0], {
           files: existingFiles,
           allowMultiple: true,
           maxFiles: input.data('filepondMaxfiles') || null,
           server: {
               url: 'index.php',
               process: async (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                   try {
                       const fileMetadata = await createMetadataDialog(file);
                       
                       const formData = new FormData();
                       formData.append(fieldName, file);
                       formData.append('rex-api-call', 'filepond_uploader');
                       formData.append('func', 'upload');
                       formData.append('category_id', input.data('filepondCat') || '1');
                       formData.append('metadata', JSON.stringify(fileMetadata));

                       const response = await fetch('index.php', {
                           method: 'POST',
                           headers: {
                               'X-Requested-With': 'XMLHttpRequest'
                           },
                           body: formData
                       });

                       const result = await response.json();

                       if (!response.ok) {
                           console.error('Upload error:', result);
                           error(result.error || 'Upload failed');
                           return;
                       }

                       load(result);
                   } catch (err) {
                       console.error('Upload cancelled:', err);
                       error('Upload cancelled');
                       abort();
                   }
               },
               revert: {
                   method: 'POST',
                   headers: {
                       'X-Requested-With': 'XMLHttpRequest'
                   },
                   ondata: (formData) => {
                       formData.append('rex-api-call', 'filepond_uploader');
                       formData.append('func', 'delete');
                       formData.append('filename', formData.get('serverId'));
                       return formData;
                   }
               },
               load: (source, load, error, progress, abort, headers) => {
                   debug.log('Loading file:', source);
                   
                   const url = '/media/' + source.replace(/^"|"$/g, '');
                   debug.log('Loading from URL:', url);
                   
                   fetch(url)
                       .then(response => {
                           if (!response.ok) {
                               throw new Error('HTTP error! status: ' + response.status);
                           }
                           return response.blob();
                       })
                       .then(blob => {
                           debug.log('File loaded successfully');
                           load(blob);
                       })
                       .catch(e => {
                           debug.log('Error loading file:', e);
                           error(e.message);
                       });
                   
                   return {
                       abort: () => {
                           debug.log('Load aborted');
                           abort();
                       }
                   };
               }
           },
           labelIdle: t.labelIdle,
           styleButtonRemoveItemPosition: 'right',
           styleLoadIndicatorPosition: 'right',
           styleProgressIndicatorPosition: 'right',
           styleButtonProcessItemPosition: 'right',
           imagePreviewHeight: 100,
           itemPanelAspectRatio: 1,
           acceptedFileTypes: (input.data('filepondTypes') || 'image/*').split(','),
           maxFileSize: (input.data('filepondMaxsize') || '10') + 'MB',
           credits: false
       });

       // Events loggen
       pond.on('initfile', (file) => {
           debug.log('File initialized:', file);
       });

       pond.on('processfile', (error, file) => {
           if (!error && file.serverId) {
               debug.log('File processed:', file.serverId);
               const currentValue = input.val() ? input.val().split(',').filter(Boolean) : [];
               if (!currentValue.includes(file.serverId)) {
                   currentValue.push(file.serverId);
                   const newValue = currentValue.join(',');
                   input.val(newValue);
                   debug.log('Updated input value:', newValue);
               }
           } else if (error) {
               debug.log('Process error:', error);
           }
       });

       pond.on('removefile', (error, file) => {
           if (!error) {
               debug.log('Removing file:', file.serverId || file.source);
               const currentValue = input.val() ? input.val().split(',').filter(Boolean) : [];
               const removeValue = file.serverId || file.source;
               const index = currentValue.indexOf(removeValue);
               if (index > -1) {
                   currentValue.splice(index, 1);
                   const newValue = currentValue.join(',');
                   input.val(newValue);
                   debug.log('Updated input value after remove:', newValue);
               }
           } else {
               debug.log('Remove error:', error);
           }
       });

       // FilePond Debug Events
       pond.on('warning', (error) => debug.log('Warning:', error));
       pond.on('error', (error) => debug.log('Error:', error));

       debug.log('FilePond instance created');
   });
});