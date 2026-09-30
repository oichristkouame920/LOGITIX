<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';

$user = getCurrentUser();
$stats = [
    'total_expeditions' => 0,
    'en_cours' => 0,
    'livrees' => 0,
    'total_devis' => 0
];

try {
    $stats = (new ClientLogisticsModel())->dashboardStats((int) $_SESSION['user_id']);
} catch (Throwable $e) {
    error_log('LOGITIX dashboard stats: ' . $e->getMessage());
}

$prenom = trim((string) ($_SESSION['user_prenom'] ?? 'Client'));
if ($prenom === '') {
    $prenom = 'Client';
}

$totalExpeditions = (int) $stats['total_expeditions'];
$enCours = (int) $stats['en_cours'];
$livrees = (int) $stats['livrees'];
$totalDevis = (int) $stats['total_devis'];

$tauxLivraison = $totalExpeditions > 0
    ? min(100, (int) round(($livrees / $totalExpeditions) * 100))
    : 0;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Espace client LOGITIX">
    <meta name="author" content="LOGITIX">
    <title>Tableau de bord client - LOGITIX</title>

    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">

    <style>
        :root {
            --logitix-navy: #01203F;
            --logitix-navy-2: #0a1a2e;
            --logitix-orange: #D2691E;
            --logitix-orange-dark: #a94f12;
            --logitix-bg: #f5f3f0;
            --logitix-surface: #ffffff;
            --logitix-border: #e7e2dc;
            --logitix-text: #17212b;
            --logitix-muted: #6f7883;
            --logitix-success: #198754;
            --sidebar-width: 270px;
            --radius-lg: 20px;
            --radius-md: 14px;
            --shadow-sm: 0 8px 24px rgba(1, 32, 63, 0.06);
            --shadow-md: 0 18px 50px rgba(1, 32, 63, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--logitix-bg);
            color: var(--logitix-text);
            font-family: 'Jost', sans-serif;
        }

        a {
            text-decoration: none;
        }

        .dashboard-shell {
            min-height: 100vh;
            display: flex;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            min-height: 100vh;
            position: sticky;
            top: 0;
            align-self: flex-start;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at top left, rgba(210, 105, 30, 0.18), transparent 32%),
                linear-gradient(180deg, var(--logitix-navy) 0%, var(--logitix-navy-2) 100%);
            color: #fff;
            padding: 24px 18px;
            z-index: 1040;
            box-shadow: 12px 0 35px rgba(1, 32, 63, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 8px 26px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            margin-bottom: 22px;
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            padding: 5px;
        }

        .brand-text {
            line-height: 1.05;
        }

        .brand-name {
            display: block;
            color: #fff;
            font-size: 1.32rem;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .brand-subtitle {
            display: block;
            margin-top: 5px;
            color: rgba(255,255,255,0.55);
            font-size: 0.73rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .sidebar-label {
            padding: 0 12px;
            margin: 8px 0 10px;
            color: rgba(255,255,255,0.38);
            font-size: 0.67rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.13em;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-link,
        .sidebar-logout {
            width: 100%;
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 13px;
            border: 1px solid transparent;
            border-radius: 12px;
            color: rgba(255,255,255,0.76);
            background: transparent;
            font-size: 0.94rem;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .sidebar-link i,
        .sidebar-logout i {
            width: 22px;
            font-size: 1.05rem;
            text-align: center;
        }

        .sidebar-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.07);
            border-color: rgba(255,255,255,0.08);
            transform: translateX(2px);
        }

        .sidebar-link.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(210,105,30,0.96), rgba(169,79,18,0.96));
            border-color: rgba(255,255,255,0.12);
            box-shadow: 0 8px 22px rgba(0,0,0,0.16);
        }

        .sidebar-spacer {
            flex: 1;
        }

        .sidebar-user {
            margin-top: 24px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 14px;
            background: rgba(255,255,255,0.05);
        }

        .sidebar-user-row {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 10px;
        }

        .sidebar-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(210,105,30,0.20);
            color: #f1a66f;
            font-size: 1.15rem;
        }

        .sidebar-user-name {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .sidebar-user-role {
            color: rgba(255,255,255,0.48);
            font-size: 0.72rem;
        }

        .sidebar-logout {
            justify-content: center;
            color: #ffd1cc;
            border-color: rgba(255,255,255,0.09);
            cursor: pointer;
        }

        .sidebar-logout:hover {
            color: #fff;
            background: rgba(220,53,69,0.18);
            border-color: rgba(220,53,69,0.24);
        }

        /* MAIN */
        .main-panel {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 32px;
            background: rgba(255,255,255,0.94);
            border-bottom: 1px solid var(--logitix-border);
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 4px 16px rgba(1,32,63,0.03);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .sidebar-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border: 1px solid var(--logitix-border);
            border-radius: 11px;
            background: #fff;
            color: var(--logitix-navy);
            font-size: 1.2rem;
        }

        .page-eyebrow {
            margin: 0 0 3px;
            color: var(--logitix-orange);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .page-title {
            margin: 0;
            color: var(--logitix-navy);
            font-size: 1.25rem;
            font-weight: 750;
        }

        .topbar-account {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 11px;
            border: 1px solid var(--logitix-border);
            border-radius: 12px;
            background: #fff;
        }

        .topbar-account-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(1,32,63,0.07);
            color: var(--logitix-navy);
            font-size: 1.15rem;
        }

        .topbar-account strong {
            display: block;
            color: var(--logitix-navy);
            font-size: 0.88rem;
            line-height: 1.1;
        }

        .topbar-account small {
            color: var(--logitix-muted);
            font-size: 0.72rem;
        }

        .dashboard-content {
            width: 100%;
            max-width: 1450px;
            margin: 0 auto;
            padding: 30px 32px 36px;
        }

        /* HERO */
        .welcome-panel {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            padding: 32px;
            margin-bottom: 26px;
            border-radius: var(--radius-lg);
            color: #fff;
            background:
                radial-gradient(circle at 82% 15%, rgba(210,105,30,0.28), transparent 22%),
                linear-gradient(135deg, var(--logitix-navy) 0%, #07355e 58%, var(--logitix-navy-2) 100%);
            box-shadow: var(--shadow-md);
        }

        .welcome-panel::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -80px;
            bottom: -120px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 50%;
            box-shadow:
                0 0 0 35px rgba(255,255,255,0.025),
                0 0 0 70px rgba(255,255,255,0.018);
            pointer-events: none;
        }

        .welcome-copy {
            position: relative;
            z-index: 1;
            max-width: 660px;
        }

        .welcome-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 13px;
            padding: 6px 11px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.78);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .welcome-title {
            margin: 0 0 10px;
            font-size: clamp(1.65rem, 2.4vw, 2.35rem);
            font-weight: 800;
        }

        .welcome-text {
            margin: 0;
            max-width: 600px;
            color: rgba(255,255,255,0.67);
            font-size: 0.96rem;
            line-height: 1.65;
        }

        .welcome-actions {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-logitix-primary,
        .btn-logitix-light {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 11px;
            font-size: 0.88rem;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .btn-logitix-primary {
            background: var(--logitix-orange);
            color: #fff;
            border: 1px solid var(--logitix-orange);
        }

        .btn-logitix-primary:hover {
            background: var(--logitix-orange-dark);
            border-color: var(--logitix-orange-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-logitix-light {
            background: rgba(255,255,255,0.10);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.16);
        }

        .btn-logitix-light:hover {
            background: #fff;
            color: var(--logitix-navy);
            transform: translateY(-1px);
        }

        /* SECTION HEADERS */
        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin: 0 0 14px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--logitix-navy);
            font-size: 1.08rem;
            font-weight: 750;
        }

        .section-heading p {
            margin: 3px 0 0;
            color: var(--logitix-muted);
            font-size: 0.82rem;
        }

        /* STATS */
        .stat-card {
            height: 100%;
            position: relative;
            overflow: hidden;
            background: var(--logitix-surface);
            border: 1px solid var(--logitix-border);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: 0.22s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(1,32,63,0.09);
            border-color: rgba(210,105,30,0.34);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: rgba(210,105,30,0.11);
            color: var(--logitix-orange);
            font-size: 1.2rem;
        }

        .stat-number {
            margin-top: 20px;
            color: var(--logitix-navy);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            margin-top: 7px;
            color: var(--logitix-muted);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.045em;
            text-transform: uppercase;
        }

        .stat-accent {
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--logitix-orange);
        }

        /* LOWER GRID */
        .dashboard-card {
            height: 100%;
            background: #fff;
            border: 1px solid var(--logitix-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .dashboard-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 20px 22px 14px;
        }

        .dashboard-card-header h3 {
            margin: 0;
            color: var(--logitix-navy);
            font-size: 1rem;
            font-weight: 750;
        }

        .dashboard-card-header span {
            color: var(--logitix-muted);
            font-size: 0.77rem;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 0 22px 22px;
        }

        .quick-action {
            min-height: 116px;
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 16px;
            border: 1px solid var(--logitix-border);
            border-radius: 14px;
            background: #fff;
            color: inherit;
            transition: 0.22s ease;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            border-color: rgba(210,105,30,0.40);
            box-shadow: 0 10px 24px rgba(1,32,63,0.07);
        }

        .quick-action-icon {
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: rgba(1,32,63,0.07);
            color: var(--logitix-navy);
            font-size: 1.05rem;
        }

        .quick-action:hover .quick-action-icon {
            background: rgba(210,105,30,0.12);
            color: var(--logitix-orange);
        }

        .quick-action h4 {
            margin: 1px 0 4px;
            color: var(--logitix-navy);
            font-size: 0.91rem;
            font-weight: 700;
        }

        .quick-action p {
            margin: 0;
            color: var(--logitix-muted);
            font-size: 0.76rem;
            line-height: 1.45;
        }

        .activity-summary {
            padding: 2px 22px 22px;
        }

        .summary-main {
            padding: 18px;
            border-radius: 14px;
            background:
                linear-gradient(135deg, rgba(1,32,63,0.035), rgba(210,105,30,0.06));
            border: 1px solid rgba(1,32,63,0.07);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 9px;
            font-size: 0.82rem;
        }

        .summary-row span:first-child {
            color: var(--logitix-muted);
        }

        .summary-row strong {
            color: var(--logitix-navy);
        }

        .progress {
            height: 8px;
            margin: 13px 0 8px;
            border-radius: 100px;
            background: #e8e3dd;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--logitix-orange), var(--logitix-orange-dark));
            border-radius: 100px;
        }

        .summary-help {
            margin: 0;
            color: var(--logitix-muted);
            font-size: 0.74rem;
            line-height: 1.5;
        }

        .support-box {
            margin-top: 14px;
            padding: 15px 17px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 13px;
            background: var(--logitix-navy);
            color: #fff;
        }

        .support-box i {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(255,255,255,0.08);
            color: #e6a679;
            font-size: 1rem;
        }

        .support-box strong {
            display: block;
            font-size: 0.82rem;
        }

        .support-box small {
            color: rgba(255,255,255,0.56);
            font-size: 0.72rem;
        }

        .dashboard-footer {
            margin-top: auto;
            padding: 22px 32px;
            color: #8a9097;
            font-size: 0.78rem;
            text-align: center;
        }

        .sidebar-backdrop {
            display: none;
        }

        /* RESPONSIVE */
        @media (max-width: 1199.98px) {
            :root {
                --sidebar-width: 245px;
            }

            .dashboard-content {
                padding-left: 24px;
                padding-right: 24px;
            }

            .topbar {
                padding-left: 24px;
                padding-right: 24px;
            }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                width: 280px;
                min-width: 280px;
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                transform: translateX(-105%);
                transition: transform 0.28s ease;
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: inline-grid;
                place-items: center;
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 1035;
                background: rgba(1,32,63,0.48);
            }

            body.sidebar-open .sidebar-backdrop {
                display: block;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            .welcome-panel {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767.98px) {
            .topbar {
                min-height: 68px;
                padding: 11px 16px;
            }

            .topbar-account {
                padding: 7px;
            }

            .topbar-account div:last-child {
                display: none;
            }

            .dashboard-content {
                padding: 20px 16px 28px;
            }

            .welcome-panel {
                padding: 24px 20px;
                border-radius: 16px;
            }

            .welcome-actions {
                width: 100%;
            }

            .btn-logitix-primary,
            .btn-logitix-light {
                flex: 1 1 180px;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-footer {
                padding: 18px 16px;
            }
        }

        @media (max-width: 575.98px) {
            .page-title {
                font-size: 1rem;
            }

            .welcome-title {
                font-size: 1.55rem;
            }

            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .quick-action {
                min-height: auto;
            }
        }
    </style>
</head>

<body>
<div class="dashboard-shell">

    <aside class="sidebar" id="clientSidebar" aria-label="Navigation client">
        <div class="brand">
            <img src="../images/logo.png" class="brand-logo" alt="LOGITIX">
            <div class="brand-text">
                <span class="brand-name">LOGITIX</span>
                <span class="brand-subtitle">Espace client</span>
            </div>
        </div>

        <div class="sidebar-label">Navigation</div>

        <nav class="sidebar-nav">
            <a class="sidebar-link active" href="dashboard.php" aria-current="page">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Tableau de bord</span>
            </a>

            <a class="sidebar-link" href="expeditions.php">
                <i class="bi bi-box-seam"></i>
                <span>Mes expéditions</span>
            </a>

            <a class="sidebar-link" href="devis.php">
                <i class="bi bi-file-earmark-text"></i>
                <span>Mes devis</span>
            </a>

            <a class="sidebar-link" href="profil.php">
                <i class="bi bi-person"></i>
                <span>Mon profil</span>
            </a>

            <a class="sidebar-link" href="changer_mot_de_passe.php">
                <i class="bi bi-shield-lock"></i>
                <span>Mot de passe</span>
            </a>
        </nav>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-user">
            <div class="sidebar-user-row">
                <div class="sidebar-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <div>
                    <div class="sidebar-user-name"><?= e($prenom) ?></div>
                    <div class="sidebar-user-role">Compte client</div>
                </div>
            </div>

            <form action="../deconnexion.php" method="post" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="sidebar-logout" type="submit">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <div class="main-panel">

        <header class="topbar">
            <div class="topbar-left">
                <button
                    class="sidebar-toggle"
                    type="button"
                    id="sidebarToggle"
                    aria-label="Ouvrir le menu"
                    aria-controls="clientSidebar"
                    aria-expanded="false"
                >
                    <i class="bi bi-list"></i>
                </button>

                <div>
                    <p class="page-eyebrow">Espace client</p>
                    <h1 class="page-title">Tableau de bord</h1>
                </div>
            </div>

            <div class="topbar-account">
                <div class="topbar-account-icon">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div>
                    <strong><?= e($prenom) ?></strong>
                    <small>Client LOGITIX</small>
                </div>
            </div>
        </header>

        <main class="dashboard-content">

            <section class="welcome-panel">
                <div class="welcome-copy">
                    <div class="welcome-label">
                        <i class="bi bi-shield-check"></i>
                        Espace personnel sécurisé
                    </div>

                    <h2 class="welcome-title">
                        Bonjour, <?= e($prenom) ?> 👋
                    </h2>

                    <p class="welcome-text">
                        Gérez vos expéditions, consultez vos devis et suivez vos opérations
                        logistiques depuis un espace unique, clair et sécurisé.
                    </p>
                </div>

                <div class="welcome-actions">
                    <a class="btn-logitix-primary" href="expeditions.php?action=create">
                        <i class="bi bi-plus-lg"></i>
                        Nouvelle expédition
                    </a>

                    <a class="btn-logitix-light" href="devis.php?action=create">
                        <i class="bi bi-file-earmark-plus"></i>
                        Demander un devis
                    </a>
                </div>
            </section>

            <section class="mb-4">
                <div class="section-heading">
                    <div>
                        <h2>Vue d'ensemble</h2>
                        <p>Les principaux indicateurs de votre activité LOGITIX.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <span class="stat-accent"></span>
                            <div class="stat-top">
                                <div>
                                    <div class="stat-number"><?= $totalExpeditions ?></div>
                                    <div class="stat-label">Expéditions</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <span class="stat-accent"></span>
                            <div class="stat-top">
                                <div>
                                    <div class="stat-number"><?= $enCours ?></div>
                                    <div class="stat-label">En cours</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <span class="stat-accent"></span>
                            <div class="stat-top">
                                <div>
                                    <div class="stat-number"><?= $livrees ?></div>
                                    <div class="stat-label">Livrées</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <span class="stat-accent"></span>
                            <div class="stat-top">
                                <div>
                                    <div class="stat-number"><?= $totalDevis ?></div>
                                    <div class="stat-label">Devis</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <section class="dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3>Actions rapides</h3>
                                <span>Accédez directement aux opérations les plus utilisées.</span>
                            </div>
                        </div>

                        <div class="quick-actions-grid">
                            <a class="quick-action" href="expeditions.php?action=create">
                                <div class="quick-action-icon">
                                    <i class="bi bi-plus-circle"></i>
                                </div>
                                <div>
                                    <h4>Nouvelle expédition</h4>
                                    <p>Enregistrez une nouvelle demande d'expédition.</p>
                                </div>
                            </a>

                            <a class="quick-action" href="expeditions.php#suivi">
                                <div class="quick-action-icon">
                                    <i class="bi bi-search"></i>
                                </div>
                                <div>
                                    <h4>Suivre un colis</h4>
                                    <p>Consultez rapidement le suivi de vos expéditions.</p>
                                </div>
                            </a>

                            <a class="quick-action" href="devis.php?action=create">
                                <div class="quick-action-icon">
                                    <i class="bi bi-file-earmark-plus"></i>
                                </div>
                                <div>
                                    <h4>Demander un devis</h4>
                                    <p>Préparez une nouvelle demande de tarification.</p>
                                </div>
                            </a>

                            <a class="quick-action" href="profil.php">
                                <div class="quick-action-icon">
                                    <i class="bi bi-person-gear"></i>
                                </div>
                                <div>
                                    <h4>Mon profil</h4>
                                    <p>Consultez les informations associées à votre compte.</p>
                                </div>
                            </a>
                        </div>
                    </section>
                </div>

                <div class="col-12 col-xl-4">
                    <section class="dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h3>Résumé d'activité</h3>
                                <span>Situation actuelle de vos opérations.</span>
                            </div>
                        </div>

                        <div class="activity-summary">
                            <div class="summary-main">
                                <div class="summary-row">
                                    <span>Expéditions enregistrées</span>
                                    <strong><?= $totalExpeditions ?></strong>
                                </div>

                                <div class="summary-row">
                                    <span>Expéditions livrées</span>
                                    <strong><?= $livrees ?></strong>
                                </div>

                                <div class="summary-row">
                                    <span>Expéditions en cours</span>
                                    <strong><?= $enCours ?></strong>
                                </div>

                                <div class="progress" role="progressbar" aria-label="Part des expéditions livrées" aria-valuenow="<?= $tauxLivraison ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?= $tauxLivraison ?>%;"></div>
                                </div>

                                <p class="summary-help">
                                    <?= $tauxLivraison ?> % de vos expéditions enregistrées sont actuellement indiquées comme livrées.
                                </p>
                            </div>

                            <div class="support-box">
                                <i class="bi bi-headset"></i>
                                <div>
                                    <strong>Besoin d'aide ?</strong>
                                    <small>Retrouvez vos opérations depuis les rubriques du menu.</small>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <footer class="dashboard-footer">
            Copyright © <?= date('Y') ?> LOGITIX — Espace client
        </footer>
    </div>
</div>

<script src="../js/bootstrap.min.js"></script>
<script>
    (function () {
        "use strict";

        const toggle = document.getElementById("sidebarToggle");
        const backdrop = document.getElementById("sidebarBackdrop");

        function setSidebar(open) {
            document.body.classList.toggle("sidebar-open", open);

            if (toggle) {
                toggle.setAttribute("aria-expanded", open ? "true" : "false");
            }
        }

        if (toggle) {
            toggle.addEventListener("click", function () {
                setSidebar(!document.body.classList.contains("sidebar-open"));
            });
        }

        if (backdrop) {
            backdrop.addEventListener("click", function () {
                setSidebar(false);
            });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setSidebar(false);
            }
        });

        window.addEventListener("resize", function () {
            if (window.innerWidth >= 992) {
                setSidebar(false);
            }
        });
    })();
</script>
</body>
</html>
