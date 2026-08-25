<?php
/**
 * Dashboard template.
 *
 * @package Ravnsight\Detective
 * @var array $data From Queries::dashboard().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ravndet-wrap">
	<h1><?php esc_html_e( 'Ravnsight Detective', 'ravnsight-detective' ); ?></h1>

	<?php if ( $data['spike'] ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'Error spike: more errors in the last 24 hours than in the six days before them. Check the timeline — did something change right before it started?', 'ravnsight-detective' ); ?>
		</p></div>
	<?php endif; ?>

	<div class="ravndet-cards">
		<div class="ravndet-card <?php echo $data['fatals_24h'] > 0 ? 'is-bad' : ''; ?>">
			<span class="ravndet-card-label"><?php esc_html_e( 'Fatal errors, 24 h', 'ravnsight-detective' ); ?></span>
			<span class="ravndet-card-value"><?php echo esc_html( number_format_i18n( $data['fatals_24h'] ) ); ?></span>
		</div>
		<div class="ravndet-card">
			<span class="ravndet-card-label"><?php esc_html_e( 'Errors, 24 h', 'ravnsight-detective' ); ?></span>
			<span class="ravndet-card-value"><?php echo esc_html( number_format_i18n( $data['errors_24h'] ) ); ?></span>
		</div>
		<div class="ravndet-card">
			<span class="ravndet-card-label"><?php esc_html_e( 'Errors, 7 days', 'ravnsight-detective' ); ?></span>
			<span class="ravndet-card-value"><?php echo esc_html( number_format_i18n( $data['errors_7d'] ) ); ?></span>
		</div>
		<div class="ravndet-card">
			<span class="ravndet-card-label"><?php esc_html_e( 'Changes, 7 days', 'ravnsight-detective' ); ?></span>
			<span class="ravndet-card-value"><?php echo esc_html( number_format_i18n( $data['changes_7d'] ) ); ?></span>
		</div>
	</div>

	<h2><?php esc_html_e( 'Top error sources, 7 days', 'ravnsight-detective' ); ?></h2>
	<?php if ( empty( $data['offenders'] ) ) : ?>
		<p><?php esc_html_e( 'No errors recorded. Quiet is good.', 'ravnsight-detective' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ravndet-table">
			<thead><tr>
				<th><?php esc_html_e( 'Component', 'ravnsight-detective' ); ?></th>
				<th><?php esc_html_e( 'Type', 'ravnsight-detective' ); ?></th>
				<th><?php esc_html_e( 'Occurrences', 'ravnsight-detective' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $data['offenders'] as $ravndet_row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $ravndet_row->component_id ); ?></code></td>
					<td><?php echo esc_html( $ravndet_row->component_type ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $ravndet_row->hits ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Latest activity', 'ravnsight-detective' ); ?></h2>
	<?php $ravndet_signals_rows = $data['latest']; require RAVNDET_PATH . 'templates/partial-signal-rows.php'; ?>
	<p><a class="button" href="<?php echo esc_url( ravndet_url( 'timeline' ) ); ?>"><?php esc_html_e( 'Open the full timeline', 'ravnsight-detective' ); ?></a></p>
</div>
