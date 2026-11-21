<?php
/**
 * Official regulatory provinces, preset trust badges, and filing number parsers.
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static catalog of Chinese provincial abbreviations, official HTTPS trust badges,
 * and filing number normalization helpers.
 */
class BadgeCatalog {

	/**
	 * Return all 31 provincial-level abbreviations mapped to Chinese names.
	 *
	 * @return array<string, string>
	 */
	public static function provinces() {
		return array(
			'京' => __( '北京', 'wpicp' ),
			'津' => __( '天津', 'wpicp' ),
			'冀' => __( '河北', 'wpicp' ),
			'晋' => __( '山西', 'wpicp' ),
			'蒙' => __( '内蒙古', 'wpicp' ),
			'辽' => __( '辽宁', 'wpicp' ),
			'吉' => __( '吉林', 'wpicp' ),
			'黑' => __( '黑龙江', 'wpicp' ),
			'沪' => __( '上海', 'wpicp' ),
			'苏' => __( '江苏', 'wpicp' ),
			'浙' => __( '浙江', 'wpicp' ),
			'皖' => __( '安徽', 'wpicp' ),
			'闽' => __( '福建', 'wpicp' ),
			'赣' => __( '江西', 'wpicp' ),
			'鲁' => __( '山东', 'wpicp' ),
			'豫' => __( '河南', 'wpicp' ),
			'鄂' => __( '湖北', 'wpicp' ),
			'湘' => __( '湖南', 'wpicp' ),
			'粤' => __( '广东', 'wpicp' ),
			'桂' => __( '广西', 'wpicp' ),
			'琼' => __( '海南', 'wpicp' ),
			'渝' => __( '重庆', 'wpicp' ),
			'川' => __( '四川', 'wpicp' ),
			'黔' => __( '贵州', 'wpicp' ),
			'滇' => __( '云南', 'wpicp' ),
			'藏' => __( '西藏', 'wpicp' ),
			'陕' => __( '陕西', 'wpicp' ),
			'甘' => __( '甘肃', 'wpicp' ),
			'青' => __( '青海', 'wpicp' ),
			'宁' => __( '宁夏', 'wpicp' ),
			'新' => __( '新疆', 'wpicp' ),
		);
	}

	/**
	 * Return select choices for provincial abbreviation field.
	 *
	 * @return array<string, string>
	 */
	public static function province_choices() {
		$choices = array(
			'' => __( '自动识别 / 请选择省份', 'wpicp' ),
		);
		foreach ( self::provinces() as $abbr => $name ) {
			$choices[ $abbr ] = $abbr . ' · ' . $name;
		}
		return $choices;
	}

	/**
	 * Official HTTPS preset trust badges.
	 *
	 * Includes the 12 primary official presets shown in the 4.0 catalog plus
	 * legacy keys (`szcert`, `sz315`, `trustasia`) for lossless migration from `wpicp-license`.
	 *
	 * @param bool $include_legacy Whether to include legacy-only preset keys.
	 * @return array<string, array{title:string, group:string, url:string, file:string}>
	 */
	public static function presets( $include_legacy = true ) {
		$primary = array(
			'miit'     => array(
				'title' => __( '工信部政务服务平台', 'wpicp' ),
				'group' => __( '政务备案', 'wpicp' ),
				'url'   => 'https://beian.miit.gov.cn/',
				'file'  => 'miit.svg',
			),
			'psb'      => array(
				'title' => __( '全国互联网安全管理服务平台', 'wpicp' ),
				'group' => __( '政务备案', 'wpicp' ),
				'url'   => 'https://beian.mps.gov.cn/',
				'file'  => 'psb.svg',
			),
			'cac'      => array(
				'title' => __( '中央网信办（国家互联网信息办公室）', 'wpicp' ),
				'group' => __( '网信监管', 'wpicp' ),
				'url'   => 'https://www.cac.gov.cn/',
				'file'  => 'cac.svg',
			),
			'12377'    => array(
				'title' => __( '12377 违法和不良信息举报中心', 'wpicp' ),
				'group' => __( '网信监管', 'wpicp' ),
				'url'   => 'https://www.12377.cn/',
				'file'  => '12377.jpg',
			),
			'piyao'    => array(
				'title' => __( '中国互联网联合辟谣平台', 'wpicp' ),
				'group' => __( '网信监管', 'wpicp' ),
				'url'   => 'https://www.piyao.org.cn/',
				'file'  => 'piyao.jpg',
			),
			'dzyyzz'   => array(
				'title' => __( '市场监管总局电子营业执照亮照', 'wpicp' ),
				'group' => __( '主体亮照', 'wpicp' ),
				'url'   => 'https://zzlz.gsxt.gov.cn/',
				'file'  => 'dzyyzz.jpg',
			),
			'12315'    => array(
				'title' => __( '全国 12315 消费维权平台', 'wpicp' ),
				'group' => __( '主体亮照', 'wpicp' ),
				'url'   => 'https://www.12315.cn/',
				'file'  => '12315.jpg',
			),
			'wenming'  => array(
				'title' => __( '中国文明网', 'wpicp' ),
				'group' => __( '公共服务', 'wpicp' ),
				'url'   => 'https://www.wenming.cn/',
				'file'  => 'wenming.jpg',
			),
			'cnca'     => array(
				'title' => __( '国家认证认可监督管理委员会', 'wpicp' ),
				'group' => __( '标准认证', 'wpicp' ),
				'url'   => 'https://www.cnca.gov.cn/',
				'file'  => 'cnca.svg',
			),
			'a11y'     => array(
				'title' => __( '互联网信息无障碍服务标识', 'wpicp' ),
				'group' => __( '标准认证', 'wpicp' ),
				'url'   => 'https://www.wza.cn/',
				'file'  => 'a11y.jpg',
			),
			'iso27001' => array(
				'title' => __( 'ISO/IEC 27001 信息安全认证', 'wpicp' ),
				'group' => __( '标准认证', 'wpicp' ),
				'url'   => 'https://www.iso.org/standard/27001',
				'file'  => 'iso-27001.png',
			),
			'knet'     => array(
				'title' => __( '可信网站身份验证', 'wpicp' ),
				'group' => __( '信任验真', 'wpicp' ),
				'url'   => 'https://ss.knet.cn/',
				'file'  => 'kxwz.jpg',
			),
		);

		if ( ! $include_legacy ) {
			return $primary;
		}

		$legacy = array(
			'szcert'    => array(
				'title' => __( '深圳工商网监电子标识', 'wpicp' ),
				'group' => __( '信任验真', 'wpicp' ),
				'url'   => 'https://szcert.ebs.org.cn/',
				'file'  => 'ebs.jpg',
			),
			'sz315'     => array(
				'title' => __( '众信网电子商务诚信认证', 'wpicp' ),
				'group' => __( '信任验真', 'wpicp' ),
				'url'   => 'https://www.sz315.org/',
				'file'  => 'sz315.jpg',
			),
			'trustasia' => array(
				'title' => __( 'TrustAsia 数字安全认证', 'wpicp' ),
				'group' => __( '信任验真', 'wpicp' ),
				'url'   => 'https://www.trustasia.com/',
				'file'  => 'trustasia.svg',
			),
		);

		return $primary + $legacy;
	}

	/**
	 * Return full asset URL for a bundled image inside `assets/images/`.
	 *
	 * @param string $filename Image file name.
	 * @return string
	 */
	public static function image_url( $filename ) {
		$clean = ltrim( (string) $filename, '/' );
		if ( defined( 'WPICP_PLUGIN_URL' ) && '' !== WPICP_PLUGIN_URL ) {
			return trailingslashit( WPICP_PLUGIN_URL ) . 'assets/images/' . $clean;
		}
		return '../../assets/images/' . $clean;
	}

	/**
	 * Parse an ICP filing number into normalized string, province abbreviation,
	 * entity subject number, and optional `-1`/`-2` site sequence suffix.
	 *
	 * @param string $raw Raw ICP filing input.
	 * @return array{normalized:string, province:string, subject:string, sequence:string, valid:bool, url:string}
	 */
	public static function parse_icp( $raw ) {
		$clean = trim( preg_replace( '/\s+/u', '', (string) $raw ) );
		if ( '' === $clean ) {
			return array(
				'normalized' => '',
				'province'   => '',
				'subject'    => '',
				'sequence'   => '',
				'valid'      => false,
				'url'        => 'https://beian.miit.gov.cn/',
			);
		}

		$provinces = implode( '', array_keys( self::provinces() ) );
		$pattern   = '/^([' . $provinces . '])?(?:ICP备|B2-)?(\d{6,12})号?(?:-(\d+[A-Za-z]?))?$/iu';

		if ( preg_match( '/^([' . $provinces . '])ICP备(\d{6,12})号(?:-(\d+))?$/u', $clean, $m ) ) {
			$province = $m[1];
			$subject  = $province . 'ICP备' . $m[2] . '号';
			$seq      = isset( $m[3] ) && '' !== $m[3] ? $m[3] : '';
			$norm     = '' !== $seq ? $subject . '-' . $seq : $subject;
			return array(
				'normalized' => $norm,
				'province'   => $province,
				'subject'    => $subject,
				'sequence'   => $seq,
				'valid'      => true,
				'url'        => 'https://beian.miit.gov.cn/',
			);
		}

		$province = '';
		foreach ( array_keys( self::provinces() ) as $abbr ) {
			if ( 0 === strpos( $clean, $abbr ) ) {
				$province = $abbr;
				break;
			}
		}

		$valid = (bool) $pattern && (bool) preg_match( '/ICP备\d{6,12}号/u', $clean );

		return array(
			'normalized' => $clean,
			'province'   => $province,
			'subject'    => $clean,
			'sequence'   => '',
			'valid'      => $valid,
			'url'        => 'https://beian.miit.gov.cn/',
		);
	}

	/**
	 * Parse a Public Security (MPS / Wangan) filing input into province abbreviation,
	 * pure numeric record code, formatted display label, and standard query URL.
	 *
	 * @param string $raw_input     Raw code or full `粤公网安备 44010602000000号` string.
	 * @param string $province_hint Optional selected province abbreviation.
	 * @return array{province:string, code:string, formatted:string, valid:bool, url:string}
	 */
	public static function parse_mps( $raw_input, $province_hint = '' ) {
		$raw      = trim( (string) $raw_input );
		$province = trim( (string) $province_hint );
		$known    = self::provinces();

		if ( '' !== $province && ! isset( $known[ $province ] ) ) {
			$province = '';
		}

		// Explicit leading province in `$raw` (e.g. `沪公网安备...`) overrides any stale `$province_hint`.
		if ( '' !== $raw ) {
			foreach ( array_keys( $known ) as $abbr ) {
				if ( 0 === strpos( $raw, $abbr ) ) {
					$province = $abbr;
					break;
				}
			}
		}

		$digits = preg_replace( '/\D+/', '', $raw );
		$digits = is_string( $digits ) ? $digits : '';

		if ( '' === $digits ) {
			return array(
				'province'  => $province,
				'code'      => '',
				'formatted' => '',
				'valid'     => false,
				'url'       => 'https://beian.mps.gov.cn/',
			);
		}

		$formatted = ( '' !== $province ? $province : '' ) . '公网安备' . $digits . '号';
		$valid     = '' !== $province && (bool) preg_match( '/^\d{14}$/', $digits );

		return array(
			'province'  => $province,
			'code'      => $digits,
			'formatted' => $formatted,
			'valid'     => $valid,
			'url'       => 'https://beian.mps.gov.cn/#/query/webSearch?code=' . rawurlencode( $digits ),
		);
	}

	/**
	 * Upgrade any legacy `http://` official regulatory link to `https://`.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function enforce_https( $url ) {
		$clean = trim( (string) $url );
		if ( '' === $clean ) {
			return '';
		}
		if ( 0 === stripos( $clean, 'http://' ) ) {
			$clean = 'https://' . substr( $clean, 7 );
		}
		return $clean;
	}
}
