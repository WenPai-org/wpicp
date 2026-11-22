<?php
/**
 * Native Gutenberg dynamic blocks:
 * - `wpicp/compliance-bar`
 * - `wpicp/trust-badges`
 * - `wpicp/certificates-directory`
 * - `wpicp/filing-field`
 *
 * @package WenPai\ICP
 */

namespace WenPai\ICP\Blocks;

use WenPai\ICP\Frontend\Renderer;
use WenPai\ICP\Options\SettingsStore;
use Wenpai_Admin_Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers dynamic blocks for FSE block themes (such as Twenty Twenty-Five) and Post Editor.
 */
class ComplianceBlocks {

	/**
	 * Hook block registration to `init`.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_blocks' ), 25 );
	}

	/**
	 * Register all 4 WPICP Gutenberg dynamic blocks.
	 *
	 * @return void
	 */
	public static function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) || ! defined( 'WPICP_PLUGIN_URL' ) ) {
			return;
		}

		$ver = defined( 'WPICP_VERSION' ) ? WPICP_VERSION : '4.0.0';

		wp_register_style(
			'wpicp-frontend',
			WPICP_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$ver
		);

		wp_register_script(
			'wpicp-blocks-editor',
			WPICP_PLUGIN_URL . 'assets/js/blocks.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
				'wp-i18n',
			),
			$ver,
			true
		);

		$manage_url = class_exists( 'Wenpai_Admin_Menu', false )
			? Wenpai_Admin_Menu::url( 'wpicp', array( 'tab' => 'filing' ) )
			: admin_url( 'options-general.php?page=wpicp&tab=filing' );
		$certs_url  = class_exists( 'Wenpai_Admin_Menu', false )
			? Wenpai_Admin_Menu::url(
				'wpicp',
				array(
					'tab'     => 'badges',
					'section' => 'certificates',
				)
			)
			: admin_url( 'options-general.php?page=wpicp&tab=badges&section=certificates' );

		wp_localize_script(
			'wpicp-blocks-editor',
			'wpicpBlocksData',
			array(
				'manageUrl' => $manage_url,
				'certsUrl'  => $certs_url,
			)
		);

		register_block_type(
			'wpicp/compliance-bar',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpicp-blocks-editor',
				'style'           => 'wpicp-frontend',
				'editor_style'    => 'wpicp-frontend',
				'render_callback' => array( __CLASS__, 'render_compliance_bar_block' ),
				'attributes'      => array(
					'preset'           => array(
						'type'    => 'string',
						'default' => 'standard',
					),
					'align'            => array(
						'type'    => 'string',
						'default' => 'center',
					),
					'separator'        => array(
						'type'    => 'string',
						'default' => '|',
					),
					'theme'            => array(
						'type'    => 'string',
						'default' => 'auto',
					),
					'showCompany'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showIcp'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMps'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showEdi'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAigc'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAlgorithm'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showApp'          => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showMiniapp'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showReport'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPolicy'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCertificates' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_block_type(
			'wpicp/trust-badges',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpicp-blocks-editor',
				'style'           => 'wpicp-frontend',
				'editor_style'    => 'wpicp-frontend',
				'render_callback' => array( __CLASS__, 'render_trust_badges_block' ),
				'attributes'      => array(
					'displayType'    => array(
						'type'    => 'string',
						'default' => 'badges',
					),
					'includePresets' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'align'          => array(
						'type'    => 'string',
						'default' => 'center',
					),
					'imageSize'      => array(
						'type'    => 'string',
						'default' => 'thumbnail',
					),
				),
			)
		);

		register_block_type(
			'wpicp/certificates-directory',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpicp-blocks-editor',
				'style'           => 'wpicp-frontend',
				'editor_style'    => 'wpicp-frontend',
				'render_callback' => array( __CLASS__, 'render_certificates_directory_block' ),
				'attributes'      => array(
					'showHeader'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showFilters' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMeta'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showBadges'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'columns'     => array(
						'type'    => 'number',
						'default' => 3,
					),
					'category'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_block_type(
			'wpicp/filing-field',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpicp-blocks-editor',
				'style'           => 'wpicp-frontend',
				'editor_style'    => 'wpicp-frontend',
				'render_callback' => array( __CLASS__, 'render_filing_field_block' ),
				'attributes'      => array(
					'field'  => array(
						'type'    => 'string',
						'default' => 'icp',
					),
					'prefix' => array(
						'type'    => 'string',
						'default' => '',
					),
					'align'  => array(
						'type'    => 'string',
						'default' => 'left',
					),
				),
			)
		);
	}

	/**
	 * Dynamic render callback for `wpicp/compliance-bar`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_compliance_bar_block( $attributes = array() ) {
		$map   = array(
			'showCompany'      => 'company',
			'showIcp'          => 'icp',
			'showMps'          => 'mps',
			'showEdi'          => 'edi',
			'showAigc'         => 'aigc',
			'showAlgorithm'    => 'algorithm',
			'showApp'          => 'app',
			'showMiniapp'      => 'miniapp',
			'showReport'       => 'report',
			'showPolicy'       => 'policy',
			'showCertificates' => 'certificates',
		);
		$items = array( 'custom_licenses' );
		foreach ( $map as $attr => $key ) {
			if ( ! array_key_exists( $attr, $attributes ) || ! empty( $attributes[ $attr ] ) ) {
				$items[] = $key;
			}
		}

		$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

		return Renderer::render_compliance_bar(
			array(
				'preset'        => isset( $attributes['preset'] ) ? (string) $attributes['preset'] : 'standard',
				'align'         => isset( $attributes['align'] ) ? (string) $attributes['align'] : 'center',
				'separator'     => isset( $attributes['separator'] ) ? (string) $attributes['separator'] : '|',
				'theme'         => isset( $attributes['theme'] ) ? (string) $attributes['theme'] : 'auto',
				'items'         => $items,
				'demo_fallback' => $is_editor_preview,
			)
		);
	}

	/**
	 * Dynamic render callback for `wpicp/trust-badges`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_trust_badges_block( $attributes = array() ) {
		$type    = isset( $attributes['displayType'] ) ? (string) $attributes['displayType'] : 'badges';
		$align   = isset( $attributes['align'] ) ? (string) $attributes['align'] : 'center';
		$size    = isset( $attributes['imageSize'] ) ? (string) $attributes['imageSize'] : 'thumbnail';
		$presets = ! array_key_exists( 'includePresets', $attributes ) || ! empty( $attributes['includePresets'] );

		$parts = array();

		if ( 'badges' === $type || 'both' === $type ) {
			$b = Renderer::render_badges_list(
				array(
					'include_presets' => $presets,
					'align'           => $align,
					'size'            => $size,
				)
			);
			if ( '' !== $b ) {
				$parts[] = $b;
			}
		}

		if ( 'certificates' === $type || 'both' === $type ) {
			$c = Renderer::shortcode_certificates(
				array(
					'full'  => 'full' === $size ? 'true' : 'false',
					'align' => $align,
				)
			);
			if ( '' !== $c ) {
				$parts[] = $c;
			}
		}

		if ( empty( $parts ) && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$demo                     = SettingsStore::effective();
			$demo['selected_presets'] = array( 'miit', 'psb', '12377' );
			return Renderer::render_badges_list(
				array(
					'include_presets' => true,
					'align'           => $align,
					'size'            => $size,
				),
				$demo
			);
		}

		return implode( '', $parts );
	}

	/**
	 * Dynamic render callback for `wpicp/certificates-directory`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_certificates_directory_block( $attributes = array() ) {
		$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

		$html = Renderer::render_certificates_directory(
			array(
				'show_header'   => ! array_key_exists( 'showHeader', $attributes ) || ! empty( $attributes['showHeader'] ),
				'show_filters'  => ! array_key_exists( 'showFilters', $attributes ) || ! empty( $attributes['showFilters'] ),
				'show_meta'     => ! array_key_exists( 'showMeta', $attributes ) || ! empty( $attributes['showMeta'] ),
				'show_badges'   => ! array_key_exists( 'showBadges', $attributes ) || ! empty( $attributes['showBadges'] ),
				'columns'       => isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3,
				'category'      => isset( $attributes['category'] ) ? (string) $attributes['category'] : '',
				'demo_fallback' => $is_editor_preview,
			)
		);
		if ( function_exists( 'get_block_wrapper_attributes' ) && ! $is_editor_preview && isset( $attributes['align'] ) && in_array( (string) $attributes['align'], array( 'wide', 'full' ), true ) ) {
			return '<div class="align' . esc_attr( (string) $attributes['align'] ) . '">' . $html . '</div>';
		}
		return $html;
	}

	/**
	 * Dynamic render callback for `wpicp/filing-field` (block equivalent of legacy field shortcodes).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_filing_field_block( $attributes = array() ) {
		$field  = isset( $attributes['field'] ) ? sanitize_key( (string) $attributes['field'] ) : 'icp';
		$prefix = isset( $attributes['prefix'] ) ? trim( (string) $attributes['prefix'] ) : '';
		$align  = isset( $attributes['align'] ) && in_array( (string) $attributes['align'], array( 'left', 'center' ), true )
			? (string) $attributes['align']
			: 'left';

		$html = Renderer::shortcode_wpicp( array( 'field' => $field ) );
		if ( '' === $html && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$html = '<span>' . esc_html__( '（当前字段未填写，请在文派备案后台补充）', 'wpicp' ) . '</span>';
		}
		if ( '' === $html ) {
			return '';
		}

		Renderer::enqueue_assets();
		$cls   = 'wpicp-bar wpicp-bar--align-' . $align;
		$label = '' !== $prefix ? '<span class="wpicp-bar__item">' . esc_html( $prefix ) . '</span>' : '';
		return '<div class="' . esc_attr( $cls ) . '"><span class="wpicp-bar__item">' . $label . $html . '</span></div>';
	}
}
