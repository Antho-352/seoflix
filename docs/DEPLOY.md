# Déploiement WEAS

Ce document décrit le déploiement HestiaCP de `weas.fr`. Il complète le runbook fail-closed [`WEAS_DOMAIN_MIGRATION.md`](WEAS_DOMAIN_MIGRATION.md).

## Préconditions obligatoires

- candidat committé et worktree propre ;
- contre-revue indépendante PASS ;
- deux ZIP WEAS reproductibles avec SHA-256 et manifeste ;
- sauvegarde vérifiée du webroot, de la base et des composants actifs ;
- certificat TLS valide pour les hostnames servis ;
- base MySQL/MariaDB dédiée et recette staging PASS ;
- compte `contact@weas.fr` testé en émission et réception ;
- validation explicite avant toute mutation publique ou redirection.

## Infrastructure cible

- HestiaCP : serveur `54.36.62.104`
- utilisateur : `weas`
- domaine : `weas.fr`
- webroot : `/home/weas/web/weas.fr/public_html/`
- DNS/edge : Cloudflare

Ne jamais remplacer le placeholder HTTP tant que TLS, sauvegarde et rollback ne sont pas prêts : le domaine pointe déjà vers le serveur public.

## 1. Sauvegarde et inventaire

Avant chaque mutation :

```bash
wp core version
wp option get home
wp option get siteurl
wp option get blogname
wp plugin list --format=json
wp theme list --format=json
wp db export /chemin-hors-webroot/weas-pre-deploy.sql
```

Archiver séparément le webroot, vérifier l’ouverture du dump et des archives, puis enregistrer tailles, propriétaires, modes et SHA-256.

## 2. Installation WordPress et base

Provisionner une base et un utilisateur MySQL dédiés dans Hestia. Installer WordPress 6.5+ avec PHP 8+ dans le webroot, sans utiliser `admin` comme identifiant administrateur.

Avant exposition publique :

```php
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
```

Conserver WP-Cron actif jusqu’à preuve qu’un cron système fonctionnel le remplace.

## 3. Installation des artefacts vérifiés

Les noms exacts sont fournis par le manifeste de release :

```bash
wp plugin install /chemin/release/weas-core-0.26.2-<HEAD>.zip --force --activate
wp theme install /chemin/release/weas-theme-0.13.1-<HEAD>.zip --force --activate
wp rewrite flush
```

Ne jamais reconstruire les ZIP sur le serveur. Vérifier d’abord leurs SHA-256 contre `SHA256SUMS`.

## 4. Configuration WEAS

```bash
wp option update home 'https://weas.fr'
wp option update siteurl 'https://weas.fr'
wp option update blogname 'WEAS'
wp option update blogdescription 'Apprends le business web sans perdre des heures sur YouTube.'
wp option update permalink_structure '/%postname%/'
wp rewrite flush
```

Configurer ensuite la clé YouTube dans **WEAS → Réglages** et importer les données depuis **WEAS → Ingestion → Importer JSON**.

Les options `seoflix_user_accounts_enabled` et `seoflix_video_discussions_enabled` restent à `0` jusqu’à validation séparée.

## 5. Cron Hestia

Après vérification manuelle de `wp-cron.php`, créer dans Hestia un cron système sous l’utilisateur `weas` :

```cron
*/5 * * * * /usr/bin/php /home/weas/web/weas.fr/public_html/wp-cron.php >/dev/null 2>&1
```

Seulement après plusieurs exécutions réussies, définir `DISABLE_WP_CRON` à `true`.

## 6. Recette staging MySQL/MariaDB

Vérifier au minimum :

- activation/désactivation idempotente ;
- tables et données `seoflix_*` intactes ;
- migrations et rewrites ;
- homepage, `/parcours/`, six parcours et `/commencer/` ;
- source vidéo avant capsule WEAS ;
- export/effacement Privacy paginés ;
- purge affiliation et crons ;
- erreurs SQL fail-closed ;
- comptes et discussions OFF par défaut ;
- aucun warning PHP, 5xx ou erreur SQL.

## 7. TLS, Cloudflare et QA publique

Émettre le certificat Let’s Encrypt dans Hestia pour les hostnames réellement utilisés. Tester d’abord l’origine avec Host/SNI, puis le proxy Cloudflare.

La QA publique exige : HTTP→HTTPS correct, certificat valide, canonical/JSON-LD WEAS, robots/sitemap cohérents, 14 routes représentatives, mobile 320 px, formulaires, mail, cache et absence d’ancienne marque visible.

## 8. Rollback

En cas d’échec :

1. désactiver les flags comptes/discussions ;
2. remettre les ZIP rollback fournis ;
3. restaurer les options et permaliens ;
4. restaurer le dump MySQL seulement si nécessaire ;
5. vérifier les logs et purger les caches uniquement après confirmation de la version servie.

Aucune redirection de l’ancien domaine ne doit être activée avant le PASS complet décrit dans `WEAS_DOMAIN_MIGRATION.md`.
