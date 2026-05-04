$(document).on('rex:ready', function() {
    initAiButtons();
});

// Auch beim initialen Laden ausführen
$(function() {
    initAiButtons();
});

function initAiButtons() {
    // Nur auf der Medienpool-Detailseite ausführen
    if (!$('body').hasClass('rex-page-mediapool-media')) {
        return;
    }
    
    // Funktion zum Hinzufügen des AI-Buttons
    function addAiButton(inputField, langCode) {
        var $input = $(inputField);
        
        // Prüfen ob Button schon existiert
        if ($input.parent().find('.btn-ai-generate-mp').length > 0) {
            return;
        }
        
        // Button HTML
        var btnHtml = '<span class="input-group-btn">' +
            '<button class="btn btn-default btn-ai-generate-mp" type="button" title="AI Alt-Text generieren" data-lang="' + langCode + '">' +
            '<i class="fa fa-magic"></i>' +
            '</button>' +
            '</span>';

        // Input in Input-Group wrappen falls noch nicht geschehen
        if (!$input.parent().hasClass('input-group')) {
            $input.wrap('<div class="input-group"></div>');
        }
        
        $input.after(btnHtml);
    }

    // 1. Suche nach med_alt (Standard Metainfo)
    var mainInput = $('input[name="rex_media[med_alt]"], textarea[name="rex_media[med_alt]"]');
    if (mainInput.length > 0) {
        addAiButton(mainInput, 'de'); 
    }

    // 2. Suche nach med_description (Beschreibung)
    var descInput = $('input[name="rex_media[med_description]"], textarea[name="rex_media[med_description]"]');
    if (descInput.length > 0) {
        addAiButton(descInput, 'de');
    }

    // 3. Suche nach allen Feldern, die wie Alt-Text oder Beschreibung aussehen (Multilang)
    $('input[type="text"], textarea').each(function() {
        var name = $(this).attr('name');
        if (!name) return;
        
        // Ignoriere Systemfelder ohne rex_media
        if (name.indexOf('rex_media') === -1) return;

        // Suche nach Sprach-Suffixen (z.B. _en, _fr)
        // Muster: rex_media[med_alt_en] oder rex_media[med_description_fr]
        var langMatch = name.match(/_([a-z]{2})\]$/); // Endet auf _en]
        
        // Prüfen ob es ein relevantes Feld ist (alt, desc, caption, title)
        var isRelevant = name.indexOf('alt') !== -1 || name.indexOf('desc') !== -1 || name.indexOf('caption') !== -1;
        
        if (langMatch && isRelevant) {
            addAiButton(this, langMatch[1]);
        }
    });

    // Click Handler (nur einmal binden)
    if (!window.aiBtnHandlerBound) {
        $(document).on('click', '.btn-ai-generate-mp', function(e) {
            e.preventDefault();
            var btn = $(this);
            var input = btn.closest('.input-group').find('input, textarea');
            var lang = btn.data('lang');
            
            // Dateinamen aus URL holen
            var urlParams = new URLSearchParams(window.location.search);
            var fileName = urlParams.get('file_name');
            
            if (!fileName) {
                // Versuche Dateinamen aus dem Formular zu holen (Hidden Field)
                fileName = $('input[name="file_name"]').val();
            }
            
            if (!fileName) {
                // Fallback: Versuche es aus dem Formular action
                var action = $('form#rex-form-mediapool-media').attr('action');
                if (action && action.indexOf('file_name=') !== -1) {
                    var match = action.match(/file_name=([^&]+)/);
                    if (match) fileName = match[1];
                }
            }

            if (!fileName) {
                alert('Dateiname konnte nicht ermittelt werden.');
                return;
            }

            // Loading State
            var originalIcon = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            // API Call
            $.ajax({
                url: 'index.php',
                data: {
                    'rex-api-call': 'filepond_ai_generate',
                    'media_name': fileName,
                    'language': lang
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.alt_text) {
                        input.val(data.alt_text);
                        // Change Event triggern damit REDAXO merkt dass sich was geändert hat
                        input.trigger('change');
                    } else {
                        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    alert('Systemfehler: ' + error);
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalIcon);
                }
            });
        });
        window.aiBtnHandlerBound = true;
    }
}
