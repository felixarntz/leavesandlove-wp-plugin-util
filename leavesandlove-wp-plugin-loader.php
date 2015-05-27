<?php
/**
 * @package leavesandlove-wp-plugin-util
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'LaL_WP_Plugin_Loader' ) ) {

	class LaL_WP_Plugin_Loader {

		private static $initialized = false;

		private static $plugins = null;
		private static $basenames = null;
		private static $errors = null;
		private static $autoload_classes = null;

		public static function init() {
			if ( ! self::$initialized ) {
				self::$plugins = array();
				self::$basenames = array();
				self::$errors = array();
				self::$autoload_classes = array();

				self::_load_textdomain();

				if ( function_exists( 'spl_autoload_register' ) ) {
					spl_autoload_register( array( __CLASS__, '_autoload' ) );
				}

				self::$initialized = true;

				add_action( 'admin_init', array( __CLASS__, '_deactivate_invalid_plugins' ) );
				add_action( 'admin_notices', array( __CLASS__, '_display_error_messages' ) );
			}
		}

		public static function load_plugin( $args, $dependencies = array() ) {
			if ( ! did_action( 'plugins_loaded' ) ) {
				$args = wp_parse_args( $args, array(
					'slug'					=> '',
					'name'					=> '',
					'version'				=> '1.0.0',
					'main_file'				=> '',
					'namespace'				=> '',
					'textdomain'			=> '',
					'autoload_files'		=> array(),
					'autoload_classes'		=> array(),
				) );

				$dependencies = wp_parse_args( $dependencies, array(
					'phpversion'			=> '5.3.0',
					'wpversion'				=> '3.5.0',
					'functions'				=> array(),
					'plugins'				=> array(),
				) );

				$running = true;

				if ( ! empty( $args['slug'] ) && ! empty( $args['name'] ) && ! empty( $args['version'] ) && ! empty( $args['main_file'] ) && ! empty( $args['namespace'] ) ) {
					$args['basename'] = plugin_basename( $args['main_file'] );
					$args['textdomain_dir'] = dirname( $args['basename'] ) . '/languages/';
					$args['namespace'] = trim( $args['namespace'], '\\' );

					$autoload_files = $args['autoload_files'];

					if ( count( $args['autoload_classes'] ) > 0 && ! isset( $args['autoload_classes'][0] ) ) {
						self::$autoload_classes = array_merge( self::$autoload_classes, $args['autoload_classes'] );
					}

					unset( $args['autoload_files'] );
					unset( $args['autoload_classes'] );

					if ( ! in_array( 'spl_autoload_register', $dependencies['functions'] ) ) {
						$dependencies['functions'][] = 'spl_autoload_register';
					}

					self::$errors[ $args['slug'] ] = array(
						'name'				=> $args['name'],
						'function_errors'	=> array(),
						'version_errors'	=> array(),
					);

					foreach ( $dependencies['functions'] as $func ) {
						if ( ! is_callable( $func ) ) {
							$funcname = '';
							if ( is_array( $func ) ) {
								$func = array_values( $func );
								if ( count( $func ) == 2 ) {
									if ( is_object( $func[0] ) ) {
										$func[0] = get_class( $func[0] );
									}
									$funcname = implode( '::', $func );
								} else {
									$funcname = $func[0];
								}
							} else {
								$funcname = $func;
							}
							self::$errors[ $args['slug'] ]['function_errors'][] = $funcname;
							$running = false;
						}
					}

					$check = self::_check_php( $dependencies['phpversion'] );
					if ( $check !== true ) {
						self::$errors[ $args['slug'] ]['version_errors'][] = array(
							'name'			=> 'PHP',
							'requirement'	=> $dependencies['phpversion'],
							'installed'		=> $check,
						);
						$running = false;
					}

					$check = self::_check_wordpress( $dependencies['wpversion'] );
					if ( $check !== true ) {
						self::$errors[ $args['slug'] ]['version_errors'][] = array(
							'name'			=> 'WordPress',
							'requirement'	=> $dependencies['wpversion'],
							'installed'		=> $check,
						);
						$running = false;
					}

					foreach ( $dependencies['plugins'] as $plugin_slug => $version ) {
						$check = self::_check_plugin( $plugin_slug, $version );
						if ( $check !== true ) {
							self::$errors[ $args['slug'] ]['version_errors'][] = array(
								'name'			=> $plugin_slug,
								'requirement'	=> $version,
								'installed'		=> $check,
							);
							$running = false;
						}
					}

					self::$basenames[ $args['slug'] ] = $args['basename'];

					if ( $running ) {
						unset( self::$errors[ $args['slug'] ] );

						$plugin_path = plugin_dir_path( $args['main_file'] );
						foreach ( $autoload_files as $file ) {
							require_once \LaL_WP_Plugin_Util::build_path( $plugin_path, $file );
						}

						$classname = $args['namespace'] . '\\App';
						self::$plugins[ $args['slug'] ] = call_user_func( array( $classname, 'instance' ), $args );
						self::$plugins[ $args['slug'] ]->_maybe_run();

						register_activation_hook( $args['main_file'], array( __CLASS__, '_activate' ) );
						register_deactivation_hook( $args['main_file'], array( __CLASS__, '_deactivate' ) );
						register_uninstall_hook( $args['main_file'], array( __CLASS__, '_uninstall' ) );
					}
				}

				return $running;
			}

			return false;
		}

		public static function get_plugin( $plugin_slug ) {
			if ( isset( self::$plugins[ $plugin_slug ] ) ) {
				return self::$plugins[ $plugin_slug ];
			}
			return null;
		}

		public static function _deactivate_invalid_plugins() {
			if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
				$deactivate_basenames = array_diff_key( self::$basenames, self::$plugins );
				foreach ( $deactivate_basenames as $slug => $basename ) {
					unset( self::$basenames[ $slug ] );
					deactivate_plugins( $basename );
					if ( isset( $_GET['activate'] ) ) {
						unset( $_GET['activate'] );
					}
				}
			}
		}

		public static function _display_error_messages() {
			if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
				foreach ( self::$errors as $slug => $data ) {
					echo '<div class="error">';
					echo '<h4>' . sprintf( __( 'Fatal error with plugin %s', 'lalwpplugin' ), '<em>' . $data['name'] . ':</em>' ) . '</h4>';
					echo '<p>' . __( 'Due to missing dependencies, the plugin has been automatically deactivated.', 'lalwpplugin' ) . '</p>';
					echo '<hr>';
					if ( count( $data['function_errors'] ) > 0 ) {
						echo '<p>' . __( 'The following required PHP functions could not be found:', 'lalwpplugin' ) . '</p>';
						echo '<ul>';
						foreach ( $data['function_errors'] as $funcname ) {
							echo '<li>' . $funcname . '</li>';
						}
						echo '</ul>';
						echo '<p>' . __( 'There are probably some PHP extensions missing, or you might be using an outdated version of PHP. If you do not know how to fix this, please ask your hosting provider.', 'lalwpplugin' ) . '</p>';
					}
					if ( count( $data['version_errors'] ) > 0 ) {
						echo '<p>' . __( 'The following required dependencies are either inactive or outdated:', 'lalwpplugin' ) . '</p>';
						echo '<ul>';
						foreach ( $data['version_errors'] as $dependency ) {
							echo '<li>';
							if ( ! $dependency['installed'] ) {
								printf( __( '%s could not be found.', 'lalwpplugin' ), $dependency['name'] );
							} else {
								printf( __( '%1$s is outdated. You are using version %3$s, but version %2$s is required.', 'lalwpplugin' ), $dependency['name'], $dependency['requirement'], $dependency['installed'] );
							}
							echo '</li>';
						}
						echo '</ul>';
						echo '<p>' . __( 'Please install / update and activate the above resources.', 'lalwpplugin' ) . '</p>';
					}
					echo '</div>';
				}
			}
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

		public static function restore_original_blog() {
			if ( empty( $GLOBALS['_wp_switched_stack'] ) ) {
				return false;
			}

			$GLOBALS['_wp_switched_stack'] = array( $GLOBALS['_wp_switched_stack'][0] );

			return restore_current_blog();
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

		private static function _check_php( $version ) {
			if ( ! empty( $version ) ) {
				if ( version_compare( phpversion(), $version ) < 0 ) {
					return phpversion();
				}
			}

			return true;
		}

		private static function _check_wordpress( $version ) {
			global $wp_version;

			if ( ! empty( $version ) ) {
				if ( version_compare( $wp_version, $version ) < 0 ) {
					return $wp_version;
				}
			}

			return true;
		}

		private static function _check_plugin( $plugin_slug, $version ) {
			if ( strpos( $plugin_slug, '.php' ) === strlen( $plugin_slug ) - 4 ) {
				$plugin_slug = substr( $plugin_slug, 0, strlen( $plugin_slug ) - 4 );
			}
			if ( strpos( $plugin_slug, '/' ) === false ) {
				$plugin_slug .= '/' . $plugin_slug;
			}
			$plugin_slug .= '.php';

			if ( ! in_array( $plugin_slug, (array) get_option( 'active_plugins', array() ) ) ) {
				if ( ! is_multisite() ) {
					return false;
				}
				$network_plugins = get_site_option( 'active_sitewide_plugins' );
				if ( ! isset( $network_plugins[ $plugin_slug ] ) ) {
					return false;
				}
			}

			if ( ! empty( $version ) ) {
				$plugin_data = \LaL_WP_Plugin_Util::get_package_data( $plugin_slug, false, false );
				if ( is_array( $plugin_data ) && isset( $plugin_data['Version'] ) ) {
					if ( version_compare( $plugin_data['Version'], $version ) < 0 ) {
						return $plugin_data['Version'];
					}
				}
			}

			return true;
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

	}

	LaL_WP_Plugin_Loader::init();

}
