/**
 * WPICP 4.0 Admin Interactions:
 * Paginated full-site offline Ad-Law batch scanner + one-click allowlist.
 */
( function () {
	'use strict';

	var cfg = window.wpicpAdminData || {};
	var i18n = cfg.i18n || {};

	var NOTICE_ICONS = {
		ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12.5 11.2 14.7 15.5 9.8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
		warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.8 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.8a2 2 0 0 0-3.4 0Z"/></svg>',
		bad: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 9 9 15m0-6 6 6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
	};

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}

	function noticeHtml( level, text ) {
		var cls = level === 'ok' || level === 'warn' || level === 'bad' ? 'notice ' + level : 'notice';
		var role = level === 'bad' ? 'alert' : 'status';
		var icon = NOTICE_ICONS[ level ] || NOTICE_ICONS.warn;
		return '<div class="' + cls + '" role="' + role + '">' + icon + '<span>' + escHtml( text ) + '</span></div>';
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
			container.innerHTML = noticeHtml(
				'ok',
				i18n.doneZero || '扫描完成，未发现《广告法》极限词风险。'
			);
			return;
		}

		var summary = ( i18n.doneHits || '扫描完成，共发现 %d 处风险表达。' ).replace( '%d', String( findings.length ) );
		var html = '<div class="wenpai-stack">';
		html += noticeHtml( 'warn', summary );
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

	function setProgress( progressWrap, pct ) {
		if ( ! progressWrap ) {
			return;
		}
		var bar = progressWrap.querySelector( '.progress[role="progressbar"]' ) || progressWrap.querySelector( '.progress' );
		var fill = progressWrap.querySelector( '.progress i' );
		var meta = progressWrap.querySelector( '.progress-wrap .meta' );
		if ( bar ) {
			bar.setAttribute( 'aria-valuenow', String( pct ) );
		}
		if ( fill ) {
			fill.style.width = pct + '%';
		}
		if ( meta ) {
			meta.textContent = pct + '%';
		}
	}

	async function runFullSiteScan( card ) {
		if ( ! cfg.restScanSiteUrl ) {
			return;
		}
		var progressWrap = card.querySelector( '[data-wpicp-scan-progress]' );
		var resultsWrap = card.querySelector( '[data-wpicp-scan-results]' );
		var buttons = card.querySelectorAll( '[data-wpicp-scan-start]' );

		buttons.forEach( function ( btn ) {
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
		} );

		if ( progressWrap ) {
			progressWrap.hidden = false;
			setProgress( progressWrap, 0 );
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
				setProgress( progressWrap, pct );
				page += 1;
			}
			if ( progressWrap ) {
				progressWrap.hidden = true;
			}
			renderScanResults( resultsWrap, allFindings );
		} catch ( err ) {
			if ( progressWrap ) {
				progressWrap.hidden = true;
			}
			if ( resultsWrap ) {
				resultsWrap.innerHTML = noticeHtml( 'bad', i18n.failed || '巡检请求未完成，请稍后重试。' );
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
		}
	} );
} )();
