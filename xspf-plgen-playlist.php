<?php

class xspf_plgen_playlist {
    var $post_id;
    var $playlist_title;
    var $playlist_author;
    var $tracklist_url;
    var $tracks_selector;
    var $track_artist_selector;
    var $track_artist_regex;
    var $track_title_selector;
    var $track_title_regex;
    var $track_album_selector;
    var $track_album_regex;
    var $track_album_art_selector;
    var $track_comment_selector;
    var $max_tracks;
    var $musicbrainz;
    var $tomahk_embed;
    var $xspf_link;
    var $playlist_info;

    var $tracks = array(); //tracks found
    
    var $errors;
    var $body_el;
    var $is_wizard = false; //special behaviour when wizard is enabled
    
    static $meta_key_settings = 'xspf_plgen_settings';

    function __construct($args = false){ //args or post id
        
        $this->errors = new WP_Error();

        if ( (isset($args))&&(is_numeric($args)) ){ //is a post ID
            $this->post_id = $args;
            $args = self::get_post_metas($this->post_id);
           
        }
        
        $default = self::get_default_args();
        $args = wp_parse_args($args,$default);

        //regexes
        $args['track_artist_regex'] = stripslashes($args['track_artist_regex']);
        $args['track_title_regex'] = stripslashes($args['track_title_regex']);
        $args['track_album_regex'] = stripslashes($args['track_album_regex']);

        $args = apply_filters('xspf_plgen_parser_args',$args);

        foreach ($args as $slug=>$arg){
            $this->$slug = trim($arg);
        }

    }
    
    static function get_default_args(){
        $default = array(
            'tracklist_url'             => null, //url to parse
            'tracks_selector'           => null,
            'track_artist_selector'     => null,
            'track_artist_regex'        => null,
            'track_title_selector'      => null,
            'track_title_regex'         => null,
            'track_album_selector'      => null,
            'track_album_regex'         => null,
            'track_album_art_selector'  => null,
            'track_comment_selector'    => null,
            'max_tracks'                => 15,   //max titles
            'musicbrainz'               => false,   //check tracks with musicbrainz
            'tomahk_embed'              => true,
            'xspf_link'                 => true,
        );
        return $default;
    }
    
    function get_post_metas($post_id){
        
        $post = get_post($post_id);
        
        $default = self::get_default_args();
        $metas = get_post_meta($post_id, self::$meta_key_settings, true);

        $post_args = wp_parse_args($metas,$default);

        //pl title
        $post_args['playlist_title'] = get_the_title($post_id);
        //pl author
        $post_args['playlist_author'] = get_the_author_meta('user_nicename', $post->post_author );
        //pl info
        $post_args['playlist_info'] = get_permalink($post_id);
        
        
        
        return $post_args;
    }
    
    /**
     * Select the requested documents
     * @return boolean 
     */
    
    function get_body_el(){
        //URL 
        
        if (!$this->tracklist_url){
            $this->errors->add( 'tracklist_url_empty', __('The tracklist URL is empty.','xspf-plgen') );
            return false;
        }
        
        
        //check is correct url
        if (!filter_var($this->tracklist_url, FILTER_VALIDATE_URL)){
            $this->errors->add( 'tracklist_url', __('The tracklist URL is invalid.','xspf-plgen') );
            return false;
        }

        //get page
        
        //allows us to filter the parameters depending on the URL
        $remote_args = apply_filters('xspf_plgen_get_page_args',array(),$this->tracklist_url);
        $response = wp_remote_get( $this->tracklist_url, $remote_args );
        
        if (is_wp_error($response)){
            $this->errors->add( 'tracklist_page_empty', __('There was an error fetching the content from this URL.','xspf-plgen') );
            return false;
        }
        
        $page_doc = phpQuery::newDocumentHTML($response['body']);
        $body_el = $page_doc->find('body');
        if (!$body_el){
            $this->errors->add( 'tracklist_page_empty', __('There was an error fetching the content from this URL.','xspf-plgen') );
            return false;
        }

        return $body_el;
    }
    
    /**
     * Select all the tracks items
     * @return boolean 
     */
    
    function get_tracklist(){
        
        if (!$this->body_el){ //first time called
            $this->body_el = self::get_body_el();
        }
        
        if(!$this->body_el){
            return false;
        }

        phpQuery::selectDocument($this->body_el);
        
        if (!$this->tracks_selector){
            $this->errors->add( 'tracks_selector_empty', __('The tracks selector is empty','xspf-plgen') );
            return false;
        }

        $tracklist_el = pq($this->tracks_selector);

        //check tracklist is found
        if (!$tracklist_el->htmlOuter()){
            $this->errors->add( 'tracks_selector', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','xspf-plgen') );
            return false;
        }

        return $tracklist_el;
        
    }
    
    function populate_tracks(){
        
        $this->tracks = self::get_tracks();

        do_action('xspf_plgen_populate_tracks',$this);
    }

    
    function get_tracks(){
        
        $tracks = array();

        // if last call to get_tracks() is <2min, 
        // return cached tracks

        if ( (!$this->is_wizard) && ($this->post_id) ){
            
            $cachemeta =  xspf_plgen_get_tracks_cache($this->post_id);
            
            if ( isset($cachemeta['time']) && isset($cachemeta['tracks'] ) ){

                $limit = current_time( 'timestamp' ) - $cachemeta['time'];
                if (xspf_plgen()->cache_tracks_intval > $limit) $tracks = $cachemeta['tracks'];
                
            }

        }

        if (empty($tracks)){

            if ( (!$this->is_wizard) && (!$this->track_artist_selector) ){
                $this->errors->add( 'tracks_selector_empty', __('The track artist selector is empty','xspf-plgen') );
                return false;
            }

            if ( (!$this->is_wizard) && (!$this->track_title_selector) ){
                $this->errors->add( 'tracks_selector_empty', __('The track title selector is empty','xspf-plgen') );
                return false;
            }

            $tracklist_items = self::get_tracklist();
            
            

            if ($tracklist_items){

                
                // Get all tracks
                foreach($tracklist_items as $key=>$track) {

                    $newtrack = $this->new_track();
                    
                    //artist
                    $newtrack['artist'] = strip_tags(self::get_dom_element_content($track,'artist'));

                    //title
                    $newtrack['title'] = strip_tags(self::get_dom_element_content($track,'title'));

                    //album
                    if($this->track_album_selector)
                        $newtrack['album'] = strip_tags(self::get_dom_element_content($track,'album'));

                    //picture
                    if($this->track_album_art_selector){
                        $url = pq($track)->find($this->track_album_art_selector)->attr('src');
                        if(filter_var($url, FILTER_VALIDATE_URL)){
                            $newtrack['image'] = $url;
                        }
                    }


                    //comment
                    /*
                    $time = pq($track)->find($this->track_album_art_selector)->htmlOuter();
                    $newtrack['comments'][] = sprintf('played %s', $time); //x min ago
                     */

                    //format all

                    $tracks[]=$newtrack;
                }

            }

            //clean inputs
            foreach ((array)$tracks as $key=>$track){
                $tracks[$key] = $this->format_trackinfo($track);
            }

            //remove songs where there is no artist+title
            if (!$this->is_wizard){
                foreach ((array)$tracks as $key=>$track){

                    if(!$track['artist'] || !$track['title']){
                        unset($tracks[$key]);
                    }
                }
            }

            //array unique
            $tracks = array_unique($tracks, SORT_REGULAR);

            //limit tracklist
            if ($this->max_tracks){
                $tracks = array_slice($tracks, 0, (int)$this->max_tracks);
            }

            //some radios have bad metadatas
            //try to correct them using musicbrainz.
            //ignore while running wizard.
            if ((!$this->is_wizard) && ($this->musicbrainz)){

                foreach ((array)$tracks as $key=>$track){
                    $tracks[$key] = $this->musicbrainz_lookup($track);

                    if (count($tracks)>1){//delay of 1 sec
                        sleep(1);
                    }

                }

            }

            //save time & tracks
            if ($this->post_id){
                $cachemeta = array(
                    'time'      => current_time( 'timestamp' ),
                    'tracks'    => $tracks
                );
                update_post_meta($this->post_id, xspf_plgen()->cache_tracks_key, $cachemeta);
            }
            
        }

        return apply_filters('xspf_plgen_tracks',$tracks);
        
    }
    
    function get_dom_element_content($track,$slug){
        
        $result = '';
        
        $selector_var_name='track_'.$slug.'_selector';
        $regex_var_name='track_'.$slug.'_regex';

        //selector
        $selector = $this->$selector_var_name;
        //regex pattern
        $pattern = $this->$regex_var_name;

        //get string
        $string = pq($track)->find($selector)->htmlOuter();

        if(!$pattern) {
            $result = $string;
        }else{
            //flags
            $flags = 'm';
            //add trailing slash
            $pattern = '~'.$pattern.'~'.$flags;
            //add beginning slash
            //$pattern = strrev($pattern); //reverse string
            //$pattern = trailingslashit($pattern);
            //$pattern = strrev($pattern);
            
            preg_match($pattern, $string, $matches);

            if (isset($matches[1]))
                $result = strip_tags($matches[1]);
        }
        
        return apply_filters('xspf_plgen_dom_element_content',$result,$slug,$track,$selector,$pattern);
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
    
    function musicbrainz_lookup($track){
        //abord
        if((!isset($track['artist'])) || (!isset($track['title']))) return $track;

        //BUILD QUERY
        $mzb_args['query'] = '"'.$track['title'].'"';
        $mzb_args['artist'] = '"'.$track['artist'].'"';
        
        
        if(!isset($track['album'])){
            $track['album']='';
        }
        
        $track['comments'][] = sprintf('original query: artist="%1$s",title="%2$s",album="%3$s" (ignored)',$track['artist'],$track['title'],$track['album']);

        $mzb_url = 'http://www.musicbrainz.org/ws/2/recording?fmt=json&query=';
        
        //args
        
        $mzb_args = '"'.$track['title'].'" AND artist:"'.$track['artist'].'"';
        
        /*ignore album
        if(!empty($track['album'])){
           $mzb_args.=' AND release:"'.$track['album'].'"'; 
        }
         */
        
        //clear url
        //print_r($mzb_url.$mzb_args);
        
        $mzb_args = urlencode($mzb_args);

        
        $results = file_get_contents($mzb_url.$mzb_args);
        $results = json_decode($results);

        if (!$results->count){
            $track['comments'][] = 'musicbrainz: not found';
            return $track;
        }
        
        //WE'VE GOT A MATCH !!!
        $match = $results->recording[0];

        $track['comments'][] = sprintf('musicbrainz: id=%1$s, score=%2$s',$match->id,$match->score);
        
        //check score is high enough
        if($match->score<70) return $track;

        //title
        $new_track['title'] = $match->title;
        
        //length
        if(isset($match->length))
            $new_track['duration'] = $match->length;
        
        //artist
        $artists = $match->{'artist-credit'};
        
        foreach($artists as $artist){
            $obj = $artist->artist;
            $artists_names_arr[]=$obj->name;
        }
        $new_track['artist'] = implode(' & ',$artists_names_arr);
        
        //album
        $albums = $match->releases;

        if(isset($albums[0])){
            $new_track['album'] = $albums[0]->title;
        }

        //comment
        $new_track['comments'] = $track['comments'];

        return $new_track;
    }
    
    function get_xpls(){
        
        $this->populate_tracks();
        
        do_action('xspf_plgen_get_xpls',$this);


        if(empty($this->tracks)) { //display error message inside playlist
            
            $this->tracks[] = array(
                'artist'    => xspf_plgen()->name,
                'title'     => sprintf(__('Error : no tracks found / we were unable to parse the tracks for "%s"',"xspf_plgen"),$this->playlist_title)
            );

        }
        

        ///RENDER XSPF

            

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        header("Content-Type: text/plain");

        // create playlist element
        $playlist_el = $dom->createElement("playlist");
        $playlist_el->setAttribute('xmlns', 'http://xspf.org/ns/0/');
        $playlist_el->setAttribute('version', '1');
        $dom->appendChild($playlist_el);

        // playlist title
        $pl_title = $this->playlist_title;

        if ($pl_title){
            if (isset($this->limit))
                $pl_title = sprintf('%s (%s)', $pl_title, $this->limit); //add limit in playlist title

            $pl_title_el = $dom->createElement("title");
            $playlist_el->appendChild($pl_title_el);
            $pl_title_txt_el = $dom->createTextNode($pl_title);
            $pl_title_el->appendChild($pl_title_txt_el);
        }
        


        // playlist author
        if ($this->playlist_author){
            $pl_author_el = $dom->createElement("creator");
            $playlist_el->appendChild($pl_author_el);
            $pl_author_txt_el = $dom->createTextNode($this->playlist_author);
            $pl_author_el->appendChild($pl_author_txt_el);
        }
        
        //playlist info
        if ($this->playlist_info){
            $pl_info_el = $dom->createElement("info");
            $playlist_el->appendChild($pl_info_el);
            $pl_info_txt_el = $dom->createTextNode($this->playlist_info);
            $pl_info_el->appendChild($pl_info_txt_el);
        }
        
        //playlist annotation

        $pl_annot_el = $dom->createElement("annotation");
        $playlist_el->appendChild($pl_annot_el);
        $pl_annot_txt_el = $dom->createTextNode(sprintf(__('Playlist generated with the %1s Plugin by %2s on %3s at %4s.  Original playlist URL : %5s','thl-plp'),xspf_plgen()->name,xspf_plgen()->author,date(get_option('date_format')),date(get_option('time_format')),$this->tracklist_url));
        $pl_annot_el->appendChild($pl_annot_txt_el);

        // tracklist
        $pl_tracklist_el = $dom->createElement("trackList");
        $playlist_el->appendChild($pl_tracklist_el);

        //tracks
        foreach ((array)$this->tracks as $key=>$newtrack){
            
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
            
            //info (toma.hk link)
            $tomahk_url = 'http://toma.hk/?artist='.urlencode($newtrack['artist']).'&title='.urlencode($newtrack['title']);
            $track_info = $dom->createElement("info");
            $track_el->appendChild($track_info);
            $track_info_txt = $dom->createTextNode($tomahk_url);
            $track_info->appendChild($track_info_txt);

            $pl_tracklist_el->appendChild($track_el);
        }
        
        $rendered = $dom->saveXML();
        echo $rendered;
        
        }
        
        function format_trackinfo($track){
            
            //comments is an array
            foreach ((array)$track['comments'] as $key=>$string){
                $new_track['comments'][$key] = $this->format_input_string($string);
            }
            
            
            
            unset($track['comments']);
            
            //others

            foreach ((array)$track as $key=>$string){
                $new_track[$key]=$this->format_input_string($string);
            }

            return $new_track;
        }
        
        function format_input_string($value){
            $value = strip_tags($value);
            $value = trim($value);
            $value = urldecode($value);
            
            return $value;
        }

    
}

?>
