<?php
/*
 * Plugin Name: XSPF Playlists Generator
 * Plugin URI: http://radios.pencil2d.org
 * Description: Parse tracklists from websites and generate a dynamic XSPF file out of it; with its a Toma.hk playlist URL.  You even can <strong>import</strong> (Tools > Import > Wordpress) our selection of stations from this <a href="https://github.com/gordielachance/xspf-playlists-generator/blob/master/HQstations.xml">XML file</a>.
 * Author: G.Breant
 * Version: 0.3.1
 * Author URI: http://radios.pencil2d.org
 * License: GPL2+
 * Text Domain: xspfpl
 * Domain Path: /languages/
 */


/**
 * Plugin Main Class
 */

class XSPFPL_Core {
    
    public $name = 'XSPF Playlists Generator';
    public $author = 'G.Breant';

    /** Version ***************************************************************/

    /**
    * @public string plugin version
    */
    public $version = '0.3.1';

    /**
    * @public string plugin DB version
    */
    public $db_version = '111';

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

    var $options_default = array();
    var $options = array();

    public $post_type='playlist';
    public $tax_tag='playlist_tag';
    public $xpsf_render_var='xspf';

    static $meta_key_db_version = 'xspfpl-db';
    static $meta_key_options = 'xspfpl-options';


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

            //options
            $this->options_default = array(
                'playlist_link'         => 'on',
                'thk_friendly'          => 'on',
                'cache_tracks_intval'   => 120,
                'widget_embed'          => 'on',
                
            );
            $options = get_option( self::$meta_key_options, $this->options_default );
            $this->options = apply_filters('xspfpl_options',$options);
            
    }

    function includes(){
        if (!class_exists("phpQuery")){
            require($this->plugin_dir . '_inc/lib/phpQuery/phpQuery.php');
        }
        
        require( $this->plugin_dir . 'xspfpl-wizard.php' );
        require( $this->plugin_dir . 'xspfpl-playlist.php');
        require( $this->plugin_dir . 'xspfpl-playlist-stats.php' );
        
        //we need the Hatchet.is plugin ! 
        //https://wordpress.org/plugins/wp-hatchet/
        if ( class_exists('Hatchet') ){
            //require( $this->plugin_dir . 'xspfpl-hatchet.php');
        }
        
        require( $this->plugin_dir . 'xspfpl-templates.php');

        //admin
        if(is_admin()){
            require($this->plugin_dir . 'xspfpl-admin.php');
            require($this->plugin_dir . 'xspfpl-admin-options.php');
            require($this->plugin_dir . 'xspfpl-admin-wizard.php');
        }

    }

    function setup_actions(){    

        register_activation_hook( $this->file , array( $this, 'set_roles_capabilities' ) );//roles & capabilities

        add_action( 'init', array(&$this,'register_post_type' ));
        add_action( 'init', array(&$this,'register_taxonomy' ));
        
        add_action( 'init' , array($this, 'upgrade'));//install and upgrade

        add_action( 'init', array(&$this,'add_xspf_endpoint' ));            
        add_filter( 'request', array($this, 'filter_request'));

        add_action( 'wp', array(&$this,'populate_post_playlist' ));
        

        add_action('template_redirect', array($this, 'render_xspf'), 5);

        add_action( 'wp_enqueue_scripts', array($this, 'scripts_styles'));

        add_filter( 'the_content', array(&$this,'embed_playlist_link' ));
        
        add_action('xspfpl_populate_tracks',array($this,'update_tracks_cache'));

    }
    
    function update_tracks_cache($playlist){
        
        if ($playlist->is_wizard) return;
        
        //save time & tracks
        if ($playlist->post_id){
            $cachemeta = array(
                'time'      => current_time( 'timestamp' ),
                'tracks'    => $playlist->tracks
            );
            update_post_meta($playlist->post_id, XSPFPL_Single_Playlist::$meta_key_tracks_cache, $cachemeta);
        }
    }
    
    //needed for 110->111 update. We can remove this after a while.
    function upgrade_tags_taxonomy(){
        $query_args = array(
            'post_type'         => $this->post_type,
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );

        $query = new WP_Query( $query_args );
        
        foreach ($query->posts as $id){
            $tags_names=array();
            $tags = get_the_terms( $id, 'post_tag' );
            
            if ($tags){
                foreach($tags as $tag){
                    $tags_names[] = $tag->name;
                }
                if (!empty($tags_names)){
                    if ($result = wp_set_post_terms( $id, $tags_names, $this->tax_tag)){ //set custom taxonomy
                        wp_set_post_terms( $id, array(), 'post_tag'); //set custom taxonomy
                    }
                }
            }

        }
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

                $default = XSPFPL_Single_Playlist::get_default_options();
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
        
        //> 110
        if ( $current_version < 111 ) {
            self::upgrade_tags_taxonomy();
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
    
    public function get_option($name){
        if (!isset($this->options[$name])) return;
        return $this->options[$name];
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

    function embed_playlist_link($content){
        
        if(!is_single()) return $content;
        if ( get_post_type()!=$this->post_type ) return $content;
        if (!xspfpl()->get_option('playlist_link')) return $content;
        
        $link_block = xspfpl_get_playlist_link();

        $content = $link_block.$content;

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
            'taxonomies' => array( $this->tax_tag ),
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
            'capability_type' => 'playlist',
            'map_meta_cap'        => true,
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_playlist',
                'read_post'              => 'read_playlist',
                'delete_post'            => 'delete_playlist',

                // primitive/meta caps
                'create_posts'           => 'create_playlists',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_playlists',
                'edit_others_posts'      => 'manage_playlists',
                'publish_posts'          => 'manage_playlists',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_playlists',
                'delete_private_posts'   => 'manage_playlists',
                'delete_published_posts' => 'manage_playlists',
                'delete_others_posts'    => 'manage_playlists',
                'edit_private_posts'     => 'edit_playlists',
                'edit_published_posts'   => 'edit_playlists'
            ),
        );

        register_post_type( $this->post_type, $args );
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
                'rewrite'               => array( 'slug' => $this->tax_tag ),
                'capabilities' => array(
                    'manage_terms' => 'manage_playlists',
                    'edit_terms' => 'edit_playlists',
                    'delete_terms' => 'edit_playlists',
                    'assign_terms' => 'edit_playlists'
                )
        );

        register_taxonomy( $this->tax_tag, $this->post_type, $args );
    }
    
        function set_roles_capabilities(){
            
            global $wp_roles;
            if ( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();

            //create a new role, based on the subscriber role 
            $role_name = 'playlist_author';
            $subscriber = $wp_roles->get_role('subscriber');
            $wp_roles->add_role($role_name,__('Playlist Author','xspfpl'), $subscriber->capabilities);

            //list of custom capabilities and which role should get it
            $wiki_caps=array(
                'manage_playlists'=>array('administrator','editor'),
                'edit_playlists'=>array('administrator','editor',$role_name),
                'create_playlists'=>array('administrator','editor',$role_name),
            );

            foreach ($wiki_caps as $wiki_cap=>$roles){
                foreach ($roles as $role){
                    $wp_roles->add_cap( $role, $wiki_cap );
                }
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
