<?php
/**
 * Plugin Name: Fixture: Broken Warnings
 * Description: Deliberately noisy fixture — emits warnings from a plugin context on demand.
 */
function brokw_noise( $n = 100 ) {
	for ( $i = 0; $i < $n; $i++ ) {
		trigger_error( 'Undefined array key "sku" while rendering product card', E_USER_WARNING );
	}
}
