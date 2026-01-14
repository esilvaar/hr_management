<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

function joyas_business_agency_theme_setup(){

    // Make theme available for translation.
    load_theme_textdomain( 'joyas-business-agency', get_stylesheet_directory_uri() . '/languages' );
    
    add_theme_support( 'custom-header', apply_filters( 'joyas_business_agency_custom_header_args', array(
        'default-image' => get_stylesheet_directory_uri() . '/assets/image/custom-header.jpg',
        'default-text-color'     => '000000',
        'width'                  => 1000,
        'height'                 => 350,
        'flex-height'            => true,
        'wp-head-callback'       => 'joyas_shop_header_style',
    ) ) );
    
    register_default_headers( array(
        'default-image' => array(
        'url' => '%s/assets/image/custom-header.jpg',
        'thumbnail_url' => '%s/assets/image/custom-header.jpg',
        'description' => esc_html__( 'Default Header Image', 'joyas-business-agency' ),
        ),
    ));

}
add_action( 'after_setup_theme', 'joyas_business_agency_theme_setup' );

// BEGIN ENQUEUE PARENT ACTION 
function joyas_business_agency_child_enqueue_styles() {
    // Load parent style first
    wp_enqueue_style(
        'joyas-shop',
        get_template_directory_uri() . '/style.css'
    );

    // Then load child style, after parent
    wp_enqueue_style(
        'joyas-business-agency',
        get_stylesheet_directory_uri() . '/style.css',
        array('joyas-shop'), // Make it dependent on parent
        wp_get_theme()->get('Version') // Automatically use child theme version
    );
    wp_enqueue_style( 'aos-next', get_stylesheet_directory_uri() . '/assets/aos-next/aos.css');
    wp_enqueue_script( 'aos-next-js', get_stylesheet_directory_uri() . '/assets/aos-next/aos.js',0, '3.3.7', true );
     wp_enqueue_script( 'joyas-business-agency', get_theme_file_uri( '/assets/js/joyas-business-agency.js'), array('jquery'), '1.0.0', true);
}
add_action( 'wp_enqueue_scripts', 'joyas_business_agency_child_enqueue_styles',999 );

// END ENQUEUE PARENT ACTION
if ( ! function_exists( 'joyas_business_agency_disable_from_parent' ) ) {
    function joyas_business_agency_disable_from_parent() {
        global $joyas_shop_header_layout, $joyas_shop_post_related, $joyas_shop_footer_layout;
        // Safely remove parent actions if object is set
        remove_action( 'joyas_shop_site_header', array( $joyas_shop_header_layout, 'site_header_layout' ), 30 );
        remove_action( 'joyas_shop_site_header', array( $joyas_shop_header_layout, 'site_header_wrap_before' ), 10 );
        remove_action( 'joyas_shop_loop_navigation', array( $joyas_shop_post_related,'site_loop_navigation') );

        remove_action('joyas_shop_site_footer', array( $joyas_shop_footer_layout, 'site_footer_info' ), 80 );  

        remove_action( 'woocommerce_before_main_content', 'joyas_shop_woocommerce_wrapper_before' ); 
        remove_action( 'woocommerce_after_main_content', 'joyas_shop_woocommerce_wrapper_after' );
        remove_action( 'woocommerce_shop_loop_item_title', 'joyas_shop_shop_loop_item_title', 10 );
        
    }

    add_action( 'init', 'joyas_business_agency_disable_from_parent', 10 );
}
if( !function_exists('joyas_business_agency_wrap_before') ) : 
function joyas_business_agency_wrap_before(){
?>
<header id="masthead" class="site-header style_6">
<?php
}
add_action('joyas_shop_site_header', 'joyas_business_agency_wrap_before', 15 );
endif;

if( !function_exists('joyas_business_agency_header_layout') ) : 
function joyas_business_agency_header_layout(){
?>
<div class="header_wrap ">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <?php
            do_action('joyas_shop_header_layout_1_branding');
            do_action('joyas_shop_header_layout_1_navigation'); 
            ?>
            <ul class="header-icon d-flex justify-content-end ">
            <li class="flex-fill flex-grow-1"><button type="button" class="searchbar-action"><i class="icofont-ui-search"></i></button></li>
            <?php if ( class_exists( 'WooCommerce' ) ) :?>
            <li><?php joyas_shop_woocommerce_cart_link(); ?></li>
            <?php endif;?>
            <li class="toggle-list"><button class="joyas-shop-rd-navbar-toggle" tabindex="0" autofocus="true"><i class="icofont-navigation-menu"></i></button></li>
            </ul>
        </div>
    </div>
</div>
<?php
}
add_action('joyas_shop_site_header', 'joyas_business_agency_header_layout', 30 );
endif;

add_filter( 'joyas-shop_image_thumbnail', 'joyas_business_agency_image_thumbnail' );

function joyas_business_agency_image_thumbnail($html) {
    // Modify the value
    $html = '';
    if ( has_post_thumbnail() ) :
        $html = '<div class="img-box">';
        $post_thumbnail_id  = get_post_thumbnail_id( get_the_ID() );
        $post_thumbnail_url = wp_get_attachment_url( $post_thumbnail_id );
        
        $html .= '<i class="icofont-image"></i>';
        if ( is_singular() )
        {
            $html  .=  '<a href="'.esc_url( $post_thumbnail_url ).'" class="image-popup thickbox animation_on" >';
        } else{
            $html  .= '<a href="'.esc_url( get_permalink(get_the_ID()) ).'" class="image-link animation_on">';
        }
        $html .= get_the_post_thumbnail( get_the_ID(), 'full' );
        $html .='</a>';
        $html .= '</div>';
    endif;
    return $html;
}

if( !function_exists('joyas_business_agency_search_modal') ):
function joyas_business_agency_search_modal(){
    echo '<div class="search-bar-modal" id="search-bar"><div class="modal-wrap">
    <button class="button appw-modal-close-button" type="button"><i class="bi bi-x-lg"></i></button>';
        if( class_exists('APSW_Product_Search_Finale_Class_Pro') && class_exists( 'WooCommerce' ) ){
            do_action('apsw_search_bar_preview');
        }else if( class_exists('APSW_Product_Search_Finale_Class') && class_exists( 'WooCommerce' ) ){
            do_action('apsw_search_bar_preview');
        }else{
            echo '<form role="search" method="get" id="searchform" class="search-form" action="' . esc_url( home_url( '/' ) ) . '" >
            <input type="search" value="' . get_search_query() . '" name="s" id="s" placeholder="'.esc_html__('Search …','joyas-business-agency').'" />
            <button type="submit" class="search-submit">'.esc_html__( 'Search', 'joyas-business-agency' ).'</button>
            </form>';
        }
    echo'</div></div>';

}
add_action('joyas_shop_site_footer', 'joyas_business_agency_search_modal', 999);
endif;

if( !function_exists('joyas_business_agency_default_options') ):
add_filter( 'joyas_shop_filter_default_theme_options', 'joyas_business_agency_default_options' );
function joyas_business_agency_default_options( $value ) {
    $value['blog_layout']         = 'full-container';
    $value['single_post_layout']  = 'no-sidebar';
    return $value;
}
endif;

function joyas_business_agency_render_meta() {
    if ( empty( get_the_ID() ) || !is_singular('post') ) {
        return;
    }
    $post_id = get_the_ID();
    $author_id = get_post_field( 'post_author', $post_id );
    $author_url = esc_url( get_author_posts_url( $author_id ) );
    $author_name = esc_html( get_the_author_meta( 'display_name', $author_id ) );

    // Published and modified dates
    $published_time = sprintf(
        '<time class="entry-date published" datetime="%s" content="%s">%s</time>',
        esc_attr( get_the_date( 'c', $post_id ) ),
        esc_attr( get_the_date( 'Y-m-d', $post_id ) ),
        esc_html( get_the_date( '', $post_id ) )
    );

    if ( get_the_time( 'U', $post_id ) === get_the_modified_time( 'U', $post_id ) ) {
        $time_html = $published_time;
    } else {
        $modified_time = sprintf(
            '<time class="updated" datetime="%s">%s</time>',
            esc_attr( get_the_modified_date( 'c', $post_id ) ),
            esc_html( get_the_modified_date( '', $post_id ) )
        );
        $time_html = $published_time .' - '. $modified_time;
    }

    // Categories list
    $category_list = get_the_category_list( ', ', '', $post_id );

    // Comments count
    $comments_number = get_comments_number( $post_id );

    $markup = '<ul class="post-meta tb-cell">';

    $markup .= '<li class="post-by">';
    $markup .= '<span>' . esc_html__( 'By - ', 'joyas-business-agency') . '</span>';
    $markup .= '<a href="' . $author_url . '">' . $author_name . '</a>';
    $markup .= '</li>';

    $markup .= '<li class="meta date posted-on">';
    $markup .= esc_html__( 'Posted on ', 'joyas-business-agency' );
    $markup .= $time_html;
    $markup .= '</li>';

    if ( $category_list ) {
        $markup .= '<li class="meta category">';
        $markup .= esc_html__( 'Posted in ', 'joyas-business-agency');
        $markup .= $category_list;
        $markup .= '</li>';
    }

    if ( $comments_number ) {
        $markup .= '<li class="meta comments">';
        $markup .= sprintf(
            /* translators: %s: Number of comments. */
            _n( '%s Comment', '%s Comments', $comments_number, 'joyas-business-agency'),
            number_format_i18n( $comments_number )
        );
        $markup .= '</li>';
    }

    $markup .= '</ul>';

    echo wp_kses_post( $markup );
}

add_action( 'joyas_shop_single_post_title', 'joyas_business_agency_render_meta' );
/**
 * Post Posts Loop Navigation
 * add_action( 'joyas_loop_navigation', $array( $this,'site_loop_navigation' ) ); 
 * @since 1.0.0
 */
function joyas_business_agency_loop_navigation( $type = '' ) {
     echo '<div class="joyas-business-agency">';
        the_posts_pagination( array(
            'type' => 'list',
            'mid_size' => 2,
            'prev_text' => esc_html__( 'Previous', 'joyas-business-agency' ),
            'next_text' => esc_html__( 'Next', 'joyas-business-agency' ),
            'screen_reader_text' => esc_html__( '&nbsp;', 'joyas-business-agency' ),
        ) );
    echo '</div>';
}
add_action('joyas_shop_loop_navigation', 'joyas_business_agency_loop_navigation', 20 );

function joyas_business_agency_footer_info (){
    $text ='';
    $html = '<div class="container site_info">
                <div class="row">';
        $html .= '<div class="col-12 ">';
        if( get_theme_mod('copyright_text') != '' ) 
        {
            $text .= esc_html(  get_theme_mod('copyright_text') );
        }else
        {
            /* translators: 1: Current Year, 2: Blog Name  */
            $text .= sprintf( esc_html__( 'Copyright &copy; %1$s %2$s. All Right Reserved.', 'joyas-business-agency' ), date_i18n( _x( 'Y', 'copyright date format', 'joyas-business-agency' ) ), esc_html( get_bloginfo( 'name' ) ) );
        }
        $html  .= apply_filters( 'joyas_shop_footer_copywrite_filter', $text );
        $l=substr(strtolower(get_locale()),0,2);
        $dev_url=($l==='de')?'https://de.athemeart.com/':(($l==='es')?'https://es.athemeart.com/':(($l==='pl')?'https://pl.athemeart.com/':(($l==='jp'||$l==='ja')?'https://jp.athemeart.com/':'https://athemeart.com/')));
        $html.=' <span class="dev_info">'.sprintf(
            esc_html__('%1$s theme by %2$s.','joyas-business-agency'),
            '<a href="'.esc_url('https://athemeart.com/downloads/joyas-business-agency/').'" target="_blank">'.esc_html_x('Joyas Business Agency','credit - theme','joyas-business-agency').'</a>',
            '<a href="'.esc_url($dev_url).'" target="_blank">'.esc_html_x('aThemeArt','credit - theme','joyas-business-agency').'</a>'
        ).'</span>';
        $html .= '</div>';
    $html .= '  </div>
            </div>';
    echo wp_kses( $html, joyas_shop_alowed_tags() );

}
add_action('joyas_shop_site_footer', 'joyas_business_agency_footer_info', 30 );

if ( ! function_exists( 'joyas_business_agency_woocommerce_wrapper_before' ) ) {

function joyas_business_agency_widgets_init() {
    if ( class_exists( 'WooCommerce' ) ) {  
        register_sidebar( array(
            'name'          => esc_html__( 'WooCommerce', 'joyas-business-agency' ),
            'id'            => 'woocommerce',
            'description'   => esc_html__( 'Add widgets here.', 'joyas-business-agency' ),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h3 class="widget-title"><span>',
            'after_title'   => '</span></h3>',
        ) );
    }
}
add_action( 'widgets_init', 'joyas_business_agency_widgets_init' );
}

if ( ! function_exists( 'joyas_business_agency_woocommerce_wrapper_before' ) ) {
    /**
     * Before Content.
     *
     * Wraps all WooCommerce content in wrappers which match the theme markup.
     *
     * @return void
     */
    function joyas_business_agency_woocommerce_wrapper_before() {
        /**
        * Hook - joyas_shop_container_wrap_start    
        *
        * @hooked joyas_shop_container_wrap_start   - 5
        */
        if( is_product() ){
         do_action( 'joyas_shop_container_wrap_start', 'no-sidebar');
        }else{
         do_action( 'joyas_shop_container_wrap_start', 'content-sidebar');
        }
    }
}
add_action( 'woocommerce_before_main_content', 'joyas_business_agency_woocommerce_wrapper_before' );

if ( ! function_exists( 'joyas_business_agency_woocommerce_wrapper_after' ) ) {
    /**
     * After Content.
     *
     * Closes the wrapping divs.
     *
     * @return void
     */
    function joyas_business_agency_woocommerce_wrapper_after() {
        /**
        * Hook - joyas_shop_container_wrap_end  
        *
        * @hooked container_wrap_end - 999
        */
        if( is_product() ){
         do_action( 'joyas_shop_container_wrap_end', 'no-sidebar');
        }else{
         do_action( 'joyas_shop_container_wrap_end', 'content-sidebar');
        }

    }
}
add_action( 'woocommerce_after_main_content', 'joyas_business_agency_woocommerce_wrapper_after' );

/**
 * Default loop columns on product archives.
 *
 * @return integer products per row.
 */
function joyas_business_agency_woocommerce_loop_columns() {
    return 3;
}
add_filter( 'loop_shop_columns', 'joyas_business_agency_woocommerce_loop_columns',999 );

/**
 * Related Products Args.
 *
 * @param array $args related products args.
 * @return array $args related products args.
 */
function joyas_business_agency_related_products_args( $args ) {
    $defaults = array(
        'posts_per_page' => 3,
        'columns'        => 3,
    );

    $args = wp_parse_args( $defaults, $args );

    return $args;
}

add_filter( 'woocommerce_cross_sells_columns', 'joyas_business_agency_related_products_args',99 );
 
function joyas_business_agency_change_cross_sells_columns( $columns ) {
    return 3;
}
add_filter( 'woocommerce_cross_sells_columns', 'joyas_business_agency_change_cross_sells_columns',99 );

if ( ! function_exists( 'joyas_business_agency_loop_item_title' ) ) {

    /**
     * Show the product title in the product loop. By default this is an h4.
     */
    function joyas_business_agency_loop_item_title() {
        echo '<h3 class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . esc_html( get_the_title() ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    add_action( 'woocommerce_shop_loop_item_title', 'joyas_business_agency_loop_item_title', 10 );
}
