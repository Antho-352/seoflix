<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permet d'ordonner les vidéos au sein d'un parcours d'apprentissage (taxonomy seoflix_path).
 *
 * Stocke un meta `_seoflix_path_order` (int) sur chaque vidéo : son rang dans le parcours.
 * Ajoute un champ "Ordre dans le parcours" dans la métabox de la vidéo.
 *
 * Utilisé par User_Accounts::path_progress() et par les templates frontend.
 */
final class Path_Order {

	public const META_ORDER_KEY = '_seoflix_path_order';

	public static function init(): void {
		add_action( 'add_meta_boxes',                       [ self::class, 'register_metabox' ] );
		add_action( 'save_post_seoflix_video',              [ self::class, 'save_metabox' ], 10, 2 );
	}

	public static function register_metabox(): void {
		add_meta_box(
			'seoflix_path_order',
			'Parcours — ordre dans le parcours',
			[ self::class, 'render_metabox' ],
			CPT::VIDEO,
			'side',
			'default'
		);
	}

	public static function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'seoflix_path_order_save', 'seoflix_path_order_nonce' );
		$order = (int) get_post_meta( $post->ID, self::META_ORDER_KEY, true );
		$paths = wp_get_object_terms( $post->ID, 'seoflix_path' );
		?>
		<p>
			<label for="seoflix_path_order">Ordre dans le parcours :</label><br>
			<input type="number" id="seoflix_path_order" name="seoflix_path_order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" max="999" class="small-text" style="width:80px;">
		</p>
		<p class="description">
			<?php if ( ! is_wp_error( $paths ) && $paths ) : ?>
				Cette vidéo fait partie du/des parcours :<br>
				<?php foreach ( $paths as $p ) : ?>
					→ <strong><?php echo esc_html( $p->name ); ?></strong><br>
				<?php endforeach; ?>
			<?php else : ?>
				Pas encore associée à un parcours. Ajoute-la via la métabox « Parcours » du contenu.
			<?php endif; ?>
		</p>
		<p class="description">Plus le chiffre est petit, plus la vidéo apparaît tôt dans le parcours. <code>0</code> = ordre par date de publication. Si plusieurs vidéos ont le même ordre, elles seront triées par date.</p>
		<?php
	}

	public static function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['seoflix_path_order_nonce'] ) || ! wp_verify_nonce( $_POST['seoflix_path_order_nonce'], 'seoflix_path_order_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$value = isset( $_POST['seoflix_path_order'] ) ? max( 0, min( 999, (int) $_POST['seoflix_path_order'] ) ) : 0;
		if ( $value > 0 ) {
			update_post_meta( $post_id, self::META_ORDER_KEY, $value );
		} else {
			delete_post_meta( $post_id, self::META_ORDER_KEY );
		}
	}
}
