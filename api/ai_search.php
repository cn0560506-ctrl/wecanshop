<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Méthode non autorisée']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');

if (!$query) {
    echo json_encode(['error' => 'Requête vide']); exit;
}

if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
    echo json_encode(['error' => 'Clé API non configurée. Ajoutez ANTHROPIC_API_KEY dans config/db.php']); exit;
}

// Récupérer les produits disponibles (filtrés par boutique si vendeur)
$where  = ["p.status = 'active'"];
$params = [];

if (isSeller() && !isAdmin()) {
    $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
    $storeStmt->execute([$_SESSION['user_id']]);
    $storeId = $storeStmt->fetchColumn();
    if ($storeId) { $where[] = "p.store_id = ?"; $params[] = $storeId; }
}

$stmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.description, p.patient_problems, p.advantages,
           p.stock, p.image, c.name as category
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.created_at DESC LIMIT 50
");
$stmt->execute($params);
$products = $stmt->fetchAll();

if (empty($products)) {
    echo json_encode(['reply' => "Aucun produit disponible pour le moment.", 'products' => []]); exit;
}

// Construire le contexte produits pour Claude
$productContext = [];
foreach ($products as $p) {
    $productContext[] = [
        'id'       => $p['id'],
        'nom'      => $p['name'],
        'prix'     => $p['price'] . ' FCFA',
        'categorie'=> $p['category'] ?? 'Non classé',
        'description' => mb_substr(strip_tags($p['description'] ?? ''), 0, 150),
        'problemes'   => mb_substr(strip_tags($p['patient_problems'] ?? ''), 0, 150),
        'avantages'   => mb_substr(strip_tags($p['advantages'] ?? ''), 0, 150),
        'en_stock' => $p['stock'] > 0 ? 'oui' : 'non',
    ];
}

$systemPrompt = "Tu es un assistant de vente expert pour une boutique en ligne africaine.
Tu aides les clients à trouver le bon produit selon leurs besoins.
Voici les produits disponibles en stock :
" . json_encode($productContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

Règles :
- Réponds TOUJOURS en français, de façon chaleureuse et naturelle
- Recommande les produits les plus pertinents selon la demande
- Explique brièvement pourquoi chaque produit correspond
- Si aucun produit ne correspond, dis-le honnêtement
- Mentionne le prix
- Réponds en 2-3 phrases maximum par produit
- À la fin de ta réponse, liste les IDs des produits recommandés sur une ligne séparée au format : PRODUITS:[1,2,3]";

// Appel API Anthropic
$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 600,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $query]
    ]
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    echo json_encode(['error' => 'Erreur API (' . $httpCode . '). Vérifiez votre clé API.']); exit;
}

$data = json_decode($response, true);
$text = $data['content'][0]['text'] ?? '';

// Extraire les IDs produits de la réponse
$recommendedIds = [];
if (preg_match('/PRODUITS:\[([0-9,\s]+)\]/', $text, $m)) {
    $recommendedIds = array_map('intval', explode(',', $m[1]));
    $text = trim(preg_replace('/PRODUITS:\[[0-9,\s]+\]/', '', $text));
}

// Récupérer les données complètes des produits recommandés
$recommendedProducts = [];
if ($recommendedIds) {
    $in = implode(',', array_map('intval', $recommendedIds));
    $rStmt = $pdo->query("
        SELECT p.id, p.name, p.price, p.original_price, p.image, p.stock
        FROM products p WHERE p.id IN ($in) AND p.status = 'active'
    ");
    foreach ($rStmt->fetchAll() as $rp) {
        $recommendedProducts[] = [
            'id'             => $rp['id'],
            'name'           => $rp['name'],
            'price'          => formatPrice($rp['price']),
            'original_price' => $rp['original_price'] ? formatPrice($rp['original_price']) : null,
            'image'          => getProductImageUrl($rp['image']),
            'url'            => SITE_URL . '/product.php?id=' . $rp['id'],
            'in_stock'       => $rp['stock'] > 0,
        ];
    }
}

echo json_encode([
    'reply'    => $text,
    'products' => $recommendedProducts,
]);
