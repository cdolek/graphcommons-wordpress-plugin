<?php
/*
Plugin Name: Graph Commons
Plugin URI:  https://github.com/cdolek/graphcommons-wordpress
Description: This plugin helps you find and insert node cards into your blog using Graph Commons API.
Version: 1.0.0
Author: Cenk Dolek
Text Domain: graphcommons
Domain Path: /lang
Author URI: http://cenkdolek.com
Author Email: cdolek@gmail.com
License: Released under the MIT License
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// GC Class
class GraphCommons {

    private $plugin_path;
    private $plugin_url;
    private $api_key;
    private $api_limit;
    private $action;
    private $keyword;
    
    function __construct() 
    {   
        // Set up default vars
        $this->plugin_path          = plugin_dir_path( __FILE__ );
        $this->plugin_url           = plugin_dir_url( __FILE__ );
        $this->api_key              = esc_attr( get_option( 'gc-api_key' ) );
        $this->api_limit            = 20;

        // Set up activation hooks
        register_activation_hook( __FILE__, array(&$this,   'activate') );
        register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );

        // Set up l10n        
        load_plugin_textdomain( 'graphcommons', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );                

        // actions
        add_action( 'pre_get_posts',            array(&$this, 'init') );
        add_action( 'admin_menu',               array(&$this, 'gc_create_admin_menu' ) );
        add_action( 'admin_init',               array(&$this, 'gc_admin_init' ) );
        add_action( 'admin_notices',            array(&$this, 'gc_admin_notice' ) );
        add_action( 'init',                     array(&$this, 'gc_custom_rewrite_tag'), 10, 0 );    
        add_action( 'wp_ajax_get_nodes_json',   array(&$this, 'get_nodes_json' ) );
        add_action( 'wp_ajax_get_hubs_json',    array(&$this, 'get_hubs_json' ) );
        add_action( 'admin_footer',             array(&$this, 'gc_admin_footer' ) ) ;        
        add_action( 'media_buttons',            array( &$this, 'gc_media_buttons' ), 11 );
        
        /** 
        todo:
            add_action( 'init', array( &$this, 'gc_oembed_provider' ) );
        **/

        // shortcodes
        add_shortcode('graphcommons', array(&$this, 'gc_shortcode') );

        // filters
        add_filter( 'mce_external_plugins', array( &$this, 'gc_tinymce_plugin' ) );
        add_filter( 'mce_buttons', array( &$this, 'gc_register_button' ) );            
        add_filter( 'mce_css', array(&$this, 'gc_mce_css' ) );



    } // construct ends

    function init() {

        $this->action          = get_query_var( 'gc_action' );
        $this->keyword         = get_query_var( 'gc_keyword' );

        if( isset( $this->action ) && !empty( $this->action ) ) {            

            switch ( $this->action ) {

                /**
                *
                *   GC API Endpoints

                */              

                case 'nodes_search':
                    $this->gc_api_nodes_search('https://graphcommons.com/api/v1/nodes/search?query='. $this->keyword . '&limit=' . $this->api_limit);
                    break;

                case 'hubs':
                    $this->get_hubs_json();
                    break;

                default:                
                    break;
            } // switch

            exit(0);

        } //if

    }

    // get json for nodes
    function get_nodes_json() {
        $keyword = $_POST['keyword'];
        $hub = $_POST['hub'];
        $url = 'https://graphcommons.com/api/v1/nodes/search?query='. urlencode($keyword) . '&limit=' . $this->api_limit;       
        if ( $hub !== '' ) {
            $url = $url . '&hub=' . $hub;
        }

        header( 'Content-type: application/json' );
        echo $this->get_url($url);
        die();
    }

    // getting hubs list
    function get_hubs_json() {
        $url = 'https://graphcommons.com/hubs.json';        

        header( 'Content-type: application/json' );
        echo $this->get_url($url);
        die();
    }

    // node search
    function gc_api_nodes_search( $url ) {
        header( 'Content-type: application/json' );
        echo $this->get_url($url);
        die();
    }

    // Register oEmbed providers
    function gc_oembed_provider() {
        wp_oembed_add_provider( 'https://graphcommons.com/nodes/*', 'https://graphcommons.com/oembed', false );
        wp_oembed_add_provider( 'https://graphcommons.com/graphs/*', 'https://graphcommons.com/oembed', false );
    }

    // graphcommons shortcode
    function gc_shortcode( $atts ) {

        $atts = shortcode_atts( array(
            'id'    => 'id',
            'name'  => 'name',
            'type'  => '',
            'embed' => 'node'
        ), $atts, 'graphcommons' );

        switch ( $atts['embed'] ) {
            case 'node':
                $html = '<iframe src="https://graphcommons.com/nodes/'.$atts["id"].'/embed?p=&et=i%C5%9F%20orta%C4%9F%C4%B1d%C4%B1r&g=true" frameborder="0" style="overflow:auto;width:320px;min-width:320px;height:100%;min-height:460px;border:1px solid #CCCCCC;" width="320" height="460"></iframe>';
                break;            
            default:
                $html = __('<strong>Error:</strong> Unknown embed type - Graph Commons Wordpress Plugin', 'graphcommons');
                break;
        }

        return $html;
    }

    // plugin options related functions
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
        
        // settings / options pages
        add_settings_section( 'section-one', 'API Credentials', array(&$this, 'section_one_callback'), 'graphcommons' );
        register_setting( 'graphcommons-settings-group', 'gc-api_key' );                    
        add_settings_field( 'gc-api_key', 'api_key', array(&$this, 'field_api_key_callback'), 'graphcommons', 'section-one' );
        
        // load javascript
        wp_register_script( 'gc-script', plugins_url( '/js/graphcommons.js', __FILE__ ), array('jquery'), '1.0.0', true );

        wp_localize_script( 'gc-script', 'graphcommons', array(
            'api_key'       => $this->api_key, 
            'plugin_url'    => $this->plugin_url,
            'language'      => array(                
                'hubsearchtext' => __('Select if you would like to search in hubs','graphcommons'),
                'searchkeyword' => __('Search keyword', 'graphcommons'),
                'typesomething' => __('Type something, results will be displayed here.'),
                'addgcnodecard' => __('Insert Graph Commons Node Card', 'graphcommons'),
                'noresultsfound' => __('No results found for:', 'graphcommons')
            )
        ) );
        
        wp_enqueue_script( array( 'knockoutjs', 'gc-script' ) );

        // load styles
        wp_register_style( 'gc-style', plugins_url('css/graphcommons.css', __FILE__) );        
        wp_enqueue_style( 'gc-style' );

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

    // options page
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

    // show admin notice if api key is missing
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

    // is curl installed?
    function _is_curl_installed() {
        if  (in_array  ('curl', get_loaded_extensions())) {
            return true;
        }
        else {
            return false;
        }
    }

    // get url contents via curl
    function get_url($url, $method = null) {

        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER      => array( 'Authentication: ' . $this->api_key ),
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 2
        ));

        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    function gc_media_buttons() {
        echo '<a href="#" id="insert-graphcommons-node" class="button"><span class="dashicons dashicons-edit" style="margin-top: 3px;"></span> '.__('Insert Graph Commons Node Card', 'graphcommons').'</a>';
    }

    // custom editor style
    function gc_mce_css( $mce_css ) {
        if ( ! empty( $mce_css ) )
            $mce_css .= ',';

        $mce_css .= plugins_url( '/css/gc_tinymce_style.css', __FILE__ );

        return $mce_css;
    }

    function gc_tinymce_plugin( $plugin_array ) {
        $plugin_array['graphcommons'] = plugins_url( '/js/graphcommons-tinymce-plugin.js',__FILE__ );
        return $plugin_array;
    }
    
    function gc_register_button( $buttons ) {
        array_push( $buttons, 'seperator', 'graphcommons' );
        return $buttons;
    }

    function gc_add_rewrite_rules() {
        add_rewrite_rule('^gc_api/([^/]*)/([^/]*)/?','index.php?gc_action=$matches[1]&gc_keyword=$matches[2]','top');        
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

    function gc_admin_footer() {
    ?>

        <script type="text/html" id="tmpl-editor-graphcommons">
            <div class="graphcommons_{{ data.type }}"></div>
            <div class="gc_node" id="gc_node_{{ data.id }}">
                <span class="title">{{ data.name }}</span>
                <span class="type">{{ data.type }}</span>
                <# if ( data.link ) { #>
                    <# if ( data.linkhref ) { #>
                        <a href="{{ data.linkhref }}" class="link dtbaker_button_light">{{ data.link }}</a>
                    <# } #>
                <# } #>
            </div>
        </script>

    <?php
    }


} // class ends

$graphcommons = new GraphCommons();

?>