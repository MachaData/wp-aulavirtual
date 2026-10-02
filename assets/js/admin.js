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

	// Generar contrasena: rellena los campos indicados y la muestra para copiarla.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-av-generate]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*?';
		var bytes = new Uint32Array( 14 );
		window.crypto.getRandomValues( bytes );
		var value = '';
		for ( var i = 0; i < bytes.length; i++ ) {
			value += chars.charAt( bytes[ i ] % chars.length );
		}
		button.getAttribute( 'data-av-generate' ).split( ',' ).forEach( function ( id ) {
			var field = document.getElementById( id.trim() );
			if ( field ) {
				field.value = value;
				field.type = 'text';
			}
		} );
	} );

	// Casilla "marcar todos" de las acciones en lote.
	document.addEventListener( 'change', function ( event ) {
		var all = event.target.closest( '[data-av-check-all]' );
		if ( ! all ) {
			return;
		}
		var form = all.closest( 'form' );
		if ( form ) {
			form.querySelectorAll( 'input[name="' + all.getAttribute( 'data-av-check-all' ) + '"]' ).forEach( function ( box ) {
				box.checked = all.checked;
			} );
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var message = form.getAttribute( 'data-av-confirm' );
		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	/* ===== Editor de sesión ===== */

	// Materiales: abrir la biblioteca de medios (subir o elegir). wp.media se
	// imprime al pie de la página, por eso se busca en el momento del clic.
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-av-material-choose]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		if ( ! window.wp || ! window.wp.media ) {
			window.alert( 'La biblioteca de medios no cargó. Recarga la página e inténtalo de nuevo.' );
			return;
		}
		var form = button.closest( '[data-av-material-form]' );
		var frame = button.avFrame;
		if ( ! frame ) {
			frame = window.wp.media( {
				title: button.getAttribute( 'data-av-title' ) || '',
				button: { text: button.getAttribute( 'data-av-button' ) || '' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var file = frame.state().get( 'selection' ).first().toJSON();
				form.querySelector( '[data-av-material-attachment]' ).value = file.id;
				form.querySelector( '[data-av-material-name]' ).textContent = file.filename || file.title;
				form.querySelector( '[data-av-material-chip]' ).hidden = false;
				var title = form.querySelector( '[data-av-material-title]' );
				if ( title && ! title.value ) {
					title.value = file.title || '';
				}
				var url = form.querySelector( '[data-av-material-url]' );
				if ( url ) {
					url.value = '';
				}
				materialState( form );
			} );
			button.avFrame = frame;
		}
		frame.open();
	} );

	function materialState( form ) {
		var hasFile = '0' !== form.querySelector( '[data-av-material-attachment]' ).value;
		var url = form.querySelector( '[data-av-material-url]' );
		var submit = form.querySelector( '[data-av-material-submit]' );
		if ( submit ) {
			submit.disabled = ! hasFile && ! ( url && url.value.trim() );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var clear = event.target.closest( '[data-av-material-clear]' );
		if ( ! clear ) {
			return;
		}
		var form = clear.closest( '[data-av-material-form]' );
		form.querySelector( '[data-av-material-attachment]' ).value = '0';
		form.querySelector( '[data-av-material-chip]' ).hidden = true;
		materialState( form );
	} );

	document.addEventListener( 'input', function ( event ) {
		if ( event.target.matches( '[data-av-material-url]' ) ) {
			var form = event.target.closest( '[data-av-material-form]' );
			if ( event.target.value.trim() ) {
				form.querySelector( '[data-av-material-attachment]' ).value = '0';
				form.querySelector( '[data-av-material-chip]' ).hidden = true;
			}
			materialState( form );
		}
	} );

	// Disponibilidad: mostrar solo el campo de la opción elegida.
	document.addEventListener( 'change', function ( event ) {
		if ( ! event.target.matches( '[data-av-release]' ) ) {
			return;
		}
		document.querySelectorAll( '[data-av-release-show]' ).forEach( function ( field ) {
			field.hidden = field.getAttribute( 'data-av-release-show' ) !== event.target.value;
		} );
	} );

	// Clase en vivo: se despliega al elegir el tipo o con el enlace.
	function openLive() {
		var card = document.querySelector( '[data-av-live-card]' );
		if ( card ) {
			card.classList.remove( 'av-card--collapsed' );
		}
		return card;
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-av-lesson-type]' ) && 'live' === event.target.value ) {
			openLive();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-av-live-open]' ) ) {
			event.preventDefault();
			var card = openLive();
			if ( card ) {
				card.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}
	} );

	// Cambios sin guardar: avisar antes de enviar otro formulario o salir.
	var watched = document.querySelector( '[data-av-dirty-watch]' );
	if ( watched ) {
		var dirty = false;
		var editor = function () {
			return window.tinymce ? window.tinymce.get( 'av-content' ) : null;
		};
		var isDirty = function () {
			var ed = editor();
			return dirty || !! ( ed && ed.isDirty() );
		};
		var clean = function () {
			var ed = editor();
			dirty = false;
			if ( ed ) {
				ed.setDirty( false );
			}
		};
		var mark = function ( event ) {
			if ( event.target.form === watched || watched.contains( event.target ) ) {
				dirty = true;
			}
		};
		document.addEventListener( 'input', mark );
		document.addEventListener( 'change', mark );
		document.addEventListener( 'submit', function ( event ) {
			if ( event.target === watched ) {
				clean();
				return;
			}
			if ( isDirty() ) {
				if ( window.confirm( 'Tienes cambios sin guardar en la sesión (título, video, texto…). Si continúas se perderán. ¿Continuar?' ) ) {
					clean();
				} else {
					event.preventDefault();
					event.stopImmediatePropagation();
				}
			}
		}, true );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( isDirty() ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	}
} )();
