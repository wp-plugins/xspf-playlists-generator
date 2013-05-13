<?php
/*
Plugin Name: XSPF Playlists Generator
Description: Parse tracklists from websites and generate a dynamic XSPF file out of it; with its a Toma.hk playlist URL.
Version: 0.1
Author: G.Breant
Author URI: http://pencil2d.org
Plugin URI: http://wordpress.org/extend/plugins/pencil-wiki
License: GPL2
*/

class xspf_playlists_generator {
    
    public $name = 'XSPF Playlists Generator';
    public $author = 'G.Breant';

    /** Version ***************************************************************/

    /**
    * @public string plugin version
    */
    public $version = '0.1';

    /**
    * @public string plugin DB version
    */
    public $db_version = '100';

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

    public $post_type='xspf_plgen';
    public $xpsf_render_var='xspf';


    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new xspf_playlists_generator;
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
        require($this->plugin_dir . '_inc/lib/phpQuery/phpQuery.php');

        require($this->plugin_dir . 'xspf-plgen-classes.php');
        require($this->plugin_dir . 'xspf-plgen-templates.php');

        //admin
        if(is_admin()){
            require($this->plugin_dir . 'xspf-plgen-admin.php');
            $this->admin = new xspf_plgen_admin();
        }

    }

    function setup_actions(){            

        add_action( 'init', array(&$this,'register_post_type' ));

        add_action( 'init', array(&$this,'add_xspf_endpoint' ));            
        add_filter( 'request', array($this, 'filter_request'));

        add_action( 'wp', array(&$this,'populate_post_playlist' ));
        

        add_action('template_redirect', array($this, 'render_xspf'), 5);

        add_action( 'wp_enqueue_scripts', array($this, 'scripts_styles'));

        add_filter( 'the_content', array(&$this,'the_content_links' ));
        add_filter( 'the_content', array(&$this,'the_content_tomahk_playlist' )); //singular

    }

    function scripts_styles(){
        wp_register_style( 'xspf-plgen', xspf_plgen()->plugin_url .'_inc/css/style.css',false,$this->version);
        wp_enqueue_style( 'xspf-plgen' );
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
        global $post;

        if (get_post_type()!=$this->post_type) return false;
        if (!is_singular()) return false;

        $this->post_playlist = new xspf_plgen_playlist($post->ID);


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

        $link_xspf = xspf_plgen_get_xspf_permalink();

        $link_tomahk = xspf_plgen_get_tomahk_playlist_link();
        
        if($link_xspf)
            $new_content .= '<a href="'.$link_xspf.'" class="xspf-plgen-link"><b>'.__('Link to XSPF file','xspf-plgen').'</b></a>';
        
        if(!is_singular()){
            if($link_tomahk)
                $new_content .= '<a href="'.$link_tomahk.'" class="xspf-plgen-link"><b>'.__('Toma.hk Playlist','xspf-plgen').'</b></a>';
        }
        

        $content='<p class="xspf-plgen-links">'.$new_content.'</p>'.$content;

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
        
        $content.=xspf_plgen_get_tomahk_playlist();

        return $content;

    }
    function register_post_type() {

        $labels = array( 
            'name' => _x( 'Playlist Parsers', 'xspf-plgen' ),
            'singular_name' => _x( 'Playlist Parser', 'xspf-plgen' ),
            'add_new' => _x( 'Add New', 'xspf-plgen' ),
            'add_new_item' => _x( 'Add New Playlist Parser', 'xspf-plgen' ),
            'edit_item' => _x( 'Edit Playlist Parser', 'xspf-plgen' ),
            'new_item' => _x( 'New Playlist Parser', 'xspf-plgen' ),
            'view_item' => _x( 'View Playlist Parser', 'xspf-plgen' ),
            'search_items' => _x( 'Search Playlist Parsers', 'xspf-plgen' ),
            'not_found' => _x( 'No playlist parsers found', 'xspf-plgen' ),
            'not_found_in_trash' => _x( 'No playlist parsers found in Trash', 'xspf-plgen' ),
            'parent_item_colon' => _x( 'Parent Playlist Parser:', 'xspf-plgen' ),
            'menu_name' => _x( 'Playlist Parsers', 'xspf-plgen' ),
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

function xspf_plgen() {
	return xspf_playlists_generator::instance();
}

xspf_plgen();



?>
