<?php
namespace Seoflix\Admin;

use Seoflix\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin Seoflix → Ingestion.
 *
 * V1 : Importer un fichier JSON conforme à docs/IMPORT_FORMAT.md.
 * V2 : boutons de scan YouTube par chaîne.
 */
final class Admin_Ingestion {

	private const NONCE_ACTION = 'seoflix_ingestion_import';
	private const NONCE_NAME   = 'seoflix_ingestion_nonce';

	public static function init(): void {
		add_action( 'admin_post_seoflix_import_json', [ self::class, 'handle_import' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		// Affichage du rapport (transient post-redirect-get)
		$report_key = 'seoflix_import_report_' . get_current_user_id();
		$report     = get_transient( $report_key );
		if ( $report ) {
			delete_transient( $report_key );
		}
		?>
		<div class="wrap seoflix-wrap">
			<h1>Seoflix — Ingestion</h1>

			<?php if ( $report ) : self::render_report( $report ); endif; ?>

			<div class="seoflix-card">
				<h2>Importer un backlog JSON</h2>
				<p>Téléverse un fichier JSON conforme à <code>docs/IMPORT_FORMAT.md</code>. Les vidéos seront importées en statut <strong>« en attente de validation »</strong>.</p>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="seoflix_import_json">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

					<table class="form-table">
						<tr>
							<th scope="row"><label for="seoflix_json_file">Fichier JSON</label></th>
							<td>
								<input type="file" name="seoflix_json_file" id="seoflix_json_file" accept=".json,application/json" required>
								<p class="description">Taille max : <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></p>
							</td>
						</tr>
					</table>

					<p>
						<button type="submit" class="button button-primary">Importer</button>
					</p>
				</form>
			</div>

			<div class="seoflix-card">
				<h2>Ingestion automatique YouTube</h2>
				<p><em>À venir (phase 3) :</em> boutons « Scanner toutes les chaînes » et « Scanner [nom chaîne] » qui interrogeront l'API YouTube Data v3, détecteront les nouvelles vidéos non présentes en base, et les créeront en statut « pending » pour validation.</p>
				<p>Pour activer : configure ta clé YouTube Data API dans <a href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-settings' ) ); ?>">Réglages</a>.</p>
			</div>
		</div>
		<?php
	}

	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$redirect = admin_url( 'admin.php?page=seoflix-ingestion' );

		if ( empty( $_FILES['seoflix_json_file']['tmp_name'] ) ) {
			self::store_report( [ 'ok' => false, 'errors' => [ 'Aucun fichier reçu.' ], 'channels_created' => 0, 'channels_updated' => 0, 'videos_created' => 0, 'videos_updated' => 0, 'products_created' => 0, 'products_updated' => 0 ] );
			wp_safe_redirect( $redirect );
			exit;
		}

		$tmp = $_FILES['seoflix_json_file']['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) ) {
			self::store_report( [ 'ok' => false, 'errors' => [ 'Upload invalide.' ], 'channels_created' => 0, 'channels_updated' => 0, 'videos_created' => 0, 'videos_updated' => 0, 'products_created' => 0, 'products_updated' => 0 ] );
			wp_safe_redirect( $redirect );
			exit;
		}

		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-importer.php';
		require_once SEOFLIX_PLUGIN_DIR . 'includes/class-meta-keys.php';

		$report = Importer::import_from_file( $tmp );
		self::store_report( $report );

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function store_report( array $report ): void {
		set_transient( 'seoflix_import_report_' . get_current_user_id(), $report, 60 );
	}

	private static function render_report( array $r ): void {
		$class = ! empty( $r['ok'] ) ? 'ok' : 'err';
		?>
		<div class="seoflix-report <?php echo esc_attr( $class ); ?>">
			<strong>Rapport d'import :</strong>
			<ul style="margin: 0.5rem 0 0;">
				<li>Chaînes : <?php echo (int) $r['channels_created']; ?> créée(s), <?php echo (int) $r['channels_updated']; ?> mise(s) à jour</li>
				<li>Produits : <?php echo (int) $r['products_created']; ?> créé(s), <?php echo (int) $r['products_updated']; ?> mis à jour</li>
				<li>Vidéos : <?php echo (int) $r['videos_created']; ?> créée(s), <?php echo (int) $r['videos_updated']; ?> mise(s) à jour (statut « en attente »)</li>
			</ul>
			<?php if ( ! empty( $r['errors'] ) ) : ?>
				<details style="margin-top: 0.75rem;">
					<summary><strong><?php echo count( $r['errors'] ); ?> erreur(s)</strong></summary>
					<ul style="margin: 0.5rem 0 0; font-family: monospace; font-size: 0.85rem;">
						<?php foreach ( $r['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}
}
