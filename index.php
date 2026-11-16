
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

// Récupérer les informations de l'utilisateur connecté
$nom = $_SESSION["nom"] ?? "";
$prenom = $_SESSION["prenom"] ?? "";
$role = $_SESSION["role"] ?? "";

// Vérifier que le compte est toujours actif et récupérer son rôle actuel
$verification = $connexion->prepare(
    "SELECT nom, prenom, role, actif FROM utilisateur WHERE id = ?"
);
$verification->execute([$_SESSION["utilisateur_id"]]);
$utilisateur = $verification->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur || (int)$utilisateur["actif"] !== 1) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Actualiser les informations de session
$nom = $utilisateur["nom"];
$prenom = $utilisateur["prenom"];
$role = $utilisateur["role"];

$_SESSION["nom"] = $nom;
$_SESSION["prenom"] = $prenom;
$_SESSION["role"] = $role;

// Statistiques générales
$totalPlats = $connexion->query(
    "SELECT COUNT(*) FROM plats"
)->fetchColumn();

$platsDisponibles = $connexion->query(
    "SELECT COUNT(*) FROM plats WHERE disponible = 1"
)->fetchColumn();

$totalCommandes = $connexion->query(
    "SELECT COUNT(*) FROM commandes"
)->fetchColumn();

$commandesEnAttente = $connexion->query(
    "SELECT COUNT(*) FROM commandes WHERE statut = 'En attente'"
)->fetchColumn();

$totalClients = $connexion->query(
    "SELECT COUNT(*) FROM clients"
)->fetchColumn();

// Définir les rubriques accessibles selon le rôle
$permissions = [

    "Gerant" => [
        "menu",
        "ajouter",
        "commandes",
        "clients",
        "tables",
        "stock",
        "fournisseurs",
        "recettes",
        "employes",
        "paiements"
    ],

    "Caissier" => [
        "menu",
        "commandes",
        "clients",
        "tables",
        "paiements"
    ],

    "Serveur" => [
        "menu",
        "commandes",
        "clients",
        "tables"
    ],

    "Cuisinier" => [
        "menu",
        "commandes",
        "stock",
        "recettes"
    ]
];

// Si le rôle est inconnu, refuser les rubriques
$acces = $permissions[$role] ?? [];

function autorise($rubrique, $acces) {
    return in_array($rubrique, $acces, true);
}

// Seul le Gérant voit les statistiques générales
$voirStatistiques = ($role === "Gerant");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord - Gestion Restaurant</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #1e293b;
            color: white;
            padding: 20px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h2 {
            font-size: 23px;
        }

        .deconnexion {
            background: #dc2626;
            color: white;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
        }

        .container {
            max-width: 1200px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .bienvenue {
            margin-bottom: 30px;
        }

        .bienvenue h1 {
            font-size: 27px;
            margin-bottom: 10px;
        }

        .bienvenue p {
            color: #64748b;
        }

        .role {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 7px 13px;
            border-radius: 20px;
            margin-top: 12px;
            font-size: 13px;
            font-weight: bold;
        }

        .section-title {
            margin-bottom: 20px;
            font-size: 22px;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .stat-card p {
            color: #64748b;
            margin-bottom: 12px;
        }

        .stat-card h2 {
            font-size: 28px;
            color: #2563eb;
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .dashboard-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            text-decoration: none;
            color: #1e293b;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            transition: 0.3s;
            border: 1px solid #e2e8f0;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.09);
        }

        .icon {
            font-size: 38px;
            margin-bottom: 18px;
        }

        .dashboard-card h3 {
            font-size: 19px;
            margin-bottom: 10px;
        }

        .dashboard-card p {
            color: #64748b;
            line-height: 1.5;
            font-size: 14px;
        }

        .aucun-acces {
            background: white;
            padding: 25px;
            border-radius: 12px;
            color: #64748b;
        }

        footer {
            text-align: center;
            padding: 30px;
            color: #64748b;
            margin-top: 30px;
        }

        @media (max-width: 900px) {
            .statistiques {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 550px) {
            header {
                padding: 18px;
            }

            .statistiques,
            .dashboard {
                grid-template-columns: 1fr;
            }

            .bienvenue h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>🍽️ Gestion Restaurant</h2>

    <a href="deconnexion.php" class="deconnexion">
        Déconnexion
    </a>
</header>

<div class="container">

    <div class="bienvenue">
        <h1>
            Bienvenue,
            <?= htmlspecialchars($prenom . " " . $nom) ?> 👋
        </h1>

        <p>Voici votre espace de gestion du restaurant.</p>

        <span class="role">
            Rôle : <?= htmlspecialchars($role) ?>
        </span>
    </div>

    <?php if ($voirStatistiques): ?>

        <h2 class="section-title">Vue d'ensemble</h2>

        <div class="statistiques">

            <div class="stat-card">
                <p>Total des plats</p>
                <h2><?= (int)$totalPlats ?></h2>
            </div>

            <div class="stat-card">
                <p>Plats disponibles</p>
                <h2><?= (int)$platsDisponibles ?></h2>
            </div>

            <div class="stat-card">
                <p>Total des commandes</p>
                <h2><?= (int)$totalCommandes ?></h2>
            </div>

            <div class="stat-card">
                <p>Commandes en attente</p>
                <h2><?= (int)$commandesEnAttente ?></h2>
            </div>

        </div>

    <?php endif; ?>

    <h2 class="section-title">Gestion du restaurant</h2>

    <div class="dashboard">

        <?php if (autorise("menu", $acces)): ?>
        <a href="menu.php" class="dashboard-card">
            <div class="icon">🍽️</div>
            <h3>Gestion des plats</h3>
            <p>Consulter les plats du restaurant.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("ajouter", $acces)): ?>
        <a href="ajouter.php" class="dashboard-card">
            <div class="icon">➕</div>
            <h3>Ajouter un plat</h3>
            <p>Enregistrer de nouveaux plats, leurs prix et leurs photos.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("commandes", $acces)): ?>
        <a href="commandes.php" class="dashboard-card">
            <div class="icon">🛒</div>
            <h3>Gestion des commandes</h3>
            <p>Enregistrer et suivre les commandes des clients.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("paiements", $acces)): ?>
        <a href="paiements.php" class="dashboard-card">
            <div class="icon">💳</div>
            <h3>Gestion des paiements</h3>
            <p>Encaisser les commandes, gérer les modes de paiement et consulter les paiements effectués.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("clients", $acces)): ?>
        <a href="clients.php" class="dashboard-card">
            <div class="icon">👥</div>
            <h3>Gestion des clients</h3>
            <p>Ajouter et consulter les informations des clients.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("tables", $acces)): ?>
        <a href="tables.php" class="dashboard-card">
            <div class="icon">🪑</div>
            <h3>Gestion des tables</h3>
            <p>Gérer les tables, leur capacité et leur disponibilité.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("stock", $acces)): ?>
        <a href="stock.php" class="dashboard-card">
            <div class="icon">📦</div>
            <h3>Gestion des stocks</h3>
            <p>Gérer les ingrédients, les quantités et les mouvements de stock.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("fournisseurs", $acces)): ?>
        <a href="fournisseurs.php" class="dashboard-card">
            <div class="icon">🚚</div>
            <h3>Gestion des fournisseurs</h3>
            <p>Enregistrer les fournisseurs et gérer leurs coordonnées.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("recettes", $acces)): ?>
        <a href="recettes.php" class="dashboard-card">
            <div class="icon">🍲</div>
            <h3>Gestion des recettes</h3>
            <p>Associer les ingrédients aux plats et définir les quantités nécessaires.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("employes", $acces)): ?>
        <a href="employes.php" class="dashboard-card">
            <div class="icon">🧑‍💼</div>
            <h3>Gestion des employés</h3>
            <p>Ajouter des employés, attribuer les rôles et gérer les accès.</p>
        </a>
        <?php endif; ?>

        <?php if (empty($acces)): ?>
        <div class="aucun-acces">
            Aucun module ne vous est actuellement attribué.
            Contactez le Gérant.
        </div>
        <?php endif; ?>

    </div>

</div>

<footer>
    Gestion Restaurant © <?= date("Y") ?> - Tous droits réservés
</footer>

</body>
</html>
