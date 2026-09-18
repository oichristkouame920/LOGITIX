<?php
/**
 * Assistant local de mise a niveau / activation admin.
 *
 * Securite :
 * - desactive par defaut via .env.local ;
 * - development uniquement ;
 * - acces boucle locale uniquement ;
 * - identifiants DBA utilises en memoire pour la requete, jamais stockes ;
 * - CSRF ;
 * - aucun mot de passe admin par defaut.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_upgrade.php';
require_once __DIR__ . '/../includes/admin_bootstrap_service.php';

$remote=(string)($_SERVER['REMOTE_ADDR']??'');
$httpHost=strtolower(trim((string)($_SERVER['HTTP_HOST']??'')));
$httpHost=preg_replace('/:\d+$/','',$httpHost) ?? $httpHost;
$localHosts=['localhost','127.0.0.1','[::1]','::1'];
if(APP_ENV!=='development' || !ADMIN_BOOTSTRAP_ENABLED || !in_array($remote,['127.0.0.1','::1'],true) || !in_array($httpHost,$localHosts,true)){
    http_response_code(404);
    exit('Page introuvable.');
}

$messages=[];$error=null;$temporaryPassword=null;
if(isPost()){
    requireCsrf();
    $host=trim((string)($_POST['db_host']??DB_HOST));
    $port=filter_var($_POST['db_port']??DB_PORT,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
    $db=trim((string)($_POST['db_name']??DB_NAME));
    $dbaUser=trim((string)($_POST['dba_user']??'root'));
    $dbaPass=(string)($_POST['dba_password']??'');
    $adminEmail=normalizeEmail((string)($_POST['admin_email']??'admin@logitix.ci'));
    $doUpgrade=!empty($_POST['do_upgrade']);
    $doActivate=!empty($_POST['do_activate']);
    $forceReset=!empty($_POST['force_reset']);
    try{
        if($port===false||!preg_match('/^[A-Za-z0-9_]+$/',$db)||$dbaUser===''||strlen($host)>255){throw new InvalidArgumentException('Parametres MySQL invalides.');}
        if(!in_array(strtolower($host),['127.0.0.1','localhost','::1'],true)){throw new InvalidArgumentException('Pour la securite, l assistant Web accepte uniquement un serveur MySQL local. Utilisez l outil CLI pour une base distante.');}
        if(!$doUpgrade&&!$doActivate){throw new InvalidArgumentException('Selectionnez au moins une operation.');}
        if($doActivate&&!isValidEmail($adminEmail)){throw new InvalidArgumentException('Email administrateur invalide.');}
        if(!extension_loaded('pdo_mysql')){throw new RuntimeException('Extension PHP pdo_mysql absente.');}

        $pdoOptions=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5];
        if(defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')){$pdoOptions[PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;}
        $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$dbaUser,$dbaPass,$pdoOptions);
        if($doUpgrade){
            foreach(logitixUpgradeAdminFeatures($pdo,$db) as $line){$messages[]=$line;}
        }
        if($doActivate){
            $bootstrapResult=logitixBootstrapAdminAccount($pdo,$adminEmail,$forceReset);
            $temporaryPassword=$bootstrapResult['password'];
            $adminEmail=$bootstrapResult['email'];
            $messages[]=$bootstrapResult['created']
                ? 'Compte administrateur cree et active. Le mot de passe doit etre change a la premiere connexion.'
                : 'Compte administrateur reinitialise/active. Les anciennes sessions admin et l ancienne 2FA sont invalidees.';
        }
        rotateCsrfToken();
    }catch(Throwable $e){
        if(isset($temporaryPassword) && $temporaryPassword!==null && isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()){$pdo->rollBack();}
        $temporaryPassword=null;
        error_log('LOGITIX bootstrap local: '.$e->getMessage());
        $error=$e->getMessage();
    }
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assistant Admin local - LOGITIX</title><link href="../css/bootstrap.min.css" rel="stylesheet"><link href="../css/bootstrap-icons.css" rel="stylesheet"><link href="../css/fonts.css" rel="stylesheet"><style>body{background:#f5f3f0;font-family:'Jost',sans-serif}.box{max-width:850px;margin:3rem auto;background:#fff;border-radius:16px;padding:2rem;box-shadow:0 4px 18px rgba(0,0,0,.08)}h2{color:#01203F}.btn-logitix{background:#D2691E;color:#fff;border:0}.btn-logitix:hover{background:#8B4513;color:#fff}code{overflow-wrap:anywhere}</style></head><body><div class="container"><div class="box">
<h2>Assistant administrateur local</h2><p class="text-muted">Cette page ne fonctionne que sur localhost (adresse et nom d'hôte locaux), en mode development, et seulement lorsque <code>LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true</code>. Les identifiants DBA saisis ici ne sont jamais écrits dans un fichier.</p>
<div class="alert alert-warning"><strong>Après utilisation :</strong> remettez immédiatement <code>LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false</code> dans <code>.env.local</code>.</div>
<?php if($error):?><div class="alert alert-danger"><?= e($error) ?></div><?php endif;?>
<?php foreach($messages as $m):?><div class="alert alert-success mb-2"><?= e($m) ?></div><?php endforeach;?>
<?php if($temporaryPassword):?><div class="alert alert-info"><strong>Email :</strong> <?= e($adminEmail) ?><br><strong>Mot de passe temporaire (affiché une seule fois) :</strong><br><code class="fs-5"><?= e($temporaryPassword) ?></code><br><small>Connectez-vous ensuite via <a href="connexion.php">admin/connexion.php</a>. LOGITIX imposera un nouveau mot de passe puis la 2FA si elle est obligatoire.</small></div><?php endif;?>
<form method="post" autocomplete="off" class="row g-3"><?= csrfField() ?>
<div class="col-md-6"><label class="form-label">Hôte MySQL DBA</label><input class="form-control" name="db_host" value="<?= e((string)DB_HOST) ?>" required></div><div class="col-md-3"><label class="form-label">Port</label><input class="form-control" type="number" name="db_port" min="1" max="65535" value="<?= (int)DB_PORT ?>" required></div><div class="col-md-3"><label class="form-label">Base</label><input class="form-control" name="db_name" value="<?= e((string)DB_NAME) ?>" required></div>
<div class="col-md-6"><label class="form-label">Utilisateur DBA</label><input class="form-control" name="dba_user" value="root" required></div><div class="col-md-6"><label class="form-label">Mot de passe DBA</label><input class="form-control" type="password" name="dba_password" autocomplete="new-password"><small class="text-muted">Laissez vide uniquement si votre root Wamp local n'a pas de mot de passe.</small></div>
<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="do_upgrade" value="1" id="up" checked><label class="form-check-label" for="up"><strong>Mettre la base à niveau vers v7.7.2</strong><br><small class="text-muted">Non destructif : conserve clients, devis et expéditions.</small></label></div></div>
<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="do_activate" value="1" id="act" checked><label class="form-check-label" for="act"><strong>Activer/réinitialiser le compte admin</strong></label></div></div>
<div class="col-md-6"><label class="form-label">Email administrateur</label><input class="form-control" type="email" name="admin_email" maxlength="150" value="admin@logitix.ci"></div>
<div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="force_reset" value="1" id="force"><label class="form-check-label" for="force">Je confirme la réinitialisation si un admin actif existe déjà.</label></div></div>
<div class="col-12"><button class="btn btn-logitix px-4" type="submit"><i class="bi bi-shield-lock me-1"></i> Exécuter</button> <a class="btn btn-outline-secondary" href="connexion.php">Retour connexion admin</a></div>
</form></div></div></body></html>
