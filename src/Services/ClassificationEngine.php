<?php
// src/Services/ClassificationEngine.php

class ClassificationEngine {

    /**
     * Lance le pipeline complet d'analyse pour un fichier donné.
     * Retourne le tableau de résultats à stocker via FileModel::saveAnalysis().
     */
    public static function run(int $fileId, string $filePath, string $ext): array {
        // Marquer "en cours"
        FileModel::saveAnalysis($fileId, ['niveau_sensibilite' => 'en_cours']);

        $analysisData = [
            'texte_extrait'       => '',
            'mots_cles_detectes'  => [],
            'cb_detectee'         => false,
            'nombre_cb'           => 0,
            'score_ia'            => null,
            'verdict_ia'          => null,
            'raisons_ia'          => null,
            'resume_ai'           => null,
            'niveau_sensibilite'  => 'non_sensible',
            'raisons'             => [],
            'metadata_analysee'   => 0,
            'contenu_analyse'     => 0,
        ];

        try {
            // ── Étape 1 : Extraction des métadonnées ──────────────────────────
            $meta = MetadataExtractor::extract($filePath, $ext);
            if (!empty($meta)) {
                FileModel::saveMetadata($fileId, $meta);
                $analysisData['metadata_analysee'] = 1;
            }

            // ── Étape 2 : Extraction du texte ─────────────────────────────────
            $text = TextExtractor::extract($filePath, $ext);
            $analysisData['texte_extrait']    = $text;
            $analysisData['contenu_analyse']  = !empty(trim($text)) ? 1 : 0;

            // ── Étape 3 : Détection locale ────────────────────────────────────
            $localResult = SensitivityDetector::analyze($text);
            $analysisData['mots_cles_detectes'] = $localResult['mots_cles_detectes'];
            $analysisData['cb_detectee']         = $localResult['cb_detectee'];
            $analysisData['nombre_cb']           = $localResult['nombre_cb'];

            // ── Étape 4 : Appel IA ────────────────────────────────────────────
            $aiResult = ['is_sensitive' => false, 'confidence' => 0.0, 'reasons' => [], 'error' => null];
            if (!empty(trim($text)) && !empty(CLAUDE_API_KEY)) {
                $aiResult = ClaudeAiService::analyze($text, $localResult);
            }
            if (!$aiResult['error']) {
                $analysisData['score_ia']   = round($aiResult['confidence'] * 100, 2);
                $analysisData['verdict_ia'] = $aiResult['is_sensitive'] ? 1 : 0;
                $analysisData['raisons_ia'] = implode(' | ', $aiResult['reasons']);
            }

            // ── Étape 4b : Résumé IA en 3 mots ───────────────────────────────
            if (!empty(trim($text)) && !empty(CLAUDE_API_KEY)) {
                $analysisData['resume_ai'] = ClaudeAiService::summarize($text);
            }

            // ── Étape 5 : Fusion et niveau final ─────────────────────────────
            $analysisData['niveau_sensibilite'] = self::computeLevel($localResult, $aiResult);

            // ── Raisons consolidées ───────────────────────────────────────────
            $raisons = $localResult['raisons'];
            if (!empty($aiResult['reasons'])) {
                $raisons = array_merge($raisons, array_map(fn($r) => "IA : $r", $aiResult['reasons']));
            }
            $analysisData['raisons'] = $raisons;

        } catch (Exception $e) {
            error_log("ClassificationEngine::run error for file $fileId: " . $e->getMessage());
            $analysisData['niveau_sensibilite'] = 'erreur';
            $analysisData['raisons'] = ['Analysis error: ' . $e->getMessage()];
        }

        FileModel::saveAnalysis($fileId, $analysisData);
        return $analysisData;
    }

    /**
     * Calcule le niveau de sensibilité final en fusionnant détection locale + IA.
     */
    private static function computeLevel(array $local, array $ai): string {
        $niveau = 'non_sensible';

        $hasCb       = $local['cb_detectee'] ?? false;
        $hasKeywords = !empty($local['mots_cles_detectes']);

        // Mots-clés "top secret" → sensible élevé immédiat
        $hasTopSecret = false;
        foreach ($local['mots_cles_detectes'] ?? [] as $kw) {
            if (in_array(mb_strtolower($kw), ['top secret','très secret','tres secret','strictly confidential'])) {
                $hasTopSecret = true;
                break;
            }
        }

        // Logique de fusion
        if ($hasCb || $hasTopSecret || ($hasCb && $hasKeywords)) {
            $niveau = 'sensible_eleve';
        } elseif ($hasKeywords) {
            $niveau = 'sensible';
            // Si l'IA confirme avec forte confiance → élever
            if (($ai['is_sensitive'] ?? false) && ($ai['confidence'] ?? 0) >= 0.85) {
                $niveau = 'sensible_eleve';
            }
        } elseif ($ai['is_sensitive'] ?? false) {
            if (($ai['confidence'] ?? 0) >= 0.80) {
                $niveau = 'sensible';
            } elseif (($ai['confidence'] ?? 0) >= 0.90) {
                $niveau = 'sensible_eleve';
            }
        }

        return $niveau;
    }
}
