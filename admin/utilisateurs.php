<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$q = clean((string)($_GET['q'] ?? ''));
$state = (string)($_GET['state'] ?? '');
if (strlen($q) > 150) { $q = substr($q, 0, 150); }
if (!in_array($state, ['', 'active', 'inactive'], true)) { $state = ''; }

$model = new AdminLogisticsModel();
try { $clients = $model->listClients($q, $state); }
catch (Throwable $e) { error_log('LOGITIX admin clients: '.$e->getMessage()); $clients=[]; setError('Impossible de charger les clients.'); }

adminPageStart('Utilisateurs clients', 'utilisateurs');
?>
<div class="admin-card mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">Rechercher</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Nom, email, téléphone, entreprise"></div>
        <div class="col-md-3"><label class="form-label">État</label><select class="form-select" name="state"><option value="">Tous</option><option value="active"<?= $state==='active'?' selected':'' ?>>Actifs</option><option value="inactive"<?= $state==='inactive'?' selected':'' ?>>Désactivés</option></select></div>
        <div class="col-md-3"><button class="btn btn-logitix w-100">Filtrer</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Client</th><th>Contact</th><th>Type</th><th>Inscription</th><th>État</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$clients): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun client trouvé.</td></tr><?php endif; ?>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><strong><?= e($client['prenom'].' '.$client['nom']) ?></strong><?php if (!empty($client['raison_sociale'])): ?><div class="small text-muted"><?= e($client['raison_sociale']) ?></div><?php endif; ?></td>
                    <td><div class="break-word"><?= e($client['email']) ?></div><small class="text-muted"><?= e($client['telephone']) ?></small></td>
                    <td><?= e($client['status']) ?></td>
                    <td><?= e(formatDate($client['created_at'], 'd/m/Y')) ?></td>
                    <td><?= !empty($client['actif']) ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Désactivé</span>' ?><?php if (!empty($client['password_must_change'])): ?><div><span class="badge bg-warning text-dark mt-1">MDP temporaire</span></div><?php endif; ?></td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="post" action="traitement_utilisateur.php" onsubmit="return confirm('Confirmer ce changement d’état ?');">
                                <?= csrfField() ?><input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>"><input type="hidden" name="action" value="<?= !empty($client['actif']) ? 'deactivate' : 'activate' ?>">
                                <button class="btn btn-sm <?= !empty($client['actif']) ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= !empty($client['actif']) ? 'Désactiver' : 'Activer' ?></button>
                            </form>
                            <form method="post" action="traitement_utilisateur.php" onsubmit="return confirm('Générer un nouveau mot de passe temporaire ? Les anciennes sessions seront invalidées.');">
                                <?= csrfField() ?><input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>"><input type="hidden" name="action" value="reset_password">
                                <button class="btn btn-sm btn-outline-dark">Réinitialiser MDP</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php adminPageEnd(); ?>
