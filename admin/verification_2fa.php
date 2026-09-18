<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['pending_2fa_user_id']) || empty($_SESSION['pending_2fa_time'])) {
    redirect('admin/connexion.php');
}

// La fenêtre d'authentification en 2 temps expire au bout de 5 minutes.
if (time() - $_SESSION['pending_2fa_time'] > 300) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
    setError('Session de connexion expirée. Veuillez vous reconnecter.');
    redirect('admin/connexion.php');
}

$error = getFlash('error');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification en 2 étapes - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #01203F, #0a1a2e); min-height: 100vh; display: flex; align-items: center; font-family: 'Jost', sans-serif; }
        .admin-login-box { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2.5rem; max-width: 420px; width: 100%; margin: 0 auto; }
        .admin-login-box h2 { color: #fff; font-weight: 700; }
        .admin-login-box .form-control { background: rgba(255,255,255,0.9); border: none; padding: 0.8rem 1.2rem; border-radius: 8px; text-align: center; letter-spacing: 0.5em; font-size: 1.4rem; }
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
                        <h2 class="mt-3">Vérification</h2>
                        <p class="text-white-50" style="font-size:0.9rem;">Entrez le code à 6 chiffres généré par votre application d'authentification</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form action="traitement_verification_2fa.php" method="post">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <input type="text" name="code" class="form-control" placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                        </div>
                        <button type="submit" class="btn-admin">Vérifier</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="connexion.php" class="text-white-50" style="font-size:0.85rem;text-decoration:none;">
                            <i class="bi bi-arrow-left"></i> Annuler la connexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
