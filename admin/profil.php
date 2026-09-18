<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/UserModel.php';

$userModel = new UserModel('admin');
$user = $userModel->findById($_SESSION['user_id']);

$error = getFlash('error');
$success = getFlash('success');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil - LOGITIX Admin</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: #f5f3f0; font-family: 'Jost', sans-serif; }
        .sidebar { min-height: 100vh; background: #01203F; padding: 1.5rem 0; }
        .sidebar .logo { text-align: center; padding: 0 1rem 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar .logo h3 { color: #D2691E; font-weight: 700; }
        .sidebar .logo span { color: rgba(255,255,255,0.5); font-size: 0.7rem; display: block; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 0.7rem 1.5rem; border-radius: 0; transition: all 0.3s; }
        .sidebar .nav-link:hover { background: rgba(210,105,30,0.2); color: #fff; }
        .sidebar .nav-link.active { background: #D2691E; color: #fff; }
        .sidebar .nav-link i { margin-right: 0.8rem; width: 20px; text-align: center; }
        .main-content { padding: 2rem; }
        .profile-card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 600px; }
        .profile-card .form-control { border-radius: 8px; padding: 0.7rem 1rem; }
        .btn-save { background: #D2691E; color: #fff; border: none; padding: 0.7rem 2rem; border-radius: 8px; font-weight: 600; transition: background 0.3s; }
        .btn-save:hover { background: #8B4513; color: #fff; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="logo">
                    <h3>LOGITIX</h3>
                    <span>Administration</span>
                </div>
                <nav class="nav flex-column mt-3">
                    <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
                    <a href="utilisateurs.php" class="nav-link"><i class="bi bi-people"></i> Utilisateurs</a>
                    <a href="vehicules.php" class="nav-link"><i class="bi bi-truck"></i> Véhicules</a>
                    <a href="remorques.php" class="nav-link"><i class="bi bi-box-seam"></i> Remorques</a>
                    <a href="chauffeurs.php" class="nav-link"><i class="bi bi-person-badge"></i> Chauffeurs</a>
                    <a href="expeditions.php" class="nav-link"><i class="bi bi-box"></i> Expéditions</a>
                    <a href="devis.php" class="nav-link"><i class="bi bi-file-earmark-text"></i> Devis</a>
                    <a href="entrepots.php" class="nav-link"><i class="bi bi-building"></i> Entrepôts</a>
                    <a href="audit.php" class="nav-link"><i class="bi bi-shield-check"></i> Journal admin</a>
                    <a href="profil.php" class="nav-link active"><i class="bi bi-person"></i> Mon profil</a>
                    <a href="changer_mot_de_passe.php" class="nav-link"><i class="bi bi-key"></i> Mot de passe</a>
                    <form action="deconnexion.php" method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="nav-link text-danger border-0 bg-transparent" type="submit"><i class="bi bi-box-arrow-right"></i> Déconnexion</button></form>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h4 class="mb-4">Mon profil</h4>

                <?php displayFlash(); ?>

                <div class="profile-card">
                    <form action="traitement_profil.php" method="post">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= e($user['nom'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" value="<?= e($user['telephone'] ?? '') ?>" required>
                        </div>

                        <button type="submit" class="btn-save">Mettre à jour</button>
                    </form>

                    <hr class="my-4">
                    <h5><i class="bi bi-key"></i> Mot de passe</h5>
                    <p class="text-muted small">La modification du mot de passe utilise une vérification dédiée et invalide les autres sessions.</p>
                    <a href="changer_mot_de_passe.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-key"></i> Modifier mon mot de passe</a>

                    <hr class="my-4">
                    <h5><i class="bi bi-shield-lock"></i> Double authentification</h5>
                    <p class="text-muted small">Ajoutez une couche de sécurité supplémentaire à votre compte administrateur avec un code à usage unique.</p>
                    <a href="2fa_setup.php" class="btn btn-outline-primary btn-sm">Gérer la double authentification</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.min.js"></script>
</body>
</html>
