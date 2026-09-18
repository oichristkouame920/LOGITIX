<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$changePasswordIntent = (string) ($_GET['action'] ?? '') === 'change_password';

if (isLoggedIn()) {
    if ($changePasswordIntent) {
        redirect(isAdmin() ? 'admin/changer_mot_de_passe.php' : 'client/changer_mot_de_passe.php');
    }
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('client/dashboard.php');
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Se connecter - LOGITIX</title>
    <link href="css/fonts.css" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/vegas.min.css" rel="stylesheet">
    <link href="css/tooplate-barista.css" rel="stylesheet">
    <style>
        body { background: #8B4513; font-family: 'Jost', sans-serif; }
        .booking-form-wrap { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border-radius: 16px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1); }
        .form-control { background: rgba(255,255,255,0.95); border: none; padding: 0.8rem 1.2rem; border-radius: 8px; }
        .btn-connect { background: #D2691E; color: white; font-weight: 700; padding: 0.8rem; border-radius: 8px; border: none; transition: background 0.3s; }
        .btn-connect:hover { background: #8B4513; color: white; }
        .btn-create { background: white; color: #8B4513; font-weight: 700; padding: 0.8rem; border-radius: 8px; border: none; transition: background 0.3s; text-decoration: none; display: block; text-align: center; }
        .btn-create:hover { background: #f0f0f0; color: #8B4513; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <nav class="navbar navbar-expand-xl fixed-top" style="background: rgba(1,32,63,0.95);">
            <div class="container">
                <div class="navbar-brand d-flex align-items-center">
                    <a href="index.html"><img src="images/logo.png" class="navbar-brand-image img-fluid" alt="LOGITIX" style="height:50px;"></a>
                </div>
                <a class="navbar-brand text-white" href="index.html">LOGITIX</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-lg-auto">
                        <li class="nav-item"><a class="nav-link text-white" href="index.html">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="index.html#section_2">À propos</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="index.html#section_3">Flotte</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="index.html#section_4">Services</a></li>
                        <li class="nav-item"><a class="nav-link text-white" href="index.html#section_6">Entrepôts</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <section class="booking-section section-padding" style="min-height:70vh; padding-top:150px;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8 col-12 mx-auto">
                        <div class="booking-form-wrap">
                            <form action="client/traitement_connexion.php" method="post">
                                <?= csrfField() ?>
                                <?php if ($changePasswordIntent): ?><input type="hidden" name="intent" value="change_password"><?php endif; ?>
                                <div class="text-center mb-4">
                                    <img src="images/logo.png" alt="LOGITIX" style="height:80px;"><br>
                                    <h2 class="text-white mt-3"><?= $changePasswordIntent ? 'MODIFIER MON MOT DE PASSE' : 'SE CONNECTER' ?></h2>
                                    <p class="text-white-50"><?= $changePasswordIntent ? 'Identifiez-vous avant de choisir un nouveau mot de passe.' : 'Accédez à votre espace client LOGITIX' ?></p>
                                </div>

                                <?php displayFlash(); ?>

                                <div class="booking-form-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <input type="email" name="email" class="form-control" placeholder="Adresse email" required>
                                        </div>
                                        <div class="col-12">
                                            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
                                        </div>
                                        <div class="col-lg-4 col-md-6 col-8 mx-auto mt-2">
                                            <button type="submit" class="form-control btn-connect"><?= $changePasswordIntent ? 'Continuer' : 'Se connecter' ?></button>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <?php if ($changePasswordIntent): ?>
                                        <a href="se_connecter.php" class="text-white-50 text-decoration-none"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
                                    <?php else: ?>
                                        <a href="se_connecter.php?action=change_password" class="text-white text-decoration-none"><i class="bi bi-key"></i> Modifier mon mot de passe</a>
                                    <?php endif; ?>
                                </div>

                                <hr class="border-light my-4">

                                <div class="text-center">
                                    <h5 class="text-white">Vous n'avez pas de compte ?</h5>
                                    <div class="col-lg-4 col-md-6 col-8 mx-auto mt-2">
                                        <a href="crer_compte.php" class="btn-create">Créer un compte</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer" style="background: #1a1612; color: rgba(255,255,255,0.6); padding: 2rem 0;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 col-12 me-auto">
                        <em class="text-white d-block mb-4">Où nous trouver ?</em>
                        <strong class="text-white"><i class="bi-geo-alt me-2"></i> 📍 Terminal à Conteneurs de Vridi, Port Autonome d'Abidjan, Côte d'Ivoire</strong>
                        <ul class="social-icon mt-4">
                            <li class="social-icon-item"><a href="https://www.facebook.com/logitix" class="social-icon-link bi-facebook"></a></li>
                            <li class="social-icon-item"><a href="https://wa.me/2250503231625" class="social-icon-link bi-whatsapp"></a></li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-12 mt-4 mb-3 mt-lg-0 mb-lg-0">
                        <em class="text-white d-block mb-4">Contacts</em>
                        <p class="d-flex mb-1"><strong class="me-2 text-white">Téléphone:</strong><a href="tel:+2250503231625" class="text-white-50 text-decoration-none">(+225) 05 03 23 16 25</a></p>
                        <p class="d-flex"><strong class="me-2 text-white">Email:</strong><a href="mailto:oichristkouame920@gmail.com" class="text-white-50 text-decoration-none">oichristkouame920@gmail.com</a></p>
                    </div>
                    <div class="col-lg-8 col-12 mt-4"><p class="copyright-text mb-0">Copyright © kouame — Design 2025</p></div>
                </div>
            </div>
        </footer>
    </main>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.sticky.js"></script>
    <script src="js/vegas.min.js"></script>
    <script src="js/custom.js"></script>
</body>
</html>
