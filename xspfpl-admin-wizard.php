<?php

/**
 * Class that loads the Playlist Wizard on the backend
 */

class XSPFPL_Admin_Wizard {
    
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
        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );
        add_action( 'add_meta_boxes',  array( $this, 'wizard_init' ) );
        add_action( 'save_post',  array( $this, 'wizard_save' ) );

    }
    
    public function scripts_styles($hook) {
        if( get_post_type()!=xspfpl()->post_type ) return false;
        XSPFPL_Playlist_Wizard::scripts_styles();
    }
    
    function wizard_init(){

        if( get_post_type()==xspfpl()->post_type ){
            $this->wizard = new XSPFPL_Playlist_Wizard( get_the_ID() );
        }
        
        if (!empty($this->wizard->playlist->tracks)){
            $count = count($this->wizard->playlist->tracks);
            add_meta_box( 'xspfpl-tracks', sprintf(__('Found Tracks : %d','xspfpl'),$count), array(&$this->wizard,'found_tracks_metabox'), xspfpl()->post_type,'normal','high');
        }
        
        
        add_meta_box( 'xspfpl-wizard-metabox', __('Playlist Parser Wizard','xspfpl'), array(&$this->wizard,'wizard_metabox'), xspfpl()->post_type,'normal','high');
        

        
    }
    function wizard_save($post_id){
        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = false;
        if ( isset( $_POST[ 'xspfpl_form' ]) && wp_verify_nonce( $_POST['xspfpl_form'], xspfpl()->basename)) $is_valid_nonce=true;

        if ($is_autosave || $is_revision || !$is_valid_nonce) return;
        if( get_post_type($post_id)!=xspfpl()->post_type ) return;
        
        XSPFPL_Playlist_Wizard::wizard_save($post_id);
    }

}

new XSPFPL_Admin_Wizard();

?>