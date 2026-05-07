<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Métabox "Identité YouTube" sur l'écran d'édition d'une chaîne.
 *
 * Permet :
 *   - Saisie manuelle des champs YouTube
 *   - Bouton "Récupérer depuis YouTube" qui fait un AJAX vers l'API et auto-remplit
 */
final class Channel_Meta {

	private const NONCE_ACTION       = 'seoflix_channel_meta';
	private const NONCE_NAME         = 'seoflix_channel_meta_nonce';
	private const AJAX_NONCE         = 'seoflix_fetch_channel';
	private const SYNC_AJAX_NONCE    = 'seoflix_sync_videos';

	public static function init(): void {
		add_action( 'add_meta_boxes',                       [ self::class, 'register_metabox' ] );
		add_action( 'save_post_seoflix_channel',            [ self::class, 'save_metabox' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',                [ self::class, 'enqueue_assets' ] );
		add_action( 'wp_ajax_seoflix_fetch_channel',        [ self::class, 'ajax_fetch_channel' ] );
		add_action( 'wp_ajax_seoflix_sync_channel_videos',  [ self::class, 'ajax_sync_videos' ] );
	}

	public static function register_metabox(): void {
		add_meta_box(
			'seoflix_channel_identity',
			'Identité YouTube',
			[ self::class, 'render_metabox' ],
			CPT::CHANNEL,
			'normal',
			'high'
		);
		add_meta_box(
			'seoflix_channel_sync',
			'Synchronisation des vidéos',
			[ self::class, 'render_sync_metabox' ],
			CPT::CHANNEL,
			'side',
			'high'
		);
	}

	public static function render_sync_metabox( \WP_Post $post ): void {
		$api_ready  = YouTube_API::is_configured();
		$channel_id = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_YOUTUBE_ID, true );
		$ready      = $api_ready && $channel_id;

		$count_existing = (int) wp_count_posts( CPT::VIDEO )->publish + (int) wp_count_posts( CPT::VIDEO )->pending;
		$channel_videos = $channel_id ? get_posts( [
			'post_type'      => CPT::VIDEO,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => Meta_Keys::VIDEO_CHANNEL_ID, 'value' => $post->ID ],
			],
		] ) : [];
		?>
		<p>
			<button type="button" class="button button-primary" id="seoflix-sync-videos" style="width:100%;" <?php disabled( ! $ready ); ?>>
				🔄 Récupérer les nouvelles vidéos
			</button>
		</p>
		<p id="seoflix-sync-status" style="font-size: 0.85rem; color: #666; margin: 0.75rem 0 0;"></p>

		<div style="font-size: 0.85rem; color: #666; margin-top: 1rem;">
			<p><strong><?php echo (int) count( $channel_videos ); ?></strong> vidéo<?php echo count( $channel_videos ) > 1 ? 's' : ''; ?> déjà en base pour cette chaîne.</p>

			<?php if ( ! $api_ready ) : ?>
				<p style="color: #dc3545;">❗ Clé YouTube Data API non configurée. <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Configurer</a>.</p>
			<?php elseif ( ! $channel_id ) : ?>
				<p style="color: #dc3545;">❗ ID de chaîne YouTube manquant. Saisis-le dans la section « Identité YouTube » ou clique sur « Récupérer depuis YouTube ».</p>
			<?php else : ?>
				<p>Le bouton récupère jusqu'à 50 dernières vidéos &gt; 7 min, filtre les Shorts, et crée les nouvelles en statut <strong>« en attente »</strong>.</p>
				<p>Les vidéos déjà en base sont ignorées (dédup par <code>youtube_id</code>).</p>
				<p>Coût API : ~3 unités (sur 10 000/jour).</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$handle       = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_HANDLE, true );
		$channel_id   = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_YOUTUBE_ID, true );
		$real_name    = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_REAL_NAME, true );
		$subs         = (int) get_post_meta( $post->ID, Meta_Keys::CHANNEL_SUBSCRIBER_COUNT, true );
		$thumb_url    = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_THUMBNAIL_URL, true );
		$channel_url  = (string) get_post_meta( $post->ID, Meta_Keys::CHANNEL_YOUTUBE_URL, true );
		$api_ready    = YouTube_API::is_configured();

		?>
		<table class="form-table seoflix-channel-meta">
			<tr>
				<th scope="row"><label for="seoflix_handle">Handle YouTube</label></th>
				<td>
					<input type="text" id="seoflix_handle" name="seoflix_handle" value="<?php echo esc_attr( $handle ); ?>" class="regular-text" placeholder="@MaChaine">
					<button type="button" class="button" id="seoflix-fetch-channel" <?php disabled( ! $api_ready ); ?>>
						🔄 Récupérer depuis YouTube
					</button>
					<span id="seoflix-fetch-status" style="margin-left: 0.5rem; color: #666;"></span>
					<p class="description">
						<?php if ( $api_ready ) : ?>
							Saisis le handle (@xxx), clique sur le bouton, et tous les champs ci-dessous se rempliront automatiquement depuis YouTube.
						<?php else : ?>
							<strong>Clé YouTube Data API non configurée.</strong> Configure-la dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Réglages</a> pour activer le bouton « Récupérer ». Sinon, remplis les champs manuellement.
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_youtube_channel_id">ID de chaîne YouTube</label></th>
				<td>
					<input type="text" id="seoflix_youtube_channel_id" name="seoflix_youtube_channel_id" value="<?php echo esc_attr( $channel_id ); ?>" class="regular-text code" placeholder="UCxxxxxxxxxxxxxxxxxxxx">
					<p class="description">Format <code>UCxxx...</code>, 24 caractères. Visible dans l'URL <code>/channel/UCxxx</code> sur YouTube.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_real_name">Nom réel / animateur(s)</label></th>
				<td>
					<input type="text" id="seoflix_real_name" name="seoflix_real_name" value="<?php echo esc_attr( $real_name ); ?>" class="regular-text" placeholder="Sylvain Peyronnet, ou Paul Vengeons + Antoine Veyrieres">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_subscriber_count">Nombre d'abonnés</label></th>
				<td>
					<input type="number" id="seoflix_subscriber_count" name="seoflix_subscriber_count" value="<?php echo esc_attr( (string) $subs ); ?>" min="0" step="1" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_thumbnail_url">URL miniature (avatar)</label></th>
				<td>
					<input type="url" id="seoflix_thumbnail_url" name="seoflix_thumbnail_url" value="<?php echo esc_attr( $thumb_url ); ?>" class="large-text code" placeholder="https://yt3.googleusercontent.com/...">
					<?php if ( $thumb_url ) : ?>
						<p><img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="max-width: 80px; border-radius: 50%; margin-top: 0.5rem;"></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="seoflix_channel_url">URL YouTube</label></th>
				<td>
					<input type="url" id="seoflix_channel_url" name="seoflix_channel_url" value="<?php echo esc_attr( $channel_url ); ?>" class="large-text code" placeholder="https://www.youtube.com/@MaChaine">
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = [
			'seoflix_handle'             => Meta_Keys::CHANNEL_HANDLE,
			'seoflix_youtube_channel_id' => Meta_Keys::CHANNEL_YOUTUBE_ID,
			'seoflix_real_name'          => Meta_Keys::CHANNEL_REAL_NAME,
			'seoflix_thumbnail_url'      => Meta_Keys::CHANNEL_THUMBNAIL_URL,
			'seoflix_channel_url'        => Meta_Keys::CHANNEL_YOUTUBE_URL,
		];

		foreach ( $fields as $key => $meta_key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = trim( wp_unslash( $_POST[ $key ] ) );
				if ( $value ) {
					$value = ( strpos( $key, '_url' ) !== false ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
					update_post_meta( $post_id, $meta_key, $value );
				} else {
					delete_post_meta( $post_id, $meta_key );
				}
			}
		}

		if ( isset( $_POST['seoflix_subscriber_count'] ) ) {
			$subs = max( 0, (int) $_POST['seoflix_subscriber_count'] );
			update_post_meta( $post_id, Meta_Keys::CHANNEL_SUBSCRIBER_COUNT, $subs );
		}
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== CPT::CHANNEL ) {
			return;
		}

		// JS inline pour les boutons "Récupérer" + "Sync"
		$nonce      = wp_create_nonce( self::AJAX_NONCE );
		$sync_nonce = wp_create_nonce( self::SYNC_AJAX_NONCE );
		$ajax_url   = admin_url( 'admin-ajax.php' );
		$post_id    = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		$js = "
		(function() {
			const btn = document.getElementById('seoflix-fetch-channel');
			if (!btn) return;
			const status = document.getElementById('seoflix-fetch-status');
			btn.addEventListener('click', async function() {
				const handle = document.getElementById('seoflix_handle').value.trim();
				if (!handle) { status.textContent = 'Saisis un handle d\\'abord.'; return; }
				btn.disabled = true;
				status.textContent = 'Récupération…';
				try {
					const formData = new FormData();
					formData.append('action', 'seoflix_fetch_channel');
					formData.append('_ajax_nonce', " . wp_json_encode( $nonce ) . ");
					formData.append('handle', handle);
					const r = await fetch(" . wp_json_encode( $ajax_url ) . ", { method: 'POST', body: formData, credentials: 'same-origin' });
					const json = await r.json();
					if (!json.success) throw new Error(json.data || 'Erreur inconnue');
					const d = json.data;
					if (d.handle) document.getElementById('seoflix_handle').value = d.handle;
					if (d.youtube_channel_id) document.getElementById('seoflix_youtube_channel_id').value = d.youtube_channel_id;
					if (d.subscriber_count) document.getElementById('seoflix_subscriber_count').value = d.subscriber_count;
					if (d.thumbnail_url) document.getElementById('seoflix_thumbnail_url').value = d.thumbnail_url;
					if (d.channel_url) document.getElementById('seoflix_channel_url').value = d.channel_url;
					// Pré-remplir titre + description si vides
					const titleInput = document.getElementById('title');
					if (titleInput && !titleInput.value && d.title) titleInput.value = d.title;
					status.innerHTML = '<span style=\"color: #28a745;\">✓ Récupéré : ' + (d.subscriber_count || 0).toLocaleString('fr-FR') + ' abonnés.</span>';
				} catch (e) {
					status.innerHTML = '<span style=\"color: #dc3545;\">✗ ' + e.message + '</span>';
				} finally {
					btn.disabled = false;
				}
			});
		})();

		// Bouton Sync (récupération des nouvelles vidéos depuis YouTube)
		(function() {
			const syncBtn = document.getElementById('seoflix-sync-videos');
			if (!syncBtn) return;
			const syncStatus = document.getElementById('seoflix-sync-status');
			syncBtn.addEventListener('click', async function() {
				if (!confirm('Récupérer toutes les nouvelles vidéos > 7 min de cette chaîne ? Elles seront créées en statut \"En attente\".')) return;
				syncBtn.disabled = true;
				syncStatus.innerHTML = '⏳ Synchronisation en cours...';
				try {
					const formData = new FormData();
					formData.append('action', 'seoflix_sync_channel_videos');
					formData.append('_ajax_nonce', " . wp_json_encode( $sync_nonce ) . ");
					formData.append('channel_post_id', " . wp_json_encode( $post_id ) . ");
					const r = await fetch(" . wp_json_encode( $ajax_url ) . ", { method: 'POST', body: formData, credentials: 'same-origin' });
					const json = await r.json();
					if (!json.success) throw new Error(json.data || 'Erreur inconnue');
					const d = json.data;
					let msg = '<span style=\"color: #28a745;\">✓ ' + d.created + ' nouvelle(s) vidéo(s) créée(s) en attente</span>';
					if (d.skipped_existing > 0) msg += ', <span style=\"color: #999;\">' + d.skipped_existing + ' déjà en base</span>';
					if (d.skipped_short > 0) msg += ', <span style=\"color: #999;\">' + d.skipped_short + ' < 7 min ignorée(s)</span>';
					syncStatus.innerHTML = msg + '. <a href=\"' + " . wp_json_encode( admin_url( 'admin.php?page=seoflix-pending' ) ) . " + '\">Valider les vidéos →</a>';
				} catch (e) {
					syncStatus.innerHTML = '<span style=\"color: #dc3545;\">✗ ' + e.message + '</span>';
				} finally {
					syncBtn.disabled = false;
				}
			});
		})();
		";
		wp_register_script( 'seoflix-channel-meta', '', [], '1.0', true );
		wp_enqueue_script( 'seoflix-channel-meta' );
		wp_add_inline_script( 'seoflix-channel-meta', $js );
	}

	public static function ajax_sync_videos(): void {
		check_ajax_referer( self::SYNC_AJAX_NONCE );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Accès refusé.' );
		}

		$channel_post_id = isset( $_POST['channel_post_id'] ) ? (int) $_POST['channel_post_id'] : 0;
		if ( ! $channel_post_id || get_post_type( $channel_post_id ) !== CPT::CHANNEL ) {
			wp_send_json_error( 'Chaîne invalide.' );
		}

		$channel_yt_id = (string) get_post_meta( $channel_post_id, Meta_Keys::CHANNEL_YOUTUBE_ID, true );
		if ( ! $channel_yt_id ) {
			wp_send_json_error( 'ID YouTube de la chaîne manquant. Saisis-le ou utilise « Récupérer depuis YouTube » d\'abord.' );
		}

		// Récupère les vidéos via API
		$videos = YouTube_API::fetch_channel_videos( $channel_yt_id, 50, 420 );
		if ( is_wp_error( $videos ) ) {
			wp_send_json_error( $videos->get_error_message() );
		}

		$created          = 0;
		$skipped_existing = 0;
		$skipped_short    = 0; // pour info, déjà filtré côté API

		foreach ( $videos as $v ) {
			// Dédup par youtube_id
			$existing = get_posts( [
				'post_type'      => CPT::VIDEO,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[ 'key' => Meta_Keys::VIDEO_YOUTUBE_ID, 'value' => $v['youtube_id'] ],
				],
			] );
			if ( $existing ) {
				$skipped_existing++;
				continue;
			}

			$post_date = $v['published_at'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v['published_at'] )
				? $v['published_at'] . ' 00:00:00'
				: current_time( 'mysql' );

			$post_id = wp_insert_post( [
				'post_type'    => CPT::VIDEO,
				'post_title'   => sanitize_text_field( $v['title'] ),
				'post_content' => wp_kses_post( $v['description'] ),
				'post_status'  => 'pending',
				'post_date'    => $post_date,
			], true );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_ID, $v['youtube_id'] );
			update_post_meta( $post_id, Meta_Keys::VIDEO_CHANNEL_ID, $channel_post_id );
			update_post_meta( $post_id, Meta_Keys::VIDEO_DURATION, (int) $v['duration_seconds'] );
			update_post_meta( $post_id, Meta_Keys::VIDEO_VIEW_COUNT, (int) $v['view_count'] );
			update_post_meta( $post_id, Meta_Keys::VIDEO_PUBLISHED_AT, $v['published_at'] );
			update_post_meta( $post_id, Meta_Keys::VIDEO_THUMBNAIL_URL, esc_url_raw( $v['thumbnail_url'] ) );
			update_post_meta( $post_id, Meta_Keys::VIDEO_YOUTUBE_URL, esc_url_raw( $v['youtube_url'] ) );

			$created++;
		}

		wp_send_json_success( [
			'created'          => $created,
			'skipped_existing' => $skipped_existing,
			'skipped_short'    => $skipped_short,
			'total_fetched'    => count( $videos ),
		] );
	}

	public static function ajax_fetch_channel(): void {
		check_ajax_referer( self::AJAX_NONCE );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Accès refusé.' );
		}

		$handle = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
		if ( ! $handle ) {
			wp_send_json_error( 'Handle requis.' );
		}

		$result = YouTube_API::fetch_channel( $handle );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}
}
