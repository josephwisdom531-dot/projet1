
<?php

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "connexion.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

// Vérifier le compte directement dans la base de données
$verification = $connexion->prepare(
    "SELECT id, nom, prenom, email, role, actif
     FROM utilisateur
     WHERE id = ?"
);

$verification->execute([$_SESSION["utilisateur_id"]]);

$utilisateur = $verification->fetch(PDO::FETCH_ASSOC);

// Refuser les comptes inexistants ou désactivés
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

    // Gestion des plats
    "menu.php" => [
        "Gerant", "Caissier", "Serveur", "Cuisinier"
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

    // Gestion des commandes
    "commandes.php" => [
        "Gerant", "Caissier", "Serveur", "Cuisinier"
    ],

    // Gestion des clients
    "clients.php" => [
        "Gerant", "Caissier", "Serveur"
    ],

    // Gestion des tables
    "tables.php" => [
        "Gerant", "Caissier", "Serveur"
    ],

    // Gestion du stock
    "stock.php" => [
        "Gerant"
    ],

    // Gestion des fournisseurs
    "fournisseurs.php" => [
        "Gerant"
    ],
"paiements.php" => ["Gerant", "Caissier"],
    // Gestion des recettes
    "recettes.php" => [
        "Gerant", "Cuisinier"
    ],

    // Gestion des employés
    "employes.php" => [
        "Gerant"
    ]
];


// Identifier la page demandée
$pageActuelle = basename($_SERVER["PHP_SELF"]);

// Vérifier si la page figure dans les autorisations
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
        <title>Accès refusé</title>

        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f1f5f9;
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
                max-width: 450px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            }

            h1 {
                color: #dc2626;
            }

            p {
                color: #64748b;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #1e293b;
                color: white;
                text-decoration: none;
                border-radius: 7px;
            }
        </style>
    </head>

    <body>

        <div class="message">
            <h1>⛔ Accès refusé</h1>

            <p>
                Vous n'avez pas l'autorisation d'accéder
                à cette rubrique.
            </p>

            <p>
                Contactez le Gérant si vous pensez
                qu'il s'agit d'une erreur.
            </p>

            <a href="index.php">Retour au tableau de bord</a>
        </div>

    </body>
    </html>

    <?php
    exit;
}
