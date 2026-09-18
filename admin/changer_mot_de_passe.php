<?php
require_once __DIR__ . '/../includes/auth_guard.php';

$forcedChange = passwordChangeRequired();
$preauthorized = passwordChangeRecentlyAuthorized();
$requireCurrentPassword = !$forcedChange && !$preauthorized;
$requireTotp = $requireCurrentPassword && !empty($databaseUser['totp_enabled']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier le mot de passe Admin - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #01203F, #0a1a2e); min-height: 100vh; display: flex; align-items: center; font-family: 'Jost', sans-serif; }
        .admin-login-box { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2.5rem; max-width: 460px; width: 100%; margin: 0 auto; }
        .admin-login-box h2 { color: #fff; font-weight: 700; }
        .admin-login-box .form-control { background: rgba(255,255,255,0.9); border: none; padding: 0.8rem 1.2rem; border-radius: 8px; }
        .btn-admin { background: #D2691E; color: #fff; font-weight: 700; padding: 0.8rem; border-radius: 8px; border: none; transition: background 0.3s; width: 100%; }
        .btn-admin:hover { background: #8B4513; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6 col-xl-5">
                <div class="admin-login-box">
                    <div class="text-center mb-4">
                        <img src="../images/logo.png" alt="LOGITIX" style="height:70px;">
                        <h2 class="mt-3"><?= $forcedChange ? 'Nouveau mot de passe' : 'Modifier mon mot de passe' ?></h2>
                        <p class="text-white-50" style="font-size:0.9rem;">
                            <?php if ($forcedChange): ?>
                                Le mot de passe temporaire doit être remplacé avant l'accès à l'administration.
                            <?php elseif ($preauthorized): ?>
                                Votre identité<?= !empty($databaseUser['totp_enabled']) ? ' et votre 2FA ont' : ' a' ?> été vérifiée. Définissez le nouveau mot de passe.
                            <?php else: ?>
                                Pour cette opération sensible, confirmez vos identifiants actuels.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php displayFlash(); ?>

                    <form action="traitement_changement_mot_de_passe.php" method="post" autocomplete="off">
                        <?= csrfField() ?>
                        <?php if ($requireCurrentPassword): ?>
                            <div class="mb-3">
                                <input type="password" name="mot_de_passe_actuel" class="form-control" placeholder="Mot de passe actuel" maxlength="1024" autocomplete="current-password" required>
                            </div>
                        <?php endif; ?>
                        <?php if ($requireTotp): ?>
                            <div class="mb-3">
                                <input type="text" name="code_totp" class="form-control" placeholder="Code 2FA à 6 chiffres" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <input type="password" name="nouveau_mot_de_passe" class="form-control" placeholder="Nouveau mot de passe" minlength="12" maxlength="200" autocomplete="new-password" required>
                        </div>
                        <div class="mb-3">
                            <input type="password" name="confirmation" class="form-control" placeholder="Confirmer le nouveau mot de passe" minlength="12" maxlength="200" autocomplete="new-password" required>
                        </div>
                        <p class="text-white-50 small">Minimum 12 caractères avec majuscule, minuscule, chiffre et caractère spécial.</p>
                        <button type="submit" class="btn-admin"><i class="bi bi-key"></i> Enregistrer le nouveau mot de passe</button>
                    </form>

                    <?php if ($forcedChange): ?>
                        <form action="deconnexion.php" method="post" class="text-center mt-3">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-link text-white-50 text-decoration-none">Se déconnecter</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center mt-3"><a href="dashboard.php" class="text-white-50 text-decoration-none"><i class="bi bi-arrow-left"></i> Retour au tableau de bord</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
