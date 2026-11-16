
<?php
// Vérifier les droits d'accès de l'utilisateur
require_once "permissions.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom_plat = trim($_POST["nom_plat"] ?? "");
    $categorie = $_POST["categorie"] ?? "";
    $prix = $_POST["prix"] ?? "";
    $disponible = isset($_POST["disponible"]) ? 1 : 0;

    $categories_autorisees = ["Entrée", "Plat", "Dessert", "Boisson"];

    if (
        $nom_plat === "" ||
        !in_array($categorie, $categories_autorisees, true) ||
        !is_numeric($prix) ||
        (float)$prix < 0
    ) {
        $message = "Veuillez vérifier les informations saisies.";

    } else {

        $photo = null;

        // Vérifier et enregistrer la photo
        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
                $message = "Erreur lors de l'envoi de la photo.";

            } else {

                $taille_max = 5 * 1024 * 1024;

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $type = $finfo->file($_FILES["photo"]["tmp_name"]);

                $types_autorises = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if ($_FILES["photo"]["size"] > $taille_max) {
                    $message = "La photo ne doit pas dépasser 5 Mo.";

                } elseif (!isset($types_autorises[$type])) {
                    $message = "Format accepté : JPG, PNG ou WEBP.";

                } else {

                    $dossier = __DIR__ . "/images/";

                    if (!is_dir($dossier)) {
                        mkdir($dossier, 0755, true);
                    }

                    $nom_fichier = bin2hex(random_bytes(16))
                        . "." . $types_autorises[$type];

                    if (
                        move_uploaded_file(
                            $_FILES["photo"]["tmp_name"],
                            $dossier . $nom_fichier
                        )
                    ) {
                        $photo = "images/" . $nom_fichier;

                    } else {
                        $message = "Impossible d'enregistrer la photo.";
                    }
                }
            }
        }

        // Enregistrer le plat dans la base de données
        if ($message === "") {

            try {

                $requete = $connexion->prepare(
                    "INSERT INTO plats
                    (nom_plat, categorie, prix, disponible, photo)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $requete->execute([
                    $nom_plat,
                    $categorie,
                    $prix,
                    $disponible,
                    $photo
                ]);

                header("Location: menu.php?ajout=success");
                exit;

            } catch (PDOException $e) {

                if (
                    $photo !== null &&
                    file_exists(__DIR__ . "/" . $photo)
                ) {
                    unlink(__DIR__ . "/" . $photo);
                }

                $message = "Erreur lors de l'enregistrement du plat.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un plat</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #eef4ff;
            margin: 0;
            padding: 30px 15px;
            color: #1e293b;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }

        .disponible {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .disponible input {
            width: auto;
            margin: 0;
        }

        .disponible label {
            margin: 0;
        }

        button {
            width: 100%;
            padding: 14px;
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
            color: #dc2626;
            text-align: center;
            margin-bottom: 15px;
        }

        .retour {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .retour:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Ajouter un nouveau plat</h2>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label for="nom_plat">Nom du plat</label>

        <input
            type="text"
            id="nom_plat"
            name="nom_plat"
            placeholder="Exemple : Poulet braisé"
            maxlength="200"
            required
        >

        <label for="categorie">Catégorie</label>

        <select id="categorie" name="categorie" required>
            <option value="">Choisir une catégorie</option>
            <option value="Entrée">Entrée</option>
            <option value="Plat">Plat</option>
            <option value="Dessert">Dessert</option>
            <option value="Boisson">Boisson</option>
        </select>

        <label for="prix">Prix (FCFA)</label>

        <input
            type="number"
            id="prix"
            name="prix"
            placeholder="Exemple : 2500"
            min="0"
            step="0.01"
            required
        >

        <label for="photo">Photo du plat</label>

        <input
            type="file"
            id="photo"
            name="photo"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <div class="disponible">
            <input
                type="checkbox"
                id="disponible"
                name="disponible"
                checked
            >

            <label for="disponible">Plat disponible</label>
        </div>

        <button type="submit">Enregistrer le plat</button>

    </form>

    <a href="index.php" class="retour">
        Retour au tableau de bord
    </a>

</div>

</body>
</html>
