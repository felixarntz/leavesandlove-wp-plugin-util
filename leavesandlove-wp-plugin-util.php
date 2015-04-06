<?php
/**
 * @package leavesandlove-wp-plugin-util
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! class_exists( 'LaL_WP_Plugin_Util' ) ) {

	class LaL_WP_Plugin_Util {

		private static $instances = null;
		private static $autoload_paths = null;

		public static function init() {
			self::$instances = array();
			self::$autoload_paths = array_fill( 0, 10, array() );

			self::_load_textdomain();

			if ( function_exists( 'spl_autoload_register' ) ) {
				spl_autoload_register( array( __CLASS__, '_autoload' ), true, true );
			}
		}

		public static function get( $name, $args = array() ) {
			if ( ! isset( self::$instances[ $name ] ) ) {
				self::$instances[ $name ] = new self( $args );
			}
			return self::$instances[ $name ];
		}

		public static function _autoload( $class_name ) {
			$parts = explode( '\\', $class_name );

			$class_name = array_pop( $parts );
			$namespace = implode( '\\', $parts );
			$count = count( $parts );

			while ( $count > 0 ) {
				if ( isset( self::$autoload_paths[ $count - 1 ][ $namespace ] ) ) {
					$file = self::$autoload_paths[ $count - 1 ][ $namespace ] . '/' . $class_name . '.php';
					if ( file_exists( $file ) ) {
						require_once $file;
						return true;
					}
				}

				$class_name = array_pop( $parts ) . '/' . $class_name;
				$namespace = implode( '\\', $parts );
				$count = count( $parts );
			}

			return false;
		}

		public static function register_autoload_namespace( $namespace, $path ) {
			$parts = explode( '\\', $namespace );
			$count = count( $parts );

			if ( $count > 10 || $count < 1 ) {
				return false;
			}

			self::$autoload_paths[ $count - 1 ][ $namespace ] = untrailingslashit( $path );
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

		private $args = array(
			'name'					=> '',
			'version'				=> '1.0.0',
			'required_wp'			=> '3.5.0',
			'required_php'			=> '5.2.0',
			'main_file'				=> '',
			'basename'				=> '',
			'autoload_namespace'	=> '',
			'autoload_path'			=> '',
			'textdomain'			=> '',
			'textdomain_dir'		=> '',
		);

		private $status = null;

		private function __construct( $args = array() ) {
			$this->args = wp_parse_args( $args, $this->args );

			if ( empty( $this->args['basename'] ) && ! empty( $this->args['main_file'] ) ) {
				$this->args['basename'] = plugin_basename( $this->args['main_file'] );
			}

			if ( empty( $this->args['textdomain_dir'] ) && ! empty( $this->args['basename'] ) ) {
				$this->args['textdomain_dir'] = dirname( $this->args['basename'] ) . '/languages/';
			}

			if ( ! empty( $this->args['autoload_namespace'] ) && ! empty( $this->args['autoload_path'] ) ) {
				self::register_autoload_namespace( $this->args['autoload_namespace'], $this->args['autoload_path'] );
			}
		}

		public function __get( $field ) {
			if ( isset( $this->args[ $field ] ) ) {
				return $this->args[ $field ];
			}
			return false;
		}

		public function load_textdomain() {
			if ( ! empty( $this->args['textdomain'] ) && ! empty( $this->args['textdomain_dir'] ) ) {
				return load_plugin_textdomain( $this->args['textdomain'], false, $this->args['textdomain_dir'] );
			}
			return false;
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

				if ( $this->status < 1 ) {
					add_action( 'admin_notices', array( $this, '_display_version_error_notice' ) );
				}
			}

			if ( $this->status > 0 ) {
				return true;
			}
			return false;
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
				echo '</div>';
			}
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

	}

	LaL_WP_Plugin_Util::init();

}
