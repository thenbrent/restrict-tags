<?php
/*
Plugin Name: Restrict Tags & Add Columns for Custom Taxonomies
Description: Allow site admins to restrict the tags available for other users on their site. Give custom taxonomies a column on the manage posts page. 
Author: Jason Conroy, Brent Shepherd
Version: 1.0
*/


/**
 * For non-administrators, this function hides the tag metabox, quick edit form as well as 
 * the Post Tags menu item.
 *
 * It also hooks a custom meta box to display tags created by an admin with a checkbox to
 * non-administrator users.
 **/
function rt_modify_tag_ui(){
	global $menu, $submenu, $wp_taxonomies;

	if( !current_user_can( 'activate_plugins' ) ) {
		unset( $submenu[ 'edit.php' ][ 16 ] ); // Remove "Post Tags" item from the Admin Menu
		$wp_taxonomies[ 'post_tag' ]->show_ui = false; // Removes quick edit & Post Tags metabox
		add_meta_box( 'post_tag' . 'div', __( 'Post Tags' ), 'rt_custom_tag_metabox', 'post', 'side', 'low' );
	}
}
add_action( 'admin_menu' , 'rt_modify_tag_ui' );


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
 * When a post is saved by a non-admin user, the tags are sent as an array because
 * they come from a hierarchal UI. This confuses the WordPress tag saving process,
 * so this function transforms the array into the flat CSV string WP expects.
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
add_action( 'init', 'rt_modify_tags_structure' );


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
			echo join( ', ', $termlist );
		} else {
			printf( __( 'No %s.'), $taxonomy->label );
		}
	}
}
add_action( 'manage_posts_custom_column', 'rt_column_contents', 10, 2 );
