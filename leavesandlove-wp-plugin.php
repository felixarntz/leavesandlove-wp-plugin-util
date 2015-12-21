<?php
/**
 * @package leavesandlove-wp-plugin-util
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'LaL_WP_Plugin' ) ) {

	abstract class LaL_WP_Plugin {

		protected static $instances = array();

		protected static $_args = array();

		public static function instance( $args = array() ) {
			$slug = '';
			if ( isset( $args['slug'] ) ) {
				$slug = $args['slug'];
			} else {
				$slug = static::$_args['slug'];
			}

			if ( ! isset( self::$instances[ $slug ] ) ) {
				self::$instances[ $slug ] = new static( $args );
			}
			return self::$instances[ $slug ];
		}

		protected $_run_method_called = false;

		protected function __construct( $args ) {
			static::$_args = $args;
		}

		public function _maybe_run() {
			if ( ! $this->_run_method_called ) {
				$this->_run_method_called = true;
				$this->load_textdomain();
				$this->run();
			}
		}

		protected function load_textdomain() {
			if ( ! empty( static::$_args['textdomain'] ) ) {
				if ( ! empty( static::$_args['textdomain_dir'] ) ) {
					if ( 0 === strpos( $args['mode'], 'bundled' ) ) {
						$locale = apply_filters( 'plugin_locale', get_locale(), static::$_args['textdomain'] );
						return load_textdomain( static::$_args['textdomain'], static::$_args['textdomain_dir'] . static::$_args['textdomain'] . '-' . $locale . '.mo' );
					} elseif ( 'muplugin' === static::$_args['mode'] ) {
						return load_muplugin_textdomain( static::$_args['textdomain'], static::$_args['textdomain_dir'] );
					} else {
						return load_plugin_textdomain( static::$_args['textdomain'], false, static::$_args['textdomain_dir'] );
					}
				} else {
					if ( 0 === strpos( $args['mode'], 'bundled' ) ) {
						$locale = apply_filters( 'plugin_locale', get_locale(), static::$_args['textdomain'] );
						return load_textdomain( static::$_args['textdomain'], WP_LANG_DIR . '/plugins/' . static::$_args['textdomain'] . '-' . $locale . '.mo' );
					} elseif ( 'muplugin' === static::$_args['mode'] ) {
						return load_muplugin_textdomain( static::$_args['textdomain'] );
					} else {
						return load_plugin_textdomain( static::$_args['textdomain'] );
					}
				}
			}
			return false;
		}

		protected abstract function run();

		public static function get_info( $field = '' ) {
			if ( ! empty( $field ) ) {
				if ( isset( static::$_args[ $field ] ) ) {
					return static::$_args[ $field ];
				}
				return false;
			}
			return static::$_args;
		}

		public static function get_path( $path = '' ) {
			$base_path = '';
			switch ( static::$_args['mode'] ) {
				case 'bundled-childtheme':
					$base_path = get_stylesheet_directory() . str_replace( wp_normalize_path( get_stylesheet_directory() ), '', wp_normalize_path( dirname( static::$_args['main_file'] ) ) );
					break;
				case 'bundled-theme':
					$base_path = get_template_directory() . str_replace( wp_normalize_path( get_template_directory() ), '', wp_normalize_path( dirname( static::$_args['main_file'] ) ) );
					break;
				case 'muplugin':
					$base_path = plugin_dir_path( dirname( static::$_args['main_file'] ) . '/' . static::$_args['slug'] . '/composer.json' );
					break;
				case 'bundled-muplugin':
				case 'bundled-plugin':
				case 'plugin':
				default:
					$base_path = plugin_dir_path( static::$_args['main_file'] );
			}

			return \LaL_WP_Plugin_Util::build_path( $base_path, $path );
		}

		public static function get_url( $path = '' ) {
			$base_path = '';
			switch ( static::$_args['mode'] ) {
				case 'bundled-childtheme':
					$base_path = get_stylesheet_directory_uri() . str_replace( wp_normalize_path( get_stylesheet_directory() ), '', wp_normalize_path( dirname( static::$_args['main_file'] ) ) );
					break;
				case 'bundled-theme':
					$base_path = get_template_directory_uri() . str_replace( wp_normalize_path( get_template_directory() ), '', wp_normalize_path( dirname( static::$_args['main_file'] ) ) );
					break;
				case 'muplugin':
					$base_path = plugin_dir_url( dirname( static::$_args['main_file'] ) . '/' . static::$_args['slug'] . '/composer.json' );
					break;
				case 'bundled-muplugin':
				case 'bundled-plugin':
				case 'plugin':
				default:
					$base_path = plugin_dir_url( static::$_args['main_file'] );
			}

			return \LaL_WP_Plugin_Util::build_path( $base_path, $path );
		}

		public static function doing_it_wrong( $function, $message, $version ) {
			if ( WP_DEBUG && apply_filters( 'doing_it_wrong_trigger_error', true ) ) {
				$version = sprintf( __( 'This message was added in %1$s version %2$s.', 'lalwpplugin' ), '&quot;' . static::$_args['name'] . '&quot;', $version );
				trigger_error( sprintf( __( '%1$s was called <strong>incorrectly</strong>: %2$s %3$s', 'lalwpplugin' ), $function, $message, $version ) );
			}
		}

		public static function deprecated_function( $function, $version, $replacement = null ) {
			if ( WP_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
				if ( null === $replacement ) {
					trigger_error( sprintf( __( '%1$s is <strong>deprecated</strong> as of %4$s version %2$s with no alternative available.', 'lalwpplugin' ), $function, $version, '', '&quot;' . static::$_args['name'] . '&quot;' ) );
				} else {
					trigger_error( sprintf( __( '%1$s is <strong>deprecated</strong> as of %4$s version %2$s. Use %3$s instead!', 'lalwpplugin' ), $function, $version, $replacement, '&quot;' . static::$_args['name'] . '&quot;' ) );
				}
			}
		}

		public static function deprecated_action( $tag, $version, $replacement = null ) {
			if ( WP_DEBUG && apply_filters( 'deprecated_action_trigger_error', true ) ) {
				if ( null === $replacement ) {
					trigger_error( sprintf( __( 'The action %1$s is <strong>deprecated</strong> as of %4$s version %2$s with no alternative available.', 'lalwpplugin' ), $function, $version, '', '&quot;' . static::$_args['name'] . '&quot;' ) );
				} else {
					trigger_error( sprintf( __( 'The action %1$s is <strong>deprecated</strong> as of %4$s version %2$s. Use %3$s instead!', 'lalwpplugin' ), $function, $version, $replacement, '&quot;' . static::$_args['name'] . '&quot;' ) );
				}
			}
		}

		public static function deprecated_filter( $tag, $version, $replacement = null ) {
			if ( WP_DEBUG && apply_filters( 'deprecated_filter_trigger_error', true ) ) {
				if ( null === $replacement ) {
					trigger_error( sprintf( __( 'The filter %1$s is <strong>deprecated</strong> as of %4$s version %2$s with no alternative available.', 'lalwpplugin' ), $function, $version, '', '&quot;' . static::$_args['name'] . '&quot;' ) );
				} else {
					trigger_error( sprintf( __( 'The filter %1$s is <strong>deprecated</strong> as of %4$s version %2$s. Use %3$s instead!', 'lalwpplugin' ), $function, $version, $replacement, '&quot;' . static::$_args['name'] . '&quot;' ) );
				}
			}
		}

		public static function deprecated_argument( $function, $version, $message = null ) {
			if ( WP_DEBUG && apply_filters( 'deprecated_argument_trigger_error', true ) ) {
				if ( null === $message ) {
					trigger_error( sprintf( __( '%1$s was called with an argument that is <strong>deprecated</strong> as of %4$s version %2$s. %3$s', 'lalwpplugin' ), $function, $version, '', '&quot;' . static::$_args['name'] . '&quot;' ) );
				} else {
					trigger_error( sprintf( __( '%1$s was called with an argument that is <strong>deprecated</strong> as of %4$s version %2$s with no alternative available.', 'lalwpplugin' ), $function, $version, $message, '&quot;' . static::$_args['name'] . '&quot;' ) );
				}
			}
		}

	}

}
