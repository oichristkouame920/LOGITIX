# LOGITIX v7.7.2 - mise a niveau WampServer sans perdre les donnees

Ce guide correspond au cas recommande : vous avez deja LOGITIX v7.6 en local, votre compte client fonctionne, votre base `logitix` contient deja des donnees et vous voulez ajouter l'administration v7.7.2.

## A. Avant de remplacer le dossier

1. Dans phpMyAdmin, exportez la base `logitix` en SQL et gardez cette sauvegarde hors du dossier Web.
2. Fermez les onglets LOGITIX pendant la migration afin d'eviter une requete au moment ou les vues/privileges sont remis a niveau.
3. Conservez votre ancien dossier jusqu'a ce que les tests de la nouvelle version soient termines.
4. Ne reimportez pas `Logitix_SQL_FINAL_COMPLET.sql` sur votre base existante : ce fichier est destine a une installation neuve.

## B. Installer le nouveau dossier

Decompressez le ZIP dans :

`C:\wamp64\www\LOGITIX_FINAL_COMPLET_ADMIN_V7_7_2`

Copiez ensuite uniquement votre fichier secret existant :

`ANCIEN_DOSSIER\.env.local`

vers :

`C:\wamp64\www\LOGITIX_FINAL_COMPLET_ADMIN_V7_7_2\.env.local`

Ne copiez pas un ancien `config.php`, `database.php`, dossier `admin`, dossier `models` ou ancien SQL par-dessus cette version.

## C. Valeurs a verifier dans `.env.local`

Gardez vos valeurs reelles actuelles et ajoutez seulement la variable bootstrap si elle n'existe pas :

```text
LOGITIX_APP_ENV=development
LOGITIX_APP_URL=auto
LOGITIX_FORCE_HTTPS=false
LOGITIX_TRUST_PROXY_HTTPS=false
LOGITIX_TRUSTED_PROXY_IPS=
LOGITIX_ADMIN_REQUIRE_2FA=true
LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false

LOGITIX_DB_HOST=127.0.0.1
LOGITIX_DB_PORT=3306
LOGITIX_DB_NAME=logitix
LOGITIX_DB_USER_CLIENT=logitix_client
LOGITIX_DB_PASS_CLIENT_B64=VOTRE_VALEUR_EXISTANTE
LOGITIX_DB_USER_ADMIN=logitix_admin
LOGITIX_DB_PASS_ADMIN_B64=VOTRE_VALEUR_EXISTANTE
LOGITIX_DB_REQUIRE_TLS=false
LOGITIX_DB_SSL_CA=

LOGITIX_TOTP_ENCRYPTION_KEY=VOTRE_CLE_EXISTANTE
```

Important : ne changez pas `LOGITIX_TOTP_ENCRYPTION_KEY` si une 2FA a deja ete configuree. Ne partagez ni cette cle ni les mots de passe MySQL.

Si WampServer utilise un autre port MySQL, remplacez `3306` par le port indique dans `my.ini`.

## D. Mettre la base a niveau et activer l'admin sans PHP CLI

Dans `.env.local`, passez temporairement :

`LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true`

Puis ouvrez exactement avec `localhost` :

`http://localhost/LOGITIX_FINAL_COMPLET_ADMIN_V7_7_2/admin/bootstrap.php`

L'assistant refuse de fonctionner hors mode `development`, hors boucle locale, avec un nom d'hote HTTP non local ou avec un serveur MySQL distant.

Dans le formulaire :

- hote MySQL : `127.0.0.1` ;
- port : votre vrai port Wamp ;
- base : `logitix` ;
- utilisateur DBA : souvent `root` en local ;
- mot de passe DBA : celui de votre root MySQL ; il n'est jamais enregistre par LOGITIX ;
- laissez `Mettre la base a niveau vers v7.7.2` coche ;
- laissez `Activer/reinitialiser le compte admin` coche si vous avez besoin d'un acces admin ;
- ne cochez la reinitialisation forcee que si un admin actif existe deja et que vous voulez volontairement invalider son ancien mot de passe et son ancienne 2FA.

La migration est relancable et ne supprime pas les comptes, devis ou expeditions. Si des donnees existantes violent une nouvelle contrainte, elle s'arrete avec un message au lieu de supprimer ou corriger silencieusement les donnees.

Des que l'assistant a termine, remettez :

`LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false`

puis rechargez la page : `admin/bootstrap.php` doit alors renvoyer `Page introuvable.`

## E. Premiere connexion admin

Ouvrez :

`http://localhost/LOGITIX_FINAL_COMPLET_ADMIN_V7_7_2/admin/connexion.php`

Utilisez l'email admin et le mot de passe temporaire affiche par le bootstrap. LOGITIX impose ensuite :

1. remplacement du mot de passe temporaire ;
2. configuration de la 2FA si `LOGITIX_ADMIN_REQUIRE_2FA=true` ;
3. acces au dashboard admin.

## F. Tests a faire avant de supprimer l'ancien dossier

Testez dans cet ordre :

1. connexion client existante ;
2. creation d'un nouveau compte client ;
3. creation d'un devis et apparition dans l'admin ;
4. validation du devis avec montant/devise/reponse ;
5. conversion du devis en expedition ;
6. affectation d'un vehicule et d'un chauffeur puis passage `en_cours` ;
7. verification cote client du nouveau statut ;
8. passage `livree` et verification de la date reelle ;
9. activation/desactivation d'un client ;
10. reinitialisation du mot de passe d'un client ;
11. ajout/mise a jour vehicule, remorque, chauffeur et entrepot ;
12. consultation du journal admin ;
13. deconnexion/reconnexion admin avec 2FA.

Ne supprimez l'ancien dossier et la sauvegarde SQL qu'apres validation de ces parcours.

## G. Erreurs courantes

- `1045` : mot de passe MySQL de `.env.local` different du mot de passe du compte MySQL `logitix_client` ou `logitix_admin`.
- `2002` / `2003` : MySQL arrete, mauvais port ou mauvais hote.
- `1049` : base `logitix` absente.
- `1142` / `1143` / `1356` : vues ou privileges incomplets ; relancer le bootstrap avec la migration cochee.
- erreur de migration sur une expedition `en_cours` : corriger l'expedition mentionnee (vehicule, chauffeur, date de depart ou doublon), puis relancer.
- conflit de vehicule/chauffeur/remorque : une autre expedition utilise deja la ressource ; rechargez puis choisissez une ressource disponible.
- `Secret base64 invalide` : recopier exactement la valeur Base64 du mot de passe, sans guillemets et sans supprimer le `=` final.
- erreur TOTP/dechiffrement apres changement de dossier : restaurer la meme `LOGITIX_TOTP_ENCRYPTION_KEY` que dans l'ancien `.env.local`.
- message Xdebug `Failed loading ...` en CLI : l'assistant `admin/bootstrap.php` permet de faire la migration/activation sans utiliser le PHP CLI.

## H. Installation neuve uniquement

Pour une base vide, utilisez `Logitix_SQL_FINAL_COMPLET.sql`. Le garde-fou destructif reste a `0` par defaut. Ne le passez a `1` que pour une reinstallation volontaire apres sauvegarde.
