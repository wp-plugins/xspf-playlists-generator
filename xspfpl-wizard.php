<?php

/**
 * Wizard class.  Used to build a playlist.  Actually only in the backend, will maybe be extended to the frontend someday.
 */

class XSPFPL_Playlist_Wizard {
    
    var $playlist;
    var $steps = array();
    
    function __construct($post_id = false) {
        $this->playlist = new XSPFPL_Single_Playlist($post_id);
        $this->playlist->is_wizard = true;
        $this->playlist->populate_tracks();
        
        $step_default = self::step_defaults();
        
        $this->steps = array(
            
            wp_parse_args(array(
                'title'         => __('Playlist URL','xspfpl'),
                'desc'          => __('Enter the URL of the page where the tracklist is displayed.','xspfpl'),
                'error_codes'   => array('tracklist_url','tracklist_page_empty'),
            ),$step_default),
            
            wp_parse_args(array(
                'title'         => __('Tracks Selector','xspfpl'),
                'desc'          => __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> to get each track from the tracklist page, for example: <code>#content #tracklist .track</code>','xspfpl'),
                'error_codes'   => array('tracks_selector')
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Track Infos','xspfpl'),
                'desc'          => sprintf('%s<br/>%s',
                                    __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selectors</a> to extract the artist, title, album (optional) and image (optional) for each track.','xspfpl'),
                                    __('Advanced users can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches, using the links <strong>[...^]</strong>.','xspfpl'))
                                    
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Playlist Options','xspfpl'),
                'required'      => false
            ),$step_default)
        );
        
        
    }
    
    static function scripts_styles(){
        wp_enqueue_style( 'xspfpl-wizard', xspfpl()->plugin_url .'_inc/css/wizard.css', array(), xspfpl()->version );
        wp_enqueue_script( 'xspfpl-wizard', xspfpl()->plugin_url .'_inc/js/wizard.js', array( 'jquery-ui-tabs' ), xspfpl()->version );
    }
    
    function step_defaults(){
        $step = array(
            'title'          =>'',
            'desc'          => '',
            'error_codes'   => array(),
            'required'      => true
        );
        return $step;
    }
    
    
    function wizard_metabox(){
        $wizard_classes=array();
        if ($this->playlist->get_option('is_static')) $wizard_classes[] = 'static';
        ?>
        <div id="xspfpl-wizard"<?php xspf_classes($wizard_classes);?>>
            <!--
            <ul>
                <li><a href="#xspf-wizard-step-1" class="nav-tab nav-tab-active"><?php _e('Tracklist URL','xspfpl');?></a></li>
                <li><a href="#xspf-wizard-step-2" class="nav-tab"><?php _e('Tracks Selector','xspfpl');?></a></li>
                <li><a href="#xspf-wizard-step-3" class="nav-tab"><?php _e('Track Infos','xspfpl');?></a></li>
                <li><a href="#xspf-wizard-step-4" class="nav-tab"><?php _e('Playlist Options','xspfpl');?></a></li>
            </ul>
            -->
            <?php

            // display steps
            foreach ($this->steps as $key=>$step){
                self::wizard_step($key);
            }

            wp_nonce_field(xspfpl()->basename,'xspfpl_form',false);
            
            ?>
        </div>
        <?php
    }
    
    function wizard_step($step_key){
        $step = $step_key+1;
        $step_classes = array('xspf-wizard-step');
        $previous_errors = self::wizard_get_previous_errors($step_key);
        $errors = self::wizard_errors($step_key);
        
        //required
        if ($this->steps[$step_key]['required']){
            $step_classes[]='is-required';
        }
        
        
        //if ($previous_errors) $step_classes[]='hidden';
        if ($errors){
            $step_classes[]='has-errors';
            foreach ($errors as $slug=>$error){
                $step_classes[]='has-error-'.$slug;
            }
        }
        
        ?>
        <div id="xspf-wizard-step-<?php echo $step;?>"<?php xspf_classes($step_classes);?>>
            <div class="xspf-wizard-step-header">
                <h3 class="xspf-wizard-step-title"><?php echo $this->steps[$step_key]['title'];?></h3>
                <p class="xspf-wizard-step-desc"><?php echo $this->steps[$step_key]['desc'];?></p>
                
                <?php self::wizard_errors_block($step_key);?>
                
            </div>
            <?php
            switch ($step_key){
                case 0:
                    ?>
                    <input id="<?php $this->field_name('tracklist_url');?>" name="<?php $this->field_name('tracklist_url');?>" type="text" value="<?php echo $this->playlist->get_option('tracklist_url');?>"/>
                    <?php
                break;
                case 1:
                    ?>
                    
                    <input id="<?php $this->field_name('tracks_selector');?>" name="<?php $this->field_name('tracks_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->get_option('tracks_selector');?>"/>
                    <?php
                    $this->wizard_feedback($step_key);
                break;
                case 2:
                    ?>
                    
                    <!-- track artist-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_artist_selector');?>"><?php _e('Artist Selector','xspfpl');?> * <a href="#" title="<?php _e('Use Regular Expression','xspfpl');?>" class="regex-link">[...^]</a></label>
                        <input id="<?php $this->field_name('track_artist_selector');?>" name="<?php $this->field_name('track_artist_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->get_option('track_artist_selector');?>"/>
                        <?php _e('eg. <code>h4 .artist strong</code>');?>
                        <?php self::wizard_regex_block('track_artist_regex');?>
                    </div>
                    <!-- track title-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_title_selector');?>"><?php _e('Title Selector','xspfpl');?> * <a href="#" title="<?php _e('Use Regular Expression','xspfpl');?>" class="regex-link">[...^]</a></label>
                        <input id="<?php $this->field_name('track_title_selector');?>" name="<?php $this->field_name('track_title_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->get_option('track_title_selector');?>"/>
                        <?php _e('eg. <code>span.track</code>');?>
                        <?php self::wizard_regex_block('track_title_regex');?>
                    </div>
                    <!-- track album-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_selector');?>"><?php _e('Album Selector','xspfpl');?> <a href="#" title="<?php _e('Use Regular Expression','xspfpl');?>" class="regex-link">[...^]</a></label>
                        <input id="<?php $this->field_name('track_album_selector');?>" name="<?php $this->field_name('track_album_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->get_option('track_album_selector');?>"/>
                        <?php _e('eg. <code>span.album</code>');?>
                        <?php self::wizard_regex_block('track_album_regex');?>
                    </div>

                    <!-- track image-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_art_selector');?>"><?php _e('Image Selector','xspfpl');?></label>
                        <input name="<?php $this->field_name('track_album_art_selector');?>" class="code selector" type="text" value="<?php echo $this->playlist->get_option('track_album_art_selector');?>"/>
                        <?php _e('eg. <code>.album-art img</code>');?>
                    </div>
                    <?php
                    $this->wizard_feedback($step_key);
                break;
                case 3:
                    ?>
                    <!-- MusicBrainz -->
                    <div>
                        <?php
                        $checked_musicbrainz = (bool)$this->playlist->get_option('musicbrainz');
                        ?>
                        <input id="<?php $this->field_name('musicbrainz');?>" name="<?php $this->field_name('musicbrainz');?>" type="checkbox"<?php checked($checked_musicbrainz);?> value="on"/>
                        <label style="display:inline" for="<?php $this->field_name('musicbrainz');?>"><?php _e('Compare tracks informations with <a href="http://musicbrainz.org/" target="_blank">MusicBrainz</a>','xspfpl');?></label>
                        <p class="xspfpl-info">
                            <?php _e('Sometimes, the metadatas (title,artist,...) of the tracks are not corrects.  Enabling this will make MusicBrainz try to correct wrong values.','xspfpl');?><br/>
                            <?php _e('This makes the playlist render slower : each track takes about ~1 second to be checked with MusicBrainz.','xspfpl');?><br/>
                            <?php _e('(Ignored in the wizard, for non-freezed playlists)','xspfpl');?>
                        </p>
                    </div>
                    <!-- Is static -->
                    <div>
                        <?php
                        $checked_static = (bool)$this->playlist->get_option('is_static');
                        ?>
                        <input id="<?php $this->field_name('is_static');?>" name="<?php $this->field_name('is_static');?>" type="checkbox"<?php checked($checked_static);?> value="on"/>
                        <label style="display:inline" for="<?php $this->field_name('is_static');?>"><?php _e('Freeze this playlist','xspfpl');?></label>
                        <p class="xspfpl-info">
                            <?php _e('If the playlist you parse is static — not like a radio station playlist —, you could freeze your playlist once your settings are correct (check the XSPF file first).','xspfpl');?><br/>
                            <?php _e('This will save the whole tracklist locally and avoid parsing the remote web page each time it is called.','xspfpl');?><br/>
                            <?php _e('Safer and faster !','xspfpl');?>
                        </p>
                    </div>
                    <?php
                break;
            }
            
            ?>
        </div><!-- end of .xspf-wizard-step-->
        <hr/>
        <?php
    }
    
    function wizard_errors_block($step_key){
        $errors = self::wizard_errors($step_key);
        if (empty($errors)) return;
        ?>
        <div class="step-errors">
            <?php
            foreach ((array)$errors as $slug=>$error){
                ?>
                    <div class="step-error"><div class="dashicons dashicons-no-alt"></div><?php echo $error;?></div>
                <?php
            }
            ?>
        </div>
        <?php
    }
    
    function wizard_regex_block($field_name){
        ?>
        <div class="regex-wrapper">
            <label for="<?php $this->field_name($field_name);?>"><?php _e('Regex Filter','xspfpl');?></label>
            <span class="code"><input class="regex" id="<?php $this->field_name($field_name);?>" name="<?php $this->field_name($field_name);?>" type="text" value="<?php echo esc_html($this->playlist->get_option($field_name));?>"/></span>
        </div>
        <?php
    }
    
    function wizard_errors($step_key){
        $error_codes = $this->playlist->errors->get_error_codes();
        
        $step_errors = array();
        if (!isset($this->steps[$step_key]['error_codes'])) return $step_errors;
        $step_error_codes = $this->steps[$step_key]['error_codes'];
        
        foreach ((array) $error_codes as $code){
            if (!in_array($code,$step_error_codes)) continue;
            $step_errors[$code] = $this->playlist->errors->get_error_message($code);
        }
        return $step_errors;
    }
    function wizard_get_previous_errors($step_key){
        
        $previous_errors = array();
        if ($step_key == 0) return $previous_errors;
        
        for ($i = 0; $i < $step_key; $i++) {
            $previous_errors = array_merge(self::wizard_errors($i),$previous_errors);
        }
        return $previous_errors;
    }
    
    function wizard_feedback($step_key){
        $feedback = '';

        switch ($step_key){
            case 1: //Tracks Selector
                
                if ($this->playlist->body_el){
                    $body_html = htmlspecialchars($this->playlist->body_el->htmlOuter());
                    $feedback = '<pre><code class="output code html">'.$body_html.'</code></pre>';
                    
                }
            break;
            case 2: //Track Infos
                $tracklist_items = $this->playlist->get_tracklist();
                
                if (!$tracklist_items) break;
                
                $feedback = '<div>';
                foreach($tracklist_items as $track_el) {
                    $track = htmlspecialchars(pq($track_el)->html());
                    $feedback .= '<pre><code class="output code html">'.$track.'</code></pre>';
                }
                $feedback .= '</div>';
            break;
        }
        
        if (!$feedback) return false;

        echo '<div class="feedback-wrapper"><span class="feedback-link"><a href="#">'.__('Show input','xspfpl').'</a></span><div class="feedback-content">'.$feedback.'</div></div>';
        
    }
    

    
    
    static function wizard_save($post_id){
        
            $args = array();
            $default_args = XSPFPL_Single_Playlist::get_default_options();
            
            $data = self::get_wizard_data();

            //remove default values
            foreach ( $data as $slug => $value ){
                if ( $value==$default_args[$slug] ) unset($data[$slug]);
            }

            update_post_meta( $post_id, XSPFPL_Single_Playlist::$meta_key_settings, $data );

            do_action('xspfpl_save', $data, $post_id);

    }
    
    static function field_name($slug){
        echo self::get_field_name($slug);
    }
    
    static function get_field_name($slug){
        return 'xspfpl['.$slug.']';
    }

    static function get_wizard_data(){
        if (!isset($_POST['xspfpl'])) return false;
        return self::sanitize_data($_POST['xspfpl']);
    }
    
    /*
     * Sanitize wizard data
     */
    
    static function sanitize_data($input){
        
        $new_input = array();
        
        //tracklist url
        if (isset($input['tracklist_url'])) $new_input['tracklist_url'] = $input['tracklist_url'];
        
        //selectors
        if (isset($input['tracks_selector'])) $new_input['tracks_selector'] = $input['tracks_selector'];
        if (isset($input['track_artist_selector'])) $new_input['track_artist_selector'] = $input['track_artist_selector'];
        if (isset($input['track_title_selector'])) $new_input['track_title_selector'] = $input['track_title_selector'];
        if (isset($input['track_album_selector'])) $new_input['track_album_selector'] = $input['track_album_selector'];
        if (isset($input['track_album_art_selector'])) $new_input['track_album_art_selector'] = $input['track_album_art_selector'];

        //regex
        if (isset($input['track_artist_regex'])) $new_input['track_artist_regex'] = addslashes($input['track_artist_regex']);
        if (isset($input['track_title_regex'])) $new_input['track_title_regex'] = addslashes($input['track_title_regex']);
        if (isset($input['track_album_regex'])) $new_input['track_album_regex'] = addslashes($input['track_album_regex']);

        //musicbrainz
        if (isset($input['musicbrainz'])) $new_input['musicbrainz'] = $input['musicbrainz'];
        
        //static
        if (isset($input['is_static'])) $new_input['is_static'] = $input['is_static'];
        
        return $new_input;
    }
    
    function found_tracks_metabox(){
        xspfpl_tracks_table($this->playlist->tracks);
    }
    
}

?>
