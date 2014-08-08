<?php

/**
 * Here's all the function you could need in your templates.
 */


/**
 * Get the XSPF link for a post.
 * @global type $post
 * @global type $wp_rewrite
 * @param type $post_id
 * @return type 
 */

function xspfpl_get_xspf_permalink($post_id=false){
    global $wp_rewrite;

    if(!$post_id) $post_id = get_the_ID();

    $post_permalink = get_permalink($post_id);

    if ($wp_rewrite->get_page_permastruct()){ //permalinks enabled
        $url = $post_permalink.xspfpl()->xpsf_render_var;
        $url = trailingslashit( $url );
    }else{
        $url = add_query_arg(array(xspfpl()->xpsf_render_var => true),$post_permalink);
    }


    return apply_filters('xspfpl_get_xspf_permalink',$url,$post_id);

}

/*
 * Checks if the playlist is still alive : each time tracks are populated,
 * A "health" meta is added with the time and number of tracks found.
 * If health fell to zero, maybe the playlist is no more alive.
 */

function xspfpl_get_health($post_id){
    
    //no health for static playlists
    if (XSPFPL_Single_Playlist::get_option('is_static', $post_id)) return false;
    if ( get_post_status($post_id) != 'publish') return false;

    $metas = get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_health, true);

    //no entries
    if ( empty($metas) ) return false;

    $total = count($metas);
    $health = 0;

    foreach ($metas as $meta){
        if ($meta['tracks'] == 0) continue;
        $health++;
    }

    $percent = ($health / $total)*100;

    return $percent;     

}

/**
 * Get the cache for a playlist
 * @param type $post_id
 * @return array('time'=>,'tracks'=>)
 */
function xspfpl_get_tracks_cache($post_id){
    return get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_tracks_cache, true);
}

/*
 * Display tracks in a table
 */

function xspfpl_tracks_table($tracks = false, $max_tracks = false, $hide_header = false){
    echo xspfpl_get_tracks_table($tracks, $max_tracks, $hide_header);
}

function xspfpl_get_tracks_table($tracks = false, $max_tracks = false, $hide_header = false){
    
    $header_style='';
    $track_number_style='';
    $tabletracks = '';
    $count=0;
    
    if (empty($tracks)) return;
    
    if (is_int($max_tracks)){
        $tracks = array_slice($tracks,-$max_tracks,$max_tracks);
    }

    //header
    if ($hide_header) $header_style=' style="display:none;"';
    
    //one single track
    if (count($tracks) == 1){
        $track_number_style=' style="display:none;"';
    }
    
    //tracks
    foreach ((array)$tracks as $track){
        $count++;
        $artist = '';   if (isset($track['artist']))    $artist = $track['artist'];
        $title = '';    if (isset($track['title']))     $title = $track['title'];
        $album = '';   if (isset($track['album']))     $album = $track['album'];
        
        $tabletracks.= sprintf('<tr class="tracks-table-row"><td class="track-number"%1$s>%2$s</td><td class="track-artist">%3$s</td><td class="track-title">%4$s</td><td class="track-album">%5$s</td></tr>',
                $track_number_style,
                $count,
                $artist,
                $title,
                $album
            );
    }
    
    $table = sprintf('<table class="tracks-table"><tr class="tracks-table-header"%1$s><th class="track-number"%2$s></td><th class="track-artist">'.__('Artist','xspfpl').'</th><th class="track-title">'.__('Title','xspfpl').'</th><th class="track-album">'.__('Album','xspfpl').'</th></tr>%3$s</table>',
        $header_style,
        $track_number_style,
        $tabletracks
    );
    
    return $table;
}

/**
 * Returns a table containing the X last tracks
 * @param type $post_id
 * @param type $max_tracks
 * @param type $hide_header
 * @return string
 */

function xspfpl_get_last_cached_tracks($post_id = false, $max_tracks = false, $hide_header = false){
    
    $output = null;
    if (!$post_id) $post_id = get_the_ID();
    $cache = xspfpl_get_tracks_cache($post_id);
    
    if (!isset($cache['tracks'])) return;
    $tracks = $cache['tracks'];
    
    return xspfpl_get_tracks_table($tracks, $max_tracks, $hide_header);

}

function xspfpl_get_last_cached_track($post_id = false){
    return xspfpl_get_last_cached_tracks($post_id,1, true);
}

/*
 * Get the number of time an XSPF file has been requested
 */

function xspfpl_get_xspf_request_count($post_id = false){
    if (!$post_id) $post_id = get_the_ID();
    if ( get_post_status($post_id) != 'publish') return false;
    return (int)get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_requests, true);
}

function xspf_classes($array){
    echo xspf_get_classes($array);
}

function xspf_get_classes($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}

function xspf_get_hatchet_id($post_id = false){
    if (!$post_id) $post_id = get_the_ID();
    $id = get_post_meta( $post_id,XSPFPL_Hatchet::$meta_key_hatchet_id,true );
    return hatchet_sanitize_id($id);
}

function xspfpl_get_widget_playlist($post_id = false){

    if (!class_exists('Hatchet')) return false; // Hatchet plugin needed

    if (!$post_id) $post_id = get_the_ID();
    
    if ($hatchet_id = xspf_get_hatchet_id()){
            $widget_options = array(
                'hatchet_id'=> $hatchet_id
            );

            $widget = new Hatchet_Widget_Playlist($widget_options);
            return $widget->get_html();
    }

}

?>
