<?php
/*
 * Linkate Posts
 */

define('LP_OT_LIBRARY', true);

// Called by the post plugins to match output tags to the actions that evaluate them
function lp_output_tag_action($tag) {
	return 'linkate_otf_'.$tag;
}

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

function linkate_otf_score($option_key, $result, $ext) {						
	return sprintf("%.0f", $result->score);	
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
		$imgsrc = $attachement;
    }

    if (!$imgsrc) // placeholder
        $imgsrc = $crb_placeholder_path;

	return $imgsrc;
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
	
function linkate_oth_format_date($date, $fmt) {
	if (!$fmt) $fmt = get_option('date_format');
	$d = mysql2date($fmt, $date);
	$d = apply_filters('get_the_time', $d, $fmt);
	return apply_filters('the_time', $d, $fmt);
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
