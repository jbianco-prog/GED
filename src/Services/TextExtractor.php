<?php
// src/Services/TextExtractor.php

class TextExtractor {

    public static function extract(string $filePath, string $ext): string {
        $ext  = strtolower($ext);
        $text = '';

        try {
            switch ($ext) {
                case 'txt':
                case 'csv':
                case 'json':
                case 'xml':
                    $text = self::readPlain($filePath);
                    break;
                case 'pdf':
                    $text = self::extractPdf($filePath);
                    break;
                case 'docx':
                    $text = self::extractDocx($filePath);
                    break;
                case 'xlsx':
                    $text = self::extractXlsx($filePath);
                    break;
                case 'pptx':
                    $text = self::extractPptx($filePath);
                    break;
                case 'odt':
                case 'ods':
                case 'odp':
                    $text = self::extractOdf($filePath);
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            error_log("TextExtractor error ($ext): " . $e->getMessage());
        }

        return mb_substr($text, 0, 100000); // Cap à 100k caractères
    }

    private static function readPlain(string $path): string {
        return file_get_contents($path) ?: '';
    }

    private static function extractPdf(string $path): string {
        // Méthode 1 : pdftotext (poppler-utils)
        if (self::commandExists('pdftotext')) {
            $output = [];
            exec('pdftotext ' . escapeshellarg($path) . ' - 2>/dev/null', $output);
            $text = implode("\n", $output);
            if (!empty(trim($text))) return $text;
        }
        // Méthode 2 : lecture binaire rudimentaire
        $content = file_get_contents($path) ?: '';
        // Extraire les chaînes entre parenthèses (texte PDF brut)
        preg_match_all('/\(([^)]{3,})\)/', $content, $matches);
        return implode(' ', $matches[1] ?? []);
    }

    private static function extractDocx(string $path): string {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';

        $xml  = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) return '';

        return self::xmlToText($xml);
    }

    private static function extractXlsx(string $path): string {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';

        $texts = [];
        // Strings partagées
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sharedStrings    = [];
        if ($sharedStringsXml) {
            $xml = @simplexml_load_string($sharedStringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t ?? strip_tags($si->asXML());
                }
            }
        }

        // Feuilles
        for ($i = 1; $i <= 10; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet$i.xml");
            if (!$sheetXml) break;
            $xml = @simplexml_load_string($sheetXml);
            if ($xml) {
                foreach ($xml->sheetData->row ?? [] as $row) {
                    foreach ($row->c ?? [] as $cell) {
                        $type  = (string)($cell['t'] ?? '');
                        $value = (string)($cell->v ?? '');
                        if ($type === 's' && isset($sharedStrings[(int)$value])) {
                            $texts[] = $sharedStrings[(int)$value];
                        } elseif (!empty($value)) {
                            $texts[] = $value;
                        }
                    }
                }
            }
        }

        $zip->close();
        return implode(' ', $texts);
    }

    private static function extractPptx(string $path): string {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';

        $texts = [];
        for ($i = 1; $i <= 500; $i++) {
            $slideXml = $zip->getFromName("ppt/slides/slide$i.xml");
            if (!$slideXml) break;
            $texts[] = self::xmlToText($slideXml);
        }

        $zip->close();
        return implode("\n", $texts);
    }

    private static function extractOdf(string $path): string {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if (!$xml) return '';
        return self::xmlToText($xml);
    }

    private static function xmlToText(string $xml): string {
        // Supprimer les namespaces et les balises XML, garder le texte
        $clean = preg_replace('/<[^>]+>/', ' ', $xml);
        $clean = html_entity_decode($clean ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        return preg_replace('/\s+/', ' ', $clean ?? '');
    }

    private static function commandExists(string $cmd): bool {
        $result = shell_exec("which $cmd 2>/dev/null");
        return !empty(trim($result ?? ''));
    }
}
