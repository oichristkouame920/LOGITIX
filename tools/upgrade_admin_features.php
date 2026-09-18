<?php
/** Mise a niveau non destructive v7.6/v7.7 -> v7.7.2. CLI uniquement. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/admin_upgrade.php';

function promptValue(string $label, string $default=''): string {
    $suffix=$default!==''?" [$default]":'';
    $v=trim((string)readline($label.$suffix.': '));
    return $v===''?$default:$v;
}
function promptSecret(string $label): string {
    if (DIRECTORY_SEPARATOR==='/' && function_exists('shell_exec')) {
        $stty=trim((string)@shell_exec('command -v stty 2>/dev/null'));
        if($stty!==''){fwrite(STDOUT,$label.': ');@shell_exec('stty -echo');$v=trim((string)fgets(STDIN));@shell_exec('stty echo');fwrite(STDOUT,PHP_EOL);return $v;}
    }
    return trim((string)readline($label.' (saisie visible): '));
}
if(!extension_loaded('pdo_mysql')){fwrite(STDERR,"Extension PDO MySQL absente.\n");exit(1);}
$host=promptValue('Hote MySQL DBA','127.0.0.1');
$portRaw=promptValue('Port MySQL','3306');
$port=filter_var($portRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
$db=promptValue('Base LOGITIX','logitix');
$user=promptValue('Utilisateur DBA','root');
$pass=promptSecret('Mot de passe DBA (laisser vide si root local n en a pas)');
if($port===false||!preg_match('/^[A-Za-z0-9_]+$/',$db)){fwrite(STDERR,"Port ou base invalide.\n");exit(1);}
try{
    $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    foreach(logitixUpgradeAdminFeatures($pdo,$db) as $line){fwrite(STDOUT,"[PASS] {$line}\n");}
    fwrite(STDOUT,"\nMise a niveau v7.7.2 terminee. Vous pouvez maintenant activer/connecter l administrateur.\n");
}catch(Throwable $e){fwrite(STDERR,"[FAIL] ".$e->getMessage().PHP_EOL);exit(1);}
