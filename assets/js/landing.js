/**
 * Aula Virtual: barra de compra movil. Aparece solo cuando ningun boton de
 * compra esta a la vista (hero, precio, llamado final) ni el pie del sitio.
 */
( function () {
	var bar = document.querySelector( '.av-sticky-cta' );

	if ( ! bar || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var targets = [ '.av-hero__buy', '.av-price__card', '#av-cta', '.av-editions', 'footer' ]
		.map( function ( s ) { return document.querySelector( s ); } )
		.filter( Boolean );
	var visible = new Set();
	var observer = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			if ( e.isIntersecting ) {
				visible.add( e.target );
			} else {
				visible.delete( e.target );
			}
		} );
		bar.classList.toggle( 'is-visible', 0 === visible.size );
	} );

	targets.forEach( function ( el ) { observer.observe( el ); } );
} )();
