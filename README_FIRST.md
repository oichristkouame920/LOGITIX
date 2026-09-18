# LOGITIX v7.7.2 - VERSION COMPLETE CLIENT + ADMIN SECURISEE

Cette archive est la version complete de reference de LOGITIX pour PHP 8.4 et MySQL 8.x.
Elle conserve l'identite visuelle existante et active les parcours client et administrateur.

Si vous mettez a jour votre installation WampServer actuelle, commencez par `MISE_A_NIVEAU_WAMP_V7_7_2.md`.

## Ce qui est fonctionnel

- inscription, connexion et profil client ;
- changement/reinitialisation securisee des mots de passe ;
- dashboard client avec compteurs MySQL ;
- creation, consultation et suivi des expeditions du client connecte ;
- creation et consultation des devis, y compris reponse/prix admin ;
- connexion admin, mot de passe temporaire obligatoire et 2FA ;
- dashboard admin avec statistiques reelles ;
- gestion des clients, devis, expeditions, vehicules, remorques, chauffeurs et entrepots ;
- journal d'audit des actions administratives ;
- assistant Web LOCAL pour mettre a niveau la base et activer l'admin sans dependance au PHP CLI/Xdebug ;
- migration DBA non destructive v7.6/v7.7 -> v7.7.2 ;
- un seul SQL d'installation neuve : `Logitix_SQL_FINAL_COMPLET.sql`.

## IMPORTANT - votre `.env.local`

`.env.local` n'est volontairement jamais inclus dans le ZIP : il contient vos vrais secrets.
Si vous remplacez un ancien dossier LOGITIX par cette version, copiez votre `.env.local` existant dans le nouveau dossier. Ne le partagez pas et ne le commitez pas.

## Mise a niveau d'une installation v7.6/v7.7 existante (recommandee)

Ne reimportez PAS le SQL complet : il est destine a une installation neuve et contient un garde-fou destructif.

1. Decompressez cette version dans `C:\wamp64\www\`.
2. Copiez votre ancien `.env.local` dans la racine du nouveau dossier.
3. Dans ce `.env.local`, mettez temporairement :

   `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true`

4. Ouvrez dans le navigateur :

   `http://localhost/NOM_DU_DOSSIER/admin/bootstrap.php`

5. Entrez les identifiants DBA MySQL locaux (souvent `root` sous WampServer), gardez la mise a niveau v7.7.2 cochee et activez/reinitialisez l'admin si necessaire.
6. Copiez le mot de passe admin temporaire affiche une seule fois.
7. Remettez IMMEDIATEMENT :

   `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false`

8. Connectez-vous a `admin/connexion.php`. LOGITIX impose le changement du mot de passe puis la 2FA si `LOGITIX_ADMIN_REQUIRE_2FA=true`.

L'assistant Web refuse de fonctionner en production, hors localhost, si l'option n'est pas activee, ou avec un serveur MySQL distant.

## Installation locale neuve

1. Demarrez WampServer et MySQL.
2. Verifiez le port MySQL (souvent `3306`).
3. Importez une seule fois `Logitix_SQL_FINAL_COMPLET.sql` avec root/DBA.
4. Le SQL refuse MariaDB, exige MySQL 8.0.18+ et refuse par defaut d'ecraser une installation LOGITIX existante.
5. Conservez uniquement les mots de passe generes pour `logitix_client@127.0.0.1` et `logitix_admin@127.0.0.1`. Les comptes `*_view_definer` sont verrouilles et ne sont jamais utilises par PHP.
6. Creez `.env.local` avec `tools/setup_local_env.php`, ou partez de `.env.example` et renseignez vos secrets localement.
7. Lancez les diagnostics `tools/diagnose_registration.php` et `tools/diagnose_admin.php` si le PHP CLI est disponible.
8. Pour activer l'admin sans CLI, utilisez l'assistant local decrit ci-dessus.

## Alternative CLI

Si le PHP CLI est correctement configure :

- `php tools/upgrade_admin_features.php` : migration non destructive ;
- `php tools/activate_admin.php` : activation/reinitialisation admin ;
- `php tools/diagnose_registration.php` : diagnostic client ;
- `php tools/diagnose_admin.php` : diagnostic admin ;
- `php tools/reset_user_password.php` : reinitialisation d'un compte utilisateur.

Sous WampServer, vous pouvez utiliser l'executable complet, par exemple :

`& "C:\wamp64\bin\php\php8.4.0\php.exe" ".\tools\diagnose_admin.php"`

## Securite essentielle

- PDO avec requetes preparees natives ;
- CSRF sur les ecritures ;
- sessions client/admin separees et regeneration d'identifiant ;
- `auth_version` pour invalider les anciennes sessions ;
- mots de passe hashes, jamais stockes en clair ;
- TOTP admin chiffre et compteur anti-rejeu ;
- comptes MySQL client/admin distincts, vues `SQL SECURITY DEFINER` et privileges minimaux ;
- aucune suppression metier exposee aux comptes PHP ;
- protection base de donnees contre la double affectation concurrente vehicule/chauffeur/remorque ;
- journal d'audit admin ;
- fichiers `.env`, SQL, outils et fichiers internes bloques depuis le Web ;
- en production : HTTPS obligatoire et TLS MySQL obligatoire pour une base distante.

Consultez `ADMIN_V7_7_2_GUIDE.md`, `LOCAL_SECURITY_SETUP.md`, `SECURITY_CHANGES.md` et `VERIFICATION_REPORT.txt`.
