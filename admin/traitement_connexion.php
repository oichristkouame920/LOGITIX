<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('admin/connexion.php');
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
    redirect('admin/connexion.php');
}

if (!isValidEmail($email)) {
    setError('Format d\'email invalide.');
    redirect('admin/connexion.php');
}

try {
    $userModel = new UserModel('admin');
    $ip = clientIp();

    if ($userModel->isLoginRateLimited($email, $ip, 'password')) {
        setError('Trop de tentatives. Reessayez dans 15 minutes.');
        redirect('admin/connexion.php');
    }

    $user = $userModel->authenticate($email, $password);
    if (!$user || $user['role'] !== ROLE_ADMIN) {
        $userModel->recordLoginAttempt($email, $ip, false, 'password');
        setError('Identifiants incorrects.');
        redirect('admin/connexion.php');
    }

    $userModel->recordLoginAttempt($email, $ip, true, 'password');

    if (!empty($user['totp_enabled'])) {
        beginPendingTwoFactor($user, $intent === 'change_password' ? 'change_password' : 'dashboard');
        redirect('admin/verification_2fa.php');
    }

    establishAuthenticatedSession($user);
    if (!empty($user['password_must_change'])) {
        setInfo('Votre mot de passe temporaire doit etre remplace avant de continuer.');
        redirect('admin/changer_mot_de_passe.php');
    }
    if ($intent === 'change_password') {
        authorizePasswordChange();
        setInfo('Identite verifiee. Choisissez maintenant votre nouveau mot de passe.');
        redirect('admin/changer_mot_de_passe.php');
    }
    if (ADMIN_REQUIRE_2FA) {
        $_SESSION['mfa_enrollment_required'] = true;
        redirect('admin/2fa_setup.php');
    }

    setSuccess('Bienvenue sur le tableau de bord administrateur !');
    redirect('admin/dashboard.php');
} catch (Throwable $e) {
    error_log('Erreur connexion admin : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('admin/connexion.php');
}
