<?php
/**
 * Detects the notable tooling a site runs — page builder, cache,
 * security, SEO, backup — from the active plugin list and the theme.
 * Setups fail differently depending on their stack, so knowing it up
 * front is diagnostic gold. Shared by the plugin's support block, the
 * environment summary and (via that) the platform card.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

defined( 'ABSPATH' ) || exit;

final class StackDetector {

	/**
	 * category => ( plugin basename => label ).
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function map() {
		return array(
			'builder'  => array(
				'elementor/elementor.php'                    => 'Elementor',
				'js_composer/js_composer.php'                => 'WPBakery',
				'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
				'bb-plugin/fl-builder.php'                   => 'Beaver Builder',
				'oxygen/functions.php'                       => 'Oxygen',
				'fusion-builder/fusion-builder.php'          => 'Avada Builder',
				'breakdance/plugin.php'                      => 'Breakdance',
				'brizy/brizy.php'                            => 'Brizy',
				'siteorigin-panels/siteorigin-panels.php'    => 'SiteOrigin',
				'divi-builder/divi-builder.php'              => 'Divi Builder',
			),
			'cache'    => array(
				'wp-rocket/wp-rocket.php'                => 'WP Rocket',
				'litespeed-cache/litespeed-cache.php'    => 'LiteSpeed Cache',
				'w3-total-cache/w3-total-cache.php'      => 'W3 Total Cache',
				'wp-super-cache/wp-cache.php'            => 'WP Super Cache',
				'wp-fastest-cache/wpFastestCache.php'    => 'WP Fastest Cache',
				'sg-cachepress/sg-cachepress.php'        => 'SiteGround Optimizer',
				'cache-enabler/cache-enabler.php'        => 'Cache Enabler',
				'nitropack/main.php'                     => 'NitroPack',
				'wp-optimize/wp-optimize.php'            => 'WP-Optimize',
				'autoptimize/autoptimize.php'            => 'Autoptimize',
			),
			'security' => array(
				'wordfence/wordfence.php'                            => 'Wordfence',
				'better-wp-security/better-wp-security.php'           => 'Solid Security (iThemes)',
				'sucuri-scanner/sucuri.php'                           => 'Sucuri',
				'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security',
				'wp-cerber/wp-cerber.php'                            => 'Cerber Security',
				'ninjafirewall/ninjafirewall.php'                    => 'NinjaFirewall',
				'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => 'Limit Login Attempts',
				'defender-security/wp-defender.php'                  => 'Defender',
			),
			'seo'      => array(
				'wordpress-seo/wp-seo.php'         => 'Yoast SEO',
				'seo-by-rank-math/rank-math.php'   => 'Rank Math',
				'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
				'wp-seopress/seopress.php'         => 'SEOPress',
				'autodescription/autodescription.php' => 'The SEO Framework',
			),
			'backup'   => array(
				'updraftplus/updraftplus.php'      => 'UpdraftPlus',
				'backwpup/backwpup.php'            => 'BackWPup',
				'duplicator/duplicator.php'        => 'Duplicator',
				'backupbuddy/backupbuddy.php'      => 'BackupBuddy',
				'wpvivid-backuprestore/wpvivid-backuprestore.php' => 'WPvivid',
			),
		);
	}

	/**
	 * Detected tooling: category => label ('' when none). Theme-based
	 * builders (Divi/Bricks) are folded into 'builder'.
	 *
	 * @return array<string, string>
	 */
	public static function detect() {
		$active = (array) get_option( 'active_plugins', array() );
		$out    = array();
		foreach ( self::map() as $category => $plugins ) {
			$out[ $category ] = '';
			foreach ( $plugins as $basename => $label ) {
				if ( in_array( $basename, $active, true ) ) {
					$out[ $category ] = $label;
					break;
				}
			}
		}

		if ( '' === $out['builder'] ) {
			$template = strtolower( (string) wp_get_theme()->get_template() );
			if ( in_array( $template, array( 'divi', 'bricks' ), true ) ) {
				$out['builder'] = ucfirst( $template );
			}
		}

		return $out;
	}

	/**
	 * The category a single plugin basename belongs to, or '' — used to
	 * tag rows in the support block.
	 *
	 * @param string $basename Plugin basename.
	 * @return string
	 */
	public static function category_of( $basename ) {
		foreach ( self::map() as $category => $plugins ) {
			if ( isset( $plugins[ $basename ] ) ) {
				return $category;
			}
		}

		return '';
	}
}
