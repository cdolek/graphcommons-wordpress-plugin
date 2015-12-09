    var win;


jQuery(function ($) {
    
    'use strict';

    var
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

        self.a = ko.observable("1");

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
                            label   : 'Keyword'
                        },
                        {
                            type    : 'container',
                            name    : 'container',
                            // html   : '<div class="wrap"><input type="search" placeholder="Keyword" class="regular-text" autocomplete="off" id="gc_text_input"><div id="gc_content"></div></div>'
                            html    : '<div id="gc_content">Hello</div>',
                        }
                    ],

                    buttons: [],

                    onPostRender: function(e){
                        // console.log(e.data.title);
                        var self = this;
                        var textbox = $('#'+self._id).find('input');                    

                        $(textbox).attr('placeholder', 'Search keyword');
                        $(textbox).attr('type', 'search');
                        
                        $(textbox).on('keyup', function(){
                            keyword = $(this).val().trim();
                            clearTimeout(typingTimer);
                            typingTimer = setTimeout(doneTyping, doneTypingInterval);
                        });

                        window.maw = self;

                    }
                });




/*
                // Open window
                win = editor.windowManager.open({
                    width: 600,
                    height: 400,
                    title: 'Graph Commons',
                    body: [
                        {
                            type: 'textbox', 
                            name: 'title', 
                            label: 'Keyword',
                        },
                        {
                            type   : 'container',
                            name   : 'container',
                            html   : '<p>Hey</p><hr/><pre data-bind="text: ko.toJSON($data, null, 2)"></pre>'
                        }
                    ],
                    onsubmit: function(e) {
                        // Insert content when the window form is submitted
                        editor.insertContent('Title: ' + e.data.title);
                    }
                });
*/


                // jQuery(win.$el).find('input').attr('id')
            }
        });

    });

    /* user has "finished typing"  */
    function doneTyping () {

        if ( keyword.trim() === '' || keyword.length < 3 ) {
            console.log("keyword not ok");
            return;
        }

        $.ajax({
            type : "post",
            dataType : "json",
            url : ajaxurl,
            data : {
            action: "get_nodes_json",
            keyword: keyword
        },
            success: function(response) {
                console.log( response );
            }
        });



        var html = '<div id="most-recent-results" class="query-results" tabindex="0"><div class="query-notice" id="query-notice-message"><em class="query-notice-default">No search term specified. Showing recent items.</em><em class="query-notice-hint screen-reader-text">Search or use up and down arrow keys to select an item.</em></div><ul><li class="alternate"><input type="hidden" class="item-permalink" value="http://local.blank.dev/instagram_post/1135110432720502733_1922582382/"><span class="item-title">1135110432720502733_1922582382</span><span class="item-info">Instagram Post</span></li><li><input type="hidden" class="item-permalink" value="http://local.blank.dev/sample-page/"><span class="item-title">Sample Page</span><span class="item-info">Page</span></li><li class="alternate"><input type="hidden" class="item-permalink" value="http://local.blank.dev/hello-world/"><span class="item-title">Hello world!</span><span class="item-info">2015/04/17</span></li></ul><div class="river-waiting"><span class="spinner"></span></div></div>';


        $('#gc_content').html( html );










        console.log( 'finished typing' );
    }








}); // jQuery ends