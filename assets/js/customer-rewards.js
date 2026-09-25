( function () {
	'use strict';

	function setButtonLabel( button, icon, label ) {
		var iconSpan = document.createElement( 'span' );
		iconSpan.setAttribute( 'aria-hidden', 'true' );
		iconSpan.textContent = icon;
		button.textContent = '';
		button.appendChild( iconSpan );
		button.appendChild( document.createTextNode( ' ' + label ) );
	}

	function setCopiedState( button ) {
		var status = button.nextElementSibling;
		var labels = window.infirewardsCouponText;

		button.classList.add( 'is-copied' );
		button.setAttribute( 'aria-label', labels.copiedLabel );
		setButtonLabel( button, '✓', labels.copied );
		if ( status ) {
			status.textContent = labels.copiedLabel;
		}

		window.setTimeout( function () {
			button.classList.remove( 'is-copied' );
			button.setAttribute( 'aria-label', labels.copyLabel );
			setButtonLabel( button, '⧉', labels.copy );
		}, 2000 );
	}

	function reportCopyFailure( button ) {
		var status = button.nextElementSibling;
		if ( status ) {
			status.textContent = window.infirewardsCouponText.copyFailed;
		}
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
				} else {
					reportCopyFailure( button );
				}
			} );
		} else if ( copyFallback( code ) ) {
			setCopiedState( button );
		} else {
			reportCopyFailure( button );
		}
	} );
}() );
