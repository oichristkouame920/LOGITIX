<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/AdminLogisticsModel.php';

if (!isPost()) { redirect('admin/expeditions.php'); }
requireCsrf();
$id = filter_var($_POST['expedition_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$statut = (string)($_POST['statut'] ?? '');
if ($id === false || !in_array($statut, ['planifiee','en_cours','livree','annulee'], true)) { setError('Expédition ou statut invalide.'); redirect('admin/expeditions.php'); }

$parseId = static function($value): ?int {
    if ($value === '' || $value === null) { return null; }
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($v === false) { throw new InvalidArgumentException('Affectation invalide.'); }
    return (int)$v;
};
$parseDate = static function(string $value): ?string {
    $value = trim($value);
    if ($value === '') { return null; }
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    $errors = DateTime::getLastErrors();
    if (!$d || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) { throw new InvalidArgumentException('Date invalide.'); }
    return $d->format('Y-m-d H:i:s');
};

try {
    $depart = $parseDate((string)($_POST['date_depart'] ?? ''));
    $estimee = $parseDate((string)($_POST['date_livraison_estimee'] ?? ''));
    $reelle = $parseDate((string)($_POST['date_livraison_reelle'] ?? ''));

    // Passage en cours : si aucune date n'a ete saisie, le depart devient
    // l'instant de validation par l'administrateur. Cela evite un etat actif
    // sans date de depart, interdit egalement par la base v7.7.2.
    if ($statut === 'en_cours' && !$depart) { $depart = date('Y-m-d H:i:s'); }
    if ($statut === 'livree' && !$reelle) { $reelle = date('Y-m-d H:i:s'); }
    if ($statut !== 'livree') { $reelle = null; }

    if ($depart && $estimee && $estimee < $depart) { throw new InvalidArgumentException('La livraison estimée ne peut pas précéder le départ.'); }
    if ($depart && $reelle && $reelle < $depart) { throw new InvalidArgumentException('La livraison réelle ne peut pas précéder le départ.'); }

    $model = new AdminLogisticsModel();
    $model->updateExpedition((int)$id, [
        'statut'=>$statut,
        'vehicule_id'=>$parseId($_POST['vehicule_id'] ?? ''),
        'chauffeur_id'=>$parseId($_POST['chauffeur_id'] ?? ''),
        'remorque_id'=>$parseId($_POST['remorque_id'] ?? ''),
        'date_depart'=>$depart,
        'date_livraison_estimee'=>$estimee,
        'date_livraison_reelle'=>$reelle,
    ], (int)$_SESSION['user_id'], clientIp());
    rotateCsrfToken();
    setSuccess('Expédition mise à jour.');
} catch (Throwable $e) {
    error_log('LOGITIX admin expedition update: '.$e->getMessage());
    setError(APP_ENV === 'development' ? 'Mise à jour impossible : '.$e->getMessage() : 'Mise à jour impossible.');
}
redirect('admin/expeditions.php');
