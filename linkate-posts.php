<?php
/*
Plugin Name: CherryLink
Plugin URI: http://seocherry.ru/dev/cherrylink/
Description: Плагин для упрощения ручной внутренней перелинковки. Поиск релевантных ссылок, ускорение монотонных действий, гибкие настройки, удобная статистика и экспорт.
Version: 1.6.11
Author: SeoCherry.ru
Author URI: http://seocherry.ru/
Text Domain: linkate-posts
*/

function linkate_posts($args = '') {
	return LinkatePosts::execute($args);
}

function linkate_posts_mark_current(){
	global $post, $linkate_posts_current_ID;
	$linkate_posts_current_ID = $post->ID;
}

// define ('LINKATE_POST_PLUGIN_LIBRARY', true);

if ( ! defined( 'WP_CONTENT_URL' ) )
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );


if (!defined('LINKATE_CF_LIBRARY')) require(WP_PLUGIN_DIR.'/cherrylink/common_functions.php');
if (!defined('LINKATE_ACF_LIBRARY')) require(WP_PLUGIN_DIR.'/cherrylink/admin_common_functions.php');
if (!defined('LP_OT_LIBRARY')) require(WP_PLUGIN_DIR.'/cherrylink/output_tags.php');
if (!defined('LP_ADMIN_SUBPAGES_LIBRARY')) require(WP_PLUGIN_DIR.'/cherrylink/admin-subpages.php');
if (!defined('LINKATE_TERMS_LIBRARY')) require(WP_PLUGIN_DIR.'/cherrylink/linkate-terms.php');

if (!defined('DSEP')) define('DSEP', DIRECTORY_SEPARATOR);
// if (!defined('LINKATE_STOP_WORDS')) require(WP_PLUGIN_DIR.'/cherrylink/stopwords.php');

$linkate_posts_current_ID = -1;

class LinkatePosts {
  static $version = 0;

  static function get_linkate_version() {
    $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');
    LinkatePosts::$version = $plugin_data['version'];

    return $plugin_data['version'];
  } // get_linkate_version

  // check if plugin's admin page is shown
  static function linkate_is_plugin_admin_page($page = 'settings') {
    $current_screen = get_current_screen();

    if ($page == 'settings' && $current_screen->id == 'settings_page_linkate-posts') {
      return true;
    }

    return false;
  } // linkate_is_plugin_admin_page

  // add settings link to plugins page
  static function linkate_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=linkate-posts') . '" title="Настройки CherryLink">Настройки</a>';

    array_unshift($links, $settings_link);

    return $links;
  } // linkate_plugin_action_links


    
	static function execute($args='', $default_output_template='<li>{link}</li>', $option_key='linkate-posts'){
		global $table_prefix, $wpdb, $wp_version, $linkate_posts_current_ID;
		//$start_time = link_cf_microtime();
		
		// Manually throws id of the current post if set
		$arg_id = 0;
		$is_term = 0;
		$offset = 0;
		$relevant_block = 0;
		if (function_exists('get_string_between')) {
		    $linkate_posts_current_ID = get_string_between ($args, "manual_ID=", "&");
		    $is_term = get_string_between ($args, "is_term=", "&");
		    $offset = get_string_between ($args, "offset=", "&");
			$relevant_block = get_string_between ($args, "relevant_block=", "&");
			if (empty($relevant_block)) $relevant_block = 0;
		}

		$postid = link_cf_current_post_id($linkate_posts_current_ID);
		
		// if (defined('POC_CACHE_4')) {
		// 	$cache_key = $option_key.$postid.$args;
		// 	$result = poc_cache_fetch($cache_key);
		// 	if ($result !== false) return $result . sprintf("<!-- Linkate Posts took %.3f ms (cached) -->", 1000 * (link_cf_microtime() - $start_time));
		// }
		$table_name = $table_prefix . 'linkate_posts';
		// First we process any arguments to see if any defaults have been overridden
		$options = link_cf_parse_args($args);
		// Next we retrieve the stored options and use them unless a value has been overridden via the arguments
		$options = link_cf_set_options($option_key, $options, $default_output_template);
		// echo json_encode($options);
		//print_r($options);
		if (0 < $options['limit_ajax']) {
			$match_tags = ($options['match_tags'] !== 'false' && $wp_version >= 2.3);
			$exclude_cats = ($options['excluded_cats'] !== '');
			$include_cats = ($options['included_cats'] !== '');
			$exclude_authors = ($options['excluded_authors'] !== '');
			$include_authors = ($options['included_authors'] !== '');
			$exclude_posts = (trim($options['excluded_posts']) !== '');
			$include_posts = (trim($options['included_posts']) !== '');
			$match_category = ($options['match_cat'] === 'true');
			$match_author = ($options['match_author'] === 'true');
			$use_tag_str = ('' != trim($options['tag_str']) && $wp_version >= 2.3);
			$omit_current_post = ($options['omit_current_post'] !== 'false');
			$ignore_relevance = ($options['ignore_relevance'] !== 'false');
			$match_against_title = ($options['match_all_against_title'] !== 'false');
			$hide_pass = ($options['show_private'] === 'false');
			$check_age = ('none' !== $options['age']['direction']);
			$check_custom = (trim($options['custom']['key']) !== '');
			$limit = $offset.', '.$options['limit_ajax'];
	 		//get the terms to do the matching
			// if ($options['term_extraction'] === 'pagerank') {
			// 	list( $contentterms, $titleterms, $tagterms, $suggestions) = linkate_sp_terms_by_textrank($postid, $options['num_terms'], $is_term);
			// } else {
				list( $contentterms, $titleterms, $tagterms, $suggestions) = linkate_sp_terms_by_freq($postid, $options['num_terms'], $is_term);
			// }
	 		// these should add up to 1.0
			$weight_content = $options['weight_content'];
			$weight_title = $options['weight_title'];
			$weight_tags = $options['weight_tags'];
			// below a threshold we ignore the weight completely and save some effort
			if ($weight_content < 0.001) $weight_content = (int) 0;
			if ($weight_title < 0.001) $weight_title = (int) 0;
			if ($weight_tags < 0.001) $weight_tags = (int) 0;

			$count_content = substr_count($contentterms, ' ') + 1;
			$count_title = substr_count($titleterms, ' ') + 1;
			$count_tags  = substr_count($tagterms, ' ') + 1;
			if ($weight_content) $weight_content = 57.0 * $weight_content / $count_content;
			if ($weight_title) $weight_title = 18.0 * $weight_title / $count_title;
			if ($weight_tags) $weight_tags = 24.0 * $weight_tags / $count_tags;
			if ($options['hand_links'] === 'true') {
				// check custom field for manual links
				$forced_ids = $wpdb->get_var("SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $postid AND meta_key = 'linkate_sp_linkate' ") ;
			} else {
				$forced_ids = '';
			}
			// the workhorse...
			if ($ignore_relevance) {
				$sql = "SELECT * FROM `$table_name` LEFT JOIN `$wpdb->posts` ON `pID` = `ID` ";
			} else {
				$sql = "SELECT *, ";
				$sql .= link_cf_score_fulltext_match($table_name, $weight_title, $titleterms, $weight_content, $contentterms, $weight_tags, $tagterms, $forced_ids, $match_against_title);
			}

			if ($check_custom) $sql .= "LEFT JOIN $wpdb->postmeta ON post_id = ID ";

			// build the 'WHERE' clause
			$where = array();
			if (!$ignore_relevance) {
				$where[] = link_cf_where_fulltext_match($weight_title, $titleterms, $weight_content, $contentterms, $weight_tags, $tagterms, $match_against_title);
			}

			if (!function_exists('get_post_type')) {
				$where[] = link_cf_link_cf_where_hide_future();
			} else {
				$where[] = link_cf_where_show_status($options['status'], $options['show_attachments']);
			}
			if ($is_term == 0) {
				if ($match_category) $where[] = link_cf_where_match_category($postid);
				// echo json_encode(link_cf_where_match_category($postid));
				// return;
				if ($match_tags) $where[] = link_cf_where_match_tags($options['match_tags']);
				if ($match_author) $where[] = link_cf_where_match_author();
				if ($omit_current_post) $where[] = link_cf_where_omit_post($linkate_posts_current_ID);		
				if ($check_custom) $where[] = link_cf_where_check_custom($options['custom']['key'], $options['custom']['op'], $options['custom']['value']);
			}
			$where[] = link_cf_where_show_pages($options['show_pages'], $options['show_attachments'], $options['show_customs']);
			if ($include_cats) $where[] = link_cf_where_included_cats($options['included_cats']);
			if ($exclude_cats) $where[] = link_cf_where_excluded_cats($options['excluded_cats']);
			if ($exclude_authors) $where[] = link_cf_where_excluded_authors($options['excluded_authors']);
			if ($include_authors) $where[] = link_cf_where_included_authors($options['included_authors']);
			if ($exclude_posts) $where[] = link_cf_where_excluded_posts(trim($options['excluded_posts']));
			if ($include_posts) $where[] = link_cf_where_included_posts(trim($options['included_posts']));
			if ($use_tag_str) $where[] = link_cf_where_tag_str($options['tag_str']);
			if ($hide_pass) $where[] = link_cf_where_hide_pass();
			if ($check_age) $where[] = link_cf_where_check_age($options['age']['direction'], $options['age']['length'], $options['age']['duration']);

			$sql .= "WHERE ".implode(' AND ', $where);
			if ($check_custom) $sql .= " GROUP BY $wpdb->posts.ID";
			if ($ignore_relevance) {
				$sql .= " LIMIT $limit";
			} else {
				$sql .= " ORDER BY score DESC LIMIT $limit";
			}
			$results = $wpdb->get_results($sql);
		} else {
			$results = false;
		}
		if ($relevant_block) {
		    return CherryLink_Related_Block::prepare_related_block($results, $option_key, $options);
        } else {
			return LinkatePosts::prepare_for_cherrylink_panel($results, $option_key, $options);
		}
	}

	static function prepare_for_cherrylink_panel($results, $option_key, $options) {
		// TEMPLATES
		$add_to_related_block_btn = '';
		if (class_exists('CherryLink_Related_Block')) {
			$add_to_related_block_btn = '<div class="link-add-to-block" title="Добавить в блок релевантных ссылок"></div><div class="link-del-from-block btn-hidden" title="Убрать из блока ссылок"></div>';
		}

		$output_template_item_prefix = '
		<div class="linkate-item-container">
			<div class="linkate-controls">
				<div class="link-counter" title="Найдено в тексте / переход к ссылке">0</div>
				<div class="link-preview" title="Что за статья? Откроется в новой вкладке"></div>
				'.$add_to_related_block_btn.'
			</div>
			<div class="linkate-link" title="Нажмите для вставки в текст" data-url="{url}" data-titleseo="{title_seo}" data-title="{title}" data-category="{categorynames}" data-date="{date}" data-author="{author}" data-postid="{postid}" data-imagesrc="{imagesrc}" data-anons="{anons}" data-suggestions="{suggestions}"><span class="link-title" >';

		$output_template_item_suffix = '</span></div>
			<div class="link-right-controls"><div class="link-individual-stats-income" title="Сколько раз сослались на эту статью">?</div><div class="link-individual-stats-out" title="Сколько исходящих ссылок содержит статья">?</div><div class="link-suggestions" title="Подсказка"></div></div></div>';

		$results_count = 0;
		if ($results) {
			$out_final = $output_template_item_prefix . $options['output_template'] . $output_template_item_suffix;
			$translations = link_cf_prepare_template($out_final);
			foreach ($results as $result) {
				$items[] = link_cf_expand_template($result, $out_final, $translations, $option_key);
			}
			if ($options['sort']['by1'] !== '') $items = link_cf_sort_items($options['sort'], $results, $option_key, $options['group_template'], $items);
			$output = implode(($options['divider']) ? $options['divider'] : "\n", $items);

			$results_count = sizeof($results);
		} else {
			// we display the blank message, with tags expanded if necessary
			$translations = link_cf_prepare_template($options['none_text']);
			$output = "<p>" . link_cf_expand_template(array(), $options['none_text'], $translations, $option_key) . "</p>";
		}
		$send_data['links'] = trim($output);
		$send_data['count'] = $results_count;
		return $send_data;
//		wp_die();
	}

  // save some info
  static function lp_activate() {
    $options = get_option('linkate_posts_meta', array());

    if (empty($options['first_version'])) {
      $options['first_version'] = LinkatePosts::get_linkate_version();
      $options['first_install'] = current_time('timestamp');
      update_option('linkate_posts_meta', $options);
    }
  } // lp_activate

} // linkateposts class


function linkate_sp_terms_by_freq($ID, $num_terms = 50, $is_term = 0) {
	if (!$ID) return array('', '', '');
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$terms = '';
	$results = $wpdb->get_results("SELECT title, content, tags, suggestions FROM $table_name WHERE pID=$ID AND is_term=$is_term LIMIT 1", ARRAY_A);
	if ($results) {
		$word = strtok($results[0]['content'], ' ');
		$n = 0;
		$wordtable = array();
		while ($word !== false) {
			if(!array_key_exists($word,$wordtable)){
				$wordtable[$word]=0;
			}
			$wordtable[$word] += 1;
			$word = strtok(' ');
		}
		arsort($wordtable);
		if ($num_terms < 1) $num_terms = 1;
		$wordtable = array_slice($wordtable, 0, $num_terms);

		foreach ($wordtable as $word => $count) {
			$terms .= ' ' . $word;
		}

		$res[] = $terms;
		$res[] = $results[0]['title'];
		$res[] = $results[0]['tags'];
		$res[] = $results[0]['suggestions'];
 	}
	return $res;
}

// Extract the most popular words to make ankor suggestions 
function linkate_sp_terms_by_freq_ankor($content) {
	if (empty($content))
		return "";
	$terms = "";
	$num_terms = 3; // max words num
	$word = strtok($content, ' ');
	$n = 0;
	$wordtable = array();
	while ($word !== false) {
		if(!array_key_exists($word,$wordtable)){
			$wordtable[$word]=0;
		}
		$wordtable[$word] += 1;
		$word = strtok(' ');
	}
	arsort($wordtable);
	if ($num_terms < 1) $num_terms = 1;
	$wordtable = array_slice($wordtable, 0, $num_terms);

	foreach ($wordtable as $word => $count) {
		$terms .= ' ' . $word;
	}
	return $terms;
}


// ONLY FOR TEST
//function linkate_sp_terms_by_freq_test($ID) {
//	if (!$ID) return '';
//	global $wpdb, $table_prefix;
//	$table_name = $table_prefix . 'linkate_posts';
//	$post = $wpdb->get_row("SELECT post_content, post_title, post_type FROM $wpdb->posts WHERE ID = $ID", ARRAY_A);
//
//	if ($post) {
//		$seotitle = '';
//		if (function_exists('wpseo_init')){
//	    	$seotitle = get_post_meta( $ID, "_yoast_wpseo_title", true);
//		}
//	    if (function_exists( 'aioseop_init_class' )){
//	        $seotitle = get_post_meta( $ID, "_aioseop_title", true);
//	    }
//
//	    return linkate_sp_prepare_suggestions($post['post_title'], $seotitle);
// 	}
//}

// Convert an UTF-8 encoded string to a single-byte string suitable for
// functions such as levenshtein.
// 
// The function simply uses (and updates) a tailored dynamic encoding
// (in/out map parameter) where non-ascii characters are remapped to
// the range [128-255] in order of appearance.
//
// Thus it supports up to 128 different multibyte code points max over
// the whole set of strings sharing this encoding.
//
function utf8_to_extended_ascii($str, &$map)
{
    // find all multibyte characters (cf. utf-8 encoding specs)
    $matches = array();
    if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches))
        return $str; // plain ascii string
    
    // update the encoding map with the characters not already met
    foreach ($matches[0] as $mbc)
        if (!isset($map[$mbc]))
            $map[$mbc] = chr(128 + count($map));
    
    // finally remap non-ascii characters
    return strtr($str, $map);
}

// Didactic example showing the usage of the previous conversion function but,
// for better performance, in a real application with a single input string
// matched against many strings from a database, you will probably want to
// pre-encode the input only once.
//
function levenshtein_utf8($s1, $s2)
{
    $charMap = array();
    $s1 = utf8_to_extended_ascii($s1, $charMap);
    $s2 = utf8_to_extended_ascii($s2, $charMap);
    
    return levenshtein($s1, $s2);
}

function linkate_sp_terms_by_textrank($ID, $num_terms = 50, $is_term = 0) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$terms = '';
	$results = $wpdb->get_results("SELECT title, content, tags, suggestions FROM $table_name WHERE pID=$ID AND is_term=$is_term LIMIT 1", ARRAY_A);
	if ($results) {
		// build a directed graph with words as vertices and, as edges, the words which precede them
 		$prev_word = 'aaaaa';
		$graph = array();
		$out_edges = array();
		$word = strtok($results[0]['content'], ' ');
		while ($word !== false) {
			isset($graph[$word][$prev_word]) ? $graph[$word][$prev_word] += 1 : $graph[$word][$prev_word] = 1; // list the incoming words and keep a tally of how many times words co-occur
			isset($out_edges[$prev_word]) ? $out_edges[$prev_word] += 1 : $out_edges[$prev_word] = 1; // count the number of different words that follow each word
			$prev_word = $word;
			$word = strtok(' ');
		}
 		// initialise the list of PageRanks-- one for each unique word
		reset($graph);
		while (list($vertex, $in_edges) =  each($graph)) {
			$oldrank[$vertex] = 0.25;
		}
		$n = count($graph);
		if ($n > 0) {
			$base = 0.15 / $n;
			$error_margin = $n * 0.005;
			do {
				$error = 0.0;
				// the edge-weighted PageRank calculation
				reset($graph);
				while (list($vertex, $in_edges) =  each($graph)) {
					$r = 0;
					reset($in_edges);
					while (list($edge, $weight) =  each($in_edges)) {
						if (isset($oldrank[$edge])) {
							$r += ($weight * $oldrank[$edge]) / $out_edges[$edge];
						}
					}
					$rank[$vertex] = $base + 0.95 * $r;
					$error += abs($rank[$vertex] - $oldrank[$vertex]);
				}
				$oldrank = $rank;
				//echo $error . '<br>';
			} while ($error > $error_margin);
			arsort($rank);
			if ($num_terms < 1) $num_terms = 1;
			$rank = array_slice($rank, 0, $num_terms);
			foreach ($rank as $vertex => $score) {
				$terms .= ' ' . $vertex;
			}
		}
		$res[] = $terms;
		$res[] = $results[0]['title'];
		$res[] = $results[0]['tags'];
		$res[] = $results[0]['suggestions'];
 	}
	return $res;
}

// Update post index

function linkate_sp_save_index_entry($postID) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$post = $wpdb->get_row("SELECT post_content, post_title, post_type FROM $wpdb->posts WHERE ID = $postID", ARRAY_A);
	if ($post['post_type'] === 'revision') return $postID;
	$options = get_option('linkate-posts');
	require_once (WP_PLUGIN_DIR . "/cherrylink/ru_stemmer.php");
	$stemmer = new Stem\LinguaStemRu();


	// wp_linkate_scheme, create new scheme for this post
	if ($options['linkate_scheme_exists']) {
		linkate_scheme_delete_record($postID, 0);
		linkate_scheme_add_row($post['post_content'], $postID, 0); 
		$options['linkate_scheme_time'] = time();
		update_option('linkate-posts', $options);
	}

	$suggestions_donors_src = $options['suggestions_donors_src'];
    $suggestions_donors_join = $options['suggestions_donors_join'];
	$clean_suggestions_stoplist = $options['clean_suggestions_stoplist'];
	$min_len = $options['term_length_limit'];

    $words_table = $table_prefix."linkate_stopwords";
    $black_words = $wpdb->get_col("SELECT stemm FROM $words_table WHERE is_white = 0 GROUP BY stemm");
    $white_words = $wpdb->get_col("SELECT word FROM $words_table WHERE is_white = 1");
    $black_words = array_filter($black_words);
    $white_words = array_filter($white_words);
    $linkate_overusedwords["black"] = array_flip($black_words);
    $linkate_overusedwords["white"] = array_flip($white_words);

	list($content, $content_sugg) = linkate_sp_get_post_terms($post['post_content'], $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
    $content = iconv("UTF-8", "UTF-8//IGNORE", $content); // convert broken symbols
	// Seo title is more relevant, usually
	// Extracting terms from the custom titles, if present
	$seotitle = '';
	if (function_exists('wpseo_init')){
//    	$seotitle = get_post_meta( $postID, "_yoast_wpseo_title", true);
    	$seotitle = linkate_decode_yoast_variables($postID);
	}
    if (function_exists( 'aioseop_init_class' )){
        $seotitle = get_post_meta( $postID, "_aioseop_title", true);
    }
    // anti-memory leak
    wp_cache_delete( $postID, 'post_meta' );

    if (!empty($seotitle) && $seotitle !== $post['post_title']) {
        $title = $post['post_title'] . " " . $seotitle;
    } else {
        $title = $post['post_title'];
    }
    list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );

    // Extract ancor terms
	$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);

	$tags = linkate_sp_get_tag_terms($postID);
	//check to see if the field is set
	$pid = $wpdb->get_var("SELECT pID FROM $table_name WHERE pID=$postID limit 1");
	//then insert if empty
	if (is_null($pid)) {
		$wpdb->query("INSERT INTO $table_name (pID, content, title, tags, suggestions) VALUES ($postID, \"$content\", \"$title\", \"$tags\", \"$suggestions\")");
	} else {
		$wpdb->query("UPDATE $table_name SET content=\"$content\", title=\"$title\", tags=\"$tags\", suggestions=\"$suggestions\" WHERE pID=$postID" );
	}
	return $postID;
}

function linkate_sp_prepare_suggestions($title, $content, $suggestions_donors_src, $suggestions_donors_join) {

	if (empty($suggestions_donors_src))
	    return '';

	$suggestions_donors_src = explode(',', $suggestions_donors_src);

	// change old settings
	if (!in_array('title', $suggestions_donors_src) && !in_array('content', $suggestions_donors_src)) {
        $suggestions_donors_src = array('title');
    }

    $array = array();
    if (in_array('title',$suggestions_donors_src))
	    $array[] = array_filter($title);
	if (in_array('content', $suggestions_donors_src)) {
	    // get most used words from content
        $wordlist = array_count_values($content);
        arsort($wordlist);
        $wordlist = array_slice($wordlist, 0, 20);
        $wordlist = array_keys($wordlist);
        $array[] = array_filter($wordlist);
	}
    $array = array_filter($array);
    if (empty($array))
        return '';

    if (sizeof($array) === 1) {
        return implode(' ', array_unique($array[0]));
    }

    if ($suggestions_donors_join == 'intersection') {
        $result = array_unique(array_intersect(...$array));
        return  implode(' ', $result);
    } else { //join
        $result = array_unique(array_merge(...$array));
        return  implode(' ', $result);
    }

}

function linkate_sp_delete_index_entry($postID) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$wpdb->query("DELETE FROM $table_name WHERE pID = $postID ");
	return $postID;
}

// Update term index

function linkate_sp_save_index_entry_term($term_id, $tt_id, $taxonomy) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	require_once (WP_PLUGIN_DIR . "/cherrylink/ru_stemmer.php");
	$stemmer = new Stem\LinguaStemRu();


	$term = $wpdb->get_row("SELECT `term_id`, `name` FROM $wpdb->terms WHERE term_id = $term_id", ARRAY_A);
	//if ($post['post_type'] === 'revision') return $postid;
	//extract its terms
	$options = get_option('linkate-posts');

	$suggestions_donors_src = $options['suggestions_donors_src'];
    $suggestions_donors_join = $options['suggestions_donors_join'];
	$clean_suggestions_stoplist = $options['clean_suggestions_stoplist'];
	$min_len = $options['term_length_limit'];

    $words_table = $table_prefix."linkate_stopwords";
    $black_words = $wpdb->get_col("SELECT stemm FROM $words_table WHERE is_white = 0 GROUP BY stemm");
    $white_words = $wpdb->get_col("SELECT word FROM $words_table WHERE is_white = 1");
    $black_words = array_filter($black_words);
    $white_words = array_filter($white_words);
    $linkate_overusedwords["black"] = array_flip($black_words);
    $linkate_overusedwords["white"] = array_flip($white_words);

	$descr = '';
	$descr .= term_description($term_id); // standart 
	// custom plugins sp-category && f-cattxt
	$opt = get_option('category_'.$term_id);
	if ($opt && (function_exists('contents_sp_category') || function_exists('show_descr_top'))) {
		$descr .= $opt['descrtop'] ? ' '.$opt['descrtop'] : '';  
		$descr .= $opt['descrbottom'] ? ' '.$opt['descrbottom'] : '';  
		$aio_title = $opt['title'];
	}

	// wp_linkate_scheme, create new scheme for this term
	if ($options['linkate_scheme_exists']) {
		linkate_scheme_delete_record($term_id, 1);
		linkate_scheme_add_row($descr, $term_id, 1); 
		$options['linkate_scheme_time'] = time();
		update_option('linkate-posts', $options);
	}

    list($content, $content_sugg) = linkate_sp_get_post_terms($descr, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
	//Seo title is more relevant, usually
	//Extracting terms from the custom titles, if present
	$seotitle = '';

    $yoast_opt = get_option('wpseo_taxonomy_meta');
    if ($yoast_opt && $yoast_opt['category'] && function_exists('wpseo_init')) {
        $seotitle = $yoast_opt['category'][$term_id]['wpseo_title'];
    }
    if (!$seotitle && $aio_title && function_exists('show_descr_top'))
        $seotitle = $aio_title;

    if (!empty($seotitle) && $seotitle !== $term['name']) {
        $title = $term['name'] . " " . $seotitle;
    } else {
        $title = $term['name'];
    }

    list($title, $title_sugg) = linkate_sp_get_title_terms( $title, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist );


	// Extract ancor terms
	$suggestions = linkate_sp_prepare_suggestions($title_sugg, $content_sugg, $suggestions_donors_src, $suggestions_donors_join);
	$tags = "";
	//check to see if the field is set
	$pid = $wpdb->get_var("SELECT pID FROM $table_name WHERE pID=$term_id AND is_term=1 limit 1");
	//then insert if empty
	if (is_null($pid)) {
		$wpdb->query("INSERT INTO $table_name (pID, content, title, tags, is_term, suggestions) VALUES ($term_id, \"$content\", \"$title\", \"$tags\", 1, \"$suggestions\")");
	} else {
		$wpdb->query("UPDATE $table_name SET content=\"$content\", title=\"$title\", tags=\"$tags\", suggestions=\"$suggestions\" WHERE pID=$term_id AND is_term=1" );
	}
	//return $postID;
}

function linkate_sp_delete_index_entry_term($term_id, $term_taxonomy_ID, $taxonomy_slug, $already_deleted_term) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$wpdb->query("DELETE FROM $table_name WHERE pID = $term_id AND is_term = 1");
	//return $term_id;
}


function linkate_decode_yoast_variables($post_id, $is_term = false) {

//    $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
    $string =  WPSEO_Meta::get_value( 'title', $post_id );
    if ($string !== '') {
        $replacer = new WPSEO_Replace_Vars();

        return $replacer->replace( $string, get_post($post_id) );
    } else {
        return '';
    }

}

function linkate_sp_clean_words($text) {
	$text = strip_tags($text);
	$text = strtolower($text);
	$text = str_replace("’", "'", $text); // convert MSWord apostrophe
	$text = preg_replace(array('/\[(.*?)\]/', '/&[^\s;]+;/', '/‘|’|—|“|”|–|…/', "/'\W/"), ' ', $text); //anything in [..] or any entities or MS Word droppings
	return $text;
}

function linkate_sp_mb_clean_words($text) {
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$text = strip_tags($text);
	$text = mb_strtolower($text);
	$text = str_replace("’", "'", $text); // convert MSWord apostrophe
	$text = preg_replace(array('/\[(.*?)\]/u', '/&[^\s;]+;/u', '/‘|’|—|“|”|–|…/u', "/'\W/u"), ' ', $text); //anything in [..] or any entities
	return 	$text;
}

function linkate_sp_mb_str_pad($text, $n, $c) {
//	mb_internal_encoding('UTF-8');
//	$l = mb_strlen($text);
//	if ($l > 0 && $l < $n) {
//		$text .= str_repeat($c, $n-$l);
//	}
	return $text;
}

function linkate_sp_get_post_terms($text, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist) {
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
    $stemms = '';
    $words = array();

	reset($wordlist);

	foreach ($wordlist as $word) {
		if ( mb_strlen($word) > $min_len || isset($linkate_overusedwords["white"][$word])) {
			$stemm = $stemmer->stem_word($word);
			if (!isset($linkate_overusedwords["black"][$stemm]))
				if (mb_strlen($stemm) > 1)
					$stemms .= linkate_sp_mb_str_pad($stemm, 4, '_') . ' ';
            if ($clean_suggestions_stoplist == 'false' || ($clean_suggestions_stoplist == 'true' && !isset($linkate_overusedwords["black"][$stemm])))
                $words[] = $word;
		}
	}

	unset($wordlist);
	
	return array($stemms, $words);
}

function linkate_sp_get_title_terms( $text, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist ) {
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
	$stemms = '';
	$words = array();
	foreach ($wordlist as $word) {
		if ( mb_strlen($word) > $min_len || isset($linkate_overusedwords["white"][$word])) {
			$stemm = $stemmer->stem_word($word);
			if (!isset($linkate_overusedwords["black"][$stemm]))
				$stemms .= linkate_sp_mb_str_pad($stemm, 4, '_') . ' ';
			if ($clean_suggestions_stoplist == 'false' || ($clean_suggestions_stoplist == 'true' && !isset($linkate_overusedwords["black"][$stemm])))
                $words[] = $word;

		}
	}
	unset($wordlist);
	
	return array($stemms, $words);
}

function linkate_sp_get_suggestions_terms($text, $min_len, $linkate_overusedwords, $clean_suggestions_stoplist, $stemmer) {
	if ($clean_suggestions_stoplist == 'false') {
		$linkate_overusedwords = array();
	}
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
	$wordlist = array_count_values($wordlist);
	arsort($wordlist);
	$wordlist = array_slice($wordlist, 0, 20);
	$wordlist = array_keys($wordlist);
	$words = '';
	$exists = array();
	foreach ($wordlist as $word) {
		if (mb_strlen($word) > $min_len || isset($linkate_overusedwords["white"][$word])) {
			$stemm = $stemmer->stem_word($word);
			if (!isset($linkate_overusedwords["black"][$stemm]) && !in_array($word, $exists)) {
				$exists[] = $word;
				$words .= $word . ' ';
			}
		}
	}
	unset($exists);
	unset($wordlist);
	
	return trim($words);
}

function linkate_sp_get_tag_terms($ID) {
	global $wpdb;
	if (!function_exists('get_object_term_cache')) return '';
	$tags = array();
	$query = "SELECT t.name FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = 'post_tag' AND tr.object_id = '$ID'";
	$tags = $wpdb->get_col($query);
	if (!empty ($tags)) {

			mb_internal_encoding('UTF-8');
			foreach ($tags as $tag) {
				$newtags[] = linkate_sp_mb_str_pad(mb_strtolower(str_replace('"', "'", $tag)), 4, '_');
			}

		$newtags = str_replace(' ', '_', $newtags);
		$tags = implode (' ', $newtags);
	} else {
		$tags = '';
	}
	return $tags;
}

// Using this to parse query from linkate-editor.php, probably not the smartest way to do it
function get_string_between($string, $start, $end){
	$string = ' ' . $string;
	$ini = strpos($string, $start);
	if ($ini == 0) return '';
	$ini += strlen($start);
	$len = strpos($string, $end, $ini) - $ini;
	return substr($string, $ini, $len);
}

// Manipulate DB
function linkate_scheme_delete_record($id, $type) {
	// delete record by post ID or term ID
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_scheme';
	$wpdb->query("DELETE FROM $table_name WHERE source_id = $id AND source_type = $type");
	return $id;
}

function linkate_scheme_add_row($str, $post_id, $is_term) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_scheme';

	// quit if there is no content
	if (empty($str) || $str === false)
		return;
	// set error level, get rid of some warnings
	$internalErrors = libxml_use_internal_errors(true);
	$doc = new DOMDocument('1.0', 'UTF-8');
	$doc->loadHTML(mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8'));
	// Restore error level
	libxml_use_internal_errors($internalErrors);
	$selector = new DOMXPath($doc);
	$result = $selector->query('//a'); //get all <a>

	$target_id = 0;
	$target_type = 0;
	$values_string = '';
	$prohibited = array('.jpg','.jpeg','.tiff','.bmp','.psd', '.png', '.gif','.webp', '.doc', '.docx', '.xlsx', '.xls', '.odt', '.pdf', '.ods','.odf', '.ppt', '.pptx', '.txt', '.rtf', '.mp3', '.mp4', '.wav', '.avi', '.ogg', '.zip', '.7z', '.tar', '.gz', '.rar', 'attachment');

	// loop through all found items
	foreach($result as $node) {
		$href = $node->getAttribute('href');

		// if its doc,file or img - skip
		$is_doc = false;
		foreach ($prohibited as $v) {
			if (strpos($href, $v) !== false){
				$is_doc = true;
				break;
			}
		}

		if ($is_doc)
			continue;

		// remove some escaping stuff
		$href = str_replace("\"", "", str_replace("\\", "", $href));

		// prepare relative links
		// if (strpos($href, 'http') == false || strpos($href, 'www') == false) {
		// 	$proto = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';
		// 	$base =  isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
		// 	$href = $proto.$base.$href;
		// }

		$ext_url = '';
		$ankor = esc_sql($node->textContent);
		$target_id = url_to_postid($href); //post_id
		$target_type = 0;
		if ($target_id === 0) { // term_id
			$target_id = linkate_get_term_id_from_slug($href);
			$target_type = 1;
		}
		if ($target_id === 0) {	// target - external
			$target_type = 2;
			if (empty($href))
				continue; // no href - no need
			if (strpos($href, '#') !== false && strpos($href, 'http') !== true)
				continue; // this is just our internal navigational links
			$ext_url = esc_sql($href);
		}
		if (!empty($values_string)) $values_string .= ',';
		$values_string .= "($post_id, $is_term, $target_id, $target_type, \"$ankor\", \"$ext_url\")";
	}

	if (!empty($values_string))
		$wpdb->query("INSERT INTO `$table_name` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $values_string");
}

function linkate_get_term_id_from_slug($url) {
	$current_url = rtrim($url, "/");
	$arr_current_url = explode("/", $current_url);
	$thecategory = get_category_by_slug( end($arr_current_url) );
	if (!$thecategory) {
		return 0;
	} else {
		$catid = $thecategory->term_id;
		return $catid;
	}
}

if ( is_admin()) {
	require(dirname(__FILE__).'/linkate-posts-admin.php');

	if (linkate_callDelay() && linkate_lastStatus()) {
		$r = true;
	}
	if (linkate_callDelay() && !linkate_lastStatus()) {
		$r = false;
	}
	if (!linkate_callDelay()) {
		$r = linkate_checkNeededOption();
	}
	if ($r)
		require(WP_PLUGIN_DIR . '/cherrylink/linkate-editor.php');

}

function linkate_posts_wp_admin_style() {
	if (LinkatePosts::linkate_is_plugin_admin_page('settings')) {
		wp_register_style( 'linkate-posts-admin', plugins_url('', __FILE__) . '/css/linkate-posts-admin.css', false, LinkatePosts::$version );
		wp_register_style( 'linkate-posts-admin-table', "https://unpkg.com/tabulator-tables@4.2.3/dist/css/tabulator.min.css", false, LinkatePosts::$version );
		wp_enqueue_style( 'linkate-posts-admin' );
		wp_enqueue_style( 'linkate-posts-admin-table' );

		wp_register_script( 'linkate-script-admin', plugins_url( '/js/linkate-admin.js', __FILE__ ), array( 'jquery' ), LinkatePosts::get_linkate_version() );
		wp_register_script( 'linkate-script-admin-table', "https://unpkg.com/tabulator-tables@4.2.3/dist/js/tabulator.min.js", array( 'jquery' ), LinkatePosts::get_linkate_version() );

		$options = (array) get_option('linkate-posts');
		$scheme_exists = array("state" => $options['linkate_scheme_exists'] ? true : false);
		wp_localize_script('linkate-script-admin', 'scheme', $scheme_exists);

		wp_enqueue_script( 'linkate-script-admin' );
		wp_enqueue_script( 'linkate-script-admin-table' );
	}
}

function linkate_posts_init () {
	global $wp_db_version;
	load_plugin_textdomain('linkate_posts');

  	LinkatePosts::get_linkate_version();

	$options = get_option('linkate-posts');
	if ($options['content_filter'] === 'true' && function_exists('link_cf_register_content_filter')) link_cf_register_content_filter('LinkatePosts');
	if ($options['append_condition']) {
		$condition = $options['append_condition'];
	} else {
		$condition = 'true';
	}
	$condition = (stristr($condition, "return")) ? $condition : "return ".$condition;
	$condition = rtrim($condition, '; ') . ';';
	if ($options['append_on'] === 'true' && function_exists('link_cf_register_post_filter')) link_cf_register_post_filter('append', 'linkate-posts', 'LinkatePosts', $condition);

	//install the actions to keep the index up to date
	add_action('save_post', 'linkate_sp_save_index_entry', 1);
	add_action('delete_post', 'linkate_sp_delete_index_entry', 1);
	
	add_action('create_term', 'linkate_sp_save_index_entry_term', 1,3);
	add_action('edited_term', 'linkate_sp_save_index_entry_term', 1,3);
	add_action('delete_term', 'linkate_sp_delete_index_entry_term', 1,3);

	add_action( 'admin_enqueue_scripts', 'linkate_posts_wp_admin_style' );

  	// additional links in plugin description
  	add_filter('plugin_action_links_' . basename(dirname(__FILE__)) . '/' . basename(__FILE__),
             array('LinkatePosts', 'linkate_plugin_action_links'));
} // init

function linkate_check_update(){
//    if (!class_exists('Puc_v4_Factory')) {
        require 'updater/plugin-update-checker.php';
//    }

    $update_checker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/tonyAndr/cherrylink',
        __FILE__,
        'cherrylink'
    );

    $update_checker->setAuthentication('6d568422fc0119bba8ac68799afb87572e0f571e');
    $update_checker->setBranch('master');
    $update_checker->getVcsApi()->enableReleaseAssets();
}
linkate_check_update();
//add_action('admin_init', 'linkate_check_update');
add_action ('init', 'linkate_posts_init', 1);
register_activation_hook(__FILE__, array('LinkatePosts', 'lp_activate'));
