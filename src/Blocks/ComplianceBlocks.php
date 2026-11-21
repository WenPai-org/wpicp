<?php
/**
 * Native Gutenberg dynamic blocks (`wpicp/compliance-bar` and `wpicp/trust-badges`).
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
	 * Register `wpicp/compliance-bar` and `wpicp/trust-badges`.
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
			? Wenpai_Admin_Menu::url( 'wpicp', array( 'tab' => 'settings' ) )
			: admin_url( 'options-general.php?page=wpicp&tab=settings' );

		wp_localize_script(
			'wpicp-blocks-editor',
			'wpicpBlocksData',
			array(
				'manageUrl' => $manage_url,
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
					'preset'        => array(
						'type'    => 'string',
						'default' => 'standard',
					),
					'align'         => array(
						'type'    => 'string',
						'default' => 'center',
					),
					'separator'     => array(
						'type'    => 'string',
						'default' => '|',
					),
					'theme'         => array(
						'type'    => 'string',
						'default' => 'auto',
					),
					'showCompany'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showIcp'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showMps'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showEdi'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAigc'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showAlgorithm' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showApp'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showMiniapp'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showReport'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPolicy'    => array(
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
	}

	/**
	 * Dynamic render callback for `wpicp/compliance-bar`.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_compliance_bar_block( $attributes = array() ) {
		$map   = array(
			'showCompany'   => 'company',
			'showIcp'       => 'icp',
			'showMps'       => 'mps',
			'showEdi'       => 'edi',
			'showAigc'      => 'aigc',
			'showAlgorithm' => 'algorithm',
			'showApp'       => 'app',
			'showMiniapp'   => 'miniapp',
			'showReport'    => 'report',
			'showPolicy'    => 'policy',
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
}
