
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

// Récupérer les clients
$clients = $connexion->query(
    "SELECT id, nom, telephone FROM clients ORDER BY nom"
)->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les tables libres
$tables = $connexion->query(
    "SELECT id, numero_table, capacite
     FROM tables_restaurant
     WHERE statut = 'Libre'
     ORDER BY numero_table"
)->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les plats disponibles
$plats = $connexion->query(
    "SELECT id, nom_plat, prix
     FROM plats
     WHERE disponible = 1
     ORDER BY nom_plat"
)->fetchAll(PDO::FETCH_ASSOC);

// Enregistrer une commande
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $type = $_POST["type_commande"] ?? "";
    $clientId = !empty($_POST["client_id"])
        ? filter_var($_POST["client_id"], FILTER_VALIDATE_INT)
        : null;

    $tableId = !empty($_POST["table_id"])
        ? filter_var($_POST["table_id"], FILTER_VALIDATE_INT)
        : null;

    $idsPlats = $_POST["plat_id"] ?? [];
    $quantites = $_POST["quantite"] ?? [];

    $typesAutorises = ["Sur place", "À emporter", "Livraison"];

    if (!in_array($type, $typesAutorises, true)) {
        $erreur = "Type de commande invalide.";
    } elseif ($type === "Sur place" && !$tableId) {
        $erreur = "Veuillez sélectionner une table pour cette commande.";
    } elseif ($type !== "Sur place" && $tableId) {
        $erreur = "Une table ne doit être sélectionnée que pour une commande sur place.";
    } elseif ($clientId === false || $tableId === false) {
        $erreur = "Client ou table invalide.";
    } elseif (!is_array($idsPlats) || !is_array($quantites) || count($idsPlats) === 0) {
        $erreur = "Ajoutez au moins un plat.";
    } else {

        try {
            $connexion->beginTransaction();

            // Vérifier le client
            if ($clientId !== null) {
                $verif = $connexion->prepare(
                    "SELECT id FROM clients WHERE id = ?"
                );
                $verif->execute([$clientId]);

                if (!$verif->fetch()) {
                    throw new Exception("Client introuvable.");
                }
            }

            // Vérifier et réserver la table
            if ($tableId !== null) {
                $verif = $connexion->prepare(
                    "SELECT id FROM tables_restaurant
                     WHERE id = ? AND statut = 'Libre'
                     FOR UPDATE"
                );
                $verif->execute([$tableId]);

                if (!$verif->fetch()) {
                    throw new Exception("Cette table n'est plus libre.");
                }
            }

            // Vérifier les plats et calculer le total
            $articles = [];
            $total = 0;

            foreach ($idsPlats as $index => $platId) {

                $platId = filter_var($platId, FILTER_VALIDATE_INT);
                $quantite = filter_var(
                    $quantites[$index] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$platId || !$quantite || $quantite < 1) {
                    throw new Exception("Vérifiez les plats et les quantités.");
                }

                $verif = $connexion->prepare(
                    "SELECT id, prix FROM plats
                     WHERE id = ? AND disponible = 1"
                );
                $verif->execute([$platId]);

                $plat = $verif->fetch(PDO::FETCH_ASSOC);

                if (!$plat) {
                    throw new Exception("Un plat sélectionné n'est plus disponible.");
                }

                $prix = (float)$plat["prix"];
                $sousTotal = $prix * $quantite;

                $articles[] = [
                    "plat_id" => $platId,
                    "quantite" => $quantite,
                    "prix" => $prix,
                    "sous_total" => $sousTotal
                ];

                $total += $sousTotal;
            }

            // Créer la commande
            $requete = $connexion->prepare(
                "INSERT INTO commandes
                (client_id, table_id, utilisateur_id, type_commande, total)
                VALUES (?, ?, ?, ?, ?)"
            );

            $requete->execute([
                $clientId,
                $tableId,
                $_SESSION["utilisateur_id"],
                $type,
                $total
            ]);

            $commandeId = $connexion->lastInsertId();

            // Ajouter les détails de la commande
            $ajouterDetail = $connexion->prepare(
                "INSERT INTO details_commande
                (commande_id, plat_id, quantite, prix_unitaire, sous_total)
                VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($articles as $article) {
                $ajouterDetail->execute([
                    $commandeId,
                    $article["plat_id"],
                    $article["quantite"],
                    $article["prix"],
                    $article["sous_total"]
                ]);
            }

            // Marquer la table comme occupée
            if ($tableId !== null) {
                $updateTable = $connexion->prepare(
                    "UPDATE tables_restaurant
                     SET statut = 'Occupée'
                     WHERE id = ?"
                );
                $updateTable->execute([$tableId]);
            }

            $connexion->commit();

            header("Location: commandes.php?success=1");
            exit;

        } catch (Throwable $e) {

            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            $erreur = $e instanceof PDOException
                ? "Une erreur est survenue lors de l'enregistrement."
                : $e->getMessage();
        }
    }
}

// Message de confirmation
if (isset($_GET["success"])) {
    $succes = "Commande enregistrée avec succès !";
}

// Historique des commandes
$historique = $connexion->query(
    "SELECT c.id, c.type_commande, c.statut, c.total,
            c.date_commande, cl.nom AS nom_client,
            t.numero_table
     FROM commandes c
     LEFT JOIN clients cl ON c.client_id = cl.id
     LEFT JOIN tables_restaurant t ON c.table_id = t.id
     ORDER BY c.id DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des commandes</title>

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
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        h1, h2 {
            color: #1e293b;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: white;
        }

        .ligne {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
            align-items: end;
            margin-top: 15px;
        }

        .ligne label {
            margin-top: 0;
        }

        .supprimer {
            background: #dc2626;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 7px;
            cursor: pointer;
        }

        .ajouter {
            background: #475569;
            color: white;
            border: none;
            padding: 12px 18px;
            margin-top: 18px;
            border-radius: 7px;
            cursor: pointer;
        }

        .enregistrer {
            width: 100%;
            background: #2563eb;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 7px;
            margin-top: 25px;
            font-size: 16px;
            cursor: pointer;
        }

        .total {
            text-align: right;
            font-size: 23px;
            font-weight: bold;
            margin-top: 25px;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .erreur {
            background: #fee2e2;
            color: #991b1b;
        }

        .succes {
            background: #dcfce7;
            color: #166534;
        }

        .table-historique {
            width: 100%;
            border-collapse: collapse;
        }

        .table-historique th,
        .table-historique td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .table-historique th {
            background: #f8fafc;
        }

        @media (max-width: 650px) {
            body {
                padding: 12px;
            }

            .ligne {
                grid-template-columns: 1fr 1fr auto;
            }

            .historique {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Gestion des commandes</h1>

    <div class="card">

        <h2>Nouvelle commande</h2>

        <?php if ($erreur): ?>
            <div class="message erreur"><?= h($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
            <div class="message succes"><?= h($succes) ?></div>
        <?php endif; ?>

        <form method="POST" id="formCommande">

            <label>Type de commande</label>
            <select name="type_commande" id="typeCommande" required>
                <option value="Sur place">Sur place</option>
                <option value="À emporter">À emporter</option>
                <option value="Livraison">Livraison</option>
            </select>

            <label>Client (facultatif)</label>
            <select name="client_id">
                <option value="">Client non renseigné</option>

                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int)$client["id"] ?>">
                        <?= h($client["nom"]) ?>
                        <?= !empty($client["telephone"])
                            ? " - " . h($client["telephone"])
                            : "" ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="choixTable">
                <label>Choisir une table</label>

                <select name="table_id" id="tableId">
                    <option value="">Sélectionner une table</option>

                    <?php foreach ($tables as $table): ?>
                        <option value="<?= (int)$table["id"] ?>">
                            Table <?= h($table["numero_table"]) ?>
                            - <?= (int)$table["capacite"] ?> places
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (count($tables) === 0): ?>
                    <p>Aucune table libre n'est enregistrée actuellement.</p>
                <?php endif; ?>
            </div>

            <h3>Plats commandés</h3>

            <div id="listePlats"></div>

            <button type="button" class="ajouter" id="ajouterPlat">
                + Ajouter un plat
            </button>

            <div class="total">
                Total : <span id="total">0</span> FCFA
            </div>

            <button type="submit" class="enregistrer">
                Enregistrer la commande
            </button>

        </form>
    </div>
<div style="margin-bottom: 20px;">
    <a href="index.php" style="
        display: inline-block;
        background: #334155;
        color: white;
        padding: 11px 18px;
        border-radius: 7px;
        text-decoration: none;
        font-weight: bold;
    ">
        ← Retour au tableau de bord
    </a>
</div>
    <div class="card">
        <h2>Dernières commandes</h2>

        <div class="historique">
            <table class="table-historique">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Table</th>
                        <th>Total</th>
                        <th>Statut</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($historique as $commande): ?>
                        <tr>
                            <td>#<?= (int)$commande["id"] ?></td>
                            <td><?= h($commande["nom_client"] ?? "Non renseigné") ?></td>
                            <td><?= h($commande["type_commande"]) ?></td>
                            <td><?= h($commande["numero_table"] ?? "-") ?></td>
                            <td>
                                <?= number_format((float)$commande["total"], 0, ",", " ") ?>
                                FCFA
                            </td>
                            <td><?= h($commande["statut"]) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($historique) === 0): ?>
                        <tr>
                            <td colspan="6">Aucune commande enregistrée.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
const listePlats = document.getElementById("listePlats");
const totalElement = document.getElementById("total");

const optionsPlats = `
    <option value="">Choisir un plat</option>
    <?php foreach ($plats as $plat): ?>
        <option value="<?= (int)$plat["id"] ?>"
                data-prix="<?= h($plat["prix"]) ?>">
            <?= h($plat["nom_plat"]) ?>
            - <?= number_format((float)$plat["prix"], 0, ",", " ") ?> FCFA
        </option>
    <?php endforeach; ?>
`;

function ajouterUneLigne() {
    const ligne = document.createElement("div");
    ligne.className = "ligne";

    ligne.innerHTML = `
        <label>
            Plat
            <select name="plat_id[]" class="plat" required>
                ${optionsPlats}
            </select>
        </label>

        <label>
            Quantité
            <input type="number" name="quantite[]"
                   class="quantite" min="1" value="1" required>
        </label>

        <button type="button" class="supprimer">
            Retirer
        </button>
    `;

    ligne.querySelector(".plat").addEventListener("change", calculerTotal);
    ligne.querySelector(".quantite").addEventListener("input", calculerTotal);

    ligne.querySelector(".supprimer").addEventListener("click", function() {
        ligne.remove();
        calculerTotal();
    });

    listePlats.appendChild(ligne);
    calculerTotal();
}

function calculerTotal() {
    let total = 0;

    document.querySelectorAll(".ligne").forEach(function(ligne) {
        const select = ligne.querySelector(".plat");
        const quantite = parseInt(
            ligne.querySelector(".quantite").value
        ) || 0;

        const option = select.options[select.selectedIndex];
        const prix = parseFloat(option.dataset.prix) || 0;

        total += prix * quantite;
    });

    totalElement.textContent = total.toLocaleString("fr-FR");
}

document.getElementById("ajouterPlat").addEventListener(
    "click",
    ajouterUneLigne
);

const typeCommande = document.getElementById("typeCommande");
const choixTable = document.getElementById("choixTable");
const tableId = document.getElementById("tableId");

function gererTable() {
    const surPlace = typeCommande.value === "Sur place";

    choixTable.style.display = surPlace ? "block" : "none";
    tableId.required = surPlace;

    if (!surPlace) {
        tableId.value = "";
    }
}

typeCommande.addEventListener("change", gererTable);

ajouterUneLigne();
gererTable();
</script>

</body>
</html>
