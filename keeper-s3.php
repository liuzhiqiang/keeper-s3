<?php
/**
 * Plugin Name: Keeper-S3
 * Description: A WordPress plugin to upload attachments to Amazon S3 with flexible storage options (local, S3, or both).
 * Version: 1.0
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author: codecraftsman
 * Text Domain: keeper-s3
 * Domain Path: /languages
 */

// 防止直接访问插件文件
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件路径常量
define('S3KEEPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S3KEEP_ATTACHMENT_STORAGE', 's3keep_attachment_storage');
define('S3KEEPER_PLUGIN_URL', plugins_url('', __FILE__));


function s3keeper_plugin_load_textdomain() {
//    load_plugin_textdomain( 'keeper-s3', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 's3keeper_plugin_load_textdomain' );

require_once S3KEEPER_PLUGIN_DIR . 'includes/front-functions.php';

if (is_admin()) {

    require_once S3KEEPER_PLUGIN_DIR . 'includes/admin-functions.php';

}



// 加载 React 文件



