<?php
/**
 * Show admin notice
 *
 * @package TutorLMSBunnyNetIntegration\Notice
 * @since v1.0.0
 */

namespace Tutor\BunnyNetIntegration\AdminNotice;

use TutorLMSBunnyNetIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show admin notice typically when Tutor LMS is not exits
 * or active
 *
 * @version  1.0.0
 * @package  TutorLMSBunnyNetIntegration\Notice
 * @category AdminNotice
 * @author   Themeum <support@themeum.com>
 */
class AdminNotice {

	/**
	 * Register hooks
	 *
	 * @since v1.0.0
	 */
	public function __construct() {
		add_action( 'admin_notices', __CLASS__ . '::show_admin_notice' );
	}

	/**
	 * Show notice to the admin area if
	 * Tutor is not active or not available
	 *
	 * @since v1.0.0
	 *
	 * @return void
	 */
	public static function show_admin_notice() {
		$plugin_data = TutorLMSBunnyNetIntegration::meta_data();
		require_once $plugin_data['views'] . '/notice/notice.php';
	}

	/**
	 * Check whether Tutor core has required version installed
	 *
	 * @since v1.0.0
	 *
	 * @return bool | if has return true otherwise false
	 */
	public static function is_tutor_core_has_req_version(): bool {
		$meta_data = TutorLMSBunnyNetIntegration::meta_data();
		$file_path   = WP_PLUGIN_DIR . '/tutor/tutor.php';

		$plugin_data = get_file_data(
			$file_path,
			array(
				'Version' => 'Version',
			)
		);

		$tutor_version          = $plugin_data['Version'];
		$tutor_core_req_version = $meta_data['tutor_req_ver'];
		$is_compatible          = version_compare( $tutor_version, $tutor_core_req_version, '>=' );
		return $is_compatible ? true : false;
	}

	/**
	 * Check if Tutor file is available
	 *
	 * @since v1.0.0
	 *
	 * @return boolean
	 */
	public static function is_tutor_file_available(): bool {
		return file_exists( WP_PLUGIN_DIR . '/tutor/tutor.php' );
	}
}
