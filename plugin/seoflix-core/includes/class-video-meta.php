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
			'seoflix_video_editorial',
			'Contenu éditorial MADIAS',
			[ self::class, 'render_editorial_metabox' ],
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
				<span id="seoflix-detect-products-status" role="status"></span>
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
						const status = document.getElementById('seoflix-detect-products-status');
						if (status) {
							status.textContent = count + ' produit(s) auto-détecté(s). Mets à jour la vidéo pour enregistrer.';
						}
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
	 *  Métabox "Contenu éditorial MADIAS"
	 * ====================================================================== */

	public static function render_editorial_metabox( \WP_Post $post ): void {
		$editorial_url  = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_EDITORIAL_URL, true );
		$duration       = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_DURATION, true );
		$timestamps     = self::decode_timestamps( get_post_meta( $post->ID, Meta_Keys::VIDEO_TIMESTAMPS, true ), $duration );
		$key_concepts   = self::decode_key_concepts( get_post_meta( $post->ID, Meta_Keys::VIDEO_KEY_CONCEPTS, true ) );
		$timestamp_next = count( $timestamps );
		$concept_next   = count( $key_concepts );

		?>
		<input type="hidden" name="seoflix_editorial_submitted" value="1">
		<input type="hidden" name="seoflix_timestamps_present" value="1">
		<input type="hidden" name="seoflix_key_concepts_present" value="1">

		<p>
			<label for="seoflix_editorial_video_url" style="display:block; font-weight:600; margin-bottom:0.25rem;">Vidéo personnelle MADIAS (YouTube)</label>
			<input type="text" id="seoflix_editorial_video_url" name="seoflix_editorial_video_url" value="<?php echo esc_attr( $editorial_url ); ?>" class="widefat code" placeholder="ID ou URL YouTube">
			<span class="description">Optionnelle. Une valeur valide est normalisée vers youtube-nocookie.com.</span>
		</p>

		<h3>Passages à regarder</h3>
		<p class="description">Les timestamps pilotent toujours la vidéo source. Le libellé est obligatoire.</p>
		<div id="seoflix-timestamps-list">
			<?php foreach ( $timestamps as $index => $row ) : ?>
				<div class="seoflix-editorial-row" style="display:grid; grid-template-columns:100px 1fr 1fr auto; gap:8px; margin-bottom:8px; align-items:start;">
					<input type="hidden" name="seoflix_timestamps[<?php echo (int) $index; ?>][id]" value="<?php echo esc_attr( $row['id'] ); ?>">
					<input type="number" min="0" name="seoflix_timestamps[<?php echo (int) $index; ?>][seconds]" value="<?php echo esc_attr( (string) $row['seconds'] ); ?>" aria-label="Secondes" placeholder="Secondes">
					<input type="text" name="seoflix_timestamps[<?php echo (int) $index; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" aria-label="Libellé" placeholder="Libellé obligatoire">
					<input type="text" name="seoflix_timestamps[<?php echo (int) $index; ?>][takeaway]" value="<?php echo esc_attr( $row['takeaway'] ); ?>" aria-label="À retenir" placeholder="À retenir (optionnel)">
					<button type="button" class="button-link-delete seoflix-remove-editorial-row">Supprimer</button>
				</div>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="seoflix-add-timestamp">Ajouter un passage</button></p>

		<h3>Points à retenir</h3>
		<div id="seoflix-key-concepts-list">
			<?php foreach ( $key_concepts as $index => $point ) : ?>
				<div class="seoflix-editorial-row" style="display:grid; grid-template-columns:1fr auto; gap:8px; margin-bottom:8px; align-items:start;">
					<input type="hidden" name="seoflix_key_concepts[<?php echo (int) $index; ?>][id]" value="<?php echo esc_attr( $point['id'] ); ?>">
					<input type="text" name="seoflix_key_concepts[<?php echo (int) $index; ?>][text]" value="<?php echo esc_attr( $point['text'] ); ?>" class="widefat" aria-label="Point à retenir" placeholder="Point à retenir">
					<button type="button" class="button-link-delete seoflix-remove-editorial-row">Supprimer</button>
				</div>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="seoflix-add-key-concept">Ajouter un point</button></p>

		<script>
		(function() {
			'use strict';
			let timestampIndex = <?php echo (int) $timestamp_next; ?>;
			let conceptIndex = <?php echo (int) $concept_next; ?>;

			function field(type, name, placeholder) {
				const input = document.createElement('input');
				input.type = type;
				input.name = name;
				input.placeholder = placeholder;
				input.setAttribute('aria-label', placeholder);
				if (type === 'number') input.min = '0';
				if (type === 'hidden') input.removeAttribute('aria-label');
				return input;
			}

			function removeButton() {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'button-link-delete seoflix-remove-editorial-row';
				button.textContent = 'Supprimer';
				return button;
			}

			function addTimestamp() {
				const list = document.getElementById('seoflix-timestamps-list');
				const row = document.createElement('div');
				const base = 'seoflix_timestamps[' + timestampIndex + ']';
				row.className = 'seoflix-editorial-row';
				row.style.cssText = 'display:grid;grid-template-columns:100px 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:start;';
				row.appendChild(field('hidden', base + '[id]', ''));
				row.appendChild(field('number', base + '[seconds]', 'Secondes'));
				row.appendChild(field('text', base + '[label]', 'Libellé obligatoire'));
				row.appendChild(field('text', base + '[takeaway]', 'À retenir (optionnel)'));
				row.appendChild(removeButton());
				list.appendChild(row);
				timestampIndex++;
			}

			function addConcept() {
				const list = document.getElementById('seoflix-key-concepts-list');
				const row = document.createElement('div');
				const base = 'seoflix_key_concepts[' + conceptIndex + ']';
				row.className = 'seoflix-editorial-row';
				row.style.cssText = 'display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:8px;align-items:start;';
				row.appendChild(field('hidden', base + '[id]', ''));
				row.appendChild(field('text', base + '[text]', 'Point à retenir'));
				row.appendChild(removeButton());
				list.appendChild(row);
				conceptIndex++;
			}

			document.getElementById('seoflix-add-timestamp').addEventListener('click', addTimestamp);
			document.getElementById('seoflix-add-key-concept').addEventListener('click', addConcept);
			document.addEventListener('click', function(event) {
				if (event.target.classList.contains('seoflix-remove-editorial-row')) {
					event.target.closest('.seoflix-editorial-row').remove();
				}
			});
		})();
		</script>
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
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) && is_string( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_type !== CPT::VIDEO ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Produits sélectionnés
		$selected = isset( $_POST['seoflix_products'] ) && is_array( $_POST['seoflix_products'] )
			? wp_unslash( $_POST['seoflix_products'] )
			: [];
		$selected = array_values( array_unique( array_map( 'intval', $selected ) ) );
		update_post_meta( $post_id, Meta_Keys::VIDEO_PRODUCTS, wp_json_encode( $selected ) );

		// Métadonnées YouTube
		if ( isset( $_POST['seoflix_video_youtube_id'] ) ) {
			$youtube_id = is_string( $_POST['seoflix_video_youtube_id'] )
				? sanitize_text_field( wp_unslash( $_POST['seoflix_video_youtube_id'] ) )
				: '';
			update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_ID, $youtube_id );
		}
		if ( isset( $_POST['seoflix_video_channel_id'] ) ) {
			$cid = is_scalar( $_POST['seoflix_video_channel_id'] ) ? (int) $_POST['seoflix_video_channel_id'] : 0;
			if ( $cid > 0 ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID, $cid );
			} else {
				delete_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID );
			}
		}
		if ( isset( $_POST['seoflix_video_duration'] ) ) {
			$duration = is_scalar( $_POST['seoflix_video_duration'] ) ? max( 0, (int) $_POST['seoflix_video_duration'] ) : 0;
			update_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, $duration );
		}
		if ( isset( $_POST['seoflix_video_views'] ) ) {
			$views = is_scalar( $_POST['seoflix_video_views'] ) ? max( 0, (int) $_POST['seoflix_video_views'] ) : 0;
			update_post_meta( $post_id, Meta_Keys::VIDEO_VIEW_COUNT, $views );
		}
		if ( isset( $_POST['seoflix_video_published'] ) && is_string( $_POST['seoflix_video_published'] ) ) {
			$pub = trim( wp_unslash( $_POST['seoflix_video_published'] ) );
			if ( $pub && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pub ) ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_PUBLISHED_AT, $pub );
			}
		}
		if ( isset( $_POST['seoflix_video_thumbnail'] ) && is_string( $_POST['seoflix_video_thumbnail'] ) ) {
			$url = trim( wp_unslash( $_POST['seoflix_video_thumbnail'] ) );
			if ( $url ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL, esc_url_raw( $url ) );
			} else {
				delete_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL );
			}
		}

		// Ne jamais modifier les données éditoriales depuis un formulaire qui ne les expose pas.
		if ( ! isset( $_POST['seoflix_editorial_submitted'] ) ) {
			return;
		}

		if ( isset( $_POST['seoflix_editorial_video_url'] ) ) {
			$raw_editorial_url = is_string( $_POST['seoflix_editorial_video_url'] )
				? wp_unslash( $_POST['seoflix_editorial_video_url'] )
				: null;
			$editorial_url = self::normalize_editorial_youtube_url( $raw_editorial_url );
			if ( $editorial_url === '' ) {
				delete_post_meta( $post_id, Meta_Keys::VIDEO_EDITORIAL_URL );
			} elseif ( $editorial_url !== null ) {
				update_post_meta( $post_id, Meta_Keys::VIDEO_EDITORIAL_URL, $editorial_url );
			}
		}

		$duration = isset( $_POST['seoflix_video_duration'] ) && is_scalar( $_POST['seoflix_video_duration'] )
			? max( 0, (int) $_POST['seoflix_video_duration'] )
			: (int) get_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, true );

		if ( isset( $_POST['seoflix_timestamps_present'] ) ) {
			if ( ! isset( $_POST['seoflix_timestamps'] ) ) {
				$timestamps = [];
				update_post_meta( $post_id, Meta_Keys::VIDEO_TIMESTAMPS, wp_json_encode( $timestamps ) );
			} elseif ( is_array( $_POST['seoflix_timestamps'] ) ) {
				$timestamps = self::sanitize_timestamps( wp_unslash( $_POST['seoflix_timestamps'] ), $duration );
				update_post_meta( $post_id, Meta_Keys::VIDEO_TIMESTAMPS, wp_json_encode( $timestamps ) );
			}
		}

		if ( isset( $_POST['seoflix_key_concepts_present'] ) ) {
			if ( ! isset( $_POST['seoflix_key_concepts'] ) ) {
				$key_concepts = [];
				update_post_meta( $post_id, Meta_Keys::VIDEO_KEY_CONCEPTS, wp_json_encode( $key_concepts ) );
			} elseif ( is_array( $_POST['seoflix_key_concepts'] ) ) {
				$key_concepts = self::sanitize_key_concepts( wp_unslash( $_POST['seoflix_key_concepts'] ) );
				update_post_meta( $post_id, Meta_Keys::VIDEO_KEY_CONCEPTS, wp_json_encode( $key_concepts ) );
			}
		}
	}

	/**
	 * Normalise un ID/une URL YouTube vers l'URL d'embed sans cookies.
	 * Retourne null pour une valeur non vide invalide afin de préserver la méta existante.
	 */
	public static function normalize_editorial_youtube_url( mixed $value ): ?string {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( (string) $value ) );
		if ( $value === '' ) {
			return '';
		}

		if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $value ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $value;
		}

		$url   = esc_url_raw( $value, [ 'http', 'https' ] );
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return null;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return null;
		}

		$allowed_hosts = [
			'youtube.com'        => true,
			'www.youtube.com'    => true,
			'm.youtube.com'      => true,
			'youtu.be'           => true,
			'youtube-nocookie.com' => true,
			'www.youtube-nocookie.com' => true,
		];
		$host = strtolower( $parts['host'] );
		if ( ! isset( $allowed_hosts[ $host ] ) ) {
			return null;
		}

		$video_id = '';
		$path     = $parts['path'] ?? '';
		if ( $host === 'youtu.be' ) {
			$video_id = explode( '/', trim( $path, '/' ) )[0] ?? '';
		} elseif ( $path === '/watch' ) {
			$query = [];
			parse_str( $parts['query'] ?? '', $query );
			$video_id = is_string( $query['v'] ?? null ) ? $query['v'] : '';
		} elseif ( preg_match( '#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $matches ) ) {
			$video_id = $matches[1];
		}

		if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $video_id ) ) {
			return null;
		}

		return 'https://www.youtube-nocookie.com/embed/' . $video_id;
	}

	/**
	 * @param array<mixed> $rows
	 * @return array<int, array{id:string,seconds:int,label:string,takeaway:string}>
	 */
	public static function sanitize_timestamps( array $rows, int $duration = 0 ): array {
		$clean = [];
		foreach ( array_values( $rows ) as $order => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$raw_seconds = $row['seconds'] ?? null;
			$seconds     = is_scalar( $raw_seconds ) ? filter_var( $raw_seconds, FILTER_VALIDATE_INT ) : false;
			$label       = is_scalar( $row['label'] ?? null ) ? sanitize_text_field( (string) $row['label'] ) : '';
			$takeaway    = is_scalar( $row['takeaway'] ?? null ) ? sanitize_textarea_field( (string) $row['takeaway'] ) : '';
			if ( $seconds === false || $seconds < 0 || ( $duration > 0 && $seconds > $duration ) || $label === '' ) {
				continue;
			}

			$posted_id = is_scalar( $row['id'] ?? null ) ? sanitize_text_field( (string) $row['id'] ) : '';
			$id        = $posted_id && wp_is_uuid( $posted_id ) ? $posted_id : wp_generate_uuid4();
			$clean[]   = [
				'id'       => $id,
				'seconds'  => $seconds,
				'label'    => $label,
				'takeaway' => $takeaway,
				'_order'   => $order,
			];
		}

		usort( $clean, static function ( array $left, array $right ): int {
			return ( $left['seconds'] <=> $right['seconds'] ) ?: ( $left['_order'] <=> $right['_order'] );
		} );
		foreach ( $clean as &$row ) {
			unset( $row['_order'] );
		}
		unset( $row );

		return $clean;
	}

	/**
	 * @param array<mixed> $points
	 * @return array<int, array{id:string,text:string}>
	 */
	public static function sanitize_key_concepts( array $points ): array {
		$clean = [];
		foreach ( array_values( $points ) as $point ) {
			$text      = '';
			$posted_id = '';
			if ( is_string( $point ) || is_numeric( $point ) ) {
				$text = sanitize_text_field( (string) $point );
			} elseif ( is_array( $point ) ) {
				$text      = is_scalar( $point['text'] ?? null ) ? sanitize_text_field( (string) $point['text'] ) : '';
				$posted_id = is_scalar( $point['id'] ?? null ) ? sanitize_text_field( (string) $point['id'] ) : '';
			}
			if ( $text === '' ) {
				continue;
			}

			$id      = $posted_id && wp_is_uuid( $posted_id ) ? $posted_id : wp_generate_uuid4();
			$clean[] = [ 'id' => $id, 'text' => $text ];
		}
		return $clean;
	}

	private static function decode_timestamps( mixed $stored, int $duration ): array {
		$decoded = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
		return is_array( $decoded ) ? self::sanitize_timestamps( $decoded, $duration ) : [];
	}

	private static function decode_key_concepts( mixed $stored ): array {
		$decoded = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
		return is_array( $decoded ) ? self::sanitize_key_concepts( $decoded ) : [];
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
