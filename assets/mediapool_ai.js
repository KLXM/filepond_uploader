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

    var getButtonContent = function(label, isSpinning) {
        return getMagicIcon(isSpinning) + '<span class="filepond-ai-btn-label">' + label + '</span>';
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

        // Versteckte Inputs (z.B. von metainfo_lang_fields) überspringen
        if ($input.is('input[type="hidden"]') || $input.is(':hidden')) {
            return;
        }

        // Wenn das Feld innerhalb eines metainfo_lang_fields Containers liegt,
        // dort wird ein separater "alle Sprachen"-Button angehängt.
        if ($input.closest('.meta_lang_field_all, .meta_lang_field').length > 0) {
            return;
        }

        // Prüfen ob Button schon existiert
        if ($input.closest('.form-group').find('.btn-ai-generate-mp').length > 0) {
            return;
        }

        var inputName = $input.attr('name') || '';
        var btnHtml = '<button class="btn btn-default btn-ai-generate-mp" type="button" title="AI Alt-Text generieren" aria-label="AI Alt-Text generieren" data-lang="' + langCode + '" data-target-name="' + inputName.replace(/"/g, '&quot;') + '">' + getButtonContent('AI ALT', false) + '</button>';

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

    // Liefert die Sprachinputs eines metainfo_lang_fields Containers
    // ($container = .meta_lang_field_all oder .meta_lang_field)
    function collectLangInputs($container) {
        // "Alle Sprachen"-Modus: .meta_lang_field_input mit data-clang-id
        var $all = $container.find('.meta_lang_field_input');
        if ($all.length > 0) {
            return $all;
        }
        // Repeater-Modus: Existierende Übersetzungen in .meta_lang_translation_item
        return $container.find('.meta_lang_translation_item').find('.meta_lang_textarea, .meta_lang_input');
    }

    function addAiButtonForLangContainer($container) {
        if ($container.data('filepondAiAttached')) {
            return;
        }
        $container.data('filepondAiAttached', true);

        var btnHtml = '<button class="btn btn-default btn-ai-generate-mp-lang" type="button" title="AI Alt-Text generieren (alle Sprachen)" aria-label="AI Alt-Text generieren (alle Sprachen)">' + getButtonContent('AI ALT alle', false) + '</button>';
        var $wrap = $('<div class="filepond-ai-btn-wrap" style="margin: 4px 0 8px 0;"></div>').append(btnHtml);

        var $label = $container.find('> label.meta_lang_main_label').first();
        if ($label.length > 0) {
            $label.after($wrap);
        } else {
            $container.prepend($wrap);
        }
    }

    function attachButtonsForLangContainers(targetField) {
        var selector = '.meta_lang_field_all[data-field-name="' + targetField + '"], .meta_lang_field[data-field-name="' + targetField + '"]';
        $(selector).each(function() {
            addAiButtonForLangContainer($(this));
        });
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

    // Sprachen-Mapping (clang_id => code) für mehrsprachige Felder
    var languagesMap = window.filepondAiLanguagesMap || {};

    function resolveFileName() {
        var fileName = urlParams.get('file_name');
        if (!fileName) {
            fileName = $('input[name="file_name"]').val();
        }
        if (!fileName) {
            var action = $('form').first().attr('action');
            if (action && action.indexOf('file_name=') !== -1) {
                var match = action.match(/file_name=([^&]+)/);
                if (match) fileName = decodeURIComponent(match[1]);
            }
        }
        if (!fileName) {
            var fileHref = $('.form-control-static a[href*="/media/"]').first().attr('href') || '';
            if (fileHref !== '') {
                var cleanHref = fileHref.split('?')[0];
                var parts = cleanHref.split('/');
                fileName = decodeURIComponent(parts[parts.length - 1] || '');
            }
        }
        return fileName || '';
    }

    function generateForLanguage(fileName, langCode) {
        return $.ajax({
            url: '/redaxo/index.php',
            data: {
                'rex-api-call': 'filepond_ai_generate',
                'media_name': fileName,
                'language': langCode
            },
            dataType: 'json'
        });
    }

    function generateForLanguages(fileName, langCodes) {
        return $.ajax({
            url: '/redaxo/index.php',
            method: 'POST',
            traditional: true,
            data: {
                'rex-api-call': 'filepond_ai_generate',
                'media_name': fileName,
                'languages[]': langCodes
            },
            dataType: 'json'
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

            if (data.languages && typeof data.languages === 'object') {
                languagesMap = data.languages;
                window.filepondAiLanguagesMap = languagesMap;
            }

            attachButtonsForTarget(targetField);
            attachButtonsForLangContainers(targetField);
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

            var fileName = resolveFileName();
            if (!fileName) {
                alert('Dateiname konnte nicht ermittelt werden.');
                return;
            }

            // Loading State
            var originalIcon = btn.html();
            btn.prop('disabled', true).html(getButtonContent('AI ALT', true));

            generateForLanguage(fileName, lang)
                .done(function(data) {
                    if (data.success && data.alt_text) {
                        input.val(data.alt_text);
                        // Change Event triggern damit REDAXO merkt dass sich was geändert hat
                        input.trigger('change');
                    } else {
                        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error(xhr.responseText);
                    alert('Systemfehler: ' + error);
                })
                .always(function() {
                    btn.prop('disabled', false).html(originalIcon);
                });
        });

        // Handler für metainfo_lang_fields Container (alle Sprachen generieren)
        $(document).on('click', '.btn-ai-generate-mp-lang', function(e) {
            e.preventDefault();
            var btn = $(this);
            var $container = btn.closest('.meta_lang_field_all, .meta_lang_field');
            if ($container.length === 0) {
                return;
            }

            var $inputs = collectLangInputs($container);
            if ($inputs.length === 0) {
                alert('Keine Sprachfelder gefunden.');
                return;
            }

            var fileName = resolveFileName();
            if (!fileName) {
                alert('Dateiname konnte nicht ermittelt werden.');
                return;
            }

            var originalIcon = btn.html();
            btn.prop('disabled', true).html(getButtonContent('AI ALT alle', true));

            (async function() {
                try {
                    var groupedInputs = {};

                    for (var i = 0; i < $inputs.length; i++) {
                        var $input = $($inputs[i]);
                        var currentVal = ($input.val() || '').toString().trim();
                        if (currentVal !== '') {
                            continue;
                        }

                        var clangId = $input.data('clang-id') || $input.data('clangId') ||
                                      $input.closest('[data-clang-id]').data('clangId') ||
                                      $input.closest('[data-clang-id]').data('clang-id');
                        var langCode = 'de';
                        if (clangId && languagesMap[String(clangId)]) {
                            langCode = languagesMap[String(clangId)];
                        }

                        if (!groupedInputs[langCode]) {
                            groupedInputs[langCode] = [];
                        }
                        groupedInputs[langCode].push($input);
                    }

                    var languageCodes = Object.keys(groupedInputs);
                    if (languageCodes.length === 0) {
                        return;
                    }

                    var batchData = null;
                    try {
                        batchData = await generateForLanguages(fileName, languageCodes);
                    } catch (batchError) {
                        console.error('AI-Mehrsprachen-Fehler', batchError);
                    }

                    for (var j = 0; j < languageCodes.length; j++) {
                        var code = languageCodes[j];
                        var suggestion = '';

                        if (batchData && batchData.success && batchData.alt_texts && typeof batchData.alt_texts[code] === 'string') {
                            suggestion = batchData.alt_texts[code].trim();
                        }

                        if (suggestion === '') {
                            try {
                                var fallbackData = await generateForLanguage(fileName, code);
                                if (fallbackData && fallbackData.success && fallbackData.alt_text) {
                                    suggestion = String(fallbackData.alt_text).trim();
                                } else if (fallbackData && fallbackData.error) {
                                    console.error('AI-Fehler (' + code + '): ' + fallbackData.error);
                                }
                            } catch (fallbackError) {
                                console.error('AI-Fehler (' + code + ')', fallbackError);
                            }
                        }

                        if (suggestion !== '') {
                            groupedInputs[code].forEach(function($targetInput) {
                                $targetInput.val(suggestion);
                                $targetInput.trigger('input').trigger('change');
                            });
                        }
                    }
                } finally {
                    btn.prop('disabled', false).html(originalIcon);
                }
            })();
        });

        window.aiBtnHandlerBound = true;
    }
    }
})();
