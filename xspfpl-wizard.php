<?php

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
                'error_codes'   => array('tracklist_url','tracklist_page_empty')
            ),$step_default),
            
            wp_parse_args(array(
                'title'         => __('Tracks Selector','xspfpl'),
                'desc'          => __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> to get each track from the tracklist page, for example: <code>#content #tracklist .track</code>','xspfpl'),
                'error_codes'   => array('tracks_selector')
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Track Infos','xspfpl'),
                'desc'          => sprintf('%s<br/>%s',
                                    __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selectors</a> to extract the artist (eg. <code>h4 .artist strong</code>) and title (eg. <code>span</code>) for each track.','xspfpl'),
                                    __('You can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches.','xspfpl'))
                                    
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Playlist Options','xspfpl'),
                'feedback_box'  => false,
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
            'feedback_box'  => true,
            'required'      => true
        );
        return $step;
    }
    
    
    function wizard(){

        ?>
        <div id="xspfpl-wizard">
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
<?php 
                //feedback box link
                if ($this->steps[$step_key]['feedback_box']){?>
                	<span class="feedback-link"><a href="#"><?php _e('Show feedback','xspfpl');?></a></span>
            	<?php }?>
                <h3 class="xspf-wizard-step-title"><?php echo $this->steps[$step_key]['title'];?></h3>
                <p class="xspf-wizard-step-desc"><?php echo $this->steps[$step_key]['desc'];?></p>
                
                <?php self::wizard_errors_block($step_key);?>
                
            </div>
            <?php
            switch ($step_key){
                case 0:
                    ?>
                    <input name="<?php $this->field_name('tracklist_url');?>" type="text" value="<?php echo $this->playlist->tracklist_url;?>"/>
                    <?php
                break;
                case 1:
                    ?>
                    <input name="<?php $this->field_name('tracks_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->tracks_selector;?>"/>
                    <?php
                break;
                case 2:
                    ?>
                    <!-- track artist-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_artist_selector');?>"><?php _e('Artist Selector','xspfpl');?> *</label>
                        <input name="<?php $this->field_name('track_artist_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_artist_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspfpl');?></a>
                        <?php self::wizard_regex_block('track_artist_regex');?>
                    </div>
                    <!-- track title-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_title_selector');?>"><?php _e('Title Selector','xspfpl');?> *</label>
                        <input name="<?php $this->field_name('track_title_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_title_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspfpl');?></a>
                        <?php self::wizard_regex_block('track_title_regex');?>
                    </div>
                    <!-- track album-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_selector');?>"><?php _e('Album Selector','xspfpl');?></label>
                        <input name="<?php $this->field_name('track_album_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_album_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspfpl');?></a>
                        <?php self::wizard_regex_block('track_album_regex');?>
                    </div>

                    <!-- track image-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_art_selector');?>"><?php _e('Image Selector','xspfpl');?></label>
                        <input name="<?php $this->field_name('track_album_art_selector');?>" class="code selector" type="text" value="<?php echo $this->playlist->track_album_art_selector;?>"/>
                    </div>
                    <?php
                break;
                case 3:
                    ?>
                    <!-- MusicBrainz -->
                    <div>
                        <input name="<?php $this->field_name('musicbrainz');?>" type="checkbox"<?php checked((bool)$this->playlist->musicbrainz);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('musicbrainz');?>"><?php _e('Compare tracks informations with <a href="http://musicbrainz.org/" target="_blank">MusicBrainz</a>','xspfpl');?></label>
                        <p class="xspfpl-info">
                            <?php _e('Sometimes, the metadatas (title,artist,...) of the tracks are not corrects.  Enabling this will make MusicBrainz try to correct wrong values.','xspfpl');?><br/>
                            <?php _e('This makes the playlist render slower : each track takes about ~1 second to be checked with MusicBrainz.','xspfpl');?>
                        </p>
                    </div>

                    <!-- XSPF link embed -->
                    <div>
                        <input name="<?php $this->field_name('xspf_link');?>" type="checkbox"<?php checked((bool)$this->playlist->xspf_link);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('xspf_link');?>"><?php _e('XSPF link','xspfpl');?></label>
                        <p class="xspfpl-info">
                            <?php _e('Adds automatically an XPSF link to your post content.','xspfpl');?><br/>
                            <?php printf(__('You might want to disable this and use function %s instead, in your templates.','xspfpl'),'<code>xspfpl_get_xspf_permalink()</code>');?><br/>
                        </p>
                    </div>

                    <!-- Toma.hk embed -->
                    <div>
                        <input name="<?php $this->field_name('tomahk_embed');?>" type="checkbox"<?php checked((bool)$this->playlist->tomahk_embed);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('tomahk_embed');?>"><?php _e('Embed playlist from <a href="http://toma.hk/tools/embeds.php" target="_blank">Toma.hk</a>','xspfpl');?></label>
                        <p class="xspfpl-info">
                            <?php _e('Displays the playlist generated from the toma.hk website, under your post.','xspfpl');?><br/>
                            <?php _e('ATTENTION : Toma.hk playlists do not update automatically yet, but this will be improved.','xspfpl');?><br/>
                        </p>
                    </div>
                    <?php
                break;
            }
            
            //feedback box
            $this->wizard_feedback($step_key);
            
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
            <span class="code"><input class="regex" id="<?php $this->field_name($field_name);?>" name="<?php $this->field_name($field_name);?>" type="text" value="<?php echo esc_html($this->playlist->$field_name);?>"/></span>
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
        
        if (!$this->steps[$step_key]['feedback_box']) return $feedback;
        
        switch ($step_key){
            case 0:
                if ($this->playlist->body_el){
                    $body_html = htmlspecialchars($this->playlist->body_el->htmlOuter());
                    $feedback = '<pre><code class="output code html">'.$body_html.'</code></pre>';
                }
            break;
            case 1:
                $tracklist_items = $this->playlist->get_tracklist();
                
                if (!$tracklist_items) break;
                
                $feedback = '<div>';
                foreach($tracklist_items as $track_el) {
                    $track = htmlspecialchars(pq($track_el)->html());
                    $feedback .= '<pre><code class="output code html">'.$track.'</code></pre>';
                }
                $feedback .= '</div>';
            break;
            case 2:
                if (empty($this->playlist->tracks)) break;
                $feedback = '<ol>';
                foreach($this->playlist->tracks as $track) {
                    $feedback .='<li class="output code"><ul>';
                    $feedback .='<li><strong>'.__('Artist','xspfpl').':</strong> '.$track['artist'].'</li>';
                    $feedback .='<li><strong>'.__('Title','xspfpl').':</strong> '.$track['title'].'</li>';
                    $feedback .='<li><strong>'.__('Album','xspfpl').':</strong> '.$track['album'].'</li>';
                    $feedback .='<li><strong>'.__('Image','xspfpl').':</strong> '.$track['image'].'</li>';
                    $feedback .='</ul></li>';
                }
                $feedback .= '</ol>';
            break;
        }
        
        if (!$feedback) return false;
        
        echo '<div class="feedback-wrapper"><div class="feedback-content">'.$feedback.'</div></div>';
        
    }
    

    
    
    static function wizard_save($post_id){
        
            $args = array();
            $default_args = XSPFPL_Single_Playlist::get_default_args();
            
            foreach ( (array)$default_args as $slug => $default ){
                $args[$slug] = self::get_field_value($slug);
            }
       
            $args = apply_filters('xspfpl_save_args',$args,$post_id);

            //remove default values
            foreach ( $args as $slug => $value ){
                if ( $value==$default_args[$slug] ) unset($args[$slug]);
            }

            update_post_meta( $post_id, XSPFPL_Single_Playlist::$meta_key_settings, $args );

            do_action('xspfpl_save', $args, $post_id);

    }
    
    static function field_name($slug){
        echo self::get_field_name($slug);
    }
    
    static function get_field_name($slug){
        return 'xspfpl['.$slug.']';
    }
    static function get_field_value($slug){
        
        $bools = array('musicbrainz','tomahk_embed','xspf_link');
        
        if (!isset($_POST['xspfpl'][$slug])) return false;
        $value = $_POST['xspfpl'][$slug];
        
        //regex
        if ( ($slug=='track_artist_regex') || ($slug=='track_title_regex') || ($slug=='track_album_regex') ){
            $value = addslashes($value);
        }
        
        //bools
        if ( in_array($slug,$bools) ){
            $value = (bool)$value;
        }
        
        return $value;
    }
    
}

?>