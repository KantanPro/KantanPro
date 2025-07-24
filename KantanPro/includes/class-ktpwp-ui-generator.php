<?php
/**
 * UI Generator class for KTPWP plugin
 *
 * Handles the generation of UI components like controller and workflow sections.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Ui_Generator' ) ) {

	class KTPWP_Ui_Generator {

		/**
		 * Generate controller section
		 *
		 * @since 1.0.0
		 * @return string HTML content for the controller section
		 */
		public function generate_controller() {
			return '<div class="controller">
            <button onclick="alert(\'印刷機能は準備中です\')" title="印刷する" style="padding: 6px 10px; font-size: 12px;">
                <span class="material-symbols-outlined" aria-label="印刷">print</span>
            </button>
        </div>';
		}

		/**
		 * Generate workflow section
		 *
		 * @since 1.0.0
		 * @return string HTML content for the workflow section
		 */
		public function generate_workflow() {
			return '<div class="workflow"></div>';
		}
	}

}
