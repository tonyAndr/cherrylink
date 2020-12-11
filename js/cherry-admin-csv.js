jQuery(document).ready(function ($) {

	/*
	--- === STATS === ---
	*/
    let stats_interval_check, stats_serialized_form;
    let stats_admin_preview = false, stats_object = {};
    let stats_offset = 0, stats_limit = 300, stats_posts_count = 0, in_progress = false;

    $('#generate_csv').click(function (e) {
        e.preventDefault();
        $('#csv_progress').show();
        $('#generate_csv').hide();
        stats_serialized_form = $("#form_generate_csv").serialize();
        $("input").prop('disabled', true);
        stats_get_posts_count();
    })

    $('#generate_preview').click(function (e) {
        e.preventDefault();
        $('#csv_progress').show();
        $('#generate_preview').hide();
        stats_admin_preview = true;
        stats_serialized_form = $("#form_generate_stats").serialize() + "&admin_preview_stats=true";
        $("input").prop('disabled', true);
        stats_get_posts_count();
    })

    $('#links_direction').change(function () {
        $("#links_direction_outgoing").toggle()
        $("#links_direction_incoming").toggle()
    })

    // Get posts count to know how many requests we have to make
    function stats_get_posts_count() {
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
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Process next batch of posts
    function stats_process_next() {
        if (stats_offset >= stats_posts_count) {
            clearInterval(stats_interval_check);


            $('#csv_progress').hide();
            $("input").prop('disabled', false);
            console.log("Stats created successfully")
            if (!stats_admin_preview) {
                stats_get_file();
            } else {
                console.log("Resulting object")
                console.log(stats_object)
                create_preview_html();
            }
            return;
        }

        if (in_progress)
            return;

        let direction = $('#links_direction option:selected').val();
        let ajax_action = '';
        if (direction == "incoming")
            ajax_action = "linkate_generate_csv_or_json_prettyfied_backwards";
        else
            ajax_action = "linkate_generate_csv_or_json_prettyfied";

        let ajax_data = stats_serialized_form
            + '&action=' + ajax_action
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
                stats_object = Object.assign(stats_object, JSON.parse(response));
                stats_offset += stats_limit;
                in_progress = false;
                stats_update_progress();
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Display CSV creation progress
    function stats_update_progress() {
        let current = 0;
        current = Math.round(stats_offset / stats_posts_count * 100)
        $('#csv_progress').prop('max', 100);
        $('#csv_progress').val(current);
    }

    // Create results file
    function stats_get_file() {
        let ajax_data = 'action=linkate_merge_csv_files';

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function (response) {
                response = JSON.parse(response);
                console.log(response);
                $('#generate_csv').after('<a id="btn_csv_dload" class="button button-download" href="' + response['url'] + '" download>Скачать файл</a>');
            },
            error: function (jqXHR, textStatus, errorThrown ) {
                handle_errors(errorThrown, jqXHR.responseText);
            }
        });
    }

    // Stats preview
    function create_preview_html () {
        let output = "";
        let posts = {
            no_incoming: [],
            no_outgoing: [],
            has_repeats: []
        }

        for (const key in stats_object) {
            if (stats_object.hasOwnProperty(key)) {
                const element = stats_object[key];
                if (element.has_repeats) {
                    posts.has_repeats.push(Object.assign({id: key}, element))
                }
                if (!element.has_outgoing) {
                    posts.no_outgoing.push(Object.assign({id: key}, element))
                }
                if (!element.has_incoming) {
                    posts.no_incoming.push(Object.assign({id: key}, element))
                }
            }
        }

        let out_repeats = '';
        if (posts.has_repeats.length > 0) {
            out_repeats = posts.has_repeats.map((v, k) => {
                let repeats = '';
                for (const target in v.targets) {
                    if (v.targets.hasOwnProperty(target)) {
                        const count = v.targets[target];
                        repeats += "<li><a href=\""+target+"\" target=\"_blank\">" + target + "</a> <strong>("+count+")</strong>";
                    }
                }
                repeats = "<ol>" + repeats + "</ol>";
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td>${repeats}</td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            out_repeats = "<h3>Найдены повторы ("+posts.has_repeats.length+")</h3><table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Ссылается на (количество)</th><th>Действия</th></tr></thead><tbody>" + out_repeats + "</tbody></table>"; 
        }
        let out_incoming = '';
        if (posts.no_incoming.length > 0) {
            out_incoming = posts.no_incoming.map((v, k) => {
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            out_incoming = "<h3>Статьи без входящих ссылок ("+posts.no_incoming.length+")</h3><table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Действия</th></tr></thead><tbody>" + out_incoming + "</tbody></table>"; 
        }
        let out_outgoing = '';
        if (posts.no_outgoing.length > 0) {
            out_outgoing = posts.no_outgoing.map((v, k) => {
                return `<tr><td>${v.id}</td><td><a href="${v.url}" target="_blank">${v.url}</a></td><td><a href="/wp-admin/post.php?post=${v.id}&action=edit" target="_blank">В редактор</a></td></tr>`;
            }).join('\n');
            out_outgoing = "<h3>Статьи, которые никуда не ссылаются ("+posts.no_outgoing.length+")</h3><table class='cherry-stats-preview-table'><thead><tr><th>Post ID</th><th>URL</th><th>Действия</th></tr></thead><tbody>" + out_outgoing + "</tbody></table>"; 
        }
        output = [out_repeats, out_incoming, out_outgoing].join("<br><hr>");

        $("#cherry_preview_stats_container").html(output);
    }

    function handle_errors (error_msg, error_details) {
        console.log(error_msg);
        $('#csv_progress').hide();
        $("input").prop('disabled', false);

        $('#csv_progress').parent().append("<p>Что-то пошло не так, возникла ошибка при обработке запроса: <strong>" + error_msg +"</strong>.</p>" + error_details);
    }
});