<?php


/**
 * Get the XSPF link for a post.
 * @global type $post
 * @global type $wp_rewrite
 * @param type $post_id
 * @return type 
 */

function xspfpl_get_xspf_permalink($post_id=false){
    global $wp_rewrite;

    if(!$post_id){
        $post_id = get_the_ID();
    }

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

function xspfpl_get_last_track($post_id = false){
    
    $output = null;
    
    if (!$post_id) $post_id = get_the_ID();
    
    $cache = xspfpl_get_tracks_cache($post_id);

    if (isset($cache['tracks'])){
        $last_track = end($cache['tracks']);
        if (isset($last_track['title']) && isset($last_track['artist'])){
            $output = sprintf(__('<span class="track-title">%1$s</span> by <span class="track-artist">%2$s</span>','xspfpl'),$last_track['title'],$last_track['artist']);
        }
    }
    
    return apply_filters('xspfpl_get_last_track',$output,$post_id);

}

/*
 * Get the number of time an XSPF file has been requested
 */

function xspfpl_get_xspf_request_count($post_id = false){
    if (!$post_id) $post_id = get_the_ID();
    return (int)get_post_meta($post_id, XSPFPL_Single_Playlist::$meta_key_requests, true);
}



/**
 * Get the Toma.hk ID of the playlist from the post metas.
 * If the post meta do not exists, find the playlist ID and save it.
 * @global type $post
 * @param type $post_id
 * @return type 
 */

function xspfpl_get_tomahk_playlist_id($post_id=false,$force=false){

    if(!$post_id){
        $post_id = get_the_ID();
    }

    $playlist_id = get_post_meta($post_id,'toma_hk_id', true);

    if ((!$playlist_id)||($force)){ //send XSPF to toma.hk and get playlist ID back
        
        //$playlist = new XSPFPL_Single_Playlist($post_id);
        //$tracks = $playlist->populate_tracks();
        //if(!$tracks) return false;
        

        $link = xspfpl_get_xspf_permalink($post_id);

        $tomahk_import_link = 'http://toma.hk/importPlaylistXSPF.php';
        $tomahk_import_args =array(
            'xspf'=>urlencode($link),
            //'title'=>get_the_title()
        );

        $tomahk_import_link = add_query_arg($tomahk_import_args,$tomahk_import_link);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $tomahk_import_link);
        curl_setopt($curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $return = curl_exec($curl);
        $info = curl_getinfo($curl); //Some information on the fetch
        curl_close($curl);

        $redirect_url = $info['redirect_url'];

        if ($redirect_url){
            $redirect_split = explode('http://toma.hk/p/',$redirect_url);
            $playlist_id = $redirect_split[1];

            if($playlist_id){ //save it
                update_post_meta($post_id,'toma_hk_id', $playlist_id);
            }

        }


    }

    return apply_filters('xspfpl_get_tomahk_playlist_id',$playlist_id,$post_id);


}

/**
 * Get the Toma.hk playlist link for a post.
 * @param type $post_id
 * @return type 
 */

function xspfpl_get_tomahk_playlist_link($post_id=false){

    $playlist_id = xspfpl_get_tomahk_playlist_id($post_id);
    if (!$playlist_id) return false;
    
    $playlist_url = 'http://toma.hk/p/'.$playlist_id;

    return apply_filters('xspfpl_get_tomahk_playlist_link',$playlist_url,$playlist_id,$post_id);

}

/**
 * Get the playlist saved on Toma.hk for a post.
 * @param type $post_id
 * @return boolean 
 */

function xspfpl_get_tomahk_playlist($post_id=false){
    
    if (!xspfpl_is_local_wp()){ //not local
        $tomahk_id = xspfpl_get_tomahk_playlist_id($post_id);
    }else{ //for testing
        //$tomahk_id = 'adi2222M';
    }

    $tomahk_iframe_args = array(
        'width'=>550,
        'height'=>430
    );
    $tomahk_iframe_args = apply_filters('tomahk_plp_iframe_args',$tomahk_iframe_args);



    if (!xspfpl_is_local_wp()&&(!$tomahk_id)) return false;

    $url = 'http://toma.hk/p/'.$tomahk_id;
    $url_args['embed'] = 'true';
    $url = add_query_arg($url_args,$url);

    ob_start();
    ?>
    <iframe src="<?php echo $url;?>" width="<?php echo $tomahk_iframe_args['width'];?>" height="<?php echo $tomahk_iframe_args['height'];?>" scrolling="no" frameborder="0" allowtransparency="true" ></iframe>
    <?php
    $content = ob_get_contents();
    
    if (xspfpl_is_local_wp()){ //local
        //we will not be able to send XSPF playlist to tomahawk if its url is local; abord
        $content.= '<p>('.__('Toma.hk embeds do not work on local Wordpress installations','thk-pl').')</p>';
    }
    
    ob_end_clean();
    return apply_filters('xspfpl_get_tomahk_playlist',$content,$tomahk_id,$post_id);
}

function xspfpl_is_local_wp(){
    if ($_SERVER['REMOTE_ADDR']=='127.0.0.1') return true;
    return false;
}

function xspf_classes($array){
    echo xspf_get_classes($array);
}

function xspf_get_classes($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}




?>
