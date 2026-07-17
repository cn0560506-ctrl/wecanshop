<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Requête invalide']);
    exit;
}

$filename = uploadTestimonialFile($_FILES['file']);

if ($filename === false) {
    $f = $_FILES['file'];
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop grand (limite serveur)',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop grand (limite formulaire)',
        UPLOAD_ERR_PARTIAL    => 'Upload incomplet, réessayez',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier reçu',
        UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque',
    ];
    $msg = $errors[$f['error']] ?? 'Format non supporté ou fichier trop grand (max 50 Mo). Formats acceptés: JPG, PNG, WEBP, MP4, WEBM, MOV, AVI, MKV.';
    http_response_code(422);
    echo json_encode(['error' => $msg]);
    exit;
}

echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'type'     => getTestimonialType($filename),
    'url'      => SITE_URL . '/uploads/testimonials/' . urlencode($filename),
]);
