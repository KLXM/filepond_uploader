<?php

/**
 * Metadaten-Verarbeitung für FilePond Uploads.
 *
 * Konvertiert Sprachdaten ins MetaInfo-Format und
 * verarbeitet zusätzliche MetaInfo-Felder.
 */
class filepond_metadata_handler
{
    /**
     * Konvertiert Frontend-Sprachdaten ins MetaInfo Lang Fields Format.
     * Frontend: {"de": "Text", "en": "Text"}
     * MetaInfo: [{"clang_id": 1, "value": "Text"}, {"clang_id": 2, "value": "Text"}]
     *
     * @param array<int|string, mixed> $fieldValue
     * @return list<array{clang_id: int, value: string}>
     */
    public function convertToMetaInfoLangFormat(array $fieldValue): array
    {
        $result = [];
        $languages = rex_clang::getAll();

        foreach ($fieldValue as $langCode => $value) {
            foreach ($languages as $clang) {
                if ($clang->getCode() === (string) $langCode) {
                    $result[] = [
                        'clang_id' => $clang->getId(),
                        'value' => (string) $value,
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Verarbeitet zusätzliche MetaInfo-Felder.
     *
     * @param array<string, mixed> $metadata
     */
    public function processAdditionalMetaInfoFields(rex_sql $sql, array $metadata): void
    {
        $additionalFields = ['med_description', 'med_title_lang', 'med_keywords', 'med_source'];

        foreach ($additionalFields as $fieldName) {
            if (isset($metadata[$fieldName])) {
                if (is_array($metadata[$fieldName])) {
                    $sanitizedArray = $this->sanitizeMetaInfoValue($metadata[$fieldName]);
                    if (is_array($sanitizedArray)) {
                        $langData = $this->convertToMetaInfoLangFormat($sanitizedArray);
                        $sql->setValue($fieldName, json_encode($langData));
                    }
                } else {
                    $sanitizedValue = $this->sanitizeMetaInfoValue($metadata[$fieldName]);
                    if (is_string($sanitizedValue)) {
                        $sql->setValue($fieldName, $sanitizedValue);
                    }
                }
            }
        }
    }

    /**
     * Sanitize a metadata value (string or array).
     *
     * @return array<int|string, mixed>|string
     */
    public function sanitizeMetaInfoValue(mixed $value): array|string
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = $this->sanitizeMetaInfoValue($v);
            }
            return $sanitized;
        }
        $sanitized = trim((string) $value);
        $sanitized = (string) preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $sanitized);
        $sanitized = (string) preg_replace('/javascript:/i', '', $sanitized);
        $sanitized = (string) preg_replace('/on\w+\s*=/i', '', $sanitized);
        return $sanitized;
    }
}
