
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

$message = "";
$erreur = "";

// Ajouter un fournisseur
if (isset($_POST["ajouter"])) {
    $nom = trim($_POST["nom"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");

    if ($nom === "") {
        $erreur = "Le nom du fournisseur est obligatoire.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Veuillez saisir une adresse e-mail valide.";
    } else {
        $sql = "INSERT INTO fournisseurs (nom, telephone, email, adresse)
                VALUES (?, ?, ?, ?)";
        $stmt = $connexion->prepare($sql);
        $stmt->execute([$nom, $telephone, $email, $adresse]);

        header("Location: fournisseurs.php?ajout=success");
        exit;
    }
}

// Modifier un fournisseur
if (isset($_POST["modifier"])) {
    $id = (int) ($_POST["id"] ?? 0);
    $nom = trim($_POST["nom"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");

    if ($id <= 0 || $nom === "") {
        $erreur = "Informations de modification incorrectes.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Veuillez saisir une adresse e-mail valide.";
    } else {
        $sql = "UPDATE fournisseurs
                SET nom = ?, telephone = ?, email = ?, adresse = ?
                WHERE id = ?";

        $stmt = $connexion->prepare($sql);
        $stmt->execute([$nom, $telephone, $email, $adresse, $id]);

        header("Location: fournisseurs.php?modification=success");
        exit;
    }
}

// Supprimer un fournisseur
if (isset($_POST["supprimer"])) {
    $id = (int) ($_POST["id"] ?? 0);

    try {
        $stmt = $connexion->prepare(
            "DELETE FROM fournisseurs WHERE id = ?"
        );
        $stmt->execute([$id]);

        header("Location: fournisseurs.php?suppression=success");
        exit;

    } catch (PDOException $e) {
        $erreur = "Impossible de supprimer ce fournisseur.";
    }
}

// Messages
if (isset($_GET["ajout"])) {
    $message = "Fournisseur ajouté avec succès.";
} elseif (isset($_GET["modification"])) {
    $message = "Fournisseur modifié avec succès.";
} elseif (isset($_GET["suppression"])) {
    $message = "Fournisseur supprimé avec succès.";
}

// Fournisseur à modifier
$fournisseurAModifier = null;

if (isset($_GET["modifier"])) {
    $id = (int) $_GET["modifier"];

    $stmt = $connexion->prepare(
        "SELECT * FROM fournisseurs WHERE id = ?"
    );
    $stmt->execute([$id]);

    $fournisseurAModifier = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les fournisseurs et le nombre d'ingrédients associés
$sql = "SELECT f.*,
        COUNT(i.id) AS nombre_ingredients
        FROM fournisseurs f
        LEFT JOIN ingredients i ON i.fournisseur_id = f.id
        GROUP BY f.id
        ORDER BY f.nom ASC";

$stmt = $connexion->query($sql);
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFournisseurs = count($fournisseurs);
$totalIngredients = 0;

foreach ($fournisseurs as $f) {
    $totalIngredients += (int) $f["nombre_ingredients"];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des fournisseurs</title>

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
            max-width: 1150px;
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
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000010;
        }

        .stat h2 {
            font-size: 28px;
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
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            margin-bottom: 15px;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        button {
            border: none;
            cursor: pointer;
            padding: 11px 15px;
            border-radius: 6px;
            font-weight: bold;
        }

        .ajouter {
            background: #198754;
            color: white;
        }

        .modifier {
            background: #0d6efd;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 5px;
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

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f4f6f9;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #777;
        }

        .annuler {
            display: inline-block;
            margin-left: 10px;
            color: #555;
        }

        @media (max-width: 650px) {
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
        <h1>🚚 Gestion des fournisseurs</h1>

        <a href="index.php" class="retour">
            ← Tableau de bord
        </a>
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
            <p>Total des fournisseurs</p>
            <h2><?= $totalFournisseurs ?></h2>
        </div>

        <div class="stat">
            <p>Ingrédients associés</p>
            <h2><?= $totalIngredients ?></h2>
        </div>

    </div>

    <div class="formulaire">

        <h2>
            <?= $fournisseurAModifier
                ? "Modifier un fournisseur"
                : "Ajouter un fournisseur" ?>
        </h2>

        <form method="POST">

            <?php if ($fournisseurAModifier): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $fournisseurAModifier["id"] ?>"
                >
            <?php endif; ?>

            <div class="champs">

                <div>
                    <label>Nom du fournisseur *</label>
                    <input
                        type="text"
                        name="nom"
                        placeholder="Nom du fournisseur"
                        value="<?= htmlspecialchars($fournisseurAModifier["nom"] ?? "") ?>"
                        required
                    >
                </div>

                <div>
                    <label>Téléphone</label>
                    <input
                        type="tel"
                        name="telephone"
                        placeholder="Exemple : 01XXXXXXXX"
                        value="<?= htmlspecialchars($fournisseurAModifier["telephone"] ?? "") ?>"
                    >
                </div>

                <div>
                    <label>Adresse e-mail</label>
                    <input
                        type="email"
                        name="email"
                        placeholder="exemple@gmail.com"
                        value="<?= htmlspecialchars($fournisseurAModifier["email"] ?? "") ?>"
                    >
                </div>

                <div>
                    <label>Adresse</label>
                    <textarea
                        name="adresse"
                        placeholder="Adresse du fournisseur"
                    ><?= htmlspecialchars($fournisseurAModifier["adresse"] ?? "") ?></textarea>
                </div>

            </div>

            <?php if ($fournisseurAModifier): ?>

                <button type="submit" name="modifier" class="ajouter">
                    Enregistrer les modifications
                </button>

                <a href="fournisseurs.php" class="annuler">
                    Annuler
                </a>

            <?php else: ?>

                <button type="submit" name="ajouter" class="ajouter">
                    + Ajouter le fournisseur
                </button>

            <?php endif; ?>

        </form>

    </div>

    <div class="liste">

        <h2>Liste des fournisseurs</h2>

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>E-mail</th>
                        <th>Adresse</th>
                        <th>Ingrédients associés</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($fournisseurs) > 0): ?>

                    <?php foreach ($fournisseurs as $f): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($f["nom"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($f["telephone"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($f["email"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($f["adresse"] ?? "") ?>
                            </td>

                            <td>
                                <?= (int) $f["nombre_ingredients"] ?>
                            </td>

                            <td>

                                <a
                                    href="fournisseurs.php?modifier=<?= (int) $f["id"] ?>"
                                    class="modifier"
                                >
                                    Modifier
                                </a>

                                <form method="POST"
                                      style="display:inline"
                                      onsubmit="return confirm('Voulez-vous vraiment supprimer ce fournisseur ?');">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $f["id"] ?>"
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
                        <td colspan="6" class="vide">
                            Aucun fournisseur enregistré.
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
