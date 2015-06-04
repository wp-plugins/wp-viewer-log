<?php
/*
Plugin Name: WP Viewer Log
Plugin URI: http://wordpress.org/extend/plugins/wp-viewer-log/
Description: Lets see how many errors have had in the present day through a widget, configure your wp-config.php and see the file log.
Author: Sergio P.A. ( 23r9i0 )
Version: 2.0.1
Author URI: http://dsergio.com/
*/
/*  Copyright 2013  Sergio Prieto Alvarez  ( email : info@dsergio.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General License as published by
    the Free Software Foundation; either version 2 of the License, or
    ( at your option ) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General License for more details.

    You should have received a copy of the GNU General License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( !defined( 'ABSPATH' ) )
	exit;

add_action( 'plugins_loaded', array( 'WP_VIEWER_LOG', 'get_instance' ) );
register_activation_hook( __FILE__, array( 'WP_VIEWER_LOG', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'WP_VIEWER_LOG', 'deactivation' ) );

final class WP_VIEWER_LOG {

	const WPVL_VERSION = '2.0.1';

	private static $instance = null;

	private static $options = array();

	private static $default_options = array(
		'wpvl_version'			=> self::WPVL_VERSION,
		'wpvl_enable_widget'	=> '1',
		'wpvl_enable_admin_bar'	=> '1',
		'wpvl_target_admin_bar' => '1',
		'wpvl_show_wp_config'	=> '0',
		'wpvl_custom_code'		=> '1',
		'wpvl_text_wp_config'	=> ''
	);

	private static $file_log;

	private static $current_log;

	private static $file_config;

	private static $file_config_bak;

	private static $current_config;

	private static $text_config;

	private static $page_hook;

	private static $subpage_hook;

	private static $admin_color;

	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	public function __clone() {}

	private function __construct() {
		if ( ! current_user_can( 'activate_plugins' ) )
			return;

		self::$options = get_option( 'wpvl-options' );
		self::$file_log = ini_get( 'error_log' );
		self::$file_config = ABSPATH . 'wp-config.php';
		self::$file_config_bak = ABSPATH . 'wp-config-backup.php';
		self::$text_config = array(
			"define( 'WP_DEBUG', true );\n",
			"if ( WP_DEBUG ) {\n",
			"\tdefine( 'WP_DEBUG_LOG', true );\n",
			"\tdefine( 'WP_DEBUG_DISPLAY', false );\n",
			"\t@ini_set( 'display_errors',0 );\n",
			"}"
		);
		self::$admin_color = get_user_option( 'admin_color', get_current_user_id() );

		load_plugin_textdomain( 'wpvllang', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$this->clear_log();
		$this->rewrite_wp_config();

		if ( file_exists( self::$file_log ) && is_readable( self::$file_log ) ) {
			self::$current_log = file( self::$file_log, FILE_IGNORE_NEW_LINES );
			self::$current_log = array_reverse( self::$current_log, true );
		} else {
				set_transient( 'wpvl_log_error', 'log_error', 60 );
			}

		if ( file_exists( self::$file_config ) && is_readable( self::$file_config ) ) {
			self::$current_config = file( self::$file_config );
		} else {
			set_transient( 'wpvl_config_error', 'config_error', 60 );
		}

		add_action( 'init', array( $this, 'init' ) );

		if ( version_compare( PHP_VERSION, '5.4.0', '<') ) {
			add_filter( 'site_transient_update_plugins', array( $this, 'delete_plugin_update' ) );
			add_filter( 'plugin_row_meta', array( $this, 'update_info_plugin' ), 10, 2 );
		}
	}

	public function delete_plugin_update( $data ) {
		unset( $data->response[ plugin_basename( __FILE__ ) ] );
		return $data;
	}

	public function update_info_plugin( $plugin_meta, $plugin_file ) {
		if ( plugin_basename( __FILE__ ) !== $plugin_file )
			return $plugin_meta;

			$plugin_meta[] = sprintf( '<strong>%s</strong>', __( 'Your PHP Version is not compatible with future updates, Upgrade to 5.4 or later', 'wabelang' ) );

			return $plugin_meta;
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menus' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_item' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'all_admin_notices', array( $this, 'add_notices' ) );

		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
	}

	public function plugin_action_links( $links, $file ) {
		$options_link = '<a href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options', 'wpvllang' ) . '</a>';

    	if ( $file == plugin_basename( __FILE__ ) )
      		array_unshift( $links, $options_link );

    	return $links;
	}

	public function activation() {
		global $wp_version;

		if ( version_compare( $wp_version, '3.3', '<' ) ) {
			wp_die( __( 'This plugin requires WordPress 3.3 or greater.', 'wpvllang' ), '', array( 'back_link' => true ) );
			exit;
		}

		if ( self::$options )
			$update = array_merge( self::$default_options, self::$options );

		if ( ! self::$options ) {
			add_option( 'wpvl-options', self::$default_options );

		} elseif ( ! isset( self::$options['wpvl_enable_admin_bar'] ) ) {
			update_option( 'wpvl-options', $update );

		} elseif ( get_option( 'wpvl-version' ) ) {
			delete_option( 'wpvl-version' );
			update_option( 'wpvl-options', $update );
		}

		unset( $update );
	}

	public function deactivation() {
		// Restore Original wp-config.php
		if ( file_exists( self::$file_config_bak ) )
			rename( self::$file_config_bak, self::$file_config );

		// Delete file log
		if( file_exists( self::$file_log ) )
			unlink( self::$file_log );

		delete_option( 'wpvl-options' );
	}

	public function add_dashboard_widget() {
		if ( isset( self::$options['wpvl_enable_widget'] ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
			add_action( 'wp_network_dashboard_setup', array( $this, 'dashboard_widget' ) );
		}
	}

	public function admin_enqueue() {
		//wp_register_script( 'wpvl-scripts', plugins_url( 'assets/javascript/dev/jquery.wpvl.js', __FILE__ ), array( 'jquery' ), self::WPVL_VERSION );
		wp_register_script( 'wpvl-scripts', plugins_url( 'assets/javascript/jquery.wpvl.min.js', __FILE__ ), array( 'jquery' ), self::WPVL_VERSION );
		wp_register_style( 'wpvl-styles', plugins_url( 'assets/css/wpvl-styles.css', __FILE__ ), '', self::WPVL_VERSION );
		wp_enqueue_script( 'wpvl-scripts' );
		wp_enqueue_style( 'wpvl-styles' );
	}

	public function add_notices() {
    	$screen = get_current_screen();
    	if ( $screen->id != self::$page_hook || $screen->id != self::$subpage_hook )
			return;

		$html = '';
		if ( get_transient( 'wpvl_config_error' ) ) {
			$html .= '<div class="error"><p>';
			$html .= __( 'Error to load wp-config.php.','wpvllang' );
			$html .= '</p></div>';

			delete_transient( 'wpvl_config_error' );
		}
		if ( get_transient( 'wpvl_log_error' ) ) {
			$html .= '<div class="error"><p>';
			$html .= __( 'Error to load log file.','wpvllang' );
   			$html .= '</p></div>';

			delete_transient( 'wpvl_log_error' );
		}

		echo $html;
	}

	public function admin_bar_item( $admin_bar ) {
		if ( isset( self::$options['wpvl_enable_admin_bar'] ) && self::$options['wpvl_enable_admin_bar'] != '1' ) {
			$frontend = ( self::$options['wpvl_enable_admin_bar'] === '3' ) ? true : false;
			$num = $this->count_errors();
			$text = ( $num > 1 ) ? __( 'Errors', 'wpvllang' ) : __( 'Error', 'wpvllang' );
			$title = sprintf( '<span style="color:red" class="count-%1$s"> %1$s %2$s</span>', $num, $text );
			$target = ( self::$options['wpvl_target_admin_bar'] === '1' ) ? '_blank' : '';
			if ( $num > 0 && is_admin() || $frontend ) {
				$admin_bar->add_node( array(
					'id'		=>	'wpvl-node',
					'title'		=>	$title,
					'href'		=>	admin_url( 'admin.php?page=wp-viewer-log' ),
					'meta'		=>	array(
						'title'		=>	__( 'View Log', 'wpvllang' ),
						'target'	=>	$target
						)
					)
				);
			}
		}
	}

	private function count_errors() {
		$count_errors = 0;
		if ( is_array( self::$current_log ) && ! empty( self::$current_log ) ) {
			$i = 1;
			foreach ( self::$current_log as $line ) {
				if ( strpos( $line, date( 'd-M-Y' ) ) !== false ) {
					$errors[] = $i;
					$i++;
				}
			}
			if ( isset( $errors ) )
				$count_errors = count( $errors );
		}
		return $count_errors;
	}

	private function clear_log() {
		if ( isset( $_POST['clear-log'] ) ) {
			$clear = fopen( self::$file_log, 'w' );
			fclose( $clear );
			// Update self::$file_log
			self::$file_log = ini_get( 'error_log' );
			add_action( 'admin_notices', array( $this, 'add_notice_clear_log' ) );
		}
	}

	public function add_notice_clear_log() {
		echo '<div class="updated"><p>' . __( 'I delete the contents of log.','wpvllang' ) . '</p></div>';
	}

	private function rewrite_wp_config() {
		$text_comment = "\n// " . __( 'Added from the plugin WP Viewer Log', 'wpvllang' ) . "\n";
		$text_custom_comment = "\n// " . __( 'Added from the plugin WP Viewer Log with custom code', 'wpvllang' ) . "\n";
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wp-viewer-log-options' &&
			 isset( $_GET['settings-updated'] ) && self::$options['overwrite'] === self::$options['wpvl_custom_code'] ) {

			switch ( self::$options['wpvl_custom_code'] ) {
				case '1': // Reset
					if ( file_exists( self::$file_config_bak ) )
						rename( self::$file_config_bak, self::$file_config );
					break;
				case '2': // Add Default Code
					if ( file_exists( self::$file_config_bak ) )
						rename( self::$file_config_bak, self::$file_config );

					array_unshift( self::$text_config, $text_comment );
					array_push( self::$text_config, $text_comment );
					$this->write_config( self::$text_config );
					break;
				case '3': // Add Custom Code
					if ( file_exists( self::$file_config_bak ) )
						rename( self::$file_config_bak, self::$file_config );

					self::$text_config = explode( '\n', self::$options['wpvl_text_wp_config'] );
					array_unshift( self::$text_config, $text_custom_comment );
					array_push( self::$text_config, $text_custom_comment );
					$this->write_config( self::$text_config );
					break;
				default:
					break;
			}
		}
	}
	private function write_config( $wpvl_text = array() ) {
		if ( ! file_exists( self::$file_config_bak ) ) {
			copy( self::$file_config, self::$file_config_bak );
			$conf = file( self::$file_config );
			$i = 0;
			foreach ( $conf as $line => $text ) {
				$text = preg_replace( '([\W]+)', '', $text );
				if ( stripos( $text, 'definewp_debug' ) !== false ) {
					$nline = $i;
					$conf[$line] = '';
					break;
				}
				++$i;
			}
			if ( isset( $nline ) ) {
				array_splice( $conf, $nline, 0, $wpvl_text );
				$new_conf = fopen( self::$file_config, 'w' );
				foreach ( $conf as $line )
					fwrite( $new_conf, $line );
				fclose( $new_conf );
			} else {
				// Define error and update options to check in function setting_custom_code() to disable custom code
				if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === 'wp-viewer-log-options' ) {
						add_action( 'admin_notices', array( $this, 'add_notice_error_conf' ) );
						$reset = self::$options;
						$reset['wpvl_custom_code_error'] = '1';
						update_option( 'wpvl-options', $reset );
						unset($reset);
				}
			}
		}
	}

	public function add_notice_error_conf() {
		echo '<div class="error"><p>' . __( 'For security has not been edited wp-config.php file, edit it manually.', 'wpvllang' ) . '</p></div>';
	}

	public function dashboard_widget() {
		wp_add_dashboard_widget( 'wpvl_dashboard_widget', 'WP Viewer Log', array( $this, 'render_widget' ) );
	}

	public function render_widget() {
		$html = '<div class="wpvl-html wpvl-widget theme-' . self::$admin_color . '"><pre>';
		if ( empty( self::$current_log ) ) {
			$html .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
			$html .= '</pre></div>';
			$html .= '<div class="wpvl-widget-buttons">';
			$html .= '<p class="submit options">';
			$html .= '<a class="button" href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a>';
			$html .= '</p>';
		} else {
			$regex = '/(\]\\s(.*?)\:)/'; // capture name error
			$html .= '<table width="100%" border="0" cellspacing="0" cellpadding="0">';
			$html .= '<tbody><tr>';
			$html .= '<th class="left-widget" scope="col">' . __( 'Type','wpvllang' ) . '</th><th class="right-widget" scope="col">' . __( 'No.', 'wpvllang' ) . '</th>';
			$html .= '</tr>';
			for ( $i=0; $i < count( self::$current_log ); $i++ ) {
				if ( strpos( self::$current_log[$i], date( 'd-M-Y' ) ) !== false ) {
					preg_match_all( $regex, self::$current_log[$i], $lines[$i] );
					$errors[] = $lines[$i][2][0];
				}
			}
			foreach ( array_count_values( $errors ) as $error => $num ) {
				$html .=  '<tr><td class="left-widget">' . $error . '</td><td class="right-widget">' . $num . '</td></tr>';
			}
			$html .= '</tbody></table>';
			$html .= '</pre></div>';
			$html .= '<div class="wpvl-widget-buttons">';
			$html .= '<p class="submit options">';
			$html .= '<a class="button" href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a>';
			$html .= '</p>';
			$html .= '<p class="submit view">';
			$html .= '<a class="button-primary" href="' . admin_url( 'admin.php?page=wp-viewer-log' ) . '">' . __( 'View Full Log', 'wpvllang' ) . '</a>';
			$html .= '</p>';
		}

		$html .= '<div class="clear"></div>';
		// end div.wpvl-widget
		$html .= '</div>';

		echo $html;
	}

	public function register_settings() {
		register_setting( 'wpvl-register', 'wpvl-options', array( $this, 'validation' ) );
		add_settings_section( 'wpvl_general', '', '__return_false', 'wpvl_settings' );
		add_settings_field( 'wpvl_enable_widget', __( 'Dashboard widget:', 'wpvllang' ),
			array( $this, 'setting_enable_widget' ), 'wpvl_settings', 'wpvl_general',
			array( 'label_for' => 'wpvl-enable-widget' ) );
		add_settings_field( 'wpvl_enable_admin_bar', __( 'Link on Admin Bar:', 'wpvllang' ),
			array( $this, 'setting_enable_admin_bar' ), 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_target_admin_bar', __( 'Define Target:', 'wpvllang' ),
			array( $this, 'setting_target_admin_bar' ), 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_header',  __( '<strong>Advanced Settings</strong>','wpvllang' ),
			'__return_false', 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_show_wp_config', __( 'View wp-config.php:', 'wpvllang' ),
			array( $this, 'setting_show_wp_config' ), 'wpvl_settings', 'wpvl_general',
			array( 'label_for' => 'wpvl-show-wp-config' ) );
		add_settings_field( 'wpvl_custom_code', __( 'Add Code:', 'wpvllang' ),
			array( $this, 'setting_custom_code' ), 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_text_wp_config', __( 'Your custom code:', 'wpvllang' ),
			array( $this, 'setting_text_wp_config' ), 'wpvl_settings', 'wpvl_general',
			array( 'label_for' => 'wpvl-text-wp-config' ) );

	}

	public function validation( $input ) {
		$output = array();
		// Define internal option for disable overwrite wp-config.php file
		$output['overwrite'] = '0';

		foreach ( $input as $key => $value ) {
			if ( isset( $input[$key] ) )
				$output[$key] = $input[$key];
		}
		 // remove custom code in textarea
		if ( $output['wpvl_custom_code'] === '1' || $output['wpvl_custom_code'] === '2' )
			$output['wpvl_text_wp_config'] = '';

		if ( $output['overwrite'] != $output['wpvl_custom_code'] )
			$output['overwrite'] = $output['wpvl_custom_code'];

		return apply_filters( 'validation', $output, $input );
	}

	public function admin_menus() {
		$num_errors = $this->count_errors();

		$menu_title = sprintf( 'WPVL <span class="update-plugins count-%1$s wpvl-bubble"><span class="update-count">%1$s</span></span>', $num_errors );

		add_menu_page( 'WP Viewer Log', $menu_title, 'activate_plugins', 'wp-viewer-log', array( $this, 'page_log' ), plugins_url( 'assets/images/a-error.png', __FILE__ ) );
    	self::$page_hook = add_submenu_page('wp-viewer-log', __( 'WPVL Log', 'wpvllang' ), __( 'Log', 'wpvllang' ), 'activate_plugins', 'wp-viewer-log' );
    	self::$subpage_hook = add_submenu_page('wp-viewer-log', __( 'WPVL Options', 'wpvllang' ), __( 'Options', 'wpvllang' ), 'activate_plugins', 'wp-viewer-log-options', array( $this, 'page_options' ) );
		add_action( 'load-' . self::$subpage_hook, array( $this, 'add_help_tab' ) );
	}

	public function page_log() {
?>
		<div class="wrap">
			<?php screen_icon( 'wp-viewer-log' ); ?>
			<h2><?php printf( __( 'WP Viewer Log - Version: %s', 'wpvllang' ), self::WPVL_VERSION ); ?></h2>
			<div id="tab-view-log">
<?php
				$html = '<div class="wpvl-html theme-' . self::$admin_color . '"><pre>';
				if ( is_array( self::$current_log ) && !empty( self::$current_log ) ) {
					$html .= '<table width="100%"  border="0" cellspacing="0" cellpadding="0">';
					$html .= '<tbody><tr><td class="gutter">';
					for ( $i=1; $i <= count( self::$current_log ); $i++ ) {
						$html .= '<div>' . $i . '</div>';
					}
					$html .= '</td><td class="code">';
					foreach ( self::$current_log as $line => $string ) {
						// eliminating occasional line breaks in the string
						$string = str_replace( array( "\r\n", "\r", "\n"), '', $string );
						$html .= '<div>' . esc_html( $string ) . '</div>';
					}
					$html .= '</td></tr></tbody></table>';
				} else {
					$html .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
				}
				$html .= '</pre></div>';

				echo $html;
?>
				<form method="post" action="">
					<?php submit_button( __( 'Clear Log', 'wpvllang' ), 'delete', 'clear-log' ); ?>
				</form>
			</div><!-- #tab-view-log -->
		</div><!-- .wrap -->
<?php
	}

	public function page_options() {
?>
		<div class="wrap">
			<?php screen_icon( 'wp-viewer-log' ); ?>
			<h2><?php printf( __( 'WP Viewer Log - Version: %s', 'wpvllang' ), self::WPVL_VERSION ); ?></h2>
<?php
		if ( isset( $_GET['settings-updated'] ) )
    		echo '<div class="updated fade"><p>' . __( 'Settings saved.' ) . '</p></div>';

		if ( isset( self::$options['wpvl_show_wp_config'] ) && self::$options['wpvl_show_wp_config'] === '1' ) :
?>
			<div id="wpvl-tabs">
			<h3 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-active" href="#tab-options"><?php _e( 'Plugin Options', 'wpvllang' ); ?></a>
				<a class="nav-tab" href="#tab-view-wp-config"><?php _e( 'Current WP Config File', 'wpvllang' ); ?></a>
<?php
		else :
?>
			<div>
			<h3><?php _e( 'Plugin Options', 'wpvllang' );
		endif;
?>
		</h3>
		<div id="tab-options" class="wpvl-content">
			<form method="post" action="options.php">
<?php
			settings_fields( 'wpvl-register' );
			do_settings_sections( 'wpvl_settings' );
			submit_button( null, 'primary', 'submit' );
?>
			</form>
		</div>
		<!-- #tab-options -->
<?php
		if ( isset( self::$options['wpvl_show_wp_config'] ) && self::$options['wpvl_show_wp_config'] === '1' ) :
?>
			<div id="tab-view-wp-config" class="wpvl-content">
<?php
				if ( is_array( self::$current_config ) ) {
					$html = '<div class="wpvl-html conf theme-' . self::$admin_color . '"><pre>';
					$html .= '<table width="100%"  border="0" cellspacing="0" cellpadding="0">';
					$html .= '<tbody><tr><td class="gutter">';
					for ( $i=1; $i <= count( self::$current_config ); $i++ ) {
						$html .= '<div>' . $i . '</div>';
					}
					$html .= '</td><td class="code">';
					foreach ( self::$current_config as $line => $string ) {
						$html .= '<div>' . esc_html( $string ) . '</div>';
					}
					$html .= '</td></tr></tbody></table></pre></div>';

					echo $html;
				}
?>
			</div>
<?php
		endif;
?>
	</div>
	<!-- #wpvl-tabs -->
</div>
<!-- .wrap -->
<?php
	}

	public function add_help_tab() {
    	$screen = get_current_screen();
    	if ( $screen->id != self::$subpage_hook )
			return;

		$screen->add_help_tab( array(
			'id' => 'wpvl-help-one',
			'title' => __( 'Info', 'wpvllang' ),
			'content' => '',
			'callback' => array( $this, 'tab_info' )
		) );
		$screen->add_help_tab( array(
			'id' => 'wpvl-help-two',
			'title' => __( 'Important', 'wpvllang' ),
			'content' => '',
			'callback' => array( $this, 'tab_important' )
		) );
	}

	public function tab_info() {
?>
		<p><?php _e( '- You can add a widget on the desktop, by default is enabled.', 'wpvllang' ); ?></p>
		<p><?php _e( '- If you activate the admin bar link will be displayed only when there are errors and select target to open link.', 'wpvllang' ); ?></p>
		<p><?php _e( '- Edit the wp-config.php file to configure the WP_DEBUG.', 'wpvllang' ); ?></p>
		<p><?php _e( '- The default code is:', 'wpvllang'); ?></p>
		<div class="wpvl-html theme-<?php echo self::$admin_color; ?>">
			<pre>
<?php
				echo "\n// " . __( 'Added from the plugin WP Viewer Log', 'wpvllang' ) . "\n";
				echo $line = implode( '', self::$text_config );
				echo "\n// " . __( 'Added from the plugin WP Viewer Log', 'wpvllang' ) . "\n";
?>
			</pre>
		</div>
<?php
    }

	public function tab_important() {
?>
		<p><?php _e( '- If you manually add code after using the plugin were eliminated because they always used the copy for editing.', 'wpvllang'); ?></p>
		<p><?php _e( '- If you disable the custom code file wp-config.php will be restored to its original state before activating the plugin.', 'wpvllang'); ?></p>
		<p><?php _e( '- If you configure your wp-config.php without the plugin, the widget as the page log work.', 'wpvllang'); ?></p>
<?php
    }

	public function setting_enable_widget() {
		$checked = ( isset( self::$options['wpvl_enable_widget'] ) ) ? self::$options['wpvl_enable_widget'] : 0;
?>
		<input type="checkbox" id="wpvl-enable-widget" name="wpvl-options[wpvl_enable_widget]" value="1" <?php checked( '1', $checked, true ); ?>/>
<?php
    }

	public function setting_enable_admin_bar() {
?>
		<p>
			<label>
				<input id="disable-admin-bar" class="wpvl-checked disabled" type="radio" value="1" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '1', self::$options['wpvl_enable_admin_bar'], true ); ?>/>
				<span><?php _e( 'Disabled', 'wpvllang' ); ?></span>
			</label>
		</p>
		<p>
			<label>
				<input id="default-admin-bar" class="wpvl-checked" type="radio" value="2" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '2', self::$options['wpvl_enable_admin_bar'], true ); ?>/>
				<span><?php _e( 'Enable', 'wpvllang' ); ?></span>
			</label>
		</p>
		<p>
			<label>
				<input id="full-admin-bar" class="wpvl-checked" type="radio" value="3" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '3', self::$options['wpvl_enable_admin_bar'], true ); ?>/>
				<span><?php _e( 'Also enabled on Front End', 'wpvllang' ); ?></span>
			</label>
		</p>
<?php
	}

	public function setting_target_admin_bar() {
?>
		<p>
			<label>
				<input id="blank-target-admin-bar" type="radio" value="1" name="wpvl-options[wpvl_target_admin_bar]" <?php checked( '1', self::$options['wpvl_target_admin_bar'], true ); ?>/>
				<span>_blank</span>
			</label>
		</p>
		<p>
			<label>
				<input id="self-target-admin-bar" type="radio" value="2" name="wpvl-options[wpvl_target_admin_bar]" <?php checked( '2', self::$options['wpvl_target_admin_bar'], true ); ?>/>
				<span>_self</span>
			</label>
		</p>
<?php
	}

	public function setting_show_wp_config() {
		$checked = ( isset( self::$options['wpvl_show_wp_config'] ) ) ? self::$options['wpvl_show_wp_config'] : 0;
?>
		<input type="checkbox" id="wpvl-show-wp-config" name="wpvl-options[wpvl_show_wp_config]" value="1" <?php checked( '1', $checked, true ); ?>/>
<?php
	}

	public function setting_custom_code() {
		// Restore to disabled after check error write wp-config.php
		$reset = get_option( 'wpvl-options' );
		if ( isset( $reset['wpvl_custom_code_error'] ) )
			self::$options['wpvl_custom_code'] = 1;
		unset($reset);
?>
		<p>
			<label>
				<input id="disable-code" class="wpvl-checked disabled" type="radio" value="1" name="wpvl-options[wpvl_custom_code]" <?php checked( '1', self::$options['wpvl_custom_code'], true ); ?>/>
				<span><?php _e( 'Disabled', 'wpvllang' ); ?></span>
			</label>
		</p>
		<p>
			<label>
				<input id="default-code" class="wpvl-checked disabled" type="radio" value="2" name="wpvl-options[wpvl_custom_code]" <?php checked( '2', self::$options['wpvl_custom_code'], true ); ?>/>
				<span><?php _e( 'Enabled', 'wpvllang' ); ?></span>
			</label>
		</p>
		<p>
			<label>
				<input id="custom-code" class="wpvl-checked" type="radio" value="3" name="wpvl-options[wpvl_custom_code]" <?php checked( '3', self::$options['wpvl_custom_code'], true ); ?>/>
				<span><?php _e( 'Custom Code', 'wpvllang' ); ?></span>
			</label>
		</p>
<?php
	}

	public function setting_text_wp_config() {
?>
		<textarea cols="60" rows="10" id="wpvl-text-wp-config" name="wpvl-options[wpvl_text_wp_config]"><?php echo esc_textarea( self::$options['wpvl_text_wp_config'] ); ?></textarea>
<?php
	}
}
?>
