<?php

/**
 * FilePond Upload API – Dispatcher.
 *
 * Nimmt API-Requests entgegen und delegiert an die spezialisierten Handler-Klassen:
 * - filepond_upload_handler  (Prepare, Upload, Chunk, Finalize)
 * - filepond_file_manager    (Delete, Load, Restore, Cleanup, Cancel)
 * - filepond_image_processor (Bildverarbeitung, EXIF, ImageMagick/GD)
 * - filepond_metadata_handler (Metadaten-Konvertierung, Sanitierung)
 */
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    private string $chunksDir;
    private string $metadataDir;
    private bool $debug = false;

    private filepond_upload_handler $uploadHandler;
    private filepond_file_manager $fileManager;

    public function __construct()
    {
        parent::__construct();

        $baseDir = rex_path::addonData('filepond_uploader', 'upload');

        $this->chunksDir = $baseDir . '/chunks';
        rex_dir::create($this->chunksDir);

        $this->metadataDir = $baseDir . '/metadata';
        rex_dir::create($this->metadataDir);

        // Handler-Instanzen erzeugen
        $imageProcessor = new filepond_image_processor($this->debug);
        $metadataHandler = new filepond_metadata_handler();
        $this->fileManager = new filepond_file_manager($this->chunksDir, $this->metadataDir, $this->debug);
        $this->uploadHandler = new filepond_upload_handler(
            $this->chunksDir,
            $this->metadataDir,
            $imageProcessor,
            $metadataHandler,
            $this->fileManager,
            $this->debug,
        );
    }

    /**
     * @return never
     */
    private function sendResponse(mixed $data, string $statusCode = '200'): void
    {
        rex_response::cleanOutputBuffers();
        if ('200' !== $statusCode) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    private function log(string $level, string $message): void
    {
        if ($this->debug) {
            $logger = rex_logger::factory();
            /** @phpstan-ignore psr3.interpolated */
            $logger->log($level, 'FILEPOND: {message}', ['message' => $message]);
        }
    }

    public function execute(): rex_api_result
    {
        try {
            $this->log('info', 'Starting execute()');

            if (!$this->isAuthorized()) {
                throw new rex_api_exception('Unauthorized access');
            }

            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);

            match ($func) {
                'prepare' => $this->sendResponse($this->uploadHandler->handlePrepare()),
                'upload' => $this->sendResponse($this->uploadHandler->handleUpload($categoryId)),
                'chunk-upload' => $this->uploadHandler->handleChunkUpload($categoryId),
                'finalize-upload' => $this->sendResponse($this->uploadHandler->handleFinalizeUpload($categoryId)),
                'delete' => $this->fileManager->handleDelete(),
                'cancel-upload' => $this->sendResponse($this->fileManager->handleCancelUpload()),
                'load' => $this->fileManager->handleLoad(),
                'restore' => $this->fileManager->handleRestore(),
                'cleanup' => $this->sendResponse($this->handleCleanup()),
                default => throw new rex_api_exception('Invalid function: ' . $func),
            };
        } catch (Exception $e) {
            rex_logger::logException($e);
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_FORBIDDEN);
        }

        return new rex_api_result(true);
    }

    /**
     * Prüft ob der aktuelle Nutzer berechtigt ist (Backend, Token oder YCom).
     */
    private function isAuthorized(): bool
    {
        $this->log('info', 'Checking authorization');

        // Backend User Check
        $user = rex_backend_login::createUser();
        $isBackendUser = null !== $user;
        $this->log('info', 'isBackendUser = ' . ($isBackendUser ? 'true' : 'false'));

        // Token Check
        $apiToken = rex_config::get('filepond_uploader', 'api_token');
        $apiTokenStr = is_string($apiToken) ? $apiToken : '';
        $requestToken = rex_request('api_token', 'string', '');
        $sessionToken = rex_session('filepond_token', 'string', '');

        $isValidToken = ('' !== $apiTokenStr && '' !== $requestToken && hash_equals($apiTokenStr, $requestToken))
            || ('' !== $apiTokenStr && '' !== $sessionToken && hash_equals($apiTokenStr, $sessionToken));

        // YCom Check
        $isYComUser = false;
        if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
            /** @phpstan-ignore class.notFound */
            if (null !== rex_ycom_auth::getUser()) {
                $isYComUser = true;
            }
        }

        $authorized = $isBackendUser || $isValidToken || $isYComUser;

        if (!$authorized) {
            $this->log('error', 'Unauthorized - no YCom login, no Backend login, invalid API token');
        }

        return $authorized;
    }

    /**
     * Proxy für Cleanup – wird auch über func=cleanup aufgerufen.
     *
     * @return array<string, mixed>
     */
    public function handleCleanup(): array
    {
        return $this->fileManager->handleCleanup();
    }
}
