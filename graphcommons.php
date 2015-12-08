<?php
/*
Plugin Name: Graph Commons
Plugin URI: http://binfil.com
Description: This plugin helps you find and insert node cards into your blog
Version: 1.0.0
Author: Cenk Dolek
Text Domain: graphcommons
Domain Path: /languages
Author URI: http://binfil.com
Author Email: cdolek@gmail.com
License: GPL2
*/

class GraphCommons {

    private $plugin_path;
    private $plugin_url;
    private $api_key;
    private $client_secret;
    private $object;
    private $object_id;
    private $aspect;
    private $verify_token;
    private $callback_url;
    private $logging;
    
    function __construct() 
    {   
        // Set up default vars
        $this->plugin_path          = plugin_dir_path( __FILE__ );
        $this->plugin_url           = plugin_dir_url( __FILE__ );

        $this->api_key              = esc_attr( get_option( 'graphcommons-api_key' ) );
        $this->logging              = false;

        // Set up activation hooks
        // register_activation_hook( __FILE__, array(&$this, 'activate') );
        // register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );
        
        // Set up l10n        
        load_plugin_textdomain( 'graphcommons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );                

        /* actions */
        add_action( 'wp_loaded', array(&$this, 'gc_flush_rules' ) );
        add_action( 'pre_get_posts', array(&$this, 'init') );
        add_action( 'admin_menu', array(&$this, 'gc_create_admin_menu' ) );
        add_action( 'admin_init', array(&$this, 'gc_admin_init' ) );
        add_action( 'admin_notices', array(&$this, 'gc_admin_notice' ) );

        /* filters */
        add_filter( 'rewrite_rules_array', array(&$this, 'gc_insert_rewrite_rules' ) );
        add_filter( 'query_vars', array(&$this, 'gc_insert_query_vars' ) );

       /* shortcodes */
        // add_shortcode('permalink', array(&$this, 'custom_permalink') );


    } // construct ends


    function init() {

        $gc_action          = get_query_var( 'myroute' );
        $instagram_argument = get_query_var( 'myargument' );

        if( isset($gc_action) && !empty($gc_action) ) {            

            switch ($gc_action) {
                
                case 'tag':
                    $this->grabInstagramPhotos('https://api.instagram.com/v1/tags/'.$instagram_argument.'/media/recent?api_key='.$this->api_key.'&count=100');
                    break;

                /**
                *
                *    DEBUG
                                
                */                
               
                case 'debug':  
                    
                    var_dump( $instagram_argument );
                    var_dump( $this->callback_url );

                    $this->log("debug");
                    break;

                /**                
                *
                *    DEFAULT
                                
                */
                
                default:                
                    break;
            } // switch

            exit(0);

        } //if

    }

    function gc_create_admin_menu() {
        add_options_page(
            'Graph Commons',
            'Graph Commons',
            'manage_options',
            'wp-instagram-tag-to-posts-plugin',
            array(&$this, 'options_page')
        );
    }

    function gc_admin_init() {
        add_settings_section( 'section-one', 'API Credentials', array(&$this, 'section_one_callback'), 'wp-instagram-tag-to-posts-plugin' );
        add_settings_section( 'section-two', 'Preferences', array(&$this, 'section_two_callback'), 'wp-instagram-tag-to-posts-plugin' );

        register_setting( 'wp-instagram-tag-to-posts-settings-group', 'wpittp-api_key' );            
        register_setting( 'wp-instagram-tag-to-posts-settings-group', 'wpittp-client_secret' );            
        register_setting( 'wp-instagram-tag-to-posts-settings-group', 'wpittp-verify_token' );            
        register_setting( 'wp-instagram-tag-to-posts-settings-group', 'wpittp-post_type' );            
        register_setting( 'wp-instagram-tag-to-posts-settings-group', 'wpittp-post_status' );            
        
        add_settings_field( 'wpittp-api_key', 'api_key', array(&$this, 'field_api_key_callback'), 'wp-instagram-tag-to-posts-plugin', 'section-one' );
        add_settings_field( 'wpittp-client_secret', 'client_secret', array(&$this, 'field_client_secret_callback'), 'wp-instagram-tag-to-posts-plugin', 'section-one' );
        add_settings_field( 'wpittp-verify_token', 'verify_token', array(&$this, 'field_verify_token_callback'), 'wp-instagram-tag-to-posts-plugin', 'section-one' );
        
        add_settings_field( 'wpittp-post_type', 'Post Type', array(&$this, 'field_post_types_callback'), 'wp-instagram-tag-to-posts-plugin', 'section-two' );
        add_settings_field( 'wpittp-post_status', 'Post Status', array(&$this, 'field_post_statuses_callback'), 'wp-instagram-tag-to-posts-plugin', 'section-two' );

    }

    function section_one_callback() {
        echo '<a href="https://www.instagram.com/developer/" target="_blank">Refer to Instagram API</a>';
    }

    function section_two_callback() {
        echo 'How do you want the posts recorded?';
    }

    function field_api_key_callback() {
        $setting = esc_attr( get_option( 'wpittp-api_key' ) );
        echo "<input type='text' name='wpittp-api_key' value='$setting' class='regular-text'/>";
    }

    function field_verify_token_callback() {
        $setting = esc_attr( get_option( 'wpittp-verify_token' ) );
        echo "<input type='text' name='wpittp-verify_token' value='$setting' />";
    }

    function field_client_secret_callback() {
        $setting = esc_attr( get_option( 'wpittp-client_secret' ) );
        echo "<input type='text' name='wpittp-client_secret' value='$setting' class='regular-text'/>";
    }

    function field_post_types_callback() {
        
        $saved_post_type = esc_attr( get_option( 'wpittp-post_type' ) );        

        if ( $saved_post_type === '' ) {
            $saved_post_type = 'instagram-post';
        }
        
        $args = array(
           'public'   => true,
           // '_builtin' => false
        );

        $output = 'names'; // names or objects, note names is the default
        $operator = 'and'; // 'and' or 'or'

        $post_types = get_post_types( $args, $output, $operator ); 

        echo '<select name="wpittp-post_type">';

        foreach ( $post_types  as $post_type ) {
           $isSelected = $post_type == $saved_post_type ? ' SELECTED' : '';
           echo '<option' . $isSelected . '>' . $post_type . '</option>';
        }

        echo '</select>';

    }

    function field_post_statuses_callback() {
        
        $saved_post_status = esc_attr( get_option( 'wpittp-post_status' ) );        

        if ( $saved_post_status === '' ) {
            $saved_post_status = 'draft';
        }

        $post_statuses = get_post_statuses(); 

        echo '<select name="wpittp-post_status">';

        foreach ( $post_statuses as $key=>$post_status ) {
           $isSelected = $saved_post_status == $key ? ' SELECTED' : '';
           echo '<option' . $isSelected . ' value="'.$key.'">' . $post_status . '</option>';
        }

        echo '</select>';

    }



    function options_page(){
    ?>

    <div class="wrap">        
        <h2>Graph Commons Settings</h2>
        <form action="options.php" method="POST">
            <?php settings_fields( 'wp-instagram-tag-to-posts-settings-group' ); ?>
            <?php do_settings_sections( 'wp-instagram-tag-to-posts-plugin' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>

    <?php
    }



    function gc_admin_notice() {


        $api_key      = esc_attr( get_option( 'wpittp-api_key' ) );
        $client_secret  = esc_attr( get_option( 'wpittp-client_secret' ) );
        $verify_token   = esc_attr( get_option( 'wpittp-verify_token' ) );

        $missingArr = array();

        if ( ! $api_key ) {
            $missingArr[] = 'api_key';
        }

        if ( ! $client_secret ) {
            $missingArr[] = 'client_secret';
        }

        if ( ! $verify_token ) {
            $missingArr[] = 'verify_token';
        }

        if ( sizeof($missingArr) == 0 ) {
            return;
        }


        ?>

        <div class="error">
            <p><?php 

            printf(                
            __( 'Please visit <a href="%1$s">Graph Commons Plugin Settings page</a> and specify the following field(s): <strong>%2$s</strong>.', 'graphcommons' ),
                esc_url( get_admin_url(null, 'options-general.php?page=wp-instagram-tag-to-posts-plugin') ),
                implode(', ', $missingArr)
            );

            ?></p>
        </div>
        <?php
    
    }


    function grabInstagramPhotos( $url = null ){
        
        $results = json_decode($this->get_url($url), true);
        $r = array();
        $i = 0;

        // if error
        if ( isset($results['meta']['code']) && 200 != $results['meta']['code'] ) {
            return;
        }

        foreach( $results['data'] as $item ){
            
            $r['image_url'] = $item['images']['standard_resolution']['url'];
            $r['image_url_lowres'] = $item['images']['low_resolution']['url'];
            $r['username'] = $item['user']['username'];                
            $r['avatar'] = $item['user']['profile_picture'];
            $r['caption'] = $item['caption']['text'];                
            $r['link'] = $item['link'];                          
            $r['id'] = $item['id'];                        
            
            if ( !$this->insertPost($r) ) {
                continue;
            };
            
            $i++;
        }

        $logmsg = mysql2date("F j, Y - g:i:s a ", time() ) .' (' . $i . ' / '. sizeof($results['data']) . ') items inserted.';
        $this->log_txt( 'scrape' , $logmsg );
        echo ( '<h2>' . $logmsg . '</h2>' );

        // recursive reading
        if ( isset($results['pagination']['next_url']) && $i == 0 && $this->loop < 3 ) {
            $this->loop++;
            $this->grabInstagramPhotos($results['pagination']['next_url']);
        }

    }

    // get url contents with curl
    function get_url($url, $method = null) {

        $ch = curl_init();

        if ( isset($method) && $method == 'delete' ) {
    
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CUSTOMREQUEST => 'DELETE'
            ));

        } else {

            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2
            ));

        }

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    // flush_rules() if our rules are not yet included
    function gc_flush_rules() {
        $rules = get_option( 'rewrite_rules' );

        if ( ! isset( $rules['api/(.*?)/(\w*)'] ) ) {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }

    }

    // Adding a new rule
    function gc_insert_rewrite_rules( $rules ) {
        $newrules = array();
        $newrules['api/(.*?)/(\w*)'] = 'index.php?myroute=$matches[1]&myargument=$matches[2]'; //&id=$matches[2] (.+)$
        return $newrules + $rules;
    }

    // Adding the vars so that WP recognizes it
    function gc_insert_query_vars( $vars ) {
        array_push($vars, 'myroute', 'myargument');
        return $vars;
    }
    //end of rewrite rules

    // log actions to files as string
    function log_txt( $activity, $myString ) {
        if ( !$this->logging ) { return; }        
        file_put_contents( plugin_dir_path( __FILE__ ) . '_' . $activity . '.log', $myString . "\r\n", FILE_APPEND);
    }

    // log actions to files
    function log( $activity ) {
        if ( !$this->logging ) { return; }
        $myString = file_get_contents('php://input');
        $ALL = mysql2date("F j, Y, g:i a - s", time() )." ".$myString."\r\n";
        file_put_contents( plugin_dir_path( __FILE__ ) . '_' . $activity . '.log', $ALL, FILE_APPEND);
    }


} // class ends

new GraphCommons();

?>