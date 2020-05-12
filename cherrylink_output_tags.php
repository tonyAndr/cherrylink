<?php
/*
 * Linkate Posts
 */

define('LP_OT_LIBRARY', true);

// Called by the post plugins to match output tags to the actions that evaluate them
function lp_output_tag_action($tag) {
	return 'linkate_otf_'.$tag;
}

/*
	innards
*/

// To add a new output template tag all you need to do is write a tag function like those below.

// All the tag functions must follow the pattern of 'linkate_otf_title' below. 
//	the name is the tag name prefixed by 'linkate_otf_'
//	the arguments are always $option_key, $result and $ext
//		$option_key	the key to the plugin's options
//		$result		the particular row of the query result
//		$ext			some extra data which a tag may use
//	the return value is the value of the tag as a string  

function linkate_otf_postid ($option_key, $result, $ext) {
	return $result->ID;	
}

function linkate_otf_title ($option_key, $result, $ext) {
	if (isset($result->manual_title))
		return $result->manual_title; // return manual title for block links
    $value = linkate_oth_truncate_text($result->post_title, $ext);
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // for json
	return apply_filters('the_title', $value, $result->ID);
}

function linkate_otf_title_seo ($option_key, $result, $ext) {
	if (isset($result->manual_title))
		return $result->manual_title; // return manual title for block links
    if (function_exists('wpseo_init'))
        $title = linkate_decode_yoast_variables($result->ID);
    if (function_exists( 'aioseop_init_class' ))
        $title = get_post_meta( $result->ID, "_aioseop_title", true);
    if (!$title)
		$title = $result->post_title;
	$title = htmlspecialchars($title, ENT_QUOTES);  
    return linkate_oth_truncate_text($title, $ext);
}

// function linkate_otf_manual_title($option_key, $result, $ext) {
// 	$titles = get_post_meta( $result->ID, "crb-meta-links", true);



// 	$title = htmlspecialchars($title, ENT_QUOTES);  
//     return linkate_oth_truncate_text($title, $ext);
// }

function linkate_otf_url($option_key, $result, $ext) {
	$options = get_option('linkate-posts');
	$url_option = $options['relative_links'];
	$value = get_permalink($result->ID);
	$value = linkate_unparse_url($value, $url_option);
	return linkate_oth_truncate_text($value, $ext);
}

function linkate_otf_author($option_key, $result, $ext) {
	$type = false;	
	if ($ext) {
		$s = explode(':', $ext);
		if (count($s) == 1) {
			$type = $s[0];
		} 
	}
	switch ($type) {
	case 'display':
		$author = get_the_author_meta('display_name',$result->post_author);
		break;
	case 'full':
		$auth = get_userdata($result->post_author);
		$author = $auth->first_name.' '.$auth->last_name;
		break;
	case 'reverse':
		$auth = get_userdata($result->post_author);
		$author = $auth->last_name.', '.$auth->first_name;
		break;
	case 'first':
		$auth = get_userdata($result->post_author);
		$author = $auth->first_name;
		break;
	case 'last':
		$auth = get_userdata($result->post_author);
		$author = $auth->last_name;
		break;
	default:	
		$author = get_the_author_meta('display_name',$result->post_author);
	}
	return $author;
}

function linkate_otf_authorurl($option_key, $result, $ext) {
	return get_author_posts_url($result->post_author);
}

function linkate_otf_date($option_key, $result, $ext) {
	if ($ext === 'raw') return $result->post_date;
	else return linkate_oth_format_date($result->post_date, $ext);
}

function linkate_otf_dateedited($option_key, $result, $ext) {
	if ($ext === 'raw') return $result->post_modified;
	else return linkate_oth_format_date($result->post_modified, $ext);
}

function linkate_otf_time($option_key, $result, $ext) {
	return linkate_oth_format_time($result->post_date, $ext);
}

function linkate_otf_timeedited($option_key, $result, $ext) {
	return linkate_oth_format_time($result->post_modified, $ext);
}

function linkate_otf_anons($option_key, $result, $ext) {
    $options = get_option($option_key);

    if ($options['anons_len']) {
        $limit = intval($options['anons_len']);
    } else {
        $limit = 220;
    }
    $meta = get_post_meta($result->ID, 'perelink', true);
    if ($meta) {
        $value = $meta;
    } else {
        $value = trim($result->post_excerpt);
        if ($value == '') $value = $result->post_content;
    }
	//$value = linkate_oth_trim_excerpt($value, $ext);
	$excerpt = preg_replace(" (\[.*?\])",'',$value);
    $excerpt = strip_shortcodes($excerpt);
    $excerpt = strip_tags($excerpt);
    $excerpt = mb_substr($excerpt, 0, $limit);
    $next_space_pos = mb_strripos($excerpt, " ");
    if ($next_space_pos)
        $excerpt = mb_substr($excerpt, 0, $next_space_pos);
    $excerpt = trim(preg_replace( '/\s+/', ' ', $excerpt));
    $excerpt = htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); // for json
    $excerpt = $excerpt.'...';
    return $excerpt;
}

function linkate_otf_suggestions($option_key, $result, $ext) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix.'linkate_posts';
	$suggestions = $wpdb->get_var("SELECT suggestions FROM $table_name WHERE pID=$result->ID AND is_term=0 limit 1"); 
	return $suggestions;
}


function linkate_otf_excerpt($option_key, $result, $ext) {
	if (!$ext) {
		$len = 15;
		$type = 'a';
	} else {
		$s = explode(':', $ext);
		if (count($s) == 1) {
			$s[] = 'a';
		}
		$len = $s[0];
		$type = $s[1];	
		if ($type === 'b') {
			if (count($s) > 2) {
				$more = $s[2];
			} else {
				$more = ' &hellip;';
			}	
			if (count($s) > 3) {
				if ($s[3] === 'link') {
					$url = linkate_otf_url($option_key, $result, '');
					$more = '<a href="'.$url.'">'.$more.'</a>';
				}	
			}	
			if (count($s) > 4) {
				$numsent = $s[4];
			}	
		}
	}
	switch ($type) {
	case 'a':
		$value = trim($result->post_excerpt);
		if ($value == '') $value = $result->post_content;
		$value = linkate_oth_trim_excerpt($value, $ext);
		break;
	case 'b':
		$value = trim($result->post_excerpt);
		if ($value === '') {
			$value = $result->post_content;
			$value = convert_smilies($value);
			$value = linkate_oth_trim_extract($value, $len, $more, $numsent);
			$value = apply_filters('get_the_content', $value);
			remove_filter('the_content', 'link_cf_content_filter', 5);
			remove_filter('the_content', 'link_cf_post_filter', 5);
			$value = apply_filters('the_content', $value);
			add_filter('the_content', 'link_cf_content_filter', 5);
			add_filter('the_content', 'link_cf_post_filter', 5);
			
		} else {
			$value = convert_smilies($value);
			$value = apply_filters('get_the_excerpt', $value);
			remove_filter('the_excerpt', 'link_cf_content_filter', 5);
			$value = apply_filters('the_excerpt', $value);
			add_filter('the_excerpt', 'link_cf_content_filter', 5);
		}
		break;
	default:
		$value = trim($result->post_excerpt);
		if ($value == '') $value = $result->post_content;
		$value = linkate_oth_trim_excerpt($value, $len);
		break;
	}
	return $value;
}

function linkate_otf_snippet($option_key, $result, $ext) {
	$len = 100;
	$type = 'char';
	$more = '';
	$link = 'nolink';
	if ($ext) {
		$s = explode(':', $ext);
		if ($s[0]) $len = $s[0];
		if ($s[1]) $type = $s[1];
		if ($s[2]) $more = $s[2];
		if ($s[3]) $link = $s[3];
	}
	if ($link === 'link') {
		$url = linkate_otf_url($option_key, $result, '');
		$more = '<a href="'.$url.'">'.$more.'</a>';
	}	
	return linkate_oth_format_snippet($result->post_content, $option_key, $type, $len, $more); 
}

function linkate_otf_snippetword($option_key, $result, $ext) {
	$len = 100;
	$more = '';
	$link = 'nolink';
	if ($ext) {
		$s = explode(':', $ext);
		if ($s[0]) $len = $s[0];
		if ($s[1]) $more = $s[1];
		if ($s[2]) $link = $s[2];
	}
	if ($link === 'link') {
		$url = linkate_otf_url($option_key, $result, '');
		$more = '<a href="'.$url.'">'.$more.'</a>';
	}	
	return linkate_oth_format_snippet($result->post_content, $option_key, 'word', $len, $more);
}

function linkate_otf_fullpost($option_key, $result, $ext) {
	remove_filter( 'the_content', 'link_cf_content_filter', 5 );
	remove_filter( 'the_content', 'link_cf_post_filter', 5 );
	$value = apply_filters('the_content', $result->post_content);
	add_filter( 'the_content', 'link_cf_content_filter', 5 );
	add_filter( 'the_content', 'link_cf_post_filter', 5 );
	return str_replace(']]>', ']]&gt;', $value);
}

function linkate_otf_commentcount($option_key, $result, $ext) {
	$value = $result->comment_count;
	if ($ext) {
		$s = explode(':', $ext);
		if (count($s) == 3) {
			if ($value == 0) $value = $s[0];
			elseif ($value == 1) $value .= ' ' . $s[1];
			else $value .= ' ' . $s[2];
		}
	}
	return $value;
}

function linkate_otf_commentexcerpt($option_key, $result, $ext) {
	if (!$ext) {
		$len = 55;
		$type = 'a';
	} else {
		$s = explode(':', $ext);
		if (count($s) == 1) {
			$s[] = 'a';
		}
		$len = $s[0];
		$type = $s[1];	
		if ($type === 'b') {
			if (count($s) > 2) {
				$more = $s[2];
			} else {
				$more = ' &hellip;';
			}	
			if (count($s) > 3) {
				if ($s[3] === 'link') {
					$url = linkate_otf_commenturl($option_key, $result, '');
					$more = '<a href="'.$url.'">'.$more.'</a>';
				}	
			}	
		}
	}
	switch ($type) {
	case 'a':
		$value = linkate_oth_trim_comment_excerpt($result->comment_content, $ext);
		break;
	case 'b':
		$value = $result->comment_content;
		$value = convert_smilies($value);

		$text = str_replace(']]>', ']]&gt;', $value);
		if ($len <= count(preg_split('/[\s]+/', strip_tags($text), -1))) {		
			// remove html entities for now	
			$text = str_replace("\x06", "", $text);
			preg_match_all("/&([a-z\d]{2,7}|#\d{2,5});/i", $text, $ents);
			$text = preg_replace("/&([a-z\d]{2,7}|#\d{2,5});/i", "\x06", $text);
			// now we start counting
			$parts = preg_split('/([\s]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
			$in_tag = false;
			$num_words = 0;
			$text = '';
			foreach($parts as $part) {
				if(0 < preg_match('/<[^>]*$/s', $part)) {
					$in_tag = true;
				} else if(0 < preg_match('/>[^<]*$/s', $part)) {
					$in_tag = false;
				}
				if(!$in_tag && '' != trim($part) && substr($part, -1, 1) != '>') {
					$num_words++;
				}
				$text .= $part;
				if($num_words >= $len && !$in_tag) break;
			}
			// put back the missing html entities
			foreach ($ents[0] as $ent) $text = preg_replace("/\x06/", $ent, $text, 1);
			$text = balanceTags($text, true);
			$value = $text . $more;
		}
		$value = apply_filters('get_comment_text', $value);
		break;
	default:
		$value = linkate_oth_trim_comment_excerpt($result->comment_content, $ext);
		break;
	}
	return $value;
}

function linkate_otf_commentsnippet($option_key, $result, $ext) {
	$len = 100;
	$type = 'char';
	$more = '';
	$link = 'nolink';
	if ($ext) {
		$s = explode(':', $ext);
		if ($s[0]) $len = $s[0];
		if ($s[1]) $type = $s[1];
		if ($s[2]) $more = $s[2];
		if ($s[3]) $link = $s[3];
	}
	if ($link === 'link') {
		$url = linkate_otf_commenturl($option_key, $result, '');
		$more = '<a href="'.$url.'">'.$more.'</a>';
	}	
	return linkate_oth_format_snippet($result->comment_content, $option_key, $type, $len, $more);
}

function linkate_otf_commentsnippetword($option_key, $result, $ext) {
	$len = 100;
	$more = '';
	$link = 'nolink';
	if ($ext) {
		$s = explode(':', $ext);
		if ($s[0]) $len = $s[0];
		if ($s[1]) $more = $s[1];
		if ($s[2]) $link = $s[2];
	}
	if ($link === 'link') {
		$url = linkate_otf_commenturl($option_key, $result, '');
		$more = '<a href="'.$url.'">'.$more.'</a>';
	}	
	return linkate_oth_format_snippet($result->comment_content, $option_key, 'word', $len, $more);
}

function linkate_otf_commentdate($option_key, $result, $ext) {
	if ($ext === 'raw') return $result->comment_date;
	return linkate_oth_format_date($result->comment_date, $ext);
}

function linkate_otf_commenttime($option_key, $result, $ext) {
	return linkate_oth_format_time($result->comment_date, $ext);
}

function linkate_otf_commentdategmt($option_key, $result, $ext) {
	if ($ext === 'raw') return $result->comment_date_gmt;
	return linkate_oth_format_date($result->comment_date_gmt, $ext);
}

function linkate_otf_commenttimegmt($option_key, $result, $ext) {
	return linkate_oth_format_time($result->comment_date_gmt, $ext);
}

function linkate_otf_commenter($option_key, $result, $ext) {
	$value = $result->comment_author;
	$value = apply_filters('get_comment_author', $value);
	$value = apply_filters('comment_author', $value);
	return linkate_oth_truncate_text($value, $ext);
}

function linkate_otf_commenterurl($option_key, $result, $ext) {
	$value = $result->comment_author_url;
	$value = apply_filters('get_comment_author_url', $value);
	return linkate_oth_truncate_text($value, $ext);
}

function linkate_otf_commenterlink($option_key, $result, $ext) {
	$url = linkate_otf_commenterurl($option_key, $result, '');
	$author = linkate_otf_commenter($option_key, $result, $ext);
	if (empty($url) || $url == 'http://') $value = $author;
	else $value = "<a href='$url' rel='external nofollow'>$author</a>";
	return $value;
}

function linkate_otf_commenterip($option_key, $result, $ext) {
	return $result->comment_author_IP;
}

function linkate_otf_commenturl($option_key, $result, $ext) {
	$value = apply_filters('the_permalink', get_permalink($result->ID)) . '#comment-' . $result->comment_ID;
	return linkate_oth_truncate_text($value, $ext);
}

function linkate_otf_commentlink($option_key, $result, $ext) {
	$ttl = linkate_otf_commenter($option_key, $result, '');
	$ttl = '<span class="rc-commenter">' . $ttl . '</span>';
	if (!$ext) $ext = ' commented on ';
	$ttl .= $ext;
	$ttl .= '<span class="rc-title">'.linkate_otf_title($option_key, $result, '').'</span>';
	$pml = linkate_otf_commenturl($option_key, $result, '');
	$pdt = linkate_oth_format_date($result->comment_date_gmt, '');
	$pdt .= __(' at ', 'post_plugin_library');
	$pdt .= linkate_oth_format_time($result->comment_date_gmt, '');
	return "<a href=\"$pml\" rel=\"bookmark\" title=\"$pdt\">$ttl</a>";
}

function linkate_otf_commentlink2($option_key, $result, $ext) {
	$commenturl = linkate_otf_commenturl($option_key, $result, '');
	$commentdate = linkate_otf_commentdate($option_key, $result, '');
	$commenttime = linkate_otf_commenttime($option_key, $result, '');
	$title = linkate_otf_title($option_key, $result, '');
	$commenter = linkate_otf_commenter($option_key, $result, '');
	$commentexcerpt = linkate_otf_commentexcerpt($option_key, $result, '10');
	return "<a href=\"$commenturl\" rel=\"bookmark\" title=\"$commentdate at $commenttime on '$title'\">$commenter</a> - $commentexcerpt&hellip;";
}

function linkate_otf_commentpopupurl($option_key, $result, $ext) {
	global $wpcommentspopupfile, $wpcommentsjavascript;
	$output = '';
	if ( $wpcommentsjavascript ) {
		if ( empty( $wpcommentspopupfile ) )
			$home = get_option('home');
		else
			$home = get_option('siteurl');
		$output .= $home . '/' . $wpcommentspopupfile . '?comments_popup=' . $result->ID;
		$output .= '#comment-' . $result->comment_ID;
		$output .= '" onclick="wpopen(this.href); return false';
	}
	return $output;
}

function linkate_otf_catlinks($option_key, $result, $ext) {
	return linkate_otf_categorylinks($option_key, $result, $ext);
}

function linkate_otf_categorylinks($option_key, $result, $ext) {
	$cats = get_the_category($result->ID);
	$value = ''; $n = 0;
	foreach ($cats as $cat) {
		if ($n > 0) $value .= $ext;
		$catname = apply_filters('single_cat_title', $cat->cat_name);
		$value .= '<a href="' . get_category_link($cat->cat_ID) . '" title="' . sprintf(__("View all posts in %s", 'post_plugin_library'), $catname) . '" rel="category tag">'.$catname.'</a> ';
		++$n;
	}
	return $value;
}

function linkate_otf_catnames($option_key, $result, $ext) {
	return linkate_otf_categorynames($option_key, $result, $ext);
}

function linkate_otf_categorynames($option_key, $result, $ext) {
	$cats = get_the_category($result->ID);
	$value = ''; //$n = 0;
	foreach ($cats as $k=>$cat) {
        //if ($n > 0) $value[] = $ext;
        
		$value .= $k === 0 ? $cat->name : ", " . $cat->name;
		//++$n;
	}
    // return implode(", ", $value);
    return $value;
}

function linkate_otf_custom($option_key, $result, $ext) {
	$custom = get_post_custom($result->ID);
	return $custom[$ext][0];
}

function linkate_otf_tags($option_key, $result, $ext) {
	$tags = (array) get_the_tags($result->ID);
	$tag_list = array();
	foreach ( $tags as $tag ) {
		$tag_list[] = $tag->name;
	}
	if (!$ext) $ext = ', ';
	$tag_list = join( $ext, $tag_list );
	return $tag_list;
}

function linkate_otf_taglinks($option_key, $result, $ext) {
	$tags = (array) get_the_tags($result->ID);
	$tag_list = '';
	$tag_links = array();
	foreach ( $tags as $tag ) {
		$link = get_tag_link($tag->term_id);
		if ( is_wp_error( $link ) )
			return $link;
		$tag_links[] = '<a href="' . $link . '" rel="tag">' . $tag->name . '</a>';
	}
	if (!$ext) $ext = ' ';
	$tag_links = join( $ext, $tag_links );
	$tag_links = apply_filters( 'the_tags', $tag_links );
	$tag_list .= $tag_links;
	return $tag_list;
}

function linkate_otf_totalposts($option_key, $result, $ext) {
	global $wpdb;
	$value = '';
	if (function_exists('get_post_type')) {
		$value = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'");
	} else {
		$value = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'");
	}
	return $value;
}

function linkate_otf_totalpages($option_key, $result, $ext) {
	global $wpdb;
	$value = '';
	if (function_exists('get_post_type')) {
		$value = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish'");
	} else {
		$value = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'static'");	
	}
	return $value;
}

function linkate_otf_link($option_key, $result, $ext) {
	$ttl = linkate_otf_title($option_key, $result, $ext);
	$pml = linkate_otf_url($option_key, $result, null);
	$pdt = linkate_otf_date($option_key, $result, null);
	return "<a href=\"$pml\" rel=\"bookmark\" title=\"$pdt\">$ttl</a>";
}

function linkate_otf_score($option_key, $result, $ext) {						
	return sprintf("%.0f", $result->score);	
}

// tries to get the number of post views from a few popular plugins if the are installed
function linkate_otf_postviews($option_key, $result, $ext) {
	global $wpdb;
	// alex king's popularity contest
	if (class_exists('ak_popularity_contest')) $count = $akpc->get_post_total($result->ID);
	// my own post view count
	else if (function_exists('popular_posts_views')) $count = popular_posts_views($result->ID);
	// lester chan's postviews
	else if (function_exists('the_views')) {
		$count = get_post_custom($result->ID);
		$count = intval($count['views'][0]);
	}	
	// mark ghosh's top10
	else if (function_exists('show_post_count')) {$id = $result->ID; $count = $wpdb->get_var("select cntaccess from mostAccessed WHERE postnumber = $id");}
	// Ivan Djurdjevac's CountPosts
	else if (function_exists('HitThisPost')) {$id = $result->ID; $count = $wpdb->get_var("SELECT post_hits FROM $wpdb->posts WHERE ID=$id");}
	if (!$count) $count	= 0;
	return $count;
}

function linkate_oth_get_actual_size($imgtag) {
	// first try extracting the width and height attributes
	if (preg_match('/\s+width\s*=\s*[\'|\"](.*?)[\'|\"]/is', $imgtag, $matches)) {
		$current_width = $matches[1]; 
		if (preg_match('/\s+height\s*=\s*[\'|\"](.*?)[\'|\"]/is', $imgtag, $matches)) {
			$current_height = $matches[1];
		} 
	}
	// then try using the GD library
	if (!(($current_width) && ($current_height))) {
		// extract the image src url
		preg_match('/\s+src\s*=\s*[\'|\"](.*?)[\'|\"]/is', $imgtag, $matches);
		$error_level = error_reporting(0);
		if (function_exists('getimagesize') && $imagesize = getimagesize($matches[1])) {
			$current_width = $imagesize['0'];
			$current_height = $imagesize['1'];
		} else {
			// if all else fails...
			$current_width = $current_height = 0;
		}
		error_reporting($error_level);
	}
	return array($current_width, $current_height);
}

function linkate_oth_image_size_full($w, $h, $imgtag){
	return array(1, 1);
}

function linkate_oth_image_size_scale($w, $h, $imgtag){
	$maxsize = max($w, $h);
	list($current_width, $current_height) = linkate_oth_get_actual_size($imgtag);
	$width_ratio = $height_ratio = 1.0;
	if ($current_width > $maxsize)
		$width_ratio = $maxsize / $current_width;
	if ($current_height > $maxsize)
		$height_ratio = $maxsize / $current_height;
	// the smaller ratio is the one we need to fit it to the constraining box
	$ratio = min( $width_ratio, $height_ratio );
	$w = intval($current_width * $ratio);
	$h = intval($current_height * $ratio);
	return array($w, $h);
}

function linkate_oth_image_size_blank($w, $h, $imgtag){
	return array(0, 0);
}

function linkate_oth_image_size_exact($w, $h, $imgtag){
	return array($w, $h);
}

function linkate_oth_image_size_fixedw($w, $h, $imgtag){
	list($current_width, $current_height) = linkate_oth_get_actual_size($imgtag);
	$h = intval($w * ($current_height / $current_width));
	return array($w, $h);
}

function linkate_oth_image_size_fixedh($w, $h, $imgtag){
	list($current_width, $current_height) = linkate_oth_get_actual_size($imgtag);
	$w = intval($h * ($current_width / $current_height));
	return array($w, $h);
}

function linkate_oth_test($x) {
	if (empty($x)) return 'a';
	if (is_numeric($x)) return 'b';
	return 'c';
}

function linkate_oth_process($w, $h) {
	static $table = array(	'a' => array('a' => 'full', 'b' => 'scale', 'c' => 'blank'), 
							'b' => array('a' => 'scale', 'b' => 'exact', 'c' => 'fixedw'), 
							'c' => array('a' => 'blank', 'b' => 'fixedh', 'c' => 'blank'));
	return 'linkate_oth_image_size_' . $table[linkate_oth_test($w)][linkate_oth_test($h)];
}

function linkate_otf_image($option_key, $result, $ext) {
	// extract any image tags
	$content = $result->post_content;
	if ($ext) { 
		$s = explode(':', $ext);
		if ($s[3] === 'post') {
			$content = apply_filters('the_content', $content);
		}	
	}
	if ($s[4] === 'link') {
		$pattern = '/<a.+?<img.+?>.+?a>/i';
		$pattern2 = '#(<a.+?<img.+?)(/>|>)#is';
	} else {
		$pattern = '/<img.+?>/i';
		$pattern2 = '#(<img.+?)(/>|>)#is';
	}
	if (!preg_match_all($pattern, $content, $matches)) {
		// no <img> tags in content
		if (($s[5]) && ($s[6])) {
			// a default <img> tag has been given
			return $s[5].':'.$s[6];
		} else {
			return '';
		}
	}	
	$i = $s[0];
	if (!$i) $i = 0;
	$imgtag = $matches[0][$i];
	$process = linkate_oth_process($s[1],$s[2]);
	list($w, $h) = $process(intval($s[1]), intval($s[2]), $imgtag);
	if ($w === 0) return '';
	if ($w === 1) return $imgtag;
	// remove height or width if present
	$imgtag = preg_replace('/(width|height)\s*=\s*[\'|\"](.*?)[\'|\"]/is', '', $imgtag);
	// insert the new size
	$imgtag = preg_replace($pattern2, "$1 height=\"$h\" width=\"$w\" $2", $imgtag);
	return $imgtag;
}

function linkate_otf_imagesrc($option_key, $result, $ext) {
	$options = get_option($option_key);
	$url_option = $options['relative_links'];
	$crb_image_size = $options['crb_image_size'];
	$crb_placeholder_path = empty($options['crb_placeholder_path']) ? WP_PLUGIN_URL . '/cherrylink/img/imgsrc_placeholder.jpg' : $options['crb_placeholder_path'];
	$crb_content_filter = $options['crb_content_filter'] == 1;

    // Check Featured Image first
    $imgsrc = get_the_post_thumbnail_url($result->ID, $crb_image_size);

    if ($imgsrc) {
        // $featured_src = linkate_get_featured_src($result->ID);
        // $featured_src = get_site_url() . "/wp-content/uploads/" . $featured_src;
        $imgsrc = linkate_unparse_url($imgsrc, $url_option);
        return $imgsrc;
    }

    // DANGEROUS but possibly can find more images
	$content = $result->post_content;
	if ($crb_content_filter) {
		$content = str_replace("[crb_show_block]", "", $content); // preventing nesting overflow
		$content = apply_filters('the_content', $content);
	}

	// Try to extract img tags from html
    $pattern = '/<img.+?src\s*=\s*[\'|\"](.*?)[\'|\"].+?>/i';
    $found = preg_match_all($pattern, $content, $matches);
    if ($found)  {
        // $i = isset($s[0]) ? $s[0] : false;
        // if (!$i) $i = 0;
        // $imgsrc = $matches[1][$i];
        $imgsrc = $matches[1][0];
        $imgsrc = linkate_unparse_url($imgsrc, $url_option);
    }
	
	// Well, shite, return placeholder
    if (!$imgsrc) { // placeholder
		return $crb_placeholder_path;
	}

    // Now we try to find suitable size
	// first check using vanilla url
	$att_id = attachment_url_to_postid($imgsrc);

	// cut the shit outta here
	if (!$att_id) {
        $imgsrc = preg_replace("~-\d{2,4}x\d{2,4}(?!.*-\d{2,4}x\d{2,4})~", '', $imgsrc);
        $att_id = attachment_url_to_postid($imgsrc);
	}
	
	// If not found again, return imgsrc from prev step and relax
	if (!$att_id) {
		return $imgsrc;
	}

	// Now lets try to get needed size
    // If size is empty then original will be returned
    $attachement = wp_get_attachment_image_url( $att_id, $crb_image_size );
	if ($attachement) {
		$imgsrc = wp_get_attachment_image_url( $att_id, $crb_image_size );
    }

    if (!$imgsrc) // placeholder
        $imgsrc = $crb_placeholder_path;

	return $imgsrc;
}

function linkate_get_featured_src($post_id) {
	global $wpdb;
	$posts_table = $wpdb->prefix . "posts";
	$meta_table = $wpdb->prefix . "postmeta";
	$query = "SELECT wpm2.meta_value
				FROM $posts_table wp
			    INNER JOIN $meta_table wpm
			        ON (wp.ID = wpm.post_id AND wpm.meta_key = '_thumbnail_id')
			    INNER JOIN $meta_table wpm2
			        ON (wpm.meta_value = wpm2.post_id AND wpm2.meta_key = '_wp_attached_file')
			    WHERE wp.ID = $post_id";

	$res = $wpdb->get_var($query);
	return $res;
}

function linkate_otf_imagealt($option_key, $result, $ext) {
	// extract any image tags
	$content = $result->post_content;
	if ($ext) { 
		$s = explode(':', $ext);
		if ($s[1] === 'post') {
			$content = apply_filters('the_content', $content);
		}	
		if ($s[2]) $suffix = $s[2];
	}
	$pattern = '/<img.+?alt\s*=\s*[\'|\"](.*?)[\'|\"].+?>/i';
	if (!preg_match_all($pattern, $content, $matches)) return '';
	$i = $s[0];
	if (!$i) $i = 0;
	return $matches[1][$i];
}

function linkate_otf_gravatar($option_key, $result, $ext) {
	$size = 96;
	$rating = '';
	$default = "http://www.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=$size"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
	if ($ext) {
		$s = explode(':', $ext);
		if (isset($s[0])) $size = $s[0];
		if (isset($s[1])) $rating = $s[1];
		if (isset($s[3])) {  
			$default = 'http:'.$s[3];
		} else {
			if (isset($s[2])) $default = $s[2];
		}	
	}
	$email = '';
	if (isset($result->comment_author_email)) {
		$email = $result->comment_author_email;
	} else {
		$user = get_userdata($result->post_author);
		if ($user) $email = $user->user_email;
	}
	if (!empty($email)) {
		$out = 'http://www.gravatar.com/avatar/';
		$out .= md5(strtolower($email));
		$out .= '?s='.$size;
		$out .= '&amp;d=' . urlencode( $default );
		if ('' !== $rating)
			$out .= "&amp;r={$rating}";
		$avatar = "<img alt='' src='{$out}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";
	} else {
		$avatar = "<img alt='' src='{$default}' class='avatar avatar-{$size} avatar-default' height='{$size}' width='{$size}' />";
	}
	return apply_filters('get_avatar', $avatar, $email, $size, $default);
}

// returns the principal category id of a post -- if a cats are hierarchical chooses the most specific -- if multiple cats chooses the first (numerically smallest)
function linkate_otf_categoryid($option_key, $result, $ext) {
	$cats = get_the_category($result->ID);
	foreach ($cats as $cat) {
		$parents[] = $cat->category_parent;
	}
	foreach ($cats as $cat) {
		if (!in_array($cat->cat_ID, $parents)) $categories[] = $cat->cat_ID;
	}
	return $categories[0];
}

// fails if parentheses are out of order or nested
function linkate_oth_splitapart($subject) {
	$bits = explode(':', $subject);
	$inside = false;
	$newbits = array();
	$acc = '';
	foreach ($bits as $bit) {
		if (false !== strpos($bit, '{')) {
			$inside = true;
			$acc = '';
		}	
		if (false !== strpos($bit, '}')) {
			$inside = false;
			if ($acc !== '') {
				$acc .= ':' . $bit;
			} else {
				$acc = $bit;
			}
		}	
		if ($inside) {
			if ($acc !== '') {
				$acc .= ':' . $bit;
			} else {
				$acc = $bit;
			}
		} else {
			if ($acc !== '') {
				$newbits[] = $acc;
				$acc = '';
			} else {
				$newbits[] = $bit;
			}	
		}
	}
	return $newbits;
}

function linkate_otf_if($option_key, $result, $ext) {
	global $post;
	$ID = link_cf_current_post_id();
	$condition = 'true';
	$true = '';
	$false = '';
	if ($ext) {
		$s = linkate_oth_splitapart($ext);
		if (isset($s[0])) $condition = $s[0];
		if (isset($s[1])) $true = $s[1];
		if (isset($s[2])) $false = $s[2];
	}
	if (strpos($condition, '{')!==false) {
		$condition = link_cf_expand_template($result, $condition, link_cf_prepare_template($condition), $option_key);
	}
	if (eval("return ($condition);")) $tag = $true; else $tag = $false;
	// if the replacement tag contains pseudotags expand them
	if (strpos($tag, '}')!==false) {
		$tag = link_cf_expand_template($result, $tag, link_cf_prepare_template($tag), $option_key);
	}
	return $tag;
}

function linkate_otf_php($option_key, $result, $ext) {
	global $post;
	$ID = link_cf_current_post_id();
	$value = '';
	if ($ext) {
		if (strpos($ext, '{')!==false) {
			$ext = link_cf_expand_template($result, $ext, link_cf_prepare_template($ext), $option_key);
		}
		ob_start();
		eval($ext);
		$value = ob_get_contents();
		ob_end_clean();
	}
	return $value;
}


// ****************************** Helper Functions *********************************************

function linkate_oth_truncate_text($text, $ext) {
	if (!$ext) {
		return $text;
	}
	$s = explode(':', $ext);
	if (count($s) > 2) {
		return $text;
	}
	if (count($s) == 1) {
		$s[] = 'wrap';
	}
	$length = $s[0];
	$type = $s[1];
	switch ($type) {
	case 'wrap':
		$length += strlen('<br />');
		if (!function_exists('mb_detect_encoding')) {
			return wordwrap($text, $length, '<br />', true);
		} else {
			$e = mb_detect_encoding($text);
			$formatted = '';
			$position = -1;
			$prev_position = 0;
			$last_line = -1;
			while($position = mb_strpos($text, " ", ++$position, $e)) {
				if($position > $last_line + $length + 1) {
					$formatted.= mb_substr($text, $last_line + 1, $prev_position - $last_line - 1, $e).'<br />';
					$last_line = $prev_position;
				}
				$prev_position = $position;
			}
			$formatted.= mb_substr($text, $last_line + 1, mb_strlen( $text ), $e);
			return $formatted;
		}	
	case 'chop':
		if (!function_exists('mb_detect_encoding')) {
			 return substr($text, 0, $length);
		} else {
			$e = mb_detect_encoding($text);
			return mb_substr($text, 0, $length, $e);
		}	
	case 'trim':
		if (strlen($text) > $length) {
		} else {
			return $text;
		}	
		if (!function_exists('mb_detect_encoding')) {
			$textlen = strlen($text);
			if ($textlen > $length) {
				$text = substr($text, 0, $length-2);
				return rtrim($text,".").'&hellip;';
			} else {
				return $text;
			}
		} else {
			$e = mb_detect_encoding($text);
			$textlen = mb_strlen($text, $e);
			if ($textlen > $length) {
				$text = mb_substr($text, 0, $length-2, $e);
				return rtrim($text,".").'&hellip;';
			} else {
				return $text;
			}
		}	
	case 'snip':
		if (!function_exists('mb_detect_encoding')) {
			$textlen = strlen($text);
			if ($textlen > $length) {
				$b = floor(($length - 2)/2);
				$l = $textlen - $b - 1;
				return substr($text, 0, $b).'&hellip;'.substr($text, $l);
			} else {
				return $text;
			}
		} else {
			$e = mb_detect_encoding($text);
			$textlen = mb_strlen($text, $e);
			if ($textlen > $length) {
				$b = floor(($length - 2)/2);
				$l = $textlen - $b - 1;
				return mb_substr($text, 0, $b, $e).'&hellip;'.mb_substr($text, $l, 1000, $e);
			} else {
				return $text;
			}
		}	
	default:
		return wordwrap($t, $length, '<br />', true);
	}
}	

function linkate_oth_trim_extract($text, $len, $more, $numsent) {
	$text = str_replace(']]>', ']]&gt;', $text);
	if(strpos($text, '<!--more-->')) {
		$parts = explode('<!--more-->', $text, 2);
		$text = $parts[0];
	} else {
		if ($len > count(preg_split('/[\s]+/', strip_tags($text), -1))) return $text;		
		// remove html entities for now	
		$text = str_replace("\x06", "", $text);
		preg_match_all("/&([a-z\d]{2,7}|#\d{2,5});/i", $text, $ents);
		$text = preg_replace("/&([a-z\d]{2,7}|#\d{2,5});/i", "\x06", $text);
		// now we start counting
		$parts = preg_split('/([\s]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		$in_tag = false;
		$num_words = 0;
		$sentences = array();
		$words = '';
		foreach($parts as $part) {
			if(0 < preg_match('/<[^>]*$/s', $part)) {
				$in_tag = true;
			} else if(0 < preg_match('/>[^<]*$/s', $part)) {
				$in_tag = false;
			}
			if(!$in_tag && '' != trim($part) && substr($part, -1, 1) != '>') {
				$num_words++;
			}
			if(!$in_tag && '' != trim($part) && false !== strpos('.?!', substr($part, -1, 1))) {
				$sentences [] = $words . $part;
				$words = '';
			} else {
				$words .= $part;
			}
			if($num_words >= $len && !$in_tag) break;
		}
		if (!isset($numsent)) {
			$text = implode('', $sentences) . $words;
		} else {
			$numsent = abs($numsent);
			if ($numsent == 0) {
				$text = implode('', $sentences);
			} else {
				$text = implode('', array_slice($sentences, 0, $numsent));
			}
		}
		// put back the missing html entities
	    foreach ($ents[0] as $ent) $text = preg_replace("/\x06/", $ent, $text, 1);
	}
	$text = balanceTags($text, true);
	$text = $text . $more;
	return $text;
}

function linkate_oth_format_snippet($content, $option_key, $trim, $len, $more) {
	$content = strip_tags($content);
	$p = get_option($option_key);
	if ($p['stripcodes']) $content = linkate_oth_strip_special_tags($content, $p['stripcodes']);
	// strip extra whitespace
	$content = preg_replace('/\s+/u', ' ', $content);
	$content = stripslashes($content);
	if (function_exists('mb_detect_encoding')) $enc = mb_detect_encoding($content);
	// grab a maximum number of characters
	if ($enc) {
		mb_internal_encoding($enc);
		if (mb_strlen($content) >= $len) {
			$snippet = mb_substr($content, 0, $len);
			if ($trim == 'word' && mb_strlen($snippet) == $len) {
				// trim back to the last full word--NB if our snippet ends on a word
				// boundary we still have to trim back to the non-word character
				// (the final 's' in the pattern makes sure we match newlines)
				preg_match('/^(.*)\W/su', $snippet, $matches);
				//if we can't get a single full word we use the full snippet
				// (we use $matches[1] because we don't want the white-space)
				if ($matches[1]) $snippet = $matches[1];
			} 
			$snippet .= $more;
		} else {
			$snippet = $content;
		}
	} else {
		if (strlen($content) >= $len) {
			$snippet = substr($content, 0, $len);
			if ($trim == 'word' && strlen($snippet) == $len) {
				// trim back to the last full word--NB if our snippet ends on a word
				// boundary we still have to trim back to the non-word character
				// (the final 's' in the pattern makes sure we match newlines)
				preg_match('/^(.*)\W/s', $snippet, $matches);
				//if we can't get a single full word we use the full snippet
				// (we use $matches[1] because we don't want the white-space)
				if ($matches[1]) $snippet = $matches[1];
			} 
			$snippet .= $more;
		} else {
			$snippet = $content;
		}
	}
	return $snippet;
}

function linkate_oth_strip_special_tags($text, $stripcodes) {
		$numtags = count($stripcodes);
		for ($i = 0; $i < $numtags; $i++) {
			if (!$stripcodes[$i]['start'] || !$stripcodes[$i]['end']) return $text;
			$pattern = '/('. linkate_oth_regescape($stripcodes[$i]['start']) . '(.*?)' . linkate_oth_regescape($stripcodes[$i]['end']) . ')/i';
			$text = preg_replace($pattern, '', $text);
		}
		return $text;
}

function linkate_oth_trim_excerpt($content, $len) {
	// taken from the wp_trim_excerpt filter
	remove_filter( 'the_content', 'link_cf_content_filter', 5 );
	remove_filter( 'the_content', 'link_cf_post_filter', 5 );
	$text = apply_filters('the_content', $content);
	add_filter( 'the_content', 'link_cf_content_filter', 5 );
	add_filter( 'the_content', 'link_cf_post_filter', 5 );
	$text = str_replace(']]>', ']]&gt;', $text);
	$text = strip_tags($text);
	if (!$len) $len = 55; 
	$excerpt_length = $len;
	$words = explode(' ', $text, $excerpt_length + 1);
	if (count($words) > $excerpt_length) {
		array_pop($words);
		$text = implode(' ', $words);
	}
	$text = convert_smilies($text);
	return $text;
}

function linkate_oth_trim_comment_excerpt($content, $len) {
	// adapted from the wp_trim_excerpt filter
	$text = $content;
	$text = apply_filters('get_comment_text', $text);
	$text = str_replace(']]>', ']]&gt;', $text);
	$text = strip_tags($text);
	if (!$len) $len = 55; 
	$excerpt_length = $len;
	$words = explode(' ', $text, $excerpt_length + 1);
	if (count($words) > $excerpt_length) {
		array_pop($words);
		$text = implode(' ', $words);
	}
	$text = convert_smilies($text);
	return $text;
}
	
function linkate_oth_format_date($date, $fmt) {
	if (!$fmt) $fmt = get_option('date_format');
	$d = mysql2date($fmt, $date);
	$d = apply_filters('get_the_time', $d, $fmt);
	return apply_filters('the_time', $d, $fmt);
}

function linkate_oth_format_time($time, $fmt) {
	if (!$fmt) $fmt = get_option('time_format');
	$d = mysql2date($fmt, $time);
	$d = apply_filters('get_the_time', $d, $fmt);
	return apply_filters('the_time', $d, $fmt);
}

function linkate_oth_regescape($s) {
	$s = str_replace('\\', '\\\\', $s);
	$s = str_replace('/', '\\/', $s);
	$s = str_replace('[', '\\[', $s);
	$s = str_replace(']', '\\]', $s);
	return $s;
}


function linkate_unparse_url($url, $opt) {
	$parsed_url = parse_url($url);
	if ($parsed_url) {

		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		if ($opt === "full") {
			return "$scheme$host$port$path";
		}
		if ($opt === "no_proto") {
			return "//$host$port$path";
		}
		if ($opt === "no_domain") {
			return "$path";
		}
	}
	return $url;
}