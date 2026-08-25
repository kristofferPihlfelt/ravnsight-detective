/**
 * Ravnsight Detective JS error reporter. Same-origin only: reports go to
 * this site's own REST API, never anywhere else. Dedupes per page load.
 */
( function () {
	'use strict';
	if ( ! window.ravndetReporter || ! window.ravndetReporter.endpoint ) {
		return;
	}
	var seen = {};
	var sent = 0;

	function report( message, source, line ) {
		var key = message + '|' + source + '|' + line;
		if ( seen[ key ] || sent >= 5 ) {
			return;
		}
		seen[ key ] = true;
		sent++;
		try {
			var body = JSON.stringify( {
				message: String( message ).slice( 0, 500 ),
				source: String( source || '' ).slice( 0, 300 ),
				line: line || 0,
				page: window.location.pathname
			} );
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( window.ravndetReporter.endpoint, new Blob( [ body ], { type: 'application/json' } ) );
			} else {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', window.ravndetReporter.endpoint, true );
				xhr.setRequestHeader( 'Content-Type', 'application/json' );
				xhr.send( body );
			}
		} catch ( e ) { /* the reporter must never cause errors itself */ }
	}

	window.addEventListener( 'error', function ( event ) {
		if ( event && event.message ) {
			report( event.message, event.filename, event.lineno );
		}
	} );
	window.addEventListener( 'unhandledrejection', function ( event ) {
		var reason = event && event.reason ? ( event.reason.message || String( event.reason ) ) : 'Unhandled promise rejection';
		report( 'Unhandled rejection: ' + reason, '', 0 );
	} );
}() );
