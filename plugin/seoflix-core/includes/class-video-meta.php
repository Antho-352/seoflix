<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Métaboxes sur l'écran d'édition d'une vidéo :
 *  - "Produits mentionnés" : liste cochable de tous les produits, avec filtre de recherche.
 *    Persisté dans post_meta `_seoflix_products` (JSON array of IDs).
 *  - "Métadonnées YouTube" : champs YouTube ID, durée, vues, miniature, etc.
 */
final class Video_Meta {

	private const NONCE_ACTION = 'seoflix_video_meta';
	private const NONCE_NAME   = 'seoflix_video_meta_nonce';

	public static function init(): void {
		add_action( 'add_meta_boxes',                [ self::class, 'register_metaboxes' ] );
		add_action( 'save_post_seoflix_video',       [ self::class, 'save_metaboxes' ], 10, 2 );
	}

	public static function register_metaboxes(): void {
		add_meta_box(
			'seoflix_video_products',
			'Produits mentionnés dans cette vidéo',
			[ self::class, 'render_products_metabox' ],
			CPT::VIDEO,
			'normal',
			'high'
		);
		add_meta_box(
			'seoflix_video_youtube',
			'Métadonnées YouTube',
			[ self::class, 'render_youtube_metabox' ],
			CPT::VIDEO,
			'side',
			'default'
		);
	}

	/* ======================================================================
	 *  Métabox "Produits mentionnés" — checkbox list avec filtre live
	 * ====================================================================== */

	public static function render_products_metabox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$selected_json = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_PRODUCTS, true );
		$selected      = $selected_json ? (array) json_decode( $selected_json, true ) : [];
		$selected      = array_map( 'intval', $selected );

		$products = get_posts( [
			'post_type'      => CPT::PRODUCT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		?>
		<input type="text" id="seoflix-product-filter" placeholder="Filtrer la liste (linkuma, ahrefs…)" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem;">

		<?php if ( $products ) : ?>
			<div id="seoflix-product-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 0.75rem; background: #fafafa;">
				<?php foreach ( $products as $p ) :
					$cats = wp_get_object_terms( $p->ID, 'seoflix_product_category', [ 'number' => 1 ] );
					$cat  = ( ! is_wp_error( $cats ) && $cats ) ? $cats[0]->name : '';
					$is_selected = in_array( (int) $p->ID, $selected, true );
					?>
					<label class="seoflix-product-item" data-search="<?php echo esc_attr( strtolower( $p->post_title . ' ' . $cat ) ); ?>" style="display: block; padding: 0.3rem 0; cursor: pointer; border-bottom: 1px solid #eee;">
						<input type="checkbox" name="seoflix_products[]" value="<?php echo (int) $p->ID; ?>" <?php checked( $is_selected ); ?>>
						<strong><?php echo esc_html( $p->post_title ); ?></strong>
						<?php if ( $cat ) : ?>
							<span style="color: #999; font-size: 0.85rem;"> · <?php echo esc_html( $cat ); ?></span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>

			<p style="margin-top: 0.5rem; color: #666; font-size: 0.85rem;">
				<button type="button" class="button" id="seoflix-detect-products">🔍 Auto-détecter depuis la description</button>
				Scanne la description et coche automatiquement les produits dont le nom apparaît.
			</p>

			<script>
			(function() {
				const filter = document.getElementById('seoflix-product-filter');
				const items = document.querySelectorAll('#seoflix-product-list .seoflix-product-item');
				if (filter) {
					filter.addEventListener('input', function() {
						const q = filter.value.toLowerCase().trim();
						items.forEach(function(el) {
							el.style.display = (!q || el.dataset.search.indexOf(q) !== -1) ? '' : 'none';
						});
					});
				}
				const detectBtn = document.getElementById('seoflix-detect-products');
				if (detectBtn) {
					detectBtn.addEventListener('click', function() {
						// Lit la description depuis Gutenberg ou Classic editor
						let desc = '';
						if (window.wp && wp.data && wp.data.select('core/editor')) {
							desc = wp.data.select('core/editor').getEditedPostContent() || '';
						} else if (document.getElementById('content')) {
							desc = document.getElementById('content').value || '';
						}
						const titleEl = document.querySelector('input[name=\"post_title\"], .editor-post-title__input');
						if (titleEl) desc = (titleEl.value || titleEl.textContent || '') + '\\n' + desc;
						const text = desc.toLowerCase();
						let count = 0;
						items.forEach(function(el) {
							const name = el.querySelector('strong').textContent.trim().toLowerCase();
							if (name.length < 4) return;
							const re = new RegExp('\\\\b' + name.replace(/[.*+?^\${}()|[\\]\\\\]/g, '\\\\\$&') + '\\\\b', 'i');
							const cb = el.querySelector('input[type=checkbox]');
							if (re.test(text)) { cb.checked = true; count++; }
						});
						alert(count + ' produit(s) auto-détecté(s). N\\'oublie pas de mettre à jour la vidéo.');
					});
				}
			})();
			</script>
		<?php else : ?>
			<p>Aucun produit dans la base. Crée des produits via Seoflix → Produits → Ajouter.</p>
		<?php endif; ?>
		<?php
	}

	/* ======================================================================
	 *  Métabox "Métadonnées YouTube" — édition manuelle des champs YT
	 * ====================================================================== */

	public static function render_youtube_metabox( \WP_Post $post ): void {
		$youtube_id  = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_YOUTUBE_ID, true );
		$duration    = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_DURATION, true );
		$views       = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_VIEW_COUNT, true );
		$published   = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_PUBLISHED_AT, true );
		$thumbnail   = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_THUMBNAIL_URL, true );
		$channel_id  = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_CHANNEL_ID, true );

		$channels = get_posts( [
			'post_type'      => CPT::CHANNEL,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		?>
		<p>
			<label for="seoflix_video_youtube_id" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">YouTube ID</label>
			<input type="text" id="seoflix_video_youtube_id" name="seoflix_video_youtube_id" value="<?php echo esc_attr( $youtube_id ); ?>" class="widefat" placeholder="abcDEF1234">
		</p>

		<p>
			<label for="seoflix_video_channel_id" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">Chaîne</label>
			<select id="seoflix_video_channel_id" name="seoflix_video_channel_id" class="widefat">
				<option value="">— Aucune —</option>
				<?php foreach ( $channels as $ch ) : ?>
					<option value="<?php echo (int) $ch->ID; ?>" <?php selected( $channel_id, $ch->ID ); ?>><?php echo esc_html( $ch->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="seoflix_video_duration" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">Durée (secondes)</label>
			<input type="number" id="seoflix_video_duration" name="seoflix_video_duration" value="<?php echo esc_attr( (string) $duration ); ?>" min="0" class="widefat">
		</p>

		<p>
			<label for="seoflix_video_views" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">Vues</label>
			<input type="number" id="seoflix_video_views" name="seoflix_video_views" value="<?php echo esc_attr( (string) $views ); ?>" min="0" class="widefat">
		</p>

		<p>
			<label for="seoflix_video_published" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">Publiée le (YYYY-MM-DD)</label>
			<input type="date" id="seoflix_video_published" name="seoflix_video_published" value="<?php echo esc_attr( $published ); ?>" class="widefat">
		</p>

		<p>
			<label for="seoflix_video_thumbnail" style="display:block; font-weight: 600; margin-bottom: 0.25rem;">URL miniature</label>
			<input type="url" id="seoflix_video_thumbnail" name="seoflix_video_thumbnail" value="<?php echo esc_attr( $thumbnail ); ?>" class="widefat code">
			<?php if ( $thumbnail ) : ?>
				<img src="<?php echo esc_url( $thumbnail ); ?>" alt="" style="margin-top: 0.5rem; max-width: 100%; border-radius: 4px;">
			<?php endif; ?>
		</p>
		<?php
	}

	public static function save_metaboxes( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Produits sélectionnés
		$selected = isset( $_POST['seoflix_products'] ) ? (array) $_POST['seoflix_products'] : [];
		$selected = array_values( array_unique( array_map( 'intval', $selected ) ) );
		update_post_meta( $post_id, Meta_Keys::VIDEO_PRODUCTS, wp_json_encode( $selected ) );

		// Métadonnées YouTube
		if ( isset( $_POST['seoflix_video_youtube_id'] ) ) {
			update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_ID, sanitize_text_field( wp_unslash( $_POST['seoflix_video_youtube_id'] ) ) );
		}
		if ( isset( $_POST['seoflix_video_channel_id'] ) ) {
			$cid = (int) $_POST['seoflix_video_channel_id'];
			if ( $cid > 0 ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID, $cid );
			} else {
				delete_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID );
			}
		}
		if ( isset( $_POST['seoflix_video_duration'] ) ) {
			update_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, max( 0, (int) $_POST['seoflix_video_duration'] ) );
		}
		if ( isset( $_POST['seoflix_video_views'] ) ) {
			update_post_meta( $post_id, Meta_Keys::VIDEO_VIEW_COUNT, max( 0, (int) $_POST['seoflix_video_views'] ) );
		}
		if ( isset( $_POST['seoflix_video_published'] ) ) {
			$pub = trim( wp_unslash( $_POST['seoflix_video_published'] ) );
			if ( $pub && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pub ) ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_PUBLISHED_AT, $pub );
			}
		}
		if ( isset( $_POST['seoflix_video_thumbnail'] ) ) {
			$url = trim( wp_unslash( $_POST['seoflix_video_thumbnail'] ) );
			if ( $url ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL, esc_url_raw( $url ) );
			} else {
				delete_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL );
			}
		}
	}

	/* ======================================================================
	 *  Helper : auto-détection de produits depuis un texte (titre + description)
	 *  Utilisé pendant la sync YouTube et exposé pour le bouton manuel.
	 * ====================================================================== */

	public static function detect_products_in_text( string $text ): array {
		if ( ! $text ) {
			return [];
		}
		$products = get_posts( [
			'post_type'      => CPT::PRODUCT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );

		$matched = [];
		foreach ( $products as $p ) {
			$name = $p->post_title;
			// Skip noms trop courts pour éviter les faux positifs (cuik, ia, etc.)
			if ( mb_strlen( $name ) < 4 ) {
				continue;
			}
			$pattern = '/(?<![\p{L}])' . preg_quote( $name, '/' ) . '(?![\p{L}])/iu';
			if ( @preg_match( $pattern, $text ) ) {
				$matched[] = (int) $p->ID;
			}
		}
		return $matched;
	}
}
