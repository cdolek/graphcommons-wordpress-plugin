jQuery(function ($) {

    'use strict';

    var
        working                 = false,
        selectedHubId           = '',
        selectedObjectType      = 'node',
        doneTypingInterval      = 500,
        keyword,
        typingTimer,
        selectedNode,
        gcwindow,
        gcwindowData,
        textbox,
        cache                   = {
            node: {},
            graph: {}
        };

    /* TinyMCE Plugin */
    tinymce.PluginManager.add('graphcommons', function(editor, url) {

        $(document).on('click', '#insert-graphcommons-node', function(e){
            e.preventDefault();
            wp.mce.graphcommons.popupwindow(editor);
        });

    });


    var media = wp.media, shortcode_string = 'graphcommons', w = false, pw = false;

    wp.mce = wp.mce || {};

    wp.mce.graphcommons = {

        shortcode_data: {},

        template: media.template( 'editor-graphcommons' ),

        getContent: function() {
            var options = this.shortcode.attrs.named;
            options['innercontent'] = this.shortcode.content;
            return this.template(options);
        },

        View: { // before WP 4.2:
            template: media.template( 'editor-graphcommons' ),
            postID: $('#post_ID').val(),
            initialize: function( options ) {
                this.shortcode = options.shortcode;
                wp.mce.graphcommons.shortcode_data = this.shortcode;
            },
            getHtml: function() {
                var options = this.shortcode.attrs.named;
                options['innercontent'] = this.shortcode.content;
                return this.template(options);
            }
        },

        edit: function( data, update ) {
            var shortcode_data = wp.shortcode.next(shortcode_string, data);
            var values = shortcode_data.shortcode.attrs.named;
            values['innercontent'] = shortcode_data.shortcode.content;
            wp.mce.graphcommons.popupwindow(tinyMCE.activeEditor, values);
        },

        popupwindow: function(editor, values){

            values = values || [];

            w = editor.windowManager.open( {
                title: graphcommons.language.addgcobject,
                width: 800,
                height: 540,
                buttons: [],
                onPostRender: function(e){

                    var self = this;

                    textbox = $('#'+self._id).find('.mce-textbox');

                    // textbox formatting which can not be done via tinymce. Damn.
                    $(textbox).attr({
                        'placeholder'   : graphcommons.language.searchkeyword,
                        'type'          : 'search',
                        'onfocus'       : 'this.value = this.value;'
                    });

                    $(textbox).css('padding-left', '10px');

                    // typing timer
                    $(textbox).on('keyup', function(){
                        keyword = $(this).val().trim();
                        clearTyperTimeout();
                    });

                    // clear cache on type change and trigger the search
                    $('#gc_object_type_graph, #gc_object_type_node').on('click', function(e){
                        selectedObjectType = $(this).val();
                        resetCache();
                        $(textbox).trigger('keyup');
                    });

                },

                body:[
                        {
                            type    : 'container',
                            name    : 'container',
                            html    : '<div id="gc_wrapper" class="gc_content_2"><input type="radio" id="gc_object_type_node" name="gc_object_type" value="node" checked><label for="gc_object_type_node"> Nodes</label> <input type="radio" id="gc_object_type_graph" name="gc_object_type" value="graph"><label for="gc_object_type_graph"> Graphs</label></div>',

                        },

                        {
                            type    : 'textbox',
                            name    : 'search',
                            value   : values['name']
                        },

                        {
                            type: 'listbox',
                            name: 'gc_hubs',
                            value: values['hub'],
                            values: graphcommons.hubs,
                            onselect: function(e) {
                                selectedHubId = this.value();
                                resetCache();
                                clearTyperTimeout();
                                $(textbox).trigger('keyup');
                            }
                        },

                        {
                            type    : 'container',
                            name    : 'container',
                            html    : '<div id="gc_content" class="gc_content">' + graphcommons.language.typesomething + '</div>',
                        },

                    ],

                onclose: function(){
                    wp.mce.graphcommons.clearKeywordAndWindowData();
                },
                oncancel: function(){
                }
            } );
        },

        popupwindowNodePreview: function(editor, values, onsubmit_callback){

            pw = editor.windowManager.open( {
                title: graphcommons.language.nodepreview,
                body: [
                    {
                    type   : 'container',
                    name   : 'container',
                    html   : '<iframe src="https://graphcommons.com/nodes/'+gcwindowData.gcId+'/embed?p=&et=i%C5%9F%20orta%C4%9F%C4%B1d%C4%B1r&g=true" frameborder="0" style="overflow:auto;width:320px;min-width:320px;height:100%;min-height:460px;border:1px solid #CCCCCC;" width="320" height="460"></iframe>'
                    }

                ],
                onsubmit: function( e ) {
                    var self = this;
                    wp.mce.graphcommons.insertContent(editor);
                },
                onclose: function(){
                    wp.mce.graphcommons.clearKeywordAndWindowData();
                },
                oncancel: function(){
                }
            });

        },

        popupwindowGraphPreview: function(editor, values, onsubmit_callback){

            pw = editor.windowManager.open( {
                title: graphcommons.language.graphpreview,
                body: [ {
                    type    : 'container',
                    name    : 'container',
                    html    : '<iframe src="https://graphcommons.com/graphs/'+gcwindowData.gcId+'/embed" frameborder="0" style="overflow:hidden;width:600px;min-width:600px;border:1px solid #DDDDDD;;height:400px;min-height:400px;" width="600" height="400" allowfullscreen></iframe>',
                } ],
                onsubmit: function( e ) {
                    var self = this;
                    wp.mce.graphcommons.insertContent(editor);
                },
                onclose: function(){
                    wp.mce.graphcommons.clearKeywordAndWindowData();
                },
                oncancel: function(){
                }
            });

        },

        insertContent: function(editor) {
            editor.insertContent( 'https://graphcommons.com/' + selectedObjectType + 's/' + gcwindowData.gcId );
            wp.mce.graphcommons.closeAllWindows();
        },

        clearKeywordAndWindowData: function() {
            keyword = '';
            gcwindowData = {};
        },

        closeAllWindows: function(){
            for (var i = tinyMCE.activeEditor.windowManager.windows.length - 1; i >= 0; i--) {
                tinyMCE.activeEditor.windowManager.windows[i].close();
            }
        }

    }; // wp.mce.graphcommons -------------------------------------------------------

    wp.mce.views.register( shortcode_string, wp.mce.graphcommons );


    /* trigger for the preview window */
    $(document).on('click', '.tt-suggestion', function(e){
        e.preventDefault();
        gcwindowData = $(this).data();
        if ( 'node' === selectedObjectType ) {
            wp.mce.graphcommons.popupwindowNodePreview( tinyMCE.activeEditor );
        } else if ( 'graph' === selectedObjectType ) {
            wp.mce.graphcommons.popupwindowGraphPreview( tinyMCE.activeEditor );
        }
    } );

    /* user has "finished typing"  */
    function doneTyping() {

        if ( keyword.trim() === '' || keyword.length < 3 || working === true ) {
            return;
        }

        if( typeof cache[selectedObjectType][keyword] !== 'undefined' ) {
            draw( cache[selectedObjectType][keyword] );
            // console.log('> got ', keyword, ' from cache!');
            return;
        }

        $('#gc_content').html('<img src="'+graphcommons.plugin_url+'/images/spinner.gif">');

        $.ajax({
            type        : "post",
            dataType    : "json",
            url         : ajaxurl,
            data        :
                {
                    action: 'get_from_api',
                    keyword: keyword,
                    hub: selectedHubId,
                    type: selectedObjectType
                },
            beforeSend: function(){
                working = true;
            },
            success: function(response) {

                var noresults = false;

                if ( 'node' === selectedObjectType ) {

                    if ( response.nodes.length > 0 ) {
                        draw( response.nodes );
                        cache[selectedObjectType][keyword] = response.nodes;
                    } else {
                        noresults = true;
                    }

                } else if ( 'graph' === selectedObjectType ) {

                    if ( response.length > 0 ) {
                        draw( response );
                        cache[selectedObjectType][keyword] = response;
                    } else {
                        noresults = true;
                    }

                }

                working = false;

                if ( noresults ) {
                    $('#gc_content').html( graphcommons.language.noresultsfound + ' <strong>' + keyword + '</strong>.');
                }

            }
        });
        // console.log( 'finished typing' );
    }

    /* Draw the table */
    function draw( nodes ) {

        var html = '<div class="gc_results">';

        for (var i = 0; i < nodes.length; i++) {

            var node = nodes[i];

            if ( 'node' === selectedObjectType ) {

                var graphname = node.graphs.length > 0 && node.graphs[0].name !== null ? node.graphs[0].name : "Untitled Graph";

                html += '<div class="tt-suggestion" data-gc-id="'+node.id+'" data-gc-name="'+node.name+'" data-gc-node-type="'+node.nodetype.name+'"><p><span class="type-icon" style="background-color: '+node.nodetype.color+'">' + node.nodetype.name.charAt(0) + '</span>';
                html += '<span class="name gc_toe" title="'+node.name+'">' + highlightKeyword( node.name ) + '</span>';
                html += '<span class="type gc_toe">' + node.nodetype.name + ' in</span>';
                html += '<span class="graph gc_toe">'+ graphname + '</span></p></div>';

            } else if ( 'graph' === selectedObjectType ) {

                // some api data has missing ids!
                if ( typeof ( node.id ) !== 'undefined' ) {
                    html += '<div class="tt-suggestion" data-gc-id="'+node.id+'" data-gc-name="'+node.name+'"><p><span class="graph-icon"></span>';
                    html += '<span class="graphname gc_toe" title="'+node.name+'">' + highlightKeyword( node.name ) + '</span></p></div>';
                } else {
                    console.log("gc: missing id error ", node);
                }


            }

        }

        html += '</div>';

        $('#gc_content').html( html );

        working = false;
    }

    /* utils */
    function highlightKeyword( text ) {
        var searchTextRegExp = new RegExp(keyword , "i"); //  case insensitive regexp
        return text.replace(searchTextRegExp , '<span class="tt-highlight">$&</span>');
    }

    function clearTyperTimeout() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    }

    function resetCache() {
        cache = {
            node: {},
            graph: {}
        };
    }

}); // jQuery ends