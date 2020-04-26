<?php
/*
 * Linkate Posts
 */
 
define('LINKATE_ACF_LIBRARY', true);

function link_cf_is_base64_encoded($data)
{
    if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
        return TRUE;
    } else {
        return FALSE;
    }
};

function link_cf_options_from_post($options, $args) {
	foreach ($args as $arg) {
		switch ($arg) {
		case 'limit':
		case 'skip':
		    $options[$arg] = link_cf_check_cardinal($_POST[$arg]);
			break;
		case 'excluded_cats':
		case 'included_cats':
			if (isset($_POST[$arg]) && !empty($_POST[$arg])) {
				// get the subcategories too
				if (function_exists('get_term_children')) {
					$catarray = $_POST[$arg];
					$catarray = is_array($catarray) ? $catarray : explode(",", $catarray);
					foreach ($catarray as $cat) {
						$catarray = array_merge($catarray, get_term_children($cat, 'category'));
					}
					$_POST[$arg] = array_unique($catarray);
				}
				$options[$arg] = implode(',', $_POST[$arg]);
			} else {
				$options[$arg] = '';
			}	
			break;
		case 'excluded_authors':
        case 'included_authors':
        case 'show_customs':
        case 'suggestions_donors_src':
			if (isset($_POST[$arg]) && !empty($_POST[$arg])) {

				$options[$arg] = is_array($_POST[$arg]) ? implode(',', $_POST[$arg]) : $_POST[$arg];
			} else {
				$options[$arg] = '';
			}	
			break;
		case 'excluded_posts':
		case 'included_posts':
			$check = explode(',', rtrim($_POST[$arg]));
			$ids = array();
			foreach ($check as $id) {
				$id = link_cf_check_cardinal($id);
				if ($id !== 0) $ids[] = $id;
			}
			$options[$arg] = implode(',', array_unique($ids));
			break;
		case 'stripcodes':
			$st = explode("\n", trim($_POST['starttags']));
			$se = explode("\n", trim($_POST['endtags']));
			if (count($st) != count($se)) {
				$options['stripcodes'] = array(array());
			} else {
				$num = count($st);
				for ($i = 0; $i < $num; $i++) {
					$options['stripcodes'][$i]['start'] = $st[$i];
					$options['stripcodes'][$i]['end'] = $se[$i];
				}
			}
			break;
        case 'age':
            if (isset($_POST['age']) && is_array($_POST['age'])) {
                $options['age']['direction'] = $_POST['age']['direction'];
                $options['age']['length'] = link_cf_check_cardinal($_POST['age']['length']);
                $options['age']['duration'] = $_POST['age']['duration'];
            } else {
                $options['age']['direction'] = $_POST['age-direction'];
                $options['age']['length'] = link_cf_check_cardinal($_POST['age-length']);
                $options['age']['duration'] = $_POST['age-duration'];
            }
        break;
        case 'custom':
            if (isset($_POST['custom']) && is_array($_POST['custom'])) {
                $options['custom']['key'] = $_POST['custom']['key'];
                $options['custom']['op'] = $_POST['custom']['op'];
                $options['custom']['value'] = $_POST['custom']['value'];
            } else {
                $options['custom']['key'] = $_POST['custom-key'];
                $options['custom']['op'] = $_POST['custom-op'];
                $options['custom']['value'] = $_POST['custom-value'];
            }
			break;
        case 'sort':
            if (isset($_POST['sort']) && is_array($_POST['sort'])) {
                $options['sort']['by1'] = $_POST['sort']['by1'];
                $options['sort']['order1'] = $_POST['sort']['order1'];
                $options['sort']['case1'] = $_POST['sort']['case1'];
                $options['sort']['order2'] = $_POST['sort']['order2'];
                $options['sort']['by2'] = $_POST['sort']['by2'];
                $options['sort']['case2'] = $_POST['sort']['case2']; 
            } else {
                $options['sort']['by1'] = $_POST['sort-by1'];
                $options['sort']['order1'] = $_POST['sort-order1'];
                $options['sort']['case1'] = $_POST['sort-case1'];
                $options['sort']['order2'] = $_POST['sort-order2'];
                $options['sort']['by2'] = $_POST['sort-by2'];
                $options['sort']['case2'] = $_POST['sort-case2'];
            }
            
			if ($options['sort']['order1'] === 'SORT_ASC') $options['sort']['order1'] = SORT_ASC; else $options['sort']['order1'] = SORT_DESC; 
			if ($options['sort']['order2'] === 'SORT_ASC') $options['sort']['order2'] = SORT_ASC; else $options['sort']['order2'] = SORT_DESC; 
			if ($options['sort']['by1'] === '') {
				$options['sort']['order1'] = SORT_ASC;
				$options['sort']['case1'] = 'false';
				$options['sort']['by2'] = '';
			}
			if ($options['sort']['by2'] === '') {
				$options['sort']['order2'] = SORT_ASC;
				$options['sort']['case2'] = 'false';
			}
			break;
		case 'status':
			unset($options['status']);
			if (isset($_POST['status']) && is_array($_POST['status'])) {
                $options['status']['publish'] = $_POST['status']['publish'];
                $options['status']['private'] = $_POST['status']['private'];
                $options['status']['draft'] = $_POST['status']['draft'];
                $options['status']['future'] = $_POST['status']['future'];
            } else {
                $options['status']['publish'] = $_POST['status-publish'];
                $options['status']['private'] = $_POST['status-private'];
                $options['status']['draft'] = $_POST['status-draft'];
                $options['status']['future'] = $_POST['status-future'];
            }
			break;
		case 'num_terms':
			$options['num_terms'] = $_POST['num_terms'];
			if ($options['num_terms'] < 1) $options['num_terms'] = 50;
            break;
            
        case 'weight_title':
        case 'weight_content':
        case 'weight_tags':
            $options[$arg] = round((double) $_POST[$arg], 2);
            break;
		case 'multilink':
		case 'compare_seotitle':
			if (isset($options[$arg])) {
				$options[$arg] = 'checked';
			} else {
				$options[$arg] = '';
			}
		case 'link_before':
		case 'link_after':		
		case 'term_before':
		case 'term_after':
		case 'link_temp_alt':
		case 'term_temp_alt':
		case 'crb_temp_before': // For Relevant Block Addon
		case 'crb_temp_link':
		case 'crb_temp_after':
		    $options[$arg] = link_cf_is_base64_encoded($_POST[$arg]) ? $_POST[$arg] : base64_encode(urlencode(str_replace("'", "\"", $_POST[$arg])));
            break;
        case 'crb_css_override':
            $options[$arg] = $_POST[$arg];
            break;
        case 'export':
//        	parse_str(base64_decode($_POST['export']),$options);
            $options = $_POST['export'];
        	parse_str($_POST['export'], $options);
        	break;
		default:
			$options[$arg] = trim($_POST[$arg]);
		}
	}
	return $options;
}

function encodeURIComponent($str) {
    $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
    return strtr(rawurlencode($str), $revert);
}

function link_cf_check_cardinal($string) {
	$value = intval($string);
	return ($value > 0) ? $value : 0;
}

function link_cf_display_available_tags($is_term) {
	?>
		<strong>Доступные теги:</strong>
		<ul class="linkate-available-tags">
		<li><strong>{title}</strong> - Заголовок H1;</li>
		<li><strong>{url}</strong> - адрес ссылки;</li>
		<?php if (!$is_term) {
			?>
			<li><strong>{title_seo}</strong> - Из AIOSeo или Yoast;</li>
	        <li><strong>{categorynames}</strong> - категории;</li> 
			<li><strong>{date}</strong> - дата;</li>
			<li><strong>{author}</strong> - автор;</li>
			<li><strong>{postid}</strong> - id поста;</li>
			<li><strong>{imagesrc}</strong> - ссылка на превью;</li>
			<li><strong>{anons}</strong> - текст анонса.</li>
			<?php
		} ?>
		</ul>
	<?php
}
function link_cf_get_available_tags($is_term) {
	$tags ='
		<strong>Доступные теги:</strong>
		<ul class="linkate-available-tags">
		<li><strong>{title}</strong> - Заголовок H1;</li>
		<li><strong>{url}</strong> - адрес ссылки;</li>';

	if (!$is_term) {
		$tags .= '
			<li><strong>{title_seo}</strong> - Из AIOSeo или Yoast;</li>
	        <li><strong>{categorynames}</strong> - категории;</li> 
			<li><strong>{date}</strong> - дата;</li>
			<li><strong>{author}</strong> - автор;</li>
			<li><strong>{postid}</strong> - id поста;</li>
			<li><strong>{imagesrc}</strong> - ссылка на превью;</li>
			<li><strong>{anons}</strong> - текст анонса.</li>';
	}
	$tags .= '</ul>';
	return $tags;
}


function get_linkate_version($prefix) {
	$plugin_version = str_replace('-', '_', $prefix) . '_version';
	global $$plugin_version;
	return ${$plugin_version};
}
function link_cf_display_export_template($export) {
	?>
	<label for="export"><?php _e('Настройки плагина:', 'post_plugin_library') ?></label>
	<textarea name="export" id="export" rows="10"><?php echo $export; ?></textarea> 
	<?php
}

function link_cf_display_accessibility_template($hash_field) {
	?>

	<?php
}


function link_cf_display_accessibility_response($info) {

}

/*

	inserts a form button to completely remove the plugin and all its options etc.

*/

function link_cf_confirm_eradicate() {
 return (isset($_POST['eradicate-check']) && 'yes'===$_POST['eradicate-check']);
}

function link_cf_deactivate_plugin($plugin_file) {
	$current = get_option('active_plugins');
	$plugin_file = substr($plugin_file, strlen(WP_PLUGIN_DIR)+1);
	$plugin_file = str_replace('\\', '/', $plugin_file);
	if (in_array($plugin_file, $current)) {
		array_splice($current, array_search($plugin_file, $current), 1); 
		update_option('active_plugins', $current);
	}
}


/*

	For the display of the option pages

*/

function link_cf_display_multilink($multilink, $no_selection_action, $relative_links = "full") {
	?>
		<tr valign="top">
			<th scope="row"><label for="multilink"><?php _e('Разрешить множественную вставку ссылок:', 'post_plugin_library') ?></label></th>
			<td><input name="multilink" type="checkbox" id="multilink" value="cb_multilink" <?php echo $multilink; ?>/></td>
            <td><?php link_cf_prepare_tooltip("Разрешить или запретить вставлять одну и ту же ссылку несколько раз. Если выключено - ссылки перечеркивются в панели перелинковки."); ?></td>
		</tr>
		<tr valign="top">
            <th scope="row"><label for="no_selection_action"><?php _e('Если текст не выделен, что делаем?', 'post_plugin_library') ?></label></th>
            <td>
                <select name="no_selection_action" id="no_selection_action">
                <option <?php if($no_selection_action == 'title') { echo 'selected="selected"'; } ?> value="title">Вставить в анкор Title Seo</option>
                <option <?php if($no_selection_action == 'h1') { echo 'selected="selected"'; } ?> value="h1">Вставить в анкор Заголовок H1</option>
                <option <?php if($no_selection_action == 'placeholder') { echo 'selected="selected"'; } ?> value="placeholder">Вставить в анкор заглушку ТЕКСТ_ССЫЛКИ</option>
                <option <?php if($no_selection_action == 'empty') { echo 'selected="selected"'; } ?> value="empty">Ничего (будет вставлен 1 пробел)</option>
                </select>
            </td>
	    </tr>
		<tr valign="top">
            <th scope="row"><label for="relative_links"><?php _e('Относительные ссылки', 'post_plugin_library') ?></label></th>
            <td>
                <select name="relative_links" id="relative_links">
                <option <?php if($relative_links == 'full') { echo 'selected="selected"'; } ?> value="full">Полный путь (http://domain.ru/page.html)</option>
                <option <?php if($relative_links == 'no_proto') { echo 'selected="selected"'; } ?> value="no_proto">Без протокола (//domain.ru/page.html)</option>
                <option <?php if($relative_links == 'no_domain') { echo 'selected="selected"'; } ?> value="no_domain">Без домена (/page.html)</option>
                </select>
             </td>
            <td><?php link_cf_prepare_tooltip("Применяется к тегам {url} и {imagesrc}, т.е. для ссылок на статьи и изображения соответственно. Относительные ссылки не придется менять при переезде на HTTPS или новый домен."); ?></td>
	    </tr>
	<?php
}

function link_cf_display_limit($limit) {
	?>
	<tr valign="top">
		<th scope="row"><label for="limit"><?php _e('Количество ссылок:', 'post_plugin_library') ?></label></th>
		<td><input name="limit" type="number" id="limit" style="width: 60px;" value="<?php echo $limit; ?>" size="2" /></td>
        <td><?php link_cf_prepare_tooltip("Рекомендую ставить большое число и использовать фильтрацию прямо в редакторе. Если больше доверяете алгоритму, чем КМу - ставьте ограничение и пусть вписывают то, что предложил плагин :)"); ?></td>
	</tr>
	<?php
}
function link_cf_display_limit_ajax($limit_ajax) {
	?>
	<tr valign="top">
		<th scope="row"><label for="limit_ajax"><?php _e('Количество ссылок :', 'post_plugin_library') ?></label></th>
		<td><input name="limit_ajax" type="number" id="limit_ajax" style="width: 60px;" value="<?php echo $limit_ajax; ?>" size="2" /></td>
        <td><?php link_cf_prepare_tooltip("Сколько ссылок будет выведено на панели перелинковки по умолчанию / сколько ссылок подгружать при нажатии на кнопку \"загрузить еще...\""); ?></td>
	</tr>
	<?php
}


function link_cf_display_skip($skip) {
	?>
	<tr valign="top">
		<th scope="row"><label for="skip"><?php _e('Сдвиг от начала на кол-во ссылок:', 'post_plugin_library') ?></label></th>
		<td><input name="skip" type="number" id="skip" style="width: 60px;" value="<?php echo $skip; ?>" size="2" /></td>
	</tr>
	<?php
}

function link_cf_display_omit_current_post($omit_current_post) {
	?>
	<tr valign="top">
		<th scope="row"><label for="omit_current_post"><?php _e('Скрыть ссылку на текущий пост?', 'post_plugin_library') ?></label></th>
		<td>
		<select name="omit_current_post" id="omit_current_post" >
		<option <?php if($omit_current_post == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($omit_current_post == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select> 
		</td>
	</tr>
	<?php
}


function link_cf_display_show_private($show_private) {
	?>
	<tr valign="top">
		<th scope="row"><label for="show_private"><?php _e('Показывать защищенные паролем?', 'post_plugin_library') ?></label></th>
		<td>
		<select name="show_private" id="show_private">
		<option <?php if($show_private == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($show_private == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select> 
		</td>
	</tr>
	<?php
}
function link_cf_display_suggestions_switch_action($suggestions_switch_action) {
	?>
	<tr valign="top">
		<th scope="row"><label for="suggestions_switch_action"><?php _e('Быстрые действия в подсказках: переход к анкору в тексте при наведении мышкой, вставка ссылки по клику на элемент', 'post_plugin_library') ?></label></th>
		<td>
		<select name="suggestions_switch_action" id="suggestions_switch_action">
		<option <?php if($suggestions_switch_action == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($suggestions_switch_action == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select> 
		</td>
	</tr>
	<?php
}

function link_cf_display_suggestions_donors($suggestions_donors_src, $suggestions_donors_join) {
	?>
	<tr valign="top">
		<th scope="row"><label for="suggestions_donors_src"><?php _e('Доноры слов/фраз для подсказок', 'post_plugin_library') ?></label></th>
		<td>
            <table class="linkateposts-inner-table">
                <?php
                $opts = array('title', 'content');
                    $turned_on = explode(',', $suggestions_donors_src);
                    echo "\n\t<tr valign=\"top\"><td><strong>Источник</strong></td><td>Включить?</td></tr>";
                    foreach ($opts as $opt) {
                            if (false === in_array($opt, $turned_on)) {
                                $ischecked = '';
                            } else {
                                $ischecked = 'checked';
                            }
                            echo "\n\t<tr valign=\"top\"><td>$opt</td><td><input type=\"checkbox\" name=\"suggestions_donors_src[]\" value=\"$opt\" $ischecked /></td></tr>";
                    }
                ?>
            </table>
		</td>
	</tr>
    <tr valign="top">
        <th scope="row"><label for="suggestions_donors_join"><?php _e('Что делать с донорами для подсказок?', 'post_plugin_library') ?></label></th>
        <td>
            <select name="suggestions_donors_join" id="suggestions_donors_join">
                <option <?php if($suggestions_donors_join == 'join') { echo 'selected="selected"'; } ?> value="join">Дополнить друг друга (берем все слова = больше подсказок)</option>
                <option <?php if($suggestions_donors_join == 'intersection') { echo 'selected="selected"'; } ?> value="intersection">Выбрать только общие слова (пересечение = меньше подсказок)</option>
            </select>
        </td>
        <td><?php link_cf_prepare_tooltip("Пример:<br>
У нас есть 3 поля, которые содержат слова:
<ol>
<li>Заголовок (Н1) - [ипотека, квартира, документы]</li>
<li>Тайтл (СЕО) - [кредит, квартира, оформить]</li>
<li>Контент (текст записи) - [кредит, ипотека, документы, квартира]</li>
</ol>
Если мы их объединим, то в подсказках будут слова:<br>
<strong>[ипотека, квартира, документы, креди, оформить]</strong>
<br><br>
При пересечении (ищем общие слова):<br>
<strong>[квартира] - только это слово встретилось во всех полях одновременно.</strong>
<br><br>
Если какое-либо из полей пустое (например у вас не задан сео тайтл), то это поле просто не учитывается."); ?></td>
    </tr>
	<?php
}
function link_cf_display_suggestions_join($suggestions_join) {
	?>
	<tr valign="top">
		<th scope="row"><label for="suggestions_join"><?php _e('Опции отображения', 'post_plugin_library') ?></label></th>
		<td>
		<select name="suggestions_join" id="suggestions_join">
		<option <?php if($suggestions_join == 'all') { echo 'selected="selected"'; } ?> value="all">Объединить все в 2 группы: "Простые анкоры" и "Фразы-анкоры"</option>
		<option <?php if($suggestions_join == 'same') { echo 'selected="selected"'; } ?> value="same">Объединять только одинаковые анкоры</option>
<!-- 		<option <?php if($suggestions_join == 'not') { echo 'selected="selected"'; } ?> value="not">Ничего не объединять</option> -->
		</select> 
		</td>
	</tr>
	<?php
}
function link_cf_display_suggestions_click($suggestions_click) {
	?>
	<tr valign="top">
		<th scope="row"><label for="suggestions_click"><?php _e('Быстрый переход к анкору при листании группы', 'post_plugin_library') ?></label></th>
		<td>
		<select name="suggestions_click" id="suggestions_click">
		<option <?php if($suggestions_click == 'select') { echo 'selected="selected"'; } ?> value="select">Поиск и выделение</option>
		<option <?php if($suggestions_click == 'insert') { echo 'selected="selected"'; } ?> value="insert">Вставка ссылки</option>
		<option <?php if($suggestions_click == 'none') { echo 'selected="selected"'; } ?> value="none">Ничего не делать</option>
		</select> 
		</td>
	</tr>
	<?php
}

function link_cf_display_show_pages($show_pages) {
	?>
	<tr valign="top">
		<th scope="row"><label for="show_pages"><?php _e('Показывать ссылки на страницы или записи?', 'post_plugin_library') ?></label></th>
		<td>
			<select name="show_pages" id="show_pages">
			<option <?php if($show_pages == 'false') { echo 'selected="selected"'; } ?> value="false">Только записи</option>
			<option <?php if($show_pages == 'true') { echo 'selected="selected"'; } ?> value="true">Записи и страницы</option>
			<option <?php if($show_pages == 'but') { echo 'selected="selected"'; } ?> value="but">Только страницы</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_show_custom_posts($show_customs) {
	$hide_types = array ('post','page','attachment','revision','nav_menu_item','custom_css','oembed_cache','user_request','customize_changeset');
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Включить в список произвольные типы записей?', 'post_plugin_library') ?></th>
		<td>
			<table class="linkateposts-inner-table">	
			<?php 
				$types = get_post_types('','names');
				if ($types) {
					$turned_on = explode(',', $show_customs);
					echo "\n\t<tr valign=\"top\"><td><strong>Тип записи</strong></td><td><strong>Показать</strong></td></tr>";
					foreach ($types as $type) {
						if (false === in_array($type, $hide_types)) {

							if (false === in_array($type, $turned_on)) {
								$ischecked = '';
							} else {
								$ischecked = 'checked';
							}
							echo "\n\t<tr valign=\"top\"><td>$type</td><td><input type=\"checkbox\" name=\"show_customs[]\" value=\"$type\" $ischecked /></td></tr>";
						}
					}
				}	
			?>
			</table>
		</td> 
	</tr>
	<?php
}



function link_cf_display_quickfilter_dblclick($quickfilter_dblclick) {
	?>
	<tr valign="top">
		<th scope="row"><label for="quickfilter_dblclick"><?php _e('При выделении слова в редакторе вставлять его в поле быстрого фильтра автоматически <span style="color: red">(classic editor)</span>', 'post_plugin_library') ?></label></th>
		<td>
			<select name="quickfilter_dblclick" id="quickfilter_dblclick">
			<option <?php if($quickfilter_dblclick == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
			<option <?php if($quickfilter_dblclick == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_singleword_suggestions($singleword_suggestions) {
	?>
    <tr valign="top">
        <th scope="row"><label for="singleword_suggestions"><?php _e('Предлагать однословные подсказки анкоров', 'post_plugin_library') ?></label></th>
        <td>
            <select name="singleword_suggestions" id="singleword_suggestions">
                <option <?php if($singleword_suggestions == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
                <option <?php if($singleword_suggestions == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
            </select>
        </td>
    </tr>
	<?php
}


function link_cf_display_show_attachments($show_attachments) {
	?>
	<tr valign="top">
		<th scope="row"><label for="show_attachments"><?php _e('Show attachments?', 'post_plugin_library') ?></label></th>
		<td>
			<select name="show_attachments" id="show_attachments">
			<option <?php if($show_attachments == 'false') { echo 'selected="selected"'; } ?> value="false">No</option>
			<option <?php if($show_attachments == 'true') { echo 'selected="selected"'; } ?> value="true">Yes</option>
			</select>
		</td>
	</tr>
	<?php
}

function link_cf_display_match_author($match_author) {
	?>
	<tr valign="top">
		<th scope="row"><label for="match_author"><?php _e('Только ссылки на посты от того же автора?', 'post_plugin_library') ?></label></th>
		<td>
			<select name="match_author" id="match_author">
			<option <?php if($match_author == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
			<option <?php if($match_author == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_match_cat($match_cat) {
	?>
	<tr valign="top">
		<th scope="row"><label for="match_cat"><?php _e('Только ссылки из той же категории?', 'post_plugin_library') ?></label></th>
		<td>
			<select name="match_cat" id="match_cat">
			<option <?php if($match_cat == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
			<option <?php if($match_cat == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_match_tags($match_tags) {
	global $wp_version;
	?>
	<tr valign="top">
		<th scope="row"><label for="match_tags"><?php _e('Ссылки с совпадающими метками (поле для ввода ниже)', 'post_plugin_library') ?></label></th>
		<td>
			<select name="match_tags" id="match_tags" <?php if ($wp_version < 2.3) echo 'disabled="true"'; ?> >
			<option <?php if($match_tags == 'false') { echo 'selected="selected"'; } ?> value="false">Все равно</option>
			<option <?php if($match_tags == 'any') { echo 'selected="selected"'; } ?> value="any">Один из перечесленных</option>
			<option <?php if($match_tags == 'all') { echo 'selected="selected"'; } ?> value="all">Все обязательно</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_none_text($none_text) {
	?>
	<tr valign="top">
		<th scope="row"><label for="none_text"><?php _e('Текст, если ничего не найдено:', 'post_plugin_library') ?></label></th>
		<td><input name="none_text" type="text" id="none_text" value="<?php echo htmlspecialchars(stripslashes($none_text)); ?>" size="40" /></td>
	</tr>
	<?php
}


function link_cf_display_anons_len($len) {
	?>
	<tr valign="top">
		<th scope="row"><label for="anons_len"><?php _e('Длина анонса в символах (тег {anons}):', 'post_plugin_library') ?></label></th>
		<td><input name="anons_len" type="number" min="0" id="anons_len" value="<?php echo htmlspecialchars(stripslashes($len)); ?>"  /></td>
        <td><?php link_cf_prepare_tooltip("Тег анонса {anons} выводит вступительный текст к статье. Используется в шаблонах вставки ниже."); ?></td>
	</tr>
	<?php
}

function link_cf_display_no_text($no_text) {
	?>
	<tr valign="top">
		<th scope="row"><label for="no_text"><?php _e('Скрывать вывод, если нет ссылок?', 'post_plugin_library') ?></label></th>
		<td>
			<select name="no_text" id="no_text">
			<option <?php if($no_text == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
			<option <?php if($no_text == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
			</select>
		</td> 
	</tr>
	<?php
}

function link_cf_display_prefix($prefix) {
	?>
	<tr valign="top">
		<th scope="row"><label for="prefix"><?php _e('Префикс (код перед ссылками):', 'post_plugin_library') ?></label></th>
		<td><input name="prefix" type="text" id="prefix" value="<?php echo htmlspecialchars(stripslashes($prefix)); ?>" size="40" /></td>
	</tr>
	<?php
}

function link_cf_display_suffix($suffix) {
	?>
	<tr valign="top">
		<th scope="row"><label for="suffix"><?php _e('Суффикс (код после ссылок):', 'post_plugin_library') ?></label></th>
		<td><input name="suffix" type="text" id="suffix" value="<?php echo htmlspecialchars(stripslashes($suffix)); ?>" size="40" /></td>
	</tr>
	<?php
}


function link_cf_display_output_template($output_template) {
	?>
	<tr valign="top">
		<th scope="row"><label for="output_template"><?php _e('Содержание ссылки в списке:', 'post_plugin_library') ?></label></th>
		<td><input type="text" name="output_template" id="output_template" value="<?php echo htmlspecialchars(stripslashes($output_template)); ?>" size="40"/></td>
        <td><?php link_cf_prepare_tooltip(link_cf_get_available_tags(false)); ?></td>
	</tr>
	<?php
}

function link_cf_display_replace_template($link_before, $link_after, $link_temp_alt) {
	?>
	<tr valign="top">
		<th scope="row"><label for="link_before"><?php _e('Вывод ссылки перед выделенным текстом:', 'post_plugin_library') ?></label></th>
		<td><textarea name="link_before" id="link_before" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($link_before)))); ?></textarea></td>
        <td><?php link_cf_prepare_tooltip(link_cf_get_available_tags(false)); ?></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="link_after"><?php _e('Вывод после выделенного текста:', 'post_plugin_library') ?></label></th>
		<td><textarea name="link_after" id="link_after" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($link_after)))); ?></textarea></td>
	</tr>

	<tr valign="top">
		<th scope="row"><label for="link_temp_alt"><?php _e('Альтернативный шаблон:', 'post_plugin_library') ?></label></th>
		<td><textarea name="link_temp_alt" id="link_temp_alt" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($link_temp_alt)))); ?></textarea></td>
        <td><?php link_cf_prepare_tooltip("Альтернативный шаблон будет использован, если нажата комбинация CTRL/CMD+Click. Код в данном поле дан для примера, меняйте его по своему усмотрению."); ?></td>
	</tr>
	<?php
}

function link_cf_display_replace_term_template($term_before, $term_after, $term_temp_alt) {
	?>
	<tr valign="top">
		<th scope="row"><label for="term_before"><?php _e('Вывод ссылки перед выделенным текстом:', 'post_plugin_library') ?></label></th>
		<td><textarea name="term_before" id="term_before" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($term_before)))); ?></textarea></td>
        <td><?php link_cf_prepare_tooltip(link_cf_get_available_tags(true)); ?></td>
	</tr>
		<tr valign="top">
		<th scope="row"><label for="term_after"><?php _e('Вывод после выделенного текста:', 'post_plugin_library') ?></label></th>
		<td><textarea name="term_after" id="term_after" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($term_after)))); ?></textarea></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="term_temp_alt"><?php _e('Альтернативный шаблон:', 'post_plugin_library') ?></label></th>
		<td><textarea name="term_temp_alt" id="term_temp_alt" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes(urldecode(base64_decode($term_temp_alt)))); ?></textarea></td>
        <td><?php link_cf_prepare_tooltip("Альтернативный шаблон будет использован, если нажата комбинация CTRL/CMD+Click. Код в данном поле дан для примера, меняйте его по своему усмотрению."); ?></td>
	</tr>
	<?php
}

function link_cf_display_divider($divider) {
	?>
	<tr valign="top">
		<th scope="row"><label for="divider"><?php _e('Разделитель между ссылками:', 'post_plugin_library') ?></label></th>
		<td><input name="divider" type="text" id="divider" value="<?php echo $divider; ?>" size="40" /></td>
	</tr>
	<?php
}

function link_cf_display_tag_str($tag_str) {
	global $wp_version;
	?>
	<tr valign="top">
		<th scope="row"><label for="tag_str"><?php _e('Совпадающие метки:<br />(a,b _через запятую_, чтобы совпала любая из перечисленных, a+b _через плюс_, чтобы совпали все метки)', 'post_plugin_library') ?></label></th>
		<td><input name="tag_str" type="text" id="tag_str" value="<?php echo $tag_str; ?>" <?php if ($wp_version < 2.3) echo 'disabled="true"'; ?> size="40" /></td>
	</tr>
	<?php
}

function link_cf_display_excluded_posts($excluded_posts) {
	?>
	<tr valign="top">
		<th scope="row"><label for="excluded_posts"><?php _e('Исключить записи с ID (через запятую):', 'post_plugin_library') ?></label></th>
		<td><input name="excluded_posts" type="text" id="excluded_posts" value="<?php echo $excluded_posts; ?>" size="40" /> <?php _e('', 'post_plugin_library'); ?></td>
	</tr>
	<?php
}

function link_cf_display_included_posts($included_posts) {
	?>
	<tr valign="top">
		<th scope="row"><label for="included_posts"><?php _e('Только записи из списка ID (через запятую):', 'post_plugin_library') ?></label></th>
		<td><input name="included_posts" type="text" id="included_posts" value="<?php echo $included_posts; ?>" size="40" /> <?php _e('', 'post_plugin_library'); ?></td>
	</tr>
	<?php
}
function link_cf_display_scheme_info($exists, $time) {
	if ($exists) {
		?>
			<p>Последнее обновление схемы: <strong style="color:green"><?php echo date('j-m-Y H:i:s', $time); ?></strong></p>
		<?php
	} else {
		?>
			<p>Чтобы сделать экспорт и видеть статистику входящих ссылок на панели перелинковки, необходимо создать индекс (кнопка ниже). Это может занять некоторое время, в зависимости от количества публикаций. </p>
		<?php
	}

}
function link_cf_display_scheme_export_options() {
	$hide_types = array ('attachment','revision','nav_menu_item','custom_css','oembed_cache','user_request','customize_changeset', 'sticky_ad', 'post_format', 'nav_menu', 'link_category','tablepress_table');
	?>
	<h2>Опции экспорта</h2>
	<div style="display: flex">
		<div>
		<p><strong>Типы записей и таксономий</strong></p>
				<table class="linkateposts-inner-table">	
				<?php 
					$types = get_post_types('','names');
					if ($types) {
						echo "\n\t<tr valign=\"top\"><td colspan=\"2\"><strong>Типы публикаций</strong></td></tr>";
						foreach ($types as $type) {
							if (false === in_array($type, $hide_types)) {
								echo "\n\t<tr valign=\"top\"><td>$type</td><td><input type=\"checkbox\" name=\"export_types[]\" value=\"$type\" checked /></td></tr>";
							}
						}
					}
					$taxonomies = get_taxonomies(array(),'names');
					if ($taxonomies) {
						echo "\n\t<tr valign=\"top\"><td colspan=\"2\"><strong>Таксономии</strong></td></tr>";
						foreach ($taxonomies as $tax) {
							if (false === in_array($tax, $hide_types)) {
								echo "\n\t<tr valign=\"top\"><td>$tax</td><td><input type=\"checkbox\" name=\"export_types[]\" value=\"$tax\" checked /></td></tr>";
							}
						}
					}
						
				?>
				</table>
		</div>
		<div style="margin-left: 50px">
		<p><strong>Поля данных</strong></p>
			Ориентация ссылок
			<select name="links_direction" id="links_direction"> 
				<option value="outgoing" selected>Исходящие</option>
				<option value="incoming">Входящие</option>
			</select>
			<div id="links_direction_outgoing">
				<input name="source_id" type="checkbox" value="cb_source_id" checked>ID источника</input><br>
				<input name="source_type" type="checkbox" value="cb_source_type" checked>Тип источника</input><br>
				<input name="source_cats" type="checkbox" value="cb_source_cats" checked>Рубрики</input><br>
				<input name="source_url" type="checkbox" value="cb_source_url" checked>URL источника</input><br>
				<input name="target_url" type="checkbox" value="cb_target_url" checked>URL цели</input><br>
			</div>
			<div id="links_direction_incoming" style="display:none">
				<input name="target_id" type="checkbox"   value="cb_target_id" checked>ID цели</input><br>
				<input name="target_type" type="checkbox" value="cb_target_type" checked>Тип цели</input><br>
				<input name="target_cats" type="checkbox" value="cb_target_cats" checked>Рубрики цели</input><br>
				<input name="target_url" type="checkbox"  value="cb_target_url" checked>URL цели</input><br>
				<input name="source_url" type="checkbox"  value="cb_source_url" checked>URL источника</input><br>
			</div>
			<input name="ankor" type="checkbox" value="cb_ankor" checked>Анкор</input><br>
			<input name="count_out" type="checkbox" value="cb_count_out" checked>Кол-во исходящих ссылок</input><br>
			<input name="count_in" type="checkbox" value="cb_count_in" checked>Кол-во входящих ссылок</input><br>
			<input name="duplicate_fields" type="checkbox" value="cb_duplicate_fields" checked>Дублировать поля (id, тип, ...)</input><br>

		</div>
	</div>
	<p>Если возникнут затруднения с экспортом/импортом в эксель - посмотрите <a href="https://seocherry.ru/dev/statistika-vnutrennej-perelinkovki-v-cherrylink-jeksport-iz-plagina-i-import-v-excel/">этот пост</a>.</p>
	<?php
}
function link_cf_display_sidebar() {
	?>
	<div class="linkateposts-admin-sidebar">
				<h2>CherryLink <?php echo LinkatePosts::get_linkate_version();?></h2>
				<img src="<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/cherry_side_top.png"/>
                <p>В обновлении 2.0 добавлена поддержка редактора Gutenberg!</p>
                <p>Подробности о новой версии на <a href="https://seocherry.ru/dev/cherrylink-2-0-perelinkovka-gutenberg/">официальном сайте</a>.</p>
				<h2>Мануал</h2>
				<a href="https://seocherry.ru/dev/cherrylink-manual/"><img src="<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/side_2.png"/></a>
				<p>На многие вопросы по использованию плагина может ответить <a href="https://seocherry.ru/dev/cherrylink-manual/">руководство пользователя</a> на моем сайте.</p>
				<h2>Где взять ключ?</h2>
				<a href="http://seocherry.ru/dev/cherrylink" ><img src="<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/side_3.png"/></a>
                <p>Вся информация о плагине и его покупке находится на официальном сайте по адресу: <a href="http://seocherry.ru/dev/cherrylink" >SeoCherry.ru</a>.</p>
                <p>Справка по поводу <a href="https://seocherry.ru/perenos-licenzii-cherrylink-i-vozvrat-deneg/" target="_blank">переноса лицензии или возврата денежных средств</a>.</p>
				<h2>Техподдержка</h2>
				<img src="<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/side_4.png"/>
				<p>Если есть вопросы о работе плагина, покупке или баг репорт (найденные ошибки) - пишите в <a href="https://t.me/joinchat/HCjIHgtC9ePAkJOP1V_cPg">телеграм-чат</a> или на почту <strong>mail@seocherry.ru</strong>. </p>
				<p>Другие плагины разработчика можно найти <a href="https://seocherry.ru/buy-plugin/">на этой страничке</a>.</p>

	</div>
	<?php
}

function link_cf_display_authors($excluded_authors, $included_authors) {
	global $wpdb;
	?>
    <tr valign="top">
        <th scope="row"><?php _e('Пояснение к фильтрам авторов и рубрик:', 'post_plugin_library') ?></th>
        <td>Не нужно ставить галочки во всех полях _Показать_, чтобы вывести всех авторов и рубики. Если ничего не выбрано, они будут выведены по умолчанию.<br><br>Если хотите вывести только выбранных авторов/категории, ставьте галочки напротив них - остальное показано не будет.<br><br>Если хотите скрыть какую-либо категорию, ставьте галочку в столбце _Скрыть_ напротив соотв. категории, при этом не нужно у остальных проставлять галочки в _Показать_.</td>
    </tr>
	<tr valign="top">
		<th scope="row"><?php _e('Записи каких авторов выводить:', 'post_plugin_library') ?></th>
		<td>
			<table class="linkateposts-inner-table">	
			<?php 
				$users = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY user_login");
				if ($users) {
					$excluded = explode(',', $excluded_authors);
					$included = explode(',', $included_authors);
					echo "\n\t<tr valign=\"top\"><td><strong>Имя юзера</strong></td><td><strong>Скрыть</strong></td><td><strong>Показать</strong></td></tr>";
					foreach ($users as $user) {
						if (false === in_array($user->ID, $excluded)) {
							$ex_ischecked = '';
						} else {
							$ex_ischecked = 'checked';
						}
						if (false === in_array($user->ID, $included)) {
							$in_ischecked = '';
						} else {
							$in_ischecked = 'checked';
						}
						echo "\n\t<tr valign=\"top\"><td>$user->user_login</td><td><input type=\"checkbox\" name=\"excluded_authors[]\" value=\"$user->ID\" $ex_ischecked /></td><td><input type=\"checkbox\" name=\"included_authors[]\" value=\"$user->ID\" $in_ischecked /></td></tr>";
					}
				}	
			?>
			</table>
		</td>
	</tr>
	<?php
}

function link_cf_display_cats($excluded_cats, $included_cats) {
	global $wpdb;
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Рубирки скрыть/показать:', 'post_plugin_library') ?></th>
		<td>
			<table class="linkateposts-inner-table">	
			<?php 
				if (function_exists("get_categories")) {
					$categories = get_categories();//('&hide_empty=1');
				} else {
					//$categories = $wpdb->get_results("SELECT * FROM $wpdb->categories WHERE category_count <> 0 ORDER BY cat_name");
					$categories = $wpdb->get_results("SELECT * FROM $wpdb->categories ORDER BY cat_name");
				}
				if ($categories) {
					echo "\n\t<tr valign=\"top\"><td><strong>Рубрика</strong></td><td><strong>Скрыть</strong></td><td><strong>Показать</strong></td></tr>";
					$excluded = explode(',', $excluded_cats);
					$included = explode(',', $included_cats);
					$level = 0;
					$cats_added = array();
					$last_parent = 0;
					$cat_parent = 0;
					foreach ($categories as $category) {
						$category->cat_name = esc_html($category->cat_name);
						if (false === in_array($category->cat_ID, $excluded)) {
							$ex_ischecked = '';
						} else {
							$ex_ischecked = 'checked';
						}
						if (false === in_array($category->cat_ID, $included)) {
							$in_ischecked = '';
						} else {
							$in_ischecked = 'checked';
						}
						$last_parent = $cat_parent;
						$cat_parent = $category->category_parent;
						if ($cat_parent == 0) {
							$level = 0;
						} elseif ($last_parent != $cat_parent) {
							if (in_array($cat_parent, $cats_added)) {
								$level = $level - 1;
							} else {
								$level = $level + 1;
							}
							$cats_added[] = $cat_parent;
						}
						if ($level < 0) {
							$level = 0;
						}
						$pad = str_repeat('&nbsp;', 3*$level);
						echo "\n\t<tr valign=\"top\"><td>$pad$category->cat_name</td><td><input type=\"checkbox\" name=\"excluded_cats[]\" value=\"$category->cat_ID\" $ex_ischecked /></td><td><input type=\"checkbox\" name=\"included_cats[]\" value=\"$category->cat_ID\" $in_ischecked /></td></tr>";
					}
				}
			?>
			</table>
		</td> 
	</tr>
	<?php
}


function link_cf_display_age($age) {
	?>
	<tr valign="top">
		<th scope="row"><label for="age-direction"><?php _e('Скрыть записи по возрасту:', 'post_plugin_library') ?></label></th>
		<td>
			
				<select name="age-direction" id="age-direction">
				<option <?php if($age['direction'] == 'before') { echo 'selected="selected"'; } ?> value="before">младше</option>
				<option <?php if($age['direction'] == 'after') { echo 'selected="selected"'; } ?> value="after">старше</option>
				<option <?php if($age['direction'] == 'none') { echo 'selected="selected"'; } ?> value="none">-----</option>
				</select>
				<input style="vertical-align: middle; width: 60px;" name="age-length" type="number" id="age-length" value="<?php echo $age['length']; ?>" size="4" />
                
				<select name="age-duration" id="age-duration">
				<option <?php if($age['duration'] == 'day') { echo 'selected="selected"'; } ?> value="day">дней</option>
				<option <?php if($age['duration'] == 'month') { echo 'selected="selected"'; } ?> value="month">месяцев</option>
				<option <?php if($age['duration'] == 'year') { echo 'selected="selected"'; } ?> value="year">лет</option>
				</select>
				

		</td>
	</tr>
	<?php
}

function link_cf_display_status($status) {
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Статус записей:', 'post_plugin_library') ?></th>
		<td>

				<label for="status-publish">Опубликованы</label>
				<select name="status-publish" id="status-publish" <?php if (!function_exists('get_post_type')) echo 'disabled="true"'; ?>>
				<option <?php if($status['publish'] == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
				<option <?php if($status['publish'] == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
				</select>

				<label for="status-private">Личные</label>
				<select name="status-private" id="status-private" <?php if (!function_exists('get_post_type')) echo 'disabled="true"'; ?>>
				<option <?php if($status['private'] == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
				<option <?php if($status['private'] == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
				</select>

				<label for="status-draft">Черновик</label>
				<select name="status-draft" id="status-draft" <?php if (!function_exists('get_post_type')) echo 'disabled="true"'; ?>>
				<option <?php if($status['draft'] == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
				<option <?php if($status['draft'] == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
				</select>

				<label for="status-future">Запланированные</label>
				<select name="status-future" id="status-future" <?php if (!function_exists('get_post_type')) echo 'disabled="true"'; ?>>
				<option <?php if($status['future'] == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
				<option <?php if($status['future'] == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
				</select>

		</td>
	</tr>
	<?php
}

function link_cf_display_custom($custom) {
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Совпадающие по кастомному полю:', 'post_plugin_library') ?></th>
		<td>
			<table>
			<tr><td style="border-bottom-width: 0">Имя поля</td><td style="border-bottom-width: 0"></td><td style="border-bottom-width: 0">Значение</td></tr>
			<tr>
			<td style="border-bottom-width: 0"><input name="custom-key" type="text" id="custom-key" value="<?php echo $custom['key']; ?>" size="20" /></td>
			<td style="border-bottom-width: 0">
				<select name="custom-op" id="custom-op">
				<option <?php if($custom['op'] == '=') { echo 'selected="selected"'; } ?> value="=">=</option>
				<option <?php if($custom['op'] == '!=') { echo 'selected="selected"'; } ?> value="!=">!=</option>
				<option <?php if($custom['op'] == '>') { echo 'selected="selected"'; } ?> value=">">></option>
				<option <?php if($custom['op'] == '>=') { echo 'selected="selected"'; } ?> value=">=">>=</option>
				<option <?php if($custom['op'] == '<') { echo 'selected="selected"'; } ?> value="<"><</option>
				<option <?php if($custom['op'] == '<=') { echo 'selected="selected"'; } ?> value="<="><=</option>
				<option <?php if($custom['op'] == 'LIKE') { echo 'selected="selected"'; } ?> value="LIKE">LIKE</option>
				<option <?php if($custom['op'] == 'NOT LIKE') { echo 'selected="selected"'; } ?> value="NOT LIKE">NOT LIKE</option>
				<option <?php if($custom['op'] == 'REGEXP') { echo 'selected="selected"'; } ?> value="REGEXP">REGEXP</option>
				<option <?php if($custom['op'] == 'EXISTS') { echo 'selected="selected"'; } ?> value="EXISTS">EXISTS</option>			
				</select>
			</td>
			<td style="border-bottom-width: 0"><input name="custom-value" type="text" id="custom-value" value="<?php echo $custom['value']; ?>" size="20" /></td>
			</tr>
			</table>
		</td>
	</tr>
	<?php
}

function link_cf_display_append($options) {
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Вывод после записи:', 'post_plugin_library') ?></th>
		<td>
			<table>
			<tr><td style="border-bottom-width: 0"><label for="append_on">Activate</label></td><td style="border-bottom-width: 0"><label for="append_priority">Priority</label></td><td style="border-bottom-width: 0"><label for="append_parameters">Parameters</label></td><td style="border-bottom-width: 0"><label for="append_condition">Condition</label></td></tr>
			<tr>
			<td style="border-bottom-width: 0">			
				<select name="append_on" id="append_on">
				<option <?php if($options['append_on'] == 'false') { echo 'selected="selected"'; } ?> value="false">No</option>
				<option <?php if($options['append_on'] == 'true') { echo 'selected="selected"'; } ?> value="true">Yes</option>
				</select>
			</td>
			<td style="border-bottom-width: 0"><input name="append_priority" type="number" id="append_priority" style="width: 60px;" value="<?php echo $options['append_priority']; ?>" size="3" /></td>
			<td style="border-bottom-width: 0"><textarea name="append_parameters" id="append_parameters" rows="4" cols="38"><?php echo htmlspecialchars(stripslashes($options['append_parameters'])); ?></textarea></td>
			<td style="border-bottom-width: 0"><textarea name="append_condition" id="append_condition" rows="4" cols="20"><?php echo htmlspecialchars(stripslashes($options['append_condition'])); ?></textarea></td>
			</tr></table>
		</td> 
	</tr>
	<?php
}

function link_cf_display_content_filter($content_filter) {
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Output in content:<br />(<em>via</em> special tags)', 'post_plugin_library') ?></th>
		<td>
			<table>
			<tr><td style="border-bottom-width: 0"><label for="content_filter">Activate</label></td></tr>
			<tr>
			<td style="border-bottom-width: 0">			
			<select name="content_filter" id="content_filter">
			<option <?php if($content_filter == 'false') { echo 'selected="selected"'; } ?> value="false">No</option>
			<option <?php if($content_filter == 'true') { echo 'selected="selected"'; } ?> value="true">Yes</option>
			</select>
			</td>
			</tr>
			</table>
		</td> 
	</tr>
	<?php
}

function link_cf_display_sort($sort) {
	global $wpdb;
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Сортировать по:<br />можно оставить пустым для сортировки по умолчанию', 'post_plugin_library') ?></th>
		<td>
			<table>
			<tr><td style="border-bottom-width: 0"></td><td style="border-bottom-width: 0">Тег <?php link_cf_prepare_tooltip(link_cf_get_available_tags(false)); ?></td><td style="border-bottom-width: 0">Порядок</td><td style="border-bottom-width: 0">Заглавные буквы</td></tr>
			<tr>
			<td style="border-bottom-width: 0">Условие №1</td>
			<td style="border-bottom-width: 0"><input name="sort-by1" type="text" id="sort-by1" value="<?php echo $sort['by1']; ?>" size="20" /></td>
			<td style="border-bottom-width: 0">
				<select name="sort-order1" id="sort-order1">
				<option <?php if($sort['order1'] == SORT_ASC) { echo 'selected="selected"'; } ?> value="SORT_ASC">По возрастанию</option>
				<option <?php if($sort['order1'] == SORT_DESC) { echo 'selected="selected"'; } ?> value="SORT_DESC">По убыванию</option>
				</select>
			</td> 
			<td style="border-bottom-width: 0">
				<select name="sort-case1" id="sort-case1">
				<option <?php if($sort['case1'] == 'false') { echo 'selected="selected"'; } ?> value="false">чувствительный</option>
				<option <?php if($sort['case1'] == 'true') { echo 'selected="selected"'; } ?> value="true">без разницы</option>
				</select>
			</td> 
			</tr>
			<tr>
			<td style="border-bottom-width: 0">Условие №2</td>
			<td style="border-bottom-width: 0"><input name="sort-by2" type="text" id="sort-by2" value="<?php echo $sort['by2']; ?>" size="20" /></td>
			<td style="border-bottom-width: 0">
				<select name="sort-order2" id="sort-order2">
				<option <?php if($sort['order2'] == SORT_ASC) { echo 'selected="selected"'; } ?> value="SORT_ASC">По возрастанию</option>
				<option <?php if($sort['order2'] == SORT_DESC) { echo 'selected="selected"'; } ?> value="SORT_DESC">По убыванию</option>
				</select>
			</td> 
			<td style="border-bottom-width: 0">
				<select name="sort-case2" id="sort-case2">
				<option <?php if($sort['case2'] == 'false') { echo 'selected="selected"'; } ?> value="false">чувствительный</option>
				<option <?php if($sort['case2'] == 'true') { echo 'selected="selected"'; } ?> value="true">без разницы</option>
				</select>
				<br>
				
			</td> 
			</tr>
			</table>
		</td>
	</tr>
	<?php
}

function link_cf_display_orderby($options) {
	global $wpdb;
	$limit = 30;
	$keys = $wpdb->get_col( "
		SELECT meta_key
		FROM $wpdb->postmeta
		WHERE meta_key NOT LIKE '\_%'
		GROUP BY meta_key
		ORDER BY meta_id DESC
		LIMIT $limit" );
	$metaselect = "<select id='orderby' name='orderby'>\n\t<option value=''></option>";
	if ( $keys ) {
		natcasesort($keys);
		foreach ( $keys as $key ) {
			$key = esc_attr( $key );
			if ($options['orderby'] == $key) {
				$metaselect .= "\n\t<option selected='selected' value='$key'>$key</option>";
			} else {
				$metaselect .= "\n\t<option value='$key'>$key</option>";
			}
		}
		$metaselect .= "</select>";
	}

	?>
	<tr valign="top">
		<th scope="row"><?php _e('Select output by custom field:', 'post_plugin_library') ?></th>
		<td>
			<table>
			<tr><td style="border-bottom-width: 0">Field</td><td style="border-bottom-width: 0">Order</td><td style="border-bottom-width: 0">Case</td></tr>
			<tr>
			<td style="border-bottom-width: 0">
			<?php echo $metaselect;	?>	
			</td>
			<td style="border-bottom-width: 0">
				<select name="orderby_order" id="orderby_order">
				<option <?php if($options['orderby_order'] == 'ASC') { echo 'selected="selected"'; } ?> value="ASC">ascending</option>
				<option <?php if($options['orderby_order'] == 'DESC') { echo 'selected="selected"'; } ?> value="DESC">descending</option>
				</select>
			</td> 
			<td style="border-bottom-width: 0">
				<select name="orderby_case" id="orderby_case">
				<option <?php if($options['orderby_case'] == 'false') { echo 'selected="selected"'; } ?> value="false">case-sensitive</option>
				<option <?php if($options['orderby_case'] == 'true') { echo 'selected="selected"'; } ?> value="true">case-insensitive</option>
				<option <?php if($options['orderby_case'] == 'num') { echo 'selected="selected"'; } ?> value="num">numeric</option>
				</select>
			</td> 
			</tr>
			</table>
		</td>
	</tr>
	<?php
}

// now for linkate_posts

function link_cf_display_num_term_length_limit($term_length_limit) {
	?>
	<tr valign="top">
		<th scope="row"><label for="term_length_limit"><?php _e('Не учитывать слова короче (кол-во букв, включительно):', 'post_plugin_library') ?></label></th>
		<td><input name="term_length_limit" type="number" id="term_length_limit" style="width: 60px;" value="<?php echo $term_length_limit; ?>" size="3"  min="0"/></td>
	</tr>
	<?php
}


function link_cf_display_num_terms($num_terms) {
	?>
	<tr valign="top">
		<th scope="row"><label for="num_terms"><?php _e('Количество ключевых слов для определения схожести:', 'post_plugin_library') ?></label></th>
		<td><input name="num_terms" type="number" id="num_terms" style="width: 60px;" value="<?php echo $num_terms; ?>" size="3" /></td>
        <td><?php link_cf_prepare_tooltip("Количество самых часто встречающихся в тексте слов, которые использует алгоритм для сравнения с другими статьями. <br><br>Если алгоритм не нашел статью, которую вы считаете релевантной, то можете попробовать увеличить это число (больше 100-200 не рекомендую).<br><br>Подробнее читайте в мануале (ссылка справа)."); ?></td>
	</tr>
	<?php
}

function link_cf_display_term_extraction($term_extraction) {
	?>
	<tr valign="top">
		<th scope="row" title=""><label for="term_extraction"><?php _e('Алгоритм поиска ключевых слов:', 'post_plugin_library') ?></label></th>
		<td>
			<select name="term_extraction" id="term_extraction">
			<option <?php if($term_extraction == 'frequency') { echo 'selected="selected"'; } ?> value="frequency">Частота использования</option>
			<option <?php if($term_extraction == 'pagerank') { echo 'selected="selected"'; } ?> value="pagerank">Алгоритм TextRank</option>
			</select>
		</td> 

	</tr>
		<tr valign="top"><td colspan="2">* Стоп-слова учитываются только при включенном алгоритме по частотности использования слов.</td></tr>
	<?php
}

function link_cf_display_weights($options) {
	?>
	<tr valign="top">
		<th scope="row"><?php _e('Значимость полей:', 'post_plugin_library') ?></th>
		<td>
			<label for="weight_content">содержание записи:  </label><input name="weight_content" type="number" style="width: 60px;" id="weight_content" value="<?php echo round(100 * $options['weight_content']); ?>" size="3" /> %
            <br>
			<label for="weight_title">заголовок статьи:  </label><input name="weight_title" type="number" style="width: 60px;" id="weight_title" value="<?php echo round(100 * $options['weight_title']); ?>" size="3" /> %
            <br>
			<label for="weight_tags">метки (теги):  </label><input name="weight_tags" type="number" style="width: 60px;" id="weight_tags" value="<?php echo round(100 * $options['weight_tags']); ?>" size="3" /> %
		</td>
        <td><?php link_cf_prepare_tooltip("Укажите в процентах, какое поле для вас важнее при поиске схожих записей. Сумма не может превышать 100%.<br><br>Заголовок записи может быть из тега H1 или Title, в зависимости от настроек на странице Индекс ссылок. "); ?></td>
		
	</tr>
	<?php
}

function link_cf_display_which_title($which_title) {
	?>
	<tr valign="top">
	<th scope="row"><label for="compare_seotitle"><?php _e('Использовать SEO Title вместо H1:', 'post_plugin_library') ?></label></th>
		<td><input name="compare_seotitle" type="checkbox" id="compare_seotitle" value="cb_compare_seotitle" <?php echo $which_title; ?>/></td>
        <td><? link_cf_prepare_tooltip("Использовать SEO тайтл (берется из Yoast или AIOSEO, если найден) при поиске похожих статей вместо заголовка H1"); ?></td>
	</tr>
	<?php
}
function link_cf_display_stopwords() {
	?>
    <tr valign="top">
        <th scope="row"><label for="is_white"><?php _e('Добавить в белый список?', 'post_plugin_library') ?></label></th>
        <td><input name="is_white" type="checkbox" id="is_white" value="is_white"/></td>
        <td><? link_cf_prepare_tooltip("Поставьте эту галочку, чтобы добавить слова в белый список. Они не будут удалены из текста при реиндексации. Полезно, если вы не хотите учитывать короткие слова, но нужно сохранить какие-либо сокращения в тексте (ИП, НИИ, ОАО и пр)."); ?></td>
    </tr>
	<tr valign="top">
		<th scope="row"><label for="custom_stopwords"><?php _e('Произвольный список стоп-слов:', 'post_plugin_library') ?></label></th>
		<td><textarea name="custom_stopwords" id="custom_stopwords" rows="6" cols="38" placeholder="слово1&#10;слово2"></textarea></td>
	</tr>

	<?php
}

function link_cf_display_match_against_title($match_all_against_title) {
	?>
	<tr valign="top">
		<th scope="row"><label for="match_all_against_title"><?php _e('Одностороннее сравнение с тайтлом?', 'post_plugin_library') ?></label></th>
		<td>
		<select name="match_all_against_title" id="match_all_against_title" >
		<option <?php if($match_all_against_title == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($match_all_against_title == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select>
        <td><?php link_cf_prepare_tooltip("Берем слова из _текста_ И _тайтла_, который редактируем и сравниваем <strong>только</strong> с _тайтлом_ других статей)"); ?></td>
		</td>
	</tr>
	<?php
}
function link_cf_display_ignore_relevance($ignore_relevance) {
	?>
	<tr valign="top">
		<th scope="row"><label for="ignore_relevance"><?php _e('Игнорировать релевантность статей (вывести все подряд)', 'post_plugin_library') ?></label></th>
		<td>
		<select name="ignore_relevance" id="ignore_relevance" >
		<option <?php if($ignore_relevance == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($ignore_relevance == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select>
		</td>
        <td><?php link_cf_prepare_tooltip("Если включено (опция \"Да\"), то настройки ниже не имеют силы, а в панели перелинковки будут выведены все публикации"); ?></td>
	</tr>
	<?php
}
function link_cf_display_clean_suggestions_stoplist($clean_suggestions_stoplist) {
	?>
	<tr valign="top">
		<th scope="row"><label for="clean_suggestions_stoplist"><?php _e('Применить стоп-слова к подсказкам анкоров?', 'post_plugin_library') ?></label></th>
		<td>
		<select name="clean_suggestions_stoplist" id="clean_suggestions_stoplist" >
		<option <?php if($clean_suggestions_stoplist == 'false') { echo 'selected="selected"'; } ?> value="false">Нет</option>
		<option <?php if($clean_suggestions_stoplist == 'true') { echo 'selected="selected"'; } ?> value="true">Да</option>
		</select>
        <td><?php link_cf_prepare_tooltip("В положении \"ДА\" может значительно уменьшить количество неплохих анкоров для перелинковки"); ?></td>
		</td>
	</tr>
	<?php
}

function link_cf_get_plugin_data($plugin_file) {
	if(!function_exists( 'get_plugin_data' ) ) require_once( ABSPATH . 'wp-admin/includes/plugin.php');
	static $plugin_data;
	if(!$plugin_data) {
		$plugin_data = get_plugin_data($plugin_file);
		if (!isset($plugin_data['Title'])) {
			if ('' != $plugin_data['PluginURI'] && '' != $plugin_data['Name']) {
				$plugin_data['Title'] = '<a href="' . $plugin_data['PluginURI'] . '" title="'. __('Посетите страницу плагина', 'post-plugin-library') . '">' . $plugin_data['Name'] . '</a>';
			} else {
				$plugin_data['Title'] = $name;
			}
		}
	}
	return $plugin_data;
}

function link_cf_prepare_tooltip($text) {
    ?>
    <div class='cherry-adm-tooltip'><img src='<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/information-button.png'>
         <div class='tooltiptext'><?php echo $text; ?></div>
    </div>
    <?php
}
