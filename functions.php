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

/*2025-02-05 jdev Verhindern, dass der Beaver Builder Google Fonts von Google abruft */
add_filter( 'fl_builder_google_fonts_pre_enqueue', function( $fonts ) {
return array();
} );

/* 2026-07-19 jdev Einbindung FontAwesome 7.3.1, zwingt Beaver Builder zur self-hosted Version */
function additional_scripts_before() {
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
/* 2026-07-19 jdev Handle umbenannt: kollidierte mit GeneratePress' eigenem 'custom-js'-Handle, wodurch jenny.js nie geladen wurde */
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

/* 2026-07-18 jdev Die oben per gform_*_pre_render_4 injizierten Choices für
   Felder 6, 7, 12, 13, 14, 15 gelten nur für Kontexte, die diese Hooks
   tatsächlich feuern (Formular selbst, Validierung, Admin-Preview) bzw. für
   die Entries-Liste über gform_entries_field_value. Die einzelne
   Entry-Detailansicht (View Entry) rendert Auswahlfelder dagegen direkt aus
   den in der Datenbank gespeicherten Feld-Choices, ohne diese Hooks zu
   feuern - dort tauchen deshalb weiterhin rohe IDs/Werte statt Namen auf.
   Deshalb hier zusätzlich die echten Choices dauerhaft in die
   Formular-Definition selbst schreiben, damit Gravity Forms sie überall von
   sich aus korrekt auflöst. Läuft gedrosselt (alle 5 Minuten) bei jedem
   Admin-Seitenaufruf, damit neue User/Terms zeitnah einsortiert werden. */
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

/* 2026-07-19 jdev Advanced Post Creation feuert für "Post erstellt" und "Post
   aktualisiert" zwei komplett getrennte Hooks mit unterschiedlicher
   Parameter-Signatur:
   - post_after_creation: ( $post_id, $feed, $entry, $form )
   - post_update_post:    ( $post-Objekt, $feed, $entry )  [kein $form]
   Wichtig: Advanced Post Creation verarbeitet seinen Feed asynchron (im
   Hintergrund, nach dem eigentlichen Formular-Request) - "gform_after_submission"
   feuert dafür zu früh, der Post existiert an der Stelle noch nicht (weder
   beim Erstellen noch beim Aktualisieren). Deshalb ausschließlich diese
   beiden eigenen Hooks des Add-Ons nutzen, die erst feuern, wenn der Post
   wirklich (an)gelegt wurde.
   Dieser Helper registriert einen Callback der Form ( $post_id, $entry )
   einheitlich für beide Fälle, damit post-verarbeitende Logik (ACF-Felder,
   Featured Image, Excerpt, Render-Webhook) nicht separat für Create und
   Edit dupliziert werden muss - sonst funktioniert eine solche Funktion
   nur beim Erstellen und schweigt beim Bearbeiten (genau das Bug-Muster,
   das uns beim Raw-Video-Webhook und jetzt beim Featured Image begegnet
   ist). */
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
        // 2026-07-19 jdev: $post->ID ist beim Update-Hook in der Praxis 0
        // (vermutlich ein APC-Bug) - der Entry selbst kennt die echte
        // Post-ID zuverlässig über entry['post_id'], das nutzen wir zuerst.
        $post_id = (int) rgar( $entry, 'post_id' );
        if ( ! $post_id && $post ) {
            $post_id = (int) rgar( (array) $post, 'ID' );
        }
        if ( $post_id ) {
            call_user_func( $callback, $post_id, $entry );
        }
    }, 10, 3 );
}

/* 2026-07-13 jdev Die ausgewählten User-IDs aus Feld 6/7 korrekt in die
   ACF-Felder "video_monkeys" / "pattern_author" schreiben (per update_field
   statt roher Custom-Field-Mapping, damit ACF die Felder im Backend wieder
   erkennt und richtig anzeigt). */
pz_gf_on_pattern_post_saved( 'pz_gf_save_user_selects_to_acf' );
function pz_gf_save_user_selects_to_acf( $post_id, $entry ) {
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

/* 2026-07-18 jdev Gravity Forms (Form 4) Entries-Liste im Backend: Feld 6
   ("Monkeys") und Feld 7 ("Pattern Author") speichern User-IDs als JSON-Array
   (siehe pz_gf_save_user_selects_to_acf oben). Die per gform_*_pre_render_4
   gesetzten dynamischen Choices lösen die IDs zwar im Formular selbst und in
   der Entry-Detailansicht zu Namen auf, NICHT aber in der Entries-Liste -
   dort rendert Gravity Forms die Spalte direkt aus dem rohen Entry-Wert.
   Zusätzlich zeigt die eingebaute "Created By"-Spalte (= post_author, der
   einreichende, angemeldete User) dort ebenfalls nur die rohe User-ID statt
   des Namens. Deshalb hier beides manuell in Anzeigenamen auflösen. */
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

/* 2026-07-19 jdev Feld 1 ("Pattern name") ist vom Feldtyp "title" (Post
   Title) - dieser Feldtyp bietet in der GF-Oberfläche keine "Max
   Characters"-Option (im Gegensatz zu Single Line Text), deshalb hier per
   Code auf 25 Zeichen begrenzen. */
add_filter( 'gform_field_validation_4_1', 'pz_gf_limit_pattern_name_length', 10, 4 );
function pz_gf_limit_pattern_name_length( $result, $value, $form, $field ) {
    if ( mb_strlen( trim( (string) $value ) ) > 25 ) {
        $result['is_valid'] = false;
        $result['message']  = 'Pattern name darf maximal 25 Zeichen lang sein.';
    }
    return $result;
}

/* 2026-07-19 jdev Auf der "Pattern bearbeiten"-Seite (Edit Post Page von
   Form 4) sind einzelne Felder (z.B. Raw Video/Feld 19, Audiofile/Feld 20)
   zwar über die Post Editing Settings als editierbar markiert, bleiben aber
   Pflichtfelder in Form 4 selbst - beim Bearbeiten OHNE Änderung an so einem
   Feld wirft GF deshalb einen "Feld erforderlich"-Fehler, obwohl am Post
   schon ein Wert steht. Deshalb "Required" pauschal für ALLE Felder nur auf
   der Edit-Seite deaktivieren: leer lassen = bestehenden Wert behalten,
   ausfüllen/hochladen = ersetzen. Auf der Create-Seite (bzw. überall sonst)
   bleiben die Felder wie im Formular konfiguriert Pflicht. */
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

/* 2026-07-13 jdev Ein einziges Upload-Feld (Field 5, "Pattern Image") für
   sowohl das native WordPress Featured Image als auch das ACF-Feld
   "pattern_image" nutzen - damit man nicht zwei separate Upload-Felder im
   Formular braucht. Feld 5 bleibt daher im Feed selbst UNGEMAPPT (weder unter
   Featured Image noch unter Custom Fields), diese Funktion übernimmt beides.
   Die von GF hochgeladene Datei liegt bereits lokal im gravity_forms-Ordner;
   wir erstellen daraus ein echtes Attachment in der Mediathek (statt per
   media_sideload_image() nochmal per HTTP zu laden - unnötig fragil wegen
   der Cloudflare-Loopback-Problematik).
   2026-07-19 jdev: Weil Feld 5 im Feed ungemappt ist, taucht es in den
   Post Editing Settings ("welche Felder sind beim Bearbeiten editierbar")
   vermutlich gar nicht als Option auf und bleibt deshalb auf der Edit-Seite
   deaktiviert - ein neuer Upload kommt so nie im Entry an. Hier deshalb die
   editierbare-Felder-Liste von Advanced Post Creation per Code um Feld 5
   ergänzen, unabhängig davon, ob es in der UI wählbar ist. */
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

/* 2026-07-19 jdev Nach dem Erstellen/Aktualisieren des "pattern"-Posts: Raw
   Video (Field 19) und Audiofile (Field 20) als geschützte (unterstrich-
   prefixte, nicht im Custom-Fields-Metabox sichtbare) Postmeta speichern -
   kein Frontend-Display vorgesehen - und zusammen mit Titel/Ort/Monkeys/Bild
   per Webhook an den Hetzner-Renderserver schicken.
   Läuft bewusst über die eigenen Hooks von Advanced Post Creation statt über
   das Gravity Forms Webhooks Add-on: Webhooks feuert synchron direkt beim
   Submit, Advanced Post Creation verarbeitet seinen Feed aber asynchron im
   Hintergrund - zu dem Zeitpunkt existiert post_id am Entry noch nicht.
   Der Webhooks-Add-on-Feed für Form 4 ist deshalb deaktiviert, sonst gingen
   zwei Requests raus. */
pz_gf_on_pattern_post_saved( 'pz_gf_send_render_webhook' );
function pz_gf_send_render_webhook( $post_id, $entry ) {
    $raw_video_url  = pz_gf_first_file_upload_url( rgar( $entry, '19' ) );
    $audio_file_url = pz_gf_first_file_upload_url( rgar( $entry, '20' ) );

    // Beim Editieren ist Feld 19/20 nicht mehr Pflicht (siehe
    // pz_gf_optional_on_edit_page) - kommt kein neuer Upload mit, bleibt der
    // vorher gespeicherte Wert erhalten statt ihn mit einem leeren String zu
    // überschreiben.
    if ( '' !== $raw_video_url ) {
        update_post_meta( $post_id, '_pz_raw_video_url', $raw_video_url );
    } else {
        $raw_video_url = get_post_meta( $post_id, '_pz_raw_video_url', true );
    }
    if ( '' !== $audio_file_url ) {
        update_post_meta( $post_id, '_pz_audio_file_url', $audio_file_url );
    } else {
        $audio_file_url = get_post_meta( $post_id, '_pz_audio_file_url', true );
    }
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

/* 2026-07-13 jdev Sicherheitsnetz: falls ein User (z.B. durch fehlerhafte GF-Konfiguration
   oder manuelle Anlage) ohne Anzeigename landet, "Vorname Nachname-Initiale." als Fallback setzen.
   Greift NICHT ein, wenn der User bereits einen (auch freien) Anzeigenamen gesetzt hat. */
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

/* 2026-07-13 jdev Prefill des "Profil bearbeiten"-Formulars mit den Daten des
   eingeloggten Users, statt Gravity Forms per Query-String zu befüllen.
   Parameter-Namen müssen im Formular (Feld > Advanced > "Allow field to be
   populated dynamically" > Parameter Name) exakt so gesetzt werden. */
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

/* 2026-07-16 jdev Gravity Forms (Form 1, Registrierung): kein separates
   "Username"-Feld mehr - der Username wird automatisch aus Vor- und Nachname
   (Feld 1, Advanced-Name: 1.3 = Vorname, 1.6 = Nachname) als "vorname.nachname"
   erzeugt. Umlaute werden per remove_accents() transliteriert (ä->a, ö->o,
   ü->u, ß->ss), damit sanitize_user() sie nicht einfach verwirft. Mehrteilige
   Vornamen (z.B. "Anna Lena") werden mit Bindestrich verbunden, mehrteilige
   Nachnamen (z.B. "de Vries") mit Punkt - so bleibt "vorname.nachname" immer
   eindeutig als das trennende Element zwischen Vor- und Nachname erkennbar.
   Ist der Username bereits vergeben, wird eine fortlaufende Nummer angehängt.
   Die "Username"-Zuordnung im User-Registration-Feed kann auf ein beliebiges
   vorhandenes Feld (z.B. E-Mail) zeigen - dieser Filter überschreibt den Wert
   ohnehin immer. */
add_filter( 'gform_username_1', 'pz_gf_generate_username_from_name', 10, 4 );
function pz_gf_generate_username_from_name( $username, $feed, $form, $entry ) {
    $first_name = trim( remove_accents( rgar( $entry, '1.3' ) ) );
    $last_name  = trim( remove_accents( rgar( $entry, '1.6' ) ) );

    // Sicherheitsnetz: falls 1.3/1.6 bei der Übermittlung leer ankommen (z.B.
    // durch einen JS-Reload oder eine fehlgeschlagene Client-Validierung, bei
    // der die Namensfelder nicht neu befüllt wurden), auf Feld 1 als Ganzes
    // zurückfallen und am ersten Leerzeichen in Vor-/Nachname trennen.
    // Verhindert, dass ein leerer Vor-/Nachname zu einem Username aus nur
    // einem Punkt führt (siehe Bug 2026-07-20 bei "Tine Oymann").
    if ( '' === $first_name && '' === $last_name ) {
        $full_name = trim( remove_accents( rgar( $entry, '1' ) ) );
        if ( '' !== $full_name ) {
            $parts      = preg_split( '/\s+/', $full_name, 2 );
            $first_name = $parts[0];
            $last_name  = isset( $parts[1] ) ? $parts[1] : '';
        }
    }

    // Mehrfache/innere Leerzeichen bei mehrteiligen Namen vereinheitlichen,
    // bevor sie mit Bindestrich (Vorname) bzw. Punkt (Nachname) verbunden werden.
    $first_part = preg_replace( '/\s+/', '-', $first_name );
    $last_part  = preg_replace( '/\s+/', '.', $last_name );

    // trim() entfernt einen führenden/nachgestellten Punkt, falls Vor- oder
    // Nachname leer ist - sonst würde z.B. bei fehlendem Nachnamen ".vorname"
    // oder bei fehlendem Vornamen "nachname." als Username entstehen.
    $base_username = sanitize_user( strtolower( trim( $first_part . '.' . $last_part, '.' ) ), true );

    // Letzter Fallback, falls gar kein Name ermittelt werden konnte: eindeutigen
    // Username aus der Entry-ID bilden, statt einen leeren/ungültigen Username
    // an wp_create_user() durchzureichen (führt sonst zu "Cannot create a user
    // with an empty nicename").
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

/* 2026-07-20 jdev REST-API-Discovery-Link fuer Autoren entfernen: Die "Monkeys"/
   "Pattern Author"-User werden nicht als echter post_author gefuehrt (Zuordnung
   laeuft ueber ACF, siehe pz_gf_populate_user_choices), daher stuft WP sie als
   "nicht oeffentlich" ein und /wp-json/wp/v2/users/{id} liefert fuer anonyme
   Requests 404 - obwohl WP Core den Link trotzdem in <head> und im HTTP-Link-
   Header jeder Autoren-Seite ausgibt (rest_output_link_wp_head /
   rest_output_link_header). Passt zur bestehenden REST-User-Enumeration-Sperre
   in der .htaccess: der Link wird konsequent gar nicht erst beworben. */
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

/* 2026-07-21 jdev Nach dem Login alle Mitglieder bis einschließlich author
   direkt zum Pattern-Upload schicken, statt ins wp-admin-Dashboard bzw. zu
   my-account. "edit_others_posts" haben nur editor/administrator, nicht
   author - wer sie hat, landet weiterhin ganz normal im Backend. */
add_filter( 'login_redirect', 'pz_redirect_members_after_login', 10, 3 );
function pz_redirect_members_after_login( $redirect_to, $requested_redirect_to, $user ) {
    if ( $user instanceof WP_User && ! $user->has_cap( 'edit_others_posts' ) ) {
        return 'https://passing.zone/upload-a-new-pattern/';
    }
    return $redirect_to;
}
