
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

$message = "";
$type_message = "success";

// Récupérer les plats
$plats = $connexion->query("
    SELECT id, nom_plat
    FROM plats
    ORDER BY nom_plat ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les ingrédients
$ingredients = $connexion->query("
    SELECT id, nom, unite
    FROM ingredients
    ORDER BY nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Valeurs du formulaire
$recette_modifier = null;
$details_modifier = [];

// Charger une recette à modifier
if (isset($_GET["modifier"])) {
    $id = filter_input(INPUT_GET, "modifier", FILTER_VALIDATE_INT);

    if ($id) {
        $requete = $connexion->prepare("
            SELECT * FROM recettes WHERE id = ?
        ");
        $requete->execute([$id]);
        $recette_modifier = $requete->fetch(PDO::FETCH_ASSOC);

        if ($recette_modifier) {
            $requete = $connexion->prepare("
                SELECT ingredient_id, quantite
                FROM details_recette
                WHERE recette_id = ?
            ");
            $requete->execute([$id]);
            $details_modifier = $requete->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Enregistrer une recette
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // Suppression
    if ($action === "supprimer") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

        if ($id) {
            $requete = $connexion->prepare(
                "DELETE FROM recettes WHERE id = ?"
            );
            $requete->execute([$id]);

            $message = "Recette supprimée avec succès.";
        }
    }

    // Ajout ou modification
    if ($action === "enregistrer") {

        $recette_id = filter_input(
            INPUT_POST,
            "recette_id",
            FILTER_VALIDATE_INT
        );

        $plat_id = filter_input(
            INPUT_POST,
            "plat_id",
            FILTER_VALIDATE_INT
        );

        $description = trim($_POST["description"] ?? "");

        $ingredient_ids = $_POST["ingredient_id"] ?? [];
        $quantites = $_POST["quantite"] ?? [];

        try {
            if (!$plat_id) {
                throw new Exception("Veuillez sélectionner un plat.");
            }

            if (count($ingredient_ids) === 0) {
                throw new Exception("Ajoutez au moins un ingrédient.");
            }

            if (count($ingredient_ids) !== count($quantites)) {
                throw new Exception("Les données des ingrédients sont incorrectes.");
            }

            $details = [];
            $ingredients_vus = [];

            foreach ($ingredient_ids as $index => $ingredient_id) {
                $ingredient_id = filter_var(
                    $ingredient_id,
                    FILTER_VALIDATE_INT
                );

                $quantite = filter_var(
                    $quantites[$index],
                    FILTER_VALIDATE_FLOAT
                );

                if (!$ingredient_id || $quantite === false || $quantite <= 0) {
                    throw new Exception("Vérifiez les ingrédients et leurs quantités.");
                }

                if (in_array($ingredient_id, $ingredients_vus)) {
                    throw new Exception("Un ingrédient ne doit apparaître qu'une seule fois.");
                }

                $ingredients_vus[] = $ingredient_id;

                $details[] = [
                    "ingredient_id" => $ingredient_id,
                    "quantite" => $quantite
                ];
            }

            // Vérifier que le plat existe
            $verifier = $connexion->prepare(
                "SELECT id FROM plats WHERE id = ?"
            );
            $verifier->execute([$plat_id]);

            if (!$verifier->fetch()) {
                throw new Exception("Le plat sélectionné n'existe pas.");
            }

            // Vérifier que tous les ingrédients existent
            $verifier = $connexion->prepare(
                "SELECT id FROM ingredients WHERE id = ?"
            );

            foreach ($details as $detail) {
                $verifier->execute([$detail["ingredient_id"]]);

                if (!$verifier->fetch()) {
                    throw new Exception("Un ingrédient sélectionné n'existe pas.");
                }
            }

            $connexion->beginTransaction();

            if ($recette_id) {
                // Modifier la recette
                $requete = $connexion->prepare("
                    UPDATE recettes
                    SET plat_id = ?, description = ?
                    WHERE id = ?
                ");
                $requete->execute([
                    $plat_id,
                    $description,
                    $recette_id
                ]);

                if ($requete->rowCount() === 0) {
                    $verifier = $connexion->prepare(
                        "SELECT id FROM recettes WHERE id = ?"
                    );
                    $verifier->execute([$recette_id]);

                    if (!$verifier->fetch()) {
                        throw new Exception("Recette introuvable.");
                    }
                }

                $requete = $connexion->prepare(
                    "DELETE FROM details_recette WHERE recette_id = ?"
                );
                $requete->execute([$recette_id]);

            } else {
                // Vérifier qu'une recette n'existe pas déjà pour ce plat
                $verifier = $connexion->prepare(
                    "SELECT id FROM recettes WHERE plat_id = ?"
                );
                $verifier->execute([$plat_id]);

                if ($verifier->fetch()) {
                    throw new Exception("Ce plat possède déjà une recette.");
                }

                $requete = $connexion->prepare("
                    INSERT INTO recettes (plat_id, description)
                    VALUES (?, ?)
                ");
                $requete->execute([$plat_id, $description]);

                $recette_id = $connexion->lastInsertId();
            }

            // Enregistrer les ingrédients de la recette
            $requete = $connexion->prepare("
                INSERT INTO details_recette
                (recette_id, ingredient_id, quantite)
                VALUES (?, ?, ?)
            ");

            foreach ($details as $detail) {
                $requete->execute([
                    $recette_id,
                    $detail["ingredient_id"],
                    $detail["quantite"]
                ]);
            }

            $connexion->commit();

            header("Location: recettes.php?success=1");
            exit;

        } catch (Exception $e) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            $message = $e->getMessage();
            $type_message = "error";
        } catch (PDOException $e) {
            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            $message = "Une erreur est survenue lors de l'enregistrement.";
            $type_message = "error";
        }
    }
}

// Message de confirmation
if (isset($_GET["success"])) {
    $message = "Recette enregistrée avec succès.";
}

// Récupérer toutes les recettes
$recettes = $connexion->query("
    SELECT
        r.id,
        r.description,
        r.date_creation,
        p.nom_plat,
        COUNT(d.id) AS nombre_ingredients
    FROM recettes r
    INNER JOIN plats p ON p.id = r.plat_id
    LEFT JOIN details_recette d ON d.recette_id = r.id
    GROUP BY r.id, r.description, r.date_creation, p.nom_plat
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails de chaque recette
$details_recettes = [];

foreach ($recettes as $recette) {
    $requete = $connexion->prepare("
        SELECT i.nom, i.unite, d.quantite
        FROM details_recette d
        INNER JOIN ingredients i ON i.id = d.ingredient_id
        WHERE d.recette_id = ?
        ORDER BY i.nom
    ");

    $requete->execute([$recette["id"]]);

    $details_recettes[$recette["id"]] =
        $requete->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des recettes</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #333;
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
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            color: #1e293b;
        }

        .btn {
            display: inline-block;
            border: none;
            padding: 11px 16px;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        h2 {
            margin-bottom: 18px;
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            min-height: 75px;
        }

        .ingredient-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .ingredient-row button {
            padding: 10px 13px;
            border: none;
            border-radius: 7px;
            background: #dc2626;
            color: white;
            cursor: pointer;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .ingredient-list {
            line-height: 1.8;
        }

        @media (max-width: 650px) {
            body {
                padding: 12px;
            }

            .ingredient-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>🍲 Gestion des recettes</h1>
            <p>Associez les ingrédients aux plats de votre restaurant.</p>
        </div>

        <a href="index.php" class="btn btn-secondary">
            ← Tableau de bord
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $type_message ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2>
            <?= $recette_modifier ? "Modifier une recette" : "Créer une recette" ?>
        </h2>

        <?php if (empty($plats)): ?>
            <p>Vous devez d'abord enregistrer au moins un plat.</p>

        <?php elseif (empty($ingredients)): ?>
            <p>Vous devez d'abord enregistrer des ingrédients dans le stock.</p>

        <?php else: ?>

        <form method="POST" action="recettes.php">

            <input type="hidden" name="action" value="enregistrer">

            <input
                type="hidden"
                name="recette_id"
                value="<?= htmlspecialchars((string)($recette_modifier["id"] ?? "")) ?>"
            >

            <div class="form-group">
                <label>Choisir le plat</label>

                <select name="plat_id" required>
                    <option value="">-- Sélectionner un plat --</option>

                    <?php foreach ($plats as $plat): ?>
                        <option
                            value="<?= (int)$plat["id"] ?>"
                            <?= isset($recette_modifier["plat_id"]) &&
                                $recette_modifier["plat_id"] == $plat["id"]
                                ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($plat["nom_plat"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description de la recette</label>

                <textarea
                    name="description"
                    placeholder="Exemple : Préparation du riz avec la sauce..."
                ><?= htmlspecialchars($recette_modifier["description"] ?? "") ?></textarea>
            </div>

            <h3 style="margin-bottom: 12px;">Ingrédients nécessaires</h3>

            <div id="ingredients-container">

                <?php
                $lignes = $recette_modifier
                    ? $details_modifier
                    : [["ingredient_id" => "", "quantite" => ""]];
                ?>

                <?php foreach ($lignes as $ligne): ?>
                    <div class="ingredient-row">

                        <select name="ingredient_id[]" required>
                            <option value="">-- Choisir un ingrédient --</option>

                            <?php foreach ($ingredients as $ingredient): ?>
                                <option
                                    value="<?= (int)$ingredient["id"] ?>"
                                    <?= $ligne["ingredient_id"] == $ingredient["id"]
                                        ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($ingredient["nom"]) ?>
                                    (<?= htmlspecialchars($ingredient["unite"]) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input
                            type="number"
                            name="quantite[]"
                            min="0.001"
                            step="0.001"
                            placeholder="Quantité"
                            value="<?= htmlspecialchars((string)$ligne["quantite"]) ?>"
                            required
                        >

                        <button type="button" onclick="supprimerIngredient(this)">
                            Retirer
                        </button>

                    </div>
                <?php endforeach; ?>

            </div>

            <button
                type="button"
                class="btn btn-secondary"
                onclick="ajouterIngredient()"
                style="margin-bottom: 20px;"
            >
                + Ajouter un ingrédient
            </button>

            <br>

            <button type="submit" class="btn btn-primary">
                <?= $recette_modifier ? "Enregistrer les modifications" : "Enregistrer la recette" ?>
            </button>

            <?php if ($recette_modifier): ?>
                <a href="recettes.php" class="btn btn-secondary">
                    Annuler
                </a>
            <?php endif; ?>

        </form>

        <?php endif; ?>

    </div>

    <div class="card">

        <h2>📋 Liste des recettes</h2>

        <div class="table-wrapper">

            <table>
                <thead>
                    <tr>
                        <th>Plat</th>
                        <th>Ingrédients et quantités</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($recettes)): ?>

                    <tr>
                        <td colspan="4">Aucune recette enregistrée.</td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recettes as $recette): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($recette["nom_plat"]) ?>
                                </strong>
                                <br>
                                <small>
                                    <?= (int)$recette["nombre_ingredients"] ?>
                                    ingrédient(s)
                                </small>
                            </td>

                            <td class="ingredient-list">
                                <?php foreach ($details_recettes[$recette["id"]] as $detail): ?>
                                    • <?= htmlspecialchars($detail["nom"]) ?> :
                                    <strong>
                                        <?= htmlspecialchars((string)$detail["quantite"]) ?>
                                        <?= htmlspecialchars($detail["unite"]) ?>
                                    </strong>
                                    <br>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <?= nl2br(htmlspecialchars($recette["description"] ?? "")) ?>
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        href="recettes.php?modifier=<?= (int)$recette["id"] ?>"
                                        class="btn btn-warning"
                                    >
                                        Modifier
                                    </a>

                                    <form method="POST" action="recettes.php"
                                        onsubmit="return confirm('Voulez-vous vraiment supprimer cette recette ?');">

                                        <input type="hidden" name="action" value="supprimer">
                                        <input type="hidden" name="id" value="<?= (int)$recette["id"] ?>">

                                        <button type="submit" class="btn btn-danger">
                                            Supprimer
                                        </button>

                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

<script>
const listeIngredients = <?= json_encode(
    $ingredients,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

function ajouterIngredient() {
    const container = document.getElementById("ingredients-container");

    const ligne = document.createElement("div");
    ligne.className = "ingredient-row";

    const select = document.createElement("select");
    select.name = "ingredient_id[]";
    select.required = true;

    const optionVide = document.createElement("option");
    optionVide.value = "";
    optionVide.textContent = "-- Choisir un ingrédient --";
    select.appendChild(optionVide);

    listeIngredients.forEach(function(ingredient) {
        const option = document.createElement("option");
        option.value = ingredient.id;
        option.textContent = ingredient.nom + " (" + ingredient.unite + ")";
        select.appendChild(option);
    });

    const quantite = document.createElement("input");
    quantite.type = "number";
    quantite.name = "quantite[]";
    quantite.min = "0.001";
    quantite.step = "0.001";
    quantite.placeholder = "Quantité";
    quantite.required = true;

    const bouton = document.createElement("button");
    bouton.type = "button";
    bouton.textContent = "Retirer";
    bouton.onclick = function() {
        supprimerIngredient(bouton);
    };

    ligne.appendChild(select);
    ligne.appendChild(quantite);
    ligne.appendChild(bouton);

    container.appendChild(ligne);
}

function supprimerIngredient(bouton) {
    const container = document.getElementById("ingredients-container");

    if (container.children.length > 1) {
        bouton.parentElement.remove();
    } else {
        alert("Une recette doit contenir au moins un ingrédient.");
    }
}
</script>

</body>
</html>
