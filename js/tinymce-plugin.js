jQuery(function ($) {
    
    'use strict';

    var
    cache = {},
    keyword,
    typingTimer,
    doneTypingInterval  = 1000;

    /**
    *
    * App View Model
    *
    */

    function AppViewModel() {

        var self = this;

        self.people = ko.observableArray([
            { firstName: 'Bert', lastName: 'Bertington' },
            { firstName: 'Charles', lastName: 'Charlesforth' },
            { firstName: 'Denise', lastName: 'Dentiste' }
        ]);

    }


    var gcmodel = new AppViewModel();
    ko.applyBindings( gcmodel );

    window.gcmodel = gcmodel;


    tinymce.PluginManager.add('graphcommons', function(editor, url) {


        editor.addButton('graphcommons', {
            text: 'Add GraphCommons Node Card',
            icon: false,
            onclick: function() {

                editor.windowManager.open({
                    title: 'Graph Commons - Find Node',
                    // url: graphcommons.plugin_url + '/mydialog.html',
                    // html: '<div id="gc_content"></div>',
                    width: 600,
                    height: 400,                    
                    body: [
                        {
                            type    : 'textbox', 
                            name    : 'search', 
                        },
                        {
                            type    : 'container',
                            name    : 'container',
                            // html    : '<div id="gc_content" class="gc_content">Type something...</div>',
                            html    : '<div id="gc_content" class="gc_content"><div class="tt-suggestion"><p title="Tunisia as Country in Penal Systems Network" style="white-space: normal;"><span class="type-icon" style="background-color: #4d504e">C</span><span class="name">T<span class="tt-highlight">uni</span>sia</span><span class="type">Country in</span><span class="graph">Penal Systems Network</span></p></div></div>'
                        }
                    ],

                    buttons: [],

                    onPostRender: function(e){
                        // console.log(e.data.title);
                        var self = this;
                        var textbox = $('#'+self._id).find('input');                    

                        $(textbox).attr('placeholder', 'Search keyword');
                        $(textbox).attr('type', 'search');
                        $(textbox).css('padding-left', '10px');
                        
                        $(textbox).on('keyup', function(){
                            keyword = $(this).val().trim();
                            clearTimeout(typingTimer);
                            typingTimer = setTimeout(doneTyping, doneTypingInterval);
                        });

                        window.maw = self;

                    }
                });

            }
        });

    });

    /* user has "finished typing"  */
    function doneTyping () {

        if ( keyword.trim() === '' || keyword.length < 3 ) {
            return;
        }

        if( typeof cache[keyword] !== 'undefined' ) {
            drawToDom( cache[keyword] );
            console.log('> got ', keyword, ' from cache!');
            return;
        }

        $.ajax({
            type        : "post",
            dataType    : "json",
            url         : ajaxurl,
            data        : 
                {
                    action: "get_nodes_json",
                    keyword: keyword
                },
            success: function(response) {
                if ( response.nodes.length > 0 ) {
                    drawToDom( response.nodes );
                    cache[keyword] = response.nodes;
                } else {
                    $('#gc_content').html('No results found for <strong>' + keyword + '</strong>.');
                }
                console.log('> got ', response.nodes.length, ' results from the api');
            }
        });



        /*
        var html = '<div id="most-recent-results" class="query-results" tabindex="0"><div class="query-notice" id="query-notice-message"><em class="query-notice-default">No search term specified. Showing recent items.</em><em class="query-notice-hint screen-reader-text">Search or use up and down arrow keys to select an item.</em></div><ul><li class="alternate"><input type="hidden" class="item-permalink" value="http://local.blank.dev/instagram_post/1135110432720502733_1922582382/"><span class="item-title">1135110432720502733_1922582382</span><span class="item-info">Instagram Post</span></li><li><input type="hidden" class="item-permalink" value="http://local.blank.dev/sample-page/"><span class="item-title">Sample Page</span><span class="item-info">Page</span></li><li class="alternate"><input type="hidden" class="item-permalink" value="http://local.blank.dev/hello-world/"><span class="item-title">Hello world!</span><span class="item-info">2015/04/17</span></li></ul><div class="river-waiting"><span class="spinner"></span></div></div>';


        $('#gc_content').html( html );
        */










        console.log( 'finished typing' );
    }


    function drawToDom( nodes ) {
        var html = '<div class="gc_results">';
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            // html += '<div class="gc_row"><div class="gc_cell">' + node.name + '</div><div class="gc_cell">' + node.nodetype.name + '</div></div>';
            html += '<div class="tt-suggestion"><p><span class="type-icon" style="background-color: #4d504e">'+node.nodetype.name.charAt(0)+'</span>';
            html += '<span class="name" title="'+node.name+'">'+highlightKeyword( node.name )+'</span>';
            html += '<span class="type">'+node.nodetype.name+' in</span>';
            html += '<span class="graph">Penal Systems Network</span></p></div>';
        }
        html += '</div>';
        $('#gc_content').html( html );        
    }

    function highlightKeyword( text ) {
        var searchTextRegExp = new RegExp(keyword , "i"); //  case insensitive regexp
        return text.replace(searchTextRegExp , '<span class="tt-highlight">$&</span>');
    }



}); // jQuery ends