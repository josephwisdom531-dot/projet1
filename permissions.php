
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "connexion.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

// Récupérer les informations actuelles de l'utilisateur
$verification = $connexion->prepare(
    "SELECT id, nom, prenom, email, role, actif
     FROM utilisateur
     WHERE id = ?"
);

$verification->execute([$_SESSION["utilisateur_id"]]);
$utilisateur = $verification->fetch(PDO::FETCH_ASSOC);

// Vérifier si le compte existe et est actif
if (!$utilisateur || (int)$utilisateur["actif"] !== 1) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

// Actualiser les informations de session
$_SESSION["nom"] = $utilisateur["nom"];
$_SESSION["prenom"] = $utilisateur["prenom"];
$_SESSION["email"] = $utilisateur["email"];
$_SESSION["role"] = $utilisateur["role"];

// Définir les autorisations par page
$autorisations = [

    // Menu
    "menu.php" => [
        "Gerant",
        "Caissier",
        "Serveur",
        "Cuisinier"
    ],

    "ajouter.php" => [
        "Gerant"
    ],

    "modifier.php" => [
        "Gerant"
    ],

    "supprimer.php" => [
        "Gerant"
    ],

    // Commandes
    "commandes.php" => [
        "Gerant",
        "Caissier",
        "Serveur",
        "Cuisinier"
    ],

    // Clients
    "clients.php" => [
        "Gerant",
        "Caissier",
        "Serveur"
    ],

    // Tables
    "tables.php" => [
        "Gerant",
        "Caissier",
        "Serveur"
    ],

    // Stock
    "stock.php" => [
        "Gerant",
        "Cuisinier"
    ],

    // Fournisseurs
    "fournisseurs.php" => [
        "Gerant"
    ],

    // Recettes
    "recettes.php" => [
        "Gerant",
        "Cuisinier"
    ],

    // Employés
    "employes.php" => [
        "Gerant"
    ],

    // Paiements
    "paiements.php" => [
        "Gerant",
        "Caissier"
    ],

    // Réservations
    "reservations.php" => [
        "Gerant",
        "Caissier",
        "Serveur"
    ]
];

// Identifier la page actuelle
$pageActuelle = basename($_SERVER["PHP_SELF"]);

// Vérifier si la page est déclarée
if (!isset($autorisations[$pageActuelle])) {
    http_response_code(403);
    exit("Accès refusé : cette page n'est pas autorisée.");
}

// Vérifier le rôle de l'utilisateur
if (!in_array(
    $utilisateur["role"],
    $autorisations[$pageActuelle],
    true
)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accès refusé</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #eef4ff;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }

            .message {
                background: white;
                padding: 35px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            h2 {
                color: #dc2626;
            }

            a {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #1e293b;
                color: white;
                text-decoration: none;
                border-radius: 6px;
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Accès refusé</h2>
            <p>Vous n'avez pas les autorisations nécessaires pour accéder à cette page.</p>
            <a href="index.php">Retour au tableau de bord</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
