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
/* Gravatar komplett disablen*/
add_filter( 'option_show_avatars', '__return_false' );
/* 2025-02-05 jdev Verhindern, dass der Beaver Builder html löscht oder modifiziert...*/
add_filter( 'fl_inline_editing_enabled', '__return_false' );

/* 2025-02-05 jdev Add mime type .svg and .vcard */
function jdev_ext_mimes ( $mimes ){
$mimes['svg'] = 'image/svg+xml';
$mimes['vcf'] = 'text/vcard';
return $mimes;
}
add_filter( 'upload_mimes', 'jdev_ext_mimes' );

/*2025-02-05 jdev Einbindung self-hosted fonts*/
function remove_external_fonts() {
wp_deregister_style('generate-fonts');
wp_enqueue_style('generate-fonts', get_stylesheet_directory_uri() . '/css/fonts.css');
}
add_action('wp_enqueue_scripts','remove_external_fonts');

/*2025-02-05 jdev Verhindern, dass der Beaver Builder Google Fonts von Google abruft */
add_filter( 'fl_builder_google_fonts_pre_enqueue', function( $fonts ) {
return array();
} );

/* 2025-02-05 jdev Einbindung FontAwesome*/
function additional_scripts_before() {
wp_deregister_style('font-awesome');
wp_dequeue_style('font-awesome');
wp_deregister_style('font-awesome-6');
wp_enqueue_style('font-awesome-6', get_stylesheet_directory_uri() . '/fonts/fontawesome-free-6.7.2-web/css/all.min.css');
}
add_action('wp_enqueue_scripts', 'additional_scripts_before',1000);
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

/* 2025-10-14 Ein eigenes JavaScript mit jQuery korrekt hinzufügen (Enqueue a custom JS file with jQuery as a dependency)*/
function jdev_custom_js_file() {
 	wp_enqueue_script('custom-js', get_stylesheet_directory_uri() . '/js/jenny.js', array('jquery'), false, false);
	wp_enqueue_script('custom-js', get_stylesheet_directory_uri() . '/js/jdev.js', array('jquery'), false, false);
	wp_enqueue_script('custom-js', get_stylesheet_directory_uri() . '/js/toggle-archive-extras.js', array('jquery'), false, false);
	
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
/* 2026-03-03 jdev entferne das "neuen Content" Menu item aus der admin bar am Handy. Sonst überlappt das Profilbild mit dem Hauptmenü...*/
add_action( 'admin_bar_menu', 'remove_new_posts_from_admin_bar', 999 );
function remove_new_posts_from_admin_bar( $wp_admin_bar ) {
    // "Neue Beiträge" entfernen
    $wp_admin_bar->remove_node( 'new-post' );
    $wp_admin_bar->remove_node( 'new-content' );
	
}
/* 2026-03-04 jdev Video schema markup for Pattern
add_action('wp_head', 'add_video_schema_patterns', 99);

 Video SEO
function add_video_schema_patterns() {
    if (is_singular('pattern')) {
        // Hole Video-URL, Thumbnail, Titel etc. aus ACF-Feldern (passe an)
        $video_url = get_field('video_url'); // Beispiel-Feld
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
            $data['thumbnail']['url'] = $thumb['url']; // Für Yoast
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
        } elseif ($thumb) { // Falls nur URL
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
    // a serialized PHP array of integers. The three clauses cover both formats.
    $patterns = new WP_Query( [
        'post_type'      => 'pattern',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'video_monkeys',
                'value'   => $user_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => 'video_monkeys',
                'value'   => ';i:' . $user_id . ';',
                'compare' => 'LIKE',
            ],
            [
                'key'     => 'video_monkeys',
                'value'   => ':"' . $user_id . '"',
                'compare' => 'LIKE',
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

/* 2026-06-13 jdev Weißen Balken oben in Firefox entfernen wenn nicht eingeloggt */
add_action( 'wp_head', function() {
    if ( ! is_user_logged_in() ) {
        echo '<style>html,body{margin-top:0!important;padding-top:0!important}</style>';
    }
}, 99 );

/* 2026-07-07 jdev Content-Restriction für Rolle "author" und höher.
   Usage: [members-only]geschützter Inhalt[/members-only]
   "publish_posts" ist die Capability, die author/editor/administrator haben,
   aber subscriber/contributor nicht - damit lässt sich "author und höher" prüfen. */
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
