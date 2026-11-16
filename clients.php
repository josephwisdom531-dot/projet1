
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

function h($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}

$erreur = "";
$succes = "";

// Ajouter un client
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($nom === "") {
        $erreur = "Le nom du client est obligatoire.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } else {
        try {
            $requete = $connexion->prepare(
                "INSERT INTO clients (nom, telephone, email)
                 VALUES (?, ?, ?)"
            );

            $requete->execute([
                $nom,
                $telephone !== "" ? $telephone : null,
                $email !== "" ? $email : null
            ]);

            header("Location: clients.php?ajout=success");
            exit;

        } catch (PDOException $e) {
            $erreur = "Impossible d'enregistrer ce client.";
        }
    }
}

// Supprimer un client
if (isset($_GET["supprimer"])) {

    $id = filter_input(INPUT_GET, "supprimer", FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $requete = $connexion->prepare(
            "DELETE FROM clients WHERE id = ?"
        );
        $requete->execute([$id]);

        header("Location: clients.php?suppression=success");
        exit;
    }
}

// Messages
if (isset($_GET["ajout"])) {
    $succes = "Client ajouté avec succès.";
}

if (isset($_GET["suppression"])) {
    $succes = "Client supprimé avec succès.";
}

// Récupérer les clients
$clients = $connexion->query(
    "SELECT * FROM clients ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$totalClients = count($clients);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des clients</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 25px;
            color: #1e293b;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .entete {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .retour {
            text-decoration: none;
            color: #2563eb;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 13px 20px;
            border-radius: 7px;
            margin-top: 20px;
            cursor: pointer;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .erreur {
            background: #fee2e2;
            color: #991b1b;
        }

        .succes {
            background: #dcfce7;
            color: #166534;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #f8fafc;
        }

        .supprimer {
            color: #dc2626;
            text-decoration: none;
            font-weight: bold;
        }

        .compteur {
            color: #64748b;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="entete">
        <h1>👥 Gestion des clients</h1>
        <a href="index.php" class="retour">← Tableau de bord</a>
    </div>

    <?php if ($erreur): ?>
        <div class="message erreur"><?= h($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
        <div class="message succes"><?= h($succes) ?></div>
    <?php endif; ?>

    <div class="card">

        <h2>Ajouter un client</h2>

        <form method="POST">

            <label>Nom complet *</label>
            <input
                type="text"
                name="nom"
                placeholder="Nom du client"
                required
            >

            <label>Téléphone</label>
            <input
                type="tel"
                name="telephone"
                placeholder="Exemple : 01XXXXXXXX"
            >

            <label>Adresse email</label>
            <input
                type="email"
                name="email"
                placeholder="client@email.com"
            >

            <button type="submit">Enregistrer le client</button>

        </form>

    </div>

    <div class="card">

        <h2>Liste des clients</h2>

        <p class="compteur">
            Nombre total de clients : <?= $totalClients ?>
        </p>

        <div class="table-container">

            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($clients as $client): ?>

                    <tr>
                        <td><?= (int)$client["id"] ?></td>

                        <td><?= h($client["nom"]) ?></td>

                        <td><?= h($client["telephone"] ?? "-") ?></td>

                        <td><?= h($client["email"] ?? "-") ?></td>

                        <td>
                            <a
                                href="clients.php?supprimer=<?= (int)$client["id"] ?>"
                                class="supprimer"
                                onclick="return confirm('Voulez-vous vraiment supprimer ce client ?');"
                            >
                                Supprimer
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (count($clients) === 0): ?>
                    <tr>
                        <td colspan="5">Aucun client enregistré.</td>
                    </tr>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

</body>
</html>
