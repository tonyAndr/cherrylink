<?php 
/*
 * Linkate Posts
 */
 
define('LINKATE_EF_LIBRARY', true);

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


// Update post index

function linkate_sp_save_index_entry($postID) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';
	$post = $wpdb->get_row("SELECT post_content, post_title, post_type FROM $wpdb->posts WHERE ID = $postID", ARRAY_A);
	if ($post['post_type'] === 'revision') return $postID;
	$options = get_option('linkate-posts');
	require_once (WP_PLUGIN_DIR . "/cherrylink/cherrylink_stemmer_ru.php");
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

    $array = array_values($array);
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
	require_once (WP_PLUGIN_DIR . "/cherrylink/cherrylink_stemmer_ru.php");
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
	// $wordlist = array_unique(mb_split("\W+", linkate_sp_mb_clean_words($text)));
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
    $stemms = '';
    $words = array();

	reset($wordlist);

	foreach ($wordlist as $word) {
		if ( mb_strlen($word) > $min_len || isset($linkate_overusedwords["white"][$word])) {
			$stemm = $stemmer->stem_word($word);
			if (!isset($linkate_overusedwords["black"][$stemm]))
				if (mb_strlen($stemm) > 1)
					$stemms .= $stemm . ' ';
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
				$stemms .= $stemm . ' ';
			if ($clean_suggestions_stoplist == 'false' || ($clean_suggestions_stoplist == 'true' && !isset($linkate_overusedwords["black"][$stemm])))
                $words[] = $word;

		}
	}
	unset($wordlist);
	
	return array($stemms, $words);
}

function linkate_sp_get_suggestions_terms($text, $min_len, $linkate_overusedwords, $clean_suggestions_stoplist, $stemmer) {
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
        if ($clean_suggestions_stoplist == 'false') {
            if (mb_strlen($word) > $min_len) {
                $stemm = $stemmer->stem_word($word);
                if (!in_array($word, $exists)) {
                    $exists[] = $word;
                    $words .= $word . ' ';
                }
            }
        } else {
            if (mb_strlen($word) > $min_len || isset($linkate_overusedwords["white"][$word])) {
                $stemm = $stemmer->stem_word($word);
                if (!isset($linkate_overusedwords["black"][$stemm]) && !in_array($word, $exists)) {
                    $exists[] = $word;
                    $words .= $word . ' ';
                }
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

// Using this to parse query from cherrylink_editor.php, probably not the smartest way to do it
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

    $outgoing_count = 0;
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
        
        // add count to update post meta with outgoing links
        $outgoing_count++;

		if (!empty($values_string)) $values_string .= ',';
		$values_string .= "($post_id, $is_term, $target_id, $target_type, \"$ankor\", \"$ext_url\")";
    }
    
    // for stats column
    update_post_meta( (int) $post_id, "cherry_outgoing", $outgoing_count );

	if (!empty($values_string))
		$wpdb->query("INSERT INTO `$table_name` (source_id, source_type, target_id, target_type, ankor_text, external_url) VALUES $values_string");
}

function linkate_scheme_get_add_row_query($str, $post_id, $is_term) {
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

    $outgoing_count = 0;
	// loop through all found items
	foreach($result as $node) {
        $href = $node->getAttribute('href');
        if (empty($href)) continue; // no href - no need

		// if its doc,file or img - skip
		$is_doc = false;
		foreach ($prohibited as $v) {
			if (strpos($href, $v) !== false){
				$is_doc = true;
				break;
			}
		}

		if ($is_doc) continue;

		// remove some escaping stuff
        $href = trim(str_replace("\"", "", str_replace("\\", "", $href)));
        
        if (empty($href)) continue; // no href - no need
        if (strpos($href, '#') !== false && strpos($href, 'http') !== true) continue; // this is just our internal navigational links

		$ext_url = '';
        $ankor = esc_sql(trim($node->textContent));
        $ankor = empty($ankor) ? "_NOT_FOUND_" : $ankor;
		$target_id = url_to_postid($href); //post_id
		$target_type = 0;
		if ($target_id === 0) { // term_id
			$target_id = linkate_get_term_id_from_slug($href);
			$target_type = 1;
		}
		if ($target_id === 0) {	// target - external
			$target_type = 2;

			$ext_url = esc_sql($href);
        }
        
        // add count to update post meta with outgoing links
        $outgoing_count++;

		if (!empty($values_string)) $values_string .= ',';
        $values_string .= "($post_id, $is_term, $target_id, $target_type, \"$ankor\", \"$ext_url\")";
        unset($href);
    }
    
    // for stats column
    update_post_meta( (int) $post_id, "cherry_outgoing", $outgoing_count );
    //wp_cache_delete( (int) $post_id, 'post_meta' );

    unset($internalErrors);
    libxml_clear_errors();
    unset($doc);
    unset($selector);
    unset($result);
    unset($prohibited);
    
    return $values_string;
}

function linkate_get_term_id_from_slug($url) {
	$current_url = rtrim($url, "/");
	$arr_current_url = explode("/", $current_url);
	$thecategory = get_category_by_slug( end($arr_current_url) );
	if (!$thecategory) {
        unset($thecategory);
		return 0;
	} else {
        $catid = $thecategory->term_id;
		return $catid;
	}
}