<?php
/**
 * Gabarit visuel commun de l'administration LOGITIX.
 * Conserve l'identite graphique du dashboard admin existant.
 */

function adminPageStart(string $title, string $active = ''): void {
    $items = [
        'dashboard' => ['dashboard.php', 'bi-speedometer2', 'Tableau de bord'],
        'utilisateurs' => ['utilisateurs.php', 'bi-people', 'Utilisateurs'],
        'vehicules' => ['vehicules.php', 'bi-truck', 'Véhicules'],
        'remorques' => ['remorques.php', 'bi-box-seam', 'Remorques'],
        'chauffeurs' => ['chauffeurs.php', 'bi-person-badge', 'Chauffeurs'],
        'expeditions' => ['expeditions.php', 'bi-box', 'Expéditions'],
        'devis' => ['devis.php', 'bi-file-earmark-text', 'Devis'],
        'entrepots' => ['entrepots.php', 'bi-building', 'Entrepôts'],
        'audit' => ['audit.php', 'bi-shield-check', 'Journal admin'],
        'profil' => ['profil.php', 'bi-person', 'Mon profil'],
        'password' => ['changer_mot_de_passe.php', 'bi-key', 'Mot de passe'],
    ];
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - LOGITIX</title>
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
        .stat-card, .admin-card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card { border-left: 4px solid #D2691E; }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: #01203F; }
        .stat-card .label { color: #888; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-card .icon { float: right; font-size: 2.5rem; color: rgba(210,105,30,0.3); }
        .welcome-banner { background: linear-gradient(135deg, #01203F, #0a1a2e); color: #fff; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .btn-logitix { background: #D2691E; color: #fff; border: none; }
        .btn-logitix:hover { background: #8B4513; color: #fff; }
        .table thead th { color: #01203F; white-space: nowrap; }
        .badge-soft { background: #f2e6dc; color: #8B4513; }
        .form-label { font-weight: 600; color: #01203F; }
        .break-word { overflow-wrap: anywhere; }
        @media (max-width: 767.98px) { .sidebar { min-height: auto; } .main-content { padding: 1rem; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="logo">
                <h3>LOGITIX</h3>
                <span>Administration</span>
            </div>
            <nav class="nav flex-column mt-3">
                <?php foreach ($items as $key => [$href, $icon, $label]): ?>
                    <a href="<?= e($href) ?>" class="nav-link<?= $active === $key ? ' active' : '' ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a>
                <?php endforeach; ?>
                <form action="deconnexion.php" method="post" class="mt-3">
                    <?= csrfField() ?>
                    <button class="nav-link text-danger border-0 bg-transparent w-100 text-start" type="submit"><i class="bi bi-box-arrow-right"></i> Déconnexion</button>
                </form>
            </nav>
        </div>
        <main class="col-md-9 col-lg-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
                <h4 class="mb-0"><?= e($title) ?></h4>
                <span class="text-muted"><?= e(date('d/m/Y H:i')) ?></span>
            </div>
            <?php displayFlash(); ?>
    <?php
}

function adminPageEnd(): void {
    ?>
        </main>
    </div>
</div>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
    <?php
}

function adminStatusBadge(string $status): string {
    $labels = [
        'en_attente' => ['warning', 'En attente'],
        'valide' => ['success', 'Validé'],
        'refuse' => ['danger', 'Refusé'],
        'converti' => ['info', 'Converti'],
        'planifiee' => ['secondary', 'Planifiée'],
        'en_cours' => ['primary', 'En cours'],
        'livree' => ['success', 'Livrée'],
        'annulee' => ['danger', 'Annulée'],
        'disponible' => ['success', 'Disponible'],
        'en_service' => ['primary', 'En service'],
        'maintenance' => ['warning', 'Maintenance'],
    ];
    [$class, $label] = $labels[$status] ?? ['secondary', $status !== '' ? $status : '—'];
    return '<span class="badge bg-' . e($class) . '">' . e($label) . '</span>';
}
