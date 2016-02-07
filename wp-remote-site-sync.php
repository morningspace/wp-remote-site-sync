<?php
 
/**
 Plugin Name: WP Remote Site Sync
 Plugin URI: https://github.com/morningspace/wp-remote-site-sync
 Description: 
 Version: 1.0.0
 Author: MorningSpace
 Author URI: http://morningspace.51.net/weblog
 Copyright 2016  WP Remote Site Sync  (email : morningspace@yahoo.com)
*/

wprss_init();

/**
 * Add a link on main menu
 */
function wprss_add_options_page(){
    add_options_page(
        'WP Remote Site Sync', 
        'WP Remote Site Sync', 
        'administrator', 
        __FILE__, 
        'wprss_settings_page');
}
 
function wprss_add_meta_box() {
    $screens = array( 'post', 'page' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wprss_metabox',
            __( 'WP Remote Site Sync', 'wprss_metabox' ),
            'wprss_metabox_callback',
            $screen,
            'wprss_mb', // ??
            'high'
        );
    }
}

function wprss_metabox_callback( $post ) {
    wp_nonce_field( 'wprss_save_meta_box_data', 'wprss_meta_box_nonce' );

    $options =  get_option('wprss_options') ;
    if ( is_array($options) && count($options) > 0 ) {
        global $post;
    ?>
    <a href="options-general.php?page=wp-remote-site-sync%2Fwp-remote-site-sync.php" target="_blank">Settings</a>
    <table class="form-table">
        <tr>
            <td> </td>
            <td><b>Host Name</b></td>
        </tr>
    <?php 
        include_once(get_home_path().'wp-includes/class-IXR.php');
        foreach ( $options as $key => $val ) { 
            if ( $options[$key]["wprss_host"] != "" ) {
                $post_synced = "";
                $post_remote_id = "";
                $h = $options[$key]["wprss_host"];
                $u = $options[$key]["wprss_username"];
                $p = $options[$key]["wprss_pwd"];
                $meta_key = 'post_at_'.$h;
                $client = new IXR_CLIENT($h.'/xmlrpc.php');
                if ( $post->ID ) {
                    $post_remote_id = get_post_meta($post->ID, $meta_key, true);

                    if ( $post_remote_id != "" && $client->query('wp.getPost', '1', $u, $p, $post_remote_id) ) {
                        if (count($client->getResponse()) > 0) {
                            $post_synced = "checked";
                        }
                    }
                }
    ?>
        <tr valign="top" id="row<?php echo $key; ?>">
            <td><input type="checkbox" value="<?php echo $options[$key]["wprss_host"] ?>" name="wprss_check_<?php echo $key; ?>" <?php echo $post_synced; ?> /></td>
            <td><?php echo $options[$key]["wprss_host"] ?></td>
        </tr>
    <?php
            }
        }
    ?>
    </table>
    <?php
    }
}

function wprss_add_edit_form_multipart_encoding() {
    echo ' enctype="multipart/form-data"';
}

add_action('post_edit_form_tag', 'wprss_add_edit_form_multipart_encoding');

function wprss_save_meta_box_data( $post_id ) {

    // Check if our nonce is set.
    if ( ! isset( $_POST['wprss_meta_box_nonce'] ) ) {
        return;
    }

    // Verify that the nonce is valid.
    if ( ! wp_verify_nonce( $_POST['wprss_meta_box_nonce'], 'wprss_save_meta_box_data' ) ) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
    } else {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    // OK, it's safe for us to save the data now.
    $options = get_option('wprss_options');
    if ( is_array($options) && count($options) > 0 ) {
        if ( is_object($post_id) ) {
            $post_id = $post_id->ID;
        }

        $post = get_post($post_id);

        $post_categories = wp_get_post_categories( $post_id );
        $post_tags = wp_get_post_tags( $post_id );
        $listcat = array();
        $listtag = array();
        if ( count($post_categories) > 0 ) {
            foreach ( $post_categories as $c ) {
                $cat = get_category( $c );
                $listcat[] = $cat->name;     
            }
        }
        if ( count($post_tags) > 0 ) {
            foreach ( $post_tags as $t ) {
                $listtag[] = $t->name;
            }
        }

        $path_to_www_folder = get_home_path();
        include_once($path_to_www_folder.'wp-includes/class-IXR.php');
        $post_date_gmt = new IXR_Date(strtotime( $post->post_date_gmt ) );
        foreach ( $options as $key => $val ) {
            if ( $options[$key]["wprss_host"] != "" ) {
                if ( $_POST["wprss_check_".$key] != "" ) {
                    $h = $options[$key]["wprss_host"];
                    $u = $options[$key]["wprss_username"];
                    $p = $options[$key]["wprss_pwd"];

                    $update_post = 0;
                    $meta_key = 'post_at_'.$h;
                    $remote_post_id = get_post_meta($post_id, $meta_key, true);
                    $client = new IXR_CLIENT($h.'/xmlrpc.php');
                    if ( $remote_post_id != "" ) {
                        if ( $client->query('wp.getPost','1', $u, $p, $remote_post_id) ) {
                            $response = $client->getResponse();
                            if ( count($response) > 0 ) {
                                $update_post = 1;
                            } else {
                                delete_post_meta($post_id, $meta_key, $remote_post_id);
                            }
                        }
                        else {
                            delete_post_meta($post_id, $meta_key, $remote_post_id);
                        }
                    }            

                    if ( $update_post == 0 ) {
                        $content = array();
                        $content['post_status'] = $post->post_status;
                        $content['post_title'] = $post->post_title;
                        $content['post_excerpt'] = substr($post->post_content_filtered, 0, 250);
                        $content['post_date_gmt'] = $post_date_gmt;
                        $content['post_content'] = $post->post_content_filtered;
                        $content['terms'] = array('category' =>  $listcat);
                        $content['terms_names'] = array( 'post_tag' =>  $listtag );
                        if ( !$client->query('wp.newPost', '', $u, $p, $content) ) { 
                            error_log("Error creation new post: ".$client->getErrorMessage());
                        } else {
                            $remote_post_id =  $client->getResponse();
                            add_post_meta($post_id, $meta_key, $remote_post_id, true);
                        }
                    } else {
                        $content = array();
                        $content['post_status'] = $post->post_status;
                        $content['post_title'] = $post->post_title;
                        $content['post_excerpt'] = substr($post->post_content_filtered, 0, 250);
                        $content['post_date_gmt'] = $post_date_gmt;
                        $content['post_content'] = $post->post_content_filtered;
                        $content['terms'] = array( 'category' => $listcat);
                        $content['terms_names'] = array( 'post_tag' => $listtag );
                        if ( !$client->query('wp.editPost', "1", $u, $p, $remote_post_id, $content, true) ) {
                            error_log("Error edit post: ".$client->getErrorMessage()); 
                        }
                    }
                } 
            }
        }
    }
    return;
}
    
function wprss_add_meta_box_move() {
    # Get the globals:
    global $post, $wp_meta_boxes;

    # Output the "advanced" meta boxes:
    do_meta_boxes( get_current_screen(), 'wprss_mb', $post );

    # Remove the initial "advanced" meta boxes:
    unset($wp_meta_boxes['post']['wprss_mb']);
}    
    
/**
 * Init plugin
 */
function wprss_init(){
    add_action('admin_enqueue_scripts', 'wprss_admin_enqueue' );
    add_action('admin_menu', 'wprss_add_options_page');
    add_action('admin_init', 'wprss_register_mysettings' ); 

    add_action('add_meta_boxes', 'wprss_add_meta_box' );
    add_action('edit_form_after_title', 'wprss_add_meta_box_move');
    add_action('publish_post',  'wprss_save_meta_box_data');
    add_action('edit_page_form',   'wprss_save_meta_box_data');      
}    
 
function wprss_admin_enqueue() {
    wp_enqueue_style ('default_admin_style_wprss_1', plugins_url('css/wprss_admin_style.css', __FILE__), false, time());
    wp_enqueue_script('default_admin_scripts_wprss_2', plugins_url('js/wprss_mimic.js', __FILE__), array(), time(), false );
    wp_enqueue_script('default_admin_scripts_wprss_3', plugins_url('js/wprss_wordpress.js', __FILE__), array(), time(), true );
    wp_enqueue_script('default_admin_scripts_wprss_4', plugins_url('js/wprss_connectwp.js', __FILE__), array(), time(), true );
}

/**
 * Register settings
 */
function wprss_register_mysettings() {
    register_setting( 'wprss-settings-group', 'wprss_options' );
}

/**
 * View settings page 
 */
function wprss_settings_page() {
?>
<div class="wrap">
    <h2>WP Remote Site Sync</h2>
    <form id="wprssform" name="wprssform" method="post" action="options.php">
    <?php 
        $total = 0;
        settings_fields( 'wprss-settings-group' );  
        do_settings_sections( 'wprss-settings-group' ); 
        $options = get_option('wprss_options') ;
        if (is_array($options)) {
            $total = count($options);
        }
    ?>
        <input type="hidden" name='wprss_plugin_path' id="wprss_plugin_path" value="<?php echo plugins_url( 'img/loading.gif', __FILE__ ); ?>" />
        <table class="form-table">
            <tr valign="top" id="row<?php echo $total; ?>">
            <td>Add new host</td>
                <td>Host:<input class="required" type="text" name='wprss_options[<?php echo $total; ?>][wprss_host]' id='wprss_host_<?php echo $total; ?>' value="" size=60 /></td>
                <td>Username:<input class="required" type="text" name='wprss_options[<?php echo $total; ?>][wprss_username]' id='wprss_username_<?php echo $total; ?>' value=""  /></td>
                <td>Password:<input class="required" type="password" name='wprss_options[<?php echo $total; ?>][wprss_pwd]' id='wprss_pwd_<?php echo $total; ?>' value=""  /></td>
                <td><input type="button" value="Test" name="wprss_test_<?php echo $total; ?>" onclick='testSite(<?php echo $total; ?>)' /></td>
            </tr>
            <tr valign="top">
                <td> </td>
                <td><b>Host Name</b></td>
                <td><b>Username</b></td>
                <td><b>Password</b> </td>
                <td> </td>
            </tr>
    <?php 
        foreach ( $options as $key => $val) { 
            if ( $options[$key]["wprss_host"] != "" ) {
    ?>
            <tr valign="top" id="row<?php echo $key; ?>">
                <td><input type="button" value="Delete" name="wprss_delete_<?php echo $key; ?>" onclick=" delEvent=true;(function($) { $('#row<?php echo $key; ?>').remove();  })(jQuery);" /></td>
                <td>Host:<input type="text" size=60 name='wprss_options[<?php echo $key; ?>][wprss_host]' id="wprss_host_<?php echo $key; ?>" value="<?php echo $options[$key]["wprss_host"]; ?>" /></td>
                <td>Username:<input type="text" name='wprss_options[<?php echo $key; ?>][wprss_username]' id="wprss_username_<?php echo $key; ?>" value="<?php echo $options[$key]["wprss_username"]; ?>" /></td>
                <td>Password:<input type="password" name='wprss_options[<?php echo $key; ?>][wprss_pwd]' id="wprss_pwd_<?php echo $key; ?>" value="<?php echo $options[$key]["wprss_pwd"]; ?>" /></td>
                <td><input type="button" value="Test" name="wprss_test_<?php echo $key; ?>" onclick='testSite(<?php echo $key; ?>)' /></td>
            </tr>
    <?php
            }
        }
    ?>
        </table>
        <input type="hidden" name="cf" id="cf" value="<?php echo ($total); ?>">
        <input type="button" name="btnsubmit" id="btnsubmit" class="button button-primary" value="Save Changes"  onclick="wprssValidateForm(<?php echo ($total); ?>);"  />
    </form>
</div>
<?php
}
?>