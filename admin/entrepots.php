<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$model = new AdminLogisticsModel();
try {
    $items = $model->listEntrepots();
} catch (Throwable $e) {
    error_log('LOGITIX entrepots: ' . $e->getMessage());
    $items = [];
    setError('Impossible de charger les entrepôts.');
}
adminPageStart('Entrepôts', 'entrepots');
?>
<div class="admin-card mb-4">
    <h5>Ajouter un entrepôt</h5>
    <form method="post" action="traitement_entrepot.php" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-4"><label class="form-label">Nom</label><input class="form-control" name="nom" maxlength="100" required></div>
        <div class="col-md-3"><label class="form-label">Ville</label><input class="form-control" name="ville" maxlength="100" required></div>
        <div class="col-md-3"><label class="form-label">Pays</label><input class="form-control" name="pays" maxlength="100" required></div>
        <div class="col-md-2"><button class="btn btn-logitix w-100">Ajouter</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nom</th><th>Ville</th><th>Pays</th><th>Modifier</th></tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="4" class="text-center text-muted">Aucun entrepôt.</td></tr><?php endif; ?>
            <?php foreach ($items as $w): $formId = 'entrepot-' . (int) $w['id']; ?>
                <tr>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="nom" maxlength="100" value="<?= e($w['nom']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="ville" maxlength="100" value="<?= e($w['ville']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="pays" maxlength="100" value="<?= e($w['pays']) ?>" required></td>
                    <td>
                        <form id="<?= e($formId) ?>" method="post" action="traitement_entrepot.php">
                            <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                            <button class="btn btn-sm btn-outline-dark">Enregistrer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminPageEnd(); ?>
