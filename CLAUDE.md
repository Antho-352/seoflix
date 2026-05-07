# Seoflix — instructions pour Claude

Plateforme d'agrégation YouTube SEO sur `seoflix.fr`. Stack WordPress + plugin custom + thème FSE.

## Architecture

```
seoflix/
├── plugin/seoflix-core/        # Logique métier (CPT, tax, YouTube API, affiliation)
├── theme/seoflix/              # Thème FSE, vanilla CSS, noir + rouge
├── backlog/                    # JSON d'import initial (yt-dlp)
├── scripts/fetch-backlog.py    # Script local yt-dlp (fallback agents)
├── docs/                       # DEPLOY.md, IMPORT_FORMAT.md, LEGAL_PAGES.md
└── dist/                       # Zips livrables (seoflix-core.zip, seoflix-theme.zip)
```

### Plugin (PHP 8+, namespaces `Seoflix\` / `Seoflix\Admin\`)

Chargement dans `includes/class-plugin.php::boot()` :
- `CPT` : `seoflix_video`, `seoflix_channel`, `seoflix_product`
- `Taxonomies` : `seoflix_topic`, `seoflix_format`, `seoflix_path`, `seoflix_product_category` — **tous hiérarchiques** (UI en cases à cocher, pas en tag input)
- `FeatureFlags` : `seoflix_user_accounts_enabled` (V2 dormant)
- `Affiliate` : tracker `/go/[slug]` → table `wp_seoflix_affiliate_clicks`
- `YouTube_API` : wrapper Data API v3 + protections quota
- `Channel_Meta` : metabox identité YouTube + sync vidéos par chaîne
- `Video_Meta` : metabox produits mentionnés + auto-détection (regex word-boundary)
- `Admin\Admin_Columns` : colonnes custom + bulk actions

### Tables custom

- `wp_seoflix_favorites` (V2 dormant)
- `wp_seoflix_watch` (V2 dormant)
- `wp_seoflix_affiliate_clicks` (V1 actif)

### Thème

Hybride classic + theme.json. Pas de Tailwind, vanilla CSS uniquement.
- Palette : noir `#0B0B0F` + `#16161D` + rouge `#FF2D3F` + texte `#F5F5F7`
- Logo : triangle rouge pointant vers le haut sur carré noir (pas de courbe Netflix)
- Helpers dans `functions.php` : `seoflix_render_video_card()`, `seoflix_render_product_card($p, $opts)`, `seoflix_render_video_row()`
- Footer en widgets éditables (Apparence → Widgets), 4 zones `sx-footer-1` à `sx-footer-4`
- Menu auto-créé à l'activation, éditable depuis WP

## Conventions

- **Vanilla CSS only**, pas de Tailwind, pas de framework JS
- **Pas d'emojis** dans le code/UI sauf ceux déjà présents (icônes admin)
- **Pas de copie Netflix** dans le branding (logo, wordmark distincts)
- **Versions** : bump `SEOFLIX_VERSION` dans `seoflix-core.php` + header plugin à chaque release. Idem `Version:` dans `theme/seoflix/style.css`
- **Git** : push après chaque changement significatif. Commits en français, format conventional commits (`feat(plugin):`, `fix(theme):`, etc.)

## Workflow de livraison

User n'a pas de CLI sur son serveur, tout passe par upload zip dans WP admin.

```bash
# Plugin
cd plugin/seoflix-core && zip -r ../../dist/seoflix-core.zip . -x "*.DS_Store"

# Thème
cd theme/seoflix && zip -r ../../dist/seoflix-theme.zip . -x "*.DS_Store"
```

⚠️ **Toujours `cd` dans le dossier source** avant de zipper pour avoir les fichiers à la racine du zip (sinon WP refuse).

⚠️ **Filenames fixes** (`seoflix-core.zip`, `seoflix-theme.zip`), jamais de timestamp — le user veut écraser, pas accumuler.

## Protections quota YouTube API

3 couches (anti-runaway + anti-dépassement Google) :
1. **Cooldown** 30s entre 2 calls
2. **Cap horaire** 200 unités
3. **Cap journalier** 1000 unités (Google hard cap = 10k)

Compteurs en transients (`seoflix_yt_quota_hour`, `seoflix_yt_quota_day`).

**IPv4 forcé** pour tous les calls googleapis.com via `http_api_curl` filter — le serveur Kimsufi sort en IPv6 par défaut, ce qui casse la whitelist IP de la clé API. Ne jamais retirer ce filter.

Pagination uploads playlist : trick `UU` + channel_id sans `UC` (préfixe).

## Auto-détection produits

`Video_Meta::detect_products_in_text($text)` :
- Regex `(?<![\p{L}])` + `preg_quote($name)` + `(?![\p{L}])` avec flags `iu`
- Skip les noms < 4 caractères (faux positifs)
- Appelé : (a) à la sync YouTube par chaîne, (b) via bulk action "Auto-détecter les produits" sur la liste des vidéos, (c) via bouton dans la metabox single

## Gotchas connus

- **WPS Hide Login** : si actif sur le serveur, casse `/wp-admin` et `/wp-login.php`. À désactiver.
- **Permaliens** : flush manuel via Réglages → Permaliens → Save après ajout d'un CPT, sinon URLs en `?post_type=...`
- **Channels archive** : ne pas faire d'INNER JOIN sur `meta_key=_seoflix_subscriber_count` (exclut les nouvelles chaînes sans data). Order by `title ASC` dans `pre_get_posts`.
- **Auto-création menu** : skip si un menu est déjà assigné à l'emplacement → user doit ajouter les items à la main ou supprimer son menu pour re-trigger.
- **REST API users** : `/wp/v2/users` désactivé via `Security` (anti-énumération).

## V1 vs V2

V1 (actuel) : 100% public, pas de comptes. Code V2 (favoris, historique) écrit mais gardé dormant via `FeatureFlags::user_accounts_enabled()`. Tables déjà créées par `Activator`.

## Hors-scope

- Pas de Yoast/RankMath (SEO géré dans le thème + plugin si besoin)
- Pas de Contact Form 7
- Pas d'AdSense (affiliation only)
- Pas de Tailwind/JS framework
