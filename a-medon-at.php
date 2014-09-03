<?php
/**
 * Plugin Name: Custom Code for a.medonline.at
 * Description: Site-specific functionality for a.medonline.at like returning https links of wp-filebase-pro's CSS on https sites and redirecting page "/pneumo/" to https.
 * Author: Frank St&uuml;rzebecher
 * Version: 0.2
 */

defined( 'ABSPATH' ) || exit();

/**
 * Correct WP Filebase Pro's setup option when SSL pages are requested.
 */
function amed_return_https( $wp_filebase_pro_css_file_url ) {
        if (is_ssl() )
                return str_replace( 'http://', 'https://', $wp_filebase_pro_css_file_url );
        else
                return $wp_filebase_pro_css_file_url;
}
add_filter( 'pre_option_wpfb_css', 'amed_return_https' );


/**
 * Make sure that page "/pneumo/" is delivered by https.
 */
function amed_redirect_pneumo() {
	if( $_SERVER['REQUEST_URI'] == '/pneumo/' && $_SERVER['SERVER_PORT'] == 80 ) {
		wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit();
	}
}
add_action( 'template_redirect', 'amed_redirect_pneumo');