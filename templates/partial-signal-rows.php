<?php
/**
 * Shared signal list: every row expands into the full story — what
 * happened, where (file:line), who (component + version), when (first/last
 * seen + count), what it means and what to do.
 *
 * @package Ravnsight\Detective
 * @var array $ravndet_signals_rows Signal rows.
 */

defined( 'ABSPATH' ) || exit;

use Ravnsight\Detective\Support\SignalInfo;
?>
<div class="ravndet-signals">
	<?php if ( empty( $ravndet_signals_rows ) ) : ?>
		<p><?php esc_html_e( 'Nothing recorded yet. Errors and changes will appear here as they happen.', 'ravnsight-detective' ); ?></p>
	<?php else : ?>
		<?php foreach ( $ravndet_signals_rows as $ravndet_row ) : ?>
			<?php
			$ravndet_context  = json_decode( (string) $ravndet_row->context, true );
			$ravndet_guide    = SignalInfo::guidance( $ravndet_row->type );
			$ravndet_file     = isset( $ravndet_context['file'] ) ? (string) $ravndet_context['file'] : '';
			$ravndet_line     = isset( $ravndet_context['line'] ) ? (int) $ravndet_context['line'] : 0;
			$ravndet_is_error = 0 === strpos( (string) $ravndet_row->type, 'error.' );
			?>
			<details class="ravndet-signal ravndet-severity-<?php echo esc_attr( $ravndet_row->severity ); ?>">
				<summary>
					<span class="ravndet-signal-badge ravndet-badge-<?php echo esc_attr( $ravndet_row->severity ); ?>"><?php echo esc_html( SignalInfo::label( $ravndet_row->type ) ); ?></span>
					<span class="ravndet-signal-msg"><?php echo esc_html( wp_html_excerpt( (string) $ravndet_row->message, 110, '…' ) ); ?></span>
					<span class="ravndet-signal-meta">
						<?php if ( $ravndet_row->component_id ) : ?><code><?php echo esc_html( $ravndet_row->component_id ); ?></code><?php endif; ?>
						<?php if ( (int) $ravndet_row->count > 1 ) : ?>
							<span class="ravndet-count" title="<?php esc_attr_e( 'Number of occurrences grouped into this row', 'ravnsight-detective' ); ?>">×<?php echo esc_html( number_format_i18n( (int) $ravndet_row->count ) ); ?></span>
						<?php endif; ?>
						<span class="ravndet-when"><?php echo esc_html( human_time_diff( (int) $ravndet_row->last_seen ) ); ?></span>
					</span>
				</summary>
				<div class="ravndet-signal-detail">
					<p class="ravndet-full-message"><code><?php echo esc_html( (string) $ravndet_row->message ); ?></code></p>

					<table class="ravndet-facts">
						<?php if ( $ravndet_file ) : ?>
							<tr>
								<th><?php esc_html_e( 'Where', 'ravnsight-detective' ); ?></th>
								<td><code><?php echo esc_html( $ravndet_file ); ?><?php echo $ravndet_line ? ':' . esc_html( (string) $ravndet_line ) : ''; ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( $ravndet_row->component_id ) : ?>
							<tr>
								<th><?php esc_html_e( 'Caused by', 'ravnsight-detective' ); ?></th>
								<td>
									<code><?php echo esc_html( $ravndet_row->component_id ); ?></code>
									(<?php echo esc_html( $ravndet_row->component_type ); ?><?php echo $ravndet_row->component_version ? ' ' . esc_html( $ravndet_row->component_version ) : ''; ?>)
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'First seen', 'ravnsight-detective' ); ?></th>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $ravndet_row->first_seen ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Last seen', 'ravnsight-detective' ); ?></th>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $ravndet_row->last_seen ) ); ?>
								<?php if ( (int) $ravndet_row->count > 1 ) : ?>
									— <?php
									/* translators: %s: number of occurrences. */
									echo esc_html( sprintf( __( '%s occurrences grouped into this row', 'ravnsight-detective' ), number_format_i18n( (int) $ravndet_row->count ) ) );
									?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $ravndet_row->scope ) : ?>
							<tr>
								<th><?php esc_html_e( 'On request', 'ravnsight-detective' ); ?></th>
								<td><code><?php echo esc_html( $ravndet_row->scope ); ?></code></td>
							</tr>
						<?php endif; ?>
					</table>

					<?php if ( null === $ravndet_row->resolved_detected && 0 !== strpos( (string) $ravndet_row->type, 'change.' ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ravndet-resolve">
							<?php wp_nonce_field( 'ravndet_admin' ); ?>
							<input type="hidden" name="action" value="ravndet_resolve" />
							<input type="hidden" name="signal_id" value="<?php echo esc_attr( (string) $ravndet_row->id ); ?>" />
							<strong><?php esc_html_e( 'Mark as resolved', 'ravnsight-detective' ); ?></strong>
							<label>
								<?php esc_html_e( 'What fixed it?', 'ravnsight-detective' ); ?>
								<select name="fix_type">
									<option value="updated"><?php esc_html_e( 'Updated', 'ravnsight-detective' ); ?></option>
									<option value="deactivated"><?php esc_html_e( 'Deactivated', 'ravnsight-detective' ); ?></option>
									<option value="rolled_back"><?php esc_html_e( 'Rolled back', 'ravnsight-detective' ); ?></option>
									<option value="config"><?php esc_html_e( 'Config change', 'ravnsight-detective' ); ?></option>
									<option value="other"><?php esc_html_e( 'Other', 'ravnsight-detective' ); ?></option>
								</select>
							</label>
							<label>
								<?php esc_html_e( 'Caused by', 'ravnsight-detective' ); ?>
								<input type="text" name="actual_component" value="<?php echo esc_attr( (string) $ravndet_row->component_id ); ?>" />
							</label>
							<?php  ?>
							<?php if ( ! get_option( 'ravndet_share_outcomes' ) ) : ?>
								<label class="ravndet-share">
									<input type="checkbox" name="share_outcome" value="1" />
									<?php esc_html_e( 'Share this outcome anonymously with Ravnsight to improve diagnoses (error type, component and fix only — never your site identity)', 'ravnsight-detective' ); ?>
								</label>
							<?php endif; ?>
							<?php  ?>
							<button class="button"><?php esc_html_e( 'Save', 'ravnsight-detective' ); ?></button>
						</form>
					<?php elseif ( null !== $ravndet_row->resolved_detected ) : ?>
						<p class="ravndet-resolved-note"><?php esc_html_e( 'Marked as resolved.', 'ravnsight-detective' ); ?></p>
					<?php endif; ?>
					<?php $ravndet_corr = \Ravnsight\Detective\Support\Correlator::analyze( $ravndet_row ); ?>
					<?php if ( null !== $ravndet_corr ) : ?>
						<div class="ravndet-correlation ravndet-correlation-<?php echo esc_attr( $ravndet_corr['confidence'] ); ?>">
							<strong><?php esc_html_e( 'Likely cause', 'ravnsight-detective' ); ?> — <?php echo esc_html( \Ravnsight\Detective\Support\Correlator::confidence_label( $ravndet_corr['confidence'] ) ); ?></strong>
							<ul>
								<?php foreach ( $ravndet_corr['lines'] as $ravndet_corr_line ) : ?>
									<li class="ravndet-corr-<?php echo esc_attr( '+' === $ravndet_corr_line['sign'] ? 'plus' : ( '-' === $ravndet_corr_line['sign'] ? 'minus' : 'alt' ) ); ?>"><?php echo esc_html( $ravndet_corr_line['sign'] . ' ' . $ravndet_corr_line['text'] ); ?></li>
								<?php endforeach; ?>
							</ul>
							<em><?php esc_html_e( 'Correlation, not proven cause.', 'ravnsight-detective' ); ?></em>
						</div>
					<?php endif; ?>
					<?php if ( $ravndet_guide['what'] ) : ?>
						<div class="ravndet-guidance">
							<p><strong><?php esc_html_e( 'What this means', 'ravnsight-detective' ); ?></strong><br><?php echo esc_html( $ravndet_guide['what'] ); ?></p>
							<p><strong><?php esc_html_e( 'What to do', 'ravnsight-detective' ); ?></strong><br><?php echo esc_html( $ravndet_guide['action'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $ravndet_is_error && $ravndet_row->component_id && 'plugin' === $ravndet_row->component_type ) : ?>
						<p class="ravndet-copy-hint"><?php esc_html_e( 'Reporting this to the developer? Copy the full message and the Where line above — that is the exact information they need.', 'ravnsight-detective' ); ?></p>
					<?php endif; ?>
				</div>
			</details>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
