<?php

/**
 * Admin Options Page
 */

class XSPFPL_Admin_Options{

    /**
     * Start up
     */
    public function __construct(){
        
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        //add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
    }

    function enqueue_scripts_styles($hook){
        if ($hook!='settings_page_ari-admin') return;
        wp_enqueue_script('xspfpl-options', ari()->plugin_url.'_inc/js/settings.js', array('jquery'),xspfpl()->version);
        
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_submenu_page(
                'edit.php?post_type='.xspfpl()->post_type,
                __('Options'),
                __('Options'),
                'manage_options',
                'xspfpl-options',
                array( $this, 'options_page' )
        );
    }

    /**
     * Options page callback
     */
    public function options_page(){
        // Set class property
        
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e('XSPF Playlist Generator','xspfpl');?></h2>  
            
            
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'xspfpl_option_group' );   
                do_settings_sections( 'xspfpl-settings-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'xspfpl_option_group', // Option group
            XSPFPL_Core::$meta_key_options, // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'settings_general', // ID
            __('General Options','xspfpl'), // Title
            array( $this, 'section_general_desc' ), // Callback
            'xspfpl-settings-admin' // Page
        );  
        
        add_settings_section(
            'settings_playlist', // ID
            __('Playlist Options','xspfpl'), // Title
            array( $this, 'section_playlist_desc' ), // Callback
            'xspfpl-settings-admin' // Page
        );
        
        add_settings_field(
            'playlist_link', 
            __('Embed playlist link','xspfpl'), 
            array( $this, 'playlist_link_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_field(
            'thk_friendly', 
            __('Tomahawk Friendly','xspfpl'), 
            array( $this, 'tomahawk_friendly_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_field(
            'widget_embed', 
            __('Hatchet Embed','xspfpl'), 
            array( $this, 'widget_embed_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_section(
            'settings_system', // ID
            __('System Options','xspfpl'), // Title
            array( $this, 'section_system_desc' ), // Callback
            'xspfpl-settings-admin' // Page
        );

        add_settings_field(
            'reset_options', 
            __('Reset Options','xspfpl'), 
            array( $this, 'reset_options_callback' ), 
            'xspfpl-settings-admin', 
            'settings_system'
        );
        
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ){

        $new_input = array();

        if( isset( $input['reset_options'] ) ){
            
            $new_input = xspfpl()->options_default;
            
        }else{ //sanitize values

            
            if( isset( $input['playlist_link'] ) ){
                $new_input['playlist_link'] = $input['playlist_link'];
            }
            
            if( isset( $input['thk_friendly'] ) ){
                $new_input['thk_friendly'] = $input['thk_friendly'];
            }
            
            if( isset( $input['widget_embed'] ) ){
                $new_input['widget_embed'] = $input['widget_embed'];
            }

        }

        //remove default values
        foreach($input as $slug => $value){
            $default = xspfpl()->get_default_option($slug);
            if ($value == $default) unset ($input[$slug]);
        }

        $new_input = array_filter($new_input);

        return $new_input;
       
    }

    /** 
     * Print the Section text
     */
    public function section_general_desc(){
    }
    
    public function section_playlist_desc(){
    }
    
    public function playlist_link_callback(){
        $option = xspfpl()->get_option('playlist_link');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Adds automatically a link to the playlist in the post content.','xspfpl');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','xspfpl'),'<code>xspfpl_playlist_link()</code>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[playlist_link]" value="on" %2$s/> %3$s<br/>%4$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    public function tomahawk_friendly_callback(){
        $option = xspfpl()->get_option('thk_friendly');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Format playlist links to be Tomahawk-friendly.','xspfpl');
        $help = '<small>'.sprintf(__('Use the %1$s protocol, so %2$s automatically loads the playlist when the link is clicked.  If disabled, clicking the playlist link will display the XSPF file content.','xspfpl'),'<code>tomahawk://</code>','<a href="http://www.tomahawk-player.org/" target="_blank">Tomahawk</a>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[thk_friendly]" value="on" %2$s/> %3$s<br/>%4$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    public function widget_embed_callback(){
        $option = xspfpl()->get_option('widget_embed');

        $checked = checked( (bool)$option, true, false );
        $disabled = disabled(class_exists('Hatchet'), false, false); 
 
        $desc = __('Embeds the hatchet playlist widget after post content.','xspfpl');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','xspfpl'),'<code>xspfpl_get_widget_playlist()</code>').'</small>';
                
        if ($disabled){
            $help = '<small><strong>'.sprintf(__('The plugin %1$s is needed to enable this feature.','xspfpl'),'<a href="https://wordpress.org/plugins/wp-hatchet/" target="_blank">Hatchet</a>').'</strong></small>';
        }
        
        printf(
            '<input type="checkbox" name="%1$s[widget_embed]" value="on" %2$s %3$s/> %4$s<br/>%5$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $disabled,
            $desc,
            $help
        );
    }
    

    public function section_system_desc(){
    }

    
    public function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%1$s[reset_options]" value="on"/> %2$s',
            XSPFPL_Core::$meta_key_options,
            __("Reset options to their default values.","ari")
        );
    }
    
}

new XSPFPL_Admin_Options();