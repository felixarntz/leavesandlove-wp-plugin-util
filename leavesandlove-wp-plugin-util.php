<?php
/**
 * @package leavesandlove-wp-plugin-util
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'LaL_WP_Plugin_Util' ) ) {

	class LaL_WP_Plugin_Util {

		public static function parse_args( $args, $defaults = array(), $hard = false ) {
			$args = wp_parse_args( $args, $defaults );
			if ( $hard ) {
				$args = array_intersect_key( $args, $defaults );
			}
			return $args;
		}

		public static function build_path( $base_path, $path = '' ) {
			$base_path = untrailingslashit( $base_path );
			if ( ! empty ( $path ) ) {
				return $base_path . '/' . ltrim( $path, '/\\' );
			}
			return $base_path;
		}

		public static function get_package_data( $package, $type = 'plugin', $remote = false ) {
			if ( $remote ) {
				switch ( $type ) {
					case 'theme':
						$response = themes_api( 'theme_information', array(
							'slug'		=> $package,
							'fields'	=> array(
								'sections'	=> false,
								'tags'		=> false,
							),
						) );
						if ( is_object( $response ) ) {
							$response = json_decode( json_encode( $response ), true );
						}
						return $response;
						break;
					case 'plugin':
						$package = trim( $package, '/' );
						$package = explode( '/', $package );
						$package = $package[0];
						$args = array(
							'slug'		=> $package,
							'fields'	=> array(
								'sections'		=> false,
								'tags'			=> false,
								'banners'		=> false,
								'reviews'		=> false,
								'ratings'		=> false,
								'compatibility'	=> false,
							),
						);
						if ( function_exists( 'plugins_api' ) ) {
							$response = plugins_api( 'plugin_information', $args );
						} else {
							$args = (object) $args;

							$response = wp_remote_post( 'http://api.wordpress.org/plugins/info/1.0/', array(
								'timeout'		=> 15,
								'body'			=> array(
									'action'		=> 'plugin_information',
									'request'		=> serialize( $args ),
								),
							) );
						}
						if ( ! is_wp_error( $response ) ) {
							$response = maybe_unserialize( wp_remote_retrieve_body( $response ) );
							if ( is_object( $response ) ) {
								$response = json_decode( json_encode( $response ), true );
							}
							if ( is_array( $response ) ) {
								return $response;
							}
						}
						break;
					default:
				}
			} else {
				switch ( $type ) {
					case 'theme':
						$theme = wp_get_theme( $package );
						if ( $theme->exists() ) {
							$default_headers = array(
								'Name' => 'Theme Name',
								'ThemeURI' => 'Theme URI',
								'Version' => 'Version',
								'Description' => 'Description',
								'Author' => 'Author',
								'AuthorURI' => 'Author URI',
								'TextDomain' => 'Text Domain',
								'DomainPath' => 'Domain Path',
							);
							$data = array();
							foreach ( $default_headers as $header => $name ) {
								$value = $theme->get( $header );
								if ( $value ) {
									$data[ $header ] = $value;
								}
							}
							return $data;
						}
						break;
					case 'plugin':
						if ( strpos( $package, '.php' ) === strlen( $package ) - 4 ) {
							$package = substr( $package, 0, strlen( $package ) - 4 );
						}
						if ( strpos( $package, '/' ) === false ) {
							$package .= '/' . $package;
						}
						$package .= '.php';
						if ( function_exists( 'get_plugin_data' ) ) {
							return get_plugin_data( WP_PLUGIN_DIR . '/' . $package );
						} else {
							$default_headers = array(
								'Name' => 'Plugin Name',
								'PluginURI' => 'Plugin URI',
								'Version' => 'Version',
								'Description' => 'Description',
								'Author' => 'Author',
								'AuthorURI' => 'Author URI',
								'TextDomain' => 'Text Domain',
								'DomainPath' => 'Domain Path',
							);
							return get_file_data( WP_PLUGIN_DIR . '/' . $package, $default_headers, 'plugin' );
						}
						break;
					default:
				}
			}

			return null;
		}

	}

}
