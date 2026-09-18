<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';
$user = getCurrentUser();
$stats = ['total_expeditions' => 0, 'en_cours' => 0, 'livrees' => 0, 'total_devis' => 0];
try {
    $stats = (new ClientLogisticsModel())->dashboardStats((int) $_SESSION['user_id']);
} catch (Throwable $e) {
    error_log('LOGITIX dashboard stats: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Client - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: #f5f3f0; font-family: 'Jost', sans-serif; }
        .navbar-custom { background: #01203F; padding: 1rem 0; }
        .navbar-custom .navbar-brand { color: #D2691E; font-weight: 700; font-size: 1.5rem; }
        .navbar-custom .nav-link { color: rgba(255,255,255,0.8); }
        .navbar-custom .nav-link:hover { color: #D2691E; }
        .navbar-custom .nav-link.active { color: #D2691E; }
        .welcome-banner { background: linear-gradient(135deg, #01203F, #0a1a2e); color: #fff; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .stat-card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 4px solid #D2691E; }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #01203F; }
        .stat-card .label { color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-card .icon { float: right; font-size: 2.5rem; color: rgba(210,105,30,0.3); }
        .quick-action { background: #fff; border-radius: 12px; padding: 1.5rem; text-align: center; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; }
        .quick-action:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .quick-action .icon { font-size: 2.5rem; color: #D2691E; }
        .quick-action h6 { color: #01203F; margin-top: 0.8rem; font-weight: 600; }
        .footer-custom { background: #01203F; color: rgba(255,255,255,0.6); padding: 1.5rem 0; margin-top: 3rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">LOGITIX</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="expeditions.php"><i class="bi bi-box"></i> Mes expéditions</a></li>
                    <li class="nav-item"><a class="nav-link" href="devis.php"><i class="bi bi-file-earmark-text"></i> Devis</a></li>
                    <li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person"></i> Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="changer_mot_de_passe.php"><i class="bi bi-key"></i> Mot de passe</a></li>
                    <li class="nav-item"><form action="../deconnexion.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="nav-link text-danger border-0 bg-transparent" type="submit"><i class="bi bi-box-arrow-right"></i> Déconnexion</button></form></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="welcome-banner">
            <h4>Bonjour, <?= e($_SESSION['user_prenom'] ?? 'Client') ?> 👋</h4>
            <p class="mb-0 text-white-50">Bienvenue sur votre espace client LOGITIX.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bi bi-box"></div>
                    <div class="number"><?= (int) $stats['total_expeditions'] ?></div>
                    <div class="label">Mes expéditions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bi bi-clock-history"></div>
                    <div class="number"><?= (int) $stats['en_cours'] ?></div>
                    <div class="label">En cours</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bi bi-check-circle"></div>
                    <div class="number"><?= (int) $stats['livrees'] ?></div>
                    <div class="label">Livrées</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon bi bi-file-earmark-text"></div>
                    <div class="number"><?= (int) $stats['total_devis'] ?></div>
                    <div class="label">Devis</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <div class="col-md-3">
                <a class="quick-action d-block text-decoration-none" href="expeditions.php?action=create">
                    <div class="icon bi bi-plus-circle"></div>
                    <h6>Nouvelle expédition</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a class="quick-action d-block text-decoration-none" href="expeditions.php#suivi">
                    <div class="icon bi bi-search"></div>
                    <h6>Suivre un colis</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a class="quick-action d-block text-decoration-none" href="devis.php?action=create">
                    <div class="icon bi bi-file-earmark-text"></div>
                    <h6>Demander un devis</h6>
                </a>
            </div>
            <div class="col-md-3">
                <a class="quick-action d-block text-decoration-none" href="profil.php">
                    <div class="icon bi bi-person"></div>
                    <h6>Mon profil</h6>
                </a>
            </div>
        </div>
    </div>

    <footer class="footer-custom">
        <div class="container text-center">
            <p class="mb-0">Copyright © kouame — Design 2025</p>
        </div>
    </footer>

    <script src="../js/bootstrap.min.js"></script>
</body>
</html>
