<?php
/**
 * @package Restrict Tags & Custom Taxonomy Columns
 * @version 1.0
 */
/*
Plugin Name: Restrict Tags
Description: Allow site admins to restrict the tags available for other users on their site.
Author: Jason Conroy, Brent Shepherd
Version: 1.0
*/


/**
 * For non-administrators, this function hides the tag metabox, quick edit and
 * Post Tags menu links. 
 **/
function rt_remove_tag_traces(){
	global $menu, $submenu, $wp_taxonomies;

	if( !current_user_can( 'activate_plugins' ) ) {
		unset( $submenu[ 'edit.php' ][ 16 ] ); // Remove "Post Tags" item from the Admin Menu
		$wp_taxonomies[ 'post_tag' ]->show_ui = false; // Need custom metabox so it doesn't include the 'Add New Tag'. 
		$wp_taxonomies[ 'post_tag' ]->hierarchical = true; // Checkboxes for quick edit & advanced edit
		//remove_meta_box( 'tagsdiv-post_tag', 'post', 'side' );
	}
}
add_action( 'admin_menu' , 'rt_remove_tag_traces' );


/**
 * Because the tags are set to be hierachal, the markup changes and therefore the 
 * tags need to be saved manually. 
 **/
function rt_save_tags( $post_id ){

	// First delete the tags added by WordPress (not way to overcome that)


	//wp_delete_term( $term, $taxonomy, $args = array() )

	// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return $post_id;

	if ( 'page' == $_POST['post_type'] )
  		return $post_id;
	elseif ( !current_user_can( 'edit_post', $post_id ) )
	  	return $post_id;

	error_log( '$_POST = ' . print_r( $_POST, true ) );
	if( isset( $_POST[ 'post_tags' ] ) ){
		$post_tags = $_POST[ 'post_tag' ];
		//wp_set_object_terms( $post_id, $post_tags, 'post_tags' );
		error_log( '$post_tags = ' . print_r( $post_tags, true ) );
	}

	return $post_id;
}
add_action( 'save_post', 'rt_save_tags' );


/**
 * When a post is saved by a non-admin user, the tags are sent as an array because
 * they use a hierarchal UI. This screws with the internal WP admin tag saving
 * process, so this function restores the array to a flat CSV string.
 **/
function rt_modify_tags_structure(){
	if( isset( $_POST[ 'tax_input' ][ 'post_tag' ] ) && is_array( $_POST[ 'tax_input' ][ 'post_tag' ] ) ){
		$terms = $_POST[ 'tax_input' ][ 'post_tag' ];
		unset( $terms[0] );
		foreach( $terms as $id => $term ){
			$term = get_term( $term, 'post_tag' );
			$terms[ $id ] = $term->name;
		}
		$_POST[ 'tax_input' ][ 'post_tag' ] = implode( ', ', $terms );
	}
}
add_action( 'admin_init', 'rt_modify_tags_structure' );


/**
 * For non-administrators, this function adds a new meta box which lists the tags created by 
 * an admin with a checkbox. 
 **/
function rt_add_tag_metabox(){

	if( !current_user_can( 'activate_plugins' ) ) // check for admin
		add_meta_box( 'post_tag' . 'div', __( 'Post Tags' ), 'rt_custom_tag_metabox', 'post', 'side', 'core' );
		//add_meta_box( 'tagsdiv-' . 'post_tag', __( 'Post Tags' ), 'rt_custom_tag_metabox', 'post', 'side', 'core' );
}
//add_action( 'add_meta_boxes' , 'rt_add_tag_metabox' );


/**
 * The callback function for the custom tags metabox. 
 **/
function rt_custom_tag_metabox( $post ) {

	$taxonomy = 'post_tag';
	$tax = get_taxonomy( $taxonomy );

	?>
	<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">

		<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
			<?php
            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
            ?>
			<ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
				<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy ) ) ?>
			</ul>
		</div>
	</div>
	<?php
}


/**
 * The callback function for the custom tags metabox. 
 **/
function rt_mock_tax(){
	$args = array( 'label' => 'Mocks' );
	register_taxonomy( 'mock_tax', 'post', $args );

	$args = array( 'label' => 'Faux', 'hierarchical' => true );
	register_taxonomy( 'faux_tax', 'post', $args );
}
//add_action( 'init', 'rt_mock_tax' );


/**
 * Add a column to the manage posts page for each registered custom taxonomy. 
 **/
function rt_add_columns( $columns ) {

	$taxonomy_names = get_object_taxonomies( 'post' );

	foreach ( $taxonomy_names as $taxonomy_name ) {

		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( $taxonomy->_builtin )
			continue;

		$columns[ $taxonomy_name ] = $taxonomy->label;
	}

	return $columns;
}
add_filter( 'manage_posts_columns', 'rt_add_columns' ); //Filter out Post Columns with 2 custom columns


/**
 * Add the terms assigned to a post for each registered custom taxonomy to the 
 * custom column on the manage posts page.
 **/
function rt_column_contents( $column_name, $post_id ) {
	global $wpdb;

	$taxonomy_names = get_object_taxonomies( 'post' );

	foreach ( $taxonomy_names as $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( $taxonomy->_builtin || $column_name != $taxonomy_name )
			continue;

		$terms = get_the_terms( $post_id, $taxonomy_name ); //lang is the first custom taxonomy slug
		if ( !empty( $terms ) ) {
			$out = array();
			foreach ( $terms as $term )
				$termlist[] = "<a href='edit.php?$taxonomy_name=$term->slug'> " . esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, $taxonomy_name, 'display' ) ) . "</a>";
			echo implode( ', ', $termlist );
		} else {
			printf( __( 'No %s.'), $taxonomy->label );
		}
	}
}
add_action( 'manage_posts_custom_column', 'rt_column_contents', 10, 2 );
