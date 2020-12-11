<?php
/*
 * Linkate Posts
 */

// ========================================================================================= //
// ============================== CherryLink Setup Settings Pages  ============================== //
// ========================================================================================= //

function linkate_posts_option_menu() {
	add_options_page(__('CherryLink Options', 'linkate_posts'), __('CherryLink', 'linkate_posts'), 'cherrylink_settings', 'linkate-posts', 'linkate_posts_options_page');
}

add_action('admin_menu', 'linkate_posts_option_menu', 1);

function linkate_posts_options_page(){
	echo '<div class="wrap"><h2>';
	_e('CherryLink - Настройки', 'linkate_posts');
	echo '</h2></div><hr>';

	linkate_posts_license_field();

	$m = new lp_admin_subpages();
	$m->add_subpage('Индекс ссылок', 'other', 'linkate_posts_index_options_subpage');
	$m->add_subpage('Шаблон ссылок', 'output', 'linkate_posts_output_options_subpage');
	$m->add_subpage('Фильтрация', 'general', 'linkate_posts_filter_options_subpage');
	$m->add_subpage('Релевантность', 'relevance', 'linkate_posts_relevance_options_subpage');
	$m->add_subpage('Блок ссылок', 'output_block', 'linkate_posts_output_block_options_subpage');
    $m->add_subpage('Экспорт и сброс', 'accessibility', 'linkate_posts_accessibility_options_subpage');
    $m->add_subpage('Статистика', 'statistics', 'linkate_posts_statistics_options_subpage');
	$m->display();
	// add_action('in_admin_footer', 'linkate_posts_admin_footer');
}

function linkate_posts_license_field() {
	$options = get_option('linkate-posts');
	if (isset($_POST['update_license'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
		$options = link_cf_options_from_post($options, array('hash_field'));
		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Обновление ключа</b>', 'linkate_posts') . '</p></div>';
    }
    if (isset($_POST['remove_license'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
        $options['hash_last_check'] = 0;
        $options['hash_last_status'] = false;
        $options['hash_field'] = '';
        unset($options['activations_left']);

		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Ключ сброшен</b>', 'linkate_posts') . '</p></div>';
    }


	$info = linkate_checkNeededOption();
	if ($info) {
		$license_class = "linkateposts-accessibility-good";
		$license_header = "<h2>Лицензия активирована!</h2>";
	} else {
		$license_class = "linkateposts-accessibility-warning";
		$license_header = "<h2>Введите действительный ключ лицензии!</h2><p>Для получения ключа посетите страницу плагина: [<strong><a href=\"https://seocherry.ru/dev/cherrylink\">SeoCherry.ru</a></strong>].</p>";
    }

	?>
	<div class="<?php echo $license_class;?>">
		<?php echo $license_header; ?>
        <?php if ($info): ?>
        <p>Действует лицензия на текущий домен, ключ скрыт в целях безопасности.</p>
        <form method="post" action="">
			<input type="submit" class="button button-cherry" name="remove_license" value="<?php _e('Сбросить лицензию', 'linkate_posts') ?>" />
			<?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
		</form>
        <?php else: ?>
		<form method="post" action="">
			<label for="hash_field"><?php _e('Ваш ключ:', 'linkate_posts') ?></label>
			<input type="text" size="100" name="hash_field" id="hash_field" value="<?php echo htmlspecialchars(stripslashes($options['hash_field'])); ?>">
			<input type="submit" class="button button-cherry" name="update_license" value="<?php _e('Сохранить', 'linkate_posts') ?>" />
			<?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
		</form>
        <?php endif; ?>
	</div>
	<?php

}

// ========================================================================================= //
// ============================== CherryLink Settings Pages Callbacks  ============================== //
// ========================================================================================= //

function linkate_posts_filter_options_subpage(){
	$options = get_option('linkate-posts');
	if (isset($_POST['update_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
		$options = link_cf_options_from_post($options, array('show_customs','excluded_posts', 'included_posts', 'excluded_authors', 'included_authors', 'excluded_cats', 'included_cats', 'tag_str', 'custom', 'limit_ajax', 'show_private', 'show_pages', 'status', 'age', 'omit_current_post', 'match_cat', 'match_tags', 'sort', 'quickfilter_dblclick', 'singleword_suggestions'));
		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки обновлены.</b>', 'linkate_posts') . '</p></div>';
	}
	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">

		<div class="wrap linkateposts-tab-content">
			<form method="post" action="">
                <h2>Какие ссылки выводить?</h2>
                <hr>
                <table class="optiontable form-table">
                    <?php
                    //link_cf_display_skip($options['skip']);
                    link_cf_display_limit_ajax($options['limit_ajax']);
                    link_cf_display_show_pages($options['show_pages']);
                    link_cf_display_omit_current_post($options['omit_current_post']);
                    link_cf_display_match_cat($options['match_cat']);
                    link_cf_display_status($options['status']);
                    link_cf_display_show_private($options['show_private']);
                    link_cf_display_age($options['age']);
                    link_cf_display_show_custom_posts($options['show_customs']);
                    link_cf_display_quickfilter_dblclick($options['quickfilter_dblclick']);
                    link_cf_display_singleword_suggestions($options['singleword_suggestions']);

                    ?>
                </table>
                <input type="checkbox"  id="spoiler" />
                <label for="spoiler" >Расширенные настройки...</label>

                <div class="spoiler">
                    <h2>Расширенные настройки</h2>
                    <hr>
                    <table class="optiontable form-table">
		                <?php
		                link_cf_display_sort($options['sort']);
		                link_cf_display_match_tags($options['match_tags']);
		                //link_cf_display_match_author($options['match_author']);
		                link_cf_display_tag_str($options['tag_str']);
		                link_cf_display_excluded_posts($options['excluded_posts']);
		                link_cf_display_included_posts($options['included_posts']);
		                link_cf_display_authors($options['excluded_authors'], $options['included_authors']);
		                link_cf_display_cats($options['excluded_cats'], $options['included_cats']);
		                link_cf_display_custom($options['custom']);
		                ?>
                    </table>
                </div>
                <hr>
                <div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить настройки', 'linkate_posts') ?>" /></div>
                <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			</form>
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}

function linkate_posts_output_options_subpage(){
	$options = get_option('linkate-posts');
	if (isset($_POST['update_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		$options = link_cf_options_from_post($options, 
												array('output_template', 
													'link_before',
													'link_after', 
                                                    'link_temp_alt', 
                                                    'template_image_size',
													'no_selection_action',
													'term_before',
													'term_after', 
													'term_temp_alt', 
													'anons_len', 
													'relative_links', 
													'suggestions_switch_action', 
													'multilink')
											);

		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки обновлены.</b>', 'linkate_posts') . '</p></div>';
	}
	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
	        <form method="post" action="">
 
	    		    <h2>Вывод списка ссылок в редакторе</h2>
	    		    <p>В нужные поля подставить желаемый HTML код с использованием тегов из списка ниже. Теги выводят данные о записе или странице. </p>

	        		<table class="optiontable form-table">
	        			<?php
	        				link_cf_display_output_template($options['output_template']);
	        				//link_cf_display_none_text($options['none_text']);
	        			?>
	        		</table>

			    <hr>
			    <h2>Общие настройки шаблонов и вставки</h2>
                <hr>
				<table class="optiontable form-table">

		        	<?php
			        link_cf_display_anons_len($options['anons_len']);
			        link_cf_display_multilink($options['multilink'], $options['no_selection_action'], $options['relative_links']);
                    link_cf_display_suggestions_switch_action($options['suggestions_switch_action']);
                    link_cf_template_image_size($options['template_image_size'])
			        ?>
	        	</table>
                <p style="color:red"><strong>Изменения шаблона не повлияют на уже вставленные ссылки в статьях!</strong></p>
                <hr>
                <h2>Вывод ссылки на запись/страницу в тексте</h2>
                <p>Шаблон обрамления выделенного текста ссылкой на <i>запись, страницу</i> и пр.</p>
			    <hr>

			        <table class="optiontable form-table">
			          <?php link_cf_display_replace_template($options['link_before'], $options['link_after'], $options['link_temp_alt']); ?>
			        </table>
			    <hr>
			    <h2>Вывод ссылки на рубрику/таксономию в тексте</h2>
			    <p>Шаблон обрамления выделенного текста ссылкой на <i>рубрику, метку</i> и пр.</p>
			    <hr>

			        <table class="optiontable form-table">
			          <?php link_cf_display_replace_term_template($options['term_before'], $options['term_after'], $options['term_temp_alt']); ?>
			        </table>
			    <div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить настройки', 'linkate_posts') ?>" /><input type="submit" id="restore_templates" class="button button-download" style="float: right;" value="<?php _e('Восстановить шаблоны по умолчанию', 'linkate_posts') ?>" /></div>
			    <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			</form>
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}

function linkate_posts_output_block_options_subpage(){

	//now we drop into html to display the option page form
	?>
    <div class="linkateposts-admin-flex">
        <div class="wrap linkateposts-tab-content">
            <?php if (is_plugin_active('cherrylink-related-block/cherrylink-related-block.php')): ?>
                <div style="border: 3px dashed tomato; padding: 10px; font-size:20px;text-align: center;line-height: 25px;">Плагины <code>CherryLink</code> и <code>CRB</code> объединились. Удалите дополнение <code>CherryLink Related Block</code>, чтобы не возникало конфликтов. Все настройки останутся на месте.</div>
            <?php endif; ?>
            <?php CL_RB_Admin_Area::output_admin_options(); ?>
        </div>
		<?php link_cf_display_sidebar(); ?>
    </div>
	<?php
}

function linkate_posts_index_options_subpage(){
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');
	$options_meta = get_option('linkate_posts_meta');
	$table_index = $table_prefix."linkate_posts";
	$table_scheme = $table_prefix."linkate_scheme";


	if (isset($_POST['truncate_all'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Remove scheme
		unset($options['linkate_scheme_exists']);
		unset($options['linkate_scheme_time']);
        update_option('linkate-posts', $options);
        
        unset($options_meta['indexing_process']);
        update_option('linkate_posts_meta', $options_meta);
		$wpdb->query("TRUNCATE `$table_scheme`");
		// Remove index
		$wpdb->query("TRUNCATE `$table_index`");

		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Базы данных почищены.</b>', 'linkate_posts') . '</p></div>';
	}

	$index_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_index");
	if ($index_rows) {
		$index_status_text = " найдено $index_rows записей.";
		$index_status_class = "cherry_db_status_good";
	} else {
		$index_status_text = " статьи не найдены или нужна индексация (пересоздайте индекс).";
		$index_status_class = "cherry_db_status_bad";
	}

	$scheme_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_scheme");
	if ($scheme_rows) {
		$scheme_status_text = " найдено $scheme_rows ссылок (<a href=\"/wp-admin/options-general.php?page=linkate-posts&subpage=statistics\">поиск проблем</a>).";
		$scheme_status_class = "cherry_db_status_good";
	} else {
		$scheme_status_text = " ссылки не найдены.";
		$scheme_status_class = "cherry_db_status_bad";
    }
    
    // Is there index, was it successful, is it in progress or crushed?
    $index_process_status = isset($options_meta['indexing_process']) ? $options_meta['indexing_process'] : 'VALUE_NOT_EXIST';
    $index_process_status_text = '';
    switch($index_process_status) {
        case 'VALUE_NOT_EXIST':
            // if ($index_rows || $scheme_rows) {
            //     // probably we had index already, but not the option
            //     $index_process_status_text = '';
            // } else {
            //     $index_process_status_text = '<code class="bad-index">[Индекс не создан]</code>';
            // }
            $index_process_status_text = '<code class="bad-index">[Индекс не создан]</code>';
        break;
        case 'IN_PROGRESS': 
            $index_process_status_text = '<code class="bad-index">[Создание индекса не закончено]</code>';
        break;
        case 'DONE':
            $index_process_status_text = '<code class="good-index">[Индекс создан, все в порядке]</code>';
        break;
        default:
            $index_process_status_text = '';
        break;
    }
	
	//php moved below for ajax
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<div class="cherry-db-status">
				<h2>Статус индексирования <?php echo $index_process_status_text; ?></h2>
				<ul>
					<li>Количество записей:<span id="cherry_index_status" class="<?php echo $index_status_class; ?>"><?php echo $index_status_text; ?></span></li>
					<li>Индекс перелинковки:<span id="cherry_scheme_status" class="<?php echo $scheme_status_class; ?>"><?php echo $scheme_status_text; ?></span></li>
                </ul>
                
                <?php link_cf_prepare_tooltip('
                <p>Справа от заголовка "Статус индексирования" есть шильдик с одним из вариантов:</p><ul><li>[Индекс не создан]</li>
                <li>[Создание индекса не закончено]</li><li>[Индекс создан]</li></ul>
                <p>Текст "Создание индекса не закончено" обычно означает, что индексация не завершилась корректно. 
                Рекомендуется пересоздать индекс. Эта же надпись появится, если вы создаете индекс прямо сейчас, например, в другой вкладке браузера.</p>
                <p>Текст "Индекс не создан" говорит сам за себя. Необходимо его создать кнопкой "Пересоздать индекс".</p>
                <p>Если [Индекс создан], или шильдика с надписью нет вообще, то никаких действий не требуется.</p>'); ?>
			</div>

			<form id="options_form" method="post" action="" onsubmit="return confirm('Вы точно хотите удалить все данные?');">
			    <h2>Настройка индексирования</h2>
			    <p>Изменение любых настроек на этой странице влияет на данные в БД для алгоритма релевантности ссылок, поэтому, необходимо пересоздать индекс, чтобы изменения вступили в силу.</p>
			    <hr>
				<table class="optiontable form-table">
					<?php
						//link_cf_display_which_title($options['compare_seotitle']);
						link_cf_display_clean_suggestions_stoplist($options['clean_suggestions_stoplist']);
						link_cf_display_suggestions_donors($options['suggestions_donors_src'], $options['suggestions_donors_join'] );
						//link_cf_display_term_extraction($options['term_extraction']);
						link_cf_display_num_term_length_limit($options['term_length_limit']);
					?>
				</table>
                <h3>Полезные советы</h3>
                <ol style="color:red;">
                    <li>Если плагин не предлагает ссылок или присутствуют дубликаты - пересоздайте индекс. </li>
                    <li>"Очистка индекса" очищает только таблицы связанные с плагином. Она не повредит ваши записи и уже вставленные ссылки останутся на месте.</li>
                    <li>Реиндексация ссылок может занять значительное время, если на сайте тысячи и десятки тысяч публикаций (до нескольких минут), пожалуйста, не обновляйте страницу пока идет процесс.</li>
                    <li>После добавления/удаления/обновления записей или страниц не нужно каждый раз пересоздавать индекс - это происходит автоматически.</li>
                    <li style="font-weight:bold">Сайт большой и вы боитесь за сохранность данных? Рекомендую сделать бэкап базы перед любыми действиями.</li>
                </ol>
      		    <div id="reindex_progress_text"></div>
			    <progress id="reindex_progress"></progress>
				<div class="submit" style="text-align:right">
					<input type="submit" class="button button-cherry" name="truncate_all" value="<?php _e('Очистить индекс', 'linkate_posts') ?>" />
					<input type="submit" class="button button-download button-reindex" name="reindex_all" value="<?php _e('Пересоздать индекс', 'linkate_posts') ?>" />
					<?php  if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
				</div>
			</form>
			<hr>	
			<br>
			<input type="checkbox"  id="spoiler_stop" />
			<label for="spoiler_stop" id="label_spoiler_stop" >Редактор стоп-слов</label>

			<div class="spoiler_stop">
				<h2>Стоп-слова</h2>
				<p>Список стоп-слов индивидуальный для вашего сайта. В плагин уже встроены самые распространенные слова из русского языка, которые не учитываются в поиске схожести. Если их требуется расширить - используйте поле справа от таблицы.</p> <p>Слова нужно вводить без знаков препинания, каждое слово с новой строки. По умолчанию, все слова состоящие из 3 и меньше букв автоматически <strong>не учитывается алгоритмом</strong>. </p><p>Необходимо вписать все возможные словоформы (пример: узнать, узнал, узнала, узнают, узнавать и тд.) </p>
				<hr>
				<div style="display:flex;flex-flow: row;width: 100%;flex-wrap:nowrap;justify-content: space-between">
					<div style="flex-grow: 1; flex-shrink: 0; flex-basis: 0">
						<div class="table-controls" style="text-align: right; margin-bottom:10px;">
							<button id="stopwords-remove-all"  tabIndex="-1">Удалить все из таблицы</button>
							<button id="stopwords-defaults" tabIndex="-1">Вернуть стандартные</button>
						</div>
						<div id="example-table"></div>
					</div>
					<div style="flex-grow: 1; flex-shrink: 0; flex-basis: 0; padding-left:20px;">
						<div id="index_stopwords_suggestions"></div>
						<table class="optiontable form-table">
							<?php
							link_cf_display_stopwords();
							?>
						</table>
						<div class="table-controls">
							<button id="stopwords-add">Добавить слова</button>
						</div>
					</div>
				</div>
			</div>
			<br>
			<hr>
			<br>
			<?php $show_options = $options['linkate_scheme_exists'] == true ? 'block' : 'none'; ?>
			<input type="checkbox"  id="spoiler_scheme" />
            <label for="spoiler_scheme" style="display: <?php echo $show_options; ?>">Экспорт схемы перелинковки</label>

            <div class="spoiler_scheme">
				
				<form id="form_generate_csv" method="post" action="" >
					<?php link_cf_display_scheme_export_options(); ?>
					<progress id="csv_progress"></progress>
					<div class="submit"><input id="generate_csv" type="submit" class="button button-cherry" name="generate_csv" value="<?php _e('Создать CSV', 'linkate_posts') ?>" /></div>
					
				</form>
			</div>
		    <!--  We save and update index using ajax call, see function linkate_ajax_call_reindex below -->
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}

function linkate_posts_statistics_options_subpage(){
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');
	$options_meta = get_option('linkate_posts_meta');
	$table_index = $table_prefix."linkate_posts";
	$table_scheme = $table_prefix."linkate_scheme";
	
	$scheme_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_scheme");
	if ($scheme_rows) {
		$scheme_status_text = " найдено $scheme_rows ссылок.";
		$scheme_status_class = "cherry_db_status_good";
	} else {
		$scheme_status_text = " ссылки не найдены.";
		$scheme_status_class = "cherry_db_status_bad";
    }
	
	//php moved below for ajax
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<div class="cherry-db-status">
				<h2>Поиск проблем с перелинковкой</h2>
                <p>Нажмите на кнопку "Проверить перелинковку", чтобы найти записи, в которых:</p>
                    <ol>
                        <li>Есть повторяющиеся ссылки;</li>
                        <li>Нет входящих ссылок;</li>
                        <li>Нет исходящих ссылок.</li>
                    </ol>
                <p>Подробную статистику по перелинковке вы можете скачать в формате CSV с помощью инструмента Экспорт перелинковки на вкладке "Индекс ссылок".</p>
                <p>Всего на сайте обнаружено <strong><?php echo $scheme_rows; ?></strong> ссылок.</p>

                <?php link_cf_prepare_tooltip(''); ?>
			</div>
            <form id="form_generate_stats" method="post" action="" >
					<?php link_cf_display_scheme_statistics_options(); ?>
					<progress id="csv_progress"></progress>
					<div class="submit">
                        <input id="generate_preview" type="submit" class="button button-cherry" name="generate_preview" value="<?php _e('Проверить перелинковку', 'linkate_posts') ?>" />  
                    </div>
				</form>
            <br>
            <div id="cherry_preview_stats_container">
            </div>
            
		    <!--  We save and update index using ajax call, see function linkate_ajax_call_reindex below -->
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}


function linkate_posts_accessibility_options_subpage(){
	global $wpdb, $wp_version;
	$options = get_option('linkate-posts');

	// Create options file to export
	if (!isset($_POST['import_settings'])) {
		$str = http_build_query($options);
		$res = file_put_contents(WP_PLUGIN_DIR.'/cherrylink/export_options.txt', $str);
	}

	if (isset($_POST['import_settings']) && isset($_FILES['upload_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
		$name    = basename($_FILES['upload_options']['name']);
		$ext     = end(explode('.', $name));
        if ($ext === 'txt') {
            // get text from file
	        $str = file_get_contents($_FILES['upload_options']['tmp_name']);
	        // convert to array
	        parse_str($str,$arr);
	        // get args
	        $keys = array_keys($arr);
	        // they say - it's a bad practice
            // I say - it's okay
	        $_POST = array_merge($arr, $_POST);
	        // rewrite options
	        $options = link_cf_options_from_post($options, $keys);
	        update_option('linkate-posts', $options);
	        echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки импортированы.</b>', 'linkate_posts') . '</p></div>';
        } else {
	        echo '<div class="updated settings-error notice"><p>' . __('<b>Не удалось импортировать...</b>', 'linkate_posts') . '</p></div>';
        }
	}

	if (isset($_POST['reset_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
		fill_options(NULL);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки сброшены.</b>', 'linkate_posts') . '</p></div>';
	}
	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<div class="column-left-export">	
				<form method="post" action=""  enctype="multipart/form-data">
				    <h2>Экспорт и импорт настроек плагина</h2>
                    <p>Для переноса настроек между сайтами, скачайте файл настроек и импортируйте его на другом сайте. </p><p><strong>Внимание!</strong> Формат настроек в файле не совместим с закодированными настройками из версий младше 1.4.9. Для переноса с более младших версий, сначала обновите плагин на сайте-доноре, затем экспортируйте их в файл.</p>
		    			<?php
//		    				link_cf_display_export_template(http_build_query($options));
//		    				link_cf_display_export_template(base64_encode(http_build_query($options)));
		    			?>
                    <a class="button button-download" href="<?php echo WP_PLUGIN_URL.'/cherrylink/export_options.txt'; ?>" download>Скачать файл настроек</a>
                    <div class="submit">
                        <p><strong>Поле для импорта:</strong></p>
                        <label for="upload_options">Выберите файл </label><input type="file" name="upload_options" required>
                        <input type="submit" class="button button-cherry" name="import_settings" value="<?php _e('Импортировать настройки', 'linkate_posts') ?>" /></div>
				    <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
				</form>
			</div>
			<div class="column-right-export">
				<form method="post" action="">
					<h2>Вернуть настройки по умолчанию</h2>
			    	<p>Нажмите эту волшебную кнопочку, чтобы начать все с чистого листа. <strong>Внимание! Все настройки будут сброшены, в том числе лицезионный ключ!</strong></p>
				    <div class="submit"><input type="submit" class="button button-cherry" name="reset_options" value="<?php _e('Сбросить настройки', 'linkate_posts') ?>" /></div>
				    <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			    </form>
			</div>
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
	if (isset($_POST['export_settings'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();

		$str = http_build_query($options);
		header("Content-Disposition: attachment; filename=\"cherrylink_options.txt\"");
		header("Content-Type: application/force-download");
		header("Content-Length: " . mb_strlen($str));
		header("Connection: close");

		echo $str;
		exit();
	}
}

function linkate_posts_suggestions_options_subpage(){
	global $wpdb;
	$options = get_option('linkate-posts');
	if (isset($_POST['update_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...

		$options = link_cf_options_from_post($options, array( 'suggestions_join', 'suggestions_switch_action'));
		update_option('linkate-posts', $options);

		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки обновлены.</b>', 'linkate_posts') . '</p></div>';
	}

	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<form method="post" action="">
	        <h2>Настройки поведения панели подсказок для возможных анкоров</h2>
	        <hr>
			<table class="optiontable form-table">
				<?php
					link_cf_display_suggestions_join($options['suggestions_join']);
					//link_cf_display_suggestions_click($options['suggestions_click']);
					link_cf_display_suggestions_switch_action($options['suggestions_switch_action']);
				?>
			</table>
	        
			<div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить настройки', 'linkate_posts') ?>" /></div>
			<?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			</form>
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}

function linkate_posts_relevance_options_subpage(){
	$options = get_option('linkate-posts');
	if (isset($_POST['update_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...

		$options = link_cf_options_from_post($options, array( 'num_terms', 'match_all_against_title','weight_title', 'weight_content', 'weight_tags', 'ignore_relevance'));
		$wcontent = $options['weight_content'] + 0.0001;
		$wtitle = $options['weight_title'] + 0.0001;
		$wtags = $options['weight_tags'] + 0.0001;
		$wcombined = $wcontent + $wtitle + $wtags;
		$options['weight_content'] = $wcontent / $wcombined;
		$options['weight_title'] = $wtitle / $wcombined;
		$options['weight_tags'] = $wtags / $wcombined;
		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки обновлены.</b>', 'linkate_posts') . '</p></div>';
	}
	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<form method="post" action="">
	        <h2>Настройка релевантности ссылок с помощью дополнительных параметров алгоритма</h2>
	        <hr>
			<table class="optiontable form-table">
				<?php
					link_cf_display_ignore_relevance($options['ignore_relevance']);
					link_cf_display_weights($options);
					link_cf_display_num_terms($options['num_terms']);
					link_cf_display_match_against_title($options['match_all_against_title']);
				?>
			</table>
			<div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить настройки', 'linkate_posts') ?>" /></div>
			<?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			</form>
		</div>
		<?php link_cf_display_sidebar(); ?>
	</div>
	<?php
}