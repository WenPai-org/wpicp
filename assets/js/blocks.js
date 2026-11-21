/**
 * WPICP 4.0 Native Gutenberg Dynamic Blocks:
 * - `wpicp/compliance-bar`
 * - `wpicp/trust-badges`
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var ExternalLink = wp.components.ExternalLink;
	var ServerSideRender = wp.serverSideRender;
	var manageUrl = ( window.wpicpBlocksData && window.wpicpBlocksData.manageUrl ) || '';

	registerBlockType( 'wpicp/compliance-bar', {
		title: '文派合规公示栏',
		description: '在页脚或页面输出工信部 ICP 备案、公安联网备案、生成式 AI 与算法备案等主体资质。',
		icon: 'shield-alt',
		category: 'widgets',
		keywords: [ 'ICP', '备案', '网安', 'WPICP', '合规' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ],
		},
		edit: function ( props ) {
			var attrs = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: '版式与外观', initialOpen: true },
						el( SelectControl, {
							label: '预设版式',
							value: attrs.preset,
							options: [
								{ label: '标准图文双行', value: 'standard' },
								{ label: '极简文字单行', value: 'minimal' },
								{ label: '完整信任徽章栏', value: 'badges' },
							],
							onChange: function ( v ) {
								setAttributes( { preset: v } );
							},
						} ),
						el( SelectControl, {
							label: '对齐方式',
							value: attrs.align,
							options: [
								{ label: '居中对齐', value: 'center' },
								{ label: '靠左对齐', value: 'left' },
								{ label: '两端分散', value: 'between' },
							],
							onChange: function ( v ) {
								setAttributes( { align: v } );
							},
						} ),
						el( SelectControl, {
							label: '分隔符',
							value: attrs.separator,
							options: [
								{ label: '竖线 |', value: '|' },
								{ label: '圆点 ·', value: '·' },
								{ label: '纯留白', value: 'space' },
							],
							onChange: function ( v ) {
								setAttributes( { separator: v } );
							},
						} ),
						el( SelectControl, {
							label: '配色模式',
							value: attrs.theme,
							options: [
								{ label: '跟随宿主文字色', value: 'auto' },
								{ label: '浅色背景', value: 'light' },
								{ label: '深色背景', value: 'dark' },
							],
							onChange: function ( v ) {
								setAttributes( { theme: v } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: '显示项目', initialOpen: false },
						el( ToggleControl, {
							label: '主办单位名称',
							checked: !! attrs.showCompany,
							onChange: function ( v ) {
								setAttributes( { showCompany: v } );
							},
						} ),
						el( ToggleControl, {
							label: '工信部 ICP 备案号',
							checked: !! attrs.showIcp,
							onChange: function ( v ) {
								setAttributes( { showIcp: v } );
							},
						} ),
						el( ToggleControl, {
							label: '公安机关联网备案号',
							checked: !! attrs.showMps,
							onChange: function ( v ) {
								setAttributes( { showMps: v } );
							},
						} ),
						el( ToggleControl, {
							label: '增值电信许可证（EDI）',
							checked: !! attrs.showEdi,
							onChange: function ( v ) {
								setAttributes( { showEdi: v } );
							},
						} ),
						el( ToggleControl, {
							label: '生成式 AI 服务备案号',
							checked: !! attrs.showAigc,
							onChange: function ( v ) {
								setAttributes( { showAigc: v } );
							},
						} ),
						el( ToggleControl, {
							label: '互联网算法备案号',
							checked: !! attrs.showAlgorithm,
							onChange: function ( v ) {
								setAttributes( { showAlgorithm: v } );
							},
						} ),
						el( ToggleControl, {
							label: '移动应用 APP 备案号',
							checked: !! attrs.showApp,
							onChange: function ( v ) {
								setAttributes( { showApp: v } );
							},
						} ),
						el( ToggleControl, {
							label: '小程序备案号',
							checked: !! attrs.showMiniapp,
							onChange: function ( v ) {
								setAttributes( { showMiniapp: v } );
							},
						} ),
						el( ToggleControl, {
							label: '举报邮箱与电话',
							checked: !! attrs.showReport,
							onChange: function ( v ) {
								setAttributes( { showReport: v } );
							},
						} ),
						el( ToggleControl, {
							label: '合规政策声明链接',
							checked: !! attrs.showPolicy,
							onChange: function ( v ) {
								setAttributes( { showPolicy: v } );
							},
						} ),
						manageUrl ? el( ExternalLink, { href: manageUrl }, '前往文派备案维护资质数据' ) : null
					)
				),
				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: 'wpicp/compliance-bar',
								attributes: attrs,
						  } )
						: el( 'p', null, '文派合规公示栏预览' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'wpicp/trust-badges', {
		title: '文派信任徽章与电子证照',
		description: '展示官方监管认证徽章、合作安全标识或电子营业执照亮照画廊。',
		icon: 'awards',
		category: 'widgets',
		keywords: [ '徽章', '证照', '营业执照', '12377', 'WPICP' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ],
		},
		edit: function ( props ) {
			var attrs = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: '展示设置', initialOpen: true },
						el( SelectControl, {
							label: '展示内容',
							value: attrs.displayType,
							options: [
								{ label: '信任徽章列表', value: 'badges' },
								{ label: '电子证照亮照', value: 'certificates' },
								{ label: '徽章与电子证照同屏', value: 'both' },
							],
							onChange: function ( v ) {
								setAttributes( { displayType: v } );
							},
						} ),
						attrs.displayType !== 'certificates'
							? el( ToggleControl, {
									label: '包含已勾选的官方预设徽章',
									checked: !! attrs.includePresets,
									onChange: function ( v ) {
										setAttributes( { includePresets: v } );
									},
							  } )
							: null,
						el( SelectControl, {
							label: '对齐方式',
							value: attrs.align,
							options: [
								{ label: '居中对齐', value: 'center' },
								{ label: '靠左对齐', value: 'left' },
							],
							onChange: function ( v ) {
								setAttributes( { align: v } );
							},
						} ),
						el( SelectControl, {
							label: '图片尺寸',
							value: attrs.imageSize,
							options: [
								{ label: '缩略图', value: 'thumbnail' },
								{ label: '原始尺寸', value: 'full' },
							],
							onChange: function ( v ) {
								setAttributes( { imageSize: v } );
							},
						} ),
						manageUrl ? el( ExternalLink, { href: manageUrl }, '前往文派备案管理徽章与证照' ) : null
					)
				),
				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: 'wpicp/trust-badges',
								attributes: attrs,
						  } )
						: el( 'p', null, '文派信任徽章与电子证照预览' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
