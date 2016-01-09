jQuery(function ($) {
    
    'use strict';

    var
    cache                   = {},
    working                 = false,
    selectedHubId           = '',
    doneTypingInterval      = 500,
    keyword,
    typingTimer,
    selectedNode,
    gcwindow,
    gcwindowData,
    textbox;

    /* GC TinyMCE Plugin */
    tinymce.PluginManager.add('graphcommons', function(editor, url) {        

        $(document).on('click', '#insert-graphcommons-node', function(e){
            e.preventDefault();
            wp.mce.graphcommons.popupwindow(editor);                        
        });

    /*
        // contentEditable bug copying the last class used! remove it when enter key is pressed!
        tinyMCE.editors.content.on('keyup',function(e){
            if ( 13 === e.keyCode ) {
               $(tinyMCE.editors.content.selection.getNode()).closest('p').removeAttr('class');
            }
        });
    */

    }); // tinymce plugin ******************************************************************************


    var media = wp.media, shortcode_string = 'graphcommons';
    wp.mce = wp.mce || {};
    wp.mce.graphcommons = {
        shortcode_data: {},
        template: media.template( 'editor-graphcommons' ),
        getContent: function() {
            var options = this.shortcode.attrs.named;
            options['innercontent'] = this.shortcode.content;
            console.log("options", options);
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
            console.log("editting", data, update);
            var shortcode_data = wp.shortcode.next(shortcode_string, data);
            var values = shortcode_data.shortcode.attrs.named;
            values['innercontent'] = shortcode_data.shortcode.content;
            wp.mce.graphcommons.popupwindow(tinyMCE.activeEditor, values);
        },
        // this is called from our tinymce plugin, also can call from our "edit" function above
        // wp.mce.graphcommons.popupwindow(tinyMCE.activeEditor, "bird");
        popupwindow: function(editor, values, onsubmit_callback){
            values = values || [];
            if(typeof onsubmit_callback != 'function'){
                onsubmit_callback = function( e ) {
                    // Insert content when the window form is submitted (this also replaces during edit, handy!)
                    var s = '[' + shortcode_string;
                    for(var i in e.data){
                        if(e.data.hasOwnProperty(i) && i != 'innercontent'){
                            s += ' ' + i + '="' + e.data[i] + '"';
                        }
                    }
                    s += ']';
                    if(typeof e.data.innercontent != 'undefined'){
                        s += e.data.innercontent;
                        s += '[/' + shortcode_string + ']';
                    }
                    editor.insertContent( s );
                    console.log("onsubmit from popupwindow");
                };
            }
            editor.windowManager.open( {
                title: graphcommons.language.addgcnodecard,
                width: 800,
                height: 580,
                buttons: [],
                onPostRender: function(e){
                    // console.log(e.data.title);
                    var self = this;
                    textbox = $('#'+self._id).find('input');                    

                    $(textbox).attr('placeholder', graphcommons.language.searchkeyword);
                    $(textbox).attr('type', 'search');
                    $(textbox).css('padding-left', '10px');
                    
                    $(textbox).on('keyup', function(){
                        keyword = $(this).val().trim();
                        // console.log("typing", keyword);
                        clearTyperTimeout();
                    });

                    $(document).on('click', '.tt-suggestion', function(e){
                        e.preventDefault();
                        gcwindowData = $(this).data();
                        // editor.execCommand('open_preview');
                        // self.close();
                        // console.log("data",data);
                        wp.mce.graphcommons.popupwindowPreview(tinyMCE.activeEditor, "heyya");
                    } );            
                    console.log("values-->", values);
                },

                body:[
                        {
                            type    : 'textbox',
                            name    : 'search',
                            value  : values['name']
                        },

                        {
                            type: 'listbox', 
                            name: 'gc_hubs', 
                            value: values['hub'],
                            values: graphcommons.hubs,
                            onselect: function(e) {
                                selectedHubId = this.value();
                                cache = {};
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


                onsubmit: onsubmit_callback
            } );
        },
        popupwindowPreview: function(editor, values, onsubmit_callback){

            values = values || [];
            if(typeof onsubmit_callback != 'function'){
                onsubmit_callback = function( e ) {
                    // Insert content when the window form is submitted (this also replaces during edit, handy!)
                    var s = '[' + shortcode_string;
                    for(var i in e.data){
                        if(e.data.hasOwnProperty(i) && i != 'innercontent'){
                            s += ' ' + i + '="' + e.data[i] + '"';
                        }
                    }
                    s += ']';
                    if(typeof e.data.innercontent != 'undefined'){
                        s += e.data.innercontent;
                        s += '[/' + shortcode_string + ']';
                    }
                    editor.insertContent( s );
                    // console.log("onsubmit from popupwindowPreview");
                };
            }

            editor.windowManager.open( {
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
                    console.log("added");
                    var new_shortcode_str = '[graphcommons embed="node" id="'+gcwindowData.gcId+'" name="'+gcwindowData.gcName+'" type="'+gcwindowData.gcNodeType+'"][/graphcommons]';

                    editor.insertContent( new_shortcode_str );

                    wp.mce.graphcommons.shortcode_data = wp.shortcode.next('graphcommons', new_shortcode_str);

                    tinyMCE.activeEditor.windowManager.close();
                    tinyMCE.activeEditor.windowManager.windows[0].close();                    
                },
                onclose: function(){
                    keyword = '';
                    gcwindowData = {};
                },
                oncancel: function(){
                    // console.log("canceled");
                }
            });

        }
    };
    wp.mce.views.register( shortcode_string, wp.mce.graphcommons );




    /* user has "finished typing"  */
    function doneTyping() {

        if ( keyword.trim() === '' || keyword.length < 3 || working === true ) {
            return;
        }

        if( typeof cache[keyword] !== 'undefined' ) {
            draw( cache[keyword] );
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
                    action: "get_nodes_json",
                    keyword: keyword,
                    hub: selectedHubId
                },
            beforeSend: function(){
                working = true;
            },
            success: function(response) {
                
                if ( response.nodes.length > 0 ) {
                    draw( response.nodes );
                    cache[keyword] = response.nodes;
                } else {
                    $('#gc_content').html( graphcommons.language.noresultsfound + ' <strong>' + keyword + '</strong>.');
                }
                // console.log('> got ', response.nodes.length, ' results from the api');
                working = false;
            }
        });
        // console.log( 'finished typing' );
    }

    /* Draw the table */
    function draw( nodes ) {

        var html = '<div class="gc_results">';

        for (var i = 0; i < nodes.length; i++) {

            var node = nodes[i];
            var graphname = node.graphs.length > 0 && node.graphs[0].name !== null ? node.graphs[0].name : "Untitled Graph";

            html += '<div class="tt-suggestion" data-gc-id="'+node.id+'" data-gc-name="'+node.name+'" data-gc-node-type="'+node.nodetype.name+'"><p><span class="type-icon" style="background-color: '+node.nodetype.color+'">' + node.nodetype.name.charAt(0) + '</span>';
            html += '<span class="name" title="'+node.name+'">' + highlightKeyword( node.name ) + '</span>';
            html += '<span class="type">' + node.nodetype.name + ' in</span>';
            html += '<span class="graph">'+ graphname + '</span></p></div>';
        }

        html += '</div>';

        $('#gc_content').html( html );        

        working = false;
    }

    function highlightKeyword( text ) {
        var searchTextRegExp = new RegExp(keyword , "i"); //  case insensitive regexp
        return text.replace(searchTextRegExp , '<span class="tt-highlight">$&</span>');
    }

    function clearTyperTimeout(){
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    }


}); // jQuery ends