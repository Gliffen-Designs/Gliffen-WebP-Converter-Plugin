<?php
/**
 * GitHub Update Checker for Gliffen WebP Converter
 *
 * Checks GitHub releases for plugin updates and displays
 * update notifications in the WordPress admin.
 *
 * @package Gliffen_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WIC_Update_Checker {
	private static $instance = null;
	private $github_repo = 'Gliffen-Designs/Gliffen-WebP-Converter-Plugin';
	private $plugin_file = '';
	private $plugin_slug = 'gliffen-webp-converter';
	private $transient_key = 'wic_github_release_info';
	private $cache_duration = 12 * HOUR_IN_SECONDS; // Check every 12 hours

	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Resolve plugin path dynamically in case folder name differs per install.
		$this->plugin_file = plugin_basename( WIC_PLUGIN_DIR . 'webp-image-converter.php' );

		// Hook into WordPress update checks
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
	}

	/**
	 * Check for updates from GitHub
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release_info = $this->get_github_release();

		if ( ! $release_info ) {
			return $transient;
		}

		$current_version = $this->get_current_version();
		$remote_version = $release_info['version'];

		// Compare versions
		if ( version_compare( $remote_version, $current_version, '>' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $remote_version,
				'url'         => $release_info['url'],
				'package'     => $release_info['package'],
				'tested'      => $release_info['tested'],
				'requires'    => '5.0',
				'icons'       => array(),
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the detail modal
	 */
	public function plugin_info( $response, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $response;
		}

		$accepted_slugs = array( $this->plugin_slug, 'Gliffen-WebP-Converter', 'gliffen-webp-converter-plugin' );
		if ( ! isset( $args->slug ) || ! in_array( $args->slug, $accepted_slugs, true ) ) {
			return $response;
		}

		$release_info = $this->get_github_release();

		if ( ! $release_info ) {
			return $response;
		}

		return (object) array(
			'name'              => 'Gliffen WebP Converter',
			'slug'              => $this->plugin_slug,
			'version'           => $release_info['version'],
			'author'            => 'Gliffen',
			'author_profile'    => 'https://github.com/Gliffen-Designs', // Change this!
			'requires'          => '5.0',
			'requires_php'      => '7.2',
			'tested'            => $release_info['tested'],
			'requires_plugins'  => array(),
			'last_updated'      => $release_info['published_date'],
			'sections'          => array(
				'description'  => $release_info['description'] ?: 'WebP image converter plugin',
				'installation' => 'Install and activate the plugin. Go to Tools > WebP Converter to configure.',
				'changelog'    => $release_info['body'] ?: 'See GitHub releases for details.',
			),
			'download_link'     => $release_info['package'],
			'banners'           => array(),
			'url'               => $release_info['url'],
		);
	}

	/**
	 * Fetch the latest release from GitHub
	 */
	private function get_github_release() {
		// Check cache first
		$cached = get_transient( $this->transient_key );
		if ( $cached ) {
			return $cached;
		}

		$url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";

		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				),
				'sslverify' => true,
				'timeout'   => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			WIC_File_Logger::log( 'GitHub update check failed: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			WIC_File_Logger::log( 'GitHub update check failed with HTTP ' . $code, 'error' );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		// Extract version from tag (e.g., "v1.0.5" or "V1.0.5" -> "1.0.5")
		$version = trim( preg_replace( '/^[vV]/', '', $data['tag_name'] ) );

		// Find the plugin zip file in assets
		$package_url = false;
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( strpos( $asset['name'], '.zip' ) !== false ) {
					$package_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		// Fallback to zipball if no release asset
		if ( ! $package_url ) {
			$package_url = $data['zipball_url'];
		}

		$release_info = array(
			'version'        => $version,
			'url'            => $data['html_url'],
			'package'        => $package_url,
			'body'           => $data['body'],
			'description'    => $data['body'],
			'published_date' => $data['published_at'],
			'tested'         => '6.4', // Update this to the latest WP version tested
		);

		// Cache the result
		set_transient( $this->transient_key, $release_info, $this->cache_duration );

		return $release_info;
	}

	/**
	 * Get the current plugin version from the header
	 */
	private function get_current_version() {
		$plugin_data = get_file_data(
			WIC_PLUGIN_DIR . 'webp-image-converter.php',
			array( 'Version' => 'Version' )
		);
		return $plugin_data['Version'] ?: '1.0.0';
	}

	/**
	 * Clear update cache (useful for testing)
	 */
	public static function clear_cache() {
		delete_transient( 'wic_github_release_info' );
		delete_site_transient( 'update_plugins' );
	}
}
