<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package joyas-shop
 */

?>
<aside id="secondary" class="widget-area">
	<?php 
	if( is_active_sidebar( 'woocommerce' ) && ( function_exists('is_shop') && is_shop() ) || ( function_exists('is_product_category') && is_product_category() ) ){
		dynamic_sidebar( 'woocommerce' );
	}elseif ( is_active_sidebar( 'sidebar-1' ) ) {
        dynamic_sidebar( 'sidebar-1' );
   	}?>
</aside><!-- #secondary -->


