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
	<?php if ( isset( $_GET['ravndet_notice'] ) && 'dropin_installed' === $_GET['ravndet_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice code. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Drop-in installed.', 'ravnsight-detective' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['ravndet_notice'] ) && 'dropin_removed' === $_GET['ravndet_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice code. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Drop-in removed.', 'ravnsight-detective' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['ravndet_notice'] ) && 'dropin_foreign' === $_GET['ravndet_notice'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice code. ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Another fatal-error-handler.php already exists — Ravnsight Detective will not overwrite it.', 'ravnsight-detective' ); ?></p></div>
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
					<div>
						<label><input type="checkbox" name="module_perf_detective" <?php checked( $flags['perf_detective'] ); ?>>
						<?php esc_html_e( 'Performance Detective — record slow requests, slow queries and high memory use', 'ravnsight-detective' ); ?></label>
					</div>
					<div>
						<label><input type="checkbox" name="module_js_detective" <?php checked( $flags['js_detective'] ); ?>>
						<?php esc_html_e( 'JavaScript Detective — record front-end JS errors (reports to this site\'s own REST API, never externally)', 'ravnsight-detective' ); ?></label>
					</div>
					<div>
						<label><input type="checkbox" name="module_mail_detective" <?php checked( $flags['mail_detective'] ); ?>>
						<?php esc_html_e( 'Mail Detective — record failed outgoing e-mail with the real transport error', 'ravnsight-detective' ); ?></label>
					</div>
					<p class="description"><?php esc_html_e( 'A disabled module records nothing; existing data stays until retention removes it.', 'ravnsight-detective' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Deep query profiling', 'ravnsight-detective' ); ?></th>
				<td>
					<label><input type="checkbox" name="savequeries" <?php checked( $savequeries ); ?>>
					<?php esc_html_e( 'Enable SAVEQUERIES — slow-request signals then include the exact slow query shapes', 'ravnsight-detective' ); ?></label>
					<p class="description"><?php esc_html_e( 'WordPress then keeps every query of a request in memory. Fine for a debugging period; turn it off on very high-traffic sites when you are done.', 'ravnsight-detective' ); ?></p>
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

	<h2><?php esc_html_e( 'Early fatal capture', 'ravnsight-detective' ); ?></h2>
	<?php if ( 'ours' === $dropin_status ) : ?>
		<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> <?php esc_html_e( 'The fatal-error-handler drop-in is installed. Fatals that happen before plugins load are recorded too.', 'ravnsight-detective' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ravndet_admin' ); ?>
			<input type="hidden" name="action" value="ravndet_dropin">
			<input type="hidden" name="dropin_action" value="uninstall">
			<button type="submit" class="button"><?php esc_html_e( 'Remove the drop-in', 'ravnsight-detective' ); ?></button>
		</form>
	<?php elseif ( 'foreign' === $dropin_status ) : ?>
		<p><?php esc_html_e( 'Another fatal-error-handler.php exists in wp-content. Ravnsight Detective never overwrites it — early fatals are captured by that handler instead.', 'ravnsight-detective' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Optional. Catches fatal errors that happen before plugins load (for example a parse error in another plugin). A single file in wp-content, safe to delete at any time, always hands over to the WordPress core handler.', 'ravnsight-detective' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ravndet_admin' ); ?>
			<input type="hidden" name="action" value="ravndet_dropin">
			<input type="hidden" name="dropin_action" value="install">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Install the drop-in', 'ravnsight-detective' ); ?></button>
		</form>
	<?php endif; ?>
</div>
