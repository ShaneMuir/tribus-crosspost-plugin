<?php

/*
 *  Plugin Name: Tribus Crosspost Plugin
 *  Description: Duplicates posts to site's on the network.
 *  Version: 1.0.0
 *  Author: Shane Muirhead
 *  Author URI: https://tribusdigital.com/
 */

class MultisiteCrosspostPlugin {

    function __construct() {
        add_action('admin_menu', array($this, 'adminPage'));
        add_action('admin_init', array($this, 'settings'));
        add_action('save_post', array($this, 'tribusPostToAllSites'), 20, 2);
    }

    function settings() {
        add_settings_section('crosspost-section', 'Multisite Network Sites', null, 'tribus-crosspost-settings-page');
        $sites = get_sites();
        foreach(array_slice($sites, 1) as $site):
            $blog_titles = strtolower(str_replace(' ', '', get_blog_details($site->blog_id)->blogname));
            add_settings_field($blog_titles, $blog_titles, array($this, 'generateCheckboxes'), 'tribus-crosspost-settings-page', 'crosspost-section', array('theName' => $blog_titles, 'siteID' => $site->blog_id));
            register_setting('tribus-crosspost', $blog_titles, array('sanitize_callback' => 'sanitize_text_field', 'default' => '0'));
        endforeach;
    }

    function generateCheckboxes($args) { ?>
        <input type="checkbox" name="<?php echo $args['theName'] ?>" value="<?php echo $args['siteID']; ?>" <?php checked(get_option($args['theName']), $args['siteID']) ?>>
    <?php }

    function adminPage() {
        add_options_page('Tribus Crosspost Settings', 'Tribus Cross Post',
            'manage_options', 'tribus-crosspost-settings-page', array($this, 'ourHTML'));
    }

    function ourHTML() { ?>
        <div class="wrap">
            <h1>Tribus Crossposting Plugin</h1>
            <form action="options.php" method="POST">
                <?php
                settings_fields('tribus-crosspost');
                do_settings_sections('tribus-crosspost-settings-page');
                submit_button();
                ?>
            </form>
        </div>
    <?php }

    function tribusPostToAllSites($original_post_id, $original_post) {
        // Don't publish revisions
        if(defined('DOING_AUTOSAVE') AND DOING_AUTOSAVE):
            return $original_post_id;
        endif;

        // Actually we only need published posts
        if('publish' !== get_post_status($original_post)):
            return $original_post_id;
        endif;

        // prevent "Fatal error: Maximum function nesting level reached"
        remove_action('save_post', __FUNCTION__);

        $blog_ids = array();

        $sites = get_sites();
        foreach(array_slice($sites, 1) as $site):
            $blog_titles = strtolower(str_replace(' ', '', get_blog_details($site->blog_id)->blogname));
            $blog_ids[] = get_option($blog_titles);
        endforeach;

        // let's get this post data as an array
        $post_data = array(
            'post_author' => $original_post->post_author,
            'post_date' => $original_post->post_date,
            'post_modified' => $original_post->post_modified,
            'post_content' => $original_post->post_content,
            'post_title' => $original_post->post_title,
            'post_excerpt' => $original_post->post_excerpt,
            'post_status' => 'publish',
            'post_name' => $original_post->post_name,
            'post_type' => $original_post->post_type,
        );

        // terms and post meta as well
        $post_terms = wp_get_object_terms($original_post_id, 'category', array('fields' => 'slugs'));
        $post_meta = get_post_custom($original_post_id);

        foreach($blog_ids as $blog_id):
            switch_to_blog($blog_id);

            if(get_posts(array('name' => $post_data['post_name'], 'post_type' => $post_data['post_type'], 'post_status' => 'publish'))):
                restore_current_blog();
                continue;
            endif;

            $inserted_post_id = wp_insert_post($post_data);

            wp_set_object_terms($inserted_post_id, $post_terms, 'category', false);

            foreach($post_meta as $meta_key => $meta_values):
                // We don't need these redirects
                if('_wp_old_slug' === $meta_key):
                    continue;
                endif;

                foreach($meta_values as $meta_value):
                    add_post_meta($inserted_post_id, $meta_key, $meta_value);
                endforeach;
            endforeach;

            restore_current_blog();
        endforeach;
    }

}

$multisiteCrosspostPlugin = new MultisiteCrosspostPlugin();
