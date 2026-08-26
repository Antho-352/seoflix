<?php
namespace Seoflix\Admin;

use Seoflix\CPT;
use Seoflix\YouTube_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tableau de bord WEAS : stats globales.
 */
final class Admin_Dashboard {

	public static function init(): void {}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$stats = self::stats();
		?>
		<div class="wrap seoflix-wrap">
			<h1>WEAS — Tableau de bord</h1>

			<div class="seoflix-stats">
				<div class="seoflix-stat">
					<div class="num"><?php echo (int) $stats['videos_published']; ?></div>
					<div class="label">Vidéos publiées</div>
				</div>
				<div class="seoflix-stat">
					<div class="num"><?php echo (int) $stats['videos_pending']; ?></div>
					<div class="label">Vidéos à valider</div>
				</div>
				<div class="seoflix-stat">
					<div class="num"><?php echo (int) $stats['channels']; ?></div>
					<div class="label">Chaînes</div>
				</div>
				<div class="seoflix-stat">
					<div class="num"><?php echo (int) $stats['products']; ?></div>
					<div class="label">Produits affiliés</div>
				</div>
			</div>

			<div class="seoflix-card">
				<h2>Démarrage rapide</h2>
				<ol>
					<li>Configure ta clé API YouTube dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Réglages</a> (optionnel pour l'instant — le backlog s'importe sans clé).</li>
					<li>Importe le backlog initial via <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-ingestion' ) ); ?>">Ingestion</a> → Importer JSON.</li>
					<li>Valide les vidéos dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-pending' ) ); ?>">Vidéos à valider</a> avant publication.</li>
					<li>Saisis tes URLs affiliées dans chaque produit (Produits → un produit → champ « URL affiliée »).</li>
				</ol>
			</div>

			<?php if ( $stats['videos_pending'] > 0 ) : ?>
				<div class="seoflix-card">
					<h2><?php echo (int) $stats['videos_pending']; ?> vidéo(s) en attente de validation</h2>
					<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-pending' ) ); ?>">Voir les vidéos à valider</a></p>
				</div>
			<?php endif; ?>

			<?php
			require_once SEOFLIX_PLUGIN_DIR . 'includes/class-youtube-api.php';
			$yt_today      = YouTube_API::get_today_usage();
			$yt_limit      = YouTube_API::get_daily_limit();
			$yt_configured = YouTube_API::is_configured();
			?>
			<div class="seoflix-card">
				<h2>YouTube Data API — utilisation aujourd'hui</h2>
				<?php if ( ! $yt_configured ) : ?>
					<p>Clé API non configurée. <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Configurer la clé →</a></p>
				<?php else : ?>
					<?php
					$pct = $yt_limit > 0 ? min( 100, (int) round( $yt_today / $yt_limit * 100 ) ) : 0;
					$bar_color = $pct >= 90 ? '#dc3545' : ( $pct >= 70 ? '#ffc107' : '#28a745' );
					?>
					<p style="font-size: 1.1rem; margin-bottom: 0.5rem;"><strong><?php echo esc_html( number_format_i18n( $yt_today ) ); ?></strong> unités utilisées
						<?php if ( $yt_limit > 0 ) : ?>
							sur <strong><?php echo esc_html( number_format_i18n( $yt_limit ) ); ?></strong> (limite locale)
						<?php else : ?>
							(pas de limite locale ; cap Google global = 10 000/j)
						<?php endif; ?>
					</p>
					<?php if ( $yt_limit > 0 ) : ?>
						<div style="background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 0.5rem;">
							<div style="background: <?php echo esc_attr( $bar_color ); ?>; height: 100%; width: <?php echo (int) $pct; ?>%;"></div>
						</div>
					<?php endif; ?>
					<p style="color: #666; font-size: 0.9rem;">Reset à minuit Pacific Time (≈ 9h du matin heure française). Modifier la limite : <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Réglages</a>.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function stats(): array {
		$videos_published = wp_count_posts( CPT::VIDEO )->publish ?? 0;
		$videos_pending   = wp_count_posts( CPT::VIDEO )->pending ?? 0;
		$channels         = wp_count_posts( CPT::CHANNEL )->publish ?? 0;
		$products         = wp_count_posts( CPT::PRODUCT )->publish ?? 0;

		return [
			'videos_published' => (int) $videos_published,
			'videos_pending'   => (int) $videos_pending,
			'channels'         => (int) $channels,
			'products'         => (int) $products,
		];
	}
}
