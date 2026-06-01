(function() {
    if (typeof window.jQuery === 'undefined') {
        return;
    }

    var $ = window.jQuery;

    $(document).on('rex:ready', function() {
        initAiButtons();
    });

    // Auch beim initialen Laden ausführen
    $(function() {
        initAiButtons();
    });

    function initAiButtons() {
    var magicIconUrl = window.location.origin + '/assets/addons/filepond_uploader/icons/magic.svg';
    var getMagicIcon = function(isSpinning) {
        var spinClass = isSpinning ? ' filepond-magic-icon--spin' : '';
        return '<img src="' + magicIconUrl + '" class="filepond-magic-icon' + spinClass + '" alt="" aria-hidden="true">';
    };

    // Nur auf der echten Medienpool-Detailseite ausführen, nicht auf Unterseiten wie mediapool/cropper
    var urlParams = new URLSearchParams(window.location.search);
    var currentPage = urlParams.get('page') || '';
    var hasFileId = $('form input[name="file_id"]').length > 0;

    if (currentPage !== 'mediapool/media' || !hasFileId) {
        return;
    }

    function addAiButton(inputField, langCode) {
        var $input = $(inputField);

        // Prüfen ob Button schon existiert
        if ($input.closest('.form-group').find('.btn-ai-generate-mp').length > 0) {
            return;
        }

        var inputName = $input.attr('name') || '';
        var btnHtml = '<button class="btn btn-default btn-ai-generate-mp" type="button" title="AI-Text generieren" data-lang="' + langCode + '" data-target-name="' + inputName.replace(/"/g, '&quot;') + '">' + getMagicIcon(false) + '</button>';

        if ($input.is('textarea')) {
            var $wrap = $('<div class="filepond-ai-btn-wrap" style="margin-top:6px;"></div>');
            $wrap.append(btnHtml);
            $input.after($wrap);
            return;
        }

        if (!$input.parent().hasClass('input-group')) {
            $input.wrap('<div class="input-group"></div>');
        }

        $input.after('<span class="input-group-btn">' + btnHtml + '</span>');
    }

    function getInputsByExactName(name) {
        return $('input[type="text"], textarea').filter(function() {
            return ($(this).attr('name') || '') === name;
        });
    }

    function resolveLanguageFromName(fieldName) {
        var match = fieldName.match(/_([a-z]{2})(?:_[a-z]{2})?\]$/i);
        if (match && match[1]) {
            return match[1].toLowerCase();
        }
        return 'de';
    }

    function attachButtonsForTarget(targetField) {
        var selector = [
            'input[name="' + targetField + '"]',
            'textarea[name="' + targetField + '"]',
            'input[name^="' + targetField + '_"]',
            'textarea[name^="' + targetField + '_"]',
            'input[name="rex_media[' + targetField + ']"]',
            'textarea[name="rex_media[' + targetField + ']"]',
            'input[name^="rex_media[' + targetField + '_"]',
            'textarea[name^="rex_media[' + targetField + '_"]'
        ].join(', ');
        var $targets = $(selector);
        if ($targets.length === 0) {
            return;
        }

        $targets.each(function() {
            var name = $(this).attr('name') || '';
            var lang = resolveLanguageFromName(name);
            addAiButton(this, lang);
        });
    }

    // Konfiguration aus API laden (gleiches Addon/API wie Upload-Modal)
    $.ajax({
        url: '/redaxo/index.php',
        dataType: 'json',
        data: {
            'rex-api-call': 'filepond_auto_metainfo',
            'action': 'get_ai_target_field'
        },
        success: function(data) {
            if (!data || !data.success || !data.enabled) {
                return;
            }

            var targetField = typeof data.target_field === 'string' && data.target_field.trim() !== ''
                ? data.target_field.trim()
                : 'med_alt';

            attachButtonsForTarget(targetField);
        }
    });

    // Click Handler (nur einmal binden)
    if (!window.aiBtnHandlerBound) {
        $(document).on('click', '.btn-ai-generate-mp', function(e) {
            e.preventDefault();
            var btn = $(this);
            var targetName = btn.attr('data-target-name') || '';
            var input = targetName !== ''
                ? getInputsByExactName(targetName).first()
                : btn.closest('.form-group').find('input[type="text"], textarea').first();
            var lang = btn.data('lang');
            
            // Dateinamen aus URL holen
            var fileName = urlParams.get('file_name');
            
            if (!fileName) {
                // Versuche Dateinamen aus dem Formular zu holen (Hidden Field)
                fileName = $('input[name="file_name"]').val();
            }
            
            if (!fileName) {
                // Fallback: Versuche es aus dem Formular action
                var action = $('form').first().attr('action');
                if (action && action.indexOf('file_name=') !== -1) {
                    var match = action.match(/file_name=([^&]+)/);
                    if (match) fileName = decodeURIComponent(match[1]);
                }
            }

            if (!fileName) {
                // Fallback: Dateiname aus dem Dateilink im Detailbereich lesen
                var fileHref = $('.form-control-static a[href*="/media/"]').first().attr('href') || '';
                if (fileHref !== '') {
                    var cleanHref = fileHref.split('?')[0];
                    var parts = cleanHref.split('/');
                    fileName = decodeURIComponent(parts[parts.length - 1] || '');
                }
            }

            if (!fileName) {
                alert('Dateiname konnte nicht ermittelt werden.');
                return;
            }

            // Loading State
            var originalIcon = btn.html();
            btn.prop('disabled', true).html(getMagicIcon(true));

            // API Call
            $.ajax({
                url: '/redaxo/index.php',
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
})();
