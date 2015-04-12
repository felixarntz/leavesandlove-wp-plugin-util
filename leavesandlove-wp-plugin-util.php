<?php
/**
 * @package leavesandlove-wp-plugin-util
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! class_exists( 'LaL_WP_Plugin_Util' ) ) {

	class LaL_WP_Plugin_Util {

		private static $instances = null;
		private static $basenames = null;
		private static $autoload_classes = null;

		public static function init() {
			self::$instances = array();
			self::$basenames = array();
			self::$autoload_classes = array();

			self::_load_textdomain();

			if ( function_exists( 'spl_autoload_register' ) ) {
				spl_autoload_register( array( __CLASS__, '_autoload' ) );
			}
		}

		public static function get( $name, $args = array() ) {
			if ( ! isset( self::$instances[ $name ] ) ) {
				$args['slug'] = $name;
				self::$instances[ $name ] = new self( $args );
			}
			return self::$instances[ $name ];
		}

		public static function is_active( $plugin_name ) {
			return isset( self::$instances[ $plugin_name ] );
		}

		public static function format( $value, $type, $mode = 'input', $args = array() ) {
			$mode = $mode == 'output' ? 'output' : 'input';

			$formatted = $value;

			switch ( $type ) {
				case 'string':
					$formatted = esc_html( $value );
					break;
				case 'html':
					$formatted = wp_kses_post( $value );
					if ( $mode == 'input' ) {
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
						$timestamp = mysql2date( 'U', $timestamp );
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
								$format = 'Y-m-d';
							} elseif ( $type == 'time' ) {
								$format = 'H:i:s';
							} else {
								$format = 'Y-m-d H:i:s';
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

		public static function _activate( $network_wide = false ) {
			$slug = str_replace( 'activate_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				if ( $network_wide ) {
					$blogs = wp_get_sites();
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog['blog_id'] );

						$installed = get_option( 'lalwpplugin_installed_plugins', array() );

						if ( ! isset( $installed[ $slug ] ) ) {
							$status = apply_filters( $slug . '_install', true );

							if ( $status === true ) {
								$installed[ $slug ] = true;
								update_option( 'lalwpplugin_installed_plugins', $installed );
							}
						} elseif ( ! $installed[ $slug ] ) {
							$installed[ $slug ] = true;
							update_option( 'lalwpplugin_installed_plugins', $installed );
						}

						$status = apply_filters( $slug . '_activate', true );
					}
					self::restore_original_blog();
				} else {
					$installed = get_option( 'lalwpplugin_installed_plugins', array() );

					if ( ! isset( $installed[ $slug ] ) ) {
						$status = apply_filters( $slug . '_install', true );

						if ( $status === true ) {
							$installed[ $slug ] = false;
							update_option( 'lalwpplugin_installed_plugins', $installed );
						}
					}

					$status = apply_filters( $slug . '_activate', true );
				}
			}
		}

		public static function _deactivate( $network_wide = false ) {
			$slug = str_replace( 'deactivate_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				if ( $network_wide ) {
					$blogs = wp_get_sites();
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog['blog_id'] );

						$status = apply_filters( $slug . '_deactivate', true );
					}
					self::restore_original_blog();
				} else {
					$status = apply_filters( $slug . '_deactivate', true );
				}
			}
		}

		public static function _uninstall() {
			$slug = str_replace( 'uninstall_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				$installed = get_option( 'lalwpplugin_installed_plugins', array() );

				if ( isset( $installed[ $slug ] ) ) {
					$network_wide = $installed[ $slug ];

					if ( $network_wide ) {
						$blogs = wp_get_sites();
						foreach ( $blogs as $blog ) {
							switch_to_blog( $blog['blog_id'] );

							$installed = get_option( 'lalwpplugin_installed_plugins', array() );

							if ( isset( $installed[ $slug ] ) ) {
								$status = apply_filters( $slug . '_uninstall', true );

								if ( $status === true ) {
									unset( $installed[ $slug ] );
									update_option( 'lalwpplugin_installed_plugins', $installed );
								}
							}
						}
						self::restore_original_blog();
					} else {
						$status = apply_filters( $slug . '_uninstall', true );

						if ( $status === true ) {
							unset( $installed[ $slug ] );
							update_option( 'lalwpplugin_installed_plugins', $installed );
						}
					}
				}
			}
		}

		public static function register_autoload_classes( $classes = array() ) {
			if ( is_array( $classes ) && count( $classes ) > 0 && ! isset( $classes[0] ) ) {
				self::$autoload_classes = array_merge( self::$autoload_classes, $classes );
				return true;
			}

			return false;
		}

		public static function _autoload( $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				$class_name = strtolower( $class_name );

				if ( isset( self::$autoload_classes[ $class_name ] ) ) {
					require_once self::$autoload_classes[ $class_name ];
					return true;
				}
			}

			return false;
		}

		private static function _load_textdomain() {
			$domain = 'lalwpplugin';
			$locale = get_locale();
			$path = dirname( __FILE__ ) . '/languages/';
			$mofile = $domain . '-' . $locale . '.mo';

			if ( $loaded = load_textdomain( $domain, $path . $mofile ) ) {
				return $loaded;
			}

			return load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $mofile );
		}

		private static function _sort_html_attributes( $a, $b ) {
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

		public static function restore_original_blog() {
			if ( empty( $GLOBALS['_wp_switched_stack'] ) ) {
				return false;
			}

			$GLOBALS['_wp_switched_stack'] = array( $GLOBALS['_wp_switched_stack'][0] );

			return restore_current_blog();
		}

		private $args = array(
			'slug'					=> '',
			'name'					=> '',
			'version'				=> '1.0.0',
			'required_wp'			=> '3.5.0',
			'required_php'			=> '5.2.0',
			'main_file'				=> '',
			'basename'				=> '',
			'autoload_classes'		=> array(),
			'textdomain'			=> '',
			'textdomain_dir'		=> '',
		);

		private $activated = true;
		private $status = null;

		private function __construct( $args = array() ) {
			$this->args = wp_parse_args( $args, $this->args );

			if ( ! empty( $this->args['main_file'] ) ) {
				$this->args['basename'] = plugin_basename( $this->args['main_file'] );
			}

			if ( empty( $this->args['textdomain_dir'] ) && ! empty( $this->args['basename'] ) ) {
				$this->args['textdomain_dir'] = dirname( $this->args['basename'] ) . '/languages/';
			}

			if ( count( $this->args['autoload_classes'] ) > 0 ) {
				self::register_autoload_classes( $this->args['autoload_classes'] );
			}
		}

		public function __get( $field ) {
			if ( isset( $this->args[ $field ] ) ) {
				return $this->args[ $field ];
			}
			return false;
		}

		public function maybe_init( $init_function, $spl_available = true ) {

			$running = false;
			if ( $spl_available ) {
				if ( $this->do_version_check() ) {
					$running = true;
					if ( ! defined( 'WP_INSTALLING' ) || WP_INSTALLING === false ) {
						if ( ! empty( $this->args['slug'] ) && ! empty( $this->args['main_file'] ) ) {
							self::$basenames[ $this->args['slug'] ] = $this->args['basename'];
							register_activation_hook( $this->args['main_file'], array( __CLASS__, '_activate' ) );
							register_deactivation_hook( $this->args['main_file'], array( __CLASS__, '_deactivate' ) );
							register_uninstall_hook( $this->args['main_file'], array( __CLASS__, '_uninstall' ) );
						}
						add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 1 );
						add_action( 'plugins_loaded', $init_function );
					}
				} else {
					if ( is_admin() ) {
						add_action( 'admin_notices', array( $this, '_display_version_error_notice' ) );
					}
				}
			} else {
				if ( is_admin() ) {
					add_action( 'admin_notices', array( $this, '_display_spl_error_notice' ) );
				}
			}

			if ( ! $running && is_admin() ) {
				add_action( 'admin_init', array( $this, 'deactivate' ) );
			}
		}

		public function load_textdomain() {
			if ( ! empty( $this->args['textdomain'] ) && ! empty( $this->args['textdomain_dir'] ) ) {
				return load_plugin_textdomain( $this->args['textdomain'], false, $this->args['textdomain_dir'] );
			}
			return false;
		}

		public function deactivate() {
			if ( $this->activated && ! empty( $this->args['basename'] ) ) {
				$this->activated = false;

				deactivate_plugins( $this->args['basename'] );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}

		public function do_version_check() {
			global $wp_version;

			if ( $this->status === null ) {
				$this->status = 1;
				if ( ! empty( $this->args['required_wp'] ) && version_compare( $wp_version, $this->args['required_wp'] ) < 0 ) {
					$this->status -= 1;
				}
				if ( ! empty( $this->args['required_php'] ) && version_compare( phpversion(), $this->args['required_php'] ) < 0 ) {
					$this->status -= 2;
				}
			}

			if ( $this->status > 0 ) {
				return true;
			}
			return false;
		}

		public function doing_it_wrong( $function, $message, $version = '' ) {
			if ( WP_DEBUG && apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
				$version = !empty( $version ) ? sprintf( __( 'This message was added in %1$s version %2$s.', 'lalwpplugin' ), '&quot;' . $this->args['name'] . '&quot;', $version ) : '';
				trigger_error( sprintf( __( '%1$s was called <strong>incorrectly</strong>: %2$s %3$s', 'lalwpplugin' ), $function, $message, $version ) );
			}
		}

		public function deprecated_function( $function, $version, $replacement = null ) {
			if ( WP_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
				if ( $replacement === null ) {
					trigger_error( sprintf( __( '%1$s is <strong>deprecated</strong> as of %4$s version %2$s with no alternative available.', 'lalwpplugin' ), $function, $version, '', '&quot;' . $this->args['name'] . '&quot;' ) );
				} else {
					trigger_error( sprintf( __( '%1$s is <strong>deprecated</strong> as of %4$s version %2$s. Use %3$s instead!', 'lalwpplugin' ), $function, $version, $replacement, '&quot;' . $this->args['name'] . '&quot;' ) );
				}
			}
		}

		public function _display_version_error_notice() {
			global $wp_version;

			if ( $this->status !== null ) {
				echo '<div class="error">';
				echo '<p>' . sprintf( __( 'Fatal problem with plugin %s', 'lalwpplugin' ), '<strong>' . $this->args['name'] . ':</strong>' ) . '</p>';
				if ( $this->status != -1 ) {
					echo '<p>';
					printf( __( 'The plugin requires WordPress version %1$s. However, you are currently using version %2$s.', 'lalwpplugin' ), $this->args['required_wp'], $wp_version );
					echo '</p>';
				}
				if ( $this->status != 0 ) {
					echo '<p>';
					printf( __( 'The plugin requires PHP version %1$s. However, you are currently using version %2$s.', 'lalwpplugin' ), $this->args['required_php'], phpversion() );
					echo '</p>';
				}
				echo '<p>' . __( 'Please update the above resources to run it.', 'lalwpplugin' ) . '</p>';
				echo '<p>' . __( 'The plugin has been deactivated for now.', 'lalwpplugin' ) . '</p>';
				echo '</div>';
			}
		}

		public function _display_spl_error_notice() {
			echo '<div class="error">';
			echo '<p>' . sprintf( __( 'Fatal problem with plugin %s', 'lalwpplugin' ), '<strong>' . $this->args['name'] . ':</strong>' ) . '</p>';
			echo '<p>' . __( 'The PHP SPL functions can not be found. Please ask your hosting provider to enable them.', 'lalwpplugin' ) . '</p>';
			echo '<p>' . __( 'The plugin has been deactivated for now.', 'lalwpplugin' ) . '</p>';
			echo '</div>';
		}

	}

	LaL_WP_Plugin_Util::init();

}
