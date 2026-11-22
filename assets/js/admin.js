/**
 * WPICP 4.0 Admin Interactions:
 * 1. Official preset badge 3-column grid (filter, search, select all/clear).
 * 2. Compact license table-row editor & custom badge media strip + WP Media picker.
 * 3. Electronic certificate gallery + native <dialog> metadata editor + live watermark preview.
 * 4. Display preview workbench (light/dark footer + live preset/align/separator sync).
 * 5. Live Homepage/Access probe, WordYeah remote ruleset sync, and Ad-Law batch scanner.
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

	function toast( msg ) {
		if ( typeof window.wenpaiToast === 'function' ) {
			window.wenpaiToast( msg );
		}
	}

	function markDirty( el ) {
		var form = el && el.closest ? el.closest( 'form' ) : document.querySelector( '.wenpai-app form' );
		if ( form ) {
			form.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			form.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
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

	/* 1. Preset badges 3-column grid */
	function initBadgeCatalog() {
		var grid = document.querySelector( '[data-wpicp-badge-grid]' );
		if ( ! grid ) {
			return;
		}
		var cards = Array.from( grid.querySelectorAll( '.wpicp-badges__card' ) );
		var countEl = document.querySelector( '[data-wpicp-badge-count]' );
		var searchEl = document.querySelector( '[data-wpicp-badge-search]' );
		var groupBtns = Array.from( document.querySelectorAll( '[data-wpicp-badge-group]' ) );
		var currentGroup = 'all';

		function updateCount() {
			var selected = cards.filter( function ( c ) {
				var inp = c.querySelector( 'input[type="checkbox"]' );
				var checked = !! ( inp && inp.checked );
				c.classList.toggle( 'is-selected', checked );
				return checked;
			} ).length;
			if ( countEl ) {
				countEl.textContent = '已选 ' + selected + ' / ' + cards.length;
			}
		}

		function applyFilter() {
			var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
			cards.forEach( function ( c ) {
				var grp = c.getAttribute( 'data-group' ) || '';
				var text = ( c.textContent || '' ).toLowerCase();
				c.hidden = ! ( ( currentGroup === 'all' || grp === currentGroup ) && ( ! q || text.indexOf( q ) !== -1 ) );
			} );
		}

		cards.forEach( function ( c ) {
			var inp = c.querySelector( 'input[type="checkbox"]' );
			if ( inp ) {
				inp.addEventListener( 'change', updateCount );
			}
		} );

		groupBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				currentGroup = btn.getAttribute( 'data-wpicp-badge-group' ) || 'all';
				groupBtns.forEach( function ( b ) {
					var active = b === btn;
					b.classList.toggle( 'btn-secondary', active );
					b.classList.toggle( 'btn-ghost', ! active );
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );
				applyFilter();
			} );
		} );

		if ( searchEl ) {
			searchEl.addEventListener( 'input', applyFilter );
		}

		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-wpicp-badge-all]' ) ) {
				cards.forEach( function ( c ) {
					if ( ! c.hidden ) {
						var inp = c.querySelector( 'input[type="checkbox"]' );
						if ( inp ) {
							inp.checked = true;
						}
					}
				} );
				updateCount();
				markDirty( grid );
			}
			if ( e.target.closest( '[data-wpicp-badge-clear]' ) ) {
				cards.forEach( function ( c ) {
					var inp = c.querySelector( 'input[type="checkbox"]' );
					if ( inp ) {
						inp.checked = false;
					}
				} );
				updateCount();
				markDirty( grid );
			}
		} );

		updateCount();
	}

	/* 2. Display workbench live reactivity */
	function initDisplayWorkbench() {
		var stage = document.querySelector( '[data-wpicp-preview-stage]' );
		if ( ! stage ) {
			return;
		}

		function syncPreview() {
			var styleInput = document.querySelector( 'input[name$="[footer_style]"]:checked' );
			var alignInput = document.querySelector( 'input[name$="[footer_align]"]:checked' );
			var sepInput = document.querySelector( 'input[name$="[footer_separator]"]:checked' );
			var style = styleInput ? styleInput.value : 'standard';
			var align = alignInput ? alignInput.value : 'center';
			var sep = sepInput ? sepInput.value : '|';

			stage.querySelectorAll( '[data-wpicp-bar-variant]' ).forEach( function ( el ) {
				el.hidden = el.getAttribute( 'data-wpicp-bar-variant' ) !== style;
			} );
			stage.querySelectorAll( '.wpicp-bar' ).forEach( function ( bar ) {
				bar.classList.remove( 'wpicp-bar--align-center', 'wpicp-bar--align-left', 'wpicp-bar--align-between' );
				bar.classList.add( 'wpicp-bar--align-' + align );
				bar.querySelectorAll( '.wpicp-bar__sep' ).forEach( function ( s ) {
					s.textContent = sep === 'space' ? '' : sep;
				} );
			} );
		}

		document.addEventListener( 'click', function ( e ) {
			var bgBtn = e.target.closest( '[data-wpicp-stage-bg]' );
			if ( bgBtn ) {
				var mode = bgBtn.getAttribute( 'data-wpicp-stage-bg' );
				stage.classList.toggle( 'is-dark', mode === 'dark' );
				document.querySelectorAll( '[data-wpicp-stage-bg]' ).forEach( function ( b ) {
					var active = b === bgBtn;
					b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					b.classList.toggle( 'btn-secondary', active );
					b.classList.toggle( 'btn-ghost', ! active );
				} );
			}
		} );

		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.name && ( e.target.name.indexOf( '[footer_style]' ) !== -1 || e.target.name.indexOf( '[footer_align]' ) !== -1 || e.target.name.indexOf( '[footer_separator]' ) !== -1 ) ) {
				syncPreview();
			}
		} );
	}

	/* 3. Certificate live watermark preview */
	function syncCertWatermarks() {
		var layers = Array.from( document.querySelectorAll( '[data-wpicp-wm-layer]' ) );
		if ( ! layers.length ) {
			return;
		}
		var enabledEl = document.querySelector( 'input[type="checkbox"][name$="[cert_watermark_enabled]"]' );
		var textEl = document.querySelector( 'input[name$="[cert_watermark_text]"]' );
		var layoutEl = document.querySelector( 'input[name$="[cert_watermark_layout]"]:checked' );
		var toneEl = document.querySelector( 'input[name$="[cert_watermark_tone]"]:checked' );

		var enabled = enabledEl ? enabledEl.checked : true;
		var rawText = textEl ? textEl.value.trim() : '';
		var resolved = ( rawText || '仅供 {site_name} 官网亮照公示使用，他用无效' )
			.replace( /\{site_name\}/g, cfg.siteName || '官网' )
			.replace( /\{domain\}/g, cfg.siteDomain || 'example.com' )
			.replace( /\{date\}/g, cfg.todayDate || '' );
		var layout = layoutEl ? layoutEl.value : 'tile';
		var tone = toneEl ? toneEl.value : 'standard';

		layers.forEach( function ( el ) {
			el.hidden = ! enabled;
			el.classList.remove( 'is-center', 'is-corner', 'is-light', 'is-strong', 'is-red' );
			if ( layout === 'center' ) {
				el.classList.add( 'is-center' );
			} else if ( layout === 'corner' ) {
				el.classList.add( 'is-corner' );
			}
			if ( tone === 'light' || tone === 'strong' || tone === 'red' ) {
				el.classList.add( 'is-' + tone );
			}
			el.innerHTML = layout === 'tile'
				? '<span>' + escHtml( resolved ) + '</span><span>' + escHtml( resolved ) + '</span>'
				: '<span>' + escHtml( resolved ) + '</span>';
		} );
	}

	function openMediaFrame( callback, multiple ) {
		if ( ! window.wp || ! window.wp.media ) {
			toast( '媒体库脚本未就绪，请刷新重试。' );
			return;
		}
		var frame = window.wp.media( {
			title: i18n.pickMedia || '选择媒体图片',
			multiple: !! multiple,
			library: { type: 'image' },
			button: { text: i18n.useMedia || '使用此图片' },
		} );
		frame.on( 'select', function () {
			var selection = frame.state().get( 'selection' );
			if ( multiple ) {
				var items = [];
				selection.each( function ( model ) {
					var data = typeof model.toJSON === 'function' ? model.toJSON() : model;
					var url = ( data.sizes && data.sizes.medium && data.sizes.medium.url ) || ( data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url ) || data.url || '';
					items.push( { id: data.id || 0, url: url, title: data.title || '' } );
				} );
				if ( items.length ) {
					callback( items );
				}
				return;
			}
			var first = selection.first();
			if ( ! first ) {
				return;
			}
			var data = typeof first.toJSON === 'function' ? first.toJSON() : first;
			var url = ( data.sizes && data.sizes.medium && data.sizes.medium.url ) || ( data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url ) || data.url || '';
			callback( { id: data.id || 0, url: url, title: data.title || '' } );
		} );
		frame.open();
	}

	function initCertCatalog() {
		var grid = document.querySelector( '[data-wpicp-cert-grid]' );
		if ( ! grid ) {
			return;
		}
		var toolbar = document.querySelector( '[data-wpicp-cert-toolbar]' );
		var filterWrap = document.querySelector( '[data-wpicp-cert-filters]' );
		var searchEl = document.querySelector( '[data-wpicp-cert-search]' );
		var activeCat = 'all';

		function applyFilter() {
			var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
			var cards = Array.from( grid.querySelectorAll( '[data-wpicp-cert-card]' ) );
			cards.forEach( function ( card ) {
				var cat = card.getAttribute( 'data-cert-cat' ) || '';
				var titleInp = card.querySelector( 'input[data-subkey="title"]' );
				var numInp = card.querySelector( 'input[data-subkey="number"]' );
				var hay = ( ( titleInp ? titleInp.value : '' ) + ' ' + ( numInp ? numInp.value : '' ) + ' ' + cat ).toLowerCase();
				card.hidden = ! ( ( activeCat === 'all' || cat === activeCat ) && ( ! q || hay.indexOf( q ) !== -1 ) );
			} );
		}

		function rebuildFilters() {
			var cards = Array.from( grid.querySelectorAll( '[data-wpicp-cert-card]' ) );
			if ( toolbar ) {
				toolbar.hidden = cards.length === 0;
			}
			if ( ! filterWrap ) {
				return;
			}
			var seen = {};
			cards.forEach( function ( card ) {
				var cat = card.getAttribute( 'data-cert-cat' ) || '营业执照';
				seen[ cat ] = ( seen[ cat ] || 0 ) + 1;
			} );
			if ( activeCat !== 'all' && ! seen[ activeCat ] ) {
				activeCat = 'all';
			}
			var html = '<button type="button" class="btn btn-sm ' + ( activeCat === 'all' ? 'btn-secondary' : 'btn-ghost' ) + '" data-wpicp-cert-group="all" aria-pressed="' + ( activeCat === 'all' ? 'true' : 'false' ) + '">全部 (' + cards.length + ')</button>';
			Object.keys( seen ).forEach( function ( cat ) {
				var on = activeCat === cat;
				html += '<button type="button" class="btn btn-sm ' + ( on ? 'btn-secondary' : 'btn-ghost' ) + '" data-wpicp-cert-group="' + escHtml( cat ) + '" aria-pressed="' + ( on ? 'true' : 'false' ) + '">' + escHtml( cat ) + '</button>';
			} );
			filterWrap.innerHTML = html;
			applyFilter();
		}

		grid._wpicpRebuildFilters = rebuildFilters;

		if ( filterWrap ) {
			filterWrap.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[data-wpicp-cert-group]' );
				if ( ! btn ) {
					return;
				}
				activeCat = btn.getAttribute( 'data-wpicp-cert-group' ) || 'all';
				filterWrap.querySelectorAll( '[data-wpicp-cert-group]' ).forEach( function ( b ) {
					var on = b === btn;
					b.classList.toggle( 'btn-secondary', on );
					b.classList.toggle( 'btn-ghost', ! on );
					b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				} );
				applyFilter();
			} );
		}

		if ( searchEl ) {
			searchEl.addEventListener( 'input', applyFilter );
		}
	}

	function reindexList( container, rowSelector, fieldName ) {
		if ( ! container ) {
			return;
		}
		var opt = container.getAttribute( 'data-option' ) || 'wpicp_settings';
		var rows = container.querySelectorAll( rowSelector );
		rows.forEach( function ( row, idx ) {
			row.setAttribute( 'data-index', String( idx ) );
			row.querySelectorAll( '[data-subkey]' ).forEach( function ( inp ) {
				var key = inp.getAttribute( 'data-subkey' );
				inp.setAttribute( 'name', opt + '[' + fieldName + '][' + idx + '][' + key + ']' );
			} );
		} );
	}

	/* 4. Ad-Law batch scanner */
	function renderScanResults( container, findings ) {
		if ( ! container ) {
			return;
		}
		if ( ! findings.length ) {
			container.innerHTML = noticeHtml( 'ok', i18n.doneZero || '扫描完成，未发现《广告法》极限词风险。' );
			return;
		}
		var summary = ( i18n.doneHits || '扫描完成，共发现 %d 处风险表达。' ).replace( '%d', String( findings.length ) );
		var html = '<div class="wenpai-stack">';
		html += noticeHtml( 'warn', summary );
		html += '<div class="wenpai-table-wrap"><table class="tbl"><thead><tr>';
		html += '<th scope="col">来源内容</th><th scope="col">风险用语</th><th scope="col">上下文摘录</th><th scope="col">合规替代建议</th><th scope="col">操作</th>';
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
			html += '</div></td></tr>';
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

	async function runFullSiteScan() {
		if ( ! cfg.restScanSiteUrl ) {
			return;
		}
		var scope = document.querySelector( '[data-wpicp-scanner]' ) || document;
		var progressWrap = scope.querySelector( '[data-wpicp-scan-progress]' );
		var resultsWrap = scope.querySelector( '[data-wpicp-scan-results]' );
		var buttons = document.querySelectorAll( '[data-wpicp-scan-start]' );

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
					body: JSON.stringify( { page: page, per_page: 20, accumulated_hits: allFindings.length } ),
				} );
				if ( ! res.ok ) {
					throw new Error( 'HTTP ' + res.status );
				}
				var data = await res.json();
				totalPages = Math.max( 1, parseInt( data.total_pages || 1, 10 ) );
				if ( Array.isArray( data.items ) ) {
					allFindings = allFindings.concat( data.items );
				}
				setProgress( progressWrap, Math.min( 100, Math.round( ( page / totalPages ) * 100 ) ) );
				page += 1;
			}
			if ( progressWrap ) {
				progressWrap.hidden = true;
			}
			renderScanResults( resultsWrap, allFindings );
			var hitStat = document.querySelector( '[data-wenpai-stat-hub="wpicp-adlaw-drawer"] .wenpai-statbar__item:last-child dd' );
			if ( hitStat ) {
				hitStat.innerHTML = String( allFindings.length ) + ' <small>处风险</small>';
			}
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
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce || '' },
				body: JSON.stringify( { term: term } ),
			} );
			if ( res.ok ) {
				button.textContent = i18n.addedAllow || '已加入白名单';
				toast( ( i18n.addedAllow || '已加入白名单' ) + '：' + term );
			} else {
				button.disabled = false;
			}
		} catch ( e ) {
			button.disabled = false;
		}
	}

	async function runAccessCheck( button ) {
		if ( ! cfg.restAccessCheckUrl ) {
			return;
		}
		var orig = button.textContent;
		button.disabled = true;
		button.textContent = i18n.checking || '检测中…';
		try {
			var res = await window.fetch( cfg.restAccessCheckUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce || '' },
			} );
			if ( res.ok ) {
				toast( '首页备案与接入商体检完成，正在刷新结果…' );
				window.setTimeout( function () {
					window.location.reload();
				}, 500 );
				return;
			}
			toast( '检测请求失败，请稍后重试。' );
		} catch ( e ) {
			toast( '检测请求异常，请检查网络连接。' );
		}
		button.disabled = false;
		button.textContent = orig;
	}

	async function syncWordYeah( button ) {
		if ( ! cfg.restWordYeahSyncUrl ) {
			return;
		}
		var orig = button.textContent;
		button.disabled = true;
		button.textContent = i18n.syncing || '同步中…';
		try {
			var res = await window.fetch( cfg.restWordYeahSyncUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce || '' },
			} );
			var data = res.ok ? await res.json() : null;
			toast( ( data && data.message ) || '已完成远程词库检查。' );
			if ( data && data.ok && data.status === 'updated' ) {
				window.setTimeout( function () {
					window.location.reload();
				}, 700 );
				return;
			}
		} catch ( e ) {
			toast( '暂无法连接 api.wpcy.com，已保持内置词库。' );
		}
		button.disabled = false;
		button.textContent = orig;
	}

	document.addEventListener( 'click', function ( event ) {
		var t = event.target;
		if ( ! t || ! t.closest ) {
			return;
		}

		if ( t.closest( '[data-wpicp-scan-start]' ) ) {
			event.preventDefault();
			runFullSiteScan();
			return;
		}

		var allowBtn = t.closest( '[data-wpicp-allowlist-add]' );
		if ( allowBtn ) {
			event.preventDefault();
			addTermToAllowlist( allowBtn );
			return;
		}

		var accessBtn = t.closest( '[data-wpicp-run-access-check]' );
		if ( accessBtn ) {
			event.preventDefault();
			runAccessCheck( accessBtn );
			return;
		}

		var wyBtn = t.closest( '[data-wpicp-sync-wordyeah]' );
		if ( wyBtn ) {
			event.preventDefault();
			syncWordYeah( wyBtn );
			return;
		}

		// Add custom license compact table row.
		if ( t.closest( '[data-wpicp-add-license]' ) ) {
			event.preventDefault();
			var licBox = document.querySelector( '[data-wpicp-license-rows]' );
			if ( ! licBox ) {
				return;
			}
			var licEmpty = document.querySelector( '[data-wpicp-license-empty]' );
			if ( licEmpty ) {
				licEmpty.hidden = true;
			}
			var licHead = licBox.querySelector( '.wpicp-editor__head' );
			if ( licHead ) {
				licHead.hidden = false;
			}
			var row = document.createElement( 'div' );
			row.className = 'wpicp-editor__row';
			row.innerHTML =
				'<input type="text" data-subkey="label" placeholder="网络文化经营许可证" aria-label="证照名称">' +
				'<input type="text" data-subkey="number" placeholder="粤网文〔2025〕0001-001号" aria-label="许可证编号">' +
				'<input type="url" data-subkey="url" placeholder="https://" aria-label="查验链接">' +
				'<div class="wpicp-editor__acts"><button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-license>移除</button></div>';
			licBox.appendChild( row );
			reindexList( licBox, '.wpicp-editor__row', 'custom_licenses' );
			markDirty( licBox );
			return;
		}

		var delLic = t.closest( '[data-wpicp-del-license]' );
		if ( delLic ) {
			event.preventDefault();
			var lb = delLic.closest( '[data-wpicp-license-rows]' );
			var lr = delLic.closest( '.wpicp-editor__row' );
			if ( lr ) {
				lr.remove();
			}
			reindexList( lb, '.wpicp-editor__row', 'custom_licenses' );
			markDirty( lb );
			return;
		}

		// Add custom badge compact media row.
		if ( t.closest( '[data-wpicp-add-badge]' ) ) {
			event.preventDefault();
			var badgeBox = document.querySelector( '[data-wpicp-custom-badges]' );
			if ( ! badgeBox ) {
				return;
			}
			var bEmpty = document.querySelector( '[data-wpicp-badges-empty]' );
			if ( bEmpty ) {
				bEmpty.hidden = true;
			}
			var bHead = badgeBox.querySelector( '.wpicp-editor__media-head' );
			if ( bHead ) {
				bHead.hidden = false;
			}
			var brow = document.createElement( 'div' );
			brow.className = 'wpicp-editor__media-row';
			brow.innerHTML =
				'<input type="hidden" data-subkey="image_id" value="0">' +
				'<button type="button" class="wpicp-editor__thumb" data-wpicp-pick-badge-img aria-label="选择徽章图片">选择图片</button>' +
				'<input type="text" data-subkey="title" placeholder="如：ISO 27001 信息安全认证" aria-label="徽章名称">' +
				'<input type="url" data-subkey="url" placeholder="https://" aria-label="核验跳转链接">' +
				'<div class="wpicp-editor__acts"><button type="button" class="btn btn-ghost btn-sm" data-wpicp-pick-badge-img>更换</button><button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-badge>移除</button></div>';
			badgeBox.appendChild( brow );
			reindexList( badgeBox, '.wpicp-editor__media-row', 'badges' );
			markDirty( badgeBox );
			return;
		}

		var pickBadge = t.closest( '[data-wpicp-pick-badge-img]' );
		if ( pickBadge ) {
			event.preventDefault();
			var mrow = pickBadge.closest( '.wpicp-editor__media-row' );
			if ( ! mrow ) {
				return;
			}
			openMediaFrame( function ( att ) {
				var hid = mrow.querySelector( 'input[data-subkey="image_id"]' );
				var thumb = mrow.querySelector( '.wpicp-editor__thumb' );
				var titleInp = mrow.querySelector( 'input[data-subkey="title"]' );
				if ( hid ) {
					hid.value = String( att.id );
				}
				if ( thumb && att.url ) {
					thumb.classList.add( 'has-img' );
					thumb.innerHTML = '<img src="' + escHtml( att.url ) + '" alt="">';
				}
				if ( titleInp && ! titleInp.value && att.title ) {
					titleInp.value = att.title;
				}
				markDirty( mrow );
			} );
			return;
		}

		var delBadge = t.closest( '[data-wpicp-del-badge]' );
		if ( delBadge ) {
			event.preventDefault();
			var bb = delBadge.closest( '[data-wpicp-custom-badges]' );
			var br = delBadge.closest( '.wpicp-editor__media-row' );
			if ( br ) {
				br.remove();
			}
			reindexList( bb, '.wpicp-editor__media-row', 'badges' );
			markDirty( bb );
			return;
		}

		// Add electronic certificate cards via Media Library (supports multi-select batch upload).
		if ( t.closest( '[data-wpicp-add-cert]' ) ) {
			event.preventDefault();
			var certGrid = document.querySelector( '[data-wpicp-cert-grid]' );
			var addSlot = certGrid ? certGrid.querySelector( '[data-wpicp-add-cert]' ) : null;
			if ( ! certGrid || ! addSlot ) {
				return;
			}
			openMediaFrame( function ( items ) {
				items.forEach( function ( att ) {
					var card = document.createElement( 'div' );
					card.className = 'wpicp-certificates__card';
					card.setAttribute( 'data-wpicp-cert-card', '1' );
					card.setAttribute( 'data-cert-cat', '营业执照' );
					card.innerHTML =
						'<input type="hidden" data-subkey="image_id" value="' + escHtml( att.id ) + '">' +
						'<input type="hidden" data-subkey="type" value="营业执照">' +
						'<input type="hidden" data-subkey="number" value="">' +
						'<input type="hidden" data-subkey="expires_at" value="">' +
						'<input type="hidden" data-subkey="visible" value="1">' +
						'<div class="wpicp-certificates__stage"><img src="' + escHtml( att.url ) + '" alt=""><div class="wpicp-certificates__wm-admin" data-wpicp-wm-layer aria-hidden="true"></div></div>' +
						'<div class="wpicp-certificates__meta"><span data-cert-type-out>营业执照</span><span data-cert-exp-out>长期有效</span></div>' +
						'<input type="text" data-subkey="title" value="' + escHtml( att.title || '电子证照扫描件' ) + '" aria-label="证照标题">' +
						'<div class="wpicp-certificates__foot"><button type="button" class="btn btn-ghost btn-sm" data-wpicp-cert-edit>编辑信息</button><button type="button" class="btn btn-ghost btn-sm" data-wpicp-del-cert>移除</button></div>';
					certGrid.insertBefore( card, addSlot );
				} );
				reindexList( certGrid, '[data-wpicp-cert-card]', 'certificates' );
				if ( typeof certGrid._wpicpRebuildFilters === 'function' ) {
					certGrid._wpicpRebuildFilters();
				}
				syncCertWatermarks();
				markDirty( certGrid );
			}, true );
			return;
		}

		var delCert = t.closest( '[data-wpicp-del-cert]' );
		if ( delCert ) {
			event.preventDefault();
			var cg = delCert.closest( '[data-wpicp-cert-grid]' );
			var cc = delCert.closest( '[data-wpicp-cert-card]' );
			if ( cc ) {
				cc.remove();
			}
			reindexList( cg, '[data-wpicp-cert-card]', 'certificates' );
			if ( cg && typeof cg._wpicpRebuildFilters === 'function' ) {
				cg._wpicpRebuildFilters();
			}
			markDirty( cg );
			return;
		}

		// Certificate metadata native <dialog>.
		var editCert = t.closest( '[data-wpicp-cert-edit]' );
		if ( editCert ) {
			event.preventDefault();
			var dlg = document.getElementById( 'wpicp-cert-dialog' );
			var cCard = editCert.closest( '[data-wpicp-cert-card]' );
			if ( ! dlg || ! cCard ) {
				return;
			}
			dlg._wpicpCard = cCard;
			var tInp = cCard.querySelector( 'input[data-subkey="title"]' );
			var tyInp = cCard.querySelector( 'input[data-subkey="type"]' );
			var nInp = cCard.querySelector( 'input[data-subkey="number"]' );
			var eInp = cCard.querySelector( 'input[data-subkey="expires_at"]' );
			var vInp = cCard.querySelector( 'input[data-subkey="visible"]' );
			document.getElementById( 'wpicp-dlg-title' ).value = tInp ? tInp.value : '';
			document.getElementById( 'wpicp-dlg-type' ).value = tyInp && tyInp.value ? tyInp.value : '营业执照';
			document.getElementById( 'wpicp-dlg-number' ).value = nInp ? nInp.value : '';
			document.getElementById( 'wpicp-dlg-exp' ).value = eInp ? eInp.value : '';
			var visEl = document.getElementById( 'wpicp-dlg-visible' );
			if ( visEl ) {
				visEl.checked = ! vInp || vInp.value !== '0';
			}
			if ( typeof dlg.showModal === 'function' ) {
				dlg.showModal();
			}
			return;
		}

		if ( t.closest( '[data-wpicp-dlg-close]' ) ) {
			var d1 = document.getElementById( 'wpicp-cert-dialog' );
			if ( d1 && typeof d1.close === 'function' ) {
				d1.close();
			}
			return;
		}

		if ( t.closest( '[data-wpicp-dlg-pick]' ) ) {
			var dPick = document.getElementById( 'wpicp-cert-dialog' );
			var cardPick = dPick && dPick._wpicpCard;
			if ( ! cardPick ) {
				return;
			}
			openMediaFrame( function ( att ) {
				var hid = cardPick.querySelector( 'input[data-subkey="image_id"]' );
				var img = cardPick.querySelector( '.wpicp-certificates__stage img' );
				if ( hid ) {
					hid.value = String( att.id );
				}
				if ( img && att.url ) {
					img.src = att.url;
				}
				markDirty( cardPick );
			} );
			return;
		}

		if ( t.closest( '[data-wpicp-dlg-save]' ) ) {
			var d2 = document.getElementById( 'wpicp-cert-dialog' );
			var target = d2 && d2._wpicpCard;
			if ( target ) {
				var tVal = document.getElementById( 'wpicp-dlg-title' ).value;
				var tyVal = document.getElementById( 'wpicp-dlg-type' ).value.trim() || '营业执照';
				var nVal = document.getElementById( 'wpicp-dlg-number' ).value;
				var eVal = document.getElementById( 'wpicp-dlg-exp' ).value;
				var vEl = document.getElementById( 'wpicp-dlg-visible' );
				var vis = ! vEl || vEl.checked;
				target.setAttribute( 'data-cert-cat', tyVal );
				target.querySelector( 'input[data-subkey="title"]' ).value = tVal;
				target.querySelector( 'input[data-subkey="type"]' ).value = tyVal;
				target.querySelector( 'input[data-subkey="number"]' ).value = nVal;
				target.querySelector( 'input[data-subkey="expires_at"]' ).value = eVal;
				target.querySelector( 'input[data-subkey="visible"]' ).value = vis ? '1' : '0';
				var tyOut = target.querySelector( '[data-cert-type-out]' );
				var eOut = target.querySelector( '[data-cert-exp-out]' );
				if ( tyOut ) {
					tyOut.textContent = tyVal;
				}
				if ( eOut ) {
					eOut.textContent = eVal || '长期有效';
				}
				var hostGrid = target.closest( '[data-wpicp-cert-grid]' );
				if ( hostGrid && typeof hostGrid._wpicpRebuildFilters === 'function' ) {
					hostGrid._wpicpRebuildFilters();
				}
				markDirty( target );
			}
			if ( d2 && typeof d2.close === 'function' ) {
				d2.close();
			}
			toast( '已更新证照台账信息，请点击保存按钮生效。' );
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.name && e.target.name.indexOf( 'cert_watermark_' ) !== -1 ) {
			syncCertWatermarks();
		}
	} );
	document.addEventListener( 'input', function ( e ) {
		if ( e.target && e.target.name && e.target.name.indexOf( 'cert_watermark_text' ) !== -1 ) {
			syncCertWatermarks();
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		initBadgeCatalog();
		initCertCatalog();
		initDisplayWorkbench();
		syncCertWatermarks();
	} );
} )();
