<?php
/**
 * Plugin Name: Fixture: Broken Deprecated
 * Description: Emits deprecations on demand.
 */
function brokd_old( $n = 20 ) {
	for ( $i = 0; $i < $n; $i++ ) {
		trigger_error( 'Function brokd_legacy() is deprecated, use brokd_new()', E_USER_DEPRECATED );
	}
}
