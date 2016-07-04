<?php
/**
 * @package Periodic Publishing
 * @version 1.0
 */
/*
Plugin Name: Periodic Publishing
Plugin URI: http://wpmu.org
Description: Adds functionality for publishing issue-based / periodical content
Author: Chris Knowles
Version: 1.0
Author URI: http://wpmu.org
*/

/* 
 * Register the Issues Custom Taxonomy
 *
 * query_var => true provides automatic filtering of posts if issue in querystring!
 *
 */
function mag_custom_taxonomy_issue()  {

	$labels = array(
		'name'                       => _x( 'Issues', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Issue', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Issues', 'text_domain' ),
		'all_items'                  => __( 'All Issues', 'text_domain' ),
		'parent_item'                => __( 'Parent Issue', 'text_domain' ),
		'parent_item_colon'          => __( 'Parent Issue:', 'text_domain' ),
		'new_item_name'              => __( 'New Issue Name', 'text_domain' ),
		'add_new_item'               => __( 'Add New Issue', 'text_domain' ),
		'edit_item'                  => __( 'Edit Issue', 'text_domain' ),
		'update_item'                => __( 'Update Issue', 'text_domain' ),
		'separate_items_with_commas' => __( 'Separate issues with commas', 'text_domain' ),
		'search_items'               => __( 'Search issues', 'text_domain' ),
		'add_or_remove_items'        => __( 'Add or remove issues', 'text_domain' ),
		'choose_from_most_used'      => __( 'Choose from the most used issues', 'text_domain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'query_var'					 => true,
		'rewrite'					 => false,
	);
	register_taxonomy( 'issue', 'post', $args );
}

// Register the Issue taxonomy via the init action
add_action( 'init', 'mag_custom_taxonomy_issue' );

/*****************************
 *
 * Front-end manipulations
 *
 *****************************/
 
/*
 * Only show the post from the master category for current issue on the home page
 */

function mag_front_page_filter( $query ) {

	if ( $query->is_home() && $query->is_main_query() ) {
		
		// uncomment below if you want to only show post
		//$query->set( 'category_name' , get_option( 'mag_master_category') );
	
		if ( !get_query_var( 'issue' )){
	
			$tax_query = array(
							array(
								'taxonomy' => 'issue' ,
								'field' => 'slug',
								'terms' => mag_get_current_issue(),
							)
						);
					
			$query->set( 'tax_query' , $tax_query);
		}
	}
}  

// register the filter
add_action( 'pre_get_posts', 'mag_front_page_filter' );


/*
 * Post navigation - prevent linking to posts that are not in the issue
 */
function mag_check_post_navigation ( $output, $format, $link, $post ) {

	// if this post is not the current issue then return blank
	if ( !has_term( mag_get_current_issue() , 'issue' , $post) ) return '';
	
	return $output;
	
}

// add the check to the next and previous post links - make sure 4 parameters are passed so we get $post
add_filter( 'next_post_link' , 'mag_check_post_navigation', 10, 4);
add_filter( 'previous_post_link' , 'mag_check_post_navigation', 10, 4);

/*
 * Add issue= to querystring to maintain issue browsing
 */
function mag_add_issue_to_link ( $url ) {

	return add_query_arg( 'issue' , mag_get_current_issue() , $url ); 

}

// ensure issue is added to page links, category links and search links
add_filter( 'page_link', 'mag_add_issue_to_link', 10, 1);
add_filter( 'category_link', 'mag_add_issue_to_link', 10, 1);
add_filter( 'search_link', 'mag_add_issue_to_link', 10, 1);

/***************
 *
 * Shortcodes 
 *
 ***************/
 
/* 
 * [backissues] - lists the posts from the mag_master_category
 */
function mag_backissues_func( $atts ) {
	
	// build a list of posts from source

       	$args = array (	
       	        'orderby'		=> 'date',
                'order'			=> 'DESC',
                'post_per_page'		=> -1,
                'category_name'		=> get_option( 'mag_master_category', 'editorials'),
                'suppress_filters'	=> true,              
       	); 
	
	$cat_posts = get_posts( $args );
	
	$output = '<ul>';
				
	foreach ( $cat_posts as $post ) {
					
		setup_postdata( $post );
		
		$term_list = wp_get_post_terms($post->ID, 'issue', array("fields" => "slugs"));
		
		if ( count($term_list) ) {
		
			$term = $term_list[0];
		
			$thumb_attr = array(
				'alt'	=> trim(strip_tags( get_the_excerpt() )),
				'title'	=> trim(strip_tags( get_the_title($post->ID) )),
				'class' => 'alignleft',
			);

       			$output .= '<li style="clear:both">';
       			$output .= '<h2><a href="'. get_permalink($post->ID) . '" rel="bookmark">' . get_the_title($post->ID) . '</a></h2>';
       			$output .= get_the_post_thumbnail($post->ID , 'thumbnail', $thumb_attr); 
       			$output .= '<div class="entry-summary">' . get_the_excerpt() . '</div>';
       			$output .= '</li>';
       			
       		}
 			       
    	}
    				
    	wp_reset_postdata();

        $output .= '</ul>';    	

	return $output;
}

// register the shortcode
add_shortcode( 'backissues', 'mag_backissues_func' );

/* 
 * [contents format="long|short"] - lists posts by category for the issue
 */
function mag_contents_func( $atts ) {

	extract( shortcode_atts( array(
		'format' => 'short',
	), $atts ) );
	
	
	$output = '<ul class="contents">';        
                	
    $args = array(
  			'orderby' => 'name',
  			'hide_empty' => 1
  	);
                	
	$categories = get_categories( $args ); 

    foreach ( $categories as $category ) { 
     	
     	// ignore the master category
    	if ( $category->slug == get_option( 'mag_master_category') ) continue;
    	
    	$catname = $category->name;
      
		$args = array(	
                'orderby'		=> 'title',
                'order'			=> 'ASC',
                'post_per_page'	=> -1,
                'cat'			=> $category->cat_ID,
                'tax_query' 	=> array(
										array(
											'taxonomy' => 'issue' ,
											'field' => 'slug',
											'terms' => mag_get_current_issue(),
										)),
		);
		
		// get the posts for the category			
		$cat_posts = new WP_Query( $args );
		
		while( $cat_posts->have_posts() ) {
			
			if ( $catname ) {
			
				$output .= '<li><h3>' . $catname . '</h3></li>';
				$output .= '<ul>';
				
				$catname = '';
			}
			
			$cat_posts->the_post();
			
			$output .= '<li>';
       			$output .= '<a href="' . get_permalink() . '" rel="bookmark">' . get_the_title() . '</a>';
       			
       			if ($format == 'long') {
       				$output .= '<div class="entry-summary">' . get_the_excerpt() . '</div>';
       			}
       			
       			$output .= '</li>';
 		
    	}
    		
    	if ( !$catname ) {
    			
    		$output .= '</ul>';
    	}
    }
    				
    wp_reset_postdata();   
        		
	$output .= '</ul><!-- contents -->';
	
	return $output;

}

// register the shortcode
add_shortcode( 'contents', 'mag_contents_func' ); 

/*
 * [display_issue] - Outputs the issue name and description
 */
function mag_display_issue_func() {

	$term = get_term_by( 'slug', mag_get_current_issue() , 'issue' );

	return '<div id="current-issue-title"><h2>Issue ' . $term->name . '</h2><p>' . $term->description . '</p></div>';

}

// register the shortcode
add_shortcode( 'display_issue', 'mag_display_issue_func' ); 

// make the shortcodes work in widgets
add_filter('widget_text', 'do_shortcode');


/******************
 * 
 * Admin actions
 *
 ******************/
 
/*
 * When a post is published check if mag_current_issue needs to be updated
 */

function mag_check_for_new_issue_on_publish( $post_id ){

	// check that this is a real publish and not an update
	if( ( $_POST['post_status'] == 'publish' ) && ( $_POST['original_post_status'] != 'publish' ) ) {
        
        // is the post in the master category? 
        if ( in_category( get_option( 'mag_master_category' ) , $post_id ) ) {
        
        	// get the issue and assign this to global option current_issue
        	$terms = get_the_terms ( $post_id , 'issue' );
        	
        	if ($terms) {
        	
        		update_option ( 'mag_current_issue' , $terms[0]->slug );
        	
        	}
        	
        	// publish all posts that are in the same issue
        	$args = array(
        				'posts_per_page'	=> -1,
        				'post_status'		=> 'pending',
        				'tax_query' 		=> array(
        						array(
        							'taxonomy' 	=> 'issue',		
        							'field'		=> 'slug',
        							'terms'		=> $terms[0]->slug, 			
        						)
        				)
        	);
        	
        	remove_action( 'publish_post' , 'mag_check_for_new_issue_on_publish' );

        	// get list of posts
        	$issue_pending_posts = get_posts( $args );
        	
        	// loop through and set each post_status to publish
        	foreach( $issue_pending_posts as $pending_post ) {
        	
        		$pending_post->post_status = 'publish';
        		wp_update_post( $pending_post );
        	
        	}
        	
        	add_action( 'publish_post' , 'mag_check_for_new_issue_on_publish' );
    	}
    }
}

// register the action
add_action( 'publish_post' , 'mag_check_for_new_issue_on_publish' );



/***********************
 *
 *  Settings 
 *
 ***********************/

/*
 *  Set up the Magazine settings on the Settings > General page in the admin interface
 */
 
function mag_settings_api_init() {
 	// Add the section to reading settings so we can add our
 	// fields to it
 	add_settings_section('mag_setting_section',
		'Magazine Settings',
		'mag_setting_section_function',
		'general');
 	
 	// Add the field with the names and function to use for our new
 	// settings, put it in our new section
 	add_settings_field('mag_current_issue',
		'Current Issue',
		'mag_current_issue_setting_function',
		'general',
		'mag_setting_section');
 	
 	add_settings_field('mag_master_category',
		'Master Category',
		'mag_master_category_setting_function',
		'general',
		'mag_setting_section');
 	
 	// Register our setting so that $_POST handling is done for us and
 	// our callback function just has to echo the <input>
 	register_setting('general','mag_current_issue');
 	register_setting('general','mag_master_category');
}
 
// set up call to create magazine settings
add_action('admin_init', 'mag_settings_api_init');
 
/*
 * Display an intro message for the Magazine settings section
 */
 
function mag_setting_section_function() {
 	echo '<p>Set the current issue and the master category. Publishing a post in the master category updates the current issue with the published post\'s issue (if assigned).</p>';
}
 
/*
 * Display the mag_current_issue dropdown 
 */
 
function mag_current_issue_setting_function() {
 
 	$terms = get_terms ( 'issue' );
 	$mag_current_issue = get_option( 'mag_current_issue', '' );
 	 	
 	$output = '<select name="mag_current_issue" id="mag_current_issue"><option value="">Select</option>';
 	
 	foreach( $terms as $term ) {
 
 		$selected = '';
 
 		if ($term->slug == $mag_current_issue ) $selected = ' selected';
 		
 	 	$output .= '<option value="' . $term->slug . '"' . $selected . '>' . $term->name . '</option>';
 	 	
 	}
 	
 	echo $output . '</select>';
 
}
 
/*
 * Display the mag_master_category dropdown 
 */
 
function mag_master_category_setting_function() {
 
 	$terms = get_terms ( 'category' );
 	$mag_master_category = get_option( 'mag_master_category' );
 	 	
 	$output = '<select name="mag_master_category" id="mag_master_category"><option value="">Select</option>';
 	
 	foreach( $terms as $term ) {
 
 		$selected = '';
 
 		if ($term->slug == $mag_master_category ) $selected = ' selected';
 		
 	 	$output .= '<option value="' . $term->slug . '"' . $selected . '>' . $term->name . '</option>';
 	 	
 	}
 	
 	echo $output . '</select>';
 
}

/***************************
*
*  Helper functions
*
***************************/
 
/*
 * Get the current issue - will get it off the post first, then the query variable issue, then the setting mag_current_issue
 */
 
function mag_get_current_issue() {

	global $post;

	if ( $post ) {
	
		$term_list = wp_get_post_terms($post->ID, 'issue');
		
		if ( count($term_list) ) return $term_list[0]->slug;
	
	}

	// if an issue is passed on the querystring then use it otherwise use the global option
	if ( get_query_var( 'issue' )) {
		return get_query_var( 'issue' );		
	} else {
		// get the issue value from the global option
		return get_option( 'mag_current_issue' , 'one');		
	}

}
?>