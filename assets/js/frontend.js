/**
 * WPICP 4.0 Frontend Certificate Lightbox, Directory Filter & Anti-Save Protection.
 */
( function () {
	'use strict';

	function getVisibleDirCards( dir ) {
		return Array.from( dir.querySelectorAll( '[data-wpicp-dir-card]' ) ).filter( function ( c ) {
			return ! c.hidden;
		} );
	}

	function showDirCard( dir, index ) {
		var dialog = dir.querySelector( '[data-wpicp-dir-dialog]' );
		var cards = getVisibleDirCards( dir );
		if ( ! dialog || ! cards.length ) {
			return;
		}
		var safeIdx = ( ( index % cards.length ) + cards.length ) % cards.length;
		var card = cards[ safeIdx ];
		dialog._wpicpDir = dir;
		dialog._wpicpIdx = safeIdx;

		var img = dialog.querySelector( '[data-wpicp-dir-img]' );
		var titleEl = dialog.querySelector( '[data-wpicp-dir-title]' );
		var metaEl = dialog.querySelector( '[data-wpicp-dir-meta]' );
		var counterEl = dialog.querySelector( '[data-wpicp-dir-counter]' );
		var prevBtn = dialog.querySelector( '[data-wpicp-dir-prev]' );
		var nextBtn = dialog.querySelector( '[data-wpicp-dir-next]' );

		var fullUrl = card.getAttribute( 'data-full-url' ) || '';
		var title = card.getAttribute( 'data-title' ) || '';
		var type = card.getAttribute( 'data-type' ) || '';
		var number = card.getAttribute( 'data-number' ) || '';
		var expires = card.getAttribute( 'data-expires' ) || '';

		if ( img ) {
			img.setAttribute( 'src', fullUrl );
			img.alt = title;
		}
		if ( titleEl ) {
			titleEl.textContent = title;
		}
		if ( metaEl ) {
			var parts = [];
			if ( type ) {
				parts.push( type );
			}
			if ( number ) {
				parts.push( number );
			}
			if ( expires ) {
				parts.push( expires );
			}
			metaEl.textContent = parts.join( ' · ' );
		}
		if ( counterEl ) {
			counterEl.textContent = String( safeIdx + 1 ) + ' / ' + String( cards.length );
		}
		var single = cards.length <= 1;
		if ( prevBtn ) {
			prevBtn.hidden = single;
		}
		if ( nextBtn ) {
			nextBtn.hidden = single;
		}
		if ( ! dialog.open && typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		// Directory category filter tabs.
		var filterBtn = target.closest( '[data-wpicp-dir-filter]' );
		if ( filterBtn ) {
			var dir = filterBtn.closest( '[data-wpicp-cert-dir]' );
			if ( dir ) {
				var chosen = filterBtn.getAttribute( 'data-wpicp-dir-filter' ) || 'all';
				dir.querySelectorAll( '[data-wpicp-dir-filter]' ).forEach( function ( b ) {
					var active = b === filterBtn;
					b.classList.toggle( 'is-active', active );
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				dir.querySelectorAll( '[data-wpicp-dir-card]' ).forEach( function ( card ) {
					var cat = card.getAttribute( 'data-category' ) || '';
					card.hidden = chosen !== 'all' && cat !== chosen;
				} );
			}
			return;
		}

		// Directory card lightbox open.
		var openCardBtn = target.closest( '[data-wpicp-dir-open]' );
		if ( openCardBtn ) {
			var hostDir = openCardBtn.closest( '[data-wpicp-cert-dir]' );
			var hostCard = openCardBtn.closest( '[data-wpicp-dir-card]' );
			if ( hostDir && hostCard ) {
				event.preventDefault();
				var visCards = getVisibleDirCards( hostDir );
				var idx = visCards.indexOf( hostCard );
				showDirCard( hostDir, idx >= 0 ? idx : 0 );
			}
			return;
		}

		// Directory lightbox prev / next.
		var prevBtn = target.closest( '[data-wpicp-dir-prev]' );
		if ( prevBtn ) {
			var dlgP = prevBtn.closest( 'dialog' );
			if ( dlgP && dlgP._wpicpDir ) {
				showDirCard( dlgP._wpicpDir, ( dlgP._wpicpIdx || 0 ) - 1 );
			}
			return;
		}
		var nextBtn = target.closest( '[data-wpicp-dir-next]' );
		if ( nextBtn ) {
			var dlgN = nextBtn.closest( 'dialog' );
			if ( dlgN && dlgN._wpicpDir ) {
				showDirCard( dlgN._wpicpDir, ( dlgN._wpicpIdx || 0 ) + 1 );
			}
			return;
		}

		// Legacy standalone lightbox trigger.
		var trigger = target.closest( '[data-wpicp-lightbox]' );
		if ( trigger ) {
			var id = trigger.getAttribute( 'data-wpicp-lightbox' );
			var dialog = id ? document.getElementById( id ) : null;
			if ( dialog && typeof dialog.showModal === 'function' ) {
				event.preventDefault();
				dialog.showModal();
			}
			return;
		}

		var closeBtn = target.closest( '[data-wpicp-lightbox-close]' );
		if ( closeBtn ) {
			var openDialog = closeBtn.closest( 'dialog' );
			if ( openDialog && typeof openDialog.close === 'function' ) {
				openDialog.close();
			}
			return;
		}

		if ( target.tagName === 'DIALOG' && target.classList.contains( 'wpicp-certificates__dialog' ) ) {
			target.close();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var openDirDlg = document.querySelector( 'dialog[data-wpicp-dir-dialog][open]' );
		if ( ! openDirDlg || ! openDirDlg._wpicpDir ) {
			return;
		}
		if ( event.key === 'ArrowLeft' ) {
			event.preventDefault();
			showDirCard( openDirDlg._wpicpDir, ( openDirDlg._wpicpIdx || 0 ) - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			event.preventDefault();
			showDirCard( openDirDlg._wpicpDir, ( openDirDlg._wpicpIdx || 0 ) + 1 );
		}
	} );

	document.addEventListener( 'contextmenu', function ( event ) {
		if ( event.target && event.target.closest && event.target.closest( '[data-wpicp-anti-save="1"]' ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'dragstart', function ( event ) {
		if ( event.target && event.target.closest && event.target.closest( '[data-wpicp-anti-save="1"]' ) ) {
			event.preventDefault();
		}
	} );
} )();
