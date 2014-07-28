<?php

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
        require( xspf_plgen()->plugin_dir . 'xspf-plgen-wizard.php' );
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