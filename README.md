# WEAS

Plateforme éditoriale de curation de vidéos YouTube francophones sur les business web — domaine canonique cible : `weas.fr`.

> Promesse : « Apprends le business web sans perdre des heures sur YouTube. »

## Stack

- **CMS** : WordPress 6.5+
- **Plugin métier** : `plugin/seoflix-core` — package public **WEAS Core**
- **Thème** : `theme/seoflix` — package public **WEAS**
- **Base** : MySQL/MariaDB WordPress + tables métier historiques `seoflix_*`
- **Hébergement cible** : HestiaCP sur `54.36.62.104`, utilisateur système `weas`
- **Edge** : Cloudflare
- **Livraison** : ZIP déterministes, SHA-256, manifeste et rollback

Les chemins, namespaces, CPT, taxonomies, options, métadonnées, tables et text-domains `seoflix_*` restent volontairement stables pour éviter une migration risquée.

## Fonctionnalités

- homepage WEAS fixe et index public `/parcours/` ;
- six parcours éditoriaux ;
- fiches vidéo source-first, capsule WEAS personnelle optionnelle après la source ;
- articles WordPress natifs ;
- FOCUS limité aux vidéos ;
- questionnaire déterministe sans stockage de réponses ;
- comptes, favoris, carnet, historique et reprise ;
- discussions vidéo privées durcies, désactivées par défaut ;
- Privacy API, exports et suppressions administratives bornés et reprenables.

## Structure

```text
seoflix/
├── plugin/seoflix-core/        # Plugin métier, racine technique conservée
├── theme/seoflix/              # Thème, racine technique conservée
├── tests/contracts/            # Contrats source et runtime simulé
├── docs/                       # Architecture, sécurité et déploiement
└── backlog/                    # Données éditoriales d'import
```

## Gates avant production

Le site n’est publiable qu’après :

1. contrats, lints, WordPress/PHP et navigateur au vert ;
2. contre-revue indépendante PASS ;
3. ZIP reproductibles et hashes vérifiés ;
4. sauvegarde fichiers + MySQL et rollback testé ;
5. recette MySQL/MariaDB sur staging Hestia ;
6. DNS, TLS, SMTP et QA publique PASS ;
7. validation explicite avant activation des 301.

Voir :

- [Déploiement](docs/DEPLOY.md)
- [Migration de domaine](docs/WEAS_DOMAIN_MIGRATION.md)
- [Format d’import](docs/IMPORT_FORMAT.md)
- [Sécurité](docs/SECURITY.md)

## Administration

- Réglages et clé YouTube : **WEAS → Réglages**
- Import JSON : **WEAS → Ingestion → Importer JSON**
- Modération vidéo : **WEAS → Vidéos à valider**
- Privacy/RGPD : **WEAS → RGPD**

Les feature flags comptes et discussions restent désactivés jusqu’au PASS staging MySQL et à la validation de leur activation.
