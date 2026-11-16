
<?php
require_once "permissions.php";

$message = "";
$typeMessage = "";

// Traitement de l'encaissement
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $commandeId = filter_input(INPUT_POST, "commande_id", FILTER_VALIDATE_INT);
    $modePaiement = $_POST["mode_paiement"] ?? "";
    $montantRecuSaisi = $_POST["montant_recu"] ?? "";
    $reference = trim($_POST["reference_paiement"] ?? "");

    $modesAutorises = [
        "Espèces",
        "MTN Mobile Money",
        "Moov Money",
        "Carte bancaire"
    ];

    if (
        !$commandeId ||
        !in_array($modePaiement, $modesAutorises, true) ||
        !is_numeric($montantRecuSaisi) ||
        (float)$montantRecuSaisi <= 0
    ) {
        $message = "Veuillez vérifier les informations saisies.";
        $typeMessage = "erreur";

    } else {

        try {

            $connexion->beginTransaction();

            // Verrouiller la commande pendant le paiement
            $requete = $connexion->prepare(
                "SELECT id, total, statut
                 FROM commandes
                 WHERE id = ?
                 FOR UPDATE"
            );

            $requete->execute([$commandeId]);
            $commande = $requete->fetch(PDO::FETCH_ASSOC);

            if (!$commande) {
                throw new Exception("Cette commande n'existe pas.");
            }

            if ($commande["statut"] === "Annulée") {
                throw new Exception("Impossible de payer une commande annulée.");
            }

            // Vérifier si la commande est déjà payée
            $verification = $connexion->prepare(
                "SELECT id FROM paiements WHERE commande_id = ?"
            );

            $verification->execute([$commandeId]);

            if ($verification->fetch()) {
                throw new Exception("Cette commande a déjà été payée.");
            }

            $montantTotal = round((float)$commande["total"], 2);
            $montantRecu = round((float)$montantRecuSaisi, 2);

            if ($montantTotal <= 0) {
                throw new Exception("Le montant de cette commande est invalide.");
            }

            if ($montantRecu < $montantTotal) {
                throw new Exception("Le montant reçu est insuffisant.");
            }

            // Pour les paiements électroniques, aucun surplus
            if (
                $modePaiement !== "Espèces" &&
                $montantRecu !== $montantTotal
            ) {
                throw new Exception(
                    "Pour ce mode de paiement, le montant reçu doit être égal au total."
                );
            }

            $monnaie = round($montantRecu - $montantTotal, 2);

            // Enregistrer le paiement
            $insertion = $connexion->prepare(
                "INSERT INTO paiements
                (
                    commande_id,
                    montant,
                    mode_paiement,
                    reference_paiement,
                    montant_recu,
                    monnaie_rendue,
                    statut
                )
                VALUES (?, ?, ?, ?, ?, ?, 'Payé')"
            );

            $insertion->execute([
                $commandeId,
                $montantTotal,
                $modePaiement,
                $reference !== "" ? $reference : null,
                $montantRecu,
                $monnaie
            ]);

            $connexion->commit();

            header("Location: paiements.php?paiement=success");
            exit;

        } catch (Throwable $e) {

            if ($connexion->inTransaction()) {
                $connexion->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === "23000") {
                $message = "Cette commande possède déjà un paiement.";
            } elseif ($e instanceof PDOException) {
                $message = "Une erreur est survenue pendant l'enregistrement.";
            } else {
                $message = $e->getMessage();
            }

            $typeMessage = "erreur";
        }
    }
}

// Message après un paiement réussi
if (isset($_GET["paiement"]) && $_GET["paiement"] === "success") {
    $message = "Paiement enregistré avec succès !";
    $typeMessage = "succes";
}

// Récupérer les commandes qui ne sont pas encore payées
$requeteCommandes = $connexion->query(
    "SELECT c.id, c.total, c.date_commande, c.type_commande
     FROM commandes c
     LEFT JOIN paiements p ON p.commande_id = c.id
     WHERE p.id IS NULL
       AND c.statut <> 'Annulée'
     ORDER BY c.id DESC"
);

$commandes = $requeteCommandes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'historique des paiements
$requeteHistorique = $connexion->query(
    "SELECT
        p.id,
        p.commande_id,
        p.montant,
        p.mode_paiement,
        p.reference_paiement,
        p.montant_recu,
        p.monnaie_rendue,
        p.date_paiement,
        p.statut
     FROM paiements p
     ORDER BY p.date_paiement DESC
     LIMIT 30"
);

$paiements = $requeteHistorique->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des paiements</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #eef4ff;
            color: #1e293b;
            margin: 0;
            padding: 25px 15px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h1, h2 {
            color: #1e293b;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
        }

        .carte {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 15px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .erreur {
            background: #fee2e2;
            color: #991b1b;
        }

        .succes {
            background: #dcfce7;
            color: #166534;
        }

        .montant {
            background: #eff6ff;
            padding: 15px;
            border-radius: 7px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #1e293b;
            color: white;
        }

        .vide {
            color: #64748b;
            text-align: center;
            padding: 20px;
        }

        .retour {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .retour:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .carte {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="index.php" class="retour">
        ← Retour au tableau de bord
    </a>

    <h1>Gestion des paiements</h1>

    <?php if ($message !== ""): ?>
        <div class="message <?= $typeMessage ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <div class="carte">

        <h2>Enregistrer un paiement</h2>

        <?php if (count($commandes) > 0): ?>

            <form method="POST">

                <label for="commande_id">Sélectionner une commande</label>

                <select name="commande_id" id="commande_id" required>
                    <option value="">Choisir une commande</option>

                    <?php foreach ($commandes as $commande): ?>

                        <option
                            value="<?= (int)$commande["id"] ?>"
                            data-total="<?= htmlspecialchars($commande["total"], ENT_QUOTES, "UTF-8") ?>"
                        >
                            Commande #<?= (int)$commande["id"] ?>
                            - <?= number_format((float)$commande["total"], 0, ",", " ") ?> FCFA
                        </option>

                    <?php endforeach; ?>

                </select>

                <div class="montant">
                    Montant à payer :
                    <span id="montantTotal">0 FCFA</span>
                </div>

                <label for="mode_paiement">Mode de paiement</label>

                <select name="mode_paiement" id="mode_paiement" required>
                    <option value="">Choisir un mode de paiement</option>
                    <option value="Espèces">Espèces</option>
                    <option value="MTN Mobile Money">MTN Mobile Money</option>
                    <option value="Moov Money">Moov Money</option>
                    <option value="Carte bancaire">Carte bancaire</option>
                </select>

                <label for="montant_recu">Montant reçu (FCFA)</label>

                <input
                    type="number"
                    name="montant_recu"
                    id="montant_recu"
                    min="0.01"
                    step="0.01"
                    required
                >

                <label for="reference_paiement">
                    Référence du paiement (facultatif)
                </label>

                <input
                    type="text"
                    name="reference_paiement"
                    id="reference_paiement"
                    maxlength="100"
                    placeholder="Référence Mobile Money ou bancaire"
                >

                <button type="submit">
                    Enregistrer le paiement
                </button>

            </form>

        <?php else: ?>

            <p class="vide">
                Aucune commande en attente de paiement.
            </p>

        <?php endif; ?>

    </div>

    <div class="carte">

        <h2>Historique des paiements</h2>

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>N° Paiement</th>
                        <th>Commande</th>
                        <th>Montant</th>
                        <th>Mode</th>
                        <th>Montant reçu</th>
                        <th>Monnaie rendue</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($paiements) > 0): ?>

                        <?php foreach ($paiements as $paiement): ?>

                            <tr>
                                <td>
                                    #<?= (int)$paiement["id"] ?>
                                </td>

                                <td>
                                    #<?= (int)$paiement["commande_id"] ?>
                                </td>

                                <td>
                                    <?= number_format((float)$paiement["montant"], 0, ",", " ") ?> FCFA
                                </td>

                                <td>
                                    <?= htmlspecialchars($paiement["mode_paiement"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= number_format((float)$paiement["montant_recu"], 0, ",", " ") ?> FCFA
                                </td>

                                <td>
                                    <?= number_format((float)$paiement["monnaie_rendue"], 0, ",", " ") ?> FCFA
                                </td>

                                <td>
                                    <?= htmlspecialchars($paiement["date_paiement"], ENT_QUOTES, "UTF-8") ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($paiement["statut"], ENT_QUOTES, "UTF-8") ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="vide">
                                Aucun paiement enregistré pour le moment.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
    const commandeSelect = document.getElementById("commande_id");
    const montantTotal = document.getElementById("montantTotal");
    const montantRecu = document.getElementById("montant_recu");

    commandeSelect?.addEventListener("change", function () {

        const option = this.options[this.selectedIndex];
        const total = parseFloat(option.dataset.total || 0);

        montantTotal.textContent =
            total.toLocaleString("fr-FR") + " FCFA";

        montantRecu.value = total > 0 ? total : "";
        montantRecu.min = total > 0 ? total : 0.01;
    });
</script>

</body>
</html>
