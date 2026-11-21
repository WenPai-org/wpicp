/**
 * WPICP 4.0 Admin Interactions:
 * 1. Paginated full-site offline Ad-Law batch scanner + one-click allowlist.
 * 2. WordPress Media Library picker enhancement for repeater `image_id` inputs.
 */
( function () {
	'use strict';

	var cfg = window.wpicpAdminData || {};
	var i18n = cfg.i18n || {};

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}

	function highlightTerm( excerpt, term ) {
		var safeExcerpt = escHtml( excerpt );
		var safeTerm = escHtml( term );
		if ( ! safeTerm ) {
			return safeExcerpt;
		}
		return safeExcerpt.split( safeTerm ).join( '<mark class="wpicp-audit__mark">' + safeTerm + '</mark>' );
	}

	function renderScanResults( container, findings ) {
		if ( ! container ) {
			return;
		}
		if ( ! findings.length ) {
			container.innerHTML =
				'<div class="notice is-ok"><p>' +
				escHtml( i18n.doneZero || '扫描完成，未发现《广告法》极限词风险。' ) +
				'</p></div>';
			return;
		}

		var summary = ( i18n.doneHits || '扫描完成，共发现 %d 处风险表达。' ).replace( '%d', String( findings.length ) );
		var html = '<div class="wenpai-stack">';
		html += '<div class="notice is-warn"><p>' + escHtml( summary ) + '</p></div>';
		html += '<div class="wenpai-table-wrap"><table class="tbl"><thead><tr>';
		html += '<th scope="col">来源内容</th>';
		html += '<th scope="col">风险用语</th>';
		html += '<th scope="col">上下文摘录</th>';
		html += '<th scope="col">合规替代建议</th>';
		html += '<th scope="col">操作</th>';
		html += '</tr></thead><tbody>';

		findings.forEach( function ( item ) {
			var suggestions = Array.isArray( item.suggestions ) ? item.suggestions.join( ' / ' ) : '';
			html += '<tr>';
			html += '<td><span class="t">' + escHtml( item.post_title || '' ) + '</span><div class="d">' + escHtml( item.post_type || '' ) + '</div></td>';
			html += '<td class="wpicp-audit__term"><span class="t">' + escHtml( item.term || '' ) + '</span><div class="d">' + escHtml( item.category_label || item.category || '' ) + '</div></td>';
			html += '<td class="wpicp-audit__excerpt">' + highlightTerm( item.excerpt || '', item.term || '' ) + '</td>';
			html += '<td>' + escHtml( suggestions ) + '</td>';
			html += '<td class="wpicp-audit__actions"><div class="wenpai-row">';
			if ( item.edit_url ) {
				html += '<a class="btn btn-sm btn-ghost" href="' + escHtml( item.edit_url ) + '" target="_blank" rel="noopener noreferrer">' + escHtml( i18n.editPost || '直达编辑' ) + '</a>';
			}
			html += '<button type="button" class="btn btn-sm btn-ghost" data-wpicp-allowlist-add="' + escHtml( item.term || '' ) + '">' + escHtml( i18n.addAllowlist || '加入白名单' ) + '</button>';
			html += '</div></td>';
			html += '</tr>';
		} );

		html += '</tbody></table></div></div>';
		container.innerHTML = html;
	}

	async function runFullSiteScan( card ) {
		if ( ! cfg.restScanSiteUrl ) {
			return;
		}
		var progressWrap = card.querySelector( '[data-wpicp-scan-progress]' );
		var progressBar = progressWrap ? progressWrap.querySelector( '.wenpai-progress' ) : null;
		var progressFill = progressWrap ? progressWrap.querySelector( '.wenpai-progress__bar' ) : null;
		var resultsWrap = card.querySelector( '[data-wpicp-scan-results]' );
		var buttons = card.querySelectorAll( '[data-wpicp-scan-start]' );

		buttons.forEach( function ( btn ) {
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
		} );

		if ( progressWrap ) {
			progressWrap.hidden = false;
		}
		if ( resultsWrap ) {
			resultsWrap.innerHTML = '<p class="meta">' + escHtml( i18n.scanning || '正在扫描站点内容…' ) + '</p>';
		}

		var page = 1;
		var totalPages = 1;
		var allFindings = [];

		try {
			while ( page <= totalPages ) {
				var res = await window.fetch( cfg.restScanSiteUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': cfg.restNonce || '',
					},
					body: JSON.stringify( {
						page: page,
						per_page: 20,
						accumulated_hits: allFindings.length,
					} ),
				} );
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				var data = await res.json();
				totalPages = Math.max( 1, parseInt( data.total_pages || 1, 10 ) );
				if ( Array.isArray( data.items ) ) {
					allFindings = allFindings.concat( data.items );
				}
				var pct = Math.min( 100, Math.round( ( page / totalPages ) * 100 ) );
				if ( progressBar ) {
					progressBar.setAttribute( 'aria-valuenow', String( pct ) );
				}
				if ( progressFill ) {
					progressFill.style.width = pct + '%';
				}
				page += 1;
			}
			if ( progressWrap ) {
				progressWrap.hidden = true;
			}
			renderScanResults( resultsWrap, allFindings );
		} catch ( err ) {
			if ( resultsWrap ) {
				resultsWrap.innerHTML = '<div class="notice is-bad"><p>' + escHtml( i18n.failed || '巡检请求未完成，请稍后重试。' ) + '</p></div>';
			}
		} finally {
			buttons.forEach( function ( btn ) {
				btn.disabled = false;
				btn.removeAttribute( 'aria-busy' );
			} );
		}
	}

	async function addTermToAllowlist( button ) {
		var term = button.getAttribute( 'data-wpicp-allowlist-add' );
		if ( ! term || ! cfg.restAllowlistUrl ) {
			return;
		}
		button.disabled = true;
		try {
			var res = await window.fetch( cfg.restAllowlistUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.restNonce || '',
				},
				body: JSON.stringify( { term: term } ),
			} );
			if ( res.ok ) {
				button.textContent = i18n.addedAllow || '已加入白名单';
				if ( typeof window.wenpaiToast === 'function' ) {
					window.wenpaiToast( ( i18n.addedAllow || '已加入白名单' ) + '：' + term );
				}
			} else {
				button.disabled = false;
			}
		} catch ( e ) {
			button.disabled = false;
		}
	}

	function enhanceRepeaterMediaInputs( root ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var scope = root || document;
		var inputs = scope.querySelectorAll( 'input[type="number"][name$="[image_id]"]' );
		inputs.forEach( function ( input ) {
			if ( input.dataset.wpicpMediaBound === '1' ) {
				return;
			}
			input.dataset.wpicpMediaBound = '1';

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'btn btn-ghost';
			btn.textContent = i18n.pickMedia || '选择媒体图片';

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var frame = window.wp.media( {
					title: i18n.pickMedia || '选择媒体图片',
					multiple: false,
					library: { type: 'image' },
					button: { text: i18n.useMedia || '使用此图片' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					if ( attachment && attachment.id ) {
						input.value = String( attachment.id );
						input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
						input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						var row = input.closest( '[data-wenpai-repeater-row]' );
						if ( row ) {
							var titleInput = row.querySelector( 'input[name$="[title]"]' );
							if ( titleInput && ! titleInput.value && attachment.title ) {
								titleInput.value = attachment.title;
								titleInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );
							}
						}
					}
				} );
				frame.open();
			} );

			if ( input.parentNode ) {
				var rowWrap = document.createElement( 'div' );
				rowWrap.className = 'wenpai-row wenpai-row--tight';
				input.parentNode.insertBefore( rowWrap, input );
				rowWrap.appendChild( input );
				rowWrap.appendChild( btn );
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var scanBtn = event.target.closest( '[data-wpicp-scan-start]' );
		if ( scanBtn ) {
			var card = scanBtn.closest( '[data-wpicp-scanner]' );
			if ( card ) {
				event.preventDefault();
				runFullSiteScan( card );
			}
			return;
		}

		var allowBtn = event.target.closest( '[data-wpicp-allowlist-add]' );
		if ( allowBtn ) {
			event.preventDefault();
			addTermToAllowlist( allowBtn );
			return;
		}

		var addRepeaterBtn = event.target.closest( '[data-wenpai-repeater-add]' );
		if ( addRepeaterBtn ) {
			window.setTimeout( function () {
				enhanceRepeaterMediaInputs( document );
			}, 30 );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			enhanceRepeaterMediaInputs( document );
		} );
	} else {
		enhanceRepeaterMediaInputs( document );
	}
} )();
