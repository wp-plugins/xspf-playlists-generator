<?php
class XSPFPL_Hatchet {
    
    static $meta_key_hatchet_id = 'xspfpl_hatchet_id';
    
    function __construct() {
        
        //self::setup_globals();
        self::setup_actions();

    }
    
    function setup_actions(){
        //testing it
        //add_action('wp', array(&$this,'hatchet_tests') );
        
        //embed hatchet playlist
        //add_filter( 'the_content', array(&$this,'embed_playlist_widget' )); //singular
        
        //update hatchet playlist
        //add_action( 'xspfpl_populated_playlist_tracks', array(&$this,'update_hatchet_playlist') );
        
        //delete hatchet playlist when a WP playlist is deleted
        //add_action( 'before_delete_post', array(&$this,'delete_hatchet_playlist') );
    }
    
    function hatchet_tests(){
        //$api = new Hatchet_API();
        $playlist = new Hatchet_Playlist('532af0dcded8e9b141fe270f_53e27bc22484f2098b000549');
        $playlist->delete_playlist();
        die();

        //$wp_playlist = new XSPFPL_Single_Playlist(13256);
        //$wp_playlist->populate_tracks();
        //$this->update_hatchet_playlist($wp_playlist);

    }
    
    function embed_playlist_widget($content){
        
        if (!class_exists('Hatchet')) return $content;
        
        if(!is_single()) return $content;
        if ( get_post_type()!=xspfpl()->station_post_type ) return $content;
        if (!xspfpl()->get_options('enable_hatchet')) return $content;

        if ($hatchet_id = self::get_hatchet_id()){
            if ($widget = xspfpl_get_widget_playlist()){
                $content .= $widget;
            }
        }
        
        return $content;
    }
    
    /*
     * Formats a XSPF Playlist (from our plugin) to a format Hatchet can read
     */
    
    function prepare_hatchet_playlist($wp_playlist){
        $playlist = new Hatchet_Playlist();
        $playlist->title = $wp_playlist->playlist_datas['title'];
        return $playlist;
    }
    
    
    /* Add a playlist or update it if it already exists on Hatchet*/
    
    function update_hatchet_playlist($wp_playlist){
        
        if (!$this->username || !$this->password) return;
        
        if (!$wp_playlist->post_id) return;
        
        $playlist = self::prepare_hatchet_playlist($wp_playlist);

        if ($hatchet_id = self::get_hatchet_id($wp_playlist->post_id) ){
            
            hatchet()->api->update_playlist($hatchet_id,$playlist);
            
        }else{
            
            $hatchet_id = hatchet()->api->add_playlist($wp_playlist);
            
            if (!is_wp_error($hatchet_id)){
                update_post_meta($wp_playlist->post_id, self::$meta_key_hatchet_id, $hatchet_id);
            }
        }
    }
    
    /**
     * Delete the hatchet playlist linked to a playlist post.
     * @param type $post_id
     */
    
    function delete_hatchet_playlist($post_id = false){
        
        if (!$this->username || !$this->password) return;
        
        if (!$post_id) $post_id = get_the_ID();
        
        if ( get_post_type( $post_id ) != xspfpl()->station_post_type ) return;

        if ($hatchet_id != self::get_hatchet_id($post_id) ) return;

        if ( !is_wp_error(hatchet()->api->delete_playlist($hatchet_id)) ){
            delete_post_meta( $post_id, self::$meta_key_hatchet_id );
        }
        
    }
    
}


new XSPFPL_Hatchet();


