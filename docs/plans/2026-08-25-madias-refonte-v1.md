# MADIAS Refonte V1 Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Rebrander Seoflix en MADIAS et livrer un socle éditorial différenciant autour des parcours, timestamps, analyses vidéo d’Antho, progression et découverte guidée.

**Architecture:** Conserver tous les identifiants techniques historiques `seoflix_*`. Étendre le plugin métier pour les données structurées et la persistance; garder le rendu dans le thème. La homepage reste un assemblage fixe avec quelques sélections ciblées, sans builder générique. Les comptes ne sont élargis qu’après correction et preuve runtime.

**Tech Stack:** WordPress 6.5+, PHP 8+, MySQL, thème hybride/FSE vanilla CSS, JavaScript natif, Python `unittest` pour contrats statiques, WordPress Playground pour runtime et Playwright pour QA navigateur.

---

## Décisions Antho intégrées

- Promesse principale : « Apprends le business web sans perdre des heures sur YouTube. »
- Mettre à jour le hero et retirer netlinking, SEO, GEO et black hat des mots rotatifs.
- Pas de builder homepage générique.
- Créer `/parcours/`.
- Utiliser `post` pour le blog avec un template éditorial soigné.
- FOCUS ne touche que les vidéos.
- Les promotions restent gérées manuellement par Antho; pas de workflow de fraîcheur complexe.
- Une vidéo peut appartenir à plusieurs parcours, avec ordre indépendant.
- Conserver les identifiants techniques `seoflix_*`.
- Préparer la migration 301 `seoflix.fr` vers `madias.fr` sans l’activer avant réservation et validation du domaine.
- Chaque fiche vidéo peut recevoir une vidéo personnelle optionnelle d’Antho, rendue sous « L’analyse MADIAS ».
- Export initial du carnet : vue imprimable et copie Markdown.

## Modèle de données retenu

- `_seoflix_editorial_video_url` : URL YouTube optionnelle de la vidéo personnelle d’Antho, rendue via `youtube-nocookie.com` pour respecter la CSP existante.
- `_seoflix_timestamps` : JSON versionné de lignes `{id, seconds, label, takeaway}` triées par temps.
- `_seoflix_key_concepts` : compatibilité avec les tableaux de chaînes existants; normalisation vers `{id, text}` à l’édition, avec identifiants persistants.
- `_seoflix_path_orders` : JSON `{term_id: order}`; fallback/migration idempotente depuis `_seoflix_path_order` pour tous les parcours déjà associés.
- Table future `wp_seoflix_point_states` : état utilisateur `understood|review` par `(user_id, video_id, point_id)`; ajout seulement dans la phase comptes.
- Recherche par passages V1 : index lexical dérivé des lignes de timestamps et points clés. Pas d’embeddings au MVP.

## Politique de compatibilité

- Ne renommer ni CPT, ni taxonomies, ni méta historiques, ni tables, ni namespaces PHP.
- Les anciennes valeurs `key_concepts` sous forme de chaînes restent lisibles.
- Les vidéos sans ordre explicite restent visibles, après les vidéos ordonnées, triées par date.
- Les nouvelles fonctionnalités sont optionnelles et n’empêchent jamais l’affichage d’une vidéo existante.
- Toute migration de données est versionnée, idempotente et vérifiée avant mutation.

---

### Task 1: Installer un harnais de contrats statiques

**Objective:** Créer une base de tests exécutable sans WordPress pour sécuriser les nouveaux contrats et les régressions critiques.

**Files:**
- Create: `tests/contracts/test_madias_editorial_contracts.py`
- Create: `tests/contracts/test_madias_path_order_contracts.py`
- Create: `tests/contracts/test_madias_homepage_contracts.py`
- Create: `tests/contracts/test_madias_accounts_contracts.py`
- Create: `tests/contracts/php_source.py`
- Create: `tests/run-contracts.sh`

**RED:** Ajouter un premier test exigeant les nouvelles constantes de métadonnées dans `class-meta-keys.php`; exécuter `python3 -m unittest discover -s tests/contracts -p 'test_*.py' -v` et conserver l’échec attendu.

**GREEN:** Ajouter seulement les constantes nécessaires, puis faire passer le test. Répéter un cycle RED→GREEN par comportement.

**Verification:**
- `bash tests/run-contracts.sh`
- `php -l` sur les fichiers PHP touchés.

---

### Task 2: Ajouter la couche éditoriale structurée des vidéos

**Objective:** Permettre la saisie sûre de l’analyse vidéo d’Antho, des timestamps et des points clés existants.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-meta-keys.php`
- Modify: `plugin/seoflix-core/includes/class-video-meta.php`
- Modify: `plugin/seoflix-core/includes/class-importer.php`
- Modify: `docs/IMPORT_FORMAT.md`
- Test: `tests/contracts/test_madias_editorial_contracts.py`

**Behaviors:**
1. URL oEmbed optionnelle, validée avec `esc_url_raw`; valeur vide supprimée.
2. Timestamps triés, temps positif borné par la durée lorsqu’elle existe, labels obligatoires, lignes vides ignorées, IDs conservés.
3. Points clés existants chargés depuis les chaînes JSON; à l’enregistrement, chaque point reçoit un ID stable.
4. Les données malformées échouent en sécurité sans supprimer silencieusement les anciennes valeurs.
5. Import JSON compatible avec anciens exports et nouveaux champs.

**Verification:** contrats RED→GREEN, `php -l`, mutation ciblée des sanitizers et du contrôle de capacités.

---

### Task 3: Rendre la fiche vidéo MADIAS

**Objective:** Donner une valeur éditoriale propre sans masquer la vidéo source.

**Files:**
- Modify: `theme/seoflix/single-seoflix_video.php`
- Modify: `theme/seoflix/functions.php`
- Modify: `theme/seoflix/style.css`
- Test: `tests/contracts/test_madias_editorial_contracts.py`

**Ordre UX:**
1. titre, chaîne, durée, sujets/parcours;
2. vidéo source clairement nommée « Vidéo source »;
3. « Les passages à regarder » avec liens YouTube horodatés;
4. « Les points à retenir »;
5. bloc optionnel « L’essentiel par MADIAS » avec embed `youtube-nocookie.com`, toujours après la vidéo source;
6. prochaine étape dans le parcours;
7. produits associés.

**Empty states:** aucun bloc vide si analyse, timestamps ou points clés absents.

**Verification:** rendu échappé, un seul H1, iframe titrée, mobile 320 px sans overflow, liens timestamp ouvrant au bon temps.

**Interaction:** les timestamps pilotent toujours la vidéo source, jamais la capsule MADIAS. La source reste le premier lecteur de la page; la capsule personnelle est chargée paresseusement plus bas.

---

### Task 4: Ajouter les discussions questions/réponses sous les vidéos

**Objective:** Permettre aux membres connectés de poser une question et de répondre sans ouvrir une surface d’upload, de lien ou d’exécution de contenu actif.

**Architecture:** réutiliser les commentaires natifs WordPress attachés à `seoflix_video`; ne créer ni table de commentaires parallèle ni éditeur riche.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-cpt.php`
- Create: `plugin/seoflix-core/includes/class-video-comments.php`
- Modify: `plugin/seoflix-core/includes/class-plugin.php`
- Modify: `plugin/seoflix-core/includes/class-feature-flags.php`
- Modify: l’administration des feature flags
- Modify: `theme/seoflix/single-seoflix_video.php`
- Create: `theme/seoflix/comments-video.php`
- Modify: les styles vidéo du thème
- Test: `tests/contracts/test_madias_video_comments_contracts.py`

**Security contract:**
1. section, lecture et formulaire réservés aux utilisateurs authentifiés; un visiteur ne voit qu’une invitation de connexion sans le contenu des échanges;
2. nonce obligatoire et identité reconstruite depuis l’utilisateur courant, jamais depuis les champs POST;
3. texte brut uniquement, borné en longueur; HTML, shortcodes, embeds et contenu actif supprimés ou rejetés avant écriture;
4. toute URL ou forme de lien (`http`, `https`, `www`, domaine reconnaissable, balise ou schéma) est rejetée avec une erreur explicite;
5. aucun champ fichier; toute requête contenant un upload est rejetée; aucun média n’est créé;
6. réponses imbriquées uniquement si le parent existe, est approuvé et appartient à la même vidéo;
7. limitation de fréquence par utilisateur et longueur maximale; aucune confiance dans JavaScript pour ces contrôles;
8. rendu personnalisé via `esc_html` et retours à la ligne sûrs; ne pas utiliser de filtre qui transforme automatiquement les URLs en liens;
9. statuts et modération WordPress conservés; les outils de modération restent accessibles aux rôles autorisés;
10. export, anonymisation/effacement RGPD et suppression de compte testés sur les commentaires natifs;
11. feature flag désactivable sans supprimer les discussions existantes;
12. REST ou soumission directe ne doit pas pouvoir contourner nonce, authentification, interdiction des liens ou limitation de fréquence.

**UX:** fil chronologique lisible, réponses limitées en profondeur, état en attente de modération, erreurs inline et retour au commentaire après envoi. Aucun score, badge ou système de réputation au lancement.

---

### Task 5: Migrer l’ordre vers une valeur par parcours

**Objective:** Autoriser un ordre indépendant quand une vidéo appartient à plusieurs parcours et conserver toutes les vidéos non ordonnées.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-path-order.php`
- Modify: `plugin/seoflix-core/includes/class-user-accounts.php`
- Modify: `plugin/seoflix-core/includes/class-db-schema.php`
- Modify: `plugin/seoflix-core/includes/class-plugin.php`
- Modify: `theme/seoflix/taxonomy-seoflix_path.php`
- Modify: `plugin/seoflix-core/includes/class-activator.php`
- Modify: version DB/plugin dans `plugin/seoflix-core/seoflix-core.php`
- Test: `tests/contracts/test_madias_path_order_contracts.py`

**Behaviors:**
1. Métabox avec un champ par parcours actuellement associé.
2. Migration idempotente de l’ancien ordre vers chaque parcours lié.
3. Tri : ordre explicite ascendant, puis non ordonné par date ascendante.
4. Progression et prochaine vidéo utilisent exactement le même ordre.
5. Aucune vidéo publiée du parcours n’est perdue si la méta manque.
6. Un `maybe_upgrade()` versionné s’exécute au boot afin que les remplacements de ZIP appliquent aussi les migrations; il est idempotent et ne dépend pas d’une réactivation du plugin.

**Runtime cases:** vidéo sans ordre; vidéo dans deux parcours avec positions différentes; égalités; suppression d’une association; migration rejouée deux fois.

---

### Task 6: Rebrander les surfaces visibles sans renommer l’interne

**Objective:** Afficher MADIAS partout où l’utilisateur ou Antho voit encore Seoflix, tout en préservant les contrats techniques.

**Files:**
- Modify: labels et textes visibles dans le plugin et le thème
- Modify: `plugin/seoflix-core/seoflix-core.php`
- Modify: `theme/seoflix/style.css`
- Modify: `theme/seoflix/header.php`
- Modify: `theme/seoflix/footer.php`
- Modify: réglages SEO et pages légales générées seulement si non personnalisées
- Test: `tests/contracts/test_madias_homepage_contracts.py`

**Guard:** interdire par test tout renommage de `seoflix_video`, `seoflix_path`, `_seoflix_*`, namespaces et noms de tables.

---

### Task 7: Refondre la homepage fixe

**Objective:** Livrer la nouvelle hiérarchie sans builder générique.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-homepage.php`
- Modify: `plugin/seoflix-core/admin/class-admin-homepage.php`
- Modify: `theme/seoflix/front-page.php`
- Modify: `theme/seoflix/style.css`
- Test: `tests/contracts/test_madias_homepage_contracts.py`

**Sections fixes:**
1. hero MADIAS + CTA « Commencer à apprendre »;
2. six cartes parcours;
3. nouveautés;
4. meilleurs outils choisis manuellement dans l’admin;
5. encart promesse;
6. trois rangées de parcours choisies et ordonnées dans un réglage ciblé;
7. CTA `/parcours/`;
8. À propos, texte exact Antho;
9. quatre derniers `post` publiés;
10. newsletter existante à une position définie et non dépendante du nombre de rangées.

La homepage ne doit pas recevoir une seconde newsletter depuis le footer.

**Admin ciblé:** seulement hero, IDs d’outils, slugs des trois parcours et visibilité des blocs historiques; aucun ajout/suppression libre de types.

---

### Task 8: Créer l’index `/parcours/`

**Objective:** Fournir une vraie page d’entrée vers les six parcours, sans 404.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-frontend.php`
- Create: `theme/seoflix/page-paths-index.php`
- Modify: `theme/seoflix/style.css`
- Modify: `plugin/seoflix-core/includes/class-seo.php`
- Test: `tests/contracts/test_madias_homepage_contracts.py`

**Behaviors:** route dédiée, six cartes seulement, description/compte de vidéos/durée estimée si disponible, progression si connecté, CTA commencer/continuer, canonical propre, breadcrumbs et ItemList appropriés.

---

### Task 9: Créer le template d’article natif

**Objective:** Donner aux articles un rendu éditorial cohérent avec MADIAS.

**Files:**
- Create: `theme/seoflix/single-post.php`
- Modify: `theme/seoflix/index.php`
- Modify: `theme/seoflix/functions.php`
- Modify: `theme/seoflix/style.css`
- Modify: `plugin/seoflix-core/includes/class-seo.php`
- Test: `tests/contracts/test_madias_homepage_contracts.py`

**Components:** hero article, métadonnées sobres, chapô/extrait, image, sommaire sticky seulement s’il existe, contenu Gutenberg, encarts accessibles, articles liés, CTA parcours/newsletter. Exactement un H1 et aucune affiliation non divulguée.

---

### Task 10: Implémenter FOCUS uniquement sur les vidéos

**Objective:** Personnaliser la découverte vidéo sans filtrer outils ni articles.

**Files:**
- Create: `plugin/seoflix-core/includes/class-focus.php`
- Modify: `plugin/seoflix-core/includes/class-plugin.php`
- Modify: `theme/seoflix/functions.php`
- Modify: templates vidéo/home/header concernés
- Test: nouveau `tests/contracts/test_madias_focus_contracts.py`

**Behaviors:** préférence fonctionnelle en cookie anonyme et user meta connecté; bannière visible; reset immédiat; priorité/filtrage limité aux requêtes `seoflix_video`; aucune modification des requêtes `post` ou `seoflix_product`; état vide proposant « Voir toutes les vidéos ».

---

### Task 11: Améliorer le questionnaire « Trouver mon business »

**Objective:** Orienter sans promettre un résultat ni lancer de parcours vide.

**Files:**
- Create: `plugin/seoflix-core/includes/class-business-finder.php`
- Modify: `plugin/seoflix-core/includes/class-frontend.php`
- Create: `theme/seoflix/page-business-finder.php`
- Modify: `theme/seoflix/style.css`
- Test: `tests/contracts/test_madias_business_finder_contracts.py`

**Questions V1:**
1. préférence : construire un actif qui peut rapporter plus tard, vendre un service dès maintenant, ou garder les deux ouverts;
2. horizon nécessaire avant les premiers revenus : rapide, quelques mois, sans urgence;
3. budget engageable sans difficulté : presque zéro, petit budget récurrent, investissement plus important;
4. volonté de prospecter et gérer régulièrement des clients;
5. exposition possible : visage, voix seulement, ou totale discrétion;
6. temps disponible chaque semaine;
7. appétence pour tester de nouveaux outils et suivre une veille technique;
8. préférence entre modèle plutôt stable et borné ou potentiel plus élevé mais plus incertain.

**Scoring:** table déterministe et versionnée. Elle écarte d’abord les incompatibilités fortes, classe les parcours restants, puis expose les critères décisifs. Aucun LLM et aucune prétention à mesurer une personnalité.

**Result:** principal + alternative, raisons et contraintes explicites, aucun vocabulaire de garantie, activation volontaire de FOCUS au clic vers le parcours.

**Route:** le CTA « Commencer à apprendre » pointe vers `/commencer/`, qui propose d’abord « Trouver le business qui me correspond » ou « Je sais déjà ce que je veux apprendre ».

**Tests:** cas limites et égalités déterministes; chaque profil mène à un parcours publié et non vide; navigation clavier complète.

---

### Task 12: Fiabiliser les comptes avant d’ajouter le carnet

**Objective:** Corriger les risques existants avant toute nouvelle persistance utilisateur.

**Files:**
- Modify: `plugin/seoflix-core/includes/class-custom-auth.php`
- Modify: `plugin/seoflix-core/includes/class-user-accounts.php`
- Modify: `plugin/seoflix-core/includes/class-db-schema.php`
- Modify: `plugin/seoflix-core/admin/class-admin-rgpd.php`
- Modify: `plugin/seoflix-core/includes/class-affiliate.php`
- Modify: pages légales et version DB/plugin
- Test: `tests/contracts/test_madias_accounts_contracts.py`

**Pre-flight blockers:** supprimer notification admin dupliquée; cleanup `delete_user`; export/effacement favoris, historique et futurs états de points; purge de rétention affiliée conforme; flag OFF ferme réellement l’inscription; rewrites valides; aucune IP en clair dans les nouveaux stockages ou e-mails inutiles.

**Runtime gate:** inscription, activation e-mail interceptée, login, reset, suppression, export, flag ON/OFF dans WordPress Playground. Aucun passage à la tâche suivante sans GREEN runtime.

---

### Task 13: Ajouter le carnet progressif et « Continuer »

**Objective:** Transformer les points clés consultés en ressource personnelle réutilisable.

**Files:**
- Modify: schéma DB et `class-user-accounts.php`
- Modify: `theme/seoflix/page-mon-parcours.php`
- Modify: `theme/seoflix/single-seoflix_video.php`
- Create: endpoint AJAX/REST borné pour les états compris/à revoir
- Test: contrats comptes + runtime Playground

**Behaviors:** état par point stable; liste « À revoir »; carnet disponible avant la fin; reprise vers la prochaine vidéo; vue imprimable noindex; copie Markdown côté client; suppression/export RGPD complet.

---

### Task 14: Ajouter les codes promo sur les outils

**Files:**
- Modify: `plugin/seoflix-core/includes/class-meta-keys.php`
- Modify: le module de métadonnées produit existant
- Modify: `plugin/seoflix-core/includes/class-importer.php`
- Modify: `plugin/seoflix-core/includes/class-frontend.php`
- Create: `theme/seoflix/page-promo-codes.php`
- Modify: `theme/seoflix/single-seoflix_product.php`
- Modify: `theme/seoflix/style.css`
- Test: `tests/contracts/test_madias_promo_contracts.py`

**Fields:** code promo, texte exact de la réduction et profils/parcours « idéal pour » sélectionnés manuellement. Une réduction sans code reste possible si l’offre passe directement par le lien affilié.

**Page:** `/codes-promo/` réutilise les cartes outils mais ne liste que les produits ayant un code ou une réduction active. Le tri est éditorial et manuel; aucun classement par clic affilié.

**Safety:** copie du code accessible au clavier avec confirmation textuelle; mentions affiliées visibles; aucune date d’expiration inventée; état vide propre; FOCUS ne filtre jamais cette page.

---

### Task 15: Préparer la recherche lexicale par passages

**Objective:** Permettre une recherche précise sans IA ni infrastructure vectorielle.

**Phase V1:**
- indexer le texte normalisé de chaque timestamp et point clé;
- chercher les mots avec pondération label > takeaway > titre vidéo;
- renvoyer vidéo, passage, extrait, parcours et URL horodatée;
- journaliser uniquement des requêtes agrégées si une mesure est explicitement activée.

**Implementation choice gate:** mesurer le volume réel du catalogue. Utiliser un index calculé en cache pour petit volume; créer une table dédiée seulement si le volume/temps de réponse le justifie.

**Phase V2:** embeddings par passage et recherche sémantique uniquement après validation d’un corpus, d’un fournisseur, des coûts, de la confidentialité et d’un benchmark lexical vs sémantique.

---

### Task 16: Préparer la migration de domaine

**Objective:** Livrer un runbook vérifiable sans déclencher prématurément la bascule.

**Files:**
- Create: `docs/MADIAS_DOMAIN_MIGRATION.md`
- Modify: métadonnées visibles, e-mails, OG, JSON-LD et pages générées

**Checklist:** sauvegarde; inventaire URL; 301 une-à-une; `home`/`siteurl`; Cloudflare; TLS; Turnstile; clés API; expéditeur mail; Search Console; sitemap; robots; canonical; liens affiliés; cache; rollback. Interdire la redirection tant que `madias.fr` n’est pas résolu, certifié et validé par Antho.

---

### Task 17: QA intégrée et packaging

**Objective:** Produire des ZIPs remplaçables et prouvés, sans publier.

**Verification:**
1. Tous les contrats RED→GREEN.
2. `php -l` sur tous les PHP suivis.
3. WordPress Playground : activation plugin/thème, routes, métaboxes, sauvegardes, migrations idempotentes, comptes.
4. Playwright : desktop et 320/375/768/1440 px; overflow, clavier, focus, contrastes, un H1, embeds, états vides.
5. Crawling local : `/`, `/parcours/`, six parcours, vidéo enrichie/non enrichie, article, questionnaire, auth, sitemap, canonicals, robots et JSON-LD.
6. Revue indépendante de conformité puis revue qualité.
7. `git diff --check`; statut scoped.
8. ZIPs déterministes avec dossier parent, manifestes SHA-256, deux builds byte-identiques.
9. Aucun déploiement ou publication sans instruction explicite d’Antho.

**Contraste bloquant:** le texte blanc sur l’accent `#FF2D3F` est insuffisant pour du texte normal. Les CTA remplis utilisent soit un texte sombre sur cet accent, soit un rouge plus sombre avec texte blanc; vérifier tous les états normal/hover/focus.
