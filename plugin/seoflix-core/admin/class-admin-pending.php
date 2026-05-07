<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\Meta_Keys;
use Seoflix\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin Seoflix → Vidéos à valider.
 *
 * Liste les vidéos en statut `pending`, avec actions Publier / Modifier / Rejeter.
 */
final class Admin_Pending {

	private const NONCE_ACTION = 'seoflix_pending_action';
	private const NONCE_NAME   = 'seoflix_pending_nonce';

	public static function init(): void {
		add_action( 'admin_post_seoflix_pending_action', [ self::class, 'handle_action' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$videos = get_posts( [
			'post_type'      => CPT::VIDEO,
			'post_status'    => 'pending',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		?>
		<div class="wrap seoflix-wrap">
			<h1>Seoflix — Vidéos à valider <span class="title-count"><?php echo count( $videos ); ?></span></h1>

			<?php if ( ! $videos ) : ?>
				<div class="seoflix-card">
					<p>Aucune vidéo en attente de validation.</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<div class="seoflix-card" style="padding: 0;">
				<?php foreach ( $videos as $v ) : self::render_row( $v ); endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_row( \WP_Post $v ): void {
		$thumb       = get_post_meta( $v->ID, Meta_Keys::VIDEO_THUMBNAIL_URL, true );
		$duration    = (int) get_post_meta( $v->ID, Meta_Keys::VIDEO_DURATION, true );
		$views       = (int) get_post_meta( $v->ID, Meta_Keys::VIDEO_VIEW_COUNT, true );
		$channel_id  = (int) get_post_meta( $v->ID, Meta_Keys::VIDEO_CHANNEL_ID, true );
		$channel     = $channel_id ? get_post( $channel_id ) : null;
		$topics      = wp_get_object_terms( $v->ID, Taxonomies::TOPIC, [ 'fields' => 'names' ] );
		$formats     = wp_get_object_terms( $v->ID, Taxonomies::FORMAT, [ 'fields' => 'names' ] );

		$publish_url = self::action_url( $v->ID, 'publish' );
		$reject_url  = self::action_url( $v->ID, 'reject' );
		$edit_url    = get_edit_post_link( $v->ID );

		?>
		<div class="seoflix-pending-row">
			<div>
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="">
				<?php endif; ?>
			</div>
			<div>
				<strong><?php echo esc_html( $v->post_title ); ?></strong>
				<div class="meta">
					<?php if ( $channel ) : ?>
						<?php echo esc_html( $channel->post_title ); ?> ·
					<?php endif; ?>
					<?php echo esc_html( self::format_duration( $duration ) ); ?> ·
					<?php echo esc_html( number_format_i18n( $views ) ); ?> vues
				</div>
				<div class="meta">
					<?php if ( ! is_wp_error( $topics ) && $topics ) : ?>
						<strong>Sujets :</strong> <?php echo esc_html( implode( ', ', $topics ) ); ?>
					<?php endif; ?>
					<?php if ( ! is_wp_error( $formats ) && $formats ) : ?>
						 · <strong>Format :</strong> <?php echo esc_html( implode( ', ', $formats ) ); ?>
					<?php endif; ?>
				</div>
				<details style="margin-top: 0.5rem;">
					<summary>Description IA</summary>
					<p style="margin: 0.5rem 0; padding: 0.5rem; background: #f6f7f7; border-radius: 4px;"><?php echo nl2br( esc_html( $v->post_content ) ); ?></p>
				</details>
			</div>
			<div class="actions">
				<a class="button button-primary" href="<?php echo esc_url( $publish_url ); ?>">Publier</a>
				<a class="button" href="<?php echo esc_url( $edit_url ); ?>">Modifier</a>
				<a class="button" href="<?php echo esc_url( $reject_url ); ?>" onclick="return confirm('Rejeter cette vidéo ?');">Rejeter</a>
			</div>
		</div>
		<?php
	}

	private static function action_url( int $post_id, string $what ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=seoflix_pending_action&post_id=' . $post_id . '&op=' . $what ),
			self::NONCE_ACTION,
			self::NONCE_NAME
		);
	}

	private static function format_duration( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return '?';
		}
		$h = (int) floor( $seconds / 3600 );
		$m = (int) floor( ( $seconds % 3600 ) / 60 );
		$s = $seconds % 60;
		if ( $h > 0 ) {
			return sprintf( '%dh%02d', $h, $m );
		}
		return sprintf( '%d:%02d', $m, $s );
	}

	public static function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		$op      = isset( $_GET['op'] ) ? sanitize_key( $_GET['op'] ) : '';

		if ( ! $post_id || get_post_type( $post_id ) !== CPT::VIDEO ) {
			wp_die( 'Vidéo invalide.' );
		}

		switch ( $op ) {
			case 'publish':
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
				break;
			case 'reject':
				wp_trash_post( $post_id );
				break;
			default:
				wp_die( 'Action inconnue.' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seoflix-pending' ) );
		exit;
	}
}
