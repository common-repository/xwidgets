<?php

/*
 Plugin Name: XWidgets
 Description: Gives you the ability to configure widgets on a per page basis.
 Version: 2.2
 Author: Benjamin Boudreau, Jules Delisle, Dale Taylor, Nelson Lai, Jason Manion, Nicholas Crawford, Jairus Pryor
 Author URI: http://www.bankofcanada.ca/
 Plugin URI: http://bankofcanada.wordpress.com/
 */

/*
XWidgets is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

XWidgets is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if( version_compare(phpversion(), '5.2', '<') ){
	if ( function_exists( 'deactivate_plugins' ) ) {
		deactivate_plugins( __FILE__ );
		die( 'Only PHP version 5.2 or higher is supported by this plugin.' );
	} else {
		wp_die( 'Only PHP version 5.2 or higher is supported by this plugin.' );
	}
}

define( 'XWIDGETS_VERSION' , '2.1' );

define( 'XWIDGETS_PATH', plugin_dir_path( __FILE__ ) );
define( 'XWIDGETS_ID_PARAM_NAME', 'xwidgets_id' );
define( 'XWIDGETS_TYPE_PARAM_NAME', 'xwidgets_type' );
define( 'XWIDGETS_CAPABILITY_NAME', 'Manage X Widgets' );

// Options Name Constants
define( 'XWIDGETS_OPTION_NAME_PREFIX', '_xwidgets' );
define( 'XWIDGETS_VERSION_OPTION_NAME', XWIDGETS_OPTION_NAME_PREFIX.'_version' );
define( 'XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY', XWIDGETS_OPTION_NAME_PREFIX.'-use-global-widgets-when-empty' );
define( 'XWIDGETS_POSTMETA_NAME_SIDEBARS_WIDGETS', XWIDGETS_OPTION_NAME_PREFIX );
define( 'XWIDGETS_POSTMETA_NAME_WIDGET_PREFIX', XWIDGETS_OPTION_NAME_PREFIX.'_' );
define( 'XWIDGETS_POSTMETA_NAME_INHERIT', XWIDGETS_OPTION_NAME_PREFIX.'-inherit' );

require_once('xwidgets.class.php');

XWidgets::getInstance(); // Init

register_activation_hook( __FILE__, array( XWidgets::getInstance(), 'xwidgets_activate' ) );