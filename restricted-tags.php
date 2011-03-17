<?php
/**
 * @package Restrict Tags & Custom Taxonomy Columns
 * @version 1.0
 */
/*
Plugin Name: Restrict Tags & Add Columns for Custom Taxonomies
Description: Allow site admins to restrict the tags available for other users on their site. Give custom taxonomies a column on the manage posts page. 
Author: Jason Conroy, Brent Shepherd
Version: 1.0
*/


/**
 * For non-administrators, this function converts the tag metabox on the quick edit and 
 * edit post screens.
 *
 * It does this by overriding the 'post_tag' item in the $wp_taxonomies global and setting
 * it to be a hierarchical type. This is the only method to convert the tag form to radio boxes 
 * on the quick edit form. It does not change the 'post_tag' to hierarchical when a post is
 * being saved because this breaks the internal WordPress process for saving tags.
 *
 * The function also removes the Post Tags submenu menu item from the $submenu global. 
 **/
function rt_convert_tags(){
	global $submenu, $wp_taxonomies;

	if( !current_user_can( 'activate_plugins' ) && !isset( $_POST[ 'action' ] ) ){ 
		unset( $submenu[ 'edit.php' ][ 16 ] );
		$wp_taxonomies[ 'post_tag' ]->hierarchical = true;
	}
}
add_action( 'admin_menu' , 'rt_remove_tag_traces' );


/**
 * When a post is saved by a non-admin user, the tags are sent as an array because
 * they use a hierarchal UI. This confuses the WordPress tag saving process,
 * so this function transforms the array into a flat CSV string.
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
 * Include CSS to hide the "Add Post Tags" for non-administrators on the edit post screen.
 **/
function rt_add_css(){	
	if( !current_user_can( 'activate_plugins' ) ) {
		?>
		<style type="text/css">
			#post_tag-adder { display:none; }
		</style>
	<?php
	}
}
add_action( 'admin_print_styles-post.php', 'rt_add_css' );


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
