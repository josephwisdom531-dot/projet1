
<?php
// Vérifier les autorisations avant tout affichage
require_once "permissions.php";

$nom = $_SESSION["nom"] ?? "";
$prenom = $_SESSION["prenom"] ?? "";
$role = $_SESSION["role"] ?? "";

$estGerant = ($role === "Gerant");


// Récupérer les critères de recherche
$recherche = trim($_GET["recherche"] ?? "");
$categorie = $_GET["categorie"] ?? "";

// Construire la requête
$sql = "SELECT * FROM plats WHERE 1=1";
$parametres = [];

if ($recherche !== "") {
    $sql .= " AND nom_plat LIKE ?";
    $parametres[] = "%" . $recherche . "%";
}

$categories_autorisees = [
    "Entrée",
    "Plat",
    "Dessert",
    "Boisson"
];

if (in_array($categorie, $categories_autorisees, true)) {
    $sql .= " AND categorie = ?";
    $parametres[] = $categorie;
}

$sql .= " ORDER BY id DESC";

$requete = $connexion->prepare($sql);
$requete->execute($parametres);
$plats = $requete->fetchAll(PDO::FETCH_ASSOC);

$total_plats = count($plats);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mon menu - Gestion Restaurant</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #eef4ff;
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

        .conteneur {
            max-width: 1200px;
            margin: 35px auto;
            padding: 20px;
        }

        .entete {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .bouton {
            display: inline-block;
            padding: 12px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

        .filtres {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filtres input,
        .filtres select {
            flex: 1;
            min-width: 180px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }

        .filtres button {
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            background: #1e293b;
            color: white;
            cursor: pointer;
        }

        .nombre {
            margin-bottom: 20px;
            color: #64748b;
        }

        .grille {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 22px;
        }

        .carte {
            background: white;
            border-radius: 13px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .photo {
            width: 100%;
            height: 190px;
            object-fit: cover;
            background: #e2e8f0;
        }

        .sans-photo {
            height: 190px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #64748b;
        }

        .informations {
            padding: 18px;
        }

        .informations h3 {
            margin-bottom: 10px;
        }

        .categorie {
            color: #64748b;
            margin-bottom: 10px;
        }

        .prix {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 12px;
        }

        .statut {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 15px;
        }

        .disponible {
            background: #dcfce7;
            color: #166534;
        }

        .indisponible {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .modifier,
        .supprimer {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 14px;
        }

        .modifier {
            background: #2563eb;
        }

        .supprimer {
            background: #dc2626;
        }

        .vide {
            background: white;
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            color: #64748b;
        }

        .message {
            padding: 14px;
            background: #dcfce7;
            color: #166534;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .retour {
            display: inline-block;
            margin-top: 30px;
            color: #2563eb;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px;
            }

            .conteneur {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>Gestion Restaurant</h2>
    <span>
        Bienvenue <?= htmlspecialchars($prenom) ?>
        (<?= htmlspecialchars($role) ?>)
    </span>
</header>

<div class="conteneur">

    <div class="entete">
        <div>
            <h1>Gestion de mon menu</h1>
            <p>Consulte les plats de ton restaurant.</p>
        </div>

        <?php if ($estGerant): ?>
            <a href="ajouter.php" class="bouton">
                + Ajouter un plat
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET["ajout"]) && $_GET["ajout"] === "success"): ?>
        <div class="message">
            Le plat a été enregistré avec succès !
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["modification"]) && $_GET["modification"] === "success"): ?>
        <div class="message">
            Le plat a été modifié avec succès !
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["suppression"]) && $_GET["suppression"] === "success"): ?>
        <div class="message">
            Le plat a été supprimé avec succès !
        </div>
    <?php endif; ?>

    <form method="GET" action="menu.php" class="filtres">

        <input
            type="search"
            name="recherche"
            placeholder="Rechercher un plat..."
            value="<?= htmlspecialchars($recherche) ?>"
        >

        <select name="categorie">
            <option value="">Toutes les catégories</option>

            <?php foreach ($categories_autorisees as $cat): ?>
                <option
                    value="<?= htmlspecialchars($cat) ?>"
                    <?= $categorie === $cat ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Rechercher</button>

        <a href="menu.php" class="bouton">Réinitialiser</a>

    </form>

    <p class="nombre">
        <?= $total_plats ?> plat(s) trouvé(s)
    </p>

    <?php if ($total_plats > 0): ?>

        <div class="grille">

            <?php foreach ($plats as $plat): ?>

                <div class="carte">

                    <?php if (
                        !empty($plat["photo"]) &&
                        is_file(__DIR__ . "/" . $plat["photo"])
                    ): ?>

                        <img
                            src="<?= htmlspecialchars($plat["photo"]) ?>"
                            alt="<?= htmlspecialchars($plat["nom_plat"]) ?>"
                            class="photo"
                        >

                    <?php else: ?>

                        <div class="sans-photo">
                            Aucune photo disponible
                        </div>

                    <?php endif; ?>

                    <div class="informations">

                        <h3>
                            <?= htmlspecialchars($plat["nom_plat"]) ?>
                        </h3>

                        <p class="categorie">
                            Catégorie :
                            <?= htmlspecialchars($plat["categorie"]) ?>
                        </p>

                        <p class="prix">
                            <?= number_format(
                                (float)$plat["prix"],
                                0,
                                ",",
                                " "
                            ) ?> FCFA
                        </p>

                        <?php if ((int)$plat["disponible"] === 1): ?>

                            <span class="statut disponible">
                                Disponible
                            </span>

                        <?php else: ?>

                            <span class="statut indisponible">
                                Indisponible
                            </span>

                        <?php endif; ?>

                        <?php if ($estGerant): ?>

                            <div class="actions">

                                <a
                                    href="modifier.php?id=<?= (int)$plat["id"] ?>"
                                    class="modifier"
                                >
                                    Modifier
                                </a>

                                <a
                                    href="supprimer.php?id=<?= (int)$plat["id"] ?>"
                                    class="supprimer"
                                    onclick="return confirm('Voulez-vous vraiment supprimer ce plat ?');"
                                >
                                    Supprimer
                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="vide">
            <h3>Aucun plat trouvé</h3>
            <p>Modifie tes critères de recherche.</p>
        </div>

    <?php endif; ?>

    <a href="index.php" class="retour">
        ← Retour au tableau de bord
    </a>

</div>

</body>
</html>
