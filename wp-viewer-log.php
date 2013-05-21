<?php
/*
Plugin Name: WP Viewer Log
Plugin URI: http://wordpress.org/extend/plugins/wp-viewer-log/
Description: Lets see how many errors have had in the present day through a widget, configure your wp-config.php and see the file log to a maximum of 100 lines.
Author: Sergio P.A. ( 23r9i0 )
Version: 1.0
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
if( !class_exists( 'WP_VIEWER_LOG' ) ): 
class WP_VIEWER_LOG {
	const wpvl_version = '1.0';
	private
		$total_errors,
		$wpvl_log_errors,
		$conf_original,
		$conf_backup,
		$wpvl_options,
		$wpvl_options_defaults = array(
			'wpvl_enable_widget'		=>	'1',
			'wpvl_enable_admin_bar'		=>	'1',
			'wpvl_show_wp_config'		=>	'0',
			'wpvl_custom_code'			=>	'1',
			'wpvl_text_wp_config'		=>	''
		);
	function __construct(){
		add_action( 'init', array( $this, 'wpvl_init' ) );
		add_action( 'admin_init', array( $this, 'wpvl_enable_widget' ) );
		add_action( 'admin_init', array( $this, 'wpvl_admin_options' ) );
		add_action( 'admin_init', array( $this, 'wpvl_write_wp_config' ) );
		add_action( 'admin_init', array( $this, 'wpvl_clear_log' ) );
		add_action( 'admin_init', array( $this, 'count_bubble' ), 99 );
		add_action( 'admin_menu', array( $this, 'wpvl_page_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'wpvl_add_admin_bar_item' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'wpvl_page_scripts' ) );
		add_filter( 'plugin_action_links', array( $this, 'wpvl_plugin_action_links' ), 10, 2 );
		$this->wpvl_options = get_option( 'wpvl-options' );
		$this->wpvl_log_errors = ini_get('error_log');
		if ( !defined( 'ABSPATH' ) )
			define( 'ABSPATH', '../' );
		$this->conf_backup = ABSPATH . 'wp-config-backup.php';
		$this->conf_original = ABSPATH . 'wp-config.php';
		$this->total_errors = $this->wpvl_read_file( 'bubble', false );
		register_activation_hook( __FILE__, array( $this, 'wpvl_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'wpvl_deactivate' ) );
	}
	function wpvl_init(){
		global $wp_version;
		if ( version_compare( $wp_version, '3.3', '< ' ) )
			wp_die( __( 'This plugin requires WordPress 3.3 or greater.', 'wpvllang' ) );
  		load_plugin_textdomain( 'wpvllang', false, dirname( plugin_basename( __FILE__ ) ) . '/include/languages/' );
		$this->wpvl_saved_options();		
	}
	function wpvl_activate(){	
		$this->wpvl_saved_options();
	}
	function wpvl_deactivate(){
		if( file_exists( $this->conf_backup ) )
			rename( $this->conf_backup, $this->conf_original ); // Restore Original wp-config.php
		if( substr( $this->wpvl_log_errors, -9 ) === 'debug.log' )
			@unlink( $this->wpvl_log_errors ); // Delete debug.log file
		delete_option( 'wpvl-options' );
	}
	function wpvl_saved_options(){
		if( !isset( $this->wpvl_options['wpvl_enable_admin_bar'] ) ){
		// update plugin
			foreach( $this->wpvl_options as $option => $value ){
				$this->wpvl_options_defaults[$option] = $this->wpvl_options[$option];
			}
			return update_option( 'wpvl-options', $this->wpvl_options_defaults );
		} else {
			return add_option( 'wpvl-options', $this->wpvl_options_defaults );
		}
	}
	function wpvl_plugin_action_links( $links, $file ){
    	if ( $file == plugin_basename( __FILE__ ) ){
      		$setting_link = '<a href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options', 'wpvllang' ) . '</a>';
	  		array_unshift( $links, $setting_link );
		}
    	return $links;
	}
	function wpvl_admin_options(){
		register_setting( 'wpvl-register', 'wpvl-options', array( $this, 'wpvl_validate' ) );
		add_settings_section( 'wpvl_general', '', '__return_false', 'wpvl_settings' );
		add_settings_field( 'wpvl_enable_widget', __( 'Active widget Dashboard:', 'wpvllang' ), array( $this, 'setting_wpvl_enable_widget' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-enable-widget' ) );
		add_settings_field( 'wpvl_enable_admin_bar', __( 'Active Link on Admin Bar:', 'wpvllang' ), array( $this, 'setting_wpvl_enable_admin_bar' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-enable-admin-bar' ) );
		add_settings_field( 'wpvl_header',  __( '<strong>Advanced Settings</strong>','wpvllang' ), '__return_false', 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_show_wp_config', __( 'View Current wp-config.php file:', 'wpvllang' ), array( $this, 'setting_wpvl_show_wp_config' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-show-wp-config' ) );
		add_settings_field( 'wpvl_custom_code', __( 'Add Custom Code:', 'wpvllang' ), array( $this, 'setting_wpvl_custom_code' ), 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_text_wp_config', __( 'Enter the Custom code:', 'wpvllang' ), array( $this, 'setting_wpvl_text_wp_config' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-text-wp-config' ) );
	}
	function wpvl_validate( $input ){
		$output = array();
		$output['no_overwrite'] = '0'; // Define internal option for disable overwrite wp-config.php
		foreach( $input as $key => $value ){
			if( isset( $input[$key] ) )
				$output[$key] = $input[$key];
		}
		if( $output['wpvl_custom_code'] === '1' || $output['wpvl_custom_code'] === '2' ) // remove custom code in textarea
			$output['wpvl_text_wp_config'] = '';
		if( $output['no_overwrite'] != $output['wpvl_custom_code'] )
			$output['no_overwrite'] = $output['wpvl_custom_code'];	
		return apply_filters( 'wpvl_validate', $output, $input );
	}
	function setting_wpvl_enable_widget(){
		$checked_wpvlew = ( isset( $this->wpvl_options['wpvl_enable_widget'] ) ) ? $this->wpvl_options['wpvl_enable_widget'] : 0;
?>
		<input type="checkbox" id="wpvl-enable-widget" name="wpvl-options[wpvl_enable_widget]" value="1" <?php checked( '1', $checked_wpvlew, true ); ?>/>
<?php
    }
	function setting_wpvl_enable_admin_bar(){
?>
		<p>
		<label>
		<input id="r-disable-admin-bar" type="radio" value="1" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '1', $this->wpvl_options['wpvl_enable_admin_bar'], true ); ?>/>
		<span><?php _e( 'Disabled', 'wpvllang' ); ?></span>	
		</label>
		</p>
		<p> 
		<label>
		<input id="r-default-admin-bar" type="radio" value="2" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '2', $this->wpvl_options['wpvl_enable_admin_bar'], true ); ?>/>
		<span><?php _e( 'Enable in Admin Bar', 'wpvllang' ); ?></span>
		</label>
		</p>
		<p>
		<label>
		<input id="r-full-admin-bar" type="radio" value="3" name="wpvl-options[wpvl_enable_admin_bar]" <?php checked( '3', $this->wpvl_options['wpvl_enable_admin_bar'], true ); ?>/>
		<span><?php _e( 'Enable in Admin Bar (FrontEnd)', 'wpvllang' ); ?></span>
		</label>
		</p>
<?php
	}
	function setting_wpvl_show_wp_config(){
		$checked_wpvlswpc = ( isset( $this->wpvl_options['wpvl_show_wp_config'] ) ) ? $this->wpvl_options['wpvl_show_wp_config'] : 0;
?>
        <input type="checkbox" id="wpvl-show-wp-config" name="wpvl-options[wpvl_show_wp_config]" value="1" <?php checked( '1', $checked_wpvlswpc, true ); ?>/>
<?php
	}
	function setting_wpvl_custom_code(){
?>
		<p>
		<label>
		<input id="r-disable" class="wpvl-checked" type="radio" value="1" name="wpvl-options[wpvl_custom_code]" <?php checked( '1', $this->wpvl_options['wpvl_custom_code'], true ); ?>/>
		<span><?php _e( 'Disabled', 'wpvllang' ); ?></span>	
		</label>
		</p>
		<p> 
		<label>
		<input id="r-default" class="wpvl-checked" type="radio" value="2" name="wpvl-options[wpvl_custom_code]" <?php checked( '2', $this->wpvl_options['wpvl_custom_code'], true ); ?>/>
		<span><?php _e( 'Add Default Code', 'wpvllang' ); ?></span>
		</label>
		</p>
		<p>
		<label>
		<input id="r-custom" class="wpvl-checked" type="radio" value="3" name="wpvl-options[wpvl_custom_code]" <?php checked( '3', $this->wpvl_options['wpvl_custom_code'], true ); ?>/>
		<span><?php _e( 'Add Custom Code', 'wpvllang' ); ?></span>
		</label>
		</p>
<?php
	}
	function setting_wpvl_text_wp_config(){
?>
		<textarea cols="60" rows="10" id="wpvl-text-wp-config" name="wpvl-options[wpvl_text_wp_config]"><?php echo esc_textarea( $this->wpvl_options['wpvl_text_wp_config'] ); ?></textarea>
<?php
	}
	function wpvl_page_menu(){
		global $page_options;
		add_menu_page( 'WP Viewer Log', 'WPVL', 'activate_plugins', 'wp-viewer-log', array( $this, 'wpvl_page_log' ), plugins_url( 'include/images/a-error.png', __FILE__ ) );
    	add_submenu_page('wp-viewer-log', 'WPVL View Log', __( 'WPVL View Log', 'wpvllang' ), 'activate_plugins', 'wp-viewer-log' );
    	$page_options = add_submenu_page('wp-viewer-log', 'WPVL Options', __('WPVL Options', 'wpvllang' ), 'activate_plugins', 'wp-viewer-log-options', array( $this, 'wpvl_page_options' ) );
		add_action( 'load-' . $page_options, array( $this, 'wpvl_add_help_tab' ) );
		
	}
	function wpvl_page_log(){
?>
			<div class="wrap">
			<?php screen_icon( 'wp-viewer-log' ); ?>
			<h2><?php printf( __( 'WP Viewer Log - Version: %s', 'wpvllang' ), self::wpvl_version ); ?></h2>
<?php
		if ( isset( $_POST['clear-log'] ) ) {
    		echo '<div class="updated fade"><p>' . __( 'Cleared Log', 'wpvllang' ) . '</p></div>';
		}
?>
			<h3><?php _e( 'Current Log', 'wpvllang' ); ?></h3>
			<div id="tab-view-log">
			<?php echo $this->wpvl_read_file( 'log' ); ?>
			<form method="post" action="">
			<?php submit_button( __( 'Clear Log', 'wpvllang' ), 'delete', 'clear-log' ); ?>
			</form>
			</div>
			</div><!-- .wrap -->
<?php
	}
	function wpvl_page_options(){
?>
		<div class="wrap">
		<?php screen_icon( 'wp-viewer-log' ); ?>
		<h2><?php printf( __( 'WP Viewer Log - Version: %s', 'wpvllang' ), self::wpvl_version ); ?></h2>
<?php
		if ( isset( $_GET['settings-updated'] ) ) {
    		echo '<div class="updated fade"><p>' . __( 'Settings saved.' ) . '</p></div>';
		}
?>
		<div id="wpvl-tabs">
		<h3 class="nav-tab-wrapper">
		<a class="nav-tab  nav-tab-active" href="#tab-options"><?php _e( 'Plugin Options', 'wpvllang' ); ?></a>
<?php
		if( isset($this->wpvl_options['wpvl_show_wp_config']) && $this->wpvl_options['wpvl_show_wp_config'] === '1' )
			echo '<a class="nav-tab" href="#tab-view-wp-config">' . __( 'Current WP Config File', 'wpvllang' ) . '</a>';
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
		</div><!-- #tab-options -->
<?php
		if( isset($this->wpvl_options['wpvl_show_wp_config']) && $this->wpvl_options['wpvl_show_wp_config'] === '1' ){
?>
		<div id="tab-view-wp-config" class="wpvl-content">
		<?php echo $this->wpvl_read_file( 'conf' ); ?>
        </div>
<?php
		}
?>
		</div><!-- #wpvl-tabs -->
		</div><!-- .wrap -->
<?php
	}
	function wpvl_add_help_tab(){
    	global $page_options;
    	$screen = get_current_screen();
    	if ( $screen->id != $page_options )
			return;
    	$screen->add_help_tab( array( 'id' => 'wpvl-help-one', 'title' => __( 'Info', 'wpvllang' ), 'content' => '', 'callback' => array( $this, 'wpvl_info' ) ) );
	}
	function wpvl_info(){
?>
		<p><?php _e( 'You can add a widget on the desktop and edit the wp-config.php file to configure the WP_DEBUG.', 'wpvllang' ); ?></p>
		<p><?php _e( 'By default only the widget is enabled.', 'wpvllang' ); ?></p>
        <p><?php _e( 'If you activate the admin bar link will be displayed only when there are errors.', 'wpvllang' ); ?></p>
		<p><?php _e( 'If you disable the custom code file wp-config.php will be restored to its original state before activating the plugin.', 'wpvllang'); ?></p>
		<p><?php _e( 'If you configure your wp-config.php without the plugin, the widget as the page log work.', 'wpvllang'); ?></p>
        <h5 style="color:red"><?php _e( 'Important!!!', 'wpvllang' ); ?></h5>
		<p><?php _e( 'If you manually add code after using the plugin were eliminated because they always used the copy for editing.', 'wpvllang'); ?></p>
<?php
    }
	function wpvl_page_scripts(){
		if( !current_user_can( 'activate_plugins' ) )
			return;
		//wp_register_script( 'wpvl-scripts', plugins_url( 'include/javascript/dev/jquery.wpvl.js', __FILE__ ), array( 'jquery' ), self::wpvl_version, false );
		wp_register_script( 'wpvl-scripts', plugins_url( 'include/javascript/jquery.wpvl.min.js', __FILE__ ), array( 'jquery' ), self::wpvl_version, false );
		wp_register_style( 'wpvl-styles', plugins_url( 'include/css/wpvl-styles.css', __FILE__ ), array(), self::wpvl_version, 'all' );
		wp_enqueue_script( 'wpvl-scripts' );
		wp_enqueue_style( 'wpvl-styles' );
	}
	function wpvl_enable_widget(){
		if( isset( $this->wpvl_options['wpvl_enable_widget'] ) && current_user_can( 'activate_plugins' ) ){
			add_action( 'wp_dashboard_setup', array( $this, 'wpvl_add_dashboard_widgets' ) );
			add_action( 'wp_network_dashboard_setup', array( $this, 'wpvl_add_dashboard_widgets' ) );
		}
	}
	function wpvl_add_dashboard_widgets(){
		wp_add_dashboard_widget( 'wpvl_dashboard_widget', 'WP Viewer Log', array( $this, 'wpvl_dashboard_widget_function' ) );
	}
	function wpvl_dashboard_widget_function(){
		return $this->wpvl_read_file( 'widget' );
	}
	private function wpvl_admin_color(){
		// Private Function to check current color wordpress admin
		global $user_id;
		$current_color = get_user_option( 'admin_color', $user_id );
		return $current_color;
	}
	private function wpvl_read_file( $type, $html = true ){
		$file = array();
		if( $html ){
		$echo = '<div class="wpvl-html' . ( ( $type === 'conf' ) ? ' conf' : '' ) . ' theme-' . $this->wpvl_admin_color() . ( ( $type === 'widget' ) ? ' wpvl-widget' : '' ) . '">';
		$echo .= '<pre>';
		}
		if( file_exists( $this->wpvl_log_errors ) )
			$file = file( $this->wpvl_log_errors, FILE_IGNORE_NEW_LINES );
		switch( $type ){
			case 'log':
				if( is_array( $file ) && !empty( $file ) ){
					$file = array_reverse( $file, true );			
					$echo .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td class="gutter">';
					if( count( $file ) <= 100 ){
						for( $i=1; $i <= count( $file ); $i++ ){
							$echo .= '<div>' . $i . '</div>';	
						}
						$echo .= '</td><td class="code">';
						foreach( $file as $line => $text ){
							$echo .= '<div>';
							$echo .= esc_html( $text );
							$echo .= '</div>';
						}
					} else {
						for( $i=1; $i <= 100; $i++ ){
							$echo .= '<div>' . $i . '</div>';	
						}
						$echo .= '</td><td class="code">';
						$file = array_slice( $file, 0, 100 );
						foreach( $file as $line => $text ){
							$echo .= '<div>';
							$echo .= esc_html( $text );
							$echo .= '</div>';
						}
					}
					$echo .= '</td></tr></tbody></table>';
				} else {
					if( isset( $_POST['clear-log'] ) )
						$echo .= sprintf( __( '%1sI delete the contents of log%2s','wpvllang' ), '<p class="str">','</p>' );
					else
						$echo .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
				}
				$echo .= '</pre></div>';	
			break;
			case 'widget':
				if( is_array( $file ) && !empty( $file ) ){
					$file = array_reverse( $file, true );
					$empty = false;
					if( count( $file ) > 100 )
						$file = array_slice( $file, 0, 100 );
					$regex = '/(\\[.*?\\])\\s((?:[A-Z]{3})\\s(?:[A-Z]{1}[a-z]+|[A-Z]{1}[a-z]+\\s[a-z]+)\:)/'; // capture date and name error
					$del = array( '/(?:[0-9]+\:[0-9]+\:[0-9]+)/', '/(?:[A-Z]{3})/', '/(\\[)/', '/(\\])/', '/\\s/' ); // remove extras 
					for( $i=0; $i < count( $file ); $i++ ){
						preg_match_all( $regex, $file[$i], $lines[$i] );
						$dates[] = preg_replace( $del, '', $lines[$i][1][0] );
						$errors[] = $lines[$i][2][0];
					}
					$i=0;
					foreach( $dates as $date ){
						if( ( strtotime( date( 'd-M-Y' ) ) <= strtotime( $date ) ) && ( strtotime( $date ) <= strtotime( date( 'd-M-Y', strtotime( '+1 day' ) ) ) ) )
							$day_errors[$i] = $errors[$i];
						$i++;
					}
					$echo .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>';
					$echo .= '<tr><th class="left-widget" scope="col">' . __( 'Type','wpvllang' ) . '</th><th class="right-widget" scope="col">' . __( 'No.', 'wpvllang' ) . '</th></tr>';
					if( !empty( $day_errors ) )
					foreach( array_count_values( $day_errors ) as $error => $num ){
						$echo .=  '<tr><td class="left-widget">' . $error . '</td><td class="right-widget">' . $num . '</td></tr>';
					}
					$echo .= '</tbody></table>';
				} else {
					$empty = true;
					$echo .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
				}
				$echo .= '</pre></div>';
				$echo .= '<div class="wpvl-widget-buttons">';
				if( $empty ){
					$echo .= '<p class="submit options"><a class="button" href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a></p>';
				} else {
					$echo .= '<p class="submit options"><a class="button" href="' . admin_url( 'admin.php?page=wp-viewer-log-options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a></p>';
					$echo .= '<p class="submit view"><a class="button-primary" href="' . admin_url( 'admin.php?page=wp-viewer-log' ) . '">' . __( 'View Log', 'wpvllang' ) . '</a></p>';
				}
				$echo .= '<div class="clear"></div></div>';
			break;
			case 'bubble':
				$count_errors = 0;
				if( is_array( $file ) && !empty( $file ) ){
					$file = array_reverse( $file, true );
					$regex = '/(\\[.*?\\])\\s/'; // capture date
					$del = array( '/(?:[0-9]+\:[0-9]+\:[0-9]+)/', '/(?:[A-Z]{3})/', '/(\\[)/', '/(\\])/', '/\\s/' ); // remove extras
					for( $i=0; $i < count( $file ); $i++ ){
						preg_match_all( $regex, $file[$i], $lines[$i] );
						$dates[] = preg_replace( $del, '', $lines[$i][1][0] );
						$dates[] = $lines[$i][1][0];
					}
					foreach( $dates as $date ){
						if( ( strtotime( date( 'd-M-Y' ) ) <= strtotime( $date ) ) && ( strtotime( $date ) <= strtotime( date( 'd-M-Y', strtotime( '+1 day' ) ) ) ) )
							$errors[$i] = $i;
						$i++;
					}
					$count_errors = count( $errors );
				}
				$echo = $count_errors;
			break;
			case 'conf':
				$file = '';
				if( file_exists( $this->conf_original ) )
					$file = file( $this->conf_original );
				if( is_array( $file ) && !empty( $file ) ){
					$echo .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td class="gutter">';
					for( $i=1; $i <= count( $file ); $i++ ){
						$echo .= '<div>' . $i . '</div>';	
					}
					$echo .= '</td><td class="code">';
					foreach( $file as $line => $text ){
						$echo .= '<div>';
						$echo .= esc_html( $text );
						$echo .= '</div>';
					}
					$echo .= '</td></tr></tbody></table>';
				} else {
					$echo .= sprintf( __( '%1sWarnnig!!!%2s','wpvllang' ), '<p class="str error">','</p>' );
					$echo .= sprintf( __( '%1sError to load wp-config.php, check file to really exists.%2s','wpvllang' ), '<p class="str error">','</p>' );
				}
				$echo .= '</pre></div>';
			break;
			default:
			break;
		}
		unset( $file );
		if( $html )
			echo ( isset( $echo ) && !empty( $echo ) ) ? $echo : __('Error!!!', 'wpvllang');
		else
			return ( isset( $echo ) && !empty( $echo ) ) ? $echo : 0;
		unset( $echo );
	}
	function wpvl_write_wp_config(){
		$text_comment = "\n\n// " . __( 'Added from the plugin WP Viewer Log', 'wpvllang' ) . "\n\n";
		$text_custom_comment = "\n\n// " . __( 'Added from the plugin WP Viewer Log with custom code', 'wpvllang' ) . "\n\n";
		if( isset( $_GET['page'] ) && $_GET['page'] === 'wp-viewer-log-options' && isset( $_GET['settings-updated'] ) && $this->wpvl_options['no_overwrite'] != $this->wpvl_options['wpvl_custom_code'] ){
			switch( $this->wpvl_options['wpvl_custom_code'] ){
				case '1': // Reset
					if( file_exists( $this->conf_backup ) )
						rename( $this->conf_backup, $this->conf_original );
				break;
				case '2': // Add Default Code
					if( file_exists( $this->conf_backup ) )
						rename( $this->conf_backup, $this->conf_original );
					$wpvl_text = array( 
						"define( 'WP_DEBUG', true );\n",
						"if( WP_DEBUG ){\n",
						"\tdefine( 'WP_DEBUG_LOG', true );\n",
						"\tdefine( 'WP_DEBUG_DISPLAY', false );\n",
						"\t@ini_set( 'display_errors',0 );\n",
						"}"
					);
					array_unshift( $wpvl_text, $text_comment );
					array_push( $wpvl_text, $text_comment );
					$this->wpvl_write_function( $wpvl_text );
				break;
				case '3': // Add Custom Code
					if( file_exists( $this->conf_backup ) )
						rename( $this->conf_backup, $this->conf_original );
					$wpvl_text = explode( '\n', $this->wpvl_options['wpvl_text_wp_config'] );
					array_unshift( $wpvl_text, $text_custom_comment );
					array_push( $wpvl_text, $text_custom_comment );
					$this->wpvl_write_function( $wpvl_text );
				break;
				default:
				break;
			}
		}
	}
	private function wpvl_write_function( $wpvl_text = array() ){
		if( !file_exists( $this->conf_backup ) ){
			copy( $this->conf_original, $this->conf_backup );
			$conf = file( $this->conf_original );
			$i = 0;
			foreach( $conf as $line => $text ){
				if ( ( substr( str_replace( ' ', '', $text ), 8, 8 ) === 'WP_DEBUG' ) || ( substr( str_replace( ' ', '', $text ), 8, 9 ) === 'WP_DEBUG' ) ){
					$nline = $i;
					$conf[$line] = '';			
					break;
				}
				$i++;	
			}
			if( isset( $nline ) ){
				array_splice( $conf, $nline, 0, $wpvl_text );
				$new_conf = @fopen( $this->conf_original, 'w' );
				foreach( $conf as $line )
					@fwrite( $new_conf, $line );
				@fclose( $new_conf );
			} else {
				if( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === 'wp-viewer-log-options' ){
						add_action( 'admin_notices', function(){
								echo '<div class="error fade"><p>' . __( 'For security has not been edited wp-config.php file, edit it manually.', 'wpvllang' ) . '</p></div>';
							}
						);
				}
			}
		}
	}
	function wpvl_clear_log(){
		if( isset( $_POST['clear-log'] ) ){			
			if( file_exists( $this->wpvl_log_errors ) ){
				$clear = @fopen( $this->wpvl_log_errors, 'w' );
				unset($clear);
			}
				
		}
	}
	function wpvl_add_admin_bar_item( $admin_bar ){
		if( isset( $this->wpvl_options['wpvl_enable_admin_bar'] ) && $this->wpvl_options['wpvl_enable_admin_bar'] != '1' ){
			if( !current_user_can( 'activate_plugins' ) )
				return;
			$frontend = ( $this->wpvl_options['wpvl_enable_admin_bar'] === '3' ) ? true : false;
			$num = $this->total_errors;
			$text = ( $num > 1 ) ? __( 'Errors', 'wpvllang' ) : __( 'Error', 'wpvllang' );
			$title = sprintf( '<span style="color:red" class="count-%1s"> %2s %3s</span>', $num, number_format_i18n( $num ), $text );
			if( $num > 0 )
				if( is_admin() || $frontend ){
					$admin_bar->add_menu( array(
						'id'		=>	'wpvl-add-link-errors',
						'title'		=>	$title,
						'href'		=>	admin_url( 'admin.php?page=wp-viewer-log' ),
						'meta'		=>	array( 
											'title' =>  __( 'View Log', 'wpvllang' ),
											'target' => '_blank'
										)
					) );
				}
		}
	}
	function count_bubble(){
		$num = $this->total_errors;
		$count = '<span class="update-plugins wpvl-bubble count-' . $num . '"><span class="plugin-count">' . $num . '</span></span>';
		global $menu;
		foreach( $menu as $key => $submenu ){
			if( $submenu[2] == 'wp-viewer-log' ){
				$menu[$key][0] = $menu[$key][0] . $count;
				break;
			}
		}
		return $menu;
	}
}
$wpvl = new WP_VIEWER_LOG;
endif;
?>