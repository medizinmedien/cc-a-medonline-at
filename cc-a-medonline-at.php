<?php
/**
 * Plugin Name: Custom Code for a.medonline.at
 * Description: Site-specific functionality for a.medonline.at like returning https links of wp-filebase-pro's CSS on https sites and redirecting page "/pneumo/" to https.
 * Author: Frank St&uuml;rzebecher
 * Version: 0.3
 * GitHub Plugin URI: https://github.com/medizinmedien/cc-a-medonline-at
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
	if( strpos( $_SERVER['REQUEST_URI'], '/pneumo' ) !== false && $_SERVER['SERVER_PORT'] == 80 ) {
		wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit();
	}
}
add_action( 'template_redirect', 'amed_redirect_pneumo');


/**
 * Make the "Post Password Logout Button" appear in German.
 */
function amed_translate_postpass_button() {
	// The plugin should be installed.
	if( function_exists( 'pplb_logout_filter' ) ) {
		function amed_adjust_postpass_logout_button( $content ) {
			return str_replace( 'value="logout">', 'value="Abmelden"><br/><br/>', $content );
		}

		// Interferes with other password protected posts (Logoff not working).
		if ( $_SERVER['REQUEST_URI'] != '/pneumo/' ) {
			remove_filter( 'the_content', 'pplb_logout_filter', 9999, 1 );
		}

		// Because the plugin itself sets a filter priority of 9999 we have to overrule this.
		add_filter( 'the_content', 'amed_adjust_postpass_logout_button', 10000 );
	}
}
add_action( 'template_redirect', 'amed_translate_postpass_button' );


/**
 * Avoid autocomplete in password fields of proteced posts.
 */
function amed_secure_postpass_form ( $form ) {

	$needed_form = str_replace(
		array(
			'class="post-password-form"',
			'name="post_password"',
		),
		array(
			'class="post-password-form" autocomplete="off"',
			'name="post_password" autocomplete="off"',
		),
		$form
	);
	return $needed_form;
}
add_filter( 'the_password_form', 'amed_secure_postpass_form');


/**
 * Provide pluggable WP function to renew the post password cookie
 * and add security attributes to it.
 */
if ( !function_exists( 'wp_safe_redirect' ) ) {
function wp_safe_redirect($location, $status = 302) {

	// Added part: make the hardcoded WP cookie "secure" and "httponly".
	if( isset($_GET['action']) && $_GET['action'] == 'postpass' ) { // set in wp-login.php
		global $hasher, $expire;
		$ssl = ( strpos( $location, 'https://' ) === 0 ) ? true : false;
		setcookie( 'wp-postpass_' . COOKIEHASH,
			$hasher->HashPassword( wp_unslash( $_POST['post_password'] ) ),
			$expire,
			COOKIEPATH,
			'',   // actual domain
			$ssl, // secure
			true  // httponly
		);
		// Since we die here, the cookie has to be renewed already.
		if( strlen($location) == 0 )
			wp_die( 'Ihr Webbrowser scheint keinen Referer zu senden.<br/>Bitte verwenden Sie daher jetzt die Zur&uuml;ck-Schaltfl&auml;che Ihres Browsers und dr&uuml;cken Sie dann die Taste F5 auf Ihrer Tastatur ("Aktualisieren").' );

	} // End of added part - what follows is WP stuff.

	// Need to look at the URL the way it will end up in wp_redirect()
	$location = wp_sanitize_redirect($location);
	$location = wp_validate_redirect($location, admin_url());
	wp_redirect($location, $status);
}
}

