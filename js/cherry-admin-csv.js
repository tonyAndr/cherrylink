jQuery(document).ready(function ($) {

	/*
	--- === STATS === ---
	*/
    let stats_interval_check, stats_serialized_form;
    let stats_offset = 0, stats_limit = 300, stats_posts_count = 0, in_progress = false;

    $('#generate_csv').click(function (e) {
        e.preventDefault();
        $('#csv_progress').show();
        $('#generate_csv').hide();
        stats_serialized_form = $("#form_generate_csv").serialize();
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
            stats_get_file();
            return;
        }

        if (in_progress)
            return;

        let direction = $('#links_direction option:selected').val();
        let ajax_action = '';
        if (direction == "outgoing")
            ajax_action = "linkate_generate_csv_or_json_prettyfied";
        else
            ajax_action = "linkate_generate_csv_or_json_prettyfied_backwards";

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
                stats_offset += stats_limit;
                in_progress = false;
                stats_update_progress();
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
            }
        });
    }
});