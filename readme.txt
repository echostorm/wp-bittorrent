=== BitTorrent My Blog ===
Contributors: meitar
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WP-BitTorrent&item_number=WP-BitTorrent&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: BitTorrent, torrent, file sharing, p2p
Requires at least: 3.9.1
Tested up to: 4.1
Stable tag: 0.1.5
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish your blog as a BitTorrent seed. Automatically make and share torrents for every page on your website.

== Description ==

Bring the power of BitTorrent to your blog in just a few clicks. BitTorrent My Blog automatically creates `.torrent` files for every part of your website. It automatically serves these torrents to BitTorrent-capable browsers. Even without a BitTorrent-capable Web browser, your visitors can download and share copies of your content over the BitTorrent peer-to-peer file sharing network. Your web site itself serves as the web seed for each new torrent.

= Turn any webpage into a torrent =
With the plugin installed, any web page on your site can be turned into a torrent by adding a `webseed` parameter to the URL. So, for instance, if your blog has a page at the address `http://example.com/about/`, then the torrent download for this page is:

    http://example.com/about/webseed

If you do not use pretty permalinks, then you might have a similar page at an address like `http://example.com/?p=123`, in which case your torrent download for that page is located at:

    http://example.com/?p=123&webseed

See the [plugin FAQ](https://wordpress.org/plugins/bittorrent/faq/) for more details on theming.

Visitors using a natively BitTorrent-capable browser (like Maelstrom), will automatically receive `.torrent` versions of your pages without any configuration needed.

= Torrent anything in your WordPress Media Library =
You can also create torrents out of any files or folders you have on your website with simple shortcodes. (Matching template tags are also available for theme designers.) For example, you have a big file called `my-awesome-video.avi` that you'd like to distribute as a torrent. When you upload it to your site, it's available at `http://example.com/uploads/2015/01/my-awesome-video.avi` so you can make a torrent out of it and get a URL pointing to the torrent with a shortcode that looks like this:

    [wp_bittorrent_tag metainfo_file="http://example.com/uploads/2015/01/my-awesome-video.avi"]Download my video as a torrent![/wp_bittorrent_tag]

This will create an HTML link like this:

    <a href="http://example.com/wp-content/wp-bittorrent-seeds/my-awesome-video.torrent">Download my video as a torrent!</a>

The matching template tag is `<?php do_action('wp_bittorrent_metainfo_file', $url_to_torrent_seed);?>` where `$url_to_torrent_seed` is a URL to the file you want to make into a torrent. For the above example, the complete template code would be:

    <a href="<?php do_action('wp_bittorrent_metainfo_file', content_url('uploads/2015/01/my-awesome-video.avi'));?>">Download my video as a torrent!</a>

See the [Other Notes](https://wordpress.org/plugins/bittorrent/other_notes/) tab for additional shortcodes and template tag information.

= Add a torrent feed to your podcast with zero configuration =
BitTorrent My Blog automatically detects enclosures in RSS2 feeds and creates a new feed that replaces the original direct download enclosure with a torrent metainfo file enclosure. In other words, if you already have a podcast feed for episodes of your show, such as `http://example.com/category/episodes/feed/`, then simply installing this plugin will create another feed at `http://example.com/category/episodes/feed/torrent/`, which is the same as the regular feed but using torrent downloads instead of direct downloads. It couldn't get easier than that!

= Why might you want to publish your site on BitTorrent? =

* If you have a particularly popular post, replacing it with a web seed to share over BitTorrent can **dramatically reduce the load on your server.** This is also extremely helpful for podcasts or other large-size periodicals.
* If you regularly host controversial content likely to be censored or threatened with a copyright takedown notice, publishing a web seed and encouraging your visitors to re-share it over BitTorrent can be **the difference between being silenced and being heard.**
* Today's centralized architectures are a thing of the past. New Web browsers, like [Project Maelstrom](https://torrentfreak.com/bittorrent-inc-works-p2p-powered-browser-141210/), that use BitTorrent by default are already being experiemented with. **Stay on the cutting edge.**

You don't need to know anything about BitTorrent to use this plugin. Use the zero-configuration out of the box options or customize the generated torrents on the plugin options screen. (The default tracker addresses `udp://tracker.publicbt.com:80` and `udp://open.demonii.com:1337/announce` are used for all generated torrents unless you set your own.)

= New to BitTorrent? =

Read [this gentle introduction to BitTorrent](http://maymay.net/blog/2015/01/03/howto-download-movies-games-books-and-other-digital-media-freely-and-anonymously-using-bittorrent-with-public-proxies/) that clarifies BitTorrent's complexity in very simple language.

Want to try *before* you install? [Download the previous link as a torrent](http://maymay.net/blog/2015/01/03/howto-download-movies-games-books-and-other-digital-media-freely-and-anonymously-using-bittorrent-with-public-proxies/webseed)!

== Installation ==

1. Upload the unzipped `wp-bittorrent` directory to your `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Optionally, configure the plugin's defaults in its settings screen. See [Screenshots](https://wordpress.org/plugins/bittorrent/screenshots/) for some examples.
1. Add links to your site with the special `webseed` query string to generate a torrent.

= System requirements =

For all features of this plugin to work, you must be using PHP 5.3, with the [fileinfo extension](https://php.net/manual/en/book.fileinfo.php). (This is available by default on most PHP 5.3 and greater systems.)

== Frequently Asked Questions ==

= What can I turn into a torrent? =
You can turn anything you host on your website into a torrent. Simply upload a file or folder to your website (using either the built-in WordPress media uploader or your favorite file transfer application), and then point to it from any post or page on your website with the `[wp_bittorrent_tag metainfo_file=""]` shortcode.

For example, if you uploaded `my-awesome-video.avi` to your website, and you would ordinarily link to it with a URL like `http://example.com/uploads/2015/01/my-awesome-video.avi`, then you can use the following shortcode to link to its torrent:

    [wp_bittorrent_tag metainfo_file="http://example.com/uploads/2015/01/my-awesome-video.avi"]

= How do I add torrent links to my pages? =
Every page on your site has an associated torrent URL that is the same as the regular URL but with `webseed` or `?webseed` added to the end, depending on whether you use [WordPress's Pretty Permalinks](https://codex.wordpress.org/Using_Permalinks) feature or not, respectively. In your themes, you can programmatically output the torrent link to the current page like this:

    <a href="<?php print add_query_arg('webseed', true, get_permalink());?>">seed this using BitTorrent</a>

= Can I use this plugin to distribute my podcast or netcast over BitTorrent? =
Yes. BitTorrent My Blog automatically detects enclosures in RSS2 feeds and creates a new feed that replaces the original direct download enclosure with a torrent metainfo file enclosure. In other words, if you already have a podcast feed for episodes of your show, such as `http://example.com/category/episodes/feed/`, then simply installing this plugin will create another feed at `http://example.com/category/episodes/feed/torrent/`, which is the same as the regular feed but using torrent downloads instead of direct downloads. It couldn't get easier than that!

= The plugin says "mkdir() permission denied"? =
Make sure your WordPress content directory (`wp-content/`) is read and writeable by your webserver. (This is the default on most systems.)

== Screenshots ==

1. The plugin's options screen lets you customize the way your blog is published on BitTorrent. You can leave the default tracker addresses, or set your own. To further improve performance, generated torrent seeds are cached, and you can configure how long the seeds are cached for before they are regenerated.

== Change log ==

= Version 0.1.5 =

* Feature: BitTorrent-capable browsers (like Maelstrom) are automatically detected and served `.torrent` files, even if they don't explicitly add the `webseed` parameter to their requests. You can disable this feature from the plugin's options screen.

= Version 0.1.4 =

* Feature: Support pretty permalinks. Use `/webseed` at the end of pretty permalink URLs to download the requested page as a web seeded torrent. Plugin now requires WordPress 3.9.1 or later.
* Bugfix: The name of torrents for archive pages now correctly matches the web page's `<title>`.

= Version 0.1.3 =

* Feature: Automatic torrent feed creation from WordPress RSS2 enclosures. This is especially useful for podcasters!

= Version 0.1.2 =

* Feature: Three new shortcodes and matching template tags let you easily turn any file or folder on your website into a torrent download.
* Feature: Option to customize your distribution signature for multi-file torrents.

= Version 0.1.1 =

* Usability: Torrents are downloaded as a folder with an `index.html` file inside. This provides more human-readable filesystem names and integrates with Project Maelstrom more efficiently.

= Version 0.1 =

* Initial release.

== Other notes ==

If you like this plugin, **please consider [making a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=TJLPJYXHSRBEE&lc=US&item_name=WP-BitTorrent&item_number=WP-BitTorrent&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted) for your use of the plugin**, [purchasing one of Meitar's web development books](http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fs%3Fie%3DUTF8%26redirect%3Dtrue%26sort%3Drelevancerank%26search-type%3Dss%26index%3Dbooks%26ref%3Dntt%255Fathr%255Fdp%255Fsr%255F2%26field-author%3DMeitar%2520Moscovitz&tag=maymaydotnet-20&linkCode=ur2&camp=1789&creative=390957) or, better yet, contributing directly to [Meitar's Cyberbusking fund](http://Cyberbusking.org/). (Publishing royalties ain't exactly the lucrative income it used to be, y'know?) Your support is appreciated!

= Template tags and shortcodes =

* `wp_bittorrent_metainfo_file` - Creates a `.torrent` metainfo file and returns the URL to it. Parameters:
    * `$seed` (string) The seed for the torrent. Can be a URL, a local file, or a local folder.
    * `$return` (bool) Whether to return the URL to the torrent or to print it. (Default: `false`, prints it.)
    * Example: `<?php do_action('wp_bittorrent_metainfo_file', content_url('uploads/my-awesome-video.avi'));?>`
* `wp_bittorrent_magnet_uri` - Creates a `.torrent` metainfo file and returns the [magnet URI](https://en.wikipedia.org/wiki/Magnet_URI_scheme) for it. Parameters:
    * `$seed` (string) The seed for the torrent. Can be a URL, a local file, or a local folder.
    * `$return` (bool) Whether to return the URL to the torrent or to print it. (Default: `false`, prints it.)
    * Example: `<?php do_action('wp_bittorrent_magnet_uri', content_url('uploads/my-awesome-video.avi'));?>`
* `wp_bittorrent_magnet_pointer` - Creates a `.torrent` metainfo file and returns a magnet pointer to it. (Mostly useful for Project Maelstrom, at the moment.) Parameters:
    * `$seed` (string) The seed for the torrent. Can be a URL, a local file, or a local folder.
    * `$return` (bool) Whether to return the URL to the torrent or to print it. (Default: `false`, prints it.)
    * Example: `<?php do_action('wp_bittorrent_magnet_pointer', content_url('uploads/my-awesome-video.avi'));?>`

Each of the above template tags has a matching shortcode:

* `[wp_bittorrent_tag metainfo_file="SEED_URL"]`
* `[wp_bittorrent_tag magnet_uri="SEED_URL"]`
* `[wp_bittorrent_tag magnet_pointer="SEED_URL"]`
