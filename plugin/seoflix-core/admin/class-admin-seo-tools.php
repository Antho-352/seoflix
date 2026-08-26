<?php
namespace Seoflix\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outils SEO :
 *  - Éditeur robots.txt (override le robots.txt virtuel de WP)
 *  - Éditeur .htaccess (avec backup automatique avant chaque save, restauration possible)
 */
final class Admin_SEO_Tools {

	public const OPTION_ROBOTS = 'seoflix_robots_txt';

	public static function init(): void {
		add_action( 'admin_menu',   [ self::class, 'register_pages' ], 12 );
		add_filter( 'robots_txt',   [ self::class, 'filter_robots_txt' ], 99, 2 );

		add_action( 'admin_post_seoflix_save_robots',   [ self::class, 'handle_save_robots' ] );
		add_action( 'admin_post_seoflix_save_htaccess', [ self::class, 'handle_save_htaccess' ] );
		add_action( 'admin_post_seoflix_restore_htaccess', [ self::class, 'handle_restore_htaccess' ] );
	}

	public static function register_pages(): void {
		add_submenu_page( 'seoflix', 'robots.txt', 'robots.txt', 'manage_options', 'seoflix-seo-robots', [ self::class, 'render_robots_page' ] );
		add_submenu_page( 'seoflix', '.htaccess',  '.htaccess',  'manage_options', 'seoflix-seo-htaccess', [ self::class, 'render_htaccess_page' ] );
	}

	/* ======================================================================
	 *  robots.txt
	 * ====================================================================== */

	public static function filter_robots_txt( string $output, $public ): string {
		$custom = (string) get_option( self::OPTION_ROBOTS, '' );
		if ( $custom ) {
			return $custom;
		}
		// Fallback : robots.txt par défaut + sitemap WP
		$sitemap = home_url( '/wp-sitemap.xml' );
		return $output . "\nSitemap: {$sitemap}\n";
	}

	public static function render_robots_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$current = (string) get_option( self::OPTION_ROBOTS, '' );
		$default = self::default_robots_content();
		?>
		<div class="wrap seoflix-wrap">
			<h1>robots.txt</h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>robots.txt enregistré.</p></div>
			<?php endif; ?>

			<div class="seoflix-card">
				<p>WordPress sert un robots.txt <strong>virtuel</strong> à <code><?php echo esc_url( home_url( '/robots.txt' ) ); ?></code>. Le contenu ci-dessous remplace ce fichier virtuel. Si tu laisses vide, WP utilise sa configuration par défaut (+ sitemap auto).</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="seoflix_save_robots">
					<?php wp_nonce_field( 'seoflix_save_robots' ); ?>
					<textarea name="robots" rows="14" class="large-text code" spellcheck="false" placeholder="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $current ); ?></textarea>
					<p class="description">Voir <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank">le robots.txt actuel</a>. Le sitemap est <code><?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?></code> — pense à l'inclure (<code>Sitemap: ...</code>).</p>
					<?php submit_button( 'Enregistrer robots.txt' ); ?>
				</form>

				<details style="margin-top: 1rem;">
					<summary style="cursor: pointer;">Modèle conseillé pour WEAS</summary>
					<pre style="background: #f6f7f7; padding: 1rem; border-radius: 4px;"><?php echo esc_html( $default ); ?></pre>
				</details>
			</div>
		</div>
		<?php
	}

	private static function default_robots_content(): string {
		$sitemap = home_url( '/wp-sitemap.xml' );
		return <<<TXT
User-agent: *
Allow: /
Disallow: /wp-admin/
Disallow: /wp-login.php
Disallow: /go/
Disallow: /?s=
Disallow: /search/

Allow: /wp-admin/admin-ajax.php

# IA crawlers : autorise (utile pour la visibilité GEO)
User-agent: GPTBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Google-Extended
Allow: /

Sitemap: {$sitemap}
TXT;
	}

	public static function handle_save_robots(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'seoflix_save_robots' );

		$value = isset( $_POST['robots'] ) ? wp_unslash( (string) $_POST['robots'] ) : '';
		$value = trim( $value );
		if ( $value === '' ) {
			delete_option( self::OPTION_ROBOTS );
		} else {
			update_option( self::OPTION_ROBOTS, $value, false );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'seoflix-seo-robots', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ======================================================================
	 *  .htaccess
	 * ====================================================================== */

	private static function htaccess_path(): string {
		return get_home_path() . '.htaccess';
	}

	private static function backup_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'seoflix-backups';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" );
			file_put_contents( $dir . '/index.html', '' );
		}
		return $dir;
	}

	public static function render_htaccess_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$path     = self::htaccess_path();
		$exists   = file_exists( $path );
		$readable = $exists && is_readable( $path );
		$writable = $exists && is_writable( $path );
		$content  = $readable ? (string) file_get_contents( $path ) : '';

		$backups = self::list_backups();
		?>
		<div class="wrap seoflix-wrap">
			<h1>.htaccess</h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>.htaccess enregistré. Un backup a été créé. Si le site est cassé, restaure depuis la liste ci-dessous.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['restored'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>.htaccess restauré depuis le backup.</p></div>
			<?php endif; ?>

			<div class="seoflix-card">
				<h2 style="color: #b32d2e;">⚠️ Attention</h2>
				<p>Une erreur dans <code>.htaccess</code> peut <strong>casser tout le site</strong> (erreur 500). Avant chaque sauvegarde, un backup automatique est créé dans <code>/wp-content/uploads/seoflix-backups/</code>. Tu peux restaurer depuis la liste en bas de page.</p>
				<p>En cas de site cassé sans accès admin : utilise FTP/cPanel File Manager pour copier un backup à la place du <code>.htaccess</code> à la racine.</p>
			</div>

			<div class="seoflix-card">
				<h2>Fichier actuel</h2>
				<p>Chemin : <code><?php echo esc_html( $path ); ?></code><br>
					État :
					<?php if ( ! $exists ) : ?>
						<span style="color:#d63638;">Inexistant</span>
					<?php elseif ( ! $writable ) : ?>
						<span style="color:#d63638;">Lecture seule</span> (chmod 644 nécessaire)
					<?php else : ?>
						<span style="color:#28a745;">Lisible et modifiable</span>
					<?php endif; ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Confirmer la sauvegarde ? Un backup automatique va être créé. Si le site casse, restaure depuis la liste.');">
					<input type="hidden" name="action" value="seoflix_save_htaccess">
					<?php wp_nonce_field( 'seoflix_save_htaccess' ); ?>
					<textarea name="htaccess" rows="20" class="large-text code" spellcheck="false" <?php disabled( ! $writable ); ?>><?php echo esc_textarea( $content ); ?></textarea>
					<?php if ( $writable ) : ?>
						<?php submit_button( 'Enregistrer .htaccess (avec backup auto)' ); ?>
					<?php else : ?>
						<p>Édition désactivée : le fichier n'est pas modifiable. <code>chmod 644</code> via FTP/cPanel pour activer.</p>
					<?php endif; ?>
				</form>
			</div>

			<div class="seoflix-card">
				<h2>Backups (<?php echo count( $backups ); ?>)</h2>
				<?php if ( $backups ) : ?>
					<table class="widefat striped">
						<thead><tr><th>Date</th><th>Taille</th><th>Action</th></tr></thead>
						<tbody>
							<?php foreach ( array_slice( $backups, 0, 30 ) as $b ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', $b['mtime'] ) ); ?></td>
									<td><?php echo esc_html( size_format( $b['size'] ) ); ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Restaurer ce backup ? Le .htaccess actuel sera remplacé (un nouveau backup sera créé avant la restauration).');">
											<input type="hidden" name="action" value="seoflix_restore_htaccess">
											<input type="hidden" name="file" value="<?php echo esc_attr( basename( $b['path'] ) ); ?>">
											<?php wp_nonce_field( 'seoflix_restore_htaccess' ); ?>
											<button type="submit" class="button button-small">Restaurer</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( count( $backups ) > 30 ) : ?>
						<p><em>30 backups récents affichés sur <?php echo count( $backups ); ?>.</em></p>
					<?php endif; ?>
				<?php else : ?>
					<p>Aucun backup pour le moment.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function list_backups(): array {
		$dir = self::backup_dir();
		$out = [];
		foreach ( glob( $dir . '/htaccess-*.bak' ) ?: [] as $path ) {
			$out[] = [
				'path'  => $path,
				'mtime' => filemtime( $path ),
				'size'  => filesize( $path ),
			];
		}
		usort( $out, static fn( $a, $b ) => $b['mtime'] <=> $a['mtime'] );
		return $out;
	}

	public static function handle_save_htaccess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'seoflix_save_htaccess' );

		$path = self::htaccess_path();
		if ( ! is_writable( $path ) ) {
			wp_die( 'Fichier .htaccess non modifiable. Vérifie les permissions (chmod 644).' );
		}

		// Backup avant écriture
		$current = file_exists( $path ) ? (string) file_get_contents( $path ) : '';
		$bak     = self::backup_dir() . '/htaccess-' . gmdate( 'Y-m-d-His' ) . '.bak';
		if ( $current !== '' ) {
			file_put_contents( $bak, $current );
		}

		$new = isset( $_POST['htaccess'] ) ? wp_unslash( (string) $_POST['htaccess'] ) : '';
		$new = str_replace( "\r\n", "\n", $new );
		// Garde-fou : refuse une chaîne vide pour éviter de blanchir par accident
		if ( trim( $new ) === '' ) {
			wp_die( '.htaccess vide refusé. Utilise la restauration depuis un backup pour vider proprement.' );
		}

		file_put_contents( $path, $new );

		// Rotation : garde max 50 backups
		$all = self::list_backups();
		foreach ( array_slice( $all, 50 ) as $old ) {
			@unlink( $old['path'] );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'seoflix-seo-htaccess', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_restore_htaccess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'seoflix_restore_htaccess' );

		$file = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		if ( ! preg_match( '/^htaccess-\d{4}-\d{2}-\d{2}-\d{6}\.bak$/', $file )
			|| strpos( $file, '..' ) !== false
			|| strpos( $file, '/' ) !== false
			|| strpos( $file, '\\' ) !== false ) {
			wp_die( 'Nom de fichier invalide.' );
		}
		$bak_dir  = self::backup_dir();
		$bak_path = $bak_dir . '/' . $file;
		// Vérifie que le path résolu reste dans backup_dir (anti path-traversal)
		$real_bak = realpath( $bak_path );
		$real_dir = realpath( $bak_dir );
		if ( ! $real_bak || ! $real_dir || strpos( $real_bak, $real_dir ) !== 0 ) {
			wp_die( 'Chemin invalide.' );
		}
		if ( ! file_exists( $bak_path ) ) {
			wp_die( 'Backup introuvable.' );
		}

		$path = self::htaccess_path();

		// Backup du courant avant restauration
		$current = file_exists( $path ) ? (string) file_get_contents( $path ) : '';
		if ( $current !== '' ) {
			$pre_restore = self::backup_dir() . '/htaccess-' . gmdate( 'Y-m-d-His' ) . '.bak';
			file_put_contents( $pre_restore, $current );
		}

		copy( $bak_path, $path );

		wp_safe_redirect( add_query_arg( [ 'page' => 'seoflix-seo-htaccess', 'restored' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
