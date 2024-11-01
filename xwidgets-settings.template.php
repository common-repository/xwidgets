<?php
/**
 * XWidgets File
 *	Admin interface
 *
 *	@author: Bank of Canada (Benjamin Boudreau<benjamin@bboudreau.ca>, Jules Delisle, Dale Taylor, Nelson Lai, Jason Manion, Nicholas Crawford, Jairus Pryor)
 *	@version: 2.2
 *	@package xwidgets
 */

if ( ! XWidgets::getInstance()->check_capabilities() ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.', 'xwidgets' ) );
}

?>
<div class="wrap">
<div id="icon-options-general" class="icon32"><br/></div>
<h2><?php _e( 'XWidgets Settings', 'xwidgets' ); ?></h2>

<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>

	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Use global widgets', 'xwidgets' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php _e( 'Use global widgets', 'xwidgets' ); ?></span>
					</legend>
					<label for="<?php echo XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY ?>">
						<input id="<?php echo XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY ?>" <?php checked( get_option(XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY), 1 ) ?> value="1" type="checkbox" name="<?php echo XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY ?>" /><?php _e('When no widgets on page.', 'xwidgets') ?>
					</label>
				</fieldset>
			</td>
		</tr>
	</table>

	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="<?php echo XWIDGETS_OPTION_NAME_USE_GLOBAL_WHEN_EMPTY ?>" />

	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>

</form>
</div>