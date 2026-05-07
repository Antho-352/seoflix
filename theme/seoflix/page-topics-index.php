<?php
/**
 * Vue : index de toutes les catégories (sujets, formats, parcours).
 * URL : /categories/
 */
get_header();

$topics  = get_terms( [ 'taxonomy' => 'seoflix_topic',  'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ] );
$formats = get_terms( [ 'taxonomy' => 'seoflix_format', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ] );
$paths   = get_terms( [ 'taxonomy' => 'seoflix_path',   'hide_empty' => false, 'orderby' => 'name',  'order' => 'ASC' ] );
?>

<div class="sx-container sx-page">

	<header class="sx-archive-header">
		<div class="sx-archive-header__kicker">Index</div>
		<h1 class="sx-archive-header__title">Toutes les catégories</h1>
		<p class="sx-archive-header__count">Explore les vidéos par sujet, format ou parcours d'apprentissage.</p>
	</header>

	<?php if ( ! is_wp_error( $topics ) && $topics ) : ?>
		<section class="sx-row" style="margin-bottom: var(--sx-space-12);">
			<div class="sx-row__header">
				<h2 class="sx-row__title">Sujets</h2>
			</div>
			<div class="sx-grid sx-grid--products">
				<?php foreach ( $topics as $t ) : ?>
					<a class="sx-card-product" href="<?php echo esc_url( get_term_link( $t ) ); ?>" style="text-decoration: none; display: block;">
						<h3 class="sx-card-product__name"><?php echo esc_html( $t->name ); ?></h3>
						<p class="sx-card-product__excerpt"><?php echo (int) $t->count; ?> vidéo<?php echo $t->count > 1 ? 's' : ''; ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $formats ) && $formats ) : ?>
		<section class="sx-row" style="margin-bottom: var(--sx-space-12);">
			<div class="sx-row__header">
				<h2 class="sx-row__title">Formats</h2>
			</div>
			<div class="sx-grid sx-grid--products">
				<?php foreach ( $formats as $t ) : ?>
					<a class="sx-card-product" href="<?php echo esc_url( get_term_link( $t ) ); ?>" style="text-decoration: none; display: block;">
						<h3 class="sx-card-product__name"><?php echo esc_html( $t->name ); ?></h3>
						<p class="sx-card-product__excerpt"><?php echo (int) $t->count; ?> vidéo<?php echo $t->count > 1 ? 's' : ''; ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! is_wp_error( $paths ) && $paths ) : ?>
		<section class="sx-row">
			<div class="sx-row__header">
				<h2 class="sx-row__title">Parcours d'apprentissage</h2>
			</div>
			<div class="sx-grid sx-grid--products">
				<?php foreach ( $paths as $t ) : ?>
					<a class="sx-card-product" href="<?php echo esc_url( get_term_link( $t ) ); ?>" style="text-decoration: none; display: block;">
						<h3 class="sx-card-product__name"><?php echo esc_html( $t->name ); ?></h3>
						<p class="sx-card-product__excerpt"><?php echo (int) $t->count; ?> vidéo<?php echo $t->count > 1 ? 's' : ''; ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

</div>

<?php get_footer();
