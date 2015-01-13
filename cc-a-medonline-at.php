<?php
/**
 * Plugin Name: Custom Code for a.medonline.at
 * Description: Site-specific functionality for a.medonline.at like returning https links of wp-filebase-pro's CSS on https sites and redirecting page "/pneumo/" to https.
 * Author: Frank St&uuml;rzebecher
 * Version: 0.7
 * Plugin URI: https://github.com/medizinmedien/cc-a-medonline-at
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
	if( ( strpos( $_SERVER['REQUEST_URI'], '/pneumo'   ) !== false   ||
	      strpos( $_SERVER['REQUEST_URI'], '/novalgin' ) !== false )
	      && $_SERVER['SERVER_PORT'] == 80 ) {
		wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit();
	}
}
add_action( 'template_redirect', 'amed_redirect_pneumo');


/**
 * Make the "Protected Posts Logout Button" appear in German, when it is set to
 * be inserted into posts automatically.
 */
function amed_translate_automatic_postpass_logout_button() {
	global $post;

	// Protected post?
	if ( empty( $post->post_password) || ! in_the_loop() )
		return;

	// The plugin "Protected Posts Logout Button" should also be active.
	// Does not work when buttons inserted via shortcodes (see extra filter below).
	if( function_exists( 'pplb_logout_filter' ) ) {
		function amed_adjust_postpass_logout_button( $content ) {
			return str_replace( 'value="logout">', 'value="Abmelden"><br><br>', $content );
		}

		// Interferes with other password protected posts (Logoff not working).
		// Does nothing if logout button is not set to be inserted automatically, via settings page.
		if ( $_SERVER['REQUEST_URI'] != '/pneumo/' && $_SERVER['REQUEST_URI'] != '/novalgin/' ) {
			remove_filter( 'the_content', 'pplb_logout_filter', 9999, 1 );
		}

		// Because the plugin itself sets a filter priority of 9999 we have to overrule this.
		add_filter( 'the_content', 'amed_adjust_postpass_logout_button', 10000 );
	}
}
add_action( 'template_redirect', 'amed_translate_automatic_postpass_logout_button' );

/**
 * Make the "Protected Posts Logout Button" appear in German, when it is set to
 * be inserted into posts by shortcode.
 */
function amed_translate_shortcoded_postpass_logout_button( $content ) {
	if ( $_SERVER['REQUEST_URI'] != '/pneumo/' && $_SERVER['REQUEST_URI'] != '/novalgin/' )
		return $content;
	return str_replace( 'value="logout"', 'value="Abmelden"', $content );
}
// WP do_shortcode filter is applied with priority 11
add_filter( 'the_content', 'amed_translate_shortcoded_postpass_logout_button', 12 );


/**
 * Avoid autocomplete in password fields of protected posts.
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

/**
 * Add X-Frame-Options directive to secure pages from being embedded.
 */
function cc_amed_add_xframeoptions() {
	if ( ! is_admin() )
		?><meta http-equiv="X-Frame-Options" content="deny" /><?php
}
add_action( 'wp_head', 'cc_amed_add_xframeoptions', 5 );

/**
* Load Fullstory from Shared Includes.
*/
function cc_amed_load_fullstory() {

	$fullstory_file = WP_PLUGIN_DIR . '/Shared-Includes/inc/track/fullstory-tracking.php';

	if( file_exists( $fullstory_file ) )
		include( $fullstory_file );

}
add_action( 'wp_footer',    'cc_amed_load_fullstory' );
add_action( 'login_footer', 'cc_amed_load_fullstory' );

/**
* Embed Groove code into page footers to avoid anonymous support requests.
*/
function cc_amed_add_groove() {

	// No include on special pages.
	if(( defined( 'DOING_CRON' ) && DOING_CRON )
	|| ( defined( 'XMLRPC_REQUEST') && XMLRPC_REQUEST )
	|| ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE )
	|| ( defined( 'DOING_AJAX' ) && DOING_AJAX)
	|| is_page( array('impressum', 'kontakt', 'pneumo') ))
		return;

	$groove_include = WP_PLUGIN_DIR . '/Shared-Includes/inc/groove/groove-help-widget.php';

	if( file_exists( $groove_include ) )
		include( $groove_include );

}
//add_action( 'wp_footer',  'cc_amed_add_groove' );
//add_action( 'login_head', 'cc_amed_add_groove' );


/**
 * Make Headway links https when needed.
 */
 // Callback function for ob_start.
function cc_amed_headway_replace_https( $buffer ){
	$scheme = is_ssl() ? 'https://' : 'http://';
	return str_replace( 'http://a.medonline.at', $scheme . 'a.medonline.at', $buffer );
}
// Start buffering.
function cc_amed_begin_headway_obstart() {
	ob_start('cc_amed_headway_replace_https');
}
add_action('headway_html_open', 'cc_amed_begin_headway_obstart');
// Finish buffering.
function cc_amed_headway_ob_end_flush() {
	ob_end_flush();
}
add_action('headway_html_close', 'cc_amed_headway_ob_end_flush');


