<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Each xspf_plgen_playlist::time get_tracks() is called; 
 * Check if tracks are found and save the result;
 * So we are able to check the reliability of a playlist.
 *
 * @author gordie
 */
class xspf_plgen_stats {
    
    static $health_key = 'xspf_plgen_health';
    static $request_count_key = 'xspf_plgen_xspf_request_count';
    var $interval; 
    var $column_name;
    
    
    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }

    function setup_globals() {
        $this->column_name_health = 'xspf_plgen_health';
        $this->column_name_last_track = 'xspf_plgen_last_track';
        $this->column_name_loads = 'xspf_plgen_loads';
        $this->interval = 60 * 10;  //10 min - seconds before a new health entry can be saved
    }

    function includes(){
    }

    function setup_actions(){
        add_filter('manage_posts_columns', array(&$this,'post_column_register'), 5);
        add_action('manage_posts_custom_column', array(&$this,'post_column_content'), 5, 2);
        add_action('xspf_plgen_populate_tracks', array(&$this,'save_health_status'));
        add_action('xspf_plgen_get_xpls', array(&$this,'update_xpsf_request_count'));
    }

    function post_column_register($defaults){
        
        if ( get_post_type() != xspf_plgen()->post_type) return;
        
        $defaults[$this->column_name_last_track] = __('Last track','xspf_plgen');
        $defaults[$this->column_name_health] = __('Health','xspf_plgen');
        $defaults[$this->column_name_loads] = __('XSPF requests','xspf_plgen');
        return $defaults;
    }
    function post_column_content($column_name, $post_id){
        
        if ( get_post_type() != xspf_plgen()->post_type) return;
        
        $output = '&ndash;';
        
        switch ($column_name){
            
            //health
            case $this->column_name_health:
                $percentage = xspf_plgen_get_health($post_id);
                if ($percentage === false){

                }else{
                    $output = sprintf('%d %%',$percentage);
                }
            break;
            
            //last track
            case $this->column_name_last_track:

                if ($last_track = xspf_plgen_get_last_track($post_id)){
                    $output = $last_track;
                }
                
            break;
            
            //loaded
            case $this->column_name_loads:
                $output = xspf_plgen_get_xspf_request_count($post_id);
            break;
        
        }
        
        echo $output;
    }
    
    function save_health_status($playlist){
        
        $metas[] = array();
        
        $post_id = $playlist->post_id;
        $tracks_found = count($playlist->tracks);
        $time = current_time( 'timestamp' );
        
        $meta = array(
            'time'      => $time,
            'tracks'    => $tracks_found
        );
        
        //old entries
        $metas = get_post_meta($post_id, self::$health_key, true);
        
        
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

        update_post_meta($post_id, self::$health_key, $metas);
           
    }
    
    function update_xpsf_request_count($playlist){
        $post_id = $playlist->post_id;
        $count = (int)get_post_meta($post_id, self::$request_count_key, true);
        $count++;
        update_post_meta($post_id, self::$request_count_key, $count);
    }
    
}

$stats = new xspf_plgen_stats();
