<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';

if (!isPost()) { redirect('admin/devis.php'); }
requireCsrf();
$id = filter_var($_POST['devis_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$action = (string)($_POST['action'] ?? '');
if ($id === false || !in_array($action, ['update','convert'], true)) { setError('Action devis invalide.'); redirect('admin/devis.php'); }

try {
    $model = new AdminLogisticsModel();
    $actor = (int)$_SESSION['user_id'];
    $ip = clientIp();
    if ($action === 'convert') {
        $reference = $model->convertDevisToExpedition((int)$id, $actor, $ip);
        rotateCsrfToken();
        setSuccess('Devis converti en expédition : '.$reference.'.');
        redirect('admin/expeditions.php?q='.rawurlencode($reference));
    }

    $statut = (string)($_POST['statut'] ?? '');
    $montantRaw = trim((string)($_POST['montant'] ?? ''));
    $devise = strtoupper(trim((string)($_POST['devise'] ?? 'XOF')));
    $reponse = trim(strip_tags((string)($_POST['reponse'] ?? '')));
    $errors=[];
    if (!in_array($statut, ['en_attente','valide','refuse'], true)) { $errors[]='Statut invalide.'; }
    $montant = null;
    if ($montantRaw !== '') {
        if (!is_numeric($montantRaw) || (float)$montantRaw < 0 || (float)$montantRaw > 999999999999.99) { $errors[]='Montant invalide.'; }
        else { $montant = number_format((float)$montantRaw, 2, '.', ''); }
    }
    if (!preg_match('/^[A-Z]{3}$/', $devise)) { $errors[]='La devise doit contenir 3 lettres (ex. XOF).'; }
    if (strlen($reponse) > 3000) { $errors[]='Réponse trop longue.'; }
    if ($statut === 'valide' && $montant === null) { $errors[]='Un montant est requis pour valider le devis.'; }
    if ($errors) { setError(implode(' ', $errors)); redirect('admin/devis.php'); }

    $model->updateDevis((int)$id, $statut, $montant, $devise, $reponse, $actor, $ip);
    rotateCsrfToken();
    setSuccess('Devis mis à jour.');
} catch (Throwable $e) {
    error_log('LOGITIX admin devis action: '.$e->getMessage());
    setError(APP_ENV === 'development' ? 'Action devis impossible : '.$e->getMessage() : 'Action devis impossible.');
}
redirect('admin/devis.php');
