( function () {
	'use strict';

	function renderMath( root ) {
		if ( ! window.katex ) {
			return;
		}
		var scope = root || document;
		scope.querySelectorAll( '.bits-markdown-math' ).forEach( function ( node ) {
			if ( node.getAttribute( 'data-rendered' ) === '1' ) {
				return;
			}
			try {
				window.katex.render( node.textContent, node, {
					displayMode: node.getAttribute( 'data-display' ) === 'true',
					throwOnError: false,
				} );
				node.setAttribute( 'data-rendered', '1' );
			} catch ( e ) {
				// Leave TeX visible if rendering fails.
			}
		} );
	}

	function onReady() {
		renderMath( document );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', onReady );
	} else {
		onReady();
	}
} )();
