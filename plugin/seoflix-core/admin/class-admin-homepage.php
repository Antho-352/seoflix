<?php
namespace Seoflix\Admin;

use Seoflix\Homepage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page admin Seoflix → Page d'accueil.
 *
 * Permet de configurer :
 *   - Hero (titre + placeholder [rotate], sous-titre, mots qui tournent, stats)
 *   - Sections (ordre, visibilité, titre H2 override)
 */
final class Admin_Homepage {

	public const PAGE_SLUG = 'seoflix-homepage';
	public const NONCE     = 'seoflix_homepage_save';

	public static function init(): void {
		add_action( 'admin_menu',                              [ self::class, 'register_page' ], 10 );
		add_action( 'admin_post_seoflix_save_homepage',        [ self::class, 'handle_save' ] );
		add_action( 'admin_post_seoflix_reset_homepage',       [ self::class, 'handle_reset' ] );
	}

	public static function register_page(): void {
		add_submenu_page(
			'seoflix',
			"Page d'accueil",
			"Page d'accueil",
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$cfg      = Homepage::get_config();
		$labels   = Homepage::section_labels();
		$saved    = isset( $_GET['saved'] );
		$resetted = isset( $_GET['reset'] );
		?>
		<div class="wrap seoflix-wrap">
			<h1>Page d'accueil</h1>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Configuration enregistrée. <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">Voir la home →</a></p></div>
			<?php endif; ?>
			<?php if ( $resetted ) : ?>
				<div class="notice notice-info is-dismissible"><p>Configuration remise aux valeurs par défaut.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoflix_save_homepage">
				<?php wp_nonce_field( self::NONCE ); ?>

				<div class="seoflix-card">
					<h2>Hero (bandeau du haut)</h2>
					<table class="form-table">
						<tr>
							<th><label for="hero_title">Titre principal (H1)</label></th>
							<td>
								<input type="text" id="hero_title" name="hero[title]" value="<?php echo esc_attr( $cfg['hero']['title'] ?? '' ); ?>" class="large-text">
								<p class="description">Utilise <code>[rotate]</code> à la place du mot qui doit tourner. Exemple : <code>Maîtrise [rotate] avec les meilleurs.</code></p>
							</td>
						</tr>
						<tr>
							<th><label for="hero_subtitle">Sous-titre</label></th>
							<td>
								<textarea id="hero_subtitle" name="hero[subtitle]" rows="3" class="large-text"><?php echo esc_textarea( $cfg['hero']['subtitle'] ?? '' ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th><label for="hero_rotating">Mots qui tournent</label></th>
							<td>
								<textarea id="hero_rotating" name="hero[rotating_words]" rows="10" class="large-text" placeholder="Un mot par ligne"><?php echo esc_textarea( implode( "\n", (array) ( $cfg['hero']['rotating_words'] ?? [] ) ) ); ?></textarea>
								<p class="description">Un mot ou expression par ligne. Inclure les articles (« le », « la », « l' »…) si nécessaire.</p>
							</td>
						</tr>
						<tr>
							<th>Statistiques</th>
							<td>
								<label>
									<input type="checkbox" name="hero[show_stats]" value="1" <?php checked( ! empty( $cfg['hero']['show_stats'] ) ); ?>>
									Afficher la ligne « X vidéos / Y chaînes / Z outils »
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="seoflix-card">
					<h2>Sections</h2>
					<p>Glisse l'ordre via la colonne <strong>Ordre</strong> (chiffre — plus petit = plus haut sur la page). Décoche <strong>Visible</strong> pour cacher une section.</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width:60px;">Ordre</th>
								<th style="width:60px;">Visible</th>
								<th>Type</th>
								<th>Titre H2 (laisser vide = défaut)</th>
								<th style="width:120px;">Limite</th>
								<th style="width:120px;">Topics (count)</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cfg['sections'] as $idx => $s ) :
								$type = $s['type'] ?? '';
								?>
								<tr>
									<td>
										<input type="number" name="sections[<?php echo (int) $idx; ?>][order]" value="<?php echo esc_attr( (string) ( $s['order'] ?? 99 ) ); ?>" min="1" max="99" class="small-text" style="width:60px;">
										<input type="hidden" name="sections[<?php echo (int) $idx; ?>][type]" value="<?php echo esc_attr( $type ); ?>">
									</td>
									<td>
										<input type="checkbox" name="sections[<?php echo (int) $idx; ?>][visible]" value="1" <?php checked( ! empty( $s['visible'] ) ); ?>>
									</td>
									<td><strong><?php echo esc_html( $labels[ $type ] ?? $type ); ?></strong></td>
									<td>
										<input type="text" name="sections[<?php echo (int) $idx; ?>][title]" value="<?php echo esc_attr( $s['title'] ?? '' ); ?>" class="regular-text" style="width:100%;">
									</td>
									<td>
										<?php if ( in_array( $type, [ Homepage::TYPE_NEW, Homepage::TYPE_MOST_VIEWED, Homepage::TYPE_CHANNELS, Homepage::TYPE_TOPICS ], true ) ) : ?>
											<input type="number" name="sections[<?php echo (int) $idx; ?>][limit]" value="<?php echo esc_attr( (string) ( $s['limit'] ?? 12 ) ); ?>" min="1" max="50" class="small-text">
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $type === Homepage::TYPE_TOPICS ) : ?>
											<input type="number" name="sections[<?php echo (int) $idx; ?>][topics_count]" value="<?php echo esc_attr( (string) ( $s['topics_count'] ?? 6 ) ); ?>" min="1" max="20" class="small-text">
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description">
						<strong>Topics (auto)</strong> : génère automatiquement N rangées, une par sujet (top par nombre de vidéos). « Limite » = nombre de vidéos par rangée. « Topics (count) » = nombre de sujets affichés.
					</p>
				</div>

				<p>
					<?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
					&nbsp;
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seoflix_reset_homepage' ), self::NONCE ) ); ?>" class="button" onclick="return confirm('Réinitialiser toute la configuration de la page d\'accueil aux valeurs par défaut ?');">Réinitialiser aux défauts</a>
					&nbsp;
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="button button-secondary">Aperçu de la home ↗</a>
				</p>
			</form>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		$hero_in = isset( $_POST['hero'] ) && is_array( $_POST['hero'] ) ? wp_unslash( $_POST['hero'] ) : [];
		$rotating_raw = isset( $hero_in['rotating_words'] ) ? (string) $hero_in['rotating_words'] : '';
		$rotating     = array_values( array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $rotating_raw ) ) ) ) );

		$hero = [
			'title'          => sanitize_text_field( $hero_in['title'] ?? '' ),
			'subtitle'       => sanitize_textarea_field( $hero_in['subtitle'] ?? '' ),
			'rotating_words' => array_map( 'sanitize_text_field', $rotating ),
			'show_stats'     => ! empty( $hero_in['show_stats'] ),
		];

		$sections_in = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : [];
		$sections    = [];
		foreach ( $sections_in as $s ) {
			$type = sanitize_key( $s['type'] ?? '' );
			if ( ! $type ) {
				continue;
			}
			$row = [
				'type'    => $type,
				'title'   => sanitize_text_field( $s['title'] ?? '' ),
				'visible' => ! empty( $s['visible'] ),
				'order'   => max( 1, min( 99, (int) ( $s['order'] ?? 99 ) ) ),
			];
			if ( isset( $s['limit'] ) ) {
				$row['limit'] = max( 1, min( 50, (int) $s['limit'] ) );
			}
			if ( isset( $s['topics_count'] ) ) {
				$row['topics_count'] = max( 1, min( 20, (int) $s['topics_count'] ) );
			}
			$sections[] = $row;
		}

		Homepage::save_config( [
			'hero'     => $hero,
			'sections' => $sections,
		] );

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'saved' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( self::NONCE );

		Homepage::reset();

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'reset' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
