<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('admin/dashboard.php');
}
requireCsrf();

$userModel = new UserModel('admin');
$user = $userModel->findById((int) $_SESSION['user_id']);

if (!$user) {
    logout();
    redirect('admin/connexion.php');
}

$nom = clean((string) ($_POST['nom'] ?? ''));
$prenom = clean((string) ($_POST['prenom'] ?? ''));
$email = normalizeEmail((string) ($_POST['email'] ?? ''));
$telephone = clean((string) ($_POST['telephone'] ?? ''));
$currentPassword = (string) ($_POST['ancien_mot_de_passe'] ?? '');
$emailChanged = !hash_equals((string) $user['email'], $email);
$errors = [];

if ($nom === '') $errors[] = 'Le nom est obligatoire.';
if ($prenom === '') $errors[] = 'Le prenom est obligatoire.';
if ($email === '') $errors[] = 'L\'email est obligatoire.';
if ($telephone === '') $errors[] = 'Le telephone est obligatoire.';
if (!isValidEmail($email)) $errors[] = 'Format d\'email invalide.';
if (strlen($nom) > 100 || strlen($prenom) > 100) $errors[] = 'Nom ou prenom trop long.';
if (strlen($email) > 150) $errors[] = 'Adresse email trop longue.';
if (strlen($telephone) > 30) $errors[] = 'Numero de telephone trop long.';
if (strlen($currentPassword) > 1024) $errors[] = 'Mot de passe trop long.';
if (!preg_match('/^[0-9+ -]{8,30}$/', $telephone)) $errors[] = 'Numero de telephone invalide.';

if ($emailChanged && $userModel->emailExists($email, (int) $user['id'])) {
    $errors[] = 'Cette adresse email ne peut pas etre utilisee.';
}

if ($emailChanged && ($currentPassword === '' || !password_verify($currentPassword, (string) $user['mot_de_passe']))) {
    $errors[] = 'Le mot de passe actuel est requis pour modifier l\'adresse email.';
}

if ($errors) {
    setError(implode(' ', $errors));
    redirect('admin/profil.php');
}

$pdo = Database::getInstance('admin')->getConnection();
try {
    $pdo->beginTransaction();

    $userModel->updateProfile((int) $user['id'], [
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone,
    ]);

    $newAuthVersion = (int) $user['auth_version'];
    if ($emailChanged) {
        $newAuthVersion = $userModel->bumpAuthVersion((int) $user['id']);
    }

    $pdo->commit();

    $_SESSION['user_nom'] = $nom;
    $_SESSION['user_prenom'] = $prenom;
    $_SESSION['user_email'] = $email;
    $_SESSION['auth_version'] = $newAuthVersion;
    if ($emailChanged) {
        $_SESSION['password_authenticated_at'] = time();
        session_regenerate_id(true);
    }
    rotateCsrfToken();

    setSuccess('Profil mis a jour avec succes !');
    redirect('admin/profil.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erreur mise a jour profil : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('admin/profil.php');
}
