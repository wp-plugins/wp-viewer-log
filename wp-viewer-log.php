<?php
/**
 * @package WP Viewer Log
 * @version 0.1
 */
/*
Plugin Name: WP Viewer Log
Plugin URI: http://wordpress.org/extend/plugins/wp-viewer-log/
Description: Lets see how many errors have had in the present day through a widget, configure your wp-config.php and see the file log to a maximum of 100 lines.
Author: Sergio P.A. ( 23r9i0 )
Version: 0.1
Author URI: http://dsergio.com/
*/
/*  Copyright 2013  Sergio Prieto Alvarez  ( email : info@dsergio.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General public License as published by
    the Free Software Foundation; either version 2 of the License, or
    ( at your option ) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General public License for more details.

    You should have received a copy of the GNU General public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if( !class_exists( 'WP_VIEWER_LOG' ) ) : 
class WP_VIEWER_LOG {
	private
		$wpvl_version = '0.1',
		$wpvl_log_errors,
		$wpvl_options,
		$wpvl_options_defaults = array( 'wpvl_enable_widget' => '1', 'wpvl_custom_code' => '1', 'wpvl_text_wp_config' => '' ),
		$conf_original,
		$conf_backup,
		$total_errors;
	public function __construct(){
		add_action( 'init', array( $this, 'wpvl_init' ) );
		add_action( 'init', array( $this, 'wpvl_init_only_admin' ) );
		add_action( 'admin_init', array( $this, 'wpvl_admin_options' ) );
		add_action( 'admin_init', array( $this, 'wpvl_write_wp_config' ) );
		add_action( 'admin_init', array( $this, 'wpvl_clear_log' ) );
		add_action( 'admin_menu', array( $this, 'wpvl_page_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wpvl_page_scripts' ) );
		add_filter( 'plugin_action_links', array( $this, 'wpvl_plugin_action_links' ), 10, 2 );
		register_activation_hook( __FILE__, array( $this, 'wpvl_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'wpvl_deactivate' ) );
		$this->wpvl_options = get_option( 'wpvl-options' );
		$this->wpvl_log_errors = ini_get('error_log');
		if ( !defined('ABSPATH') )
			define('ABSPATH', dirname(__FILE__) . '/');
		$this->conf_backup = ABSPATH . 'wp-config-backup.php';
		$this->conf_original = ABSPATH . 'wp-config.php';
	}
	public function wpvl_init(){
		global $wp_version;
		if ( version_compare( $wp_version, '3.1', '< ' ) )
			wp_die( __( 'This plugin requires WordPress 3.1 or greater.', 'wpvllang' ) );
  		load_plugin_textdomain( 'wpvllang', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	public function wpvl_init_only_admin(){
		if( current_user_can('activate_plugins') )
			add_action( 'admin_init', array( $this, 'wpvl_enable_widget' ) );
	}
	public function wpvl_activate(){	
		add_option( 'wpvl-options', $this->wpvl_options_defaults );
	}
	public function wpvl_deactivate(){
		delete_option( 'wpvl-options' );
	}
	public function wpvl_plugin_action_links( $links, $file ){
    	if ( $file == plugin_basename( __FILE__ ) ){
      		$setting_link = '<a href="' . admin_url( 'options-general.php?page=wp-viewer-log&options' ) . '">' . __( 'Options', 'wpvllang' ) . '</a>';
	  		array_unshift( $links, $setting_link );
		}
    	return $links;
	}
	public function wpvl_admin_options(){
		register_setting( 'wpvl-register', 'wpvl-options', array( $this, 'wpvl_validate' ) );
		add_settings_section( 'wpvl_general', '', '__return_false', 'wpvl_settings' );
		add_settings_field( 'wpvl_enable_widget', __( 'Active widget Dashboard:', 'wpvllang' ), array( $this, 'setting_wpvl_enable_widget' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-enable-widget' ) );
		add_settings_field( 'wpvl_header',  __( '<strong>Advanced Settings</strong>','wpvllang' ), '__return_false', 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_custom_code', __( 'Add Custom Code:', 'wpvllang' ), array( $this, 'setting_wpvl_custom_code' ), 'wpvl_settings', 'wpvl_general' );
		add_settings_field( 'wpvl_text_wp_config', __( 'Enter the code:', 'wpvllang' ), array( $this, 'setting_wpvl_text_wp_config' ), 'wpvl_settings', 'wpvl_general', array( 'label_for' => 'wpvl-text-wp-config' ) );
	}
	public function wpvl_validate( $input ){
		$output = array();
		foreach( $input as $key => $value ){
			if( isset( $input[$key] ) )
				$output[$key] = strip_tags( stripslashes( $input[$key] ) );
		}
		if( $output['wpvl_custom_code'] === '1' ) // remove custom code in textarea
			$output['wpvl_text_wp_config'] = '';
			
		return apply_filters( 'wpvl_validate', $output, $input );
	}
	public function setting_wpvl_enable_widget(){
		$checked_wpvlew = ( isset( $this->wpvl_options['wpvl_enable_widget'] ) ) ? $this->wpvl_options['wpvl_enable_widget'] : 0;
		echo '<input type="checkbox" id="wpvl-enable-widget" name="wpvl-options[wpvl_enable_widget]" value="1" ' . checked( '1', $checked_wpvlew, false ) . '/>';
	}
	public function setting_wpvl_custom_code(){
		echo '
			<p>
			<label>
			<input id="r-disable" class="wpvl-checked" type="radio" value="1" name="wpvl-options[wpvl_custom_code]"' . checked( '1', $this->wpvl_options['wpvl_custom_code'], false ) . '/>
			<span>' . __( 'Disabled', 'wpvllang' ) . '</span>	
			</label>
			</p>
			<p> 
			<label>
			<input id="r-default" class="wpvl-checked" type="radio" value="2" name="wpvl-options[wpvl_custom_code]"' . checked( '2', $this->wpvl_options['wpvl_custom_code'], false ) . '/>
			<span>' . __( 'Add Default Code', 'wpvllang' ) . '</span>
			</label>
			</p>
			<p>
			<label>
			<input id="r-custom" class="wpvl-checked" type="radio" value="3" name="wpvl-options[wpvl_custom_code]"' . checked( '3', $this->wpvl_options['wpvl_custom_code'], false ) . '/>
			<span>' . __( 'Add Custom Code', 'wpvllang' ) . '</span>
			</label>
			</p>
			 ';
	}
	public function setting_wpvl_text_wp_config(){
		echo '
		<textarea cols="60" rows="10" id="wpvl-text-wp-config" name="wpvl-options[wpvl_text_wp_config]">'
		. esc_textarea( $this->wpvl_options['wpvl_text_wp_config'] ) .
		'</textarea>
		';
	}
	public function wpvl_page_menu(){
		global $page;
		$page = add_options_page( 'WP Viewer Log', 'WP Viewer Log', 'manage_options', 'wp-viewer-log', array( $this, 'wpvl_page' ) );
		add_action( 'load-' . $page, array( $this, 'wpvl_add_help_tab' ) );
	}
	public function wpvl_page(){
		echo '
			<div class="wrap">'
			. screen_icon() .
			'<h2>' . sprintf( __( 'WP Viewer Log - Version: %s', 'wpvllang' ), $this->wpvl_version ) . '</h2>';
		echo '
			<div id="wpvl-tabs">
			<h3 class="nav-tab-wrapper">
			<a class="nav-tab nav-tab-active" href="#tab-view-log">' . __( 'Current Log', 'wpvllang' ) . '</a>
			<a class="nav-tab" href="#tab-options">' . __( 'Plugin Options', 'wpvllang' ) . '</a>
		';
		if( isset($this->wpvl_options['wpvl_custom_code']) && $this->wpvl_options['wpvl_custom_code'] !== '1' )
			echo '<a class="nav-tab" href="#tab-view-wp-config">' . __( 'Current WP Config File', 'wpvllang' ) . '</a>';
		echo '
			</h3>
			<div id="tab-view-log" class="wpvl-content">';
			$this->wpvl_read_file( 'log' ); 
		echo '
			<form method="post" action="">
			<p class="submit">
		';
			submit_button( __( 'Clear Log', 'wpvllang' ), 'delete', 'clear-log', false );
		echo '
			</p>
			</form>
			</div>
			<div id="tab-options" class="wpvl-content">
			<form method="post" action="options.php">
		';
		settings_fields( 'wpvl-register' ); 
		do_settings_sections( 'wpvl_settings' );
		echo '<p class="submit">';
		submit_button( '', 'primary', 'submit', false );
		echo '
			</p>
			</form>
			</div><!-- #tab-options -->
		';
		if( isset($this->wpvl_options['wpvl_custom_code']) && $this->wpvl_options['wpvl_custom_code'] !== '1' ){
			echo '
				<div id="tab-view-wp-config" class="wpvl-content">
			';
			$this->wpvl_read_file( 'conf' );
			echo '
				</div>
			';
		}
		echo '
			</div><!-- #wpvl-tabs -->
			</div><!-- .wrap -->
		';
	}
	public function wpvl_add_help_tab(){
    	global $page;
    	$screen = get_current_screen();
    	if ( $screen->id != $page )
			return;
    	$screen->add_help_tab( array( 'id' => 'wpvl-help-one', 'title' => __( 'Info', 'wpvllang' ), 'content' => '', 'callback' => array( $this, 'wpvl_info' ) ) );
	}
	public function wpvl_info(){
		echo '
		<p>' . __( 'You can add a widget on the desktop and edit the wp-config.php file to configure the WP_DEBUG.', 'wpvllang' ) . '</p>
		<p>' . __( 'By default only the widget is enabled.', 'wpvllang' ) . '</p>
		<p>' . __( 'If you disable the custom code file wp-config.php will be restored to its original state before activating the plugin.', 'wpvllang') . '</p>
		<p>' . __( 'If you configure the wp-config.php without the plugin, the widget as the log tab will work just, but shall not see the file wp-config.php', 'wpvllang') . '</p>
		';
    }
	public function wpvl_page_scripts( $hook ){
		if( 'settings_page_wp-viewer-log' === $hook ){
			//wp_register_script( 'wpvl-scripts', plugins_url( 'include/javascript/dev/jquery.wpvl.js', __FILE__ ), array( 'jquery' ), $this->wpvl_version, true );
			wp_register_script( 'wpvl-scripts', plugins_url( 'include/javascript/jquery.wpvl.min.js', __FILE__ ), array( 'jquery' ), $this->wpvl_version, true );
			wp_enqueue_script( 'wpvl-scripts' );
		}
		wp_register_style( 'wpvl-styles', plugins_url( 'include/css/wpvl-styles.css', __FILE__ ), array(), $this->wpvl_version, 'all' );
		wp_enqueue_style( 'wpvl-styles' );
	}
	public function wpvl_enable_widget(){
		if( isset( $this->wpvl_options['wpvl_enable_widget'] ) ){
			add_action( 'wp_dashboard_setup', array( $this, 'wpvl_add_dashboard_widgets' ) );
			add_action( 'wp_network_dashboard_setup', array( $this, 'wpvl_add_dashboard_widgets' ) );
		}
	}
	public function wpvl_add_dashboard_widgets(){
		wp_add_dashboard_widget( 'wpvl_dashboard_widget', 'WP Viewer Log', array( $this, 'wpvl_dashboard_widget_function' ) );
	}
	public function wpvl_dashboard_widget_function(){
		return $this->wpvl_read_file( 'widget' );
	}
	public function wpvl_read_file( $type ){
		global $user_id;
		$file = array();
		$current_color = get_user_option( 'admin_color', $user_id );
		$html = '<div class="wpvl-html' . (( $type === 'conf' ) ? ' conf' : '' ) . ' theme-' . $current_color . (( $type === 'widget' ) ? ' wpvl-widget' : '' ) . '">';
		$html .= '<pre>';
		switch( $type ){
			case 'widget':
				if( file_exists( $this->wpvl_log_errors ) )
					$file = file( $this->wpvl_log_errors );
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
					$html .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>';
					$html .= '<tr><th class="left-widget" scope="col">' . __( 'Type','wpvllang' ) . '</th><th class="right-widget" scope="col">' . __( 'No.', 'wpvllang' ) . '</th></tr>';
					foreach( array_count_values( $day_errors ) as $error => $num ){
						$html .=  '<tr><td class="left-widget">' . $error . '</td><td class="right-widget">' . $num . '</td></tr>';
					}
					$html .= '</tbody></table>';
				} else {
					$empty = true;
					$html .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
				}
				$html .= '</pre></div>';
				$html .= '<div class="wpvl-widget-buttons">';
				if( $empty ){
					$html .= '<p class="submit options"><a class="button" href="' . admin_url( 'options-general.php?page=wp-viewer-log&options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a></p>';
				} else {
					$html .= '<p class="submit options"><a class="button" href="' . admin_url( 'options-general.php?page=wp-viewer-log&options' ) . '">' . __( 'Options Plugin', 'wpvllang' ) . '</a></p>';
					$html .= '<p class="submit view"><a class="button-primary" href="' . admin_url( 'options-general.php?page=wp-viewer-log' ) . '">' . __( 'View Log', 'wpvllang' ) . '</a></p>';
				}
				$html .= '<div class="clear"></div></div>';
			break;
			case 'log':
				if( file_exists( $this->wpvl_log_errors ) )
					$file = file( $this->wpvl_log_errors, FILE_IGNORE_NEW_LINES ); // extra line break // bug?
				if( is_array( $file ) && !empty( $file ) ){
					$file = array_reverse( $file, true );			
					$html .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td class="gutter">';
					if( count( $file ) <= 100 ){
						for( $i=1; $i <= count( $file ); $i++ ){
							$html .= '<div>' . $i . '</div>';	
						}
						$html .= '</td><td class="code">';
						foreach( $file as $line => $text ){
							$html .= '<div>';
							$html .= esc_html( $text );
							$html .= '</div>';
						}
					} else {
						for( $i=1; $i <= 100; $i++ ){
							$html .= '<div>' . $i . '</div>';	
						}
						$html .= '</td><td class="code">';
						$file = array_slice( $file, 0, 100 );
						foreach( $file as $line => $text ){
							$html .= '<div>';
							$html .= esc_html( $text );
							$html .= '</div>';
						}
					}
					$html .= '</td></tr></tbody></table>';
				} else {
					if( isset( $_POST['clear-log'] ) )
						$html .= sprintf( __( '%1sI delete the contents of log%2s','wpvllang' ), '<p class="str">','</p>' );
					else
						$html .= sprintf( __( '%1sWithout Error%2s','wpvllang' ), '<p class="str">','</p>' );
				}
				$html .= '</pre></div>';	
			break;
			case 'conf':
				if( file_exists( $this->conf_original ) )
					$file = file( $this->conf_original );
				if( is_array( $file ) && !empty( $file ) ){
					$html .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td class="gutter">';
					for( $i=1; $i <= count( $file ); $i++ ){
						$html .= '<div>' . $i . '</div>';	
					}
					$html .= '</td><td class="code">';
					foreach( $file as $line => $text ){
						$html .= '<div>';
						$html .= esc_html( $text );
						$html .= '</div>';
					}
					$html .= '</td></tr></tbody></table>';
				} else {
					$html .= sprintf( __( '%1sWarnnig!!!%2s','wpvllang' ), '<p class="str error">','</p>' );
					$html .= sprintf( __( '%1sError to load wp-config.php, check file to really exists.%2s','wpvllang' ), '<p class="str error">','</p>' );
				}
				$html .= '</pre></div>';
			break;
		}	
		echo ( isset( $html ) && !empty( $html ) ) ? $html : __('Error!!!', 'wpvllang');
		unset( $html );
	}
	public function wpvl_write_wp_config(){
		$text_comment = "\n\n// " . __( 'Added from the plugin WP Viewer Log', 'wpvllang' ) . "\n\n";
		$text_custom_comment = "\n\n// " . __( 'Added from the plugin WP Viewer Log with custom code', 'wpvllang' ) . "\n\n";
		if( isset( $this->wpvl_options['wpvl_custom_code'] ) ){
			switch( $this->wpvl_options['wpvl_custom_code'] ){
				case '1': // Reset
					if( file_exists( $this->conf_backup ) )
						rename( $this->conf_backup, $this->conf_original );
				break;
				case '2': // Add Default Code
					$wpvl_text = array( 
						"define( 'WP_DEBUG', true );\n",
						"if( WP_DEBUG ){\n",
						"\tdefine( 'WP_DEBUG_LOG', true );\n",
						"\tdefine( 'WP_DEBUG_DISPLAY', false );\n",
						"\t@ini_set( 'display_errors',0 );\n",
						"}",
					 );
					array_unshift( $wpvl_text, $text_comment );
					array_push( $wpvl_text, $text_comment );
					$this->wpvl_write_function( $wpvl_text );
				break;
				case '3': // Add Custom Code
					if( file_exists( $this->conf_backup ) ){
						rename( $this->conf_backup, $this->conf_original );
					}
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
	public function wpvl_write_function( $wpvl_text = array() ){
		if( !file_exists( $this->conf_backup ) ){
			copy( $this->conf_original, $this->conf_backup );
			$conf = file( $this->conf_original );
			$i = 0;
			foreach( $conf as $line => $text ){
				if ( (substr( str_replace( ' ', '', $text ), 8, 8 ) === 'WP_DEBUG') || (substr( str_replace( ' ', '', $text ), 8, 9 ) === 'WP_DEBUG') ){
					$nline = $i;
					$conf[$line] = '';			
					break;
				}
				$i++;	
			}
			if( isset( $nline ) ){
				array_splice( $conf, $nline, 0, $wpvl_text );
				$new_conf = fopen( $this->conf_original, 'w' );
				foreach( $conf as $line )
					fwrite( $new_conf, $line );
				fclose( $new_conf );
			} else {
				if( isset( $_REQUEST ) && $_REQUEST['page'] === 'wp-viewer-log' )
					add_settings_error( 'wpvl-no-wp-debug', 'wp_degub_log', __( 'For security has not been edited wp-config.php file, edit it manually.', 'wpvllang' ), 'updated fade' );
			}
		}
	}
	public function wpvl_clear_log(){
		if( isset( $_POST['clear-log'] ) ){
			add_settings_error( 'wpvl-clear', 'clear_log', __( 'Clear Log', 'wpvllang' ), 'updated fade' );
			if( file_exists( $this->wpvl_log_errors ) )
				$clear = fopen( $this->wpvl_log_errors, 'w' );
		}
	}
}
$wpvl = new WP_VIEWER_LOG();
endif;
?>