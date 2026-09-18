<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/UserModel.php';

if (!isPost()) {
    redirect('crer_compte.php');
}
requireCsrf();

$data = [
    'nom' => clean((string) ($_POST['nom'] ?? '')),
    'prenom' => clean((string) ($_POST['prenom'] ?? '')),
    'email' => normalizeEmail((string) ($_POST['email'] ?? '')),
    'telephone' => clean((string) ($_POST['telephone'] ?? '')),
    'status' => clean((string) ($_POST['status'] ?? '')),
    'raison_sociale' => clean((string) ($_POST['raison_sociale'] ?? '')),
    'rccm' => clean((string) ($_POST['rccm'] ?? '')),
    'mot_de_passe' => (string) ($_POST['mot_de_passe'] ?? ''),
];
$confirmation = (string) ($_POST['confirmation'] ?? '');
$errors = [];

foreach (['nom', 'prenom', 'email', 'telephone', 'status'] as $field) {
    if ($data[$field] === '') {
        $errors[] = 'Tous les champs obligatoires doivent etre renseignes.';
        break;
    }
}
if (!isValidEmail($data['email'])) $errors[] = 'Format d’email invalide.';
if (strlen($data['nom']) > 100 || strlen($data['prenom']) > 100) $errors[] = 'Nom ou prenom trop long.';
if (strlen($data['email']) > 150) $errors[] = 'Adresse email trop longue.';
if (strlen($data['telephone']) > 30) $errors[] = 'Numero de telephone trop long.';
if (strlen($data['raison_sociale']) > 150) $errors[] = 'Raison sociale trop longue.';
if (strlen($data['rccm']) > 50) $errors[] = 'Numero RCCM trop long.';
if (strlen($data['mot_de_passe']) > 1024 || strlen($confirmation) > 1024) $errors[] = 'Mot de passe trop long.';
if (!in_array($data['status'], ['particulier', 'entreprise', 'cooperative'], true)) $errors[] = 'Statut invalide.';
if (in_array($data['status'], ['entreprise', 'cooperative'], true) && $data['raison_sociale'] === '') $errors[] = 'La raison sociale est obligatoire.';
if (!preg_match('/^[0-9+ -]{8,30}$/', $data['telephone'])) $errors[] = 'Numero de telephone invalide.';
if (!isStrongPassword($data['mot_de_passe'])) $errors[] = 'Le mot de passe doit contenir au moins 12 caracteres, une majuscule, une minuscule, un chiffre et un caractere special.';
if (!hash_equals($data['mot_de_passe'], $confirmation)) $errors[] = 'Les mots de passe ne correspondent pas.';
if (($_POST['confid'] ?? '') !== 'accepte') $errors[] = 'Vous devez accepter la politique de confidentialite.';

if ($errors) {
    unset($data['mot_de_passe']);
    $_SESSION['flash_errors'] = $errors;
    $_SESSION['old'] = $data;
    redirect('crer_compte.php');
}

$userModel = null;

try {
    $userModel = new UserModel('client');
    $ip = clientIp();

    if ($userModel->isRegistrationRateLimited($ip)) {
        setError('Trop de creations de compte depuis cette connexion. Reessayez plus tard.');
        redirect('crer_compte.php');
    }

    if ($userModel->emailExists($data['email'])) {
        $userModel->recordRegistrationAttempt($data['email'], $ip, false);
        setInfo('Si cette adresse peut etre utilisee, le compte est maintenant disponible. Vous pouvez essayer de vous connecter.');
        redirect('se_connecter.php');
    }

    // La creation du compte et son journal de securite forment une seule
    // operation : si le journal echoue, le compte n'est pas laisse a moitie
    // cree. Cela evite aussi les etats incoherents apres une erreur MySQL.
    $userModel->beginTransaction();
    $userModel->create($data);
    $userModel->recordRegistrationAttempt($data['email'], $ip, true);
    $userModel->commit();

    setInfo('Si cette adresse peut etre utilisee, le compte est maintenant disponible. Vous pouvez essayer de vous connecter.');
    redirect('se_connecter.php');
} catch (PDOException $e) {
    if ($userModel instanceof UserModel) {
        $userModel->rollBackIfActive();
    }

    error_log('Erreur inscription SQL : ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        setInfo('Si cette adresse peut etre utilisee, le compte est maintenant disponible. Vous pouvez essayer de vous connecter.');
        redirect('se_connecter.php');
    }

    if (APP_ENV === 'development') {
        $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
        if ($mysqlCode === 1045) {
            setError('Connexion MySQL refusee. Verifiez les mots de passe dans .env.local avec tools/setup_local_env.php.');
        } elseif (in_array($mysqlCode, [2002, 2003], true)) {
            setError('MySQL est injoignable. Verifiez que le service WampServer est demarre ainsi que LOGITIX_DB_HOST et LOGITIX_DB_PORT.');
        } elseif ($mysqlCode === 1049) {
            setError('La base MySQL LOGITIX est introuvable. Importez Logitix_SQL_FINAL_COMPLET.sql.');
        } elseif (in_array($mysqlCode, [1142, 1143, 1356], true)) {
            setError('Les privileges ou vues MySQL de LOGITIX sont incomplets. Reinstallez le SQL final puis relancez le diagnostic.');
        } else {
            setError('Echec MySQL pendant l inscription. Lancez : php tools/diagnose_registration.php');
        }
    } else {
        setError('Une erreur technique est survenue.');
    }
    redirect('crer_compte.php');
} catch (Throwable $e) {
    if ($userModel instanceof UserModel) {
        $userModel->rollBackIfActive();
    }

    error_log('Erreur inscription : ' . $e->getMessage());
    if (APP_ENV === 'development') {
        if (strpos($e->getMessage(), 'Identifiants MySQL LOGITIX non configures') !== false) {
            setError('Configuration MySQL locale absente ou incomplete. Lancez : php tools/setup_local_env.php');
        } elseif (strpos($e->getMessage(), 'pdo_mysql') !== false) {
            setError('Extension PHP pdo_mysql absente. Activez-la dans WampServer.');
        } else {
            setError('Configuration locale incomplete. Lancez : php tools/diagnose_registration.php');
        }
    } else {
        setError('Une erreur technique est survenue.');
    }
    redirect('crer_compte.php');
}
