<?php

function xspfpl_xspf_response_presets($presets,$meta_options,$playlist){

    //wait for feed response
    if ( $playlist->response && !is_wp_error($playlist->response) ){

        //is regular xspf file, force selectors.
        if ( $playlist->feed_is_xspf() ) {

            $xspf_presets = array(
                'tracks_order' => $playlist->get_default_options('tracks_order'),
                'selectors' => array(
                        'tracks'        => 'tracklist track',
                        'track_artist'  => 'creator',
                        'track_title'   => 'title',
                        'track_album'   => 'album',
                        'track_image'   => 'image'
                ),
                'selectors_regex' => array(
                    'track_artist'      => false,
                    'track_title'       => false,
                    'track_album'       => false,
                )
            );

            $presets = array_merge( (array)$presets,$xspf_presets );

        }
        
    }
    
    return $presets;

}

function xspfpl_get_slacker_station_slug($url){
    
    $pattern = '~^(?:http(?:s)?://(?:www\.)?slacker.com/station/)([^/]*)(?:/?)$~';
    preg_match($pattern, $url, $matches);

    if (!isset($matches[1])) return false;

    return $matches[1];
}

function xspfpl_slacker_get_body_node($content,$playlist){

    if( !xspfpl_get_slacker_station_slug($playlist->get_feed_url()) ) return $content;

    libxml_use_internal_errors(true);
    
    //QueryPath
    try{
        $json_node = htmlqp( $content, 'head script[type="application/ld+json"]', xspfpl()->querypath_options );
    }catch(Exception $e){
        return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','xspfpl'),$e->getCode(),$e->getMessage()) );
    }
    
    libxml_clear_errors();
    
    $playlist->feed_type = 'application/json'; //set content

    return $json_node->text();
    
}

function xspfpl_slacker_get_playlist_datas($datas,$playlist){
    
    $feed_url = $playlist->get_feed_url();
    
    if (!xspfpl_get_slacker_station_slug($feed_url)) return $datas;
    
    $feed_body_node = $playlist->get_feed_body_node();
    
    //QueryPath
    try{
        if ( $page_title = $feed_body_node->find('title')->text() ){
            
            $pattern = '~^(.*?)(?:\s\|\s)(?:.*)$~';
            preg_match($pattern, $page_title, $matches);

            //title
            if ( isset($matches[1]) ){
                $datas['title'] = $matches[1];
            }
            
        }

    }catch(Exception $e){
        
    }

    return $datas;
}

function xspfpl_slacker_get_presets($presets,$meta_options,$playlist){

    //wait for feed response
    if ( $playlist->response && !is_wp_error($playlist->response) ){
        
        $feed_url = ( isset($meta_options['feed_url']) ) ? $meta_options['feed_url'] : null;

        if( xspfpl_get_slacker_station_slug($feed_url) ) {

            $slacker_presets = array(
                'tracks_order' => $playlist->get_default_options('tracks_order'),
                'selectors' => array(
                        'tracks'        => 'track',
                        'track_artist'  => 'byartist name',
                        'track_title'   => 'name',
                        'track_album'   => 'inalbum name',
                        'track_image'   => null
                ),
                'selectors_regex' => array(
                    'track_artist'      => false,
                    'track_title'       => false,
                    'track_album'       => false,
                )
            );


           $presets = array_merge( (array)$presets,$slacker_presets );
        }

    }
    
    return $presets;
    
}

function xspfpl_get_somafm_station_slug($url){

    $pattern = '~^(?:http(?:s)?://(?:www\.)?somafm.com/)([^/]+)(?:/?)$~';

    preg_match($pattern, $url, $matches);

    if ( isset($matches[1]) ){
        return $matches[1];
    }
    return false;
}


function xspfpl_somafm_get_presets($presets,$meta_options,$playlist){

    $website_url = ( isset($meta_options['website_url']) ) ? $meta_options['website_url'] : null;
    $slug = null;

    if( $slug = xspfpl_get_somafm_station_slug($website_url) ){

        $somafm_presets = array(
            'feed_url'      => sprintf('http://www.somafm.com/songs/%s.xml',$slug),
            'tracks_order'  => $playlist->get_default_options('tracks_order'),
            'selectors'     => array(
                    'tracks'        => 'song',
                    'track_artist'  => 'artist',
                    'track_title'   => 'title',
                    'track_album'   => 'album',
                    'track_image'   => null
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       => false,
                'track_album'       => false,
            )
        );
        $presets = array_merge( (array)$presets,$somafm_presets );

    }

    return $presets;

}

function xspfpl_is_spotify_playlist($url){
    
    $vars = array();

    $pattern = '~^(?:http(?:s)?://embed.spotify.com/\?uri=)(?:spotify:user:)(.*)(?:playlist:)(.*)$~';
    preg_match($pattern, $url, $matches);

    if ( isset($matches[1]) && isset($matches[2]) ){
        return array(
            'user'      => $matches[1],
            'playlist'  => $matches[2]
        );
    }
    return false;
}

function xspfpl_spotify_get_presets($presets,$meta_options,$playlist){

    $feed_url = ( isset($meta_options['feed_url']) ) ? $meta_options['feed_url'] : null;
    
    if (xspfpl_is_spotify_playlist($feed_url)){
        
        $presets_spotify = array(
            'selectors' => array(
                    'tracks'        => '#mainContainer ul.track-info',
                    'track_artist'  => '.artist',
                    'track_title'   => '.track-title',
                    'track_album'   => null,
                    'track_image'   => null
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       =>  '^(?:\d+\W+)?(.*)$',
                'track_album'       => false,
            )
        );
        
        $presets = array_merge( (array)$presets,$presets_spotify );
    }

    return $presets;
    
}

function xspfpl_spotify_get_playlist_datas($datas,$playlist){
    
    $feed_url = $playlist->get_feed_url();
    if (!xspfpl_is_spotify_playlist($feed_url)) return $datas;
    
    //wait for feed response
    if ( $playlist->response && !is_wp_error($playlist->response) ){
    
        $feed_body_node = $playlist->get_feed_body_node();

        //QueryPath
        try{
            if ( $page_title = $feed_body_node->find('title')->text() ){

                $pattern = '~^(.*)(?: by )(.*)$~';
                preg_match($pattern, $page_title, $matches);

                //title
                if ( isset($matches[1]) ){
                    $datas['title'] = $matches[1];
                }

                //author
                if ( isset($matches[2]) ){
                    $datas['author'] = $matches[2];
                }

            }

        }catch(Exception $e){

        }
        
    }

    return $datas;
}

function xspfpl_get_radionomy_station_slug($url){

    $pattern = '~^(?:http(?:s)?://(?:www\.)?radionomy.com/.*?/radio/)([^/]+)~';

    preg_match($pattern, $url, $matches);

    if ( isset($matches[1]) ){
        return $matches[1];
    }
    return false;
}

function xspfpl_get_radionomy_station_id($station_url){

    if (!$slug = xspfpl_get_radionomy_station_slug($station_url)) return;

    $response = wp_remote_get( $station_url );
    if ( is_wp_error($response) ) return;

    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code != 200) return;
    
    $content = wp_remote_retrieve_body( $response );
        
    libxml_use_internal_errors(true);
    
    //QueryPath

    try{
        $imagepath = htmlqp( $content, 'head meta[property="og:image"]', xspfpl()->querypath_options )->attr('content');
    }catch(Exception $e){
        return false;
    }

    
    libxml_clear_errors();
    
    $image_file = basename($imagepath);

    $pattern = '~^([^.]+)~';
    preg_match($pattern, $image_file, $matches);

    //title
    if ( !isset($matches[1]) ) return false;

    return $matches[1];
    
}

function xspfpl_radionomy_get_variables($variables,$playlist){
    
    remove_filter('xspfpl_get_playlist_variables','xspfpl_radionomy_get_variables',10,2);
    if (!$raw_website_url = $playlist->get_options('website_url')) return $variables;
    $website_url = $playlist->get_feed_website_url();
    $radio_id_var = $playlist->get_service_variables(array('radio_id'));
    add_filter('xspfpl_get_playlist_variables','xspfpl_radionomy_get_variables',10,2);

    $filter_vars = array();

    if ( $raw_slug = xspfpl_get_radionomy_station_slug($raw_website_url) ){ //is radionomy
        
        $slug = xspfpl_get_radionomy_station_slug($website_url);

        if ( $slug && ($slug != $raw_slug) ){ //radio has a dynamic slug, we should compute radio ID

            $radio_var_options = array(
                'label'     => 'Radio ID',
                'system'    => true
            );

            //populate radio ID
            if ( !$radio_id = $playlist->get_variable_form_value('radio_id') ){ //radio ID is not set yet
                if ($slug = xspfpl_get_radionomy_station_slug($website_url)){ //radio slug is set
                    $radio_id = xspfpl_get_radionomy_station_id($website_url);
                    $radio_var_options['value'] = $radio_id;
                }
            }

            $filter_vars     = array(
                'radio_id'  => $playlist->build_service_variable('radio_id',$radio_var_options)
            );
            
        }

        $variables = array_merge( (array)$variables,$filter_vars );

    }
    
    return $variables;
    
}

function xspfpl_radionomy_get_presets($presets,$meta_options,$playlist){

    remove_filter('xspfpl_get_playlist_presets','xspfpl_radionomy_get_presets',10,3);
    if (!$raw_website_url = $playlist->get_options('website_url')) return $presets;
    $website_url = $playlist->get_feed_website_url();
    add_filter('xspfpl_get_playlist_presets','xspfpl_radionomy_get_presets',10,3);

    if ( $raw_slug = xspfpl_get_radionomy_station_slug($raw_website_url) ){ //is radionomy
        
        $presets_radionomy = array(
            'tracks_order'  => $playlist->get_default_options('tracks_order'),
            'selectors' => array(
                    'tracks'        => 'div.titre',
                    'track_artist'  => 'table td',
                    'track_title'   => 'table td i',
                    'track_album'   => null,
                    'track_image'   => 'img'
            ),
            'selectors_regex' => array(
                'track_artist'      => '^(.*?)(?:<br ?/?>)',
                'track_title'       => false,
                'track_album'       => false,
            )
        );
        
        $slug = xspfpl_get_radionomy_station_slug($website_url);
        if ($slug != $raw_slug){ //radio has a dynamic slug, we should compute radio ID
            $presets_radionomy['feed_url'] = 'http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%radio_id%';
        }else{
            $radio_id = xspfpl_get_radionomy_station_id($website_url);
            $presets_radionomy['feed_url'] = sprintf('http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%s',$radio_id);
        }

        $presets = array_merge( (array)$presets,$presets_radionomy );
        
    }

    return $presets;

}

function xspfpl_radionomy_get_playlist_datas($datas,$playlist){
    
    $website_url = $playlist->get_feed_website_url();

    if ( $slug = xspfpl_get_radionomy_station_slug($website_url) ){

        //wait for feed response
        if ( $playlist->response && !is_wp_error($playlist->response) ){

            $content = $playlist->get_feed_body_node();

            //QueryPath
            try{
                $title = htmlqp( $content, 'head meta[property="og:title"]', xspfpl()->querypath_options )->attr('content');
                
                //title
                if ( $title ){
                    $datas['title'] = $title;
                }


            }catch(Exception $e){

            }
            
        }
    
    }

    return $datas;
}

function xspfpl_get_bbc_station_slug($url){
    $pattern = '~^(?:http(?:s)?://(?:www\.)?bbc.co.uk/)([^/]*)~';
    
    preg_match($pattern, $url, $matches);

    if (!isset($matches[1])) return false;

    return $matches[1];
}

function xspfpl_get_bbc_program_id($station_url){
    
    if (!$slug = xspfpl_get_bbc_station_slug($station_url)) return;
    
    $station_url = sprintf('http://www.bbc.co.uk/%s',$slug);

    $response = wp_remote_get( $station_url );
    if ( is_wp_error($response) ) return;

    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code != 200) return;
    
    $content = wp_remote_retrieve_body( $response );
        
    libxml_use_internal_errors(true);
    
    //QueryPath

    try{
        $music_played_link = htmlqp( $content, '.t1-live-title a', xspfpl()->querypath_options )->attr('href');
    }catch(Exception $e){
        return false;
    }

    libxml_clear_errors();

    $pattern = '~(?:/programmes/)([^/#]*)~';
    preg_match($pattern, $music_played_link, $matches);

    //title
    if ( !isset($matches[1]) ) return false;

    return $matches[1];
    
}

function xspfpl_bbc_get_presets($presets,$meta_options,$playlist){

    $website_url = ( isset($meta_options['website_url']) ) ? $meta_options['website_url'] : null;

    if ( $slug = xspfpl_get_bbc_station_slug($website_url) ){
        
        $bbc_presets = array(
            'feed_url'      => 'http://www.bbc.co.uk/programmes/%program_id%/segments.inc',
            'tracks_order'  => $playlist->get_default_options('tracks_order'),
            'selectors' => array(
                    'tracks'        => 'ul li',
                    'track_artist'  => 'span[property=byArtist] a span',
                    'track_title'   => 'span[property=name]',
                    'track_album'   => 'li.inline em',
                    'track_image'   => 'img'
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       => false,
                'track_album'       => false,
            )
        );

        $playlist->options['website_url'] = sprintf('http://www.bbc.co.uk/%s',$slug);
        
        if ( !isset($meta_options['variables']['program_id']) || (!$radio_id = $meta_options['variables']['program_id']['value']) ){
            $program_id = xspfpl_get_bbc_program_id($website_url);
        }

        $bbc_presets['variables']['program_id'] = array(
            'label'     => 'Program ID',
            'system'   => true,
            'value'     => $program_id
        );

        $presets = array_merge( (array)$presets,$bbc_presets );
        
    }

    return $presets;

}

//xspf
add_filter('xspfpl_get_playlist_presets','xspfpl_xspf_response_presets',10,3);

//slacker
add_filter('xspfpl_get_feed_body_node_pre','xspfpl_slacker_get_body_node',10,2);
add_filter('xspfpl_get_playlist_presets','xspfpl_slacker_get_presets',10,3);
add_filter('xspfpl_get_playlist_datas','xspfpl_slacker_get_playlist_datas',10,2);

//somaFM
add_filter('xspfpl_get_playlist_presets','xspfpl_somafm_get_presets',10,3);

//spotify
add_filter('xspfpl_get_playlist_presets','xspfpl_spotify_get_presets',10,3);
add_filter('xspfpl_get_playlist_datas','xspfpl_spotify_get_playlist_datas',10,2);

//radionomy
add_filter('xspfpl_get_playlist_presets','xspfpl_radionomy_get_presets',10,3);
add_filter('xspfpl_get_playlist_variables','xspfpl_radionomy_get_variables',10,2);
add_filter('xspfpl_get_playlist_datas','xspfpl_radionomy_get_playlist_datas',10,2);

//BBC
//add_filter('xspfpl_get_playlist_presets','xspfpl_bbc_get_presets',10,3);
