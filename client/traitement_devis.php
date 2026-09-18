<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/ClientLogisticsModel.php';

if (!isPost()) {
    redirect('client/devis.php');
}
requireCsrf();

$depart = clean((string) ($_POST['depart'] ?? ''));
$destination = clean((string) ($_POST['destination'] ?? ''));
$typeMarchandise = clean((string) ($_POST['type_marchandise'] ?? ''));
$poids = trim((string) ($_POST['poids_estime'] ?? ''));
$dateSouhaitee = trim((string) ($_POST['date_souhaitee'] ?? ''));
$message = trim(strip_tags((string) ($_POST['message'] ?? '')));
$errors = [];

if ($depart === '' || $destination === '') {
    $errors[] = 'Le depart et la destination sont obligatoires.';
}
if (strlen($depart) > 150 || strlen($destination) > 150 || strlen($typeMarchandise) > 150) {
    $errors[] = 'Un des champs saisis est trop long.';
}
if ($poids !== '') {
    if (!is_numeric($poids) || (float) $poids < 0 || (float) $poids > 99999999.99) {
        $errors[] = 'Le poids estime est invalide.';
    } else {
        $poids = number_format((float) $poids, 2, '.', '');
    }
}
if ($dateSouhaitee !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $dateSouhaitee);
    $dateErrors = DateTime::getLastErrors();
    if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $dateSouhaitee) {
        $errors[] = 'La date souhaitee est invalide.';
    }
}
if (strlen($message) > 3000) {
    $errors[] = 'Le message est trop long.';
}

if ($errors) {
    setError(implode(' ', array_unique($errors)));
    redirect('client/devis.php?action=create');
}

try {
    $model = new ClientLogisticsModel();
    $model->createDevis((int) $_SESSION['user_id'], [
        'depart' => $depart,
        'destination' => $destination,
        'type_marchandise' => $typeMarchandise,
        'poids_estime' => $poids,
        'date_souhaitee' => $dateSouhaitee,
        'message' => $message,
    ]);
    rotateCsrfToken();
    setSuccess('Votre demande de devis a bien ete enregistree.');
    redirect('client/devis.php');
} catch (Throwable $e) {
    error_log('LOGITIX creation devis: ' . $e->getMessage());
    if (APP_ENV === 'development') {
        setError('Impossible d enregistrer le devis. Verifiez que le SQL v7.6 des fonctionnalites client a ete applique.');
    } else {
        setError('Impossible d enregistrer votre devis pour le moment.');
    }
    redirect('client/devis.php?action=create');
}
