<?php

/**
 * Admin Options Page
 */

class XSPFPL_Admin_Options{

    /**
     * Start up
     */
    function __construct(){
        
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
    function add_plugin_page()
    {
        // This page will be under "Settings"
        add_submenu_page(
                'edit.php?post_type='.xspfpl()->station_post_type,
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
    function options_page(){
        // Set class property
        
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e('XSPF Playlist Generator','xspfpl');?></h2>  
            <?php settings_errors('xspfpl_option_group'); ?>
            
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
    function page_init(){        
        
        if ( !is_dir(xspfpl()->cache_dir) ){
            add_settings_error( 'xspfpl_option_group', 'cache_disabled', sprintf(__("The directory %s does not exists.  Cache cannot be enabled.",'xspfpl'), '<em>'.xspfpl()->cache_dir.'</em>') );
        }

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
            'cache_tracks_intval', 
            __('Playlist cache duration','xspfpl'), 
            array( $this, 'playlist_cache_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );

        add_settings_field(
            'playlist_link', 
            __('Embed links','xspfpl'), 
            array( $this, 'playlist_link_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_field(
            'tracklist_embed', 
            __('Embed Tracklist','xspfpl'), 
            array( $this, 'tracklist_embed_callback' ), 
            'xspfpl-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_field(
            'enable_hatchet', 
            __('Enable Hatchet','xspfpl'), 
            array( $this, 'enable_hatchet_callback' ), 
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
    function sanitize( $input ){

        $new_input = array();

        if( isset( $input['reset_options'] ) ){
            
            $new_input = xspfpl()->options_default;
            
        }else{ //sanitize values
            
            if ( isset ($input['cache_tracks_intval']) && ctype_digit ($input['cache_tracks_intval']) ){
                $new_input['cache_tracks_intval'] = $input['cache_tracks_intval'];
            }

            if( isset( $input['playlist_link'] ) ){
                $new_input['playlist_link'] = $input['playlist_link'];
            }

            if( isset( $input['tracklist_embed'] ) ){
                $new_input['tracklist_embed'] = $input['tracklist_embed'];
            }
            
            if( isset( $input['enable_hatchet'] ) ){
                $new_input['enable_hatchet'] = $input['enable_hatchet'];
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
    function section_general_desc(){
    }
    
    function section_playlist_desc(){
    }
    
    function playlist_cache_callback(){
        $option = (int)xspfpl()->get_options('cache_tracks_intval');
        $disabled = (!is_dir(xspfpl()->cache_dir)); 

        $help = '<small>'.__('Number of seconds a playlist is cached before requesting the remote page again. 0 = Disabled.','xspfpl').'</small>';
        
        printf(
            '<input type="number" name="%1$s[cache_tracks_intval]" size="4" min="0" value="%2$s" %3$s/><br/>%4$s',
            XSPFPL_Core::$meta_key_options,
            $option,
            disabled( $disabled , true, false),
            $help
        );
        
    }
    
    function playlist_link_callback(){
        $option = xspfpl()->get_options('playlist_link');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Automatically embed the playlist links.','xspfpl');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','xspfpl'),'<code>xspfpl_html_pre()</code>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[playlist_link]" value="on" %2$s/> %3$s<br/>%4$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    function tracklist_embed_callback(){
        $option = xspfpl()->get_options('tracklist_embed');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Automatically embed the tracklist.','xspfpl');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','xspfpl'),'<code>xspfpl_tracklist_table()</code>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[tracklist_embed]" value="on" %2$s/> %3$s<br/>%4$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    function enable_hatchet_callback(){
        $option = xspfpl()->get_options('enable_hatchet');
        $help = null;

        $checked = checked( (bool)$option, true, false );
        $disabled = disabled(class_exists('Hatchet'), false, false); 
 
        $desc = __('Embeds the hatchet widgets in the tracklist.','xspfpl');
      
        if ($disabled){
            $help = '<small><strong>'.sprintf(__('The plugin %1$s is needed to enable this feature.','xspfpl'),'<a href="https://wordpress.org/plugins/wp-hatchet/" target="_blank">Hatchet</a>').'</strong></small>';
        }
        
        printf(
            '<input type="checkbox" name="%1$s[enable_hatchet]" value="on" %2$s %3$s/> %4$s<br/>%5$s',
            XSPFPL_Core::$meta_key_options,
            $checked,
            $disabled,
            $desc,
            $help
        );
    }
    

    function section_system_desc(){
    }

    
    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%1$s[reset_options]" value="on"/> %2$s',
            XSPFPL_Core::$meta_key_options,
            __("Reset options to their default values.","ari")
        );
    }
    
}

new XSPFPL_Admin_Options();