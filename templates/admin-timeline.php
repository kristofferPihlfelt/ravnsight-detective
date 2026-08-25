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

	<?php $ravndet_signals_rows = $signals; require RAVNDET_PATH . 'templates/partial-signal-rows.php'; ?>
</div>
