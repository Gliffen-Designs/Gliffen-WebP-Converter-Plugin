<?php
/**
 * Settings Class
 * Manages plugin settings and options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_Settings {

	const PREFIX = 'wic_';

	/**
	 * Initialize settings
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_defaults' ) );
	}

	/**
	 * Ensure all default options exist
	 */
	public static function ensure_defaults() {
		$defaults = array(
			'auto_convert_enabled' => true,
			'webp_quality' => 80,
			'auto_backup_enabled' => false,
			'htaccess_configured' => false,
			'update_db_refs' => true,
			'batch_size' => 200,
		);

		foreach ( $defaults as $option => $default_value ) {
			if ( get_option( self::PREFIX . $option ) === false ) {
				add_option( self::PREFIX . $option, $default_value );
			}
		}
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting( 'wic_settings', self::PREFIX . 'auto_convert_enabled' );
		register_setting( 'wic_settings', self::PREFIX . 'webp_quality' );
		register_setting( 'wic_settings', self::PREFIX . 'auto_backup_enabled' );
		register_setting( 'wic_settings', self::PREFIX . 'htaccess_configured' );
		register_setting( 'wic_settings', self::PREFIX . 'update_db_refs' );
		register_setting( 'wic_settings', self::PREFIX . 'batch_size' );
	}

	/**
	 * Get option value
	 *
	 * @param string $option Option name (without prefix)
	 * @param mixed  $default Default value
	 *
	 * @return mixed Option value
	 */
	public static function get_option( $option, $default = false ) {
		return get_option( self::PREFIX . $option, $default );
	}

	/**
	 * Update option value
	 *
	 * @param string $option Option name (without prefix)
	 * @param mixed  $value Option value
	 *
	 * @return bool Success status
	 */
	public static function update_option( $option, $value ) {
		return update_option( self::PREFIX . $option, $value );
	}

	/**
	 * Delete option
	 *
	 * @param string $option Option name (without prefix)
	 *
	 * @return bool Success status
	 */
	public static function delete_option( $option ) {
		return delete_option( self::PREFIX . $option );
	}

	/**
	 * Get all plugin settings
	 *
	 * @return array All settings
	 */
	public static function get_all_settings() {
		return array(
			'auto_convert_enabled' => self::get_option( 'auto_convert_enabled', true ),
			'webp_quality' => self::get_option( 'webp_quality', 80 ),
			'auto_backup_enabled' => self::get_option( 'auto_backup_enabled', false ),
			'htaccess_configured' => self::get_option( 'htaccess_configured', false ),
			'update_db_refs' => self::get_option( 'update_db_refs', true ),
			'batch_size' => self::get_option( 'batch_size', 200 ),
		);
	}

	/**
	 * Reset to default settings
	 */
	public static function reset_to_defaults() {
		self::update_option( 'auto_convert_enabled', true );
		self::update_option( 'webp_quality', 80 );
		self::update_option( 'auto_backup_enabled', false );
		self::update_option( 'htaccess_configured', false );
		self::update_option( 'update_db_refs', true );
	}
}

WIC_Settings::init();
