<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';

$showCreate = (string) ($_GET['action'] ?? '') === 'create';
$devis = [];
$pageError = null;

try {
    $model = new ClientLogisticsModel();
    $devis = $model->listDevis((int) $_SESSION['user_id']);
} catch (Throwable $e) {
    error_log('LOGITIX devis client: ' . $e->getMessage());
    $pageError = APP_ENV === 'development'
        ? 'Les acces MySQL aux devis ne sont pas encore actives. Appliquez la mise a niveau LOGITIX v7.7.2.'
        : 'Les devis sont temporairement indisponibles.';
}

function devisStatusLabel(string $status): string {
    return [
        'en_attente' => 'En attente',
        'valide' => 'Valide',
        'refuse' => 'Refuse',
        'converti' => 'Converti',
    ][$status] ?? $status;
}

function devisStatusClass(string $status): string {
    return [
        'en_attente' => 'warning',
        'valide' => 'success',
        'refuse' => 'danger',
        'converti' => 'primary',
    ][$status] ?? 'secondary';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes devis - LOGITIX</title>
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
                <li class="nav-item"><a class="nav-link" href="expeditions.php"><i class="bi bi-box"></i> Mes expeditions</a></li>
                <li class="nav-item"><a class="nav-link active" href="devis.php"><i class="bi bi-file-earmark-text"></i> Devis</a></li>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="section-title mb-1">Mes devis</h3>
                <p class="text-muted mb-0">Consultez vos demandes et demandez une nouvelle estimation.</p>
            </div>
            <a class="btn btn-logitix" href="devis.php?action=create"><i class="bi bi-file-earmark-plus"></i> Demander un devis</a>
        </div>
    </div>

    <?php if ($showCreate): ?>
    <div class="page-card mb-4">
        <h4 class="section-title mb-3">Nouvelle demande de devis</h4>
        <form action="traitement_devis.php" method="post" autocomplete="off">
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
                    <label class="form-label" for="type_marchandise">Type de marchandise</label>
                    <input class="form-control" id="type_marchandise" name="type_marchandise" maxlength="150">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="poids_estime">Poids estime (kg)</label>
                    <input class="form-control" type="number" min="0" max="99999999.99" step="0.01" id="poids_estime" name="poids_estime">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date_souhaitee">Date souhaitee</label>
                    <input class="form-control" type="date" id="date_souhaitee" name="date_souhaitee">
                </div>
                <div class="col-12">
                    <label class="form-label" for="message">Informations complementaires</label>
                    <textarea class="form-control" id="message" name="message" rows="4" maxlength="3000"></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-logitix" type="submit"><i class="bi bi-send"></i> Envoyer la demande</button>
                    <a class="btn btn-outline-secondary" href="devis.php">Annuler</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="page-card">
        <?php if (!$pageError && !$devis): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-text" style="font-size:3rem"></i>
                <p class="mt-3 mb-0">Vous n avez encore aucune demande de devis.</p>
            </div>
        <?php elseif ($devis): ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Trajet</th>
                        <th>Marchandise</th>
                        <th>Poids</th>
                        <th>Date souhaitee</th>
                        <th>Statut</th>
                        <th>Proposition</th>
                        <th>Réponse LOGITIX</th>
                        <th>Demande le</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($devis as $quote): ?>
                        <tr>
                            <td><?= (int) $quote['id'] ?></td>
                            <td><?= e((string) $quote['depart']) ?> <i class="bi bi-arrow-right"></i> <?= e((string) $quote['destination']) ?></td>
                            <td><?= e((string) ($quote['type_marchandise'] ?: 'Non precise')) ?></td>
                            <td><?= $quote['poids_estime'] !== null ? e(number_format((float) $quote['poids_estime'], 2, ',', ' ')) . ' kg' : '<span class="text-muted">-</span>' ?></td>
                            <td><?= !empty($quote['date_souhaitee']) ? e(formatDate((string) $quote['date_souhaitee'], 'd/m/Y')) : '<span class="text-muted">-</span>' ?></td>
                            <td><span class="badge text-bg-<?= e(devisStatusClass((string) $quote['statut'])) ?>"><?= e(devisStatusLabel((string) $quote['statut'])) ?></span></td>
                            <td><?= $quote['montant_propose'] !== null ? e(number_format((float) $quote['montant_propose'], 2, ',', ' ')) . ' ' . e((string) ($quote['devise'] ?: 'XOF')) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= !empty($quote['reponse_admin']) ? nl2br(e((string) $quote['reponse_admin'])) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= e(formatDate((string) $quote['date_creation'], 'd/m/Y H:i')) ?></td>
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
