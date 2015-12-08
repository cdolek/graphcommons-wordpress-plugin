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
    
    function __construct() 
    {   
        // Set up default vars
        $this->plugin_path          = plugin_dir_path( __FILE__ );
        $this->plugin_url           = plugin_dir_url( __FILE__ );
        $this->api_key              = esc_attr( get_option( 'graphcommons-api_key' ) );

        // Set up activation hooks
        register_activation_hook( __FILE__, array(&$this,   'activate') );
        register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );

        // Set up l10n        
        load_plugin_textdomain( 'graphcommons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );                

        // actions */
        add_action( 'pre_get_posts', array(&$this, 'init') );
        add_action( 'admin_menu', array(&$this, 'gc_create_admin_menu' ) );
        add_action( 'admin_init', array(&$this, 'gc_admin_init' ) );
        add_action( 'admin_notices', array(&$this, 'gc_admin_notice' ) );
        add_action( 'init',  array(&$this, 'gc_custom_rewrite_tag'), 10, 0 );

        // shortcodes
        add_shortcode('graphcommons', array(&$this, 'graphcommons_shortcode') );


    } // construct ends

    function init() {

        $gc_action          = get_query_var( 'gc_action' );
        $gc_keyword         = get_query_var( 'gc_keyword' );

        if( isset($gc_action) && !empty($gc_action) ) {            

            switch ($gc_action) {
                
                case 'tag':
                    // $this->grabInstagramPhotos('https://api.instagram.com/v1/tags/'.$gc_keyword.'/media/recent?api_key='.$this->api_key.'&count=100');
                    break;

                /**
                *
                *    DEBUG
                                
                */                
               
                case 'debug':  
                    
                    var_dump( $gc_action );
                    var_dump( $gc_keyword );                    

                    // $this->log("debug");
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

    function graphcommons_shortcode() {
        $atts = shortcode_atts( array(
            'foo' => 'no foo',
            'baz' => 'default baz'
        ), $atts, 'bartag' );

        return "foo = {$atts['foo']}";        
    }

    function gc_create_admin_menu() {
        add_options_page(
            'Graph Commons',
            'Graph Commons',
            'manage_options',
            'graphcommons',
            array(&$this, 'options_page')
        );

        add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' );
    }

    function gc_admin_init() {
        add_settings_section( 'section-one', 'API Credentials', array(&$this, 'section_one_callback'), 'graphcommons' );
        register_setting( 'graphcommons-settings-group', 'gc-api_key' );                    
        add_settings_field( 'gc-api_key', 'api_key', array(&$this, 'field_api_key_callback'), 'graphcommons', 'section-one' );
    }

    function section_one_callback() {
        $api_key = esc_attr( get_option( 'gc-api_key' ) );
        if ( empty($api_key) ) {
            echo '<a href="https://graphcommons.com/me/edit" target="_blank">'.__('Create or get your Graph Commons API key', 'graphcommons').'</a>';    
        }        
    }

    function field_api_key_callback() {
        $api_key = esc_attr( get_option( 'gc-api_key' ) );
        echo "<input type='text' name='gc-api_key' value='$api_key' class='regular-text'/>";
    }


    function options_page(){
    ?>

    <div class="wrap">        
        <h2>Graph Commons Settings</h2>
        <form action="options.php" method="POST">
            <?php settings_fields( 'graphcommons-settings-group' ); ?>
            <?php do_settings_sections( 'graphcommons' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>

    <?php
    }



    function gc_admin_notice() {

        $api_key = esc_attr( get_option( 'gc-api_key' ) );

        if ( ! empty($api_key) ) {
            return;
        }

        ?>

        <div class="error">
            <p><?php 

            printf(                
            __( 'Please visit <a href="%1$s">Graph Commons Plugin Settings page</a> and specify your API key', 'graphcommons' ),
                esc_url( get_admin_url(null, 'options-general.php?page=graphcommons') )
            );

            ?></p>
        </div>
        <?php
    
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

    function gc_add_rewrite_rules() {
        add_rewrite_rule('^graphcommons_api/([^/]*)/([^/]*)/?','index.php?gc_action=$matches[1]&gc_keyword=$matches[2]','top');        
    }

    function gc_custom_rewrite_tag() {
        add_rewrite_tag('%gc_action%', '([^&]+)');
        add_rewrite_tag('%gc_keyword%', '([^&]+)');
    }

    function activate() {
        global $wp_rewrite;
        $this->gc_add_rewrite_rules();
        $wp_rewrite->flush_rules();
    }

    function deactivate(){        
        flush_rewrite_rules();
    }

} // class end

new GraphCommons();

?>