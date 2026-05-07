<?php
/**
 * Page WordPress standard (mentions légales, affiliation, confidentialité, etc.).
 */
get_header();

while ( have_posts() ) : the_post(); ?>
	<article class="sx-container sx-page sx-page--narrow">
		<h1 style="font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; letter-spacing: -0.02em; margin-bottom: var(--sx-space-6);"><?php the_title(); ?></h1>
		<div style="line-height: 1.7; max-width: 70ch;"><?php the_content(); ?></div>
	</article>
<?php endwhile;

get_footer();
