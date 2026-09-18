# Guide administrateur LOGITIX v7.7.2

## Premier demarrage sur votre base actuelle

1. Copiez l'ancien `.env.local` dans cette version.
2. Mettez temporairement `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true`.
3. Ouvrez `admin/bootstrap.php` sur `localhost`.
4. Laissez "Mettre la base a niveau vers v7.7.2" coche.
5. Laissez "Activer/reinitialiser le compte admin" coche si vous devez obtenir un acces admin.
6. Si un admin actif existe deja, la reinitialisation est refusee par defaut. Ne cochez la reinitialisation forcee que si vous souhaitez volontairement invalider son ancien mot de passe, ses sessions et son ancienne 2FA.
7. Copiez le mot de passe temporaire affiche une seule fois.
8. Remettez `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false`.
9. Connectez-vous via `admin/connexion.php`, changez le mot de passe temporaire et configurez la 2FA.

## Modules admin actifs

- **Dashboard** : clients, expeditions, expeditions en cours, devis en attente, vehicules et chauffeurs disponibles.
- **Clients** : recherche, activation/desactivation, reinitialisation de mot de passe temporaire. Les hashes ne sont jamais affiches.
- **Devis** : montant, devise, reponse, validation/refus, conversion d'un devis valide en expedition.
- **Expeditions** : recherche, statut, vehicule, chauffeur, remorque, dates de depart/livraison.
- **Vehicules / Remorques / Chauffeurs / Entrepots** : ajout et mise a jour sans suppression destructive.
- **Audit** : historique des actions metier sensibles effectuees depuis l'espace admin.
- **Profil / Mot de passe / 2FA** : gestion securisee du compte administrateur.

## Regles d'expedition

Transitions autorisees :

```text
planifiee -> en_cours -> livree
     |          |
     +-> annulee<+
```

Une expedition `livree` ou `annulee` est finale dans l'interface admin. Une expedition `en_cours` exige un vehicule, un chauffeur et une date de depart. Une expedition `livree` exige une date de livraison reelle.

La base contient des index uniques techniques qui empechent deux requetes simultanees d'affecter le meme vehicule, chauffeur ou remorque a deux expeditions `en_cours`.

## Actions sensibles

La reinitialisation du mot de passe d'un client exige une authentification admin recente. Elle genere un mot de passe temporaire, force son remplacement et incremente `auth_version`, ce qui invalide les anciennes sessions du client.

La reinitialisation forcee de l'admin via l'assistant local invalide volontairement son ancien mot de passe, ses sessions et son ancien enrôlement 2FA.

## En cas d'erreur

- `1045` : mot de passe MySQL de `.env.local` non aligne avec le compte MySQL.
- `2002/2003` : MySQL injoignable ou mauvais port.
- `1142/1143/1356` : vues/privileges incomplets ; lancer la migration v7.7.2 via `admin/bootstrap.php` ou `tools/upgrade_admin_features.php`.
- migration bloquee sur une expedition : corriger les donnees indiquees (ressource/date/doublon) puis relancer ; la migration ne supprime rien pour "forcer" le passage.
- conflit de ressource pendant une mise a jour : recharger la page ; une autre expedition a obtenu la ressource avant la validation.
