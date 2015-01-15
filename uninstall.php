<?php
/**
 * WP-BitTorrent
 *
 * @package plugin
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

// Delete options.
delete_option('wp_bittorrent_settings');

// Delete caches.
$cache_dir = WP_CONTENT_DIR . '/wp-bittorrent-seeds';
require_once dirname(__FILE__) . '/wp-bittorrent.php';
WP_BitTorrent::rmtree($cache_dir);
