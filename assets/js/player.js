/* Aula Virtual: reproduce listas HLS (playlist.m3u8) donde el navegador no lo hace solo. */
( function () {
	'use strict';

	var HLS_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.15/hls.min.js';

	function attach( video, src ) {
		if ( video.canPlayType( 'application/vnd.apple.mpegurl' ) ) {
			video.src = src;
			return;
		}
		if ( ! window.Hls || ! window.Hls.isSupported() ) {
			var link = document.createElement( 'a' );
			link.href = src;
			link.textContent = 'Abrir el video';
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			video.replaceWith( link );
			return;
		}
		var hls = new window.Hls();
		hls.loadSource( src );
		hls.attachMedia( video );
	}

	function init() {
		var videos = document.querySelectorAll( 'video[data-hls]' );
		if ( ! videos.length ) { return; }

		var run = function () {
			videos.forEach( function ( v ) { attach( v, v.getAttribute( 'data-hls' ) ); } );
		};

		var native = Array.prototype.every.call( videos, function ( v ) {
			return v.canPlayType( 'application/vnd.apple.mpegurl' );
		} );

		if ( native || window.Hls ) { run(); return; }

		var script = document.createElement( 'script' );
		script.src = HLS_SRC;
		script.async = true;
		script.onload = run;
		script.onerror = run;
		document.head.appendChild( script );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
