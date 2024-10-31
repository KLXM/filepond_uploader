<?php

class rex_api_yform_filepond extends rex_api_function
{
    protected $published = true;

    public function getPath() {
        return rex_path::pluginData(
            'yform', 
            'manager', 
            'upload/filepond/'.
            rex_request("formId", 'string', 'public')."/".
            rex_request("fieldId", 'string', 'all')."/".
            rex_request("uniqueKey", 'string', 'public').'/'
        );
    }

    public function getAllowedExtensions() {
        return explode(
            ",",
            rex_session('rex_yform_filepond')
            [rex_request("formId")]
            [rex_request("fieldId")]
            [rex_request("uniqueKey")]
            ["allowed_types"]
        );
    }

    public function getAllowedSizePerFile() {
        return rex_session('rex_yform_filepond')
            [rex_request("formId")]
            [rex_request("fieldId")]
            [rex_request("uniqueKey")]
            ["allowed_filesize"];
    }

    function execute()
    {
        $func = rex_request('func', 'string', '');
        
        if ($func == 'upload') {
            return $this->executeUpload();
        } 
        else if ($func == 'delete') {
            $serverId = rex_request('serverId', 'string', '');
            return $this->executeDelete($serverId);
        }
    }

    protected function executeUpload() 
    {
        // Prüfen ob eine Datei hochgeladen wurde
        if (!rex_request::files('filepond', 'array', false)) {
            return $this->error("Keine Datei hochgeladen");
        }

        // Upload-Verzeichnis erstellen
        $uploadPath = $this->getPath();
        rex_dir::create($uploadPath);

        // Datei-Informationen
        $file = rex_request::files('filepond');
        $tempFile = $file['tmp_name'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Eindeutigen Dateinamen generieren
        $uniqueName = uniqid() . '_' . rex_string::normalize($originalName);
        $targetPath = $uploadPath . $uniqueName;

        // Validierung
        if (!$this->validateFile($fileExt, $fileSize)) {
            return $this->error("Datei entspricht nicht den Vorgaben");
        }

        // Datei verschieben
        if (move_uploaded_file($tempFile, $targetPath)) {
            // Erfolg - ServerId zurückgeben
            return $this->success($uniqueName);
        }

        return $this->error("Upload fehlgeschlagen");
    }

    protected function executeDelete($serverId)
    {
        if (!$serverId) {
            return $this->error("Keine Datei angegeben");
        }

        $filePath = $this->getPath() . $serverId;

        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                return $this->success();
            }
            return $this->error("Löschen fehlgeschlagen");
        }

        return $this->error("Datei nicht gefunden");
    }

    protected function validateFile($fileExt, $fileSize) 
    {
        // Dateityp prüfen
        if (!in_array(".".$fileExt, $this->getAllowedExtensions())) {
            return false;
        }

        // Dateigröße prüfen
        if ($fileSize > $this->getAllowedSizePerFile()) {
            return false;
        }

        return true;
    }

    protected function success($data = null) 
    {
        if ($data) {
            return rex_response::sendJson($data);
        }
        rex_response::setStatus(rex_response::HTTP_OK);
        return rex_response::sendJson(['status' => 'success']);
    }

    protected function error($message) 
    {
        rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
        return rex_response::sendJson([
            'status' => 'error',
            'message' => $message
        ]);
    }
}
