<?php
namespace Seoflix\Admin;

use Seoflix\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tableau de bord Seoflix : stats globales.
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
			<h1>Seoflix — Tableau de bord</h1>

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
