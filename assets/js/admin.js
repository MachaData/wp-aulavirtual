/* Aula Virtual — admin: copiar enlaces y confirmar acciones. Sin dependencias. */
( function () {
	'use strict';

	function copy( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();
			try {
				document.execCommand( 'copy' );
				resolve();
			} catch ( e ) {
				reject( e );
			}
			document.body.removeChild( area );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-av-copy]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var input = document.getElementById( button.getAttribute( 'data-av-copy' ) );
		if ( ! input ) {
			return;
		}
		var label = button.textContent;
		copy( input.value ).then( function () {
			button.classList.add( 'is-copied' );
			button.textContent = button.getAttribute( 'data-av-copied' ) || label;
			window.setTimeout( function () {
				button.classList.remove( 'is-copied' );
				button.textContent = label;
			}, 2000 );
		} ).catch( function () {
			input.focus();
			input.select();
		} );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var message = form.getAttribute( 'data-av-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );
} )();
