
<?php
require_once "connexion.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";

    if ($nom === "" || $prenom === "" || $email === "" || $mot_de_passe === "") {

        $message = "Veuillez remplir tous les champs.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Veuillez saisir une adresse e-mail valide.";

    } elseif (strlen($mot_de_passe) < 8) {

        $message = "Le mot de passe doit contenir au moins 8 caractères.";

    } else {

        try {

            // Vérifier si l'adresse e-mail existe déjà
            $verification = $connexion->prepare(
                "SELECT id FROM utilisateur WHERE email = ?"
            );

            $verification->execute([$email]);

            if ($verification->fetch()) {

                $message = "Cette adresse e-mail est déjà utilisée.";

            } else {

                // Sécuriser le mot de passe
                $mot_de_passe_hash = password_hash(
                    $mot_de_passe,
                    PASSWORD_DEFAULT
                );

                // Enregistrer le nouvel utilisateur
                $requete = $connexion->prepare(
                    "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe)
                     VALUES (?, ?, ?, ?)"
                );

                $requete->execute([
                    $nom,
                    $prenom,
                    $email,
                    $mot_de_passe_hash
                ]);

                // Rediriger vers la page de connexion
                header("Location: login.php");
                exit;
            }

        } catch (PDOException $e) {

            $message = "Erreur lors de l'inscription. Vérifiez votre base de données.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Créer un compte</title>

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

    <h2>Créer un compte</h2>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="inscription.php" autocomplete="on">

        <label for="nom">Nom</label>
        <input
            type="text"
            id="nom"
            name="nom"
            placeholder="Votre nom"
            autocomplete="family-name"
            required
        >

        <label for="prenom">Prénom</label>
        <input
            type="text"
            id="prenom"
            name="prenom"
            placeholder="Votre prénom"
            autocomplete="given-name"
            required
        >

        <label for="email">Adresse e-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Votre adresse e-mail"
            autocomplete="email"
            required
        >

        <label for="mot_de_passe">Mot de passe</label>
        <input
            type="password"
            id="mot_de_passe"
            name="mot_de_passe"
            placeholder="Au moins 8 caractères"
            autocomplete="new-password"
            minlength="8"
            required
        >

        <button type="submit">S'inscrire</button>

    </form>

    <div class="lien">
        Vous avez déjà un compte ?
        <a href="login.php">Se connecter</a>
    </div>

</div>

</body>
</html>
