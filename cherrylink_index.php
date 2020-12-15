<?php 
/*
 * Linkate Posts
 */
 
define('LINKATE_INDEX_LIBRARY', true);

// ========================================================================================= //
// ============================== CherryLink Links Index  ============================== //
// ========================================================================================= //


add_action('wp_ajax_linkate_ajax_call_reindex', 'linkate_ajax_call_reindex');
function linkate_ajax_call_reindex() {

	$options = get_option('linkate-posts');
	// Fill up the options with the values chosen...
	$options = link_cf_options_from_post($options, array('term_length_limit', 'clean_suggestions_stoplist', 'suggestions_donors_src', 'suggestions_donors_join'));
    update_option('linkate-posts', $options);

    $options_meta = get_option('linkate_posts_meta');
    $options_meta['indexing_process'] = 'IN_PROGRESS';
    update_option('linkate_posts_meta', $options_meta);

    linkate_posts_save_index_entries ();
	wp_die();
}

add_action('wp_ajax_linkate_get_posts_count_reindex', 'linkate_get_posts_count_reindex');
function linkate_get_posts_count_reindex() {
	global $wpdb;
	$amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item', 'wp_block')");

    echo $amount_of_db_rows;
    // echo 10;
	wp_die();
}

// sets up the index for the blog
function linkate_posts_save_index_entries ($is_initial = false) {
    $EXEC_TIME = microtime(true);
	global $wpdb, $table_prefix;
    $options = get_option('linkate-posts');
    $options_meta = get_option('linkate_posts_meta');
    
    if ($is_initial) {
        $amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item', 'wp_block')");
        if ($amount_of_db_rows > CHERRYLINK_INITIAL_LIMIT)   
            return false; 
    }

	$batch = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 200;
    $reindex_offset = isset($_POST['index_offset']) ? (int)$_POST['index_offset'] : 0;
    $index_posts_count = isset($_POST['index_posts_count']) ? (int)$_POST['index_posts_count'] : $amount_of_db_rows;

    require_once (WP_PLUGIN_DIR . "/cherrylink/cherrylink_stemmer_ru.php");

	$stemmer = new Stem\LinguaStemRu();
	$suggestions_donors_src = $options['suggestions_donors_src'];
	$suggestions_donors_join = $options['suggestions_donors_join'];
	$clean_suggestions_stoplist = $options['clean_suggestions_stoplist'];
	$min_len = $options['term_length_limit'];

	$words_table = $table_prefix."linkate_stopwords";
	$black_words = array_filter($wpdb->get_col("SELECT stemm FROM $words_table WHERE is_white = 0 GROUP BY stemm"));
	$white_words = array_filter($wpdb->get_col("SELECT word FROM $words_table WHERE is_white = 1"));
	$linkate_overusedwords["black"] = array_flip($black_words);
	$linkate_overusedwords["white"] = array_flip($white_words);

	$table_name = $table_prefix.'linkate_posts';

	// Truncate table on first call
	if ($reindex_offset == 0) {
		$wpdb->query("TRUNCATE `$table_name`");
	}
	
	$common_words = array();
	$values_string = '';

	// TERMS
	// Reindex terms on first call ONLY
	if ($reindex_offset == 0) {
		$start = 0;
		$terms_batch = 50;
		$amount_of_db_rows = $index_posts_count + $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->terms");

		while ($terms = $wpdb->get_results("SELECT `term_id`, `name` FROM $wpdb->terms LIMIT $start, $terms_batch", ARRAY_A)) {
			reset($terms);
			foreach ($terms as $term) {
				$termID = $term['term_id'];

				$descr = term_description($termID); // standart 
                // custom plugins sp-category && f-cattxt
                $aio_title = '';
				if (function_exists('show_descr_top') || function_exists('contents_sp_category')) {
                    $opt = get_option('category_'.$termID);
                    if ($opt) {
                        $descr .= $opt['descrtop'] ? ' '.$opt['descrtop'] : '';  
                        $descr .= $opt['descrbottom'] ? ' '.$opt['descrbottom'] : '';  
                        $aio_title = $opt['title'];
                    }
				}

				list($content, $content_sugg) = linkate_sp_get_post_terms($descr, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
				//Seo title is more relevant, usually
				//Extracting terms from the custom titles, if present
				$title = '';
				if (function_exists('wpseo_init')) {
                    $yoast_opt = get_option('wpseo_taxonomy_meta');
                    if ($yoast_opt && $yoast_opt['category']) {
                        $title = $yoast_opt['category'][$termID]['wpseo_title'];
                    }
                } else if ($aio_title && function_exists('show_descr_top')) {
                    $title = $aio_title;
                } 
                
				if ($title !== $term['name']) {
					$title = $term['name'] . " " . $title;
				} 

				list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );

				// Extract ancor terms
				$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);

                $tags = "";
                
                // Create query
                if (!empty($values_string)) $values_string .= ',';
                $values_string .= "($termID, \"$content\", \"$title\", \"$tags\", 1, \"$suggestions\")";
            }

            $wpdb->query("INSERT INTO `$table_name` (pID, content, title, tags, is_term, suggestions) VALUES $values_string");
            $values_string = '';

			$start += $terms_batch;
		}
        unset($terms);
        $wpdb->flush();
	}
	// POSTS
	$posts = $wpdb->get_results("SELECT `ID`, `post_title`, `post_content`, `post_type` 
									FROM $wpdb->posts 
									WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item', 'wp_block') 
									LIMIT $reindex_offset, $batch", ARRAY_A);
    reset($posts);
    
    $values_string = '';
    // Save overused words TODO
    
    if (isset($options['overused_words_temp'])) $common_words = $options['overused_words_temp'];

	foreach ($posts as $post) {
        $postID = $post['ID'];

		list($content, $content_sugg) = linkate_sp_get_post_terms($post['post_content'], $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);

		// convert broken symbols
		$content = iconv("UTF-8", "UTF-8//IGNORE", $content); 
		if (!$content)
			$content = '';

		// Check SEO Fields
		$seotitle = '';
		if (function_exists('wpseo_init')){
			$seotitle = linkate_decode_yoast_variables($postID);
		}
		if (function_exists( 'aioseop_init_class' )){
			$seotitle = get_post_meta( $postID, "_aioseop_title", true);
		}

		// Title for suggestions
		if (!empty($seotitle) && $seotitle !== $post['post_title']) {
			$title = $post['post_title'] . " " . $seotitle;
		} else {
			$title = $post['post_title'];
		}

		list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );

        // Extract ancor suggestions
		$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);

		// Tags (useless)
		$tags = linkate_sp_get_tag_terms($postID);
		
		// Create query
		if (!empty($values_string)) $values_string .= ',';
		$values_string .= "(".$postID.", \"".$content."\", \"".$title."\", \"".$tags."\", \"".$suggestions."\")";

		$word = strtok($content, ' ');
		while ($word !== false) {
            if(!array_key_exists($word,$common_words)){
                $common_words[$word]=0;
            }
            $common_words[$word] += 1;
            $word = strtok(' ');
        }

		// fix memory leak
		wp_cache_delete( $postID, 'post_meta' );
        unset($content);
        unset($title);
        unset($seotitle);
        unset($title_sugg);
        unset($content_sugg);
        unset($suggestions);
	}

    $wpdb->flush();
    // Insert into DB
    $wpdb->query("INSERT INTO `$table_name` (pID, content, title, tags, suggestions) VALUES $values_string");
    //$wpdb->flush();

    $wpdb_error = $wpdb->last_error;
    $wpdb_query = $wpdb->last_query;
    $wpdb->flush();
    
    arsort($common_words);
    $common_words = array_slice($common_words, 0 , 100);
    // Temporarely store overused words for the future 
    $options['overused_words_temp'] = $common_words;
    update_option( 'linkate-posts', $options );

	// SCHEME
	linkate_create_links_scheme($reindex_offset, $batch);

	// Output for frontend
	$ajax_array = array();
	$ajax_array['status'] = 'OK';
    $time_elapsed_secs = microtime(true) - $EXEC_TIME;
    $ajax_array['time'] = number_format($time_elapsed_secs, 5);
    $ajax_array['wpdb_error'] = $wpdb_error;
    $ajax_array['wpdb_query'] = $wpdb_query;
    
    if ($reindex_offset + $batch >= $index_posts_count) {
        $options_meta['indexing_process'] = 'DONE';
        update_option('linkate_posts_meta', $options_meta);
        $ajax_array['status'] = 'DONE';
    }
    unset($suggestions_donors_src);
	unset($suggestions_donors_join);
	unset($clean_suggestions_stoplist);
	unset($min_len);
    unset($posts);
    unset($values_string);
    unset($common_words);
    unset($options);
    unset($options_meta);
    unset($black_words);
    unset($white_words);
    unset($linkate_overusedwords);
    unset($stemmer);

    if (!$is_initial)
        echo json_encode($ajax_array);
        
    unset($ajax_array);

	return true;
}

add_action('wp_ajax_linkate_last_index_overused_words', 'linkate_last_index_overused_words');
function linkate_last_index_overused_words() {
	// UPDATE SCHEME TIMESTAMP HERE CUZ WE ARE CREATING IT TOGETHER
	linkate_scheme_update_option_timestamp();

	$ajax_array = array();
	// Add overused words
	$existing_blacklist = array_flip(array_filter(linkate_get_blacklist(true)));
	$options = get_option( 'linkate-posts' );

	if (isset($options['overused_words_temp']))
		$common_words = $options['overused_words_temp'];
	else {
		// send empty array
		$ajax_array['common_words'] = '';
		echo json_encode($ajax_array);
		wp_die();
	}

	// Remove words which already in the blacklist
	arsort($common_words);
	$sw_count = 30;
	foreach ($common_words as $k => $v) {
		if ($sw_count == 0) break;
		if (!isset($existing_blacklist[$k])) {
			$ajax_array['common_words'][] = array('word' => $k, 'count' => $v);
			$sw_count--;
		} 
	}

    unset($existing_blacklist);

	// Remove temp words from options
	unset($options['overused_words_temp']);
	update_option( 'linkate-posts', $options );


	// Send words
	echo json_encode($ajax_array);
	wp_die();

}

// ========================================================================================= //
// ============================== CherryLink Scheme Creation  ============================== //
// ========================================================================================= //

//add_action('wp_ajax_linkate_create_links_scheme', 'linkate_create_links_scheme');
function linkate_create_links_scheme($offset = 0, $batch = 200) {
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');

	$table_name_scheme = $table_prefix.'linkate_scheme';
	// Truncate on first call
	if ($offset == 0) {
		$wpdb->query("TRUNCATE `$table_name_scheme`");
	}

	// TERM SCHEME on FIRST CALL ONLY
	if ($offset == 0) {
		// $amount_of_db_rows = $amount_of_db_rows + $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->terms");
		//doing the same with terms (category, tag...)
        $start = 0;
        
		while ($terms = $wpdb->get_results("SELECT `term_id` FROM $wpdb->terms LIMIT $start, $batch", ARRAY_A)) {
            $query_values = array();
			reset($terms);
			foreach ($terms as $term) {
				$termID = $term['term_id'];
	
				$descr = '';
				$descr .= term_description($termID); // standart
				// custom plugins sp-category && f-cattxt
				$opt = get_option('category_'.$termID);
				if ($opt && (function_exists('show_descr_top') || function_exists('contents_sp_category'))) {
					$descr .= $opt['descrtop'] ? ' '.$opt['descrtop'] : '';
					$descr .= $opt['descrbottom'] ? ' '.$opt['descrbottom'] : '';
				}
	
                // linkate_scheme_add_row($descr, $termID, 1);
                $query_values[] = linkate_scheme_get_add_row_query($descr, $termID, 1);
            }
            $query_values = array_filter($query_values);

            if (!empty($query_values)) {
                $query_values = implode(",", $query_values);
                $wpdb->query("INSERT INTO `$table_name_scheme` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $query_values");
            }

			$start += $batch;
		}
        unset($terms);
        $wpdb->flush();
	}

	$posts = $wpdb->get_results("SELECT `ID`, `post_content`, `post_type` 
									FROM $wpdb->posts 
									WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item') 
									LIMIT $offset, $batch", ARRAY_A);
	reset($posts);

    $query_values = array();
	foreach($posts as $post) {
		$postID = $post['ID'];
        $query_values[] = linkate_scheme_get_add_row_query($post['post_content'], $postID, 0);
        // linkate_scheme_add_row($post['post_content'], $postID, 0);
        
    }
    $query_values = array_filter($query_values);

    if (!empty($query_values)) {
        $query_values = implode(",", $query_values);
        $wpdb->query("INSERT INTO `$table_name_scheme` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $query_values");
    }
    unset($options);
	unset($query_values);
    unset($posts);
    $wpdb->flush();
}

function linkate_scheme_update_option_timestamp() {
	$options = get_option('linkate-posts');
	$options['linkate_scheme_exists'] = true;
	$options['linkate_scheme_time'] = time();

	update_option('linkate-posts', $options);
}