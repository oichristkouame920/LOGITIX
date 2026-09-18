<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/UserModel.php';

$userModel = new UserModel('client');
$user = $userModel->findById((int) $_SESSION['user_id']);
if (!$user) {
    logout();
    redirect('se_connecter.php');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: #f5f3f0; font-family: 'Jost', sans-serif; }
        .navbar-custom { background: #01203F; padding: 1rem 0; }
        .navbar-custom .navbar-brand { color: #D2691E; font-weight: 700; font-size: 1.5rem; }
        .navbar-custom .nav-link { color: rgba(255,255,255,0.8); }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { color: #D2691E; }
        .profile-card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 700px; margin: 2rem auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">LOGITIX</a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link active" href="profil.php"><i class="bi bi-person"></i> Profil</a>
                <form action="../deconnexion.php" method="post" class="m-0"><?= csrfField() ?><button class="nav-link text-danger border-0 bg-transparent" type="submit"><i class="bi bi-box-arrow-right"></i> Déconnexion</button></form>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-card">
            <h4 class="mb-4">Mon profil</h4>
            <?php displayFlash(); ?>
            <div class="row g-3">
                <div class="col-md-6"><strong>Nom</strong><div><?= e((string) $user['nom']) ?></div></div>
                <div class="col-md-6"><strong>Prénom</strong><div><?= e((string) $user['prenom']) ?></div></div>
                <div class="col-md-6"><strong>Email</strong><div><?= e((string) $user['email']) ?></div></div>
                <div class="col-md-6"><strong>Téléphone</strong><div><?= e((string) $user['telephone']) ?></div></div>
            </div>
            <hr class="my-4">
            <h5><i class="bi bi-key"></i> Sécurité du compte</h5>
            <p class="text-muted">Vous pouvez modifier votre mot de passe à tout moment. Les autres sessions seront invalidées après le changement.</p>
            <a href="changer_mot_de_passe.php" class="btn btn-outline-primary"><i class="bi bi-key"></i> Modifier mon mot de passe</a>
        </div>
    </div>
    <script src="../js/bootstrap.min.js"></script>
</body>
</html>
