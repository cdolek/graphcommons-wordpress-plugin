jQuery(function ($) {

    function test() {
        $.ajax({
         type : "post",
         dataType : "json",
         url : ajaxurl,
         data : {
            action: "get_nodes_json",
            keyword: "arikan"

         },
         success: function(response) {
            console.log( response );
         }
      });
    }

    window.testajax = test;

    $('#insert-graphcommons-media').click(open_media_window);






















}); // jQuery ends



function open_media_window() {
    var window = wp.media({
            title: 'Insert a media',
            library: {type: 'image'},
            multiple: false,
            button: {text: 'Insert'}
    });
}