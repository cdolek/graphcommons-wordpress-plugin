jQuery(function ($) {

    function node_search() {
        $.ajax({
            url: "https://graphcommons.com/api/v1/nodes/search",
            dataType: 'json',
            data: { 
                query: 'cenk',
                limit: 1
            },
            type: "GET",
            beforeSend: function(xhr){xhr.setRequestHeader('Authentication', graphcommons.api_key);},
            success: function() { alert('Success!' + authHeader); }
        });        
    }

//    node_search();



    function smm() {

        $.ajax({
            url: "https://graphcommons.com/api/v1/nodes/search",
         
            // The name of the callback parameter, as specified by the YQL service
            jsonp: "callback",
         
            // Tell jQuery we're expecting JSONP
            dataType: "jsonp",
         
            // Tell YQL what we want and that we want JSON
            data: {
                query: 'cenk',
                limit: 1
            },
            beforeSend: function(xhr){
                xhr.setRequestHeader('Authentication', graphcommons.api_key);
                xhr.setRequestHeader('Cengo', 'heyhey');
                xhr.setRequestHeader('User-Agent', 'my user agent');
            }, 
            // Work with the response
            success: function( response ) {
                console.log( response ); // server response
            }
        });


    }

    window.smm = smm;



    function staging() {

        $.ajax({
            url: "http://staging.graphcommons.com/api/v1/nodes/search",
            jsonp: "callback",
            dataType: "jsonp",
            data: {
                query: 'arikan',
                limit: 1
            },
            beforeSend: function(xhr){
                xhr.setRequestHeader('Authentication', 'sk_15smUutbdi8esM-NlBHFvg');
            }, 
            success: function( response ) {
                console.log( response );
            }
        });


    }

    window.staging = staging;



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


}); // jQuery ends
