
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

// Récupérer les informations de l'utilisateur connecté
$verification = $connexion->prepare(
    "SELECT id, nom, prenom, role, actif
     FROM utilisateur
     WHERE id = ?"
);

$verification->execute([$_SESSION["utilisateur_id"]]);
$utilisateur = $verification->fetch(PDO::FETCH_ASSOC);

// Vérifier que le compte est toujours actif
if (!$utilisateur || (int)$utilisateur["actif"] !== 1) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}

// Actualiser les informations de session
$_SESSION["nom"] = $utilisateur["nom"];
$_SESSION["prenom"] = $utilisateur["prenom"];
$_SESSION["role"] = $utilisateur["role"];

$nom = $utilisateur["nom"];
$prenom = $utilisateur["prenom"];
$role = $utilisateur["role"];

// Définir les permissions du tableau de bord
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
        "paiements",
        "reservations"
    ],

    "Caissier" => [
        "menu",
        "commandes",
        "clients",
        "tables",
        "paiements",
        "reservations"
    ],

    "Serveur" => [
        "menu",
        "commandes",
        "clients",
        "tables",
        "reservations"
    ],

    "Cuisinier" => [
        "menu",
        "commandes",
        "stock",
        "recettes"
    ]
];

$acces = $permissions[$role] ?? [];

// Fonction de vérification des autorisations
function autorise($module, $acces) {
    return in_array($module, $acces, true);
}

// Statistiques générales
$totalPlats = 0;
$platsDisponibles = 0;
$totalCommandes = 0;
$commandesEnAttente = 0;
$totalClients = 0;

if ($role === "Gerant") {

    $totalPlats = (int)$connexion->query(
        "SELECT COUNT(*) FROM plats"
    )->fetchColumn();

    $platsDisponibles = (int)$connexion->query(
        "SELECT COUNT(*) FROM plats WHERE disponible = 1"
    )->fetchColumn();

    $totalCommandes = (int)$connexion->query(
        "SELECT COUNT(*) FROM commandes"
    )->fetchColumn();

    $commandesEnAttente = (int)$connexion->query(
        "SELECT COUNT(*) FROM commandes
         WHERE statut = 'En attente'"
    )->fetchColumn();

    $totalClients = (int)$connexion->query(
        "SELECT COUNT(*) FROM clients"
    )->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord - Restaurant</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #1e293b;
            color: white;
            padding: 22px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0;
            font-size: 25px;
        }

        .utilisateur {
            font-size: 14px;
            line-height: 1.7;
        }

        .deconnexion {
            background: #dc2626;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
        }

        .conteneur {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .bienvenue {
            margin-bottom: 25px;
        }

        .bienvenue h2 {
            margin-bottom: 8px;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 35px;
        }

        .statistique {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }

        .statistique h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #64748b;
        }

        .statistique p {
            margin: 0;
            font-size: 27px;
            font-weight: bold;
        }

        .titre-section {
            margin-bottom: 20px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-decoration: none;
            color: #1e293b;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
            transition: 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 7px 18px rgba(0,0,0,0.12);
        }

        .dashboard-card .icon {
            font-size: 35px;
            margin-bottom: 12px;
        }

        .dashboard-card h3 {
            margin: 0 0 10px;
        }

        .dashboard-card p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
        }

        @media (max-width: 850px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 550px) {
            header {
                padding: 20px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .conteneur {
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <div>
        <h1>Tableau de bord - Restaurant</h1>
        <div class="utilisateur">
            Bienvenue, <?= htmlspecialchars($prenom . " " . $nom) ?>
            <br>
            Fonction : <?= htmlspecialchars($role) ?>
        </div>
    </div>

    <a href="deconnexion.php" class="deconnexion">
        Déconnexion
    </a>
</header>

<main class="conteneur">

    <section class="bienvenue">
        <h2>Bienvenue dans votre espace de gestion</h2>
        <p>Gérez les différentes activités de votre restaurant depuis ce tableau de bord.</p>
    </section>

    <?php if ($role === "Gerant"): ?>

    <h2 class="titre-section">Statistiques générales</h2>

    <section class="statistiques">

        <div class="statistique">
            <h3>Total des plats</h3>
            <p><?= $totalPlats ?></p>
        </div>

        <div class="statistique">
            <h3>Plats disponibles</h3>
            <p><?= $platsDisponibles ?></p>
        </div>

        <div class="statistique">
            <h3>Total des commandes</h3>
            <p><?= $totalCommandes ?></p>
        </div>

        <div class="statistique">
            <h3>Commandes en attente</h3>
            <p><?= $commandesEnAttente ?></p>
        </div>

        <div class="statistique">
            <h3>Total des clients</h3>
            <p><?= $totalClients ?></p>
        </div>

    </section>

    <?php endif; ?>

    <h2 class="titre-section">Gestion du restaurant</h2>

    <section class="dashboard-grid">

        <?php if (autorise("menu", $acces)): ?>
        <a href="menu.php" class="dashboard-card">
            <div class="icon">🍽️</div>
            <h3>Gestion du menu</h3>
            <p>Consulter les plats, les catégories et les prix.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("ajouter", $acces)): ?>
        <a href="ajouter.php" class="dashboard-card">
            <div class="icon">➕</div>
            <h3>Ajouter un plat</h3>
            <p>Enregistrer de nouveaux plats dans le menu.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("commandes", $acces)): ?>
        <a href="commandes.php" class="dashboard-card">
            <div class="icon">📝</div>
            <h3>Gestion des commandes</h3>
            <p>Créer, suivre et gérer les commandes.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("clients", $acces)): ?>
        <a href="clients.php" class="dashboard-card">
            <div class="icon">👥</div>
            <h3>Gestion des clients</h3>
            <p>Enregistrer et consulter les informations des clients.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("tables", $acces)): ?>
        <a href="tables.php" class="dashboard-card">
            <div class="icon">🪑</div>
            <h3>Gestion des tables</h3>
            <p>Consulter et gérer les tables du restaurant.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("stock", $acces)): ?>
        <a href="stock.php" class="dashboard-card">
            <div class="icon">📦</div>
            <h3>Gestion du stock</h3>
            <p>Suivre les ingrédients et les mouvements de stock.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("fournisseurs", $acces)): ?>
        <a href="fournisseurs.php" class="dashboard-card">
            <div class="icon">🚚</div>
            <h3>Gestion des fournisseurs</h3>
            <p>Enregistrer et gérer les fournisseurs.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("recettes", $acces)): ?>
        <a href="recettes.php" class="dashboard-card">
            <div class="icon">📋</div>
            <h3>Gestion des recettes</h3>
            <p>Gérer les ingrédients nécessaires à chaque plat.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("employes", $acces)): ?>
        <a href="employes.php" class="dashboard-card">
            <div class="icon">👨‍🍳</div>
            <h3>Gestion des employés</h3>
            <p>Gérer les employés, les comptes et les rôles.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("paiements", $acces)): ?>
        <a href="paiements.php" class="dashboard-card">
            <div class="icon">💳</div>
            <h3>Gestion des paiements</h3>
            <p>Encaisser les commandes et consulter les paiements.</p>
        </a>
        <?php endif; ?>

        <?php if (autorise("reservations", $acces)): ?>
        <a href="reservations.php" class="dashboard-card">
            <div class="icon">📅</div>
            <h3>Gestion des réservations</h3>
            <p>Enregistrer, confirmer et suivre les réservations des clients.</p>
        </a>
        <?php endif; ?>

    </section>

</main>

</body>
</html>
