/**
 * WPICP 4.0 Native Gutenberg Dynamic Blocks:
 * - `wpicp/compliance-bar`          (replaces `[wpicp]`)
 * - `wpicp/trust-badges`            (replaces `[wpicp_badges]`)
 * - `wpicp/certificates-directory`  (replaces `[wpicp_certificates layout="directory"]`)
 * - `wpicp/filing-field`            (replaces 10+ legacy field shortcodes)
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.components ) {
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var createBlock = wp.blocks.createBlock;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ExternalLink = wp.components.ExternalLink;
	var ServerSideRender = wp.serverSideRender;
	var manageUrl = ( window.wpicpBlocksData && window.wpicpBlocksData.manageUrl ) || '';
	var certsUrl = ( window.wpicpBlocksData && window.wpicpBlocksData.certsUrl ) || manageUrl;

	/* 1. Compliance Bar Block */
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
		transforms: {
			from: [
				{
					type: 'shortcode',
					tag: 'wpicp',
					attributes: {
						preset: {
							type: 'string',
							shortcode: function ( a ) {
								return ( a.named && a.named.preset ) || 'standard';
							},
						},
					},
					isMatch: function ( a ) {
						return ! ( a.named && a.named.field );
					},
				},
			],
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
						el( ToggleControl, {
							label: '电子证照公示合集页入口',
							checked: !! attrs.showCertificates,
							onChange: function ( v ) {
								setAttributes( { showCertificates: v } );
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

	/* 2. Trust Badges Block */
	registerBlockType( 'wpicp/trust-badges', {
		title: '文派信任徽章',
		description: '展示官方监管认证徽章、合作安全标识或紧凑型电子证照缩略图。',
		icon: 'awards',
		category: 'widgets',
		keywords: [ '徽章', '证照', '营业执照', '12377', 'WPICP' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ],
		},
		transforms: {
			from: [
				{
					type: 'shortcode',
					tag: 'wpicp_badges',
					attributes: {
						displayType: {
							type: 'string',
							default: 'badges',
						},
					},
				},
			],
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
								{ label: '电子证照缩略图', value: 'certificates' },
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
						certsUrl ? el( ExternalLink, { href: certsUrl }, '前往文派备案管理徽章与证照' ) : null
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
						: el( 'p', null, '文派信任徽章预览' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	/* 3. Public Certificates Directory Block */
	registerBlockType( 'wpicp/certificates-directory', {
		title: '文派证照公示合集',
		description: '生成带主体抬头、分类筛选、防盗水印与左右翻页灯箱的前台企业资质与电子证照公示合集。',
		icon: 'portfolio',
		category: 'widgets',
		keywords: [ '证照', '营业执照', '资质', '亮照', '合集', 'WPICP' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ],
		},
		transforms: {
			from: [
				{
					type: 'shortcode',
					tag: 'wpicp_certificates',
					attributes: {},
				},
			],
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
						{ title: '公示合集布局', initialOpen: true },
						el( SelectControl, {
							label: '每行证照列数',
							value: String( attrs.columns || 3 ),
							options: [
								{ label: '2 列（大图展示）', value: '2' },
								{ label: '3 列（推荐标准）', value: '3' },
								{ label: '4 列（密集多证照）', value: '4' },
							],
							onChange: function ( v ) {
								setAttributes( { columns: parseInt( v, 10 ) || 3 } );
							},
						} ),
						el( TextControl, {
							label: '仅展示指定分类（留空展示全部）',
							value: attrs.category || '',
							placeholder: '例如：营业执照 或 食品经营许可',
							onChange: function ( v ) {
								setAttributes( { category: v } );
							},
						} ),
						el( ToggleControl, {
							label: '显示主体与备案抬头',
							checked: !! attrs.showHeader,
							onChange: function ( v ) {
								setAttributes( { showHeader: v } );
							},
						} ),
						el( ToggleControl, {
							label: '显示分类筛选标签',
							checked: !! attrs.showFilters,
							onChange: function ( v ) {
								setAttributes( { showFilters: v } );
							},
						} ),
						el( ToggleControl, {
							label: '显示证照编号与有效期',
							checked: !! attrs.showMeta,
							onChange: function ( v ) {
								setAttributes( { showMeta: v } );
							},
						} ),
						el( ToggleControl, {
							label: '底部附带官方信任徽章',
							checked: !! attrs.showBadges,
							onChange: function ( v ) {
								setAttributes( { showBadges: v } );
							},
						} ),
						certsUrl ? el( ExternalLink, { href: certsUrl }, '管理电子证照与水印设置' ) : null
					)
				),
				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: 'wpicp/certificates-directory',
								attributes: attrs,
						  } )
						: el( 'p', null, '文派证照公示合集预览' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	/* 4. Single Filing Field Block (Block-First replacement for 10+ legacy field shortcodes) */
	var legacyTagMap = {
		wpicp_license: 'icp',
		wpicp_wangan: 'mps',
		wpicp_company: 'company',
		wpicp_edi: 'edi',
		wpicp_app: 'app',
		wpicp_miniapp: 'miniapp',
		wpicp_email: 'email',
		wpicp_phone: 'phone',
		wpicp_province: 'province',
		wpicp_p: 'province_abbr',
	};

	var fieldTransforms = Object.keys( legacyTagMap ).map( function ( scTag ) {
		return {
			type: 'shortcode',
			tag: scTag,
			transform: function () {
				return createBlock( 'wpicp/filing-field', { field: legacyTagMap[ scTag ] } );
			},
		};
	} );

	registerBlockType( 'wpicp/filing-field', {
		title: '文派单条备案信息',
		description: '按字段单独输出 ICP 备案号、公安网安备号、主体名称、AI/算法备案号或证照公示页链接，替代传统短代码。',
		icon: 'id-alt',
		category: 'widgets',
		keywords: [ '备案号', 'ICP', '公网安备', '主体', '短代码', 'WPICP' ],
		supports: {
			html: false,
		},
		transforms: {
			from: fieldTransforms,
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
						{ title: '备案字段设置', initialOpen: true },
						el( SelectControl, {
							label: '选择输出字段',
							value: attrs.field,
							options: [
								{ label: '工信部 ICP 备案号', value: 'icp' },
								{ label: '公安机关联网备案号（含徽标）', value: 'mps' },
								{ label: '主办单位法定全称', value: 'company' },
								{ label: '增值电信许可证（EDI）', value: 'edi' },
								{ label: '生成式 AI 服务备案号', value: 'aigc' },
								{ label: '境内互联网算法备案号', value: 'algorithm' },
								{ label: '移动应用 APP 备案号', value: 'app' },
								{ label: '小程序备案号', value: 'miniapp' },
								{ label: '举报邮箱与电话', value: 'report' },
								{ label: '不良信息举报邮箱', value: 'email' },
								{ label: '投诉与举报电话', value: 'phone' },
								{ label: '所属省份名称', value: 'province' },
								{ label: '所属省份简称', value: 'province_abbr' },
								{ label: '电子证照公示合集页链接', value: 'certificates_link' },
							],
							onChange: function ( v ) {
								setAttributes( { field: v } );
							},
						} ),
						el( TextControl, {
							label: '前缀文案（选填）',
							value: attrs.prefix || '',
							placeholder: '例如：主办单位：',
							onChange: function ( v ) {
								setAttributes( { prefix: v } );
							},
						} ),
						el( SelectControl, {
							label: '对齐方式',
							value: attrs.align,
							options: [
								{ label: '靠左对齐', value: 'left' },
								{ label: '居中对齐', value: 'center' },
							],
							onChange: function ( v ) {
								setAttributes( { align: v } );
							},
						} ),
						manageUrl ? el( ExternalLink, { href: manageUrl }, '前往文派备案维护字段' ) : null
					)
				),
				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
								block: 'wpicp/filing-field',
								attributes: attrs,
						  } )
						: el( 'p', null, '文派单条备案信息预览' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
