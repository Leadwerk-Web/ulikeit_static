<?php
/**
 * Global ACF-compatible functions provided by Leadwerk Fields.
 * Loaded ONLY when ACF (Pro) is not active.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_field' ) ) {
	function get_field( $name, $post_id = null ) {
		return Leadwerk_Fields_API::get_field( $name, $post_id );
	}
}

if ( ! function_exists( 'update_field' ) ) {
	function update_field( $name, $value, $post_id = null ) {
		Leadwerk_Fields_API::update_field( $name, $value, $post_id );
	}
}

if ( ! function_exists( 'the_field' ) ) {
	function the_field( $name, $post_id = null ) {
		echo esc_html( (string) get_field( $name, $post_id ) );
	}
}

if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $name, $post_id = null ) {
		return Leadwerk_Fields_API::have_rows( $name, $post_id );
	}
}

if ( ! function_exists( 'the_row' ) ) {
	function the_row() {
		Leadwerk_Fields_API::the_row();
	}
}

if ( ! function_exists( 'get_row_layout' ) ) {
	function get_row_layout() {
		return Leadwerk_Fields_API::get_row_layout();
	}
}

if ( ! function_exists( 'get_sub_field' ) ) {
	function get_sub_field( $name ) {
		return Leadwerk_Fields_API::get_sub_field( $name );
	}
}

if ( ! function_exists( 'acf_add_options_page' ) ) {
	function acf_add_options_page( $args = array() ) {
		Leadwerk_Fields_API::acf_add_options_page( $args );
	}
}

if ( ! function_exists( 'acf_register_block_type' ) ) {
	function acf_register_block_type( $args = array() ) {
		Leadwerk_Fields_API::acf_register_block_type( $args );
	}
}
