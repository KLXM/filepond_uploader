<?php

interface filepond_ai_provider_interface
{
    /**
     * Gibt den Key des Providers zurück (z.B. 'gemini')
     */
    public function getKey(): string;
    
    /**
     * Gibt den Anzeigenamen des Providers zurück
     */
    public function getLabel(): string;
    
    /**
     * Prüft, ob der Provider korrekt konfiguriert und einsatzbereit ist
     */
    public function isConfigured(): bool;
    
    /**
     * Generiert einen Alt-Text für das übergebene Bild
     * 
     * @param string $base64Image Base64 kodierter Bild-String (ohne Prefix)
     * @param string $mimeType Mime-Type des Bildes
     * @param string $prompt Der Prompt für die Generierung
     * @param int $maxTokens Maximale Anzahl Tokens
     * @return array{text: string, tokens: ?array{prompt: int, response: int, total: int}}
     * @throws Exception
     */
    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array;
    
    /**
     * Testet die Verbindung zur API
     * 
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;
}
