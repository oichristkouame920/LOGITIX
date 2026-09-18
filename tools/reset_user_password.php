<?php
/**
 * Reinitialise le mot de passe d'un utilisateur avec un mot de passe
 * temporaire aleatoire et impose son remplacement a la prochaine connexion.
 * CLI uniquement. Le compte DBA est demande interactivement et jamais stocke.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function resetPrompt(string $label, string $default = ''): string {
    $suffix = $default !== '' ? " [$default]" : '';
    $value = trim((string) readline($label . $suffix . ': '));
    return $value === '' ? $default : $value;
}

function resetHiddenPrompt(string $label): string {
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

function temporaryPassword(): string {
    // Garantit majuscule, minuscules, chiffre et caractere special.
    return 'Tmp!' . bin2hex(random_bytes(10)) . 'A7';
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Extension PDO MySQL absente.\n");
    exit(1);
}

$host = resetPrompt('Hote MySQL', '127.0.0.1');
$portRaw = resetPrompt('Port MySQL', '3306');
$port = filter_var($portRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
if ($port === false) { fwrite(STDERR, "Port MySQL invalide.\n"); exit(1); }
$dbName = resetPrompt('Base LOGITIX', 'logitix');
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) { fwrite(STDERR, "Nom de base invalide.\n"); exit(1); }
$dbaUser = resetPrompt('Utilisateur DBA', 'root');
$dbaPassword = resetHiddenPrompt('Mot de passe DBA (laisser vide si votre root local n en a pas)');
$email = strtolower(resetPrompt('Email du compte a reinitialiser'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    fwrite(STDERR, "Email invalide.\n");
    exit(1);
}

$tempPassword = temporaryPassword();
$hash = password_hash($tempPassword, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Impossible de generer le hash.\n");
    exit(1);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $dbName);
    $pdo = new PDO($dsn, $dbaUser, $dbaPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $stmt = $pdo->prepare(
        "UPDATE users
         SET mot_de_passe = :password,
             password_must_change = 1,
             password_changed_at = NULL,
             auth_version = auth_version + 1
         WHERE email = :email"
    );
    $stmt->execute(['password' => $hash, 'email' => $email]);

    if ($stmt->rowCount() !== 1) {
        fwrite(STDERR, "Compte introuvable ou reinitialisation impossible.\n");
        exit(1);
    }

    fwrite(STDOUT, "\nMot de passe temporaire genere :\n");
    fwrite(STDOUT, $tempPassword . "\n\n");
    fwrite(STDOUT, "Transmettez-le au titulaire par un canal sur. Il devra obligatoirement le remplacer a sa prochaine connexion.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Echec MySQL : " . $e->getMessage() . PHP_EOL);
    exit(1);
}
