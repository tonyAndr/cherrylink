<?php

 /** 

 ** Linkate terms, cats, custom tax

 **/

define('LINKATE_TERMS_LIBRARY', true);

//echo hierarchical_term_tree();

// def attrs just for info
$get_terms_default_attributes = array (
            'taxonomy' => 'category', //empty string(''), false, 0 don't work, and return empty array
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => true, //can be 1, '1' too
            'include' => 'all', //empty string(''), false, 0 don't work, and return empty array
            'exclude' => 'all', //empty string(''), false, 0 don't work, and return empty array
            'exclude_tree' => 'all', //empty string(''), false, 0 don't work, and return empty array
            'number' => false, //can be 0, '0', '' too
            'offset' => '',
            'fields' => 'all',
            'name' => '',
            'slug' => '',
            'hierarchical' => true, //can be 1, '1' too
            'search' => '',
            'name__like' => '',
            'description__like' => '',
            'pad_counts' => false, //can be 0, '0', '' too
            'get' => '',
            'child_of' => false, //can be 0, '0', '' too
            'childless' => false,
            'cache_domain' => 'core',
            'update_term_meta_cache' => true, //can be 1, '1' too
            'meta_query' => '',
            'meta_key' => array(),
            'meta_value'=> '',
    );

// Get terms, including tags and custom
// Creates hierarchical list for CherryLink panel
function hierarchical_term_tree($category = 0, $taxonomy = array()) {
    $output_template_item_prefix = '<li><span class="link-counter"  title="Найдено в тексте / переход к ссылке">[ 0 ]</span><div  title="Нажмите для вставки в текст" class="linkate-link link-term" data-url="{url}" data-title="{title}" data-taxonomy="{taxonomy}"><span class="link-title">';
    $output_template_item_suffix = '</span></div></li>';
    $list_prefix = '<ul class="linkate-terms-list">';
    $list_suffix = '</ul>';
    $output_tepmlate_devider = '<li class="linkate-terms-devider">{taxonomy}</li>';
    $r = ''; // tax item

    // get all terms from DB
    $args = array( 
        'parent' => $category,
        'taxonomy' => $taxonomy, // if not empty - looking for children
        'hide_empty'    => '0',
        'orderby' => 'taxonomy',
        'order' => 'ASC',
    );

    $next = get_terms($args);

    if ($next) {
        $r .= $list_prefix;

        foreach ($next as $cat) {
             if (!$cat instanceof WP_Term || $cat->taxonomy == 'nav_menu')
                 continue;

            if ($taxonomy != $cat->taxonomy) { // if next type of taxonomy - add header/divider
                $taxonomy = $cat->taxonomy;
                $label = is_object(get_taxonomy($cat->taxonomy)) ? get_taxonomy($cat->taxonomy)->label : $cat->taxonomy;
                $r .= str_replace('{taxonomy}', $label, $output_tepmlate_devider);
            }
            $r .= str_replace(  // item template with values
                    array('{url}','{title}','{taxonomy}'),
                    array(get_term_link($cat),$cat->name,$cat->taxonomy),
                    $output_template_item_prefix) .  $cat->name . ' ('.$cat->count.')' . $output_template_item_suffix;
            $r .= $cat->term_id !== 0 ? hierarchical_term_tree($cat->term_id, $taxonomy) : null; // check children

        }

        $r .= $list_suffix;
    }

    return $r;
}

// For quick filtering Classic
function linkate_get_all_categories($category = 0, $level = 0) {
	// get all terms from DB
	$args = array(
		'parent' => $category,
		'taxonomy' => 'category', // if not empty - looking for children
		'hide_empty'    => '0',
	);

	$r = array();

	$next = get_terms($args);

	if ($next) {
		foreach ($next as $cat) {
			if (!$cat instanceof WP_Term)
				continue;

			$r[] = array ($level => $cat->name);

			if ($cat->term_id !== 0) {
				$r = array_merge($r, linkate_get_all_categories($cat->term_id, $level+1));
			}

		}
	}

	return $r;
}

/* ====== GUTENBERG ====== */

// Cat links list, including custom and tags
function linkate_gutenberg_hierarchical_terms($category = 0, $taxonomy = array()) {
    $r = array(); // tax item

    // get all terms from DB
    $args = array( 
        'parent' => $category,
        'taxonomy' => $taxonomy, // if not empty - looking for children
        'hide_empty'    => '0',
        'orderby' => 'taxonomy',
        'order' => 'ASC',
    );

    $next = get_terms($args);

    if ($next) {
        foreach ($next as $cat) {
             if (!$cat instanceof WP_Term || $cat->taxonomy == 'nav_menu')
                 continue;

            if ($taxonomy != $cat->taxonomy) { // if next type of taxonomy - add header/divider
                $taxonomy = $cat->taxonomy;
                $label = is_object(get_taxonomy($cat->taxonomy)) ? get_taxonomy($cat->taxonomy)->label : $cat->taxonomy;
                // $r .= str_replace('{taxonomy}', $label, $output_tepmlate_devider);
                $r[] = array(
                    "name" => $label,
                    "is_divider" => "yes"
                );
            }
            // $r .= str_replace(  // item template with values
            //         array('{url}','{title}','{taxonomy}'),
            //         array(get_term_link($cat),$cat->name,$cat->taxonomy),
            //         $output_template_item_prefix) .  $cat->name . ' ('.$cat->count.')' . $output_template_item_suffix;

            $r[] = array(
                "url" => get_term_link($cat),
                "name" => $cat->name,
                "taxonomy" => $cat->taxonomy,
                "post_count" => $cat->count,
                "is_divider" => "no",
                "children" => []
            ); 

            // $r .= $cat->term_id !== 0 ? linkate_gutenberg_hierarchical_terms($cat->term_id, $taxonomy) : null; // check children

			if ($cat->term_id !== 0) {
				$r[sizeof($r)-1]['children'] = linkate_gutenberg_hierarchical_terms($cat->term_id, $taxonomy);
			}


        }
    }

    return $r;
}
//, returns json
function linkate_gutenberg_hierarchical_terms_json() {
    $cats = linkate_gutenberg_hierarchical_terms();
    echo json_encode($cats);
    wp_die();
}
add_action('wp_ajax_linkate_gutenberg_hierarchical_terms_json', 'linkate_gutenberg_hierarchical_terms_json');

// For quick filtering Gutenberg
function linkate_get_all_categories_gutenberg($category = 0) {
	// get all terms from DB
	$args = array(
		'parent' => $category,
		'taxonomy' => 'category', // if not empty - looking for children
		'hide_empty'    => '0',
	);

	$r = array();

	$next = get_terms($args);

	if ($next) {
		foreach ($next as $cat) {
			if (!$cat instanceof WP_Term)
				continue;

			$r[] = array ('name' => $cat->name, 'children' => []);

			if ($cat->term_id !== 0) {
				$r[sizeof($r)-1]['children'] = linkate_get_all_categories_gutenberg($cat->term_id);
			}

		}
	}

	return $r;
}
//, returns json
function linkate_get_all_categories_json() {
    $cats = linkate_get_all_categories_gutenberg();
    echo json_encode($cats);
    wp_die();
}
add_action('wp_ajax_linkate_get_all_categories_json', 'linkate_get_all_categories_json');
//$str = "[
//	{\"cat\": \"name\",
//		\"sub\": [
//			{\"cat\": \"name\"},
//			{\"cat\": \"name\"},
//			{\"cat\": \"name\",
//				\"sub\": [
//					{\"cat\": \"name\"}
//				]
//			}
//
//		]
//	},
//	{\"cat\": \"name\",
//		\"sub\": [
//			{\"cat\": \"name\"},
//			{\"cat\": \"name\"},
//			{\"cat\": \"name\",
//				\"sub\": [
//					{\"cat\": \"name\"}
//				]
//			}
//
//		]
//	}
//]";
//
//$data = json_decode($str, true);
//exit();