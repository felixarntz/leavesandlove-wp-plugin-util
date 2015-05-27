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

		public static function format( $value, $type, $mode = 'input', $args = array() ) {
			$mode = $mode == 'output' ? 'output' : 'input';

			$formatted = $value;

			switch ( $type ) {
				case 'string':
					if ( $mode == 'output' ) {
						$formatted = esc_html( $formatted );
					}
					break;
				case 'html':
					$formatted = wp_kses_post( $value );
					if ( $mode == 'output' ) {
						$formatted = esc_html( $formatted );
					}
					break;
				case 'url':
					$formatted = esc_html( $value );
					if ( $mode == 'output' ) {
						$formatted = esc_url( $formatted );
					} else {
						$formatted = esc_url_raw( $formatted );
					}
					break;
				case 'boolean':
				case 'bool':
					if ( is_int( $value ) ) {
						if ( $value > 0 ) {
							$formatted = true;
						} else {
							$formatted = false;
						}
					} elseif ( is_string( $value ) ) {
						if ( ! empty( $value ) ) {
							if ( strtolower( $value ) == 'false' ) {
								$formatted = false;
							} else {
								$formatted = true;
							}
						} else {
							$formatted = false;
						}
					} else {
						$formatted = (bool) $value;
					}
					if ( $mode == 'output' ) {
						if ( $formatted ) {
							$formatted = 'true';
						} else {
							$formatted = 'false';
						}
					}
					break;
				case 'integer':
				case 'int':
					$positive_only = isset( $args['positive_only'] ) ? (bool) $args['positive_only'] : false;
					if ( $positive_only ) {
						$formatted = absint( $value );
					} else {
						$formatted = intval( $value );
					}
					if ( $mode == 'output' ) {
						$formatted = number_format_i18n( floatval( $formatted ), 0 );
					}
					break;
				case 'float':
				case 'double':
					$positive_only = isset( $args['positive_only'] ) ? (bool) $args['positive_only'] : false;
					$formatted = floatval( $value );
					if ( $positive_only ) {
						$formatted = abs( $formatted );
					}
					if ( $mode == 'output' ) {
						$decimals = isset( $args['decimals'] ) ? absint( $args['decimals'] ) : 2;
						$formatted = number_format_i18n( $formatted, $decimals );
					} else {
						$decimals = isset( $args['decimals'] ) ? absint( $args['decimals'] ) : false;
						if ( $decimals !== false ) {
							$formatted = number_format( $formatted, $decimals );
						}
					}
					break;
				case 'date':
				case 'time':
				case 'datetime':
					$timestamp = $value;
					if ( ! is_int( $timestamp ) ) {
						$timestamp = strtotime( $timestamp );
					}
					$format = isset( $args['format'] ) ? $args['format'] : '';
					if ( empty( $format ) ) {
						if ( $mode == 'output' ) {
							if ( $type == 'date' ) {
								$format = get_option( 'date_format' );
							} elseif ( $type == 'time' ) {
								$format = get_option( 'time_format' );
							} else {
								$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
							}
						} else {
							if ( $type == 'date' ) {
								$format = 'Ymd';
							} elseif ( $type == 'time' ) {
								$format = 'His';
							} else {
								$format = 'YmdHis';
							}
						}
					}
					$formatted = date_i18n( $format, $timestamp );
					break;
				case 'byte':
					$formatted = floatval( $value );
					if ( $mode == 'output' ) {
						$units = array( 'B', 'kB', 'MB', 'GB', 'TB' );
						$decimals = isset( $args['decimals'] ) ? absint( $args['decimals'] ) : 2;
						$base_unit = isset( $args['base_unit'] ) && in_array( $args['base_unit'], $units ) ? $args['base_unit'] : 'B';
						if ( $base_unit != 'B' ) {
							$formatted *= pow( 1024, array_search( $base_unit, $units ) );
						}
						for ( $i = count( $units ) - 1; $i >= 0; $i-- ) {
							if ( $formatted > pow( 1024, $i ) ) {
								$formatted = number_format_i18n( $formatted / pow( 1024, $i ), $decimals ) . ' ' . $units[ $i ];
								break;
							} elseif ( $i == 0 ) {
								$formatted = number_format_i18n( $formatted, $decimals ) . ' B';
							}
						}
					} else {
						$decimals = isset( $args['decimals'] ) ? absint( $args['decimals'] ) : false;
						if ( $decimals !== false ) {
							$formatted = number_format( $formatted, $decimals );
						}
					}
					break;
				default:
			}

			return $formatted;
		}

		public static function build_path( $base_path, $path = '' ) {
			$base_path = untrailingslashit( $base_path );
			if ( ! empty ( $path ) ) {
				return $base_path . '/' . ltrim( $path, '/\\' );
			}
			return $base_path;
		}

		public static function make_html_attributes( $atts, $html5 = true, $echo = true ) {
			$output = '';

			$bool_atts = array_filter( $atts, 'is_bool' );
			$atts = array_diff_key( $atts, $bool_atts );
			uksort( $atts, array( __CLASS__, '_sort_html_attributes' ) );
			$atts = array_merge( $atts, $bool_atts );

			foreach ( $atts as $key => $value ) {
				if ( is_bool( $value ) || $key == $value ) {
					if ( $value ) {
						if ( $html5 ) {
						  $output .= ' ' . $key;
						} else {
						  $output .= ' ' . $key . '="' . esc_attr( $key ) . '"';
						}
					}
				} else {
					$output .= ' ' . $key . '="' . esc_attr( $value ) . '"';
				}
			}

			if ( $echo ) {
				echo $output;
			}

			return $output;
		}

		public static function _sort_html_attributes( $a, $b ) {
			if ( $a != $b ) {
				$priorities = array( 'id', 'name', 'class' );
				if ( strpos( $a, 'data-' ) === 0 && strpos( $b, 'data-' ) !== 0 ) {
					if ( in_array( $b, $priorities ) ) {
						return 1;
					}
					return -1;
				} elseif ( strpos( $a, 'data-' ) !== 0 && strpos( $b, 'data-' ) === 0 ) {
					if ( in_array( $a, $priorities ) ) {
						return -1;
					}
					return 1;
				} elseif ( strpos( $a, 'data-' ) === 0 && strpos( $b, 'data-' ) === 0 ) {
					return 0;
				}

				$priorities = array_merge( $priorities, array( 'rel', 'type', 'value', 'href' ) );
				if ( in_array( $a, $priorities ) && ! in_array( $b, $priorities ) ) {
					return -1;
				} elseif ( ! in_array( $a, $priorities ) && in_array( $b, $priorities ) ) {
					return 1;
				} elseif ( in_array( $a, $priorities ) && in_array( $b, $priorities ) ) {
					$key_a = array_search( $a, $priorities );
					$key_b = array_search( $b, $priorities );
					if ( $key_a < $key_b ) {
						return -1;
					} elseif ( $key_a > $key_b ) {
						return 1;
					}
				}
			}

			return 0;
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
