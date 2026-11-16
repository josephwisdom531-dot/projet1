
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

$message = "";
$erreur = "";

// Ajouter un ingrédient
if (isset($_POST["ajouter"])) {
    $nom = trim($_POST["nom"] ?? "");
    $categorie = trim($_POST["categorie"] ?? "");
    $unite = trim($_POST["unite"] ?? "");
    $quantite = (float) ($_POST["quantite_stock"] ?? 0);
    $seuil = (float) ($_POST["seuil_alerte"] ?? 0);
    $prix = (float) ($_POST["prix_unitaire"] ?? 0);

    $fournisseur = !empty($_POST["fournisseur_id"])
        ? (int) $_POST["fournisseur_id"]
        : null;

    if ($nom === "" || $unite === "") {
        $erreur = "Le nom et l'unité sont obligatoires.";
    } elseif ($quantite < 0 || $seuil < 0 || $prix < 0) {
        $erreur = "Les valeurs numériques ne peuvent pas être négatives.";
    } else {
        try {
            $sql = "INSERT INTO ingredients
                    (nom, categorie, unite, quantite_stock,
                     seuil_alerte, prix_unitaire, fournisseur_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $connexion->prepare($sql);
            $stmt->execute([
                $nom,
                $categorie,
                $unite,
                $quantite,
                $seuil,
                $prix,
                $fournisseur
            ]);

            header("Location: stock.php?ajout=success");
            exit;

        } catch (PDOException $e) {
            $erreur = "Erreur lors de l'ajout de l'ingrédient.";
        }
    }
}

// Supprimer un ingrédient
if (isset($_POST["supprimer"])) {
    $id = (int) $_POST["id"];

    try {
        $stmt = $connexion->prepare(
            "DELETE FROM ingredients WHERE id = ?"
        );
        $stmt->execute([$id]);

        header("Location: stock.php?suppression=success");
        exit;

    } catch (PDOException $e) {
        $erreur = "Impossible de supprimer cet ingrédient : il possède peut-être un historique de stock.";
    }
}

// Messages de confirmation
if (isset($_GET["ajout"])) {
    $message = "Ingrédient ajouté avec succès.";
} elseif (isset($_GET["suppression"])) {
    $message = "Ingrédient supprimé avec succès.";
}

// Récupérer les fournisseurs
$stmt = $connexion->query(
    "SELECT id, nom FROM fournisseurs ORDER BY nom ASC"
);
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ingrédients et leurs fournisseurs
$sql = "SELECT i.*, f.nom AS nom_fournisseur
        FROM ingredients i
        LEFT JOIN fournisseurs f ON i.fournisseur_id = f.id
        ORDER BY i.nom ASC";

$stmt = $connexion->query($sql);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalIngredients = count($ingredients);
$stockFaible = 0;
$valeurStock = 0;

foreach ($ingredients as $ingredient) {
    if ($ingredient["quantite_stock"] <= $ingredient["seuil_alerte"]) {
        $stockFaible++;
    }

    $valeurStock +=
        (float) $ingredient["quantite_stock"] *
        (float) $ingredient["prix_unitaire"];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des stocks</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #263238;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            color: #263238;
        }

        .retour {
            text-decoration: none;
            background: #263238;
            color: white;
            padding: 11px 16px;
            border-radius: 6px;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000010;
        }

        .stat h2 {
            font-size: 25px;
            margin-top: 10px;
        }

        .formulaire, .liste {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px #00000010;
        }

        .formulaire h2, .liste h2 {
            margin-bottom: 20px;
        }

        .champs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        button {
            border: none;
            cursor: pointer;
            padding: 12px 16px;
            border-radius: 6px;
            font-weight: bold;
        }

        .ajouter {
            background: #198754;
            color: white;
        }

        .supprimer {
            background: #dc3545;
            color: white;
        }

        .message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .erreur {
            background: #f8d7da;
            color: #842029;
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alerte {
            display: inline-block;
            background: #fff3cd;
            color: #664d03;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .normal {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f4f6f9;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #777;
        }

        @media (max-width: 750px) {
            body {
                padding: 12px;
            }

            .champs {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>📦 Gestion des stocks</h1>
        <a href="index.php" class="retour">← Tableau de bord</a>
    </div>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <div class="erreur">
            <?= htmlspecialchars($erreur) ?>
        </div>
    <?php endif; ?>

    <div class="statistiques">

        <div class="stat">
            <p>Total des ingrédients</p>
            <h2><?= $totalIngredients ?></h2>
        </div>

        <div class="stat">
            <p>Stocks faibles</p>
            <h2><?= $stockFaible ?></h2>
        </div>

        <div class="stat">
            <p>Valeur estimée du stock</p>
            <h2>
                <?= number_format($valeurStock, 0, ',', ' ') ?> FCFA
            </h2>
        </div>

    </div>

    <div class="formulaire">

        <h2>Ajouter un ingrédient</h2>

        <form method="POST">

            <div class="champs">

                <div>
                    <label>Nom de l'ingrédient *</label>
                    <input
                        type="text"
                        name="nom"
                        placeholder="Exemple : Riz"
                        required
                    >
                </div>

                <div>
                    <label>Catégorie</label>
                    <input
                        type="text"
                        name="categorie"
                        placeholder="Exemple : Céréales"
                    >
                </div>

                <div>
                    <label>Unité *</label>
                    <select name="unite" required>
                        <option value="">Choisir une unité</option>
                        <option value="Kg">Kilogramme (Kg)</option>
                        <option value="Litre">Litre</option>
                        <option value="Pièce">Pièce</option>
                        <option value="Paquet">Paquet</option>
                        <option value="Sac">Sac</option>
                        <option value="Carton">Carton</option>
                        <option value="Bouteille">Bouteille</option>
                    </select>
                </div>

                <div>
                    <label>Quantité disponible *</label>
                    <input
                        type="number"
                        name="quantite_stock"
                        min="0"
                        step="0.01"
                        value="0"
                        required
                    >
                </div>

                <div>
                    <label>Seuil d'alerte *</label>
                    <input
                        type="number"
                        name="seuil_alerte"
                        min="0"
                        step="0.01"
                        value="5"
                        required
                    >
                </div>

                <div>
                    <label>Prix unitaire (FCFA) *</label>
                    <input
                        type="number"
                        name="prix_unitaire"
                        min="0"
                        step="0.01"
                        value="0"
                        required
                    >
                </div>

                <div>
                    <label>Fournisseur</label>
                    <select name="fournisseur_id">
                        <option value="">Aucun fournisseur</option>

                        <?php foreach ($fournisseurs as $fournisseur): ?>
                            <option value="<?= (int) $fournisseur["id"] ?>">
                                <?= htmlspecialchars($fournisseur["nom"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <button type="submit" name="ajouter" class="ajouter">
                + Ajouter l'ingrédient
            </button>

        </form>

    </div>

    <div class="liste">

        <h2>Liste des ingrédients</h2>

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>Ingrédient</th>
                        <th>Catégorie</th>
                        <th>Quantité</th>
                        <th>Seuil d'alerte</th>
                        <th>Prix unitaire</th>
                        <th>Fournisseur</th>
                        <th>État du stock</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($ingredients) > 0): ?>

                    <?php foreach ($ingredients as $ingredient): ?>

                        <?php
                        $faible =
                            (float) $ingredient["quantite_stock"] <=
                            (float) $ingredient["seuil_alerte"];
                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($ingredient["nom"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($ingredient["categorie"] ?? "") ?>
                            </td>

                            <td>
                                <?= number_format((float) $ingredient["quantite_stock"], 2, ',', ' ') ?>
                                <?= htmlspecialchars($ingredient["unite"]) ?>
                            </td>

                            <td>
                                <?= number_format((float) $ingredient["seuil_alerte"], 2, ',', ' ') ?>
                                <?= htmlspecialchars($ingredient["unite"]) ?>
                            </td>

                            <td>
                                <?= number_format((float) $ingredient["prix_unitaire"], 0, ',', ' ') ?>
                                FCFA
                            </td>

                            <td>
                                <?= htmlspecialchars($ingredient["nom_fournisseur"] ?? "Non renseigné") ?>
                            </td>

                            <td>
                                <?php if ($faible): ?>
                                    <span class="alerte">Stock faible</span>
                                <?php else: ?>
                                    <span class="normal">Disponible</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="POST"
                                      onsubmit="return confirm('Supprimer cet ingrédient ?');">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $ingredient["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="supprimer"
                                        class="supprimer"
                                    >
                                        Supprimer
                                    </button>

                                </form>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="vide">
                            Aucun ingrédient enregistré.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
