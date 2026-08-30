/**
 * Registriert filepond_uploader als MediaPlace-Upload-Anbieter (siehe
 * MEDIAPLACE_UPLOAD_PROVIDERS in boot.php + MP3.registerUploadProvider()
 * im mediaplace-Addon): MediaPlace's eigener Upload-Button/Drag&Drop/Paste
 * uebergibt Dateien hierher statt an seinen eingebauten Upload-Flow, sobald
 * "FilePond" als Upload-Anbieter in MediaPlace's Einstellungen gewaehlt ist.
 *
 * Nutzt dafuer ein dauerhaftes, verstecktes FilePond-Widget (siehe
 * boot.php, #filepond-mp3-upload-provider), das initFilePond() bereits wie
 * jedes andere data-widget="filepond"-Element beim normalen rex:ready/
 * DOMContentLoaded-Scan initialisiert hat -- KEIN eigener Nachbau der
 * Upload-/Metadaten-Dialog-Logik, addFiles() speist die Dateien einfach in
 * dieselbe, bereits bestehende FilePond-Pipeline (server.process ruft dort
 * pro Datei createMetadataDialog() auf, bevor irgendein Byte gesendet wird).
 */
(function () {
    'use strict';

    var INPUT_ID = 'filepond-mp3-upload-provider';
    var pondListenerAttached = false;
    var pendingOnDone = null;

    function waitForMP3(cb, attemptsLeft) {
        if (window.MP3 && typeof window.MP3.registerUploadProvider === 'function') {
            cb();
            return;
        }
        if (attemptsLeft <= 0) return;
        setTimeout(function () { waitForMP3(cb, attemptsLeft - 1); }, 100);
    }

    function getPondInstance() {
        return (window.FilePondGlobal && window.FilePondGlobal.instances)
            ? window.FilePondGlobal.instances[INPUT_ID]
            : null;
    }

    function withPondInstance(cb, attemptsLeft) {
        var pond = getPondInstance();
        if (pond) {
            cb(pond);
            return;
        }
        if (attemptsLeft <= 0) {
            throw new Error('filepond_uploader: FilePond-Instanz "' + INPUT_ID + '" wurde nicht rechtzeitig initialisiert.');
        }
        setTimeout(function () { withPondInstance(cb, attemptsLeft - 1); }, 100);
    }

    function handleMediaplaceUpload(payload) {
        var files = payload && payload.files;
        var catId = payload && payload.catId;
        var onDone = payload && payload.onDone;
        if (!files || !files.length) return;

        var input = document.getElementById(INPUT_ID);
        if (!input) {
            throw new Error('filepond_uploader: #' + INPUT_ID + ' nicht im DOM gefunden.');
        }
        input.setAttribute('data-filepond-cat', String(catId != null ? catId : 0));
        input.dataset.filepondCat = String(catId != null ? catId : 0);

        withPondInstance(function (pond) {
            if (!pondListenerAttached) {
                pondListenerAttached = true;
                // Feuert, sobald ALLE gerade per addFiles() hinzugefuegten Dateien
                // fertig verarbeitet (hochgeladen) sind -- ein Listener reicht, da
                // pendingOnDone bei jedem neuen Aufruf einfach ueberschrieben wird
                // (MediaPlace ruft diesen Handler ohnehin nie zwei Mal parallel auf,
                // die Instanz bleibt aber ueber mehrere Upload-Vorgaenge hinweg bestehen).
                pond.on('processfiles', function () {
                    var cb = pendingOnDone;
                    pendingOnDone = null;
                    if (typeof cb === 'function') cb();
                });
            }
            pendingOnDone = onDone;
            pond.addFiles(Array.prototype.slice.call(files));
        }, 50);
    }

    waitForMP3(function () {
        window.MP3.registerUploadProvider('filepond', handleMediaplaceUpload);
    }, 100);
})();
