<?php

/**
 * Class that allows to check / save Some stats for each playlist
 * Health : reliability of a playlist (allows to check its status)
 */

class XSPFPL_Playlist_Stats {
    

    var $interval; 
    
    
    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }

    function setup_globals() {
        $this->interval = 60 * 10;  //10 min - seconds before a new health entry can be saved
    }

    function includes(){
    }

    function setup_actions(){
        add_action('xspfpl_populate_tracks', array(&$this,'save_health_status'));
        add_action('xspfpl_get_xpls', array(&$this,'update_xpsf_request_count'));
    }
    
    function save_health_status($playlist){
        
        $metas[] = array();
        
        $post_id = $playlist->post_id;
        
        if ( get_post_status($post_id) != 'publish') return;
        
        
        $tracks_found = count($playlist->tracks);
        $time = current_time( 'timestamp' );
        
        $meta = array(
            'time'      => $time,
            'tracks'    => $tracks_found
        );
        
        //old entries
        $metas = get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_health, true);
        
        
        if (!empty($metas)){

            //skip if last entry <10min
            $last_previous_meta = end($metas);

            $limit = $time - $this->interval;
            if ($last_previous_meta['time'] > $limit){
                return;
            }

            //clean if period > ~24h
            //(may not be exact since a meta will not be especially saved at each perdiod,
            //so this is arbitrary
            $day = 60*60*24; //day in seconds
            $max_entries = $day / $this->interval;

            if (count($metas)>=$max_entries){
                $metas = array_slice($metas, 0, (int)$max_entries-1);
            }
            
            
        }

        //add new entry
        $metas[] = $meta;

        update_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_health, $metas);
           
    }
    
    function update_xpsf_request_count($playlist){
        $post_id = $playlist->post_id;
        
        if ( get_post_status($post_id) != 'publish') return;
        
        $count = (int)get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_requests, true);
        $count++;
        update_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_requests, $count);
    }
    
}

$stats = new XSPFPL_Playlist_Stats();
