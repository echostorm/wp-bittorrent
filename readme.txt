=== BitTorrent My Blog ===
Contributors: meitar
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WP-BitTorrent&item_number=WP-BitTorrent&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: BitTorrent, torrent, file sharing, p2p
Requires at least: 3.5
Tested up to: 4.1
Stable tag: 0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish your blog as a BitTorrent seed. Automatically make and share torrents for every page on your website.

== Description ==

Bring the power of BitTorrent to your blog in just a few clicks. BitTorrent My Blog automatically creates `.torrent` files for every part of your website, enabling your visitors to download and share copies of your content over the BitTorrent peer-to-peer file sharing network. Your web site itself serves as the web seed for each new torrent.

To turn your web page into a torrent download, simply add a `wp_bittorrent_seed` parameter to the URL. So, for instance, if your blog has a page at the address `http://example.com/about/`, then the torrent download for this page is:

    http://example.com/about/?wp_bittorrent_seed

If you do not use pretty permalinks, then you might have a similar page at an address like `http://example.com/?p=123`, in which case your torrent download for that page is located at:

    http://example.com/?p=123&wp_bittorrent_seed

Why might you want to publish your site on BitTorrent?

* If you have a particularly popular post, replacing it with a web seed to share over BitTorrent can **dramatically reduce the load on your server.**
* If you regularly host controversial content likely to be censored or threatened with a copyright takedown notice, publishing a web seed and encouraging your visitors to re-share it over BitTorrent can be **the difference between being silenced and being heard.**
* Today's centralized architectures are a thing of the past. New Web browsers, like [Project Maelstrom](http://www.pcworld.com/article/2859113/project-maelstrom-detailed-more-info-about-bittorrents-vision-for-a-peer-to-peer-web.html), that use BitTorrent by default are already being experiemented with. **Stay on the cutting edge.**

You don't need to know anything about BitTorrent to use this plugin. Use the zero-configuration out of the box options or customize the generated torrents on the plugin options screen. (The default tracker addresses `udp://tracker.publicbt.com:80` and `udp://open.demonii.com:1337/announce` are used for all generated torrents unless you set your own.)

= New to BitTorrent? =

Read [this gentle introduction to BitTorrent](http://maymay.net/blog/2015/01/03/howto-download-movies-games-books-and-other-digital-media-freely-and-anonymously-using-bittorrent-with-public-proxies/) that clarifies BitTorrent's complexity in very simple language.

Want to try *before* you install? [Download the previous link as a torrent](http://maymay.net/blog/2015/01/03/howto-download-movies-games-books-and-other-digital-media-freely-and-anonymously-using-bittorrent-with-public-proxies/?wp_bittorrent_seed)!

== Installation ==

1. Upload the unzipped `wp-bittorrent` directory to your `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Optionally, configure the plugin's defaults in its settings screen. See [Screenshots](https://wordpress.org/plugins/bittorrent/screenshots/) for some examples.
1. Add links to your site with the special `wp_bittorrent_seed` query string to generate a torrent.

= System requirements =

For all features of this plugin to work, you must be using PHP 5.3, with the [fileinfo extension](https://php.net/manual/en/book.fileinfo.php). (This is available by default on most PHP 5.3 and greater systems.)

== Frequently Asked Questions ==

= The plugin says "mkdir() permission denied"? =
Make sure your WordPress content directory (`wp-content/`) is read and writeable by your webserver. (This is the default on most systems.)

== Screenshots ==

1. The plugin's options screen lets you customize the way your blog is published on BitTorrent. You can leave the default tracker addresses, or set your own. To further improve performance, generated torrent seeds are cached, and you can configure how long the seeds are cached for before they are regenerated.

== Change log ==

= Version 0.1 =

* Initial release.

== Other notes ==

If you like this plugin, **please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WP-BitTorrent&item_number=WP-BitTorrent&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
) for your use of the plugin**, [purchasing one of Meitar's web development books](http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fs%3Fie%3DUTF8%26redirect%3Dtrue%26sort%3Drelevancerank%26search-type%3Dss%26index%3Dbooks%26ref%3Dntt%255Fathr%255Fdp%255Fsr%255F2%26field-author%3DMeitar%2520Moscovitz&tag=maymaydotnet-20&linkCode=ur2&camp=1789&creative=390957) or, better yet, contributing directly to [Meitar's Cyberbusking fund](http://Cyberbusking.org/). (Publishing royalties ain't exactly the lucrative income it used to be, y'know?) Your support is appreciated!
