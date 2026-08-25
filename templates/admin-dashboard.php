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

	<?php $ravndet_updates = count( $health['plugin_updates'] ) + count( $health['theme_updates'] ) + ( $health['core_update'] ? 1 : 0 ); ?>
	<h2><?php esc_html_e( 'Needs attention', 'ravnsight-detective' ); ?></h2>
	<?php if ( 0 === $ravndet_updates && ! $health['php_old'] ) : ?>
		<p><?php esc_html_e( 'Everything is up to date: WordPress, plugins and themes are current.', 'ravnsight-detective' ); ?></p>
	<?php else : ?>
		<table class="widefat striped ravndet-table">
			<thead><tr>
				<th><?php esc_html_e( 'What', 'ravnsight-detective' ); ?></th>
				<th><?php esc_html_e( 'Installed', 'ravnsight-detective' ); ?></th>
				<th><?php esc_html_e( 'Available', 'ravnsight-detective' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( $health['core_update'] ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'WordPress core', 'ravnsight-detective' ); ?></strong></td>
					<td><?php echo esc_html( $health['wp_version'] ); ?></td>
					<td><?php echo esc_html( $health['core_update'] ); ?> — <a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'update screen', 'ravnsight-detective' ); ?></a></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $health['plugin_updates'] as $ravndet_up ) : ?>
				<tr>
					<td><?php echo esc_html( $ravndet_up['name'] ); ?> <code><?php echo esc_html( $ravndet_up['slug'] ); ?></code></td>
					<td><?php echo esc_html( $ravndet_up['current'] ); ?></td>
					<td>
						<?php echo esc_html( $ravndet_up['new'] ); ?>
						<?php if ( ! empty( $ravndet_up['no_package'] ) ) : ?>
							<strong style="color:#996800"><?php esc_html_e( '— update announced but not downloadable: the licence or subscription for this premium plugin has likely expired', 'ravnsight-detective' ); ?></strong>
						<?php else : ?>
							— <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'plugins screen', 'ravnsight-detective' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php foreach ( $health['theme_updates'] as $ravndet_up ) : ?>
				<tr>
					<td><?php echo esc_html( $ravndet_up['name'] ); ?> <code><?php echo esc_html( $ravndet_up['slug'] ); ?></code> (<?php esc_html_e( 'theme', 'ravnsight-detective' ); ?>)</td>
					<td><?php echo esc_html( $ravndet_up['current'] ); ?></td>
					<td><?php echo esc_html( $ravndet_up['new'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( $health['php_old'] ) : ?>
				<tr>
					<td><strong>PHP</strong></td>
					<td><?php echo esc_html( $health['php_version'] ); ?></td>
					<td><?php esc_html_e( 'Below 8.1 — ask your host to upgrade', 'ravnsight-detective' ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Detective never updates anything for you — it tells you what needs doing and records what happens when you do it.', 'ravnsight-detective' ); ?></p>
	<?php endif; ?>

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
