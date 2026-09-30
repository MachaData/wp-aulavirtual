/**
 * Aula Virtual: vista de sesión del campus.
 * - Panel «Contenido del curso»: en escritorio se oculta o muestra (se recuerda en este
 *   navegador); en pantallas chicas se abre como cajón desde la derecha.
 * - Pestañas bajo el video (Descripción, Materiales, Preguntas).
 * Sin JavaScript todo queda visible: el panel a la derecha (o debajo) y las pestañas abiertas.
 */
( function () {
	var player = document.querySelector( '[data-av-player]' );

	if ( ! player ) {
		return;
	}

	var KEY = 'av_outline_closed';
	var mobile = window.matchMedia( '(max-width: 1023px)' );
	var outline = player.querySelector( '#av-outline' );
	var backdrop = player.querySelector( '.av-outline__backdrop' );
	var toggles = player.querySelectorAll( '[data-av-outline-toggle]' );

	player.classList.add( 'av-player--js' );

	function remember( value ) {
		try {
			window.localStorage.setItem( KEY, value ? '1' : '0' );
		} catch ( e ) {}
	}

	function remembered() {
		try {
			return '1' === window.localStorage.getItem( KEY );
		} catch ( e ) {
			return false;
		}
	}

	function isOpen() {
		return mobile.matches ? player.classList.contains( 'is-outline-open' ) : ! player.classList.contains( 'is-outline-closed' );
	}

	function sync() {
		var open = isOpen();

		toggles.forEach( function ( t ) { t.setAttribute( 'aria-expanded', open ? 'true' : 'false' ); } );

		if ( backdrop ) {
			backdrop.hidden = ! ( mobile.matches && open );
		}

		document.documentElement.classList.toggle( 'av-no-scroll', mobile.matches && open );
	}

	function setOpen( open ) {
		if ( mobile.matches ) {
			player.classList.toggle( 'is-outline-open', open );

			if ( open && outline ) {
				outline.focus( { preventScroll: true } );
			}
		} else {
			player.classList.toggle( 'is-outline-closed', ! open );
			remember( ! open );
		}

		sync();
	}

	if ( remembered() ) {
		player.classList.add( 'is-outline-closed' );
	}

	if ( outline ) {
		outline.setAttribute( 'tabindex', '-1' );
	}

	toggles.forEach( function ( t ) {
		t.addEventListener( 'click', function () { setOpen( ! isOpen() ); } );
	} );

	if ( backdrop ) {
		backdrop.addEventListener( 'click', function () { setOpen( false ); } );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && mobile.matches && isOpen() ) {
			setOpen( false );
		}
	} );

	( mobile.addEventListener ? mobile.addEventListener.bind( mobile, 'change' ) : mobile.addListener.bind( mobile ) )( function () {
		player.classList.remove( 'is-outline-open' );
		sync();
	} );

	sync();

	// Deja la sesión actual a la vista dentro del panel.
	var current = player.querySelector( '.av-outline__item.is-current' );
	var list = player.querySelector( '.av-outline__sections' );

	if ( current && list ) {
		list.scrollTop = Math.max( 0, current.offsetTop - list.offsetTop - 80 );
	}

	// Pestañas.
	player.querySelectorAll( '[data-av-tabs]' ).forEach( function ( box ) {
		var tabs = Array.prototype.slice.call( box.querySelectorAll( '[role="tab"]' ) );

		function select( tab, focus ) {
			tabs.forEach( function ( t ) {
				var on = t === tab;
				var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );

				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;

				if ( panel ) {
					panel.hidden = ! on;
				}
			} );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () { select( tab, false ); } );
			tab.addEventListener( 'keydown', function ( e ) {
				var next = 'ArrowRight' === e.key ? i + 1 : ( 'ArrowLeft' === e.key ? i - 1 : null );

				if ( null !== next ) {
					e.preventDefault();
					select( tabs[ ( next + tabs.length ) % tabs.length ], true );
				}
			} );
		} );

		// Un enlace a un comentario (#av-comment-12) abre la pestaña de preguntas.
		var target = window.location.hash ? document.getElementById( window.location.hash.slice( 1 ) ) : null;
		var initial = tabs.filter( function ( t ) { return 'true' === t.getAttribute( 'aria-selected' ); } )[ 0 ] || tabs[ 0 ];

		tabs.forEach( function ( t ) {
			var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );

			if ( target && panel && panel.contains( target ) ) {
				initial = t;
			}
		} );

		if ( initial ) {
			select( initial, false );
		}

		if ( target ) {
			target.scrollIntoView();
		}
	} );
} )();
