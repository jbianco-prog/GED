<?php
// src/Helpers/Helpers.php — Fonctions utilitaires globales

class H {

    // ── Icônes par extension — SVG inline pour les types Office/PDF ───────────
    public static function fileIcon(string $ext): string {
        $ext = strtolower($ext);

        // ── Word (bleu Microsoft) ─────────────────────────────────────────────
        $word = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22" style="vertical-align:middle;flex-shrink:0">'
              . '<rect width="30" height="38" x="9" y="5" rx="2" fill="#2B579A"/>'
              . '<rect width="22" height="2" x="13" y="12" fill="#fff" opacity=".7"/>'
              . '<rect width="22" height="2" x="13" y="17" fill="#fff" opacity=".7"/>'
              . '<rect width="22" height="2" x="13" y="22" fill="#fff" opacity=".7"/>'
              . '<rect width="15" height="2" x="13" y="27" fill="#fff" opacity=".7"/>'
              . '<path d="M6 10h12v28H6z" fill="#1A3F7A"/>'
              . '<path d="M10 16l2 10 2-7 2 7 2-10h1.5l-3.5 14h-2L12 23l-2 7h-2L4.5 16z" fill="#fff"/>'
              . '</svg>';

        // ── Excel (vert Microsoft) ────────────────────────────────────────────
        $excel = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22" style="vertical-align:middle;flex-shrink:0">'
               . '<rect width="30" height="38" x="9" y="5" rx="2" fill="#217346"/>'
               . '<rect width="22" height="2" x="13" y="14" fill="#fff" opacity=".5"/>'
               . '<rect width="22" height="2" x="13" y="19" fill="#fff" opacity=".5"/>'
               . '<rect width="22" height="2" x="13" y="24" fill="#fff" opacity=".5"/>'
               . '<rect width="22" height="2" x="13" y="29" fill="#fff" opacity=".5"/>'
               . '<path d="M6 10h12v28H6z" fill="#185C37"/>'
               . '<path d="M9 17l3 5-3 5h2l2-3.5L15 27h2l-3-5 3-5h-2l-2 3.5L11 17z" fill="#fff"/>'
               . '</svg>';

        // ── PowerPoint (orange Microsoft) ─────────────────────────────────────
        $ppt = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22" style="vertical-align:middle;flex-shrink:0">'
             . '<rect width="30" height="38" x="9" y="5" rx="2" fill="#C43E1C"/>'
             . '<path d="M6 10h12v28H6z" fill="#A0310F"/>'
             . '<circle cx="28" cy="21" r="7" fill="#fff" opacity=".15"/>'
             . '<path d="M9 16h2c3 0 5 1.5 5 4s-2 4-5 4H9zm2 6.5h1c1.5 0 2.5-.6 2.5-2.5S13.5 17.5 12 17.5h-1z" fill="#fff"/>'
             . '</svg>';

        // ── PDF (rouge Adobe) ─────────────────────────────────────────────────
        $pdf = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22" style="vertical-align:middle;flex-shrink:0">'
             . '<rect width="30" height="38" x="9" y="5" rx="2" fill="#E8392A"/>'
             . '<path d="M9 5h16l14 14v24a2 2 0 01-2 2H11a2 2 0 01-2-2z" fill="#FF5147"/>'
             . '<path d="M25 5l14 14H25z" fill="#C62828"/>'
             . '<text x="24" y="34" font-family="Arial,sans-serif" font-size="10" font-weight="bold" fill="#fff" text-anchor="middle">PDF</text>'
             . '</svg>';

        // ── CSV (vert clair) ──────────────────────────────────────────────────
        $csv = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22" style="vertical-align:middle;flex-shrink:0">'
             . '<rect width="30" height="38" x="9" y="5" rx="2" fill="#33915A"/>'
             . '<path d="M6 10h12v28H6z" fill="#1E6E40"/>'
             . '<rect width="22" height="1.5" x="13" y="14" fill="#fff" opacity=".5"/>'
             . '<rect width="22" height="1.5" x="13" y="19" fill="#fff" opacity=".5"/>'
             . '<rect width="22" height="1.5" x="13" y="24" fill="#fff" opacity=".5"/>'
             . '<rect width="22" height="1.5" x="13" y="29" fill="#fff" opacity=".5"/>'
             . '<text x="12" y="28" font-family="Arial,sans-serif" font-size="8" font-weight="bold" fill="#fff">CSV</text>'
             . '</svg>';

        return match($ext) {
            'doc','docx','odt'          => $word,
            'xls','xlsx','ods'          => $excel,
            'csv'                       => $csv,
            'ppt','pptx','odp'          => $ppt,
            'pdf'                       => $pdf,
            'txt','json','xml'          => '📃',
            'jpg','jpeg','png','gif','webp','bmp' => '🖼️',
            'zip','gz','tar','7z'       => '🗜️',
            'mp4','avi','mov','mkv'     => '🎬',
            'mp3','wav','ogg'           => '🎵',
            default                     => '📎',
        };
    }

    // Classe CSS couleur pour badge icône
    public static function fileIconClass(string $ext): string {
        $ext = strtolower($ext);
        if (in_array($ext, ['pdf']))                      return 'icon-pdf';
        if (in_array($ext, ['doc','docx','odt']))         return 'icon-word';
        if (in_array($ext, ['xls','xlsx','csv','ods']))   return 'icon-excel';
        if (in_array($ext, ['ppt','pptx','odp']))         return 'icon-ppt';
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'])) return 'icon-image';
        if (in_array($ext, ['zip','gz','tar','7z']))      return 'icon-archive';
        if (in_array($ext, ['mp4','avi','mov','mkv']))    return 'icon-video';
        if (in_array($ext, ['mp3','wav','ogg']))          return 'icon-audio';
        return 'icon-default';
    }

    // ── Badge sensibilité ─────────────────────────────────────────────────────
    public static function sensitivityBadge(?string $level): string {
        $labels = [
            'non_analyse'   => ['Not analyzed',  'badge-secondary'],
            'en_cours'      => ['In progress…',  'badge-info'],
            'non_sensible'  => ['Not sensitive', 'badge-success'],
            'sensible'      => ['Sensitive',     'badge-warning'],
            'sensible_eleve'=> ['⚠ High risk',   'badge-danger'],
            'erreur'        => ['Analysis error','badge-secondary'],
        ];
        $data = $labels[$level ?? 'non_analyse'] ?? $labels['non_analyse'];
        return '<span class="badge ' . $data[1] . '">' . htmlspecialchars($data[0]) . '</span>';
    }

    // ── Formatage taille ──────────────────────────────────────────────────────
    public static function formatSize(int $bytes): string {
        if ($bytes < 1024)            return $bytes . ' B';
        if ($bytes < 1024 * 1024)     return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 1) . ' MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    // ── Formatage date ────────────────────────────────────────────────────────
    public static function formatDate(?string $date, bool $time = true): string {
        if (!$date) return '—';
        $dt = new DateTime($date);
        return $dt->format($time ? 'm/d/Y H:i' : 'm/d/Y');
    }

    // ── Sécurité HTML ─────────────────────────────────────────────────────────
    public static function e(mixed $v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ── Redirect ─────────────────────────────────────────────────────────────
    public static function redirect(string $url): never {
        header('Location: ' . $url);
        exit;
    }

    // ── Flash messages ────────────────────────────────────────────────────────
    public static function flash(string $type, string $msg): void {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }

    public static function getFlash(): ?array {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    // ── URL helper ────────────────────────────────────────────────────────────
    public static function url(string $path): string {
        return APP_URL . '/' . ltrim($path, '/');
    }

    // ── Pagination ────────────────────────────────────────────────────────────
    public static function paginate(int $total, int $perPage, int $currentPage): array {
        $totalPages = max(1, (int)ceil($total / $perPage));
        $currentPage = max(1, min($currentPage, $totalPages));
        return [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'offset'       => ($currentPage - 1) * $perPage,
            'has_prev'     => $currentPage > 1,
            'has_next'     => $currentPage < $totalPages,
        ];
    }
}
