<?php
/**
 * WP-BitTorrent torrent feed template
 *
 * This file is a default RSS feed template for .torrent enclosures.
 * Since we don't do anything too fancy, we rely on WordPress's default
 * feed template and only need to hook into its various actions here.
 * You can override this file in your own theme by adding a file with
 * the name "rss-torrent.php" in your theme directory, and it will
 * respect the normal WordPress template hierarchy.
 *
 * See
 *
 *     http://wphierarchy.com/
 *
 * and
 *
 *     https://codex.wordpress.org/Template_Hierarchy
 *
 * for further reading. Happy coding! :)
 *
 * @package plugin
 */
class WP_BitTorrent_Feed {
    public function __construct () {
        add_filter('rss_enclosure', array($this, 'filterEnclosure'));
    }

    public function filterEnclosure ($content) {
        $x = array();
        $enc = array();
        if (preg_match_all('/<enclosure (.+?)>/', $content, $m)) {
            foreach ($m[1] as $match) {
                foreach (wp_kses_hair($match, array('http', 'https')) as $attr) {
                    $x[$attr['name']] = $attr['value'];
                }
            }
        }
        $enc['url'] = wp_bittorrent_metainfo_file($x['url'], true);
        $f = str_replace(trailingslashit(site_url()), ABSPATH, $enc['url']);
        $enc['length'] = (file_exists($f)) ? filesize($f) : filesize($enc['url']);
        $enc['type'] = 'application/x-bittorrent';
        return "\t\t<enclosure url=\"{$enc['url']}\" length=\"{$enc['length']}\" type=\"{$enc['type']}\" />\n";
    }
}

new WP_BitTorrent_Feed();
load_template(ABSPATH . 'wp-includes/feed-rss2.php');
