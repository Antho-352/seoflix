# Seoflix

Plateforme d'agrégation YouTube SEO type Netflix — `seoflix.fr`.

## Stack

- **CMS** : WordPress 6.5+
- **Plugin métier** : `plugin/seoflix-core` (CPT, taxonomies, ingestion YouTube, importer JSON, affiliation tracker, REST API)
- **Thème** : `theme/seoflix` (FSE block theme, vanilla CSS, noir + rouge)
- **DB** : MySQL natif WP + 3 tables custom (favoris, historique, clics affiliés)
- **Hébergement** : Kimsufi cPanel
- **CI** : aucune ; livraison via zip → upload admin WP

## Structure du repo

```
seoflix/
├── plugin/seoflix-core/        # Plugin WP (logique métier)
├── theme/seoflix/              # Thème FSE (front)
├── docs/                       # Architecture, déploiement, format d'import
├── backlog/                    # JSON produits par les agents (vidéos initiales)
└── README.md
```

## V1 (en cours de build)

- 100% public, **aucun compte utilisateur requis**
- 50 vidéos initiales pré-catégorisées via agents Claude Code
- 4 axes de classification : Sujet, Format, Chaîne, Parcours
- Affiliation intégrée (CPT `seoflix_product`, page `/outils`, tracking via `/go/[slug]`)

## V2 (préparée mais dormante)

- Comptes utilisateurs (favoris, historique de visionnage)
- Activation via toggle `seoflix_user_accounts_enabled` (Réglages plugin)
- Tables DB déjà créées en V1, code écrit mais désactivé via feature flag

## Déploiement

Voir [docs/DEPLOY.md](docs/DEPLOY.md).

1. Installer WP via cPanel Softaculous sur `seoflix.fr`
2. Téléverser `seoflix-core.zip` (plugin) + `seoflix-theme.zip` (thème)
3. Activer plugin + thème
4. Coller la clé YouTube Data API dans Seoflix → Réglages
5. Importer le backlog initial via Seoflix → Ingestion → Importer JSON
6. Configurer le cron cPanel (voir docs/DEPLOY.md)

## Format d'import

Voir [docs/IMPORT_FORMAT.md](docs/IMPORT_FORMAT.md) pour le schéma JSON consommé par l'importer.
