<?php
/*
Дополнение для CherryLink, добавляющее настраиваемые блоки с ссылками для перелинковки статей между собой.
*/

define('LINKATE_CRB_LIBRARY', true);

class CL_Related_Block {

	const TEMP_BEFORE = "<span class='crb-header'>Читайте далее:</span><div class='crb-container'>";
	const TEMP_AFTER = "</div>";
	const TEMP_LINK = "<div class='crb-item-container'><a href='{url}' target='_blank'><img src='{imagesrc}'><p>{title}</p></a></div>";

    static function get_version() {
        $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');
        return $plugin_data['version'];
    }

    static function get_links() {
        if (!function_exists('linkate_posts'))
            return 'Не найден плагин CherryLink';
        global $post;
           
        $options = get_option('linkate-posts');
        $is_term = 0;
        $offset=0;
        $hide_existing = $options['crb_hide_existing_links'] == 'true' ? 1 : 0;
        $show_latest = $options['crb_show_latest'] == 'true' ? 1 : 0;
        $num_of_links = intval($options['crb_num_of_links_to_show']);
        $excluded = '';
        if ($post) {
            $included_posts = CL_RB_Metabox::get_custom_posts($post->ID);
            if ($hide_existing) {
                $excluded = CL_Related_Block::get_posts_to_exclude($post->ID);
            }
        }
        $args = '';
        if ($included_posts) { // if custom selection
            $args = "manual_ID=" . $post->ID . "&is_term=" . $is_term . "&offset=" . $offset . "&relevant_block=1&included_posts=" . $included_posts . "&ignore_relevance=true&";
        } else if (!$included_posts && $show_latest) { // show latest
            $ids = self::get_latest_posts_ids($post->ID, $options, $num_of_links);
            $args = "manual_ID=" . $post->ID . "&is_term=" . $is_term . "&offset=" . $offset . "&relevant_block=1&included_posts=" . $ids . "&ignore_relevance=true&";
        } else { // show related links
            $args = "manual_ID=".$post->ID."&is_term=".$is_term."&offset=".$offset."&relevant_block=1&excluded_posts=".$excluded."&limit_ajax=".$num_of_links."&";
        }
        


        if (!isset($options['crb_cache_minutes'])) $options['crb_cache_minutes'] = 1440;
        // Get relevant results
        if ( false === ( $output = get_transient( "crb__".$args ) ) ) {
            // It wasn't there, so regenerate the data and save the transient
            $output = linkate_posts($args);
            set_transient( "crb__".$args, $output, $options['crb_cache_minutes'] * MINUTE_IN_SECONDS );
        }

//        $output = linkate_posts($args);

        // If empty - ignore relevance
        if (!$output || empty($output)) {
            $args .= "ignore_relevance=true&show_pages=false&";
            if ( false === ( $output = get_transient( "non_rel_crb__".$args ) ) ) {
                // It wasn't there, so regenerate the data and save the transient
                $output = linkate_posts($args);
                set_transient( "non_rel_crb__".$args, $output, $options['crb_cache_minutes'] * MINUTE_IN_SECONDS );
            }
        }
        return $output;
    }

    static function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE `option_name` LIKE ('%crb\_\_%')");
    }

    static function get_latest_posts_ids($curr_id, $options, $limit) {
        global $wpdb;
        if ($curr_id == null) $curr_id = 0;
        $show_customs = $options['show_customs'];
        if (!empty($show_customs)) {
            $customs = explode(',', $show_customs);
            foreach ($customs as $value) {
                $typelist[] = "'".$value."'";
            }
        }
        $typelist[] = "'post'";

        if (count($typelist) === 1) {
            $sql = " AND post_type=$typelist[0]";
        } else {
            $sql = " AND post_type IN (" . implode(',',$typelist). ")";
        }

        $sql .= " AND post_status='publish' ";

        $ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE ID <> $curr_id $sql ORDER BY ID DESC LIMIT $limit");

        if ($ids) {
            return implode(",", $ids);
        } else {
            return '';
        }
    }

    static function prepare_related_block($postid, $results, $option_key, $options) {
        // TEMPLATES
        $output_template_item_prefix = isset($options['crb_temp_before']) ? stripslashes(urldecode(base64_decode($options['crb_temp_before']))) : self::TEMP_BEFORE;
        $output_template_item_suffix = isset($options['crb_temp_after']) ? stripslashes(urldecode(base64_decode($options['crb_temp_after']))) : self::TEMP_AFTER;
        $item_template = isset($options['crb_temp_link']) ? stripslashes(urldecode(base64_decode($options['crb_temp_link']))) : self::TEMP_LINK;

        if ($results) {
            // IF CUSTOM MANUAL ANKORS - REPLACE {title} or {title_seo} with them HERE
            $use_manual_titles = get_post_meta( $postid, "crb-meta-use-manual", true);
            if ($use_manual_titles === "checked") {
                $meta_titles = explode("\n", get_post_meta( $postid, "crb-meta-links", true));
                foreach($meta_titles as $line) {
                    $temp = explode("[|]", $line);

                    if ($temp[2] === "undefined" || !isset($temp[2]) || empty(trim($temp[2]))) {
                        // manual title is not present, using standart title/h1
                        $id_titles[$temp[0]] = $temp[1];
                    } else {
                        $id_titles[$temp[0]] = $temp[2];
                    }
                }
            }

            $translations = link_cf_prepare_template($item_template);
            $items = array();
            foreach ($results as $result) {
                if (isset($id_titles))
                    $result->manual_title = $id_titles[$result->ID];
                $items[] = link_cf_expand_template($result, $item_template, $translations, $option_key);
            }
            if ($options['sort']['by1'] !== '') $items = link_cf_sort_items($options['sort'], $results, $option_key, $options['group_template'], $items);
            $output = $output_template_item_prefix.implode("\n", $items).$output_template_item_suffix;

        } else {
            // we display the blank message, with tags expanded if necessary
            $translations = link_cf_prepare_template($options['none_text']);
            $output = "";
        }
        return $output;
    }

    // If table exists we can exclude used links
    static function scheme_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix."linkate_scheme";
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
            return true;
        } else {
            return false;
        }
    }

    static function get_posts_to_exclude($post_id) {
        global $wpdb;
        if (CL_Related_Block::scheme_table_exists()) {
            $tablename = $wpdb->prefix."linkate_scheme";
            $results = $wpdb->get_col("SELECT target_id FROM $tablename WHERE source_id = $post_id AND target_id > 0");
            if ($results) {
                $results = array_filter($results, function ($el) {
                    return !empty(trim($el));
                });
                return implode(",", $results);
            } else {
                return '';
            }
        }
        return '';
    }

    // add links after content for single posts
    static function add_after_content( $content ) {
        global $post;
        $options = get_option('linkate-posts');
        if( (is_single() || ($options['crb_show_for_pages'] == 'true' && is_page()) ) && ! empty( $GLOBALS['post'] ) && in_the_loop() && is_main_query() ) {
            if ( $GLOBALS['post']->ID == get_the_ID()) {
                if ($options['crb_show_after_content'] == 'true' && CL_RB_Metabox::get_custom_show(get_the_ID())) {
                    $content .= CL_Related_Block::get_links();
                    //remove_filter( current_filter(), __FUNCTION__ );
                }
            }
        }
        return $content;
    }

    static function fill_options($after_update = false) {
        $options = get_option('linkate-posts');
        if (!isset($options['crb_installed']) || isset($_POST['crb_defaults'])) {
            $options['crb_show_after_content'] = "false";
            $options['crb_hide_existing_links'] = "true";
            $options['crb_show_for_pages'] = "false";
            $options['crb_show_latest'] = "false";
            $options['crb_css_tuning'] = "default";
            $options['crb_num_of_links_to_show'] = 5;
            $options['crb_cache_minutes'] = 1440;
            $options['crb_temp_before'] = base64_encode(urlencode(self::TEMP_BEFORE));
            $options['crb_temp_link'] = base64_encode(urlencode(self::TEMP_LINK));
            $options['crb_temp_after'] = base64_encode(urlencode(self::TEMP_AFTER));
            $options['crb_image_size'] = 'thumbnail';
            $options['crb_placeholder_path'] = '';
            $options['crb_content_filter'] = 0;
            $options['crb_choose_template'] = 'crb-template-simple.css';
            $options['crb_css_override'] = array('desc' => array('columns' => 3, 'gap' => 20), 'mob' => array('columns'=> 2, 'gap' => 10));

            $options['crb_installed'] = "true";
            update_option('linkate-posts', $options);
        }

        // on plugin update check existing options and add missing
        if ($after_update == true) {
            $options['crb_show_after_content'] = isset($options['crb_show_after_content']) ? $options['crb_show_after_content'] : "false";
            $options['crb_hide_existing_links'] =  isset($options['crb_hide_existing_links']) ? $options['crb_hide_existing_links'] : "true";
            $options['crb_show_for_pages'] = isset($options['crb_show_for_pages']) ? $options['crb_show_for_pages'] : "false";
            $options['crb_show_latest'] = isset($options['crb_show_latest']) ? $options['crb_show_latest'] : "false";
            $options['crb_css_tuning'] = isset($options['crb_css_tuning']) ? $options['crb_css_tuning'] : "default";
            $options['crb_num_of_links_to_show'] = isset($options['crb_num_of_links_to_show']) ? $options['crb_num_of_links_to_show'] : 5;
            $options['crb_cache_minutes'] = isset($options['crb_cache_minutes']) ? $options['crb_cache_minutes'] : 1440;
            $options['crb_temp_before'] = isset($options['crb_temp_before']) ? $options['crb_temp_before'] : base64_encode(urlencode(self::TEMP_BEFORE));
            $options['crb_temp_link'] = isset($options['crb_temp_link']) ? $options['crb_temp_link'] : base64_encode(urlencode(self::TEMP_LINK));
            $options['crb_temp_after'] = isset($options['crb_temp_after']) ? $options['crb_temp_after'] : base64_encode(urlencode(self::TEMP_AFTER));
            $options['crb_image_size'] = isset($options['crb_image_size']) ? $options['crb_image_size'] : 'thumbnail';
            $options['crb_placeholder_path'] = isset($options['crb_placeholder_path']) ? $options['crb_placeholder_path'] : '';
            $options['crb_content_filter'] = isset($options['crb_content_filter']) ? $options['crb_content_filter'] : 0;
            $options['crb_choose_template'] = isset($options['crb_choose_template']) ? $options['crb_choose_template'] : 'crb-template-old.css';
            $options['crb_css_override'] = isset($options['crb_css_override']) ? $options['crb_css_override'] : array('desc' => array('columns' => 3, 'gap' => 20), 'mob' => array('columns'=> 2, 'gap' => 10));

            update_option('linkate-posts', $options);
        }
    }

    static function meta_assets() {
        $options = get_option('linkate-posts');

        $template = isset($options['crb_choose_template']) ? $options['crb_choose_template'] : 'crb-template-simple.css';
        if ($template == 'none')
            return false; // don't load any

        if ($options['crb_css_tuning'] == 'important')
            $template = str_replace('.css', '-important.css', $template);

        wp_register_style( 'crb-template', plugins_url( '/css/'.$template, __FILE__ ), '', CL_Related_Block::get_version() );
        wp_enqueue_style ('crb-template');
    }

    static function meta_assets_override() {
        $options = get_option('linkate-posts');
        wp_register_style( 'crb-template-override', plugins_url( '/css/crb-template-admin-options.css', __FILE__ ), '', CL_Related_Block::get_version() );
        wp_enqueue_style ('crb-template-override');

        $desc_cols = array();
        for ($i = 0; $i < intval($options['crb_css_override']['desc']['columns']); $i++) {
            $desc_cols[] = '1fr';
        }
        $mob_cols = array();
        for ($i = 0; $i < intval($options['crb_css_override']['mob']['columns']); $i++) {
            $mob_cols[] = '1fr';
        }
        $custom_css = "
                .crb-container {
                    display: grid !important;
                    grid-template-columns: ". implode(' ', $desc_cols) ." !important;
                    grid-column-gap: " . $options['crb_css_override']['desc']['gap'] ."px !important; 
                }
                @media screen and (max-width: 40em) {
                .crb-container {
                    grid-template-columns: ". implode(' ', $mob_cols) ." !important;
                    grid-column-gap: " . $options['crb_css_override']['mob']['gap'] ."px !important; 
                }
            }";
        wp_add_inline_style( 'crb-template-override', $custom_css );
    }

    static function admin_assets() {
        self::meta_assets();
        wp_register_script( 'crb-script-admin', plugins_url( '/js/crb-admin.js', __FILE__ ), array( 'jquery' ), CL_Related_Block::get_version() . '-' . rand(1000, 10000) );
        wp_enqueue_script( 'crb-script-admin' );
    }
}

// Alias to use in theme templates
if (!function_exists("cherrylink_related_block")) {
    function cherrylink_related_block() {
        // check individual settings
        if (CL_RB_Metabox::get_custom_show(get_the_ID()))
            return CL_Related_Block::get_links();
        else
            return '';
    }
}

function _crb_init() {
    // Initial setup
    CL_Related_Block::fill_options();

    // Append to content if needed
    add_filter('the_content', array('CL_Related_Block','add_after_content'));
    

    // Register shortcode
    add_shortcode( 'crb_show_block', 'cherrylink_related_block' );

    // Include styles & scripts
	add_action('wp_enqueue_scripts', array('CL_Related_Block','meta_assets'), 100);
	add_action('admin_enqueue_scripts', array('CL_Related_Block','admin_assets'), 100);

	add_action('wp_enqueue_scripts', array('CL_Related_Block','meta_assets_override'), 200);
	add_action('admin_enqueue_scripts', array('CL_Related_Block','meta_assets_override'), 200);

    // Include deps
    include('crb_metabox.php');
    include('crb_admin.php');

    // Enable Metaboxes
    _crb_metabox_init();

    // Setup updater
    // crb_config_updater();

    // After update action
    // add_action( 'upgrader_process_complete', 'crb_upgrade_function', 10, 2);
}


// Run plugin
_crb_init();
