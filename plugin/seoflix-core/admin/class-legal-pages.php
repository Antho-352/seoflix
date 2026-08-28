<?php
namespace Seoflix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Génère les 3 pages légales (Mentions légales, Politique de confidentialité,
 * Politique d'affiliation) en idempotent. Bouton dans Réglages.
 *
 * Si la page existe déjà avec le bon slug, on ne touche pas à son contenu
 * (l'utilisateur peut l'avoir édité). On signale juste qu'elle existe.
 */
final class Legal_Pages {

	private const NONCE = 'seoflix_create_legal_pages';

	public static function init(): void {
		add_action( 'admin_post_seoflix_create_legal_pages',     [ self::class, 'handle_create' ] );
		add_action( 'admin_post_seoflix_regenerate_legal_pages', [ self::class, 'handle_regenerate' ] );
		add_action( 'admin_notices', [ self::class, 'admin_notice' ] );
	}

	public static function pages_definition(): array {
		return [
			'affiliation' => [
				'title'   => "Politique d'affiliation",
				'content' => self::content_affiliation(),
			],
			'mentions-legales' => [
				'title'   => 'Mentions légales',
				'content' => self::content_mentions(),
			],
			'confidentialite' => [
				'title'   => 'Politique de confidentialité',
				'content' => self::content_privacy(),
			],
			'contact' => [
				'title'   => 'Contact',
				'content' => self::content_contact(),
			],
		];
	}

	private static function content_contact(): string {
		return <<<HTML
<!-- wp:paragraph --><p>Une question, une suggestion, un partenariat, une demande d'ajout de chaîne ou de produit ? Utilise le formulaire ci-dessous, je réponds sous 48h ouvrées.</p><!-- /wp:paragraph -->

<!-- wp:shortcode -->
[seoflix_contact_form]
<!-- /wp:shortcode -->

<!-- wp:paragraph --><p>Pour les demandes RGPD (accès, effacement de tes données), précise-le dans le sujet du message — voir aussi la <a href="/confidentialite/">politique de confidentialité</a>.</p><!-- /wp:paragraph -->
HTML;
	}

	public static function handle_create(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		$created = 0;
		$existing = 0;
		foreach ( self::pages_definition() as $slug => $data ) {
			$existing_page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing_page ) {
				$existing++;
				continue;
			}
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_title'   => $data['title'],
				'post_name'    => $slug,
				'post_content' => $data['content'],
				'post_status'  => 'publish',
			], true );
			if ( ! is_wp_error( $id ) ) {
				$created++;
			}
		}

		$redirect = add_query_arg( [
			'page'                  => 'seoflix-settings',
			'seoflix_legal_created' => $created,
			'seoflix_legal_existing' => $existing,
		], admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function admin_notice(): void {
		if ( isset( $_GET['seoflix_legal_regenerated'] ) ) {
			$updated = (int) $_GET['seoflix_legal_regenerated'];
			$created = (int) ( $_GET['seoflix_legal_created'] ?? 0 );
			printf(
				'<div class="notice notice-success is-dismissible"><p><strong>%d</strong> page(s) régénérée(s)%s. Vérifie le contenu mis à jour.</p></div>',
				$updated,
				$created > 0 ? sprintf( ', <strong>%d</strong> nouvelle(s) page(s) créée(s)', $created ) : ''
			);
			return;
		}
		if ( ! isset( $_GET['seoflix_legal_created'] ) ) {
			return;
		}
		$created  = (int) $_GET['seoflix_legal_created'];
		$existing = (int) ( $_GET['seoflix_legal_existing'] ?? 0 );
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%d</strong> page(s) légale(s) créée(s)%s. Vérifie et ajuste les contenus avant publication réelle (notamment SIRET, adresse).</p></div>',
			$created,
			$existing > 0 ? sprintf( ', <strong>%d</strong> déjà présente(s) (non modifiée(s))', $existing ) : ''
		);
	}

	public static function render_button(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<input type="hidden" name="action" value="seoflix_create_legal_pages">
			<?php wp_nonce_field( self::NONCE ); ?>
			<button type="submit" class="button">Créer les 3 pages légales</button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline; margin-left: 0.5rem;" onsubmit="return confirm('Forcer la régénération va ÉCRASER le contenu actuel des 3 pages avec la dernière version du modèle. Tes éditions manuelles seront perdues. Continuer ?');">
			<input type="hidden" name="action" value="seoflix_regenerate_legal_pages">
			<?php wp_nonce_field( self::NONCE ); ?>
			<button type="submit" class="button">Régénérer le contenu</button>
		</form>
		<p class="description">Crée <code>/affiliation</code>, <code>/mentions-legales</code> et <code>/confidentialite</code> avec un contenu modèle. Le bouton « Créer » est idempotent (n'écrase pas une page existante). Le bouton « Régénérer » force la mise à jour avec la dernière version du modèle (ex: après mise à jour du plugin).</p>
		<?php
	}

	public static function handle_regenerate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		$updated = 0;
		$created = 0;
		foreach ( self::pages_definition() as $slug => $data ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing ) {
				wp_update_post( [
					'ID'           => $existing->ID,
					'post_content' => $data['content'],
					'post_title'   => $data['title'],
				] );
				$updated++;
			} else {
				wp_insert_post( [
					'post_type'    => 'page',
					'post_title'   => $data['title'],
					'post_name'    => $slug,
					'post_content' => $data['content'],
					'post_status'  => 'publish',
				] );
				$created++;
			}
		}

		$redirect = add_query_arg( [
			'page'                       => 'seoflix-settings',
			'seoflix_legal_regenerated'  => $updated,
			'seoflix_legal_created'      => $created,
		], admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/* ====== Contenus ====== */

	private static function content_affiliation(): string {
		return <<<HTML
<!-- wp:paragraph --><p>WEAS utilise des liens d'affiliation pour financer la plateforme. Cela signifie que lorsque tu cliques sur un lien vers un produit ou service mentionné dans une vidéo ou sur une page outil, et que tu effectues un achat, WEAS peut percevoir une commission de la part du vendeur, sans coût supplémentaire pour toi.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Comment identifier un lien d'affiliation</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Tous les liens d'affiliation sont mentionnés explicitement et passent par une URL de redirection sous la forme <code>weas.fr/go/[nom-du-produit]</code>. Ils portent l'attribut <code>rel="sponsored nofollow"</code> conforme aux recommandations Google.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les blocs « Produits & services mentionnés » sur les pages vidéos sont identifiés par la mention « Liens affiliés ».</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Notre engagement éditorial</h2><!-- /wp:heading -->
<!-- wp:list --><ul>
<li>Aucun produit n'est mis en avant sur WEAS uniquement parce qu'il offre une commission.</li>
<li>Les vidéos référencées sont sélectionnées sur des critères qualité (pertinence, profondeur, retours d'expérience), indépendamment des programmes d'affiliation.</li>
<li>Les descriptions des produits sont factuelles et n'engagent pas WEAS sur les performances réelles.</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Programmes utilisés</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>WEAS peut utiliser, entre autres, les programmes d'affiliation des plateformes suivantes : Linkuma, Semrush, Ahrefs, RocketLinks, Ereferer, Awin, Kwanko, ainsi que des programmes directs auprès des éditeurs de logiciels SaaS référencés.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Tes droits</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Tu peux à tout moment décider de ne pas passer par les liens d'affiliation et accéder directement aux sites officiels des produits via un moteur de recherche. Aucun cookie tiers n'est déposé par WEAS sans ton consentement.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Pour toute question, contacte-nous à <a href="mailto:contact@weas.fr">contact@weas.fr</a>.</p><!-- /wp:paragraph -->
HTML;
	}

	private static function content_mentions(): string {
		return <<<HTML
<!-- wp:heading --><h2>Éditeur du site</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p><strong>WEAS</strong> est édité par Anthony Russo, entrepreneur individuel.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li><strong>Directeur de la publication</strong> : Anthony Russo</li>
<li><strong>SIRET</strong> : 98497752000019</li>
<li><strong>Adresse e-mail</strong> : contact@weas.fr</li>
<li><strong>Adresse postale</strong> : [à compléter]</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Hébergeur</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Le site est hébergé par OVH SAS.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>2 rue Kellermann, 59100 Roubaix, France</li>
<li>Téléphone : 09 72 10 10 07</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Propriété intellectuelle</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Le contenu rédactionnel de WEAS (descriptions des vidéos, textes des pages, organisation des catégories) est la propriété d'Anthony Russo. Toute reproduction sans autorisation est interdite.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les vidéos référencées sur WEAS sont la propriété intellectuelle de leurs auteurs respectifs (les chaînes YouTube référencées). WEAS se contente de fournir une interface d'agrégation et utilise le lecteur YouTube embarqué officiel pour la lecture, conformément aux conditions d'utilisation YouTube.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les marques et logos des produits et services référencés (Linkuma, Semrush, Ahrefs, etc.) appartiennent à leurs propriétaires respectifs.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Liens affiliés</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>WEAS utilise des liens d'affiliation. Pour plus de détails, voir la page <a href="/affiliation/">Politique d'affiliation</a>.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Limitation de responsabilité</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Les informations diffusées sur WEAS sont données à titre informatif. Les performances en SEO, affiliation, vente de liens ou tout autre business mentionné dépendent de nombreux facteurs et ne peuvent être garanties. WEAS ne saurait être tenu responsable des décisions prises sur la base des contenus référencés.</p><!-- /wp:paragraph -->
HTML;
	}

	private static function content_privacy(): string {
		return <<<HTML
<!-- wp:heading --><h2>Données collectées</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>WEAS collecte le minimum de données nécessaires à son fonctionnement :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li><strong>Logs serveur</strong> (Apache) : adresse IP, date/heure, page consultée, user-agent. Conservés pendant <strong>30 jours maximum</strong>, puis purgés automatiquement par l'hébergeur.</li>
<li><strong>Clics sur liens d'affiliation</strong> : produit cliqué, page source du clic, hash anonymisé de l'IP (SHA-256 + sel propre au site) pour empêcher les abus, user-agent. <strong>Aucune adresse IP en clair n'est conservée</strong> dans la base de WEAS : seul le hash irréversible l'est.</li>
<li><strong>Commentaires</strong> (si activés) : pseudonyme, e-mail, contenu, IP, user-agent. Stockés tant que le commentaire est publié ou modéré.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Aucune donnée personnelle nominative n'est collectée tant que tu n'as pas créé de compte. Les comptes utilisateurs ne sont pas activés en V1 — il est donc impossible de t'inscrire.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Finalités</h2><!-- /wp:heading -->
<!-- wp:list --><ul>
<li>Logs serveur : sécurité (détection d'attaques, fail2ban) et debug technique</li>
<li>Clics affiliés (hashés) : statistiques agrégées sur les produits qui intéressent les visiteurs, prévention des fraudes</li>
<li>Commentaires : modération anti-spam (Akismet le cas échéant)</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Cookies</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>WEAS utilise des cookies fonctionnels strictement nécessaires. Microsoft Clarity n'est activé qu'après ton consentement explicite aux statistiques. Le refus n'empêche pas d'utiliser le site et ton choix peut être modifié à tout moment via « Gérer mes cookies ».</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Après acceptation, WEAS et Microsoft utilisent Clarity pour traiter des données techniques et d'interaction, produire des statistiques, des cartes de chaleur et des relectures de session afin d'améliorer l'ergonomie du site. Clarity peut alors utiliser des cookies de première et de tierce parties. Consulte la <a href="https://privacy.microsoft.com/fr-fr/privacystatement" target="_blank" rel="noopener noreferrer">Politique de confidentialité Microsoft</a>.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Le lecteur vidéo YouTube intégré utilise le mode <code>youtube-nocookie.com</code> qui ne dépose des cookies qu'au moment où tu démarres la lecture d'une vidéo. Les cookies déposés à ce moment sont gérés par YouTube/Google et soumis à leur propre politique de confidentialité.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Tes droits (RGPD)</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Conformément au Règlement Général sur la Protection des Données (RGPD), tu disposes des droits suivants :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li><strong>Droit d'accès</strong> : obtenir une copie des données te concernant</li>
<li><strong>Droit de rectification</strong> : corriger une donnée inexacte</li>
<li><strong>Droit à l'effacement</strong> (« droit à l'oubli ») : faire supprimer tes données</li>
<li><strong>Droit à la limitation</strong> : geler le traitement de tes données</li>
<li><strong>Droit à la portabilité</strong> : recevoir tes données dans un format lisible</li>
<li><strong>Droit d'opposition</strong> : t'opposer à un traitement</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Comment exercer ces droits</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Pour exercer l'un de ces droits, envoie un e-mail à <a href="mailto:contact@weas.fr">contact@weas.fr</a> en précisant :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>Le droit que tu souhaites exercer</li>
<li>Une preuve d'identité (copie de pièce d'identité partiellement masquée — seul ton nom et ta photo doivent rester lisibles)</li>
<li>L'adresse IP que tu utilises ou as utilisée pour visiter WEAS (utile pour identifier d'éventuels clics affiliés)</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p><strong>Délai de réponse</strong> : 1 mois à compter de la réception de la demande, prolongeable de 2 mois supplémentaires en cas de demande complexe (tu seras informé de la prolongation et de ses motifs dans le mois suivant la demande).</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>L'exercice de ces droits est gratuit, sauf si la demande est manifestement infondée ou excessive.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Recours auprès de la CNIL</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Si tu estimes, après avoir contacté WEAS, que tes droits Informatique et Libertés ne sont pas respectés, tu peux adresser une réclamation à la <strong>Commission Nationale de l'Informatique et des Libertés (CNIL)</strong> :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>En ligne : <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">cnil.fr/fr/plaintes</a></li>
<li>Par courrier : 3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Durée de conservation</h2><!-- /wp:heading -->
<!-- wp:list --><ul>
<li><strong>Logs serveur Apache</strong> : 30 jours puis purge automatique</li>
<li><strong>Clics affiliés (hashés)</strong> : 24 mois</li>
<li><strong>Commentaires</strong> : tant qu'ils sont publiés ou en modération</li>
<li><strong>Statistiques de visite agrégées</strong> : indéfiniment (anonymes, non rattachables à une personne)</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Sécurité</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Les données sont hébergées sur un serveur dédié en France (OVH/Kimsufi). Le site est servi exclusivement en HTTPS. Les mots de passe administrateurs sont stockés hashés (bcrypt natif WordPress, jamais en clair).</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Contact</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Pour toute question relative à la confidentialité ou au traitement de tes données : <a href="mailto:contact@weas.fr">contact@weas.fr</a>.</p><!-- /wp:paragraph -->
HTML;
	}
}
