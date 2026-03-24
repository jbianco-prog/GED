<?php
// public/upload.php — Point d'entrée pour l'upload de fichiers
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

UploadController::handle();
