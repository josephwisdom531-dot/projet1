
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

$message = "";
$erreur = "";

// Ajouter une table
if (isset($_POST["ajouter"])) {
    $numero = trim($_POST["numero_table"]);
    $capacite = (int) $_POST["capacite"];

    if ($numero === "" || $capacite < 1) {
        $erreur = "Veuillez remplir correctement tous les champs.";
    } else {
        try {
            $sql = "INSERT INTO tables_restaurant
                    (numero_table, capacite, statut)
                    VALUES (?, ?, 'Libre')";
            $stmt = $connexion->prepare($sql);
            $stmt->execute([$numero, $capacite]);

            header("Location: tables.php?ajout=success");
            exit;
        } catch (PDOException $e) {
            $erreur = "Ce numéro de table existe déjà ou une erreur est survenue.";
        }
    }
}

// Modifier le statut d'une table
if (isset($_POST["modifier_statut"])) {
    $id = (int) $_POST["id"];
    $statut = $_POST["statut"];

    $statutsAutorises = ["Libre", "Occupée", "Réservée"];

    if (in_array($statut, $statutsAutorises, true)) {
        $stmt = $connexion->prepare(
            "UPDATE tables_restaurant SET statut = ? WHERE id = ?"
        );
        $stmt->execute([$statut, $id]);

        header("Location: tables.php?modification=success");
        exit;
    }
}

// Supprimer une table
if (isset($_POST["supprimer"])) {
    $id = (int) $_POST["id"];

    $stmt = $connexion->prepare(
        "DELETE FROM tables_restaurant WHERE id = ?"
    );
    $stmt->execute([$id]);

    header("Location: tables.php?suppression=success");
    exit;
}

// Messages
if (isset($_GET["ajout"])) {
    $message = "Table ajoutée avec succès.";
} elseif (isset($_GET["modification"])) {
    $message = "Statut modifié avec succès.";
} elseif (isset($_GET["suppression"])) {
    $message = "Table supprimée avec succès.";
}

// Récupérer les tables
$stmt = $connexion->query(
    "SELECT * FROM tables_restaurant ORDER BY numero_table ASC"
);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalTables = count($tables);
$libres = 0;
$occupees = 0;
$reservees = 0;

foreach ($tables as $table) {
    if ($table["statut"] === "Libre") {
        $libres++;
    } elseif ($table["statut"] === "Occupée") {
        $occupees++;
    } elseif ($table["statut"] === "Réservée") {
        $reservees++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des tables</title>

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
            max-width: 1100px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 28px;
            margin-top: 8px;
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
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
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

        table {
            width: 100%;
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

        .statut-form {
            display: flex;
            gap: 8px;
            min-width: 210px;
        }

        .statut-form button {
            background: #0d6efd;
            color: white;
        }

        .supprimer {
            background: #dc3545;
            color: white;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .libre {
            background: #d1e7dd;
            color: #0f5132;
        }

        .occupee {
            background: #f8d7da;
            color: #842029;
        }

        .reservee {
            background: #fff3cd;
            color: #664d03;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #777;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .champs {
                grid-template-columns: 1fr;
            }

            .liste {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <h1>🪑 Gestion des tables</h1>
        <a href="index.php" class="retour">← Tableau de bord</a>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <div class="erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="statistiques">
        <div class="stat">
            <p>Total des tables</p>
            <h2><?= $totalTables ?></h2>
        </div>

        <div class="stat">
            <p>Tables libres</p>
            <h2><?= $libres ?></h2>
        </div>

        <div class="stat">
            <p>Tables occupées</p>
            <h2><?= $occupees ?></h2>
        </div>

        <div class="stat">
            <p>Tables réservées</p>
            <h2><?= $reservees ?></h2>
        </div>
    </div>

    <div class="formulaire">
        <h2>Ajouter une nouvelle table</h2>

        <form method="POST">
            <div class="champs">

                <div>
                    <label>Numéro de table</label>
                    <input
                        type="text"
                        name="numero_table"
                        placeholder="Exemple : Table 01"
                        required
                    >
                </div>

                <div>
                    <label>Nombre de places</label>
                    <input
                        type="number"
                        name="capacite"
                        min="1"
                        placeholder="Exemple : 4"
                        required
                    >
                </div>

                <button type="submit" name="ajouter" class="ajouter">
                    + Ajouter la table
                </button>

            </div>
        </form>
    </div>

    <div class="liste">
        <h2>Liste des tables</h2>

        <table>
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Capacité</th>
                    <th>Statut</th>
                    <th>Modifier le statut</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php if (count($tables) > 0): ?>

                <?php foreach ($tables as $table): ?>
                    <?php
                    $classe = "libre";

                    if ($table["statut"] === "Occupée") {
                        $classe = "occupee";
                    } elseif ($table["statut"] === "Réservée") {
                        $classe = "reservee";
                    }
                    ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($table["numero_table"]) ?>
                        </td>

                        <td>
                            <?= (int) $table["capacite"] ?> places
                        </td>

                        <td>
                            <span class="badge <?= $classe ?>">
                                <?= htmlspecialchars($table["statut"]) ?>
                            </span>
                        </td>

                        <td>
                            <form method="POST" class="statut-form">
                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $table["id"] ?>"
                                >

                                <select name="statut">
                                    <option value="Libre"
                                        <?= $table["statut"] === "Libre" ? "selected" : "" ?>>
                                        Libre
                                    </option>

                                    <option value="Occupée"
                                        <?= $table["statut"] === "Occupée" ? "selected" : "" ?>>
                                        Occupée
                                    </option>

                                    <option value="Réservée"
                                        <?= $table["statut"] === "Réservée" ? "selected" : "" ?>>
                                        Réservée
                                    </option>
                                </select>

                                <button type="submit" name="modifier_statut">
                                    OK
                                </button>
                            </form>
                        </td>

                        <td>
                            <form method="POST"
                                  onsubmit="return confirm('Voulez-vous vraiment supprimer cette table ?');">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $table["id"] ?>"
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
                    <td colspan="5" class="vide">
                        Aucune table enregistrée pour le moment.
                    </td>
                </tr>

            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
