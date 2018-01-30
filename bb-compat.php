<?php

/**
 * Plugin Name: Beaver Builder VIP Go Plugin
 * Description: Tweaks to make Beaver Builder work more seamlessly on VIP Go.
 *
 * Note: this relies on a few custom overrides made to the Beaver Builder plugin in the Existent repo. These change are being discussed with the BB team to include in the plugin directly.
 *
 * For background, see:
 *    https://github.com/wpcomvip/existent/pull/2
 *    https://github.com/wpcomvip/existent/pull/3
 */

add_filter( 'fl_customizer_after_compile_css', 'existent_upload_bb_file', 10, 2 );
add_filter( 'fl_customizer_after_render_css', 'existent_upload_bb_file', 10, 2 );
add_filter( 'fl_customizer_after_render_js', 'existent_upload_bb_file', 10, 2 );

function existent_upload_bb_file( $path, $content ) {
	// Make sure that CSS and JS files can be uploaded
	existent_allow_bb_css_and_js_uploads();

	$upload_dir = wp_get_upload_dir();

	// Get the path relative to the upload basedir (i.e. all folders after /tmp/uploads )
	$new_path = $path;
	$new_path = str_replace( $upload_dir['basedir'], '', $new_path );
	$filename = basename( $new_path );
	$file_dirname = dirname( $new_path );

	// Save the content to a temporary file as _wp_handle_upload needs it on disk
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require( ABSPATH . '/wp-admin/includes/file.php' );
	}
	$tmp_filename = wp_tempnam( $filename );
	file_put_contents( $tmp_filename, $file_content ); // @codingStandardsIgnoreLine

	// To make sure our upload makes to the correct subdir (not a date-based one) we need to filter `upload_dir` and enforce the path and url.
	// We use an anonymous function so we can do this on-the-fly more easily.
	$upload_dir_callback = function( $upload_dir ) use ( $file_dirname ) {
		$upload_dir['subdir'] = $file_dirname;
		$upload_dir['path'] = $upload_dir['basedir'] . $file_dirname;
		$upload_dir['url'] = $upload_dir['baseurl'] . $file_dirname;
		return $upload_dir;
	};
	add_filter( 'upload_dir', $upload_dir_callback, 9999 );

	// Prepare the upload.
	$filedata = [
		'tmp_name' => $tmp_filename,
		'type' => wp_check_filetype( $filename ),
		'name' => $filename,
		'url' => $upload_dir['baseurl'] . $path,
	];

	// Upload the upload.
	$sideload = _wp_handle_upload( $filedata, [
		'test_form' => false, // skip the `action` check
	], null, 'beaver-builder' );

	// Cleanup
	@unlink( $tmp_filename ); //@codingStandardsIgnoreLine
	@unlink( $filename ); //@codingStandardsIgnoreLine

	// We don't need the upload_dir filter any more
	remove_filter( 'upload_dir', $upload_dir_callback, 9999 );
	existent_disallow_bb_css_and_js_uploads();

	// Something went wrong :(
	if ( isset( $sideload['error'] ) ) {
		// TODO: handle error
		return;
	}

	// TODO: handle success
}

function existent_allow_bb_css_and_js_uploads() {
	add_filter( 'upload_mimes', 'existent_add_css_and_js_mime_types' );
}

function existent_disallow_bb_css_and_js_uploads() {
	remove_filter( 'upload_mimes', 'existent_add_css_and_js_mime_types' );
}

function existent_add_css_and_js_mime_types( $mimes ) {
	$mimes['css'] = 'text/css';
	$mimes['js'] = 'application/x-javascript';
	return $mimes;
}
