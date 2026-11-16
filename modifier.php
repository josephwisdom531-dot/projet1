
<?php
session_start();

if (!isset($_SESSION["utilisateur_id"])) {
    header("Location: login.php");
    exit;
}

require_once "connexion.php";

// Vérifier l'identifiant du plat
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: menu.php");
    exit;
}

// Récupérer les informations du plat
$requete = $connexion->prepare(
    "SELECT * FROM plats WHERE id = ?"
);
$requete->execute([$id]);
$plat = $requete->fetch(PDO::FETCH_ASSOC);

if (!$plat) {
    header("Location: menu.php");
    exit;
}

$erreur = "";

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom_plat"] ?? "");
    $categorie = $_POST["categorie"] ?? "";
    $prix = $_POST["prix"] ?? "";
    $disponible = isset($_POST["disponible"]) ? 1 : 0;

    $categories = ["Entrée", "Plat", "Dessert", "Boisson"];

    if (
        $nom === "" ||
        !in_array($categorie, $categories, true) ||
        !is_numeric($prix) ||
        (float)$prix <= 0
    ) {
        $erreur = "Veuillez remplir correctement tous les champs.";
    } else {

        $anciennePhoto = $plat["photo"];
        $nouvellePhoto = $anciennePhoto;
        $photoRemplacee = false;

        // Vérifier si une nouvelle photo a été envoyée
        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
                $erreur = "Erreur lors de l'envoi de la photo.";
            } elseif ($_FILES["photo"]["size"] > 5 * 1024 * 1024) {
                $erreur = "La photo ne doit pas dépasser 5 Mo.";
            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES["photo"]["tmp_name"]);

                $extensions = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($extensions[$mime])) {
                    $erreur = "Format refusé. Utilisez JPG, PNG ou WEBP.";
                } else {

                    $dossier = __DIR__ . "/images/";

                    if (!is_dir($dossier)) {
                        mkdir($dossier, 0755, true);
                    }

                    $nomFichier = uniqid("plat_", true)
                        . "." . $extensions[$mime];

                    $chemin = $dossier . $nomFichier;

                    if (move_uploaded_file(
                        $_FILES["photo"]["tmp_name"],
                        $chemin
                    )) {
                        $nouvellePhoto = "images/" . $nomFichier;
                        $photoRemplacee = true;
                    } else {
                        $erreur = "Impossible d'enregistrer la nouvelle photo.";
                    }
                }
            }
        }

        // Enregistrer les modifications
        if ($erreur === "") {

            try {
                $requete = $connexion->prepare(
                    "UPDATE plats
                     SET nom_plat = ?,
                         categorie = ?,
                         prix = ?,
                         disponible = ?,
                         photo = ?
                     WHERE id = ?"
                );

                $requete->execute([
                    $nom,
                    $categorie,
                    $prix,
                    $disponible,
                    $nouvellePhoto,
                    $id
                ]);

                // Supprimer l'ancienne photo si elle a été remplacée
                if ($photoRemplacee && !empty($anciennePhoto)) {
                    $ancienChemin = __DIR__ . "/" . $anciennePhoto;

                    if (
                        strpos($anciennePhoto, "images/") === 0 &&
                        is_file($ancienChemin)
                    ) {
                        unlink($ancienChemin);
                    }
                }

                header("Location: menu.php?modification=success");
                exit;

            } catch (PDOException $e) {

                // Supprimer la nouvelle photo en cas d'échec SQL
                if ($photoRemplacee) {
                    $nouveauChemin = __DIR__ . "/" . $nouvellePhoto;

                    if (is_file($nouveauChemin)) {
                        unlink($nouveauChemin);
                    }
                }

                $erreur = "Erreur lors de la modification du plat.";
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
    <title>Modifier un plat</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h1 {
            text-align: center;
            color: #1e293b;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-sizing: border-box;
        }

        input[type="checkbox"] {
            width: auto;
        }

        .photo {
            width: 180px;
            height: 140px;
            object-fit: cover;
            display: block;
            margin-top: 12px;
            border-radius: 8px;
        }

        .erreur {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 7px;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 25px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .retour {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Modifier le plat</h1>

    <?php if ($erreur !== ""): ?>
        <p class="erreur">
            <?= htmlspecialchars($erreur) ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Nom du plat</label>
        <input
            type="text"
            name="nom_plat"
            value="<?= htmlspecialchars($_POST["nom_plat"] ?? $plat["nom_plat"]) ?>"
            required
        >

        <label>Catégorie</label>
        <select name="categorie" required>
            <?php
            $categorieActuelle = $_POST["categorie"] ?? $plat["categorie"];
            $categories = ["Entrée", "Plat", "Dessert", "Boisson"];

            foreach ($categories as $cat):
            ?>
                <option
                    value="<?= htmlspecialchars($cat) ?>"
                    <?= $categorieActuelle === $cat ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Prix (FCFA)</label>
        <input
            type="number"
            name="prix"
            min="1"
            step="1"
            value="<?= htmlspecialchars($_POST["prix"] ?? $plat["prix"]) ?>"
            required
        >

        <label>Photo actuelle</label>

        <?php if (!empty($plat["photo"]) && is_file(__DIR__ . "/" . $plat["photo"])): ?>
            <img
                src="<?= htmlspecialchars($plat["photo"]) ?>"
                class="photo"
                alt="Photo du plat"
            >
        <?php else: ?>
            <p>Aucune photo disponible.</p>
        <?php endif; ?>

        <label>Choisir une nouvelle photo (facultatif)</label>
        <input
            type="file"
            name="photo"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <label>
            <input
                type="checkbox"
                name="disponible"
                value="1"
                <?= (int)($_POST["disponible"] ?? $plat["disponible"]) === 1 ? "checked" : "" ?>
            >
            Plat disponible
        </label>

        <button type="submit">Enregistrer les modifications</button>

    </form>

    <a href="menu.php" class="retour">← Retour au menu</a>

</div>

</body>
</html>
