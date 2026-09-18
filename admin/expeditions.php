<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$status = (string)($_GET['status'] ?? '');
$q = clean((string)($_GET['q'] ?? ''));
if (!in_array($status, ['', 'planifiee','en_cours','livree','annulee'], true)) { $status=''; }
if (strlen($q) > 150) { $q = substr($q,0,150); }
$model = new AdminLogisticsModel();
try {
    $expeditions = $model->listExpeditions($status, $q);
    $vehicules = $model->listVehicules();
    $chauffeurs = $model->listChauffeurs();
    $remorques = $model->listRemorques();
} catch (Throwable $e) {
    error_log('LOGITIX admin expeditions: '.$e->getMessage());
    $expeditions=$vehicules=$chauffeurs=$remorques=[];
    setError('Impossible de charger les expéditions.');
}
function dtLocal(?string $v): string { return $v ? date('Y-m-d\TH:i', strtotime($v)) : ''; }
adminPageStart('Expéditions', 'expeditions');
?>
<div class="admin-card mb-4">
<form method="get" class="row g-2 align-items-end">
    <div class="col-md-5"><label class="form-label">Rechercher</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Référence, trajet ou email client"></div>
    <div class="col-md-4"><label class="form-label">Statut</label><select class="form-select" name="status"><option value="">Tous</option><option value="planifiee"<?= $status==='planifiee'?' selected':'' ?>>Planifiées</option><option value="en_cours"<?= $status==='en_cours'?' selected':'' ?>>En cours</option><option value="livree"<?= $status==='livree'?' selected':'' ?>>Livrées</option><option value="annulee"<?= $status==='annulee'?' selected':'' ?>>Annulées</option></select></div>
    <div class="col-md-3"><button class="btn btn-logitix w-100">Filtrer</button></div>
</form>
</div>
<?php if (!$expeditions): ?><div class="admin-card text-center text-muted">Aucune expédition trouvée.</div><?php endif; ?>
<?php foreach ($expeditions as $exp): ?>
<div class="admin-card mb-3">
    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
        <div><h5 class="mb-1"><?= e($exp['reference']) ?> · <?= e($exp['depart']) ?> → <?= e($exp['destination']) ?></h5><div class="small text-muted"><?= e(($exp['prenom'] ?? '').' '.($exp['nom'] ?? '')) ?> · <?= e($exp['email'] ?? '') ?><?= $exp['devis_id'] ? ' · devis #'.(int)$exp['devis_id'] : '' ?></div></div>
        <div><?= adminStatusBadge((string)$exp['statut']) ?></div>
    </div>
    <form method="post" action="traitement_expedition.php" class="row g-2 align-items-end">
        <?= csrfField() ?><input type="hidden" name="expedition_id" value="<?= (int)$exp['id'] ?>">
        <div class="col-md-2"><label class="form-label">Statut</label><select name="statut" class="form-select" required><?php foreach (['planifiee'=>'Planifiée','en_cours'=>'En cours','livree'=>'Livrée','annulee'=>'Annulée'] as $k=>$v): ?><option value="<?= e($k) ?>"<?= $exp['statut']===$k?' selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Véhicule</label><select name="vehicule_id" class="form-select"><option value="">—</option><?php foreach($vehicules as $v): ?><option value="<?= (int)$v['id'] ?>"<?= (int)$exp['vehicule_id']===(int)$v['id']?' selected':'' ?>><?= e($v['nom'].' · '.($v['immatriculation'] ?: 'sans immat.').' · '.$v['statut']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Chauffeur</label><select name="chauffeur_id" class="form-select"><option value="">—</option><?php foreach($chauffeurs as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$exp['chauffeur_id']===(int)$c['id']?' selected':'' ?>><?= e($c['prenom'].' '.$c['nom'].(!empty($c['disponible'])?' · libre':' · occupé')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Remorque</label><select name="remorque_id" class="form-select"><option value="">—</option><?php foreach($remorques as $r): ?><option value="<?= (int)$r['id'] ?>"<?= (int)$exp['remorque_id']===(int)$r['id']?' selected':'' ?>><?= e($r['nom'].' · '.$r['statut']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Départ</label><input type="datetime-local" name="date_depart" class="form-control" value="<?= e(dtLocal($exp['date_depart'])) ?>"></div>
        <div class="col-md-2"><label class="form-label">Livraison estimée</label><input type="datetime-local" name="date_livraison_estimee" class="form-control" value="<?= e(dtLocal($exp['date_livraison_estimee'])) ?>"></div>
        <div class="col-md-2"><label class="form-label">Livraison réelle</label><input type="datetime-local" name="date_livraison_reelle" class="form-control" value="<?= e(dtLocal($exp['date_livraison_reelle'])) ?>"></div>
        <div class="col-md-2"><button class="btn btn-logitix w-100">Mettre à jour</button></div>
    </form>
</div>
<?php endforeach; ?>
<?php adminPageEnd(); ?>
