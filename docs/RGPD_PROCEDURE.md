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
