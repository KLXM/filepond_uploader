<?php

abstract class filepond_ai_provider_abstract implements filepond_ai_provider_interface
{
    /**
     * Helper für cURL-Error-Handling.
     */
    protected function handleCurlError(CurlHandle $ch, string|false $response = false): void
    {
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        if ('' !== $error || 0 !== $errno) {
            curl_close($ch);
            throw new Exception('cURL Error #' . $errno . ': ' . $error);
        }

        if (false === $response) {
            curl_close($ch);
            throw new Exception('Empty response from API');
        }
    }

    /**
     * Bereinigt den generierten Text (Quotes entfernen etc.).
     */
    protected function cleanText(string $text): string
    {
        return trim(trim($text), '"\'');
    }
}
