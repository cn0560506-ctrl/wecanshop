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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$name = trim($_POST['product_name'] ?? '');
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom du produit requis']);
    exit;
}

// ---------------------------------------------------------------
// Détection de catégorie par mots-clés dans le nom du produit
// ---------------------------------------------------------------
$nameLower = mb_strtolower($name, 'UTF-8');

function containsAny(string $text, array $keywords): bool {
    foreach ($keywords as $kw) {
        if (mb_strpos($text, $kw) !== false) return true;
    }
    return false;
}

$category = 'general';

if (containsAny($nameLower, ['tisane','plante','herbe','racine','écorce','feuille','infusion','décoction','moringa','gingembre','kinkeliba','bissap','baobab','néré','soumbara','ditax','tamarin','hibiscus'])) {
    $category = 'tisane';
} elseif (containsAny($nameLower, ['café','cacao','chocolat','thé','boisson','jus','sirop','smoothie','lait'])) {
    $category = 'boisson';
} elseif (containsAny($nameLower, ['gélule','capsule','comprimé','pilule','complément','vitamine','supplément','cure'])) {
    $category = 'gelule';
} elseif (containsAny($nameLower, ['crème','beurre','huile','savon','soin','lotion','sérum','gommage','masque','gel','shampoo','après-shampooing','karité','coco','argan','baobab'])) {
    $category = 'cosmetique';
} elseif (containsAny($nameLower, ['poudre','farine','épice','condiment','sel','poivre','curcuma','cumin','ail'])) {
    $category = 'poudre';
} elseif (containsAny($nameLower, ['miel','propolis','gelée royale','pollen'])) {
    $category = 'miel';
} elseif (containsAny($nameLower, ['téléphone','smartphone','ordinateur','tablette','écouteur','casque','câble','chargeur','batterie','accessoire','électronique','laptop'])) {
    $category = 'electronique';
} elseif (containsAny($nameLower, ['robe','chemise','pantalon','jupe','vêtement','tenue','wax','tissu','mode','habit','manteau','veste'])) {
    $category = 'mode';
} elseif (containsAny($nameLower, ['lit','table','chaise','meuble','coussin','rideau','nappe','décoration','maison'])) {
    $category = 'maison';
} elseif (containsAny($nameLower, ['détox','minceur','amincissant','brûle-graisse','ventre plat','perte de poids','régime'])) {
    $category = 'minceur';
} elseif (containsAny($nameLower, ['fertilité','grossesse','libido','vitalité','énergie','force','puissance','virilité'])) {
    $category = 'vitalite';
} elseif (containsAny($nameLower, ['diabète','tension','cholestérol','foie','reins','prostate','articulation','douleur','arthrite'])) {
    $category = 'maladie';
} elseif (containsAny($nameLower, ['cheveu','chute','pousse','repousse','calvitie'])) {
    $category = 'cheveux';
}

// ---------------------------------------------------------------
// Templates par catégorie
// ---------------------------------------------------------------
$templates = [

'tisane' => [
    'description' => "$name est une tisane naturelle aux plantes médicinales africaines, soigneusement sélectionnées et récoltées à maturité. Préparée selon les traditions ancestrales, elle offre tous les bienfaits de la phytothérapie africaine dans votre tasse quotidienne. Idéale pour une cure de bien-être durable et naturelle.",
    'problems'    => "• Troubles digestifs et ballonnements\n• Fatigue chronique et manque d'énergie\n• Système immunitaire affaibli\n• Stress, nervosité et troubles du sommeil\n• Accumulation de toxines dans l'organisme\n• Inflammation et douleurs diffuses",
    'advantages'  => "• 100% naturel, sans additifs ni conservateurs\n• Riche en antioxydants et minéraux essentiels\n• Soulage rapidement les inconforts digestifs\n• Renforce les défenses naturelles de l'organisme\n• Favorise un sommeil réparateur\n• Recette traditionnelle africaine éprouvée depuis des générations",
    'posologie'   => "• 1 à 2 tasses par jour, matin et/ou soir\n• Faire bouillir 250 ml d'eau, ajouter 1 sachet ou 1 cuillère à soupe\n• Laisser infuser 5 à 10 minutes avant de boire\n• Cure recommandée : 30 jours minimum\n• Peut être sucré avec du miel\n• Déconseillé aux femmes enceintes sans avis médical",
],

'boisson' => [
    'description' => "$name est une boisson naturelle et savoureuse, élaborée à partir d'ingrédients soigneusement sélectionnés. Alliant plaisir gustatif et bienfaits pour la santé, elle s'intègre facilement dans votre routine quotidienne. Un choix savoureux pour prendre soin de vous tout en vous faisant plaisir.",
    'problems'    => "• Manque de vitalité et d'énergie au quotidien\n• Envie de sucré difficile à contrôler\n• Mauvaise digestion après les repas\n• Carences en vitamines et minéraux\n• Déshydratation et manque de tonus\n• Besoin d'une alternative saine aux boissons sucrées",
    'advantages'  => "• Goût authentique et naturel\n• Source naturelle de vitamines et minéraux\n• Boost d'énergie immédiat et durable\n• Facile à préparer et à consommer\n• Convient à toute la famille\n• Sans colorants artificiels ni conservateurs",
    'posologie'   => "• 1 à 2 verres par jour selon vos besoins\n• Se consomme froid ou chaud selon les préférences\n• Peut être dilué avec de l'eau ou du lait\n• Conservation au réfrigérateur après ouverture\n• Agiter avant utilisation\n• Consommer de préférence dans les 48h après ouverture",
],

'gelule' => [
    'description' => "$name est un complément alimentaire naturel formulé pour répondre aux besoins spécifiques de votre organisme. Chaque gélule concentre les principes actifs des plantes africaines les plus efficaces, sélectionnées pour leur qualité et leur puissance. Une solution pratique pour une santé optimale au quotidien.",
    'problems'    => "• Carences nutritionnelles et déséquilibres\n• Fatigue persistante et baisse de vitalité\n• Défenses immunitaires insuffisantes\n• Troubles métaboliques divers\n• Douleurs et inflammations chroniques\n• Vieillissement cellulaire prématuré",
    'advantages'  => "• Formule concentrée à haute biodisponibilité\n• Absorption optimale par l'organisme\n• Résultats visibles dès 2 à 3 semaines\n• Fabriqué à partir de plantes naturelles certifiées\n• Facile à prendre, sans goût désagréable\n• Dosage précis et contrôlé",
    'posologie'   => "• Adultes : 2 gélules par jour, matin et soir\n• Prendre avec un grand verre d'eau (200 ml minimum)\n• Prendre de préférence pendant ou après les repas\n• Cure minimum de 30 jours pour des résultats durables\n• Ne pas dépasser la dose journalière recommandée\n• Déconseillé aux femmes enceintes, allaitantes et aux enfants de moins de 12 ans",
],

'cosmetique' => [
    'description' => "$name est un soin cosmétique naturel formulé à base d'ingrédients africains reconnus pour leurs vertus exceptionnelles sur la peau. Sa texture riche et pénétrante agit en profondeur pour nourrir, hydrater et sublimer votre peau. Idéal pour une routine beauté naturelle et efficace.",
    'problems'    => "• Peau sèche, terne et déshydratée\n• Taches et irrégularités du teint\n• Peau rugueuse et manque d'éclat\n• Signes de vieillissement prématuré\n• Démangeaisons et irritations cutanées\n• Manque de protection contre les agressions extérieures",
    'advantages'  => "• Hydratation intense et longue durée\n• Unifie et illumine le teint naturellement\n• Riche en vitamines A, E et acides gras essentiels\n• Pénètre rapidement sans laisser de film gras\n• Convient à tous les types de peau, y compris sensibles\n• Formule 100% naturelle sans parabènes ni sulfates",
    'posologie'   => "• Appliquer sur peau propre et sèche, matin et/ou soir\n• Masser en mouvements circulaires jusqu'à absorption complète\n• Pour le visage : insister sur les zones sèches et les contours\n• Pour le corps : appliquer après la douche sur peau encore légèrement humide\n• Usage externe uniquement — éviter le contact avec les yeux\n• Conserver à l'abri de la chaleur et de la lumière directe",
],

'poudre' => [
    'description' => "$name est une poudre naturelle de qualité supérieure, issue des terres fertiles d'Afrique de l'Ouest. Riche en nutriments essentiels et en principes actifs, elle s'utilise facilement dans vos préparations culinaires ou comme complément santé. Un trésor de la nature pour enrichir votre alimentation quotidienne.",
    'problems'    => "• Alimentation pauvre en micronutriments\n• Manque de saveur dans les plats traditionnels\n• Déficit en vitamines et minéraux essentiels\n• Besoin de renforcer l'organisme naturellement\n• Digestion difficile et troubles intestinaux\n• Faible immunité et fatigue fréquente",
    'advantages'  => "• Haute concentration en nutriments actifs\n• Facile à incorporer dans les recettes et boissons\n• Goût authentique et naturel\n• Conservation longue durée dans de bonnes conditions\n• Polyvalent : cuisine, boissons, soins beauté\n• Source naturelle de vitamines et minéraux biodisponibles",
    'posologie'   => "• 1 à 2 cuillères à café par jour (5 à 10 g)\n• Peut être mélangé dans de l'eau, du lait, un smoothie ou un repas\n• Pour une cure santé : 1 cuillère à café dans 250 ml d'eau tiède chaque matin\n• Commencer par de petites doses et augmenter progressivement\n• Conserver dans un endroit frais, sec et à l'abri de la lumière\n• Se consume de préférence dans les 6 mois suivant l'ouverture",
],

'miel' => [
    'description' => "$name est un produit naturel d'exception, récolté par des apiculteurs traditionnels africains dans des zones préservées de toute pollution. Riche en enzymes, antioxydants et propriétés antibactériennes naturelles, il est bien plus qu'un simple aliment sucré. Un véritable trésor de la nature pour votre santé et votre bien-être.",
    'problems'    => "• Système immunitaire affaibli et rhumes fréquents\n• Maux de gorge et infections respiratoires\n• Plaies et brûlures superficielles lentes à cicatriser\n• Manque d'énergie et fatigue chronique\n• Troubles digestifs et ulcères gastriques\n• Besoin d'un substitut naturel au sucre raffiné",
    'advantages'  => "• Puissantes propriétés antibactériennes et antifongiques\n• Renforce naturellement le système immunitaire\n• Accélère la cicatrisation des plaies\n• Source d'énergie naturelle rapidement assimilable\n• Apaise les maux de gorge et les toux\n• Non filtré, toutes les enzymes et propriétés préservées",
    'posologie'   => "• 1 à 2 cuillères à soupe par jour (20 à 30 g)\n• Prendre le matin à jeun dans de l'eau tiède (non bouillante)\n• Pour les maux de gorge : 1 cuillère à café pure ou dans une infusion\n• Ne jamais chauffer au-dessus de 40°C pour préserver les propriétés\n• Déconseillé aux enfants de moins de 1 an\n• Personnes diabétiques : consulter un médecin avant utilisation",
],

'minceur' => [
    'description' => "$name est une solution naturelle pour accompagner votre perte de poids et retrouver la silhouette de vos rêves. Formulé à base de plantes minceur reconnues, il agit sur les mécanismes du stockage des graisses, du drainage et du métabolisme. Associé à une alimentation équilibrée, il aide à atteindre vos objectifs plus rapidement.",
    'problems'    => "• Surpoids et accumulation de graisses localisées\n• Ventre gonflé et rétention d'eau\n• Métabolisme lent et difficultés à maigrir\n• Fringales et grignotages compulsifs\n• Cellulite et manque de tonicité\n• Difficultés à retrouver une silhouette harmonieuse après grossesse",
    'advantages'  => "• Booste le métabolisme et l'élimination des graisses\n• Effet coupe-faim naturel et durable\n• Réduit la rétention d'eau et les gonflements\n• Améliore la digestion et le transit intestinal\n• Formule 100% naturelle sans risques pour la santé\n• Résultats visibles dès 2 à 3 semaines avec une alimentation adaptée",
    'posologie'   => "• 2 gélules / 1 sachet le matin à jeun avec un grand verre d'eau\n• Pratiquer une activité physique légère 3 fois par semaine pour de meilleurs résultats\n• Adopter une alimentation équilibrée, riche en légumes et pauvre en sucres raffinés\n• Cure de 30 à 60 jours recommandée\n• Boire au moins 1,5 litre d'eau par jour pendant la cure\n• Déconseillé aux femmes enceintes ou allaitantes",
],

'vitalite' => [
    'description' => "$name est un stimulant naturel de la vitalité et de l'énergie, formulé à partir des plantes les plus puissantes de la pharmacopée africaine. Il agit sur les niveaux d'énergie, la libido et la vitalité générale pour vous redonner pleine forme et confiance. Un allié indispensable pour les hommes et les femmes qui veulent donner le meilleur d'eux-mêmes.",
    'problems'    => "• Fatigue chronique et manque d'énergie persistant\n• Baisse de libido et désir sexuel diminué\n• Difficultés de performance et manque de vigueur\n• Stress, anxiété et épuisement nerveux\n• Troubles du sommeil qui affectent la récupération\n• Manque de motivation et de dynamisme au quotidien",
    'advantages'  => "• Restaure rapidement l'énergie et la vitalité\n• Stimule naturellement la libido et les performances\n• Réduit le stress et favorise la détente\n• Améliore la qualité du sommeil et la récupération\n• Renforce la confiance en soi et la motivation\n• Formule naturelle sans effets secondaires indésirables",
    'posologie'   => "• 1 à 2 gélules le matin après le petit-déjeuner\n• Pour un effet renforcé : 1 gélule supplémentaire le soir\n• Cure recommandée : 30 à 90 jours selon les besoins\n• Éviter de consommer après 18h pour ne pas perturber le sommeil\n• Effets ressentis dès 7 à 10 jours d'utilisation régulière\n• Déconseillé aux personnes souffrant de troubles cardiaques",
],

'maladie' => [
    'description' => "$name est un produit naturel à base de plantes médicinales africaines, traditionnellement utilisé pour soutenir l'organisme face aux problèmes de santé chroniques. Sa formule unique associe les bienfaits de plusieurs plantes synergiques pour une action globale et efficace. Un complément naturel à votre prise en charge médicale.",
    'problems'    => "• Douleurs chroniques et inflammations persistantes\n• Déséquilibres métaboliques (glycémie, tension, cholestérol)\n• Fonctions hépatiques et rénales perturbées\n• Douleurs articulaires et musculaires\n• Problèmes de prostate et urinaires\n• Fatigue liée aux maladies chroniques",
    'advantages'  => "• Action anti-inflammatoire naturelle et douce\n• Soutient et régule les fonctions vitales de l'organisme\n• Riche en principes actifs aux effets thérapeutiques prouvés\n• Bien toléré, sans effets secondaires notables\n• Complémentaire aux traitements médicaux conventionnels\n• Tradition médicinale africaine millénaire",
    'posologie'   => "• 2 gélules matin et soir avec un grand verre d'eau\n• Prendre pendant ou après les repas pour une meilleure tolérance\n• Cure de 30 à 90 jours recommandée selon la sévérité\n• Ne remplace pas un traitement médical prescrit par un médecin\n• Informer votre médecin de la prise de ce complément\n• Arrêter si des effets indésirables surviennent et consulter",
],

'cheveux' => [
    'description' => "$name est un soin capillaire naturel conçu pour nourrir en profondeur le cuir chevelu et stimuler la pousse des cheveux. Riche en actifs naturels africains reconnus pour leurs propriétés régénératrices, il redonne force, brillance et vitalité à vos cheveux fragilisés. La solution naturelle pour des cheveux plus beaux et plus denses.",
    'problems'    => "• Chute de cheveux excessive et calvitie progressive\n• Cheveux cassants, secs et sans éclat\n• Cuir chevelu irrité, sec ou à tendance pelliculaire\n• Pousse lente et insuffisante\n• Cheveux abîmés par les traitements chimiques\n• Manque de volume et de densité capillaire",
    'advantages'  => "• Stimule efficacement la pousse et la repousse des cheveux\n• Nourrit en profondeur jusqu'à la racine\n• Renforce la fibre capillaire et réduit la casse\n• Apaise et hydrate le cuir chevelu irrité\n• Redonne brillance, souplesse et volume\n• Formule naturelle sans sulfates ni silicones",
    'posologie'   => "• Appliquer sur cuir chevelu propre et légèrement humide\n• Masser en mouvements circulaires pendant 3 à 5 minutes\n• Laisser poser 30 minutes minimum (ou toute une nuit pour un masque intensif)\n• Rincer abondamment à l'eau tiède\n• Utiliser 2 à 3 fois par semaine pour des résultats optimaux\n• Résultats visibles après 4 à 6 semaines d'utilisation régulière",
],

'electronique' => [
    'description' => "$name est un produit électronique fiable et performant, offrant un excellent rapport qualité-prix pour les utilisateurs africains. Conçu pour répondre aux besoins du quotidien, il allie technologie moderne et robustesse pour s'adapter à tous les usages. Une référence incontournable pour rester connecté et productif.",
    'problems'    => "• Besoin de rester connecté à moindre coût\n• Manque de performance et de rapidité\n• Autonomie insuffisante de l'appareil actuel\n• Appareils fragiles et peu adaptés aux conditions locales\n• Coût élevé des produits importés\n• Difficulté à trouver des accessoires compatibles",
    'advantages'  => "• Rapport qualité-prix imbattable sur le marché africain\n• Robuste et conçu pour une utilisation intensive\n• Excellente autonomie adaptée à nos conditions\n• Compatible avec les réseaux et chargeurs locaux\n• Interface intuitive et facile à utiliser\n• Service après-vente disponible et réactif",
    'posologie'   => "• Charger complètement avant la première utilisation\n• Éviter les chocs, chutes et expositions prolongées à la chaleur\n• Nettoyer régulièrement avec un chiffon doux et sec\n• Mettre à jour le firmware/logiciel régulièrement\n• Ne pas utiliser de chargeurs non compatibles\n• Garantie constructeur : conserver la facture d'achat",
],

'mode' => [
    'description' => "$name est une pièce de mode africaine au style unique, alliant l'élégance des tissus traditionnels à des coupes modernes et tendance. Confectionné avec soin par des artisans talentueux, il met en valeur la beauté et la richesse du patrimoine vestimentaire africain. Un choix parfait pour toutes les occasions, du quotidien aux cérémonies.",
    'problems'    => "• Manque de tenue élégante pour les occasions spéciales\n• Difficulté à trouver des vêtements à sa taille\n• Tenues peu adaptées aux conditions climatiques africaines\n• Besoin de se démarquer avec un style unique et authentique\n• Vêtements importés chers et peu représentatifs de l'identité africaine",
    'advantages'  => "• Style africain authentique, élégant et tendance\n• Tissu de qualité supérieure, confortable et respirant\n• Coupes adaptées aux morphologies africaines\n• Idéal pour toutes les occasions : baptêmes, mariages, cérémonies\n• Fabriqué localement par des artisans qualifiés\n• Design unique qui met en valeur votre personnalité",
    'posologie'   => "• Laver à la main ou en machine à 30°C maximum\n• Ne pas utiliser de javel ni d'adoucissant agressif\n• Sécher à l'ombre pour préserver les couleurs\n• Repasser à la vapeur à température moyenne\n• Conserver sur cintre pour éviter les faux plis\n• Consulter l'étiquette intérieure pour les instructions spécifiques",
],

'maison' => [
    'description' => "$name est un article de décoration et d'ameublement qui transformera votre intérieur en un espace élégant et chaleureux. Inspiré des traditions artisanales africaines et des tendances décoratives actuelles, il apporte une touche d'authenticité et de caractère à votre maison. Un investissement durable pour embellir votre chez-vous.",
    'problems'    => "• Intérieur terne et manquant de personnalité\n• Mobilier inadapté à la taille et aux besoins de votre espace\n• Difficulté à trouver des articles de décoration au goût africain\n• Budget limité pour décorer sans faire de compromis sur la qualité\n• Besoin d'articles pratiques et esthétiques à la fois",
    'advantages'  => "• Design élégant qui s'intègre dans tous les styles d'intérieur\n• Matériaux de qualité durables et résistants\n• Fabriqué avec soin par des artisans locaux talentueux\n• Facile à entretenir et à nettoyer\n• Excellent rapport qualité-prix\n• Apporte une touche d'authenticité africaine à votre décoration",
    'posologie'   => "• Démonter et remonter selon le guide d'assemblage fourni\n• Nettoyer avec un chiffon humide et doux, éviter les produits abrasifs\n• Protéger des rayons du soleil directs pour éviter la décoloration\n• Serrer régulièrement les vis et fixations pour la sécurité\n• Contacter le service client pour toute question d'installation\n• Garantie produit valable 6 mois contre les défauts de fabrication",
],

'general' => [
    'description' => "$name est un produit de qualité soigneusement sélectionné pour répondre à vos besoins et vous apporter satisfaction au quotidien. Conçu avec des standards élevés, il offre un excellent rapport qualité-prix et une expérience utilisateur agréable. Faites le choix de la qualité avec ce produit fiable et éprouvé.",
    'problems'    => "• Besoin d'un produit fiable et de qualité\n• Difficulté à trouver ce type de produit à un prix raisonnable\n• Manque de solutions adaptées à vos besoins spécifiques\n• Insatisfaction des produits similaires déjà essayés\n• Besoin d'un produit durable et résistant dans le temps",
    'advantages'  => "• Qualité supérieure vérifiée et garantie\n• Excellent rapport qualité-prix sur le marché\n• Facile à utiliser et adapté à tous\n• Durable et résistant à un usage intensif\n• Service client disponible et à votre écoute\n• Livraison rapide et sécurisée partout en Afrique",
    'posologie'   => "• Utiliser selon les instructions du fabricant\n• Conserver dans un endroit frais, sec et à l'abri de la lumière\n• Tenir hors de portée des enfants\n• En cas de doute sur l'utilisation, contacter notre service client\n• Respecter les conditions d'utilisation recommandées\n• Retour ou échange possible sous 7 jours en cas de problème",
],

];

// Sélectionner le template et personnaliser avec le nom
$tpl = $templates[$category] ?? $templates['general'];

// Remplacer les occurrences génériques par le vrai nom du produit
foreach ($tpl as &$val) {
    $val = str_replace(array_keys($templates[$category]), array_values($tpl), $val);
}
unset($val);

echo json_encode([
    'success'  => true,
    'category' => $category,
    'data'     => [
        'description' => $tpl['description'],
        'problems'    => $tpl['problems'],
        'advantages'  => $tpl['advantages'],
        'posologie'   => $tpl['posologie'],
    ]
]);
