<?php
/**
 * WP-BitTorrent Template Tags
 *
 * @package plugin
 */

/**
 * Prints the URL to a torrent file of a seeded file.
 */
function wp_bittorrent_metainfo_file ($seed, $return = false) {
    global $wp_bittorrent;
    $wp_bittorrent->makeTorrentFromSeed($seed);
    $x = $wp_bittorrent->getSeedCacheUrl(basename($seed) . '.torrent');
    if ($return) {
        return $x;
    } else {
        print $x;
    }
}

/**
 * Prints the magnet URI for a given torrent.
 */
function wp_bittorrent_magnet_uri ($seed, $return = false) {
    global $wp_bittorrent;
    $torrent = $wp_bittorrent->makeTorrentFromSeed($seed);
    $x = $torrent->magnet() . '&as=' . wp_bittorrent_metainfo_file($seed, true);
    if ($return) {
        return $x;
    } else {
        print $x;
    }
}

/**
 * Prints a magnet URI that points to the torrent file for a particular asset.
 *
 * Project Maelstrom uses these.
 */
function wp_bittorrent_magnet_pointer ($seed, $return = false) {
    global $wp_bittorrent;
    $torrent = $wp_bittorrent->makeTorrentFromSeed($seed);
    $x = 'magnet:?pt=urn:btih:&as=' . $wp_bittorrent->getSeedCacheUrl(basename($seed) . '.torrent');
    if ($return) {
        return $x;
    } else {
        print $x;
    }
}
