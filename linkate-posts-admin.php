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
	_e('CherryLink - Внутренняя перелинковка', 'linkate_posts');
	echo '</h2></div>';


	$m = new lp_admin_subpages();
	$m->add_subpage('Шаблон ссылок', 'output', 'linkate_posts_output_options_subpage');
	$m->add_subpage('Фильтрация', 'general', 'linkate_posts_filter_options_subpage');
//	$m->add_subpage('Подсказки', 'suggestions', 'linkate_posts_suggestions_options_subpage');
	$m->add_subpage('Релевантность', 'relevance', 'linkate_posts_relevance_options_subpage');
	$m->add_subpage('Индекс ссылок', 'other', 'linkate_posts_index_options_subpage');
	$m->add_subpage('Статистика и экспорт', 'scheme', 'linkate_posts_scheme_options_subpage');
	$m->add_subpage('Разное', 'accessibility', 'linkate_posts_accessibility_options_subpage');
	$m->add_subpage('Блок ссылок', 'output_block', 'linkate_posts_output_block_options_subpage');
	// $m->add_subpage('TEST', 'test', 'linkate_posts_test_options_subpage');
	$m->display();

	add_action('in_admin_footer', 'linkate_posts_admin_footer');
}

function linkate_posts_admin_footer() {
	//link_cf_admin_footer(str_replace('-admin', '', __FILE__), "linkate-posts");
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

                    //link_cf_display_show_attachments($options['show_attachments']);
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
		// Fill up the options with the values chosen...

		$options = link_cf_options_from_post($options, array('output_template', 'link_before','link_after', 'link_temp_alt', 'no_selection_action','term_before','term_after', 'term_temp_alt', 'anons_len', 'relative_links', 'suggestions_switch_action'));

		if (isset($_POST['multilink'])) {
			$options['multilink'] = 'checked';
		} else {
			$options['multilink'] = '';
		}

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
            <?php if (!class_exists('CherryLink_Related_Block')): ?>
            <h2>Опции вывода блока с релевантными ссылками</h2>
                <p>Установите аддон <a href="https://seocherry.ru/dev/cherrylink-related-block/">CherryLink Related Block</a> для вывода списка похожих статей под текстом записи или внутри нее.</p>
            <h3>Некоторые возможности дополнения</h3>
                <p>При установке аддона появится следующий функционал:</p>
            <ul class="crb-addon-info">
                <li>Автоматический вывод блока ссылок после статьи</li>
                <li>Вывод в произвольном месте шаблона с помощью PHP функции</li>
                <li>Вывод в любом месте в тексте шорткодом</li>
                <li>Возможность убрать из блока те ссылки, которые уже есть в тексте</li>
                <li>Настройка произвольного шаблона для блока ссылок</li>
                <li>Поддержка всех тегов шаблона ссылки, как и в основном плагине ({imagesrc}, {title}, {anons}...)</li>
                <li>Подбор ссылок вручную со страницы редактирования статьи</li>
            </ul>
                <div class="linkate-get-addon"><a href="https://seocherry.ru/dev/cherrylink-related-block/">Получить дополнение</a></div>
            <h3>Как будет выглядеть?</h3>
                <p>Выглядит блок ссылок примерно так (этот шаблон вы можете изменить):</p>
                <img src="<?php echo WP_PLUGIN_URL.'/cherrylink/'; ?>img/crb-example.jpg">


            <?php else: ?>
                <?php CRB_Admin_Area::output_admin_options(); ?>
            <?php endif; ?>
        </div>
		<?php link_cf_display_sidebar(); ?>
    </div>
	<?php
}

function linkate_posts_index_options_subpage(){
	$options = get_option('linkate-posts');
	
	//php moved below for ajax
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">

			<form id="options_form">
			    <h2>Настройка индексирования</h2>
			    <p>Изменение любых настроек на этой странице влияет на данные в БД для алгоритма релевантности ссылок, поэтому, при сохранении, будет произведена реиндексация всех записей и таксономий.</p>
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
                <hr>
				<h2>Стоп-слова</h2>
				<p>Список стоп-слов индивидуальный для вашего сайта. В плагин уже строены самые распространенные слова из русского языка, которые не учитываются в поиске схожести. Если их требуется расширить - используйте данное поле.</p> <p>Слова нужно вводить без знаков препинания, каждое слово с новой строки. По умолчанию, все слова состоящие из 3 и меньше букв автоматически <strong>не учитывается алгоритмом</strong>. </p><p>Необходимо вписать все возможные словоформы (пример: узнать, узнал, узнала, узнают, узнавать и тд.) </p>
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

                <hr>
                <p><strong style="color:red">Реиндексация ссылок может занять значительное время, если на сайте тысячи и десятки тысяч публикаций (до нескольких минут), пожалуйста, не обновляйте страницу пока идет процесс.</strong> </p>
			    <div id="reindex_progress_text"></div>
			    <progress id="reindex_progress"></progress>
		    	<div class="submit"><input type="submit" class="button button-cherry button-reindex" name="reindex_all" value="<?php _e('Сохранить и реиндексировать', 'linkate_posts') ?>" /></div>
		    </form>
		    <?php 
		    // We save and update index using ajax call, see function linkate_ajax_call_reindex below
		    if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-manage-update-options'); ?>
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


	if (isset($_POST['update_options'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Fill up the options with the values chosen...
		$options = link_cf_options_from_post($options, array('hash_field'));
		update_option('linkate-posts', $options);
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Настройки обновлены.</b>', 'linkate_posts') . '</p></div>';
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

		// Show a message to say we've done something
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
			<div class="linkateposts-column-left">	
				<?php 
			    	link_cf_display_accessibility_response(linkate_checkNeededOption());
			    ?>	
		        <form method="post" action="">
				    <h2>Лицензионный ключ</h2>
		    			<?php
		    				link_cf_display_accessibility_template($options['hash_field']);
		    			?>
				    <div class="submit"><input type="submit" class="button button-cherry" name="update_options" value="<?php _e('Сохранить', 'linkate_posts') ?>" /></div>
				    <?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
				</form>
			</div>
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

function linkate_posts_scheme_options_subpage(){
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');
	if (isset($_POST['generate_csv'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Файл сгенерирован.</b>', 'linkate_posts') . '</p></div>';
	}

	if (isset($_POST['delete_scheme'])) {
		check_admin_referer('linkate-posts-update-options');
		if (defined('POC_CACHE_4')) poc_cache_flush();
		unset($options['linkate_scheme_exists']);
		unset($options['linkate_scheme_time']);
		update_option('linkate-posts', $options);
		$table_name = $table_prefix.'linkate_scheme';
		$wpdb->query("TRUNCATE `$table_name`");
		// Show a message to say we've done something
		echo '<div class="updated settings-error notice"><p>' . __('<b>Схема удалена.</b>', 'linkate_posts') . '</p></div>';
	}


	//now we drop into html to display the option page form
	?>
	<div class="linkateposts-admin-flex">
		<div class="wrap linkateposts-tab-content">
			<form id="options_form" method="post" action="">
			    <h2>Статистика перелинковки</h2>
<!--			    <p style="font-weight:bold;color:red;">Функция находится на стадии тестирования, принимаются пожелания и предложения!</p>-->

			    <?php
		    		link_cf_display_scheme_info($options['linkate_scheme_exists'], $options['linkate_scheme_time']);
		    	?>
			    <div id="reindex_progress_text"></div>
			    <progress id="reindex_progress"></progress>
		    	<div class="submit"><input id="create_scheme" type="submit" class="button button-cherry" value="<?php _e('Создать схему перелинковки', 'linkate_posts') ?>" /><input style="display: none;" id="delete_scheme" type="submit" name="delete_scheme" class="button button-cherry" value="<?php _e('Очистить схему', 'linkate_posts') ?>" /></div>
		    	<?php if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
		    </form>
		    <?php $show_options = $options['linkate_scheme_exists'] == true ? 'block' : 'none'; ?>
		    <form id="form_generate_csv" method="post" action="" style="display: <?php echo $show_options; ?>">
		    	<?php link_cf_display_scheme_export_options(); ?>
		    	<progress id="csv_progress"></progress>
				<div class="submit"><input id="generate_csv" type="submit" class="button button-cherry" name="generate_csv" value="<?php _e('Создать CSV', 'linkate_posts') ?>" /></div>
			    <?php  //if (function_exists('wp_nonce_field')) wp_nonce_field('linkate-posts-update-options'); ?>
			</form>
		</div>
		<?php  link_cf_display_sidebar(); ?>
	</div>
	<?php
}

function linkate_posts_test_options_subpage(){
	global $wpdb, $wp_version, $table_prefix;
	$table_name = $table_prefix.'linkate_posts';
	$result = $wpdb->get_var("SELECT title FROM $table_name WHERE pID = 2350");
	$text = 'помещения алгоритм согласования работ квартире местах общего пользования ';
	$options = get_option('linkate-posts');
	$linkate_overusedwords = file(WP_PLUGIN_DIR.'/cherrylink/stopwords.txt', FILE_IGNORE_NEW_LINES);
	if(is_array($linkate_overusedwords)) {
		if (!empty($options['custom_stopwords'])) {
			$customwords = explode("\n", str_replace("\r", "", $options['custom_stopwords']));
			$linkate_overusedwords = array_merge($linkate_overusedwords, $customwords);
		}
		$linkate_overusedwords = array_flip($linkate_overusedwords);
	}
	mb_regex_encoding('UTF-8');
	mb_internal_encoding('UTF-8');
	$wordlist = mb_split("\W+", linkate_sp_mb_clean_words($text));
	$words = '';
	$exists = array();
	foreach ($wordlist as $word) {
		if (!isset($linkate_overusedwords[$word]) && mb_strlen($word) > 1 && !in_array($word, $exists)) {
			$words .= $word . ' ';
			$exists[] = $word;
		}
	}

	//now we drop into html to display the option page form
	?>
	<div class="wrap linkateposts-tab-content">
		<pre>
		<?php 
		echo PHP_EOL;
			echo $result . PHP_EOL;
			echo $words . PHP_EOL;
			echo print_r($linkate_overusedwords);
		?>	
		</pre>
	</div>
	<?php
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

// ========================================================================================= //
// ============================== CherryLink Links Index  ============================== //
// ========================================================================================= //


add_action('wp_ajax_linkate_ajax_call_reindex', 'linkate_ajax_call_reindex');
function linkate_ajax_call_reindex() {

	$options = get_option('linkate-posts');
	// Fill up the options with the values chosen...
	$options = link_cf_options_from_post($options, array('term_extraction','term_length_limit', 'clean_suggestions_stoplist', 'suggestions_donors_src', 'suggestions_donors_join'));
	
	$customwords = array_unique(array_filter(explode("\n", str_replace("\r", "", mb_strtolower($options['custom_stopwords']))))); // remove empty lines // remove duplicates
	$customwords = array_filter($customwords, function($v) {
		if (!preg_match('/[a-zа-яё]/iu', $v)) // '/[^a-z\d]/i' should also work.
		{
		  return false;
		}
		return true;
	});
	$options['custom_stopwords'] = implode(PHP_EOL, $customwords); // to string

	if (isset($_POST['compare_seotitle'])) {
		$options['compare_seotitle'] = 'checked';
	} else {
		$options['compare_seotitle'] = '';
	}
	update_option('linkate-posts', $options);

	linkate_posts_save_index_entries (true);
}

// sets up the index for the blog
function linkate_posts_save_index_entries ($is_ajax_call) {
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');
	$batch=300;
	$batch_insert=15;

    require_once (WP_PLUGIN_DIR . "/cherrylink/ru_stemmer.php");

	$stemmer = new Stem\LinguaStemRu();
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

	$t0 = microtime(true);
	$table_name = $table_prefix.'linkate_posts';
	$wpdb->query("TRUNCATE `$table_name`");
	$termcount = 0;
	$start = 0;
	$common_words = array();
	$values_string = '';

	// faild to implement this (works on localhost tho) https://stackoverflow.com/questions/36826521/how-to-implements-progress-bar-when-doing-ajax-request-voluminous-db-updates
	$ajax_array = array();

	// remove time limit for this script to be able to use ajax progress call
	if ($is_ajax_call) {
		set_time_limit(0);
		$amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item')");
	}

	while ($posts = $wpdb->get_results("SELECT `ID`, `post_title`, `post_content`, `post_type` FROM $wpdb->posts WHERE `post_type` not in ('attachment', 'revision', 'nav_menu_item') LIMIT $start, $batch", ARRAY_A)) {
		reset($posts);


		foreach ($posts as $post) {
			if ($post['post_type'] === 'revision') continue;
            $postID = $post['ID'];
            list($content, $content_sugg) = linkate_sp_get_post_terms($post['post_content'], $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
            $content = iconv("UTF-8", "UTF-8//IGNORE", $content); // convert broken symbols
            if (!$content)
                $content = '';
			$seotitle = '';
			if (function_exists('wpseo_init')){
		    	$seotitle = linkate_decode_yoast_variables($postID);
			}
		    if (function_exists( 'aioseop_init_class' )){
		        $seotitle = get_post_meta( $postID, "_aioseop_title", true);
		    }
		    // fix memory leak
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
			
			if (!empty($values_string)) $values_string .= ',';
			$values_string .= "(".$postID.", \"".$content."\", \"".$title."\", \"".$tags."\", \"".$suggestions."\")";

			// if ($termcount !=0 && ($termcount % $batch_insert === 0 || $termcount + 1 === sizeof($posts) )) {
				$wpdb->query("INSERT INTO `$table_name` (pID, content, title, tags, suggestions) VALUES $values_string");
				$values_string = '';
			// }

			$termcount = $termcount + 1;

			 $word = strtok($content, ' ');
			 while ($word !== false) {
			 	if(!array_key_exists($word,$common_words)){
			 		$common_words[$word]=0;
			 	}
			 	$common_words[$word] += 1;
			 	$word = strtok(' ');
			 }
			 arsort($common_words);
		}
		$start += $batch;
		if (!$is_ajax_call) {
			if (!ini_get('safe_mode')) set_time_limit(30);
		}
	}
	unset($posts);

	if ($is_ajax_call) {
		$amount_of_db_rows = $amount_of_db_rows + $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->terms");
	}

	//doing the same with terms (category, tag...)
	$start = 0;
	while ($terms = $wpdb->get_results("SELECT `term_id`, `name` FROM $wpdb->terms LIMIT $start, $batch", ARRAY_A)) {
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
				$aio_title = $opt['title'];
			}

			list($content, $content_sugg) = linkate_sp_get_post_terms($descr, $min_len, $linkate_overusedwords, $stemmer, $clean_suggestions_stoplist);
			//Seo title is more relevant, usually
			//Extracting terms from the custom titles, if present
			$seotitle = '';

            $yoast_opt = get_option('wpseo_taxonomy_meta');
            if ($yoast_opt && $yoast_opt['category'] && function_exists('wpseo_init')) {
                $seotitle = $yoast_opt['category'][$termID]['wpseo_title'];
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
			$wpdb->query("INSERT INTO `$table_name` (pID, content, title, tags, is_term, suggestions) VALUES ($termID, \"$content\", \"$title\", \"$tags\", 1, \"$suggestions\")");
			$termcount = $termcount + 1;

			// this was for test reasons, let it be
			unset($termID);
			unset($content);
			unset($title);
			unset($tags);
			unset($suggestions);
		}
		$start += $batch;
		if (!ini_get('safe_mode')) set_time_limit(30);
	}
	unset($terms);

	$t = microtime(true) - $t0; // how much time we spent on reindex
	if ($is_ajax_call) {
		$ajax_array['mode'] = 'done';
		$ajax_array['total'] = $amount_of_db_rows;
		$ajax_array['time'] = $t;
		$existing_blacklist = array_flip(array_filter(linkate_get_blacklist(false)));
//		$common_words = array_slice($common_words,0,20, true);
		arsort($common_words);
		$sw_count = 35;
		foreach ($common_words as $k => $v) {
		    if ($sw_count == 0) break;
			if (!isset($existing_blacklist[$k])) $ajax_array['common_words'][] = array('word' => $k, 'count' => $v);
            $sw_count--;
		}
		echo json_encode($ajax_array);
		wp_die();
	}

	// $txt = "<pre>".print_r($common_words, true)."</pre>";
	// file_put_contents('file.txt', $txt);

	return $termcount;
}



// ========================================================================================= //
// ============================== CherryLink Scheme / Export  ============================== //
// ========================================================================================= //

add_action('wp_ajax_linkate_create_links_scheme', 'linkate_create_links_scheme');
function linkate_create_links_scheme() {
	global $wpdb, $table_prefix;
	$options = get_option('linkate-posts');
	$batch=300;

	$t0 = microtime(true);
	$table_name_scheme = $table_prefix.'linkate_scheme';
	$wpdb->query("TRUNCATE `$table_name_scheme`");
	$start = 0;

	// this is needed to output our progress https://stackoverflow.com/questions/36826521/how-to-implements-progress-bar-when-doing-ajax-request-voluminous-db-updates
	$ajax_array = array();

	// remove time limit for this script to be able to use ajax progress call
	set_time_limit(0);

	$amount_of_db_rows = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts");

	while ($posts = $wpdb->get_results("SELECT `ID`, `post_content`, `post_type` FROM $wpdb->posts LIMIT $start, $batch", ARRAY_A)) {
		reset($posts);

		foreach($posts as $post) {
			if ($post['post_type'] === 'revision' || $post['post_type'] === 'attachment' || $post['post_type'] === 'nav_menu_item') continue;
			$postID = $post['ID'];
			linkate_scheme_add_row($post['post_content'], $postID, 0);
		}

		$start += $batch;
		$ajax_array['current'] = $start;

	}
	unset($posts);


	$amount_of_db_rows = $amount_of_db_rows + $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->terms");
	$ajax_array['total'] = $amount_of_db_rows;

	//doing the same with terms (category, tag...)
	$start = 0;
	while ($terms = $wpdb->get_results("SELECT `term_id` FROM $wpdb->terms LIMIT $start, $batch", ARRAY_A)) {
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

			linkate_scheme_add_row($descr, $termID, 1);
		}
		$start += $batch;
	}
	unset($terms);
	$t = microtime(true) - $t0; // how much time we spent on reindex

	$ajax_array['mode'] = 'done';
	$ajax_array['total'] = $amount_of_db_rows;
	$ajax_array['time'] = $t;
	echo json_encode($ajax_array);

	// store this, so if we have scheme - autosave it on post update
	$options['linkate_scheme_exists'] = true;
	$options['linkate_scheme_time'] = time();

	update_option('linkate-posts', $options);
	wp_die();
}

// For test purposes
add_action('wp_ajax_linkate_generate_json', 'linkate_generate_json');
function linkate_generate_json() {
	// get rows from db
	global $wpdb, $table_prefix;
	$table_name = $table_prefix.'linkate_scheme';
	$this_id = $_POST['this_id'];
	$this_type = $_POST['this_type'];
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

// WorkHorse
add_action('wp_ajax_linkate_generate_csv_or_json_prettyfied', 'linkate_generate_csv_or_json_prettyfied');
function linkate_generate_csv_or_json_prettyfied() {
	// get rows from db
	global $wpdb, $table_prefix;
	$table_name = $table_prefix.'linkate_scheme';
	$wpdb->query('SET @@group_concat_max_len = 100000;');
	$links_post = $wpdb->get_results("
		SELECT ".$table_prefix."posts.ID as source_id, ".$table_prefix."posts.post_type, COALESCE(COUNT(scheme1.target_id), 0) AS count_targets, GROUP_CONCAT(scheme1.target_id SEPARATOR ';') AS targets, GROUP_CONCAT(scheme1.target_type SEPARATOR ';') AS target_types, GROUP_CONCAT(scheme1.ankor_text SEPARATOR ';') AS ankors, GROUP_CONCAT(scheme1.external_url SEPARATOR ';') AS ext_links, COALESCE(scheme2.count_sources, 0) AS count_sources
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
		WHERE ".$table_prefix."posts.post_type NOT IN ('attachment', 'nav_menu_item', 'revision') 
		GROUP BY ".$table_prefix."posts.ID 
		ORDER BY ".$table_prefix."posts.ID ASC", ARRAY_A); //
	reset($links_post);
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
	$output_array = linkate_queryresult_to_array($links_post, 0);
	$output_array = array_merge($output_array, linkate_queryresult_to_array($links_term, 1));

	unset($links_post);
	unset($links_term);

	if (isset($_POST["from_editor"])) {
        wp_send_json($output_array);
	} else {
		query_to_csv($output_array, 'cherrylink_stats.csv');
		$response = array();
		$response['status'] = 'OK';
		$response['url'] = WP_PLUGIN_URL.'/cherrylink/cherrylink_stats.csv';
		echo json_encode($response);
	}
	unset($output_array);
	wp_die();
}

function linkate_queryresult_to_array($links, $source_type) {
    $include_types = array();
	$include_types = $_POST['export_types'];
	$output_array = array();
	//echo sizeof($links);
	foreach ($links as $link) {
		// get source url and target url
		$source_url = '';
		if ($source_type == 0) { //post
			$source_url = get_permalink((int)$link['source_id']);
			if (false === in_array($link['post_type'], $include_types) && !isset($_POST["from_editor"]))
				continue; // skip, if not in our list
			// get post's categories
			$post_categories = get_the_terms( (int)$link['source_id'], 'category' );
			if ( ! empty( $post_categories ) && ! is_wp_error( $post_categories ) ) {
				$source_categories = wp_list_pluck( $post_categories, 'name' );
			}
		} elseif ($source_type == 1) { // term
			$source_url = get_term_link((int)$link['source_id']);
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
			if (isset($_POST["from_editor"])) {
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
            if (isset($_POST["from_editor"])) {
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
	$arr_post_cats = 		'Рубрики_источника';
	$arr_source_url = 		'URL_источника';
	$arr_target = 			'URL_цели';
	$arr_ankor = 			'Анкор';
	$arr_targets_count = 	'Исходящих_ссылок';
	$arr_sources_count = 	'Входящих_ссылок';
	$headers = array($arr_post_id, $arr_post_type, $arr_post_cats, $arr_source_url, $arr_target, $arr_ankor, $arr_targets_count, $arr_sources_count);

	$fp = fopen(WP_PLUGIN_DIR.'/cherrylink/'.$filename, 'w');

	// output header row (if at least one row exists)
	array_walk($headers, 'encodeCSV');
	fputcsv_eol($fp, $headers,',','"', "\r\n");

	foreach ($array as $row) {
		array_walk($row, 'encodeCSV');
		fputcsv_eol($fp, $row,',','"', "\r\n");
	}

	fclose($fp);
}

// Some funcs moved to main file (add/remove single item from scheme)

// ========================================================================================= //
    // ============================== CherryLink StopWords ============================== //
// ========================================================================================= //

add_action("wp_ajax_linkate_get_stopwords", "linkate_get_stopwords");
function linkate_get_stopwords() {
    global $wpdb;
    $table_name = $wpdb->prefix . "linkate_stopwords";
    $rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    wp_send_json($rows);
}

add_action("wp_ajax_linkate_get_whitelist", "linkate_get_whitelist");
function linkate_get_whitelist() {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";
	$rows = $wpdb->get_col("SELECT word FROM $table_name WHERE is_white = 1");
	wp_send_json($rows);
}
add_action("wp_ajax_linkate_get_blacklist", "linkate_get_blacklist");
function linkate_get_blacklist($is_ajax = true) {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";
	$rows = $wpdb->get_col("SELECT word FROM $table_name WHERE is_white = 0");
	if ($is_ajax)
	    wp_send_json($rows);
	else {
	    return $rows;
    }
}

add_action("wp_ajax_linkate_add_stopwords", "linkate_add_stopwords");
function linkate_add_stopwords() {
    global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";

	$is_stemm = isset($_POST['is_stemm']); // if we quick-add from stopword suggestions

	if (isset($_POST['words']) && !empty($_POST['words']) ) {
		$words = $_POST['words'];
    } else {
	    return;
    }

	$is_white = isset($_POST['is_white']) ? intval($_POST['is_white']) : 0;

	require_once (WP_PLUGIN_DIR . "/cherrylink/ru_stemmer.php");
	$stemmer = new Stem\LinguaStemRu();

	$query = "INSERT INTO $table_name (stemm, word, is_white, is_custom) VALUES ";
	foreach ($words as $word) {
		$values = $wpdb->prepare("(%s,%s,%d,1)", $is_stemm ? $word : $stemmer->stem_word($word), mb_strtolower(trim($word)), $is_white);
		if ($values) {
			$wpdb->query($query . $values);
		}
	}
}

add_action("wp_ajax_linkate_delete_stopword", "linkate_delete_stopword");
function linkate_delete_stopword() {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";
	$id = isset($_POST["id"]) ? intval($_POST["id"]) : false;
	$all = isset($_POST["all"]) ? intval($_POST["all"]) : false;

	if ($all) {
	    $wpdb->query("TRUNCATE TABLE $table_name");
    } else if ($id >= 0) {
	    $wpdb->delete($table_name, array('ID' => $id));
    }
}
add_action("wp_ajax_linkate_update_stopword", "linkate_update_stopword");
function linkate_update_stopword() {
	global $wpdb;
	$table_name = $wpdb->prefix . "linkate_stopwords";

	if (isset($_POST["id"])) {
		$id = intval($_POST["id"]);
        $is_white = intval($_POST["is_white"]);

        $wpdb->update($table_name,array('is_white' => $is_white), array("ID" => $id));
	}
}

// ========================================================================================= //
// ============================== CherryLink Install Settings ============================== //
// ========================================================================================= //

// this function gets called when the plugin is installed to set up the index and default options
function linkate_posts_install() {
   	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'linkate_posts';

	$create_index = false;

	$errorlevel = error_reporting(0);
	$suppress = $wpdb->hide_errors();

	// main table, index
	if ($result = $wpdb->query("SHOW TABLES LIKE '".$table_name."'")) {
	    if($result->num_rows == 1) {
	    	if (update_table_structure())
	        	$create_index = true;
	    }
	} else {
		$create_index = true;
		    	$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`pID` bigint( 20 ) unsigned NOT NULL ,
				`content` longtext NOT NULL ,
				`title` text NOT NULL ,
				`tags` text NOT NULL ,
				`is_term` tinyint DEFAULT false,
				`suggestions` text NOT NULL ,
				FULLTEXT KEY `title` ( `title` ) ,
				FULLTEXT KEY `content` ( `content` ) ,
				FULLTEXT KEY `tags` ( `tags` ),
				FULLTEXT KEY `suggestions` ( `suggestions` )
				) ENGINE = MyISAM CHARSET = utf8;";
		$wpdb->query($sql);
		$wpdb->show_errors($suppress);
	}

	// scheme table, export, statistics
	$table_name = $table_prefix . 'linkate_scheme';
	if ($result = $wpdb->query("SHOW TABLES LIKE '".$table_name."'")) {
	    if($result->num_rows == 1) {
	        if (update_table_structure())
	        	$create_index = true;
	    }
	} else {
		$create_index = true;
		    	$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`ID` bigint( 20 ) unsigned NOT NULL primary key AUTO_INCREMENT,
				`source_id` int unsigned NOT NULL ,
				`source_type` tinyint unsigned NOT NULL ,
				`target_id` int unsigned NOT NULL ,
				`target_type` tinyint unsigned NOT NULL ,
				`ankor_text` varchar(1000) NOT NULL ,
				`external_url` varchar(1000) NOT NULL 
				) ENGINE = MyISAM CHARSET = utf8;";
		$wpdb->query($sql);
		$wpdb->show_errors($suppress);

	}

	// stopwords table
	$table_name = $table_prefix . 'linkate_stopwords';
	if ($result = $wpdb->query("SHOW TABLES LIKE '".$table_name."'")) {
		if($result->num_rows == 1) {
			if (update_table_structure())
				$create_index = true;
		}
	} else {
		$create_index = true;
		$sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
				`ID` bigint( 20 ) unsigned NOT NULL primary key AUTO_INCREMENT,
				`stemm` varchar(15) NOT NULL ,
				`word` varchar(20) NOT NULL UNIQUE ,
				`is_white` tinyint unsigned NOT NULL default 0,
				`is_custom` tinyint unsigned NOT NULL default 0 
				) ENGINE = MyISAM CHARSET = utf8;";
		$wpdb->query($sql);
		$wpdb->show_errors($suppress);

	}

	error_reporting($errorlevel);

	// (Re)fill options if empty
	$options = (array) get_option('linkate-posts');
	fill_options($options);

	//
	fill_stopwords();
	
	if ($create_index) // only (re)create if needed
		linkate_posts_save_index_entries (false); // false because we don't need some ajax stuff
}

// Adding new column 'is_tag' in plugin ver. >= 1.2.0 
function update_table_structure() {
	global $wpdb, $table_prefix;
	$update_index = false;
	$table_name = $table_prefix . 'linkate_posts';

	if (!linkate_table_column_exists($table_name, 'is_term')) {
		$sql = "ALTER TABLE `$table_name` 
			ADD COLUMN `is_term` tinyint DEFAULT false
			AFTER `tags`;";
		$wpdb->query($sql);
		$update_index = true;
	}

	if (!linkate_table_column_exists($table_name, 'suggestions')) {
		$sql = "ALTER TABLE `$table_name` 
			ADD COLUMN `suggestions` text NOT NULL
			AFTER `is_term`;";
		$wpdb->query($sql);
		$update_index = true;
	}

	return $update_index;
}


function linkate_table_column_exists( $table_name, $column_name ) {
	global $wpdb;
	$column = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
		DB_NAME, $table_name, $column_name
	) );
	if ( ! empty( $column ) ) {
		return true;
	}
	return false;
}

// used on install, import settings, revert to defaults
function fill_options($options) {
	if ($options == NULL) {
		$options = array();
	}

	// Remove stopwords from options, read from files directly - v 1.4.9
	$options['base_stopwords'] = "";
	$options['base_tinywords'] = "";

	if (!isset($options['append_on'])) $options['append_on'] = 'false';
	if (!isset($options['append_priority'])) $options['append_priority'] = '10';
	if (!isset($options['append_parameters'])) $options['append_parameters'] = 'prefix=<h3>'.__('Linkate Posts', 'linkate-posts').':</h3><ul class="linkate-posts">&suffix=</ul>';
	if (!isset($options['append_condition'])) $options['append_condition'] = 'is_single()';
	if (!isset($options['limit'])) $options['limit'] = 1000;
	if (!isset($options['limit_ajax'])) $options['limit_ajax'] = 100; // since 1.4.0
	if (!isset($options['skip'])) $options['skip'] = 0;
	if (!isset($options['age'])) {$options['age']['direction'] = 'none'; $options['age']['length'] = '0'; $options['age']['duration'] = 'month';}
	if (!isset($options['divider'])) $options['divider'] = '';
	if (!isset($options['omit_current_post'])) $options['omit_current_post'] = 'true';
	if (!isset($options['show_private'])) $options['show_private'] = 'false';
	if (!isset($options['show_pages'])) $options['show_pages'] = 'false';
	if (!isset($options['show_attachments'])) $options['show_attachments'] = 'false';
	// show_static is now show_pages
	if ( isset($options['show_static'])) {$options['show_pages'] = $options['show_static']; unset($options['show_static']);};
	if (!isset($options['none_text'])) $options['none_text'] = __('Ничего не найдено...', 'linkate_posts');
	if (!isset($options['no_text'])) $options['no_text'] = 'false';
	if (!isset($options['tag_str'])) $options['tag_str'] = '';
	if (!isset($options['excluded_cats'])) $options['excluded_cats'] = '';
	if ($options['excluded_cats'] === '9999') $options['excluded_cats'] = '';
	if (!isset($options['included_cats'])) $options['included_cats'] = '';
	if ($options['included_cats'] === '9999') $options['included_cats'] = '';
	if (!isset($options['excluded_authors'])) $options['excluded_authors'] = '';
	if ($options['excluded_authors'] === '9999') $options['excluded_authors'] = '';
	if (!isset($options['included_authors'])) $options['included_authors'] = '';
	if ($options['included_authors'] === '9999') $options['included_authors'] = '';
	if (!isset($options['included_posts'])) $options['included_posts'] = '';
	if (!isset($options['excluded_posts'])) $options['excluded_posts'] = '';
	if ($options['excluded_posts'] === '9999') $options['excluded_posts'] = '';
	if (!isset($options['show_customs'])) $options['show_customs'] = ''; // custom post types v1.2.10
	if ($options['show_customs'] === '9999') $options['show_customs'] = '';
	if (!isset($options['stripcodes'])) $options['stripcodes'] = array(array());
    $options['prefix'] = '<div class="linkate-box-container"><ol id="linkate-links-list">';
	$options['suffix'] = '</ol></div>';
	if (!isset($options['output_template'])) $options['output_template'] = '{title_seo}';
	if (!isset($options['match_cat'])) $options['match_cat'] = 'false';
	if (!isset($options['match_tags'])) $options['match_tags'] = 'false';
	if (!isset($options['match_author'])) $options['match_author'] = 'false';
	if (!isset($options['content_filter'])) $options['content_filter'] = 'false';
	if (!isset($options['custom'])) {$options['custom']['key'] = ''; $options['custom']['op'] = '='; $options['custom']['value'] = '';}
	if (!isset($options['sort'])) {$options['sort']['by1'] = ''; $options['sort']['order1'] = SORT_ASC; $options['sort']['case1'] = 'false';$options['sort']['by2'] = ''; $options['sort']['order2'] = SORT_ASC; $options['sort']['case2'] = 'false';}
	if (!isset($options['status'])) {$options['status']['publish'] = 'true'; $options['status']['private'] = 'false'; $options['status']['draft'] = 'false'; $options['status']['future'] = 'false';}
	if (!isset($options['group_template'])) $options['group_template'] = '';
	if (!isset($options['weight_content'])) $options['weight_content'] = 0.5;
	if (!isset($options['weight_title'])) $options['weight_title'] = 0.5;
	if (!isset($options['weight_tags'])) $options['weight_tags'] = 0.0;
	if (!isset($options['num_terms'])) $options['num_terms'] = 50;
	if (!isset($options['clean_suggestions_stoplist'])) $options['clean_suggestions_stoplist'] = 'false';
	$options['term_extraction'] = 'frequency'; // since 1.4 we hide TextRank option 
	if (!isset($options['hand_links'])) $options['hand_links'] = 'false';
	if (!isset($options['utf8'])) $options['utf8'] = 'true';
	if (!function_exists('mb_internal_encoding')) $options['utf8'] = 'false';
	if (!isset($options['use_stemmer'])) $options['use_stemmer'] = 'false';
	if (!isset($options['batch'])) $options['batch'] = '100';
	if (!isset($options['match_all_against_title'])) $options['match_all_against_title'] = 'false';
	if (!isset($options['link_before'])) $options['link_before'] = base64_encode(urlencode('<a href="{url}" title="{title}">'));
	if (!isset($options['link_after'])) $options['link_after'] = base64_encode(urlencode('</a>'));
	if (!isset($options['link_temp_alt'])) $options['link_temp_alt'] = base64_encode(urlencode("<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\"><span style=\"color:lightgrey;font-size:smaller;\">Читайте также</span><div style=\"position:relative;max-width: 660px;margin: 0 auto;padding: 0 20px 20px 20px;display:flex;flex-wrap: wrap;\"><div style=\"width: 35%; min-width: 180px; height: auto; box-sizing: border-box;padding-right: 5%;\"><img src=\"{imagesrc}\" style=\"width:100%;\"></div><div style=\"width: 60%; min-width: 180px; height: auto; box-sizing: border-box;\"><strong>{title}</strong><br>{anons}</div><a target=\"_blank\" href=\"{url}\"><span style=\"position:absolute;width:100%;height:100%;top:0;left: 0;z-index: 1;\">&nbsp;</span></a></div></div>"));
	if (!isset($options['term_temp_alt'])) $options['term_temp_alt'] = base64_encode(urlencode("<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\">Больше интересной информации по данной теме вы найдете в разделе нашего сайта \"<a href=\"{url}\"><strong>{title}</strong></a>\".</div>"));
	if (!isset($options['term_before'])) $options['term_before'] = base64_encode(urlencode('<a href="{url}" title="{title}">'));
	if (!isset($options['term_after'])) $options['term_after'] = base64_encode(urlencode('</a>'));
	if (!isset($options['no_selection_action'])) $options['no_selection_action'] = 'placeholder';
	if (!isset($options['hash_field'])) $options['hash_field'] = '';
	if (!isset($options['custom_stopwords'])) $options['custom_stopwords'] = '';
	if (!isset($options['term_length_limit'])) $options['term_length_limit'] = 3;
	if (!isset($options['multilink'])) $options['multilink'] = '';
	if (!isset($options['compare_seotitle'])) $options['compare_seotitle'] = '';
	if (!isset($options['hash_last_check'])) $options['hash_last_check'] = 1523569887;
	if (!isset($options['hash_last_status'])) $options['hash_last_status'] = false;
	if (!isset($options['anons_len'])) $options['anons_len'] = 200;
	if (!isset($options['suggestions_click'])) $options['suggestions_click'] = 'select';
	if (!isset($options['suggestions_join'])) $options['suggestions_join'] = 'all';
	if (!isset($options['suggestions_donors_src'])) $options['suggestions_donors_src'] = 'title';
	if (!isset($options['suggestions_donors_join'])) $options['suggestions_donors_join'] = 'join';
	if (!isset($options['suggestions_switch_action'])) $options['suggestions_switch_action'] = 'false';
	if (!isset($options['ignore_relevance'])) $options['ignore_relevance'] = 'false'; // since 1.4.0
	if (!isset($options['linkate_scheme_exists'])) $options['linkate_scheme_exists'] = false; // since 1.4.0
	if (!isset($options['linkate_scheme_time'])) $options['linkate_scheme_time'] = 0; // since 1.4.0
	if (!isset($options['relative_links'])) $options['relative_links'] = "full"; // since 1.4.9
	if (!isset($options['quickfilter_dblclick'])) $options['quickfilter_dblclick'] = "false"; // since 1.5.0
	if (!isset($options['singleword_suggestions'])) $options['singleword_suggestions'] = "true"; // since 1.6.0

	update_option('linkate-posts', $options);
	return $options;
}

add_action("wp_ajax_fill_stopwords", "fill_stopwords");
function fill_stopwords() {
    global $wpdb, $table_prefix;
    $table_name = $table_prefix."linkate_stopwords";

	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
	if ($count == 0 || isset($_POST["restore_ajax"])) {

		require_once (WP_PLUGIN_DIR . "/cherrylink/ru_stemmer.php");
		$stemmer = new Stem\LinguaStemRu();

	    // it's empty, fill the table
		$linkate_overusedwords = file(WP_PLUGIN_DIR.'/cherrylink/stopwords.txt', FILE_IGNORE_NEW_LINES);
		if (is_array($linkate_overusedwords)) {
			$query = "INSERT INTO $table_name (stemm, word, is_white, is_custom) VALUES ";
		    foreach ($linkate_overusedwords as $word) {
		        $values = $wpdb->prepare("(%s,%s,0,0)", $stemmer->stem_word($word), mb_strtolower(trim($word)));
			    if ($values) {
				    $wpdb->query($query . $values);
			    }
            }
        }

		// Add custom stopwords from old versions to the db
		$options = get_option("linkate-posts");
		$custom_stopwords = isset($options["custom_stopwords"]) ? explode("\n", str_replace("\r", "", $options['custom_stopwords'])) : array();
		if (is_array($custom_stopwords) && !empty($custom_stopwords)) {
			$query = "INSERT INTO $table_name (stemm, word, is_white, is_custom) VALUES ";
			foreach ($custom_stopwords as $word) {
				$values = $wpdb->prepare("(%s,%s,0,1)", $stemmer->stem_word($word), mb_strtolower(trim($word)));
				if ($values) {
					$wpdb->query($query . $values);
				}
			}
        }
		$options["custom_stopwords"] = "";
		update_option("linkate-posts", $options);
	}

}

if (!function_exists('link_cf_plugin_basename')) {
	if ( !defined('WP_PLUGIN_DIR') ) define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
	function link_cf_plugin_basename($file) {
		$file = str_replace('\\','/',$file); // sanitize for Win32 installs
		$file = preg_replace('|/+|','/', $file); // remove any duplicate slash
		$plugin_dir = str_replace('\\','/',WP_PLUGIN_DIR); // sanitize for Win32 installs
		$plugin_dir = preg_replace('|/+|','/', $plugin_dir); // remove any duplicate slash
		$file = preg_replace('|^' . preg_quote($plugin_dir, '|') . '/|','',$file); // get relative path from plugins dir
		return $file;
	}
}

function linkate_checkNeededOption() {
	$options = get_option('linkate-posts');
	$arr = getNeededOption();
	$final = false;
	$status = '';
	if ($arr != NULL) {
		$k = base64_decode('c2hhMjU2');
		$d = isset($_SERVER[base64_decode('SFRUUF9IT1NU')]) ?  $_SERVER[base64_decode('SFRUUF9IT1NU')] : $_SERVER[base64_decode('U0VSVkVSX05BTUU=')];
		$h = hash($k,$d);
		for ($i = 0; $i < sizeof($arr); $i++) {
			$a = base64_decode($arr[$i]);
			if ($h == $a) {
				$final = true; //'true,oldkey_good';
				$status = 'ok_old';
				//echo $status;
				return $final;
			}
		}
		if (function_exists('curl_init')) {
			$resp = explode(',',linkate_call_home(base64_encode(implode(',',$arr)), $d));
			$final = $resp[0] == 'true' ? true : false; // new
			$status = $resp[1];
		} elseif (function_exists('wp_remote_post')) {
			$resp = explode(',',linkate_call_home_nocurl(base64_encode(implode(',',$arr)), $d));
			$final = $resp[0] == 'true' ? true : false; // new
			$status = $resp[1];
        } else {
			$final = false;
			$status = 'Не найдена библиотека curl. Плагин не может быть активирован (обратитесь к техподдержке хостинга).';
			echo $status;
		}
	}

	if ($final) {
		$options['hash_last_check'] = time() + 604800; // week
		$options['hash_last_status'] = true;
	} else {
		$options['hash_last_check'] = 0;
		$options['hash_last_status'] = false;
	}
	update_option('linkate-posts', $options);
	//echo $status;
	return $final;
}

function getNeededOption() {
	$options = get_option('linkate-posts');
	$s = $options[base64_decode('aGFzaF9maWVsZA==')];
	if (empty($s)) {
		return NULL;
	} else {
		return explode(",", base64_decode($s));
	}
}

function linkate_callDelay() {
	$options = get_option('linkate-posts');
	if (time() > $options['hash_last_check']) {
		return false;
	}
	return true;
}
function linkate_lastStatus() {
	$options = get_option('linkate-posts');
	return $options['hash_last_status'];
}

function linkate_call_home($val,$d) {
	$data = array('key' => $val, 'action' => 'getInfo', 'domain' =>$d);
	$url = base64_decode('aHR0cDovL3Nlb2NoZXJyeS5ydS9wbHVnaW5zLWxpY2Vuc2Uv');
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
	curl_setopt($curl, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if(curl_errno($curl)){
    	return 'true,curl_error';
	}
	if($status != 200) {
		return 'true,'.$status;
	}
    curl_close($curl);
    return $response;
}

function linkate_call_home_nocurl ($val,$d) {
	$data = array('key' => $val, 'action' => 'getInfo', 'domain' =>$d);
	$url = base64_decode('aHR0cDovL3Nlb2NoZXJyeS5ydS9wbHVnaW5zLWxpY2Vuc2Uv');
	$response = wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => 30,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'body' => $data,
			'cookies' => array()
		)
	);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		return "false, $error_message";
	} elseif ($response['response']['code'] != 200) {
		return 'true,'.$response['response']['code'];
    }
	else {
		return $response['body'];
	}
}

// call install func on activation
add_action('activate_'.str_replace('-admin', '', link_cf_plugin_basename(__FILE__)), 'linkate_posts_install');
// call on update
add_action('upgrader_process_complete', 'linkate_on_update', 10, 2);
// call after update
add_action('plugins_loaded', 'linkate_redirectToUpdatePlugin');

// call this when plugin updates
function linkate_on_update( $upgrader_object, $options ) {
    $current_plugin_path_name = str_replace('-admin', '', link_cf_plugin_basename(__FILE__));

    if ($options['action'] == 'update' && $options['type'] == 'plugin' ){
       foreach($options['plugins'] as $each_plugin){
          if ($each_plugin==$current_plugin_path_name){
          	// set to 1 - we need it to run update script after plugin was updated by WP
          	set_transient('cherrylink_updated', 1);
          	break;
          }
       }
    }
}
// call this after plugin updates
function linkate_redirectToUpdatePlugin() {
    if (get_transient('cherrylink_updated') && current_user_can('update_plugins')) {
        linkate_posts_install();
		set_transient('cherrylink_updated', 0);
    }// endif;
}// redirectToUpdatePlugin

// For some plugins to add access to cherrylink settings page
function cherrylink_add_cap() {
	$role = get_role( 'administrator' );
	if ( is_object( $role ) ) {
		$role->add_cap( 'cherrylink_settings' );
	}
}

add_action( 'plugins_loaded', 'cherrylink_add_cap' );
