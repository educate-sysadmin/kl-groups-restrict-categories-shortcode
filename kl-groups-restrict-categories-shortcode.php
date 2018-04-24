<?php
/*
Plugin Name: KL Groups Restrict Categories Shortcode
Plugin URI: https://github.com/educate-sysadmin/kl-groups-restrict-categories-shortcode
Description: Shortcode access controls for Groups Restrict Categories accesses
Version: 0.2
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

/* helper: get current user's group ids */
function klgrc_get_user_groups(/* for current user */) {
    // ref http://docs.itthinx.com/document/groups/api/examples/
    $groups_user = new Groups_User( get_current_user_id() );
    // get group objects
    $user_groups = $groups_user->groups;
    // get group ids (user is direct member)
    $user_group_ids = $groups_user->group_ids;
    // get group ids (user is direct member or by group inheritance)
    $user_group_ids_deep = $groups_user->group_ids_deep;
    return $user_group_ids_deep;
}

/* helper: return group ids restrictions for category */
function klgrc_get_groups_restrict_categories($category) {
    global $wpdb;

    return $wpdb->get_results( 
	    '
            SELECT meta_value 
            FROM '.$wpdb->prefix.'termmeta  
            LEFT JOIN '.$wpdb->prefix.'terms ON '.$wpdb->prefix.'termmeta.term_id = '.$wpdb->prefix.'terms.term_id 
            WHERE '.$wpdb->prefix.'termmeta.meta_key = "groups-read" 
            AND '.$wpdb->prefix.'terms.slug = "'.$category.'";'
    );
}

// thanks groups/lib/access/class-groups-access-shortcodes.php
function klgrc_shortcode( $atts, $content = null ) {
	$output = '';
    // parse options
	$options = shortcode_atts( array( 'categories' => '' ), $atts );
	$show_content = false;
    // failsafes
	if ( $content !== null) {
        if (!isset($options['categories']) || $options['categories'] === null || $options['categories'] === "") {
            $show_content = true;
        } else {
            // get categories requested
            $categories_request = explode(",",$options['categories']);
            // check against wp categories for validity, fallback to not show if problem
            $wp_categories = get_categories();
            $valid = false;            
            foreach ($categories_request as $category) {
                foreach ($wp_categories as $wp_category) {
                    if ($wp_category->category_nicename == $category) {
                        $valid = true; 
                        break; break;
                    }
                }
            }
            if ($valid) {
                // get current user's groups
                $user_groups = klgrc_get_user_groups();
                // check each category request against groups allowed for category and user's groups
                foreach ($categories_request as $category) {                    
                    $groups_allowed = klgrc_get_groups_restrict_categories($category);
                    foreach ($user_groups as $user_group) {
                        foreach ($groups_allowed as $group_allowed) {
                            if ((int)$group_allowed->meta_value == $user_group) {
                                $show_content = true;
                                break; break;
                            }
                        }    
                    }
                }
            }
        }
		if ( $show_content ) {
			remove_shortcode( 'kl_groups_restrict_categories' );
			$content = do_shortcode( $content );
			add_shortcode( 'kl_groups_restrict_categories', 'klgrc_shortcode' );
			$output = $content;
		}
	}
	return $output;
}

add_shortcode( 'kl_groups_restrict_categories', 'klgrc_shortcode' );
