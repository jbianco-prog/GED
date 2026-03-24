<?php
// src/Services/MetadataExtractor.php

class MetadataExtractor {

    public static function extract(string $filePath, string $ext): array {
        $ext = strtolower($ext);
        $meta = [];

        try {
            switch ($ext) {
                case 'pdf':
                    $meta = self::extractPdf($filePath);
                    break;
                case 'docx':
                    $meta = self::extractDocx($filePath);
                    break;
                case 'xlsx':
                    $meta = self::extractXlsx($filePath);
                    break;
                case 'pptx':
                    $meta = self::extractPptx($filePath);
                    break;
                case 'jpg':
                case 'jpeg':
                case 'tiff':
                case 'webp':
                    $meta = self::extractExif($filePath);
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            error_log("MetadataExtractor error ($ext): " . $e->getMessage());
        }

        return $meta;
    }

    // ── PDF via pdfinfo ou lecture binaire ────────────────────────────────────
    private static function extractPdf(string $path): array {
        $meta = [];

        // Tenter pdfinfo (poppler-utils)
        if (self::commandExists('pdfinfo')) {
            $output = [];
            exec('pdfinfo ' . escapeshellarg($path) . ' 2>/dev/null', $output);
            foreach ($output as $line) {
                if (preg_match('/^(.+?):\s+(.+)$/', $line, $m)) {
                    switch (strtolower(trim($m[1]))) {
                        case 'author':        $meta['auteur'] = $m[2]; break;
                        case 'title':         $meta['titre']  = $m[2]; break;
                        case 'subject':       $meta['sujet']  = $m[2]; break;
                        case 'creator':       $meta['logiciel_createur'] = $m[2]; break;
                        case 'pages':         $meta['nb_pages'] = (int)$m[2]; break;
                        case 'creationdate':  $meta['date_creation_doc']    = self::parseDate($m[2]); break;
                        case 'moddate':       $meta['date_modification_doc'] = self::parseDate($m[2]); break;
                        case 'keywords':      $meta['mots_cles'] = $m[2]; break;
                    }
                }
            }
        } else {
            // Lecture binaire basique
            $content = file_get_contents($path, false, null, 0, 4096);
            if ($content && preg_match('/\/Author\s*\(([^)]+)\)/', $content, $m))  $meta['auteur'] = $m[1];
            if ($content && preg_match('/\/Title\s*\(([^)]+)\)/', $content, $m))   $meta['titre']  = $m[1];
            if ($content && preg_match('/\/Subject\s*\(([^)]+)\)/', $content, $m)) $meta['sujet']  = $m[1];
        }

        $meta['json_complet'] = $meta;
        return $meta;
    }

    // ── DOCX / XLSX / PPTX (ZIP + XML) ───────────────────────────────────────
    private static function extractDocx(string $path): array {
        return self::extractOfficeXml($path, 'word/document.xml');
    }

    private static function extractXlsx(string $path): array {
        return self::extractOfficeXml($path, 'xl/workbook.xml');
    }

    private static function extractPptx(string $path): array {
        return self::extractOfficeXml($path, 'ppt/presentation.xml');
    }

    private static function extractOfficeXml(string $path, string $mainEntry): array {
        $meta = [];
        if (!class_exists('ZipArchive')) return $meta;

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return $meta;

        // Core properties (auteur, titre, sujet, date…)
        $coreXml = $zip->getFromName('docProps/core.xml');
        if ($coreXml) {
            $xml = @simplexml_load_string($coreXml);
            if ($xml) {
                $ns = $xml->getNamespaces(true);
                $dc  = isset($ns['dc'])  ? $xml->children($ns['dc'])  : null;
                $cp  = isset($ns['cp'])  ? $xml->children($ns['cp'])  : null;
                $dcterms = isset($ns['dcterms']) ? $xml->children($ns['dcterms']) : null;
                if ($dc)      $meta['auteur'] = (string)($dc->creator ?? '');
                if ($dc)      $meta['titre']  = (string)($dc->title   ?? '');
                if ($dc)      $meta['sujet']  = (string)($dc->subject ?? '');
                if ($dc)      $meta['mots_cles'] = (string)($dc->description ?? '');
                if ($dcterms) $meta['date_creation_doc']     = self::parseDate((string)($dcterms->created  ?? ''));
                if ($dcterms) $meta['date_modification_doc'] = self::parseDate((string)($dcterms->modified ?? ''));
                if ($cp)      $meta['mots_cles'] = $meta['mots_cles'] ?: (string)($cp->keywords ?? '');
            }
        }

        // App properties (logiciel, nb pages)
        $appXml = $zip->getFromName('docProps/app.xml');
        if ($appXml) {
            $xml = @simplexml_load_string($appXml);
            if ($xml) {
                $ns = $xml->getNamespaces(true);
                $meta['logiciel_createur'] = (string)($xml->Application ?? '');
                $meta['societe']           = (string)($xml->Company     ?? '');
                $pages = (string)($xml->Pages ?? $xml->Slides ?? '');
                if ($pages) $meta['nb_pages'] = (int)$pages;
            }
        }

        $zip->close();
        $meta['json_complet'] = $meta;
        return array_filter($meta, fn($v) => $v !== '' && $v !== null);
    }

    // ── EXIF pour images ──────────────────────────────────────────────────────
    private static function extractExif(string $path): array {
        $meta = [];
        if (!function_exists('exif_read_data')) return $meta;

        $exif = @exif_read_data($path, null, false);
        if (!$exif) return $meta;

        if (!empty($exif['Artist']))       $meta['auteur'] = $exif['Artist'];
        if (!empty($exif['Copyright']))    $meta['sujet']  = $exif['Copyright'];
        if (!empty($exif['DateTimeOriginal'])) {
            $meta['date_creation_doc'] = self::parseDate($exif['DateTimeOriginal']);
        }
        if (!empty($exif['Software']))     $meta['logiciel_createur'] = $exif['Software'];
        $meta['json_complet'] = $exif;
        return $meta;
    }

    // ── Utilitaires ───────────────────────────────────────────────────────────
    private static function parseDate(string $raw): ?string {
        if (empty($raw)) return null;
        try {
            $dt = new DateTime($raw);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private static function commandExists(string $cmd): bool {
        $result = shell_exec("which $cmd 2>/dev/null");
        return !empty(trim($result ?? ''));
    }
}
