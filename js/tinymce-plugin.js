jQuery(function ($) {
    
    'use strict';

    var
    cache = {},
    keyword,
    typingTimer,
    selectedNode,
    gcwindow,
    working = false,
    doneTypingInterval  = 500;

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

    /* TinyMCE Plugin */
    tinymce.PluginManager.add('graphcommons', function(editor, url) {
       
        var sh_tag = 'graphcommons';

        //helper functions 
        function getAttr(s, n) {
            n = new RegExp(n + '=\"([^\"]+)\"', 'g').exec(s);
            return n ?  window.decodeURIComponent(n[1]) : '';
        }

        function html( cls, data ,con) {
            var placeholder = url + '../../images/gc_nodecard.png';
            var gc_id = getAttr(data, 'id');
            var gc_node_name = getAttr(data, 'name');
            var gc_node_type = getAttr(data, 'type');

            data = window.encodeURIComponent( data );
            return '<p class="gc_placeholder"><span class="gc_placeholder_title mceNonEditable" contentEditable="false">'+gc_node_name+'</span><span class="gc_placeholder_type">'+gc_node_type+'</span><img src="' + placeholder + '" class="mceItem ' + cls + '" ' + 'data-sh-attr="' + data + '" data-mce-resize="false" data-mce-placeholder="1"/></p>';
            // return '<img src="' + placeholder + '" title="'+gc_node_name+'" class="mceItem ' + cls + '" ' + 'data-sh-attr="' + data + '" data-mce-resize="false" data-mce-placeholder="1" />';
        }

        function replaceShortcodes( content ) {
/*
            return content.replace( /\[graphcommons([^\]]*)\]([^\]]*)\[\/graphcommons\]/g, function( all,attr,con) {
                return html( 'wp-graphcommons', attr , con);
            });
*/



            // var re = /(<div class="gc_placeholder"(?: \w+="[^"]+")*>([^<]*)<\/div>)/g;
            // // var str = 'this is a string <a href="#" class="link">link</a> <a class="link">link2</a>';

            // var links = [];
            // for (var i in content.match(re)) {
            //     links.push(content.match(re)[i]);
            // }

            // var embedded_strings = [];
            // for (var s in links) {
            //     embedded_strings.push(links[s].replace(re, "$2"));
            // }

            // console.log('links', links);
            // console.log('embedded_strings', embedded_strings);

            /* content.replace(/<div class="gc_placeholder"[^>]*>.*?<\/div>/g,"") */

            return content.replace( /\[graphcommons([^\]]*)\]([^\]]*)\[\/graphcommons\]/g, function( all,attr,con) {                
                return html( 'wp-graphcommons', attr , con);
            });

        }

        function restoreShortcodes( content ) {
            
            content = content.replace(/(\r\n|\n|\r)/gm,"");            

//            return content.replace( /(?:<p(?: [^>]+)?>)*(<img [^>]+>)(?:<\/p>)*/g, function( match, image ) {

            return content.replace( /<p class="gc_placeholder">(.*?>)\<\/p>/g, function( match, image ) {

                var data = getAttr( image, 'data-sh-attr' );
                var con = getAttr( image, 'data-sh-content' );
                
                if ( data ) {
                    console.log("restoreShortcodes__________________________________________________");                    
                    return '<p>[' + sh_tag + data + ']' + con + '[/'+sh_tag+']</p>';
                }
            
                console.log("/////////////////////////////////////////////////////////");
                console.log("restoreShortcodes match:", match );

                // editor.setContent( match.replace( /<div class="gc_placeholder">(.*?>)\<\/div>/g, '') );
                
                return  match;
            
            });

        }

        gcwindow = {
                    title: 'Add Graph Commons Node Card',
                    // url: graphcommons.plugin_url + '/mydialog.html',
                    // html: '<div id="gc_content"></div>',
                    width: 800,
                    height: 580,                    
                    body: [
                        {
                            type    : 'textbox', 
                            name    : 'search', 
                        },
                        {
                            type    : 'container',
                            name    : 'container',
                            html    : '<div id="gc_content" class="gc_content">Type something, results will be displayed here.</div>',
                            // html    : '<div id="gc_content" class="gc_content"><div class="tt-suggestion"><p title="Tunisia as Country in Penal Systems Network" style="white-space: normal;"><span class="type-icon" style="background-color: #4d504e">C</span><span class="name">T<span class="tt-highlight">uni</span>sia</span><span class="type">Country in</span><span class="graph">Penal Systems Network</span></p></div></div>'
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
                            console.log("typing");
                        });

                        $(document).one('click', '.tt-suggestion', function(e){
                            e.preventDefault();
                            var data = $(this).data();
                            editor.execCommand('mceInsertContent', false, '[graphcommons embed="node" id="'+data.gcId+'" name="'+data.gcName+'" type="'+data.gcNodeType+'"][/graphcommons]');
                            self.close();
                            console.log("data",data);
                        } );            

                    }
                };

        editor.addCommand('open_window', function() {
            editor.windowManager.open(gcwindow);
        });
/*        
        editor.addButton('graphcommons', {
            text: 'Add Graph Commons Node Card',
            icon: false,
            cmd: 'open_window'
        });
*/
        // replace from shortcode to an image placeholder
        editor.on('BeforeSetcontent', function(event){ 
            event.content = replaceShortcodes( event.content );
        });

        // replace from image placeholder to shortcode
        editor.on('GetContent', function(event){
            event.content = restoreShortcodes(event.content);
        });

        $(document).on('click', '#insert-graphcommons-node', function(e){
            e.preventDefault();
            editor.execCommand('open_window');
        });

        // contentEditable bug copying the last class used! remove it when enter key is pressed!
        tinyMCE.editors.content.on('keyup',function(e){
            if ( 13 === e.keyCode ) {
               $(tinyMCE.editors.content.selection.getNode()).closest('p').removeAttr('class');
            }
        });

    }); // tinymce plugin

    /* user has "finished typing"  */
    function doneTyping () {

        if ( keyword.trim() === '' || keyword.length < 3 || working === true ) {
            return;
        }

        if( typeof cache[keyword] !== 'undefined' ) {
            drawToDom( cache[keyword] );
            console.log('> got ', keyword, ' from cache!');        
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
                    keyword: keyword
                },
            beforeSend: function(){
                working = true;
            },
            success: function(response) {
                if ( response.nodes.length > 0 ) {
                    drawToDom( response.nodes );
                    cache[keyword] = response.nodes;
                } else {
                    $('#gc_content').html('No results found for <strong>' + keyword + '</strong>.');
                }
                console.log('> got ', response.nodes.length, ' results from the api');
                console.log( response.nodes );
                window.nodes = response.nodes;
            }
        });

        console.log( 'finished typing' );
    }


    function drawToDom( nodes ) {

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

}); // jQuery ends