<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('client/changer_mot_de_passe.php');
}
requireCsrf();

$forcedChange = passwordChangeRequired();
$preauthorized = passwordChangeRecentlyAuthorized();
$currentPassword = (string) ($_POST['mot_de_passe_actuel'] ?? '');
$newPassword = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
$confirmation = (string) ($_POST['confirmation'] ?? '');
$errors = [];

if ($newPassword === '' || $confirmation === '') {
    $errors[] = 'Renseignez et confirmez le nouveau mot de passe.';
}
if (strlen($currentPassword) > 1024 || strlen($newPassword) > 200 || strlen($confirmation) > 200) {
    $errors[] = 'Mot de passe trop long.';
}
if (!isStrongPassword($newPassword)) {
    $errors[] = 'Le nouveau mot de passe doit contenir au moins 12 caracteres, une majuscule, une minuscule, un chiffre et un caractere special.';
}
if (!hash_equals($newPassword, $confirmation)) {
    $errors[] = 'Les deux mots de passe ne correspondent pas.';
}

try {
    $userModel = new UserModel('client');
    $user = $userModel->findById((int) $_SESSION['user_id']);
    if (!$user || $user['role'] !== ROLE_CLIENT) {
        logout();
        redirect('se_connecter.php');
    }

    $ip = clientIp();
    if (!$forcedChange && !$preauthorized) {
        if ($userModel->isLoginRateLimited((string) $user['email'], $ip, 'password')) {
            setError('Trop de tentatives. Reessayez dans 15 minutes.');
            redirect('client/changer_mot_de_passe.php');
        }
        if ($currentPassword === '' || !password_verify($currentPassword, (string) $user['mot_de_passe'])) {
            $userModel->recordLoginAttempt((string) $user['email'], $ip, false, 'password');
            $errors[] = 'Le mot de passe actuel est incorrect.';
        } else {
            $userModel->recordLoginAttempt((string) $user['email'], $ip, true, 'password');
        }
    }

    if (password_verify($newPassword, (string) $user['mot_de_passe'])) {
        $errors[] = 'Le nouveau mot de passe doit etre different du mot de passe actuel.';
    }

    if ($errors) {
        setError(implode(' ', $errors));
        redirect('client/changer_mot_de_passe.php');
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

    setSuccess('Votre mot de passe a ete modifie avec succes. Les autres sessions ont ete invalidees.');
    redirect('client/dashboard.php');
} catch (Throwable $e) {
    error_log('Erreur changement mot de passe client : ' . $e->getMessage());
    setError('Une erreur technique est survenue.');
    redirect('client/changer_mot_de_passe.php');
}
