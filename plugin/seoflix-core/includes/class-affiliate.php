<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracker d'affiliation : route /go/{slug}/ + tracking clic en DB.
 *
 * Workflow :
 *   1. Utilisateur clique sur un lien produit → /go/linkuma/
 *   2. Plugin enregistre le clic dans wp_seoflix_affiliate_clicks
 *   3. Redirection 302 vers l'URL affiliée (ou URL officielle si pas d'URL affiliée saisie)
 *   4. Si aucune des deux URL, retour 404
 */
final class Affiliate {

	private const QUERY_VAR = 'seoflix_go';

	public static function init(): void {
		add_action( 'init',           [ self::class, 'register_rewrite' ] );
		add_filter( 'query_vars',     [ self::class, 'add_query_var' ] );
		add_action( 'template_redirect', [ self::class, 'handle_go_redirect' ], 1 );

		// Champ "URL affiliée" sur l'écran d'édition produit
		add_action( 'add_meta_boxes', [ self::class, 'register_metabox' ] );
		add_action( 'save_post_seoflix_product', [ self::class, 'save_metabox' ], 10, 2 );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule(
			'^go/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function handle_go_redirect(): void {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return;
		}

		$slug    = sanitize_title( $slug );
		$product = get_page_by_path( $slug, OBJECT, CPT::PRODUCT );

		if ( ! $product || $product->post_status !== 'publish' ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$affiliate_url = (string) get_post_meta( $product->ID, Meta_Keys::PRODUCT_AFFILIATE_URL, true );
		$official_url  = (string) get_post_meta( $product->ID, Meta_Keys::PRODUCT_OFFICIAL_URL, true );
		$target        = $affiliate_url ?: $official_url;

		if ( ! $target ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		// Tracking clic
		self::log_click( $product->ID, $affiliate_url ? 'affiliate' : 'official' );

		// Redirection 302 (pas 301 pour préserver le contrôle SEO + analytics)
		nocache_headers();
		wp_redirect( esc_url_raw( $target ), 302, 'seoflix' );
		exit;
	}

	private static function log_click( int $product_id, string $kind ): void {
		global $wpdb;
		$table = DB_Schema::table_affiliate_clicks();

		$ip          = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ip_hash     = $ip ? hash( 'sha256', $ip . wp_salt() ) : null;
		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : null;
		$referer     = isset( $_SERVER['HTTP_REFERER'] ) ? substr( (string) wp_get_referer(), 0, 255 ) : null;

		// Détection de la vidéo source via referer (si referer = page vidéo Seoflix)
		$source_video_id = null;
		if ( $referer ) {
			$ref_post_id = url_to_postid( $referer );
			if ( $ref_post_id && get_post_type( $ref_post_id ) === CPT::VIDEO ) {
				$source_video_id = $ref_post_id;
			}
		}

		$wpdb->insert( $table, [
			'product_id'      => $product_id,
			'source_video_id' => $source_video_id,
			'source_page'     => $referer ? esc_url_raw( $referer ) : null,
			'ip_hash'         => $ip_hash,
			'user_agent'      => $user_agent,
			'referer'         => $referer,
			'clicked_at'      => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ] );
	}

	/* ======================================================================
	 *  Metabox "URL affiliée" sur l'écran produit
	 * ====================================================================== */

	public static function register_metabox(): void {
		add_meta_box(
			'seoflix_product_affiliate',
			'URLs (affilié + officiel)',
			[ self::class, 'render_metabox' ],
			CPT::PRODUCT,
			'normal',
			'high'
		);
		add_meta_box(
			'seoflix_product_pricing',
			'Tarification',
			[ self::class, 'render_pricing_metabox' ],
			CPT::PRODUCT,
			'side',
			'default'
		);
	}

	public static function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'seoflix_product_meta', 'seoflix_product_nonce' );
		$affiliate = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_AFFILIATE_URL, true );
		$official  = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_OFFICIAL_URL, true );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="seoflix_affiliate_url">URL affiliée</label></th>
				<td>
					<input type="url" id="seoflix_affiliate_url" name="seoflix_affiliate_url" value="<?php echo esc_attr( $affiliate ); ?>" class="large-text code" placeholder="https://...">
					<p class="description">URL de tracking affiliée (ex : <code>https://www.linkuma.com/?ref=ton_id</code>). Utilisée par <code>/go/<?php echo esc_html( $post->post_name ); ?>/</code>. Si vide, fallback sur l'URL officielle.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_official_url">URL officielle (non-affiliée)</label></th>
				<td>
					<input type="url" id="seoflix_official_url" name="seoflix_official_url" value="<?php echo esc_attr( $official ); ?>" class="large-text code" placeholder="https://...">
					<p class="description">URL publique du produit. Utilisée comme fallback si pas d'URL affiliée.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">URL de redirection</th>
				<td>
					<code><?php echo esc_html( home_url( '/go/' . $post->post_name . '/' ) ); ?></code>
					<p class="description">Lien à utiliser dans les contenus (ajoute automatiquement <code>rel="sponsored nofollow"</code> et tracke les clics).</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_pricing_metabox( \WP_Post $post ): void {
		$current = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_PRICING, true );
		?>
		<p>
			<label>
				<input type="radio" name="seoflix_pricing" value="" <?php checked( $current, '' ); ?>>
				Non précisé
			</label><br>
			<label>
				<input type="radio" name="seoflix_pricing" value="free" <?php checked( $current, 'free' ); ?>>
				Gratuit
			</label><br>
			<label>
				<input type="radio" name="seoflix_pricing" value="freemium" <?php checked( $current, 'freemium' ); ?>>
				Freemium
			</label><br>
			<label>
				<input type="radio" name="seoflix_pricing" value="paid" <?php checked( $current, 'paid' ); ?>>
				Payant
			</label>
		</p>
		<?php
	}

	public static function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['seoflix_product_nonce'] ) || ! wp_verify_nonce( $_POST['seoflix_product_nonce'], 'seoflix_product_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['seoflix_affiliate_url'] ) ) {
			$url = trim( wp_unslash( $_POST['seoflix_affiliate_url'] ) );
			if ( $url ) {
				update_post_meta( $post_id, Meta_Keys::PRODUCT_AFFILIATE_URL, esc_url_raw( $url ) );
			} else {
				delete_post_meta( $post_id, Meta_Keys::PRODUCT_AFFILIATE_URL );
			}
		}
		if ( isset( $_POST['seoflix_official_url'] ) ) {
			$url = trim( wp_unslash( $_POST['seoflix_official_url'] ) );
			if ( $url ) {
				update_post_meta( $post_id, Meta_Keys::PRODUCT_OFFICIAL_URL, esc_url_raw( $url ) );
			} else {
				delete_post_meta( $post_id, Meta_Keys::PRODUCT_OFFICIAL_URL );
			}
		}
		if ( isset( $_POST['seoflix_pricing'] ) ) {
			$pricing = sanitize_key( $_POST['seoflix_pricing'] );
			if ( in_array( $pricing, [ 'free', 'freemium', 'paid' ], true ) ) {
				update_post_meta( $post_id, Meta_Keys::PRODUCT_PRICING, $pricing );
			} else {
				delete_post_meta( $post_id, Meta_Keys::PRODUCT_PRICING );
			}
		}
	}
}
