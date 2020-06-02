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
	$amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item')");

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
        $amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item')");
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
									WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item') 
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
    
    if ($reindex_offset + $batch > $index_posts_count) {
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
// ============================== CherryLink Scheme / Export  ============================== //
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

// For test purposes
add_action('wp_ajax_linkate_generate_json', 'linkate_generate_json');
function linkate_generate_json() {
	// get rows from db
	global $wpdb, $table_prefix;
	$table_name = $table_prefix.'linkate_scheme';
	$gutenberg_data = json_decode(file_get_contents('php://input'), true);
	if (isset($gutenberg_data['this_id'])) {
		$this_id = $gutenberg_data['this_id'];
		$this_type = $gutenberg_data['this_type'];
	} else {
		$this_id = $_POST['this_id'];
		$this_type = $_POST['this_type'];
	}

	$this_type = $this_type == 'post' ? 0 : 1;
	$links = $wpdb->get_results("SELECT * FROM $table_name WHERE target_id = $this_id AND target_type = $this_type", ARRAY_A);
	if ($links != null && sizeof($links) > 0) {
		reset($links);
		$total_count = sizeof($links);

		$output_array = array();
		foreach ($links as $link) {
			// get source url and target url
			$source_url = '';
			if ($link['source_type'] == 0) { //post
				$source_url = get_permalink((int)$link['source_id']);
			} elseif ($link['source_type'] == 1) {
				$source_url = get_term_link((int)$link['source_id']);
			}

			$output_array[] = array(
				'source_url' => $source_url,
				'ankor' => $link['ankor_text']
			);
		}
		$json_array = array();
		$json_array['links'] = $output_array;
		$json_array['count'] = $total_count;
	} else {
		$json_array = array();
		$json_array['links'] = '';
		$json_array['count'] = 0;
	}
	unset($links);
	echo json_encode($json_array);
	wp_die();
}

// Get all needed posts type count to split into batches
add_action('wp_ajax_linkate_get_all_posts_count', 'linkate_get_all_posts_count');
function linkate_get_all_posts_count () {
	global $wpdb, $table_prefix;
	$types = array_map(function ($el) { 
		return "'".$el."'";
	}, $_POST['export_types']);
	$types = implode(",", $types);
	$count = 0;
	$count = $wpdb->get_var("SELECT COUNT(*) from ".$table_prefix."posts WHERE post_type IN (".$types.")");
	echo $count;
	linkate_stats_remove_old(false); //remove old stats csv files
	wp_die();
}

// WorkHorse
add_action('wp_ajax_linkate_generate_csv_or_json_prettyfied', 'linkate_generate_csv_or_json_prettyfied');
function linkate_generate_csv_or_json_prettyfied($is_custom_column = false, $custom_id = 0) {
	// get rows from db
	global $wpdb, $table_prefix;
    $gutenberg_data = json_decode(file_get_contents('php://input'), true);
    $from_editor = false;
	if (isset($_POST['post_ids'])) {
        $from_editor = true;
		$ids_query = $table_prefix."posts.ID IN (".$_POST['post_ids'].") AND ";
	} else if (isset($gutenberg_data['post_ids'])) {
        $from_editor = true;
		$ids_query = $table_prefix."posts.ID IN (".$gutenberg_data['post_ids'].") AND ";
    } else if ($is_custom_column) {
        $ids_query = $table_prefix."posts.ID IN (".$custom_id.") AND ";
    } else {
		$ids_query = "";
	}

	if (isset($_POST['stats_offset'])) {
		$bounds = " LIMIT ".  $_POST['stats_offset'] .",". $_POST['stats_limit'];
	} else {
		$bounds = "";
	}
	if (isset($_POST['export_types'])) {
		$types = array_map(function ($el) { 
			return "'".$el."'";
		}, $_POST['export_types']);
		$types = $table_prefix."posts.post_type IN ( ". implode(",", $types) .") ";
	} else {
		$types = $table_prefix."posts.post_type NOT IN ('attachment', 'nav_menu_item', 'revision')";
	}

	$table_name = $table_prefix.'linkate_scheme';
	$wpdb->query('SET @@group_concat_max_len = 100000;');
	$links_post = $wpdb->get_results("
        SELECT ".$table_prefix."posts.ID as source_id, ".$table_prefix."posts.post_type, 
        COALESCE(COUNT(scheme1.target_id), 0) AS count_targets, 
        GROUP_CONCAT(scheme1.target_id SEPARATOR ';') AS targets, 
        GROUP_CONCAT(scheme1.target_type SEPARATOR ';') AS target_types, 
        GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, 
        GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, 
        COALESCE(scheme2.count_sources, 0) AS count_sources
		FROM
			".$table_prefix."posts
		LEFT JOIN
		    ".$table_prefix."linkate_scheme AS scheme1 ON ".$table_prefix."posts.ID = scheme1.source_id
		    AND (scheme1.source_type = 0 OR scheme1.source_type IS NULL)
		LEFT JOIN
			(
				SELECT COUNT(*) as count_sources, target_id, target_type
				FROM ".$table_prefix."linkate_scheme
				GROUP BY target_id, target_type
				) AS scheme2 ON ".$table_prefix."posts.ID = scheme2.target_id AND (scheme2.target_type = 0 OR scheme2.target_type IS NULL)
		WHERE ".$ids_query." " // selected post IDs
		.$types // post types
		." GROUP BY ".$table_prefix."posts.ID ORDER BY ".$table_prefix."posts.ID ASC " 
		.$bounds // LIMIT X,Y
		, ARRAY_A); //

	reset($links_post);
	$output_array = linkate_queryresult_to_array($links_post, $from_editor, 0);
	unset($links_post);

	if (!($from_editor) && (isset($_POST['stats_offset']) && intval($_POST['stats_offset']) === 0)) {
		$links_term = $wpdb->get_results("
		SELECT ".$table_prefix."terms.term_id as source_id, COALESCE(COUNT(scheme1.target_id), 0) AS count_targets, GROUP_CONCAT(scheme1.target_id SEPARATOR ';') AS targets, GROUP_CONCAT(scheme1.target_type SEPARATOR ';') AS target_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_sources, 0) AS count_sources
		FROM
			".$table_prefix."terms
		LEFT JOIN
		    ".$table_prefix."linkate_scheme AS scheme1 ON ".$table_prefix."terms.term_id = scheme1.source_id
		    AND (scheme1.source_type = 1 OR scheme1.source_type IS NULL)
		LEFT JOIN
			(
				SELECT COUNT(*) as count_sources, target_id, target_type
				FROM ".$table_prefix."linkate_scheme
				GROUP BY target_id, target_type
				) AS scheme2 ON ".$table_prefix."terms.term_id = scheme2.target_id AND (scheme2.target_type = 1 OR scheme2.target_type IS NULL)
		GROUP BY ".$table_prefix."terms.term_id
		ORDER BY ".$table_prefix."terms.term_id ASC", ARRAY_A); //
		reset($links_term);

	// echo json_encode($links_post);

		$output_array = array_merge($output_array, linkate_queryresult_to_array($links_term, $from_editor, 1));
		unset($links_term);
    }
    
    // for posts list only, not ajax call
    if ($is_custom_column) {
        return $output_array;
    }

	if ($from_editor) {
        wp_send_json($output_array);
	} else {
		query_to_csv($output_array, 'cherrylink_stats_'.$_POST['stats_offset'].'.csv');
		$response = array();
		$response['status'] = 'OK';
		$response['url'] = WP_PLUGIN_URL.'/cherrylink/stats/cherrylink_stats_'.$_POST['stats_offset'].'.csv';
		echo json_encode($response);
	}
	unset($output_array);
	wp_die();
}

add_action('wp_ajax_linkate_generate_csv_or_json_prettyfied_backwards', 'linkate_generate_csv_or_json_prettyfied_backwards');
function linkate_generate_csv_or_json_prettyfied_backwards() {
	// get rows from db
	global $wpdb, $table_prefix;
	if (isset($_POST['post_ids'])) {
		$ids_query = $table_prefix."posts.ID IN (".$_POST['post_ids'].") AND ";
	} else {
		$ids_query = "";
	}

	if (isset($_POST['stats_offset'])) {
		$bounds = " LIMIT ".  $_POST['stats_offset'] .",". $_POST['stats_limit'];
	} else {
		$bounds = "";
	}
	if (isset($_POST['export_types'])) {
		$types = array_map(function ($el) { 
			return "'".$el."'";
		}, $_POST['export_types']);
		$types = $table_prefix."posts.post_type IN ( ". implode(",", $types) .") ";
	} else {
		$types = $table_prefix."posts.post_type NOT IN ('attachment', 'nav_menu_item', 'revision')";
	}

	$table_name = $table_prefix.'linkate_scheme';
	$wpdb->query('SET @@group_concat_max_len = 100000;');
	$links_post = $wpdb->get_results("
		SELECT ".$table_prefix."posts.ID as target_id, ".$table_prefix."posts.post_type, COALESCE(COUNT(scheme1.source_id), 0) AS count_sources, GROUP_CONCAT(scheme1.source_id SEPARATOR ';') AS sources, GROUP_CONCAT(scheme1.source_type SEPARATOR ';') AS source_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_targets, 0) AS count_targets
		FROM
			".$table_prefix."posts
		LEFT JOIN
		    ".$table_prefix."linkate_scheme AS scheme1 ON ".$table_prefix."posts.ID = scheme1.target_id
		    AND (scheme1.target_type = 0 OR scheme1.target_type IS NULL)
		LEFT JOIN
			(
				SELECT COUNT(*) as count_targets, source_id, source_type
				FROM ".$table_prefix."linkate_scheme
				GROUP BY source_id, source_type
				) AS scheme2 ON ".$table_prefix."posts.ID = scheme2.source_id AND (scheme2.source_type = 0 OR scheme2.source_type IS NULL)
		WHERE ".$ids_query." " // selected post IDs
		.$types // post types
		." GROUP BY ".$table_prefix."posts.ID ORDER BY ".$table_prefix."posts.ID ASC " 
		.$bounds // LIMIT X,Y
		, ARRAY_A); //

	reset($links_post);
	$output_array = linkate_queryresult_to_array_backwards($links_post, 0);
	unset($links_post);

	if (!isset($_POST["from_editor"]) && (isset($_POST['stats_offset']) && intval($_POST['stats_offset']) === 0)) {
		$links_term = $wpdb->get_results("
		SELECT ".$table_prefix."terms.term_id as target_id, COALESCE(COUNT(scheme1.source_id), 0) AS count_sources, GROUP_CONCAT(scheme1.source_id SEPARATOR ';') AS sources, GROUP_CONCAT(scheme1.source_type SEPARATOR ';') AS source_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_targets, 0) AS count_targets
		FROM
			".$table_prefix."terms
		LEFT JOIN
		    ".$table_prefix."linkate_scheme AS scheme1 ON ".$table_prefix."terms.term_id = scheme1.target_id
		    AND (scheme1.target_type = 1 OR scheme1.target_type IS NULL)
		LEFT JOIN
			(
				SELECT COUNT(*) as count_targets, source_id, source_type
				FROM ".$table_prefix."linkate_scheme
				GROUP BY source_id, source_type
				) AS scheme2 ON ".$table_prefix."terms.term_id = scheme2.source_id AND (scheme2.source_type = 1 OR scheme2.source_type IS NULL)
		GROUP BY ".$table_prefix."terms.term_id
		ORDER BY ".$table_prefix."terms.term_id ASC", ARRAY_A); //
		reset($links_term);

	// echo json_encode($links_post);

		$output_array = array_merge($output_array, linkate_queryresult_to_array_backwards($links_term, 1));
		unset($links_term);
	}

	if (isset($_POST["from_editor"])) {
        wp_send_json($output_array);
	} else {
		query_to_csv($output_array, 'cherrylink_stats_'.$_POST['stats_offset'].'.csv');
		$response = array();
		$response['status'] = 'OK';
		$response['url'] = WP_PLUGIN_URL.'/cherrylink/stats/cherrylink_stats_'.$_POST['stats_offset'].'.csv';
		echo json_encode($response);
	}
	unset($output_array);
	wp_die();
}
function linkate_queryresult_to_array_backwards($links, $target_type) {
	$include_types = $_POST['export_types'] ? $_POST['export_types'] : array();
	$output_array = array();
	//echo sizeof($links);
	foreach ($links as $link) {
		// get source url and target url
		$target_url = '';
		if ($target_type == 0) { //post
			$target_url = get_permalink((int)$link['target_id']);
			if (false === in_array($link['post_type'], $include_types) && !isset($_POST["from_editor"]))
				continue; // skip, if not in our list
			// get post's categories
			$post_categories = get_the_terms( (int)$link['target_id'], 'category' );
			if ( ! empty( $post_categories ) && ! is_wp_error( $post_categories ) ) {
				$target_categories = wp_list_pluck( $post_categories, 'name' );
			}
		} elseif ($target_type == 1) { // term
			$target_url = get_term_link((int)$link['target_id']);
			$term_obj = get_term((int)$link['target_id']);
			if ($term_obj == null || $term_obj instanceof WP_Error) {
				$term_type = 'cat/tag';
				$term_name = 'taxonomy';
			} else {
				$term_type = $term_obj->taxonomy;
				$term_name = $term_obj->name;
			}
			if (!in_array($term_type, $include_types) && $term_type != 'cat/tag')
				continue; // skip, if not in our list
		}

		$sources = explode(';', $link['sources']);
		$source_types = explode(';', $link['source_types']);
		$ext_links = explode(';', $link['ext_links']);
		$ankors = explode(';',  $link['ankors']);

		for ($i=0; $i < sizeof($sources); $i++) {
			$source_url = '';
			if ($source_types[$i] == 0) { //post
				$source_url = get_permalink((int)$sources[$i]);
			} elseif ($source_types[$i] == 1) {
				$source_url = get_term_link((int)$sources[$i]);
			} else {
				$source_url = $ext_links[$i];
			}
			// check POST options
			$buf_array = array();
			if (isset($_POST["from_editor"]) && $_POST["from_editor"] == true) {
			    if ($i > 0)
			        break;
				$buf_array[] = $link['count_targets'];
				$buf_array[] = $link['count_sources'];
            } else { //from admin panel
				if ($i == 0 || isset($_POST['duplicate_fields'])) {
					if (isset($_POST['target_id']))     $buf_array[] = $link['target_id'];
					if (isset($_POST['target_type']))   $buf_array[] = $target_type == 0 ? $link['post_type'] : $term_type;
					if (isset($_POST['target_cats']))   $buf_array[] = $target_type == 0 ? implode(", ", $target_categories) : $term_name;
					if (isset($_POST['target_url']))    $buf_array[] = $target_url;
					if (isset($_POST['source_url']))    $buf_array[] = $source_url;
					if (isset($_POST['ankor']))         $buf_array[] = $ankors[$i];
					if (isset($_POST['count_out']))     $buf_array[] = $link['count_targets'];
					if (isset($_POST['count_in']))      $buf_array[] = $link['count_sources'];
				} else { // by default, we don't repeat the same data
					if (isset($_POST['target_id'])) $buf_array[] = '';
					if (isset($_POST['target_type'])) $buf_array[] = '';
					if (isset($_POST['target_cats'])) $buf_array[] = '';
					if (isset($_POST['target_url'])) $buf_array[] = '';
					if (isset($_POST['source_url'])) $buf_array[] = $source_url;
					if (isset($_POST['ankor'])) $buf_array[] = $ankors[$i];
					if (isset($_POST['count_out'])) $buf_array[] = '';
					if (isset($_POST['count_in'])) $buf_array[] = '';
				}
            }
            if (isset($_POST["from_editor"])) {
	            $output_array["\"id_".$link['source_id']."\""] = $buf_array;
            } else {
	            $output_array[] = $buf_array;
            }

		}
	}
	return $output_array;
}
function linkate_queryresult_to_array($links, $from_editor, $source_type) {
	$include_types = isset($_POST['export_types']) ? $_POST['export_types'] : array();
	$output_array = array();
	//echo sizeof($links);
	foreach ($links as $link) {
		// get source url and target url
		$source_url = '';
		if ($source_type == 0) { //post
			$source_url = get_permalink((int)$link['source_id']);
			if (false === in_array($link['post_type'], $include_types) && !isset($from_editor))
				continue; // skip, if not in our list
			// get post's categories
			$post_categories = get_the_terms( (int)$link['source_id'], 'category' );
			if ( ! empty( $post_categories ) && ! is_wp_error( $post_categories ) ) {
				$source_categories = wp_list_pluck( $post_categories, 'name' );
			}
		} elseif ($source_type == 1) { // term
            $source_url = get_term_link((int)$link['source_id']);
            // if ($source_url instanceof WP_Error) $source_url = $source_url->get_error_message();
			$term_obj = get_term((int)$link['source_id']);
			if ($term_obj == null || $term_obj instanceof WP_Error) {
				$term_type = 'cat/tag';
				$term_name = 'taxonomy';
			} else {
				$term_type = $term_obj->taxonomy;
				$term_name = $term_obj->name;
			}
			if (!in_array($term_type, $include_types) && $term_type != 'cat/tag')
				continue; // skip, if not in our list
		}

		$targets = explode(';', $link['targets']);
		$target_types = explode(';', $link['target_types']);
		$ext_links = explode(';', $link['ext_links']);
		$ankors = explode(';',  $link['ankors']);

		for ($i=0; $i < sizeof($targets); $i++) {
			$target_url = '';
			if ($target_types[$i] == 0) { //post
				$target_url = get_permalink((int)$targets[$i]);
			} elseif ($target_types[$i] == 1) {
				$target_url = get_term_link((int)$targets[$i]);
			} else {
				$target_url = $ext_links[$i];
			}
			// check POST options
			$buf_array = array();
			if (isset($from_editor) && $from_editor == true) {
			    if ($i > 0)
			        break;
				$buf_array[] = $link['count_targets'];
				$buf_array[] = $link['count_sources'];
            } else { //from admin panel
				if ($i == 0 || isset($_POST['duplicate_fields'])) {
					if (isset($_POST['source_id']))     $buf_array[] = $link['source_id'];
					if (isset($_POST['source_type']))   $buf_array[] = $source_type == 0 ? $link['post_type'] : $term_type;
					if (isset($_POST['source_cats']))   $buf_array[] = $source_type == 0 ? implode(", ",$source_categories) : $term_name;
					if (isset($_POST['source_url']))    $buf_array[] = $source_url;
					if (isset($_POST['target_url']))    $buf_array[] = $target_url;
					if (isset($_POST['ankor']))         $buf_array[] = $ankors[$i];
					if (isset($_POST['count_out']))     $buf_array[] = $link['count_targets'];
					if (isset($_POST['count_in']))      $buf_array[] = $link['count_sources'];
				} else { // by default, we don't repeat the same data
					if (isset($_POST['source_id'])) $buf_array[] = '';
					if (isset($_POST['source_type'])) $buf_array[] = '';
					if (isset($_POST['source_cats'])) $buf_array[] = '';
					if (isset($_POST['source_url'])) $buf_array[] = '';
					if (isset($_POST['target_url'])) $buf_array[] = $target_url;
					if (isset($_POST['ankor'])) $buf_array[] = $ankors[$i];
					if (isset($_POST['count_out'])) $buf_array[] = '';
					if (isset($_POST['count_in'])) $buf_array[] = '';
				}
            }
            if (isset($from_editor)) {
	            $output_array["\"id_".$link['source_id']."\""] = $buf_array;
            } else {
	            $output_array[] = $buf_array;
            }

		}
	}
	return $output_array;
}
// Change encoding if possible
function encodeCSV(&$value, $key){
	if ($value instanceof WP_Error)
		$value = 'NULL_ERROR';
	else
		$value = iconv('UTF-8', 'Windows-1251', $value);
}
// Custom fputcsv
function fputcsv_eol($handle, $array, $delimiter = ',', $enclosure = '"', $eol = "\n") {
	$return = fputcsv($handle, $array, $delimiter, $enclosure);
	if($return !== FALSE && "\n" != $eol && 0 === fseek($handle, -1, SEEK_CUR)) {
		fwrite($handle, $eol);
	}
	return $return;
}
// Write csv file
function query_to_csv($array, $filename) {
	$arr_post_id = 			'ID';
	$arr_post_type = 		'Тип';
	$arr_post_cats = 		$_POST["links_direction"] == "outgoing" ? 'Рубрики_источника' : 'Рубрики_цели';
	$arr_source_url = 		$_POST["links_direction"] == "outgoing" ? 'URL_источника' : 'URL_цели';
	$arr_target = 			$_POST["links_direction"] == "outgoing" ? 'URL_цели' : 'URL_источника';
	$arr_ankor = 			'Анкор';
	$arr_targets_count = 	'Исходящих_ссылок';
	$arr_sources_count = 	'Входящих_ссылок';
	$headers = array($arr_post_id, $arr_post_type, $arr_post_cats, $arr_source_url, $arr_target, $arr_ankor, $arr_targets_count, $arr_sources_count);

	// create dir
	if (!file_exists(WP_PLUGIN_DIR.'/cherrylink/stats'))
	{
		mkdir(WP_PLUGIN_DIR.'/cherrylink/stats', 0755, true);
	}
    //////////////////
	$fp = fopen(WP_PLUGIN_DIR.'/cherrylink/stats/'.$filename, 'w');

	// output header row (if at least one row exists)
	array_walk($headers, 'encodeCSV');
	fputcsv_eol($fp, $headers,',','"', "\r\n");

	foreach ($array as $row) {
		array_walk($row, 'encodeCSV');
		fputcsv_eol($fp, $row,',','"', "\r\n");
	}

    fclose($fp);
    ///////////
	// $fp = fopen(WP_PLUGIN_DIR.'/cherrylink/stats/'.$filename, 'w');
    // fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

    // fputcsv($fp, $headers, ";");
	// foreach ($array as $row) {
    //     foreach ($row as $k => $v) {
    //         if ($v instanceof WP_Error) {
    //             continue;
    //         }
    //     }
    //     fputcsv($fp, $row, ";");
	// }

	// fclose($fp);
}

add_action('wp_ajax_linkate_merge_csv_files', 'linkate_merge_csv_files');
function linkate_merge_csv_files() {
	$directory = WP_PLUGIN_DIR.'/cherrylink/stats/*'; // CSV Files Directory Path

	// Open and Write Master CSV File
	$masterCSVFile = fopen(WP_PLUGIN_DIR.'/cherrylink/stats/cherrylink_stats.csv', "w+");
	$first_file = true;
	// Process each CSV file inside root directory
	foreach(glob($directory) as $file) {

		$data = []; // Empty Data

		// Allow only CSV files
		if (strpos($file, 'cherrylink_stats_') !== false) {

			// Open and Read individual CSV file
			if (($handle = fopen($file, 'r')) !== false) {
				// Collect CSV each row records
				while (($dataValue = fgetcsv($handle, 1000)) !== false) {
					$data[] = $dataValue;
				}
			}

			fclose($handle); // Close individual CSV file 

			if (!$first_file)
				unset($data[0]); // Remove first row of CSV, commonly tends to CSV header

			// Check whether record present or not
			if(count($data) > 0) {

				foreach ($data as $value) {
					try {
					// Insert record into master CSV file
					fputcsv_eol($masterCSVFile, $value,',','"', "\r\n");
					} catch (Exception $e) {
						echo $e->getMessage();
					}
				
				}

			// } else {
			// 	echo "[$file] file contains no record to process.";
			}
			$first_file = false;
		}

	}

	// Close master CSV file 
	fclose($masterCSVFile);

	linkate_stats_remove_old(true);

	$response = array();
	$response['status'] = 'OK';
	$response['url'] = WP_PLUGIN_URL.'/cherrylink/stats/cherrylink_stats.csv';
	echo json_encode($response);
	wp_die();
}

function linkate_stats_remove_old($onlytemp_files = false) {
	if (!file_exists(WP_PLUGIN_DIR.'/cherrylink/stats'))
	{
		return;
	}

	$files = glob(WP_PLUGIN_DIR.'/cherrylink/stats/*'); // get all file names
	foreach($files as $file) { // iterate files
		if(is_file($file)) {
			if ($onlytemp_files && strpos($file, 'cherrylink_stats_') !== false) {
				unlink($file); // delete file
			}
			if (!$onlytemp_files)
				unlink($file); // delete file
		}
	}
}
