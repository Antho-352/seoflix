# Déploiement Seoflix

## 1. Installer WordPress sur Kimsufi (cPanel)

1. Acheter `seoflix.fr`, configurer DNS chez le registrar pour pointer vers le serveur Kimsufi
2. Dans cPanel → **Softaculous Apps Installer** → WordPress → installer sur `seoflix.fr`
3. Réglages d'install :
   - **Site name** : Seoflix
   - **Tagline** : (vide)
   - **Admin username** : NE PAS utiliser `admin`. Choisir un username obscur
   - **Admin password** : 16+ caractères, généré aléatoirement
   - **Admin email** : ton adresse réelle

## 2. Augmenter les limites PHP

cPanel → **Sélecteur PHP MultiPHP** ou **PHP Options** sur le domaine `seoflix.fr` :

| Paramètre | Valeur |
|---|---|
| `max_execution_time` | `120` |
| `memory_limit` | `256M` |
| `upload_max_filesize` | `8M` |
| `post_max_size` | `16M` |

## 3. Téléverser le plugin

1. Connexion à `seoflix.fr/wp-admin`
2. **Extensions → Ajouter → Téléverser une extension**
3. Choisir le fichier `dist/seoflix-core.zip`
4. **Installer maintenant** → **Activer**

À l'activation, le plugin :
- Crée les CPT (vidéos, chaînes, produits)
- Crée les taxonomies (sujets, formats, parcours, catégories produits)
- Crée 3 tables custom (favoris, historique, clics affiliés)
- Pré-remplit les termes des taxonomies

## 4. Téléverser le thème

1. **Apparence → Thèmes → Ajouter → Téléverser un thème**
2. Choisir le fichier `dist/seoflix-theme.zip`
3. **Installer** → **Activer**

## 5. Configurer le cron Linux (recommandé)

WP-Cron par défaut dépend du trafic. Pour une exécution fiable, configurer un vrai cron :

cPanel → **Tâches Cron** → ajouter :

```
0 * * * * wget -q -O - https://seoflix.fr/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Puis désactiver WP-Cron par défaut. Éditer `wp-config.php` (cPanel → Gestionnaire de fichiers) et ajouter avant `/* That's all, stop editing! */` :

```php
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISABLE_WP_CRON', true );
```

## 6. Importer le backlog initial

1. WP Admin → **Seoflix → Ingestion**
2. **Importer un backlog JSON** → choisir `backlog/seoflix-backlog-v1.json`
3. **Importer**
4. Rapport : 14 chaînes, 47 vidéos (statut « pending »), 34 produits

## 6 bis. Configurer le menu (Apparence → Menus)

Par défaut, le thème affiche un menu hardcodé : **SEO / Affiliation / YouTube / Vente de liens / Business / Toutes les catégories / Chaînes / Outils SEO**.

Pour pouvoir le modifier (ajouter une entrée Blog, retirer/réordonner) :

1. **Apparence → Menus**
2. **Créer un nouveau menu** → nom : « Menu principal » → **Créer le menu**
3. Ajouter les items dans l'ordre voulu :
   - **Catégories** (panneau gauche, dérouler « Sujets ») → cocher : SEO technique, Affiliation, YouTube, Vente de liens, Business → **Ajouter au menu**
   - **Liens personnalisés** :
     - URL : `/categories/` — Texte : `Toutes les catégories`
     - URL : `/chaines/` — Texte : `Chaînes`
     - URL : `/outils/` — Texte : `Outils SEO`
4. Réordonner par drag & drop si besoin
5. **Réglages du menu** (en bas) → cocher **« Menu principal »**
6. **Enregistrer le menu**

Dès qu'un menu est assigné à l'emplacement « Menu principal », le fallback hardcodé disparaît et c'est ton menu qui s'affiche.

### Ajouter une entrée Blog

WP a un module d'articles natif (CPT `post`). Pour l'utiliser et avoir une page Blog :

1. **Pages → Ajouter** → titre : `Blog` → publier (la laisser vide, son contenu sera remplacé par la liste des articles)
2. **Réglages → Lecture** → « Page des articles » → sélectionner **Blog** → enregistrer
3. **Apparence → Menus** → ajouter un **Lien personnalisé** : URL `/blog/`, texte `Blog` → **Ajouter au menu**
4. Tu peux maintenant créer des articles via **Articles → Ajouter**, ils apparaîtront automatiquement sur `/blog/`

Le template `index.php` du thème gère déjà l'affichage des articles WP en grille style Seoflix.

## 7. Valider les vidéos

1. **Seoflix → Vidéos à valider**
2. Pour chaque vidéo : **Publier** / **Modifier** / **Rejeter**
3. Vidéos potentiellement à rejeter (hors-sujet) :
   - Areseo « +263% avec une action qui verse de Dividendes » (investissement, hors-sujet)
   - BHC France « Hakim Benotmane VS Anthony Bourbon » / « Le live de 12h de Oussama Ammar » / « Antoine Blanco coachs pyramidaux » (réacts business plutôt que SEO)
   - Wizards « Amazon KDP » (édition de livres, marginalement on-topic)

## 8. Saisir les URLs affiliées

Pour chaque produit (Linkuma, Semrush, Ereferer, etc.) :

1. **Produits → un produit → Modifier**
2. Champ « URL affiliée » (à venir en phase 4 — pour l'instant utiliser custom field `_seoflix_affiliate_url`)

## 9. Sécurité (à faire avant la mise en ligne publique)

- Plugin **Limit Login Attempts Reloaded** : limite des tentatives de login
- Plugin **Two Factor** : activer 2FA pour ton compte admin
- Plugin **Wordfence Security** (ou équivalent) : firewall + scan
- Plugin **WP Cloudflare Super Page Cache** ou **WP Rocket** : cache + minif
- Désactiver XML-RPC : ajouter dans `.htaccess` :
  ```
  <Files xmlrpc.php>
      Order Allow,Deny
      Deny from all
  </Files>
  ```
- Désactiver l'édition de fichiers depuis l'admin WP (déjà fait via `DISALLOW_FILE_EDIT` à l'étape 5)

## 10. Sauvegardes

Plugin **BackWPup** ou **UpdraftPlus** :
- Sauvegarde quotidienne de la BDD
- Sauvegarde hebdomadaire complète (fichiers + BDD)
- Destination : Google Drive, Dropbox, ou FTP distant (pas le même serveur que la prod)

---

## Mise à jour du plugin / thème

Pour livrer une nouvelle version :

1. Modifier le code dans `plugin/seoflix-core/` ou `theme/seoflix/`
2. Bumper la version dans `seoflix-core.php` (header `Version: X.Y.Z`) ou `theme/seoflix/style.css`
3. Re-générer les zips :
   ```bash
   cd /Users/anthonyrusso/seoflix/plugin && zip -r /Users/anthonyrusso/seoflix/dist/seoflix-core.zip seoflix-core -x "*.DS_Store"
   cd /Users/anthonyrusso/seoflix/theme && zip -r /Users/anthonyrusso/seoflix/dist/seoflix-theme.zip seoflix -x "*.DS_Store"
   ```
4. WP Admin → désactiver et supprimer l'ancien plugin/thème
5. Téléverser le nouveau zip
6. Activer

> **NB** : le plugin et le thème sont **idempotents** sur l'activation/désactivation. Les données (CPT, taxonomies, post_meta, tables custom) ne sont PAS supprimées à la désactivation. Pour les supprimer complètement, il faudrait passer par un `uninstall.php` (à implémenter si besoin).
