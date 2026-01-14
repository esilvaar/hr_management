<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package joyas-shop
 */
?>
<article data-aos="fade-up" id="post-<?php the_ID(); ?>" <?php post_class( array('joyas-shop-single-post') ); ?>>
 	<?php
    //do_action( 'joyas_shop_posts_blog_media' );
    ?>
    <div class="post search-page">
		<?php
        do_action( 'joyas_shop_site_content_type', array( 'date', 'category' ) );
        ?>
    </div>
</article><!-- #post-<?php the_ID(); ?> -->