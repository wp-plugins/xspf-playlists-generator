<?php

class XSPFPL_Admin {

    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }

    function setup_globals() {
        $this->column_name_synced = 'xspfpl_synced';
        $this->column_name_health = 'xspfpl_health';
        $this->column_name_last_track = 'xspfpl_last_track';
        $this->column_name_requests_count = 'xspfpl_loads';
    }

    function includes(){
        
    }

    function setup_actions(){

        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );
        add_filter('manage_posts_columns', array(&$this,'post_column_register'), 5);
        add_action('manage_posts_custom_column', array(&$this,'post_column_content'), 5, 2);

    }

    /*
     * Scripts for backend
     */
    public function scripts_styles($hook) {
        if( ( get_post_type()!=xspfpl()->station_post_type ) && ($hook != 'playlist_page_xspfpl-options') ) return;
        wp_enqueue_style( 'xspfpl-admin', xspfpl()->plugin_url .'_inc/css/admin.css', array(), xspfpl()->version );
    }
    

    function post_column_register($defaults){

        if ( get_post_type() != xspfpl()->station_post_type) return $defaults;
        
        //split at title
        
        $before = array();
        $after = array();
        
        $after[$this->column_name_last_track] = __('Last track','xspfpl');
        $after[$this->column_name_health] = __('Live','xspfpl');
        $after[$this->column_name_requests_count] = __('Requests','xspfpl').'<br/><small>'.__('month','xspfpl').'/'.__('total','xspfpl').'</small>';
        $after[$this->column_name_synced] = '';
        
        $defaults = array_merge($before,$defaults,$after);
        
        return $defaults;
    }
    function post_column_content($column_name, $post_id){
        
        if ( get_post_type() != xspfpl()->station_post_type) return;
        
        $output = '';
        
        switch ($column_name){
            
            //health
            case $this->column_name_health:
                $percentage = xspfpl_get_health_status();
                if ($percentage === false){

                }else{
                    $output = sprintf('%d %%',$percentage);
                }
            break;
            
            //last track
            case $this->column_name_last_track:

                if ( $last_track = xspfpl_get_last_track() ){
                    $output = $last_track;
                }
                
            break;
            
            //loaded
            case $this->column_name_requests_count:
                $total = xspfpl_get_track_request_count();
                $month = xspfpl_get_track_request_monthly_count();
                
                $output = $month.'/'.$total;
                
            break;
        
            //live icon
            case $this->column_name_synced:
                if ( !$is_frozen = xspfpl_is_frozen_playlist() ){
                    $output = '<div class="dashicons dashicons-rss"></div>';
                }else{
                    $output = '<div class="dashicons dashicons-rss is-frozen"></div>';
                }
            break;
        
        
        }
        
        echo $output;
    }

}

new XSPFPL_Admin();

?>
