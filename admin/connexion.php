<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$changePasswordIntent = (string) ($_GET['action'] ?? '') === 'change_password';

if (isAdmin()) {
    redirect($changePasswordIntent ? 'admin/changer_mot_de_passe.php' : 'admin/dashboard.php');
}

$error = getFlash('error');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion Admin - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #01203F, #0a1a2e); min-height: 100vh; display: flex; align-items: center; font-family: 'Jost', sans-serif; }
        .admin-login-box { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; margin: 0 auto; }
        .admin-login-box h2 { color: #fff; font-weight: 700; }
        .admin-login-box .form-control { background: rgba(255,255,255,0.9); border: none; padding: 0.8rem 1.2rem; border-radius: 8px; }
        .btn-admin { background: #D2691E; color: #fff; font-weight: 700; padding: 0.8rem; border-radius: 8px; border: none; transition: background 0.3s; width: 100%; }
        .btn-admin:hover { background: #8B4513; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="admin-login-box">
                    <div class="text-center mb-4">
                        <img src="../images/logo.png" alt="LOGITIX" style="height:70px;">
                        <h2 class="mt-3"><?= $changePasswordIntent ? 'Modifier le mot de passe' : 'Espace Admin' ?></h2>
                        <p class="text-white-50" style="font-size:0.9rem;"><?= $changePasswordIntent ? 'Authentifiez-vous avant de définir un nouveau mot de passe.' : 'Connectez-vous à votre espace d\'administration' ?></p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form action="traitement_connexion.php" method="post">
                        <?= csrfField() ?>
                        <?php if ($changePasswordIntent): ?><input type="hidden" name="intent" value="change_password"><?php endif; ?>
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email administrateur" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
                        </div>
                        <button type="submit" class="btn-admin"><?= $changePasswordIntent ? 'Continuer' : 'Se connecter' ?></button>
                    </form>

                    <div class="text-center mt-3">
                        <?php if ($changePasswordIntent): ?>
                            <a href="connexion.php" class="text-white-50" style="font-size:0.85rem;text-decoration:none;"><i class="bi bi-arrow-left"></i> Retour à la connexion admin</a>
                        <?php else: ?>
                            <a href="connexion.php?action=change_password" class="text-white" style="font-size:0.9rem;text-decoration:none;"><i class="bi bi-key"></i> Modifier mon mot de passe</a>
                        <?php endif; ?>
                    </div>

                    <div class="text-center mt-2">
                        <a href="../se_connecter.php" class="text-white-50" style="font-size:0.85rem;text-decoration:none;">
                            <i class="bi bi-arrow-left"></i> Retour à l'espace client
                        </a>
                    </div>
                    <?php if (APP_ENV === 'development' && ADMIN_BOOTSTRAP_ENABLED && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1','::1'], true)): ?>
                        <div class="text-center mt-2">
                            <a href="bootstrap.php" class="text-warning" style="font-size:0.85rem;text-decoration:none;"><i class="bi bi-tools"></i> Assistant admin local</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
