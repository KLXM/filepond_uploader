<?php

namespace FriendsOfRedaxo\FilePond;

use filepond_ai_alt_generator;
use rex_config;
use rex_file;
use rex_path;

/**
 * Gemini-Modelle live von der Google-API (GET /v1beta/models) statt einer festen
 * Liste, die bei jedem Modellwechsel von Google veraltet. Ergebnis wird je API-Key
 * 24 Stunden zwischengespeichert. Ohne Key oder ohne Verbindung greift die
 * Standardliste filepond_ai_alt_generator::GEMINI_MODELS.
 */
final class GeminiModelCatalog
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1000&key=';
    private const CACHE_TTL = 86400;

    /** Modelle, die keinen Bild-zu-Text-Aufruf koennen bzw. fuer Alt-Texte ungeeignet sind. */
    private const EXCLUDE_PATTERN = '/(embedding|tts|image|audio|live|transcribe|customtools|computer-use|robotics|aqa|learnlm)/i';

    /** @var array{key_hash: string, fetched_at: int, models: array<string, string>, all_ids: list<string>}|false|null false = geprueft, kein gueltiger Cache */
    private static array|false|null $memo = null;

    /**
     * Modelle fuer die Auswahl: Cache, sonst live abfragen, sonst Standardliste.
     *
     * @return array<string, string> id => Bezeichnung
     */
    public static function getModels(): array
    {
        $catalog = self::readCache() ?? self::refresh();

        return null !== $catalog ? $catalog['models'] : filepond_ai_alt_generator::GEMINI_MODELS;
    }

    /**
     * Live-Liste aktiv? (false = Standardliste, z. B. ohne API-Key)
     */
    public static function isLive(): bool
    {
        return null !== self::readCache();
    }

    public static function getFetchedAt(): ?int
    {
        $catalog = self::readCache();

        return null !== $catalog ? $catalog['fetched_at'] : null;
    }

    /**
     * Fragt die Modelle neu bei Google ab und speichert sie. Null bei fehlendem Key oder Fehler.
     *
     * @return array{key_hash: string, fetched_at: int, models: array<string, string>, all_ids: list<string>}|null
     */
    public static function refresh(): ?array
    {
        $apiKey = self::getApiKey();
        if ('' === $apiKey) {
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::ENDPOINT . rawurlencode($apiKey),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_string($response) || 200 !== $httpCode) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
            return null;
        }

        $parsed = self::parse($data['models']);
        if ([] === $parsed['models']) {
            return null;
        }

        $catalog = [
            'key_hash' => self::hashKey($apiKey),
            'fetched_at' => time(),
            'models' => $parsed['models'],
            'all_ids' => $parsed['all_ids'],
        ];
        rex_file::putCache(self::getCacheFile(), $catalog);
        self::$memo = $catalog;

        return $catalog;
    }

    /**
     * Filtert und sortiert die API-Antwort (public fuer Tests).
     *
     * @param array<mixed> $apiModels Eintraege aus "models" der API-Antwort
     * @return array{models: array<string, string>, all_ids: list<string>}
     */
    public static function parse(array $apiModels): array
    {
        $models = [];
        $allIds = [];
        foreach ($apiModels as $entry) {
            if (!is_array($entry) || !isset($entry['name']) || !is_string($entry['name'])) {
                continue;
            }
            $id = str_starts_with($entry['name'], 'models/') ? substr($entry['name'], 7) : $entry['name'];
            $allIds[] = $id;

            $methods = isset($entry['supportedGenerationMethods']) && is_array($entry['supportedGenerationMethods'])
                ? $entry['supportedGenerationMethods']
                : [];
            if (!str_starts_with($id, 'gemini-')
                || !in_array('generateContent', $methods, true)
                || 1 === preg_match(self::EXCLUDE_PATTERN, $id)
            ) {
                continue;
            }

            $label = isset($entry['displayName']) && is_string($entry['displayName']) && '' !== trim($entry['displayName'])
                ? trim($entry['displayName'])
                : $id;
            $models[$id] = $label . ' (' . $id . ')';
        }

        uksort($models, static function (string $a, string $b): int {
            // Neueste Version zuerst, innerhalb einer Version stabile vor Preview/Experimental.
            $cmp = version_compare(self::versionOf($b), self::versionOf($a));
            if (0 !== $cmp) {
                return $cmp;
            }
            $cmp = self::isPreview($a) <=> self::isPreview($b);

            return 0 !== $cmp ? $cmp : strcmp($a, $b);
        });

        return ['models' => $models, 'all_ids' => $allIds];
    }

    /**
     * Empfohlenes Modell aus einer Liste: neuestes stabiles "gemini-X.Y-flash",
     * sonst das erste Modell, sonst der feste Standard.
     *
     * @param array<string, string> $models
     */
    public static function pickDefault(array $models): string
    {
        foreach (array_keys($models) as $id) {
            if (1 === preg_match('/^gemini-\d+(\.\d+)?-flash$/', $id)) {
                return $id;
            }
        }

        $first = array_key_first($models);

        return null !== $first ? $first : filepond_ai_alt_generator::DEFAULT_GEMINI_MODEL;
    }

    /**
     * Alle von Google gelieferten Modell-IDs (ungefiltert) aus dem Cache, null ohne Live-Liste.
     *
     * @return list<string>|null
     */
    public static function getKnownIds(): ?array
    {
        $catalog = self::readCache();

        return null !== $catalog ? $catalog['all_ids'] : null;
    }

    /**
     * Empfohlenes Modell der Live-Liste, null ohne Live-Liste.
     */
    public static function getLiveDefault(): ?string
    {
        $catalog = self::readCache();

        return null !== $catalog ? self::pickDefault($catalog['models']) : null;
    }

    /**
     * @return array{key_hash: string, fetched_at: int, models: array<string, string>, all_ids: list<string>}|null
     */
    private static function readCache(): ?array
    {
        if (null !== self::$memo) {
            return false === self::$memo ? null : self::$memo;
        }
        self::$memo = false;

        $apiKey = self::getApiKey();
        if ('' === $apiKey) {
            return null;
        }

        $catalog = rex_file::getCache(self::getCacheFile(), null);
        if (!is_array($catalog)
            || ($catalog['key_hash'] ?? null) !== self::hashKey($apiKey)
            || !is_int($catalog['fetched_at'] ?? null)
            || (time() - $catalog['fetched_at']) > self::CACHE_TTL
            || !is_array($catalog['models'] ?? null)
            || !is_array($catalog['all_ids'] ?? null)
        ) {
            return null;
        }

        /** @var array{key_hash: string, fetched_at: int, models: array<string, string>, all_ids: list<string>} $catalog */
        self::$memo = $catalog;

        return $catalog;
    }

    private static function getApiKey(): string
    {
        $key = rex_config::get('filepond_uploader', 'gemini_api_key', '');

        return is_string($key) ? trim($key) : '';
    }

    private static function hashKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    private static function getCacheFile(): string
    {
        return rex_path::addonCache('filepond_uploader', 'gemini_models.json');
    }

    private static function versionOf(string $id): string
    {
        return 1 === preg_match('/^gemini-(\d+(?:\.\d+)?)/', $id, $m) ? $m[1] : '0';
    }

    private static function isPreview(string $id): int
    {
        return 1 === preg_match('/(preview|exp|latest)/', $id) ? 1 : 0;
    }
}
