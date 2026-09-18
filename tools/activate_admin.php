<?php
/**
 * Active, cree ou reinitialise le compte administrateur bootstrap LOGITIX.
 * Utilise temporairement un compte DBA fourni interactivement et ne stocke
 * jamais ses identifiants. CLI uniquement.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/admin_bootstrap_service.php';

function prompt(string $label, string $default = ''): string {
    $suffix = $default !== '' ? " [$default]" : '';
    $value = trim((string) readline($label . $suffix . ': '));
    return $value === '' ? $default : $value;
}

function hiddenPrompt(string $label): string {
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        $stty = trim((string) @shell_exec('command -v stty 2>/dev/null'));
        if ($stty !== '') {
            fwrite(STDOUT, $label . ': ');
            @shell_exec('stty -echo');
            $value = trim((string) fgets(STDIN));
            @shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
            return $value;
        }
    }
    return trim((string) readline($label . ' (saisie visible): '));
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Extension PDO MySQL absente.\n");
    exit(1);
}

$host = prompt('Hote MySQL', '127.0.0.1');
$portRaw = prompt('Port MySQL', '3306');
$port = filter_var($portRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
if ($port === false) {
    fwrite(STDERR, "Port MySQL invalide.\n");
    exit(1);
}
$dbName = prompt('Base LOGITIX', 'logitix');
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "Nom de base invalide.\n");
    exit(1);
}
$dbaUser = prompt('Utilisateur DBA', 'root');
$dbaPassword = hiddenPrompt('Mot de passe DBA (laisser vide si root local sans mot de passe)');
$email = strtolower(prompt('Email administrateur', 'admin@logitix.ci'));
$forceAnswer = strtolower(prompt('Reinitialiser aussi un administrateur deja actif ? (oui/non)', 'non'));
$forceReset = in_array($forceAnswer, ['oui', 'o', 'yes', 'y'], true);

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $dbName);
    $pdo = new PDO($dsn, $dbaUser, $dbaPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $result = logitixBootstrapAdminAccount($pdo, $email, $forceReset);
    fwrite(STDOUT, $result['created'] ? "Administrateur cree et active.\n" : "Administrateur active/reinitialise.\n");
    fwrite(STDOUT, "\nEmail : " . $result['email'] . "\n");
    fwrite(STDOUT, "Mot de passe temporaire (affiche une seule fois) :\n" . $result['password'] . "\n\n");
    fwrite(STDOUT, "A la premiere connexion, LOGITIX impose le remplacement du mot de passe puis la 2FA si elle est requise.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Echec MySQL : " . $e->getMessage() . PHP_EOL);
    exit(1);
}
