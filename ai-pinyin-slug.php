<?php
/**
 * Plugin Name: AI 摘要与拼音 Slug
 * Description: 调用 OpenAI 兼容接口，为文章生成摘要和拼音 slug，并填入文章编辑页。
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Sam Su
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIPS_VERSION', '1.0.0' );
define( 'AIPS_PLUGIN_FILE', __FILE__ );
define( 'AIPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-api.php';
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-editor.php';

register_activation_hook( __FILE__, array( 'AIPS_Settings', 'activate' ) );

AIPS_Settings::init();
AIPS_Editor::init();
