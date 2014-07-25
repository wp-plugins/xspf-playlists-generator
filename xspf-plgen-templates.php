<?php


/**
 * Get the XSPF link for a post.
 * @global type $post
 * @global type $wp_rewrite
 * @param type $post_id
 * @return type 
 */

function xspf_plgen_get_xspf_permalink($post_id=false){
    global $wp_rewrite;

    if(!$post_id){
        $post_id = get_the_ID();
    }

    $post_permalink = get_permalink($post_id);

    if ($wp_rewrite->get_page_permastruct()){ //permalinks enabled
        $url = $post_permalink.xspf_plgen()->xpsf_render_var;
        $url = trailingslashit( $url );
    }else{
        $url = add_query_arg(array(xspf_plgen()->xpsf_render_var => true),$post_permalink);
    }


    return apply_filters('xspf_plgen_get_xspf_permalink',$url,$post_id);

}



/**
 * Get the Toma.hk ID of the playlist from the post metas.
 * If the post meta do not exists, find the playlist ID and save it.
 * @global type $post
 * @param type $post_id
 * @return type 
 */

function xspf_plgen_get_tomahk_playlist_id($post_id=false,$force=false){

    if(!$post_id){
        $post_id = get_the_ID();
    }

    $playlist_id = get_post_meta($post_id,'toma_hk_id', true);

    if ((!$playlist_id)||($force)){ //send XSPF to toma.hk and get playlist ID back
        
        //$playlist = new xspf_plgen_playlist($post_id);
        //$tracks = $playlist->populate_tracks();
        //if(!$tracks) return false;
        

        $link = xspf_plgen_get_xspf_permalink($post_id);

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

    return apply_filters('xspf_plgen_get_tomahk_playlist_id',$playlist_id,$post_id);


}

/**
 * Get the Toma.hk playlist link for a post.
 * @param type $post_id
 * @return type 
 */

function xspf_plgen_get_tomahk_playlist_link($post_id=false){

    $playlist_id = xspf_plgen_get_tomahk_playlist_id($post_id);
    if (!$playlist_id) return false;
    
    $playlist_url = 'http://toma.hk/p/'.$playlist_id;

    return apply_filters('xspf_plgen_get_tomahk_playlist_link',$playlist_url,$playlist_id,$post_id);

}

/**
 * Get the playlist saved on Toma.hk for a post.
 * @param type $post_id
 * @return boolean 
 */

function xspf_plgen_get_tomahk_playlist($post_id=false){
    
    if (!xspf_plgen_is_local_wp()){ //not local
        $tomahk_id = xspf_plgen_get_tomahk_playlist_id($post_id);
    }else{ //for testing
        //$tomahk_id = 'adi2222M';
    }

    $tomahk_iframe_args = array(
        'width'=>550,
        'height'=>430
    );
    $tomahk_iframe_args = apply_filters('tomahk_plp_iframe_args',$tomahk_iframe_args);



    if (!xspf_plgen_is_local_wp()&&(!$tomahk_id)) return false;

    $url = 'http://toma.hk/p/'.$tomahk_id;
    $url_args['embed'] = 'true';
    $url = add_query_arg($url_args,$url);

    ob_start();
    ?>
    <iframe src="<?php echo $url;?>" width="<?php echo $tomahk_iframe_args['width'];?>" height="<?php echo $tomahk_iframe_args['height'];?>" scrolling="no" frameborder="0" allowtransparency="true" ></iframe>
    <?php
    $content = ob_get_contents();
    
    if (xspf_plgen_is_local_wp()){ //local
        //we will not be able to send XSPF playlist to tomahawk if its url is local; abord
        $content.= '<p>('.__('Toma.hk embeds do not work on local Wordpress installations','thk-pl').')</p>';
    }
    
    ob_end_clean();
    return apply_filters('xspf_plgen_get_tomahk_playlist',$content,$tomahk_id,$post_id);
}

function xspf_plgen_is_local_wp(){
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
