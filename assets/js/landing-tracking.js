(function () {
	'use strict';

	if (window.Element && !Element.prototype.matches) {
		Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
	}

	if (window.Element && !Element.prototype.closest) {
		Element.prototype.closest = function (selector) {
			var node = this;
			while (node && node.nodeType === 1) {
				if (node.matches && node.matches(selector)) {
					return node;
				}
				node = node.parentElement || node.parentNode;
			}
			return null;
		};
	}

	var config = window.crpcrmLandingTracking || {};
	if ( ! config.enabled || ! config.ajaxUrl || ! config.action ) {
		return;
	}

	function getSlug() {
		if ( ! window.URLSearchParams ) {
			return '';
		}
		try {
			var params = new URLSearchParams( window.location.search );
			var slug = params.get( 'u' ) || params.get( 'U' ) || '';
			slug = String( slug || '' ).trim().toLowerCase();
			return /^[a-z0-9_-]+$/.test( slug ) ? slug : '';
		} catch ( error ) {
			return '';
		}
	}

	function getSessionKey( slug ) {
		return 'crpcrmLandingTrack:' + slug + ':' + window.location.pathname;
	}

	function shouldSkipSession( slug ) {
		try {
			return window.sessionStorage.getItem( getSessionKey( slug ) ) === '1';
		} catch ( error ) {
			return false;
		}
	}

	function markSession( slug ) {
		try {
			window.sessionStorage.setItem( getSessionKey( slug ), '1' );
		} catch ( error ) {}
	}

	function sendTracking( slug ) {
		if ( ! slug || shouldSkipSession( slug ) ) {
			return;
		}

		if ( ! window.fetch || ! window.URLSearchParams ) {
			return;
		}

		var body = new URLSearchParams();
		body.set( 'action', config.action );
		body.set( 'token', config.token || '' );
		body.set( 'slug', slug );
		body.set( 'current_url', window.location.href );
		body.set( 'referrer', document.referrer || '' );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( payload && payload.success && payload.data && payload.data.tracking ) {
					markSession( slug );
				}
				if ( config.debug && window.console && console.debug ) {
					console.debug( '[CRPCRM] landing tracking', payload );
				}
			} )
			.catch( function ( error ) {
				if ( config.debug && window.console && console.debug ) {
					console.debug( '[CRPCRM] landing tracking failed', error );
				}
			} );
	}

	function init() {
		var slug = getSlug();
		if ( ! slug ) {
			return;
		}

		sendTracking( slug );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}());
