<?php
namespace Seoflix\Admin;

use Seoflix\Newsletter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin newsletter :
 *   - Colonnes "E-mail", "Source", "Date" sur la liste des seoflix_subscriber
 *   - Bouton export CSV
 */
final class Admin_Newsletter {

	public static function init(): void {
		add_filter( 'manage_seoflix_subscriber_posts_columns',       [ self::class, 'columns' ] );
		add_action( 'manage_seoflix_subscriber_posts_custom_column', [ self::class, 'column_content' ], 10, 2 );

		add_filter( 'bulk_actions-edit-seoflix_subscriber',          [ self::class, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-seoflix_subscriber',   [ self::class, 'handle_bulk_export' ], 10, 3 );

		add_action( 'admin_menu',                  [ self::class, 'add_export_button' ], 12 );
		add_action( 'admin_post_seoflix_news_export', [ self::class, 'export_csv' ] );
	}

	public static function columns( array $cols ): array {
		// On vire la date par défaut, on remet la colonne "title" en "E-mail"
		$new = [
			'cb'        => $cols['cb'] ?? '',
			'sx_email'  => 'E-mail',
			'sx_source' => 'Source',
			'sx_date'   => 'Inscrit le',
		];
		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'sx_email':
				$email = get_the_title( $post_id );
				echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
				break;
			case 'sx_source':
				$src = (string) get_post_meta( $post_id, Newsletter::META_SOURCE, true );
				$labels = [
					'homepage'     => 'Home',
					'footer'       => 'Footer',
					'registration' => 'Inscription',
				];
				echo '<code>' . esc_html( $labels[ $src ] ?? $src ?: '—' ) . '</code>';
				break;
			case 'sx_date':
				echo esc_html( get_the_date( 'd/m/Y H:i', $post_id ) );
				break;
		}
	}

	public static function add_export_button(): void {
		// Petit hack : ajoute un bouton "Exporter CSV" en haut de la liste
		add_action( 'admin_notices', [ self::class, 'render_export_button' ] );
	}

	public static function render_export_button(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'edit-seoflix_subscriber' ) {
			return;
		}
		$count = wp_count_posts( Newsletter::CPT );
		$total = (int) ( $count->publish ?? 0 );
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=seoflix_news_export' ), 'seoflix_news_export' );
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> inscrit(s) à la newsletter.
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" style="margin-left: 0.5rem;">📥 Exporter en CSV</a>
			</p>
		</div>
		<?php
	}

	public static function register_bulk_actions( array $actions ): array {
		$actions['seoflix_news_export_selected'] = '📥 Exporter en CSV';
		return $actions;
	}

	public static function handle_bulk_export( string $redirect_url, string $action, array $post_ids ): string {
		if ( $action !== 'seoflix_news_export_selected' || ! current_user_can( 'manage_options' ) ) {
			return $redirect_url;
		}
		self::stream_csv( $post_ids );
		exit;
	}

	public static function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'seoflix_news_export' );

		$ids = get_posts( [
			'post_type'      => Newsletter::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		self::stream_csv( $ids );
		exit;
	}

	private static function stream_csv( array $post_ids ): void {
		$filename = 'seoflix-newsletter-' . gmdate( 'Y-m-d-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		// BOM UTF-8 pour Excel
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, [ 'email', 'source', 'inscrit_le', 'ip_hash' ] );
		foreach ( $post_ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || $post->post_type !== Newsletter::CPT ) {
				continue;
			}
			fputcsv( $out, [
				$post->post_title,
				(string) get_post_meta( $pid, Newsletter::META_SOURCE, true ),
				get_the_date( 'Y-m-d H:i:s', $post ),
				(string) get_post_meta( $pid, Newsletter::META_IP_HASH, true ),
			] );
		}
		fclose( $out );
	}
}
