<?php
/*
Plugin Name: Restrict Tags
Description: Allow site admins to restrict the tags & custom taxonomies available for other users on their site.
Author: _FindingSimple
Author URI: http://findingsimple.com/
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
	global $submenu, $wp_taxonomies;

	if( !current_user_can( 'activate_plugins' ) ) {
		foreach( $submenu[ 'edit.php' ] as $key => $menu_item )
			if( $menu_item[1] == 'manage_categories' && $menu_item[0] != "Categories" )
				unset( $submenu[ 'edit.php' ][ $key ] ); // Remove "Tags" taxononmies links from the Admin Menu
		foreach( $wp_taxonomies as $tax_name => $tax_obj ){
			error_log( "$tax_name = " . print_r( $tax_obj, true ) );
			if( rt_is_tax_to_change( $tax_name, $tax_obj ) ){
				$wp_taxonomies[ $tax_name ]->show_ui = false; // Removes quick edit & Post Tags metabox
				add_meta_box( $tax_name . 'div', $tax_obj->labels->name, 'rt_custom_tag_metabox', 'post', 'side', 'low', $tax_name );
			}
		}
	}
}
add_action( 'admin_menu' , 'rt_modify_tag_ui' );


/**
 * The callback function for the custom tags metabox. 
 **/
function rt_custom_tag_metabox( $post, $args ) {

	$taxonomy = $args[ 'args' ];
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
 * When a post is saved by a non-admin user, the taxonomy tags are sent as an array because
 * they come from a hierarchal UI. This confuses the WordPress tag saving process,
 * so this function transforms the array into the flat CSV string WP expects.
 **/
function rt_modify_tags_structure(){
	global $wp_taxonomies;

	foreach( $wp_taxonomies as $tax_name => $tax_obj ){
		if( isset( $_POST[ 'tax_input' ][ $tax_name ] ) && is_array( $_POST[ 'tax_input' ][ $tax_name ] ) && rt_is_tax_to_change( $tax_name, $tax_obj ) ){
			$terms = $_POST[ 'tax_input' ][ $tax_name ];
			unset( $terms[0] );
			foreach( $terms as $id => $term ){
				$term = get_term( $term, $tax_name );
				$terms[ $id ] = $term->name;
			}
			$_POST[ 'tax_input' ][ $tax_name ] = implode( ', ', $terms );
		}
	}
}
add_action( 'init', 'rt_modify_tags_structure' );


/**
 * Check if the taxonomy specified via parameters should be changed by the plugin
 **/
function rt_is_tax_to_change( $tax_name, $tax_obj ){

	if( $tax_obj->hierarchical == false && $tax_obj->show_ui == true && in_array( 'post', $tax_obj->object_type ) && ( $tax_obj->_builtin == false || $tax_name == 'post_tag' ) )
		return true;
	else
		return false;
}


/**
 * On plugin activation, remove capabilities for editing/managing/deleting terms from non-admin roles
 **/
function ft_remove_term_caps(){
	global $wp_roles;

	$roles = $wp_roles->get_names();

	foreach ( $roles as $role => $value ){
		if( $role == 'administrator' )
			continue;
		$wp_roles->remove_cap( $role , 'manage_categories' );
	}
}
register_activation_hook( __FILE__, 'ft_remove_term_caps' );