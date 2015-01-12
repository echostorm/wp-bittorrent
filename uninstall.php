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
$cache_dir = content_url() . '/wp_bittorrent_seeds';
array_map('unlink', glob("$cache_dir/*"));
rmdir($cache_dir);
