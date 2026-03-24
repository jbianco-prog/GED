<?php
// src/Services/ClaudeAiService.php

class ClaudeAiService {

    /**
     * Analyse un extrait de texte et retourne un verdict de sensibilité.
     */
    public static function analyze(string $text, array $localResults = []): array {
        $result = [
            'is_sensitive' => false,
            'confidence'   => 0.0,
            'reasons'      => [],
            'categories'   => [],
            'error'        => null,
        ];

        if (empty(CLAUDE_API_KEY)) {
            $result['error'] = 'Claude API key not configured.';
            return $result;
        }

        $excerpt = mb_substr($text, 0, AI_TEXT_LIMIT);
        $localSummary = '';
        if (!empty($localResults['mots_cles_detectes'])) {
            $localSummary .= 'Mots-clés sensibles détectés localement : ' . implode(', ', $localResults['mots_cles_detectes']) . '. ';
        }
        if (!empty($localResults['cb_detectee'])) {
            $localSummary .= 'Numéro(s) de carte bancaire détecté(s) localement. ';
        }

        $prompt = <<<PROMPT
You are an enterprise document classification engine. Analyze the following text and determine whether it contains sensitive, confidential, or restricted information.

Local detection context already performed: {$localSummary}

Text to analyze:
---
{$excerpt}
---

Reply ONLY with a valid JSON object (no markdown, no explanation) in the following format:
{
  "is_sensitive": true|false,
  "confidence": 0.0 à 1.0,
  "reasons": ["raison 1", "raison 2"],
  "categories": ["personal data", "financial information", "trade secret", etc.]
}

Sensitivity criteria: explicit confidentiality mentions, personal data (names, addresses, numbers), financial or banking data, trade secrets, legal information, health data, passwords or technical secrets.
PROMPT;

        $payload = json_encode([
            'model'      => CLAUDE_MODEL,
            'max_tokens' => CLAUDE_MAX_TOKENS,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $result['error'] = 'cURL error: ' . $curlError;
            return $result;
        }
        if ($httpCode !== 200) {
            $result['error'] = "API Claude erreur HTTP $httpCode";
            return $result;
        }

        $body = json_decode($response, true);
        $content = $body['content'][0]['text'] ?? '';

        // Nettoyer le JSON (Claude peut parfois ajouter des backticks)
        $jsonStr = preg_replace('/```json?\s*/i', '', $content);
        $jsonStr = preg_replace('/```/', '', $jsonStr);
        $parsed  = json_decode(trim($jsonStr), true);

        if (!is_array($parsed)) {
            $result['error'] = 'Unparseable AI response: ' . substr($content, 0, 200);
            return $result;
        }

        $result['is_sensitive'] = (bool)($parsed['is_sensitive'] ?? false);
        $result['confidence']   = min(1.0, max(0.0, (float)($parsed['confidence'] ?? 0)));
        $result['reasons']      = (array)($parsed['reasons']    ?? []);
        $result['categories']   = (array)($parsed['categories'] ?? []);

        return $result;
    }

    /**
     * Génère un résumé thématique en exactement 3 mots clés.
     */
    public static function summarize(string $text): string {
        if (empty(CLAUDE_API_KEY) || empty(trim($text))) {
            return '';
        }

        $excerpt = mb_substr($text, 0, AI_TEXT_LIMIT);

        $prompt = <<<PROMPT
You are a document summarization engine. Analyze the following text and summarize its main theme in EXACTLY 3 keywords separated by commas. These 3 words must capture the essence of the document (topic, domain, nature).

Text:
---
{$excerpt}
---

Reply ONLY with the 3 words separated by commas, no trailing punctuation, no explanation, no quotes. Example: contract, legal, real-estate
PROMPT;

        $payload = json_encode([
            'model'      => CLAUDE_MODEL,
            'max_tokens' => 30,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return '';

        $body    = json_decode($response, true);
        $content = trim($body['content'][0]['text'] ?? '');

        // Nettoyer et valider : garder seulement les 3 premiers mots
        $content = preg_replace('/["""\'`]/', '', $content);
        $parts   = array_map('trim', explode(',', $content));
        $parts   = array_filter($parts, fn($p) => !empty($p));
        $parts   = array_slice(array_values($parts), 0, 3);

        return implode(', ', $parts);
    }
}
