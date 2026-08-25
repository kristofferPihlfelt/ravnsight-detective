<?php
/**
 * Settings template.
 *
 * @package Ravnsight\Detective
 * @var int   $retention           Retention days.
 * @var array $flags               Module flags.
 * @var bool  $delete_on_uninstall Delete data on uninstall.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ravndet-wrap">
	<h1><?php esc_html_e( 'Settings', 'ravnsight-detective' ); ?></h1>

	<?php if ( isset( $_GET['ravndet_notice'] ) && 'saved' === $_GET['ravndet_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice code. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ravnsight-detective' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ravndet_admin' ); ?>
		<input type="hidden" name="action" value="ravndet_save_settings">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="retention_days"><?php esc_html_e( 'Keep history for', 'ravnsight-detective' ); ?></label></th>
				<td>
					<input type="number" name="retention_days" id="retention_days" value="<?php echo esc_attr( (string) $retention ); ?>" min="1"
						<?php  ?> max="7" <?php  ?>
						<?php  ?>
						class="small-text">
					<?php esc_html_e( 'days', 'ravnsight-detective' ); ?>
					<p class="description">
						<?php  ?><?php esc_html_e( 'Signals older than this are removed daily. History is kept up to 7 days.', 'ravnsight-detective' ); ?><?php  ?>
						<?php  ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modules', 'ravnsight-detective' ); ?></th>
				<td>
					<div>
						<label><input type="checkbox" name="module_error_detective" <?php checked( $flags['error_detective'] ); ?>>
						<?php esc_html_e( 'Error Detective — record and group PHP errors', 'ravnsight-detective' ); ?></label>
					</div>
					<div>
						<label><input type="checkbox" name="module_change_detective" <?php checked( $flags['change_detective'] ); ?>>
						<?php esc_html_e( 'Change Detective — record plugin/theme/core changes and daily snapshots', 'ravnsight-detective' ); ?></label>
					</div>
					<p class="description"><?php esc_html_e( 'A disabled module records nothing; existing data stays until retention removes it.', 'ravnsight-detective' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'ravnsight-detective' ); ?></th>
				<td>
					<label><input type="checkbox" name="delete_data_on_uninstall" <?php checked( $delete_on_uninstall ); ?>>
					<?php esc_html_e( 'Delete all recorded data when the plugin is uninstalled', 'ravnsight-detective' ); ?></label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'ravnsight-detective' ) ); ?>
	</form>
</div>
