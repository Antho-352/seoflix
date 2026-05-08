<?php
/**
 * Page produit affilié.
 */
get_header();

while ( have_posts() ) :
	the_post();
	$product_id    = get_the_ID();
	$category      = wp_get_object_terms( $product_id, 'seoflix_product_category', [ 'number' => 1 ] );
	$cat_name      = ( ! is_wp_error( $category ) && $category ) ? $category[0]->name : '';
	$pricing       = seoflix_product_pricing( $product_id );
	$pricing_label = match ( $pricing ) {
		'free'     => 'Gratuit',
		'freemium' => 'Freemium',
		'paid'     => 'Payant',
		default    => '',
	};
	$affiliate_url = seoflix_product_affiliate_url( $product_id );
	$go_url        = seoflix_product_go_url( $product_id );
	$official_url  = seoflix_product_official_url( $product_id );
	$videos        = seoflix_product_videos( $product_id, 12 );
	?>
	<article class="sx-container sx-page sx-product-page">

		<header class="sx-product-page__header">
			<?php if ( has_post_thumbnail( $product_id ) ) : ?>
				<div class="sx-product-page__logo">
					<?php echo get_the_post_thumbnail( $product_id, 'medium', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $cat_name ) : ?>
				<div class="sx-product-page__category"><?php echo esc_html( $cat_name ); ?></div>
			<?php endif; ?>
			<h1 class="sx-product-page__name"><?php the_title(); ?></h1>
			<?php if ( $pricing_label ) : ?>
				<div class="sx-product-page__pricing">
					<span class="sx-card-product__pricing sx-card-product__pricing--<?php echo esc_attr( $pricing ); ?>"><?php echo esc_html( $pricing_label ); ?></span>
				</div>
			<?php endif; ?>

			<div class="sx-product-page__cta-row">
				<?php if ( $affiliate_url ) : ?>
					<a class="sx-btn" href="<?php echo esc_url( $go_url ); ?>" rel="sponsored nofollow noopener" target="_blank">Découvrir <?php echo esc_html( get_the_title() ); ?> ↗</a>
				<?php elseif ( $official_url ) : ?>
					<a class="sx-btn" href="<?php echo esc_url( $official_url ); ?>" target="_blank" rel="nofollow noopener">Site officiel ↗</a>
				<?php endif; ?>
			</div>

			<?php if ( $affiliate_url ) : ?>
				<span class="sx-affiliate-notice" style="margin-top: var(--sx-space-4);">Le bouton « Découvrir » contient un lien d'affiliation. <a href="<?php echo esc_url( home_url( '/affiliation/' ) ); ?>">En savoir plus</a>.</span>
			<?php endif; ?>
		</header>

		<div class="sx-product-page__description">
			<?php the_content(); ?>
		</div>

		<?php if ( $videos ) {
			seoflix_render_video_row( 'Vidéos qui en parlent', $videos );
		} ?>

	</article>
<?php endwhile; ?>

<?php get_footer();
