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
	private const PURGE_HOOK = 'seoflix_purge_affiliate_clicks';
	private const RETENTION_DAYS = 730;
	private const PURGE_BATCH = 500;

	public static function init(): void {
		add_action( 'init',           [ self::class, 'register_rewrite' ] );
		add_filter( 'query_vars',     [ self::class, 'add_query_var' ] );
		add_action( 'template_redirect', [ self::class, 'handle_go_redirect' ], 1 );
		add_action( self::PURGE_HOOK, [ self::class, 'purge_expired_clicks' ], 10, 1 );
		add_action( 'init', [ self::class, 'ensure_purge_scheduled' ], 40 );

		// Champ "URL affiliée" sur l'écran d'édition produit
		add_action( 'add_meta_boxes', [ self::class, 'register_metabox' ] );
		add_action( 'save_post_seoflix_product', [ self::class, 'save_metabox' ], 10, 2 );
	}

	public static function ensure_purge_scheduled(): void {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/** Purge bornée conformément à la durée publiée de 24 mois. */
	public static function purge_expired_clicks( string $run = 'daily' ): void {
		unset( $run );
		global $wpdb;
		$table      = DB_Schema::table_affiliate_clicks();
		$identifier = '`' . str_replace( '`', '``', $table ) . '`';
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$identifier}
			WHERE id IN (
				SELECT id FROM (
					SELECT id FROM {$identifier}
					WHERE clicked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
					ORDER BY id ASC LIMIT %d
				) expired
			)",
			self::RETENTION_DAYS,
			self::PURGE_BATCH
		) );
		if ( self::PURGE_BATCH === $deleted
			&& ! wp_next_scheduled( self::PURGE_HOOK, [ 'catchup' ] ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::PURGE_HOOK, [ 'catchup' ] );
		}
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
		$referer = wp_get_referer();

		// Détection de la vidéo source sans conserver l'URL référente.
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
			'source_page'     => null,
			'ip_hash'         => $ip_hash,
			'user_agent'      => null,
			'referer'         => null,
			'clicked_at'      => current_time( 'mysql', true ),
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
		$promo_code  = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_PROMO_CODE, true );
		$promo_offer = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_PROMO_OFFER, true );
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
			<tr>
				<th scope="row"><label for="seoflix_promo_code">Code promo</label></th>
				<td>
					<input type="text" id="seoflix_promo_code" name="seoflix_promo_code" value="<?php echo esc_attr( $promo_code ); ?>" class="regular-text code" maxlength="40" placeholder="WEAS20">
					<p class="description">Code exact à afficher dans L’ARSENAL. Laisser vide si l’offre ne nécessite pas de code.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_promo_offer">Offre sans code</label></th>
				<td>
					<input type="text" id="seoflix_promo_offer" name="seoflix_promo_offer" value="<?php echo esc_attr( $promo_offer ); ?>" class="regular-text" maxlength="80" placeholder="-20% ou 2 mois offerts">
					<p class="description">Texte affiché lorsqu’il n’existe pas de code. Le code promo reste prioritaire si les deux champs sont remplis.</p>
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
		foreach ( [
			'seoflix_promo_code'  => Meta_Keys::PRODUCT_PROMO_CODE,
			'seoflix_promo_offer' => Meta_Keys::PRODUCT_PROMO_OFFER,
		] as $field => $meta_key ) {
			if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 'seoflix_promo_code' === $field ? 40 : 80 ) : substr( $value, 0, 'seoflix_promo_code' === $field ? 40 : 80 );
			if ( '' !== $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}
}
