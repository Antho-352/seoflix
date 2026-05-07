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
		add_action( 'admin_post_seoflix_create_legal_pages', [ self::class, 'handle_create' ] );
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
		];
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
		<p class="description">Crée <code>/affiliation</code>, <code>/mentions-legales</code> et <code>/confidentialite</code> avec un contenu modèle. Idempotent — n'écrase pas une page déjà existante.</p>
		<?php
	}

	/* ====== Contenus ====== */

	private static function content_affiliation(): string {
		return <<<HTML
<!-- wp:paragraph --><p>Seoflix utilise des liens d'affiliation pour financer la plateforme. Cela signifie que lorsque tu cliques sur un lien vers un produit ou service mentionné dans une vidéo ou sur une page outil, et que tu effectues un achat, Seoflix peut percevoir une commission de la part du vendeur, sans coût supplémentaire pour toi.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Comment identifier un lien d'affiliation</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Tous les liens d'affiliation sont mentionnés explicitement et passent par une URL de redirection sous la forme <code>seoflix.fr/go/[nom-du-produit]</code>. Ils portent l'attribut <code>rel="sponsored nofollow"</code> conforme aux recommandations Google.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les blocs « Produits & services mentionnés » sur les pages vidéos sont identifiés par la mention « Liens affiliés ».</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Notre engagement éditorial</h2><!-- /wp:heading -->
<!-- wp:list --><ul>
<li>Aucun produit n'est mis en avant sur Seoflix uniquement parce qu'il offre une commission.</li>
<li>Les vidéos référencées sont sélectionnées sur des critères qualité (pertinence, profondeur, retours d'expérience), indépendamment des programmes d'affiliation.</li>
<li>Les descriptions des produits sont factuelles et n'engagent pas Seoflix sur les performances réelles.</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Programmes utilisés</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Seoflix peut utiliser, entre autres, les programmes d'affiliation des plateformes suivantes : Linkuma, Semrush, Ahrefs, RocketLinks, Ereferer, Awin, Kwanko, ainsi que des programmes directs auprès des éditeurs de logiciels SaaS référencés.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Tes droits</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Tu peux à tout moment décider de ne pas passer par les liens d'affiliation et accéder directement aux sites officiels des produits via un moteur de recherche. Aucun cookie tiers n'est déposé par Seoflix sans ton consentement.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Pour toute question, contacte-nous à <a href="mailto:contact@seoflix.fr">contact@seoflix.fr</a>.</p><!-- /wp:paragraph -->
HTML;
	}

	private static function content_mentions(): string {
		return <<<HTML
<!-- wp:heading --><h2>Éditeur du site</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p><strong>Seoflix</strong> est édité par Anthony Russo, entrepreneur individuel.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li><strong>Directeur de la publication</strong> : Anthony Russo</li>
<li><strong>SIRET</strong> : 98497752000019</li>
<li><strong>Adresse e-mail</strong> : contact@seoflix.fr</li>
<li><strong>Adresse postale</strong> : [à compléter]</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Hébergeur</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Le site est hébergé par OVH SAS.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>2 rue Kellermann, 59100 Roubaix, France</li>
<li>Téléphone : 09 72 10 10 07</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Propriété intellectuelle</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Le contenu rédactionnel de Seoflix (descriptions des vidéos, textes des pages, organisation des catégories) est la propriété d'Anthony Russo. Toute reproduction sans autorisation est interdite.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les vidéos référencées sur Seoflix sont la propriété intellectuelle de leurs auteurs respectifs (les chaînes YouTube référencées). Seoflix se contente de fournir une interface d'agrégation et utilise le lecteur YouTube embarqué officiel pour la lecture, conformément aux conditions d'utilisation YouTube.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Les marques et logos des produits et services référencés (Linkuma, Semrush, Ahrefs, etc.) appartiennent à leurs propriétaires respectifs.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Liens affiliés</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Seoflix utilise des liens d'affiliation. Pour plus de détails, voir la page <a href="/affiliation/">Politique d'affiliation</a>.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Limitation de responsabilité</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Les informations diffusées sur Seoflix sont données à titre informatif. Les performances en SEO, affiliation, vente de liens ou tout autre business mentionné dépendent de nombreux facteurs et ne peuvent être garanties. Seoflix ne saurait être tenu responsable des décisions prises sur la base des contenus référencés.</p><!-- /wp:paragraph -->
HTML;
	}

	private static function content_privacy(): string {
		return <<<HTML
<!-- wp:heading --><h2>Données collectées</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Seoflix collecte le minimum de données nécessaires à son fonctionnement :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li><strong>Statistiques de visite</strong> : pages consultées, durée, source (referer). Ces données sont agrégées et anonymisées.</li>
<li><strong>Clics sur liens d'affiliation</strong> : nombre de clics par produit, page source du clic, hash anonymisé de l'IP (SHA-256 + sel) pour empêcher les abus, user-agent. Aucune adresse IP en clair n'est conservée.</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Aucune donnée personnelle nominative n'est collectée tant que tu n'as pas créé de compte (les comptes utilisateurs ne sont pas activés en V1).</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Cookies</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Seoflix utilise uniquement des cookies fonctionnels strictement nécessaires (session WP). Aucun cookie publicitaire ni de tracking tiers n'est déposé par Seoflix.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Le lecteur vidéo YouTube intégré utilise le mode <code>youtube-nocookie.com</code> qui ne dépose des cookies qu'au moment où tu démarres la lecture d'une vidéo. Les cookies déposés à ce moment sont gérés par YouTube/Google.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Tes droits (RGPD)</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Conformément au Règlement Général sur la Protection des Données (RGPD), tu disposes des droits suivants :</p><!-- /wp:paragraph -->
<!-- wp:list --><ul>
<li>Droit d'accès aux données te concernant</li>
<li>Droit de rectification</li>
<li>Droit à l'effacement</li>
<li>Droit à la limitation du traitement</li>
<li>Droit à la portabilité</li>
<li>Droit d'opposition</li>
</ul><!-- /wp:list -->
<!-- wp:paragraph --><p>Pour exercer ces droits, contacte-nous à <a href="mailto:contact@seoflix.fr">contact@seoflix.fr</a>.</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Durée de conservation</h2><!-- /wp:heading -->
<!-- wp:list --><ul>
<li>Logs de clics affiliés : 24 mois</li>
<li>Statistiques de visite agrégées : indéfiniment (anonymes)</li>
</ul><!-- /wp:list -->

<!-- wp:heading --><h2>Sécurité</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Les données sont hébergées sur un serveur dédié en France. Le site est servi en HTTPS. Les mots de passe administrateurs sont stockés hashés (bcrypt natif WordPress).</p><!-- /wp:paragraph -->

<!-- wp:heading --><h2>Contact</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Pour toute question relative à la confidentialité : <a href="mailto:contact@seoflix.fr">contact@seoflix.fr</a>.</p><!-- /wp:paragraph -->
HTML;
	}
}
