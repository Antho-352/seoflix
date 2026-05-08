<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\Meta_Keys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Récupération automatique du logo d'un produit depuis son URL officielle.
 *
 * Stratégie :
 *   1. Clearbit Logo API (logo.clearbit.com/<domain>) — PNG transparent qualité haute
 *   2. Fallback Google s2 favicons (sz=128) — toujours dispo, plus petit
 *
 * Le logo est téléchargé puis attaché en featured image (post_thumbnail).
 */
final class Product_Logo {

	private const NONCE = 'seoflix_fetch_logo';

	public static function init(): void {
		add_action( 'add_meta_boxes',                  [ self::class, 'register_metabox' ] );
		add_action( 'admin_enqueue_scripts',           [ self::class, 'enqueue_assets' ] );
		add_action( 'wp_ajax_seoflix_fetch_logo',      [ self::class, 'ajax_fetch_logo' ] );
		add_filter( 'bulk_actions-edit-seoflix_product',        [ self::class, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-seoflix_product', [ self::class, 'handle_bulk_fetch_logos' ], 10, 3 );
		add_action( 'admin_notices',                            [ self::class, 'bulk_fetch_notice' ] );
	}

	public static function register_metabox(): void {
		add_meta_box(
			'seoflix_product_logo_fetch',
			'Récupération automatique du logo',
			[ self::class, 'render_metabox' ],
			CPT::PRODUCT,
			'side',
			'default'
		);
	}

	public static function render_metabox( \WP_Post $post ): void {
		$official = (string) get_post_meta( $post->ID, Meta_Keys::PRODUCT_OFFICIAL_URL, true );
		$has_thumb = has_post_thumbnail( $post->ID );
		?>
		<?php if ( ! $official ) : ?>
			<p style="color: #dc3545; font-size: 0.85rem;">⚠️ Saisis d'abord l'<strong>URL officielle</strong> du produit (dans la métabox principale) puis sauvegarde, ensuite reviens ici.</p>
		<?php else : ?>
			<p style="font-size: 0.85rem; color: #666;">Source : <code><?php echo esc_html( wp_parse_url( $official, PHP_URL_HOST ) ); ?></code></p>
			<p>
				<button type="button" class="button button-primary" id="seoflix-fetch-logo" style="width:100%;">
					<?php echo $has_thumb ? '🔄 Re-récupérer le logo' : '📥 Récupérer le logo'; ?>
				</button>
			</p>
			<p id="seoflix-fetch-logo-status" style="font-size: 0.85rem; color: #666; margin: 0.75rem 0 0;"></p>
			<p class="description">Tente Clearbit en priorité (PNG transparent), puis Google favicons en fallback. Définit l'image en miniature (logo) du produit.</p>
		<?php endif; ?>
		<?php
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== CPT::PRODUCT ) {
			return;
		}

		$nonce    = wp_create_nonce( self::NONCE );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$post_id  = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		$js = "
		(function() {
			const btn = document.getElementById('seoflix-fetch-logo');
			if (!btn) return;
			const status = document.getElementById('seoflix-fetch-logo-status');
			btn.addEventListener('click', async function() {
				btn.disabled = true;
				status.innerHTML = '⏳ Tentative Clearbit…';
				try {
					const fd = new FormData();
					fd.append('action', 'seoflix_fetch_logo');
					fd.append('_ajax_nonce', " . wp_json_encode( $nonce ) . ");
					fd.append('post_id', " . wp_json_encode( $post_id ) . ");
					const r = await fetch(" . wp_json_encode( $ajax_url ) . ", { method: 'POST', body: fd, credentials: 'same-origin' });
					const json = await r.json();
					if (!json.success) throw new Error(json.data || 'Erreur');
					status.innerHTML = '<span style=\"color:#28a745;\">✓ Logo récupéré via ' + json.data.source + '. <a href=\"#\" onclick=\"location.reload();return false;\">Recharger</a> pour voir.</span>';
				} catch (e) {
					status.innerHTML = '<span style=\"color:#dc3545;\">✗ ' + e.message + '</span>';
				} finally {
					btn.disabled = false;
				}
			});
		})();
		";
		wp_register_script( 'seoflix-product-logo', '', [], '1.0', true );
		wp_enqueue_script( 'seoflix-product-logo' );
		wp_add_inline_script( 'seoflix-product-logo', $js );
	}

	public static function ajax_fetch_logo(): void {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Accès refusé.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $post_id || get_post_type( $post_id ) !== CPT::PRODUCT ) {
			wp_send_json_error( 'Produit invalide.' );
		}

		$result = self::fetch_and_attach_logo( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( [ 'source' => $result ] );
	}

	/**
	 * Fetch + attach logo. Retourne la source utilisée ('Clearbit'/'Google') ou WP_Error.
	 */
	public static function fetch_and_attach_logo( int $post_id ) {
		$official = (string) get_post_meta( $post_id, Meta_Keys::PRODUCT_OFFICIAL_URL, true );
		if ( ! $official ) {
			return new \WP_Error( 'no_url', 'URL officielle manquante.' );
		}
		$host = wp_parse_url( $official, PHP_URL_HOST );
		if ( ! $host ) {
			return new \WP_Error( 'bad_url', 'URL invalide.' );
		}
		$domain = preg_replace( '/^www\./i', '', $host );

		// 1. Clearbit
		$clearbit = "https://logo.clearbit.com/{$domain}?size=256";
		$attach   = self::download_to_attachment( $clearbit, $post_id, $domain . '-logo' );
		if ( ! is_wp_error( $attach ) ) {
			set_post_thumbnail( $post_id, $attach );
			update_post_meta( $post_id, Meta_Keys::PRODUCT_LOGO_URL, wp_get_attachment_url( $attach ) );
			return 'Clearbit';
		}

		// 2. Google s2 favicons fallback
		$google = "https://www.google.com/s2/favicons?domain={$domain}&sz=128";
		$attach = self::download_to_attachment( $google, $post_id, $domain . '-favicon' );
		if ( ! is_wp_error( $attach ) ) {
			set_post_thumbnail( $post_id, $attach );
			update_post_meta( $post_id, Meta_Keys::PRODUCT_LOGO_URL, wp_get_attachment_url( $attach ) );
			return 'Google favicons';
		}

		return new \WP_Error( 'fetch_failed', 'Aucune source ne renvoie de logo pour ' . $domain );
	}

	/**
	 * Télécharge une image distante en pièce jointe attachée à un post.
	 */
	private static function download_to_attachment( string $url, int $post_id, string $basename ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// SSRF protection : bloque les hosts/IPs internes
		$check = self::is_safe_remote_url( $url );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Heuristique : les fichiers < 200 octets sont probablement des erreurs/blanks
		if ( filesize( $tmp ) < 200 ) {
			@unlink( $tmp );
			return new \WP_Error( 'empty_logo', 'Logo vide ou non trouvé.' );
		}

		// Tenter de deviner l'extension via mime
		$mime = wp_check_filetype( $tmp )['type'] ?? '';
		if ( ! $mime ) {
			$finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
			if ( $finfo ) {
				$mime = finfo_file( $finfo, $tmp ) ?: '';
				finfo_close( $finfo );
			}
		}
		$ext = match ( $mime ) {
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
			default      => 'png',
		};

		$file_array = [
			'name'     => sanitize_file_name( $basename . '.' . $ext ),
			'tmp_name' => $tmp,
		];

		// XXE protection : refuser les SVG contenant des entités DTD
		if ( $ext === 'svg' ) {
			$svg = (string) file_get_contents( $tmp );
			if ( preg_match( '/<!ENTITY|<!DOCTYPE|SYSTEM\s+["\']/i', $svg ) ) {
				@unlink( $tmp );
				return new \WP_Error( 'unsafe_svg', 'SVG contient des entités DTD interdites (XXE protection).' );
			}
			// Strip aussi les <script> et on* attributes par précaution
			if ( preg_match( '/<script|on\w+\s*=/i', $svg ) ) {
				@unlink( $tmp );
				return new \WP_Error( 'unsafe_svg', 'SVG contient du JavaScript inline.' );
			}
		}

		// Refus des SVG/ICO par wp_handle_sideload : on les copie manuellement dans uploads
		if ( in_array( $ext, [ 'svg', 'ico' ], true ) ) {
			$uploads = wp_upload_dir();
			$dest    = trailingslashit( $uploads['path'] ) . wp_unique_filename( $uploads['path'], $file_array['name'] );
			if ( ! @rename( $tmp, $dest ) ) {
				@unlink( $tmp );
				return new \WP_Error( 'move_failed', 'Impossible de déplacer le logo dans uploads.' );
			}
			$attach_id = wp_insert_attachment( [
				'post_mime_type' => $mime ?: 'image/png',
				'post_title'     => $basename,
				'post_status'    => 'inherit',
			], $dest, $post_id );
			if ( is_wp_error( $attach_id ) ) {
				return $attach_id;
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$meta = wp_generate_attachment_metadata( $attach_id, $dest );
			wp_update_attachment_metadata( $attach_id, $meta );
			return $attach_id;
		}

		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return $attach_id;
		}
		return $attach_id;
	}

	/**
	 * Bloque les URLs pointant vers des hôtes/IPs internes (SSRF protection).
	 */
	private static function is_safe_remote_url( string $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return new \WP_Error( 'invalid_url', 'URL invalide.' );
		}
		if ( ! in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true ) ) {
			return new \WP_Error( 'bad_scheme', 'Seules les URLs http/https sont autorisées.' );
		}

		$host = strtolower( $parsed['host'] );

		// Blacklist hostnames sensibles
		$blocked_hosts = [ 'localhost', 'metadata.google.internal' ];
		if ( in_array( $host, $blocked_hosts, true ) ) {
			return new \WP_Error( 'blocked_host', 'Host interdit : ' . $host );
		}

		// Si c'est une IP, vérifier qu'elle n'est pas privée/loopback
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return new \WP_Error( 'private_ip', 'IP privée/réservée interdite : ' . $host );
			}
			return true;
		}

		// Si hostname, résolution DNS et vérification que les IPs résolues ne sont pas privées
		$ips = @gethostbynamel( $host );
		if ( ! $ips ) {
			// IPv6 ou pas de résolution : on laisse passer (download_url échouera proprement)
			return true;
		}
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return new \WP_Error( 'private_ip', 'Host résout sur une IP privée : ' . $ip );
			}
		}
		return true;
	}

	/* ====== Bulk action sur la liste des produits ====== */

	public static function register_bulk_actions( array $actions ): array {
		$actions['seoflix_fetch_logos'] = '📥 Récupérer les logos manquants';
		return $actions;
	}

	public static function handle_bulk_fetch_logos( string $redirect_url, string $action, array $post_ids ): string {
		if ( $action !== 'seoflix_fetch_logos' ) {
			return $redirect_url;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_url;
		}

		$ok = 0; $fail = 0; $skip = 0;
		foreach ( $post_ids as $post_id ) {
			if ( get_post_type( $post_id ) !== CPT::PRODUCT ) {
				continue;
			}
			if ( has_post_thumbnail( $post_id ) ) {
				$skip++;
				continue;
			}
			$res = self::fetch_and_attach_logo( $post_id );
			if ( is_wp_error( $res ) ) {
				$fail++;
			} else {
				$ok++;
			}
		}
		return add_query_arg( [
			'seoflix_logos_ok'   => $ok,
			'seoflix_logos_fail' => $fail,
			'seoflix_logos_skip' => $skip,
		], $redirect_url );
	}

	public static function bulk_fetch_notice(): void {
		if ( ! isset( $_GET['seoflix_logos_ok'] ) ) {
			return;
		}
		$ok   = (int) $_GET['seoflix_logos_ok'];
		$fail = (int) ( $_GET['seoflix_logos_fail'] ?? 0 );
		$skip = (int) ( $_GET['seoflix_logos_skip'] ?? 0 );
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%d</strong> logo(s) récupéré(s), <strong>%d</strong> échec(s), <strong>%d</strong> ignoré(s) (logo déjà présent).</p></div>',
			$ok, $fail, $skip
		);
	}
}
