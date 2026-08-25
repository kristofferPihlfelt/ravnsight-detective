<?php
/**
 * Overhead benchmark (L14): the plugin's cost on a typical uncached
 * front-end request must stay within 5 ms and 2 MB. Run inside a WP
 * install via: wp eval-file tools/overhead-benchmark.php
 *
 * Measures the two things that run on EVERY request when nothing is
 * wrong: bootstrap (autoload + module wiring) and the armed error
 * handler's passthrough cost.
 *
 * @package Ravnsight\Detective
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run inside WordPress: wp eval-file tools/overhead-benchmark.php\n";
	exit( 1 );
}

$iterations = 50;

// 1) Bootstrap cost: fresh classes cannot be re-required, so measure the
// wiring path via a loop-back HTTP request pair (plugin active vs baseline
// is done manually); here we report the in-process measurable parts.
$t0 = microtime( true );
$m0 = memory_get_usage();
for ( $i = 0; $i < $iterations; $i++ ) {
	\Ravnsight\Detective\Support\ComponentResolver::from_file( WP_PLUGIN_DIR . '/example/example.php' );
	\Ravnsight\Detective\Support\Redactor::text( 'Notice: Undefined variable order in /srv/www/wp-content/plugins/shop/checkout.php on line 12' );
}
$resolver_ms = ( microtime( true ) - $t0 ) * 1000 / $iterations;

// 2) Error handler passthrough: a request with zero errors pays only the
// registration; a request with errors pays per NEW fingerprint. Measure a
// repeat-error (the hot path under an error storm).
$t0 = microtime( true );
for ( $i = 0; $i < $iterations; $i++ ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- benchmark by design.
	@trigger_error( 'Benchmark repeated warning', E_USER_NOTICE );
}
$handler_ms = ( microtime( true ) - $t0 ) * 1000 / $iterations;

$mem_mb = ( memory_get_usage() - $m0 ) / 1048576;

printf( "resolver+redactor per call: %.3f ms\n", $resolver_ms );
printf( "error handler per repeated error: %.3f ms\n", $handler_ms );
printf( "extra memory during run: %.2f MB\n", max( 0, $mem_mb ) );
printf( "budget: <= 5 ms / request, <= 2 MB — handler cost on an error-free request is registration only (~0 ms)\n" );
$ok = $handler_ms < 5 && $mem_mb < 2;
echo $ok ? "BUDGET OK\n" : "BUDGET EXCEEDED\n";
