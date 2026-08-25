<?php
/**
 * The single upsell surface in the free build (guideline 11). Nothing
 * elsewhere in the free UI mentions Pro.
 *
 * @package Ravnsight\Detective
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ravndet-wrap">
	<h1><?php esc_html_e( 'Go further with Ravnsight', 'ravnsight-detective' ); ?></h1>

	<p class="ravndet-lead">
		<?php esc_html_e( 'Ravnsight Detective shows you what happens inside this site. Connected to the Ravnsight platform, it becomes one half of a whole: the platform watches your site from the outside — uptime, certificates, domain, DNS, response times — and Detective explains outages from the inside.', 'ravnsight-detective' ); ?>
	</p>

	<h2><?php esc_html_e( 'What Pro adds', 'ravnsight-detective' ); ?></h2>
	<ul class="ravndet-feature-list">
		<li><?php esc_html_e( 'Cloud connection: your errors and changes correlated with external monitoring — "checkout went down 3 minutes after plugin X updated" instead of two separate mysteries.', 'ravnsight-detective' ); ?></li>
		<li><?php esc_html_e( 'Alerts through the Ravnsight platform: e-mail, Slack, Discord, Teams, Telegram, SMS — with escalation chains and maintenance windows.', 'ravnsight-detective' ); ?></li>
		<li><?php esc_html_e( 'History up to 90 days instead of 7.', 'ravnsight-detective' ); ?></li>
		<li><?php esc_html_e( 'Monthly SLA reports you can send straight to your own clients.', 'ravnsight-detective' ); ?></li>
		<li><?php esc_html_e( 'Automatic Pro updates.', 'ravnsight-detective' ); ?></li>
	</ul>

	<p>
		<a class="button button-primary button-hero" href="https://ravnsight.com/plugins/ravnsight-detective" target="_blank" rel="noopener">
			<?php esc_html_e( 'Read more at ravnsight.com', 'ravnsight-detective' ); ?>
		</a>
	</p>
	<p class="description"><?php esc_html_e( 'The free version keeps working exactly as it does today — fully local, and it never sends anything anywhere.', 'ravnsight-detective' ); ?></p>
</div>
