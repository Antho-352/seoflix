<?php
namespace Seoflix\Admin;

use Seoflix\DB_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin RGPD : recherche, export, effacement des données par e-mail ou IP.
 *
 * Sources de données scannées :
 *   - wp_users / wp_usermeta            (par e-mail)
 *   - wp_comments / wp_commentmeta      (par e-mail ou IP en clair)
 *   - wp_seoflix_affiliate_clicks       (par hash de l'IP fournie)
 *
 * Limitations :
 *   - Les logs Apache (côté hébergeur) ne sont PAS scannables d'ici → procédure manuelle.
 *   - Les hashes IP sont irréversibles : on ne peut pas "lister les clics par IP", on
 *     peut seulement vérifier si une IP donnée a cliqué (en recomputant le hash).
 */
final class Admin_Rgpd {

	private const PAGE_SLUG = 'seoflix-rgpd';
	private const NONCE     = 'seoflix_rgpd';

	public static function init(): void {
		add_action( 'admin_menu',                            [ self::class, 'register_page' ], 11 );
		add_action( 'admin_post_seoflix_rgpd_delete',        [ self::class, 'handle_delete' ] );
		add_action( 'admin_post_seoflix_rgpd_export',        [ self::class, 'handle_export' ] );
	}

	public static function register_page(): void {
		add_submenu_page(
			'seoflix',
			'Demandes RGPD',
			'RGPD',
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$email = isset( $_GET['rgpd_email'] ) ? sanitize_email( wp_unslash( $_GET['rgpd_email'] ) ) : '';
		$ip    = isset( $_GET['rgpd_ip'] )    ? trim( (string) wp_unslash( $_GET['rgpd_ip'] ) )    : '';

		?>
		<div class="wrap seoflix-wrap">
			<h1>Demandes RGPD</h1>

			<?php if ( isset( $_GET['rgpd_deleted_comments'] ) || isset( $_GET['rgpd_deleted_clicks'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php echo (int) ( $_GET['rgpd_deleted_comments'] ?? 0 ); ?></strong> commentaire(s) et
						<strong><?php echo (int) ( $_GET['rgpd_deleted_clicks'] ?? 0 ); ?></strong> clic(s) affilié(s) supprimé(s).
						N'oublie pas la procédure manuelle (logs Apache, mailbox, backups).
					</p>
				</div>
			<?php endif; ?>

			<div class="seoflix-card">
				<p>Recherche dans la base Seoflix par adresse e-mail et/ou adresse IP. Tu peux fournir l'un, l'autre, ou les deux.</p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<table class="form-table">
						<tr>
							<th><label for="rgpd_email">E-mail</label></th>
							<td><input type="email" id="rgpd_email" name="rgpd_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" placeholder="exemple@domaine.fr"></td>
						</tr>
						<tr>
							<th><label for="rgpd_ip">Adresse IP</label></th>
							<td>
								<input type="text" id="rgpd_ip" name="rgpd_ip" value="<?php echo esc_attr( $ip ); ?>" class="regular-text code" placeholder="192.168.1.42 ou 2001:db8::1">
								<p class="description">L'IP doit être fournie par la personne (les hashes en base sont irréversibles).</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Rechercher', 'primary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( $email || $ip ) : ?>
				<?php self::render_results( $email, $ip ); ?>
			<?php endif; ?>

			<div class="seoflix-card">
				<h2>Procédure manuelle (hors site)</h2>
				<p>Les éléments suivants ne sont <strong>pas accessibles depuis ce panneau</strong>. À traiter manuellement :</p>
				<ol>
					<li><strong>Logs Apache (cPanel → Raw Access Logs)</strong> : conserve l'IP en clair pendant ~30 jours. Pour purger une IP spécifique avant cette échéance, télécharge le log brut, supprime les lignes contenant l'IP, et ré-uploade-le. Ou attends la rotation automatique.</li>
					<li><strong>Mailbox <code>contact@seoflix.fr</code></strong> : si la personne t'a écrit, supprime l'e-mail original et la chaîne de réponse.</li>
					<li><strong>Backups</strong> : la donnée peut subsister dans les sauvegardes pendant la durée de rétention. C'est conforme RGPD tant que les backups sont éphémères et la suppression effective dans les copies actives.</li>
					<li><strong>Caches CDN / WP cache</strong> : purger après suppression pour éviter qu'une page contenant le commentaire reste servie depuis le cache.</li>
				</ol>
				<p>Voir <code>docs/RGPD_PROCEDURE.md</code> dans le repo pour la marche à suivre complète.</p>
			</div>

			<div class="seoflix-card">
				<h2>Registre des demandes</h2>
				<p>Conserve un fichier (Notion / Google Drive / spreadsheet privé) avec pour chaque demande RGPD reçue :</p>
				<ul>
					<li>Date de réception</li>
					<li>Identifiant de la personne (e-mail / IP fournie)</li>
					<li>Droit invoqué (accès / effacement / etc.)</li>
					<li>Date de réponse</li>
					<li>Action effectuée (export / suppression / refus motivé)</li>
				</ul>
				<p>Durée de conservation du registre : 5 ans (durée de prescription en cas de litige).</p>
			</div>
		</div>
		<?php
	}

	private static function render_results( string $email, string $ip ): void {
		$ip_hash = $ip ? hash( 'sha256', $ip . wp_salt() ) : '';

		// 1. WP Users
		$user = $email ? get_user_by( 'email', $email ) : null;

		// 2. WP Comments — par e-mail ou IP en clair
		global $wpdb;
		$comment_clauses = [];
		$comment_args    = [];
		if ( $email ) {
			$comment_clauses[] = 'comment_author_email = %s';
			$comment_args[]    = $email;
		}
		if ( $ip ) {
			$comment_clauses[] = 'comment_author_IP = %s';
			$comment_args[]    = $ip;
		}
		$comments = [];
		if ( $comment_clauses ) {
			$where    = implode( ' OR ', $comment_clauses );
			$sql      = "SELECT comment_ID, comment_post_ID, comment_author, comment_author_email, comment_author_IP, comment_date, comment_content FROM {$wpdb->comments} WHERE {$where} ORDER BY comment_date DESC LIMIT 200";
			$comments = $wpdb->get_results( $wpdb->prepare( $sql, ...$comment_args ) );
		}

		// 3. Affiliate clicks — par hash recomputé
		$clicks = [];
		if ( $ip_hash ) {
			$table  = DB_Schema::table_affiliate_clicks();
			$clicks = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, product_id, source_video_id, source_page, user_agent, clicked_at FROM {$table} WHERE ip_hash = %s ORDER BY clicked_at DESC LIMIT 500",
				$ip_hash
			) );
		}

		?>
		<div class="seoflix-card">
			<h2>Résultats</h2>

			<h3>Compte utilisateur (wp_users)</h3>
			<?php if ( $user ) : ?>
				<table class="widefat striped">
					<thead><tr><th>ID</th><th>Login</th><th>E-mail</th><th>Inscrit le</th><th>Rôle(s)</th></tr></thead>
					<tbody>
						<tr>
							<td><?php echo (int) $user->ID; ?></td>
							<td><?php echo esc_html( $user->user_login ); ?></td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( $user->user_registered ); ?></td>
							<td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
						</tr>
					</tbody>
				</table>
				<p><strong>⚠️ Attention</strong> : supprimer un compte utilisateur supprime aussi ses contenus (articles, vidéos publiées, etc.) sauf si tu réassignes à un autre auteur. À ne faire qu'en dernier recours.</p>
			<?php else : ?>
				<p><em>Aucun compte trouvé.</em></p>
			<?php endif; ?>

			<h3>Commentaires (wp_comments)</h3>
			<?php if ( $comments ) : ?>
				<table class="widefat striped">
					<thead><tr><th>ID</th><th>Auteur</th><th>E-mail</th><th>IP</th><th>Date</th><th>Extrait</th></tr></thead>
					<tbody>
						<?php foreach ( $comments as $c ) : ?>
							<tr>
								<td><?php echo (int) $c->comment_ID; ?></td>
								<td><?php echo esc_html( $c->comment_author ); ?></td>
								<td><?php echo esc_html( $c->comment_author_email ); ?></td>
								<td><code><?php echo esc_html( $c->comment_author_IP ); ?></code></td>
								<td><?php echo esc_html( $c->comment_date ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $c->comment_content, 12 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><em>Aucun commentaire trouvé.</em></p>
			<?php endif; ?>

			<h3>Clics affiliés (wp_seoflix_affiliate_clicks)</h3>
			<?php if ( $clicks ) : ?>
				<p><strong><?php echo count( $clicks ); ?></strong> clic(s) trouvé(s) pour cette IP (via hash recomputé).</p>
				<table class="widefat striped">
					<thead><tr><th>ID</th><th>Produit</th><th>Vidéo source</th><th>Page</th><th>User-agent</th><th>Date</th></tr></thead>
					<tbody>
						<?php foreach ( array_slice( $clicks, 0, 50 ) as $c ) :
							$product = get_post( $c->product_id );
							$video   = $c->source_video_id ? get_post( $c->source_video_id ) : null;
							?>
							<tr>
								<td><?php echo (int) $c->id; ?></td>
								<td><?php echo $product ? esc_html( $product->post_title ) : '—'; ?></td>
								<td><?php echo $video ? esc_html( $video->post_title ) : '—'; ?></td>
								<td><?php echo esc_html( wp_trim_words( (string) $c->source_page, 6 ) ); ?></td>
								<td><?php echo esc_html( wp_trim_words( (string) $c->user_agent, 8 ) ); ?></td>
								<td><?php echo esc_html( $c->clicked_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $clicks ) > 50 ) : ?>
					<p><em>50 premiers affichés sur <?php echo count( $clicks ); ?>.</em></p>
				<?php endif; ?>
			<?php elseif ( $ip ) : ?>
				<p><em>Aucun clic affilié trouvé pour cette IP.</em></p>
			<?php else : ?>
				<p><em>Saisis une IP pour rechercher dans les clics affiliés.</em></p>
			<?php endif; ?>

			<?php if ( $user || $comments || $clicks ) : ?>
				<h3>Actions</h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 1rem;">
					<input type="hidden" name="action" value="seoflix_rgpd_export">
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<input type="hidden" name="ip" value="<?php echo esc_attr( $ip ); ?>">
					<?php wp_nonce_field( self::NONCE ); ?>
					<button type="submit" class="button">📥 Exporter en JSON (droit de portabilité)</button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('SUPPRIMER toutes les données ci-dessus ? Cette action est IRRÉVERSIBLE.\n\nSeront supprimés : commentaires, clics affiliés. Le compte utilisateur ne sera PAS supprimé automatiquement (à faire manuellement dans Utilisateurs si nécessaire).');">
					<input type="hidden" name="action" value="seoflix_rgpd_delete">
					<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
					<input type="hidden" name="ip" value="<?php echo esc_attr( $ip ); ?>">
					<?php wp_nonce_field( self::NONCE ); ?>
					<button type="submit" class="button button-link-delete" style="color: #b32d2e;">🗑️ Supprimer commentaires + clics affiliés</button>
				</form>
				<p class="description">L'export JSON inclut toutes les données ci-dessus. La suppression efface commentaires et lignes de clics. Le compte utilisateur reste intact (à supprimer via Utilisateurs si demandé).</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$ip    = isset( $_POST['ip'] )    ? trim( (string) wp_unslash( $_POST['ip'] ) )    : '';

		global $wpdb;
		$deleted_comments = 0;
		$deleted_clicks   = 0;

		// Commentaires : par e-mail ou IP en clair
		$where_parts = [];
		$where_args  = [];
		if ( $email ) {
			$where_parts[] = 'comment_author_email = %s';
			$where_args[]  = $email;
		}
		if ( $ip ) {
			$where_parts[] = 'comment_author_IP = %s';
			$where_args[]  = $ip;
		}
		if ( $where_parts ) {
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE " . implode( ' OR ', $where_parts ),
				...$where_args
			) );
			foreach ( $ids as $cid ) {
				if ( wp_delete_comment( (int) $cid, true ) ) {
					$deleted_comments++;
				}
			}
		}

		// Clics affiliés : par hash recomputé
		if ( $ip ) {
			$ip_hash = hash( 'sha256', $ip . wp_salt() );
			$table   = DB_Schema::table_affiliate_clicks();
			$deleted_clicks = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE ip_hash = %s",
				$ip_hash
			) );
		}

		$redirect = add_query_arg( [
			'page'                  => self::PAGE_SLUG,
			'rgpd_email'            => $email,
			'rgpd_ip'               => $ip,
			'rgpd_deleted_comments' => $deleted_comments,
			'rgpd_deleted_clicks'   => $deleted_clicks,
		], admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$ip    = isset( $_POST['ip'] )    ? trim( (string) wp_unslash( $_POST['ip'] ) )    : '';

		global $wpdb;
		$data = [
			'export_date' => gmdate( 'c' ),
			'site'        => home_url(),
			'query'       => [ 'email' => $email, 'ip' => $ip ],
			'data'        => [],
		];

		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$data['data']['user'] = [
					'id'              => $user->ID,
					'login'           => $user->user_login,
					'email'           => $user->user_email,
					'display_name'    => $user->display_name,
					'registered_at'   => $user->user_registered,
					'roles'           => $user->roles,
					'meta'            => array_map( fn( $v ) => maybe_unserialize( $v[0] ?? '' ), get_user_meta( $user->ID ) ),
				];
			}
		}

		// Comments
		$where_parts = [];
		$where_args  = [];
		if ( $email ) {
			$where_parts[] = 'comment_author_email = %s';
			$where_args[]  = $email;
		}
		if ( $ip ) {
			$where_parts[] = 'comment_author_IP = %s';
			$where_args[]  = $ip;
		}
		if ( $where_parts ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->comments} WHERE " . implode( ' OR ', $where_parts ),
				...$where_args
			), ARRAY_A );
			$data['data']['comments'] = $rows;
		}

		// Affiliate clicks
		if ( $ip ) {
			$ip_hash = hash( 'sha256', $ip . wp_salt() );
			$table   = DB_Schema::table_affiliate_clicks();
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE ip_hash = %s",
				$ip_hash
			), ARRAY_A );
			$data['data']['affiliate_clicks'] = $rows;
		}

		$filename = 'seoflix-rgpd-export-' . gmdate( 'Y-m-d-His' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
}
