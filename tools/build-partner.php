<?php
/**
 * Partner build: a premium build re-branded with the partner's profile.
 * Called by the platform's build pipeline (white_label_settings) — can also
 * be run by hand:
 *
 *   php tools/build-partner.php brand.json
 *
 * brand.json: {"brand_name": "...", "brand_slug": "...", "brand_url": "...",
 *              "support_email": "...", "primary_color": "#RRGGBB"}
 *
 * The output keeps the ravnsight-detective-pro internals (constants, table
 * names, update API slug) — branding is presentation, never identity: the
 * updater must keep working whatever the partner calls the plugin.
 *
 * @package Ravnsight\Detective
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$brand_file = $argv[1] ?? null;
if ( ! $brand_file || ! is_file( $brand_file ) ) {
	fwrite( STDERR, "Usage: php tools/build-partner.php brand.json\n" );
	exit( 1 );
}
$brand = json_decode( (string) file_get_contents( $brand_file ), true );
if ( empty( $brand['brand_name'] ) || empty( $brand['brand_slug'] ) || ! preg_match( '/^[a-z0-9-]+$/', $brand['brand_slug'] ) ) {
	fwrite( STDERR, "brand.json needs brand_name and a [a-z0-9-] brand_slug\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );

// 1) Normal premium build first (all validations run).
passthru( 'php ' . escapeshellarg( $root . '/build/build.php' ) . ' premium', $code );
if ( 0 !== $code ) {
	exit( $code );
}

// 2) Copy premium → partner dir.
$src = $root . '/build/dist/premium/ravnsight-detective-pro';
$out = $root . '/build/dist/partner/' . $brand['brand_slug'];
passthru( 'rm -rf ' . escapeshellarg( dirname( $out ) ) );
mkdir( dirname( $out ), 0755, true );
passthru( 'cp -R ' . escapeshellarg( $src ) . ' ' . escapeshellarg( $out ) );

// 3) Re-brand the header + inject the brand constants.
$main    = $out . '/ravnsight-detective.php';
$content = (string) file_get_contents( $main );
$content = preg_replace( '/^( \* Plugin Name:\s+).+$/m', '${1}' . addcslashes( $brand['brand_name'], '\\$' ), $content, 1 );
if ( ! empty( $brand['brand_url'] ) ) {
	$content = preg_replace( '/^( \* (?:Plugin|Author) URI:\s+).+$/m', '${1}' . addcslashes( $brand['brand_url'], '\\$' ), $content );
	$content = preg_replace( '/^( \* Author:\s+).+$/m', '${1}' . addcslashes( $brand['brand_name'], '\\$' ), $content, 1 );
}
$constants = "\ndefine( 'RAVNDET_BRAND_NAME', '" . addslashes( $brand['brand_name'] ) . "' );\n"
	. "define( 'RAVNDET_BRAND_URL', '" . addslashes( (string) ( $brand['brand_url'] ?? '' ) ) . "' );\n"
	. "define( 'RAVNDET_BRAND_SUPPORT', '" . addslashes( (string) ( $brand['support_email'] ?? '' ) ) . "' );\n"
	. "define( 'RAVNDET_BRAND_COLOR', '" . addslashes( (string) ( $brand['primary_color'] ?? '' ) ) . "' );\n";
$content   = str_replace( "define( 'RAVNDET_BASENAME', plugin_basename( __FILE__ ) );", "define( 'RAVNDET_BASENAME', plugin_basename( __FILE__ ) );" . $constants, $content );
file_put_contents( $main, $content );

// 4) Zip.
$zip_path = $root . '/build/dist/partner/' . $brand['brand_slug'] . '.zip';
passthru( 'cd ' . escapeshellarg( dirname( $out ) ) . ' && zip -qr ' . escapeshellarg( $zip_path ) . ' ' . escapeshellarg( basename( $out ) ) );
echo "Partner build: {$zip_path}\n";
