# Migration de `seoflix.fr` vers `weas.fr`

Statut : **préparation uniquement — aucune redirection ni mutation de production autorisée par ce document**.

## Principe fail-closed

Aucune redirection depuis `seoflix.fr` ne doit être activée avant l’enchaînement complet et prouvé : **DNS → TLS → QA publique → 301**. Si une gate échoue, conserver `seoflix.fr` inchangé et appliquer le rollback du dernier changement.

## 1. Préflight sans mutation

- Confirmer que `weas.fr` est enregistré et sous le compte d’Antho.
- Identifier l’autorité DNS et inventorier A, AAAA, CNAME, MX, TXT, CAA, DNSSEC et nameservers.
- Découvrir en direct le serveur, HestiaCP, le document root, le propriétaire, PHP, WordPress, MariaDB/MySQL, les thèmes/plugins actifs, `home`, `siteurl`, les permaliens et `blog_public`.
- Vérifier la capacité d’héberger `weas.fr` sans modifier le vhost `seoflix.fr`.
- Confirmer ou créer séparément la boîte `contact@weas.fr`, puis tester l’envoi et la réception. Ne pas supprimer `contact@seoflix.fr` pendant la période de transition.

## 2. Sauvegarde et rollback avant mutation

Créer hors webroot et vérifier :

1. dump MySQL complet, lisible et listant les tables attendues ;
2. archive du document root avec test d’ouverture ;
3. copies exactes du plugin et du thème actifs ;
4. export des options `home`, `siteurl`, `blogname`, `blogdescription`, `blog_public`, permaliens et flags WEAS ;
5. inventaire des règles Cloudflare/Hestia, DNS et certificats ;
6. SHA-256, tailles, propriétaires et modes de chaque sauvegarde.

Le rollback restaure d’abord les fichiers et options, puis la base seulement si nécessaire. Il doit conserver les données créées après la sauvegarde ou documenter explicitement leur perte potentielle.

## 3. Recette applicative obligatoire

Avant production, installer les ZIP candidats sur une recette WordPress 6.5+ avec PHP 8+ et MariaDB/MySQL réel.

Vérifier :

- activation du plugin **WEAS Core** et du thème **WEAS** ;
- migrations idempotentes et tables `seoflix_*` intactes ;
- `/`, `/parcours/`, les six parcours, `/commencer/`, articles, vidéos et authentification ;
- vidéo source avant capsule WEAS ;
- export et effacement Privacy paginés ;
- crons et purge d’affiliation ;
- comptes et discussions toujours désactivés par défaut ;
- aucun 5xx, warning PHP ou erreur SQL.

## 4. Domaine candidat avant bascule

- Créer le vhost HestiaCP `weas.fr` et `www.weas.fr` sans supprimer l’ancien.
- Installer WordPress ou cloner la recette selon la stratégie validée.
- Poser les ZIP vérifiés avec fichiers `0644`, dossiers `0755` et propriétaire correct.
- Configurer `home`, `siteurl`, `blogname=WEAS`, e-mail admin/expéditeur et permaliens.
- Émettre et vérifier le certificat TLS pour chaque hostname réellement utilisé.
- Tester l’origine avec Host/SNI avant et après activation du proxy Cloudflare.

## 4 bis. Migration persistante de la marque

### 4 bis.1 Migrer les valeurs persistantes de marque

Exécuter après sauvegarde MySQL et avant toute QA publique :

```bash
wp option update blogname 'WEAS'
wp option update blogdescription 'Apprends le business web sans perdre des heures sur YouTube.'
wp option update seoflix_seo_org_name 'WEAS'
wp option update seoflix_seo_twitter ''
wp option update seoflix_seo_og_image 'https://weas.fr/wp-content/themes/seoflix/assets/images/og-default.png'
```

La valeur Twitter reste vide tant que le compte officiel WEAS n’est pas confirmé. Ne jamais conserver un ancien handle par défaut.

### 4 bis.2 Mettre à jour les pages légales existantes

Le générateur est volontairement idempotent et ne remplace pas le contenu déjà publié. Mettre donc à jour uniquement les quatre pages attendues, avec échec fermé si une page manque ou si WordPress refuse une écriture :

```bash
wp eval '
$slugs = [ "affiliation", "mentions-legales", "confidentialite", "contact" ];
$missing = [];
foreach ( $slugs as $slug ) {
    $page = get_page_by_path( $slug, OBJECT, "page" );
    if ( ! $page ) {
        $missing[] = $slug;
        continue;
    }
    $content = strtr( $page->post_content, [
        "contact@seoflix.fr" => "contact@weas.fr",
        "seoflix.fr" => "weas.fr",
        "Seoflix" => "WEAS",
    ] );
    $result = wp_update_post( [ "ID" => $page->ID, "post_content" => $content ], true );
    if ( is_wp_error( $result ) ) {
        throw new RuntimeException( $slug . ": " . $result->get_error_message() );
    }
}
if ( $missing ) {
    throw new RuntimeException( "Pages légales manquantes : " . implode( ", ", $missing ) );
}
'
```

Relire ensuite les options et les quatre contenus depuis WordPress. Toute occurrence résiduelle de `Seoflix`, `seoflix.fr` ou `contact@seoflix.fr` bloque la suite.

## 5. QA publique

Sur `https://weas.fr/` et les routes représentatives :

- statut/final URL, canonical DOM exact, un H1, title/meta/JSON-LD WEAS ;
- sitemap et robots cohérents avec l’état d’indexation choisi ;
- aucun contenu, domaine ou e-mail MADIAS restant ;
- aucun CTA vers une 404 ;
- menu clavier et mobile, focus visible, overflow nul, erreurs console nulles ;
- questionnaire complet et résultats déterministes ;
- source vidéo avant capsule WEAS ;
- cache canonique et requête cache-bustée servent la même version ;
- formulaire/contact et SMTP testés si la collecte est ouverte.

## 6. Bascule et 301

Seulement après PASS des sections précédentes et validation d’Antho :

1. abaisser le TTL si nécessaire ;
2. figer l’inventaire DNS et préparer son rollback exact ;
3. activer le domaine canonique `https://weas.fr` ;
4. mettre à jour Search Console et soumettre le sitemap WEAS ;
5. activer des redirections **301 une-à-une**, URI et query conservées, de `seoflix.fr` vers `weas.fr` ;
6. tester HTTP/HTTPS et apex/www sans boucle ni chaîne excessive ;
7. surveiller 4xx/5xx, indexation, logs PHP, cron, mail et cache.

## 7. Critères NO-GO

Aucune redirection ni mise en ligne si l’un des points suivants subsiste :

- DNS absent ou autorité inconnue ;
- TLS invalide ;
- sauvegarde ou rollback non vérifié ;
- recette MariaDB/MySQL non exécutée ;
- boîte `contact@weas.fr` non opérationnelle ;
- canonical, sitemap, robots ou liens internes incohérents ;
- erreur PHP/SQL/console ;
- ZIP non reproductible ou différent du code revu ;
- validation explicite d’Antho absente pour la mutation DNS/production.
