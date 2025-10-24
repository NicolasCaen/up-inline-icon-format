<?php
/**
 * Plugin Name: UP Inline Icon Format
 * Description: Ajoute un format RichText pour insérer des icônes inline à partir d'une police SVG (liste des glyphes + insertion au caret).
 * Author: UP
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function up_iif_find_icon_css_packs() {
    $packs = array();
    $icons_dir = trailingslashit( get_stylesheet_directory() ) . 'assets/fonts/icons';
    $fonts_root = trailingslashit( get_stylesheet_directory() ) . 'assets/fonts';
    if ( is_dir( $icons_dir ) ) {
        $files = glob( $icons_dir . '/*.css' );
        $files2 = glob( $icons_dir . '/**/*.css' );
        if ( is_array($files2) ) {
            $files = array_unique(array_merge( is_array($files)?$files:array(), $files2 ));
        }
        if ( $files ) {
            foreach ( $files as $path ) {
                $packs[] = array(
                    'name' => basename( $path ),
                    'path' => $path,
                    'url'  => trailingslashit( get_stylesheet_directory_uri() ) . ltrim( str_replace( trailingslashit( get_stylesheet_directory() ), '', $path ), '/' ),
                );
            }
        }
    }
    // Also scan assets/fonts/*/*.css (CSS next to font files)
    if ( is_dir( $fonts_root ) ) {
        $font_css = glob( $fonts_root . '/*/*.css' );
        if ( $font_css ) {
            foreach ( $font_css as $path ) {
                $packs[] = array(
                    'name' => basename( $path ),
                    'path' => $path,
                    'url'  => trailingslashit( get_stylesheet_directory_uri() ) . ltrim( str_replace( trailingslashit( get_stylesheet_directory() ), '', $path ), '/' ),
                );
            }
        }
    }
    // Legacy single file locations
    $candidates = array(
        trailingslashit( get_stylesheet_directory() ) . 'assets/fonts/glyphter.css',
    );
    foreach ( $candidates as $path ) {
        if ( file_exists( $path ) ) {
            $packs[] = array(
                'name' => basename( $path ),
                'path' => $path,
                'url'  => trailingslashit( get_stylesheet_directory_uri() ) . ltrim( str_replace( trailingslashit( get_stylesheet_directory() ), '', $path ), '/' ),
            );
        }
    }
    return $packs;
}

function up_iif_parse_icon_css( $css_path ) {
    $result = array(
        'family' => '',
        'icons'  => array(),
    );
    $css = @file_get_contents( $css_path );
    if ( false === $css ) return $result;
    // Extract first @font-face font-family
    if ( preg_match( '/@font-face\s*\{[^}]*font-family\s*:\s*([\"\'])([^\"\']+)\1/mi', $css, $mff ) ) {
        $result['family'] = trim( $mff[2] );
    }
    // Match .class:before/.class::before { content:'\\XXXX' } and also plain .class { content:'\\XXXX' }
    $icons = array();
    if ( preg_match_all( '/\.([A-Za-z0-9_-]+)\s*::?before\s*\{[^}]*content\s*:\s*(["\'])\\\\([0-9A-Fa-f]{1,6})\2[^}]*\}/i', $css, $m1, PREG_SET_ORDER ) ) {
        foreach ( $m1 as $match ) {
            $icons[$match[1]] = strtoupper( $match[3] );
        }
    }
    if ( preg_match_all( '/\.([A-Za-z0-9_-]+)\s*\{[^}]*content\s*:\s*(["\'])\\\\([0-9A-Fa-f]{1,6})\2[^}]*\}/i', $css, $m2, PREG_SET_ORDER ) ) {
        foreach ( $m2 as $match ) {
            $cls = $match[1];
            // Avoid overriding :before-defined entries
            if ( ! isset( $icons[$cls] ) ) {
                $icons[$cls] = strtoupper( $match[3] );
            }
        }
    }
    foreach ( $icons as $cls => $code ) {
        $result['icons'][] = array(
            'class' => $cls,
            'code'  => $code,
        );
    }
    return $result;
}

function up_iif_list_theme_fonts() {
    $fonts = array();
    // Try wp_get_global_settings if available
    if ( function_exists( 'wp_get_global_settings' ) ) {
        $settings = wp_get_global_settings();
        if ( isset( $settings['typography']['fontFamilies'] ) && is_array( $settings['typography']['fontFamilies'] ) ) {
            foreach ( $settings['typography']['fontFamilies'] as $group ) {
                if ( ! is_array( $group ) ) continue;
                foreach ( $group as $f ) {
                    if ( isset( $f['name'], $f['fontFamily'] ) ) {
                        $fonts[] = array(
                            'name' => $f['name'],
                            'family' => $f['fontFamily'],
                        );
                    }
                }
            }
        }
    }
    // Fallback: parse theme.json
    if ( empty( $fonts ) ) {
        $theme_json = trailingslashit( get_stylesheet_directory() ) . 'theme.json';
        if ( file_exists( $theme_json ) ) {
            $raw = file_get_contents( $theme_json );
            $data = json_decode( $raw, true );
            if ( isset( $data['settings']['typography']['fontFamilies'] ) && is_array( $data['settings']['typography']['fontFamilies'] ) ) {
                foreach ( $data['settings']['typography']['fontFamilies'] as $group ) {
                    if ( ! is_array( $group ) ) continue;
                    foreach ( $group as $f ) {
                        if ( isset( $f['name'], $f['fontFamily'] ) ) {
                            $fonts[] = array(
                                'name' => $f['name'],
                                'family' => $f['fontFamily'],
                            );
                        }
                    }
                }
            }
        }
    }
    return $fonts;
}

define( 'UP_IIF_VERSION', '1.0.0' );
define( 'UP_IIF_PLUGIN_FILE', __FILE__ );
define( 'UP_IIF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UP_IIF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function up_iif_get_fonts_dir() {
	// Par défaut: assets/icons/icons_output dans le thème enfant
	return trailingslashit( get_stylesheet_directory() ) . 'assets/icons/icons_output';
}

function up_iif_list_svg_fonts() {
	$dir = up_iif_get_fonts_dir();
	$fonts = array();
	if ( file_exists( $dir ) ) {
		$files = glob( trailingslashit( $dir ) . '*.svg' );
		if ( $files ) {
			foreach ( $files as $file ) {
				$fonts[] = array(
					'file' => basename( $file ),
					'name' => pathinfo( $file, PATHINFO_FILENAME ),
				);
			}
		}
	}
	return $fonts;
}

require_once UP_IIF_PLUGIN_DIR . 'includes/rest-inline.php';

add_action( 'enqueue_block_editor_assets', function() {
	$build_js = UP_IIF_PLUGIN_DIR . 'build/index.js';
	$asset_php = UP_IIF_PLUGIN_DIR . 'build/index.asset.php';
	$build_css = UP_IIF_PLUGIN_DIR . 'build/style-index.css';
	if ( file_exists( $build_js ) && file_exists( $asset_php ) ) {
		$asset = include $asset_php;
		wp_enqueue_script(
			'up-iif-format',
			UP_IIF_PLUGIN_URL . 'build/index.js',
			isset($asset['dependencies']) ? $asset['dependencies'] : array(),
			isset($asset['version']) ? $asset['version'] : UP_IIF_VERSION,
			true
		);
		// Enqueue compiled CSS if present (WordPress scripts emits style-index.css)
		if ( file_exists( $build_css ) ) {
			wp_enqueue_style( 'up-iif-editor', UP_IIF_PLUGIN_URL . 'build/style-index.css', array( 'wp-edit-blocks' ), isset($asset['version']) ? $asset['version'] : UP_IIF_VERSION );
		}
	} else {
		wp_enqueue_script(
			'up-iif-format',
			UP_IIF_PLUGIN_URL . 'assets/format.js',
			array( 'wp-rich-text', 'wp-editor', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-compose', 'wp-data', 'wp-api-fetch', 'wp-i18n', 'wp-hooks' ),
			UP_IIF_VERSION,
			true
		);
		wp_enqueue_style( 'up-iif-editor', UP_IIF_PLUGIN_URL . 'assets/format.css', array( 'wp-edit-blocks' ), UP_IIF_VERSION );
	}

	    // Discover icon CSS packs and parse icons
    $icon_packs_meta = up_iif_find_icon_css_packs();
    $icon_packs = array();
    foreach ( $icon_packs_meta as $pack ) {
        $parsed = up_iif_parse_icon_css( $pack['path'] );
        if ( ! empty( $parsed['icons'] ) ) {
            $icon_packs[] = array(
                'name' => $pack['name'],
                'url'  => $pack['url'],
                'icons'=> $parsed['icons'],
                'family' => $parsed['family'],
            );
        }
    }

    // Enqueue packs CSS for preview in editor
    foreach ( $icon_packs as $i => $pack ) {
        wp_enqueue_style( 'up-iif-pack-' . $i, $pack['url'], array(), UP_IIF_VERSION );
    }

    wp_localize_script( 'up-iif-format', 'UP_IIF', array(
        'rest' => array(
            'namespace' => 'up-iif/v1',
            'root' => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ),
        'fonts' => up_iif_list_svg_fonts(),
        'themeFonts' => up_iif_list_theme_fonts(),
        'iconCssPacks' => $icon_packs,
    ) );

	// Generate @font-face for available SVG fonts for editor
	$fonts = up_iif_list_svg_fonts();
	if ( ! empty( $fonts ) ) {
		$base_uri = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/icons/icons_output/';
		$css = '';
		foreach ( $fonts as $f ) {
			$name = $f['name'];
			$file = $f['file'];
			$src = esc_url_raw( $base_uri . $file );
			$css .= "@font-face{font-family:'{$name}';src:url('{$src}') format('svg');font-weight:normal;font-style:normal;font-display:swap;}\n";
			// Map data-font to font-family
			$css .= ".up-inline-icon[data-font=\"{$name}\"]{font-family:'{$name}';}\n";
		}
		// Also map theme fonts so spans pick correct family
		$theme_fonts = up_iif_list_theme_fonts();
		foreach ( $theme_fonts as $tf ) {
			$css .= ".up-inline-icon[data-font=\"" . esc_attr( $tf['family'] ) . "\"]{font-family:" . $tf['family'] . ";}\n";
		}
		wp_add_inline_style( 'up-iif-editor', $css );
	}
} );

// Admin: Upload ZIP to install icon font into theme assets
add_action( 'admin_menu', function(){
    add_options_page( 'Icônes inline', 'Icônes inline', 'manage_options', 'up-iif', 'up_iif_admin_page' );
} );

function up_iif_admin_page(){
    if ( ! current_user_can('manage_options') ) return;
    $notice = '';
    if ( isset($_POST['up_iif_action']) && $_POST['up_iif_action'] === 'upload_zip' && check_admin_referer('up_iif_upload_zip') ) {
        $notice = up_iif_handle_zip_upload();
    }
    echo '<div class="wrap"><h1>Icônes inline – Import de police</h1>';
    if ( $notice ) echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('up_iif_upload_zip');
    echo '<input type="hidden" name="up_iif_action" value="upload_zip" />';
    echo '<p><input type="file" name="up_iif_zip" accept=".zip" required /></p>';
    echo '<p><label>Nom de la famille (facultatif) <input type="text" name="up_iif_family" /></label></p>';
    submit_button('Importer la police (ZIP)');
    echo '</form></div>';
}

function up_iif_handle_zip_upload(){
    if ( empty($_FILES['up_iif_zip']['name']) ) return 'Aucun fichier ZIP fourni.';
    if ( ! class_exists('ZipArchive') ) return 'ZipArchive non disponible sur ce serveur.';
    $uploaded = wp_handle_upload( $_FILES['up_iif_zip'], array('test_form'=>false) );
    if ( isset($uploaded['error']) ) return 'Erreur upload: ' . $uploaded['error'];

    $zip_path = $uploaded['file'];
    $zip = new ZipArchive();
    if ( $zip->open( $zip_path ) !== true ) return 'Impossible d’ouvrir le ZIP.';

    $tmp = wp_tempnam();
    @unlink($tmp);
    if ( ! wp_mkdir_p( $tmp ) ) return 'Impossible de créer le dossier temporaire.';
    $zip->extractTo( $tmp );
    $zip->close();

    $theme_dir = trailingslashit( get_stylesheet_directory() );
    $fonts_root = $theme_dir . 'assets/fonts';
    $icons_root = $theme_dir . 'assets/fonts/icons';
    // Determine family folder name from ZIP name
    $zip_base = sanitize_title( pathinfo( $zip_path, PATHINFO_FILENAME ) );
    $family = $zip_base;
    // allow override from POST
    if ( isset($_POST['up_iif_family']) && $_POST['up_iif_family'] !== '' ) {
        $family = sanitize_title( $_POST['up_iif_family'] );
    }
    // normalize: lowercase, dashes, no spaces
    $family = preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $family ) );
    $family = trim( $family, '-' );

    $fonts_dir = $fonts_root . '/' . $family;
    $icons_dir = $icons_root . '/' . $family;
    wp_mkdir_p( $fonts_dir );
    wp_mkdir_p( $icons_dir );

    // Gather files
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
    $font_files = array();
    $css_files  = array();
    foreach ( $rii as $file ) {
        /** @var SplFileInfo $file */
        if ( $file->isDir() ) continue;
        $ext = strtolower( $file->getExtension() );
        $path = $file->getPathname();
        if ( in_array( $ext, array('eot','woff2','woff','ttf','otf','svg') ) ) $font_files[] = $path;
        if ( 'css' === $ext ) $css_files[] = $path;
    }

    if ( empty($css_files) ) return 'Le ZIP ne contient pas de fichier CSS d’icônes.';

    // Copy and rename font files to assets/fonts/<family>/<family>.<ext>
    $copied_fonts = array();
    $exts_seen = array();
    foreach ( $font_files as $src ) {
        $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
        $dest = $fonts_dir . '/' . $family . '.' . $ext;
        // Avoid overwriting different variants; prefer first come
        if ( isset($exts_seen[$ext]) ) continue;
        copy( $src, $dest );
        $copied_fonts[] = $dest;
        $exts_seen[$ext] = true;
    }

    // Use the first CSS file; move to icons and rewrite URLs
    $css_src = $css_files[0];
    $css_contents = file_get_contents( $css_src );
    // Force @font-face family to normalized $family
    if ( preg_match( '/@font-face\s*\{[^}]*font-family\s*:\s*([\"\'])([^\"\']+)\1/mi', $css_contents ) ) {
        $css_contents = preg_replace( '/(@font-face\s*\{[^}]*font-family\s*:\s*)([\"\'])([^\"\']+)([\"\'])/mi', '$1"' . addcslashes($family,'"') . '"', $css_contents, 1 );
    } else {
        // Prepend a minimal @font-face if missing
        $prepend = "@font-face{font-family:'" . $family . "';src:";
        $srcs = array();
        foreach ( array('woff2','woff','ttf','eot','svg') as $fmt ) {
            $p = $fonts_dir . '/' . $family . '.' . $fmt;
            if ( file_exists( $p ) ) {
                $fmt_label = ($fmt === 'ttf') ? 'truetype' : $fmt;
                $srcs[] = "url('../" . $family . "/" . $family . "." . $fmt . "') format('" . $fmt_label . "')";
            }
        }
        if ( ! empty( $srcs ) ) {
            $prepend .= implode(', ', $srcs) . ";font-weight:normal;font-style:normal;font-display:swap;}\n";
            $css_contents = $prepend . $css_contents;
        }
    }
    // Replace any font-family usage in helper rules to normalized
    $css_contents = preg_replace( '/font-family\s*:\s*([\"\'])([^\"\']+)([\"\'])/mi', "font-family: '" . $family . "'", $css_contents );
    // Rewrite url(...) to standardized ./<family>.<ext> (CSS in same folder as fonts)
    $css_contents = preg_replace_callback( '/url\(([^)]+)\)/i', function($m) use ($family){
        $raw = trim($m[1], "'\" ");
        $ext = strtolower( pathinfo( parse_url( $raw, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( $ext ) {
            return "url('./" . $family . "." . $ext . "')";
        }
        return $m[0];
    }, $css_contents );
    // Write CSS to fonts/<family>/<family>.css (same folder as fonts)
    $css_dest = $fonts_dir . '/' . $family . '.css';
    $bytes = @file_put_contents( $css_dest, $css_contents );
    if ( $bytes === false ) {
        return 'Erreur: impossible d\'écrire le CSS dans ' . str_replace( trailingslashit( ABSPATH ), '/', $css_dest );
    }
    // Keep original CSS for référence dans le même dossier
    @copy( $css_src, $fonts_dir . '/original.css' );

    // Update theme.json fontFamilies -> add family and fontFace srcs
    $theme_json_path = $theme_dir . 'theme.json';
    if ( file_exists( $theme_json_path ) ) {
        $raw = file_get_contents( $theme_json_path );
        $data = json_decode( $raw, true );
        if ( is_array($data) ) {
            $fonts_array =& $data['settings']['typography']['fontFamilies'];
            if ( ! is_array( $fonts_array ) ) { $fonts_array = array(); }

            // Build src list present
            $srcs = array();
            $formats = array('woff2','woff','ttf','eot','svg');
            foreach ( $formats as $fmt ) {
                $p = 'file:./assets/fonts/' . $family . '/' . $family . '.' . $fmt;
                $fs = $fonts_dir . '/' . $family . '.' . $fmt;
                if ( file_exists( $fs ) ) $srcs[] = $p;
            }

            $entry = array(
                'fontFamily' => '"' . $family . '", sans-serif',
                'name' => $family,
                'slug' => sanitize_title( $family ),
                'fontFace' => array(
                    array(
                        'fontFamily' => $family,
                        'src' => $srcs,
                        'fontDisplay' => 'swap',
                    ),
                ),
            );
            // Avoid duplicates by slug
            $found = false;
            foreach ( $fonts_array as &$fitem ) {
                if ( isset($fitem['slug']) && $fitem['slug'] === $entry['slug'] ) { $fitem = $entry; $found = true; break; }
            }
            if ( ! $found ) $fonts_array[] = $entry;

            file_put_contents( $theme_json_path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
        }
    }

    // Cleanup temp
    up_iif_rrmdir( $tmp );
    @unlink( $zip_path );

    return 'Police importée: ' . $family;
}

function up_iif_rrmdir( $dir ){
    if ( ! is_dir( $dir ) ) return;
    $it = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
    $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $files as $file ) {
        if ( $file->isDir() ) rmdir( $file->getRealPath() ); else unlink( $file->getRealPath() );
    }
    rmdir( $dir );
}

// Frontend styles and @font-face
add_action( 'wp_enqueue_scripts', function() {
    $build_css_url = UP_IIF_PLUGIN_URL . 'build/style-index.css';
    $build_css_path = UP_IIF_PLUGIN_DIR . 'build/style-index.css';
    if ( file_exists( $build_css_path ) ) {
        wp_enqueue_style( 'up-iif-frontend', $build_css_url, array(), UP_IIF_VERSION );
    } else {
        wp_enqueue_style( 'up-iif-frontend', UP_IIF_PLUGIN_URL . 'assets/format.css', array(), UP_IIF_VERSION );
    }
    $fonts = up_iif_list_svg_fonts();
    if ( ! empty( $fonts ) ) {
        $base_uri = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/icons/icons_output/';
        $css = '';
        foreach ( $fonts as $f ) {
            $name = $f['name'];
            $file = $f['file'];
            $src = esc_url_raw( $base_uri . $file );
            $css .= "@font-face{font-family:'{$name}';src:url('{$src}') format('svg');font-weight:normal;font-style:normal;font-display:swap;}\n";
            $css .= ".up-inline-icon[data-font=\"{$name}\"]{font-family:'{$name}';}\n";
        }
        $theme_fonts = up_iif_list_theme_fonts();
        foreach ( $theme_fonts as $tf ) {
            $css .= ".up-inline-icon[data-font=\"" . esc_attr( $tf['family'] ) . "\"]{font-family:" . $tf['family'] . ";}\n";
        }
        wp_add_inline_style( 'up-iif-frontend', $css );
    }
} );
