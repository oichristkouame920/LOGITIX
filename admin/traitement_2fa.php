<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/TOTP.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('admin/2fa_setup.php');
}
requireCsrf();

$userModel = new UserModel('admin');
$user = $userModel->findById((int) $_SESSION['user_id']);
$action = (string) ($_POST['action'] ?? '');
$code = (string) ($_POST['code'] ?? '');
$ip = clientIp();

if (strlen($code) > 32) {
    setError('Code incorrect.');
    redirect('admin/2fa_setup.php');
}

if (!$user || $user['role'] !== ROLE_ADMIN) {
    logout();
    redirect('admin/connexion.php');
}

if ($userModel->isLoginRateLimited($user['email'], $ip, 'totp')) {
    setError('Trop de tentatives de verification. Reessayez dans 10 minutes.');
    redirect('admin/2fa_setup.php');
}

if ($action === 'enable') {
    if (!hasRecentPasswordAuthentication()) {
        logout();
        redirect('admin/connexion.php');
    }
    if (empty($user['totp_secret'])) {
        setError('Aucune configuration en attente. Recommencez.');
        redirect('admin/2fa_setup.php');
    }

    $counter = TOTP::matchingCounter((string) $user['totp_secret'], $code);
    if ($counter === null) {
        $userModel->recordLoginAttempt($user['email'], $ip, false, 'totp');
        setError('Code incorrect. Verifiez l\'heure de votre telephone et reessayez.');
        redirect('admin/2fa_setup.php');
    }

    $newVersion = $userModel->enableTotp((int) $user['id'], $counter);
    $userModel->recordLoginAttempt($user['email'], $ip, true, 'totp');
    $_SESSION['auth_version'] = $newVersion;
    unset($_SESSION['mfa_enrollment_required']);
    rotateCsrfToken();
    setSuccess('Double authentification activee avec succes.');
    redirect('admin/2fa_setup.php');
}

if ($action === 'disable') {
    if (!hasRecentPasswordAuthentication()) {
        logout();
        redirect('admin/connexion.php');
    }

    if (ADMIN_REQUIRE_2FA) {
        setError('La double authentification est obligatoire pour les comptes administrateur.');
        redirect('admin/2fa_setup.php');
    }

    if (empty($user['totp_enabled']) || empty($user['totp_secret'])) {
        setError('Code incorrect.');
        redirect('admin/2fa_setup.php');
    }

    $counter = TOTP::matchingCounter((string) $user['totp_secret'], $code);
    if ($counter === null || !$userModel->acceptTotpCounter((int) $user['id'], $counter)) {
        $userModel->recordLoginAttempt($user['email'], $ip, false, 'totp');
        setError('Code incorrect ou deja utilise.');
        redirect('admin/2fa_setup.php');
    }

    $newVersion = $userModel->disableTotp((int) $user['id']);
    $userModel->recordLoginAttempt($user['email'], $ip, true, 'totp');
    $_SESSION['auth_version'] = $newVersion;
    rotateCsrfToken();
    setSuccess('Double authentification desactivee.');
    redirect('admin/2fa_setup.php');
}

redirect('admin/2fa_setup.php');
