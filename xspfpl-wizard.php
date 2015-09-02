<?php

/**
 * Wizard class.  Used to build a playlist.  Actually only in the backend, will maybe be extended to the frontend someday.
 */

class XSPFPL_Wizard {

    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }
    
    function setup_globals() {
    }

    function includes(){
        
    }

    function setup_actions(){

        
        add_action( 'admin_init',  array( $this, 'wizard_settings_init' ) );

        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );

        //TO FIX should run BEFORE wizard_settings_init
        add_action( 'save_post',  array( $this, 'save' ) );
        
        add_action( 'add_meta_boxes',  array( $this, 'register_meta_boxes' ) );

    }

    
    function scripts_styles(){
        
        wp_enqueue_style('prismjs','http://prismjs.com/themes/prism.css');
        wp_enqueue_script('prismjs','http://prismjs.com/prism.js');
        
        wp_enqueue_style( 'xspfpl-wizard', xspfpl()->plugin_url .'_inc/css/wizard.css', array(), xspfpl()->version );
        wp_enqueue_script( 'xspfpl-wizard', xspfpl()->plugin_url .'_inc/js/wizard.js', array( 'jquery', 'jquery-ui-tabs' ), xspfpl()->version );
    }

    function register_meta_boxes(){
        add_meta_box( 'xspfpl-wizard-metabox', __('Station Wizard','xspfpl'), array(&$this,'wizard_metabox'), xspfpl()->station_post_type,'normal','high');
    }
    
    function is_wizard_page(){
        global $pagenow;
        if ( 
            ( $pagenow == 'edit.php' && isset($_REQUEST['post_type']) && $_REQUEST['post_type']==xspfpl()->station_post_type) || 
            ( $pagenow == 'post.php' && isset($_REQUEST['post']) && get_post_type($_REQUEST['post'])==xspfpl()->station_post_type)
            ){
                return true;
        }
            
        return false;
    }
    
    function dummy_sanitize( $input ){
        /*
         * Do nothing here.  We use our own hooked function save_step() at init, this one is not necessary.
         */
        return false;
    }
    
    function can_show_step($slug){
         

        switch ($slug){
            case 'tracks_selector':
                
                $response = xspfpl_get_playlist()->get_feed();
                if ( !$response || is_wp_error($response) ) break;
                
                if ( (!$feed_body_node = xspfpl_get_playlist()->get_feed_body_node()) || is_wp_error($feed_body_node) ) break;
                
                return true;
            break;
            case 'playlist_service':

                if (!xspfpl_get_playlist()->get_service_variables()) break;
                return true;
                
            break;
            case 'track_details':
                
                if ( !$this->can_show_step('tracks_selector') ) break;
                
                //$selectors = xspfpl_get_playlist()->get_options('selectors');
                //if ( !$selectors['tracks'] ) break;
                //if ( (!$track_nodes = xspfpl_get_playlist()->get_track_nodes()) || is_wp_error($track_nodes) ) break;
                
                return true;
                
            break;
            case 'tracklist':
                
                $selectors = xspfpl_get_playlist()->get_options('selectors');
                
                if ( !xspfpl_get_playlist()->get_options('is_frozen') ){
                    if ( !isset($selectors['tracks']) ) break;
                    if ( !isset($selectors['track_title']) && !isset($selectors['track_artist']) ) break;
                }
                
                return true;
                
            break;
            
            case 'playlist_options':
                if ( xspfpl_get_playlist()->get_options('is_frozen') ) break;
                return true;
            break;
            
        }
        return false;
    }
    
    function populate_errors(){
        
        if ( !$this->is_wizard_page() ) return;
        if ( !xspfpl_get_playlist() ) return;
        if ( !xspfpl_get_playlist()->is_playlist_ready() ) return;
        $response = xspfpl_get_playlist()->get_feed();

        // cache
        if (  $response && !is_wp_error($response) && !xspfpl_get_playlist()->feed_is_xspf() && !xspfpl_get_playlist()->get_options('cache_tracks_intval') ){
            add_settings_error( 'wizard-steps', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Station Settings tab).",'xspfpl') );
        }

        //missing variables
        if ( xspfpl_get_playlist()->get_service_variables() && !xspfpl_get_playlist()->is_playlist_ready() ){
            add_settings_error( 'wizard-step-base', 'playlist_service_not_ready', __('Please complete the Variables tab fields.','xspfpl') );
        }

        if ( !$response || is_wp_error($response) ){

            if ( is_wp_error($response) ){
                add_settings_error( 'wizard-steps', 'no_response', $response->get_error_message() );
            }else{
                add_settings_error( 'wizard-steps', 'no_response', sprintf(
                        __('Error while trying to reach %1$s. Try to reload the page or update your settings.','xspfpl'),
                        '<em>'.xspfpl_get_playlist()->get_feed_url().'</em>'
                        )
                );
            }
            
            return;

        }
     
        //body node
        
        if ( (!$feed_body_node = xspfpl_get_playlist()->get_feed_body_node()) || is_wp_error($feed_body_node) ){
            add_settings_error( 'wizard-step-base', 'no_feed_body_node',__('Unable to populate the document content.  It is maybe not well formatted.','xspfpl'));
            if ( is_wp_error($feed_body_node) ){
                add_settings_error( 'wizard-step-base', 'no_feed_body_node', $feed_body_node->get_error_message() );
            }
            return;
        }
        $selectors = xspfpl_get_playlist()->get_options('selectors');

        if ( isset($selectors['tracks']) ){
            
            if ( (!$track_nodes = xspfpl_get_playlist()->get_track_nodes()) || is_wp_error($track_nodes)  ) {
                
                    add_settings_error( 'wizard-step-tracks_selector', 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','xspfpl') );
                    if ( is_wp_error($track_nodes) ){
                        add_settings_error( 'wizard-step-tracks_selector', 'no_track_nodes', $track_nodes->get_error_message() );
                    }
                
                return;
            }

        }

    }
    
    function wizard_settings_init(){
        
        if ( !$this->is_wizard_page() ) return;
        if ( !xspfpl_get_playlist() ) return;

        //handle errors
        $this->populate_errors();


        register_setting(
             'xspfpl', // Option group
             'xspfpl_wizard', // Option name
             array( $this, 'dummy_sanitize' ) // Sanitize
         );
        
        //General

        add_settings_section(
             'settings_general', // ID
             __('Base URLs','xspfpl'), // Title
             array( $this, 'section_general_desc' ), // Callback
             'xspfpl-wizard-step-base' // Page
        );
        
        //Allow to unfreeze playlist as other tabs are not available if frozen
        
        if ( xspfpl_get_playlist()->get_options('is_frozen') ){
            
            add_settings_field(
                'frozen', 
                __('Do not sync','xspfpl'), 
                array( $this, 'frozen_callback' ), 
                'xspfpl-wizard-step-base', 
                'settings_general'
            );
        } else{
            
            add_settings_field(
                'website_url', 
                __('Website','xspfpl'), 
                array( $this, 'website_url_callback' ), 
                'xspfpl-wizard-step-base', // Page
                'settings_general' // Section
            ); 
            
            add_settings_field(
                'feed_url', 
                __('Tracks feed URL','xspfpl'), 
                array( $this, 'feed_url_callback' ), 
                'xspfpl-wizard-step-base', 
                'settings_general'
            );

            add_settings_field(
                'playlist_content_type', 
                __('Feed Content Type','xspfpl'), 
                array( $this, 'feed_content_type_callback' ), 
                'xspfpl-wizard-step-base', 
                'settings_general'
            );
            /* TO FIX : make the wizard render extremly slowly. 
            add_settings_field(
                'playlist_raw_content', 
                __('Feed Raw Content','xspfpl'), 
                array( $this, 'feed_raw_content_callback' ), 
                'xspfpl-wizard-step-base', 
                'settings_general'
            );
            */
            
        }
        
        if ($this->can_show_step('playlist_service')){

            add_settings_section(
                 'settings_playlist_service', // ID
                 __('Variables','xspfpl'), // Title
                 array( $this, 'section_variables_desc' ), // Callback
                 'xspfpl-wizard-step-playlist-service' // Page
            );
            
           add_settings_field(
                'playlist_service_variables', 
                __('Variables','xspfpl'), 
                array( $this, 'variables_callback' ), 
                'xspfpl-wizard-step-playlist-service', 
                'settings_playlist_service'
            );
            
            if ( get_the_title() ){
                add_settings_field(
                    'playlist_service_title_suffix', 
                    __('Title Suffix','xspfpl'), 
                    array( $this, 'variable_title_suffix_callback' ), 
                    'xspfpl-wizard-step-playlist-service', 
                    'settings_playlist_service'
                );
            }
            
        }

        if ($this->can_show_step('tracks_selector')){


            add_settings_section(
                'playlist_track_selector',
                __('Tracks Selector','xspfpl'),
                array( $this, 'section_tracks_selector_desc' ),
                'xspfpl-wizard-step-tracks-selector'
            );

            add_settings_field(
                'playlist_track_selector', 
                __('Tracks Selector','xspfpl'), 
                array( $this, 'selector_tracks_callback' ), 
                'xspfpl-wizard-step-tracks-selector', 
                'playlist_track_selector'
            );
             /* TO FIX : make the wizard render extremly slowly. 
            add_settings_field(
                'tracklist_raw_content', 
                __('Tracks Raw Content','xspfpl'), 
                array( $this, 'tracklist_raw_content_callback' ), 
                'xspfpl-wizard-step-tracks-selector', 
                'playlist_track_selector'
            );
            */
        }
        
        if ($this->can_show_step('track_details')){

            add_settings_section(
                'track_details',
                __('Tracks Selector','xspfpl'),
                array( $this, 'section_tracks_selector_desc' ),
                'xspfpl-wizard-step-track-details'
            );


            add_settings_section(
                'track_details',
                __('Track Details','xspfpl'),
                array( $this, 'section_track_details_desc' ),
                'xspfpl-wizard-step-track-details'
            );

            add_settings_field(
                'track_artist_selector', 
                __('Artist Selector','xspfpl').$this->regex_link(),
                array( $this, 'track_artist_selector_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );
            
            add_settings_field(
                'track_artist_regex', 
                __('Artist Regex','xspfpl'),
                array( $this, 'track_artist_regex_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_title_selector', 
                __('Title Selector','xspfpl').$this->regex_link(), 
                array( $this, 'track_title_selector_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );
            
            add_settings_field(
                'track_title_regex', 
                __('Title Regex','xspfpl'),
                array( $this, 'track_title_regex_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_album_selector', 
                __('Album Selector','xspfpl').$this->regex_link(), 
                array( $this, 'track_album_selector_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );
            
            add_settings_field(
                'track_album_regex', 
                __('Album Regex','xspfpl'),
                array( $this, 'track_album_regex_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_image_selector', 
                __('Image Selector','xspfpl'), 
                array( $this, 'track_image_selector_callback' ), 
                'xspfpl-wizard-step-track-details', 
                'track_details'
            );
            
        }
        
        //Found Tracks
        
        if ($this->can_show_step('tracklist')){
            
            $count = 0;
            if ( $tracks = xspfpl_get_playlist()->get_tracks() ){
                if (!is_wp_error($tracks)){
                    $count = count($tracks);
                }
                
            }

            add_settings_section(
                'found_tracks',
                sprintf( __('Found Tracks : %d','xspfpl'),$count ),
                array( $this, 'section_found_tracks_desc' ),
                'xspfpl-wizard-step-tracklist'
            );
            
        }
        
        if ($this->can_show_step('playlist_options')){
            
            if ( xspfpl_get_playlist()->feed_is_xspf() ){
                add_settings_error( 'xspfpl-wizard-steps', 'xspf_options', __("As this is a direct call to an XSPF file, some options might not be available.",'xspfpl'),'updated');
            }

            //Playlist Options
            add_settings_section(
                'playlist_options',
                __('Playlist Options','xspfpl'),
                array( $this, 'section_station_options_desc' ),
                'xspfpl-wizard-step-playlist-options'
            );
  
            add_settings_field(
                'cache_tracks_intval', 
                __('Enable Cache','xspfpl'), 
                array( $this, 'cache_callback' ), 
                'xspfpl-wizard-step-playlist-options', 
                'playlist_options'
            );
            
            add_settings_field(
                'enable_musicbrainz', 
                __('Use Musicbrainz','xspfpl'), 
                array( $this, 'musicbrainz_callback' ), 
                'xspfpl-wizard-step-playlist-options', 
                'playlist_options'
            );

            add_settings_field(
                'tracks_order', 
                __('Tracks Order','xspfpl'), 
                array( $this, 'tracks_order_callback' ), 
                'xspfpl-wizard-step-playlist-options', 
                'playlist_options'
            );
            
            if ( !xspfpl_get_playlist()->get_options('is_frozen') ){ //if frozen, is in first section instead of here.

                add_settings_field(
                    'frozen', 
                    __('Do not sync','xspfpl'), 
                    array( $this, 'frozen_callback' ), 
                    'xspfpl-wizard-step-playlist-options', 
                    'playlist_options'
                );
            
            }
        }

    }
    
    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="regex-link">[...^]</a>',
            __('Use Regular Expression','xspfpl')
        );
    }
    
    function regex_block($field_name){
        
        
        $selectors_options = xspfpl_get_playlist()->get_options('selectors_regex');
        $selectors_presets = xspfpl_get_playlist()->get_presets('selectors_regex');
        
        $option = ( isset($selectors_options[$field_name]) ) ? htmlspecialchars( $selectors_options[$field_name], ENT_QUOTES) : null;
        
        $has_preset = ( isset($selectors_presets[$field_name]) );
        
        printf(
            '<p class="regex-field"><input class="regex" name="%1$s[selectors_regex][%2$s]" type="text" value="%3$s" %4$s/></p>',
            'xspfpl_wizard',
            $field_name,
            $option,
            disabled( $has_preset , true, false)
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors_regex][%2$s]" value="%3$s"/>',
                'xspfpl_wizard',
                $field_name,
                $option
            );
        }

    }
    
    function section_general_desc(){
        settings_errors('wizard-step-base');
        
        _e('Those are the settings required to reach the remote feed.','xspfpl');
        echo"<br/>";
        
        printf(
            __('You can eventually use variables in your URLs; using %1$s. Eg %2$s.  This will bring a new tab.','xspfpl'),
            '<em>%variable%</em>',
            '<code>https://soundcloud.com/%username%/likes</code>'
        );
        
    }
    
    function website_url_callback(){
        
        if (!$option = xspfpl_get_playlist()->get_options('website_url')){
            $option = xspfpl_get_playlist()->get_options('feed_url');
        }
        
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('website_url');

        printf(
            '<input type="text" name="%1$s[website_url]" value="%2$s" style="min-width:100%%" %3$s/><p class="wizard-field-desc">%4$s</p>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            __('Optional.  If you want the station source link (used as info in the XSPF) to refer to another URL than the feed.','xspfpl')
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[website_url]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function feed_url_callback(){

        $option = xspfpl_get_playlist()->get_options('feed_url');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('feed_url');

        printf(
            '<input type="text" name="%1$s[feed_url]" value="%2$s" style="min-width:100%%" %3$s/><p class="wizard-field-desc">%4$s</p>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            __('Where should we get the data from ?','xspfpl')
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[feed_url]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function feed_content_type_callback(){

        $output = "—";

        if ( $content_type = xspfpl_get_playlist()->get_feed_type() ){

            if ( !is_wp_error($content_type) ) {

                switch ($content_type){
                    case 'application/json':
                        $content_type .= ' <em>('.__('converted to XML','xspfpl').')</em>';
                    break;
                }

                $output = $content_type;
                
                if ( xspfpl_get_playlist()->feed_is_xspf() ){
                    $output .= ' <em>('.__('XSPF, a direct link to this file will be used.','xspfpl').')</em>';
                }

            }

        }
        
        echo $output;

    }
    
    function section_variables_desc(){
        settings_errors('wizard-step-playlist-service');

        echo __('When visiting the playlist page on this website, users will then be invited to fill a form to get the station based on the values they entered.','xspfpl');
        
    }
    
    function variables_callback(){

        if ( $variables = xspfpl_get_playlist()->get_service_variables() ){
            
            

            ?>
            <table id="xspfpl-urls-variables">
              <tr>
                <th><?php _e('Slug','xspfpl');?></th>
                <th><?php _e('Wizard Value','xspfpl');?></th>
                <th><?php _e('Field Label','xspfpl');?></th>
                <th><?php _e('Field Description','xspfpl');?></th>
              </tr>
              <?php

                foreach ($variables as $slug=>$variable){

                    $label_field = $desc_field = $value_field = null;
                    $has_value_preset = $has_label_preset = $has_desc_preset = null;
                    
                    //value
                    if (!$variable['system']){
                        $has_value_preset = (bool)xspfpl_get_playlist()->get_presets( array('variables',$slug,'value') );
                        $value_field = sprintf(
                            '<input type="text" name="%1$s[variables][%2$s][value]" value="%3$s" %4$s/>',
                            'xspfpl_wizard',
                            $slug,
                            $variable['value'],
                            disabled( $has_value_preset , true, false)
                        );
                    }else{
                        if (!$value = $variable['value']){
                            $value = '&ndash;';
                        }
                        $value_field = $value;
                    }
                    
                    if ($has_value_preset){
                        printf(
                            '<input type="hidden" name="%1$s[variables][%2$s][value]" value="%3$s"/>',
                            'xspfpl_wizard',
                             $slug,
                            $variable['value']
                        );
                    }
                    
                    //label
                    if (!$variable['system']){
                        $has_label_preset = (bool)xspfpl_get_playlist()->get_presets( array('variables',$slug,'label') );
                        $label_field = sprintf(
                            '<input type="text" name="%1$s[variables][%2$s][label]" value="%3$s" %4$s/>',
                            'xspfpl_wizard',
                            $slug,
                            $variable['label'],
                           disabled( $has_label_preset , true, false)
                        );
                    }else{
                        $label_field = $variable['label'];
                    }
                    
                    if ($has_label_preset){
                        printf(
                            '<input type="hidden" name="%1$s[variables][%2$s][label]" value="%3$s"/>',
                            'xspfpl_wizard',
                             $slug,
                            $variable['label']
                        );
                    }
                    
                    //desc
                    if (!$variable['system']){
                        $has_desc_preset = xspfpl_get_playlist()->get_presets( array('variables',$slug,'desc') );
                        $desc_field = sprintf(
                            '<input type="text" name="%1$s[variables][%2$s][desc]" value="%3$s" %4$s/>',
                            'xspfpl_wizard',
                            $slug,
                            $variable['desc'],
                            disabled( $has_desc_preset , true, false)
                        );
                    }else{
                        $desc_field = '<em>'.__('System value','xspfpl').'</em>';
                    }
                    
                    if ($has_desc_preset){
                        printf(
                            '<input type="hidden" name="%1$s[variables][%2$s][desc]" value="%3$s"/>',
                            'xspfpl_wizard',
                             $slug,
                            $variable['desc']
                        );
                    }
                    
                    ///

                    printf(
                        '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td>',
                        '<em>%'.$variable['slug'].'%</em>',
                        $value_field,
                        $label_field,
                        $desc_field
                    );
                }
              ?>
            </table>
            <?php
        }
        ?>
        <p class="wizard-field-desc">
            <?php
            echo __('<strong>Slug</strong> is the name of the variable as extracted from the URLs.','xspfpl').'  ';
            echo __('<strong>Field Label</strong> is the label which will appear on the frontend form field.','xspfpl').'  ';
            echo __('<strong>Wizard Value</strong> is required by this wizard to check that the station can be build.','xspfpl').'  ';
            echo __('<strong>Field Description</strong> is a description of the field.','xspfpl').'  ';
            echo __('<strong>Do not</strong> use for passwords or sensitive datas !','xspfpl')
            
        ?>
        </p>
        <?php
    }
    
    function variable_title_suffix_callback(){
         
        
        $variables = xspfpl_get_playlist()->get_service_variables();
        
        $variables_slugs = array();
        foreach($variables as $key=>$variable){
            $variables_slugs[$key] = '%'.$variable['slug'].'%';
        }

        $option = xspfpl_get_playlist()->get_options('variable_title_suffix');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('variable_title_suffix');
        
        printf(
            '<span class="playlist-service-title-suffix">%1$s</span><input type="text" name="%2$s[variable_title_suffix]" value="%3$s" style="min-width:60%%" %4$s/><p class="wizard-field-desc">%5$s</p>',
            get_the_title(),
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('You can use your variables to build a suffix that will be shown after this station title, to customize it. Eg. %1$s','xspfpl'),
                '<code>(by %username%)</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[variable_title_suffix]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
        
    }
    
    function feed_raw_content_callback(){
        
        
        $output = "—";
        
        if ( $feed_body_node = xspfpl_get_playlist()->get_feed_body_node() ){
            
            if ( !is_wp_error($feed_body_node) ) {
                
                $content = $feed_body_node->html();
                
                //$indenter = new \Gajus\Dindent\Indenter();
                //$content = $indenter->indent( $content );
                
                $content = esc_html($content);
                
                
                $output = '<pre class="xspf-raw"><code class="language-markup">'.$content.'</code></pre>';
                
                if ( xspfpl_get_playlist()->get_service_variables() ){
                    
                    $output.= '<p>'.sprintf(__('Grabbed from: %s','xspfpl'),'<em>'.xspfpl_get_playlist()->get_feed_url().'</em>').'</p>';
                    
                }
                
            }
            
        }
        
        echo $output;
        

    }
    
    function section_tracks_selector_desc(){
        settings_errors('wizard-step-tracks_selector');
    }
    
    function selector_tracks_callback(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');
        $option = ( isset($selectors['tracks']) ) ? htmlspecialchars( $selectors['tracks'], ENT_QUOTES) : null;
        $has_preset = (bool)xspfpl_get_playlist()->get_presets( array('selectors','tracks') );

        printf(
            '<input type="text" name="%1$s[selectors][tracks]" value="%2$s" %3$s style="min-width:100%%"/><p class="wizard-field-desc">%4$s</p>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('Enter a <a href="%1$s" target="_blank">jQuery selector</a> to get each track from the tracklist page, for example: %2$s','xspfpl'),
                'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
                '<code>#content #tracklist .track</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors][tracks]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function tracklist_raw_content_callback(){
        
        
        $output = "—";
        
        $track_nodes = xspfpl_get_playlist()->get_track_nodes();
        
        if ( !$track_nodes || is_wp_error($track_nodes) ) return $track_nodes;

        $output = '<pre class="xspf-raw"><code class="language-markup">';


        foreach ($track_nodes as $node){

            $node_content = $node->html();

            //$indenter = new \Gajus\Dindent\Indenter();
            //$output.= $indenter->indent( $node->html() );

            $output .= esc_html($node_content)."\r\n\n";

        }
        $output.= '</code></pre>';
            
        echo $output;

    }

    function section_track_details_desc(){
        _e('Enter a <a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">jQuery selectors</a> to extract the artist, title, album (optional) and image (optional) for each track.','xspfpl');
        echo"<br/>";
        _e('Advanced users can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches, using the links <strong>[...^]</strong>.','xspfpl');
    }
    
    function get_track_detail_selector_prefix(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');

        if (!$selectors['tracks']) return;
        return sprintf(
            '<span class="tracks-selector-prefix">%1$s</span>',
            $selectors['tracks']
        );
    }

    function track_artist_selector_callback(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');
        
        $option = ( isset($selectors['track_artist']) ) ? htmlspecialchars( $selectors['track_artist'], ENT_QUOTES) : null;
        $has_preset = (bool)xspfpl_get_playlist()->get_presets( array('selectors','track_artist') );

        echo $this->get_track_detail_selector_prefix();
        
        printf(
            '<input type="text" name="%1$s[selectors][track_artist]" value="%2$s" %3$s style="min-width:50%%"/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('eg. %1$s','xspfpl'),
                '<code>h4 .artist strong</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors][track_artist]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
        
    }
    
    function track_artist_regex_callback(){
        $this->regex_block('track_artist');
    }
    
    function track_title_selector_callback(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');
        $option = ( isset($selectors['track_title']) ) ? htmlspecialchars( $selectors['track_title'], ENT_QUOTES) : null;
        $has_preset = (bool)xspfpl_get_playlist()->get_presets( array('selectors','track_title') );
        
        echo $this->get_track_detail_selector_prefix();
        
        printf(
            '<input type="text" name="%1$s[selectors][track_title]" value="%2$s" %3$s style="min-width:50%%"/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('eg. %1$s','xspfpl'),
                '<code>span.track</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors][track_title]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function track_title_regex_callback(){
        $this->regex_block('track_title');
    }
    
    function track_album_selector_callback(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');
        $option = ( isset($selectors['track_album']) ) ? htmlspecialchars( $selectors['track_album'], ENT_QUOTES) : null;
        $has_preset = (bool)xspfpl_get_playlist()->get_presets( array('selectors','track_album') );
        
        echo $this->get_track_detail_selector_prefix();
        
        printf(
            '<input type="text" name="%1$s[selectors][track_album]" value="%2$s" %3$s style="min-width:50%%"/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('eg. %1$s','xspfpl'),
                '<code>span.album</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors][track_album]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function track_album_regex_callback(){
        $this->regex_block('track_album');
    }
    
    function track_image_selector_callback(){
        
        
        $selectors = xspfpl_get_playlist()->get_options('selectors');
        $option = ( isset($selectors['track_image']) ) ? htmlspecialchars( $selectors['track_image'], ENT_QUOTES) : null;
        $has_preset = (bool)xspfpl_get_playlist()->get_presets( array('selectors','track_image') );
        
        echo $this->get_track_detail_selector_prefix();
        
        printf(
            '<input type="text" name="%1$s[selectors][track_image]" value="%2$s" %3$s style="min-width:50%%"/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            $option,
            disabled( $has_preset , true, false),
            sprintf(
                __('eg. %1$s','xspfpl'),
                '<code>.album-art img</code>'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[selectors][track_image]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }

    
    function section_station_options_desc(){
        settings_errors('xspfpl-wizard-step-playlist-options');
    }
    
    function section_found_tracks_desc(){
        xspfpl_tracklist_table();
    }
    
    function cache_callback(){

        $option = xspfpl_get_playlist()->get_options('cache_tracks_intval');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('cache_tracks_intval');
        $is_disabled = ( $has_preset || xspfpl_get_playlist()->feed_is_xspf() || !xspfpl()->get_options('cache_tracks_intval') || !is_dir(xspfpl()->cache_dir) );
        
        printf(
            '<input type="number" name="%1$s[cache_tracks_intval]" size="4" min="0" value="%2$s" %3$s/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            $option,
            disabled( $is_disabled , true, false),
            sprintf(__('Cache page requested and tracks for %1$s seconds.  While using this wizard, only the page is cached, not the tracks.','xspfpl'),xspfpl()->get_options('cache_tracks_intval'))
        );
        
        if ($has_preset ){
            printf(
                '<input type="hidden" name="%1$s[cache_tracks_intval]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function musicbrainz_callback(){
        
        
        $option = xspfpl_get_playlist()->get_options('musicbrainz');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('musicbrainz');
        $is_disabled = ( $has_preset || xspfpl_get_playlist()->feed_is_xspf() );
        
        printf(
            '<input type="checkbox" name="%1$s[musicbrainz]" value="on" %2$s %3$s/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            checked((bool)$option, true, false),
            disabled( $is_disabled , true, false),
            sprintf(
                __('Sometimes, the metadatas (title,artist,...) of the tracks are not corrects. Enabling this will make <a href="%1$s" target="_blank">MusicBrainz</a> try to correct wrong values.
                    This makes the station render slower : each track takes about ~1 second to be checked with MusicBrainz.'),
                'http://musicbrainz.org/'
            )
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[musicbrainz]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function tracks_order_callback(){
        
        
        $option = xspfpl_get_playlist()->get_options('tracks_order');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('tracks_order');
        $is_disabled = ( $has_preset || xspfpl_get_playlist()->feed_is_xspf() );
        
        printf(
            '<input type="radio" name="%1$s[tracks_order]" value="desc" %2$s %3$s/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            checked($option, 'desc', false),
            disabled( $is_disabled , true, false),
            __('Descending','xspfpl')
        );
        echo"<br/>";
        printf(
            '<input type="radio" name="%1$s[tracks_order]" value="asc" %2$s %3$s/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            checked($option, 'asc', false),
            disabled( $is_disabled , true, false),
            __('Ascending','xspfpl')
        );
        printf(
            '<p class="wizard-field-desc">%1$s</p>',
            __('On the feed page, where is the most recent track ?  Choose "Descending" if it is on top, choose "Ascending" if it is in at the bottom.','xspfpl')
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[tracks_order]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }
    
    function frozen_callback(){
        
        
        $option = xspfpl_get_playlist()->get_options('is_frozen');
        $has_preset = (bool)xspfpl_get_playlist()->get_presets('is_frozen');
        $desc = null;
        
        if ($option){
            
            $desc = __('The playlist is currently frozen, so you cannot edit its settings.  Uncheck this to unfreeze it.','xspfpl');
            
        }else{
            
            $desc = __("Do not sync with the source feed.  This will clone the current tracklist locally and avoid having to parse the feed each time it is called.  Make sure you have updated your settings once before you check this.",'xspfpl');
        }
        
        printf(
            '<input type="checkbox" name="%1$s[is_frozen]" value="on" %2$s %3$s/><span class="wizard-field-desc">%4$s</span>',
            'xspfpl_wizard',
            checked((bool)$option, true, false),
            disabled( $has_preset , true, false),
            $desc
        );
        
        if ($has_preset){
            printf(
                '<input type="hidden" name="%1$s[is_frozen]" value="%2$s"/>',
                'xspfpl_wizard',
                $option
            );
        }
        
    }

    
    function tabs( $active_tab = '' ) {
        
        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $tracks_selector_tab = $playlist_service_tab = $track_details_tab = $tracklist_tab = $playlist_options_tab = array();
        
        if ($this->can_show_step('playlist_service')){
            
            $playlist_service_tab = array(
                'title'  => __('Variables','xspfpl'),
                'href'  => '#xspfpl-wizard-step-playlist-service-content'
            );
        }

        if ($this->can_show_step('tracks_selector')){
            $tracks_selector_tab = array(
                'title'  => __('Tracks Selector','xspfpl'),
                'href'  => '#xspfpl-wizard-step-tracks-selector-content'
            );
        }
        
        if ($this->can_show_step('track_details')){
            $track_details_tab = array(
                'title'  => __('Track details','xspfpl'),
                'href'  => '#xspfpl-wizard-step-track-details-content'
            );
        }
        
        if ($this->can_show_step('tracklist')){

            $tracklist_tab = array(
                'title'  => sprintf( __('Found Tracks : %d','xspfpl'),count( xspfpl_get_playlist()->get_tracks() ) ),
                'href'  => '#xspfpl-wizard-step-tracklist-content'
            );
        }
        
        if ($this->can_show_step('playlist_options')){
            $playlist_options_tab = array(
                'title'  => __('Station Settings','xspfpl'),
                'href'  => '#xspfpl-wizard-step-playlist-options-content'
            );
        }

        $tabs         = array(
            array(
                'title'  => __('Base URLs','xspfpl'),
                'href'  => '#xspfpl-wizard-step-base-content'
            ),
            $playlist_service_tab,
            $tracks_selector_tab,
            $track_details_tab,
            $tracklist_tab,
            $playlist_options_tab
        );
        
        $tabs = array_filter($tabs);

        // Loop through tabs and build navigation
        foreach ( array_values( $tabs ) as $key=>$tab_data ) {

                $is_current = (bool) ( $key == $active_tab );
                $tab_class  = $is_current ? $active_class : $idle_class;
                $tabs_html .= '<li><a href="' . $tab_data['href'] . '" class="' . esc_attr( $tab_class ) . '">' . esc_html( $tab_data['title'] ) . '</a></li>';
        }

        echo $tabs_html;
    }
    
    function wizard_metabox(){
    ?>
    <div id="xspf-wizard-tabs">
        <ul id="xspf-wizard-tabs-header">
            <?php $this->tabs(); ?>
        </ul>
        
        <?php settings_errors('wizard-steps');?>
        
        <div id="xspfpl-wizard-step-base-content" class="xspfpl-wizard-step-content">
            <?php do_settings_sections( 'xspfpl-wizard-step-base' );?>
        </div>
        
        <?php
        if ($this->can_show_step('playlist_service')){
            ?>
            <div id="xspfpl-wizard-step-playlist-service-content" class="xspfpl-wizard-step-content">
                <?php do_settings_sections( 'xspfpl-wizard-step-playlist-service' );?>
            </div>
            <?php
        }
        ?>
        
        <?php         
        if ($this->can_show_step('tracks_selector')){
            ?>
            <div id="xspfpl-wizard-step-tracks-selector-content" class="xspfpl-wizard-step-content">
                <?php do_settings_sections( 'xspfpl-wizard-step-tracks-selector' );?>
            </div>
            <?php
        }
        ?>
        
        <?php         
        if ($this->can_show_step('track_details')){
            ?>
            <div id="xspfpl-wizard-step-track-details-content" class="xspfpl-wizard-step-content">
                <?php do_settings_sections( 'xspfpl-wizard-step-track-details' );?>
            </div>
            <?php
        }
        ?>
        
        <?php
        if ($this->can_show_step('tracklist')){
            ?>
            <div id="xspfpl-wizard-step-tracklist-content" class="xspfpl-wizard-step-content">
                <?php do_settings_sections( 'xspfpl-wizard-step-tracklist' );?>
            </div>
            <?php
        }
        ?>
        
        <?php
        if ($this->can_show_step('playlist_options')){
            ?>
            <div id="xspfpl-wizard-step-playlist-options-content" class="xspfpl-wizard-step-content">
                <?php do_settings_sections( 'xspfpl-wizard-step-playlist-options' );?>
            </div>
            <?php
        }
        ?>

    </div>
    <?php
    submit_button();
    wp_nonce_field(xspfpl()->basename,'xspfpl_wizard_nonce',false);
    
    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_settings($post_id, $input){
        
        $previous_values = xspfpl_get_playlist()->get_options();
        $new_input = $previous_values;
        
        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //frozen
        $new_input['is_frozen'] = ( isset($input['is_frozen']) ) ? $input['is_frozen'] : null;

        if ( $previous_values['is_frozen'] ){ //don't touch other settings
            
            $new_input = array_merge($previous_values, $new_input );
            
        }else{
            
            //cache
            if ( isset($input['cache_tracks_intval']) && is_numeric($input['cache_tracks_intval']) ){
                $new_input['cache_tracks_intval'] = $input['cache_tracks_intval'];
            }

             //feed url / do not FILTER_VALIDATE_URL as we may put %variables% as value
             if ( isset($input['feed_url']) ){
                 $new_input['feed_url'] = trim($input['feed_url']);
             }
            
            //website url / do not FILTER_VALIDATE_URL as we may put %variables% as value
             if ( isset($input['website_url']) && (!isset($new_input['feed_url']) || $new_input['feed_url']!=$input['website_url']) ){
                 $new_input['website_url'] = trim($input['website_url']);
             }

             //urls variables
             if ( isset($input['variables']) ) {
                 foreach ($input['variables'] as $var_slug=>$var_options){
                     if ( !xspfpl_get_playlist()->get_service_variables($var_slug) ) continue;
                     if ( !$var_options['value'] ) continue;
                     $new_input['variables'][$var_slug] = $var_options;
                 }
             }
             
             //service playlist suffix
             if ( isset($input['variable_title_suffix']) ) {
                 $new_input['variable_title_suffix'] = trim($input['variable_title_suffix']);
             }
             
             
            //TO FIX do not erase selectors and regex if they are not enabled
            //selectors
            if ( isset($input['selectors']) ){
                foreach ($input['selectors'] as $key=>$value){
                    $new_input['selectors'][$key] = trim($value);
                }
            }

            //regex
            if ( isset($input['selectors_regex']) ){
                foreach ($input['selectors_regex'] as $key=>$value){
                    $new_input['selectors_regex'][$key] = trim($value);
                }
            }

             //order
             $new_input['tracks_order'] = ( isset($input['tracks_order']) ) ? $input['tracks_order'] : null;

             //musicbrainz
             $new_input['musicbrainz'] = ( isset($input['musicbrainz']) ) ? $input['musicbrainz'] : null;
            
        }
        
        //cache is not enabled or is_frozen have been unselected, delete existing cache
        if ( 
            ( !isset($new_input['cache_tracks_intval']) && $previous_values['cache_tracks_intval'] ) || 
            ( ($new_input['is_frozen'] == false) && $previous_values['is_frozen'] ) 
        ) {
            xspfpl_get_playlist()->delete_cache();
        }

        return $new_input;
    }

    function save_settings($post_id, $data){

        $data = $this->sanitize_settings($post_id, $data);

        $new_input = array();
        $default_args = XSPFPL_Single_Playlist::get_default_options();
        
        //ignore presets TO FIX

        //ignore default values
        foreach ( $default_args as $slug => $default ){
            if ( !isset($data[$slug]) ) continue;
            if ($data[$slug]==$default) continue;
            $new_input[$slug] = $data[$slug];
        }

        if ($result = update_post_meta( $post_id, XSPFPL_Single_Playlist::$meta_key_settings, $new_input )){
            do_action('xspfpl_save_wizard_settings', $new_input, $post_id);
            return $result;
        }

        

    }

    function save($post_id){

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = false;
        if ( isset($_POST[ 'xspfpl_wizard_nonce' ]) && wp_verify_nonce( $_POST['xspfpl_wizard_nonce'], xspfpl()->basename)) $is_valid_nonce=true;

        if ($is_autosave || $is_revision || !$is_valid_nonce) return;

        if( get_post_type($post_id)!=xspfpl()->station_post_type ) return;
        
        $data = ( isset($_POST[ 'xspfpl_wizard' ]) ) ? $_POST[ 'xspfpl_wizard' ] : null;

        $this->save_settings( $post_id, $data );
    }
    
}

new XSPFPL_Wizard();

?>
