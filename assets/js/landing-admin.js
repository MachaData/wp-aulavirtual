/* global jQuery, wp, avLandingAdmin */
( function ( $ ) {
	'use strict';

	// Selector de imagen de la biblioteca de medios.
	$( document ).on( 'click', '.av-image-field__choose', function ( e ) {
		e.preventDefault();
		var field = $( this ).closest( '.av-image-field' );
		var frame = wp.media( {
			title: avLandingAdmin.chooseImage,
			button: { text: avLandingAdmin.useImage },
			library: { type: 'image' },
			multiple: false
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			field.find( '.av-image-field__id' ).val( attachment.id );
			field.find( '.av-image-field__preview' ).html( '<img src="' + url + '" alt="">' );
			field.find( '.av-image-field__remove' ).show();
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.av-image-field__remove', function ( e ) {
		e.preventDefault();
		var field = $( this ).closest( '.av-image-field' );
		field.find( '.av-image-field__id' ).val( '0' );
		field.find( '.av-image-field__preview' ).empty();
		$( this ).hide();
	} );

	// Filas repetibles (FAQ, enlaces, datos adicionales).
	$( document ).on( 'click', '.av-repeater__add', function ( e ) {
		e.preventDefault();
		var repeater = $( this ).closest( '.av-repeater' );
		var base = repeater.data( 'base' );
		var first = repeater.data( 'first' );
		var second = repeater.data( 'second' );
		var index = Date.now();
		var row = $( '<div class="av-repeater__row"></div>' );
		row.append( $( '<input type="text">' ).attr( 'name', base + '[' + index + '][' + first + ']' ).attr( 'placeholder', $( this ).data( 'first-label' ) ) );
		row.append( $( '<textarea rows="2"></textarea>' ).attr( 'name', base + '[' + index + '][' + second + ']' ).attr( 'placeholder', $( this ).data( 'second-label' ) ) );
		row.append( '<button type="button" class="button-link-delete av-repeater__remove" aria-label="' + avLandingAdmin.remove + '">&times;</button>' );
		repeater.find( '.av-repeater__rows' ).append( row );
		row.find( 'input' ).trigger( 'focus' );
	} );

	$( document ).on( 'click', '.av-repeater__remove', function ( e ) {
		e.preventDefault();
		$( this ).closest( '.av-repeater__row' ).remove();
	} );

	// La URL solo aplica al enlace personalizado.
	function toggleUrl( select ) {
		var url = $( select ).closest( '.av-button-field' ).find( '.av-button-field__url' );
		url.toggle( $( select ).val() === 'custom' );
	}
	$( '.av-button-field__type' ).each( function () { toggleUrl( this ); } );
	$( document ).on( 'change', '.av-button-field__type', function () { toggleUrl( this ); } );
} )( jQuery );
