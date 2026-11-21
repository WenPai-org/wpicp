/**
 * WPICP 4.0 Frontend Certificate Lightbox (`<dialog>` progressive enhancement).
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-wpicp-lightbox]' );
		if ( trigger ) {
			var id = trigger.getAttribute( 'data-wpicp-lightbox' );
			var dialog = id ? document.getElementById( id ) : null;
			if ( dialog && typeof dialog.showModal === 'function' ) {
				event.preventDefault();
				dialog.showModal();
			}
			return;
		}

		var closeBtn = event.target.closest( '[data-wpicp-lightbox-close]' );
		if ( closeBtn ) {
			var openDialog = closeBtn.closest( 'dialog' );
			if ( openDialog && typeof openDialog.close === 'function' ) {
				openDialog.close();
			}
			return;
		}

		if ( event.target && event.target.tagName === 'DIALOG' && event.target.classList.contains( 'wpicp-certificates__dialog' ) ) {
			event.target.close();
		}
	} );
} )();
