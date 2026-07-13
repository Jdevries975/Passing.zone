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

/* 2026-07-13 jdev Gravity Forms (Form 4) User-Auswahl-Felder ("Monkeys" = Feld 6,
   "Pattern Author" = Feld 7): dynamisch mit allen WP-Usern befüllen
   (Value = User-ID, Text = Anzeigename). Alle vier Hooks werden gebraucht,
   damit die Choices beim Rendern, bei der Validierung, im Admin-Preview und
   beim Absenden konsistent vorhanden sind. */
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

/* 2026-07-13 jdev Gravity Forms (Form 4) "Pattern Difficulty" Radio-Feld
   (Field 12): Choices dynamisch aus der echten "pattern-difficulty"-Taxonomie
   befüllen, damit der übermittelte Wert immer exakt einem bestehenden Term
   entspricht (Advanced Post Creation legt bei einem Namens-Mismatch sonst
   stillschweigend einen neuen/doppelten Term an). */
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

/* 2026-07-13 jdev Gravity Forms (Form 4) "Number of Jugglers"-Feld (Field 13):
   Choices dynamisch aus der echten "number-of-jugglers"-Taxonomie befüllen,
   aus demselben Grund wie bei Field 12 (exakter Namens-Match statt versehentlich
   neuer/doppelter Terms). */
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

/* 2026-07-13 jdev Gravity Forms (Form 4) "Pattern Type" Multi Select (Field 14)
   und "Pattern Tags" Multi Select (Field 15): Choices dynamisch aus den echten
   Taxonomien "pattern-type" / "pattern-tag" befüllen, aus demselben Grund wie
   bei Field 12/13 (exakter Namens-Match statt versehentlich neuer/doppelter
   Terms). */
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

/* 2026-07-13 jdev Nach dem Erstellen des "pattern"-Posts über die Advanced
   Post Creation Feed von Form 4: die ausgewählten User-IDs aus Feld 6/7
   korrekt in die ACF-Felder "video_monkeys" / "pattern_author" schreiben
   (per update_field statt roher Custom-Field-Mapping, damit ACF die Felder
   im Backend wieder erkennt und richtig anzeigt).
   Wichtig: Advanced Post Creation verarbeitet den Feed asynchron (im
   Hintergrund, nach dem eigentlichen Formular-Request) - "gform_after_submission"
   feuert dafür zu früh, der Post existiert an der Stelle noch nicht. Deshalb
   der eigene "post_after_creation"-Hook des Add-Ons, der erst feuert, wenn
   der Post wirklich angelegt wurde. */
add_action( 'gform_advancedpostcreation_post_after_creation', 'pz_gf_save_user_selects_to_acf', 10, 4 );
function pz_gf_save_user_selects_to_acf( $post_id, $feed, $entry, $form ) {
    if ( ! $post_id || (int) rgar( $form, 'id' ) !== 4 ) {
        return;
    }
    $acf_fields_by_gf_field_id = [
        '6' => 'video_monkeys',
        '7' => 'pattern_author',
    ];
    foreach ( $acf_fields_by_gf_field_id as $gf_field_id => $acf_field_name ) {
        $raw = rgar( $entry, $gf_field_id ); // z.B. '["15","25"]' (Multi Select speichert als JSON-Array)
        $ids = array_filter( array_map( 'intval', (array) json_decode( $raw, true ) ) );
        update_field( $acf_field_name, $ids, $post_id );
    }
}

/* 2026-07-13 jdev Ein einziges Upload-Feld (Field 5, "Pattern Image") für
   sowohl das native WordPress Featured Image als auch das ACF-Feld
   "pattern_image" nutzen - damit man nicht zwei separate Upload-Felder im
   Formular braucht. Feld 5 bleibt daher im Feed selbst UNGEMAPPT (weder unter
   Featured Image noch unter Custom Fields), diese Funktion übernimmt beides.
   Die von GF hochgeladene Datei liegt bereits lokal im gravity_forms-Ordner;
   wir erstellen daraus ein echtes Attachment in der Mediathek (statt per
   media_sideload_image() nochmal per HTTP zu laden - unnötig fragil wegen
   der Cloudflare-Loopback-Problematik). */
add_action( 'gform_advancedpostcreation_post_after_creation', 'pz_gf_set_featured_and_acf_image', 10, 4 );
function pz_gf_set_featured_and_acf_image( $post_id, $feed, $entry, $form ) {
    if ( ! $post_id || (int) rgar( $form, 'id' ) !== 4 ) {
        return;
    }
    $raw = rgar( $entry, '5' );
    if ( empty( $raw ) ) {
        return;
    }
    $image_url = strtok( $raw, '|' ); // Post Image speichert als "url|:|title|:|caption|:|description"
    if ( ! $image_url ) {
        return;
    }
    $upload_dir = wp_upload_dir();
    $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );
    if ( ! file_exists( $file_path ) ) {
        return;
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

/* 2026-07-13 jdev Excerpt-Feld (Field 18) in "post_excerpt" schreiben.
   Advanced Post Creation hat im Content-Tab keinen Post-Excerpt-Slot (nur
   Title, Content, Featured Image, Custom Fields) und die Custom-Fields-Mappings
   schreiben nur Postmeta, keine echten wp_posts-Spalten - deshalb hier per
   wp_update_post(), analog zum Featured-Image-Workaround oben. */
add_action( 'gform_advancedpostcreation_post_after_creation', 'pz_gf_save_excerpt', 10, 4 );
function pz_gf_save_excerpt( $post_id, $feed, $entry, $form ) {
    if ( ! $post_id || (int) rgar( $form, 'id' ) !== 4 ) {
        return;
    }
    $excerpt = rgar( $entry, '18' );
    if ( '' === $excerpt ) {
        return;
    }
    wp_update_post( [
        'ID'           => $post_id,
        'post_excerpt' => $excerpt,
    ] );
}

/* 2026-07-13 jdev Nach dem Erstellen des "pattern"-Posts: Raw Video (Field 19)
   und Audiofile (Field 20) als geschützte (unterstrich-prefixte, nicht im
   Custom-Fields-Metabox sichtbare) Postmeta speichern - kein Frontend-Display
   vorgesehen - und zusammen mit Titel/Ort/Monkeys/Bild per Webhook an den
   Hetzner-Renderserver schicken. */
add_action( 'gform_advancedpostcreation_post_after_creation', 'pz_gf_send_render_webhook', 10, 4 );
function pz_gf_send_render_webhook( $post_id, $feed, $entry, $form ) {
    if ( ! $post_id || (int) rgar( $form, 'id' ) !== 4 ) {
        return;
    }

    $raw_video_url  = pz_gf_first_file_upload_url( rgar( $entry, '19' ) );
    $audio_file_url = pz_gf_first_file_upload_url( rgar( $entry, '20' ) );

    update_post_meta( $post_id, '_pz_raw_video_url', $raw_video_url );
    update_post_meta( $post_id, '_pz_audio_file_url', $audio_file_url );
    update_post_meta( $post_id, '_pz_music_attribution', rgar( $entry, '21' ) );

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
        'location'          => rgar( $entry, '3' ),
        'monkeys'           => $known_monkey_names,
        'monkeys_unlisted'  => rgar( $entry, '9' ),
        'pattern_image_url' => strtok( rgar( $entry, '5' ), '|' ), // Post Image speichert als "url|:|title|:|caption|:|description"
        'raw_video_url'     => $raw_video_url,
        'audio_file_url'    => $audio_file_url,
        'music_attribution' => rgar( $entry, '21' ),
    ];

    $response = wp_remote_post( 'http://91.99.57.23/ffmpeg/postrender', [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        GFCommon::log_debug( 'pz_gf_send_render_webhook(): failed for post ' . $post_id . ' - ' . $response->get_error_message() );
    } else {
        GFCommon::log_debug( 'pz_gf_send_render_webhook(): sent for post ' . $post_id . ', response code ' . wp_remote_retrieve_response_code( $response ) );
    }
}

/* Hilfsfunktion: ein GF File-Upload-Feldwert ist entweder ein einzelner
   URL-String oder (bei aktiviertem Multi-File-Upload) ein JSON-Array von URLs
   - liefert immer die erste URL. */
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
