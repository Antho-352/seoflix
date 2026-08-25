# Procédure de traitement des demandes RGPD

Marche à suivre quand un visiteur t'envoie une demande RGPD (accès, rectification, effacement, portabilité) à `contact@seoflix.fr`.

---

## Ce que tu collectes effectivement (V1)

| Source | Donnée | Lieu | Identifiable ? |
|--------|--------|------|----------------|
| Logs Apache | IP en clair, page, user-agent | Serveur Kimsufi (cPanel) | Oui (par IP) |
| `wp_seoflix_affiliate_clicks` | Hash IP + UA + page | Base WP | **Non** (hash irréversible, sauf si IP fournie pour recompute) |
| `wp_comments` | Nom, e-mail, IP, contenu | Base WP | Oui (par e-mail ou IP) |
| `wp_users` | Login, e-mail, hash mot de passe | Base WP | Oui (vide en V1, pas de comptes) |
| Mailbox `contact@seoflix.fr` | E-mails reçus | Hébergeur mail | Oui |
| Backups | Snapshot complet | Selon outil de backup | Oui (jusqu'à expiration) |

En V1 il n'y a pas de comptes utilisateurs, donc 99% des demandes seront vides.

## Discussions privées sous les vidéos (fonction dormante)

La fonctionnalité repose uniquement sur `wp_comments` et `wp_commentmeta`, avec le type dédié `seoflix_video_discussion`. Elle ne crée aucune table. Elle reste désactivée tant que l'option `seoflix_video_discussions_enabled` n'est pas activée **et** que les comptes utilisateurs ne sont pas actifs. Couper l'un des deux flags masque l'interface et bloque les lectures/écritures, sans effacer les fils existants.

Données conservées pour une contribution : identifiant du commentaire et de la vidéo, identifiant utilisateur, nom affiché et e-mail reconstruits depuis le compte, texte brut, parent éventuel, dates et statut de modération. L'adresse IP, l'URL auteur et le user-agent sont volontairement vides. Ce lot n'écrit aucune ligne `wp_commentmeta`. L'exporteur natif de commentaires WordPress couvre les champs de `wp_comments` ; toute extension tierce ajoutant ses propres métadonnées doit fournir son exporteur, et l'export JSON du panneau Seoflix reste un contrôle administratif complémentaire.

L'effaceur de données personnelles enregistré par Seoflix traite seulement ce type dédié, par lots bornés. Il est placé avant l'effaceur natif WordPress afin que celui-ci ne vide pas l'e-mail de correspondance avant l'application de la politique dédiée. Une question ou réponse sans enfant est supprimée. Une question ayant des réponses d'autres membres devient un **tombstone** : auteur, e-mail, URL, IP, user-agent et `user_id` sont vidés, le corps est remplacé par un texte fixe et toutes ses lignes `wp_commentmeta` sont supprimées. Cette anonymisation préserve les réponses des autres personnes sans conserver de métadonnée personnelle sur le parent. Si une purge ou une écriture échoue, l'effaceur signale des données retenues, ne marque pas le traitement comme terminé et conserve l'e-mail de correspondance pour permettre une reprise après correction. Le bouton du panneau **Seoflix → RGPD** appelle la même règle.

Les contributions suivent la modération, les doublons, l'anti-flood et les notifications natifs WordPress ; aucune approbation n'est forcée. La fermeture des commentaires sur une vidéo ferme sa discussion. Traiter les éléments en attente ou signalés dans **Commentaires** avant une suppression de compte.

### QA runtime obligatoire avant activation

La présente documentation et les contrats source ne constituent pas une preuve runtime. Avant activation, effectuer une QA runtime sur une copie WordPress représentative et consigner les résultats :

1. les deux flags désactivés, puis chaque combinaison des flags, pour confirmer le fail-closed sans suppression de données ;
2. visiteur anonyme, membre, compte sans capacité `read` et administrateur, y compris une requête REST anonyme ;
3. vidéo publiée ouverte/fermée, brouillon, autre type de contenu, nonce expiré et tentative via `wp-comments-post.php` ;
4. question, réponse de niveau 1, réponse à une réponse, parent d'une autre vidéo ou non approuvé ;
5. limites 3/1 500 caractères, Unicode, HTML/entités, shortcode/bloc Gutenberg, toutes les formes de liens et leurs obfuscations, présence de `$_FILES` ;
6. délai anti-flood, doublon natif, contribution approuvée et en attente, notifications/modération ;
7. exporteur natif, effaceur WordPress et panneau Seoflix sur une feuille, une racine sans réponse et une racine avec réponses (tombstone) ;
8. rendu clavier/lecteur d'écran et absence de débordement à 320 px, puis purge des caches.

Lors d'une suppression du compte, lancer d'abord l'export/effacement par e-mail, vérifier les tombstones, puis supprimer le compte depuis **Utilisateurs** sans réattribuer involontairement des contenus. Documenter les sauvegardes concernées ; après toute restauration, rejouer l'effacement. Purger ensuite les caches WordPress/CDN et vérifier la page en session connectée et déconnectée.

---

## Délai de réponse

- **1 mois** à compter de la réception
- Prolongeable de **2 mois** supplémentaires si demande complexe (l'informer dans le mois)
- Demande manifestement infondée ou excessive : tu peux refuser ou facturer (rare, à motiver par écrit)

---

## Procédure étape par étape

### 1. Réception de la demande

E-mail reçu sur `contact@seoflix.fr`. Note immédiatement dans le **registre des demandes** (Google Sheet privé, Notion, ou fichier local) :

- Date de réception
- E-mail de la personne
- Droit invoqué (accès / rectification / effacement / portabilité / etc.)
- Échéance (date de réception + 1 mois)

### 2. Vérifier l'identité

Réponds en demandant :

> Bonjour,
>
> Je prends en compte ta demande au titre du RGPD. Pour traiter ta demande en toute sécurité, j'ai besoin que tu me transmettes :
>
> - Une copie de pièce d'identité (les numéros peuvent être masqués, seuls le nom et la photo doivent rester lisibles)
> - L'adresse IP que tu utilises actuellement (visible sur https://www.whatismyip.com/) ainsi qu'éventuellement les IPs précédentes que tu as utilisées pour visiter le site, si tu les connais
>
> Délai de traitement : 1 mois maximum à partir de la réception de ces éléments.
>
> Cordialement,
> Anthony Russo

### 3. Recherche dans la base Seoflix

Va dans **WP Admin → Seoflix → RGPD**. Saisis l'e-mail et/ou l'IP fournie.

Le panneau scanne :
- `wp_users` (par e-mail) — vide en V1
- `wp_comments` (par e-mail ou IP en clair)
- `wp_seoflix_affiliate_clicks` (par hash recomputé depuis l'IP fournie)

### 4. Selon le droit invoqué

#### Droit d'accès / portabilité

Clique **« Exporter en JSON »** sur la page RGPD. Envoie le fichier JSON à la personne par e-mail (chiffré si possible : 7zip avec mot de passe communiqué par un autre canal).

#### Droit à l'effacement

Clique **« Supprimer commentaires + clics affiliés »** sur la page RGPD.

Puis **traite manuellement** :

##### a) Compte utilisateur

Si un compte existe (V2 uniquement), va dans **Utilisateurs**, sélectionne le compte, **Supprimer**. Au moment de la suppression, WP demande quoi faire des contenus du compte : choisis **« Tout supprimer »** ou **« Réassigner à [admin] »** selon le contexte.

##### b) Logs Apache

Connecte-toi à **cPanel → Raw Access Logs**. Télécharge le log brut du jour (ou des derniers 30 jours). Tu peux soit :

1. **Attendre la rotation automatique** (~30j) : c'est légitime de mentionner cette durée à la personne.
2. **Purger immédiatement** : décompresse le `.gz`, ouvre dans un éditeur, supprime les lignes contenant l'IP, recompresse, ré-uploade. Ou via SSH :

   ```bash
   gunzip access.log.gz
   grep -v "192.168.1.42" access.log > access.log.clean
   mv access.log.clean access.log
   gzip access.log
   ```

##### c) Mailbox

Dans Roundcube / cPanel mail, supprime l'e-mail original de la personne (s'il y a) ainsi que la chaîne de réponse, **après avoir gardé une copie dans le registre**.

##### d) Backups

Ne PAS purger les backups (perte de capacité de restauration en cas d'incident). C'est conforme RGPD tant que :
- Les backups sont éphémères (rotation max 90j)
- La donnée est bien supprimée des copies actives
- En cas de restauration depuis backup, une re-suppression est faite

Mentionne dans la réponse à la personne : *« Vos données peuvent subsister dans nos sauvegardes pendant 30 à 90 jours, durée nécessaire à notre PRA, et seront automatiquement purgées à l'expiration. »*

##### e) Caches CDN / WP

Si Cloudflare ou WP Rocket est actif : **purger le cache** après suppression des commentaires (pour éviter qu'une page contenant un commentaire reste servie depuis le cache).

#### Droit de rectification

Édite directement la donnée concernée (commentaire, profil) depuis l'admin WP.

#### Droit d'opposition / limitation

Plus rare. Note dans le registre + ajoute la personne à une liste de blocage si nécessaire (ex: blocage IP en .htaccess).

### 5. Confirmation écrite

Réponds par e-mail :

> Bonjour,
>
> Suite à ta demande [ACCÈS / EFFACEMENT / etc.] du [DATE], j'ai procédé aux opérations suivantes :
>
> - [Nombre] commentaires supprimés
> - [Nombre] clics affiliés supprimés (anonymisés)
> - Compte utilisateur supprimé : [oui/non/non concerné]
> - Logs serveur : suppression manuelle effectuée le [DATE] / suppression automatique programmée le [DATE]
>
> [Si export :]
> En pièce jointe, l'export complet de tes données telles que conservées par Seoflix.
>
> Pour rappel, en cas de désaccord sur le traitement de ta demande, tu peux saisir la CNIL : https://www.cnil.fr/fr/plaintes
>
> Cordialement,
> Anthony Russo

### 6. Mettre à jour le registre

Note dans le registre :
- Date de réponse
- Action effectuée
- Éventuels refus motivés

---

## Conservation du registre

**5 ans** (durée de prescription en cas de litige civil). Garde-le dans un endroit privé (pas Git, pas le site WP). Suggestions :
- Notion privée
- Google Sheet partagé uniquement avec toi
- Fichier `.md` chiffré local

---

## Cas particuliers

### Demande anonyme (sans pièce d'identité)

Tu peux refuser tant que l'identité n'est pas vérifiée. Réponse type : *« Conformément à l'article 12 du RGPD, je dois m'assurer de ton identité avant de procéder à toute opération sur tes données. Merci de transmettre une pièce d'identité partiellement masquée. »*

### Demande non concernée

Si la personne demande accès/effacement mais n'a aucune donnée chez toi (cas le plus fréquent en V1) : réponds explicitement *« Aucune donnée nominative te concernant n'est conservée par Seoflix. »* Note quand même la demande au registre.

### Demande abusive ou répétée

Si quelqu'un fait 10 demandes en 1 mois pour la même chose : tu peux invoquer l'article 12.5 (demande manifestement infondée ou excessive). Refuse par écrit en motivant.

---

## Notification de violation de données (data breach)

Si tu détectes une fuite (base WP compromise, fichier de logs publiquement exposé, etc.) :

1. **72h max** pour notifier la CNIL : https://www.cnil.fr/fr/notifier-une-violation-de-donnees-personnelles
2. Si risque élevé pour les personnes : informer aussi les personnes concernées
3. Documenter la fuite dans le registre

---

## Outils utiles

- Page RGPD admin : `/wp-admin/admin.php?page=seoflix-rgpd`
- CNIL plaintes : https://www.cnil.fr/fr/plaintes
- CNIL data breach : https://www.cnil.fr/fr/notifier-une-violation-de-donnees-personnelles
- Modèle pièce d'identité : recommander à la personne d'utiliser https://francaisdeletranger.org/IMG/pdf/cni_masquee.pdf comme référence
