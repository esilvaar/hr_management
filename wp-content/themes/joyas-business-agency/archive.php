<?php
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package joyas-shop
 */
get_header();
$layout = joyas_shop_get_option('blog_layout');
/**
* Hook - container_wrap_start 		- 5
*
* @hooked joyas_shop_container_wrap_start
*/
 do_action( 'joyas_shop_container_wrap_start',esc_attr( $layout ));
 if ( have_posts() ) : 
		/* Start the Loop */
		echo '<div class="row">';
		while ( have_posts() ) :
			the_post();
			/*
			 * Include the Post-Type-specific template for the content.
			 * If you want to override this in a child theme, then include a file
			 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
			 */
			get_template_part( 'template-parts/content', 'grid');
		endwhile;
		echo '</div>';
		the_posts_navigation();
	else :
		get_template_part( 'template-parts/content', 'none' );
	endif;
/**
* Hook - container_wrap_end 		- 999
*
* @hooked joyas_shop_container_wrap_end
*/
 do_action( 'joyas_shop_container_wrap_end',esc_attr( $layout ));
get_footer();
