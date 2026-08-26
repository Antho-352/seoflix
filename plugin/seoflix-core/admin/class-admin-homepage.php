<?php
namespace Seoflix\Admin;

use Seoflix\Homepage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Réglages ciblés de l'assemblage fixe de la page d'accueil WEAS. */
final class Admin_Homepage {

	public const PAGE_SLUG = 'seoflix-homepage';
	public const NONCE     = 'seoflix_homepage_save';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_page' ], 10 );
		add_action( 'admin_post_seoflix_save_homepage', [ self::class, 'handle_save' ] );
		add_action( 'admin_post_seoflix_reset_homepage', [ self::class, 'handle_reset' ] );
	}

	public static function register_page(): void {
		add_submenu_page(
			'seoflix',
			"Page d'accueil WEAS",
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

		$cfg = Homepage::get_config();
		$paths = get_terms(
			[
				'taxonomy'   => 'seoflix_path',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);
		if ( is_wp_error( $paths ) ) {
			$paths = [];
		}
		$block_labels = [
			'paths'          => 'Six cartes parcours',
			'new'            => 'Nouveautés',
			'tools'          => 'Meilleurs outils',
			'promise'        => 'Encart promesse',
			'featured_paths' => 'Trois rangées de parcours',
			'paths_cta'      => 'CTA vers tous les parcours',
			'about'          => 'À propos',
			'newsletter'     => 'Newsletter',
			'blog'           => 'Derniers articles',
		];
		?>
		<div class="wrap seoflix-wrap">
			<h1>Page d'accueil WEAS</h1>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Configuration enregistrée.</p></div>
			<?php elseif ( isset( $_GET['reset'] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p>Configuration remise aux valeurs par défaut.</p></div>
			<?php endif; ?>

			<p>La composition et l'ordre des blocs sont fixes. Ces réglages modifient uniquement les sélections éditoriales prévues.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seoflix_save_homepage">
				<?php wp_nonce_field( self::NONCE ); ?>

				<div class="seoflix-card">
					<h2>Hero</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="hero_title">Promesse (H1)</label></th>
							<td><input type="text" id="hero_title" name="hero[title]" value="<?php echo esc_attr( $cfg['hero']['title'] ); ?>" class="large-text" maxlength="140"></td>
						</tr>
						<tr>
							<th><label for="hero_subtitle">Texte d'introduction</label></th>
							<td><textarea id="hero_subtitle" name="hero[subtitle]" rows="3" class="large-text" maxlength="320"><?php echo esc_textarea( $cfg['hero']['subtitle'] ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="hero_cta_text">Libellé du CTA</label></th>
							<td>
								<input type="text" id="hero_cta_text" name="hero[cta_text]" value="<?php echo esc_attr( $cfg['hero']['cta_text'] ); ?>" class="regular-text" maxlength="80">
								<p class="description">La destination reste volontairement fixée à <code>/commencer/</code>.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="seoflix-card">
					<h2>Meilleurs outils</h2>
					<label for="best_tool_ids">IDs des produits, dans l'ordre d'affichage</label>
					<input type="text" id="best_tool_ids" name="best_tool_ids" value="<?php echo esc_attr( implode( ', ', $cfg['best_tool_ids'] ) ); ?>" class="large-text" inputmode="numeric" placeholder="42, 17, 81">
					<p class="description">Maximum <?php echo (int) Homepage::MAX_BEST_TOOLS; ?> IDs uniques. Seuls les produits publiés sont affichés.</p>
				</div>

				<div class="seoflix-card">
					<h2>Trois rangées parcours</h2>
					<p>Choisis exactement trois parcours. Leur position ci-dessous détermine l'ordre public.</p>
					<?php for ( $slot = 0; $slot < Homepage::MAX_FEATURED_ROWS; $slot++ ) : ?>
						<p>
							<label for="featured_path_<?php echo (int) $slot; ?>">Rangée <?php echo (int) ( $slot + 1 ); ?></label><br>
							<select id="featured_path_<?php echo (int) $slot; ?>" name="featured_path_slugs[]">
								<option value="">— Parcours indisponible —</option>
								<?php foreach ( $paths as $path ) : ?>
									<option value="<?php echo esc_attr( $path->slug ); ?>" <?php selected( $cfg['featured_path_slugs'][ $slot ] ?? '', $path->slug ); ?>><?php echo esc_html( $path->name ); ?> (<?php echo (int) $path->count; ?>)</option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endfor; ?>
				</div>

				<div class="seoflix-card">
					<h2>Visibilité des blocs fixes</h2>
					<fieldset>
						<legend class="screen-reader-text">Blocs visibles</legend>
						<?php foreach ( $block_labels as $key => $label ) : ?>
							<p><label><input type="checkbox" name="fixed_blocks[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $cfg['fixed_blocks'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label></p>
						<?php endforeach; ?>
					</fieldset>
				</div>

				<p>
					<?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=seoflix_reset_homepage' ), self::NONCE ) ); ?>" class="button">Réinitialiser aux valeurs par défaut</a>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" class="button button-secondary">Aperçu de la home</a>
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
		$raw_ids = isset( $_POST['best_tool_ids'] ) ? wp_unslash( $_POST['best_tool_ids'] ) : '';
		$best_tool_ids = [];
		foreach ( preg_split( '/[\s,]+/', (string) $raw_ids, -1, PREG_SPLIT_NO_EMPTY ) as $raw_id ) {
			$id = absint( $raw_id );
			if ( $id > 0 && ! in_array( $id, $best_tool_ids, true ) ) {
				$best_tool_ids[] = $id;
			}
		}

		$raw_slugs = isset( $_POST['featured_path_slugs'] ) && is_array( $_POST['featured_path_slugs'] )
			? wp_unslash( $_POST['featured_path_slugs'] )
			: [];
		$featured_path_slugs = array_map( 'sanitize_key', $raw_slugs );
		$blocks_in = isset( $_POST['fixed_blocks'] ) && is_array( $_POST['fixed_blocks'] )
			? wp_unslash( $_POST['fixed_blocks'] )
			: [];

		Homepage::save_config(
			[
				'hero' => [
					'title'    => sanitize_text_field( $hero_in['title'] ?? '' ),
					'subtitle' => sanitize_textarea_field( $hero_in['subtitle'] ?? '' ),
					'cta_text' => sanitize_text_field( $hero_in['cta_text'] ?? '' ),
				],
				'best_tool_ids'       => $best_tool_ids,
				'featured_path_slugs' => $featured_path_slugs,
				'fixed_blocks'        => $blocks_in,
			]
		);

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
