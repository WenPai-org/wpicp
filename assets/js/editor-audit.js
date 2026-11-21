/**
 * WPICP 4.0 Block Editor Real-Time Ad-Law Compliance Sidebar & Pre-Publish Panel.
 *
 * Non-blocking advisory inspection of title, excerpt, and content with one-click
 * compliant replacement, allowlist addition, and optional WPMind AI sentence rewrite.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var editorPkg = wp.editor || wp.editPost || {};
	var PluginDocumentSettingPanel = editorPkg.PluginDocumentSettingPanel || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var PluginPrePublishPanel = editorPkg.PluginPrePublishPanel || ( wp.editPost && wp.editPost.PluginPrePublishPanel );
	if ( ! PluginDocumentSettingPanel && ! PluginPrePublishPanel ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var apiFetch = wp.apiFetch;
	var cfg = window.wpicpEditorAudit || {};

	function AuditPanelBody() {
		var postData = useSelect( function ( select ) {
			var ed = select( 'core/editor' );
			if ( ! ed ) {
				return { title: '', content: '', excerpt: '' };
			}
			return {
				title: ed.getEditedPostAttribute( 'title' ) || '',
				content: ed.getEditedPostAttribute( 'content' ) || '',
				excerpt: ed.getEditedPostAttribute( 'excerpt' ) || '',
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;
		var stateFindings = useState( [] );
		var findings = stateFindings[ 0 ];
		var setFindings = stateFindings[ 1 ];

		var stateBusy = useState( false );
		var busy = stateBusy[ 0 ];
		var setBusy = stateBusy[ 1 ];

		var stateRewrites = useState( {} );
		var rewrites = stateRewrites[ 0 ];
		var setRewrites = stateRewrites[ 1 ];

		var runScan = function () {
			setBusy( true );
			apiFetch( {
				path: cfg.restScanPath || '/wpicp/v1/ad-law/scan',
				method: 'POST',
				data: {
					title: postData.title,
					html: postData.content,
					excerpt: postData.excerpt,
				},
			} )
				.then( function ( res ) {
					setFindings( Array.isArray( res && res.findings ) ? res.findings : [] );
				} )
				.catch( function () {} )
				.finally( function () {
					setBusy( false );
				} );
		};

		useEffect(
			function () {
				var timer = window.setTimeout( runScan, 650 );
				return function () {
					window.clearTimeout( timer );
				};
			},
			[ postData.title, postData.content, postData.excerpt ]
		);

		var applyReplacement = function ( term, replacement ) {
			if ( ! term || ! replacement ) {
				return;
			}
			var updates = {};
			if ( postData.title && postData.title.indexOf( term ) !== -1 ) {
				updates.title = postData.title.split( term ).join( replacement );
			}
			if ( postData.excerpt && postData.excerpt.indexOf( term ) !== -1 ) {
				updates.excerpt = postData.excerpt.split( term ).join( replacement );
			}
			if ( postData.content && postData.content.indexOf( term ) !== -1 ) {
				updates.content = postData.content.split( term ).join( replacement );
			}
			if ( Object.keys( updates ).length > 0 ) {
				editPost( updates );
			}
		};

		var addAllowlist = function ( term ) {
			apiFetch( {
				path: cfg.restAllowlistPath || '/wpicp/v1/ad-law/allowlist',
				method: 'POST',
				data: { term: term },
			} ).then( function () {
				runScan();
			} );
		};

		var requestAiRewrite = function ( idx, hit ) {
			setRewrites( function ( prev ) {
				var next = Object.assign( {}, prev );
				next[ idx ] = '正在生成合规改写建议…';
				return next;
			} );
			apiFetch( {
				path: cfg.restRewritePath || '/wpicp/v1/ad-law/rewrite',
				method: 'POST',
				data: {
					term: hit.term,
					excerpt: hit.excerpt,
				},
			} )
				.then( function ( res ) {
					setRewrites( function ( prev ) {
						var next = Object.assign( {}, prev );
						next[ idx ] = ( res && res.rewritten ) || '';
						return next;
					} );
				} )
				.catch( function () {
					setRewrites( function ( prev ) {
						var next = Object.assign( {}, prev );
						next[ idx ] = '未能获取 AI 改写，请选用上方替代词。';
						return next;
					} );
				} );
		};

		return el(
			'div',
			null,
			busy ? el( Spinner, null ) : null,
			! busy && findings.length === 0
				? el(
						Notice,
						{ status: 'success', isDismissible: false },
						'当前标题与正文未检出《广告法》极限词风险。'
				  )
				: null,
			findings.length > 0
				? el(
						Notice,
						{ status: 'warning', isDismissible: false },
						'检出 ' + findings.length + ' 处风险表达（建议核对，不阻断发布）。'
				  )
				: null,
			findings.map( function ( hit, idx ) {
				var suggestions = Array.isArray( hit.suggestions ) ? hit.suggestions : [];
				return el(
					'div',
					{
						key: idx + '-' + hit.term,
						className: 'components-panel__row',
					},
					el(
						'div',
						null,
						el(
							'p',
							null,
							el( 'strong', null, '「' + hit.term + '」' ),
							' · ',
							hit.category_label || hit.category
						),
						el( 'p', null, hit.excerpt ),
						el(
							'div',
							null,
							suggestions.map( function ( sug ) {
								return el(
									Button,
									{
										key: sug,
										variant: 'secondary',
										size: 'small',
										onClick: function () {
											applyReplacement( hit.term, sug );
										},
									},
									'替换为：' + sug
								);
							} ),
							cfg.canManageAllow
								? el(
										Button,
										{
											variant: 'tertiary',
											size: 'small',
											onClick: function () {
												addAllowlist( hit.term );
											},
										},
										'加入白名单'
								  )
								: null,
							cfg.wpmindAvailable
								? el(
										Button,
										{
											variant: 'link',
											size: 'small',
											onClick: function () {
												requestAiRewrite( idx, hit );
											},
										},
										'心思 AI 改写'
								  )
								: null
						),
						rewrites[ idx ] ? el( 'p', null, 'AI 建议：' + rewrites[ idx ] ) : null
					)
				);
			} )
		);
	}

	registerPlugin( 'wpicp-ad-law-editor-audit', {
		icon: 'shield-alt',
		render: function () {
			return el(
				Fragment,
				null,
				PluginDocumentSettingPanel
					? el(
							PluginDocumentSettingPanel,
							{
								name: 'wpicp-ad-law-doc-panel',
								title: '《广告法》合规检查',
							},
							el( AuditPanelBody, null )
					  )
					: null,
				PluginPrePublishPanel
					? el(
							PluginPrePublishPanel,
							{
								title: '《广告法》合规发布前核查',
								initialOpen: true,
							},
							el( AuditPanelBody, null )
					  )
					: null
			);
		},
	} );
} )( window.wp );
