<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$model = new AdminLogisticsModel();
try {
    $items = $model->listVehicules();
} catch (Throwable $e) {
    error_log('LOGITIX vehicules: ' . $e->getMessage());
    $items = [];
    setError('Impossible de charger les véhicules.');
}
adminPageStart('Véhicules', 'vehicules');
?>
<div class="admin-card mb-4">
    <h5>Ajouter un véhicule</h5>
    <form method="post" action="traitement_vehicule.php" class="row g-2 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-3"><label class="form-label">Nom</label><input class="form-control" name="nom" maxlength="100" required></div>
        <div class="col-md-2"><label class="form-label">Catégorie</label><input class="form-control" name="categorie" maxlength="50" required></div>
        <div class="col-md-2"><label class="form-label">Immatriculation</label><input class="form-control" name="immatriculation" maxlength="30"></div>
        <div class="col-md-2"><label class="form-label">Capacité</label><input class="form-control" name="capacite" maxlength="50"></div>
        <div class="col-md-2"><label class="form-label">Statut</label><select class="form-select" name="statut"><option value="disponible">Disponible</option><option value="maintenance">Maintenance</option></select></div>
        <div class="col-md-1"><button class="btn btn-logitix w-100">Ajouter</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nom</th><th>Catégorie</th><th>Immatriculation</th><th>Capacité</th><th>Statut</th><th>Modifier</th></tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted">Aucun véhicule.</td></tr><?php endif; ?>
            <?php foreach ($items as $v): $formId = 'vehicule-' . (int) $v['id']; ?>
                <tr>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="nom" maxlength="100" value="<?= e($v['nom']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="categorie" maxlength="50" value="<?= e($v['categorie']) ?>" required></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="immatriculation" maxlength="30" value="<?= e($v['immatriculation'] ?? '') ?>"></td>
                    <td><input form="<?= e($formId) ?>" class="form-control form-control-sm" name="capacite" maxlength="50" value="<?= e($v['capacite'] ?? '') ?>"></td>
                    <td><select form="<?= e($formId) ?>" class="form-select form-select-sm" name="statut"><?php foreach (['disponible'=>'Disponible','en_service'=>'En service','maintenance'=>'Maintenance'] as $k=>$label): ?><option value="<?= e($k) ?>"<?= $v['statut'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                    <td>
                        <form id="<?= e($formId) ?>" method="post" action="traitement_vehicule.php">
                            <?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
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
