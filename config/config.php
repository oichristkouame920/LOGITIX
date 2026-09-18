<?php
/**
 * Configuration globale LOGITIX.
 *
 * Les secrets ne doivent jamais etre commits. Pour le developpement local,
 * le fichier .env.local (ignore par Git et bloque par .htaccess) peut etre
 * utilise. Les variables d'environnement du serveur restent prioritaires.
 */


function logitixStartsWith(string $haystack, string $needle): bool {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

function logitixEndsWith(string $haystack, string $needle): bool {
    if ($needle === '') {
        return true;
    }
    $length = strlen($needle);
    return strlen($haystack) >= $length && substr_compare($haystack, $needle, -$length) === 0;
}

function logitixContains(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function logitixLoadEnvFile(string $path): void {
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || logitixStartsWith($line, '#') || !logitixContains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if ((logitixStartsWith($value, '"') && logitixEndsWith($value, '"'))
            || (logitixStartsWith($value, "'") && logitixEndsWith($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}


function logitixDetectAppBasePath(): string {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '' || PHP_SAPI === 'cli') {
        return '';
    }

    // Les traitements se trouvent actuellement dans /client ou /admin.
    // On remonte donc a la racine web reelle du projet, quel que soit le
    // nom du dossier local (Logitix, LOGITIX_secure_reaudit_v5, etc.).
    foreach (['/client/', '/admin/'] as $marker) {
        $position = strpos($scriptName, $marker);
        if ($position !== false) {
            return rtrim(substr($scriptName, 0, $position), '/');
        }
    }

    $directory = str_replace('\\', '/', dirname($scriptName));
    if ($directory === '/' || $directory === '.' || $directory === '\\') {
        return '';
    }

    return rtrim($directory, '/');
}

function logitixResolveAppUrl(string $configuredUrl, string $environment): string {
    $configuredUrl = rtrim(trim($configuredUrl), '/');
    $detectedPath = logitixDetectAppBasePath();

    if ($configuredUrl === '' || strtolower($configuredUrl) === 'auto') {
        return $detectedPath;
    }

    // En developpement, une ancienne valeur comme /Logitix ne doit pas
    // casser les sessions si le dossier local a un autre nom. Dans ce cas,
    // on utilise automatiquement le chemin reel de la requete courante.
    if ($environment === 'development' && PHP_SAPI !== 'cli') {
        $configuredPath = (string) (parse_url($configuredUrl, PHP_URL_PATH) ?: '');
        $configuredPath = '/' . trim($configuredPath, '/');
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($configuredPath !== '/' && $configuredPath !== ''
            && $scriptName !== $configuredPath
            && strpos($scriptName, $configuredPath . '/') !== 0) {
            return $detectedPath;
        }
    }

    return $configuredUrl;
}

function logitixEnvBool(string $name, bool $default = false): bool {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? $default;
}

function logitixEnvInt(string $name, int $default, int $min, int $max): int {
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    if ($parsed === false) {
        throw new RuntimeException('Valeur invalide pour ' . $name . '.');
    }
    return (int) $parsed;
}


function logitixEnvSecret(string $name): string {
    // Pour les secrets contenant des caracteres speciaux, le suffixe _B64
    // evite toute ambiguite de parsing dans .env.local. La valeur brute reste
    // supportee pour compatibilite avec les installations existantes.
    $encoded = getenv($name . '_B64');
    if ($encoded !== false && $encoded !== '') {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new RuntimeException('Secret base64 invalide pour ' . $name . '_B64');
        }
        return $decoded;
    }

    $value = getenv($name);
    return $value === false ? '' : (string) $value;
}

// Priorite : environnement du processus > .env.local > .env.
logitixLoadEnvFile(__DIR__ . '/../.env.local');
logitixLoadEnvFile(__DIR__ . '/../.env');

// --- Base de donnees ---
define('DB_HOST', getenv('LOGITIX_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', logitixEnvInt('LOGITIX_DB_PORT', 3306, 1, 65535));
define('DB_NAME', getenv('LOGITIX_DB_NAME') ?: 'logitix');
define('DB_CHARSET', 'utf8mb4');
define('DB_USER_CLIENT', getenv('LOGITIX_DB_USER_CLIENT') ?: 'logitix_client');
define('DB_PASS_CLIENT', logitixEnvSecret('LOGITIX_DB_PASS_CLIENT'));
define('DB_USER_ADMIN', getenv('LOGITIX_DB_USER_ADMIN') ?: 'logitix_admin');
define('DB_PASS_ADMIN', logitixEnvSecret('LOGITIX_DB_PASS_ADMIN'));
define('DB_REQUIRE_TLS', logitixEnvBool('LOGITIX_DB_REQUIRE_TLS', false));
define('DB_SSL_CA', getenv('LOGITIX_DB_SSL_CA') ?: '');

// --- Application ---
define('APP_NAME', 'LOGITIX');
$httpHost = strtolower(trim(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]));
$defaultEnvironment = (PHP_SAPI === 'cli' || in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true))
    ? 'development'
    : 'production';
define('APP_ENV', getenv('LOGITIX_APP_ENV') ?: $defaultEnvironment);
define('APP_URL', logitixResolveAppUrl((string) (getenv('LOGITIX_APP_URL') ?: ''), APP_ENV));
define('TRUST_PROXY_HTTPS', logitixEnvBool('LOGITIX_TRUST_PROXY_HTTPS', false));
$trustedProxyIps = array_values(array_filter(array_map('trim', explode(',', getenv('LOGITIX_TRUSTED_PROXY_IPS') ?: ''))));
define('TRUSTED_PROXY_IPS', $trustedProxyIps);
define('FORCE_HTTPS', logitixEnvBool('LOGITIX_FORCE_HTTPS', APP_ENV === 'production'));
define('ADMIN_REQUIRE_2FA', logitixEnvBool('LOGITIX_ADMIN_REQUIRE_2FA', APP_ENV === 'production'));
// Assistant Web d'installation/activation admin. Desactive par defaut et
// refuse hors environnement development + boucle locale.
define('ADMIN_BOOTSTRAP_ENABLED', logitixEnvBool('LOGITIX_ADMIN_BOOTSTRAP_ENABLED', false));
define('TOTP_ENCRYPTION_KEY', getenv('LOGITIX_TOTP_ENCRYPTION_KEY') ?: '');

if (!in_array(APP_ENV, ['development', 'production', 'testing'], true)) {
    throw new RuntimeException('LOGITIX_APP_ENV doit etre development, production ou testing.');
}
if (trim((string) DB_NAME) === '' || !preg_match('/^[A-Za-z0-9_]+$/', (string) DB_NAME)) {
    throw new RuntimeException('LOGITIX_DB_NAME invalide.');
}
if (APP_ENV === 'production') {
    $productionUrl = parse_url(APP_URL);
    if (($productionUrl['scheme'] ?? '') !== 'https' || empty($productionUrl['host'])) {
        throw new RuntimeException('En production, LOGITIX_APP_URL doit etre une URL HTTPS absolue.');
    }
    if (DB_PASS_CLIENT === '' || DB_PASS_ADMIN === '') {
        throw new RuntimeException('En production, les deux mots de passe MySQL LOGITIX sont obligatoires.');
    }

    $dbHostLower = strtolower(trim((string) DB_HOST));
    $dbIsLocal = in_array($dbHostLower, ['127.0.0.1', 'localhost', '::1'], true);
    if (!$dbIsLocal && !DB_REQUIRE_TLS) {
        throw new RuntimeException('En production, une base MySQL distante doit utiliser LOGITIX_DB_REQUIRE_TLS=true.');
    }
}

if (TOTP_ENCRYPTION_KEY !== '') {
    $decodedTotpKey = base64_decode(TOTP_ENCRYPTION_KEY, true);
    if ($decodedTotpKey === false || strlen($decodedTotpKey) !== 32) {
        throw new RuntimeException('LOGITIX_TOTP_ENCRYPTION_KEY doit contenir exactement 32 octets encodes en base64.');
    }
} elseif (ADMIN_REQUIRE_2FA && APP_ENV === 'production') {
    throw new RuntimeException('LOGITIX_TOTP_ENCRYPTION_KEY est obligatoire lorsque la 2FA admin est requise en production.');
}

// --- Roles ---
define('ROLE_CLIENT', 'client');
define('ROLE_ADMIN', 'admin');

// --- Sessions ---
define('SESSION_TIMEOUT', 3600);              // 1 h d'inactivite client
define('ADMIN_SESSION_TIMEOUT', 1800);        // 30 min d'inactivite admin
define('SESSION_ABSOLUTE_TIMEOUT', 28800);    // 8 h maximum, meme active
define('SENSITIVE_REAUTH_WINDOW', 600);       // 10 min apres saisie du mot de passe

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
if (!$isHttps && TRUST_PROXY_HTTPS
    && in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), TRUSTED_PROXY_IPS, true)) {
    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isHttps = $forwardedProto === 'https';
}

// Redirection HTTPS applicative uniquement si une URL canonique HTTPS est fournie.
// En local, LOGITIX_FORCE_HTTPS=false laisse fonctionner http://localhost.
if (PHP_SAPI !== 'cli' && FORCE_HTTPS && !$isHttps) {
    $appParts = parse_url(APP_URL);
    if (($appParts['scheme'] ?? '') === 'https' && !empty($appParts['host'])) {
        $host = $appParts['host'];
        $port = isset($appParts['port']) ? ':' . (int) $appParts['port'] : '';
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: https://' . $host . $port . $requestUri, true, 308);
        exit;
    }
}

$isAdminArea = strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name($isAdminArea ? 'LOGITIX_ADMIN' : 'LOGITIX_CLIENT');

    $basePath = parse_url(APP_URL, PHP_URL_PATH) ?: '/';
    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') {
        $clientCookiePath = '/';
    } else {
        $clientCookiePath = $basePath;
    }
    $adminCookiePath = rtrim($clientCookiePath, '/') . '/admin';
    if ($adminCookiePath === 'admin') {
        $adminCookiePath = '/admin';
    }

    $cookieSecure = $isHttps || FORCE_HTTPS || parse_url(APP_URL, PHP_URL_SCHEME) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $isAdminArea ? $adminCookiePath : $clientCookiePath,
        'secure' => $cookieSecure,
        'httponly' => true,
        'samesite' => $isAdminArea ? 'Strict' : 'Lax',
    ]);
    session_start();
}

// Headers de securite aussi poses par PHP : ils restent presents avec Nginx
// meme si .htaccess n'est pas interprete.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'; frame-src https://www.google.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Toutes les pages PHP utilisent une session (CSRF/flash/auth) : elles
    // ne doivent pas etre mises en cache par un navigateur ou un proxy.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// --- Gestion des erreurs ---
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
