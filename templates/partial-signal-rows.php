<?php
/**
 * Shared signal table.
 *
 * @package Ravnsight\Detective
 * @var array $ravndet_signals_rows Signal rows.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="widefat striped ravndet-table">
	<thead><tr>
		<th><?php esc_html_e( 'When', 'ravnsight-detective' ); ?></th>
		<th><?php esc_html_e( 'Type', 'ravnsight-detective' ); ?></th>
		<th><?php esc_html_e( 'Message', 'ravnsight-detective' ); ?></th>
		<th><?php esc_html_e( 'Component', 'ravnsight-detective' ); ?></th>
		<th><?php esc_html_e( 'Count', 'ravnsight-detective' ); ?></th>
	</tr></thead>
	<tbody>
	<?php if ( empty( $ravndet_signals_rows ) ) : ?>
		<tr><td colspan="5"><?php esc_html_e( 'Nothing recorded yet.', 'ravnsight-detective' ); ?></td></tr>
	<?php else : ?>
		<?php foreach ( $ravndet_signals_rows as $ravndet_row ) : ?>
			<tr class="ravndet-severity-<?php echo esc_attr( $ravndet_row->severity ); ?>">
				<td title="<?php echo esc_attr( gmdate( 'Y-m-d H:i:s', (int) $ravndet_row->last_seen ) ); ?>">
					<?php echo esc_html( human_time_diff( (int) $ravndet_row->last_seen ) ); ?>
				</td>
				<td><code><?php echo esc_html( $ravndet_row->type ); ?></code></td>
				<td><?php echo esc_html( wp_html_excerpt( (string) $ravndet_row->message, 120, '…' ) ); ?></td>
				<td><?php echo $ravndet_row->component_id ? '<code>' . esc_html( $ravndet_row->component_id ) . '</code>' : '—'; ?></td>
				<td><?php echo esc_html( number_format_i18n( (int) $ravndet_row->count ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>
