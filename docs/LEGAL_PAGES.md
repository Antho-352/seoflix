# Pages légales — contenus à créer dans WordPress

Trois pages référencées par le footer du thème. À créer manuellement dans **Pages → Ajouter** avec les slugs exacts indiqués.

> **Avertissement** : ces textes sont des modèles fonctionnels conformes aux pratiques courantes. **Faire valider par un avocat ou conseil juridique** avant publication réelle, en particulier pour les mentions de l'éditeur (SIRET, adresse, etc.) et la politique d'affiliation au regard de la DGCCRF.

---

## Page 1 — `/affiliation/`

**Titre** : Politique d'affiliation
**Slug** : `affiliation`

```
WEAS utilise des liens d'affiliation pour financer la plateforme. Cela signifie que lorsque tu cliques sur un lien vers un produit ou service mentionné dans une vidéo ou sur une page outil, et que tu effectues un achat, WEAS peut percevoir une commission de la part du vendeur, sans coût supplémentaire pour toi.

## Comment identifier un lien d'affiliation

Tous les liens d'affiliation sont mentionnés explicitement et passent par une URL de redirection sous la forme `weas.fr/go/[nom-du-produit]`. Ils portent l'attribut `rel="sponsored nofollow"` conforme aux recommandations Google.

Les blocs « Produits & services mentionnés » sur les pages vidéos sont identifiés par la mention « Liens affiliés ».

## Notre engagement éditorial

- Aucun produit n'est mis en avant sur WEAS uniquement parce qu'il offre une commission.
- Les vidéos référencées sont sélectionnées sur des critères qualité (pertinence, profondeur, retours d'expérience), indépendamment des programmes d'affiliation.
- Les descriptions des produits sont factuelles et n'engagent pas WEAS sur les performances réelles.

## Programmes utilisés

WEAS peut utiliser, entre autres, les programmes d'affiliation des plateformes suivantes : Linkuma, Semrush, Ahrefs, RocketLinks, Ereferer, Awin, Kwanko, ainsi que des programmes directs auprès des éditeurs de logiciels SaaS référencés.

## Tes droits

Tu peux à tout moment décider de ne pas passer par les liens d'affiliation et accéder directement aux sites officiels des produits via un moteur de recherche. Aucun cookie tiers n'est déposé par WEAS sans ton consentement.

Pour toute question, contacte-nous à contact@weas.fr.
```

---

## Page 2 — `/mentions-legales/`

**Titre** : Mentions légales
**Slug** : `mentions-legales`

```
## Éditeur du site

**WEAS** est édité par Anthony Russo, entrepreneur individuel.

- **Directeur de la publication** : Anthony Russo
- **SIRET** : 98497752000019
- **Adresse e-mail** : contact@weas.fr
- **Adresse postale** : [à compléter]

## Hébergeur

Le site est hébergé par OVH SAS, infrastructure Kimsufi exploitée via HestiaCP.

- 2 rue Kellermann, 59100 Roubaix, France
- Téléphone : 09 72 10 10 07

## Propriété intellectuelle

Le contenu rédactionnel de WEAS (descriptions des vidéos, textes des pages, organisation des catégories) est la propriété d'Anthony Russo. Toute reproduction sans autorisation est interdite.

Les vidéos référencées sur WEAS sont la propriété intellectuelle de leurs auteurs respectifs (les chaînes YouTube référencées). WEAS se contente de fournir une interface d'agrégation et utilise le lecteur YouTube embarqué officiel pour la lecture, conformément aux conditions d'utilisation YouTube.

Les marques et logos des produits et services référencés (Linkuma, Semrush, Ahrefs, etc.) appartiennent à leurs propriétaires respectifs.

## Liens affiliés

WEAS utilise des liens d'affiliation. Pour plus de détails, voir la page [Politique d'affiliation](/affiliation/).

## Limitation de responsabilité

Les informations diffusées sur WEAS sont données à titre informatif. Les performances en SEO, affiliation, vente de liens ou tout autre business mentionné dépendent de nombreux facteurs et ne peuvent être garanties. WEAS ne saurait être tenu responsable des décisions prises sur la base des contenus référencés.
```

---

## Page 3 — `/confidentialite/`

**Titre** : Politique de confidentialité
**Slug** : `confidentialite`

```
## Données collectées

WEAS collecte le minimum de données nécessaires à son fonctionnement :

- **Statistiques de visite** : pages consultées, durée, source (referer). Ces données sont agrégées et anonymisées.
- **Clics sur liens d'affiliation** : nombre de clics par produit, page source du clic, hash anonymisé de l'IP (SHA-256 + sel) pour empêcher les abus, user-agent. Aucune adresse IP en clair n'est conservée.

Aucune donnée personnelle nominative n'est collectée tant que tu n'as pas créé de compte (les comptes utilisateurs ne sont pas activés en V1).

## Cookies

WEAS utilise uniquement des cookies fonctionnels strictement nécessaires (session WP). Aucun cookie publicitaire ni de tracking tiers n'est déposé par WEAS.

Le lecteur vidéo YouTube intégré utilise le mode `youtube-nocookie.com` qui ne dépose des cookies qu'au moment où tu démarres la lecture d'une vidéo. Les cookies déposés à ce moment sont gérés par YouTube/Google.

## Tes droits (RGPD)

Conformément au Règlement Général sur la Protection des Données (RGPD), tu disposes des droits suivants :

- Droit d'accès aux données te concernant
- Droit de rectification
- Droit à l'effacement
- Droit à la limitation du traitement
- Droit à la portabilité
- Droit d'opposition

Pour exercer ces droits, contacte-nous à contact@weas.fr.

## Durée de conservation

- Logs de clics affiliés : 24 mois
- Statistiques de visite agrégées : indéfiniment (anonymes)

## Sécurité

Les données sont hébergées sur un serveur Kimsufi administré via HestiaCP en France. Le site est servi en HTTPS. Les mots de passe administrateurs sont stockés hashés (bcrypt natif WordPress).

## Contact

Pour toute question relative à la confidentialité : contact@weas.fr.
```

---

## Création dans WordPress

1. **Pages → Ajouter**
2. Coller le titre et le contenu de chaque page (en utilisant le bloc « Paragraphe » classique pour le contenu)
3. Vérifier que le **slug** correspond exactement (panneau de droite → URL → modifier)
4. Publier

Une fois les trois pages créées, les liens du footer (`/affiliation/`, `/mentions-legales/`, `/confidentialite/`) fonctionneront.
