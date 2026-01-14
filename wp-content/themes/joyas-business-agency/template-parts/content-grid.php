<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package joyas-shop
 */
?>
<article data-aos="fade-up" id="post-<?php the_ID(); ?>" <?php post_class( array('joyas-shop-single-post','col-md-6','col-12') ); ?>>
	<?php
    do_action( 'joyas_shop_posts_blog_media' );
    ?>
    <div class="post">
		<?php
        /**
        * Hook - joyas-shop_site_content_type.
        *
		* @hooked site_loop_heading - 10
        * @hooked render_meta_list	- 20
		* @hooked site_content_type - 30
        */
		$meta = array();
		if( joyas_shop_get_option('blog_meta_hide') != true ){
			
			$meta = array( 'author', 'date' );
		}
		$meta  	 = apply_filters( 'joyas_shop_blog_meta', $meta );
		do_action( 'joyas_shop_site_content_type', $meta  );
        ?>
    </div>
</article><!-- #post-<?php the_ID(); ?> -->
