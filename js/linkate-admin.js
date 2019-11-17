jQuery(document).ready(function($){

	$(document).on("keydown", ":input:not(textarea)", function(event) {
		return event.key != "Enter";
	});

	// todo - do smth with them later
	// default templates
	var link_alt_temp = "<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\"><span style=\"color:lightgrey;font-size:smaller;\">Читайте также</span><div style=\"position:relative;max-width: 660px;margin: 0 auto;padding: 0 20px 20px 20px;display:flex;flex-wrap: wrap;\"><div style=\"width: 35%; min-width: 180px; height: auto; box-sizing: border-box;padding-right: 5%;\"><img src=\"{imagesrc}\" style=\"width:100%;\"></div><div style=\"width: 60%; min-width: 180px; height: auto; box-sizing: border-box;\"><strong>{title}</strong><br>{anons}</div><a target=\"_blank\" href=\"{url}\"><span style=\"position:absolute;width:100%;height:100%;top:0;left: 0;z-index: 1;\">&nbsp;</span></a></div></div>";
	var term_alt_temp = "<div style=\"padding:10px;margin:10px;border-top:1px solid lightgrey;border-bottom:1px solid lightgrey;\">Больше интересной информации по данной теме вы найдете в разделе нашего сайта \"<a href=\"{url}\"><strong>{title}</strong></a>\".</div>";

	var def_temp_before = "<a href=\"{url}\" title=\"{title}\">";
	var def_temp_after = "</a>";

	$("#restore_templates").click(function (e) {
		e.preventDefault();
		$("#link_before").val(def_temp_before);
		$("#link_after").val(def_temp_after);
		$("#term_before").val(def_temp_before);
		$("#term_after").val(def_temp_after);
		$("#link_temp_alt").val(link_alt_temp);
		$("#term_temp_alt").val(term_alt_temp);

		alert("Шаблоны восстановлены, не забудьте сохранить настройки")
    });

	// main stuff

	var ajax_data = {
        'action': 'linkate_ajax_call_reindex'
    };

    // console.log('script loaded');
    $('.button-reindex').click(function(e) {
    	linkate_ajax_processing('linkate_ajax_call_reindex', e);
	})
    $('#create_scheme').click(function(e) {
    	linkate_ajax_processing('linkate_create_links_scheme', e);
	})

	function linkate_ajax_processing(action, event) {
		event.preventDefault();

		var ajax_data;

	    ajax_data = $("#options_form").serialize() + '&action=' + action;

		var last_response_length = 0;
    	$('#reindex_progress').show();
    	$('.button-reindex').hide();
    	let info_text = action === 'linkate_ajax_call_reindex' ? 'Идет реиндексация, пожалуйста подождите...' : 'Создаем схему, пожалуйста подождите...'
    	$('#reindex_progress_text').html(info_text);
    	$.ajax({
		      type: "POST",
			  url: ajaxurl,
			  data: ajax_data,
			  datatype: 'json',
		    xhr: function() {
		        var xhr = new XMLHttpRequest(); // Create a custom XHR object

		        xhr.onprogress = function(data) {
		            var response = data.currentTarget.response, // Get the output
		                progress = response.slice(last_response_length); // Remove old output
					var prog = JSON.parse(progress);

		            // $('#reindex_progress').attr('max', prog['total']);
		            // $('#reindex_progress').val(prog['current']);
		            output_progress_text(prog)
		            // console.log(prog); // Update the progress bar
		            last_response_length = response.length; // Track where the old data is (so they can be removed when new data is received)
		        };
		        return xhr; // IMPORTANT! Return the custom XHR for .ajax to use
		    },
		    success: function(response) {
		    	//$('#reindex_progress').val(100);
		        // console.log("DONE"); // All done!
		        $('#reindex_progress').hide();
    			$('.button-reindex').show();
    			if (action === 'linkate_create_links_scheme') {
			    	$('#form_generate_csv').css('display','block');
    			}
    			//console.log(response['common_words']);

		    },
			error: function (err) {

            }
		});
	}

    // prog - json object
    function output_progress_text(prog) {
    	var output = '';
    	if (prog['mode'] === 'posts') {
    		output = 'Реиндексируем записи... <br>Обработано публикаций: ' +prog['current'] + '/' + prog['total'] + '...';
    	}
    	if (prog['mode'] === 'terms') {
    		output = 'Реиндексируем рубрики...';
    		
    	}
    	if (prog['mode'] === 'done') {
    		output = 'Реиндексация закончена. Всего ссылок найдено: ' +prog['total']+ '. Время затрачено: ' + Math.round(prog['time']) + ' секунд';
    		if (prog['common_words'] && prog['common_words'].length > 0) {
    			var w = confirm('Найдены общие слова, которые можно добавить в список стоп-слов. Желаете посмотреть?');
				if (w) {
					var output_string = '<h2>Самые часто используемые слова на вашем сайте</h2><p>Формат вывода - <strong>основа слова: количество использований</strong>. Чтобы сразу добавить слово в черный список - просто нажмите на соответствующую строку.</p><ol>';
					prog['common_words'].forEach(function(item){
						output_string += "<li title='Нажмите, чтобы добавить в стоп-лист' data-stemm='"+item.word+"' class='index-stopsugg-add'><strong>"+item.word + "</strong>: " + item.count + "</li>";
					});
					output_string += "</ol>";
					$("#index_stopwords_suggestions").html(output_string);
					$("#index_stopwords_suggestions").show();

					// quick add suggestions w/o stemming
					$(".index-stopsugg-add").click(function (event) {
						event.preventDefault();

						let words = [$(this).attr('data-stemm').trim()];
						let ajax_data = {
							words: words,
							action: 'linkate_add_stopwords',
							is_white: 0,
							is_stemm: 1
						};
						$(this).remove();
						$.ajax({
							type: "POST",
							url: ajaxurl,
							data: ajax_data,
							datatype: 'json',
							success: function(response) {
								table.setData();
							}
						});

					});
				}
    		}

    	}
    	$('#reindex_progress_text').html(output);
    }

    if (scheme.state) { // scheme variable comes from linkate-posts.php wp_localize_script
    	$('#form_generate_csv').css('display','block');
    } else {
    	$('#form_generate_csv').css('display','none');
    }

    $('input[type="checkbox"]').change(function () {
		$('#btn_csv_dload').remove();
        $('#generate_csv').show();
    })

	let stats_interval_check, stats_serialized_form;

    $('#generate_csv').click(function(e){
    	e.preventDefault();
    	$('#csv_progress').show();
    	$('#generate_csv').hide();
		stats_serialized_form = $("#form_generate_csv").serialize();
		$("input").prop('disabled', true);
		stats_get_posts_count();
	})



	function stats_get_posts_count() {
		// let ajax_data = {
		// 	'action': 'linkate_get_all_posts_count'
		// };
		let ajax_data = stats_serialized_form + '&action=linkate_get_all_posts_count';


		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: ajax_data,
			datatype: 'text',
			success: function (response) {
				// response = JSON.parse(response);
				console.log("Starting process with " + response + " posts found");
				stats_posts_count = parseInt(response);
				// update stats_posts_count
				stats_interval_check = setInterval(stats_process_next, 500);
			}
		});
	}

	let stats_offset = 0, stats_limit = 300, stats_posts_count = 0, in_progress = false;
	
	function stats_process_next() {
		if (stats_offset >= stats_posts_count) {
			clearInterval(stats_interval_check);

			
			$('#csv_progress').hide();
			$("input").prop('disabled', false);
			console.log("Stats created successfully")
			stats_get_file();
			return;
		}

		if (in_progress)
			return;
		
		let ajax_data = stats_serialized_form
		+ '&action=linkate_generate_csv_or_json_prettyfied'
		+ '&stats_offset=' + stats_offset
		+ '&stats_limit=' + stats_limit;

		in_progress = true;
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: ajax_data,
			datatype: 'json',
			success: function (response) {
				console.log(JSON.parse(response));
				stats_offset += stats_limit; 
				in_progress = false;
				stats_update_progress();
			}
		});
	}

	function stats_get_file() {
		let ajax_data = 'action=linkate_merge_csv_files';

		in_progress = true;
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: ajax_data,
			datatype: 'json',
			success: function (response) {
				response = JSON.parse(response);
				console.log(response);
				$('#generate_csv').after('<a id="btn_csv_dload" class="button button-download" href="' + response['url'] + '" download>Скачать файл</a>');
			}
		});
	}

	function stats_update_progress() {
		let current = 0;
		current = Math.round(stats_offset/stats_posts_count*100)
		$('#csv_progress').prop('max', 100);
		$('#csv_progress').val(current);
	}


	// ========================= STOPWORDS TABLE =========================
	//create Tabulator on DOM element with id "example-table"
	if ($("#example-table").length) {
		var table = new Tabulator("#example-table", {
			ajaxURL:ajaxurl,
			ajaxParams:{action:"linkate_get_stopwords"}, //ajax parameters
			ajaxConfig:"POST", //ajax HTTP request type
			pagination:"local",
			paginationSize:25,
			addRowPos:"top",          //when adding a new row, add it to the top of the table
			history:true,
			initialSort:[
				{column:"id", dir:"asc"}, //sort by this first
			],
			columnHeaderSortMulti:true,
			responsiveLayout:"collapse",
			paginationSizeSelector:[25, 100, 250, 1000],
			layout:"fitColumns", //fit columns to width of table (optional)
			langs:{
				"ru-ru":{
					"ajax":{
						"loading":"Загрузка", //ajax loader text
						"error":"Ошибка", //ajax error text
					},
					"pagination":{
						"page_size":"Кол-во строк", //label for the page size select element
						"first":"Первая", //text for the first page button
						"first_title":"Первая", //tooltip text for the first page button
						"last":"Последняя",
						"last_title":"Последняя",
						"prev":"Назад",
						"prev_title":"Назад",
						"next":"Вперед",
						"next_title":"Вперед",
					},
					"headerFilters":{
						"default":"фильтр...", //default header filter placeholder text
					}
				}
			},
			columns: [
				{title: '#', formatter:"rownum", width:'5%', headerSort:false},
				{title: "Слово", field: "word", widthGrow:2, headerFilter:"input"},
				{title: "Корень", field: "stemm", widthGrow:2, headerFilter:"input"},
				{title: "Источник", field: "is_custom", widthGrow:1, headerFilter:'select', headerFilterParams:{values:{ "":"Все", 0:"Стандарт", 1:"Произвольное"}}, formatter:function(cell, formatterParams){
						var value = cell.getValue();
						if(value > 0){
							return "Произв.";
						}else{
							return "Станд.";
						}
					}},
				{title: "Список", field: "is_white", editor:"select", widthGrow:1, editorParams:{values:{0:"Черный список", 1:"Белый список"}}, headerFilter:true, headerFilterParams:{values:{ "":"Все", 0:"Черный список", 1:"Белый список"}}, formatter:function(cell, formatterParams){
						var value = cell.getValue();
						if(value > 0){
							return "Бел. Сп.";
						}else{
							return "Чер. Сп.";
						}
					},cellEdited:function(cell){
						//cell - cell component
						let ajax_data = {
							id: cell.getRow().getData().ID,
							action: 'linkate_update_stopword',
							is_white: cell.getValue()
						};
						$.ajax({
							type: "POST",
							url: ajaxurl,
							data: ajax_data,
							datatype: 'json',
							success: function(response) {
								//table.setData();
							}
						});
					},},
				{title:'', width:'3%', headerSort:false, formatter:"buttonCross", cellClick:function(e, cell){

						let ajax_data = {
							id: cell.getRow().getData().ID,
							action: 'linkate_delete_stopword'
						};
						cell.getRow().delete();
						$.ajax({
							type: "POST",
							url: ajaxurl,
							data: ajax_data,
							datatype: 'json',
							success: function(response) {
								//table.setData();

							}
						});

				},}
			]
		});

		table.setLocale('ru-ru');

		$("#stopwords-add").click(function (event) {
			event.preventDefault();
			if ($("#custom_stopwords").val().trim().length === 0) {
				alert("Поле пустое!")
			} else {
				let words = $("#custom_stopwords").val().trim().replace("\r", "").split("\n");
				let ajax_data = {
					words: words,
					action: 'linkate_add_stopwords',
					is_white: $("#is_white").is(':checked') ? 1 : 0
				};
				$.ajax({
					type: "POST",
					url: ajaxurl,
					data: ajax_data,
					datatype: 'json',
					success: function(response) {
						table.setData();
					}
				});
			}
		})
		$("#stopwords-defaults").click(function (event) {
			event.preventDefault();
			let conf = confirm("Это действие удалит все стоп-слова из таблицы и вернет стандартные. Хотите продолжить?");
			if (!conf)
				return false;
			let ajax_data = {
				action: 'fill_stopwords',
				restore_ajax: 'yes'
			};
			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: ajax_data,
				datatype: 'json',
				success: function(response) {
					table.setData();
				}
			});
		})
		$("#stopwords-remove-all").click(function (event) {
			event.preventDefault();
			let conf = confirm("Вы точно хотите удалить все стоп слова?");
			if (!conf)
				return false;
			let ajax_data = {
				action: 'linkate_delete_stopword',
				all: 1
			};
			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: ajax_data,
				datatype: 'json',
				success: function(response) {
					table.setData();
				}
			});
		})

	}
});