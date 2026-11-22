<?php
/**
 * Offline Chinese Advertising Law superlative and prohibited expression scanner for WPICP.
 *
 * Migrated from `bisheng-html` into `WPICP` (`wpicp`) as the single compliance
 * source of truth across the WenPai WordPress suite.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Compliance;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Millisecond offline CN Advertising Law scanner and `wenpai_ad_law_scan` filter provider.
 */
class AdLawScanner {

	/**
	 * Mask character used to neutralize safe compounds and allowlisted phrases
	 * while preserving exact UTF-8 character offsets.
	 */
	const MASK_CHAR = '□';

	/**
	 * User-facing element attributes inspected in addition to visible text nodes.
	 *
	 * @var string[]
	 */
	const USER_FACING_ATTRS = array( 'alt', 'placeholder', 'title' );

	/**
	 * Register suite-wide filter hook so companion plugins (`bisheng-html`, themes, etc.)
	 * can invoke `apply_filters( 'wenpai_ad_law_scan', array(), $html, $allowlist )`.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wenpai_ad_law_scan', array( __CLASS__, 'filter_scan' ), 10, 3 );
	}

	/**
	 * Filter callback for `wenpai_ad_law_scan`.
	 *
	 * @param array<int, array<string, mixed>> $findings  Initial findings array.
	 * @param string                           $html      HTML fragment to scan.
	 * @param string[]                         $allowlist Optional caller allowlist.
	 * @return array<int, array{term:string, category:string, selector:string, excerpt:string, suggestions:string[]}>
	 */
	public static function filter_scan( $findings, $html = '', $allowlist = array() ) {
		if ( ! self::is_enabled() ) {
			return is_array( $findings ) ? $findings : array();
		}
		$site_allowlist = self::get_site_allowlist();
		$extra          = is_array( $allowlist ) ? $allowlist : array();
		$merged_allow   = array_merge( $site_allowlist, $extra );

		return self::scan_html( (string) $html, $merged_allow );
	}

	/**
	 * Check whether offline Ad-Law scanning is enabled on the current site/network.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = is_multisite() && '1' !== (string) get_site_option( 'wpicp_allow_subsite_override', '1' )
			? get_site_option( 'wpicp_network_settings', null )
			: get_option( 'wpicp_settings', function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_network_settings', null ) : null );
		if ( is_array( $settings ) && array_key_exists( 'ad_law_enabled', $settings ) ) {
			return ! empty( $settings['ad_law_enabled'] ) && '0' !== (string) $settings['ad_law_enabled'];
		}

		$val = is_multisite() && '1' !== (string) get_site_option( 'wpicp_allow_subsite_override', '1' )
			? get_site_option( 'wpicp_ad_law_enabled', '1' )
			: get_option( 'wpicp_ad_law_enabled', function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_ad_law_enabled', '1' ) : '1' );

		return '0' !== (string) $val;
	}

	/**
	 * Retrieve configured site/network custom allowlist terms.
	 *
	 * @return string[]
	 */
	public static function get_site_allowlist() {
		$settings = is_multisite() && '1' !== (string) get_site_option( 'wpicp_allow_subsite_override', '1' )
			? get_site_option( 'wpicp_network_settings', null )
			: get_option( 'wpicp_settings', function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_network_settings', null ) : null );
		if ( is_array( $settings ) && isset( $settings['ad_law_allowlist'] ) ) {
			$raw = $settings['ad_law_allowlist'];
		} else {
			$raw = is_multisite() && '1' !== (string) get_site_option( 'wpicp_allow_subsite_override', '1' )
				? get_site_option( 'wpicp_ad_law_allowlist', array() )
				: get_option( 'wpicp_ad_law_allowlist', function_exists( 'get_site_option' ) ? get_site_option( 'wpicp_ad_law_allowlist', array() ) : array() );
		}

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\r\n,，]+/u', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $item ) {
			if ( is_scalar( $item ) ) {
				$clean = sanitize_text_field( trim( (string) $item ) );
				if ( '' !== $clean ) {
					$out[] = $clean;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Human-readable Chinese label for a finding category.
	 *
	 * @param string $category Category slug (`absolute` | `unverified-promise` | `authority`).
	 * @return string
	 */
	public static function category_label( $category ) {
		switch ( (string) $category ) {
			case 'unverified-promise':
				return __( '无法证实承诺', 'wpicp' );
			case 'authority':
				return __( '违规权威背书', 'wpicp' );
			case 'regulated':
				return __( '医疗/金融敏感词', 'wpicp' );
			case 'absolute':
			default:
				return __( '绝对化极限词', 'wpicp' );
		}
	}

	/**
	 * Safe operational compounds that contain substrings like `第一` or `唯一`
	 * without constituting prohibited superlative claims.
	 *
	 * @return string[]
	 */
	public static function safe_compounds() {
		$defaults = array(
			'第一时间',
			'第一步',
			'第一阶段',
			'第一页',
			'第一课',
			'第一行',
			'第一列',
			'第一季度',
			'第一年',
			'第一天',
			'第一周',
			'第一月',
			'第一章',
			'第一节',
			'第一版',
			'第一轮',
			'第一批',
			'第一项',
			'第一条',
			'第一方',
			'第一人称',
			'第一部分',
			'唯一标识',
			'唯一标识符',
			'唯一ID',
			'唯一id',
			'唯一Id',
			'唯一码',
			'唯一键',
			'唯一索引',
		);

		if ( class_exists( __NAMESPACE__ . '\\WordYeahClient' ) ) {
			$cached = WordYeahClient::cached();
			if ( ! empty( $cached['safe_compounds'] ) ) {
				$defaults = array_values( array_unique( array_merge( $defaults, $cached['safe_compounds'] ) ) );
			}
		}

		$filtered = apply_filters( 'wenpai_ad_law_safe_compounds', $defaults );
		return is_array( $filtered ) ? $filtered : $defaults;
	}

	/**
	 * Built-in CN Advertising Law trigger dictionary (45 expressions across
	 * `absolute`, `unverified-promise`, and `authority` categories).
	 *
	 * @return array<string, array{category:string, suggestions:string[]}>
	 */
	public static function dictionary() {
		$defaults = array(
			// 1. Absolute / superlative claims (`absolute`).
			'国家级'    => array(
				'category'    => 'absolute',
				'suggestions' => array( '行业高标准', '专业级', '成熟规范' ),
			),
			'世界级'    => array(
				'category'    => 'absolute',
				'suggestions' => array( '国际水准', '高品质', '高规格' ),
			),
			'最高级'    => array(
				'category'    => 'absolute',
				'suggestions' => array( '高阶版', '旗舰规格', '专业水准' ),
			),
			'全网最低'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '高性价比', '诚意定价', '限时特惠' ),
			),
			'史上最低'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '年度特惠', '限时优享', '诚意回馈' ),
			),
			'全网第一'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '广受好评', '口碑之选', '人气推荐' ),
			),
			'行业第一'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '行业前列', '深耕行业', '成熟品牌' ),
			),
			'世界第一'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '国际知名', '服务全球', '成熟品质' ),
			),
			'销量第一'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '热销单品', '人气畅销', '备受青睐' ),
			),
			'史无前例'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '焕新升级', '重磅推出', '诚意力度' ),
			),
			'绝无仅有'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '别具一格', '独具特色', '匠心设计' ),
			),
			'独一无二'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '特色鲜明', '专属定制', '独具匠心' ),
			),
			'天下第一'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '口碑出众', '经典代表', '品质优选' ),
			),
			'永久免费'   => array(
				'category'    => 'absolute',
				'suggestions' => array( '提供免费版', '基础功能免费', '零门槛体验' ),
			),
			'最佳'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '优选', '理想选择', '推荐方案' ),
			),
			'最优'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '更优', '精心调优', '高匹配度' ),
			),
			'最强'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '强劲', '旗舰性能', '高能表现' ),
			),
			'第一'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '领先', '前列', '代表性' ),
			),
			'唯一'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '专属', '少有', '特色' ),
			),
			'首个'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '创新推出', '早期推出', '新一代' ),
			),
			'首选'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '优选', '热门之选', '推荐选择' ),
			),
			'顶级'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '旗舰级', '高水准', '专业级' ),
			),
			'极致'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '细腻', '出色', '精心打磨' ),
			),
			'极品'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '精品', '甄选好物', '品质之选' ),
			),
			'冠军'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '表现突出', '人气之选', '实力款' ),
			),
			'销冠'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '热销款', '畅销型号', '高人气单品' ),
			),
			'万能'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '多场景适用', '多功能', '广泛兼容' ),
			),
			'巅峰'     => array(
				'category'    => 'absolute',
				'suggestions' => array( '旗舰水准', '代表之作', '高阶体验' ),
			),

			// 2. Unverified / guaranteed-outcome promises (`unverified-promise`).
			'100%有效' => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '经过实测验证', '助力提升效果', '表现稳定' ),
			),
			'100%成功' => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '高稳定度', '成熟流程保障', '全程护航' ),
			),
			'百分之百'   => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '全力保障', '高标准执行', '全面覆盖' ),
			),
			'立竿见影'   => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '快速上手', '高效响应', '短周期见效' ),
			),
			'包治百病'   => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '多维度呵护', '综合调理', '日常养护' ),
			),
			'永不反弹'   => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '长效维持', '持续巩固', '稳定保持' ),
			),
			'无效退款'   => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '支持按约退换', '透明售后保障', '支持试用评估' ),
			),
			'零风险'    => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '风险可控', '提供售后保障', '流程透明' ),
			),
			'稳赚'     => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '具备增长潜力', '商业模式成熟', '长期价值导向' ),
			),
			'包过'     => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '系统化备考辅导', '针对性训练', '助力提升通过率' ),
			),
			'根治'     => array(
				'category'    => 'unverified-promise',
				'suggestions' => array( '针对性改善', '科学缓解', '系统调理' ),
			),

			// 3. Prohibited authority / state endorsements (`authority`).
			'国家机关推荐' => array(
				'category'    => 'authority',
				'suggestions' => array( '符合行业规范', '政企客户采用', '标准合规认证' ),
			),
			'国家免检'   => array(
				'category'    => 'authority',
				'suggestions' => array( '通过质量检测', '符合国家标准', '附第三方检测报告' ),
			),
			'驰名商标'   => array(
				'category'    => 'authority',
				'suggestions' => array( '知名品牌', '注册商标', '多年品牌积淀' ),
			),
			'央视推荐'   => array(
				'category'    => 'authority',
				'suggestions' => array( '媒体报道', '行业关注', '口碑推荐' ),
			),
			'特供'     => array(
				'category'    => 'authority',
				'suggestions' => array( '定制款', '专属批次', '精选礼盒' ),
			),
			'专供'     => array(
				'category'    => 'authority',
				'suggestions' => array( '渠道定制', '企业专属版', '定向配置' ),
			),
		);

		$filtered = apply_filters( 'wenpai_ad_law_dictionary', $defaults );
		return is_array( $filtered ) ? $filtered : $defaults;
	}

	/**
	 * Scan an HTML fragment for Chinese Advertising Law prohibited terms.
	 *
	 * @param string   $html      HTML fragment to scan.
	 * @param string[] $allowlist Optional site-specific allowlisted phrases.
	 * @return array<int, array{term:string, category:string, selector:string, excerpt:string, suggestions:string[]}>
	 */
	public static function scan_html( $html, array $allowlist = array() ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return array();
		}

		// Strip <script>, <style>, and HTML comments before DOM parsing.
		$cleaned = preg_replace( '/<!--[\s\S]*?-->/', '', $html );
		$cleaned = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', (string) $cleaned );
		$cleaned = preg_replace( '/<style\b[^>]*>[\s\S]*?<\/style>/i', '', (string) $cleaned );

		if ( '' === trim( (string) $cleaned ) ) {
			return array();
		}

		$exclusions = self::prepare_exclusions( $allowlist );
		$dictionary = self::ordered_dictionary();
		$segments   = self::extract_visible_segments( (string) $cleaned );
		$findings   = array();

		foreach ( $segments as $segment ) {
			$hits = self::scan_text_segment(
				$segment['text'],
				$segment['selector'],
				$dictionary,
				$exclusions
			);
			foreach ( $hits as $hit ) {
				$findings[] = $hit;
			}
		}

		return $findings;
	}

	/**
	 * Build sorted exclusion list combining built-in safe compounds and `$allowlist`.
	 *
	 * @param string[] $allowlist Custom allowlist terms.
	 * @return string[]
	 */
	private static function prepare_exclusions( array $allowlist ) {
		$merged = self::safe_compounds();
		foreach ( $allowlist as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$clean = trim( (string) $item );
			if ( '' !== $clean && ! in_array( $clean, $merged, true ) ) {
				$merged[] = $clean;
			}
		}
		usort(
			$merged,
			static function ( $a, $b ) {
				return self::str_len( $b ) - self::str_len( $a );
			}
		);
		return $merged;
	}

	/**
	 * Return dictionary sorted longest-term-first so specific phrases match
	 * before shorter substrings (e.g. `全网第一` before `第一`).
	 *
	 * @return array<string, array{category:string, suggestions:string[]}>
	 */
	private static function ordered_dictionary() {
		$dict     = self::dictionary();
		$settings = function_exists( 'get_option' ) ? get_option( 'wpicp_settings', array() ) : array();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$remote_on = ! array_key_exists( 'ad_law_remote_sync', $settings ) || ! empty( $settings['ad_law_remote_sync'] );
		if ( $remote_on && class_exists( __NAMESPACE__ . '\\WordYeahClient' ) ) {
			$cached = WordYeahClient::cached();
			if ( ! empty( $cached['terms'] ) ) {
				$dict = array_merge( $dict, $cached['terms'] );
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpicp_ad_law_rules', $dict );
			if ( is_array( $filtered ) ) {
				$dict = $filtered;
			}
		}

		// Filter by active rule groups when explicitly configured.
		if ( isset( $settings['ad_law_rule_groups'] ) && is_array( $settings['ad_law_rule_groups'] ) && ! empty( $settings['ad_law_rule_groups'] ) ) {
			$active = array();
			foreach ( $settings['ad_law_rule_groups'] as $grp ) {
				if ( 'promise' === (string) $grp ) {
					$active[] = 'unverified-promise';
				} else {
					$active[] = (string) $grp;
				}
			}
			$dict = array_filter(
				$dict,
				static function ( $meta ) use ( $active ) {
					$cat = is_array( $meta ) && isset( $meta['category'] ) ? (string) $meta['category'] : 'absolute';
					return in_array( $cat, $active, true );
				}
			);
		}

		$keys = array_keys( $dict );
		usort(
			$keys,
			static function ( $a, $b ) {
				return self::str_len( (string) $b ) - self::str_len( (string) $a );
			}
		);
		$ordered = array();
		foreach ( $keys as $key ) {
			$ordered[ (string) $key ] = $dict[ $key ];
		}
		return $ordered;
	}

	/**
	 * Extract visible text nodes and user-facing attributes (`alt`, `placeholder`, `title`)
	 * with deterministic CSS selectors.
	 *
	 * @param string $cleaned_html HTML with script/style/comments removed.
	 * @return array<int, array{text:string, selector:string}>
	 */
	private static function extract_visible_segments( $cleaned_html ) {
		$doc        = new DOMDocument( '1.0', 'UTF-8' );
		$use_errors = libxml_use_internal_errors( true );
		$loaded     = $doc->loadHTML(
			'<?xml encoding="utf-8" ?><div id="wpicp-adlaw-root">' . $cleaned_html . '</div>',
			LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		if ( ! $loaded ) {
			return array(
				array(
					'text'     => wp_strip_all_tags( $cleaned_html ),
					'selector' => 'root',
				),
			);
		}

		$root = null;
		foreach ( $doc->getElementsByTagName( 'div' ) as $candidate ) {
			if ( 'wpicp-adlaw-root' === $candidate->getAttribute( 'id' ) ) {
				$root = $candidate;
				break;
			}
		}
		if ( ! $root instanceof DOMElement ) {
			return array();
		}

		$segments = array();
		self::walk_dom_nodes( $root, $root, $segments );
		return $segments;
	}

	/**
	 * Depth-first walk collecting user-facing attribute values and text nodes.
	 *
	 * @param DOMNode                                         $node      Current node.
	 * @param DOMElement                                      $root      Wrapper root node.
	 * @param array<int, array{text:string, selector:string}> &$segments Collected segments.
	 * @return void
	 */
	private static function walk_dom_nodes( DOMNode $node, DOMElement $root, array &$segments ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHP DOMNode properties.
		if ( $node instanceof DOMElement && $node !== $root ) {
			$tag = strtolower( $node->tagName );
			if ( in_array( $tag, array( 'script', 'style' ), true ) ) {
				return;
			}

			$el_selector = self::build_selector( $node, $root );
			foreach ( self::USER_FACING_ATTRS as $attr ) {
				if ( ! $node->hasAttribute( $attr ) ) {
					continue;
				}
				$val = trim( (string) $node->getAttribute( $attr ) );
				if ( '' !== $val ) {
					$segments[] = array(
						'text'     => $val,
						'selector' => $el_selector . '[' . $attr . ']',
					);
				}
			}
		}

		if ( $node instanceof DOMText ) {
			$text = preg_replace( '/\s+/u', ' ', (string) $node->nodeValue );
			$text = trim( (string) $text );
			if ( '' !== $text ) {
				$parent     = $node->parentNode;
				$selector   = ( $parent instanceof DOMElement && $parent !== $root )
					? self::build_selector( $parent, $root )
					: 'root';
				$segments[] = array(
					'text'     => $text,
					'selector' => $selector,
				);
			}
			return;
		}

		if ( $node->hasChildNodes() ) {
			foreach ( $node->childNodes as $child ) {
				self::walk_dom_nodes( $child, $root, $segments );
			}
		}
	}

	/**
	 * Build a readable CSS selector for `$el` relative to `$root`.
	 *
	 * @param DOMElement $el   Target element.
	 * @param DOMElement $root Fragment root wrapper.
	 * @return string
	 */
	private static function build_selector( DOMElement $el, DOMElement $root ) {
		$parts = array();
		$curr  = $el;

		while ( $curr instanceof DOMElement && $curr !== $root ) {
			$tag = strtolower( $curr->tagName );

			$id = trim( (string) $curr->getAttribute( 'id' ) );
			if ( '' !== $id && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id ) ) {
				array_unshift( $parts, $tag . '#' . $id );
				break;
			}

			$bs_id = trim( (string) $curr->getAttribute( 'data-bs-id' ) );
			if ( '' !== $bs_id && preg_match( '/^[a-zA-Z0-9_-]+$/', $bs_id ) ) {
				array_unshift( $parts, $tag . '[data-bs-id="' . $bs_id . '"]' );
				break;
			}

			$nth       = 1;
			$same_tags = 0;
			if ( $curr->parentNode instanceof DOMNode ) {
				foreach ( $curr->parentNode->childNodes as $sib ) {
					if ( $sib instanceof DOMElement && strtolower( $sib->tagName ) === $tag ) {
						++$same_tags;
						if ( $sib === $curr ) {
							$nth = $same_tags;
						}
					}
				}
			}

			$step = ( $same_tags > 1 ) ? sprintf( '%s:nth-of-type(%d)', $tag, $nth ) : $tag;
			array_unshift( $parts, $step );
			$curr = $curr->parentNode;
		}

		return empty( $parts ) ? 'root' : implode( ' > ', $parts );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Scan a single visible text or attribute string against exclusions and dictionary.
	 *
	 * @param string                                                      $raw_text   Original visible text.
	 * @param string                                                      $selector   Element CSS selector.
	 * @param array<string, array{category:string, suggestions:string[]}> $dictionary Ordered trigger dictionary.
	 * @param string[]                                                    $exclusions Safe compounds + allowlist.
	 * @return array<int, array{term:string, category:string, selector:string, excerpt:string, suggestions:string[]}>
	 */
	private static function scan_text_segment( $raw_text, $selector, array $dictionary, array $exclusions ) {
		$masked = $raw_text;

		// Neutralize safe compounds and allowlisted terms while preserving character offsets.
		foreach ( $exclusions as $safe_phrase ) {
			$masked = self::mask_occurrences( $masked, $safe_phrase );
		}

		$hits = array();
		foreach ( $dictionary as $term => $meta ) {
			$term_len = self::str_len( $term );
			$offset   = 0;

			while ( true ) {
				$pos = self::str_ipos( $masked, $term, $offset );
				if ( false === $pos ) {
					break;
				}

				$hits[] = array(
					'term'        => self::str_sub( $raw_text, $pos, $term_len ),
					'category'    => $meta['category'],
					'selector'    => $selector,
					'excerpt'     => self::build_excerpt( $raw_text, $pos, $term_len ),
					'suggestions' => $meta['suggestions'],
				);

				// Mask matched characters so shorter overlapping triggers do not double-fire.
				$masked = self::replace_char_range( $masked, $pos, $term_len );
				$offset = $pos + $term_len;
			}
		}

		return $hits;
	}

	/**
	 * Replace every case-insensitive occurrence of `$needle` in `$text` with `MASK_CHAR`.
	 *
	 * @param string $text   UTF-8 text.
	 * @param string $needle Phrase to mask.
	 * @return string
	 */
	private static function mask_occurrences( $text, $needle ) {
		$len = self::str_len( $needle );
		if ( 0 === $len ) {
			return $text;
		}
		$offset = 0;
		while ( true ) {
			$pos = self::str_ipos( $text, $needle, $offset );
			if ( false === $pos ) {
				break;
			}
			$text   = self::replace_char_range( $text, $pos, $len );
			$offset = $pos + $len;
		}
		return $text;
	}

	/**
	 * Replace `$len` UTF-8 characters starting at `$pos` with `MASK_CHAR`.
	 *
	 * @param string $text UTF-8 string.
	 * @param int    $pos  Character start offset.
	 * @param int    $len  Character count.
	 * @return string
	 */
	private static function replace_char_range( $text, $pos, $len ) {
		$mask = str_repeat( self::MASK_CHAR, $len );
		return self::str_sub( $text, 0, $pos ) . $mask . self::str_sub( $text, $pos + $len );
	}

	/**
	 * Extract a concise context excerpt around a matched trigger.
	 *
	 * @param string $raw_text Full segment text.
	 * @param int    $pos      Match start character offset.
	 * @param int    $len      Match character length.
	 * @return string
	 */
	private static function build_excerpt( $raw_text, $pos, $len ) {
		$total  = self::str_len( $raw_text );
		$radius = 16;
		$start  = max( 0, $pos - $radius );
		$end    = min( $total, $pos + $len + $radius );
		$slice  = self::str_sub( $raw_text, $start, $end - $start );

		$prefix = ( $start > 0 ) ? '…' : '';
		$suffix = ( $end < $total ) ? '…' : '';
		return $prefix . $slice . $suffix;
	}

	/**
	 * UTF-8 character length helper with non-mbstring fallback.
	 *
	 * @param string $str Input string.
	 * @return int
	 */
	private static function str_len( $str ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $str, 'UTF-8' );
		}
		return (int) preg_match_all( '/./us', $str );
	}

	/**
	 * UTF-8 case-insensitive substring search helper.
	 *
	 * @param string $haystack Haystack string.
	 * @param string $needle   Needle string.
	 * @param int    $offset   Character offset.
	 * @return int|false
	 */
	private static function str_ipos( $haystack, $needle, $offset = 0 ) {
		if ( function_exists( 'mb_stripos' ) ) {
			$total = self::str_len( $haystack );
			if ( $offset > $total ) {
				return false;
			}
			return mb_stripos( $haystack, $needle, $offset, 'UTF-8' );
		}
		$byte_pos = stripos( $haystack, $needle );
		return false === $byte_pos ? false : $byte_pos;
	}

	/**
	 * UTF-8 substring helper.
	 *
	 * @param string   $str   Input string.
	 * @param int      $start Character start index.
	 * @param int|null $len   Optional character length.
	 * @return string
	 */
	private static function str_sub( $str, $start, $len = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return null === $len
				? (string) mb_substr( $str, $start, null, 'UTF-8' )
				: (string) mb_substr( $str, $start, $len, 'UTF-8' );
		}
		return null === $len ? (string) substr( $str, $start ) : (string) substr( $str, $start, $len );
	}
}
