<?php
namespace GridMaster\Integrations\Beaver;

use GridMaster\Integrations\Embed_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beaver Builder integration loader.
 */
class Loader {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_module' ), 20 );
	}

	/**
	 * Registers the Beaver Builder module.
	 */
	public static function register_module() {
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		require_once __DIR__ . '/Grid_Module.php';

		$choices = Embed_Helper::get_grid_choices();

		$form_sections = array(
			'grid_settings' => array(
				'title'  => __( 'Grid Settings', 'ajax-filter-posts' ),
				'fields' => array(
					'grid_id' => array(
						'type'    => 'select',
						'label'   => __( 'Select Grid', 'ajax-filter-posts' ),
						'default' => '0',
						'options' => $choices,
						'preview' => array(
							'type' => 'refresh',
						),
					),
				),
			),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$create_url = Embed_Helper::get_create_url();
			$manage_url = Embed_Helper::get_manage_url();

			$links_html = '<div style="margin-top:10px;font-size:12px;color:#64748b;line-height:1.6;">';
			if ( $create_url ) {
				$links_html .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#4f46e5;text-decoration:none;">+ %s</a><br>',
					esc_url( $create_url ),
					esc_html__( 'Create New Grid in GridMaster', 'ajax-filter-posts' )
				);
			}
			if ( $manage_url ) {
				$links_html .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#64748b;text-decoration:underline;">%s</a>',
					esc_url( $manage_url ),
					esc_html__( 'Manage All Grids', 'ajax-filter-posts' )
				);
			}
			$links_html .= '</div>';

			$form_sections['grid_settings']['fields']['grid_helper_links'] = array(
				'type'    => 'raw',
				'label'   => '',
				'content' => $links_html,
			);
		}

		\FLBuilder::register_module(
			'GridMaster\Integrations\Beaver\Grid_Module',
			array(
				'general' => array(
					'title'    => __( 'General', 'ajax-filter-posts' ),
					'sections' => $form_sections,
				),
			)
		);
	}
}

Loader::init();
