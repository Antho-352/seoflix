<?php
namespace Seoflix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module SEO maison (substitut Yoast minimaliste).
 *
 * Fonctionnalités :
 *  - Métabox sur tous les post types publics : SEO title, meta description, robots, canonical
 *  - Métabox équivalent sur les termes des taxonomies publiques
 *  - Override du <title> via wp_title / document_title_parts
 *  - Sortie wp_head : meta description, meta robots, canonical
 *  - Open Graph + Twitter Card
 *  - JSON-LD : Organization (homepage), VideoObject (single video), BreadcrumbList
 *  - Désactive le générateur de description WP par défaut
 */
final class SEO {

	public const META_TITLE       = '_seoflix_seo_title';
	public const META_DESCRIPTION = '_seoflix_seo_description';
	public const META_ROBOTS      = '_seoflix_seo_robots'; // 'index'|'noindex'
	public const META_CANONICAL   = '_seoflix_seo_canonical';

	public const OPTION_TITLE_TEMPLATE_HOME = 'seoflix_seo_title_home';
	public const OPTION_TITLE_TEMPLATE      = 'seoflix_seo_title_template';
	public const OPTION_DESC_HOME           = 'seoflix_seo_desc_home';
	public const OPTION_OG_IMAGE            = 'seoflix_seo_og_image';
	public const OPTION_ORG_NAME            = 'seoflix_seo_org_name';
	public const OPTION_TWITTER_HANDLE      = 'seoflix_seo_twitter';

	public static function init(): void {
		// Metaboxes posts/CPT
		add_action( 'add_meta_boxes',         [ self::class, 'register_post_metabox' ] );
		add_action( 'save_post',              [ self::class, 'save_post_meta' ], 10, 2 );

		// Metaboxes termes
		foreach ( [ 'seoflix_topic', 'seoflix_format', 'seoflix_path', 'seoflix_product_category', 'category', 'post_tag' ] as $tax ) {
			add_action( "{$tax}_edit_form_fields", [ self::class, 'render_term_fields' ], 10, 2 );
			add_action( "edited_{$tax}",           [ self::class, 'save_term_meta' ], 10, 2 );
		}

		// Output frontend
		add_filter( 'document_title_parts',   [ self::class, 'filter_title_parts' ] );
		add_filter( 'document_title_separator', static fn() => '|' );
		add_action( 'wp_head',                [ self::class, 'render_meta_tags' ], 1 );
		add_action( 'wp_head',                [ self::class, 'render_open_graph' ], 5 );
		add_action( 'wp_head',                [ self::class, 'render_jsonld' ], 7 );

		// Réglages
		add_action( 'admin_init',             [ self::class, 'register_settings' ] );

		// Settings page (Seoflix → SEO)
		add_action( 'admin_menu',             [ self::class, 'register_admin_page' ], 11 );
	}

	public static function register_settings(): void {
		$opts = [
			self::OPTION_TITLE_TEMPLATE_HOME => '%site% — %tagline%',
			self::OPTION_TITLE_TEMPLATE      => '%title% | %site%',
			'seoflix_seo_title_template_video' => '%title% — %channel% | %site%',
			self::OPTION_DESC_HOME           => '',
			self::OPTION_OG_IMAGE            => '',
			self::OPTION_ORG_NAME            => '',
			self::OPTION_TWITTER_HANDLE      => '',
		];
		foreach ( $opts as $key => $default ) {
			register_setting( 'seoflix_seo_settings', $key, [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $default,
			] );
		}
	}

	/* ======================================================================
	 *  Metabox sur les posts / CPT
	 * ====================================================================== */

	public static function register_post_metabox(): void {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		unset( $post_types['attachment'] );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'seoflix_seo',
				'🎯 SEO (title, description, robots, canonical)',
				[ self::class, 'render_post_metabox' ],
				$pt,
				'normal',
				'high'
			);
		}
	}

	public static function render_post_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'seoflix_seo_save', 'seoflix_seo_nonce' );

		$title  = (string) get_post_meta( $post->ID, self::META_TITLE, true );
		$desc   = (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		$robots = (string) get_post_meta( $post->ID, self::META_ROBOTS, true ) ?: 'index';
		$canon  = (string) get_post_meta( $post->ID, self::META_CANONICAL, true );

		$preview_title = $title ?: get_the_title( $post );
		$preview_desc  = $desc ?: wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content );
		$preview_desc  = mb_substr( $preview_desc, 0, 160 );
		$preview_url   = get_permalink( $post );
		?>
		<style>
			.sx-seo-preview { background: #f6f7f7; border: 1px solid #ddd; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
			.sx-seo-preview__title { color: #1a0dab; font-size: 18px; line-height: 1.3; margin-bottom: 4px; cursor: pointer; }
			.sx-seo-preview__url { color: #006621; font-size: 13px; }
			.sx-seo-preview__desc { color: #4d5156; font-size: 13px; line-height: 1.4; margin-top: 4px; }
			.sx-seo-counter { font-size: 0.8rem; color: #666; }
			.sx-seo-counter--warn { color: #d63638; }
		</style>

		<p><strong>Aperçu Google :</strong></p>
		<div class="sx-seo-preview">
			<div class="sx-seo-preview__title" id="sx-seo-prev-title"><?php echo esc_html( $preview_title ); ?></div>
			<div class="sx-seo-preview__url"><?php echo esc_html( $preview_url ); ?></div>
			<div class="sx-seo-preview__desc" id="sx-seo-prev-desc"><?php echo esc_html( $preview_desc ); ?></div>
		</div>

		<table class="form-table">
			<tr>
				<th><label for="seoflix_seo_title">Title SEO</label></th>
				<td>
					<input type="text" id="seoflix_seo_title" name="seoflix_seo_title" value="<?php echo esc_attr( $title ); ?>" class="large-text" maxlength="80">
					<p class="description"><span id="sx-seo-title-counter" class="sx-seo-counter">0 / 60 caractères recommandés</span> · Si vide, utilise le titre du contenu.</p>
				</td>
			</tr>
			<tr>
				<th><label for="seoflix_seo_description">Meta description</label></th>
				<td>
					<textarea id="seoflix_seo_description" name="seoflix_seo_description" rows="3" class="large-text" maxlength="200"><?php echo esc_textarea( $desc ); ?></textarea>
					<p class="description"><span id="sx-seo-desc-counter" class="sx-seo-counter">0 / 155 caractères recommandés</span> · Si vide, utilise l'extrait ou les premiers mots du contenu.</p>
				</td>
			</tr>
			<tr>
				<th>Robots</th>
				<td>
					<label><input type="radio" name="seoflix_seo_robots" value="index" <?php checked( $robots, 'index' ); ?>> Indexer (par défaut)</label><br>
					<label><input type="radio" name="seoflix_seo_robots" value="noindex" <?php checked( $robots, 'noindex' ); ?>> Ne pas indexer (<code>noindex, follow</code>)</label>
				</td>
			</tr>
			<tr>
				<th><label for="seoflix_seo_canonical">URL canonique</label></th>
				<td>
					<input type="url" id="seoflix_seo_canonical" name="seoflix_seo_canonical" value="<?php echo esc_attr( $canon ); ?>" class="large-text code" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>">
					<p class="description">Vide = canonical sur l'URL de cette page. À remplir uniquement si tu veux pointer vers une autre URL (contenu dupliqué).</p>
				</td>
			</tr>
		</table>

		<script>
		(function() {
			const tIn = document.getElementById('seoflix_seo_title');
			const dIn = document.getElementById('seoflix_seo_description');
			const tPv = document.getElementById('sx-seo-prev-title');
			const dPv = document.getElementById('sx-seo-prev-desc');
			const tCt = document.getElementById('sx-seo-title-counter');
			const dCt = document.getElementById('sx-seo-desc-counter');
			function updateTitle() {
				const v = tIn.value || tPv.dataset.fallback || tPv.textContent;
				tPv.dataset.fallback = tPv.dataset.fallback || tPv.textContent;
				if (tIn.value) tPv.textContent = v;
				const len = tIn.value.length;
				tCt.textContent = len + ' / 60 caractères recommandés';
				tCt.classList.toggle('sx-seo-counter--warn', len > 60);
			}
			function updateDesc() {
				if (dIn.value) dPv.textContent = dIn.value;
				const len = dIn.value.length;
				dCt.textContent = len + ' / 155 caractères recommandés';
				dCt.classList.toggle('sx-seo-counter--warn', len > 155);
			}
			tIn.addEventListener('input', updateTitle);
			dIn.addEventListener('input', updateDesc);
			updateTitle(); updateDesc();
		})();
		</script>
		<?php
	}

	public static function save_post_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['seoflix_seo_nonce'] ) || ! wp_verify_nonce( $_POST['seoflix_seo_nonce'], 'seoflix_seo_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = [
			'seoflix_seo_title'       => self::META_TITLE,
			'seoflix_seo_description' => self::META_DESCRIPTION,
			'seoflix_seo_canonical'   => self::META_CANONICAL,
		];
		foreach ( $fields as $key => $meta_key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = trim( wp_unslash( $_POST[ $key ] ) );
				if ( $value ) {
					$value = ( $key === 'seoflix_seo_canonical' ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
					update_post_meta( $post_id, $meta_key, $value );
				} else {
					delete_post_meta( $post_id, $meta_key );
				}
			}
		}

		$robots = $_POST['seoflix_seo_robots'] ?? 'index';
		update_post_meta( $post_id, self::META_ROBOTS, $robots === 'noindex' ? 'noindex' : 'index' );
	}

	/* ======================================================================
	 *  Metabox sur les termes
	 * ====================================================================== */

	public static function render_term_fields( \WP_Term $term, string $taxonomy ): void {
		$title  = (string) get_term_meta( $term->term_id, self::META_TITLE, true );
		$desc   = (string) get_term_meta( $term->term_id, self::META_DESCRIPTION, true );
		$robots = (string) get_term_meta( $term->term_id, self::META_ROBOTS, true ) ?: 'index';
		?>
		<tr class="form-field">
			<th colspan="2" style="padding-top: 1.5rem; border-top: 1px solid #ddd;"><h2>SEO</h2></th>
		</tr>
		<tr class="form-field">
			<th><label for="seoflix_seo_title">Title SEO</label></th>
			<td>
				<input type="text" id="seoflix_seo_title" name="seoflix_seo_title" value="<?php echo esc_attr( $title ); ?>" maxlength="80">
				<p class="description">Titre affiché dans les résultats Google. Si vide, utilise le nom du terme.</p>
			</td>
		</tr>
		<tr class="form-field">
			<th><label for="seoflix_seo_description">Meta description</label></th>
			<td>
				<textarea id="seoflix_seo_description" name="seoflix_seo_description" rows="3" maxlength="200"><?php echo esc_textarea( $desc ); ?></textarea>
			</td>
		</tr>
		<tr class="form-field">
			<th>Robots</th>
			<td>
				<label><input type="radio" name="seoflix_seo_robots" value="index" <?php checked( $robots, 'index' ); ?>> Indexer</label>
				&nbsp;&nbsp;
				<label><input type="radio" name="seoflix_seo_robots" value="noindex" <?php checked( $robots, 'noindex' ); ?>> Ne pas indexer</label>
			</td>
		</tr>
		<?php
	}

	public static function save_term_meta( int $term_id, int $tt_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$fields = [
			'seoflix_seo_title'       => self::META_TITLE,
			'seoflix_seo_description' => self::META_DESCRIPTION,
		];
		foreach ( $fields as $key => $meta_key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( $value ) {
					update_term_meta( $term_id, $meta_key, $value );
				} else {
					delete_term_meta( $term_id, $meta_key );
				}
			}
		}
		$robots = $_POST['seoflix_seo_robots'] ?? 'index';
		update_term_meta( $term_id, self::META_ROBOTS, $robots === 'noindex' ? 'noindex' : 'index' );
	}

	/* ======================================================================
	 *  Output frontend
	 * ====================================================================== */

	public static function filter_title_parts( array $parts ): array {
		$custom = self::current_seo_title();
		if ( $custom ) {
			return [ 'title' => $custom ];
		}
		return $parts;
	}

	private static function current_seo_title(): string {
		if ( is_singular() ) {
			$post  = get_queried_object();
			$title = (string) get_post_meta( $post->ID, self::META_TITLE, true );
			if ( $title ) return $title;

			// Default template ; pour les vidéos on a un template dédié plus riche
			// avec la chaîne : "Titre — Chaîne | Seoflix" (signal différencié vs YouTube).
			if ( $post->post_type === CPT::VIDEO ) {
				$tpl = (string) get_option( 'seoflix_seo_title_template_video', '%title% — %channel% | %site%' );
				$channel_id = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_CHANNEL_ID, true );
				$channel    = $channel_id ? get_post( $channel_id ) : null;
				return self::apply_template( $tpl, [
					'%title%'   => get_the_title( $post ),
					'%channel%' => $channel ? $channel->post_title : '',
					'%site%'    => get_bloginfo( 'name' ),
				] );
			}

			$tpl = (string) get_option( self::OPTION_TITLE_TEMPLATE, '%title% | %site%' );
			return self::apply_template( $tpl, [ '%title%' => get_the_title( $post ), '%site%' => get_bloginfo( 'name' ) ] );
		}
		if ( is_front_page() || is_home() ) {
			$tpl = (string) get_option( self::OPTION_TITLE_TEMPLATE_HOME, '%site% — %tagline%' );
			return self::apply_template( $tpl, [ '%site%' => get_bloginfo( 'name' ), '%tagline%' => get_bloginfo( 'description' ) ] );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term  = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$title = (string) get_term_meta( $term->term_id, self::META_TITLE, true );
				if ( $title ) return $title;
				return $term->name . ' | ' . get_bloginfo( 'name' );
			}
		}
		return '';
	}

	private static function current_seo_description(): string {
		if ( is_singular() ) {
			$post = get_queried_object();
			$desc = (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true );
			if ( $desc ) return $desc;
			$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content );
			return mb_substr( trim( preg_replace( '/\s+/', ' ', $excerpt ) ), 0, 155 );
		}
		if ( is_front_page() || is_home() ) {
			$desc = (string) get_option( self::OPTION_DESC_HOME, '' );
			return $desc ?: get_bloginfo( 'description' );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$desc = (string) get_term_meta( $term->term_id, self::META_DESCRIPTION, true );
				if ( $desc ) return $desc;
				return mb_substr( wp_strip_all_tags( $term->description ), 0, 155 );
			}
		}
		return '';
	}

	private static function current_seo_robots(): string {
		if ( is_search() || is_404() ) return 'noindex, follow';
		if ( is_singular() ) {
			$v = (string) get_post_meta( get_queried_object_id(), self::META_ROBOTS, true );
			return $v === 'noindex' ? 'noindex, follow' : 'index, follow';
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$v = (string) get_term_meta( $term->term_id, self::META_ROBOTS, true );
				return $v === 'noindex' ? 'noindex, follow' : 'index, follow';
			}
		}
		return 'index, follow';
	}

	private static function current_canonical(): string {
		if ( is_singular() ) {
			$canon = (string) get_post_meta( get_queried_object_id(), self::META_CANONICAL, true );
			if ( $canon ) return $canon;
			return get_permalink();
		}
		if ( is_front_page() || is_home() ) return home_url( '/' );
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				return get_term_link( $term );
			}
		}
		if ( is_post_type_archive() ) {
			return get_post_type_archive_link( get_query_var( 'post_type' ) );
		}
		return '';
	}

	private static function apply_template( string $tpl, array $vars ): string {
		return trim( str_replace( array_keys( $vars ), array_values( $vars ), $tpl ) );
	}

	public static function render_meta_tags(): void {
		$desc   = self::current_seo_description();
		$robots = self::current_seo_robots();
		$canon  = self::current_canonical();

		echo "\n<!-- Seoflix SEO -->\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";
		if ( $canon ) {
			echo '<link rel="canonical" href="' . esc_url( $canon ) . '">' . "\n";
		}
	}

	public static function render_open_graph(): void {
		$title    = self::current_seo_title() ?: get_bloginfo( 'name' );
		$desc     = self::current_seo_description();
		$canon    = self::current_canonical();
		$site     = get_bloginfo( 'name' );
		$type     = is_singular() ? 'article' : 'website';
		$og_image = '';

		if ( is_singular() && has_post_thumbnail() ) {
			$og_image = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		}
		if ( ! $og_image && is_singular( CPT::VIDEO ) ) {
			$og_image = (string) get_post_meta( get_queried_object_id(), Meta_Keys::VIDEO_THUMBNAIL_URL, true );
		}
		if ( ! $og_image ) {
			$og_image = (string) get_option( self::OPTION_OG_IMAGE, '' );
		}
		if ( ! $og_image ) {
			// Fallback : image OG fournie par le thème
			$og_image = get_template_directory_uri() . '/assets/images/og-default.png';
		}

		echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc )     echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $canon )    echo '<meta property="og:url" content="' . esc_url( $canon ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site ) . '">' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '">' . "\n";
		if ( $og_image ) echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";

		// Twitter Card
		echo '<meta name="twitter:card" content="' . ( $og_image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc )     echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		if ( $og_image ) echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
		$tw = (string) get_option( self::OPTION_TWITTER_HANDLE, '' );
		if ( $tw ) {
			echo '<meta name="twitter:site" content="' . esc_attr( '@' . ltrim( $tw, '@' ) ) . '">' . "\n";
		}
	}

	public static function render_jsonld(): void {
		$blocks = [];

		if ( is_front_page() || is_home() ) {
			$blocks[] = [
				'@context' => 'https://schema.org',
				'@type'    => 'Organization',
				'name'     => get_option( self::OPTION_ORG_NAME, '' ) ?: get_bloginfo( 'name' ),
				'url'      => home_url( '/' ),
				'logo'     => get_option( self::OPTION_OG_IMAGE, '' ),
			];
			$blocks[] = [
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'name'            => get_bloginfo( 'name' ),
				'url'             => home_url( '/' ),
				'potentialAction' => [
					'@type'       => 'SearchAction',
					'target'      => home_url( '/?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				],
			];
		}

		if ( is_singular( CPT::VIDEO ) ) {
			$post     = get_queried_object();
			$yt_id    = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_YOUTUBE_ID, true );
			$duration = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_DURATION, true );
			$thumb    = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_THUMBNAIL_URL, true );
			$pub      = (string) get_post_meta( $post->ID, Meta_Keys::VIDEO_PUBLISHED_AT, true );
			$views    = (int) get_post_meta( $post->ID, Meta_Keys::VIDEO_VIEW_COUNT, true );

			$block = [
				'@context'     => 'https://schema.org',
				'@type'        => 'VideoObject',
				'name'         => get_the_title( $post ),
				'description'  => self::current_seo_description() ?: wp_trim_words( $post->post_content, 30 ),
				'thumbnailUrl' => $thumb,
				'uploadDate'   => $pub ?: get_the_date( 'c', $post ),
			];
			if ( $duration > 0 ) {
				$block['duration'] = self::seconds_to_iso8601( $duration );
			}
			if ( $yt_id ) {
				$block['embedUrl']    = 'https://www.youtube-nocookie.com/embed/' . $yt_id;
				$block['contentUrl']  = 'https://www.youtube.com/watch?v=' . $yt_id;
			}
			if ( $views > 0 ) {
				$block['interactionStatistic'] = [
					'@type'                => 'InteractionCounter',
					'interactionType'      => [ '@type' => 'WatchAction' ],
					'userInteractionCount' => $views,
				];
			}
			$blocks[] = $block;
		}

		if ( is_singular() ) {
			// Breadcrumbs simple
			$crumbs   = [ [ 'name' => 'Accueil', 'url' => home_url( '/' ) ] ];
			$post     = get_queried_object();
			$archive  = get_post_type_archive_link( $post->post_type );
			if ( $archive ) {
				$pto = get_post_type_object( $post->post_type );
				$crumbs[] = [ 'name' => $pto->labels->name ?? '', 'url' => $archive ];
			}
			$crumbs[] = [ 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) ];
			$blocks[] = [
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array_map( static function ( $c, $i ) {
					return [ '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url'] ];
				}, $crumbs, array_keys( $crumbs ) ),
			];
		}

		foreach ( $blocks as $b ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	private static function seconds_to_iso8601( int $sec ): string {
		$h = intdiv( $sec, 3600 );
		$m = intdiv( $sec % 3600, 60 );
		$s = $sec % 60;
		return 'PT' . ( $h ? $h . 'H' : '' ) . ( $m ? $m . 'M' : '' ) . ( $s ? $s . 'S' : '' ) ?: 'PT0S';
	}

	/* ======================================================================
	 *  Page admin Seoflix → SEO (templates titres + OG par défaut + outils)
	 * ====================================================================== */

	public static function register_admin_page(): void {
		add_submenu_page(
			'seoflix',
			'SEO',
			'SEO',
			'manage_options',
			'seoflix-seo',
			[ self::class, 'render_admin_page' ]
		);
	}

	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		?>
		<div class="wrap seoflix-wrap">
			<h1>SEO</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'seoflix_seo_settings' ); ?>

				<div class="seoflix-card">
					<h2>Templates de titre</h2>
					<table class="form-table">
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE_HOME ); ?>">Page d'accueil</label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE_HOME ); ?>" name="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE_HOME ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_TITLE_TEMPLATE_HOME, '%site% — %tagline%' ) ); ?>" class="large-text">
								<p class="description">Variables : <code>%site%</code>, <code>%tagline%</code>.</p>
							</td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE ); ?>">Pages internes (par défaut)</label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE ); ?>" name="<?php echo esc_attr( self::OPTION_TITLE_TEMPLATE ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_TITLE_TEMPLATE, '%title% | %site%' ) ); ?>" class="large-text">
								<p class="description">Variables : <code>%title%</code>, <code>%site%</code>. Surchargeable par page via la métabox SEO de chaque contenu.</p>
							</td>
						</tr>
						<tr>
							<th><label for="seoflix_seo_title_template_video">Vidéos (single)</label></th>
							<td>
								<input type="text" id="seoflix_seo_title_template_video" name="seoflix_seo_title_template_video" value="<?php echo esc_attr( get_option( 'seoflix_seo_title_template_video', '%title% — %channel% | %site%' ) ); ?>" class="large-text">
								<p class="description">Spécifique aux pages vidéos. Variables : <code>%title%</code>, <code>%channel%</code>, <code>%site%</code>. Le suffixe <code>— %channel% | %site%</code> donne un signal de différenciation vs YouTube côté SERP. Surchargeable par vidéo via sa métabox SEO.</p>
							</td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_DESC_HOME ); ?>">Meta description (accueil)</label></th>
							<td>
								<textarea id="<?php echo esc_attr( self::OPTION_DESC_HOME ); ?>" name="<?php echo esc_attr( self::OPTION_DESC_HOME ); ?>" rows="3" class="large-text"><?php echo esc_textarea( get_option( self::OPTION_DESC_HOME, '' ) ); ?></textarea>
								<p class="description">Si vide, utilise la baseline (Réglages → Général).</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="seoflix-card">
					<h2>Open Graph &amp; identité</h2>
					<table class="form-table">
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_OG_IMAGE ); ?>">Image OG par défaut</label></th>
							<td>
								<input type="url" id="<?php echo esc_attr( self::OPTION_OG_IMAGE ); ?>" name="<?php echo esc_attr( self::OPTION_OG_IMAGE ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_OG_IMAGE, '' ) ); ?>" class="large-text code">
								<p class="description">URL d'une image 1200×630 environ (Facebook/LinkedIn). Utilisée quand la page n'a pas de miniature. Sert aussi de logo Organization.</p>
							</td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_ORG_NAME ); ?>">Nom Organisation (JSON-LD)</label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( self::OPTION_ORG_NAME ); ?>" name="<?php echo esc_attr( self::OPTION_ORG_NAME ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_ORG_NAME, '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
								<p class="description">Si vide, utilise le nom du site.</p>
							</td>
						</tr>
						<tr>
							<th><label for="<?php echo esc_attr( self::OPTION_TWITTER_HANDLE ); ?>">Compte X / Twitter</label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( self::OPTION_TWITTER_HANDLE ); ?>" name="<?php echo esc_attr( self::OPTION_TWITTER_HANDLE ); ?>" value="<?php echo esc_attr( get_option( self::OPTION_TWITTER_HANDLE, '' ) ); ?>" class="regular-text" placeholder="seoflix">
								<p class="description">Sans le <code>@</code>. Optionnel.</p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="seoflix-card">
				<h2>Outils</h2>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-seo-robots' ) ); ?>">Éditer robots.txt</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=seoflix-seo-htaccess' ) ); ?>">Éditer .htaccess</a>
					<a class="button button-secondary" href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>" target="_blank">Voir le sitemap XML ↗</a>
				</p>
				<p class="description">WP génère automatiquement <code>/wp-sitemap.xml</code> depuis WP 5.5 — pas besoin de plugin pour ça. Tu peux le soumettre tel quel à Google Search Console.</p>
			</div>
		</div>
		<?php
	}
}
