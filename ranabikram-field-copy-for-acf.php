<?php
/**
 * Plugin Name:       Ranabikram Field Copy for ACF
 * Description:       Adds a "Copy" action to ACF fields, letting you copy a field (and its sub-fields) into another field group while keeping the original in place. Complements ACF's built-in "Move".
 * Version:           1.0.0
 * Author:            Bikram Rana
 * License:           GPL-2.0-or-later
 * Requires at least: 5.8
 * Tested up to:      7.0
 * Requires PHP:      7.2
 * Text Domain:       ranabikram-field-copy-for-acf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACFCF_VERSION', '1.0.0' );
define( 'ACFCF_URL', plugin_dir_url( __FILE__ ) );
define( 'ACFCF_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Main plugin class.
 */
final class ACFCF_Plugin {

	/**
	 * Capability required to copy fields. Mirrors who can edit field groups.
	 */
	const CAPABILITY = 'manage_options';

	public function __construct() {
		// Only wire up if ACF (free or PRO) is present.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_duplicate_field' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_acf_notice' ) );
			return;
		}

		// Enqueue assets only on the field group editor screen.
		add_action( 'acf/field_group/admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX endpoint that performs the copy.
		add_action( 'wp_ajax_acfcf_copy_field', array( $this, 'ajax_copy_field' ) );
	}

	public function missing_acf_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Ranabikram Field Copy for ACF requires Advanced Custom Fields (free or PRO) to be active.', 'ranabikram-field-copy-for-acf' );
		echo '</p></div>';
	}

	/**
	 * Load JS/CSS and pass data + a nonce to the browser.
	 */
	public function enqueue_assets() {
		wp_enqueue_script(
			'acfcf',
			ACFCF_URL . 'assets/copy-field.js',
			array( 'jquery', 'acf-field-group' ),
			ACFCF_VERSION,
			true
		);

		wp_enqueue_style(
			'acfcf',
			ACFCF_URL . 'assets/copy-field.css',
			array(),
			ACFCF_VERSION
		);

		wp_localize_script(
			'acfcf',
			'ACFCF',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'acfcf_copy' ),
				'groups'  => $this->get_field_group_choices(),
				'i18n'    => array(
					'copy'    => __( 'Copy', 'ranabikram-field-copy-for-acf' ),
					'title'   => __( 'Copy field to group', 'ranabikram-field-copy-for-acf' ),
					'confirm' => __( 'Copy', 'ranabikram-field-copy-for-acf' ),
					'cancel'  => __( 'Cancel', 'ranabikram-field-copy-for-acf' ),
					/* translators: %s: title of the destination field group. */
					'success' => __( 'Field copied to “%s”.', 'ranabikram-field-copy-for-acf' ),
					'error'   => __( 'Sorry, the field could not be copied.', 'ranabikram-field-copy-for-acf' ),
					'unsaved' => __( 'Please save this field group before copying a newly added field.', 'ranabikram-field-copy-for-acf' ),
				),
			)
		);
	}

	/**
	 * Build a lightweight list of field groups for the picker.
	 *
	 * @return array<int, array{key:string,title:string,id:int}>
	 */
	private function get_field_group_choices() {
		$choices = array();

		foreach ( acf_get_field_groups() as $group ) {
			// Skip groups registered in PHP/JSON only (no editable DB post to receive a field).
			if ( empty( $group['ID'] ) ) {
				continue;
			}

			$choices[] = array(
				'key'   => $group['key'],
				'title' => $group['title'],
				'id'    => (int) $group['ID'],
			);
		}

		return $choices;
	}

	/**
	 * AJAX handler: copy a field into the chosen field group.
	 */
	public function ajax_copy_field() {
		check_ajax_referer( 'acfcf_copy', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$field_key = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$group_key = isset( $_POST['group_key'] ) ? sanitize_text_field( wp_unslash( $_POST['group_key'] ) ) : '';

		// The source field must already exist in the database.
		$field = acf_get_field( $field_key );
		if ( ! $field || empty( $field['ID'] ) ) {
			wp_send_json_error( array( 'message' => 'unsaved' ) );
		}

		// Resolve the destination group.
		$group = acf_get_field_group( $group_key );
		if ( ! $group || empty( $group['ID'] ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		// Reuse ACF's own duplication logic, but point it at a different parent.
		// This generates a fresh field key and recursively copies sub-fields.
		$new_field = acf_duplicate_field( $field['ID'], $group['ID'] );

		if ( ! $new_field || empty( $new_field['ID'] ) ) {
			wp_send_json_error( array( 'message' => 'failed' ) );
		}

		// acf_duplicate_field() generates a fresh field key but keeps the
		// original field "name" (the post-meta key). Within a single group, two
		// fields sharing a name overwrite each other's values on save, so make
		// the copy's name unique within the destination group.
		//
		// Note: this guarantees uniqueness *within the destination group*. Two
		// fields sharing a name across *different* groups that appear on the
		// same edit screen can still collide — see the readme FAQ. Detecting
		// that reliably would require evaluating each group's location rules
		// against a live edit screen, which ACF itself does not attempt for its
		// own Move/Duplicate actions.
		$unique_name = $this->unique_field_name( $new_field['name'], $group['ID'], $new_field['ID'] );

		if ( $unique_name !== $new_field['name'] ) {
			$new_field['name']  = $unique_name;
			$new_field['label'] = $new_field['label'] . ' (copy)';
			acf_update_field( $new_field );
		}

		wp_send_json_success(
			array(
				'group_title' => $group['title'],
				'group_url'   => admin_url( 'post.php?post=' . $group['ID'] . '&action=edit' ),
				'new_key'     => $new_field['key'],
				'new_name'    => $new_field['name'],
				'field_label' => $field['label'],
			)
		);
	}

	/**
	 * Build a field name that is not already used by another field in the group.
	 *
	 * @param string    $name       Desired field name.
	 * @param array|int $group      Destination field group (array or ID).
	 * @param int       $exclude_id Field ID to ignore (the copy itself).
	 * @return string Unique field name.
	 */
	private function unique_field_name( $name, $group, $exclude_id ) {
		$taken = array();

		// acf_get_fields() can return false (e.g. an empty or unreadable group);
		// cast so the loop is always safe, and skip any malformed sibling rows.
		foreach ( (array) acf_get_fields( $group ) as $sibling ) {
			if ( empty( $sibling['name'] ) || empty( $sibling['ID'] ) ) {
				continue;
			}
			if ( (int) $sibling['ID'] === (int) $exclude_id ) {
				continue;
			}
			$taken[] = $sibling['name'];
		}

		if ( ! in_array( $name, $taken, true ) ) {
			return $name;
		}

		$suffix    = 2;
		$candidate = $name . '_' . $suffix;

		while ( in_array( $candidate, $taken, true ) ) {
			$suffix++;
			$candidate = $name . '_' . $suffix;
		}

		return $candidate;
	}
}

new ACFCF_Plugin();
