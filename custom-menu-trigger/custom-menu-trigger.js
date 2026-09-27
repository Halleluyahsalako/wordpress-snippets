/**
 * Custom Menu Trigger
 *
 * Intercepts the theme's hamburger and opens our panel instead.
 */
( function () {
	'use strict';

	var config = window.cmtConfig || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var panel = document.getElementById( 'cmt-panel' );
		var trigger = config.trigger ? document.querySelector( config.trigger ) : null;

		if ( ! panel || ! trigger ) {
			return;
		}

		var closeButton = panel.querySelector( '.cmt-panel__close' );
		var lastFocused = null;

		function open( e ) {
			if ( e ) {
				e.preventDefault();
				e.stopPropagation();
			}

			lastFocused = document.activeElement;

			panel.classList.add( 'is-open' );
			panel.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'cmt-locked' );
			panel.focus();
		}

		function close() {
			panel.classList.remove( 'is-open' );
			panel.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'cmt-locked' );

			if ( lastFocused ) {
				lastFocused.focus();
			}
		}

		// Capture phase, because the theme has its own listener on the same
		// element and we need to win before it runs.
		trigger.addEventListener( 'click', open, true );

		if ( closeButton ) {
			closeButton.addEventListener( 'click', close );
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && panel.classList.contains( 'is-open' ) ) {
				close();
			}
		} );

		// A link inside the panel should close it on the way out, otherwise
		// the panel stays open over the new page on same page anchors.
		panel.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				close();
			}
		} );
	} );
} )();
