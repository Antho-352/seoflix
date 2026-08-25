<?php
/**
 * Article éditorial natif.
 */
get_header();
?>

<div class="sx-container sx-page sx-article-shell">
	<?php while ( have_posts() ) : the_post();
		$post_id      = get_the_ID();
		$categories   = get_the_category( $post_id );
		$reading_time = seoflix_post_reading_time( get_post( $post_id ) );
		$posts_page   = (int) get_option( 'page_for_posts' );
		$blog_url     = $posts_page ? get_permalink( $posts_page ) : home_url( '/blog/' );
		$previous     = get_previous_post();
		$next         = get_next_post();
		$category_ids = wp_list_pluck( $categories, 'term_id' );
		$related_args = [
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => [ $post_id ],
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		];
		if ( $category_ids ) {
			$related_args['category__in'] = $category_ids;
		}
		$related_posts = get_posts( $related_args );
		?>

		<article <?php post_class( 'sx-article' ); ?>>
			<nav class="sx-breadcrumbs" aria-label="Fil d’Ariane">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( $blog_url ); ?>">Blog</a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
			</nav>

			<header class="sx-article__header">
				<?php if ( $categories ) : ?>
					<div class="sx-article__categories" aria-label="Catégories">
						<?php foreach ( $categories as $category ) : ?>
							<a href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<h1 class="sx-article__title"><?php echo esc_html( get_the_title() ); ?></h1>

				<div class="sx-article__meta">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<span aria-hidden="true">·</span>
					<span><?php echo esc_html( sprintf( _n( '%d minute de lecture', '%d minutes de lecture', $reading_time, 'seoflix' ), $reading_time ) ); ?></span>
				</div>

				<?php if ( has_excerpt() ) : ?>
					<p class="sx-article__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) :
				$thumbnail_id  = get_post_thumbnail_id( $post_id );
				$thumbnail_alt = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
				if ( '' === $thumbnail_alt ) {
					$thumbnail_alt = get_the_title();
				}
				?>
				<figure class="sx-article__featured">
					<?php echo get_the_post_thumbnail( $post_id, 'full', [
						'alt'      => $thumbnail_alt,
						'class'    => 'sx-article__featured-image',
						'decoding' => 'async',
					] ); ?>
				</figure>
			<?php endif; ?>

			<div class="sx-article__content">
				<?php the_content(); ?>
				<?php wp_link_pages( [
					'before' => '<nav class="sx-article__page-links" aria-label="Pages de l’article">',
					'after'  => '</nav>',
				] ); ?>
			</div>
		</article>

		<?php if ( $previous || $next ) : ?>
			<nav class="sx-article-nav" aria-label="Articles adjacents">
				<?php if ( $previous ) : ?>
					<a class="sx-article-nav__link sx-article-nav__link--previous" href="<?php echo esc_url( get_permalink( $previous ) ); ?>">
						<span class="sx-article-nav__label">Article précédent</span>
						<strong><?php echo esc_html( get_the_title( $previous ) ); ?></strong>
					</a>
				<?php endif; ?>
				<?php if ( $next ) : ?>
					<a class="sx-article-nav__link sx-article-nav__link--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
						<span class="sx-article-nav__label">Article suivant</span>
						<strong><?php echo esc_html( get_the_title( $next ) ); ?></strong>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $related_posts ) : ?>
			<section class="sx-related-posts" aria-labelledby="sx-related-title">
				<div class="sx-related-posts__heading">
					<p>À lire ensuite</p>
					<h2 id="sx-related-title">Articles récents liés</h2>
				</div>
				<div class="sx-blog-grid sx-related-posts__grid">
					<?php foreach ( $related_posts as $related_post ) : ?>
						<?php seoflix_render_post_card( $related_post, [ 'heading_level' => 3 ] ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endwhile; ?>
</div>

<?php get_footer();
