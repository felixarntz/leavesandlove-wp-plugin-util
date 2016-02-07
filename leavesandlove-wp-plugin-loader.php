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

		private static $plugins_queue = array();
		private static $muplugins_queue = array();

		public static function init() {
			if ( ! self::$initialized ) {
				self::$plugins = array();
				self::$basenames = array();
				self::$errors = array();

				self::_load_textdomain();

				self::$initialized = true;

				add_action( 'plugins_loaded', array( __CLASS__, '_run_plugins' ) );
				add_action( 'muplugins_loaded', array( __CLASS__, '_run_muplugins' ) );
				add_action( 'admin_notices', array( __CLASS__, '_display_error_messages' ) );
				add_action( 'network_admin_notices', array( __CLASS__, '_display_error_messages' ) );
			}
		}

		public static function load_plugin( $args, $dependencies = array() ) {
			if ( ! self::$initialized ) {
				self::init();
			}

			$args = wp_parse_args( $args, array(
				'slug'					=> '',
				'name'					=> '',
				'version'				=> '1.0.0',
				'main_file'				=> '',
				'namespace'				=> '',
				'textdomain'			=> '',
				'use_language_packs'	=> false,
			) );

			$dependencies = wp_parse_args( $dependencies, array(
				'phpversion'			=> '', // at least 5.3.0
				'wpversion'				=> '', // at least 3.5.0
				'functions'				=> array(),
				'plugins'				=> array(),
			) );

			// prevent double instantiation of plugin
			if ( isset( self::$plugins[ $args['slug'] ] ) ) {
				return false;
			}

			$running = true;

			if ( ! empty( $args['slug'] ) && ! empty( $args['name'] ) && ! empty( $args['version'] ) && ! empty( $args['main_file'] ) && ! empty( $args['namespace'] ) ) {
				$args['basename'] = plugin_basename( $args['main_file'] );

				$args['mode'] = 'plugin';
				if ( substr_count( $args['basename'], '/' ) > 1 ) {
					$args['basename'] = basename( $args['main_file'] );
					$args['mode'] = 'bundled';
					if ( 0 === strpos( wp_normalize_path( $args['main_file'] ), wp_normalize_path( get_stylesheet_directory() ) ) ) {
						$args['mode'] .= '-childtheme';
					} elseif ( 0 === strpos( wp_normalize_path( $args['main_file'] ), wp_normalize_path( get_template_directory() ) ) ) {
						$args['mode'] .= '-theme';
					} elseif ( 0 === strpos( wp_normalize_path( $args['main_file'] ), wp_normalize_path( WPMU_PLUGIN_DIR ) ) ) {
						$args['mode'] .= '-muplugin';
					} else {
						$args['mode'] .= '-plugin';
					}
				} elseif ( 0 === strpos( wp_normalize_path( $args['main_file'] ), wp_normalize_path( WPMU_PLUGIN_DIR ) ) ) {
					$args['mode'] = 'muplugin';
				}

				if ( $args['use_language_packs'] ) {
					$args['textdomain_dir'] = '';
				} else {
					if ( 0 === strpos( $args['mode'], 'bundled' ) ) {
						$args['textdomain_dir'] = dirname( $args['main_file'] ) . '/languages/';
					} elseif ( 'muplugin' === $args['mode'] ) {
						$args['textdomain_dir'] = $args['slug'] . '/languages/';
					} else {
						$args['textdomain_dir'] = dirname( $args['basename'] ) . '/languages/';
					}
				}

				if ( empty( $dependencies['phpversion'] ) || version_compare( $dependencies['phpversion'], '5.3.0' ) < 0 ) {
					$dependencies['phpversion'] = '5.3.0';
				}

				if ( empty( $dependencies['wpversion'] ) || version_compare( $dependencies['wpversion'], '3.5.0' ) < 0 ) {
					$dependencies['wpversion'] = '3.5.0';
				}

				$args['namespace'] = trim( $args['namespace'], '\\' );

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
							if ( 2 === count( $func ) ) {
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
						'slug'			=> 'php',
						'name'			=> 'PHP',
						'type'			=> 'php',
						'requirement'	=> $dependencies['phpversion'],
						'installed'		=> $check,
					);
					$running = false;
				}

				$check = self::_check_wordpress( $dependencies['wpversion'] );
				if ( $check !== true ) {
					self::$errors[ $args['slug'] ]['version_errors'][] = array(
						'slug'			=> 'wordpress',
						'name'			=> 'WordPress',
						'type'			=> 'core',
						'requirement'	=> $dependencies['wpversion'],
						'installed'		=> $check,
					);
					$running = false;
				}

				foreach ( $dependencies['plugins'] as $plugin_slug => $version ) {
					$check = self::_check_plugin( $plugin_slug, $version );
					if ( $check !== true ) {
						self::$errors[ $args['slug'] ]['version_errors'][] = array(
							'slug'				=> $plugin_slug,
							'type'				=> 'plugin',
							'requirement'		=> $version,
							'installed'			=> $check,
						);
						$running = false;
					}
				}

				if ( $running ) {
					unset( self::$errors[ $args['slug'] ] );

					self::$basenames[ $args['slug'] ] = $args['basename'];

					$classname = $args['namespace'] . '\\App';
					self::$plugins[ $args['slug'] ] = call_user_func( array( $classname, 'instance' ), $args );
					if ( 0 === strpos( $args['mode'], 'bundled' ) ) {
						self::$plugins[ $args['slug'] ]->_maybe_run();
					} elseif ( 'muplugin' === $args['mode'] ) {
						self::$muplugins_queue[] = $args['slug'];
					} else {
						self::$plugins_queue[] = $args['slug'];
					}

					if ( 'plugin' === $args['mode'] ) {
						register_activation_hook( $args['main_file'], array( __CLASS__, '_activate' ) );
						register_deactivation_hook( $args['main_file'], array( __CLASS__, '_deactivate' ) );
						register_uninstall_hook( $args['main_file'], array( __CLASS__, '_uninstall' ) );
					}
				}
			}

			return $running;
		}

		public static function get_plugin( $plugin_slug ) {
			if ( isset( self::$plugins[ $plugin_slug ] ) ) {
				return self::$plugins[ $plugin_slug ];
			}
			return null;
		}

		public static function _run_plugins() {
			foreach ( self::$plugins_queue as $slug ) {
				if ( ! isset( self::$plugins[ $slug ] ) ) {
					continue;
				}
				self::$plugins[ $slug ]->_maybe_run();
			}
			self::$plugins_queue = array();
		}

		public static function _run_muplugins() {
			foreach ( self::$muplugins_queue as $slug ) {
				if ( ! isset( self::$plugins[ $slug ] ) ) {
					continue;
				}
				self::$plugins[ $slug ]->_maybe_run();
			}
			self::$muplugins_queue = array();
		}

		public static function _display_error_messages() {
			if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
				foreach ( self::$errors as $slug => $data ) {
					echo '<div class="error">';
					echo '<h4>' . sprintf( __( 'Fatal error with plugin %s', 'lalwpplugin' ), '<em>' . $data['name'] . ':</em>' ) . '</h4>';
					echo '<p>' . __( 'Due to missing dependencies, the plugin cannot be initialized.', 'lalwpplugin' ) . '</p>';
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
							$dependency = self::_extend_dependency( $dependency );
							echo '<li>';
							if ( ! $dependency['installed'] ) {
								printf( __( '%s could not be found.', 'lalwpplugin' ), $dependency['name'] );
							} else {
								printf( __( '%1$s is outdated. You are using version %3$s, but version %2$s is required.', 'lalwpplugin' ), $dependency['name'], $dependency['requirement'], $dependency['installed'] );
							}
							if ( ! empty( $dependency['action_link'] ) ) {
								echo ' <a href="' . $dependency['action_link'] . '" class="button">' . $dependency['action_name'] . '</a>';
							}
							echo '</li>';
						}
						echo '</ul>';
						echo '<p>' . __( 'Please update the above resources.', 'lalwpplugin' ) . '</p>';
					}
					echo '</div>';
				}
			}
		}

		public static function _activate( $network_wide = false ) {
			global $wpdb;

			$slug = str_replace( 'activate_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				$plugin_class = get_class( self::$plugins[ $slug ] );
				if ( $network_wide ) {
					$installed = get_site_option( 'lalwpplugin_installed_plugins', array() );

					$global_status = true;

					if ( ! isset( $installed[ $slug ] ) || ! $installed[ $slug ] ) {
						if ( is_callable( array( $plugin_class, 'network_install' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'network_install' ) );
							if ( ! $status ) {
								$global_status = false;
							}
						}
					}

					if ( is_callable( array( $plugin_class, 'network_activate' ) ) ) {
						$status = call_user_func( array( $plugin_class, 'network_activate' ) );
					}

					$blogs = wp_get_sites();
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog['blog_id'] );

						if ( ! isset( $installed[ $slug ] ) || ! $installed[ $slug ] ) {
							if ( is_callable( array( $plugin_class, 'install' ) ) ) {
								$status = call_user_func( array( $plugin_class, 'install' ) );
								if ( ! $status ) {
									$global_status = false;
								}
							}
						}

						if ( is_callable( array( $plugin_class, 'activate' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'activate' ) );
						}
					}
					self::restore_original_blog();

					if ( ! isset( $installed[ $slug ] ) || ! $installed[ $slug ] ) {
						if ( $global_status ) {
							$installed[ $slug ] = true;
						} else {
							$installed[ $slug ] = false;
						}
						update_site_option( 'lalwpplugin_installed_plugins', $installed );
					}
				} else {
					$installed = get_option( 'lalwpplugin_installed_plugins', array() );

					$global_status = true;

					if ( ! isset( $installed[ $slug ] ) ) {
						if ( is_callable( array( $plugin_class, 'install' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'install' ) );
							if ( ! $status ) {
								$global_status = false;
							}
						}
					}

					if ( is_callable( array( $plugin_class, 'activate' ) ) ) {
						$status = call_user_func( array( $plugin_class, 'activate' ) );
					}

					if ( ! isset( $installed[ $slug ] ) || ! $installed[ $slug ] ) {
						if ( $global_status ) {
							$installed[ $slug ] = true;
						} else {
							$installed[ $slug ] = false;
						}
						update_option( 'lalwpplugin_installed_plugins', $installed );
					}
				}
			}
		}

		public static function _deactivate( $network_wide = false ) {
			$slug = str_replace( 'deactivate_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				$plugin_class = get_class( self::$plugins[ $slug ] );
				if ( $network_wide ) {
					if ( is_callable( array( $plugin_class, 'network_deactivate' ) ) ) {
						$status = call_user_func( array( $plugin_class, 'network_deactivate' ) );
					}

					$blogs = wp_get_sites();
					foreach ( $blogs as $blog ) {
						switch_to_blog( $blog['blog_id'] );

						if ( is_callable( array( $plugin_class, 'deactivate' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'deactivate' ) );
						}
					}
					self::restore_original_blog();
				} else {
					if ( is_callable( array( $plugin_class, 'deactivate' ) ) ) {
						$status = call_user_func( array( $plugin_class, 'deactivate' ) );
					}
				}
			}
		}

		public static function _uninstall() {
			$slug = str_replace( 'uninstall_', '', current_action() );
			$slug = array_search( $slug, self::$basenames );

			if ( $slug ) {
				$plugin_class = get_class( self::$plugins[ $slug ] );

				$installed = array();
				$network_wide = false;
				if ( is_multisite() ) {
					$installed = get_site_option( 'lalwpplugin_installed_plugins', array() );
					if ( isset( $installed[ $slug ] ) ) {
						$network_wide = true;
					} else {
						$installed = get_option( 'lalwpplugin_installed_plugins', array() );
					}
				} else {
					$installed = get_option( 'lalwpplugin_installed_plugins', array() );
				}

				if ( $network_wide ) {
					$global_status = true;

					if ( isset( $installed[ $slug ] ) ) {
						if ( is_callable( array( $plugin_class, 'network_uninstall' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'network_uninstall' ) );
							if ( ! $status ) {
								$global_status = false;
							}
						}

						$blogs = wp_get_sites();
						foreach ( $blogs as $blog ) {
							switch_to_blog( $blog['blog_id'] );

							if ( is_callable( array( $plugin_class, 'uninstall' ) ) ) {
								$status = call_user_func( array( $plugin_class, 'uninstall' ) );
								if ( ! $status ) {
									$global_status = false;
								}
							}
						}
						self::restore_original_blog();

						unset( $installed[ $slug ] );
						update_site_option( 'lalwpplugin_installed_plugins', $installed );
					}
				} else {
					$global_status = true;

					if ( isset( $installed[ $slug ] ) ) {
						if ( is_callable( array( $plugin_class, 'uninstall' ) ) ) {
							$status = call_user_func( array( $plugin_class, 'uninstall' ) );
							if ( ! $status ) {
								$global_status = false;
							}
						}

						unset( $installed[ $slug ] );
						update_option( 'lalwpplugin_installed_plugins', $installed );
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
			$plugin_slug = self::_make_plugin_basename( $plugin_slug );

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
				$plugin_data = LaL_WP_Plugin_Util::get_package_data( $plugin_slug, 'plugin', false );
				if ( is_array( $plugin_data ) && isset( $plugin_data['Version'] ) ) {
					if ( version_compare( $plugin_data['Version'], $version ) < 0 ) {
						return $plugin_data['Version'];
					}
				}
			}

			return true;
		}

		private static function _make_plugin_basename( $plugin_slug ) {
			if ( strpos( $plugin_slug, '.php' ) === strlen( $plugin_slug ) - 4 ) {
				$plugin_slug = substr( $plugin_slug, 0, strlen( $plugin_slug ) - 4 );
			}
			if ( strpos( $plugin_slug, '/' ) === false ) {
				$plugin_slug .= '/' . $plugin_slug;
			}
			$plugin_slug .= '.php';

			return $plugin_slug;
		}

		private static function _extend_dependency( $dependency ) {
			if ( ! isset( $dependency['name'] ) || empty( $dependency['name'] ) ) {
				$dependency['name'] = $dependency['slug'];
			}
			if ( ! isset( $dependency['action_link'] ) ) {
				$dependency['action_link'] = '';
			}
			if ( ! isset( $dependency['action_name'] ) ) {
				$dependency['action_name'] = '';
			}

			switch ( $dependency['type'] ) {
				case 'plugin':
					$api_data = LaL_WP_Plugin_Util::get_package_data( $dependency['slug'], 'plugin', true );
					if ( is_array( $api_data ) && isset( $api_data['slug'] ) ) {
						if ( isset( $api_data['name'] ) ) {
							$dependency['name'] = $api_data['name'];
						}
						$plugin_file = self::_make_plugin_basename( $dependency['slug'] );
						if ( ! $dependency['installed'] ) {
							if ( is_dir( WP_PLUGIN_DIR . '/' . $api_data['slug'] ) ) {
								$dependency['action_name'] = __( 'Activate', 'lalwpplugin' );
								if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) && current_user_can( 'activate_plugins' ) ) {
									$dependency['action_link'] = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . $plugin_file . '&plugin_status=all&paged=1' ), 'activate-plugin_' . $plugin_file );
								}
							} else {
								$dependency['action_name'] = __( 'Install', 'lalwpplugin' );
								if ( current_user_can( 'install_plugins' ) ) {
									$dependency['action_link'] = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $api_data['slug'] ), 'install-plugin_' . $api_data['slug'] );
								}
							}
						} else {
							$dependency['action_name'] = __( 'Update', 'lalwpplugin' );
							if ( isset( $api_data['version'] ) && version_compare( $dependency['requirement'], $api_data['version'] ) <= 0 && current_user_can( 'update_plugins' ) ) {
								$dependency['action_link'] = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $plugin_file ), 'upgrade-plugin_' . $plugin_file );
							}
						}
					}
					break;
				default:
					break;
			}

			return $dependency;
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

}
