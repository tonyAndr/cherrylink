( function( wp ) {
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var el = wp.element.createElement;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var Fragment = wp.element.Fragment;


    function Component() {
        return el(
            Fragment,
            {},
            el(
                PluginSidebarMoreMenuItem,
                {
                    target: 'cherrylink-sidebar',
                    icon: 'admin-links',
                },
                'Панель CherryLink'
            ),
            el(
                PluginSidebar,
                {
                    name: 'cherrylink-sidebar',
                    icon: 'admin-links',
                    title: 'CherryLink',
                },
                el (
                    'div',
                    {
                        className: 'cherry-panel-container'
                    }
                )
            )
        );
    }
    registerPlugin( 'cherrylink-sidebar', {
        icon: 'admin-links',
        render: Component
    });


} )( window.wp );

jQuery(document).ready(function($) {

    // keep references to be able to kill them
    let ajax_panel;
    let ajax_data;
    let ajax_stats;

    let blocks_count;
    const getBlockList = () => wp.data.select( 'core/editor' ).getBlocks();
    const getSideBarName = () => wp.data.select('core/edit-post').getActiveGeneralSidebarName();
    const isSideBarOpened = () => wp.data.select('core/edit-post').isPluginSidebarOpened();
    let sideBarState = getSideBarName() === "cherrylink-sidebar/cherrylink-sidebar" && isSideBarOpened();
    let firstRender = true;
    let blockList = getBlockList();
    wp.data.subscribe(() => {
        // content update
        const newBlockList = getBlockList();
        const blockListChanged = newBlockList !== blockList;
        blockList = newBlockList;
        if ( blockListChanged ) {
            blocks_count = blockList.length;
        }

        // sidebar events
        const newSidebarState = getSideBarName() === "cherrylink-sidebar/cherrylink-sidebar" && isSideBarOpened();
        const sideBarStateChanged = newSidebarState !== sideBarState;
        sideBarState = newSidebarState;
        if (sideBarStateChanged && isSideBarOpened()) {
            console.log("Side bar opened");
            ajax_get_panel_content();
            if (sideBarState && firstRender) {
                console.log("Side bar opened first time");
                firstRender = false;
            }
        } else if (sideBarStateChanged && !isSideBarOpened()) {
            fcl_get_data_offset = 0;

            if (ajax_panel)
                ajax_panel.abort();
            if (ajax_data)
                ajax_data.abort();
            if (ajax_stats)
                ajax_stats.abort();
        }
    });

    /*
    DATA Tools
     */
    function fcl_collectAllLinksFromContent() {
        let links = document.querySelectorAll(".editor-writing-flow a");
        cl_urls_in_content = [];
        links.forEach(function(el){
            let href = el.href;
            if (href) {
                href = fcl_convertRelativeUrl(href);
                if (!fcl_fileTypeChecker(href)) {
                    cl_urls_in_content.push(el);
                }
            }
        });
        // $.each(links, function(i, el) {
        //     let href = $(el).attr("href");
        //     if (href) {
        //         href = fcl_convertRelativeUrl(href);
        //         if (!fcl_fileTypeChecker(href)) {
        //             cl_urls_in_content.push(el);
        //         }
        //     }
        // });
    };

    function fcl_convertRelativeUrl(url) {
        let host = location.host;
        let proto = location.protocol;

        if (url.length > 0) {
            if (url.indexOf(host) === -1) {
                url = "//" + host + url;
            }
            if (url.indexOf(proto) === -1) {
                url = proto + url;
            }
        }

        return url;
    }

    function fcl_fileTypeChecker(url) { // cuz we don't want to count media as int/ext links
        let prohibited = ['.jpg','.jpeg','.tiff','.bmp','.psd', '.png', '.gif','.webp', '.doc', '.docx', '.xlsx', '.xls', '.odt', '.pdf', '.ods','.odf', '.ppt', '.pptx', '.txt', '.rtf', '.mp3', '.mp4', '.wav', '.avi', '.ogg', '.zip', '.7z', '.tar', '.gz', '.rar', '#'];

        for (let i = prohibited.length - 1; i >= 0; i--) {
            if (url.indexOf(prohibited[i]) !== -1) {
                return true;
            }
        }
        return false;
    }

    function fcl_countLinksInContent(url) {
        url = fcl_convertRelativeUrl(url);
        let selector = "a[href='"+url+"']";
        if (cl_urls_in_content) {
            let found = $(cl_urls_in_content).filter(selector);
            return found.length;
        }
        return 0;
    }
    /*
    END DATA TOOLS
     */

    /*
    VARIABLES
     */
    const T_WAIT_EDITOR_INPUT = 200; // look timeOutChecker()
    const T_WAIT_FILTER = 200; // delay on filter input
    const T_WAIT_FILTER_CB = 0; // delay on checkbox change
    const T_WAIT_TOTAL_LINKS = 100; // delay because function called twice
    const T_WAIT_SHOW_PANEL = 100; // delay on open panel

    var cl_total_links = 0; // total links from list or all from text
    var cl_urls_in_content; // array of urls
    var cl_allow_multilink = cherrylink_options['multilink'];
    var cl_exists_class = cl_allow_multilink ? 'link-exists-multi' : 'link-exists';

    var cl_list_links = $('div[class*="linkate-link"]:not(.link-term)');
    var cl_list_terms = $('div[class*="link-term"]');
    //var cl_editor_textarea = $("textarea"); // all textareas

    //var cl_editor_lastfocus = $("#content")[0]; // "#content" is a placeholder in case, if value will be empty
    //var cl_editor_lastfocus_html_content; // for suggestions
    var cl_suggestion_template_object;

    var cl_wp_ver_old = cherrylink_options['wp_ver'];

    // offset equals to skip, when data is loading from server SKIP, LIMIT
    let fcl_get_data_offset = 0;

    let cl_articles_scrolltop = 0;
    var cl_quick_cat_select;

    /*
    END VARIABLES
     */

    /*
    AJAX
     */

    // Get panel view
    function ajax_get_panel_content() {
        let ajax_data = {
            'action': 'cherrylink_gutenberg_panel'
        };
        ajax_panel = $.ajax({
            type: "POST",
            url: ajaxurl,
            data: ajax_data,
            datatype: 'json',
            success: function(response) {
                $(".cherry-panel-container").html(response);

                cherrylink_panel_tabs();

                ajax_get_data_from_server();
                ajax_get_links_stats();

                // set action to the 'load more' button
                $('.load-more-text').click(function () {
                    ajax_get_data_from_server();
                });
            }
        });
    }

    // Get Links
    function ajax_get_data_from_server() {
        var this_id = cherrylink_options['post_id'].length == 0 || cherrylink_options['post_id'] == 0  ? window.location.href.match(/tag_ID=(\d+)\&/i)[1] : cherrylink_options['post_id'];
        var ajax_post_data = {
            'action': 'getLinkateLinks',
            // here we get post or taxonomy id
            'post_id': this_id,
            'offset': fcl_get_data_offset,
            'is_term': window.location.href.indexOf('term.php') > -1 ? 1 : 0
        };
        // show loading css
        $('.lds-ellipsis').removeClass('lds-ellipsis-hide');
        $('.load-more-text').addClass('lds-ellipsis-hide');
        ajax_data = $.post(ajaxurl, ajax_post_data, function(response) {
            response = JSON.parse(response);
            // append if we have result, decide when to append 'not found' text
            if (response['count'] == 0) {
                if (fcl_get_data_offset == 0)
                    $('#linkate-links-list').append(response['links']);
            } else {
                $('#linkate-links-list').append(response['links']);
            }
            fcl_get_data_offset += response['count'];
            // hide 'load more' button if we have reached the end
            if (response['count'] < cherrylink_options['get_data_limit']) {
                $('.linkate-load-more').hide();
            } else {
                $('.linkate-load-more').show();
            }
            // reload listeners and stuff
            reLoad_when_data_received();
            // hide loading css
            $('.lds-ellipsis').addClass('lds-ellipsis-hide');
            $('.load-more-text').removeClass('lds-ellipsis-hide');
        })
    }

    // Get Statistics
    function ajax_get_links_stats() {

        if (cherrylink_options['linkate_scheme_exists']) {
            let this_id = cherrylink_options['post_id'].length == 0 || cherrylink_options['post_id'] == 0  ? window.location.href.match(/tag_ID=(\d+)\&/i)[1] : cherrylink_options['post_id'];

            let ajax_post_data = {
                'action': 'linkate_generate_json',
                'this_id': this_id,
                'this_type': cherrylink_options['post_id'].length == 0 || cherrylink_options['post_id'] == 0 ? 'term' : 'post'
            };
            ajax_stats = $.ajax({
                type: "POST",
                url: ajaxurl,
                data: ajax_post_data,
                datatype: 'json',
                success: function(response) {
                    let resp = JSON.parse(response); // All done!
                    // console.log(resp['count']);
                    let links_title='Входящие ссылки [анкор:url]<ul>';
                    for (var i = resp['links'].length - 1; i >= 0; i--) {
                        links_title += '<li><span class=\'tooltip-ankor-text\'>' + resp['links'][i]['ankor'] + '</span>: <span class=\'tooltip-url\'>' + resp['links'][i]['source_url'] + '</span></li>';
                    }
                    links_title += '</ul>';
                    $('#links-count-targets').html('<div class=\'cherry-adm-tooltip\'>'+resp['count']+'<div class=\'tooltiptext\'>'+links_title+'</div></div>');

                }
            });
        } else {
            let host = window.location.href.replace(window.location.href.slice(window.location.href.indexOf('/wp-admin/') + 10),'options-general.php?page=linkate-posts&subpage=scheme');
            $('#links-count-targets').html('<a style="font-weight:bold;color:white;" href="'+host+'" title="Нет данных, перейдите по ссылки для настройки">??</a>');
        }
    }

    /*
    END AJAX
     */

    /*
    UI
     */

    function cherrylink_panel_tabs(){
        cl_quick_cat_select = $('#quick_cat_filter');
        $('.container-articles').parent().attr('id', 'cherrylink_meta_inside'); // add it to our metabox to save scroll position

        $('.container-articles').show();
        $('.container-taxonomy').hide();

        $('div.tab').click(function() {
            if($(this).hasClass('tab-articles')) {
                $('.container-articles').show();
                $('.container-taxonomy').hide();
                $('div.tab-articles').addClass('linkate-tab-selected');
                $('div.tab-taxonomy').removeClass('linkate-tab-selected');
                // $(cl_quick_cat_select).show();
            } else {
                $('.container-articles').hide();
                $('.container-taxonomy').show();
                $('div.tab-articles').removeClass('linkate-tab-selected');
                $('div.tab-taxonomy').addClass('linkate-tab-selected');
                // $(cl_quick_cat_select).hide();
            }
        });
    }

    /*
    END UI
     */

    /*
    Editor & text utils
     */

    // func get block content
    function getBlockContentHtml(block_ind) {
        return wp.data.select( "core/editor" ).getBlocks()[block_ind].attributes.content;
    }

    function getBlockContentRich(block_ind) {
        let html = getBlockContentHtml(block_ind);
        return wp.richText.create({
            html
        });
    }

    function getBlockContentText(block_ind) {
        let richText = getBlockContentRich(block_ind);
        return wp.richText.getTextContent(richText);
    }

    // func get block uid
    function getBlockUID(block_ind) {
       return wp.data.select( "core/editor" ).getBlocks()[block_ind].clientId;
    }

    // func create el (string html)
    function createRichElement(string_html) {
        return wp.richText.create(string_html);
    }

    // func replace text (RichText Obj el) by substring
    function replaceText(text_to_replace, replacement_rich_element, block_ind) {
        let blockUid = getBlockUID(block_ind);
        let richText = getBlockContentRich(block_ind);

        richText = wp.richText.replace(richText, text_to_replace, replacement_rich_element);
        wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {
            attributes: {
                content: wp.richText.toHTMLString({
                    richText
                })
            }
        } );
    }
    // func replace text (RichText Obj el) by positions
    function replaceText(start_pos, end_pos, replacement_rich_element, block_ind) {
        let blockUid = getBlockUID(block_ind);
        let richText = getBlockContentRich(block_ind);

        richText = wp.richText.insert(richText, replacement_rich_element, start_pos, end_pos);
        wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {
            attributes: {
                content: wp.richText.toHTMLString({
                    richText
                })
            }
        } );
    }

    // func replace html by substring
    function replaceHtml(str_to_replace, replacement_html, block_ind) {
        let blockUid = getBlockUID(block_ind);
        let html = getBlockContentHtml(block_ind);

        html = html.replace(str_to_replace, replacement_html);

        let richText = wp.richText.create(html);

        wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {
            attributes: {
                content: wp.richText.toHTMLString({
                    richText
                })
            }
        } );
    }
    // func replace html by positions
    function replaceHtml(start_pos, end_pos, replacement_html, block_ind) {
        let blockUid = getBlockUID(block_ind);
        let html = getBlockContentHtml(block_ind);

        html = html.substring(0,start_pos) + replacement_html + html.substring(end_pos, html.length);

        let richText = wp.richText.create(html);

        wp.data.dispatch( 'core/editor' ).updateBlock( blockUid, {
            attributes: {
                content: wp.richText.toHTMLString({
                    richText
                })
            }
        } );
    }

    // get all indices of substr in block (text or html)
    function getIndicesOf(searchStr, str, caseSensitive) {
        var searchStrLen = searchStr.length;
        if (searchStrLen == 0) {
            return [];
        }
        var startIndex = 0, index, indices = [];
        if (!caseSensitive) {
            str = str.toLowerCase();
            searchStr = searchStr.toLowerCase();
        }
        while ((index = str.indexOf(searchStr, startIndex)) > -1) {
            indices.push(index);
            startIndex = index + searchStrLen;
        }
        return indices;
    }

    // func find all positions (string text)
    // return block_id, start, end
    function getAllPositionsOfSubstring(needle, isHtml) {
        let content = "";
        let all_indices = [];
        for (let i = 0; i < blocks_count; i++) {
            content = isHtml ? getBlockContentHtml(i) : getBlockContentText(i);
            let indices = getIndicesOf(needle, content, false);
            for (let j in indices) {
                all_indices.push({'block_id': i, 'start_pos': indices[j], 'end_pos': indices[j] + needle.length})
            }

        }
        return all_indices;
    }

    // func select (string text)
    // using find


    /*
    END Tools
     */

    /*
    Not depended on editor tools
     */

    let lastScrollSelector = "";
    let lastScrollNodes = [];
    let lastScrollNodeIndex = 0;

    let docSelection = document.getSelection();
    let customRange = document.createRange();
    docSelection.removeAllRanges();
    docSelection.addRange(customRange);

    function windowFindNodes(selector, isUrl) {
        if (lastScrollSelector !== selector) {
            lastScrollSelector = selector;
            if (isUrl) {
                lastScrollNodes = $(cl_urls_in_content).filter("a[href='"+selector+"']")
            } else {
                lastScrollNodes = $(".editor-writing-flow").find(selector);
            }
        }
        return lastScrollNodes[lastScrollNodeIndex];
    }

    // func find link and scroll
    function elementScrollSelect (selector, isUrl) {
        let node = windowFindNodes(selector, isUrl);
        console.log('Old selection');
        console.log(customRange);
        docSelection.removeRange(customRange);
        customRange = document.createRange();
        customRange.selectNode(node)
        docSelection.removeAllRanges();
        console.log('No selection');
        console.log(customRange);
        docSelection.addRange(customRange);
        console.log('New selection');
        console.log(customRange);

        node.scrollIntoView({behavior: "instant", block: "center", inline: "nearest"});
        if (lastScrollNodeIndex >= (lastScrollNodes.length - 1)) {
            lastScrollNodeIndex = 0;
        } else {
            lastScrollNodeIndex++;
        }
    }

    /*
    END NDOET
     */

    /*
    Main functionality
     */

    function reLoad_when_data_received() {
        /* =================== Input/change listeners ==================== */

        cl_list_links = $('div[class*="linkate-link"]:not(.link-term)');

        timeOutLinksChecker(T_WAIT_SHOW_PANEL);

        // incoming stats
        $('#links-count-targets').unbind().click(function() {
            ajax_get_links_stats();
        })

        $('.link-suggestions').unbind().click(function(e) {
            cl_articles_scrolltop = $('#cherrylink_meta_inside').scrollTop();
            // do the work (search everything)
            let suggestions = $(e.target).parent().find('div.linkate-link').attr('data-suggestions');
            //console.log(suggestions)

            // item contains all the data about the article: titles, url, etc
            let item = fcl_getDataAttrs(e, false, true);
            cl_suggestion_template_object = extractLinkTemplate(item, e);

            // get current article's content
            cl_editor_lastfocus_html_content = fcl_getEditorContentSuggestions();
            // remove everything but text
            let curr_content = fcl_strip(cl_editor_lastfocus_html_content).toLowerCase();
            var punctuationless = curr_content.replace(/[\.,-\/#!$%\^&\*;\":{}=\-_`~()@\+\?><\[\]\+]/g, ' ');
            var finalString = punctuationless.replace(/\n/g, " ").replace(/\s+/g," ");

            let coincidence = [];
            let sug_arr = suggestions.trim().split(' ');
            let text_arr = finalString.trim().split(' ');

            // Filter short words
            let tinywords = ['ли', 'но', 'на', 'или', 'по', 'при', 'не', 'об', 'за', 'со','от', 'до', 'то', 'ни', 'да', 'он', 'она', 'оно', 'они','его','ее','нее','про','вне','ваш','вам', 'вы', 'вас', 'го','ти', 'их', 'из', 'них'];
            text_arr = text_arr.filter(function(w) {
                return w.length > 1 && !tinywords.includes(w);
            })

            // looking for ankors in text using levenstein
            for (var i = sug_arr.length - 1; i >= 0; i--) {
                let count = 0;
                let words = [];
                let lev_koef = 1; // The longer word - the higher this value should be
                let len = sug_arr[i].length;

                if (len < 3) {
                    lev_koef = 1;
                } else if (len >= 3  && len < 7) {
                    lev_koef = 1;
                } else if (len >=7 && len < 11) {
                    lev_koef = 2;
                } else {
                    lev_koef = 3;
                }

                for (var j = text_arr.length - 1; j >= 0; j--) {
                    if (sug_arr[i] == text_arr[j]) {
                        count++;
                        if (!words.includes(text_arr[j]))
                            words.push(text_arr[j]);
                    } else if (fcl_levenshtein(sug_arr[i],text_arr[j]) <= lev_koef) {
                        count++;
                        if (!words.includes(text_arr[j]))
                            words.push(text_arr[j]);
                    }
                }
                // remove some duplicates
                let similar_coincidence_exists = false;
                for (var k = 0; k < coincidence.length; k++) {
                    if  (coincidence[k].count === count && JSON.stringify(coincidence[k].words) == JSON.stringify(words)) {
                        similar_coincidence_exists = true;
                        break;
                    }
                }
                if (!similar_coincidence_exists)
                    coincidence.push({suggestion:sug_arr[i], count:count, words:words});
            }
            // sort: less used words in text will be first
            coincidence.sort(function(a, b){return a.count - b.count});
            // not found? - remove
            coincidence = coincidence.filter(function(obj) {
                return obj.words.length > 0;
            })

            // Get positions of all ankors
            let pos_matrix = [];
            for (var i = 0; i < coincidence.length; i++) {
                let set_arr = [];
                let max_len = 0;
                for (var j = 0; j < coincidence[i].words.length; j++) {
                    let word_pos = fcl_getAllAnkorPositions(cl_editor_lastfocus_html_content, coincidence[i].words[j], 0, [])
                    if (word_pos.length !== 0) {
                        let this_len = coincidence[i].words[j].length;
                        if (this_len > max_len) max_len = this_len;
                        word_pos.forEach(function (el) {
                            let contains = false;
                            set_arr.forEach(function(sa){

                                if (sa.pos == el) {
                                    contains = true;
                                }
                            });
                            set_arr.push({pos: el, len: this_len});
                        })
                    }
                }
                pos_matrix.push(set_arr);

            }

            if (pos_matrix.length === 0) {
                //console.log('Suggestions not found');
            }
            // arrays for single and multi-word ankors
            let nearest_single = [];
            let nearest_multi = [];

            // Predlogi ne pokazivat' dlya prostih ankorov
            let predl = ['без','для','вне','под','над','безо','из-за','из-под','изо','кроме','обо','ото', 'при', 'него', 'чего'];
            predl = predl.concat(tinywords);

            // result array
            let nearest_positions = [];

            // get words, frases, all positions and text
            if (pos_matrix.length > 0) {
                //console.time('findnearest');
                for (var i = 0; i < pos_matrix.length; i++) {
                    for (var j = 0; j < pos_matrix[i].length; j++) {
                        for (var k = 0; k < pos_matrix.length; k++) {
                            if (i != k) {
                                for (var n = 0; n < pos_matrix[k].length; n++) {
                                    let start, end, diff = 100;
                                    let add = false;
                                    if (pos_matrix[i][j].pos > pos_matrix[k][n].pos) {
                                        diff = pos_matrix[i][j].pos - (pos_matrix[k][n].pos + pos_matrix[k][n].len);
                                        if (diff < 20) {

                                            start = pos_matrix[k][n].pos;
                                            end = pos_matrix[i][j].pos + pos_matrix[i][j].len;
                                            add = true;
                                            //fcl_showPosition (start, end);
                                        }
                                    } else {
                                        diff = pos_matrix[k][n].pos - (pos_matrix[i][j].pos + pos_matrix[i][j].len);
                                        if (diff < 20) {
                                            start = pos_matrix[i][j].pos;
                                            end = pos_matrix[k][n].pos + pos_matrix[k][n].len;
                                            add = true
                                            //fcl_showPosition (start, end);
                                        }
                                    }
                                    if (add) {
                                        let contains = false;
                                        nearest_positions.forEach(function(el) {
                                            if (el[0] == start && el[1] == end) {
                                                contains = true;
                                            }
                                        })
                                        if (!contains)
                                            nearest_positions.push([start, end]);
                                    }
                                }
                            }
                            let contains = false;
                            nearest_single.forEach(function(el) {
                                if (el[0] == pos_matrix[i][j].pos && el[1] == pos_matrix[i][j].pos + pos_matrix[i][j].len) {
                                    contains = true;
                                }
                            })
                            if (!contains && !fcl_inHeaderOrLink(pos_matrix[i][j].pos)) {
                                let el = fcl_getAnkorText(pos_matrix[i][j].pos,pos_matrix[i][j].pos + pos_matrix[i][j].len);
                                if (!predl.includes(el[2].toLowerCase()))
                                    nearest_single.push(el);
                            }
                        }
                    }
                }

                // Remove suggestions if more complex ankor exists at the same spot
                let n_p = nearest_positions.slice();
                for (var i = 0; i < n_p.length; i++) {
                    for (var j = 0; j < n_p.length; j++) {
                        if (n_p[i] != n_p[j] && (n_p[i][0] >= n_p[j][0] && n_p[i][1] <= n_p[j][1])) {
                            //console.log(n_p[i] + ' inside ' + n_p[j]);
                            fcl_removePos(nearest_positions, n_p[i])
                        }
                    }
                }
                // see definition below
                nearest_positions = fcl_removeBadSuggestions(nearest_positions);
                // get text for frases
                nearest_positions.forEach(function(el) {
                    nearest_multi.push(fcl_getAnkorText (el[0], el[1]));
                })
                //console.timeEnd('findnearest');
            }

            // open suggestions panel
            fcl_toggle_suggestions_tab(this, nearest_single, nearest_multi);
            return false;
        });

        $('.link-preview').unbind().click(function() {
            let url = $(this).parent().parent().find('div.linkate-link').attr('data-url');
            window.open(url,'_blank');
        });

        $('.link-counter').unbind().click(function() {
            if ($(this).hasClass('link-counter-good') || $(this).hasClass('link-counter-bad')) {
                let parent = $(this).parent()[0];
                let url = '';
                if (parent.tagName == 'LI') {
                    url = $(parent).find('div.linkate-link').attr('data-url');
                } else {
                    url = $(parent).parent().find('div.linkate-link').attr('data-url');
                }

                selectExistingTinyMCE(url);

            }
        });

        $('#hide_that_exists').change(function() {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $('#show_that_exists').change(function() {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $(cl_quick_cat_select).change(function() {
            timeOutLinksFilter(T_WAIT_FILTER_CB);
        });
        $('#filter_by_title').on('input propertychange', function() {
            timeOutLinksFilter(T_WAIT_FILTER);
        });
        $('.filter-clear-box').click(function (e) {
            $('#filter_by_title').val("").trigger("propertychange");
        });

        var cl_timerCheck;
        function timeOutLinksChecker (delay) { // wait after input some time, if input repeats - null timer and wait again, then call func
            if (cl_timerCheck) {
                clearTimeout(cl_timerCheck);
            }
            cl_timerCheck = setTimeout(function() {
                cl_total_links = 0;
                //console.time('checkTextLinks');
                fcl_checkTextLinks(cl_list_links);
                fcl_checkTextLinks(cl_list_terms);
                //console.timeEnd('checkTextLinks');
            }, delay);
        }

        var cl_timerFilter;
        function timeOutLinksFilter (delay) { // wait after input some time, if input repeats - null timer and wait again, then call func
            if (cl_timerFilter) {
                clearTimeout(cl_timerFilter);
            }
            cl_timerFilter = setTimeout(function() {
                //console.time('filterLinks');
                fcl_filterLinks(cl_list_links);
                fcl_filterLinks(cl_list_terms);
                //console.timeEnd('filterLinks');
            }, delay);
        }

        var cl_timerTotalLinks;
        function timeOutTotalCount (delay, content) { // to prevent second call of the function (it's called for links and terms separately)
            if (cl_timerTotalLinks) {
                clearTimeout(cl_timerTotalLinks);
            }
            cl_timerTotalLinks = setTimeout(function() {
                // $("#links-count-total").html(fcl_getAllIndexes(content, 'href=', 0, 0));
                //let cnt = fcl_collectAllLinksFromContent(content);
                let ankors = fcl_getAllAnkorsInUse(content);
                let links_title='Ссылки в тексте [анкор:url]<ul>';

                for (var i = ankors.length - 1; i >= 0; i--) {
                    links_title += '<li><span class=\'tooltip-ankor-text\'>' + ankors[i].ankor + '</span>: <span class=\'tooltip-url\'>' + ankors[i].url + '</span></li>';
                }
                links_title += '</ul>';
                $("#links-count-total").html('<div class=\'cherry-adm-tooltip\'>'+ankors.length+'<div class=\'tooltiptext\'>'+links_title+'</div></div>');

            }, delay);
        }

        cl_list_links.unbind().click(function (e) {
            prepareLinkTemplate(e, false);
        });

        cl_list_terms.unbind().click(function (e) {
            prepareLinkTemplate(e, true);
        });


        /* =================== END listeners ==================== */

        /* =================== SUGGESTIONS ====================== */

        function fcl_toggle_suggestions_tab(div, single_words, multi_words) {
            let ankors_in_use = fcl_getAllAnkorsInUse(cl_editor_lastfocus_html_content);
            let insert_button = '<div class="suggestion-insert-anywhere" data-url="'+$(div).parent().find('.linkate-link').attr('data-url')+'" title="Запасная кнопка, может пригодится, если вы выделили кусок текста вручную">&#9088; Вставить вокруг выделения</div>';
            let panel_header = (single_words.length > 0 || multi_words.length > 0) ? 'Найдены предполагаемые анкоры для: <strong>' + $(div).parent().find('.linkate-link').attr('data-titleseo') +'</strong>' + insert_button : '<strong>Ничего не найдено</strong>';
            let panel = '<div class="suggestions-panel"><div class="suggestions-panel-content"><div class="suggestions-panel-header">'+panel_header+'</div>';
            if (ankors_in_use.length > 0) {
                panel += '<div class="suggestions-panel-words"><div class="suggestions-panel-words-in-use-header"> > Использованные анкоры в статье</div><div class="suggestions-panel-words-in-use-text"><ul>';
                let c = 0; // to hide used ankors if many
                ankors_in_use.forEach(function(el) {
                    if (c > 2) {
                        panel += '<li class="suggestions-word-in-use-hide">' + el.ankor + '</li>';
                    } else {
                        panel += '<li>' + el.ankor + '</li>';
                    }
                    c++;
                })
                if (ankors_in_use.length > 3) {
                    panel += '</ul><a class="words-in-use-show-btn">Показать все '+c+'...</a><div style="clear:both;"></div></div></div>';
                } else {
                    panel += '</ul></div></div>';
                }
            }
            if (multi_words.length > 0) {
                panel += '<div class="suggestions-panel-frases"><div class="suggestions-panel-frases-header"> > Фразы-анкоры (найдено: '+multi_words.length+') </div>';
                panel = fcl_generateSuggestionsTemplateDropDown(panel, multi_words);
                panel += '</div>';
            }
            if (single_words.length > 0) {
                panel += '<div class="suggestions-panel-words"><div class="suggestions-panel-words-header"> > Простые анкоры (найдено: '+single_words.length+')</div>';
                panel = fcl_generateSuggestionsTemplateDropDown(panel, single_words);
                panel += '</div>';
            }

            panel += '</div></div>';
            $(div).parent().parent().parent().before(panel);
            fcl_enableSuggestionSwitches();
            if (cherrylink_options["suggestions_switch_action"] === "true")
                fcl_findSuggestionOnHover();
            fcl_toggleOnOffGeneralUI(true);
        }

        /* ================== SUGGESTIONS VIEWS AND LOGIC =============== */

        function fcl_generateSuggestionsTemplateDropDown(panel, words_array) {
            let fast_action_class = ""; // do fast insert on click, if option set
            if (cherrylink_options["suggestions_switch_action"] === "true") {
                fast_action_class = " suggestion-fast-insert";
            }
            panel += "<div class=\"suggestion-group\"><div class=\"suggestion-select-list\">"
            words_array.forEach(function(el, id, array) {
                panel += "<div class=\"suggestion-select-option \" data-start=\""+el[0]+"\" data-end=\""+el[1]+"\" data-text=\""+el[2]+"\"><div class=\"suggestion-buttons\"><a title=\"Найти в тексте\" class=\"suggestion-find\">&#x1F50E;</a><a class=\"suggestion-insert\" title=\"Вставить ссылку\">&#9088;</a></div><div class=\"suggestion-select-option-text"+fast_action_class+"\">"+el[2]+"</div></div>";
            });
            panel += "</div></div>";
            return panel;
        }

        function fcl_findSuggestionOnHover() {
            $(".suggestion-select-option").off( "mouseenter mouseleave" ).hover(function () {
                fcl_findAndSelectSuggestionDropDown(this);
            })
            $(".suggestion-select-option-text").click(function () {
                fcl_suggestionFindandInsertLink($(this).parent());
            })
        }

        // Switch between suggestion ui and links/terms lists
        function fcl_toggleOnOffGeneralUI(is_suggestions) {
            if (is_suggestions) {
                $('.linkate-filter-bar').hide();
                $('.linkate-tabs').hide();
                $('.container-articles').hide();
                $('.suggestions-panel').fadeIn();
                $('.suggestions-panel-back').fadeIn();
            } else {
                $('.suggestions-panel-back').hide();
                $('.linkate-filter-bar').fadeIn();
                $('.linkate-tabs').fadeIn();
                $('.container-articles').fadeIn('fast', function() {
                    $('#cherrylink_meta_inside').scrollTop(cl_articles_scrolltop);
                });
            }
        }

        function fcl_enableSuggestionSwitches() {
            $('.words-in-use-show-btn').unbind().click(function(){
                $(this).prev().find('li[class*="suggestions-word-in-use-hide"]').show();
                $(this).remove();
            })

            $('.suggestions-panel-back').unbind().click(function(){
                $('.suggestions-panel').remove();
                fcl_toggleOnOffGeneralUI(false);
                return false;
            })
            $('.suggestion-find').unbind().click(function(e) {
                fcl_findAndSelectSuggestionDropDown($(this).parent().parent());
            })
            $('.suggestion-insert').unbind().click(function(e) {
                let el = $(this).parent().parent();
                fcl_suggestionFindandInsertLink(el);
            })

            $('.suggestion-insert-anywhere').unbind().click(function (e) {
                    fcl_insertSuggestionTinyMCE(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                    tinymce.activeEditor.fire('change');
                $('.suggestions-panel').remove();
                fcl_toggleOnOffGeneralUI(false);
            })
        }

        function fcl_suggestionFindandInsertLink(element) {
            let start = $(element).attr('data-start');
            let end = $(element).attr('data-end');
            let word = $(element).attr('data-text');

                fcl_selectSuggestionTinyMCE(start, end, word);
                fcl_insertSuggestionTinyMCE(cl_suggestion_template_object.temp_before, cl_suggestion_template_object.temp_after);
                tinymce.activeEditor.fire('change');


            $('.suggestions-panel').remove();
            fcl_toggleOnOffGeneralUI(false);
        }

        function fcl_findAndSelectSuggestionDropDown(element) {
            let start = $(element).attr('data-start');
            let end = $(element).attr('data-end');
            let word = $(element).attr('data-text');


            fcl_selectSuggestionTinyMCE(start, end, word);

        }


        $('.suggestions-panel-close').unbind().click(function() {
            $(this).remove();
        });

        // to remove item from array
        function fcl_removePos(arr, item) {
            for (var i = arr.length; i--;) {
                if (arr[i] === item) {
                    arr.splice(i, 1);
                }
            }
        }
        // to get text between positions
        function fcl_getAnkorText(start, end) {
            let txt = cl_editor_lastfocus_html_content;
            let st = start, en = end;
            txt = txt.substring(st,en);
            return [st, en, txt];
        }

        // Remove bad suggestions with puntuation or short ones (<=3) or in h1-6
        function fcl_removeBadSuggestions(nearest_positions) {
            let n_p;
            n_p = nearest_positions.slice();
            for (var i = n_p.length - 1; i >= 0; i--) {
                let item = fcl_getAnkorText(n_p[i][0], n_p[i][1]);
                if (item[2].search(/[\.,\/#!$%\^&\*;\":{}=`~()@\+\?\\n\[\]\+]/i) > -1 || item[2].search(/ - /) > -1 || item[2].length <= 3) {
                    //console.log(item[2]);
                    fcl_removePos(nearest_positions, n_p[i]);
                }
                if (fcl_inHeaderOrLink(n_p[i][0])) {
                    fcl_removePos(nearest_positions, n_p[i]);
                }
            }
            return nearest_positions;
        }

        // check if in header or in <a> or in img
        function fcl_inHeaderOrLink(pos) {
            let txt = cl_editor_lastfocus_html_content;
            for (var i = pos - 1 ; i >= 0; i--) {
                if (!isLetter(txt.charAt(i)) && !IsNumeric(txt.charAt(i))) {
                    if (txt.charAt(i) == "<") {
                        let close_bracket_pos = txt.indexOf(">", i);
                        let tag = txt.substring(i, close_bracket_pos + 1);
                        if (tag.search(/<h[1-6].*>/i) > -1) {
                            // we are probably in the header, but we have to be sure
                            // get whole tag <h*>...</h*>, and check if we are inside
                            let whole_tag = txt.match(/<h[1-6].*<\/h[1-6]>/i)[0];
                            if (pos > i && pos < i + whole_tag.length) {
                                return true;
                            }

                        }
                        if (tag.search(/<a.*>/i) > -1) {
                            let whole_tag = txt.match(/<a.*<\/a>/i)[0];
                            if (pos > i && pos < i + whole_tag.length) {
                                return true;
                            }
                        }
                        // this one is easier, it doesn't have a closing tag
                        if (tag.search(/<img.*>/i) > -1 && (pos > i && pos < close_bracket_pos)) {
                            return true;
                        }
                        // if we are here, than we have a suggestion
                        return false;
                    }
                }
            }
            return false;
        }
        // some checkers
        function isLetter(c) {
            return c.toLowerCase() != c.toUpperCase();
        }
        function IsNumeric(n) {
            return !isNaN(parseFloat(n)) && isFinite(n);
        }

        // extract plain text from html
        function fcl_strip(html){
            var doc = new DOMParser().parseFromString(html, 'text/html');
            return doc.body.textContent || "";
        }

        /* =================== END SUGGESTIONS ================== */


        function fcl_getEditorContent() {
            let content = "";
            // todo
            return content;
        }

        // check if some links already exist
        function fcl_checkTextLinks(list) {

            fcl_collectAllLinksFromContent();
            let url;
            let limit = list.length;

            let hide_exist = $('#hide_that_exists').is(':checked');
            let show_exist = $('#show_that_exists').is(':checked');
            let filter_word = $('#filter_by_title').val().toUpperCase();

            let el;
            for (let i = limit - 1; i >= 0; i--) {
                el = list[i];
                if (el.classList.contains('linkate-link')) {
                    url = el.getAttribute('data-url');

                    let count = fcl_countLinksInContent(url);
                    cl_total_links = cl_total_links + count; // вроде не используется
                    fcl_markNumber(el, count);

                    if (fcl_hideItem(el,hide_exist,show_exist,filter_word)) {
                        el.parentElement.classList.add('link-hidden');
                    } else {
                        el.parentElement.classList.remove('link-hidden');
                    }
                }
            }
            // $("#links-count-from-list").html(cl_total_links);
            //timeOutTotalCount(T_WAIT_TOTAL_LINKS, content);
        }

        function fcl_filterLinks(list) {
            let el;

            let hide_exist = $('#hide_that_exists').is(':checked');
            let show_exist = $('#show_that_exists').is(':checked');
            let filter_word = $('#filter_by_title').val().toUpperCase().replace(/Ё/g, 'Е').trim();

            for (let i = list.length - 1; i >= 0; i--) {
                el = list[i];
                if (el.classList.contains('linkate-link') && !el.classList.contains('linkate-terms-devider')) {
                    if (fcl_hideItem(el,hide_exist,show_exist,filter_word)) {
                        el.parentElement.classList.add('link-hidden');
                    } else {
                        el.parentElement.classList.remove('link-hidden');
                    }
                }
            }
        }

        function fcl_hideItem(el,hide_exist,show_exist,filter_word){
            let text, contains, hide, hide_not_exist, dont_show_cat;
            let cat_el = $("#quick_cat_filter option:selected"); // selected item
            let cat = $(cat_el).val(); // selected val + sub val
            if (cat !== "0") {
                let cat_class_ind = parseInt($(cat_el).attr("class").substr(11)); // selected class index
                let next_el = $(cat_el).next(); // next sibling
                while (parseInt($(next_el).attr("class").substr(11)) > cat_class_ind) { // while next element has greater sub_index - concat
                    cat += "," + $(next_el).val();
                    next_el = $(next_el).next();
                }

                cat = cat.split(",");
            }

            text = el.querySelector('.link-title').innerHTML.toUpperCase().replace(/Ё/g, 'Е');
            hide = !hide_exist && el.querySelector('.link-title').classList.contains(cl_exists_class); // if we checked hide cb and link exists in text
            contains = text.indexOf(filter_word) !== -1; // if we are using quick filtering and found smth
            hide_not_exist = !show_exist && !el.querySelector('.link-title').classList.contains(cl_exists_class);
            dont_show_cat = !el.classList.contains("link-term") && cat !== "0" && !cat.includes($(el).attr("data-category"));

            return hide || !contains || hide_not_exist || dont_show_cat;
        }

        function fcl_markNumber(el,count) {
            let num = el.parentElement.querySelector('.link-counter');
            let title = el.querySelector('.link-title');

            if (count > 0) {
                if (!title.classList.contains(cl_exists_class)) {
                    title.classList.add(cl_exists_class);
                }
                num.innerText = '[ ' +count+ ' ] ' ;
                if (count > 1) {
                    num.classList.remove('link-counter-good');
                    num.classList.add('link-counter-bad');
                } else {
                    num.classList.remove('link-counter-bad');
                    num.classList.add('link-counter-good');
                }
            } else {
                if (title.classList.contains(cl_exists_class)) {
                    num.innerText = '[ 0 ]' ;
                    title.classList.remove(cl_exists_class);
                    num.classList.remove('link-counter-bad');
                    num.classList.remove('link-counter-good');
                }
            }
        }

        // When pressed link which is already in text, it's url will be found and selected in the editor
        function selectExistingTinyMCE(url) {
            fcl_collectAllLinksFromContent();
                elementScrollSelect(url, true);

        }

        // Select suggestion
        function fcl_selectSuggestionTinyMCE(start, end, word) {
            let content = cl_editor_lastfocus_html_content.substring(0,start) +  '<span id="sugg_'+ start + '_' + end + '">' + word + '</span>' + cl_editor_lastfocus_html_content.substring(end, cl_editor_lastfocus_html_content.length);
            tinyMCE.activeEditor.setContent(content);

            let selection = tinyMCE.activeEditor.dom.select('span[id="sugg_'+ start + '_' +  end + '"]')[0];

            if (selection) {
                tinyMCE.activeEditor.selection.select(selection);
                selection.scrollIntoView({behavior: "instant", block: "center", inline: "nearest"});
            }

        }

        function extractLinkTemplate(i, event) {
            let before_param = 'data-before';
            if (event.ctrlKey || event.metaKey) {
                before_param = 'data-temp-alt';
            }
            return {temp_before: decodeURIComponent(atob($('#link_template').attr(before_param)))
                    .replace(/{url}/g, i.url)
                    .replace(/{title}/g, i.title)
                    .replace(/{title_seo}/g, i.title_seo)
                    .replace(/{categorynames}/g, i.categorynames)
                    .replace(/{date}/g, i.date)
                    .replace(/{author}/g, i.author)
                    .replace(/{postid}/g, i.postid)
                    .replace(/{imagesrc}/g, i.imagesrc)
                    .replace(/{anons}/g, i.anons)
                    .replace(/\+/g, ' ')
                    .replace(/\\/g, ''),
                temp_after: event.ctrlKey || event.metaKey ? '' : decodeURIComponent(atob($('#link_template').attr('data-after')))
                    .replace(/{url}/g, i.url)
                    .replace(/{title}/g, i.title)
                    .replace(/{title_seo}/g, i.title_seo)
                    .replace(/{categorynames}/g, i.categorynames)
                    .replace(/{date}/g, i.date)
                    .replace(/{author}/g, i.author)
                    .replace(/{postid}/g, i.postid)
                    .replace(/{imagesrc}/g, i.imagesrc)
                    .replace(/{anons}/g, i.anons)
                    .replace(/\+/g, ' ')
                    .replace(/\\/g, '')
            }
        }

        function replaceSelectedLink(i, event) {

            // decode from base64 link template
            let template_obj = extractLinkTemplate(i, event);
            let temp_before = template_obj.temp_before;
            let temp_after = template_obj.temp_after;


                replaceSelectedTinyMCE(temp_before, temp_after, event, i, false);
                tinymce.activeEditor.fire('change');

        }

        function replaceSelectedTerm(i, event) {
            let before_param = 'data-before';
            if (event.ctrlKey || event.metaKey) {
                before_param = 'data-term-temp-alt';
            }
            // decode from base64 link template
            let temp_before = decodeURIComponent(atob($('#term_template').attr(before_param)))
                .replace(/{url}/g, i.url)
                .replace(/{title}/g, i.title)
                .replace(/{taxonomy}/g, i.taxonomy)
                .replace(/\+/g, ' ')
                .replace(/\\/g, '');
            let temp_after = event.ctrlKey || event.metaKey ? '' : decodeURIComponent(atob($('#term_template').attr('data-after')))
                .replace(/{url}/g, i.url)
                .replace(/{title}/g, i.title)
                .replace(/{taxonomy}/g, i.taxonomy)
                .replace(/\+/g, ' ')
                .replace(/\\/g, '');

                replaceSelectedTinyMCE(temp_before, temp_after, event, i, true);
                tinymce.activeEditor.fire('change');

        }


        function replaceSelectedTinyMCE(temp_before, temp_after, event, item, is_term) {
            let selection = tinymce.activeEditor.selection.getContent();

            if (selection) {
                let arr = trimSelection(selection);
                if (arr['hasSpaces'] == true) {
                    tinymce.activeEditor.selection.setContent(arr['first'] + temp_before + arr['selection'] + temp_after + arr['last']);
                } else {
                    tinymce.activeEditor.selection.setContent(temp_before + selection + temp_after);
                }
            }
            else {
                let between = '';
                if (!(event.ctrlKey || event.metaKey) && (between == undefined || between.length == 0)) {
                    switch(cherrylink_options['no_selection_action']) {
                        case 'title': between = item.title_seo; break;
                        case 'h1': between = item.title; break;
                        case 'placeholder': between = 'ТЕКСТ_ССЫЛКИ'; break;
                        case 'empty': between = '&nbsp;'; break;
                    }
                }
                tinymce.activeEditor.execCommand('mceInsertContent', false, temp_before+ between +temp_after);
            }

        }

        // insert suggestion

        function fcl_insertSuggestionTinyMCE(temp_before, temp_after) {
            let selection = tinymce.activeEditor.selection.getContent();

            if (selection) {
                let arr = trimSelection(selection);
                if (arr['hasSpaces'] == true) {
                    tinymce.activeEditor.selection.setContent(arr['first'] + temp_before + arr['selection'] + temp_after + arr['last']);
                } else {
                    tinymce.activeEditor.selection.setContent(temp_before + selection + temp_after);
                }
            }
        }

        // check for spaces before/after
        function trimSelection (selection) {
            let arr = [];
            if (selection) {
                selection.charAt(0) === ' ' ? arr['first'] = ' ' : arr['first'] = ''
                selection.charAt(selection.length-1) === ' ' ? arr['last'] = ' ' : arr['last'] = ''
                arr['hasSpaces'] = false;
                if (arr['first'] == " " || arr['last'] == " ") {
                    arr['hasSpaces'] = true;
                    arr['selection'] = selection.trim();
                }
                return arr;
            }
            arr['hasSpaces'] = false;
            return arr;
        }

        function fcl_getDataAttrs(event, is_term, is_suggestion) {
            let item;
            let element = is_suggestion ? $(event.target).parent().find('.linkate-link')[0] : event.target;
            // console.log(event.target);
            if (is_term) {
                let url = getAttr(element, 'data-url');
                let title = getAttr(element, 'data-title');
                let taxonomy = getAttr(element, 'data-taxonomy');
                let exists = fcl_hasClassExists(element);
                item = {url: url, title: title, taxonomy: taxonomy, exists: exists};
            } else {
                let url = getAttr(element, 'data-url');
                let title = getAttr(element, 'data-title');
                let title_seo = getAttr(element, 'data-titleseo');
                let categorynames = getAttr(element, 'data-category');
                let date = getAttr(element, 'data-date');
                let author = getAttr(element, 'data-author');
                let postid = getAttr(element, 'data-postid');
                let imagesrc = getAttr(element, 'data-imagesrc');
                let anons = getAttr(element, 'data-anons');
                let exists = fcl_hasClassExists(element);
                item = {url: url, title: title,title_seo: title_seo, categorynames: categorynames,date:date,author:author,postid:postid,imagesrc:imagesrc,anons:anons, exists: exists};
            }
            return item;
        }

        function prepareLinkTemplate(e, is_term) {
            let item = fcl_getDataAttrs(e, is_term, false);

            if (item.exists && !cl_allow_multilink) {

                    selectExistingTinyMCE(item.url)

            } else {
                is_term ? replaceSelectedTerm(item, e) : replaceSelectedLink(item, e);
            }
        }

        function fcl_hasClassExists(element) {
            let exists;
            if (element.classList.contains('linkate-link')) {
                exists = element.querySelector('.link-title').classList.contains(cl_exists_class);
            } else {
                exists = fcl_hasClassExists(element.parentElement);
            }
            return exists;
        }

        function getAttr(element, attr) {
            let val;
            if (element.classList.contains('linkate-link')) {
                val = element.getAttribute(attr);
            } else {
                val = getAttr(element.parentElement, attr);
            }
            return val;
        }




        // when open suggesions panel - check which ankors are currently in use to exclude them from suggestions
        function fcl_getAllAnkorsInUse(content) {
            let ankors = [];
            // get dom
            // get all links <a>
            var a_links = $('<div>'+content+'</div>').find('a');
            if (a_links.length > 0) {

                $.each(a_links, function(i, el) {
                    if (!fcl_fileTypeChecker($(el).attr("href"))) {
                        let url = fcl_convertRelativeUrl($(el).attr("href"));
                        ankors.push({ankor: $(el).text(), url: url});
                    }
                })
            }
            console.log(ankors);
            return ankors;
        }



        function fcl_levenshtein(a, b){
            if (a.length === 0) return b.length
            if (b.length === 0) return a.length

            var matrix = [];

            // increment along the first column of each row
            var i;
            for (i = 0; i <= b.length; i++) {
                matrix[i] = [i]
            }

            // increment each column in the first row
            var j;
            for (j = 0; j <= a.length; j++) {
                matrix[0][j] = j
            }

            // Fill in the rest of the matrix
            for (i = 1; i <= b.length; i++) {
                for (j = 1; j <= a.length; j++) {
                    if (b.charAt(i - 1) === a.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1]
                    } else {
                        matrix[i][j] = Math.min(matrix[i - 1][j - 1] + 1, // substitution
                            Math.min(matrix[i][j - 1] + 1, // insertion
                                matrix[i - 1][j] + 1)) // deletion
                    }
                }
            }

            return matrix[b.length][a.length]
        }

        function fcl_getAllAnkorPositions(text, ankor, offset, pos){
            if(pos === undefined) {
                pos = [];
                offset = 0;
            }
            offset = text.toLowerCase().indexOf(ankor, offset+ankor.length);
            if (offset > -1) {
                let dont_add = false;
                if (offset - 1 > 0) {
                    dont_add = isLetter(text.charAt(offset - 1));
                }
                if (!dont_add && offset + ankor.length < text.length) {
                    dont_add = isLetter(text.charAt(offset + ankor.length));
                }
                if (!dont_add)
                    pos.push(offset);

                if (offset < text.length) {
                    pos = fcl_getAllAnkorPositions(text, ankor, offset, pos);
                }
            }
            return pos;
        }
    }


} );

