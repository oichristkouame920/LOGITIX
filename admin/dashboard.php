<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$model = new AdminLogisticsModel();
try {
    $stats = $model->dashboardStats();
    $activity = $model->recentActivity(6);
} catch (Throwable $e) {
    error_log('LOGITIX admin dashboard: ' . $e->getMessage());
    $stats = ['clients'=>0,'expeditions'=>0,'expeditions_en_cours'=>0,'devis_en_attente'=>0,'vehicules_disponibles'=>0,'chauffeurs_disponibles'=>0];
    $activity = [];
    setError(APP_ENV === 'development'
        ? 'Fonctions admin v7.7.2 non disponibles. Appliquez la mise a niveau depuis admin/bootstrap.php ou tools/upgrade_admin_features.php.'
        : 'Les statistiques administrateur sont temporairement indisponibles.');
}

adminPageStart('Tableau de bord', 'dashboard');
?>
<div class="welcome-banner">
    <h4>Bonjour, <?= e($_SESSION['user_prenom'] ?? 'Admin') ?> 👋</h4>
    <p class="mb-0 text-white-50">Bienvenue sur votre espace d'administration LOGITIX.</p>
</div>

<div class="row g-4">
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-people"></div><div class="number"><?= (int)$stats['clients'] ?></div><div class="label">Clients</div></div></div>
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-box"></div><div class="number"><?= (int)$stats['expeditions'] ?></div><div class="label">Expéditions</div></div></div>
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-arrow-repeat"></div><div class="number"><?= (int)$stats['expeditions_en_cours'] ?></div><div class="label">En cours</div></div></div>
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-file-earmark-text"></div><div class="number"><?= (int)$stats['devis_en_attente'] ?></div><div class="label">Devis en attente</div></div></div>
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-truck"></div><div class="number"><?= (int)$stats['vehicules_disponibles'] ?></div><div class="label">Véhicules libres</div></div></div>
    <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="icon bi bi-person-badge"></div><div class="number"><?= (int)$stats['chauffeurs_disponibles'] ?></div><div class="label">Chauffeurs libres</div></div></div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <h5 class="mb-3">Accès rapides</h5>
            <div class="d-flex gap-2 flex-wrap">
                <a href="utilisateurs.php" class="btn btn-outline-dark"><i class="bi bi-people me-1"></i> Clients</a>
                <a href="expeditions.php" class="btn btn-outline-dark"><i class="bi bi-box me-1"></i> Expéditions</a>
                <a href="devis.php" class="btn btn-outline-dark"><i class="bi bi-file-earmark-text me-1"></i> Devis</a>
                <a href="vehicules.php" class="btn btn-outline-dark"><i class="bi bi-truck me-1"></i> Flotte</a>
                <a href="audit.php" class="btn btn-outline-dark"><i class="bi bi-shield-check me-1"></i> Journal admin</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h5 class="mb-3">Dernières actions admin</h5>
            <?php if (!$activity): ?>
                <p class="text-muted mb-0">Aucune action enregistrée.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activity as $item): ?>
                        <div class="list-group-item px-0">
                            <div class="fw-semibold break-word"><?= e($item['action']) ?></div>
                            <small class="text-muted"><?= e($item['entity_type']) ?><?= $item['entity_id'] ? ' #' . (int)$item['entity_id'] : '' ?> · <?= e(formatDate($item['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php adminPageEnd(); ?>
