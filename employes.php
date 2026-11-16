
<?php
session_start();
require_once "connexion.php";

// Vérifier la connexion
if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

// Vérifier les droits du Gérant
$verification = $connexion->prepare(
    "SELECT role, actif FROM utilisateur WHERE id = ?"
);
$verification->execute([$_SESSION["utilisateur_id"]]);
$gerant = $verification->fetch(PDO::FETCH_ASSOC);

if (!$gerant || $gerant["role"] !== "Gerant" || !$gerant["actif"]) {
    die("Accès refusé : cette page est réservée au Gérant.");
}

$message = "";
$erreur = "";

$roles = [
    "Gerant" => "Gérant",
    "Caissier" => "Caissier",
    "Serveur" => "Serveur",
    "Cuisinier" => "Cuisinier"
];

// AJOUTER UN EMPLOYÉ
if (isset($_POST["ajouter"])) {
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";
    $role = $_POST["role"] ?? "";

    if ($nom === "" || $prenom === "" || $email === "" || $mot_de_passe === "") {
        $erreur = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } elseif (strlen($mot_de_passe) < 8) {
        $erreur = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!array_key_exists($role, $roles)) {
        $erreur = "Rôle invalide.";
    } else {
        $verifierEmail = $connexion->prepare(
            "SELECT id FROM utilisateur WHERE email = ?"
        );
        $verifierEmail->execute([$email]);

        if ($verifierEmail->fetch()) {
            $erreur = "Cette adresse email existe déjà.";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $ajout = $connexion->prepare(
                "INSERT INTO utilisateur
                (nom, prenom, email, mot_de_passe, role, actif)
                VALUES (?, ?, ?, ?, ?, 1)"
            );

            $ajout->execute([
                $nom,
                $prenom,
                $email,
                $hash,
                $role
            ]);

            $message = "Employé ajouté avec succès.";
        }
    }
}

// MODIFIER UN EMPLOYÉ
if (isset($_POST["modifier"])) {
    $id = (int) ($_POST["id"] ?? 0);
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "";
    $nouveau_mdp = $_POST["mot_de_passe"] ?? "";

    if ($id === (int) $_SESSION["utilisateur_id"]) {
        $erreur = "Tu ne peux pas modifier ton propre rôle ici.";
    } elseif (
        $nom === "" ||
        $prenom === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !array_key_exists($role, $roles)
    ) {
        $erreur = "Veuillez vérifier les informations saisies.";
    } elseif ($nouveau_mdp !== "" && strlen($nouveau_mdp) < 8) {
        $erreur = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        $verifierEmail = $connexion->prepare(
            "SELECT id FROM utilisateur WHERE email = ? AND id != ?"
        );
        $verifierEmail->execute([$email, $id]);

        if ($verifierEmail->fetch()) {
            $erreur = "Cette adresse email est déjà utilisée.";
        } else {
            if ($nouveau_mdp !== "") {
                $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

                $modification = $connexion->prepare(
                    "UPDATE utilisateur
                     SET nom = ?, prenom = ?, email = ?,
                         role = ?, mot_de_passe = ?
                     WHERE id = ?"
                );

                $modification->execute([
                    $nom, $prenom, $email, $role, $hash, $id
                ]);
            } else {
                $modification = $connexion->prepare(
                    "UPDATE utilisateur
                     SET nom = ?, prenom = ?, email = ?, role = ?
                     WHERE id = ?"
                );

                $modification->execute([
                    $nom, $prenom, $email, $role, $id
                ]);
            }

            $message = "Employé modifié avec succès.";
        }
    }
}

// ACTIVER OU DÉSACTIVER UN EMPLOYÉ
if (isset($_POST["changer_statut"])) {
    $id = (int) ($_POST["id"] ?? 0);

    if ($id === (int) $_SESSION["utilisateur_id"]) {
        $erreur = "Tu ne peux pas désactiver ton propre compte.";
    } else {
        $changement = $connexion->prepare(
            "UPDATE utilisateur
             SET actif = IF(actif = 1, 0, 1)
             WHERE id = ?"
        );

        $changement->execute([$id]);
        $message = "Statut du compte mis à jour.";
    }
}

// RÉCUPÉRER LES EMPLOYÉS
$employes = $connexion->query(
    "SELECT id, nom, prenom, email, role, actif
     FROM utilisateur
     ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// RÉCUPÉRER L'EMPLOYÉ À MODIFIER
$employeModifier = null;

if (isset($_GET["modifier"])) {
    $idModifier = (int) $_GET["modifier"];

    if ($idModifier !== (int) $_SESSION["utilisateur_id"]) {
        $requete = $connexion->prepare(
            "SELECT id, nom, prenom, email, role
             FROM utilisateur
             WHERE id = ?"
        );

        $requete->execute([$idModifier]);
        $employeModifier = $requete->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des employés</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #eef4ff;
            color: #1e293b;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        header {
            background: #1e293b;
            color: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0;
        }

        .retour {
            background: white;
            color: #1e293b;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold;
        }

        .carte {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        h2 {
            margin-top: 0;
        }

        .formulaire {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        button {
            border: none;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-principal {
            background: #2563eb;
            color: white;
        }

        .btn-statut {
            background: #64748b;
            color: white;
        }

        .message {
            padding: 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .erreur {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 7px;
            margin-bottom: 15px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f1f5f9;
        }

        .actif {
            color: #15803d;
            font-weight: bold;
        }

        .inactif {
            color: #dc2626;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .lien-modifier {
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        @media(max-width: 650px) {
            body {
                padding: 12px;
            }

            .formulaire {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <header>
        <h1>👨‍💼 Gestion des employés</h1>

        <a href="index.php" class="retour">
            ← Tableau de bord
        </a>
    </header>

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

    <div class="carte">

        <?php if ($employeModifier): ?>

            <h2>Modifier un employé</h2>

            <form method="POST" autocomplete="off">

                <input type="hidden" name="id"
                       value="<?= (int)$employeModifier["id"] ?>">

                <div class="formulaire">

                    <input type="text" name="nom"
                           value="<?= htmlspecialchars($employeModifier["nom"]) ?>"
                           placeholder="Nom" required>

                    <input type="text" name="prenom"
                           value="<?= htmlspecialchars($employeModifier["prenom"]) ?>"
                           placeholder="Prénom" required>

                    <input type="email" name="email"
                           value="<?= htmlspecialchars($employeModifier["email"]) ?>"
                           placeholder="Email"
                           autocomplete="off"
                           readonly
                           onfocus="this.removeAttribute('readonly');"
                           required>

                    <select name="role" required>
                        <?php foreach ($roles as $valeur => $libelle): ?>
                            <option value="<?= $valeur ?>"
                                <?= $employeModifier["role"] === $valeur ? "selected" : "" ?>>
                                <?= $libelle ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="password" name="mot_de_passe"
                           placeholder="Nouveau mot de passe (facultatif)"
                           autocomplete="new-password"
                           readonly
                           onfocus="this.removeAttribute('readonly');">

                </div>

                <br>

                <button type="submit" name="modifier" class="btn-principal">
                    Enregistrer les modifications
                </button>

                <a href="employes.php">Annuler</a>

            </form>

        <?php else: ?>

            <h2>Ajouter un employé</h2>

            <form method="POST" autocomplete="off">

                <div class="formulaire">

                    <input type="text" name="nom"
                           placeholder="Nom" required>

                    <input type="text" name="prenom"
                           placeholder="Prénom" required>

                    <input type="email" name="email"
                           placeholder="Adresse email"
                           autocomplete="off"
                           readonly
                           onfocus="this.removeAttribute('readonly');"
                           required>

                    <input type="password" name="mot_de_passe"
                           placeholder="Mot de passe (8 caractères minimum)"
                           autocomplete="new-password"
                           readonly
                           onfocus="this.removeAttribute('readonly');"
                           minlength="8" required>

                    <select name="role" required>
                        <option value="">Choisir un rôle</option>
                        <?php foreach ($roles as $valeur => $libelle): ?>
                            <option value="<?= $valeur ?>">
                                <?= $libelle ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <br>

                <button type="submit" name="ajouter" class="btn-principal">
                    + Ajouter l'employé
                </button>

            </form>

        <?php endif; ?>

    </div>

    <div class="carte">

        <h2>Liste des employés</h2>

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom et prénom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($employes as $employe): ?>

                    <tr>

                        <td><?= (int)$employe["id"] ?></td>

                        <td>
                            <?= htmlspecialchars($employe["nom"] . " " . $employe["prenom"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($employe["email"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($roles[$employe["role"]] ?? $employe["role"]) ?>
                        </td>

                        <td>
                            <?php if ($employe["actif"]): ?>
                                <span class="actif">Actif</span>
                            <?php else: ?>
                                <span class="inactif">Désactivé</span>
                            <?php endif; ?>
                        </td>

                        <td>

                            <?php if ($employe["id"] != $_SESSION["utilisateur_id"]): ?>

                                <div class="actions">

                                    <a class="lien-modifier"
                                       href="employes.php?modifier=<?= (int)$employe["id"] ?>">
                                        Modifier
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Confirmer le changement de statut ?');">

                                        <input type="hidden" name="id"
                                               value="<?= (int)$employe["id"] ?>">

                                        <button type="submit"
                                                name="changer_statut"
                                                class="btn-statut">
                                            <?= $employe["actif"] ? "Désactiver" : "Activer" ?>
                                        </button>

                                    </form>

                                </div>

                            <?php else: ?>

                                <strong>Mon compte</strong>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
