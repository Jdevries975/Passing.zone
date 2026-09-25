<?php
/**
 * GeneratePress child theme functions and definitions.
 *
 * Add your custom PHP in this file. 
 * Only edit this file if you have direct access to it on your server (to fix errors if they happen).
 */

function generatepress_child_enqueue_scripts() {
	if ( is_rtl() ) {
		wp_enqueue_style( 'generatepress-rtl', trailingslashit( get_template_directory_uri() ) . 'rtl.css' );
	}
}
add_action( 'wp_enqueue_scripts', 'generatepress_child_enqueue_scripts', 100 );
/* Completely disable Gravatar */
add_filter( 'option_show_avatars', '__return_false' );
/* 2025-02-05 jdev Prevent Beaver Builder from deleting or modifying html...*/
add_filter( 'fl_inline_editing_enabled', '__return_false' );

/* 2025-02-05 jdev Add mime type .svg and .vcard
   2026-09-25 jdev SVG only for members (author and above, "publish_posts") and sanitized on every
   upload path: media library (wp_handle_upload/sideload prefilter) and the Gravity Forms pattern
   image (pz_gf_set_featured_and_acf_image() creates the attachment directly from the GF file, which
   bypasses the WordPress upload filters). Type check by file extension, not by the browser's
   Content-Type (spoofable). Directly opened SVGs additionally get a script-src 'none' CSP (.htaccess). */
function jdev_ext_mimes( $mimes ) {
	if ( current_user_can( 'publish_posts' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	$mimes['vcf'] = 'text/vcard';
	return $mimes;
}
add_filter( 'upload_mimes', 'jdev_ext_mimes' );

function jdev_is_svg_filename( $name ) {
	return 'svg' === strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
}

/* Sanitizes the SVG at $path in place. Returns '' on success, otherwise an error message
   (the file is then left untouched and must not be used). */
function jdev_sanitize_svg_file( $path ) {
	// Bound resource usage before we even try to parse the file.
	if ( ! $path || ! is_file( $path ) || filesize( $path ) > 2 * 1024 * 1024 ) {
		return __( 'SVG file is missing or too large.' );
	}

	$svg = file_get_contents( $path );
	if ( false === $svg || '' === trim( $svg ) ) {
		return __( 'Could not read the uploaded SVG file.' );
	}

	// Reject polyglot files: anything that could be reinterpreted as server
	// side script has no business in an SVG.
	if ( preg_match( '/<\?php|<%/i', $svg ) ) {
		return __( 'This SVG file contains disallowed markup.' );
	}

	// Illustrator and other tools emit a standard <!DOCTYPE svg PUBLIC ...>.
	// We don't need it and it's the classic XXE/entity-expansion vector, so
	// strip any DOCTYPE (including an internal subset) outright.
	$svg = preg_replace( '/<!DOCTYPE\b[^>\[]*(\[[^\]]*\])?[^>]*>/is', '', $svg );

	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	// LIBXML_NONET blocks network fetches; entity substitution stays disabled (default).
	$loaded = $doc->loadXML( $svg, LIBXML_NONET );
	libxml_clear_errors();

	if ( ! $loaded || ! $doc->documentElement ) {
		return __( 'This file is not a valid SVG.' );
	}
	$root = $doc->documentElement;
	if ( 'svg' !== strtolower( $root->localName ) || 'http://www.w3.org/2000/svg' !== $root->namespaceURI ) {
		return __( 'This file is not a valid SVG.' );
	}

	$xpath = new DOMXPath( $doc );

	// Drop elements that can execute or load code/markup: script, foreignObject
	// (embedded HTML) and SMIL animation elements (can rewrite href/on* at runtime).
	$dangerous_elements = '//*[local-name()="script"]'
		. ' | //*[local-name()="foreignObject"]'
		. ' | //*[local-name()="animate"]'
		. ' | //*[local-name()="animateMotion"]'
		. ' | //*[local-name()="animateTransform"]'
		. ' | //*[local-name()="animateColor"]'
		. ' | //*[local-name()="set"]'
		. ' | //*[local-name()="link"]';
	foreach ( $xpath->query( $dangerous_elements ) as $node ) {
		$node->parentNode->removeChild( $node );
	}

	// <style> can carry @import/url()/expression() based exfiltration.
	foreach ( $xpath->query( '//*[local-name()="style"]' ) as $style ) {
		if ( preg_match( '/@import|url\s*\(|expression\s*\(|javascript:/i', $style->textContent ) ) {
			$style->parentNode->removeChild( $style );
		}
	}

	foreach ( $xpath->query( '//@*' ) as $attr ) {
		$local = strtolower( $attr->localName );
		$value = preg_replace( '/[\x00-\x1F\s]+/', '', (string) $attr->nodeValue );

		// Any on* event handler (onload, onclick, ...).
		if ( 0 === strpos( $local, 'on' ) ) {
			$attr->ownerElement->removeAttributeNode( $attr );
			continue;
		}
		// xml:base can retarget how relative URLs resolve.
		if ( 'base' === $local && 'xml' === $attr->prefix ) {
			$attr->ownerElement->removeAttributeNode( $attr );
			continue;
		}
		if ( 'href' === $local ) {
			if ( preg_match( '/^(javascript|vbscript):/i', $value ) ) {
				$attr->ownerElement->removeAttributeNode( $attr );
				continue;
			}
			// Only inline raster-image data URIs; no nested SVG or data:text/html.
			if ( 0 === stripos( $value, 'data:' ) && ! preg_match( '/^data:image\/(png|jpe?g|gif|webp);base64,/i', $value ) ) {
				$attr->ownerElement->removeAttributeNode( $attr );
			}
		}
	}

	$sanitized = $doc->saveXML( $doc->documentElement );
	if ( false === $sanitized || '' === trim( $sanitized ) || false === file_put_contents( $path, $sanitized ) ) {
		return __( 'SVG sanitization failed.' );
	}
	return '';
}

function jdev_sanitize_svg_upload( $file ) {
	if ( ! jdev_is_svg_filename( $file['name'] ?? '' ) ) {
		return $file;
	}
	// Defense in depth: upload_mimes already gates this, but another plugin
	// could re-add the svg mime for other roles.
	if ( ! current_user_can( 'publish_posts' ) ) {
		$file['error'] = __( 'SVG uploads are restricted to members.' );
		return $file;
	}
	$error = jdev_sanitize_svg_file( $file['tmp_name'] ?? '' );
	if ( '' !== $error ) {
		$file['error'] = $error;
	}
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'jdev_sanitize_svg_upload' );
add_filter( 'wp_handle_sideload_prefilter', 'jdev_sanitize_svg_upload' );
/* END of mime type svg block*/

/*2025-02-05 jdev Prevent Beaver Builder from fetching Google Fonts from Google */
add_filter( 'fl_builder_google_fonts_pre_enqueue', function( $fonts ) {
return array();
} );

/* 2026-09-25 jdev Ajax Search Pro prints a preconnect to fonts.gstatic.com and @font-face rules
   (Raleway, Open Sans) pointing to Google into every page, even with all its fonts set to "inherit".
   Browsers then fetched the fonts from Google without consent (and the CSP blocks them). Strip both
   from the HTML; the search uses the site font via style.css. Runs inside WP Rocket's buffer, so the
   cached pages are clean as well -> clear the WP Rocket cache after deploying. */
function jdev_strip_google_fonts( $html ) {
	if ( false === strpos( $html, 'fonts.g' ) ) {
		return $html;
	}
	$out = preg_replace( '#<link[^>]+href=["\']https://fonts\.(gstatic|googleapis)\.com[^>]*>#i', '', $html );
	$out = preg_replace( '#@font-face\s*\{[^{}]*fonts\.gstatic\.com[^{}]*\}#i', '', (string) $out );
	// preg_replace returns null on a PCRE error -> never send an empty page.
	return ( null === $out || '' === $out ) ? $html : $out;
}
add_action( 'template_redirect', function () {
	ob_start( 'jdev_strip_google_fonts' );
}, 1 );

/* 2026-07-19 jdev Load FontAwesome 7.3.1, forces Beaver Builder to use the self-hosted version */
/* 2026-07-30 jdev Do NOT replace the plugins' own Font Awesome inside the Beaver Builder editor/UI:
   BB/PowerPack internally use FA5 classes like "far fa-copy", which don't exist in FA7 Free (no
   Regular style, renamed icons) -> field icons (Move/Duplicate/Delete) would otherwise stay invisible. */
function additional_scripts_before() {
if ( isset( $_GET['fl_builder'] ) || isset( $_GET['fl_builder_ui'] ) ) {
	return;
}
wp_deregister_style('font-awesome');
wp_dequeue_style('font-awesome');
wp_deregister_style('font-awesome-5');
wp_dequeue_style('font-awesome-5');
wp_deregister_style('font-awesome-6');
wp_dequeue_style('font-awesome-6');
wp_deregister_style('font-awesome-7');
wp_dequeue_style('font-awesome-7');
wp_enqueue_style('font-awesome-7', get_stylesheet_directory_uri() . '/fonts/fontawesome-free-7.3.1-web/css/all.min.css');
}
add_action('wp_enqueue_scripts', 'additional_scripts_before',1000);
/* remove all comments functionality*/
add_action('admin_init', function () {
    // Redirect any user trying to access comments page
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_redirect(admin_url());
        exit;
    }
    // Remove comments meta box from dashboard
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    // Remove comments admin menu
    remove_menu_page('edit-comments.php');
});

/* 2026-08-12 jdev Hide Yoast SEO dashboard widgets. Must run on
   wp_dashboard_setup (not admin_init), since Yoast only registers its
   widgets there - before that, the IDs don't exist yet to remove. */
add_action('wp_dashboard_setup', function () {
    remove_meta_box('wpseo-dashboard-overview', 'dashboard', 'normal');
    remove_meta_box('wpseo-wincher-dashboard-overview', 'dashboard', 'normal');
});

/* 2026-08-12 jdev Custom dashboard widget "Styleguide": colors from style.css
   (:root) and font download, so the current brand values are readily
   available in wp-admin. Colors are hardcoded here - if changed in
   style.css (:root), please update here as well. */
add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('pz_styleguide', 'Styleguide', 'pz_render_styleguide_widget');
});

function pz_render_styleguide_widget() {
    $colors = array(
        'Black'         => '#160E29',
        'Dark'          => '#33225D',
        'Medium'        => '#472779',
        'Medium light'  => '#552F8C',
        'Light'         => '#9682B4',
        'Superlight'    => '#DDD6EA',
        'Lightgrey'     => '#AAA3BC',
        'Tuerkis'       => '#00B3FF',
        'Orange'        => '#F9A500',
        'Mint'          => '#CBF7D4',
    );
    $font_dir = get_stylesheet_directory_uri() . '/' . rawurlencode('Font ObelixZone');
    ?>
    <style>
        @font-face {
            font-family: 'Obelix Zone Preview';
            src: url('<?php echo esc_url($font_dir . '/ObelixZone.woff2'); ?>') format('woff2'),
                 url('<?php echo esc_url($font_dir . '/ObelixZone.woff'); ?>') format('woff');
            font-display: swap;
        }
    </style>
    <div class="pz-styleguide-widget">
        <h4 style="margin-top:0;">Colors</h4>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <?php foreach ($colors as $name => $hex) :
                list($r, $g, $b) = sscanf($hex, '#%02x%02x%02x');
            ?>
                <div style="width:130px; font-size:12px; line-height:1.5;">
                    <div style="height:36px; border-radius:4px; border:1px solid #ccc; background:<?php echo esc_attr($hex); ?>;"></div>
                    <strong><?php echo esc_html($name); ?></strong><br>
                    <?php echo esc_html($hex); ?><br>
                    rgb(<?php echo esc_html("$r, $g, $b"); ?>)
                </div>
            <?php endforeach; ?>
        </div>
        <p style="font-family:'ObelixZone', sans-serif; font-size:20px; margin:20px 0 4px 0; color:#160E29;">Display font: ObelixZone</p>
        <p style="margin-top:0;">Free font "Obelix" with added custom characters (Jenny &amp; Juli).<br>
            <a href="https://passing.zone/wp-content/uploads/ObelixZone.zip" download>Download ObelixZone.zip</a></p>
			 <p style="font-family:'Quicksand', sans-serif; font-size:20px; margin:4px 0;">Text font: Quicksand (Google font)</p> 
            </div>
    <?php
}

/* 2026-08-12 jdev Load our own admin CSS for wp-admin styling. */
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('pz-admin', get_stylesheet_directory_uri() . '/css/admin.css', array(), filemtime(get_stylesheet_directory() . '/css/admin.css'));
});

/* 2026-08-12 jdev Also load the same admin CSS on wp-login.php, so the
   "Log In" button (.wp-core-ui .button.button-primary, see admin.css) also
   appears in pz-purple instead of the WP default blue there. wp-login.php
   also has the "wp-core-ui" body class, so the rules apply unchanged. */
add_action('login_enqueue_scripts', function () {
    wp_enqueue_style('pz-admin', get_stylesheet_directory_uri() . '/css/admin.css', array(), filemtime(get_stylesheet_directory() . '/css/admin.css'));

    /* 2026-08-13 jdev Logo URL via PHP instead of a relative url(../images/...)
       in admin.css - the path there looked correct, but the logo stayed
       invisible (likely a path-resolution/caching issue with the SVG).
       An absolute URL via get_stylesheet_directory_uri() is robust
       against that, and the PNG (instead of SVG) removes a possible
       source of error with the SVG viewBox's mm units. */
    $logo_url = get_stylesheet_directory_uri() . '/images/passing_zone_logo_321D5B_1000x1000.png';
    wp_add_inline_style(
        'pz-admin',
        'body.login #login h1 a { background-image: url(' . esc_url($logo_url) . ') !important; }'
    );
});

/* 2026-08-13 jdev wp-login.php: the logo at the top links to wordpress.org
   by default and shows "Powered by WordPress" as its title attribute -
   link to the homepage instead and show the site name. The logo image
   itself (Passing.zone wordmark) comes from css/admin.css
   (#login h1 a background-image). */
add_filter('login_headerurl', function () {
    return home_url('/');
});
add_filter('login_headertext', function () {
    return get_bloginfo('name');
});

/* 2026-08-12 jdev Custom dashboard widget "Mailing list". */
add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('pz_mailing_list', 'Mailing list', 'pz_render_mailing_list_widget');
});

function pz_render_mailing_list_widget() {
    ?>
    <p>To get on the mailing list, please send an email to <a href="mailto:pass-out-join@jonglaria.org">pass-out-join@jonglaria.org</a>.</p>
    <?php
}

/* 2026-08-12 jdev The dashboard's Activity widget ("Recently Published" /
   "Scheduled") only shows the "post" post type and max. 5 entries by
   default. Include patterns as well and raise the limit to 15 entries. */
add_filter('dashboard_recent_posts_query_args', function ($query_args) {
    $query_args['post_type'] = array('post', 'pattern');
    $query_args['posts_per_page'] = 15;
    return $query_args;
});

// Remove comments from post/page support
add_action('init', function () {
    remove_post_type_support('post', 'comments');
    remove_post_type_support('page', 'comments');
});

// Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove admin bar "Comments" link
add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
});
function digwp_disable_gutenberg($is_enabled, $post_type) {
	
	if ($post_type === 'pattern') return false; // change book to your post type
	
	return $is_enabled;
	
}
add_filter('use_block_editor_for_post_type', 'digwp_disable_gutenberg', 10, 2);
function my_init() {
	if (!is_admin()) {
		wp_enqueue_script('jquery');
	}
}
add_action('init', 'my_init');

/* 2025-10-14 Enqueue a custom JS file with jQuery as a dependency */
/* 2026-07-19 jdev Handle renamed: collided with GeneratePress' own 'custom-js' handle, which meant jenny.js never loaded */
function jdev_custom_js_file() {
 	wp_enqueue_script('jdev-jenny-js', get_stylesheet_directory_uri() . '/js/jenny.js', array('jquery'), '1.0', false);
}
add_action('wp_enqueue_scripts', 'jdev_custom_js_file');
/* jdev 2025-10-18 custom portable Hook for the active filter display Shortcode: [portable_hook hook_name="active_filters"]*/
add_shortcode('portable_hook', function($atts){
	ob_start();
        $atts = shortcode_atts( array(
            'hook_name' => 'active_filters'
        ), $atts, 'portable_hook' );
		do_action($atts['hook_name']);
	return ob_get_clean();
});
/* Default sort for pattern admin list: newest first */
add_action( 'pre_get_posts', 'pz_pattern_admin_default_order' );
function pz_pattern_admin_default_order( $query ) {
    global $pagenow;
    if ( ! is_admin() ) return;
    if ( $pagenow !== 'edit.php' ) return;
    if ( ( $_GET['post_type'] ?? '' ) !== 'pattern' ) return;
    if ( ! empty( $_GET['orderby'] ) ) return;
    $query->set( 'orderby', 'date' );
    $query->set( 'order', 'DESC' );
}

/* Author Archive: Main query includes patterns and Posts*/
add_action( 'pre_get_posts', 'add_cpt_to_author_archive' );
function add_cpt_to_author_archive( $query ) {
    if ( ! $query->is_main_query() || is_admin() || ! is_author() ) {
        return;
    }
    $query->set( 'post_type', array( 'post', 'pattern' ) );
}
/* Crop all preview images to a good size*/
add_action( 'after_setup_theme', 'jdev_crop' );
function jdev_crop() {
    add_image_size( 'juli-size', 750, 210, true );  // true = center-crop
}
/* 2026-03-03 jdev Remove the "new content" menu item from the admin bar on mobile. Otherwise the profile picture overlaps the main menu...*/
add_action( 'admin_bar_menu', 'remove_new_posts_from_admin_bar', 999 );
function remove_new_posts_from_admin_bar( $wp_admin_bar ) {
    // Remove "New Post"
    $wp_admin_bar->remove_node( 'new-post' );
    $wp_admin_bar->remove_node( 'new-content' );
	
}
/* 2026-03-04 jdev Video schema markup for Pattern
add_action('wp_head', 'add_video_schema_patterns', 99);

 Video SEO
function add_video_schema_patterns() {
    if (is_singular('pattern')) {
        // Get video URL, thumbnail, title etc. from ACF fields (adjust as needed)
        $video_url = get_field('video_url'); // Example field
        $thumbnail = get_field('video_thumbnail');
        $title = get_the_title();
        ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "VideoObject",
            "name": "<?php echo esc_js($title); ?>",
            "thumbnailUrl": "<?php echo esc_url($thumbnail); ?>",
            "contentUrl": "<?php echo esc_url($video_url); ?>",
            "uploadDate": "<?php echo get_the_date('c'); ?>"
        }
        </script>
        <?php
    }
}
add_filter('wpseo_schema_video', 'acf_thumbnail_for_video_schema');
function acf_thumbnail_for_video_schema($data) {
    if (is_singular('pattern')) {
        $thumb = get_field('pattern_image');
        if ($thumb && isset($thumb['url'])) {
            $data['thumbnailUrl'] = $thumb['url'];
            $data['thumbnail']['url'] = $thumb['url']; // For Yoast
            $data['thumbnail']['width'] = $thumb['width'];
            $data['thumbnail']['height'] = $thumb['height'];
        }
    }
    return $data;
}*/
add_filter('wpseo_next_wpseo_video_thumbnail', 'acf_pattern_image_thumbnail');
add_filter('wpseo_schema_video', 'acf_fix_video_thumbnail_schema');
function acf_pattern_image_thumbnail($thumbnail) {
    if (is_singular('patterns')) {
        $thumb = get_field('pattern_image');
        if ($thumb && is_array($thumb) && isset($thumb['url'])) {
            return $thumb['url'];
        } elseif ($thumb) { // In case it's just a URL
            return $thumb;
        }
    }
    return $thumbnail;
}
function acf_fix_video_thumbnail_schema($data) {
    if (is_singular('patterns')) {
        $thumb = get_field('pattern_image');
        if ($thumb) {
            $thumb_url = is_array($thumb) ? $thumb['url'] : $thumb;
            if (isset($data['thumbnailUrl'])) $data['thumbnailUrl'] = $thumb_url;
            if (isset($data['thumbnail']['@type']) && isset($data['thumbnail']['url'])) $data['thumbnail']['url'] = $thumb_url;
            if (isset($data['thumbnail']['width'])) $data['thumbnail']['width'] = $thumb['width'] ?? 1280;
            if (isset($data['thumbnail']['height'])) $data['thumbnail']['height'] = $thumb['height'] ?? 720;
        }
    }
    return $data;
}
/* 2026-04-11 jdev fix error with dashicons*/
add_action( 'wp_enqueue_scripts', function() {
    wp_register_style(
        'dashicons',
        includes_url( 'css/dashicons.min.css' ),
        [],
        false
    );
}, 1 ); // Priority 1 = runs very early

/* 2026-06-14 jdev Monkey stats shortcode for Beaver Themer author archives.
   Usage: [pz_monkey_stats] */
function pz_monkey_stats_shortcode(): string {
    $user = get_queried_object();

    if ( ! ( $user instanceof WP_User ) ) {
        return '';
    }

    $user_id = $user->ID;

    // ACF User fields store IDs either as a plain integer (single value) or as
    // a serialized PHP array of integers. Matching the serialized array via a
    // SQL LIKE on ";i:$user_id;" is unreliable: PHP's serialization format is
    // "key;value;key;value;...", and the array's own (sequential, small-integer)
    // keys can accidentally match a real user ID that was never actually
    // selected as a monkey. So we only use the meta_query to narrow down to
    // patterns that have the field at all, then decode the real stored value
    // and compare it in PHP.
    $patterns = new WP_Query( [
        'post_type'      => 'pattern',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'video_monkeys',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    if ( ! $patterns->have_posts() ) {
        return '';
    }

    $difficulty_counts = [];
    $posts_list        = [];

    while ( $patterns->have_posts() ) {
        $patterns->the_post();
        $id = get_the_ID();

        $monkey_ids = array_map( 'intval', (array) get_field( 'video_monkeys', $id, false ) );
        if ( ! in_array( $user_id, $monkey_ids, true ) ) {
            continue;
        }

        $diff_terms = get_the_terms( $id, 'pattern-difficulty' );
        $diff_term  = ( $diff_terms && ! is_wp_error( $diff_terms ) ) ? $diff_terms[0] : null;

        if ( $diff_term ) {
            $difficulty_counts[ $diff_term->slug ] = ( $difficulty_counts[ $diff_term->slug ] ?? 0 ) + 1;
        }

        $posts_list[] = [
            'title'     => get_the_title(),
            'url'       => get_permalink(),
            'diff_term' => $diff_term,
        ];
    }
    wp_reset_postdata();

    if ( empty( $posts_list ) ) {
        return '';
    }

    // Order difficulty counts by taxonomy term order (numeric slug prefix).
    $all_diff_terms = get_terms( [ 'taxonomy' => 'pattern-difficulty', 'hide_empty' => false, 'orderby' => 'name' ] );
    $ordered_counts = [];
    foreach ( (array) $all_diff_terms as $term ) {
        if ( isset( $difficulty_counts[ $term->slug ] ) ) {
            $ordered_counts[] = [
                'name'  => preg_replace( '/^\d+\s*/', '', $term->name ),
                'slug'  => $term->slug,
                'count' => $difficulty_counts[ $term->slug ],
            ];
        }
    }

    $total = count( $posts_list );

    ob_start(); ?>
    <div class="pz-monkey-stats">

        <p class="pz-monkey-stats__total">
            <strong><?= esc_html( $total ) ?></strong> pattern<?= $total !== 1 ? 's' : '' ?>
        </p>

        <?php if ( ! empty( $ordered_counts ) ) : ?>
        <ul class="pz-monkey-stats__difficulties">
            <?php foreach ( $ordered_counts as $d ) : ?>
            <li class="pz-monkey-stats__diff-item">
                <span class="pz-monkey-stats__diff-label"><?= esc_html( $d['name'] ) ?></span>
                <span class="pz-monkey-stats__diff-count"><?= esc_html( $d['count'] ) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <details class="pz-monkey-stats__accordion">
            <summary class="pz-monkey-stats__accordion-toggle">
                <h3 class="pz-monkey-stats__accordion-heading">All pattern videos with <?= esc_html( $user->display_name ) ?></h3>
            </summary>
            <ul class="pz-monkey-stats__list">
                <?php foreach ( $posts_list as $p ) : ?>
                <li class="pz-monkey-stats__item">
                    <a href="<?= esc_url( $p['url'] ) ?>"><?= esc_html( $p['title'] ) ?></a>
                    <?php if ( $p['diff_term'] ) :
                        $class = preg_replace( '/^\d+-/', '', sanitize_html_class( $p['diff_term']->slug ) );
                        $label = preg_replace( '/^\d+\s*/', '', $p['diff_term']->name );
                    ?>
                    <span class="stars pz-monkey-stats__stars"><i class="<?= esc_attr( $class ) ?>" title="<?= esc_attr( $label ) ?>"></i></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'pz_monkey_stats', 'pz_monkey_stats_shortcode' );

/* 2026-06-13 jdev Remove the white bar at the top in Firefox when not logged in */
add_action( 'wp_head', function() {
    if ( ! is_user_logged_in() ) {
        echo '<style>html,body{margin-top:0!important;padding-top:0!important}</style>';
    }
}, 99 );

/* 2026-07-07 jdev Content restriction for the "author" role and above.
   Usage: [members-only]protected content[/members-only]
   "publish_posts" is the capability that author/editor/administrator have,
   but subscriber/contributor don't - that's how "author and above" can be checked. */
function pz_members_only_shortcode( $atts, $content = null ): string {
    if ( current_user_can( 'publish_posts' ) ) {
        return do_shortcode( (string) $content );
    }

    $login    = esc_url( 'https://passing.zone/login/' );
    $register = esc_url( 'https://passing.zone/register/' );

    return '<p class="pz-members-only-notice">Sorry, this is for Passing.zone members, only. '
        . '<a href="' . $login . '">Login</a> or <a href="' . $register . '">become a member</a>. It&#8217;s free.</p>';
}
add_shortcode( 'members-only', 'pz_members_only_shortcode' );

/* 2026-07-22 jdev Login status button: Happy Juggler (logged in) opens a
   submenu (My Account / My Author Page / Upload a Pattern / Edit my Patterns / Log Out); (logged out) links
   directly to login. Usage: [pz_login_button] */
function pz_login_button_shortcode(): string {
    if ( is_user_logged_in() ) {
        $icon        = esc_url( 'https://passing.zone/wp-content/uploads/happy_juggler_dark-bg.svg' );
        $account_url = esc_url( home_url( '/my-account/' ) );
        $author_url  = esc_url( get_author_posts_url( get_current_user_id() ) );
        $edit_url    = esc_url( 'https://passing.zone/your-editable-patterns/' );
        $upload_url  = esc_url( 'https://passing.zone/upload-a-new-pattern/' );
        $logout_url  = esc_url( wp_logout_url( home_url( '/' ) ) );

        $html = '<div class="pz-login-button pz-login-button--menu">'
            . '<button type="button" class="pz-login-button__toggle" aria-haspopup="true" aria-expanded="false">'
            . '<img src="' . $icon . '" alt="Account menu" />'
            . '</button>'
            . '<ul class="pz-login-button__dropdown">'
            . '<li><a href="' . $account_url . '">My Account</a></li>'
            . '<li><a href="' . $author_url . '">My Author Page</a></li>'
            . '<li><a href="' . $upload_url . '">Upload a Pattern</a></li>'
            . '<li><a href="' . $edit_url . '">Edit My Patterns</a></li>'
            . '<li><a href="' . $logout_url . '">Log Out</a></li>'
            . '</ul>'
            . '</div>';

        static $script_printed = false;
        if ( ! $script_printed ) {
            $script_printed = true;
            $html .= '<script>(function(){'
                . 'document.addEventListener("click",function(e){'
                . 'var t=e.target.closest(".pz-login-button__toggle");'
                . 'document.querySelectorAll(".pz-login-button--menu.is-open").forEach(function(el){'
                . 'if(!t||el!==t.closest(".pz-login-button--menu")){el.classList.remove("is-open");'
                . 'var b=el.querySelector(".pz-login-button__toggle");if(b)b.setAttribute("aria-expanded","false");}'
                . '});'
                . 'if(t){var wrap=t.closest(".pz-login-button--menu");var open=wrap.classList.toggle("is-open");'
                . 't.setAttribute("aria-expanded",open?"true":"false");}'
                . '});'
                . 'document.addEventListener("keydown",function(e){'
                . 'if(e.key==="Escape"){document.querySelectorAll(".pz-login-button--menu.is-open").forEach(function(el){'
                . 'el.classList.remove("is-open");var b=el.querySelector(".pz-login-button__toggle");if(b)b.setAttribute("aria-expanded","false");});}'
                . '});'
                . '})();</script>';
        }
    } else {
        $url  = esc_url( 'https://passing.zone/login/' );
        $icon = esc_url( 'https://passing.zone/wp-content/uploads/sad_juggler_dark-bg_fixed.svg' );
        $html = '<div class="pz-login-button pz-login-button--menu">'
            . '<a href="' . $url . '" class="pz-login-button__toggle">'
            . '<img src="' . $icon . '" alt="Login" />'
            . '</a>'
            . '</div>';
    }

    return $html;
}
add_shortcode( 'pz_login_button', 'pz_login_button_shortcode' );

/* 2026-07-13 jdev Gravity Forms (Form 4) user-selection fields ("Monkeys" = field 6,
   "Pattern Author" = field 7): populate dynamically with all WP users
   (value = user ID, text = display name). All four hooks are needed
   so the choices are consistently present when rendering, validating, in
   the admin preview, and on submission. */
add_action( 'gform_pre_render_4', 'pz_gf_populate_user_choices' );
add_action( 'gform_pre_validation_4', 'pz_gf_populate_user_choices' );
add_action( 'gform_pre_submission_filter_4', 'pz_gf_populate_user_choices' );
add_action( 'gform_admin_pre_render_4', 'pz_gf_populate_user_choices' );
function pz_gf_populate_user_choices( $form ) {
    $user_select_field_ids = [ 6, 7 ];
    foreach ( $form['fields'] as &$field ) {
        if ( ! in_array( (int) $field->id, $user_select_field_ids, true ) ) {
            continue;
        }
        $users   = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
        $choices = [];
        foreach ( $users as $user ) {
            $choices[] = [ 'text' => $user->display_name, 'value' => $user->ID ];
        }
        $field->choices = $choices;
    }
    return $form;
}

/* 2026-07-13 jdev Gravity Forms (Form 4) "Pattern Difficulty" radio field
   (field 12): populate choices dynamically from the actual "pattern-difficulty"
   taxonomy, so the submitted value always matches an existing term exactly
   (otherwise, on a name mismatch, Advanced Post Creation silently creates a
   new/duplicate term). */
add_action( 'gform_pre_render_4', 'pz_gf_populate_difficulty_choices' );
add_action( 'gform_pre_validation_4', 'pz_gf_populate_difficulty_choices' );
add_action( 'gform_pre_submission_filter_4', 'pz_gf_populate_difficulty_choices' );
add_action( 'gform_admin_pre_render_4', 'pz_gf_populate_difficulty_choices' );
function pz_gf_populate_difficulty_choices( $form ) {
    foreach ( $form['fields'] as &$field ) {
        if ( (int) $field->id !== 12 ) {
            continue;
        }
        $terms   = get_terms( [ 'taxonomy' => 'pattern-difficulty', 'hide_empty' => false ] );
        $choices = [];
        foreach ( $terms as $term ) {
            $choices[] = [ 'text' => $term->name, 'value' => $term->name ];
        }
        $field->choices = $choices;
    }
    return $form;
}

/* 2026-07-13 jdev Gravity Forms (Form 4) "Number of Jugglers" field (field 13):
   populate choices dynamically from the actual "number-of-jugglers" taxonomy,
   for the same reason as field 12 (exact name match instead of accidentally
   creating new/duplicate terms). */
add_action( 'gform_pre_render_4', 'pz_gf_populate_jugglers_choices' );
add_action( 'gform_pre_validation_4', 'pz_gf_populate_jugglers_choices' );
add_action( 'gform_pre_submission_filter_4', 'pz_gf_populate_jugglers_choices' );
add_action( 'gform_admin_pre_render_4', 'pz_gf_populate_jugglers_choices' );
function pz_gf_populate_jugglers_choices( $form ) {
    foreach ( $form['fields'] as &$field ) {
        if ( (int) $field->id !== 13 ) {
            continue;
        }
        $terms   = get_terms( [ 'taxonomy' => 'number-of-jugglers', 'hide_empty' => false ] );
        $choices = [];
        foreach ( $terms as $term ) {
            $choices[] = [ 'text' => $term->name, 'value' => $term->name ];
        }
        $field->choices = $choices;
    }
    return $form;
}

/* 2026-07-13 jdev Gravity Forms (Form 4) "Pattern Type" multi select (field 14)
   and "Pattern Tags" multi select (field 15): populate choices dynamically
   from the actual "pattern-type" / "pattern-tag" taxonomies, for the same
   reason as field 12/13 (exact name match instead of accidentally creating
   new/duplicate terms). */
add_action( 'gform_pre_render_4', 'pz_gf_populate_type_and_tag_choices' );
add_action( 'gform_pre_validation_4', 'pz_gf_populate_type_and_tag_choices' );
add_action( 'gform_pre_submission_filter_4', 'pz_gf_populate_type_and_tag_choices' );
add_action( 'gform_admin_pre_render_4', 'pz_gf_populate_type_and_tag_choices' );
function pz_gf_populate_type_and_tag_choices( $form ) {
    $taxonomies_by_field_id = [
        14 => 'pattern-type',
        15 => 'pattern-tag',
    ];
    foreach ( $form['fields'] as &$field ) {
        if ( ! isset( $taxonomies_by_field_id[ (int) $field->id ] ) ) {
            continue;
        }
        $terms   = get_terms( [ 'taxonomy' => $taxonomies_by_field_id[ (int) $field->id ], 'hide_empty' => false ] );
        $choices = [];
        foreach ( $terms as $term ) {
            $choices[] = [ 'text' => $term->name, 'value' => $term->name ];
        }
        $field->choices = $choices;
    }
    return $form;
}

/* 2026-07-18 jdev The choices injected above via gform_*_pre_render_4 for
   fields 6, 7, 12, 13, 14, 15 only apply to contexts that actually fire
   these hooks (the form itself, validation, admin preview) or to the
   entries list via gform_entries_field_value. The single entry detail view
   (View Entry), however, renders selection fields directly from the field
   choices stored in the database, without firing these hooks - so raw
   IDs/values instead of names still show up there. That's why we
   additionally write the real choices permanently into the form
   definition itself, so Gravity Forms resolves them correctly everywhere
   on its own. Runs throttled (every 5 minutes) on every admin page load,
   so new users/terms get picked up promptly. */
add_action( 'admin_init', 'pz_gf_persist_dynamic_choices_form4' );
function pz_gf_persist_dynamic_choices_form4() {
    if ( ! class_exists( 'GFAPI' ) || get_transient( 'pz_gf_choices_synced_4' ) ) {
        return;
    }
    set_transient( 'pz_gf_choices_synced_4', 1, 5 * MINUTE_IN_SECONDS );

    $form = GFAPI::get_form( 4 );
    if ( ! $form ) {
        return;
    }

    $users        = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );
    $user_choices = [];
    foreach ( $users as $user ) {
        $user_choices[] = [ 'text' => $user->display_name, 'value' => $user->ID ];
    }

    $taxonomies_by_field_id = [
        12 => 'pattern-difficulty',
        13 => 'number-of-jugglers',
        14 => 'pattern-type',
        15 => 'pattern-tag',
    ];

    $dirty = false;
    foreach ( $form['fields'] as &$field ) {
        $field_id = (int) $field->id;

        if ( in_array( $field_id, [ 6, 7 ], true ) ) {
            $new_choices = $user_choices;
        } elseif ( isset( $taxonomies_by_field_id[ $field_id ] ) ) {
            $terms       = get_terms( [ 'taxonomy' => $taxonomies_by_field_id[ $field_id ], 'hide_empty' => false ] );
            $new_choices = [];
            foreach ( $terms as $term ) {
                $new_choices[] = [ 'text' => $term->name, 'value' => $term->name ];
            }
        } else {
            continue;
        }

        if ( wp_json_encode( $field->choices ) !== wp_json_encode( $new_choices ) ) {
            $field->choices = $new_choices;
            $dirty           = true;
        }
    }

    if ( $dirty ) {
        GFAPI::update_form( $form );
    }
}

/* 2026-07-19 jdev Advanced Post Creation fires two completely separate hooks
   for "post created" and "post updated", with different parameter
   signatures:
   - post_after_creation: ( $post_id, $feed, $entry, $form )
   - post_update_post:    ( $post object, $feed, $entry )  [no $form]
   Important: Advanced Post Creation processes its feed asynchronously (in
   the background, after the actual form request) - "gform_after_submission"
   fires too early for that, the post doesn't exist yet at that point
   (neither on create nor on update). So we exclusively use these two
   dedicated hooks of the add-on, which only fire once the post has
   actually been created/updated.
   This helper registers a callback of the form ( $post_id, $entry )
   uniformly for both cases, so post-processing logic (ACF fields,
   featured image, excerpt, render webhook) doesn't need to be duplicated
   separately for create and edit - otherwise such a function only works
   on create and silently does nothing on edit (exactly the bug pattern
   we ran into with the raw video webhook and now with the featured
   image). */
function pz_gf_on_pattern_post_saved( $callback ) {
    add_action( 'gform_advancedpostcreation_post_after_creation', function ( $post_id, $feed, $entry, $form ) use ( $callback ) {
        if ( $post_id && (int) rgar( $form, 'id' ) === 4 ) {
            call_user_func( $callback, $post_id, $entry );
        }
    }, 10, 4 );

    add_action( 'gform_advancedpostcreation_post_update_post', function ( $post, $feed, $entry ) use ( $callback ) {
        if ( (int) rgar( $entry, 'form_id' ) !== 4 ) {
            return;
        }
        // 2026-07-19 jdev: $post->ID is 0 in practice on the update hook
        // (likely an APC bug) - the entry itself reliably knows the real
        // post ID via entry['post_id'], which we use first.
        $post_id = (int) rgar( $entry, 'post_id' );
        if ( ! $post_id && $post ) {
            $post_id = (int) rgar( (array) $post, 'ID' );
        }
        if ( $post_id ) {
            call_user_func( $callback, $post_id, $entry );
        }
    }, 10, 3 );
}

/* 2026-07-13 jdev Correctly write the selected user IDs from field 6/7 into
   the ACF fields "video_monkeys" / "pattern_author" (via update_field
   instead of raw custom field mapping, so ACF recognizes and displays the
   fields correctly in the backend again). */
pz_gf_on_pattern_post_saved( 'pz_gf_save_user_selects_to_acf' );
function pz_gf_save_user_selects_to_acf( $post_id, $entry ) {
    $acf_fields_by_gf_field_id = [
        '6' => 'video_monkeys',
        '7' => 'pattern_author',
    ];
    foreach ( $acf_fields_by_gf_field_id as $gf_field_id => $acf_field_name ) {
        $raw = rgar( $entry, $gf_field_id ); // e.g. '["15","25"]' (Multi Select stores as a JSON array)
        $ids = array_filter( array_map( 'intval', (array) json_decode( $raw, true ) ) );
        update_field( $acf_field_name, $ids, $post_id );
    }
}

/* 2026-07-18 jdev Gravity Forms (Form 4) entries list in the backend: field 6
   ("Monkeys") and field 7 ("Pattern Author") store user IDs as a JSON array
   (see pz_gf_save_user_selects_to_acf above). The dynamic choices set via
   gform_*_pre_render_4 do resolve the IDs to names in the form itself and
   in the entry detail view, but NOT in the entries list - there, Gravity
   Forms renders the column directly from the raw entry value.
   Additionally, the built-in "Created By" column (= post_author, the
   submitting, logged-in user) also only shows the raw user ID instead of
   the name there. So we manually resolve both to display names here. */
add_filter( 'gform_entries_field_value', 'pz_gf_resolve_user_ids_in_entries_list', 10, 4 );
function pz_gf_resolve_user_ids_in_entries_list( $value, $form_id, $field_id, $entry ) {
    if ( (int) $form_id !== 4 ) {
        return $value;
    }

    if ( 'created_by' === $field_id ) {
        $user = get_userdata( (int) $value );
        return $user ? $user->display_name : $value;
    }

    if ( ! in_array( (int) $field_id, [ 6, 7 ], true ) ) {
        return $value;
    }
    $ids = json_decode( $value, true );
    if ( ! is_array( $ids ) ) {
        $ids = array_filter( [ $value ] );
    }
    $names = [];
    foreach ( $ids as $id ) {
        $user     = get_userdata( (int) $id );
        $names[] = $user ? $user->display_name : $id;
    }
    return implode( ', ', $names );
}

/* 2026-07-19 jdev Field 1 ("Pattern name") has the "title" field type (Post
   Title) - this field type doesn't offer a "Max Characters" option in the
   GF UI (unlike Single Line Text), so we limit it to 25 characters here
   via code. */
add_filter( 'gform_field_validation_4_1', 'pz_gf_limit_pattern_name_length', 10, 4 );
function pz_gf_limit_pattern_name_length( $result, $value, $form, $field ) {
    if ( mb_strlen( trim( (string) $value ) ) > 25 ) {
        $result['is_valid'] = false;
        $result['message']  = 'Pattern name must be no more than 25 characters long.';
    }
    return $result;
}

/* 2026-07-19 jdev On the "Edit Pattern" page (Edit Post Page of Form 4),
   individual fields (e.g. Raw Video/field 19, Audio File/field 20) are
   marked editable via the Post Editing Settings but remain required
   fields in Form 4 itself - so editing WITHOUT changing such a field
   throws a "field required" error from GF, even though the post already
   has a value. So we disable "Required" across the board for ALL fields,
   only on the edit page: leave empty = keep the existing value,
   fill in/upload = replace it. On the create page (or everywhere else)
   the fields stay required as configured in the form. */
add_filter( 'gform_pre_render_4', 'pz_gf_optional_on_edit_page' );
add_filter( 'gform_pre_validation_4', 'pz_gf_optional_on_edit_page' );
function pz_gf_optional_on_edit_page( $form ) {
    if ( ! is_page( 'edit-pattern' ) ) {
        return $form;
    }
    foreach ( $form['fields'] as &$field ) {
        $field->isRequired = false;
    }
    return $form;
}

/* 2026-08-15 jdev Users weren't aware on the edit page that they can simply
   replace the pattern image (field 5), video (field 19) and audio file
   (field 20) by uploading again (leave empty = keep the existing file,
   see pz_gf_optional_on_edit_page above). Can't be adjusted in the form
   itself (GF field settings), so it's shown here as a field description
   via code, only on the edit page. Replaces (rather than appending to)
   the native field description, so only a single line of hint text
   appears instead of two stacked texts. */
add_filter( 'gform_pre_render_4', 'pz_gf_add_replace_hint_on_edit_page' );
function pz_gf_add_replace_hint_on_edit_page( $form ) {
    if ( ! is_page( 'edit-pattern' ) ) {
        return $form;
    }
    // 2026-08-16 jdev: field 19's "want to replace the video" hint was
    // removed - the "Current file: ... [trash]" row plus the "+" picker
    // below it (see pz_gf_add_current_file_row_on_edit_page()) now make the
    // replace-or-keep behavior self-evident without it.
    $hints_by_field_id = [
        5  => 'Not happy with the automatically generated image? Upload your own image, here.',
        20 => 'We cannot change the audio, separately. If you are unhappy with your audio, please upload both new raw video and audio files. If you uploaded a new video file, please also upload the audio, again (or pick a different one).',
    ];
    foreach ( $form['fields'] as &$field ) {
        if ( ! isset( $hints_by_field_id[ (int) $field->id ] ) ) {
            continue;
        }
        $field->description          = $hints_by_field_id[ (int) $field->id ];
        $field->descriptionPlacement = 'above';
    }
    return $form;
}

/* 2026-08-15 jdev On the "Edit Pattern" page, users could upload a new file
   to replace the pattern image/video/audio (see the hints above), but had
   no way to remove one without replacing it.
   2026-08-16 jdev Redesigned from a plain "Delete current file" checkbox
   below the field into a "Current file: <link>" row with a trash button
   right-aligned after it - shown only when a current file actually exists
   for that field - to match the site-wide file-upload styling added above.
   Gravity Forms shows its OWN native "Current file: <a>" preview here for
   SOME fields (only when Advanced Post Creation happens to have populated
   a default value for that specific field - inconsistent across our three
   fields, e.g. it showed up for Raw Video but not for Pattern Image/Audio),
   so that native preview is stripped and replaced with our own, built
   uniformly from the actual post data (featured image / postmeta) for all
   three fields.
   The trash button reuses the same .pz-clear-file visuals as the "clear a
   pending selection" button elsewhere, but here it toggles a hidden
   checkbox (name "input_{id}_delete") instead of clearing a file input -
   there's nothing local to clear, the file is already saved server-side.
   That's a plain HTML checkbox (not a real Gravity Forms field) so it
   doesn't interfere with the actual upload field's own validation/value.
   Its value is picked up from $_POST in pz_gf_store_delete_flags() below
   and turned into entry meta - see the comment there for why entry meta is
   used instead of reading $entry directly. If both the checkbox and a new
   upload come in together, the new upload wins (see pz_gf_wants_file_deleted()
   call sites below). */
add_filter( 'gform_field_content_4', 'pz_gf_add_current_file_row_on_edit_page', 10, 5 );
function pz_gf_add_current_file_row_on_edit_page( $content, $field, $value, $lead_id, $form_id ) {
    if ( ! is_page( 'edit-pattern' ) ) {
        return $content;
    }
    $field_id = (int) $field->id;
    if ( ! in_array( $field_id, [ 5, 19, 20 ], true ) ) {
        return $content;
    }

    // Strip Gravity Forms'/Advanced Post Creation's own native "Current
    // file: ..." preview (only appeared inconsistently, see comment above,
    // and duplicated ours when it did) in favor of our own uniform version
    // built below. GF itself uses class="ginput_preview...", Advanced Post
    // Creation renders its own separate one as
    // <div id="gform_apc_current_file_{form}_{field}" class="gform_apc_current_file">.
    $content = preg_replace( '/<div class="ginput_preview[^"]*">.*?<\/div>/is', '', $content );
    $content = preg_replace( '/<div[^>]*\bclass="gform_apc_current_file"[^>]*>.*?<\/div>/is', '', $content );

    $post_id      = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
    $current_url  = $post_id ? pz_gf_current_file_url_for_field( $post_id, $field_id ) : '';
    if ( '' === $current_url ) {
        return $content;
    }

    $current_file_html = sprintf(
        '<div class="pz-file-upload pz-current-file"><span class="pz-file-upload__filename">Current file: <a href="%1$s" target="_blank" rel="noopener">%2$s</a></span><button type="button" class="pz-clear-file" aria-label="Delete current file"><i class="fa-solid fa-trash" aria-hidden="true"></i></button><input type="checkbox" name="input_%3$d_delete" value="1" class="pz-delete-file-checkbox" hidden></div>',
        esc_url( $current_url ),
        esc_html( basename( wp_parse_url( $current_url, PHP_URL_PATH ) ) ),
        $field_id
    );

    // Insert right before the new-upload picker built by
    // pz_gf_add_custom_file_upload_ui() (which runs on the generic
    // gform_field_content filter first, so its markup is already present
    // here) - layout ends up: hint text -> current file + trash -> "+ / No
    // file chosen" picker for a replacement.
    return str_replace( '<div class="pz-file-upload">', $current_file_html . '<div class="pz-file-upload">', $content );
}

/* Helper for pz_gf_add_current_file_row_on_edit_page() above: the currently
   saved file URL for a given field on the pattern post being edited, or ''
   if there is none. Mirrors where each field's value actually lives - see
   pz_gf_set_featured_and_acf_image() and pz_gf_send_render_webhook() below. */
function pz_gf_current_file_url_for_field( int $post_id, int $field_id ): string {
    switch ( $field_id ) {
        case 5:
            $attachment_id = get_post_thumbnail_id( $post_id );
            return $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '';
        case 19:
            return (string) get_post_meta( $post_id, '_pz_raw_video_url', true );
        case 20:
            return (string) get_post_meta( $post_id, '_pz_audio_file_url', true );
    }
    return '';
}

/* 2026-08-15 jdev Single (non-multi) file upload fields are plain <input
   type="file"> fields - someone might pick a file, then change their mind
   before submitting, but there's no built-in way to deselect it. Rolled out
   to every single-file "File Upload" and "Post Image" field, in every
   Gravity Forms form site-wide (2026-08-16 jdev) - originally this only
   covered Pattern Image/Audio File on the create-pattern page. Multi-file
   upload fields are deliberately skipped: GF's own enhanced uploader
   already gives those a proper preview/remove UI of its own.
   Replaces the native "Browse..." button/filename text with a custom
   square "+" trigger, a filename label, and a square orange trash-icon
   button to clear the selection. The native file input itself stays in the
   DOM (still what actually gets submitted) but is visually hidden - the
   "+" button just proxies a click to it (see pz_gf_clear_file_button_script()
   below). Nothing has reached the server yet at this point, unlike the
   edit-page delete checkboxes above, which remove an ALREADY SAVED file and
   do need server-side handling. */
add_filter( 'gform_field_content', 'pz_gf_add_custom_file_upload_ui', 10, 5 );
function pz_gf_add_custom_file_upload_ui( $content, $field, $value, $lead_id, $form_id ) {
    if ( ! in_array( $field->type, [ 'fileupload', 'post_image' ], true ) ) {
        return $content;
    }
    if ( ! empty( $field->multipleFiles ) ) {
        return $content;
    }
    // Wrap the native <input type="file"> in a .pz-file-upload container
    // together with the custom trigger/filename/clear controls, right where
    // the input itself sits (not appended to the end of $content, which
    // also includes the description text below the field).
    // Note: the native input already carries class="large" - rather than
    // injecting a second class attribute (which HTML parsers ignore, since
    // the first "class" wins on duplicates), the input is left untouched
    // and targeted structurally via ".pz-file-upload input[type=file]" in
    // CSS/JS instead.
    return preg_replace_callback(
        '/<input[^>]*type=["\']file["\'][^>]*>/i',
        function ( $m ) {
            return '<div class="pz-file-upload">'
                . '<button type="button" class="pz-file-upload__trigger" aria-label="Choose file"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>'
                . '<span class="pz-file-upload__filename">No file chosen</span>'
                . $m[0]
                . '<button type="button" class="pz-clear-file" aria-label="Remove selected file"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>'
                . '</div>';
        },
        $content,
        1
    );
}

/* Wires up the custom uploader markup from pz_gf_add_custom_file_upload_ui():
   the "+" trigger proxies a click to the (visually hidden) native file
   input, the filename label reflects the current selection, and the trash
   button clears it. Event-delegated on document (site-wide, since the
   fields it targets can now appear in any Gravity Forms form/page) since GF
   may re-render the form via AJAX. */
add_action( 'wp_footer', 'pz_gf_clear_file_button_script' );
function pz_gf_clear_file_button_script() {
    ?>
    <script>
    ( function () {
        document.addEventListener( 'click', function ( e ) {
            var trigger = e.target.closest( '.pz-file-upload__trigger' );
            if ( trigger ) {
                var input = trigger.closest( '.pz-file-upload' ).querySelector( 'input[type="file"]' );
                if ( input ) {
                    input.click();
                }
                return;
            }
            var clearBtn = e.target.closest( '.pz-clear-file' );
            if ( clearBtn ) {
                var currentFileWrap = clearBtn.closest( '.pz-current-file' );
                if ( currentFileWrap ) {
                    // Edit page: nothing local to clear here, the file is
                    // already saved server-side - toggle the hidden "delete
                    // this file on save" checkbox instead, and reflect that
                    // visually (see .is-marked-for-deletion in style.css).
                    var checkbox = currentFileWrap.querySelector( '.pz-delete-file-checkbox' );
                    if ( checkbox ) {
                        checkbox.checked = ! checkbox.checked;
                        currentFileWrap.classList.toggle( 'is-marked-for-deletion', checkbox.checked );
                    }
                    return;
                }
                var wrap  = clearBtn.closest( '.pz-file-upload' );
                var input = wrap && wrap.querySelector( 'input[type="file"]' );
                if ( input ) {
                    input.value = '';
                    input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                }
            }
        } );
        document.addEventListener( 'change', function ( e ) {
            if ( ! e.target.matches( 'input[type="file"]' ) ) {
                return;
            }
            var wrap = e.target.closest( '.pz-file-upload' );
            if ( ! wrap ) {
                return; // other file inputs on the page (e.g. Raw Video) aren't wrapped
            }
            var filename = wrap.querySelector( '.pz-file-upload__filename' );
            var clearBtn = wrap.querySelector( '.pz-clear-file' );
            var hasFile  = e.target.files && e.target.files.length;
            if ( filename ) {
                filename.textContent = hasFile ? e.target.files[0].name : 'No file chosen';
            }
            if ( clearBtn ) {
                // .pz-clear-file's CSS uses "display: none !important" (needed
                // to beat Gravity Forms' own !important button styling) - a
                // plain .style.display assignment here is not !important and
                // would silently lose to that, so the button would never show.
                clearBtn.style.setProperty( 'display', hasFile ? 'inline-flex' : 'none', 'important' );
            }
        } );
    } )();
    </script>
    <?php
}

/* 2026-08-15 jdev Captures the "delete current file" checkboxes added above
   as entry meta, keyed by entry ID. Runs on gform_entry_post_save, which
   fires synchronously right after the entry is created in the ORIGINAL
   request - unlike Advanced Post Creation's own post-creation/update hooks
   (see pz_gf_on_pattern_post_saved), which run later/asynchronously and by
   then no longer have access to $_POST. The stored flags are read back via
   pz_gf_wants_file_deleted() once post processing happens, then cleared. */
add_filter( 'gform_entry_post_save', 'pz_gf_store_delete_flags', 10, 2 );
function pz_gf_store_delete_flags( $entry, $form ) {
    if ( (int) rgar( $form, 'id' ) !== 4 ) {
        return $entry;
    }
    foreach ( [ 5, 19, 20 ] as $field_id ) {
        if ( ! empty( $_POST[ 'input_' . $field_id . '_delete' ] ) ) {
            gform_update_meta( $entry['id'], 'pz_delete_file_' . $field_id, 1 );
        }
    }
    return $entry;
}

/* Helper for pz_gf_set_featured_and_acf_image() and pz_gf_send_render_webhook()
   below: true if the user ticked "delete current file" for $field_id on the
   edit page AND didn't also upload a replacement (replacement always wins).
   Clears the flag once read, so it doesn't linger and affect later saves. */
function pz_gf_wants_file_deleted( $entry, $field_id ) {
    $entry_id = rgar( $entry, 'id' );
    if ( ! $entry_id || ! gform_get_meta( $entry_id, 'pz_delete_file_' . $field_id ) ) {
        return false;
    }
    gform_delete_meta( $entry_id, 'pz_delete_file_' . $field_id );
    return true;
}

/* 2026-07-13 jdev Use a single upload field (field 5, "Pattern Image") for
   both the native WordPress featured image and the ACF field
   "pattern_image" - so the form doesn't need two separate upload fields.
   Field 5 therefore stays UNMAPPED in the feed itself (neither under
   Featured Image nor under Custom Fields); this function handles both.
   The file uploaded by GF already sits locally in the gravity_forms
   folder; we create a real attachment in the media library from it
   (instead of loading it again via HTTP with media_sideload_image() -
   unnecessarily fragile because of the Cloudflare loopback issue).
   2026-07-19 jdev: Because field 5 is unmapped in the feed, it likely
   doesn't even show up as an option in the Post Editing Settings ("which
   fields are editable when editing") and therefore stays disabled on the
   edit page - so a new upload never reaches the entry. That's why we add
   field 5 to Advanced Post Creation's editable-fields list via code here,
   regardless of whether it's selectable in the UI. */
add_filter( 'gform_advancedpostcreation_editable_fields', function ( $editable_fields, $feed ) {
    if ( (int) rgar( $feed, 'form_id' ) !== 4 ) {
        return $editable_fields;
    }
    $editable_fields[] = 5;
    return array_unique( $editable_fields );
}, 10, 2 );

pz_gf_on_pattern_post_saved( 'pz_gf_set_featured_and_acf_image' );
function pz_gf_set_featured_and_acf_image( $post_id, $entry ) {
    $raw = rgar( $entry, '5' );
    if ( empty( $raw ) ) {
        // 2026-08-15 jdev: no new upload - if "Delete current image" was
        // checked on the edit page, clear the featured image/ACF field and
        // remove the attachment instead of just leaving the old one in place.
        if ( pz_gf_wants_file_deleted( $entry, 5 ) ) {
            $attachment_id = get_post_thumbnail_id( $post_id );
            delete_post_thumbnail( $post_id );
            update_field( 'pattern_image', false, $post_id );
            if ( $attachment_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }
        return;
    }
    $image_url = strtok( $raw, '|' ); // Post Image stores as "url|:|title|:|caption|:|description"
    if ( ! $image_url ) {
        return;
    }
    $upload_dir = wp_upload_dir();
    $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );
    if ( ! file_exists( $file_path ) ) {
        return;
    }
    // 2026-09-25 jdev: GF stores the upload itself, the WP upload filters never see it ->
    // sanitize SVGs here; on failure don't attach it and remove the file.
    if ( jdev_is_svg_filename( $file_path ) ) {
        $svg_error = jdev_sanitize_svg_file( $file_path );
        if ( '' !== $svg_error ) {
            error_log( 'pz_gf_set_featured_and_acf_image(): rejected SVG for post ' . $post_id . ' - ' . $svg_error );
            @unlink( $file_path );
            return;
        }
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_id = wp_insert_attachment(
        [
            'post_mime_type' => wp_check_filetype( $file_path )['type'],
            'post_title'     => sanitize_file_name( basename( $file_path ) ),
            'post_status'    => 'inherit',
        ],
        $file_path,
        $post_id
    );
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file_path ) );

    set_post_thumbnail( $post_id, $attachment_id );
    update_field( 'pattern_image', $attachment_id, $post_id );
}

/* 2026-07-13 jdev Write the excerpt field (field 18) into "post_excerpt".
   Advanced Post Creation has no post excerpt slot in the Content tab (only
   Title, Content, Featured Image, Custom Fields), and the custom field
   mappings only write postmeta, not actual wp_posts columns - so we use
   wp_update_post() here, analogous to the featured image workaround above. */
pz_gf_on_pattern_post_saved( 'pz_gf_save_excerpt' );
function pz_gf_save_excerpt( $post_id, $entry ) {
    $excerpt = rgar( $entry, '18' );
    if ( '' === $excerpt ) {
        return;
    }
    wp_update_post( [
        'ID'           => $post_id,
        'post_excerpt' => $excerpt,
    ] );
}

/* 2026-07-19 jdev After creating/updating the "pattern" post: store Raw
   Video (field 19) and Audio File (field 20) as protected (underscore-
   prefixed, not visible in the Custom Fields metabox) postmeta - no
   frontend display intended - and send them via webhook to the Hetzner
   render server together with title/location/monkeys/image.
   Deliberately runs via Advanced Post Creation's own hooks instead of the
   Gravity Forms Webhooks add-on: Webhooks fires synchronously right on
   submit, but Advanced Post Creation processes its feed asynchronously in
   the background - at that point, post_id doesn't exist on the entry yet.
   The Webhooks add-on feed for Form 4 is therefore disabled, otherwise
   two requests would go out. */
pz_gf_on_pattern_post_saved( 'pz_gf_send_render_webhook' );
function pz_gf_send_render_webhook( $post_id, $entry ) {
    $raw_video_url  = pz_gf_first_file_upload_url( rgar( $entry, '19' ) );
    $audio_file_url = pz_gf_first_file_upload_url( rgar( $entry, '20' ) );

    // When editing, field 19/20 is no longer required (see
    // pz_gf_optional_on_edit_page) - if no new upload comes in, the
    // previously saved value is kept instead of being overwritten with an
    // empty string.
    if ( '' !== $raw_video_url ) {
        $old_raw_video_url = get_post_meta( $post_id, '_pz_raw_video_url', true );
        // 2026-08-15 jdev: The raw video is only replaced via a URL
        // pointer when editing (no attachment like with the pattern
        // image) - without this unlink(), the old file would stay behind
        // as clutter in the gravity_forms upload folder.
        if ( $old_raw_video_url && $old_raw_video_url !== $raw_video_url ) {
            pz_delete_upload_file_by_url( $old_raw_video_url );
        }
        update_post_meta( $post_id, '_pz_raw_video_url', $raw_video_url );
    } elseif ( pz_gf_wants_file_deleted( $entry, 19 ) ) {
        // 2026-08-15 jdev: "Delete current video" checked, no new upload -
        // remove the old file from disk and clear the meta instead of
        // falling back to it below.
        $old_raw_video_url = get_post_meta( $post_id, '_pz_raw_video_url', true );
        if ( $old_raw_video_url ) {
            pz_delete_upload_file_by_url( $old_raw_video_url );
        }
        delete_post_meta( $post_id, '_pz_raw_video_url' );
    } else {
        $raw_video_url = get_post_meta( $post_id, '_pz_raw_video_url', true );
    }
    if ( '' !== $audio_file_url ) {
        $old_audio_file_url = get_post_meta( $post_id, '_pz_audio_file_url', true );
        if ( $old_audio_file_url && $old_audio_file_url !== $audio_file_url ) {
            pz_delete_upload_file_by_url( $old_audio_file_url );
        }
        update_post_meta( $post_id, '_pz_audio_file_url', $audio_file_url );
    } elseif ( pz_gf_wants_file_deleted( $entry, 20 ) ) {
        $old_audio_file_url = get_post_meta( $post_id, '_pz_audio_file_url', true );
        if ( $old_audio_file_url ) {
            pz_delete_upload_file_by_url( $old_audio_file_url );
        }
        delete_post_meta( $post_id, '_pz_audio_file_url' );
    } else {
        $audio_file_url = get_post_meta( $post_id, '_pz_audio_file_url', true );
    }
    update_post_meta( $post_id, '_pz_music_attribution', rgar( $entry, '21' ) );

    // 2026-09-14 jdev "Juggled at" (location, GF field 3) is stored as the
    // ACF field "juggled_at" on the pattern post itself, and additionally
    // sent to the render server so it can be burned into the video.
    $juggled_at = rgar( $entry, '3' );
    update_field( 'juggled_at', $juggled_at, $post_id );

    // 2026-09-14 jdev "Monkeys unlisted" (free-text names with no WP account,
    // GF field 9) is stored as the ACF field "video_monkeys_without_account"
    // on the pattern post, same reasoning as juggled_at above.
    $monkeys_unlisted = rgar( $entry, '9' );
    update_field( 'video_monkeys_without_account', $monkeys_unlisted, $post_id );

    $known_monkey_ids   = array_filter( array_map( 'intval', (array) json_decode( rgar( $entry, '6' ), true ) ) );
    $known_monkey_names = array_values( array_filter( array_map(
        function ( $user_id ) {
            $user = get_userdata( $user_id );
            return $user ? $user->display_name : null;
        },
        $known_monkey_ids
    ) ) );

    $payload = [
        'post_id'           => $post_id,
        'title'             => rgar( $entry, '1' ),
        'location'          => $juggled_at,
        'juggled_at'        => $juggled_at,
        'monkeys'           => $known_monkey_names,
        'monkeys_unlisted'  => $monkeys_unlisted,
        'pattern_image_url' => strtok( rgar( $entry, '5' ), '|' ), // Post Image speichert als "url|:|title|:|caption|:|description"
        'raw_video_url'     => $raw_video_url,
        'audio_file_url'    => $audio_file_url,
        'music_attribution' => rgar( $entry, '21' ),
    ];

    pz_pattern_send_to_render_server( $post_id, $payload );
}

/* 2026-09-14 jdev Extracted from pz_gf_send_render_webhook() so a backend
   edit (see pz_sync_pattern_backend_edit() below) can re-trigger the same
   render job from post/ACF data directly, without going through a Gravity
   Forms entry. */
function pz_pattern_send_to_render_server( int $post_id, array $payload ) {
    $response = wp_remote_post( 'http://91.99.57.23/ffmpeg/postrender', [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        GFCommon::log_debug( 'pz_pattern_send_to_render_server(): failed for post ' . $post_id . ' - ' . $response->get_error_message() );
    } else {
        GFCommon::log_debug( 'pz_pattern_send_to_render_server(): sent for post ' . $post_id . ', response code ' . wp_remote_retrieve_response_code( $response ) );
    }
}

/* Helper function: a GF file upload field value is either a single URL
   string or (with multi-file upload enabled) a JSON array of URLs -
   always returns the first URL. */
function pz_gf_first_file_upload_url( $raw ) {
    $raw = (string) $raw;
    if ( '' === $raw ) {
        return '';
    }
    if ( str_starts_with( trim( $raw ), '[' ) ) {
        $urls = json_decode( $raw, true );
        return is_array( $urls ) ? (string) reset( $urls ) : '';
    }
    return $raw;
}

/* 2026-08-15 jdev Deletes an upload file (e.g. an old raw video) from disk
   by its URL, provided it lives in the WP uploads directory (Gravity Forms
   uploads also land there, under gravity_forms/...). Purely defensive:
   silently returns if the file doesn't (or no longer) exist. */
function pz_delete_upload_file_by_url( $url ) {
    if ( ! $url ) {
        return;
    }
    $upload_dir = wp_upload_dir();
    $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
    if ( $file_path && file_exists( $file_path ) ) {
        @unlink( $file_path );
    }
}

/* 2026-09-14 jdev Reverse lookup for pz_sync_pattern_backend_edit() below:
   Advanced Post Creation stores the post it created/updated as entry meta
   "post_id" on the originating Gravity Forms entry (that's how
   pz_gf_on_pattern_post_saved() reads it back via rgar( $entry, 'post_id' )
   on the update hook, and how APC's own "Edit Pattern" post-editing feature
   finds the right entry for a given post_id in the first place) - queried
   directly against the entry meta table since GFAPI::get_entries()
   field_filters only match actual form fields or entry meta keys
   registered via gform_entry_meta, and "post_id" is neither. */
function pz_gf_entry_id_for_post( int $post_id ): int {
    global $wpdb;
    if ( ! class_exists( 'GFFormsModel' ) ) {
        return 0;
    }
    $entry_meta_table = GFFormsModel::get_entry_meta_table_name();
    $entry_id         = $wpdb->get_var( $wpdb->prepare(
        "SELECT entry_id FROM {$entry_meta_table} WHERE meta_key = 'post_id' AND meta_value = %d ORDER BY entry_id DESC LIMIT 1",
        $post_id
    ) );
    return $entry_id ? (int) $entry_id : 0;
}

/* Helper for pz_sync_pattern_backend_edit(): rebuilds the GF Post Image
   field 5 value ("url|:|title|:|caption|:|description", see the strtok()
   comment in pz_gf_send_render_webhook()) from the post's current featured
   image, mapping WP's standard attachment fields the same way Gravity
   Forms itself does (title/caption/description -> post_title/post_excerpt/
   post_content). */
function pz_gf_post_image_field_value_from_attachment( int $attachment_id ): string {
    if ( ! $attachment_id ) {
        return '';
    }
    $url = wp_get_attachment_url( $attachment_id );
    if ( ! $url ) {
        return '';
    }
    $attachment = get_post( $attachment_id );
    return implode( '|:|', [
        $url,
        $attachment ? $attachment->post_title : '',
        $attachment ? $attachment->post_excerpt : '',
        $attachment ? $attachment->post_content : '',
    ] );
}

/* Helper for pz_sync_pattern_backend_edit(): field 6 ("Monkeys") and field 7
   ("Pattern Author") store selected user IDs as a JSON array of ID strings
   (see pz_gf_save_user_selects_to_acf() above) - mirrors that exact format
   when writing the ACF-side array of IDs back into the entry. */
function pz_gf_user_ids_json( $acf_value ): string {
    $ids = array_filter( array_map( 'intval', (array) $acf_value ) );
    return (string) wp_json_encode( array_map( 'strval', array_values( $ids ) ) );
}

/* Helpers for pz_sync_pattern_backend_edit(): fields 12/13 (Pattern
   Difficulty, Number of Jugglers) are single-value fields storing the exact
   term NAME (see pz_gf_populate_difficulty_choices()/pz_gf_populate_jugglers_choices()
   above - choices use the term name as both label and value); fields 14/15
   (Pattern Type, Pattern Tags) are multi-selects storing a JSON array of
   term names (see pz_gf_populate_type_and_tag_choices()). */
function pz_gf_first_term_name( int $post_id, string $taxonomy ): string {
    $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
    return ( is_array( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
}
function pz_gf_term_names_json( int $post_id, string $taxonomy ): string {
    $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
    return (string) wp_json_encode( is_array( $terms ) ? array_values( $terms ) : [] );
}

/* Helper for pz_sync_pattern_backend_edit(): fields 19/20 (Raw Video, Audio
   File) may be configured for single or multiple files, so their entry
   value is either a plain URL string or a JSON array of URLs (see
   pz_gf_first_file_upload_url() above, which reads both). Writing back a
   single, currently-saved URL from postmeta, this keeps whichever format
   the entry already used instead of guessing. */
function pz_gf_file_field_value_preserving_format( string $existing_raw, string $new_url ): string {
    if ( '' === $new_url ) {
        return '';
    }
    if ( str_starts_with( trim( $existing_raw ), '[' ) ) {
        return (string) wp_json_encode( [ $new_url ] );
    }
    return $new_url;
}

/* Helper for pz_pattern_build_render_payload(): the ACF fields
   "raw_video_upload"/"music_upload" (used on patterns created by hand, see
   below) could be configured as a File, Video/oEmbed, or plain URL field -
   handles whichever shape get_field() returns (attachment array, raw
   attachment ID, or already a plain URL string). */
function pz_pattern_resolve_file_field_url( $value ): string {
    if ( is_array( $value ) && isset( $value['url'] ) ) {
        return (string) $value['url'];
    }
    if ( is_numeric( $value ) ) {
        $url = wp_get_attachment_url( (int) $value );
        return $url ? (string) $url : '';
    }
    if ( is_string( $value ) ) {
        return $value;
    }
    return '';
}

/* 2026-09-14 jdev Builds the exact render-webhook payload shape (see
   pz_gf_send_render_webhook()) from the CURRENT post/ACF/meta state, rather
   than from a Gravity Forms entry - used by pz_sync_pattern_backend_edit()
   for both a form-created pattern (post and entry are already in sync at
   that point, see there) and a pattern created by hand in wp-admin (no
   entry exists at all).
   raw_video_url/audio_file_url prefer the protected _pz_raw_video_url/
   _pz_audio_file_url postmeta the form-driven flow always fills in; a
   hand-created pattern never gets that meta set (there's no upload UI for
   it, deliberately - see pz_gf_send_render_webhook()'s comment), so falls
   back to the ACF fields "raw_video_upload"/"music_upload" instead. */
function pz_pattern_build_render_payload( int $post_id ): array {
    $post          = get_post( $post_id );
    $attachment_id = get_post_thumbnail_id( $post_id );

    $known_monkey_ids   = array_filter( array_map( 'intval', (array) get_field( 'video_monkeys', $post_id, false ) ) );
    $known_monkey_names = array_values( array_filter( array_map(
        function ( $user_id ) {
            $user = get_userdata( $user_id );
            return $user ? $user->display_name : null;
        },
        $known_monkey_ids
    ) ) );

    $juggled_at = (string) get_field( 'juggled_at', $post_id );

    $raw_video_url = (string) get_post_meta( $post_id, '_pz_raw_video_url', true );
    if ( '' === $raw_video_url ) {
        $raw_video_url = pz_pattern_resolve_file_field_url( get_field( 'raw_video_upload', $post_id, false ) );
    }
    $audio_file_url = (string) get_post_meta( $post_id, '_pz_audio_file_url', true );
    if ( '' === $audio_file_url ) {
        $audio_file_url = pz_pattern_resolve_file_field_url( get_field( 'music_upload', $post_id, false ) );
    }

    return [
        'post_id'           => $post_id,
        'title'             => $post ? $post->post_title : '',
        'location'          => $juggled_at,
        'juggled_at'        => $juggled_at,
        'monkeys'           => $known_monkey_names,
        'monkeys_unlisted'  => (string) get_field( 'video_monkeys_without_account', $post_id ),
        'pattern_image_url' => $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '',
        'raw_video_url'     => $raw_video_url,
        'audio_file_url'    => $audio_file_url,
        'music_attribution' => (string) get_post_meta( $post_id, '_pz_music_attribution', true ),
    ];
}

/* Helper for pz_sync_pattern_backend_edit_without_entry(): a hand-created
   pattern has no Gravity Forms entry to diff the new values against field
   by field (unlike pz_sync_pattern_backend_edit_with_entry() below), so
   "did anything video-relevant change" is instead answered by comparing a
   hash of the last-sent payload, stored in postmeta after every send. */
function pz_pattern_render_payload_fingerprint( array $payload ): string {
    unset( $payload['post_id'] );
    return md5( (string) wp_json_encode( $payload ) );
}

/* 2026-09-14 jdev A pattern post can also be edited directly in wp-admin,
   bypassing the "Edit Pattern" Gravity Forms page entirely. Without this,
   such a backend edit would only ever change the post/ACF data - the
   underlying Gravity Forms entry (which is what pre-fills the "Edit
   Pattern" form, see pz_gf_optional_on_edit_page() and friends) would keep
   showing the OLD values, and worse: submitting that stale form again
   later would silently overwrite the backend edit back to the old value
   via pz_gf_send_render_webhook()/pz_gf_set_featured_and_acf_image()/etc.
   Additionally, a pattern created entirely by hand (never submitted
   through the form at all, so no entry exists to begin with) still gets
   its video-relevant changes sent to the render server - see
   pz_sync_pattern_backend_edit_without_entry() below.
   Hooked on acf/save_post rather than save_post_pattern specifically
   because acf/save_post only fires for a real ACF-metabox save in wp-admin
   (or an acf_form() on the frontend) - never for the update_field() calls
   this codebase itself makes from Advanced Post Creation's post-saved
   hooks (see pz_gf_on_pattern_post_saved()), so there's no risk of this
   looping back into itself. Priority 20 so ACF has already written its own
   field values to the database by the time this runs. */
add_action( 'acf/save_post', 'pz_sync_pattern_backend_edit', 20 );
function pz_sync_pattern_backend_edit( $post_id ) {
    $post_id = (int) $post_id;
    if ( 'pattern' !== get_post_type( $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $entry_id = pz_gf_entry_id_for_post( $post_id );
    if ( $entry_id ) {
        pz_sync_pattern_backend_edit_with_entry( $post_id, $entry_id );
    } else {
        pz_sync_pattern_backend_edit_without_entry( $post_id );
    }
}

/* See pz_sync_pattern_backend_edit() above. Handles a pattern that DOES have
   a linked Gravity Forms entry (created/last edited through the form at
   some point): writes every field with a home on the post back into that
   entry field-by-field, and re-renders only if a video-relevant one of them
   actually changed. */
function pz_sync_pattern_backend_edit_with_entry( int $post_id, int $entry_id ) {
    $entry = GFAPI::get_entry( $entry_id );
    if ( is_wp_error( $entry ) ) {
        return;
    }

    $post          = get_post( $post_id );
    $attachment_id = get_post_thumbnail_id( $post_id );

    $new_values = [
        '1'  => (string) $post->post_title,
        '3'  => (string) get_field( 'juggled_at', $post_id ),
        '5'  => pz_gf_post_image_field_value_from_attachment( $attachment_id ),
        '9'  => (string) get_field( 'video_monkeys_without_account', $post_id ),
        '6'  => pz_gf_user_ids_json( get_field( 'video_monkeys', $post_id, false ) ),
        '7'  => pz_gf_user_ids_json( get_field( 'pattern_author', $post_id, false ) ),
        '12' => pz_gf_first_term_name( $post_id, 'pattern-difficulty' ),
        '13' => pz_gf_first_term_name( $post_id, 'number-of-jugglers' ),
        '14' => pz_gf_term_names_json( $post_id, 'pattern-type' ),
        '15' => pz_gf_term_names_json( $post_id, 'pattern-tag' ),
        '18' => (string) $post->post_excerpt,
        '19' => pz_gf_file_field_value_preserving_format( (string) rgar( $entry, '19' ), (string) get_post_meta( $post_id, '_pz_raw_video_url', true ) ),
        '20' => pz_gf_file_field_value_preserving_format( (string) rgar( $entry, '20' ), (string) get_post_meta( $post_id, '_pz_audio_file_url', true ) ),
        '21' => (string) get_post_meta( $post_id, '_pz_music_attribution', true ),
    ];

    // Fields that actually end up in the rendered video (see the $payload
    // shape in pz_pattern_build_render_payload()) - only changes to these
    // justify sending a new render job.
    $video_relevant_field_ids = [ '1', '3', '5', '6', '9', '19', '20', '21' ];
    $video_relevant_changed   = false;

    foreach ( $new_values as $field_id => $new_value ) {
        $old_value = (string) rgar( $entry, $field_id );
        if ( $old_value === $new_value ) {
            continue;
        }
        // An empty file/image field just means "nothing set on the post
        // side" (e.g. no featured image ever chosen) - don't blow away an
        // existing entry value with an empty string over that.
        if ( '' === $new_value && in_array( $field_id, [ '5', '19', '20' ], true ) ) {
            continue;
        }
        $result = GFAPI::update_entry_field( $entry_id, $field_id, $new_value );
        if ( is_wp_error( $result ) ) {
            error_log( 'pz_sync_pattern_backend_edit_with_entry(): failed to update entry ' . $entry_id . ' field ' . $field_id . ' - ' . $result->get_error_message() );
            continue;
        }
        if ( in_array( $field_id, $video_relevant_field_ids, true ) ) {
            $video_relevant_changed = true;
        }
    }

    if ( ! $video_relevant_changed ) {
        return;
    }

    $payload = pz_pattern_build_render_payload( $post_id );
    pz_pattern_send_to_render_server( $post_id, $payload );
    update_post_meta( $post_id, '_pz_render_fingerprint', pz_pattern_render_payload_fingerprint( $payload ) );
}

/* See pz_sync_pattern_backend_edit() above. Handles a pattern with NO linked
   Gravity Forms entry at all (created entirely by hand in wp-admin) - there
   is no per-field "old value" to diff against like in the with-entry case,
   so instead compares a fingerprint of the current video-relevant payload
   against the one stored after the last render, only sending a new render
   job when that actually changed. */
function pz_sync_pattern_backend_edit_without_entry( int $post_id ) {
    $payload         = pz_pattern_build_render_payload( $post_id );
    $new_fingerprint = pz_pattern_render_payload_fingerprint( $payload );
    $old_fingerprint = get_post_meta( $post_id, '_pz_render_fingerprint', true );

    if ( $new_fingerprint === $old_fingerprint ) {
        return;
    }

    pz_pattern_send_to_render_server( $post_id, $payload );
    update_post_meta( $post_id, '_pz_render_fingerprint', $new_fingerprint );
}

/* 2026-08-15 jdev Counterpart to pz_gf_send_render_webhook(): the Hetzner
   render server calls this via POST once the finished video (raw video +
   audio assembled via ffmpeg) is ready. Expected JSON body:
   { "post_id": 123, "video_url": "http://91.99.57.23/ffmpeg/output/xyz.mp4" }
   The finished video is loaded into the media library as a real attachment
   via media_handle_sideload() (attached to post_id), and the attachment ID
   is stored in postmeta "_pz_final_video_id" - frontend display runs via
   the Presto Player shortcode [presto_player src="..."], see
   pz_pattern_video_shortcode_html().
   Auth: shared secret via the "X-PZ-Render-Secret" header, must be set
   server-side in wp-config.php as define('PZ_RENDER_WEBHOOK_SECRET', '...')
   (deliberately not in the repo/as a DB option, only on the server). */
add_action( 'rest_api_init', function () {
    register_rest_route( 'pz/v1', '/render-complete', [
        'methods'             => 'POST',
        'callback'            => 'pz_handle_render_complete',
        'permission_callback' => 'pz_render_complete_permission_check',
        'args'                => [
            'post_id'   => [ 'required' => true ],
            'video_url' => [ 'required' => true ],
        ],
    ] );
} );

function pz_render_complete_permission_check( WP_REST_Request $request ) {
    if ( ! defined( 'PZ_RENDER_WEBHOOK_SECRET' ) || '' === PZ_RENDER_WEBHOOK_SECRET ) {
        return new WP_Error( 'pz_render_webhook_not_configured', 'PZ_RENDER_WEBHOOK_SECRET is not set.', [ 'status' => 500 ] );
    }
    $given_secret = $request->get_header( 'x-pz-render-secret' );
    if ( ! $given_secret || ! hash_equals( PZ_RENDER_WEBHOOK_SECRET, $given_secret ) ) {
        return new WP_Error( 'pz_render_webhook_forbidden', 'Invalid secret.', [ 'status' => 403 ] );
    }
    return true;
}

function pz_handle_render_complete( WP_REST_Request $request ) {
    $post_id   = (int) $request->get_param( 'post_id' );
    $video_url = esc_url_raw( (string) $request->get_param( 'video_url' ) );

    if ( ! $post_id || 'pattern' !== get_post_type( $post_id ) ) {
        return new WP_Error( 'pz_render_webhook_bad_post', 'Unknown pattern post_id.', [ 'status' => 404 ] );
    }
    if ( ! $video_url ) {
        return new WP_Error( 'pz_render_webhook_bad_url', 'Missing video_url.', [ 'status' => 400 ] );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp_file = download_url( $video_url );
    if ( is_wp_error( $tmp_file ) ) {
        error_log( 'pz_handle_render_complete(): download_url failed for post ' . $post_id . ' - ' . $tmp_file->get_error_message() );
        return new WP_Error( 'pz_render_webhook_download_failed', $tmp_file->get_error_message(), [ 'status' => 502 ] );
    }

    $file_array = [
        'name'     => sanitize_file_name( basename( wp_parse_url( $video_url, PHP_URL_PATH ) ) ),
        'tmp_name' => $tmp_file,
    ];
    $attachment_id = media_handle_sideload( $file_array, $post_id );
    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $tmp_file );
        error_log( 'pz_handle_render_complete(): sideload failed for post ' . $post_id . ' - ' . $attachment_id->get_error_message() );
        return new WP_Error( 'pz_render_webhook_sideload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
    }

    // Remove the previous finished video (e.g. on re-render after editing)
    // from the media library, so it doesn't accumulate as clutter there.
    $old_attachment_id = (int) get_post_meta( $post_id, '_pz_final_video_id', true );
    if ( $old_attachment_id && $old_attachment_id !== (int) $attachment_id ) {
        wp_delete_attachment( $old_attachment_id, true );
    }
    update_post_meta( $post_id, '_pz_final_video_id', $attachment_id );

    error_log( 'pz_handle_render_complete(): attached video ' . $attachment_id . ' to post ' . $post_id );

    return [
        'success'       => true,
        'attachment_id' => $attachment_id,
        'video_url'     => wp_get_attachment_url( $attachment_id ),
    ];
}

/* 2026-08-15 jdev Renders the finished pattern video via the Presto Player
   shortcode (video sits as an attachment in the media library, see
   pz_handle_render_complete()). Per the Presto Player docs, src accepts a
   direct file URL - no dedicated pp_video_block CPT entry needed. */
function pz_pattern_video_shortcode_html( int $post_id ): string {
    $attachment_id = (int) get_post_meta( $post_id, '_pz_final_video_id', true );
    if ( ! $attachment_id ) {
        return '';
    }
    $video_url = wp_get_attachment_url( $attachment_id );
    if ( ! $video_url ) {
        return '';
    }
    return do_shortcode( '[presto_player src="' . esc_url( $video_url ) . '"]' );
}

/* 2026-07-13 jdev Safety net: if a user (e.g. due to a faulty GF
   configuration or manual creation) ends up without a display name, set
   "First name Last-initial." as a fallback. Does NOT kick in if the user
   already has a display name set (including a freeform one). */
function pz_ensure_display_name_fallback( $user_id ) {
    static $running = [];
    if ( ! empty( $running[ $user_id ] ) ) {
        return;
    }
    $user = get_userdata( $user_id );
    if ( ! $user || '' !== trim( (string) $user->display_name ) ) {
        return;
    }
    $first_name = get_user_meta( $user_id, 'first_name', true );
    if ( '' === $first_name ) {
        return;
    }
    $last_name    = get_user_meta( $user_id, 'last_name', true );
    $display_name = $first_name;
    if ( '' !== $last_name ) {
        $display_name .= ' ' . mb_strtoupper( mb_substr( $last_name, 0, 1 ) ) . '.';
    }
    $running[ $user_id ] = true;
    wp_update_user( [ 'ID' => $user_id, 'display_name' => $display_name ] );
}
add_action( 'user_register', 'pz_ensure_display_name_fallback' );
add_action( 'profile_update', 'pz_ensure_display_name_fallback' );

/* 2026-07-13 jdev Prefill the "Edit Profile" form with the logged-in user's
   data, instead of populating Gravity Forms via query string. Parameter
   names must be set exactly like this in the form (Field > Advanced >
   "Allow field to be populated dynamically" > Parameter Name). */
function pz_prefill_from_current_user( $value, $field, $name ) {
    if ( ! is_user_logged_in() ) {
        return $value;
    }
    $user = wp_get_current_user();
    switch ( $name ) {
        case 'first_name':
            return $user->first_name;
        case 'last_name':
            return $user->last_name;
        case 'user_email':
            return $user->user_email;
        case 'nickname':
            return get_user_meta( $user->ID, 'nickname', true );
        default:
            return $value;
    }
}
add_filter( 'gform_field_value_first_name', function ( $value, $field ) { return pz_prefill_from_current_user( $value, $field, 'first_name' ); }, 10, 2 );
add_filter( 'gform_field_value_last_name', function ( $value, $field ) { return pz_prefill_from_current_user( $value, $field, 'last_name' ); }, 10, 2 );
add_filter( 'gform_field_value_user_email', function ( $value, $field ) { return pz_prefill_from_current_user( $value, $field, 'user_email' ); }, 10, 2 );
add_filter( 'gform_field_value_nickname', function ( $value, $field ) { return pz_prefill_from_current_user( $value, $field, 'nickname' ); }, 10, 2 );

/* 2026-07-16 jdev Gravity Forms (Form 1, registration): no separate
   "Username" field anymore - the username is generated automatically from
   first and last name (field 1, Advanced Name: 1.3 = first name, 1.6 =
   last name) as "firstname.lastname". Umlauts are transliterated via
   remove_accents() (ä->a, ö->o, ü->u, ß->ss) so sanitize_user() doesn't
   simply discard them. Multi-part first names (e.g. "Anna Lena") are
   joined with a hyphen, multi-part last names (e.g. "de Vries") with a
   dot - that way "firstname.lastname" always keeps a clearly identifiable
   separator between first and last name. If the username is already
   taken, a running number is appended. The "Username" mapping in the User
   Registration feed can point to any existing field (e.g. email) - this
   filter always overwrites the value anyway. */
add_filter( 'gform_username_1', 'pz_gf_generate_username_from_name', 10, 4 );
function pz_gf_generate_username_from_name( $username, $feed, $form, $entry ) {
    $first_name = trim( remove_accents( rgar( $entry, '1.3' ) ) );
    $last_name  = trim( remove_accents( rgar( $entry, '1.6' ) ) );

    // Safety net: if 1.3/1.6 arrive empty on submission (e.g. due to a JS
    // reload or a failed client-side validation where the name fields
    // weren't repopulated), fall back to field 1 as a whole and split into
    // first/last name at the first space. Prevents an empty first/last
    // name from resulting in a username that's just a dot (see bug
    // 2026-07-20 with "Tine Oymann").
    if ( '' === $first_name && '' === $last_name ) {
        $full_name = trim( remove_accents( rgar( $entry, '1' ) ) );
        if ( '' !== $full_name ) {
            $parts      = preg_split( '/\s+/', $full_name, 2 );
            $first_name = $parts[0];
            $last_name  = isset( $parts[1] ) ? $parts[1] : '';
        }
    }

    // Normalize repeated/inner spaces in multi-part names before joining
    // them with a hyphen (first name) or a dot (last name).
    $first_part = preg_replace( '/\s+/', '-', $first_name );
    $last_part  = preg_replace( '/\s+/', '.', $last_name );

    // trim() removes a leading/trailing dot if the first or last name is
    // empty - otherwise, e.g. a missing last name would produce
    // ".firstname", or a missing first name "lastname.", as the username.
    $base_username = sanitize_user( strtolower( trim( $first_part . '.' . $last_part, '.' ) ), true );

    // Last-resort fallback if no name could be determined at all: build a
    // unique username from the entry ID instead of passing an
    // empty/invalid username to wp_create_user() (which otherwise leads
    // to "Cannot create a user with an empty nicename").
    if ( '' === $base_username ) {
        $entry_id      = rgar( $entry, 'id' );
        $base_username = $entry_id ? 'user' . $entry_id : $username;
        if ( '' === $base_username ) {
            return $username;
        }
    }

    if ( ! function_exists( 'username_exists' ) ) {
        require_once ABSPATH . WPINC . '/registration.php';
    }

    $candidate = $base_username;
    $suffix    = 2;
    while ( username_exists( $candidate ) ) {
        $candidate = $base_username . $suffix;
        $suffix++;
    }

    return $candidate;
}

/* 2026-07-20 jdev Remove the REST API discovery link for authors: the
   "Monkeys"/"Pattern Author" users aren't tracked as a real post_author
   (the association runs via ACF, see pz_gf_populate_user_choices), so WP
   classifies them as "not public" and /wp-json/wp/v2/users/{id} returns
   404 for anonymous requests - even though WP core still outputs the link
   in <head> and in the HTTP Link header of every author page
   (rest_output_link_wp_head / rest_output_link_header). Matches the
   existing REST user-enumeration block in .htaccess: the link is
   consistently never advertised in the first place. */
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

/* 2026-09-25 jdev Don't expose user names via the REST API: /wp/v2/users listed all
   58 login slugs publicly (one derived from an e-mail address). Only logged-in users
   who can edit posts get it (backend author picker etc.); the pattern form fills its
   user choices server-side via get_users() and doesn't need the endpoint. */
function jdev_restrict_rest_users( $result, $server, $request ) {
	if ( preg_match( '#^/wp/v2/users(/|$)#', $request->get_route() ) && ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => rest_authorization_required_code() ) );
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'jdev_restrict_rest_users', 10, 3 );

/* 2026-07-21 jdev After login, send all members up to and including author
   directly to the pattern upload page, instead of the wp-admin dashboard
   or my-account. Only editor/administrator have "edit_others_posts", not
   author - anyone with it still lands in the backend as usual. */
add_filter( 'login_redirect', 'pz_redirect_members_after_login', 10, 3 );
function pz_redirect_members_after_login( $redirect_to, $requested_redirect_to, $user ) {
    if ( $user instanceof WP_User && ! $user->has_cap( 'edit_others_posts' ) ) {
        return 'https://passing.zone/upload-a-new-pattern/';
    }
    return $redirect_to;
}

/* 2026-08-12 jdev Native WP registration is disabled (users_can_register
   = false), registration runs via Gravity Forms at /register/. However,
   the "Register" link in the BB Themer login form still points to
   wp-login.php?action=register and lands on the registration=disabled
   notice there -> redirect to our own registration page instead of
   having to find/adjust the link in the login form. */
add_action( 'login_init', function () {
    if ( isset( $_GET['action'] ) && 'register' === $_GET['action'] ) {
        wp_safe_redirect( home_url( '/register/' ) );
        exit;
    }
} );
