<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';

if (!isPost()) {
    redirect('client/expeditions.php');
}
requireCsrf();

$depart = clean((string) ($_POST['depart'] ?? ''));
$destination = clean((string) ($_POST['destination'] ?? ''));
$dateDepartRaw = trim((string) ($_POST['date_depart'] ?? ''));
$errors = [];

if ($depart === '' || $destination === '') {
    $errors[] = 'Le depart et la destination sont obligatoires.';
}
if (strlen($depart) > 150 || strlen($destination) > 150) {
    $errors[] = 'Le depart ou la destination est trop long.';
}
if ($dateDepartRaw !== '') {
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $dateDepartRaw);
    $dateErrors = DateTime::getLastErrors();
    if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        $errors[] = 'La date de depart est invalide.';
    } else {
        $dateDepartRaw = $date->format('Y-m-d H:i:s');
    }
}

if ($errors) {
    setError(implode(' ', array_unique($errors)));
    redirect('client/expeditions.php?action=create');
}

try {
    $model = new ClientLogisticsModel();
    $reference = $model->createExpedition((int) $_SESSION['user_id'], [
        'depart' => $depart,
        'destination' => $destination,
        'date_depart' => $dateDepartRaw,
    ]);
    rotateCsrfToken();
    setSuccess('Expedition creee. Votre reference de suivi est ' . $reference . '.');
    redirect('client/expeditions.php');
} catch (Throwable $e) {
    error_log('LOGITIX creation expedition: ' . $e->getMessage());
    if (APP_ENV === 'development') {
        setError('Impossible de creer l expedition. Verifiez que le SQL v7.6 des fonctionnalites client a ete applique.');
    } else {
        setError('Impossible de creer l expedition pour le moment.');
    }
    redirect('client/expeditions.php?action=create');
}
