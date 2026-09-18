<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';

$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
if (strlen($reference) > 30) {
    $reference = substr($reference, 0, 30);
}
$showCreate = (string) ($_GET['action'] ?? '') === 'create';
$expeditions = [];
$pageError = null;

try {
    $model = new ClientLogisticsModel();
    $expeditions = $model->listExpeditions((int) $_SESSION['user_id'], $reference);
} catch (Throwable $e) {
    error_log('LOGITIX expeditions client: ' . $e->getMessage());
    $pageError = APP_ENV === 'development'
        ? 'Les acces MySQL aux expeditions ne sont pas encore actives. Appliquez la mise a niveau LOGITIX v7.7.2.'
        : 'Les expeditions sont temporairement indisponibles.';
}

function expeditionStatusLabel(string $status): string {
    return [
        'planifiee' => 'Planifiee',
        'en_cours' => 'En cours',
        'livree' => 'Livree',
        'annulee' => 'Annulee',
    ][$status] ?? $status;
}

function expeditionStatusClass(string $status): string {
    return [
        'planifiee' => 'secondary',
        'en_cours' => 'warning',
        'livree' => 'success',
        'annulee' => 'danger',
    ][$status] ?? 'secondary';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes expeditions - LOGITIX</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { background: #f5f3f0; font-family: 'Jost', sans-serif; }
        .navbar-custom { background: #01203F; padding: 1rem 0; }
        .navbar-custom .navbar-brand { color: #D2691E; font-weight: 700; font-size: 1.5rem; }
        .navbar-custom .nav-link { color: rgba(255,255,255,0.8); }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { color: #D2691E; }
        .page-card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .section-title { color: #01203F; font-weight: 700; }
        .btn-logitix { background: #D2691E; border-color: #D2691E; color: #fff; }
        .btn-logitix:hover { background: #a94f12; border-color: #a94f12; color: #fff; }
        .reference { font-family: monospace; font-weight: 700; color: #01203F; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">LOGITIX</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="expeditions.php"><i class="bi bi-box"></i> Mes expeditions</a></li>
                <li class="nav-item"><a class="nav-link" href="devis.php"><i class="bi bi-file-earmark-text"></i> Devis</a></li>
                <li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person"></i> Profil</a></li>
                <li class="nav-item"><a class="nav-link" href="changer_mot_de_passe.php"><i class="bi bi-key"></i> Mot de passe</a></li>
                <li class="nav-item"><form action="../deconnexion.php" method="post" class="m-0"><?= csrfField() ?><button class="nav-link text-danger border-0 bg-transparent" type="submit"><i class="bi bi-box-arrow-right"></i> Deconnexion</button></form></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php displayFlash(); ?>
    <?php if ($pageError): ?><div class="alert alert-warning"><?= e($pageError) ?></div><?php endif; ?>

    <div class="page-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h3 class="section-title mb-1">Mes expeditions</h3>
                <p class="text-muted mb-0">Consultez vos expeditions et leur statut.</p>
            </div>
            <a class="btn btn-logitix" href="expeditions.php?action=create"><i class="bi bi-plus-circle"></i> Nouvelle expedition</a>
        </div>

        <form method="get" action="expeditions.php" class="row g-2 align-items-end" id="suivi">
            <div class="col-md-8">
                <label for="reference" class="form-label">Suivre un colis par reference</label>
                <input type="text" class="form-control" id="reference" name="reference" value="<?= e($reference) ?>" maxlength="30" placeholder="Ex. LOG-20260819-ABC123DEF456">
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Rechercher</button>
            </div>
        </form>
    </div>

    <?php if ($showCreate): ?>
    <div class="page-card mb-4">
        <h4 class="section-title mb-3">Creer une expedition</h4>
        <form action="traitement_expedition.php" method="post" autocomplete="off">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="depart">Depart</label>
                    <input class="form-control" id="depart" name="depart" maxlength="150" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="destination">Destination</label>
                    <input class="form-control" id="destination" name="destination" maxlength="150" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="date_depart">Date de depart souhaitee</label>
                    <input class="form-control" type="datetime-local" id="date_depart" name="date_depart">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-logitix" type="submit"><i class="bi bi-check-circle"></i> Creer l expedition</button>
                    <a class="btn btn-outline-secondary" href="expeditions.php">Annuler</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="page-card">
        <?php if (!$pageError && !$expeditions): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-box" style="font-size:3rem"></i>
                <p class="mt-3 mb-0"><?= $reference !== '' ? 'Aucune expedition ne correspond a cette reference.' : 'Vous n avez pas encore d expedition.' ?></p>
            </div>
        <?php elseif ($expeditions): ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Trajet</th>
                        <th>Statut</th>
                        <th>Depart</th>
                        <th>Livraison estimee</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expeditions as $expedition): ?>
                        <tr>
                            <td class="reference"><?= e((string) $expedition['reference']) ?></td>
                            <td><?= e((string) $expedition['depart']) ?> <i class="bi bi-arrow-right"></i> <?= e((string) $expedition['destination']) ?></td>
                            <td><span class="badge text-bg-<?= e(expeditionStatusClass((string) $expedition['statut'])) ?>"><?= e(expeditionStatusLabel((string) $expedition['statut'])) ?></span></td>
                            <td><?= !empty($expedition['date_depart']) ? e(formatDate((string) $expedition['date_depart'], 'd/m/Y H:i')) : '<span class="text-muted">A planifier</span>' ?></td>
                            <td><?= !empty($expedition['date_livraison_estimee']) ? e(formatDate((string) $expedition['date_livraison_estimee'], 'd/m/Y H:i')) : '<span class="text-muted">A definir</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
