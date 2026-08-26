<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\DB_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin WEAS → Stats affiliation.
 */
final class Admin_Affiliate_Stats {

	public static function init(): void {
		// Hook menu (l'item est ajouté par Admin_Menu)
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		global $wpdb;
		$table_clicks = DB_Schema::table_affiliate_clicks();

		// Stats globales
		$total_30d = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_clicks} WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
		$total_7d  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_clicks} WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );
		$total_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_clicks}" );

		// Top produits sur 30j
		$top_products = $wpdb->get_results( "
			SELECT product_id, COUNT(*) as clicks
			FROM {$table_clicks}
			WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY product_id
			ORDER BY clicks DESC
			LIMIT 20
		" );

		// Top vidéos sources sur 30j
		$top_videos = $wpdb->get_results( "
			SELECT source_video_id, COUNT(*) as clicks
			FROM {$table_clicks}
			WHERE source_video_id IS NOT NULL
			  AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY source_video_id
			ORDER BY clicks DESC
			LIMIT 10
		" );

		?>
		<div class="wrap seoflix-wrap">
			<h1>WEAS — Stats affiliation</h1>

			<div class="seoflix-stats">
				<div class="seoflix-stat">
					<div class="num"><?php echo esc_html( number_format_i18n( $total_7d ) ); ?></div>
					<div class="label">Clics — 7 derniers jours</div>
				</div>
				<div class="seoflix-stat">
					<div class="num"><?php echo esc_html( number_format_i18n( $total_30d ) ); ?></div>
					<div class="label">Clics — 30 derniers jours</div>
				</div>
				<div class="seoflix-stat">
					<div class="num"><?php echo esc_html( number_format_i18n( $total_all ) ); ?></div>
					<div class="label">Clics depuis le début</div>
				</div>
			</div>

			<div class="seoflix-card">
				<h2>Top 20 produits — 30 derniers jours</h2>
				<?php if ( $top_products ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Produit</th>
								<th>Catégorie</th>
								<th style="text-align: right;">Clics</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $top_products as $row ) :
							$post = get_post( (int) $row->product_id );
							if ( ! $post ) continue;
							$cats = wp_get_object_terms( $post->ID, 'seoflix_product_category', [ 'number' => 1 ] );
							$cat  = ( ! is_wp_error( $cats ) && $cats ) ? $cats[0]->name : '—'; ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></td>
								<td><?php echo esc_html( $cat ); ?></td>
								<td style="text-align: right; font-variant-numeric: tabular-nums;"><strong><?php echo esc_html( number_format_i18n( (int) $row->clicks ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>Aucun clic enregistré sur les 30 derniers jours.</p>
				<?php endif; ?>
			</div>

			<div class="seoflix-card">
				<h2>Top 10 vidéos sources — 30 derniers jours</h2>
				<?php if ( $top_videos ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Vidéo</th>
								<th style="text-align: right;">Clics affiliés générés</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $top_videos as $row ) :
							$post = get_post( (int) $row->source_video_id );
							if ( ! $post ) continue; ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></td>
								<td style="text-align: right; font-variant-numeric: tabular-nums;"><strong><?php echo esc_html( number_format_i18n( (int) $row->clicks ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>Aucun clic avec page source identifiée sur les 30 derniers jours.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
