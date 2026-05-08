# Sécurité Seoflix

État de la sécurité du site, des fixes appliqués, et de la roadmap V2 (inscription utilisateurs).

---

## Modèle de menace

- **Site public**, pas de comptes en V1 → surface d'attaque = front + admin uniquement.
- **Hébergement** : Kimsufi/OVH cPanel + ConfigServer Firewall (CSF/LFD).
- **Frontend** : Cloudflare proxy (CDN + WAF + DDoS protection au niveau edge).
- **Stack** : WordPress + plugin custom + thème custom + FluentSMTP.

Risques principaux ciblés :
1. Spam et abus du formulaire de contact
2. Bruteforce du login admin
3. SSRF/XXE via fetch d'URL externes (logos produits)
4. Injection XSS / SQL via inputs publics
5. Exposition de données sensibles via REST/admin
6. Manipulation de fichiers (.htaccess, uploads)

---

## Fixes appliqués (v0.13+)

### Headers HTTP (`Seoflix\Security::send_security_headers`)

| Header | Valeur | Rôle |
|---|---|---|
| `Content-Security-Policy` | scoped self + youtube + cloudflare + gravatar | Anti-XSS et anti-injection scripts tiers |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | Force HTTPS pendant 1 an, chez tous les sous-domaines |
| `X-Frame-Options` | `SAMEORIGIN` | Anti-clickjacking |
| `X-Content-Type-Options` | `nosniff` | Anti-MIME-sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limite la fuite d'URLs au referrer |
| `Permissions-Policy` | `interest-cohort=(), browsing-topics=()` | Désactive FLoC / Topics |
| `Cross-Origin-Opener-Policy` | `same-origin-allow-popups` | Isolation contexte JS |

### Anti-bruteforce login (`Seoflix\Security`)

- **5 échecs** depuis la même IP en **15 min** → **lock 30 min**
- IP réelle détectée via `CF-Connecting-IP` (Cloudflare) ou `X-Forwarded-For` (autre proxy)
- Reset auto au login réussi
- Erreurs login génériques (`Identifiants invalides.`) — pas d'indication user vs password
- XML-RPC désactivé (vecteur bruteforce courant)
- `?author=N` redirige vers home (anti-énumération)
- `/wp-json/wp/v2/users` retiré du REST (anti-énumération)

### `DISALLOW_FILE_EDIT` forcé

Empêche l'édition de fichiers plugin/thème via le dashboard, même si un admin se fait compromettre. Forcé en runtime par le plugin si pas déjà défini dans `wp-config.php`.

### Formulaire contact (`Seoflix\Contact`)

- **Honeypot champ caché** + **honeypot temporel** (rejet si form rempli en < 3s)
- **Cloudflare Turnstile** (optionnel, configurable dans Réglages) — anti-bot sans CAPTCHA visuel
- **Rate-limit avec backoff exponentiel** : 30s → 60s → 120s → 300s pour récidivistes (compteur sur 24h)
- **Détection messages dupliqués** : même message = silencieusement ignoré pendant 1h
- **Sanitization CRLF** sur tous les champs → bloque l'injection de headers e-mail (`Bcc`, `Cc`, `From` injectés)
- **CRLF strip + esc_url_raw** sur User-Agent et Referer stockés → anti log-injection
- **Nonce WP** + check capability via admin-post

### SSRF + XXE (`Seoflix\Admin\Product_Logo`)

- Fetch externe (Clearbit / Google favicons) bloque :
  - Hostnames `localhost`, `metadata.google.internal`
  - IPs privées (10.x, 172.16.x, 192.168.x), loopback (127.x), link-local (169.254.x)
  - Résolution DNS → check sur les IPs résolues
- SVG : strip `<!ENTITY>`, `<!DOCTYPE>`, `SYSTEM`, `<script>`, attributs `on*` → anti-XXE et anti-script-injection

### Path traversal (`Seoflix\Admin\Admin_SEO_Tools`)

- L'éditeur `.htaccess` valide :
  - Format strict du nom de backup (regex)
  - Pas de `..`, `/`, `\` dans le nom
  - `realpath()` pour vérifier que le path résolu reste dans `seoflix-backups/`

### Affiliate redirect (`Seoflix\Affiliate`)

- IPs hashées (SHA-256 + `wp_salt`) — pas d'IP en clair
- URLs de redirect uniquement depuis post meta admin-controlled (pas d'open redirect possible)
- Nonces sur les formulaires d'admin

### RGPD export

- Pas d'`unserialize()` à l'export → JSON contient les valeurs brutes (sécurise contre une éventuelle ré-importation malveillante)

---

## Recommandations serveur (à faire dans WHM/cPanel)

### `wp-config.php` — ajouter

```php
define( 'DISALLOW_FILE_EDIT', true );      // bloque édition via dashboard (forcé aussi côté plugin)
define( 'DISALLOW_FILE_MODS', true );      // bloque l'install de plugins/thèmes via UI (en prod)
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'AUTOMATIC_UPDATER_DISABLED', false ); // laisse les màj de sécurité auto
```

### `.htaccess` — hardening (ajouter dans Seoflix → .htaccess)

```apache
# Bloque l'accès aux fichiers sensibles
<FilesMatch "^(wp-config|readme|license|version)\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Bloque les fichiers .git, .env, .DS_Store, backups
<FilesMatch "^\.(git|env|svn|hg)|^.*\.(bak|backup|old|orig|sql|swp|tar|zip)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Désactive le directory listing
Options -Indexes

# Bloque l'exécution PHP dans /uploads (anti-shell upload)
<Directory "wp-content/uploads">
    <FilesMatch "\.(php|php5|php7|phtml)$">
        Order Allow,Deny
        Deny from all
    </FilesMatch>
</Directory>
```

### Cloudflare — règles à ajouter

- **Rate Limiting Rule** : POST sur `/wp-login.php` → 5 req/min/IP, action Block
- **Rate Limiting Rule** : POST sur `/wp-admin/admin-post.php` → 30 req/min/IP, action Challenge
- **WAF Custom Rule** : bloquer `(http.request.uri.path contains "xmlrpc.php")` → Block
- **Bot Fight Mode** : Activer (Pro plan) ou Bot Management (Enterprise)
- **Page Rules** : `seoflix.fr/wp-admin/*` → Security Level: High
- **DDoS Protection** : par défaut activé, vérifier dans Security → DDoS

### CSF/LFD (déjà investigué)

- `SMTP_BLOCK = Off` ou `SMTP_ALLOWUSER = seoflix` (whitelist user)
- `LF_SMTPRELAY` raisonnable (≥ 100 mails/heure pour le user seoflix)
- `LF_TRIGGER` actif (alertes mail sur events suspects)

### File permissions standard

```bash
find /home/seoflix/public_html -type d -exec chmod 755 {} \;
find /home/seoflix/public_html -type f -exec chmod 644 {} \;
chmod 600 /home/seoflix/public_html/wp-config.php
```

---

## Procédure à suivre en cas d'incident

1. **Compromission soupçonnée** :
   - Couper l'accès admin temporairement (Cloudflare → page rule "block /wp-admin")
   - Changer tous les mots de passe (admin WP, FTP/SSH, BDD, FluentSMTP, Cloudflare)
   - Vérifier `wp-content/uploads/` à la recherche de fichiers PHP suspects
   - Vérifier `wp-content/plugins/` à la recherche de plugins ajoutés sans toi
   - Vérifier les utilisateurs WP (`SELECT * FROM wp_users` via cPanel phpMyAdmin)
2. **Restauration** :
   - Restore depuis backup propre (Cloudways/cPanel JetBackup)
   - Update WordPress + tous les plugins après
   - Re-scanner le site avec [Sucuri SiteCheck](https://sitecheck.sucuri.net) pour confirmer le clean
3. **Post-mortem** :
   - Notifier la CNIL sous 72h si donnée perso compromise (voir `docs/RGPD_PROCEDURE.md`)
   - Logger l'incident, documenter les IOCs

---

## Avant d'activer V2 (comptes utilisateurs) — checklist

À faire **AVANT** de toggle `seoflix_user_accounts_enabled = true` :

- [ ] **Captcha sur l'inscription** : Turnstile obligatoire (la clé est déjà configurée pour le contact, ré-utiliser)
- [ ] **Email verification** : double opt-in (envoi token, clic obligatoire pour activer)
- [ ] **Password strength** : min 12 caractères, complexité (chiffres + majuscules + symboles)
- [ ] **Rate-limit registrations** : 1 inscription / IP / heure
- [ ] **Rate-limit password reset** : 3 / IP / heure
- [ ] **Account lockout** : 5 échecs login → 30min lock (déjà en place pour admin, étendre aux users)
- [ ] **Profile picture upload** : whitelist MIME (jpeg, png, webp), max 2 MB, scan ClamAV si dispo
- [ ] **2FA admin** : plugin Two-Factor (officiel WP) ou WP-CLI
- [ ] **Session timeout** : 30 min inactivité forcée
- [ ] **Audit log** : log connexions, changements profil, password resets dans table dédiée
- [ ] **CSP nonce-based** : remplacer `'unsafe-inline'` par nonces (gros refactor, peut attendre V2.1)
- [ ] **Termes & conditions** : page CGU à créer avant inscription
- [ ] **Vérifier que les tables `wp_seoflix_favorites` et `wp_seoflix_watch` sont vides** (V2 dormant, doivent rester vides en V1)

---

## Audit régulier

- **Mensuel** : `wp plugin list --format=json` → vérifier qu'il n'y a pas de plugins inattendus
- **Mensuel** : `gh pr list --state merged --search "security"` sur le repo seoflix → revue des fixes mergés
- **Hebdo** : Sucuri SiteCheck (gratuit) → scan du site
- **Hebdo** : Cloudflare → Security Events → revue des alertes

---

## Out of scope V1

- **Web Application Firewall serveur** (mod_security) : redondant avec Cloudflare
- **Plugin Wordfence/Sucuri** : volontairement écarté (bloat, surface d'attaque, conflits potentiels)
- **DAST scanner automatique** : pas le ROI en V1, à reconsidérer V2
- **Bug bounty** : trop tôt, à envisager après V2 stable
