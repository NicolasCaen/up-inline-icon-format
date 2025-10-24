<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function() {
	register_rest_route( 'up-iif/v1', '/fonts', array(
		array(
			'methods' => WP_REST_Server::READABLE,
			'permission_callback' => function(){ return current_user_can('edit_posts'); },
			'callback' => function( WP_REST_Request $req ){
				$fonts = up_iif_list_svg_fonts();
				return rest_ensure_response( array( 'fonts' => $fonts ) );
			}
		)
	) );

	register_rest_route( 'up-iif/v1', '/glyphs', array(
		array(
			'methods' => WP_REST_Server::READABLE,
			'permission_callback' => function(){ return current_user_can('edit_posts'); },
			'args' => array(
				'font' => array(
					'required' => true,
					'type' => 'string',
				),
			),
			'callback' => function( WP_REST_Request $req ){
				$font = sanitize_file_name( (string) $req->get_param('font') );
				if ( '' === $font ) {
					return new WP_Error('up_iif_no_font', 'Paramètre font manquant', array('status'=>400));
				}
				$dir = up_iif_get_fonts_dir();
				$path = realpath( trailingslashit( $dir ) . $font );
				if ( ! $path || ! file_exists( $path ) ) {
					return new WP_Error('up_iif_font_missing', 'Fichier font introuvable', array('status'=>404));
				}
				if ( strpos( $path, realpath( $dir ) ) !== 0 ) {
					return new WP_Error('up_iif_forbidden', 'Accès refusé', array('status'=>403));
				}

				$xml = file_get_contents( $path );
				if ( ! $xml ) { return rest_ensure_response( array( 'glyphs' => array() ) ); }
				libxml_use_internal_errors( true );
				$doc = simplexml_load_string( $xml );
				if ( ! $doc ) { return rest_ensure_response( array( 'glyphs' => array() ) ); }
				$doc->registerXPathNamespace('svg', 'http://www.w3.org/2000/svg');
				$glyphs = array();
				foreach( $doc->xpath('//svg:glyph') as $g ) {
					$attrs = $g->attributes();
					$d = isset($attrs['d']) ? (string)$attrs['d'] : '';
					$unicode = isset($attrs['unicode']) ? (string)$attrs['unicode'] : '';
					$name = isset($attrs['glyph-name']) ? (string)$attrs['glyph-name'] : '';
					if ( $unicode === '' ) { continue; }
					$codepoint = strtoupper( dechex( uniord( $unicode ) ) );
					$glyphs[] = array(
						'name' => $name !== '' ? $name : 'glyph_' . $codepoint,
						'unicode' => $unicode,
						'code' => 'U+' . $codepoint,
					);
				}
				return rest_ensure_response( array( 'glyphs' => $glyphs ) );
				
			}
		)
	) );
} );

if ( ! function_exists('uniord') ) {
	function uniord( $c ) {
		$h = ord($c[0]);
		if ($h <= 0x7F) return $h;
		if ($h < 0xC2) return null;
		if ($h <= 0xDF) return ($h & 0x1F) << 6 | (ord($c[1]) & 0x3F);
		if ($h <= 0xEF) return ($h & 0x0F) << 12 | (ord($c[1]) & 0x3F) << 6 | (ord($c[2]) & 0x3F);
		if ($h <= 0xF4) return ($h & 0x07) << 18 | (ord($c[1]) & 0x3F) << 12 | (ord($c[2]) & 0x3F) << 6 | (ord($c[3]) & 0x3F);
		return null;
	}
}
