<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

$scriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$isAdminPage = strpos($scriptPath, '/admin/') !== false;
$loginTarget = $isAdminPage ? 'admin/connexion.php' : 'se_connecter.php';

if (!isLoggedIn()) {
    setError($isAdminPage
        ? 'Veuillez vous connecter en tant qu\'administrateur.'
        : 'Veuillez vous connecter pour acceder a cette page.');
    redirect($loginTarget);
}

$now = time();
$lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
$startedAt = (int) ($_SESSION['session_started_at'] ?? 0);
$timeout = $isAdminPage ? ADMIN_SESSION_TIMEOUT : SESSION_TIMEOUT;

if ($lastActivity <= 0 || ($now - $lastActivity) > $timeout
    || $startedAt <= 0 || ($now - $startedAt) > SESSION_ABSOLUTE_TIMEOUT) {
    logout();
    // La session est detruite : un nouveau message flash ne survivrait pas.
    redirect($loginTarget);
}

if ($isAdminPage && !isAdmin()) {
    setError('Acces refuse. Vous n\'avez pas les droits administrateur.');
    redirect('client/dashboard.php');
}

$userModel = new UserModel($isAdminPage ? 'admin' : 'client');
$databaseUser = $userModel->findById((int) $_SESSION['user_id']);

if (!$databaseUser
    || !(bool) $databaseUser['actif']
    || $databaseUser['role'] !== $_SESSION['user_role']
    || (int) $databaseUser['auth_version'] !== (int) $_SESSION['auth_version']) {
    logout();
    redirect($loginTarget);
}

$_SESSION['password_must_change'] = !empty($databaseUser['password_must_change']);

// Un mot de passe temporaire ne donne jamais acces au reste de l'application.
// Tant qu'il n'a pas ete remplace, seules la page de changement et la
// deconnexion restent accessibles.
if (!empty($databaseUser['password_must_change'])) {
    $allowedPasswordChange = $isAdminPage
        ? ['/admin/changer_mot_de_passe.php', '/admin/traitement_changement_mot_de_passe.php', '/admin/deconnexion.php']
        : ['/client/changer_mot_de_passe.php', '/client/traitement_changement_mot_de_passe.php', '/deconnexion.php'];

    $allowed = false;
    foreach ($allowedPasswordChange as $suffix) {
        if (logitixEndsWith($scriptPath, $suffix)) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        redirect($isAdminPage ? 'admin/changer_mot_de_passe.php' : 'client/changer_mot_de_passe.php');
    }
}

if ($isAdminPage && empty($databaseUser['password_must_change']) && ADMIN_REQUIRE_2FA && empty($databaseUser['totp_enabled'])) {
    $allowedWithout2fa = [
        '/admin/2fa_setup.php',
        '/admin/traitement_2fa.php',
        '/admin/changer_mot_de_passe.php',
        '/admin/traitement_changement_mot_de_passe.php',
        '/admin/deconnexion.php',
    ];
    $allowed = false;
    foreach ($allowedWithout2fa as $suffix) {
        if (logitixEndsWith($scriptPath, $suffix)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        $_SESSION['mfa_enrollment_required'] = true;
        redirect('admin/2fa_setup.php');
    }
}

$_SESSION['last_activity'] = $now;
