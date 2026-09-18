<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/TOTP.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('admin/connexion.php');
}
requireCsrf();

if (empty($_SESSION['pending_2fa_user_id']) || empty($_SESSION['pending_2fa_time'])) {
    redirect('admin/connexion.php');
}

if (time() - (int) $_SESSION['pending_2fa_time'] > 300) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
    rotateCsrfToken();
    setError('Session de connexion expiree. Veuillez vous reconnecter.');
    redirect('admin/connexion.php');
}

$code = (string) ($_POST['code'] ?? '');
if (strlen($code) > 32) {
    setError('Code incorrect.');
    redirect('admin/verification_2fa.php');
}
$pendingId = (int) $_SESSION['pending_2fa_user_id'];

try {
    $userModel = new UserModel('admin');
    $ip = clientIp();
    $user = $userModel->findById($pendingId);

    if (!$user || $user['role'] !== ROLE_ADMIN || empty($user['totp_enabled']) || empty($user['totp_secret'])) {
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_time']);
        rotateCsrfToken();
        setError('Session invalide. Veuillez vous reconnecter.');
        redirect('admin/connexion.php');
    }

    if ($userModel->isLoginRateLimited($user['email'], $ip, 'totp')) {
        setError('Trop de tentatives de verification. Reessayez dans 10 minutes.');
        redirect('admin/verification_2fa.php');
    }

    $counter = TOTP::matchingCounter((string) $user['totp_secret'], $code);
    if ($counter === null || !$userModel->acceptTotpCounter((int) $user['id'], $counter)) {
        $userModel->recordLoginAttempt($user['email'], $ip, false, 'totp');
        setError('Code incorrect ou deja utilise.');
        redirect('admin/verification_2fa.php');
    }

    $userModel->recordLoginAttempt($user['email'], $ip, true, 'totp');
    $postAuthTarget = (string) ($_SESSION['pending_2fa_target'] ?? 'dashboard');
    establishAuthenticatedSession($user);

    if (!empty($user['password_must_change'])) {
        setInfo('Votre mot de passe temporaire doit etre remplace avant de continuer.');
        redirect('admin/changer_mot_de_passe.php');
    }

    if ($postAuthTarget === 'change_password') {
        authorizePasswordChange();
        setInfo('Identite et double authentification verifiees. Choisissez votre nouveau mot de passe.');
        redirect('admin/changer_mot_de_passe.php');
    }

    setSuccess('Bienvenue sur le tableau de bord administrateur !');
    redirect('admin/dashboard.php');
} catch (Throwable $e) {
    error_log('Erreur verification 2FA : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('admin/verification_2fa.php');
}
