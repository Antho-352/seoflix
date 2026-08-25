<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordonne indépendamment les vidéos dans chaque parcours d'apprentissage.
 *
 * Les ordres courants sont stockés sous forme de JSON `{term_id: order}` dans
 * Meta_Keys::VIDEO_PATH_ORDERS. L'ancienne méta globale reste lisible tant
 * qu'aucune carte n'a encore été créée pour la vidéo.
 */
final class Path_Order {

	/** Ancienne clé globale, conservée uniquement pour fallback et migration. */
	public const META_ORDER_KEY = '_seoflix_path_order';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register_metabox' ] );
		add_action( 'save_post_seoflix_video', [ self::class, 'save_metabox' ], 10, 2 );
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

	/**
	 * Normalise une carte JSON/tableau en ordres strictement positifs.
	 *
	 * @param mixed $value JSON ou tableau term_id => order.
	 * @return array<int,int>
	 */
	public static function sanitize_order_map( $value ): array {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}

		$orders = [];
		foreach ( $value as $term_id => $order ) {
			$term_id = (int) $term_id;
			$order   = (int) $order;
			if ( $term_id > 0 && $order > 0 ) {
				$orders[ $term_id ] = $order;
			}
		}
		return $orders;
	}

	/** @return array<int,int> */
	public static function get_order_map( int $video_id ): array {
		$raw = get_post_meta( $video_id, Meta_Keys::VIDEO_PATH_ORDERS, true );
		return self::sanitize_order_map( $raw );
	}

	/**
	 * Retourne l'ordre explicite d'une vidéo pour un parcours, ou zéro.
	 *
	 * Le fallback global n'est utilisé que si la nouvelle méta n'existe pas du
	 * tout. Une carte JSON vide signifie donc explicitement « non ordonnée ».
	 */
	public static function get_explicit_order( int $video_id, int $term_id ): int {
		$orders = self::get_order_map( $video_id );
		if ( metadata_exists( 'post', $video_id, Meta_Keys::VIDEO_PATH_ORDERS ) ) {
			return $orders[ $term_id ] ?? 0;
		}

		$legacy = (int) get_post_meta( $video_id, self::META_ORDER_KEY, true );
		return $legacy > 0 ? $legacy : 0;
	}

	/**
	 * Retourne toutes les vidéos publiées d'un parcours dans un ordre stable.
	 *
	 * Les vidéos ordonnées précèdent les autres. Les égalités et les vidéos non
	 * ordonnées suivent l'ordre date de publication puis ID fourni par WP_Query.
	 * Aucune contrainte de méta n'exclut les vidéos sans ordre.
	 *
	 * @return int[]
	 */
	public static function ordered_video_ids_for_term( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return [];
		}

		$video_ids = array_map( 'intval', get_posts( [
			'post_type'      => CPT::VIDEO,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => Taxonomies::PATH,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'orderby'        => [ 'date' => 'ASC', 'ID' => 'ASC' ],
		] ) );
		if ( $video_ids ) {
			_prime_post_caches( $video_ids, false, false );
			update_meta_cache( 'post', $video_ids );
		}

		$explicit = [];
		$unordered = [];
		foreach ( $video_ids as $position => $video_id ) {
			$order = self::get_explicit_order( $video_id, $term_id );
			if ( $order > 0 ) {
				$explicit[] = [
					'id'       => $video_id,
					'order'    => $order,
					'position' => $position,
				];
			} else {
				$unordered[] = $video_id;
			}
		}

		usort( $explicit, static function ( array $left, array $right ): int {
			return ( $left['order'] <=> $right['order'] )
				?: ( $left['position'] <=> $right['position'] );
		} );

		return array_merge( array_column( $explicit, 'id' ), $unordered );
	}

	public static function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'seoflix_path_order_save', 'seoflix_path_order_nonce' );
		$paths = wp_get_object_terms( $post->ID, Taxonomies::PATH );
		?>
		<p class="description">Un ordre distinct peut être défini pour chaque parcours. <code>0</code> ou vide = ordre par date de publication.</p>
		<?php if ( ! is_wp_error( $paths ) && $paths ) : ?>
			<?php foreach ( $paths as $path ) :
				$order = self::get_explicit_order( $post->ID, (int) $path->term_id );
				$field_id = 'seoflix_path_order_' . (int) $path->term_id;
				?>
				<p>
					<label for="<?php echo esc_attr( $field_id ); ?>"><strong><?php echo esc_html( $path->name ); ?></strong></label><br>
					<input type="number" id="<?php echo esc_attr( $field_id ); ?>" name="seoflix_path_orders[<?php echo (int) $path->term_id; ?>]" value="<?php echo $order > 0 ? esc_attr( (string) $order ) : ''; ?>" min="0" step="1" class="small-text" style="width:80px;">
				</p>
			<?php endforeach; ?>
		<?php else : ?>
			<p class="description">Pas encore associée à un parcours. Ajoute-la via la métabox « Parcours » du contenu.</p>
		<?php endif; ?>
		<?php
	}

	public static function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['seoflix_path_order_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['seoflix_path_order_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'seoflix_path_order_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! DB_Schema::acquire_path_order_lock( 10 ) ) {
			wp_die(
				esc_html__( 'La migration des parcours est en cours. Recharge cette page puis enregistre à nouveau.', 'seoflix' ),
				esc_html__( 'Ordre des parcours temporairement verrouillé', 'seoflix' ),
				[ 'response' => 409, 'back_link' => true ]
			);
		}

		try {
		$assigned_term_ids = wp_get_object_terms( $post_id, Taxonomies::PATH, [ 'fields' => 'ids' ] );
		if ( is_wp_error( $assigned_term_ids ) ) {
			return;
		}

		$submitted = isset( $_POST['seoflix_path_orders'] )
			? wp_unslash( $_POST['seoflix_path_orders'] )
			: [];
		if ( ! is_array( $submitted ) ) {
			$submitted = [];
		}

		$existing = self::get_order_map( $post_id );
		$orders   = [];
		foreach ( array_map( 'intval', $assigned_term_ids ) as $term_id ) {
			if ( array_key_exists( $term_id, $submitted ) ) {
				$value = (int) $submitted[ $term_id ];
				if ( $value > 0 ) {
					$orders[ $term_id ] = $value;
				}
			} elseif ( isset( $existing[ $term_id ] ) ) {
				$orders[ $term_id ] = $existing[ $term_id ];
			}
		}

		update_post_meta( $post_id, Meta_Keys::VIDEO_PATH_ORDERS, wp_json_encode( (object) $orders ) );
		} finally {
			DB_Schema::release_path_order_lock();
		}
	}
}
