
<?php
require_once "permissions.php";

$message = "";
$typeMessage = "";

function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}

// Protection CSRF
if (empty($_SESSION["csrf_reservation"])) {
    $_SESSION["csrf_reservation"] = bin2hex(random_bytes(32));
}

// Enregistrer une réservation
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_reservation"], $token)) {

        $message = "Session expirée. Veuillez réessayer.";
        $typeMessage = "erreur";

    } else {

        $action = $_POST["action"] ?? "";

        try {

            if ($action === "ajouter") {

                $clientId = filter_input(INPUT_POST, "client_id", FILTER_VALIDATE_INT);
                $tableId = filter_input(INPUT_POST, "table_id", FILTER_VALIDATE_INT);
                $date = $_POST["date_reservation"] ?? "";
                $heure = $_POST["heure_reservation"] ?? "";
                $personnes = filter_input(INPUT_POST, "nombre_personnes", FILTER_VALIDATE_INT);
                $notes = trim($_POST["notes"] ?? "");

                $dateValide = DateTime::createFromFormat("Y-m-d", $date);
                $heureValide = DateTime::createFromFormat("H:i", $heure);

                if (
                    !$clientId || !$tableId || !$personnes ||
                    $personnes < 1 ||
                    !$dateValide || $dateValide->format("Y-m-d") !== $date ||
                    !$heureValide || $heureValide->format("H:i") !== $heure
                ) {
                    throw new Exception("Veuillez remplir correctement tous les champs.");
                }

                if ($date < date("Y-m-d") ||
                    ($date === date("Y-m-d") && $heure < date("H:i"))) {
                    throw new Exception("La date et l'heure doivent être dans le futur.");
                }

                $connexion->beginTransaction();

                // Vérifier le client
                $reqClient = $connexion->prepare(
                    "SELECT id FROM clients WHERE id = ?"
                );
                $reqClient->execute([$clientId]);

                if (!$reqClient->fetch()) {
                    throw new Exception("Client introuvable.");
                }

                // Verrouiller et vérifier la table
                $reqTable = $connexion->prepare(
                    "SELECT id, capacite, statut
                     FROM tables_restaurant
                     WHERE id = ?
                     FOR UPDATE"
                );
                $reqTable->execute([$tableId]);
                $table = $reqTable->fetch(PDO::FETCH_ASSOC);

                if (!$table) {
                    throw new Exception("Table introuvable.");
                }

                if ((int)$personnes > (int)$table["capacite"]) {
                    throw new Exception("La capacité de cette table est insuffisante.");
                }

                if ($table["statut"] === "Occupée") {
                    throw new Exception("Cette table est actuellement occupée.");
                }

                // Vérifier le chevauchement du créneau.
                // Une réservation bloque la table pendant 2 heures.
                $debut = strtotime($date . " " . $heure);
                $fin = $debut + (2 * 60 * 60);

                $reqConflit = $connexion->prepare(
                    "SELECT id
                     FROM reservations
                     WHERE table_id = ?
                       AND statut IN ('En attente', 'Confirmée')
                       AND TIMESTAMP(date_reservation, heure_reservation) < ?
                       AND DATE_ADD(
                            TIMESTAMP(date_reservation, heure_reservation),
                            INTERVAL 2 HOUR
                       ) > ?
                     FOR UPDATE"
                );

                $reqConflit->execute([
                    $tableId,
                    date("Y-m-d H:i:s", $fin),
                    date("Y-m-d H:i:s", $debut)
                ]);

                if ($reqConflit->fetch()) {
                    throw new Exception("Cette table est déjà réservée sur ce créneau.");
                }

                $insertion = $connexion->prepare(
                    "INSERT INTO reservations (
                        client_id,
                        table_id,
                        utilisateur_id,
                        date_reservation,
                        heure_reservation,
                        nombre_personnes,
                        statut,
                        notes
                    ) VALUES (?, ?, ?, ?, ?, ?, 'En attente', ?)"
                );

                $insertion->execute([
                    $clientId,
                    $tableId,
                    $_SESSION["utilisateur_id"],
                    $date,
                    $heure,
                    $personnes,
                    $notes !== "" ? $notes : null
                ]);

                $connexion->commit();

                $_SESSION["csrf_reservation"] = bin2hex(random_bytes(32));

                $message = "Réservation enregistrée avec succès.";
                $typeMessage = "succes";

            } elseif ($action === "modifier_statut") {

                $reservationId = filter_input(
                    INPUT_POST,
                    "reservation_id",
                    FILTER_VALIDATE_INT
                );

                $nouveauStatut = $_POST["statut"] ?? "";

                $statuts = [
                    "Confirmée",
                    "Terminée",
                    "Annulée"
                ];

                if (!$reservationId || !in_array($nouveauStatut, $statuts, true)) {
                    throw new Exception("Informations de modification invalides.");
                }

                $miseAJour = $connexion->prepare(
                    "UPDATE reservations
                     SET statut = ?
                     WHERE id = ?
                       AND statut IN ('En attente', 'Confirmée')"
                );

                $miseAJour->execute([$nouveauStatut, $reservationId]);

                if ($miseAJour->rowCount() === 0) {
                    throw new Exception("Réservation introuvable ou déjà clôturée.");
                }

                $message = "Statut de la réservation mis à jour.";
                $typeMessage = "succes";

            } else {
                throw new Exception("Action inconnue.");
            }

        } catch (Throwable $erreur) {

            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            $message = $erreur instanceof PDOException
                ? "Une erreur de base de données est survenue."
                : $erreur->getMessage();

            $typeMessage = "erreur";
        }
    }
}

// Récupérer les clients
$clients = $connexion->query(
    "SELECT id, nom, telephone
     FROM clients
     ORDER BY nom ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les tables
$tables = $connexion->query(
    "SELECT id, numero_table, capacite, statut
     FROM tables_restaurant
     ORDER BY numero_table ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les réservations
$reservations = $connexion->query(
    "SELECT
        r.*,
        c.nom AS nom_client,
        c.telephone,
        t.numero_table,
        u.nom AS nom_employe,
        u.prenom AS prenom_employe
     FROM reservations r
     INNER JOIN clients c ON r.client_id = c.id
     INNER JOIN tables_restaurant t ON r.table_id = t.id
     INNER JOIN utilisateur u ON r.utilisateur_id = u.id
     ORDER BY r.date_reservation DESC, r.heure_reservation DESC"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des réservations</title>

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
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h2 {
            font-size: 22px;
        }

        .retour {
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 7px;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .bloc {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        h2 {
            margin-bottom: 20px;
        }

        .message {
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .succes {
            background: #dcfce7;
            color: #166534;
        }

        .erreur {
            background: #fee2e2;
            color: #991b1b;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
        }

        .statut {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .attente {
            background: #fef3c7;
            color: #92400e;
        }

        .confirmee {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .terminee {
            background: #dcfce7;
            color: #166534;
        }

        .annulee {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-statut {
            min-width: 150px;
        }

        .form-statut select {
            margin-bottom: 8px;
        }

        .vide {
            color: #64748b;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 25px;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px;
            }

            .bloc {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>📅 Gestion des réservations</h2>

    <a href="index.php" class="retour">
        ← Retour au tableau de bord
    </a>
</header>

<div class="container">

    <div class="bloc">

        <h2>Nouvelle réservation</h2>

        <?php if ($message !== ""): ?>
            <div class="message <?= e($typeMessage) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($clients) || empty($tables)): ?>

            <p class="vide">
                Vous devez d'abord enregistrer au moins un client et une table.
            </p>

        <?php else: ?>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION["csrf_reservation"]) ?>">

                <input type="hidden" name="action" value="ajouter">

                <label for="client_id">Client</label>

                <select name="client_id" id="client_id" required>
                    <option value="">-- Choisir un client --</option>

                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client["id"] ?>">
                            <?= e($client["nom"]) ?>
                            - <?= e($client["telephone"]) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label for="table_id">Table</label>

                <select name="table_id" id="table_id" required>
                    <option value="">-- Choisir une table --</option>

                    <?php foreach ($tables as $table): ?>
                        <option
                            value="<?= (int)$table["id"] ?>"
                            data-capacite="<?= (int)$table["capacite"] ?>"
                            <?= $table["statut"] === "Occupée" ? "disabled" : "" ?>
                        >
                            Table <?= e($table["numero_table"]) ?>
                            - <?= (int)$table["capacite"] ?> places
                            - <?= e($table["statut"]) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label for="date_reservation">Date</label>

                <input
                    type="date"
                    name="date_reservation"
                    id="date_reservation"
                    min="<?= date("Y-m-d") ?>"
                    required
                >

                <label for="heure_reservation">Heure</label>

                <input
                    type="time"
                    name="heure_reservation"
                    id="heure_reservation"
                    required
                >

                <label for="nombre_personnes">Nombre de personnes</label>

                <input
                    type="number"
                    name="nombre_personnes"
                    id="nombre_personnes"
                    min="1"
                    required
                    placeholder="Exemple : 4"
                >

                <label for="notes">Notes complémentaires</label>

                <textarea
                    name="notes"
                    id="notes"
                    placeholder="Exemple : anniversaire, chaise bébé..."
                ></textarea>

                <button type="submit">
                    Enregistrer la réservation
                </button>

            </form>

        <?php endif; ?>

    </div>

    <div class="bloc">

        <h2>Liste des réservations</h2>

        <?php if (!empty($reservations)): ?>

            <div class="table-responsive">

                <table>

                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Table</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Personnes</th>
                            <th>Statut</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($reservations as $reservation): ?>

                            <?php
                            $classeStatut = [
                                "En attente" => "attente",
                                "Confirmée" => "confirmee",
                                "Terminée" => "terminee",
                                "Annulée" => "annulee"
                            ][$reservation["statut"]] ?? "attente";
                            ?>

                            <tr>

                                <td><?= (int)$reservation["id"] ?></td>

                                <td><?= e($reservation["nom_client"]) ?></td>

                                <td><?= e($reservation["telephone"]) ?></td>

                                <td><?= e($reservation["numero_table"]) ?></td>

                                <td><?= e($reservation["date_reservation"]) ?></td>

                                <td><?= e(substr($reservation["heure_reservation"], 0, 5)) ?></td>

                                <td><?= (int)$reservation["nombre_personnes"] ?></td>

                                <td>
                                    <span class="statut <?= e($classeStatut) ?>">
                                        <?= e($reservation["statut"]) ?>
                                    </span>
                                </td>

                                <td><?= e($reservation["notes"]) ?></td>

                                <td>

                                    <?php if (in_array($reservation["statut"], ["En attente", "Confirmée"], true)): ?>

                                        <form method="POST" class="form-statut"
                                            onsubmit="return confirm('Confirmer cette modification ?');">

                                            <input type="hidden" name="csrf_token"
                                                value="<?= e($_SESSION["csrf_reservation"]) ?>">

                                            <input type="hidden" name="action"
                                                value="modifier_statut">

                                            <input type="hidden" name="reservation_id"
                                                value="<?= (int)$reservation["id"] ?>">

                                            <select name="statut" required>

                                                <?php if ($reservation["statut"] === "En attente"): ?>
                                                    <option value="Confirmée">Confirmer</option>
                                                <?php endif; ?>

                                                <option value="Terminée">Terminer</option>
                                                <option value="Annulée">Annuler</option>

                                            </select>

                                            <button type="submit">
                                                Valider
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="vide">Aucune action</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p class="vide">
                Aucune réservation enregistrée.
            </p>

        <?php endif; ?>

    </div>

</div>

<footer>
    Gestion Restaurant © <?= date("Y") ?> - Tous droits réservés
</footer>

</body>
</html>
