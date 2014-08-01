<?php
/*
 * Plugin Name: XSPF Playlists Generator
 * Plugin URI: http://radios.pencil2d.org
 * Description: Parse tracklists from websites and generate a dynamic XSPF file out of it; with its a Toma.hk playlist URL.  You even can <strong>import</strong> (Tools > Import > Wordpress) our selection of stations from this <a href="https://github.com/gordielachance/xspf-playlists-generator/blob/master/HQstations.xml">XML file</a>.
 * Author: G.Breant
 * Version: 0.2.1
 * Author URI: http://radios.pencil2d.org
 * License: GPL2+
 * Text Domain: xspfpl
 * Domain Path: /languages/
 */


//Hatchet.is API : https://api.hatchet.is/apidocs/#!/playlists

class XSPFPL_Core {
    
    public $name = 'XSPF Playlists Generator';
    public $author = 'G.Breant';

    /** Version ***************************************************************/

    /**
    * @public string plugin version
    */
    public $version = '0.2.1';

    /**
    * @public string plugin DB version
    */
    public $db_version = '110';

    /** Paths *****************************************************************/

    public $file = '';

    /**
    * @public string Basename of the plugin directory
    */
    public $basename = '';

    /**
    * @public string Absolute path to the plugin directory
    */
    public $plugin_dir = '';


    /**
    * @var The one true Instance
    */
    private static $instance;

    var $admin;

    public $post_type='playlist';
    public $xpsf_render_var='xspf';

    var $cache_tracks_intval = 120; // validity of tracks cache, in seconds
    
    static $meta_key_db_version = 'xspfpl-db';


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



    }

    function includes(){
        if (!class_exists("phpQuery")){
            require($this->plugin_dir . '_inc/lib/phpQuery/phpQuery.php');
        }
        
        require( $this->plugin_dir . 'xspfpl-wizard.php' );
        require( $this->plugin_dir . 'xspfpl-playlist.php');
        require( $this->plugin_dir . 'xspfpl-playlist-stats.php' );
        require( $this->plugin_dir . 'xspfpl-templates.php');

        //admin
        if(is_admin()){
            require($this->plugin_dir . 'xspfpl-admin.php');
            $this->admin = new XSPFPL_Admin_Wizard();
        }

    }

    function setup_actions(){    
        
        add_action( 'plugins_loaded', array($this, 'upgrade'));//install and upgrade
        add_action( 'init', array(&$this,'register_post_type' ));

        add_action( 'init', array(&$this,'add_xspf_endpoint' ));            
        add_filter( 'request', array($this, 'filter_request'));

        add_action( 'wp', array(&$this,'populate_post_playlist' ));
        

        add_action('template_redirect', array($this, 'render_xspf'), 5);

        add_action( 'wp_enqueue_scripts', array($this, 'scripts_styles'));

        add_filter( 'the_content', array(&$this,'the_content_links' ));
        add_filter( 'the_content', array(&$this,'the_content_tomahk_playlist' )); //singular

    }
    
    //needed for 109 update. We can remove this after a while.
    function update_meta_key( $old_key=null, $new_key=null ){
        global $wpdb;

        $query = "UPDATE ".$wpdb->prefix."postmeta SET meta_key = '".$new_key."' WHERE meta_key = '".$old_key."'";
        $results = $wpdb->get_results( $query, ARRAY_A );

        return $results;
    }
    
    function upgrade(){
        global $wpdb;
        
        //upgrade from 109
        $old_db_option_name = '_xspf-plgen-db';
;        if ($current_version = get_option($old_db_option_name)){
            
            //upgrade from 107 to 109 - merging settings into one single meta key
            if ( $current_version < 109 ){

                $default = XSPFPL_Single_Playlist::get_default_args();
                $meta_key = XSPFPL_Single_Playlist::$meta_key_settings;
                $query_args = array(
                    'post_type'         => $this->post_type,
                    'posts_per_page'    => -1
                );

                $plquery = new WP_Query( $query_args );

                foreach((array)$plquery->posts as $post){
                    $new_meta = get_post_meta($post->ID, $meta_key, true);
                    if ($new_meta) continue;

                    $post_args = array();

                    foreach ((array)$default as $slug=>$null){
                        $post_args[$slug] = get_post_meta($post->ID, $slug, true);
                    }

                    foreach((array)$post_args as $slug=>$value){
                        if ( $value==$default[$slug] ) continue; //is default value
                        $meta[$slug] = $value;
                    }

                    $meta = array_filter($meta);

                    if (update_post_meta($post->ID, $meta_key, $meta)){
                        foreach ((array)$default as $slug=>$null){
                            delete_post_meta($post->ID, $slug);
                        }
                    }
                }
            }
            
            //rename post meta keys
            self::update_meta_key('xspf_plgen_settings',XSPFPL_Single_Playlist::$meta_key_settings );
            self::update_meta_key('xspf_plgen_health',XSPFPL_Single_Playlist::$meta_key_health );
            self::update_meta_key('xspf_plgen_xspf_request_count',XSPFPL_Single_Playlist::$meta_key_requests );
            self::update_meta_key('xspf_plgen_tracks_cache',XSPFPL_Single_Playlist::$meta_key_tracks_cache );
            delete_option( $old_db_option_name );
            
        }

        //> 109
        $current_version = get_option(self::$meta_key_db_version);

        if ( $current_version==$this->db_version ) return;

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

    function scripts_styles(){
        wp_register_style( 'xspfpl', xspfpl()->plugin_url .'_inc/css/style.css',false,$this->version);
        wp_enqueue_style( 'xspfpl' );
    }
    
    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function add_xspf_endpoint(){
        add_rewrite_endpoint($this->xpsf_render_var, EP_PERMALINK );
    }

    /**
    * Set xspf as true, see http://wordpress.stackexchange.com/questions/42279/custom-post-type-permalink-endpoint
    * @param type $vars 
    */

    function filter_request($vars){
        if( isset( $vars[$this->xpsf_render_var] ) ) $vars[$this->xpsf_render_var] = true;
        return $vars;
    }
    
    /**
     * We are on a playlist post, load the associated playlist.
     * @global type $post
     * @return boolean 
     */

    function populate_post_playlist(){
        
        if (get_post_type()!=$this->post_type) return false;
        if (!is_singular()) return false;

        $this->post_playlist = new XSPFPL_Single_Playlist(get_the_ID());


    }
    
    /**
     * Render the XSPF file
     * @return boolean 
     */

    function render_xspf(){

        if (get_post_type()!=$this->post_type) return false;
        if (!is_singular()) return false;
        if (!get_query_var( $this->xpsf_render_var )) return false;

        echo $this->post_playlist->get_xpls();
        exit;
    }
    
    /**
     * Filter the content of a playlist post and adds the XSPF link to it.
     * @global type $post
     * @param string $content
     * @return string 
     */

    function the_content_links($content){
        global $post;
        
        if ($post->post_type!=$this->post_type) return $content;
        
        $new_content='';

        $link_xspf = xspfpl_get_xspf_permalink();

        $link_tomahk = xspfpl_get_tomahk_playlist_link();
        
        if($link_xspf)
            $new_content .= '<a href="'.$link_xspf.'" class="xspfpl-link"><b>'.__('Link to XSPF file','xspfpl').'</b></a>';
        
        if(!is_singular()){
            if($link_tomahk)
                $new_content .= '<a href="'.$link_tomahk.'" class="xspfpl-link"><b>'.__('Toma.hk Playlist','xspfpl').'</b></a>';
        }
        

        $content='<p class="xspfpl-links">'.$new_content.'</p>'.$content;

        return $content;

    }

    
    /**
     * Filter the content of a playlist post and adds the toma.hk iframe to it.
     * @global type $post
     * @param type $content
     * @return type 
     */


    function the_content_tomahk_playlist($content){
        global $post;

        if (get_post_type()!=$this->post_type) return $content;

        if(!is_singular()) return $content;
        
        $content.=xspfpl_get_tomahk_playlist();

        return $content;

    }
    function register_post_type() {

        $labels = array( 
            'name' => _x( 'Playlist Parsers', 'xspfpl' ),
            'singular_name' => _x( 'Playlist Parser', 'xspfpl' ),
            'add_new' => _x( 'Add New', 'xspfpl' ),
            'add_new_item' => _x( 'Add New Playlist Parser', 'xspfpl' ),
            'edit_item' => _x( 'Edit Playlist Parser', 'xspfpl' ),
            'new_item' => _x( 'New Playlist Parser', 'xspfpl' ),
            'view_item' => _x( 'View Playlist Parser', 'xspfpl' ),
            'search_items' => _x( 'Search Playlist Parsers', 'xspfpl' ),
            'not_found' => _x( 'No playlist parsers found', 'xspfpl' ),
            'not_found_in_trash' => _x( 'No playlist parsers found in Trash', 'xspfpl' ),
            'parent_item_colon' => _x( 'Parent Playlist Parser:', 'xspfpl' ),
            'menu_name' => _x( 'Playlist Parsers', 'xspfpl' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title', 'editor','thumbnail', 'comments' ),
            'taxonomies' => array( 'post_tag' ),
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
            'capability_type' => 'post'
        );

        register_post_type( $this->post_type, $args );
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
