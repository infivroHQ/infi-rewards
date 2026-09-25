( function () {
	'use strict';

	function setCopiedState( button ) {
		var status = button.nextElementSibling;
		var originalLabel = button.getAttribute( 'aria-label' );

		button.classList.add( 'is-copied' );
		button.setAttribute( 'aria-label', 'Coupon code copied to clipboard' );
		button.innerHTML = '<span aria-hidden="true">✓</span> Copied';
		if ( status ) {
			status.textContent = 'Coupon code copied to clipboard.';
		}

		window.setTimeout( function () {
			button.classList.remove( 'is-copied' );
			button.setAttribute( 'aria-label', originalLabel );
			button.innerHTML = '<span aria-hidden="true">⧉</span> Copy coupon';
		}, 2000 );
	}

	function copyFallback( code ) {
		var input = document.createElement( 'textarea' );
		input.value = code;
		input.setAttribute( 'readonly', '' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		var copied = document.execCommand( 'copy' );
		document.body.removeChild( input );
		return copied;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.infirewards-coupon-copy' );
		if ( ! button ) {
			return;
		}

		var code = button.getAttribute( 'data-coupon-code' );
		if ( ! code ) {
			return;
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( code ).then( function () {
				setCopiedState( button );
			}, function () {
				if ( copyFallback( code ) ) {
					setCopiedState( button );
				}
			} );
		} else if ( copyFallback( code ) ) {
			setCopiedState( button );
		}
	} );
}() );
