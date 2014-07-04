<?php
/*
Plugin Name: WordPress Post Contributors
Plugin URI: https://github.com/kishoresahoo
Description: Add an option to your posts to enable display of more than one author-name on a post on WordPress.
Version: 1.0.
Author: Kishore
Author URI: http://blog.kishorechandra.co.in/
Requires at least: 3.0
Tested up to: 3.9
Text Domain: post_contributors
Domain Path: /languages/

Copyright: 2014 Kishore Sahoo
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Localisation
 */
load_plugin_textdomain( 'post_contributors', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * WP_Post_Contributors class.
 */
class WP_Post_Contributors {

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		
		// Actions
		add_action( 'add_meta_boxes', array( $this, 'post_contributors_add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
	}

	/**
	 * Adds the meta box container.
	 *
	 * @access public
	 * @param  string $post_type
	 * @return void
	 */
	public function post_contributors_add_meta_box( $post_type ) {
		
            $post_types = array('post');     //limit meta box to certain post types i.e  'page' or product etc.
            
	        if ( in_array( $post_type, $post_types )) {
				add_meta_box(
					'post_contributors'
					,__( 'Contributors', 'post_contributors' )
					,array( $this, 'render_meta_box_content' )
					,$post_type
					,'advanced'
					,'high'
				);
            }
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @access public
	 * @param  int $post_id
	 * @return void
	 */
	public function save( $post_id ) {
		
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['post_contributors_box_nonce'] ) )
			return $post_id;

		$nonce = $_POST['post_contributors_box_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'post_contributors_box' ) )
			return $post_id;

		// If this is an autosave, our form has not been submitted,
                //     so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return $post_id;

		// Check the user's permissions.
		if ( 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) )
				return $post_id;
	
		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;
		}

		/* OK, its safe for us to save the data now. */
		
		// Sanitize the user input.
		$post_contributors_list_data = sanitize_text_field( serialize( $_POST['post_contributors_list'] ) );
		

		// Update the meta field.
		update_post_meta( $post_id, '_post_contributors_list_meta_value_key', $post_contributors_list_data );
	}

	/**
	 * Render Meta Box content.
	 *
	 * @access public
	 * @param  object $post
	 * @return void
	 */
	public function render_meta_box_content( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'post_contributors_box', 'post_contributors_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$values = maybe_unserialize( get_post_meta( $post->ID, '_post_contributors_list_meta_value_key', true ) );
		
		// Get Blog User list as a array
		$blogusers = get_users();

		// Display the form, using the current value.
		echo '<label for="post_contributors_list">';
		_e( 'Choose the author', 'post_contributors' );
		echo '</label> ';
		echo '<br /> ';
		echo '<ul>';
		$checked = '';
		
		foreach ( $blogusers as $bloguser ) {
			$bloguser_id = $bloguser->ID;
			$bloguser_name = $bloguser->display_name;
			
			
			if ( is_array($values) && in_array($bloguser_id, $values) ) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}
			
			
			echo '<li>';
			echo '<input type="checkbox" id="post_contributors_list" name="post_contributors_list[]"';
	        echo ' value="' . esc_attr( $bloguser_id ) . '" size="25" '. $checked . '/>';
			echo esc_attr( __( $bloguser_name, 'post_contributors' ));
			echo '</li>';
			$checked = '';
		}
		echo '</ul>';
		
	}
}

/**
 * Calls the class on the post edit screen.
 */
function call_wp_post_contributors() {
    new WP_Post_Contributors();
}

/**
 * Calls the function on sinle page (only when in admin area)
 */
if ( is_admin() ) {
    add_action( 'load-post.php', 'call_wp_post_contributors' );
    add_action( 'load-post-new.php', 'call_wp_post_contributors' );
}


/**
 * Calls the function on sinle/blog page (only when not in admin area)
 */
if ( !is_admin() ) {
    add_filter( 'the_content', 'post_contributors_post_content_meta_box' );
	add_action( 'wp_enqueue_scripts', 'load_post_contributors_styles' ); 
}

/**
 * Loads the required css 
 */
function load_post_contributors_styles() {
    wp_enqueue_style( 'post-contributors', plugins_url( 'assets/css/wordpress-post-contributors.css', __FILE__ ) );
}

/**
 * Display post_contributors below post content.
 *
 * @param  object $content
 * @return object $content
 */
function post_contributors_post_content_meta_box( $content ) {
	
	global $post;
	
	// Use get_post_meta to retrieve an existing value from the database.
	$post_contributors = maybe_unserialize( get_post_meta( $post->ID, '_post_contributors_list_meta_value_key', true ) );
	
	if (!empty($post_contributors)) {
			
		$post_contributors_box  = '<h3 class="post-contributors-box">';
		$post_contributors_box  .= __( 'Contributors', 'post_contributors' );
		$post_contributors_box  .= '</h3>';
		$post_contributors_box  .= '<ul class="post-contributors-box">';
		
		foreach ( $post_contributors as $post_contributor ) {
				
			$post_contributors_name   = get_the_author_meta('user_login', $post_contributor);
			
			// if user is there
			if ( $post_contributors_name ) {
				
				$post_contributors_url    = get_author_posts_url( $post_contributor, $post_contributors_name );
				$post_contributors_avatar = get_avatar( $post_contributor, 24, '', 'avatar' );
				$post_contributors_post_count = count_user_posts( $post_contributor ); // get the post count
				
				$post_contributors_box .= '<li>';
				$post_contributors_box .= $post_contributors_avatar;
				
				// if post count > 0 , add link.
				if ($post_contributors_post_count > 0) {
					$post_contributors_box .= '<a href="'. $post_contributors_url . '" rel="author" target="_blank">';
				}

				$post_contributors_box .= '<span>';
				$post_contributors_box .= $post_contributors_name;	
				$post_contributors_box .= '</span>';
				
				// if post count > 0 , close link.
				if ($post_contributors_post_count > 0) {
					$post_contributors_box .= '</a>';
				}
				
				$post_contributors_box .= '</li>';
			}
			
		}
		
		$post_contributors_box .= '</ul>';
		
		$content = $content . $post_contributors_box;
	}

	return $content;
}