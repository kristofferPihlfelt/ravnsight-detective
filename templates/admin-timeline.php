<?php
/**
 * Timeline template.
 *
 * @package Ravnsight\Detective
 * @var array  $signals         Rows.
 * @var string $filter_type     '' | error | change.
 * @var string $filter_severity '' | info | warning | critical.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ravndet-wrap">
	<h1><?php esc_html_e( 'Timeline', 'ravnsight-detective' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Errors and changes on one axis. When something breaks, look at what changed right before.', 'ravnsight-detective' ); ?></p>

	<form method="get" class="ravndet-filters">
		<input type="hidden" name="page" value="ravnsight-detective-timeline">
		<select name="signal_type">
			<option value=""><?php esc_html_e( 'Everything', 'ravnsight-detective' ); ?></option>
			<option value="error" <?php selected( $filter_type, 'error' ); ?>><?php esc_html_e( 'Errors', 'ravnsight-detective' ); ?></option>
			<option value="change" <?php selected( $filter_type, 'change' ); ?>><?php esc_html_e( 'Changes', 'ravnsight-detective' ); ?></option>
		</select>
		<select name="severity">
			<option value=""><?php esc_html_e( 'All severities', 'ravnsight-detective' ); ?></option>
			<?php foreach ( array( 'critical', 'warning', 'info' ) as $ravndet_sev ) : ?>
				<option value="<?php echo esc_attr( $ravndet_sev ); ?>" <?php selected( $filter_severity, $ravndet_sev ); ?>><?php echo esc_html( ucfirst( $ravndet_sev ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button"><?php esc_html_e( 'Filter', 'ravnsight-detective' ); ?></button>
	</form>

	<?php
	// Group identical problem classes (same type + component): 300 core
	// deprecation warnings must be ONE expandable group, not 300 rows.
	$ravndet_groups = array();
	foreach ( $signals as $ravndet_signal_row ) {
		$ravndet_groups[ $ravndet_signal_row->type . '|' . (string) $ravndet_signal_row->component_id ][] = $ravndet_signal_row;
	}
	?>
	<?php foreach ( $ravndet_groups as $ravndet_group_rows ) : ?>
		<?php if ( count( $ravndet_group_rows ) === 1 ) : ?>
			<?php $ravndet_signals_rows = $ravndet_group_rows; require RAVNDET_PATH . 'templates/partial-signal-rows.php'; ?>
		<?php else : ?>
			<?php
			$ravndet_group_first = $ravndet_group_rows[0];
			$ravndet_group_total = 0;
			foreach ( $ravndet_group_rows as $ravndet_group_row ) {
				$ravndet_group_total += (int) $ravndet_group_row->count;
			}
			?>
			<details class="ravndet-signal ravndet-severity-<?php echo esc_attr( $ravndet_group_first->severity ); ?>">
				<summary>
					<span class="ravndet-signal-badge ravndet-badge-<?php echo esc_attr( $ravndet_group_first->severity ); ?>"><?php echo esc_html( \Ravnsight\Detective\Support\SignalInfo::label( $ravndet_group_first->type ) ); ?></span>
					<span class="ravndet-signal-msg">
						<?php if ( ! empty( $ravndet_group_first->component_id ) ) : ?><code><?php echo esc_html( (string) $ravndet_group_first->component_id ); ?></code><?php endif; ?>
						<?php
						/* translators: 1: number of distinct issues, 2: total occurrences. */
						echo esc_html( sprintf( __( '%1$d distinct issues · %2$s occurrences in total', 'ravnsight-detective' ), count( $ravndet_group_rows ), number_format_i18n( $ravndet_group_total ) ) );
						?>
					</span>
				</summary>
				<div class="ravndet-signal-body">
					<?php $ravndet_signals_rows = $ravndet_group_rows; require RAVNDET_PATH . 'templates/partial-signal-rows.php'; ?>
				</div>
			</details>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php if ( empty( $ravndet_groups ) ) : ?>
		<p><?php esc_html_e( 'Nothing recorded yet. Errors and changes will appear here as they happen.', 'ravnsight-detective' ); ?></p>
	<?php endif; ?>
</div>
