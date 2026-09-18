<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$model = new AdminLogisticsModel();
try {
    $items = $model->listChauffeurs();
} catch (Throwable $e) {
    error_log('LOGITIX chauffeurs: ' . $e->getMessage());
    $items = [];
    setError('Impossible de charger les chauffeurs.');
}
adminPageStart('Chauffeurs', 'chauffeurs');
?>
<div class="admin-card mb-4">
    <h5>Ajouter un chauffeur</h5>
    <form method="post" action="traitement_chauffeur.php" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-3"><label class="form-label">Nom</label><input class="form-control" name="nom" maxlength="100" required></div>
        <div class="col-md-3"><label class="form-label">Prénom</label><input class="form-control" name="prenom" maxlength="100" required></div>
        <div class="col-md-2"><label class="form-label">Permis</label><input class="form-control" name="permis" maxlength="30"></div>
        <div class="col-md-2"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" maxlength="30"></div>
        <div class="col-md-1"><label class="form-label">Libre</label><select class="form-select" name="disponible"><option value="1">Oui</option><option value="0">Non</option></select></div>
        <div class="col-md-1"><button class="btn btn-logitix w-100">Ajouter</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nom</th><th>Prénom</th><th>Permis</th><th>Téléphone</th><th>Disponible</th><th>Modifier</th></tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted">Aucun chauffeur.</td></tr><?php endif; ?>
            <?php foreach ($items as $c): $formId = 'chauffeur-' . (int) $c['id']; ?>
                <tr>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="nom" maxlength="100" value="<?= e($c['nom']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="prenom" maxlength="100" value="<?= e($c['prenom']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="permis" maxlength="30" value="<?= e($c['permis'] ?? '') ?>"></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="telephone" maxlength="30" value="<?= e($c['telephone'] ?? '') ?>"></td>
                    <td><select form="<?= e($formId) ?>" class="form-select form-select-sm" name="disponible"><option value="1"<?= !empty($c['disponible']) ? ' selected' : '' ?>>Oui</option><option value="0"<?= empty($c['disponible']) ? ' selected' : '' ?>>Non</option></select></td>
                    <td>
                        <form id="<?= e($formId) ?>" method="post" action="traitement_chauffeur.php">
                            <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
