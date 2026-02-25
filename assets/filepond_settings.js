/**
 * FilePond Settings Page – Dateitypen-Sync
 *
 * Synchronisiert die Accordion-Checkboxen und das Freitext-Feld
 * mit dem sichtbaren readonly Textfeld #filepond-allowed-types.
 */
$(document).on('rex:ready', function () {
    var $input = $('#filepond-allowed-types');
    var $custom = $('#filepond-custom-types');

    if (!$input.length) {
        return;
    }

    function syncToInput() {
        var types = [];

        $('.filepond-type-cb:checked').each(function () {
            types.push($(this).val());
        });

        // Eigene MIME-Types / Endungen hinzufügen
        if ($custom.length && $.trim($custom.val())) {
            var extra = $custom.val().split(',');
            for (var i = 0; i < extra.length; i++) {
                var t = $.trim(extra[i]);
                if (t && types.indexOf(t) === -1) {
                    types.push(t);
                }
            }
        }

        $input.val(types.join(','));

        // Badge-Zähler in den Akkordeon-Headern aktualisieren
        $('#filepond-types-accordion .panel').each(function () {
            var count = $(this).find('.filepond-type-cb:checked').length;
            var $badge = $(this).find('.panel-title .badge');
            if (count > 0) {
                if ($badge.length) {
                    $badge.text(count);
                } else {
                    $(this).find('.panel-title a').append(' <span class="badge">' + count + '</span>');
                }
            } else {
                $badge.remove();
            }
        });
    }

    // Wildcard-Logik: image/* deaktiviert/aktiviert einzelne image/-Checkboxen
    $(document).on('change', '.filepond-type-cb', function () {
        var val = $(this).val();
        if (val.indexOf('/*') !== -1) {
            var prefix = val.split('/*')[0] + '/';
            var isChecked = $(this).is(':checked');
            $(this).closest('.panel-body').find('.filepond-type-cb').each(function () {
                var cbVal = $(this).val();
                if (cbVal !== val && cbVal.indexOf(prefix) === 0) {
                    $(this).prop('checked', false).prop('disabled', isChecked);
                }
            });
        }
        syncToInput();
    });

    if ($custom.length) {
        $custom.on('input', syncToInput);
    }

    // Initial: Wildcards prüfen und einzelne Checkboxen deaktivieren
    $('.filepond-type-cb:checked').each(function () {
        var val = $(this).val();
        if (val.indexOf('/*') !== -1) {
            var prefix = val.split('/*')[0] + '/';
            $(this).closest('.panel-body').find('.filepond-type-cb').each(function () {
                var cbVal = $(this).val();
                if (cbVal !== val && cbVal.indexOf(prefix) === 0) {
                    $(this).prop('checked', false).prop('disabled', true);
                }
            });
        }
    });
});
