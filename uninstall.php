<?php
/**
 * XWidgets File
 *	Uninstall file
 *
 *	@author: Bank of Canada (Benjamin Boudreau<benjamin@bboudreau.ca>, Jules Delisle, Dale Taylor, Nelson Lai, Jason Manion, Nicholas Crawford, Jairus Pryor)
 *	@version: 2.2
 *	@package xwidgets
 */
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) {
	wp_die( __( 'You cannot access this page directly.', 'xwidgets' ) );
}

if ( ! current_user_can("delete_plugins") ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'xwidgets' ) );
}

define( 'XWIDGETS_UNINSTALL', true );

require_once("xwidgets.php");

XWidgets::getInstance()->uninstall();