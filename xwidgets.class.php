<?php
/**
 * Singleton class for XWidgets
 *
 *	@author: Bank of Canada (Benjamin Boudreau<benjamin@bboudreau.ca>, Jules Delisle, Dale Taylor, Nelson Lai, Jason Manion, Nicholas Crawford, Jairus Pryor)
 *	@version: 2.2
 *	@package xwidgets
 */
class XWidgets {
	/**
	 * The singleton instance
	 * @var XWidgets
	 */
	static private $instance;
	/**
	 * @return XWidgets
	 */
	static public function getInstance() {
		if ( ! isset(self::$instance) ) {
			self::$instance= new XWidgets();
		}
		return self::$instance;
	}

	private $post_meta;
	// Informations about the current object.
	/**
	 * tag, category, post, page
	 * @var string
	 */
	private $type;
	/**
	 * Object ID
	 * @var int
	 */
	private $id;
	/**
	 * Current page has not widgets
	 * @var bool
	 */
	private $has_no_widgets;
	/**
	 * To know if WP has been initialized.
	 * @var bool
	 */
	private $wp_initialized;
	/**
	 * Array of all widgets before they get registered.
	 * @var array
	 */
	private $widgets;

	private function __construct() {
		$this->wp_initialized= false;
		$this->widgets= array();

		if ( ! defined( 'XWIDGETS_UNINSTALL' ) ) {
			$this->init();
		}
	}

	private function init() {
		add_action('init', array($this, 'action_init'));
		//fix for when page is updated and xwidgets are out of sync with pages POST variables
		add_action('update_postmeta', array($this, 'post_edit_save'), 1, 4);
		add_action('updated_postmeta', array($this, 'post_edit_update'), 1, 4);
		// Role Management
		add_filter( 'capabilities_list', array( $this, 'filter_capabilities_list' ) );

		global $wp_widget_factory;
		remove_action( 'widgets_init', array( &$wp_widget_factory, '_register_widgets' ), 100 );
		add_action( 'widgets_init', array( $this, 'action_register_option_listener' ), 100 );

		add_filter( 'pre_option_sidebars_widgets', array( $this, 'filter_get_option_sidebars_widgets' ) );

		if ( is_admin() ) {
			/* Admin interface hooks */
			// Init menu
			add_action( 'admin_menu', array($this, 'action_add_menu') );
			// Register settings
			add_action( 'load-options.php', array( $this, 'action_register_settings' ) );
			// Action on list
			add_filter( 'post_row_actions', array($this, 'filter_row_action'), 10, 2 );
			add_filter( 'page_row_actions', array($this, 'filter_row_action'), 10, 2 );
			// Form Display
			add_action( 'edit_page_form', array($this, 'action_display_xwidgets'), 10, 1 ); //after the MCE Editor in pages
			add_action( 'edit_form_advanced', array($this, 'action_display_xwidgets'), 10, 1 ); //after the MCE Editor in posts
			// Add field to the widgets interface
			add_action( 'load-widgets.php', array( $this, 'action_admin_load_widgets_interface' ) );

			/* Widgets hooks */
			// If we delete a post make sure it takes into account inheriting
			add_action( 'delete_post', array($this, 'action_verify_inheritance_after_delete') );
			add_action( 'wp_ajax_xwidgets_save_inherit', array( $this, 'action_ajax_save_inherit' ) );
			add_filter( 'pre_update_option_sidebars_widgets', array( $this, 'filter_update_option_sidebars_widgets' ), 1, 2 );
		} else {
			add_filter( 'wp', array( $this, 'filter_init_wp' ) );
		}
	}

	/**
	  * Hook to save the xwidget post_meta that is in the database. If the metadata for that
	  * posts' xwidget(s) is stored in the POST variable (which could  be out of date),
	  * updates made in the xwidgets admin page are not saved.
	  * When updating a page, it should not update any xwidgets. This should only be done in the 
	  * xwidgets admin page for the specified page.
	  * @param int $meta_id 
	  * @param int $post_id 
	  * @param String $meta_key 
	  * @param mixed $meta_value 
	  */
	public function post_edit_save( $meta_id, $post_id, $meta_key, $meta_value ){
		//get the current xwidgets for the post, and not the ones stored in the post's POST variable, since editing the page should not edit the widgets
		if ($meta_key == XWIDGETS_OPTION_NAME_PREFIX){
			$meta_value = get_post_meta($post_id, $meta_key, true);
			$this->post_meta = $meta_value;
		}
		
	}
	/**
	  * Hook to update the xwidget post_meta that is in the database (with the data that was saved in the post_edit_save function)
	  * @param int $meta_id 
	  * @param int $post_id 
	  * @param String $meta_key 
	  * @param mixed $meta_value 
	  */
	public function post_edit_update( $meta_id, $post_id, $meta_key, $meta_value ){
		if ($meta_key == XWIDGETS_OPTION_NAME_PREFIX){
			if (unserialize($meta_value) != $this->post_meta){
				update_post_meta($post_id, $meta_key, $this->post_meta);
			}			
		}
	}
	
	public function filter_init_wp(WP $wp) {
		global $_wp_sidebars_widgets, $wp_registered_widgets, $wp_widget_factory;
		$this->wp_initialized= true;

		$_wp_sidebars_widgets= array();
		$wp_registered_widgets= array();
		//$wp_widget_factory->widgets= $this->widgets;
		$wp_widget_factory->_register_widgets();
		//unset( $this->widgets );
	}

	public function xwidgets_activate() {
		global $wp_version;
		if( version_compare( $wp_version, '2.8.5', '<') ){
			deactivate_plugins( __FILE__ );
			die( 'Only WP version 2.8.5 is supported by this plugin.' );
		}

		$xwidgets_current_version= get_option( XWIDGETS_VERSION_OPTION_NAME, 0 );
		if( version_compare( $xwidgets_current_version, XWIDGETS_VERSION, '<' ) ){
			try {
				if ( !$this->upgrade_from($xwidgets_current_version) ) {
					deactivate_plugins( __FILE__ );
					die( 'Error while upgrading to the new version.' );
				}
			} catch (Exception $e) {
				deactivate_plugins( __FILE__ );
				die( 'Error while upgrading to the new version.<br/>&nbsp;&nbsp;&nbsp;<strong>' . $e->getMessage() .'</strong>' );
			}
		}
	}

	public function uninstall(){
		// Must be called from the uninstall file only.
		if ( ! current_user_can("delete_plugins") || ! defined( 'XWIDGETS_UNINSTALL' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'xwidgets' ) );
		}
		global $wpdb;

		if ( false === $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s", XWIDGETS_OPTION_NAME_PREFIX.'%') ) ) {
			error_log( 'xwidgets : Uninstall error while deleting postmeta.' );
		}
		if ( false === $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", XWIDGETS_OPTION_NAME_PREFIX.'%') ) ) {
			error_log( 'xwidgets : Uninstall error while deleting options.' );
		}
	}

	public function action_add_menu() {
		add_submenu_page( 'options-general.php', __( 'XWidgets Settings', 'xwidgets' ),'XWidgets', 5, XWIDGETS_PATH.'xwidgets-settings.template.php' );
	}

	public function action_register_settings() {
		register_setting( 'xwidgets', XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY, array($this, 'callback_sanitize_settings_use_global_widgets_when_empty') );
	}

	/**
	 * Who wants closures?
	 */
	public function callback_sanitize_settings_use_global_widgets_when_empty($value) {
		return ( $value == 1 ? 1 : 0 );
	}

	public function filter_row_action($actions, $post) {
		$actions['xwidgets']= "<a href='" . $this->get_edit_url( $post->ID, $post->post_type ) . "' title='" . esc_attr( __('Edit XWidgets', 'xwidgets') ) . "'>" . __('XWidgets', 'xwidgets') . "</a>";
		return $actions;
	}

	/**
	 * Upgrade the options and database from the current version to the version in the code.
	 * @param $current_version : Current version number to upgrade from
	 * @return bool : True for success, False if failed.
	 */
	private function upgrade_from( $current_version ) {
		global $wpdb;
		switch( $current_version ) { // Always falling through
			case '0': // Never installed or Old version installed
				if ( false === $wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = CONCAT('_', meta_key) WHERE meta_key LIKE 'xwidgets%'" ) ) return false;
			case '2.0':
			default:
		}
		return update_option( XWIDGETS_VERSION_OPTION_NAME, XWIDGETS_VERSION );
	}

	/**
	 * Adds 'Manage X-Widgets' to the capabilities list
	 *
	 * @param array $caps
	 * @return array capabilities with 'Manage X Widgets' added
	 */
	public function filter_capabilities_list($caps) {
		$caps[] = XWIDGETS_CAPABILITY_NAME;
		return $caps;
	}

	/**
	 * Checks the current user's capabilities
	 *
	 * @return boolean
	 */
	public function check_capabilities(){
		if ( class_exists("IWG_RoleManagement") ) {
			return current_user_can( XWIDGETS_CAPABILITY_NAME );
		}
		return current_user_can( 'edit_pages' ) && current_user_can( 'edit_posts' );
	}

	/**
	 * Sets the inheritance setting for a page's sidebar
	 * When inheritance is enabled, a page will take its sidebar configuration from its parent.
	 *
	 * @param int $post_id
	 * @param bool $checked, 1 - enable, 0 - disable
	 * @return bool False on failure, true if success.
	 */
	private function set_inherit($post_id, $checked = true) {
		return update_post_meta( (int) $post_id, XWIDGETS_POSTMETA_NAME_INHERIT, ($checked ? 1 : 0) );
	}

	/**
	 * Gets the inheritance setting for a page's sidebar
	 * When inheritance is enabled, a page will take its sidebar configuration from its parent.
	 *
	 * @param int $post_id
	 * @return mixed result, will be 1 or 0, 1 - enabled, 0 - disabled
	 */
	public function is_inheriting($post_id) {
		return get_post_meta( (int) $post_id, XWIDGETS_POSTMETA_NAME_INHERIT, true ) == 1;
	}

	/**
	 * {{@internal Missing Short Description}}}
	 *
	 * @return bool False if not using global widgets, True if using global widgets.
	 */
	public function use_global_when_no_widgets_on_page() {
		// Do not show global widgets when editing widgets of a post/page (in admin)
		if ( is_admin() )	{
			return false;
		}

		return get_option( XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY, 1 ) == 1;
	}

	/**
	 * This is called when a page is deleted. If children of the page were inheriting from it, the inheritance should be removed.
	 *
	 * @param int $post_id
	 */
	function action_verify_inheritance_after_delete($post_id) {
		$post_id=(int) $post_id;

		// If the page being deleted is also inheriting, do nothing
		if ( ! $this->is_inheriting($post_id) ){
			$children = get_children("post_parent=$post_id&post_type=page");

			if ( is_array($children) && count($children) > 0 ) {
				foreach ($children as $child){
					$this->set_inherit($child->ID, false);
				}
			}
		}
	}

	/**
	 * Gets the post ID of the correct page to use with functions like widget_get_option, widget_update_option, etc
	 * Recursive to account for inheritance on multiple generations
	 * Get post_id to be used for the template, 0 if global
	 *
	 * @param int $post_id
	 * @return mixed result, will be the parent
	 */
	private function get_page_id_with_inheritance($post_id) {
		$post_id=(int) $post_id;

		if ( $this->is_inheriting($post_id) ) {
			$post= &get_post($post_id);
			$parent_id= $post->post_parent;

			if ( !empty($parent_id) ) {
				return $this->get_page_id_with_inheritance($parent_id);
			}
			return 0;
		}

		return $post_id;
	}

	private function get_edit_url( $post_id= false, $post_type= 'post', $absolute= true ) {
		if ( false === $post_id ) {
			$post_id= $this->get_id();
		}
		$base= 'widgets.php';
		if ( $absolute ) {
			admin_url( $base );
		}
		return $base.'?'.XWIDGETS_ID_PARAM_NAME.'='.$post_id.'&'.XWIDGETS_TYPE_PARAM_NAME.'='.$post_type;
	}

	/**
	 * Display the "Configure widgets for this page" link
	 */
	public function action_display_xwidgets() {
		if ( $this->check_capabilities() ){
			global $post;
			if ( isset($post) && !empty($post->ID) ) {
				$post_id= $post->ID;
				$post_type= $post->post_type;
				?>
<div id="xwidgets" class="postbox">
	<h3 id="xwidgets_sidebar_title">XWidgets</h3>
	<div class="inside"><a href="<?php echo $this->get_edit_url($post_id, $post_type); ?>">Configure widgets for this page</a></div>
</div>
				<?php
			}
		}
	}

	/**
	 * Update the widget configuration of a page
	 *
	 * @return bool False for failure. True for success.
	 */
	function update($xwidgets, $post_id) {
		if (sizeof($xwidgets) < 1) {
			return delete_post_meta( $post_id, XWIDGETS_POSTMETA_NAME_SIDEBARS_WIDGETS );
		}

		$xwidgets= serialize($xwidgets);
		$result= update_post_meta( $post_id, XWIDGETS_POSTMETA_NAME_SIDEBARS_WIDGETS, $xwidgets );

		if ( !$result ) {
			$result=add_post_meta( $post_id, XWIDGETS_POSTMETA_NAME_SIDEBARS_WIDGETS, $xwidgets, true );
		}

		return $result;
	}

	public function action_init() {
		load_plugin_textdomain( 'xwidgets', false, 'xwidgets/languages' );
	}

	/**
	 * Get the post/page id depending if we are in the admin, in the front end (within the loop or not)
	 *
	 * @return int $post_id or False if not found
	 */
	private function get_id(){
		if ( !is_admin() && !$this->wp_initialized ){
			return false;
		}

		if ( isset($this->id) && $this->id !== false ) {
			return $this->id;
		}

		$this->id= false;

		if ( is_admin() ) {
			if ( isset($_REQUEST[XWIDGETS_ID_PARAM_NAME]) ) {
				$this->id = (int)$_REQUEST[XWIDGETS_ID_PARAM_NAME];
				$this->type= $_REQUEST[XWIDGETS_TYPE_PARAM_NAME];
			}
		} else {
			if ( is_page() || is_single() ) {
				// We are in the loop
				global $post;
				$this->id = $post->ID;
				$this->type= $post->post_type;
			}
		}

		return $this->id;
	}

	/**
	 * Used to update options for widgets on specific pages.
	 * The core patch calls this function if the option being updated starts with "widget_"
	 *
	 * @param string $option_name
	 * @param string $option_value
	 * @param int $post_id
	 * @return mixed result
	 */
	function widget_update_option($option_name, $option_value, $post_id= 0) {
		if ( 0 == $post_id ) {
			$post_id= $this->get_id();
		}

		if ( false === $post_id ) {
			// Continue with global widgets option
			return false;
		}

		wp_protect_special_option( $option_name );

		$old_value= $this->widget_get_option( $option_name, $post_id );

		if ( $old_value == $option_value ) {
			return true;
		}

		$result= update_post_meta( $post_id, XWIDGETS_POSTMETA_NAME_WIDGET_PREFIX.$option_name, $option_value );

		return true;
	}

	/**
	 * Used to get options for widgets on specific pages.
	 * The core patch calls this function if the option being retrieved starts with "widget_"
	 *
	 * @param string $option_name
	 * @param int $post_id
	 * @return mixed result, the value of the option
	 */
	function widget_get_option($option_name,$post_id = 0) {
		if ( !is_admin() && ! $this->wp_initialized ) {
			return array(); // As long as WP is not initialized let the core work with empty sidebars widgets.
		}
		if ( 0 == $post_id ) {
			$post_id= $this->get_id();
		}
		
		if ( false === $post_id ) {
			// Continue with global widgets option
			return false;
		}

		// We don't want to load parent's widgets when in the admin.
		if ( ! is_admin() ) {
			// Make sure to take into account inheriting
			$post_id = $this->get_page_id_with_inheritance($post_id);
		}

		if ( $this->has_no_widgets ) {
			// Continue with global widgets (sidebars_widgets option)
			return false;
		}

		$data= get_post_meta( $post_id, XWIDGETS_POSTMETA_NAME_WIDGET_PREFIX.$option_name, true );

		if ( !is_array($data) ) {
			$data= array();
		}

		return $data;
	}


	/**
	 * Used to delete options for widgets on specific pages.
	 * The core patch calls this function if the option being deleted starts with "widget_"
	 *
	 * @param string $option_name
	 * @param int $post_id
	 * @return mixed result
	 */
	function widget_delete_option($option_name, $post_id= 0) {
		if ( $post_id == 0 ) {
			$post_id= $this->get_id();
		}

		if ( $post_id === false ) {
			// Continue with global widget option
			return false;
		}

		// We do not care if the delete worked or not. We only want it to be deleted in the page
		delete_post_meta($post_id, XWIDGETS_POSTMETA_NAME_WIDGET_PREFIX.$option_name);

		return true;
	}

	/**
	 * Used to delete options for widgets on all pages and posts.
	 *
	 * @param string $option_name
	 * @return bool true if it workes, false if it didn't work
	 */
	function delete_widget_instances( $option_name ) {
		return delete_post_meta_by_key( XWIDGETS_POSTMETA_NAME_WIDGET_PREFIX.$option_name );
	}

	/**
	 * {{@internal Missing Short Description}}}
	 *
	 * @param $newvalue
	 * @param $oldvalue
	 * @return string $newvalue to make the update option contine, $oldvalue to make it stops
	 */
	function filter_update_option_sidebars_widgets( $newvalue, $oldvalue ) {
		$post_id= $this->get_id();

		// Taking into account inheriting
		if ( false !== $post_id && $this->update($newvalue, $post_id) ) {
			return $oldvalue;
		}

		return $newvalue; // return newvalue so that the update_option function continues after the filter.
	}

	/**
	 * {{@internal Missing Short Description}}}
	 */
	function filter_get_option_sidebars_widgets() {
		if ( ! is_admin() && ! $this->wp_initialized ) {
			return array(); // As long as WP is not initialized let the core work with empty sidebars widgets.
		}
		$post_id= $this->get_id();

		// No post or page found - continue with global widgets.
		if ( false === $post_id ) {
			return false;
		}

		// We don't want to load parent's widgets when in the admin.
		if ( ! is_admin() ) {
			// Taking into account inheriting
			$post_id = $this->get_page_id_with_inheritance( $post_id );
		}

		$meta= get_post_meta( $post_id, '_xwidgets', true );
		// Need this since get_post_meta doesn't work in the same way than get_option.
		if ( ! is_array($meta) ) {
			$meta= maybe_unserialize($meta);
		}

		$this->has_no_widgets= true;
		if ( is_array($meta) ) {
			// Check if one of the sidebars contains a widget.
			$meta_tmp = $meta;
			unset( $meta_tmp['wp_inactive_widgets'], $meta_tmp['array_version'] );

			foreach( $meta_tmp as $sidebar_widgets ) {
				if ( !empty($sidebar_widgets) ) {
					$this->has_no_widgets= false;
					break;
				}
			}
		} else {
			$meta= array();
		}

		if ( $this->has_no_widgets && $this->use_global_when_no_widgets_on_page() )	{
			return false; // Continue with global widgets
		}

		return $meta;
	}

	function action_print_html(){
		$post_id= $this->get_id();
		if ( false !== $post_id  ) {
			$post= get_post($post_id);
			if ( isset($post) ) {
				?>
<div id="xwidgets-info" style="display: none; margin-left: 1.5em;">
<h4 style="display: inline"><?php echo $post->post_title ?></h4>
&nbsp;[#<?php echo $post_id ?>] <a class="button" href="<?php echo get_permalink($post_id); ?>">View</a> <a class="button" href="<?php echo get_edit_post_link($post_id); ?>">Back to page edit</a> <?php if ($post->post_type == 'page'): ?>
<p><input type="checkbox" name="_xwidgets-inherit" id="xwidgets-inherit" /> <label for="xwidgets-inherit"><?php _e( 'Inherit widgets from parent page/post (Your widgets won\'t be lost)', 'xwidgets' )?></label>
&nbsp;<img src="images/wpspin_light.gif" class="ajax-feedback" title="" alt="" /></p>
<?php endif; ?> <?php if (!isset($_GET['message'])) : /* Add the message id if not existing */ ?>
<div id=message style="display: none;"></div>
<?php endif; ?></div>
<form action="" method="post"><?php wp_nonce_field( 'xwidgets_save_inherit', '_wpnonce_xwidgets', false ); ?></form>
<?php
			}
		}
	}

	function action_print_script(){
		$post_id= $this->get_id();
		if ( false !== $post_id ) {
			?>
<script type="text/javascript">
//<![CDATA[
 // Copied from wpAjax.unserialize from wp-includes/js/wp-ajax-response.js
	function xwidgets_unserialize(s) {
		var r = {}, q, pp, i, p;
		if ( !s ) { return r; }
		q = s.split('?'); if ( q[1] ) { s = q[1]; }
		pp = s.split('&');
		for ( i in pp ) {
			if ( jQuery.isFunction(pp.hasOwnProperty) && !pp.hasOwnProperty(i) ) { continue; }
			p = pp[i].split('=');
			r[p[0]] = p[1];
		}
		return r;
	}
	function xwidgets_toggle_widgets_boxes(){
		jQuery("div.widget-liquid-left, div.widget-liquid-right").toggle();
	}
	function xwidgets_show_message(message, type) {
		if (typeof xwidgetsMessageTimeout != 'undefined') {
			clearTimeout(xwidgetsMessageTimeout);
		}
		var elMessage= jQuery("#message");
		elMessage.html(message);
		xwidgetsMessageTimeout= setTimeout(function() { jQuery("#message").hide("slow"); }, 10000);

		if (type == 'error') {
			elMessage.removeClass("updated").addClass("error").show("slow");
		} else {
			elMessage.removeClass("error").addClass("updated").show("slow");
		}

		jQuery("#xwidgets-info .ajax-feedback").css('visibility', 'hidden');
		jQuery("#xwidgets-inherit").removeAttr('disabled');
	}

	function xwidgets_ajax_error() {
		xwidgets_show_message("<?php echo esc_js( __( 'Error connecting to the server.' , 'xwidgets' ) ) ?>", "error");
		var sentData= xwidgets_unserialize(this.data);
		if (sentData.inherit) {
			jQuery("#xwidgets-inherit").removeAttr("checked");
		} else {
			jQuery("#xwidgets-inherit").attr("checked", "checked");
		}
	}
	function xwidgets_inheritance_saved(data) {
		if (data.error) {
			var sentData= xwidgets_unserialize(this.data);
			if (sentData.inherit) {
				jQuery("#xwidgets-inherit").removeAttr("checked");
			} else {
				jQuery("#xwidgets-inherit").attr("checked", "checked");
			}
			xwidgets_show_message(data.message, "error");
		} else {
			xwidgets_show_message(data.message, "updated");
			xwidgets_toggle_widgets_boxes();
		}
	}

	jQuery(document).ready(function () {
		// Removing current css class on the global Widgets menu item.
		jQuery('#menu-appearance .current').removeClass('current')
	<?php if ( $this->is_inheriting($post_id) ): ?>
		jQuery("#xwidgets-inherit").attr("checked", "checked");
		xwidgets_toggle_widgets_boxes();
	<?php endif; ?>
		jQuery("#wpbody-content div.wrap:first h2:first").append('&nbsp;|&nbsp;<small><?php echo __( 'For a', 'xwidgets' ).' '.ucfirst($this->type); ?></small>');
		jQuery("#xwidgets-info").insertAfter(jQuery("#wpbody-content div.wrap:first h2:first"));
		jQuery("#xwidgets-info").show();

		jQuery("#xwidgets-inherit").click(function(){
			jQuery("#xwidgets-inherit").attr('disabled', 'disabled');
			jQuery("#xwidgets-info .ajax-feedback").css('visibility', 'visible');
			var ajaxData= {
				action: "xwidgets_save_inherit",
				post_id: <?php echo $post_id; ?>,
				inherit: jQuery("#xwidgets-inherit").is(":checked"),
				_ajax_nonce: jQuery('#_wpnonce_xwidgets').val()
			};
			jQuery.ajax({
				type: 'POST',
				url : ajaxurl,
				data: ajaxData,
				success: xwidgets_inheritance_saved,
				error: xwidgets_ajax_error,
				dataType : 'json'
			});
		});
	});
//]]>
</script>
	<?php
		}
	}
	/**
	 * Ajax call handler for saving the inheritance of a page.
	 * Response :
	 * 	- Type : JSON
	 * 	- Content :
	 * 		* error : Boolean
	 * 		* message : Error or Success message.
	 */
	function action_ajax_save_inherit(){
		if ( ! check_ajax_referer('xwidgets_save_inherit', false, false) ) {
			$message= __( 'Couldn\'t check the referer. Is your session expired?', 'xwidgets' );
			die( json_encode( array( 'error' => true, 'message' => $message ) ) );
		}

		$post_id= (int)$_REQUEST[XWIDGETS_ID_PARAM_NAME];
		$post_type= $_REQUEST[XWIDGETS_TYPE_PARAM_NAME];
		// TODO : Get object depending on the type
		$post= &get_post($post_id);

		$actionCancelledMessage= __( '<strong>Action Cancelled.</strong><br/>Error while saving inheritance.', 'xwidgets' ).'&nbsp;';

		if ( 'post' == $post->post_type ) {
			$message= $actionCancelledMessage.__( 'Posts can\'t have parent, so they cannot inherit.', 'xwidgets' );
			die( json_encode( array( 'error' => true, 'message' => $message ) ) );
		} else {
			if ( !current_user_can('edit_page', $post_id) ) {
				$message= $actionCancelledMessage.__( 'You are not allowed to edit this page.', 'xwidgets' );
				die( json_encode( array( 'error' => true, 'message' => $message ) ) );
			}
		}

		$inherit= 'true' == $_POST['inherit'];
		if ( $inherit && empty($post->post_parent) ) {
			$message= $actionCancelledMessage.__('You need to specify a parent first.', 'xwidgets');
			die( json_encode( array( 'error' => true, 'message' => $message ) ) );
		}

		if ( ! $this->set_inherit( $_POST['post_id'], $inherit ) ) {
			die( json_encode( array( 'error' => true, 'message' => $actionCancelledMessage ) ) );
		}

		if ( $inherit ) {
			$message= __( 'Updated inheritance. (Inheriting)', 'xwidgets' );
		} else {
			$message= __( 'Updated inheritance. (Not Inheriting)', 'xwidgets' );
		}

		die( json_encode( array( 'error' => false, 'message' => $message ) ) );
	}


	/**
	 * {{@internal Missing Short Description}}}
	 *
	 * @uses filter_admin_url_ajax
	 */
	function action_admin_load_widgets_interface() {
		global $submenu_file;
		$post_id= $this->get_id();
		if ( false !== $post_id ) {
			if ( $this->type == 'post' ) {
				$submenu_file= 'edit.php';
			}	else if ( $this->type == 'page' ) {
				$submenu_file= 'edit-pages.php';
			}

			add_filter('admin_url', array($this, 'filter_admin_url_ajax'), 10, 2);
			add_action('admin_head', array($this, 'action_print_script'));
			add_action('admin_footer', array($this, 'action_print_html'));
		}
	}

	/**
	 * {{@internal Missing Short Description}}}
	 *
	 * @see action_admin_load_widgets_interface
	 */
	function filter_admin_url_ajax($url, $path) {
		$post_id= $this->get_id();
		if ( false !== $post_id ) {
			if ($path === 'admin-ajax.php') {
				return add_query_arg( XWIDGETS_ID_PARAM_NAME, $post_id, add_query_arg(XWIDGETS_TYPE_PARAM_NAME, $this->type, $url ) );
			}
		}
		return $url;
	}

	/**
	 * Creates filters to intercept widgets' options. Get the widget names from the Widget factory.
	 * TODO : Closures
	 *
	 * @global $wp_widget_factory
	 */
	function action_register_option_listener() {
		global $wp_widget_factory;
		$this->widgets = (array)$wp_widget_factory->widgets;

		foreach( $this->widgets as $wid ) {
			$get_option_func= create_function('', 'return XWidgets::getInstance()->widget_get_option("'.$wid->option_name.'");');
			add_filter("pre_option_$wid->option_name", $get_option_func);

			// return newvalue so that the update_option function continue after the filter.
			$update_option_func= create_function('$newvalue, $oldvalue', 'if (XWidgets::getInstance()->widget_update_option("'.$wid->option_name.'", $newvalue)) { return $oldvalue;	}	return $newvalue;');
			add_filter("pre_update_option_$wid->option_name", $update_option_func, 1, 2);

			$delete_option_func= create_function('', 'return XWidgets::getInstance()->widget_delete_option("'.$wid->option_name.'");');
			add_filter("pre_delete_option_$wid->option_name", $delete_option_func);
		}

		if ( is_admin() ) {
			$wp_widget_factory->_register_widgets();
		}
	}
}