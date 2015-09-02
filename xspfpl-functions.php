<?php

function xspfpl_get_playlist($post_id = null){
    global $post;
    
    //backend
    if (!$post_id && is_admin()){
        
        if ( isset($_REQUEST['post']) ){
            $post_id = $_REQUEST['post'];
        }elseif ( isset($_REQUEST['post_ID']) ){
            $post_id = $_REQUEST['post_ID'];
        }
        
    }

    if ($post_id){
        if ( !$post || ($post_id != $post->ID) ){
            $post = get_post($post_id);
        }
    }

    if (!$post) return false;
    if ( get_post_type($post) != xspfpl()->station_post_type ) return false;

    if ( !property_exists($post,'playlist') ){
        $playlist = new XSPFPL_Single_Playlist($post->ID);
        $post->playlist = $playlist;
    }

    return $post->playlist;

}

function xspfpl_musicbrainz_track_lookup($track){
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
    if(isset($match->releases)){
        $albums = $match->releases;

        if(isset($albums[0])){
            $new_track['album'] = $albums[0]->title;
        }
    }


    //comment
    $new_track['comments'] = $track['comments'];

    return $new_track;
}

/**
 * Get a value in a multidimensional array
 * http://stackoverflow.com/questions/1677099/how-to-use-a-string-as-an-array-index-path-to-retrieve-a-value
 * @param type $keys
 * @param type $array
 * @return type
 */

function xspfpl_get_array_value($keys = null, $array){
    
    if (!$keys) return $array;
    if (is_string($keys) && isset($array[$keys])) return $array[$keys];
    
    if (!isset($array[$keys[0]])) return false;

    if(count($keys) > 1) {
        return xspfpl_get_array_value(array_slice($keys, 1), $array[$keys[0]]);
    }else{
        return $array[$keys[0]];
    }

}