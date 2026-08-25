<?php
/**
 * Plugin Name: Fixture: Broken Fatal
 * Description: Calls an undefined function on demand — a real fatal, from a plugin path.
 */
function brokf_boom() {
	brokf_this_function_does_not_exist();
}
