<?php
/**
 * Fonctions utilitaires de LOGITIX.
 */

function redirect(string $path = ''): void {
    $path = '/' . ltrim($path, '/');
    header('Location: ' . APP_URL . $path, true, 303);
    exit;
}

function redirectBack(): void {
    redirect('index.html');
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Nettoyage de presentation uniquement. Ne remplace jamais une requete preparee
 * ni un echappement adapte au contexte de sortie.
 */
function clean(string $str): string {
    return trim(strip_tags($str));
}

function normalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isStrongPassword(string $password): bool {
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

function clientIp(): string {
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }

    if (!in_array($remote, TRUSTED_PROXY_IPS, true)) {
        return $remote;
    }

    $chain = [];
    foreach (explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $chain[] = $candidate;
        }
    }
    $chain[] = $remote;

    // Retire les proxies approuves depuis la droite et garde le dernier saut
    // non approuve : un X-Forwarded-For fourni directement par un client est
    // ignore si REMOTE_ADDR n'est pas dans la liste de confiance.
    while ($chain && in_array(end($chain), TRUSTED_PROXY_IPS, true)) {
        array_pop($chain);
    }

    return $chain ? (string) end($chain) : $remote;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['auth_version']);
}

function isClient(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_CLIENT;
}

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_ADMIN;
}

function passwordChangeRequired(): bool {
    return isLoggedIn() && !empty($_SESSION['password_must_change']);
}

function authorizePasswordChange(): void {
    $_SESSION['password_change_authorized_at'] = time();
}

function passwordChangeRecentlyAuthorized(): bool {
    $timestamp = (int) ($_SESSION['password_change_authorized_at'] ?? 0);
    return $timestamp > 0 && (time() - $timestamp) <= SENSITIVE_REAUTH_WINDOW;
}

function clearPasswordChangeAuthorization(): void {
    unset($_SESSION['password_change_authorized_at']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nom' => $_SESSION['user_nom'] ?? null,
        'prenom' => $_SESSION['user_prenom'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
        'status' => $_SESSION['user_status'] ?? null,
    ];
}

function rotateCsrfToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function establishAuthenticatedSession(array $user): void {
    session_regenerate_id(true);

    unset(
        $_SESSION['pending_2fa_user_id'],
        $_SESSION['pending_2fa_time'],
        $_SESSION['mfa_enrollment_required'],
        $_SESSION['pending_2fa_target'],
        $_SESSION['password_change_authorized_at']
    );

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_nom'] = (string) $user['nom'];
    $_SESSION['user_prenom'] = (string) $user['prenom'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = (string) $user['role'];
    $_SESSION['user_status'] = $user['status'] ?? null;
    $_SESSION['auth_version'] = (int) ($user['auth_version'] ?? 1);
    $_SESSION['password_must_change'] = !empty($user['password_must_change']);
    $_SESSION['session_started_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['password_authenticated_at'] = time();
    rotateCsrfToken();
}

function beginPendingTwoFactor(array $user, string $target = 'dashboard'): void {
    if (!in_array($target, ['dashboard', 'change_password'], true)) {
        $target = 'dashboard';
    }
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
    $_SESSION['pending_2fa_time'] = time();
    $_SESSION['pending_2fa_target'] = $target;
    $_SESSION['password_authenticated_at'] = time();
    rotateCsrfToken();
}

function hasRecentPasswordAuthentication(): bool {
    $timestamp = (int) ($_SESSION['password_authenticated_at'] ?? 0);
    return $timestamp > 0 && (time() - $timestamp) <= SENSITIVE_REAUTH_WINDOW;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        rotateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requete non valide.');
    }
}

function setSuccess(string $message): void {
    $_SESSION['flash_success'] = $message;
}

function setError(string $message): void {
    $_SESSION['flash_error'] = $message;
}

function setInfo(string $message): void {
    $_SESSION['flash_info'] = $message;
}

function getFlash(string $type = 'success'): ?string {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $message = (string) $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

function displayFlash(): void {
    foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $type => $class) {
        $message = getFlash($type);
        if ($message) {
            echo '<div class="alert alert-' . $class . ' alert-dismissible fade show text-center" role="alert">';
            echo e($message);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>';
            echo '</div>';
        }
    }
}

function formatDate(string $date, string $format = 'd/m/Y H:i'): string {
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'Date invalide';
}

function generateTrackingNumber(): string {
    return 'LOG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
}

function isPost(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function param(string $key, $default = null) {
    if (isset($_POST[$key]) && is_string($_POST[$key])) {
        return clean($_POST[$key]);
    }
    if (isset($_GET[$key]) && is_string($_GET[$key])) {
        return clean($_GET[$key]);
    }
    return $default;
}
