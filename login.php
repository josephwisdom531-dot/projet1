
<?php
session_start();

require_once "connexion.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";

    if ($email === "" || $mot_de_passe === "") {

        $message = "Veuillez remplir tous les champs.";

    } else {

        try {

            // Rechercher l'utilisateur
            $requete = $connexion->prepare(
                "SELECT id, nom, prenom, email, mot_de_passe, role, actif
                 FROM utilisateur
                 WHERE email = ?
                 LIMIT 1"
            );

            $requete->execute([$email]);

            $utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

            // Vérifier les identifiants
            if (
                $utilisateur &&
                password_verify(
                    $mot_de_passe,
                    $utilisateur["mot_de_passe"]
                )
            ) {

                // Vérifier si le compte est actif
                if ((int)$utilisateur["actif"] !== 1) {

                    $message = "Votre compte est désactivé. Contactez le Gérant.";

                } else {

                    // Sécuriser la session
                    session_regenerate_id(true);

                    // Enregistrer les informations de l'utilisateur
                    $_SESSION["utilisateur_id"] = $utilisateur["id"];
                    $_SESSION["nom"] = $utilisateur["nom"];
                    $_SESSION["prenom"] = $utilisateur["prenom"];
                    $_SESSION["email"] = $utilisateur["email"];
                    $_SESSION["role"] = $utilisateur["role"];

                    // Redirection vers le tableau de bord
                    header("Location: index.php");
                    exit;
                }

            } else {

                $message = "Adresse e-mail ou mot de passe incorrect.";

            }

        } catch (PDOException $e) {

            $message = "Une erreur est survenue. Veuillez réessayer.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Connexion</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef4ff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: white;
            width: 90%;
            max-width: 420px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        button {
            width: 100%;
            padding: 13px;
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
            text-align: center;
            color: #dc2626;
            margin-bottom: 15px;
        }

        .lien {
            text-align: center;
            margin-top: 18px;
        }

        .lien a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Connexion</h2>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">

        <!-- Champs destinés à limiter le remplissage automatique -->
        <input type="text" name="fake_user"
               style="display:none"
               autocomplete="username">

        <input type="password" name="fake_password"
               style="display:none"
               autocomplete="new-password">

        <label for="email">Adresse e-mail</label>

        <input
            type="email"
            id="email"
            name="email"
            placeholder="Entrez votre adresse e-mail"
            autocomplete="off"
            readonly
            onfocus="this.removeAttribute('readonly');"
            required
        >

        <label for="mot_de_passe">Mot de passe</label>

        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            placeholder="Entrez votre mot de passe"
            autocomplete="new-password"
            readonly
            onfocus="this.removeAttribute('readonly');"
            required
        >

        <button type="submit">Se connecter</button>

    </form>

    <div class="lien">
        Pas encore de compte ?
        <a href="inscription.php">Créer un compte</a>
    </div>

</div>

</body>
</html>
