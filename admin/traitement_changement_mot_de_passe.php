<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../includes/TOTP.php';

if (!isPost()) {
    redirect('admin/changer_mot_de_passe.php');
}
requireCsrf();

$forcedChange = passwordChangeRequired();
$preauthorized = passwordChangeRecentlyAuthorized();
$currentPassword = (string) ($_POST['mot_de_passe_actuel'] ?? '');
$totpCode = (string) ($_POST['code_totp'] ?? '');
$newPassword = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
$confirmation = (string) ($_POST['confirmation'] ?? '');
$errors = [];

if ($newPassword === '' || $confirmation === '') {
    $errors[] = 'Renseignez et confirmez le nouveau mot de passe.';
}
if (strlen($currentPassword) > 1024 || strlen($totpCode) > 32 || strlen($newPassword) > 200 || strlen($confirmation) > 200) {
    $errors[] = 'Une valeur saisie est trop longue.';
}
if (!isStrongPassword($newPassword)) {
    $errors[] = 'Le nouveau mot de passe doit contenir au moins 12 caracteres, une majuscule, une minuscule, un chiffre et un caractere special.';
}
if (!hash_equals($newPassword, $confirmation)) {
    $errors[] = 'Les deux mots de passe ne correspondent pas.';
}

try {
    $userModel = new UserModel('admin');
    $user = $userModel->findById((int) $_SESSION['user_id']);
    if (!$user || $user['role'] !== ROLE_ADMIN) {
        logout();
        redirect('admin/connexion.php');
    }

    $ip = clientIp();
    if (!$forcedChange && !$preauthorized) {
        if ($userModel->isLoginRateLimited((string) $user['email'], $ip, 'password')) {
            setError('Trop de tentatives. Reessayez dans 15 minutes.');
            redirect('admin/changer_mot_de_passe.php');
        }
        if ($currentPassword === '' || !password_verify($currentPassword, (string) $user['mot_de_passe'])) {
            $userModel->recordLoginAttempt((string) $user['email'], $ip, false, 'password');
            $errors[] = 'Le mot de passe actuel est incorrect.';
        } else {
            $userModel->recordLoginAttempt((string) $user['email'], $ip, true, 'password');
        }

        if (!$errors && !empty($user['totp_enabled'])) {
            if ($userModel->isLoginRateLimited((string) $user['email'], $ip, 'totp')) {
                setError('Trop de tentatives 2FA. Reessayez dans 10 minutes.');
                redirect('admin/changer_mot_de_passe.php');
            }
            $counter = TOTP::matchingCounter((string) $user['totp_secret'], $totpCode);
            if ($counter === null || !$userModel->acceptTotpCounter((int) $user['id'], $counter)) {
                $userModel->recordLoginAttempt((string) $user['email'], $ip, false, 'totp');
                $errors[] = 'Le code 2FA est incorrect ou deja utilise.';
            } else {
                $userModel->recordLoginAttempt((string) $user['email'], $ip, true, 'totp');
            }
        }
    }

    if (password_verify($newPassword, (string) $user['mot_de_passe'])) {
        $errors[] = 'Le nouveau mot de passe doit etre different du mot de passe actuel.';
    }

    if ($errors) {
        setError(implode(' ', $errors));
        redirect('admin/changer_mot_de_passe.php');
    }

    $newAuthVersion = $userModel->updatePassword((int) $user['id'], $newPassword);
    session_regenerate_id(true);
    $_SESSION['auth_version'] = $newAuthVersion;
    $_SESSION['password_must_change'] = false;
    $_SESSION['password_authenticated_at'] = time();
    $_SESSION['session_started_at'] = time();
    $_SESSION['last_activity'] = time();
    clearPasswordChangeAuthorization();
    rotateCsrfToken();

    if (ADMIN_REQUIRE_2FA && empty($user['totp_enabled'])) {
        $_SESSION['mfa_enrollment_required'] = true;
        setSuccess('Mot de passe modifie. Configurez maintenant la double authentification.');
        redirect('admin/2fa_setup.php');
    }

    setSuccess('Votre mot de passe a ete modifie avec succes. Les autres sessions ont ete invalidees.');
    redirect('admin/dashboard.php');
} catch (Throwable $e) {
    error_log('Erreur changement mot de passe admin : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('admin/changer_mot_de_passe.php');
}
