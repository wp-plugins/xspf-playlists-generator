<?php
/*
 * Plugin Name: XSPF Stations
 * Plugin URI: http://radios.pencil2d.org
 * Description: Parse tracklists from websites and generate a dynamic XSPF file out of it; with its a Toma.hk playlist URL.  You even can <strong>import</strong> (Tools > Import > Wordpress) our selection of stations from this <a href="https://github.com/gordielachance/xspf-playlists-generator/blob/master/HQstations.xml">XML file</a>.
 * Author: G.Breant
 * Version: 0.4.0
 * Author URI: http://radios.pencil2d.org
 * License: GPL2+
 * Text Domain: xspfpl
 * Domain Path: /languages/
 */


/**
 * Plugin Main Class
 */

class XSPFPL_Core {
    
    public $name = 'XSPF Stations';
    public $author = 'G.Breant';

    /** Version ***************************************************************/

    /**
    * @public string plugin version
    */
    public $version = '0.4.0';

    /**
    * @public string plugin DB version
    */
    public $db_version = '112';

    /** Paths *****************************************************************/

    public $file = null;

    /**
    * @public string Basename of the plugin directory
    */
    public $basename = null;

    /**
    * @public string Absolute path to the plugin directory
    */
    public $plugin_dir = null;
    
    public $cache_dir = null;
    public $cache_url = null;


    /**
    * @var The one true Instance
    */
    private static $instance;

    var $options_default = array();
    var $options = null;

    public $station_post_type='station';
    public $tax_music_tag='music_tag';
    public $var_xspf='xspf';
    public $var_variables='xspfpl_vars';
    public $var_station_sortby='sort_station';

    static $meta_key_db_version = 'xspfpl-db';
    static $meta_key_options = 'xspfpl-options';
    
    var $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'auto',
        'convert_to_encoding'       => 'ISO-8859-1'
    );
    
    var $remote_get_options = array(
        'User-Agent'        => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML => like Gecko) Iron/31.0.1700.0 Chrome/31.0.1700.0'
    );
    

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new XSPFPL_Core;
                    self::$instance->setup_globals();
                    self::$instance->includes();
                    self::$instance->setup_actions();
            }
            return self::$instance;
    }

    /**
        * A dummy constructor to prevent bbPress from being loaded more than once.
        *
        * @since bbPress (r2464)
        * @see bbPress::instance()
        * @see bbpress();
        */
    private function __construct() { /* Do nothing here */ }

    function setup_globals() {

            /** Paths *************************************************************/
            $this->file       = __FILE__;
            $this->basename   = plugin_basename( $this->file );
            $this->plugin_dir = plugin_dir_path( $this->file );
            $this->plugin_url = plugin_dir_url ( $this->file );
            $this->cache_dir  = $this->plugin_dir . 'cache';
            $this->cache_url  = $this->plugin_url . 'cache';

            //options
            $this->options_default = array(
                'playlist_link'         => 'on',
                'cache_tracks_intval'   => 60*5, //seconds
                'tracklist_embed'       => 'on',
                'enable_hatchet'        => 'on',
                
            );
    }

    function includes(){

        require_once($this->plugin_dir . '_inc/php/autoload.php');

        require( $this->plugin_dir . 'xspfpl-functions.php');
        require( $this->plugin_dir . 'xspfpl-templates.php');
        require( $this->plugin_dir . 'xspfpl-playlist.php');
        require( $this->plugin_dir . 'xspfpl-playlist-presets.php');
        require( $this->plugin_dir . 'xspfpl-tracks-table.php' );
        
        //we need the Hatchet.is plugin ! 
        //https://wordpress.org/plugins/wp-hatchet/
        if ( class_exists('Hatchet') ){
            //require( $this->plugin_dir . 'xspfpl-hatchet.php');
        }

        //admin
        if(is_admin()){
            require( $this->plugin_dir . 'xspfpl-wizard.php' );
            require( $this->plugin_dir . 'xspfpl-admin.php');
            require( $this->plugin_dir . 'xspfpl-admin-options.php');
        }

    }

    function setup_actions(){    
        
        register_activation_hook( $this->file , array( $this, 'set_roles_capabilities' ) );//roles & capabilities

        add_action( 'init', array(&$this,'register_station_post_type' ));
        add_action( 'init', array(&$this,'register_taxonomy' ));
        add_filter('query_vars', array(&$this,'register_query_vars' ));
        
        add_action( 'init' , array($this, 'upgrade'));//install and upgrade

        add_action( 'init', array(&$this,'add_xspf_endpoint' ));            
        add_filter( 'request', array($this, 'filter_request'));
        
        add_action( 'pre_get_posts', array($this, 'sort_stations'));

        //TO FIX : broken
        add_filter( 'the_title', array(&$this,'playlist_title' ));

        add_action( 'template_redirect', array($this, 'redirect_playlist_service_not_ready'), 5);
        add_action( 'template_redirect', array($this, 'render_xspf'), 5);

        add_action( 'wp_enqueue_scripts', array($this, 'scripts_styles'));
        
        add_filter( 'the_excerpt', array(&$this,'embed_playlist_links_archive' ));
        add_filter( 'the_content', array(&$this,'embed_playlist_links_single' ));
        add_filter( 'the_content', array(&$this,'embed_tracklist' ));
        
        //this is weird, but it is easier for us to build and hook our action on 'delete_option' than on 'deleted_transient'.
        add_action( 'delete_option', array('XSPFPL_Single_Playlist','delete_cached_file_along_transient' ));

    }


    function upgrade_from_111(){
        
        $errors = false;
        
        $query_args = array(
            'post_type'         => 'playlist',
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );
        

        $query = new WP_Query( $query_args );
        $meta_key = XSPFPL_Single_Playlist::$meta_key_settings;
        
        foreach ($query->posts as $id){
            
            //update post type
            $post = array(
                'ID'           => $id,
                'post_type'   => $this->station_post_type
            );
            wp_update_post( $post );

            $options = get_post_meta($id, $meta_key, true);

            $options['selectors'] = array(
                'tracks'        => ( isset($options['tracks_selector']) ) ? $options['tracks_selector'] : null,
                'track_artist'  => ( isset($options['track_artist_selector']) ) ? $options['track_artist_selector'] : null,
                'track_title'   => ( isset($options['track_title_selector']) ) ? $options['track_title_selector'] : null,
                'track_album'   => ( isset($options['track_album_selector']) ) ? $options['track_album_selector'] : null,
                'track_image'   => ( isset($options['track_album_art_selector']) ) ? $options['track_album_art_selector'] : null
            );
            
            $options['selectors_regex'] = array(
                'track_artist'  => ( isset($options['track_artist_regex']) ) ? $options['track_artist_regex'] : null,
                'track_title'   => ( isset($options['track_title_regex']) ) ? $options['track_title_regex'] : null,
                'track_album'   => ( isset($options['track_album_regex']) ) ? $options['track_album_regex'] : null
            );
            
            foreach ((array)$options['selectors_regex'] as $key=>$regex){
                $options['selectors_regex'][$key] = stripslashes($regex);
            }
            
            
            $options['is_frozen'] = ( isset($options['is_static']) ) ? $options['is_static'] : null;
            $options['feed_url'] = ( isset($options['tracklist_url']) ) ? $options['tracklist_url'] : null;
            
            $remove_keys = array(
                'tracks_selector',
                'track_artist_selector',
                'track_title_selector',
                'track_album_selector',
                'track_album_art_selector',
                'track_artist_regex',
                'track_title_regex',
                'track_album_regex',
                'is_static'
            );
            
            $options['cache_enabled'] = 'on';
            
            $options = array_diff_key($options,array_flip($remove_keys));
            $options = array_filter($options);
            
            update_post_meta($id, $meta_key, $options);

        }
    }
    
    function upgrade(){
        global $wpdb;

        $current_version = get_option(self::$meta_key_db_version);
        //TO FIX TO UNCOMMENT if ( $current_version==$this->db_version ) return;

        //> 111
        if ( $current_version < 112 ) {
            self::upgrade_from_111();
        }
        

        //install
        if(!$current_version){
            //handle SQL
            //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            //dbDelta($sql);
            //add_option($option_name,$this->get_default_settings()); // add settings
        }

        //upgrade DB version
        update_option(self::$meta_key_db_version, $this->db_version );//upgrade DB version
    }
    
    function register_query_vars($vars) {
        $vars[] = $this->var_xspf;
        $vars[] = $this->var_variables;
        $vars[] = $this->var_station_sortby;
        return $vars;
    }
    
    public function get_options($key = null){
        
        if ( !isset($this->options) ){
            $options = get_option( self::$meta_key_options, $this->options_default );
            $this->options = $options;

        }

        if (!$key) return $this->options;
        if (!isset($this->options[$key])) return false;
        return $this->options[$key];

    }
    
    public function get_default_option($name){
        if (!isset($this->options_default[$name])) return;
        return $this->options_default[$name];
    }

    function scripts_styles(){
        wp_register_style( 'xspfpl', xspfpl()->plugin_url .'_inc/css/style.css',false,$this->version);
        wp_enqueue_style( 'xspfpl' );
    }
    
    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function add_xspf_endpoint(){
        add_rewrite_endpoint($this->var_xspf, EP_PERMALINK );
    }

    /**
    * Set xspf as true, see http://wordpress.stackexchange.com/questions/42279/custom-post-type-permalink-endpoint
    * @param type $vars 
    */

    function filter_request($vars){
        if( isset( $vars[$this->var_xspf] ) ) $vars[$this->var_xspf] = true;
        return $vars;
    }
    
    function sort_stations( $query ) {

        if ( !$query->is_main_query() || ( !$order = $query->get( $this->var_station_sortby ) ) || $query->get('post_type')!=$this->station_post_type ) return $query;
        
        switch ($order){
            case 'popular':

                $query->set('meta_key', XSPFPL_Single_Playlist::$meta_key_requests );
                $query->set('orderby','meta_value_num');
                $query->set('order', 'DESC');
                
            break;
        
            //TO FIX
            case 'trending':
                $query->set('meta_key', XSPFPL_Single_Playlist::$meta_key_monthly_requests );
                $query->set('orderby','meta_value_num');
                $query->set('order', 'DESC');
            break;
        }
        
        return $query;
        
    }
    
    function playlist_title($title){

        if ( !is_admin() && in_the_loop() && $playlist = xspfpl_get_playlist() ){
            $title = $playlist->get_playlist_datas('title');
        }

        return $title;
    }
    
    function redirect_playlist_service_not_ready(){
        $args = array();
        
        if ( is_admin() ) return false;
        if (get_post_type()!=$this->station_post_type) return false;
        if (!is_singular()) return false;
        if ( isset($_REQUEST['missing_vars']) ) return false;
        if (!$playlist = xspfpl_get_playlist()) return false;
        if ($playlist->is_playlist_ready()) return false;

        $args = array(
            xspfpl()->var_variables => get_query_var( xspfpl()->var_variables )
        );

        $missing_slugs = $playlist->get_missing_service_variables_slugs();

        foreach ((array)$missing_slugs as $slug){
            $args['missing_vars'][] = $slug;
        }

        $url = add_query_arg($args, get_permalink() );

        wp_redirect($url);
        die();

    }

    /**
     * Render the XSPF file
     * @return boolean 
     */

    function render_xspf(){
        if (get_post_type()!=$this->station_post_type) return false;
        if (!is_singular()) return false;
        if (!get_query_var( $this->var_xspf )) return false;

        echo xspfpl_get_playlist()->get_xspf();
        exit;
    }

    /**
     * Filter the content of a playlist post and adds the XSPF link to it.
     * @global type $post
     * @param string $content
     * @return string 
     */

    function embed_playlist_links_single($content){
        
        if( is_single() && get_post_type()==$this->station_post_type && xspfpl()->get_options('playlist_link') ){
            $content= xspfpl_get_html_pre().$content;
        }
        return $content;

    }
    
    function embed_playlist_links_archive($content){
        
        if( get_post_type()==$this->station_post_type && xspfpl()->get_options('playlist_link') ){
            $content .= xspfpl_get_html_pre();
        }
        return $content;

    }
    
    function embed_tracklist($content){

        if( is_single() && get_post_type()==$this->station_post_type && xspfpl()->get_options('tracklist_embed') ){
            $content .= xspfpl_get_tracklist_table();
        }

        return $content;
        
    }

    function register_station_post_type() {

        $labels = array( 
            'name' => _x( 'Stations', 'xspfpl' ),
            'singular_name' => _x( 'Station', 'xspfpl' ),
            'add_new' => _x( 'Add New', 'xspfpl' ),
            'add_new_item' => _x( 'Add New Station', 'xspfpl' ),
            'edit_item' => _x( 'Edit Station', 'xspfpl' ),
            'new_item' => _x( 'New Station', 'xspfpl' ),
            'view_item' => _x( 'View Station', 'xspfpl' ),
            'search_items' => _x( 'Search Stations', 'xspfpl' ),
            'not_found' => _x( 'No stations found', 'xspfpl' ),
            'not_found_in_trash' => _x( 'No stations found in Trash', 'xspfpl' ),
            'parent_item_colon' => _x( 'Parent Station:', 'xspfpl' ),
            'menu_name' => _x( 'XSPF Stations', 'xspfpl' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title', 'editor','author','thumbnail', 'comments' ),
            'taxonomies' => array( $this->tax_music_tag ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet
            'capability_type' => 'station',
            'map_meta_cap'        => true,
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_station',
                'read_post'              => 'read_station',
                'delete_post'            => 'delete_station',

                // primitive/meta caps
                'create_posts'           => 'create_stations',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_stations',
                'edit_others_posts'      => 'manage_stations',
                'publish_posts'          => 'manage_stations',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_stations',
                'delete_private_posts'   => 'manage_stations',
                'delete_published_posts' => 'manage_stations',
                'delete_others_posts'    => 'manage_stations',
                'edit_private_posts'     => 'edit_stations',
                'edit_published_posts'   => 'edit_stations'
            ),
        );

        register_post_type( $this->station_post_type, $args );
    }
    
    function register_taxonomy() {
        
        $labels = array(
                'name'                       => _x( 'Tags', 'taxonomy general name' ),
                'singular_name'              => _x( 'Tag', 'taxonomy singular name' ),
                'search_items'               => __( 'Search Tags' ),
                'popular_items'              => __( 'Popular Tags' ),
                'all_items'                  => __( 'All Tags' ),
                'parent_item'                => null,
                'parent_item_colon'          => null,
                'edit_item'                  => __( 'Edit Tag' ),
                'update_item'                => __( 'Update Tag' ),
                'add_new_item'               => __( 'Add New Tag' ),
                'new_item_name'              => __( 'New Tag Name' ),
                'separate_items_with_commas' => __( 'Separate tags with commas' ),
                'add_or_remove_items'        => __( 'Add or remove tags' ),
                'choose_from_most_used'      => __( 'Choose from the most used tags' ),
                'not_found'                  => __( 'No tags found.' ),
                'menu_name'                  => __( 'Tags' ),
        );

        $args = array(
                'hierarchical'          => false,
                'labels'                => $labels,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'update_count_callback' => '_update_post_term_count',
                'query_var'             => true,
                'rewrite'               => array( 'slug' => $this->tax_music_tag ),
                'capabilities' => array(
                    'manage_terms' => 'manage_playlists',
                    'edit_terms' => 'edit_playlists',
                    'delete_terms' => 'edit_playlists',
                    'assign_terms' => 'edit_playlists'
                )
        );

        register_taxonomy( $this->tax_music_tag, $this->station_post_type, $args );
    }
    
        function set_roles_capabilities(){
            
            global $wp_roles;
            if ( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();

            //create a new role, based on the subscriber role 
            $role_name = 'station_author';
            $subscriber = $wp_roles->get_role('subscriber');
            $wp_roles->add_role($role_name,__('Station Author','xspfpl'), $subscriber->capabilities);

            //list of custom capabilities and which role should get it
            $wiki_caps=array(
                'manage_stations'=>array('administrator','editor'),
                'edit_stations'=>array('administrator','editor',$role_name),
                'create_stations'=>array('administrator','editor',$role_name),
            );

            foreach ($wiki_caps as $wiki_cap=>$roles){
                foreach ($roles as $role){
                    $wp_roles->add_cap( $role, $wiki_cap );
                }
            }

        }
        
        function debug_log($message) {

            if (WP_DEBUG_LOG !== true) return false;

            $prefix = '[xspfpl] : ';
            
            if (is_array($message) || is_object($message)) {
                error_log($prefix.print_r($message, true));
            } else {
                error_log($prefix.$message);
            }
        }
    


}

/**
 * The main function responsible for returning the one Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 */

function xspfpl() {
	return XSPFPL_Core::instance();
}

xspfpl();



?>
