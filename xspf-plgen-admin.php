<?php


class xspf_plgen_admin {
    
    var $post_playlist;

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
        add_action('admin_init',  array( $this, 'populate_post_playlist' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );
        add_action( 'add_meta_boxes',  array( $this, 'metabox_init' ) );
        add_action( 'save_post',  array( $this, 'metabox_save' ) );

    }
    
    function populate_post_playlist(){
        global $typenow;
 
        // when editing pages, $typenow isn't set until later!
        if (empty($typenow)) {
            // try to pick it up from the query string
            if (!empty($_GET['post'])) {
                $post = get_post($_GET['post']);
                $typenow = $post->post_type;
            }
            // try to pick it up from the quick edit AJAX post
            elseif (!empty($_POST['post_ID'])) {
                $post = get_post($_POST['post_ID']);
                $typenow = $post->post_type;
            }
        }

        if($typenow!=xspf_plgen()->post_type) return false;
        
        if (in_array( $pagenow, array( 'post-new.php' ) )){ // new post
            $this->post_playlist = new xspf_plgen_playlist();
        }else{ //existing post
            $this->post_playlist = new xspf_plgen_playlist($post->ID);
        }
        
    }
    
    public function scripts_styles() {
            wp_enqueue_style( 'xspf-plgen-admin', xspf_plgen()->plugin_url .'_inc/css/admin.css', array(), xspf_plgen()->version );
    }
    
    function metabox_init(){
        add_meta_box( 'xspf_plgen', __('Playlist Options','xspf-plgen'), array(&$this,'metabox_content'), xspf_plgen()->post_type,'normal','high');
    }
    
    function metabox_content(){
        global $post;
        global $pagenow;

        $tracks_selector = $this->post_playlist->tracks_selector;
        ?>
        <!-- tracklist URL-->
        <p>
            
            <label for="<?php $this->field_name('tracklist_url');?>"><?php _e('Tracklist URL','xspf-plgen');?> *</label>
            <span class="xspf-plgen-info">
                <?php _e('Enter the URL of the page where the tracklist is displayed.','xspf-plgen');?><br/>
            </span>
            <input name="<?php $this->field_name('tracklist_url');?>" type="text" value="<?php echo $this->post_playlist->tracklist_url;?>"/>
        </p>
 
        <!-- tracks selector-->
        <p>
            
            <label for="<?php $this->field_name('tracks_selector');?>"><?php _e('Tracks Selector','xspf-plgen');?> *</label>
            <span class="xspf-plgen-info"><?php _e('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> to get each track from the tracklist page, for example: <code>#content #tracklist .track</code>','xspf-plgen');?></span>
            <input name="<?php $this->field_name('tracks_selector');?>" type="text" value="<?php echo $tracks_selector;?>"/>
            
            
            
            <div id="xspf-plgen-track">
                <!-- track artist-->
                <p>
                    
                    <label for="<?php $this->field_name('track_artist_selector');?>"><?php _e('Track Artist Selector','xspf-plgen');?> *</label>
                    <span class="xspf-plgen-info"><?php _e('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> (relative to the Tracks Selector) to get the artist name for the track, for example: <code>h4 .artist strong</code>','xspf-plgen');?></span>
                    <input name="<?php $this->field_name('track_artist_selector');?>" type="text" value="<?php echo $this->post_playlist->track_artist_selector;?>"/>
                    <input name="<?php $this->field_name('track_artist_regex');?>" placeholder="<?php _e('Optional regex','xspf-plgen');?>" type="text" value="<?php echo $this->post_playlist->track_artist_regex;?>"/>
                    
                    
                </p>
                <!-- track title-->
                <p>
                    
                    <label for="<?php $this->field_name('track_title_selector');?>"><?php _e('Track Title Selector','xspf-plgen');?> *</label>
                    <span class="xspf-plgen-info"><?php _e('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> (relative to the Tracks Selector) to get the title for the track, for example: <code>span</code>','xspf-plgen');?></span>
                    <input name="<?php $this->field_name('track_title_selector');?>" type="text" value="<?php echo $this->post_playlist->track_title_selector;?>"/>
                    <input name="<?php $this->field_name('track_title_regex');?>" placeholder="<?php _e('Optional regex','xspf-plgen');?>" type="text" value="<?php echo $this->post_playlist->track_title_regex;?>"/>
                    
                </p>
                
                <!-- track album-->
                <p>
                    
                    <label for="<?php $this->field_name('track_album_selector');?>"><?php _e('Track Album Selector','xspf-plgen');?></label>
                    <span class="xspf-plgen-info"><?php _e('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> (relative to the Tracks Selector) to get the album for the track, for example: <code>span.album</code>','xspf-plgen');?></span>
                    <input name="<?php $this->field_name('track_album_selector');?>" type="text" value="<?php echo $this->post_playlist->track_album_selector;?>"/>
                    <input name="<?php $this->field_name('track_album_regex');?>" placeholder="<?php _e('Optional regex','xspf-plgen');?>" type="text" value="<?php echo $this->post_playlist->track_album_regex;?>"/>
                    
                </p>
                
                <!-- track image-->
                <p>
                    
                    <label for="<?php $this->field_name('track_album_art_selector');?>"><?php _e('Track Image Selector','xspf-plgen');?></label>
                    <span class="xspf-plgen-info"><?php _e('Enter a <a href="http://www.w3schools.com/cssref/css_selectors.asp" target="_blank">CSS selector</a> (relative to the Tracks Selector) to get the image for the track, for example: <code>.cover img</code>','xspf-plgen');?></span>
                    <input name="<?php $this->field_name('track_album_art_selector');?>" type="text" value="<?php echo $this->post_playlist->track_album_art_selector;?>"/>
                    
                    
                </p>
            </div>
        </p>
        <!-- MusicBrainz -->
        <p>
            <input name="<?php $this->field_name('musicbrainz');?>" type="checkbox"<?php checked($this->post_playlist->musicbrainz);?> value="1"/>
            <label style="display:inline" for="<?php $this->field_name('musicbrainz');?>"><?php _e('Compare tracks informations with <a href="http://musicbrainz.org/" target="_blank">MusicBrainz</a>','xspf-plgen');?></label>
            <span class="xspf-plgen-info">
                <?php _e('Sometimes, the metadatas (title,artist,...) of the tracks are not corrects.  Enabling this will make MusicBrainz try to correct wrong values.','xspf-plgen');?><br/>
                <?php _e('This makes the playlist render slower : each track takes about ~1 second to be checked with MusicBrainz.','xspf-plgen');?>
            </span>
        </p>
        
        <!-- XSPF link embed -->
        <p>
            <input name="<?php $this->field_name('xspf_link');?>" type="checkbox"<?php checked($this->post_playlist->xspf_link);?> value="1"/>
            <label style="display:inline" for="<?php $this->field_name('xspf_link');?>"><?php _e('XSPF link','xspf-plgen');?></label>
            <span class="xspf-plgen-info">
                <?php _e('Adds automatically an XPSF link to your post content.','xspf-plgen');?><br/>
                <?php printf(__('You might want to disable this and use function %s instead, in your templates.','xspf-plgen'),'<code>xspf_plgen_get_xspf_permalink()</code>');?><br/>
            </span>
        </p>

        <!-- Toma.hk embed -->
        <p>
            <input name="<?php $this->field_name('tomahk_embed');?>" type="checkbox"<?php checked($this->post_playlist->tomahk_embed);?> value="1"/>
            <label style="display:inline" for="<?php $this->field_name('tomahk_embed');?>"><?php _e('Embed playlist from <a href="http://toma.hk/tools/embeds.php" target="_blank">Toma.hk</a>','xspf-plgen');?></label>
            <span class="xspf-plgen-info">
                <?php _e('Displays the playlist generated from the toma.hk website, under your post.','xspf-plgen');?><br/>
                <?php _e('ATTENTION : Toma.hk playlists do not update automatically yet, but this will be improved.','xspf-plgen');?><br/>
            </span>
        </p>
        <?php
        
        
        
        wp_nonce_field(xspf_plgen()->basename,'xspf_plgen_form',false);
    }

    function metabox_save($post_id){
            //check save status
            $is_autosave = wp_is_post_autosave( $post_id );
            $is_revision = wp_is_post_revision( $post_id );
            $is_valid_nonce = false;
            if ( isset( $_POST[ 'xspf_plgen_form' ]) && wp_verify_nonce( $_POST['xspf_plgen_form'], xspf_plgen()->basename)) $is_valid_nonce=true;
            
            if ($is_autosave || $is_revision || !$is_valid_nonce) return;

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
            
            foreach ((array)$default_args as $meta_key=>$null){
                $save_args[$meta_key] = $args[$meta_key];
            }
            
            $save_args = apply_filters('xspf_plgen_save_args',$save_args,$post_id);

            foreach ($save_args as $meta_key=>$meta_value){
                update_post_meta($post_id, $meta_key,$meta_value);
            }

            do_action('xspf_plgen_save', $save_args, $post_id);

    }
    
    function field_name($slug){
        echo self::get_field_name($slug);
    }
    
    function get_field_name($slug){
        return 'xspf-plgen['.$slug.']';
    }
    function get_field_value($slug){
        if (!isset($_POST['xspf-plgen'][$slug])) return false;
        
        $value = $_POST['xspf-plgen'][$slug];

        return $value;
    }

}

?>
