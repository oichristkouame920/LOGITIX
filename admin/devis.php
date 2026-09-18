<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$status = (string)($_GET['status'] ?? '');
if (!in_array($status, ['', 'en_attente', 'valide', 'refuse', 'converti'], true)) { $status=''; }
$model = new AdminLogisticsModel();
try { $devis = $model->listDevis($status); }
catch (Throwable $e) { error_log('LOGITIX admin devis: '.$e->getMessage()); $devis=[]; setError('Impossible de charger les devis.'); }

adminPageStart('Devis', 'devis');
?>
<div class="admin-card mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label">Statut</label><select name="status" class="form-select"><option value="">Tous</option><option value="en_attente"<?= $status==='en_attente'?' selected':'' ?>>En attente</option><option value="valide"<?= $status==='valide'?' selected':'' ?>>Validés</option><option value="refuse"<?= $status==='refuse'?' selected':'' ?>>Refusés</option><option value="converti"<?= $status==='converti'?' selected':'' ?>>Convertis</option></select></div>
        <div class="col-md-3"><button class="btn btn-logitix w-100">Filtrer</button></div>
    </form>
</div>

<?php if (!$devis): ?><div class="admin-card text-center text-muted">Aucun devis trouvé.</div><?php endif; ?>
<?php foreach ($devis as $item): ?>
<div class="admin-card mb-3">
    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
        <div><h5 class="mb-1">Devis #<?= (int)$item['id'] ?> · <?= e($item['depart']) ?> → <?= e($item['destination']) ?></h5><div class="text-muted small"><?= e(($item['prenom'] ?? '').' '.($item['nom'] ?? '')) ?> · <?= e($item['email'] ?? '') ?><?= !empty($item['raison_sociale']) ? ' · '.e($item['raison_sociale']) : '' ?></div></div>
        <div><?= adminStatusBadge((string)$item['statut']) ?></div>
    </div>
    <div class="row g-3 mb-3 small">
        <div class="col-md-3"><strong>Marchandise :</strong><br><?= e($item['type_marchandise'] ?: '—') ?></div>
        <div class="col-md-2"><strong>Poids :</strong><br><?= $item['poids_estime'] !== null ? e((string)$item['poids_estime']).' kg' : '—' ?></div>
        <div class="col-md-3"><strong>Date souhaitée :</strong><br><?= $item['date_souhaitee'] ? e(formatDate($item['date_souhaitee'], 'd/m/Y')) : '—' ?></div>
        <div class="col-md-4"><strong>Créé :</strong><br><?= e(formatDate($item['date_creation'])) ?></div>
        <?php if (!empty($item['message'])): ?><div class="col-12"><strong>Message client :</strong><br><?= nl2br(e($item['message'])) ?></div><?php endif; ?>
    </div>

    <?php if ($item['statut'] !== 'converti'): ?>
    <form method="post" action="traitement_devis.php" class="row g-2 align-items-end">
        <?= csrfField() ?><input type="hidden" name="action" value="update"><input type="hidden" name="devis_id" value="<?= (int)$item['id'] ?>">
        <div class="col-md-2"><label class="form-label">Statut</label><select class="form-select" name="statut" required><option value="en_attente"<?= $item['statut']==='en_attente'?' selected':'' ?>>En attente</option><option value="valide"<?= $item['statut']==='valide'?' selected':'' ?>>Validé</option><option value="refuse"<?= $item['statut']==='refuse'?' selected':'' ?>>Refusé</option></select></div>
        <div class="col-md-2"><label class="form-label">Montant</label><input type="number" min="0" max="999999999999.99" step="0.01" class="form-control" name="montant" value="<?= e((string)($item['montant_propose'] ?? '')) ?>"></div>
        <div class="col-md-1"><label class="form-label">Devise</label><input class="form-control text-uppercase" name="devise" maxlength="3" value="<?= e($item['devise'] ?: 'XOF') ?>" required></div>
        <div class="col-md-5"><label class="form-label">Réponse au client</label><input class="form-control" name="reponse" maxlength="3000" value="<?= e($item['reponse_admin'] ?? '') ?>"></div>
        <div class="col-md-2"><button class="btn btn-logitix w-100">Enregistrer</button></div>
    </form>
    <?php if ($item['statut'] === 'valide'): ?>
        <form method="post" action="traitement_devis.php" class="mt-3" onsubmit="return confirm('Convertir ce devis en expédition ?');">
            <?= csrfField() ?><input type="hidden" name="action" value="convert"><input type="hidden" name="devis_id" value="<?= (int)$item['id'] ?>">
            <button class="btn btn-outline-success"><i class="bi bi-arrow-right-circle me-1"></i> Transformer en expédition</button>
        </form>
    <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info mb-0">Ce devis est verrouillé car il a déjà été transformé en expédition.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php adminPageEnd(); ?>
