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

$uploaded = uploadProductImage($_FILES['file']);

if ($uploaded === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Format non supporté ou fichier trop grand (max 5 Mo). Formats acceptés : JPG, PNG, WEBP.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'filename' => $uploaded,
    'url'      => SITE_URL . '/uploads/products/' . urlencode($uploaded),
]);
