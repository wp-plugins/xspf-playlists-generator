<?php

use PHPHtmlParser\Dom;

/**
 * Single playlist class.  The most important one !
 */

class XSPFPL_Single_Playlist {

    static $meta_key_settings = 'xspfpl_settings';
    var $meta_key_health = 'xspfpl_health';
    static $meta_key_requests = 'xspfpl_xspf_requests'; //TO FIX rename at upgrade
    static $meta_key_monthly_requests = 'xspfpl_monthly_requests';
    static $meta_key_monthly_requests_log = 'xspfpl_monthly_requests_log';
    private $frozen_cache = 'xspfpl_tracks_cache'; //frozen (permanent) cache key name

    var $post_id = null;
    
    var $playlist_data = null;
    
    var $request_url = null;
    var $response = null;
    var $feed_type = null;
    var $feed_body_node = null;
    var $response_time = null;
    
    var $track_nodes = null;
    var $is_wizard = false;  //special behaviour when wizard is enabled
    
    var $options = null;
    var $options_presets = null;
    var $variables = null;
    
    var $tracks = null;

    function __construct($post_id = null){

        $this->post_id = $post_id;
        $this->is_wizard = (is_admin());
        self::get_options();
        
    }

    static function get_default_options($keys = null){
        $default = array(
            'website_url'               => null, //url to parse
            'feed_url'                  => null,
            'variables'            => array(),
            'variable_title_suffix'     => null, //suffix using variables for service playlists
            'tracks_order'              => 'desc',
            'selectors' => array(
                'tracks'            => null,
                'track_artist'      => null,
                'track_title'       => null,
                'track_album'       => null,
                'track_image'       => null
            ),
            'selectors_regex' => array(
                'track_artist'      => null,
                'track_title'       => null,
                'track_album'       => null,
            ),
            'is_frozen'                 => false,                               //wheter or not playlist should be only parsed one time
            'cache_tracks_intval'       => xspfpl()->get_options('cache_tracks_intval'),
            'max_tracks'                => 20,                                  //max titles (if playlist is not frozen)
            'musicbrainz'               => false,                               //check tracks with musicbrainz
        );
        
        
        return xspfpl_get_array_value($keys,$default);
        
    }
    
    /**
     * Allow to use presets, see xspfpl-playlist-presets.php
     * Two filters are when fetching presets in the plugin  : 
     * 'xspfpl_playlist_pre_get_presets' and 'xspfpl_playlist_populated_get_presets'
     * @param type $key
     * @return boolean
     */
    
    function get_presets($keys = null){
        
        if ( $this->options_presets === null ) {
        
            $options = get_post_meta($this->post_id, XSPFPL_Single_Playlist::$meta_key_settings, true);

            $this->options_presets = apply_filters('xspfpl_get_playlist_presets',$this->options_presets,$options,$this);

        }
        
        return xspfpl_get_array_value($keys,$this->options_presets);
 
    }
    
    function populate_options(){
        
    }
    
    /**
     * Two filters are when fetching options in the plugin  : 
     * 'xspfpl_playlist_pre_get_options' and 'xspfpl_playlist_populated_get_options'
     * @param type $key
     * @return boolean
     */
    
    
    function get_options($keys = null){
        
        if ( $this->options === null ) {

            $default = self::get_default_options();

            if ($custom = get_post_meta($this->post_id, XSPFPL_Single_Playlist::$meta_key_settings, true)){
                $this->options = array_replace_recursive($default, $custom); //override with presets  
            }
            
            if ( $presets = $this->get_presets() ){
                $this->options = array_replace_recursive($this->options, $presets); //override with presets  
            }

            $this->options = apply_filters('xspfpl_get_playlist_options',$this->options,$this);

        }

        return xspfpl_get_array_value($keys,$this->options);
        
    }
    
    function get_playlist_datas($keys = null){
        
        if ( $this->playlist_data === null ) {
                      
            $datas = array(
                'title'     => get_post_field( 'post_title', $this->post_id ),
                'author'    => get_the_author_meta('user_nicename', get_post_field( 'post_author', $this->post_id ) )
            );

            if ( isset($datas['title']) && !is_admin() && $this->get_service_variables() && $this->is_playlist_ready() ) {
                $suffix = $this->get_options('variable_title_suffix');
                $datas['title'] .= ' '.$this->set_string_variables($suffix);
            }

            $this->playlist_data = apply_filters('xspfpl_get_playlist_datas',$datas,$this);

        }
        
        return xspfpl_get_array_value($keys,$this->playlist_data);
    }
    
    function get_variable_form_value($keys = null){

        if (isset($_REQUEST[xspfpl()->var_variables])){
            return xspfpl_get_array_value($keys,$_REQUEST[xspfpl()->var_variables]);
        }
    }
    
    function build_service_variable($slug,$variable = null){
        
        $value = null;
        
        $default = array(
            'system'        => false, //is computed backend, don't use value from form
            'slug'          => $slug,
            'label'         => $slug,
            'value'         => null,
            'desc'          => null,
        );

        $variable = wp_parse_args($variable,$default);
        
        if ( !$this->is_wizard && !$variable['system'] ){ //remove wizard value frontend, except if they are system vars
            $form_value = self::get_variable_form_value($slug);
            $variable['value'] = $form_value;
        }

        return $variable;

    }
    
    function get_service_variables($keys = null){

        if ($this->variables === null){

            $variables = (array)$this->get_options(array('variables'));
            $variables_slugs = $this->extract_urls_variables_slugs();

            $query_vars = get_query_var( xspfpl()->var_variables );

            //parse variables from url
            if ($variables_slugs){
                $extracted = array();
                foreach($variables_slugs as $variable_slug){
                    if (!array_key_exists($variable_slug, $variables)){
                        $extracted[$variable_slug] = null;
                    }
                }
                $variables = wp_parse_args($variables,$extracted);
            }

            foreach ((array)$variables as $slug=>$options){

                $variables[$slug] = $this->build_service_variable($slug,$options);

            }

            $variables = apply_filters('xspfpl_get_playlist_variables',$variables,$this);
            
            $this->variables = array_filter($variables);

        }

        return xspfpl_get_array_value($keys,$this->variables);

    }
    
    function get_missing_service_variables_slugs(){
        
        $missing_slugs = array();

        $variables = $this->get_service_variables();

        foreach ((array)$variables as $variable){
            if ($variable['value']) continue;
            $missing_slugs[] = $variable['slug'];
        }

        return $missing_slugs;

    }
    
    function is_playlist_ready(){
        if ( !$this->get_service_variables() || ( $this->get_service_variables() && !$this->get_missing_service_variables_slugs() ) ) return true;
        return false;
    }
    
    /**
     * Update string by replacing %variables% with service values.
     * @param type $string
     * @return type
     */
    
    function set_string_variables($string){

        $variables = $this->get_service_variables();

        foreach ($variables as $variable){

            $value = $variable['value'];
            
            $string = str_replace('%'.$variable['slug'].'%',$value,$string);
        }
        
        return $string;
        
    }

    /**
     * Parse request urls and get urls variables if any
     * @return type
     */

    function extract_urls_variables_slugs(){

        $variables = array();
        $regex = '/\%(.*?)\%/';
        
        if ( $website_url = $this->options['website_url'] ){
            
            preg_match_all($regex, $website_url, $matches);
            
            if (isset($matches[1])){
                $variables = array_merge($variables,$matches[1]);
            }
            
        }
        
        if ( $feed_url = $this->options['feed_url'] ){

            preg_match_all($regex, $feed_url, $matches);
            
            if (isset($matches[1])){
                $variables = array_merge($variables,$matches[1]);
            }
            
        }

        return array_unique($variables);
    }
    
    function get_feed_website_url(){
        
        if (!$url = $this->get_options('website_url')){
            $url = $this->get_options('feed_url');
        }

        if ( $variables = $this->get_service_variables() ) {
            $url = $this->set_string_variables($url);
        }
        
        return $url;

    }
    
    function get_feed_url(){
        
        if (!$this->request_url) {

            if (!$url = $this->get_options('feed_url')){
                $url = $this->get_options('website_url');
            }

            if ( $variables = $this->get_service_variables() ) {
                $url = $this->set_string_variables($url);
            }

            $this->request_url = $url;
            
        }

        return $this->request_url;

    }
    
    function get_feed(){
        
        $cache_response = null;
        
        if ($this->response === null) {

            //get feed url, check that playlist is ready to go.
            if ( ( $request_url = $this->get_feed_url() ) && $this->is_playlist_ready() ) {

                //try to get cached version of the page
                if ( ( $cache = $this->get_cache() ) && isset($cache['page']) ){

                    $cachefile_url = self::get_cache_page_url($cache['page']);
                    $cache_response = wp_remote_get( $cachefile_url );
                    $cache_response_code = wp_remote_retrieve_response_code( $cache_response );
                    
                }

                if ( $this->get_options('is_frozen') && $cache ) { //continue only if we don't have any cache
                    $this->response = false;
                    return;
                }

                if ( $cache_response && !is_wp_error($cache_response) && $cache_response_code==200 ){

                    $this->response = $cache_response;

                    if (is_admin()){
                        add_settings_error( 'wizard-step-base', 'cached_response', __("Response has been loaded from the cache.",'xspfpl'), 'updated' );
                    }
                    

                }else{
                    
                    $remote_args = apply_filters('xspfpl_get_response_args',xspfpl()->remote_get_options,$request_url );
                    $this->response = wp_remote_get( $request_url, $remote_args );
                    $this->response_time = current_time( 'timestamp' );

                }

            }else{
                $this->response = false;
                return;
            }

            if ( !is_wp_error($this->response) ){
                
                $response_code = wp_remote_retrieve_response_code( $this->response );

                if ($response_code != 200){
                    $response_message = wp_remote_retrieve_response_message( $this->response );
                    $this->response = new WP_Error( 'http_response_code', sprintf(__('Error %1$s %2$s while trying to reach the webpage %3$s','xspfpl'),'<strong>'.$response_code.'</strong>','<strong>'.$response_message.'</strong>','<em>'.$request_url.'</em>') );
                            
                }

                //force update options as some of them depends of the response

                $this->options_presets = null;
                $this->options = null;
                $this->playlist_data = null;
                $this->variables = null;

            }
            
        }

        return $this->response;
    }
    
    /**
     * Get response content-type, filtered by us
     * @return type
     */
    
    function get_feed_type(){
        
        if ($this->feed_type) return $this->feed_type;
        
        if ($response = $this->get_feed() ){
            
            if ( is_wp_error($response) ) return $response;

            $type = $response['headers']['content-type'];
            if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
                $type = 'application/json';
            }
            
            //remove charset if any
            $split = explode(';',$type);

            $this->feed_type = $split[0];

        }
        
        

        return $this->feed_type;
    }
    
    /**
     * Get response body, filtered by us
     * @return type
     */
    
    function get_feed_body_node(){
        
        if ($this->feed_body_node === null) {
        
            if ($response = $this->get_feed() ){

                if ( is_wp_error($response) ) return $response;

                $content = wp_remote_retrieve_body( $response );
                $content = trim(wp_remote_retrieve_body( $response ));
                
                $content = apply_filters('xspfpl_get_feed_body_node_pre',$content,$this);
                
                $type = $this->get_feed_type();
                
                //TO FIX CDATA FIX
                /*
                //$content = preg_replace('#<!\[CDATA\[(.*?)\]\]>#s', '$1', $content);

                echo"<xmp>";
                print_r($content);
                echo"</xmp>";
                echo"<br/>";
                print_r(strlen($content));
                */

                libxml_use_internal_errors(true);

                switch ($type){

                    case 'application/xml':
                    case 'text/xml':

                        //QueryPath
                        try{
                            $content = qp( $content, '*:first', xspfpl()->querypath_options );
                        }catch(Exception $e){
                            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
                        }
                        
                        


                    break;

                    case 'application/json':

                        try{
                            $craur = Craur::createFromJson( $content );
                        }catch(Exception $e){
                            return new WP_Error( 'craur', sprintf(__('Craur Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
                        }
                        $content = '<json>'.$craur->toXmlString().'</json>';
                       

                        //QueryPath
                        try{
                            $content = qp( $content, 'json', xspfpl()->querypath_options );
                        }catch(Exception $e){
                            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
                        }
                        

                    break;
                    default: //text/html


                        //QueryPath
                        try{
                            $content = htmlqp( $content, null, xspfpl()->querypath_options );
                        }catch(Exception $e){
                            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
                        }


                    break;
                }

                libxml_clear_errors();

                if ($content->length==0){
                    $this->feed_body_node = false;
                    return new WP_Error( 'feed_body_node', __( 'Unable to get the body node', 'xspfpl' ));
                }

                $this->feed_body_node = apply_filters('xspfpl_get_feed_body_node',$content,$this);
            }
            
        }
        
        return $this->feed_body_node;
        
    }
    
    //TO FIX
    function feed_is_xspf(){
        
        $content_type = $this->get_feed_type();

        if ( is_wp_error($content_type) ) return false;

        switch ($content_type){

            case 'application/xspf+xml':
            case 'text/xspf+xml':
                return true;
            break;
            case 'application/xml':
            case 'text/xml':

                if ( ($feed_body_node = $this->get_feed_body_node()) && !is_wp_error($feed_body_node) ){
                    
                    //TO FIX should improve code when querypath is updated ?
                    //https://github.com/technosophos/querypath/issues/167


                    //QueryPath
                    try{
                        if ( $feed_body_node->find('playlist tracklist track')->length > 0 ) return true;
                    }catch(Exception $e){
                        
                    }

                }
                
            break;
        }

        return false;

        
    }

    function get_track_nodes(){
        
        if ($this->track_nodes) return $this->track_nodes; 

        if ($feed_body_node = $this->get_feed_body_node()){

            
            if ( is_wp_error($feed_body_node) ) return $feed_body_node;

            $selectors = $this->get_options('selectors');

            if ( !$selectors['tracks'] ) return;

            //QueryPath
            try{
                $track_nodes = $feed_body_node->find($selectors['tracks']);
            }catch(Exception $e){
                return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
            }

            $this->track_nodes = $track_nodes;
            
        }

        return $this->track_nodes;

    }
    
    /**
     * Get unique key based on URL to set/get playlist transient.
     * WARNING this must be 40 characters max !  md5 returns 32 chars.
     * @return string
     */
    
    function get_cache_key(){
        $md5 = md5( $this->get_feed_url() );
        return 'xspfpl_'.$md5;
    }

    function get_cache(){ 
        
        if (!is_dir(xspfpl()->cache_dir)) return false;

        if ( !$this->get_options('is_frozen') ) { //regular cache, non persistant
            
            if ( !$this->get_options('cache_tracks_intval') ) return false;

            $transient_name = $this->get_cache_key();

            $cache = get_transient( $transient_name );
            
        }else{ //persistant
            
            $cache = get_post_meta($this->post_id, $this->frozen_cache, true);
            
        }

        if (isset($cache['response_time'])){
            $this->response_time = $cache['response_time']; //set response time to cache value
        }

        return $cache;

    }
    
    function get_tracks_cache(){
        if ( $this->is_wizard && ( !$this->get_options('is_frozen') ) ) return false; //no tracks cache for wizard, except for frozen playlists
        if ( !$cache = $this->get_cache() ) return false;
        return $cache['tracks'];
        
    }

    function update_cache($tracks){
        
        if ( $cache = $this->get_cache() ) return; //there's already a cache
        
        $cache = array(
            'response_time'     => current_time( 'timestamp' ),
            'tracks'            => $tracks
        );
        
        if ( !$this->get_options('is_frozen') ) { //regular cache, non persistant

            if ( !$cache_duration = $this->get_options('cache_tracks_intval') ) return;
            if ( $this->feed_is_xspf() ) return; //do not cache xspf files

            $transient_name = $this->get_cache_key();

            $cache['page'] = $this->update_cache_page(); //cache the requested page, too

            set_transient( $transient_name, $cache, $cache_duration );
            
        }else{ //persistant

            update_post_meta($this->post_id, $this->frozen_cache, $cache);
            
        }

    }
    
    function update_cache_page(){

        $filename = null;
        
        if (!is_dir(xspfpl()->cache_dir)) return false;

        $page = wp_remote_retrieve_body( $this->get_feed() );
        
        if (!$page || is_wp_error($page) ) return;
        
        if ($tmpfname = tempnam( xspfpl()->cache_dir , 'page_')){
            $handle = fopen($tmpfname, "w");
            fwrite($handle, $page);
            fclose($handle);
            $filename = basename($tmpfname);
        }

        return $filename;
        
        
    }
    
    static function get_cache_page_url($pagename){
        return trailingslashit( xspfpl()->cache_url ).$pagename;
    }

    static function delete_cache_page($pagename){
        
        if (!is_dir(xspfpl()->cache_dir)) return false;
        if (!$pagename) return false;
        
        $page_path = trailingslashit( xspfpl()->cache_dir ).$pagename;
        
        if (file_exists($page_path)){
            unlink($page_path);
        }
        
    }
    
    static function delete_cached_file_along_transient($option_name){
        
        //check this is a playlist cache transient by analyzing the option name
        $split = explode('_transient_xspfpl_',$option_name);
        if (!isset($split[1]) || (!$md5 = $split[1]) || !preg_match('/^[a-f0-9]{32}$/i', $md5) ) return;
        
        //populate the transient
        if ( (!$cache = get_option($option_name)) || !isset($cache['page']) ) return;

        self::delete_cache_page($cache['page']);
        
    }
    
    
    
    function delete_cache(){

        if ( !$this->get_options('is_frozen') ) { //regular cache, non persistant
        
            $transient_name = $this->get_cache_key();
            delete_transient( $transient_name );
            // an action is hooked on delete_option to remove the cache page when this transient is deleted.

        }else{ //persistant
            
            delete_post_meta($this->post_id, $this->frozen_cache);
            
        }
    }
    
    function get_raw_tracks(){

    	$tracks = array();
        
        
        $track_nodes = self::get_track_nodes(); //must be before the selectors check as requesting the response can modify get_options
        
        $selectors = $this->get_options('selectors');

        if ( (!$this->is_wizard) && (!$selectors['track_artist']) ){
            return false;
        }

        if ( (!$this->is_wizard) && (!$selectors['track_title']) ){
            return false;
        }

        if ( !$track_nodes || is_wp_error($track_nodes) ) return $track_nodes;

        // Get all tracks

        foreach($track_nodes as $key=>$track_node) {

            $newtrack = $this->new_track();

            //artist
            $newtrack['artist'] = apply_filters('xspfpl_get_track_artist',self::get_dom_element_content($track_node,'artist'));

            //title
            $newtrack['title'] = apply_filters('xspfpl_get_track_title',self::get_dom_element_content($track_node,'title'));

            //album
            $newtrack['album'] = apply_filters('xspfpl_get_track_album',self::get_dom_element_content($track_node,'album'));
            
            //album //TO FIX
            $newtrack['image'] = apply_filters('xspfpl_get_track_image',self::get_dom_element_content($track_node,'image'));


            $tracks[]=$newtrack;
        }

        //array unique
        $tracks = array_unique($tracks, SORT_REGULAR);

        
        return $tracks;
        
    }

    
    function get_tracks(){
        
        if ($this->tracks === null){
        
            $tracks = array();

            if ( !$tracks = $this->get_tracks_cache() ){

                if ( ($tracks = self::get_raw_tracks()) && !is_wp_error($tracks)){

                    
                    if ( !$this->is_wizard ){
                        
                        //keep only tracks having artist AND title
                        $tracks = array_filter(
                            $tracks,
                            function ($e) {
                                return ($e['artist'] && $e['title']);
                            }
                        );
     
                    }else{

                        //keep only tracks having artist OR title (Wizard)
                        $tracks = array_filter(
                            $tracks,
                            function ($e) {
                                return ($e['artist'] || $e['title']);
                            }
                        );
                        
                    }

                    //sort
                    if ($this->get_options('tracks_order') == 'asc'){
                        $tracks = array_reverse($tracks);
                    }

                    //limit live tracklist
                    //TO FIX needed ?
                    /*
                    if ( ($max_tracks = $this->get_options('max_tracks')) && (!$this->get_options('is_frozen')) && (!$this->is_wizard) ){
                        $tracks = array_slice($tracks, 0, (int)$max_tracks);
                    }
                     */

                    //some radios have bad metadatas
                    //try to correct them using musicbrainz.update_cache()
                    //ignore while running wizard (if the playlist is not frozen)
                    if ( ($this->get_options('musicbrainz')) && ( (!$this->is_wizard) || ($this->get_options('is_frozen')) ) ){

                        foreach ((array)$tracks as $key=>$track){
                            $tracks[$key] = xspfpl_musicbrainz_track_lookup($track);

                            if (count($tracks)>1){//delay of 1 sec
                                sleep(1);
                            }

                        }

                    }

                    $tracks = apply_filters( 'xspfpl_get_playlist_tracks',$tracks,$this );


                    $this->update_cache($tracks);

                    do_action( 'xspfpl_populated_playlist_tracks',$tracks, $this );

                }
                
                $this->update_health_status($tracks);

            }

            $this->update_track_request_count();
            $this->update_track_request_monthly_count();

            $this->tracks = $tracks;
            
        }
        
        return $this->tracks;
        
    }
    
    /**
     * Each time the tracks are requested for this playlist, update the meta.
     * @return type
     */
    
    function update_track_request_count(){
        
        if ( get_post_status($this->post_id) != 'publish') return;
        
        //total count
        $count = (int)get_post_meta($this->post_id, self::$meta_key_requests, true);
        $count++;
        
        //

        return update_post_meta($this->post_id, self::$meta_key_requests, $count);
    }
    
    /**
     * Update the number of tracks requests for the month, in two metas :
     * self::$meta_key_monthly_requests_log (array of entries with the timestamp tracks where requested)
     * self::$meta_key_monthly_requests (total requests)
     * @return type
     */
    
    function update_track_request_monthly_count(){

        if ( get_post_status($this->post_id) != 'publish') return;

        $log = array();
        $time = current_time( 'timestamp' );
        $time_remove = strtotime('-1 month',$time); 
        
        if ($existing_log = get_post_meta($this->post_id, self::$meta_key_monthly_requests_log, true)){ //get month log
            $log = $existing_log;
        }

        //remove entries that are too old from log metas (multiple)
        foreach ((array)$log as $key=>$log_time){
            if ($log_time <= $time_remove){
                unset($log[$key]);
            }
        }
        
        //update log
        $log[] = $time;
        update_post_meta($this->post_id, self::$meta_key_monthly_requests_log, $log);
        
        //avoid duplicates
        $log = array_filter($log);

        //update requests count
        $count = count($log);
        return update_post_meta($this->post_id, self::$meta_key_monthly_requests, $count );
    }
    

    
    /**
     * Each time the tracks are requested for this playlist, log the number of tracks fetched.
     * Meta will be used in 
     * @param type $tracks
     * @return type
     */
    
    function update_health_status($tracks){

        if ( get_post_status($this->post_id) != 'publish') return;

        $time = current_time( 'timestamp' );
        $log = get_post_meta($this->post_id, $this->meta_key_health, true);     //get health log
        $min_delay = 10 * MINUTE_IN_SECONDS; //save maximum once every 10 minutes

        if (!empty($log)){

            $last_previous_meta = end($log);
            $limit = $time - $min_delay;
            
            //do not save new entry if last one <10 minutes
            if ($last_previous_meta['time'] > $limit){
                return;
            }
            
        }
        
        //add new entry at the end of the log
        $log[] = array(
            'time'      => $time,
            'tracks'    => count( $tracks )
        );
        
        //limit log length
        $max_entries = DAY_IN_SECONDS / $min_delay; 
        $log = array_slice($log, 0, $max_entries);

        update_post_meta($this->post_id, $this->meta_key_health, $log);
    }

    function get_dom_element_content($track_node,$slug){
        
        $node = null;
        $result = null;
        $pattern = null;
        $string = null;
        
        $selectors = $this->get_options( 'selectors' );
        $selectors_regex = $this->get_options( 'selectors_regex' );

        //selector
        if ( isset($selectors['track_'.$slug]) && $selectors['track_'.$slug] ){
            $selector = $selectors['track_'.$slug];
        }else{
            return false;
        }

        //QueryPath
        try{
            $node = $track_node->find($selector);
            $string = $node->innerHTML();
            
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
        }

        if($slug == 'image'){

            if ($url = $node->attr('src')){ //is an image tag
                $string = $url;
            }

            if (filter_var((string)$string, FILTER_VALIDATE_URL) === false) {
                $string = '';
            }
            
        }

        //CDATA fix TO FIX should be at html load in get_feed_body_node()
        $string = preg_replace('#<!\[CDATA\[(.*?)\]\]>#s', '$1', $string);

        /*
        print_r("slug: ".$slug);echo"<br/>";
        print_r("<xmp>".$track_node->html()."</xmp>");echo"<br/>";
        print_r("selector: ".$selector);echo"<br/>";
        print_r("pattern: ".$pattern);echo"<br/>";
        print_r("string: ".$string);echo"<br/>";
        */

        //regex pattern
        if ( isset($selectors_regex['track_'.$slug]) && $selectors_regex['track_'.$slug] ){
            $pattern = $selectors_regex['track_'.$slug];
        }
        
        if(!$pattern) {
            $result = $string;
        }else{
            //flags
            $flags = 'm';
            //add delimiters
            $pattern = '~'.$pattern.'~'.$flags;
            //add beginning slash
            //$pattern = strrev($pattern); //reverse string
            //$pattern = trailingslashit($pattern);
            //$pattern = strrev($pattern);
            
            preg_match($pattern, $string, $matches);

            if (isset($matches[1]))
                $result = strip_tags($matches[1]);
        }
        
        return $result;
    }
    
    function new_track(){
        $track=array(
            'title'=>null,
            'artist'=>null,
            'album'=>null,
            'duration'=>null,
            'comments'=>array(),
            'image'=>null
        );
        return $track;
    }

    function get_xspf(){
        
        //xspf file
        if ( $this->feed_is_xspf() && !$this->get_options('is_frozen') ){ //direct link to XSPF
            $url = $this->get_feed_url();
            wp_redirect($url);die();
        }
    
    	error_reporting(E_ERROR | E_PARSE); //ignore warnings & errors

        do_action('xspfpl_get_xspf',$this);
        
        $tracks = $this->get_tracks(); //need to be up, at least to get response time

        ///RENDER XSPF
        $dom = new DOMDocument('1.0', get_bloginfo('charset') );
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        header("Content-Type: text/xml");

        // create playlist element
        $playlist_el = $dom->createElement("playlist");
        $playlist_el->setAttribute('xmlns', 'http://xspf.org/ns/0/');
        $playlist_el->setAttribute('version', '1');
        $dom->appendChild($playlist_el);

        // playlist title

        if ( $pl_title = $this->get_playlist_datas('title') ){

            $pl_title_el = $dom->createElement("title");
            $playlist_el->appendChild($pl_title_el);
            $pl_title_txt_el = $dom->createTextNode($pl_title);
            $pl_title_el->appendChild($pl_title_txt_el);
        }

        // playlist author
        if ( $author =  $this->get_playlist_datas('author') ){
            $pl_author_el = $dom->createElement("creator");
            $playlist_el->appendChild($pl_author_el);
            $pl_author_txt_el = $dom->createTextNode($author);
            $pl_author_el->appendChild($pl_author_txt_el);
        }
        
        //playlist info
        if ( $info = get_permalink($this->post_id) ){
            $pl_info_el = $dom->createElement("info");
            $playlist_el->appendChild($pl_info_el);
            $pl_info_txt_el = $dom->createTextNode($info);
            $pl_info_el->appendChild($pl_info_txt_el);
        }
        //playlist date
        $pl_date_txt_el = $dom->createTextNode( gmdate(DATE_ISO8601,$this->response_time) );
        $pl_date_el = $dom->createElement("date");
        $pl_date_el->appendChild($pl_date_txt_el);
        $playlist_el->appendChild($pl_date_el);
        
        //playlist location
        $pl_loc_txt_el = $dom->createTextNode( $this->get_feed_website_url() );
        $pl_loc_el = $dom->createElement("location");
        $pl_loc_el->appendChild($pl_loc_txt_el);
        $playlist_el->appendChild($pl_loc_el);
        
        //playlist annotation
        $pl_annot_el = $dom->createElement("annotation");
        $pl_annot_txt_el = $dom->createTextNode(sprintf(__('Station generated with the %1s Plugin by %2s.','xspfpl'),xspfpl()->name,xspfpl()->author));
        $pl_annot_el->appendChild($pl_annot_txt_el);
        $playlist_el->appendChild($pl_annot_el);

        // tracklist
        $pl_tracklist_el = $dom->createElement("trackList");
        $playlist_el->appendChild($pl_tracklist_el);

        //tracks

        foreach ((array)$tracks as $key=>$newtrack){

            $newtrack = array_filter($newtrack);

            $track_el = $dom->createElement("track");

            //title
            $track_title_el = $dom->createElement("title");
            $track_el->appendChild($track_title_el);
            $track_title_txt_el = $dom->createTextNode($newtrack['title']);
            $track_title_el->appendChild($track_title_txt_el);

            //artist
            $track_artist_el = $dom->createElement("creator");
            $track_el->appendChild($track_artist_el);
            $track_artist_txt_el = $dom->createTextNode($newtrack['artist']);
            $track_artist_el->appendChild($track_artist_txt_el);

            //album
            if (isset($newtrack['album'])){
                $track_album_el = $dom->createElement("album");
                $track_el->appendChild($track_album_el);
                $track_album_txt_el = $dom->createTextNode($newtrack['album']);
                $track_album_el->appendChild($track_album_txt_el);
            }

            //duration
            if (isset($newtrack['duration'])){
                $track_duration_el = $dom->createElement("duration");
                $track_el->appendChild($track_duration_el);
                $track_duration_txt_el = $dom->createTextNode($newtrack['duration']);
                $track_duration_el->appendChild($track_duration_txt_el);
            }

            //comment
            if (isset($newtrack['comments'])){
                $track_comment_el = $dom->createElement("annotation");
                $track_el->appendChild($track_comment_el);
                $track_comment_txt_el = $dom->createTextNode(implode(' | ',(array)$newtrack['comments']));
                $track_comment_el->appendChild($track_comment_txt_el);
            }


            //image
            if (isset($newtrack['image'])){
                $track_img = $dom->createElement("image");
                $track_el->appendChild($track_img);
                $track_img_txt = $dom->createTextNode($newtrack['image']);
                $track_img->appendChild($track_img_txt);
            }

            //info (hatchet.is link)
            if (class_exists('Hatchet')){
                $hatchet_url = hatchet_get_track_link($newtrack['artist'],$newtrack['title']);
                $track_info = $dom->createElement("info");
                $track_el->appendChild($track_info);
                $track_info_txt = $dom->createTextNode($hatchet_url);
                $track_info->appendChild($track_info_txt);
            }


            $pl_tracklist_el->appendChild($track_el);
        }
        
        //TO FIX 
        //we should remove the root XML node using
        //LIBXML_NOXMLDECL
        //but seems it is not supported yet or buggy
        //$rendered = $dom->saveXML(null,LIBXML_NOXMLDECL);
        //so we use this trick :
        
        $rendered = $dom->saveXML($dom->documentElement);

        echo $rendered;
        
        }

}

add_filter('xspfpl_get_track_artist','strip_tags');
add_filter('xspfpl_get_track_artist','urldecode');
add_filter('xspfpl_get_track_artist','htmlspecialchars_decode');
add_filter('xspfpl_get_track_artist','trim');

add_filter('xspfpl_get_track_title','strip_tags');
add_filter('xspfpl_get_track_title','urldecode');
add_filter('xspfpl_get_track_title','htmlspecialchars_decode');
add_filter('xspfpl_get_track_title','trim');

add_filter('xspfpl_get_track_album','strip_tags');
add_filter('xspfpl_get_track_album','urldecode');
add_filter('xspfpl_get_track_album','htmlspecialchars_decode');
add_filter('xspfpl_get_track_album','trim');

add_filter('xspfpl_get_track_albumart','strip_tags');
add_filter('xspfpl_get_track_albumart','urldecode');
add_filter('xspfpl_get_track_albumart','trim');


?>
