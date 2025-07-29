<?php
/**
 * Report class for KTPWP plugin
 *
 * Handles report generation, analytics display,
 * and security implementations.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-ktpwp-ui-generator.php';
require_once plugin_dir_path( __FILE__ ) . 'class-ktpwp-graph-renderer.php';

if ( ! class_exists( 'KTPWP_Report_Class' ) ) {

	/**
	 * Report class for managing reports and analytics
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Report_Class {

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// Constructor initialization
		}

		/**
		 * Display report tab view
		 *
		 * @since 1.0.0
		 * @param string $tab_name Tab name
		 * @return void
		 */
		public function Report_Tab_View( $tab_name ) {
			if ( empty( $tab_name ) ) {
				error_log( 'KTPWP: Empty tab_name provided to Report_Tab_View method' );
				return;
			}

			// ライセンスマネージャーのインスタンスを取得
			$license_manager = KTPWP_License_Manager::get_instance();
			$is_license_valid = $license_manager->is_license_valid();

			$ui_generator = new KTPWP_Ui_Generator();
			$graph_renderer = new KTPWP_Graph_Renderer();

			$content = $ui_generator->generate_controller();

			if ( ! $is_license_valid ) {
				$content .= $graph_renderer->render_dummy_graph();
			} else {
				$content .= $graph_renderer->render_graphs();
			}

			return $content;
		}
	}
} // class_exists
