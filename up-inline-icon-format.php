<?php
/**
 * Plugin Name: UP Inline Icon Format
 * Description: Ajoute un format RichText pour insérer des icônes inline à partir d'une police SVG (liste des glyphes + insertion au caret).
 * Author: UP
 * Version: 1.1.0
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

define( 'UP_IIF_VERSION', '1.1.0' );
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
    echo '<p><label>Nom de la police (affiché) <input type="text" name="up_iif_family" /></label></p>';
    echo '<p><label>Slug de la police (facultatif) <input type="text" name="up_iif_slug" /></label></p>';
    echo '<p><label><input type="checkbox" name="up_iif_is_icon" value="1" checked /> Police d\'icônes</label></p>';
    echo '<p><label><input type="checkbox" name="up_iif_is_variable" value="1" /> Police variable</label></p>';
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
    // Posted options
    $is_icon = isset($_POST['up_iif_is_icon']) && $_POST['up_iif_is_icon'] == '1';
    $is_variable = isset($_POST['up_iif_is_variable']) && $_POST['up_iif_is_variable'] == '1';
    $display_name = '';
    if ( isset($_POST['up_iif_family']) && trim($_POST['up_iif_family']) !== '' ) {
        $display_name = trim( wp_unslash( $_POST['up_iif_family'] ) );
    }
    $posted_slug = '';
    if ( isset($_POST['up_iif_slug']) && trim($_POST['up_iif_slug']) !== '' ) {
        $posted_slug = sanitize_title( wp_unslash( $_POST['up_iif_slug'] ) );
    }
    // Determine base names from ZIP if not provided
    $zip_base = sanitize_title( pathinfo( $zip_path, PATHINFO_FILENAME ) );
    $family_slug = $posted_slug ? $posted_slug : ( $display_name ? sanitize_title( $display_name ) : $zip_base );
    // normalize: lowercase, dashes, no spaces
    $family_slug = preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $family_slug ) );
    $family_slug = trim( $family_slug, '-' );
    if ( $display_name === '' ) { $display_name = $family_slug; }

    $fonts_dir = $fonts_root . '/' . $family_slug;
    $icons_dir = $icons_root . '/' . $family_slug;
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

    // Build maps for regular fonts (by detected weight)
    $weight_word_map = array(
        'thin' => 100,
        'extralight' => 200,
        'ultralight' => 200,
        'light' => 300,
        'normal' => 400,
        'regular' => 400,
        'book' => 400,
        'medium' => 500,
        'semibold' => 600,
        'demibold' => 600,
        'bold' => 700,
        'extrabold' => 800,
        'ultrabold' => 800,
        'black' => 900,
        'heavy' => 900,
    );
    // If icon mode: require CSS; else proceed without CSS
    if ( $is_icon && empty($css_files) ) return 'Le ZIP ne contient pas de fichier CSS d’icônes.';

    // Prepare data structures
    $copied_fonts = array();
    if ( $is_icon ) {
        // Icon font: copy one of each ext to standardized <family>.<ext>
        $exts_seen = array();
        foreach ( $font_files as $src ) {
            $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
            $dest = $fonts_dir . '/' . $family_slug . '.' . $ext;
            if ( isset($exts_seen[$ext]) ) continue;
            @copy( $src, $dest );
            $copied_fonts[] = $dest;
            $exts_seen[$ext] = true;
        }
    } elseif ( $is_variable ) {
        // Variable font: group by style (normal/italic), copy to <slug>-variable[-italic].ext
        $by_style = array('normal' => array(), 'italic' => array());
        foreach ( $font_files as $src ) {
            $basename = strtolower( basename( $src ) );
            $style = ( strpos($basename, 'italic') !== false || strpos($basename, 'oblique') !== false || preg_match('/\bita\b/', $basename) ) ? 'italic' : 'normal';
            $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
            if ( ! isset( $by_style[$style][$ext] ) ) {
                $by_style[$style][$ext] = $src;
            }
        }
        foreach ( $by_style as $style => $ext_map ) {
            if ( empty($ext_map) ) continue;
            foreach ( $ext_map as $ext => $src ) {
                $suffix = ($style === 'italic') ? '-variable-italic' : '-variable';
                $dest = $fonts_dir . '/' . $family_slug . $suffix . '.' . $ext;
                @copy( $src, $dest );
                $copied_fonts[] = $dest;
            }
        }
    } else {
        // Regular font: detect weights in filenames and group per weight
        $by_weight = array(); // weight => style => ext => src
        foreach ( $font_files as $src ) {
            $basename = strtolower( basename( $src ) );
            $weight = null;
            $style = ( strpos($basename, 'italic') !== false || strpos($basename, 'oblique') !== false || preg_match('/\bita\b/', $basename) ) ? 'italic' : 'normal';
            // numeric 100..900
            if ( preg_match( '/\b(100|200|300|400|500|600|700|800|900)\b/', $basename, $m ) ) {
                $weight = intval( $m[1] );
            } else {
                // words
                foreach ( $weight_word_map as $word => $val ) {
                    if ( strpos( $basename, $word ) !== false ) { $weight = $val; break; }
                }
            }
            if ( ! $weight ) { $weight = 400; }
            $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
            if ( ! isset( $by_weight[ $weight ] ) ) { $by_weight[ $weight ] = array(); }
            if ( ! isset( $by_weight[ $weight ][ $style ] ) ) { $by_weight[ $weight ][ $style ] = array(); }
            // Prefer best formats; do not overwrite once set per style
            if ( ! isset( $by_weight[ $weight ][ $style ][ $ext ] ) ) {
                $by_weight[ $weight ][ $style ][ $ext ] = $src;
            }
        }
        // Copy/rename to <family>-<weight>.<ext>
        foreach ( $by_weight as $w => $style_map ) {
            foreach ( $style_map as $style => $ext_map ) {
                foreach ( $ext_map as $ext => $src ) {
                    $suffix = ($style === 'italic') ? ('-' . $w . '-italic') : ('-' . $w);
                    $dest = $fonts_dir . '/' . $family_slug . $suffix . '.' . $ext;
                    @copy( $src, $dest );
                    $copied_fonts[] = $dest;
                }
            }
        }
    }

    if ( $is_icon ) {
        // Use the first CSS file; move to fonts folder and rewrite URLs
        $css_src = $css_files[0];
        $css_contents = file_get_contents( $css_src );
        // Force @font-face family to provided display name
        if ( preg_match( '/@font-face\s*\{[^}]*font-family\s*:\s*([\"\'])([^\"\']+)\1/mi', $css_contents ) ) {
            $css_contents = preg_replace( '/(@font-face\s*\{[^}]*font-family\s*:\s*)([\"\'])([^\"\']+)([\"\'])/mi', '$1"' . addcslashes($display_name,'"') . '"', $css_contents, 1 );
        } else {
            // Prepend a minimal @font-face if missing
            $prepend = "@font-face{font-family:'" . $display_name . "';src:";
            $srcs = array();
            foreach ( array('woff2','woff','ttf','eot','svg') as $fmt ) {
                $p = $fonts_dir . '/' . $family_slug . '.' . $fmt;
                if ( file_exists( $p ) ) {
                    $fmt_label = ($fmt === 'ttf') ? 'truetype' : $fmt;
                    $srcs[] = "url('./" . $family_slug . "." . $fmt . "') format('" . $fmt_label . "')";
                }
            }
            if ( ! empty( $srcs ) ) {
                $prepend .= implode(', ', $srcs) . ";font-weight:normal;font-style:normal;font-display:swap;}\n";
                $css_contents = $prepend . $css_contents;
            }
        }
        // Replace any font-family usage in helper rules to normalized display name
        $css_contents = preg_replace( '/font-family\s*:\s*([\"\'])([^\"\']+)([\"\'])/mi', "font-family: '" . $display_name . "'", $css_contents );
        // Rewrite url(...) to standardized ./<family-slug>.<ext>
        $css_contents = preg_replace_callback( '/url\(([^)]+)\)/i', function($m) use ($family_slug){
            $raw = trim($m[1], "'\" ");
            $ext = strtolower( pathinfo( parse_url( $raw, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
            if ( $ext ) {
                return "url('./" . $family_slug . "." . $ext . "')";
            }
            return $m[0];
        }, $css_contents );
        // Write CSS to fonts/<family>/<family>.css (same folder as fonts)
        $css_dest = $fonts_dir . '/' . $family_slug . '.css';
        $bytes = @file_put_contents( $css_dest, $css_contents );
        if ( $bytes === false ) {
            return 'Erreur: impossible d\'écrire le CSS dans ' . str_replace( trailingslashit( ABSPATH ), '/', $css_dest );
        }
        // Keep original CSS for référence
        @copy( $css_src, $fonts_dir . '/original.css' );
    }

    // Update theme.json fontFamilies -> add family and fontFace srcs
    $theme_json_path = $theme_dir . 'theme.json';
    if ( file_exists( $theme_json_path ) ) {
        $raw = file_get_contents( $theme_json_path );
        $data = json_decode( $raw, true );
        if ( is_array($data) ) {
            $fonts_array =& $data['settings']['typography']['fontFamilies'];
            if ( ! is_array( $fonts_array ) ) { $fonts_array = array(); }
            $entry = array();
            if ( $is_icon ) {
                // Icon font: single @font-face without weight
                $srcs = array();
                $formats = array('woff2','woff','ttf','eot','svg');
                foreach ( $formats as $fmt ) {
                    $p = 'file:./assets/fonts/' . $family_slug . '/' . $family_slug . '.' . $fmt;
                    $fs = $fonts_dir . '/' . $family_slug . '.' . $fmt;
                    if ( file_exists( $fs ) ) $srcs[] = $p;
                }
                $entry = array(
                    'fontFamily' => '"' . $display_name . '", sans-serif',
                    'name' => $display_name,
                    'slug' => $family_slug,
                    'fontFace' => array(
                        array(
                            'fontFamily' => $display_name,
                            'src' => $srcs,
                            'fontDisplay' => 'swap',
                        ),
                    ),
                );
            } elseif ( $is_variable ) {
                // Variable font: one face per style with range 100 900
                $faces = array();
                $formats = array('woff2','woff','ttf','otf');
                foreach ( array('normal','italic') as $style ) {
                    $srcs = array();
                    foreach ( $formats as $fmt ) {
                        $suffix = ($style === 'italic') ? '-variable-italic' : '-variable';
                        $fs = $fonts_dir . '/' . $family_slug . $suffix . '.' . $fmt;
                        if ( file_exists( $fs ) ) {
                            $srcs[] = 'file:./assets/fonts/' . $family_slug . '/' . $family_slug . $suffix . '.' . $fmt;
                        }
                    }
                    if ( ! empty( $srcs ) ) {
                        $faces[] = array(
                            'fontFamily' => $display_name,
                            'fontStyle' => $style,
                            'fontWeight' => '100 900',
                            'src' => $srcs,
                            'fontDisplay' => 'swap',
                        );
                    }
                }
                $entry = array(
                    'fontFamily' => '"' . $display_name . '", sans-serif',
                    'name' => $display_name,
                    'slug' => $family_slug,
                    'fontFace' => $faces,
                );
            } else {
                // Regular fonts: add one face per detected weight
                $faces = array();
                $formats = array('woff2','woff','ttf','otf','eot','svg');
                // Detect available weights from copied files
                $weights_found = array();
                $dir_glob = glob( $fonts_dir . '/' . $family_slug . '-*.*' );
                if ( $dir_glob ) {
                    foreach ( $dir_glob as $p ) {
                        if ( preg_match( '/-' . preg_quote($family_slug, '/') . '-(\d{3})\.[a-z0-9]+$/i', '-' . $family_slug . '-' . basename($p) ) ) {
                            // not used; fallback below
                        }
                        if ( preg_match( '/-(\d{3})(-italic)?\.[a-z0-9]+$/i', basename($p), $m ) ) {
                            $weights_found[ intval($m[1]) ] = true;
                        }
                    }
                }
                if ( empty($weights_found) ) { $weights_found[400] = true; }
                foreach ( array_keys($weights_found) as $w ) {
                    // normal style
                    $srcs_n = array();
                    foreach ( $formats as $fmt ) {
                        $fs = $fonts_dir . '/' . $family_slug . '-' . $w . '.' . $fmt;
                        if ( file_exists( $fs ) ) {
                            $srcs_n[] = 'file:./assets/fonts/' . $family_slug . '/' . $family_slug . '-' . $w . '.' . $fmt;
                        }
                    }
                    if ( ! empty( $srcs_n ) ) {
                        $faces[] = array(
                            'fontFamily' => $display_name,
                            'fontWeight' => intval($w),
                            'fontStyle' => 'normal',
                            'src' => $srcs_n,
                            'fontDisplay' => 'swap',
                        );
                    }
                    // italic style
                    $srcs_i = array();
                    foreach ( $formats as $fmt ) {
                        $fs = $fonts_dir . '/' . $family_slug . '-' . $w . '-italic.' . $fmt;
                        if ( file_exists( $fs ) ) {
                            $srcs_i[] = 'file:./assets/fonts/' . $family_slug . '/' . $family_slug . '-' . $w . '-italic.' . $fmt;
                        }
                    }
                    if ( ! empty( $srcs_i ) ) {
                        $faces[] = array(
                            'fontFamily' => $display_name,
                            'fontWeight' => intval($w),
                            'fontStyle' => 'italic',
                            'src' => $srcs_i,
                            'fontDisplay' => 'swap',
                        );
                    }
                }
                $entry = array(
                    'fontFamily' => '"' . $display_name . '", sans-serif',
                    'name' => $display_name,
                    'slug' => $family_slug,
                    'fontFace' => $faces,
                );
            }
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

    return 'Police importée: ' . $display_name;
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
