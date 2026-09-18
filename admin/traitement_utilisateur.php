<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';

if (!isPost()) { redirect('admin/utilisateurs.php'); }
requireCsrf();

$clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$action = (string)($_POST['action'] ?? '');
if ($clientId === false || !in_array($action, ['activate','deactivate','reset_password'], true)) {
    setError('Action utilisateur invalide.');
    redirect('admin/utilisateurs.php');
}

try {
    $model = new AdminLogisticsModel();
    $actor = (int)$_SESSION['user_id'];
    $ip = clientIp();
    if ($action === 'reset_password') {
        if (!hasRecentPasswordAuthentication()) {
            setError('Pour cette action sensible, reconnectez-vous puis recommencez dans les 10 minutes.');
            redirect('admin/utilisateurs.php');
        }
        $temporary = $model->resetClientPassword((int)$clientId, $actor, $ip);
        rotateCsrfToken();
        setInfo('Mot de passe temporaire (affiché une seule fois) : ' . $temporary);
    } else {
        $model->setClientActive((int)$clientId, $action === 'activate', $actor, $ip);
        rotateCsrfToken();
        setSuccess($action === 'activate' ? 'Compte client activé.' : 'Compte client désactivé et sessions invalidées.');
    }
} catch (Throwable $e) {
    error_log('LOGITIX admin user action: '.$e->getMessage());
    setError(APP_ENV === 'development' ? 'Action impossible : '.$e->getMessage() : 'Action utilisateur impossible.');
}
redirect('admin/utilisateurs.php');
