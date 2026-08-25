# Format JSON d'import — Seoflix

Ce document définit le format consommé par `Seoflix\Importer` (admin → Seoflix → Ingestion → Importer JSON). Les agents de catégorisation de backlog produisent des fichiers conformes à ce schéma.

## Vue d'ensemble

Un fichier d'import est un objet JSON unique avec quatre sections :

```json
{
  "version": 1,
  "agent_id": "agent-1",
  "channels": [...],
  "videos": [...],
  "products_detected": [...]
}
```

## Section `channels`

Liste des chaînes YouTube. L'importer crée ou met à jour le CPT `seoflix_channel`.

```json
{
  "handle": "@Ares-eo",
  "youtube_channel_id": "UCxxxxxxxxxxxxxxxxxxx",
  "display_name": "Areseo",
  "real_name": "Olivier Beining",
  "description": "Chaîne SEO d'Olivier Beining...",
  "thumbnail_url": "https://yt3.googleusercontent.com/...",
  "subscriber_count": 12500,
  "url": "https://www.youtube.com/@Ares-eo"
}
```

| Champ | Type | Requis | Notes |
|---|---|---|---|
| `handle` | string | oui | Format `@xxx`, sert d'identifiant unique d'import |
| `youtube_channel_id` | string | non | UCxxx... si trouvé |
| `display_name` | string | oui | Nom affiché dans l'UI |
| `real_name` | string | non | Nom de la personne (modifiable en admin ensuite) |
| `description` | string | non | 1-3 phrases |
| `thumbnail_url` | string | non | URL de l'avatar |
| `subscriber_count` | number | non | |
| `url` | string | non | URL canonique de la chaîne |

## Section `videos`

Liste des vidéos pré-catégorisées.

```json
{
  "youtube_id": "abc123def45",
  "channel_handle": "@Ares-eo",
  "title": "Titre exact YouTube",
  "duration_seconds": 1800,
  "published_at": "2024-01-15",
  "view_count": 12345,
  "thumbnail_url": "https://i.ytimg.com/vi/abc123def45/maxresdefault.jpg",
  "youtube_url": "https://www.youtube.com/watch?v=abc123def45",
  "topics": ["seo-technique", "netlinking"],
  "formats": ["podcast"],
  "paths": ["apprendre-le-seo"],
  "description_ai": "...",
  "editorial_video_url": "https://youtu.be/MADIAS12345",
  "timestamps": [{"id": "UUID", "seconds": 95, "label": "Audit initial", "takeaway": "Prioriser les erreurs bloquantes."}],
  "key_concepts": [{"id": "UUID", "text": "Commencer par les pages indexables"}],
  "products_mentioned": ["ahrefs", "linkuma"],
  "transcript_available": true
}
```

| Champ | Type | Requis | Notes |
|---|---|---|---|
| `youtube_id` | string | oui | ID 11 chars de la vidéo, identifiant unique |
| `channel_handle` | string | oui | Doit matcher un handle de la section `channels` |
| `title` | string | oui | Titre YouTube original |
| `duration_seconds` | number | oui | Durée en secondes |
| `published_at` | string (YYYY-MM-DD) | oui | Date de publication |
| `view_count` | number | non | Vues à la date du scrape |
| `thumbnail_url` | string | non | Si null, calculée depuis `youtube_id` |
| `youtube_url` | string | non | Calculable depuis `youtube_id` |
| `topics` | string[] | oui | Slugs de la taxonomie `seoflix_topic` (voir liste) |
| `formats` | string[] | oui | Slugs de la taxonomie `seoflix_format` |
| `paths` | string[] | non | Slugs de la taxonomie `seoflix_path` (peut être vide) |
| `description_ai` | string | oui | 150-200 mots en français, factuel |
| `editorial_video_url` | string | non | ID ou URL YouTube de la capsule MADIAS; hôtes acceptés : `youtube.com`, `www.youtube.com`, `m.youtube.com`, `youtu.be`, `youtube-nocookie.com`, `www.youtube-nocookie.com`. Stockage canonique `https://www.youtube-nocookie.com/embed/{id}`. Une chaîne vide supprime la méta; une valeur invalide laisse la valeur existante intacte. |
| `timestamps` | object[] | non | Lignes `{id, seconds, label, takeaway}`. `id` est un UUID conservé ou généré, `seconds` est un entier positif ou nul borné par `duration_seconds` lorsqu'elle est positive, `label` est requis et `takeaway` optionnel. Les lignes valides sont triées par secondes puis ordre d'entrée. |
| `key_concepts` | string[] ou object[] | non | Le format historique `string[]` reste compatible. Le format structuré est `{id, text}`; un UUID valide est conservé, sinon généré, et les textes vides sont ignorés. |
| `products_mentioned` | string[] | non | Slugs des produits (cf. `products_detected`) |
| `transcript_available` | boolean | non | true si transcript a pu être récupéré |

## Section `products_detected`

Liste des produits/services SaaS mentionnés dans les vidéos. L'importer crée le CPT `seoflix_product` avec le statut `draft` (URL affiliée à saisir manuellement par l'admin).

```json
{
  "slug": "ahrefs",
  "name": "Ahrefs",
  "category": "outils-seo",
  "description": "Suite SEO tout-en-un (backlinks, recherche de mots-clés, audit technique).",
  "official_url": "https://ahrefs.com",
  "pricing": "paid"
}
```

| Champ | Type | Requis | Notes |
|---|---|---|---|
| `slug` | string | oui | Identifiant unique kebab-case |
| `name` | string | oui | Nom commercial |
| `category` | string | oui | Slug catégorie produit (cf. taxonomie) |
| `description` | string | oui | 1-2 phrases |
| `official_url` | string | non | URL officielle non-affiliée |
| `pricing` | string | non | `free` / `freemium` / `paid` |

## Taxonomies — slugs valides

### `seoflix_topic` (Sujets)

- `seo-technique`
- `netlinking`
- `black-hat`
- `vente-de-liens`
- `affiliation`
- `vente-de-leads`
- `dropshipping`
- `youtube`
- `mindset-business`
- `organisation`
- `infrastructure`
- `business-general`
- `e-commerce`
- `ia-rédaction` *(slug technique : `ia-redaction`)*
- `analytics`

### `seoflix_format` (Formats)

- `podcast`
- `interview`
- `build-in-public`
- `tuto`
- `cas-pratique`
- `conference`
- `vlog`

### `seoflix_path` (Parcours d'apprentissage)

- `apprendre-le-seo`
- `apprendre-le-netlinking`
- `apprendre-la-vente-de-liens`
- `apprendre-l-affiliation` *(slug : `apprendre-l-affiliation`)*
- `apprendre-la-vente-de-leads`
- `apprendre-le-business`

### Catégories produits (champ `category` de `products_detected`)

- `outils-seo` (Ahrefs, Semrush, Babbar, Yourtext.guru, Mangools…)
- `crawlers` (Screaming Frog, Oncrawl, Sitebulb…)
- `plateformes-vente-de-liens` (Linkuma, RocketLinks, Develink, NextLevel…)
- `plateformes-affiliation` (Awin, Kwanko, Affilae, ShareASale…)
- `hebergement` (OVH, Hostinger, o2switch, IONOS…)
- `vps` (Kimsufi, Hetzner, Contabo…)
- `domaines` (NameCheap, OVH, Dynadot, GoDaddy…)
- `wordpress-plugins` (RankMath, WP Rocket, Elementor…)
- `wordpress-themes`
- `formations` (formations SEO, business, etc.)
- `ia-redaction` (Surfer, Frase, ChatGPT, Claude…)
- `trackers-analytics` (Plausible, Matomo, Fathom, GA…)
- `email-marketing` (Mailchimp, Brevo, ActiveCampaign…)
- `automatisation` (Make, Zapier, n8n…)
- `autres`

## Règles de validation (importer)

1. `videos[*].channel_handle` DOIT correspondre à un `channels[*].handle`.
2. `videos[*].topics[*]` DOIT être dans la liste valide `seoflix_topic`.
3. `videos[*].formats[*]` DOIT être dans la liste valide `seoflix_format`.
4. `videos[*].paths[*]` (optionnel) DOIT être dans la liste valide `seoflix_path`.
5. `videos[*].products_mentioned[*]` DOIT correspondre à un `products_detected[*].slug` (sinon ignoré).
6. `videos[*].youtube_id` est l'identifiant d'unicité — un import existant est ignoré (dédup).
7. Les champs éditoriaux sont optionnels : s'ils sont absents, l'import ne modifie pas les métadonnées existantes. Un tableau `timestamps` ou `key_concepts` malformé est également ignoré plutôt que d'effacer l'existant. Seul un vrai tableau JSON vide `[]` efface le champ ; un objet `{}` est invalide et ignoré. Les lignes sans UUID reçoivent un identifiant déterministe afin qu'un même import ne change pas leurs identités. Un UUID fourni en double est réattribué de façon unique à partir de sa deuxième occurrence.
8. Les timestamps pilotent toujours la vidéo source; `editorial_video_url` désigne uniquement la future capsule « L'essentiel par MADIAS ».

## Règles éditoriales (agents)

### Filtres obligatoires (rejeter d'office)

- **Shorts** (durée < 60 secondes) — JAMAIS dans le backlog
- **Vidéos courtes** (durée < 420 secondes / 7 minutes) — JAMAIS dans le backlog
- **Hors-sujet** (gaming pur, vlog perso non-business, contenu non lié au SEO/édition de sites)

### Sélection éditoriale

- Sélectionner les vidéos pertinentes au sujet (SEO, édition de site, business web).
- Privilégier les vidéos à fort nombre de vues, mais diversifier (pas trois vidéos quasi-identiques).
- Description en français factuel, ni promotionnel ni racoleur. Pas d'emojis. Pas de "vous découvrirez", "incroyable", "must-watch". Décrire ce que la vidéo couvre.
- Topics : 1 à 3 par vidéo, ne mettre que ceux qui correspondent clairement.
- Paths : 0 à 2 par vidéo, n'attribuer que si la vidéo est explicitement pédagogique sur le sujet.
- Ne jamais inventer un produit. Si non sûr d'un produit cité, ne pas l'inclure.
