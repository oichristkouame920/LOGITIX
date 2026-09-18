# LOGITIX v7.7.2 - installation locale WampServer + MySQL

## Prerequis

- WampServer 64 bits ;
- PHP 8.3+ (PHP 8.4 recommande) ;
- extensions `PDO`, `pdo_mysql`, `openssl` et/ou `sodium` ;
- MySQL 8.0.18+ (MySQL 8.4 LTS recommande) ;
- MariaDB n'est pas une cible supportee par le SQL v7.7.2.

## Base existante v7.6/v7.7

Ne reimportez pas le SQL complet. Copiez votre `.env.local` vers le nouveau dossier puis activez temporairement :

`LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true`

Ouvrez ensuite :

`http://localhost/NOM_DU_DOSSIER/admin/bootstrap.php`

L'assistant :

- fonctionne uniquement en `development` ;
- accepte uniquement une requete provenant directement de `127.0.0.1` ou `::1` ;
- accepte uniquement un MySQL local (`localhost`, `127.0.0.1` ou `::1`) ;
- utilise le mot de passe DBA uniquement en memoire ;
- applique la migration v7.7.2 sans supprimer les donnees ;
- peut creer/activer/reinitialiser l'administrateur ;
- genere un mot de passe admin temporaire aleatoire affiche une seule fois.

Apres utilisation, remettre obligatoirement :

`LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false`

## Installation neuve

Importer avec root/DBA : `Logitix_SQL_FINAL_COMPLET.sql`.

Le script cree 12 tables, 13 vues securisees, les comptes MySQL dedies, les contraintes/index, le journal d'audit et les controles finaux bloquants. Il protege aussi les expeditions en cours contre les affectations concurrentes d'un meme vehicule, chauffeur ou remorque.

Le SQL contient :

`SET @LOGITIX_ALLOW_DESTRUCTIVE_REINSTALL = 0;`

Laisser cette valeur a `0` sauf reinstallation volontaire apres sauvegarde.

## `.env.local`

Pour le creer avec le CLI :

`php tools/setup_local_env.php`

L'outil teste les connexions avant d'ecrire le fichier et conserve une cle TOTP existante valide lors d'un remplacement de `.env.local`, afin d'eviter d'invalider une 2FA deja enrolee.

Variables locales importantes :

```text
LOGITIX_APP_ENV=development
LOGITIX_APP_URL=auto
LOGITIX_ADMIN_REQUIRE_2FA=true
LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false
LOGITIX_DB_HOST=127.0.0.1
LOGITIX_DB_PORT=3306
LOGITIX_DB_NAME=logitix
LOGITIX_DB_USER_CLIENT=logitix_client
LOGITIX_DB_PASS_CLIENT_B64=...
LOGITIX_DB_USER_ADMIN=logitix_admin
LOGITIX_DB_PASS_ADMIN_B64=...
LOGITIX_DB_REQUIRE_TLS=false
LOGITIX_TOTP_ENCRYPTION_KEY=...
```

Ne partagez jamais les valeurs de mot de passe ou `LOGITIX_TOTP_ENCRYPTION_KEY`.

## Diagnostics

- Client : `php tools/diagnose_registration.php`
- Admin : `php tools/diagnose_admin.php`

Le diagnostic admin verifie aussi que le compte PHP admin ne peut pas lire le hash du mot de passe des clients.

## Production

Passer au minimum a `LOGITIX_APP_ENV=production`, `LOGITIX_FORCE_HTTPS=true`, laisser `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false` et conserver la 2FA admin obligatoire. Pour une base MySQL distante, activer `LOGITIX_DB_REQUIRE_TLS=true` et fournir `LOGITIX_DB_SSL_CA`.
