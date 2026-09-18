<?php
require_once __DIR__ . '/../includes/auth_guard.php';

$forcedChange = passwordChangeRequired();
$preauthorized = passwordChangeRecentlyAuthorized();
$requireCurrentPassword = !$forcedChange && !$preauthorized;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier le mot de passe - LOGITIX</title>
    <link href="../css/fonts.css" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/vegas.min.css" rel="stylesheet">
    <link href="../css/tooplate-barista.css" rel="stylesheet">
    <style>
        body { background: #8B4513; font-family: 'Jost', sans-serif; }
        .booking-form-wrap { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border-radius: 16px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1); }
        .form-control { background: rgba(255,255,255,0.95); border: none; padding: 0.8rem 1.2rem; border-radius: 8px; }
        .btn-connect { background: #D2691E; color: white; font-weight: 700; padding: 0.8rem; border-radius: 8px; border: none; transition: background 0.3s; }
        .btn-connect:hover { background: #8B4513; color: white; }
    </style>
</head>
<body>
    <main>
        <section class="booking-section section-padding" style="min-height:100vh; padding-top:100px;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8 col-12 mx-auto">
                        <div class="booking-form-wrap">
                            <form action="traitement_changement_mot_de_passe.php" method="post" autocomplete="off">
                                <?= csrfField() ?>
                                <div class="text-center mb-4">
                                    <img src="../images/logo.png" alt="LOGITIX" style="height:80px;"><br>
                                    <h2 class="text-white mt-3"><?= $forcedChange ? 'NOUVEAU MOT DE PASSE' : 'MODIFIER MON MOT DE PASSE' ?></h2>
                                    <p class="text-white-50">
                                        <?php if ($forcedChange): ?>
                                            Votre mot de passe temporaire doit être remplacé avant de continuer.
                                        <?php elseif ($preauthorized): ?>
                                            Votre identité vient d'être vérifiée. Définissez votre nouveau mot de passe.
                                        <?php else: ?>
                                            Confirmez votre mot de passe actuel puis choisissez le nouveau.
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <?php displayFlash(); ?>

                                <div class="booking-form-body">
                                    <div class="row g-3">
                                        <?php if ($requireCurrentPassword): ?>
                                            <div class="col-12">
                                                <input type="password" name="mot_de_passe_actuel" class="form-control" placeholder="Mot de passe actuel" maxlength="1024" autocomplete="current-password" required>
                                            </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <input type="password" name="nouveau_mot_de_passe" class="form-control" placeholder="Nouveau mot de passe" minlength="12" maxlength="200" autocomplete="new-password" required>
                                        </div>
                                        <div class="col-12">
                                            <input type="password" name="confirmation" class="form-control" placeholder="Confirmer le nouveau mot de passe" minlength="12" maxlength="200" autocomplete="new-password" required>
                                        </div>
                                        <div class="col-12 text-white-50 small">
                                            Minimum 12 caractères avec majuscule, minuscule, chiffre et caractère spécial.
                                        </div>
                                        <div class="col-lg-5 col-md-7 col-10 mx-auto mt-2">
                                            <button type="submit" class="form-control btn-connect"><i class="bi bi-key"></i> Enregistrer le nouveau mot de passe</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <?php if ($forcedChange): ?>
                                <form action="../deconnexion.php" method="post" class="text-center mt-3">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-link text-white-50 text-decoration-none">Se déconnecter</button>
                                </form>
                            <?php else: ?>
                                <div class="text-center mt-3"><a href="dashboard.php" class="text-white-50 text-decoration-none"><i class="bi bi-arrow-left"></i> Retour à mon espace</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script src="../js/bootstrap.min.js"></script>
</body>
</html>
