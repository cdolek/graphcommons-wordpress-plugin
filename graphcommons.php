<?php
/*
Plugin Name: Graph Commons
Plugin URI:  https://github.com/cdolek/graphcommons-wordpress
Description: Helps you find and insert Graph Commons node cards to your posts.
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
    private $hubs;

    function __construct()
    {
        // Set up default vars
        $this->plugin_path          = plugin_dir_path( __FILE__ );
        $this->plugin_url           = plugin_dir_url( __FILE__ );
        $this->api_key              = esc_attr( get_option( 'gc-api_key' ) );
        $this->api_limit            = 20;

        // Set up l10n
        load_plugin_textdomain( 'graphcommons', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

        // actions
        add_action( 'pre_get_posts',            array( &$this, 'init') );
        add_action( 'admin_menu',               array( &$this, 'gc_create_admin_menu' ) );
        add_action( 'admin_init',               array( &$this, 'gc_admin_init' ) );
        add_action( 'admin_notices',            array( &$this, 'gc_admin_notice' ) );
        add_action( 'admin_footer',             array( &$this, 'gc_admin_footer' ) ) ;
        add_action( 'media_buttons',            array( &$this, 'gc_media_buttons' ), 11 );
        add_action( 'wp_ajax_get_from_api',     array( &$this, 'gc_get_from_api' ) );
        add_action( 'init',                     array( &$this, 'gc_oembed_provider' ) );

        // shortcodes
        add_shortcode('graphcommons', array(&$this, 'gc_shortcode') );

        // filters
        add_filter( 'mce_external_plugins',     array( &$this, 'gc_tinymce_plugin' ) );
        add_filter( 'mce_buttons',              array( &$this, 'gc_register_button' ) );
        add_filter( 'mce_css',                  array( &$this, 'gc_mce_css' ) );
        add_filter( 'embed_defaults',           array( &$this, 'gc_filter_embed_defaults'), 10, 2 );

    } // construct ends

    function init() {
        // empty
    }

    // get json from api
    function gc_get_from_api() {
        $keyword    = $_POST['keyword'];
        $hub        = $_POST['hub'];
        $type        = $_POST['type'];
        $url        = 'https://graphcommons.com/api/v1/' . $type . 's/search?query='. urlencode($keyword) . '&limit=' . $this->api_limit;

        if ( $hub !== '' ) {
            $url = $url . '&hub=' . $hub;
        }

        $this->gc_get_url_and_print_json( $url );
    }

    // get and send url as json
    function gc_get_url_and_print_json( $url ) {
        header( 'Content-type: application/json' );
        $args = array(
            'headers' => array(
                'Authentication' => $this->api_key
            )
        );
        $response = wp_remote_get( $url, $args );
        // $http_code = wp_remote_retrieve_response_code( $response );
        echo $response['body'];
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
            case 'graph':
                $html = '<iframe src="https://graphcommons.com/graphs/'.$atts["id"].'/embed" frameborder="0" style="overflow:hidden;width:1px;min-width:100%;height:400px;min-height:400px;" width="100%" height="400" allowfullscreen></iframe>';
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

        // get the hubs transient
        $this->hubs = get_transient( 'graphcommons_hubs' );

        if( false === $this->hubs ) {
            // Transient expired, refresh the data
            $response = wp_remote_get( 'https://graphcommons.com/hubs.json' );

            $json = json_decode( $response['body'] );

            foreach ($json as $key => $value) {
                $myArr[$key]['text']    = $value->name;
                $myArr[$key]['value']   = $value->id;
            }

            // Add the initial value for empty selection
            array_unshift($myArr, array(
                'text'  => __('Select if you would like to search in hubs','graphcommons'),
                'value' => ''
            ));

            // record the transient
            set_transient( 'graphcommons_hubs', $myArr, 60*60*24 ); // 60x60x24 = one day

            // we have the new hubs array now
            $this->hubs = $myArr;
        }

        // settings / options pages
        add_settings_section( 'section-one', 'API Credentials', array(&$this, 'section_one_callback'), 'graphcommons' );
        register_setting( 'graphcommons-settings-group', 'gc-api_key' );
        add_settings_field( 'gc-api_key', 'api_key', array(&$this, 'field_api_key_callback'), 'graphcommons', 'section-one' );

        // load javascript
        wp_register_script( 'gc-script', plugins_url( '/js/graphcommons.js', __FILE__ ), array('jquery'), '1.0.0', true );

        wp_localize_script( 'gc-script', 'graphcommons', array(
            'api_key'       => $this->api_key,
            'plugin_url'    => $this->plugin_url,
            'hubs'          => $this->hubs,
            'language'      => array(
                'searchkeyword'     => __('Search keyword', 'graphcommons'),
                'addgcnodecard'     => __('Insert Graph Commons Node Card', 'graphcommons'),
                'addgcobject'       => __('Insert Graph Commons Object', 'graphcommons'),
                'noresultsfound'    => __('No results found for:', 'graphcommons'),
                'typesomething'     => __('Start typing, results will be displayed here', 'graphcommons'),
                'nodepreview'       => __('Graph Commons Node Preview', 'graphcommons' ),
                'graphpreview'      => __('Graph Commons Graph Preview', 'graphcommons' )
            )
        ) );

        wp_enqueue_script( array( 'gc-script' ) );

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
        global $content_width;
    ?>
        <div class="wrap">
            <h2>Graph Commons Settings</h2>
            <form action="options.php" method="POST">
                <?php settings_fields( 'graphcommons-settings-group' ); ?>
                <?php do_settings_sections( 'graphcommons' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>

        <?php if ( $content_width > 0 ) { ?>

        <div class="wrap">
            <hr>
            <h2>Notes:</h2>
            <p>
            <?php

            printf(
            __( 'Your theme has a <strong>$content_width</strong> setting of <strong>%1$s</strong> pixels. Inserted graphs will be shown at this width to be compatible with your design.', 'graphcommons' ),
                $content_width
            );

            ?>
            </p>

        </div>

        <?php } ?>

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

    function gc_media_buttons() {
        echo '<a href="#" id="insert-graphcommons-node" class="button"><span class="dashicons dashicons-edit" style="margin-top: 3px;"></span> '.__('Insert Graph Commons Object', 'graphcommons').'</a>';
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

    // change embed size for gc
    function gc_filter_embed_defaults( $compact, $url ) {

        if ( false !== strpos($url, 'graphcommons.com') ) {
            // node
            if ( false !== strpos($url, '/nodes/') ) {

                $compact = array(
                    'width'     => 320,
                    'height'    => 460
                );

            } else {
            // graph

            }

        }

        return $compact;
    }


} // class ends

$graphcommons = new GraphCommons();

?>