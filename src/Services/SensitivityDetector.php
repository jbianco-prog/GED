<?php
// src/Services/SensitivityDetector.php

class SensitivityDetector {

    /**
     * Analyse le texte brut et retourne les résultats de détection locale.
     */
    public static function analyze(string $text): array {
        $result = [
            'mots_cles_detectes' => [],
            'cb_detectee'        => false,
            'nombre_cb'          => 0,
            'cb_masquees'        => [],
            'niveau_local'       => 'non_sensible',
            'raisons'            => [],
        ];

        // ── 1. Détection mots-clés ────────────────────────────────────────────
        $textLower = mb_strtolower($text, 'UTF-8');
        foreach (SENSITIVE_KEYWORDS as $keyword) {
            if (mb_strpos($textLower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                $result['mots_cles_detectes'][] = $keyword;
            }
        }

        // ── 2. Détection numéros CB (Luhn) ────────────────────────────────────
        // Accepte : 16 chiffres, avec ou sans espaces/tirets
        $pattern = '/\b(?:\d{4}[\s\-]?){3}\d{4}\b/';
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $rawNumber) {
                $digits = preg_replace('/\D/', '', $rawNumber);
                if (strlen($digits) === 16 && self::luhn($digits)) {
                    $result['cb_detectee'] = true;
                    $result['nombre_cb']++;
                    // Masquer : ne jamais stocker/afficher le numéro complet
                    $result['cb_masquees'][] = '**** **** **** ' . substr($digits, -4);
                }
            }
        }

        // ── 3. Calcul du niveau de sensibilité local ──────────────────────────
        $hasTopSecret = false;
        foreach ($result['mots_cles_detectes'] as $kw) {
            if (in_array(mb_strtolower($kw), ['top secret','très secret','tres secret','strictly confidential'])) {
                $hasTopSecret = true;
                break;
            }
        }

        if ($result['cb_detectee'] || $hasTopSecret) {
            $result['niveau_local'] = 'sensible_eleve';
        } elseif (!empty($result['mots_cles_detectes'])) {
            $result['niveau_local'] = 'sensible';
        }

        // ── 4. Construction des raisons ───────────────────────────────────────
        if (!empty($result['mots_cles_detectes'])) {
            $result['raisons'][] = 'Mot(s)-clé(s) sensible(s) : ' . implode(', ', $result['mots_cles_detectes']);
        }
        if ($result['cb_detectee']) {
            $result['raisons'][] = $result['nombre_cb'] . ' numéro(s) de carte bancaire détecté(s)';
        }

        return $result;
    }

    /**
     * Algorithme de Luhn.
     * Retourne true si le numéro à 16 chiffres est valide.
     */
    public static function luhn(string $number): bool {
        $digits = str_split(strrev($number));
        $sum    = 0;
        foreach ($digits as $i => $digit) {
            $n = (int)$digit;
            if ($i % 2 === 1) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
        }
        return $sum % 10 === 0;
    }
}
