<?php

class xspf_plgen_wizard {
    
    var $playlist;
    var $steps = array();
    
    function __construct($post_id = false) {
        $this->playlist = new xspf_plgen_playlist($post_id);
        $this->playlist->is_wizard = true;
        $this->playlist->populate_tracks();
        
        $step_default = self::step_defaults();
        
        $this->steps = array(
            
            wp_parse_args(array(
                'title'         => __('Playlist URL','xspf-plgen'),
                'desc'          => __('Enter the URL of the page where the tracklist is displayed.','xspf-plgen'),
                'error_codes'   => array('tracklist_url','tracklist_page_empty')
            ),$step_default),
            
            wp_parse_args(array(
                'title'         => __('Tracks Selector','xspf-plgen'),
                'desc'          => __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> to get each track from the tracklist page, for example: <code>#content #tracklist .track</code>','xspf-plgen'),
                'error_codes'   => array('tracks_selector')
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Track Infos','xspf-plgen'),
                'desc'          => sprintf('%s<br/>%s',
                                    __('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selectors</a> to extract the artist (eg. <code>h4 .artist strong</code>) and title (eg. <code>span</code>) for each track.','xspf-plgen'),
                                    __('You can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches.','xspf-plgen'))
                                    
            ),$step_default),
            
            wp_parse_args(array(
                'title'  => __('Playlist Options','xspf-plgen'),
                'feedback_box'  => false,
                'required'      => false
            ),$step_default)
        );
        
        
    }
    
    static function scripts_styles(){
        wp_enqueue_style( 'xspf-plgen-wizard', xspf_plgen()->plugin_url .'_inc/css/wizard.css', array(), xspf_plgen()->version );
        wp_enqueue_script( 'xspf-plgen-wizard', xspf_plgen()->plugin_url .'_inc/js/wizard.js', array( 'jquery-ui-tabs' ), xspf_plgen()->version );
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
        <div id="xspf-plgen-wizard">
            <!--
            <ul>
                <li><a href="#xspf-wizard-step-1" class="nav-tab nav-tab-active"><?php _e('Tracklist URL','xspf-plgen');?></a></li>
                <li><a href="#xspf-wizard-step-2" class="nav-tab"><?php _e('Tracks Selector','xspf-plgen');?></a></li>
                <li><a href="#xspf-wizard-step-3" class="nav-tab"><?php _e('Track Infos','xspf-plgen');?></a></li>
                <li><a href="#xspf-wizard-step-4" class="nav-tab"><?php _e('Playlist Options','xspf-plgen');?></a></li>
            </ul>
            -->
            <?php

            // display steps
            foreach ($this->steps as $key=>$step){
                self::wizard_step($key);
            }

            wp_nonce_field(xspf_plgen()->basename,'xspf_plgen_form',false);
            
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
                	<span class="feedback-link"><a href="#"><?php _e('Show feedback','xspf-plgen');?></a></span>
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
                        <label for="<?php $this->field_name('track_artist_selector');?>"><?php _e('Artist Selector','xspf-plgen');?> *</label>
                        <input name="<?php $this->field_name('track_artist_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_artist_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspf-plgen');?></a>
                        <?php self::wizard_regex_block('track_artist_regex');?>
                    </div>
                    <!-- track title-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_title_selector');?>"><?php _e('Title Selector','xspf-plgen');?> *</label>
                        <input name="<?php $this->field_name('track_title_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_title_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspf-plgen');?></a>
                        <?php self::wizard_regex_block('track_title_regex');?>
                    </div>
                    <!-- track album-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_selector');?>"><?php _e('Album Selector','xspf-plgen');?></label>
                        <input name="<?php $this->field_name('track_album_selector');?>" class="selector code" type="text" value="<?php echo $this->playlist->track_album_selector;?>"/>
                        <a href="#" class="regex-link"><?php _e('Regex','xspf-plgen');?></a>
                        <?php self::wizard_regex_block('track_album_regex');?>
                    </div>

                    <!-- track image-->
                    <div class="track-info">
                        <label for="<?php $this->field_name('track_album_art_selector');?>"><?php _e('Image Selector','xspf-plgen');?></label>
                        <input name="<?php $this->field_name('track_album_art_selector');?>" class="code selector" type="text" value="<?php echo $this->playlist->track_album_art_selector;?>"/>
                    </div>
                    <?php
                break;
                case 3:
                    ?>
                    <!-- MusicBrainz -->
                    <div>
                        <input name="<?php $this->field_name('musicbrainz');?>" type="checkbox"<?php checked($this->playlist->musicbrainz);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('musicbrainz');?>"><?php _e('Compare tracks informations with <a href="http://musicbrainz.org/" target="_blank">MusicBrainz</a>','xspf-plgen');?></label>
                        <p class="xspf-plgen-info">
                            <?php _e('Sometimes, the metadatas (title,artist,...) of the tracks are not corrects.  Enabling this will make MusicBrainz try to correct wrong values.','xspf-plgen');?><br/>
                            <?php _e('This makes the playlist render slower : each track takes about ~1 second to be checked with MusicBrainz.','xspf-plgen');?>
                        </p>
                    </div>

                    <!-- XSPF link embed -->
                    <div>
                        <input name="<?php $this->field_name('xspf_link');?>" type="checkbox"<?php checked($this->playlist->xspf_link);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('xspf_link');?>"><?php _e('XSPF link','xspf-plgen');?></label>
                        <p class="xspf-plgen-info">
                            <?php _e('Adds automatically an XPSF link to your post content.','xspf-plgen');?><br/>
                            <?php printf(__('You might want to disable this and use function %s instead, in your templates.','xspf-plgen'),'<code>xspf_plgen_get_xspf_permalink()</code>');?><br/>
                        </p>
                    </div>

                    <!-- Toma.hk embed -->
                    <div>
                        <input name="<?php $this->field_name('tomahk_embed');?>" type="checkbox"<?php checked($this->playlist->tomahk_embed);?> value="1"/>
                        <label style="display:inline" for="<?php $this->field_name('tomahk_embed');?>"><?php _e('Embed playlist from <a href="http://toma.hk/tools/embeds.php" target="_blank">Toma.hk</a>','xspf-plgen');?></label>
                        <p class="xspf-plgen-info">
                            <?php _e('Displays the playlist generated from the toma.hk website, under your post.','xspf-plgen');?><br/>
                            <?php _e('ATTENTION : Toma.hk playlists do not update automatically yet, but this will be improved.','xspf-plgen');?><br/>
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
            <label for="<?php $this->field_name($field_name);?>"><?php _e('Regex Filter','xspf-plgen');?></label>
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
                $tracks = $this->playlist->get_tracks();
                if (!$tracks) break;
                $feedback = '<ol>';
                foreach($tracks as $track) {
                    $feedback .='<li class="output code"><ul>';
                    $feedback .='<li><strong>'.__('Artist','xspf-plgen').':</strong> '.$track['artist'].'</li>';
                    $feedback .='<li><strong>'.__('Title','xspf-plgen').':</strong> '.$track['title'].'</li>';
                    $feedback .='<li><strong>'.__('Album','xspf-plgen').':</strong> '.$track['album'].'</li>';
                    $feedback .='<li><strong>'.__('Image','xspf-plgen').':</strong> '.$track['image'].'</li>';
                    $feedback .='</ul></li>';
                }
                $feedback .= '</ol>';
            break;
        }
        
        if (!$feedback) return false;
        
        echo '<div class="feedback-wrapper"><div class="feedback-content">'.$feedback.'</div></div>';
        
    }
    

    
    
    static function wizard_save($post_id){

            $args = array(
                'tracklist_url'   => self::get_field_value('tracklist_url'),
                'tracks_selector' => self::get_field_value('tracks_selector'),
                'track_artist_selector' => self::get_field_value('track_artist_selector'),
                'track_artist_regex' => self::get_field_value('track_artist_regex'),
                'track_title_selector' => self::get_field_value('track_title_selector'),
                'track_title_regex' => self::get_field_value('track_title_regex'),
                'track_album_selector' => self::get_field_value('track_album_selector'),
                'track_album_art_selector' => self::get_field_value('track_album_art_selector'),
                'track_album_regex' => self::get_field_value('track_album_regex'),
                'track_comment_selector' => self::get_field_value('track_comment_selector'),
                'musicbrainz' => (bool)self::get_field_value('musicbrainz'),
                'tomahk_embed' => (bool)self::get_field_value('tomahk_embed'),
                'xspf_link' => (bool)self::get_field_value('xspf_link'),
            );
            
            $default_args = xspf_plgen_playlist::get_default_args();

            /*
            foreach ((array)$default_args as $meta_key=>$null){
                if (!isset($args[$meta_key])) continue;
                $save_args[$meta_key] = $args[$meta_key];
            }
             */
            
            $save_args = wp_parse_args($args,$default_args);

            $save_args = array_filter($save_args);            
            $save_args = apply_filters('xspf_plgen_save_args',$save_args,$post_id);

            foreach ($save_args as $meta_key=>$meta_value){
                update_post_meta($post_id, $meta_key,$meta_value);
            }

            do_action('xspf_plgen_save', $save_args, $post_id);

    }
    
    static function field_name($slug){
        echo self::get_field_name($slug);
    }
    
    static function get_field_name($slug){
        return 'xspf-plgen['.$slug.']';
    }
    static function get_field_value($slug){
        if (!isset($_POST['xspf-plgen'][$slug])) return false;
        $value = $_POST['xspf-plgen'][$slug];
        
        //regex
        if ( ($slug=='track_artist_regex') || ($slug=='track_title_regex') || ($slug=='track_album_regex') ){
            $value = addslashes($value);
        }
        
        return $value;
    }
    
}


class xspf_plgen_admin {
    
    var $wizard;

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
        add_action( 'admin_head',  array( $this, 'populate_post_playlist' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );
        add_action( 'add_meta_boxes',  array( $this, 'wizard_init' ) );
        add_action( 'save_post',  array( $this, 'wizard_save' ) );

    }
    
    function populate_post_playlist(){
        global $pagenow;

        if( get_post_type()!=xspf_plgen()->post_type ) return false;

        $this->wizard = new xspf_plgen_wizard( get_the_ID() );

        
    }
    
    public function scripts_styles($hook) {
        if( get_post_type()!=xspf_plgen()->post_type ) return false;
        xspf_plgen_wizard::scripts_styles();
    }
    
    function wizard_init(){
        add_meta_box( 'xspf_plgen', __('Wizard','xspf-plgen'), array(&$this->wizard,'wizard'), xspf_plgen()->post_type,'normal','high');
    }
    function wizard_save($post_id){
        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = false;
        if ( isset( $_POST[ 'xspf_plgen_form' ]) && wp_verify_nonce( $_POST['xspf_plgen_form'], xspf_plgen()->basename)) $is_valid_nonce=true;

        if ($is_autosave || $is_revision || !$is_valid_nonce) return;
        if( get_post_type($post_id)!=xspf_plgen()->post_type ) return;
        
        xspf_plgen_wizard::wizard_save($post_id);
    }

}

?>