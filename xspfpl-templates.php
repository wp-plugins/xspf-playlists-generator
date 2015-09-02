<?php

/**
 * Here's all the function you could need in your templates.
 */


/**
 * Get the XSPF link for a post.
 * Don't user permalinks here as subscriptions will break if something changes about the permalinks on the blog.
 * Using raw URLs avoid it.
 * @global type $post
 * @param type $post_id
 * @return string 
 */
function xspfpl_get_xspf_link($post_id=false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    if ( !$playlist->is_playlist_ready() ) return false;

    $args = array(
        'p'                 => $post->ID,
        xspfpl()->var_xspf   => 1
    );
    
    if ( $variables = $playlist->get_service_variables() ){

        foreach ((array)$variables as $variable){
            $args[xspfpl()->var_variables][$variable['slug']] = $variable['value'];
        }
    }
        
    $url = add_query_arg ( $args,get_bloginfo('url') );
    return apply_filters('xspfpl_get_xspf_link',$url,$post->ID);
}


function xspfpl_get_playlist_website_url($post_id=false){
    $playlist = xspfpl_get_playlist($post_id);
    return $playlist->get_feed_website_url();
}

function xspfpl_html_pre(){
    echo xspfpl_get_html_pre();
}

function xspfpl_get_html_pre(){
    $output = null;
    $output.= xspfpl_get_playlist_service_form();
    $output.= xspfpl_get_playlist_links();
    return $output;
}

function xspfpl_get_playlist_service_form($post_id=false){

    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    if ( !$variables = $playlist->get_service_variables() ) return false;

    $block = null;
    $message = null;
    $form_fields = array();
    $show_form = false;
    
    $form_classes = array('xspfpl-playlist-service-form');

    $missing_vars = ( isset($_REQUEST['missing_vars']) ) ? $_REQUEST['missing_vars'] : null;

    if ( $missing_vars ){
        $form_classes[] = 'error';
        $message = sprintf('<p class="message">%1$s</p>',__('Please complete the form','xspfpl'));
    }

    foreach ($variables as $variable){

        $field = $field_label = $field_desc = $field_input = null;
        $field_classes = array();

        if ($variable['value']){
            $field_classes[] = 'has-value';
        }

        if ( in_array($variable['slug'],(array)$missing_vars) ){
            $field_classes[] = 'error';
        }

        if (!$variable['system']){

            $show_form = true;

            $field_label = '<label>'.$variable['label'].'</label>';

            if ($variable['desc']){
                $field_desc = '<small>'.$variable['desc'].'</small>';
            }

            $field_input = sprintf(
                '<input type="text" name="%1$s" value="%2$s" placeholder="%3$s">',
                xspfpl()->var_variables.'['.$variable['slug'].']',
                $variable['value'],
                $variable['label']
            );
            $field = sprintf('<fieldset %1$s>%2$s%3$s%4$s</fieldset>',xspf_get_classes($field_classes),$field_label,$field_desc,$field_input);

        }
        $form_fields[] = $field;

    }

    if ($show_form){

        $form = sprintf(
            '<form action="%1$s" method="get" %2$s>
            %3$s
            <button type="submit">%4$s</button>
            <input type="hidden" name="p" value="%5$s"/>
            </form>',
            get_bloginfo( 'url' ),
            xspf_get_classes($form_classes),
            $message.implode("\n",$form_fields),
            __('Load Station','xspfpl'),
            $post->ID
        );

        return $form;

    }
}

function xspfpl_get_playlist_links($post_id=false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);

    $links = array();
    $links_str = null;

    //links
    if ($xspf_link = xspfpl_get_xspf_link($post->ID)){
        
        $links[] = sprintf(
            '<a href="%1$s" %2$s>%3$s</a>',
            $xspf_link,
            xspf_get_classes(array('xspf-link')),
            __('Download XSPF','xspfpl')
        );

        //THK friendly
        $thk_link = add_query_arg( array('xspf' => $xspf_link), 'tomahawk://import/playlist' );

        $links[] = sprintf(
            '<a href="%1$s" %2$s>%3$s</a>',
            $thk_link,
            xspf_get_classes(array('xspf-link','thk-link')),
            __('Add to Tomahawk','xspfpl')
        );
    }

    if ($links){
        foreach($links as $link){
            $links_str .= '<li>'.$link.'</li>';
        }
        return '<ul class="station-links">'.$links_str.'</ul>';
    }

}

function xspfpl_is_frozen_playlist($post_id = false){
    
    $playlist = xspfpl_get_playlist($post_id);
    return $playlist->get_options('is_frozen');
}

/**
 * Returns the last track
 * @param type $post_id
 * @return type
 */

function xspfpl_get_last_track($post_id = false, $cache_only = true){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);

    if ($cache_only){
        $tracks = $playlist->get_tracks_cache();
    }else{
        $tracks = $playlist->get_tracks();
    }
    
    if ( is_wp_error($tracks) || !$tracks ) return;

    $track = array_shift($tracks);

    if ( isset($track['album']) && $track['album'] ){
        
        return sprintf(
            __('%1$s by %2$s on %3$s','xspfpl'),
            '<em>'.$track['title'].'</em>',
            $track['artist'],
            '"'.$track['album'].'"'
        );
        
    }else{
        
        return sprintf(
            __('%1$s by %2$s','xspfpl'),
            '<em>'.$track['title'].'</em>',
            $track['artist']
        );
        
    }
}


/**
 * Get the number of time tracks have been requested
 * @global type $post
 * @param type $post_id
 * @return boolean
 */

function xspfpl_get_track_request_count($post_id = false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    
    $count = null;
    
    if ( get_post_status($post->ID) == 'publish') {
        $count = get_post_meta($post->ID, XSPFPL_Single_Playlist::$meta_key_requests, true);
    }

    return (int)$count;
}

function xspfpl_get_track_request_monthly_count($post_id = false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    
    $count = null;
    
    if ( get_post_status($post->ID) == 'publish') {
        $count = get_post_meta($post->ID, XSPFPL_Single_Playlist::$meta_key_monthly_requests, true);
    }

    return (int)$count;
}

/**
 * Checks if the playlist is still alive : each time tracks are populated,
 * A "health" meta is added with the time and number of tracks found.
 * If health fell to zero, maybe the playlist is no more alive.
 * @return boolean
 */

function xspfpl_get_health_status($post_id = false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    
    if ( get_post_status($post->ID) != 'publish') return false;
    
    //no health for frozen playlists
    if ( xspfpl_is_frozen_playlist() ) return false;

    $metas = get_post_meta($post->ID, $playlist->meta_key_health, true);

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


function xspfpl_tracklist_table($post_id = false){
    echo xspfpl_get_tracklist_table($post_id);
}

function xspfpl_get_tracklist_table($post_id = false){
    
    global $post;
    
    if ($post_id) {
        $post = get_post($post_id);
    }
    $playlist = xspfpl_get_playlist($post->ID);
    
    if ( !$playlist->is_playlist_ready() ) return;
    
    $tracks = $playlist->get_tracks();
    $tracks_table = new XSPFPL_Tracks_Table($tracks);
    $tracks_table->prepare_items();
    
    
    ob_start();
    //$tracks_table->views();
    $tracks_table->display();
    $tracklist = ob_get_clean();
    
    return $tracklist;
    
}


?>
