<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('se_connecter.php');
}
requireCsrf();

$email = normalizeEmail((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$intent = (string) ($_POST['intent'] ?? '');
if (!in_array($intent, ['', 'change_password'], true)) {
    $intent = '';
}

if ($email === '' || $password === '' || strlen($email) > 150 || strlen($password) > 1024) {
    setError('Veuillez remplir tous les champs.');
    redirect('se_connecter.php');
}

if (!isValidEmail($email)) {
    setError('Format d\'email invalide.');
    redirect('se_connecter.php');
}

try {
    $userModel = new UserModel('client');
    $ip = clientIp();

    if ($userModel->isLoginRateLimited($email, $ip, 'password')) {
        setError('Trop de tentatives. Reessayez dans 15 minutes.');
        redirect('se_connecter.php');
    }

    $user = $userModel->authenticate($email, $password);
    if (!$user || $user['role'] !== ROLE_CLIENT) {
        $userModel->recordLoginAttempt($email, $ip, false, 'password');
        setError('Identifiants incorrects.');
        redirect('se_connecter.php');
    }

    $userModel->recordLoginAttempt($email, $ip, true, 'password');
    establishAuthenticatedSession($user);

    if (!empty($user['password_must_change'])) {
        setInfo('Votre mot de passe temporaire doit etre remplace avant de continuer.');
        redirect('client/changer_mot_de_passe.php');
    }

    if ($intent === 'change_password') {
        authorizePasswordChange();
        setInfo('Identite verifiee. Choisissez maintenant votre nouveau mot de passe.');
        redirect('client/changer_mot_de_passe.php');
    }

    setSuccess('Bienvenue sur votre espace client !');
    redirect('client/dashboard.php');
} catch (Throwable $e) {
    error_log('Erreur connexion client : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('se_connecter.php');
}
