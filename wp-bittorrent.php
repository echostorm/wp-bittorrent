<?php
/**
 * Plugin Name: BitTorrent my Blog
 * Plugin URI: https://github.com/meitar/wp-bittorrent
 * Description: Publish your blog as a BitTorrent seed. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WP-BitTorrent&amp;item_number=WP-BitTorrent&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of WP-BitTorrent">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.1.5
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: http://maymay.net/
 * Text Domain: wp-bittorrent
 * Domain Path: /languages
 */

require_once dirname(__FILE__) . '/lib/Torrent-RW/Torrent.php';

class WP_BitTorrent {
    private $prefix = 'wp_bittorrent_';
    private $default_settings = array(
        'trackers' => array(
            'udp://tracker.publicbt.com:80',
            'udp://open.demonii.com:1337/announce'
        ),
        'max_cache_age' => 86400 // 24 hours, in seconds
    );
    private $seed_cache_dir; //< Directory to store temporary web seeds of blog content.
    private $seed;           //< Filesystem path of the web seed file (or directory).
    private $max_cache_age;  //< Seconds until filesystem cache is considered expired.
    private $torrent_name;   //< The name for the generated torrent.

    public function __construct () {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        $options = get_option($this->prefix . 'settings');
        $this->max_cache_age = (!isset($options['max_cache_age'])) ? $this->default_settings['max_cache_age'] : $options['max_cache_age'];
        $this->debugLog(sprintf(esc_html__('Cache age set to %s', 'wp-bittorrent'), $this->max_cache_age));

        $this->seed_cache_dir = WP_CONTENT_DIR . '/' . str_replace('_', '-', $this->prefix) . 'seeds';

        if (!is_dir($this->seed_cache_dir) && !mkdir($this->seed_cache_dir)) {
            die(sprintf(
                esc_html__('Failed to create the torrent seed cache directory. Make sure your WordPress content directory (%s) is writeable by your webserver', 'wp-bittorrent'),
                WP_CONTENT_DIR
            ));
        }

        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('init', array($this, 'registerRewrites'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));
        add_action('template_redirect', array($this, 'templateRedirect'));

        // Template tag actions.
        add_action($this->prefix . 'metainfo_file', $this->prefix . 'metainfo_file');
        add_action($this->prefix . 'magnet_uri', $this->prefix . 'magnet_uri');
        add_action($this->prefix . 'magnet_pointer', $this->prefix . 'magnet_pointer');

        add_shortcode($this->prefix . 'tag', array($this, 'callActionFromShortcode'));


    }

    public function registerL10n () {
        load_plugin_textdomain('wp-bittorrent', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function registerRewrites () {
        // Recognize URLs like '.*\/webseed\/?'
        add_rewrite_endpoint('webseed', EP_ALL);
        add_rewrite_endpoint($this->prefix . 'seed', EP_ALL); // Pre 0.1.4 compatibility
        // Recognize 'torrent' RSS feeds
        add_feed('torrent', array($this, 'dispatchTorrentFeed'));
    }

    public function getSeedCacheDir () {
        return $this->seed_cache_dir;
    }

    /**
     * Returns the URL to the seed cache directory for use in links, etc.
     *
     * @param string $path A path to append to the seed cache directory URL.
     * return string The URL to the seed cache directory.
     */
    public function getSeedCacheUrl ($path = '') {
        return content_url(trailingslashit(basename($this->getSeedCacheDir()))) . $path;
    }

    /**
     * Calls a template tag action
     */
    public function callActionFromShortcode ($atts, $content = '') {
        $atts = shortcode_atts(array(
            'metainfo_file' => false,
            'magnet_uri' => false,
            'magnet_pointer' => false,
            'id' => false,
            'class' => false,
            'title' => false,
            'style' => false,

        ), $atts);
        $html = '<a';
        foreach ($atts as $k => $v) {
            if ($v) {
                switch ($k) {
                    case 'metainfo_file':
                    case 'magnet_uri':
                    case 'magnet_pointer':
                        $html .= ' href="';
                        $func = $this->prefix . $k;
                        $html .= $func($v, true);
                        $html .= '"';
                        break;
                    default:
                        $html .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
                        break;
                }
            }
        }
        $html .= '>' . ((empty($content)) ? esc_html__('Download torrent', 'wp-bittorrent') : $content) . '</a>';
        return $html;
    }

    private function isBitTorrentUserAgent ($str) {
        // TODO: Share BitTorrent client UA strings with Browscap.org?
        //$x = get_browser();
        // For now, just alternate against a few known agents.
        $uas = array(
            'Torrent',     // Project Maelstrom
            'BTWebClient', // uTorrent
            'Deluge'       // Deluge
        );
        return preg_match('/(?:' . implode('|', $uas) . ')/i', $str);
    }

    public function templateRedirect () {
        global $wp_query, $wp;
        $options = get_option($this->prefix . 'settings');
        // Ensure this is a known torrent client, or that we've been explicitly asked for a webseed
        if (isset($options['no_ua_detect'])) {
            $this->debugLog('Skipping User-Agent detection.');
            if (!isset($wp_query->query_vars['webseed']) && !isset($wp_query->query_vars[$this->prefix . 'seed'])) {
                return;
            }
        } else {
            $this->debugLog(sprintf('Request by User-Agent: %s', $_SERVER['HTTP_USER_AGENT']));
            if (!$this->isBitTorrentUserAgent($_SERVER['HTTP_USER_AGENT'])) {
                if (!isset($wp_query->query_vars['webseed']) && !isset($wp_query->query_vars[$this->prefix . 'seed'])) {
                    return;
                }
            }
        }

        $this->debugLog(sprintf(
            'Generating webseed for URL %s requested by User-Agent: %s',
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_USER_AGENT']
        ));
        // The current request is either one for a webseed explicitly, or it's a request being
        // made by a User Agent who reports to be a BitTorrent client, so return a .torrent file.
        $current_url = add_query_arg($wp->query_string, '', home_url($wp->request));
        $this->seed = $this->seed_cache_dir . '/web-seed-'
            . hash('sha256', get_current_user_id() . $current_url);
        if (!$this->isExpired($this->seed)) {
            $torrent = $this->makeTorrent($this->seed);
            $torrent->send();
        } else {
            // We need a new cache file, so regenerate it. This means
            // we let WordPress do its thing, but we substitute our
            // own shutdown function in place of its default, which
            // simply flushes the output of any buffers. We do this
            // ourselves to ensure we send the right headers & data.
            remove_action('shutdown', 'wp_ob_end_flush_all', 1);
            add_action('shutdown', array($this, 'endBuffer'));
            ob_start(array($this, 'hijackOutput'));
        }
    }

    /**
     * A callback function for PHP's output buffering that replaces its contents
     * with contents more appropriate to a web seed, mostly by packing external
     * resources in the resulting HTML output into the buffer output, itself.
     *
     * The modified output is stored in the plugin's $buffer member variable.
     *
     * @see endBuffer
     * @param string $buffer The current PHP output buffer.
     * @return string Always returns an empty string, the buffer is hijacked.
     */
    private function hijackOutput ($buffer) {
        $this->buffer = $buffer;
        // TODO: Fetch CSS background images and insert them as data URIs inline?
        $options = get_option($this->prefix . 'settings');
        if (!empty($options['use_data_uri'])) {
            $this->buffer = preg_replace_callback(
                '/(\s+(src|href)=["\'])(.*?)(["\'])/',
                array($this, 'embedResource'),
                $this->buffer
            );
        }
        return '';
    }

    private function embedResource ($matches) {
        $str = $matches[3];
        switch ($matches[2]) {
            case 'href':
                $x = get_stylesheet_directory_uri(); // this theme's resources
                $y = includes_url();                 // WP's own resources
                if (0 === preg_match("!(?:$x|$y)!", $matches[3])) {
                    break;
                }
            case 'src':
            default:
                $str = $this->urlToDataURI($matches[3]);
                break;
        }
        return $matches[1] . $str . $matches[4];
    }

    /**
     * Converts the data at a URL into a data URI.
     *
     * @param string $file The URL to retrieve.
     * @return string A data: URI, or the exact same URL if no conversion could be done.
     */
    private function urlToDataURI ($file) {
        if (!class_exists('finfo')) {
            $this->debugLog(sprintf(
                esc_html__('Data URI requested for %s but no available MIME type detection method available.', 'wp-bittorrent'),
                $file
            ));
            return $file;
        }
        $p = parse_url(get_site_url());
        if ('//' === substr($file, 0, 2)) {
            $file = "{$p['scheme']}:$file";
        } else if ('/' === substr($file, 0, 1)) {
            $file = "{$p['scheme']}://{$p['host']}$file";
        }
        $buf = file_get_contents($file);
        $finfo = new finfo(FILEINFO_MIME);
        $uri = 'data:' . $finfo->buffer($buf) . ';base64,' . base64_encode($buf);
        return $uri;
    }

    public function endBuffer () {
        $x = ob_get_level();
        for ($i = 0; $i < $x; $i++) {
            $this->debugLog(sprintf(esc_html__('Cleaning output buffer %s', 'wp-bittorrent'), $i));
            ob_end_clean();
        }
        if (file_exists($this->seed)) {
            self::rmtree($this->seed);
        }
        // TODO: Am I right that there's a double-urlencode()'ing problem here?
        preg_match('/<title>(.+?)<\/title>/', $this->buffer, $m);
        $this->torrent_name = (empty($m[1])) ? get_the_title() : strtr($m[1], ' :/\\', '----'); // Lazy encoding
        if (mkdir($this->seed) && mkdir("{$this->seed}/{$this->torrent_name}")) {
            if (false !== ($s = file_put_contents("{$this->seed}/{$this->torrent_name}/index.html", $this->buffer))) {
                $torrent = $this->makeTorrent($this->seed);
                $torrent->send();
            }
        }
    }

    /**
     * Recursively deletes directories, and removes the directories.
     * Also used in the uninstaller.
     */
    public static function rmtree ($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::rmtree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Generates a torrent file out of a filesystem path.
     *
     * @param string $path Path to a file (or a folder) from which to create a torrent.
     * @return object The Torrent object.
     */
    private function makeTorrent ($path) {
        $url = content_url() . '/'
            . basename($this->seed_cache_dir) . '/' . basename($path) . '/';

        $tr = $this->getTrackers();
        $torrent = new Torrent($path, $tr[0]);
        $torrent->announce($tr);
        // TODO: Option to make the torrent folder structure mimic
        //       the website paths...?
        $torrent->name($this->torrent_name);
        $torrent->httpseeds($url . $this->torrent_name . '/');
        $torrent->url_list(array($url, $url . $this->torrent_name . '/'));
        $this->debugLog(sprintf(
            esc_html__('Made torrent object with data: %s', 'wp-bittorrent'),
            $torrent
        ));
        return $torrent;
    }

    public function makeTorrentFromSeed ($seed) {
        $file = trailingslashit($this->getSeedCacheDir()) . basename($seed) . '.torrent';
        if ($this->isExpired($file)) {
            $tr = $this->getTrackers();
            // Is the seed a directory or a file?
            $x = realpath(str_replace(site_url(), ABSPATH, $seed));
            if (is_dir($x)) {
                // Workaround for Torrent library bug
                // @see https://github.com/adriengibrat/torrent-rw/issues/22
                $sigfile = trailingslashit($x) . 'README-wp-bittorrent.txt';
                $options = get_option($this->prefix . 'settings');
                if (false === file_put_contents($sigfile, $options['sigfile'])) {
                    $this->debugLog(sprintf(esc_html__('Error creating the sigfile %s', 'wp-bittorrent'), $sigfile));
                }
                $webseed = trailingslashit(dirname($seed)); // because $seed becomes torrent root
                $seed = $x;
            }
            $torrent = new Torrent($seed, $tr[0]);
            $torrent->announce($tr);
            if (!empty($webseed)) {
                $torrent->url_list($webseed);
            }
            $torrent->save($file);
        }
        $torrent = new Torrent($file);
        return $torrent;
    }

    private function isExpired ($file) {
        $file = (is_dir($file)) ? "$file/.": $file;
        if (is_readable($file) && $this->max_cache_age > time() - filemtime($file)) {
            return false;
        } else {
            return true;
        }
    }

    public function registerSettings () {
        register_setting(
            $this->prefix . 'settings',
            $this->prefix . 'settings',
            array($this, 'validateSettings')
        );
    }

    public function registerAdminMenu () {
        add_options_page(
            __('BitTorrent my Blog Settings', 'wp-bittorrent'),
            __('BitTorrent my Blog', 'wp-bittorrent'),
            'manage_options',
            $this->prefix . 'settings',
            array($this, 'renderOptionsPage')
        );
    }

    private function getTrackers () {
        $options = get_option($this->prefix . 'settings');
        return (empty($options['trackers'])) ? $this->default_settings['trackers'] : explode("\n", $options['trackers']);
    }

    private function debugLog ($msg = '') {
        $msg = trim(strtoupper(str_replace('_', ' ', $this->prefix))) . ': ' . $msg;
        $options = get_option($this->prefix . 'settings');
        if (!empty($options['debug'])) {
            return error_log($msg);
        }
    }

    private function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-size: larger; width: 70%; margin: 0 auto;"><?php print sprintf(
esc_html__('BitTorrent my Blog is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'wp-bittorrent'),
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WP-BitTorrent&amp;item_number=WP-BitTorrent&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">' . esc_html__('making a donation', 'wp-bittorrent') . '</a>',
'<a href="http://Cyberbusking.org/">' . esc_html__('houseless, jobless, nomadic developer', 'wp-bittorrent') . '</a>'
);?></p>
</div>
<?php
    }

    public function dispatchTorrentFeed () {
        $themed = locate_template('rss-torrent.php');
        if (!empty($themed)) {
            load_template($themed);
        } else {
            load_template(dirname(__FILE__) . '/templates/rss-torrent.php');
        }
    }

    public function activate () {
        $this->registerRewrites();
        flush_rewrite_rules();
    }

    public function deactivate () {
        flush_rewrite_rules();
    }

    /**
     * @param array $input An array of of our unsanitized options.
     * @return array An array of sanitized options.
     */
    public function validateSettings ($input) {
        $safe_input = array();
        foreach ($input as $k => $v) {
            switch ($k) {
                case 'trackers':
                    if (empty($v)) {
                        $errmsg = __('Trackers cannot be empty.', 'wp-bittorrent');
                        add_settings_error($this->prefix . 'settings', 'empty-trackers', $errmsg);
                    }
                    $safe_input[$k] = implode("\n", array_map('sanitize_text_field', explode("\n", $v)));
                break;
                case 'sigfile':
                    // This becomes an inert file, no need for strict checking.
                    $safe_input[$k] = trim($v);
                    break;
                case 'max_cache_age':
                case 'use_data_uri':
                case 'no_ua_detect':
                case 'debug':
                    $safe_input[$k] = intval($v);
                    break;
            }
        }
        return $safe_input;
    }

    /**
     * Writes the HTML for the options page, and each setting, as needed.
     */
    // TODO: Add contextual help menu to this page.
    public function renderOptionsPage () {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-bittorrent'));
        }
        $options = get_option($this->prefix . 'settings');
?>
<h2><?php esc_html_e('BitTorrent my Blog Settings', 'wp-bittorrent');?></h2>
<form method="post" action="options.php">
<?php settings_fields($this->prefix . 'settings');?>
<fieldset><legend><?php esc_html_e('BitTorrent defaults', 'wp-bittorrent');?></legend>
<table class="form-table" summary="<?php esc_attr_e('Default BitTorrent publication options.', 'wp-bittorrent');?>">
    <tbody>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>trackers"><?php esc_html_e('Trackers', 'wp-bittorrent');?></label>
            </th>
            <td>
                <textarea
                    id="<?php esc_attr_e($this->prefix);?>trackers"
                    name="<?php esc_attr_e($this->prefix);?>settings[trackers]"
                    style="width: 85%; min-height: 7em;"
                    placeholder="<?php esc_attr_e('Type default tracker addresses here, one per line', 'wp-bittorrent');?>"><?php print esc_textarea(implode("\n", $this->getTrackers()));?></textarea>
                <p class="description">
                    <?php esc_html_e('Paste tracker addresses, one per line. Trackers help BitTorrent users find published torrents. You need to choose at least one tracker for your torrents to announce themselves to. The more trackers your torrents announce themselves to, the easier it will be for others to find them.', 'wp-bittorrent');?>
                    <a href="http://publicbt.com/" target="_blank"><?php esc_html_e('Learn more about trackers.', 'wp-bittorent')?></a>
                </p>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<fieldset><legend><?php esc_html_e('Plugin defaults', 'wp-bittorrent');?></legend>
<table class="form-table" summary="<?php esc_attr_e('Options for setting additional plugin defaults.', 'wp-bittorrent');?>">
    <tbody>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>max_cache_age"><?php esc_html_e('Maximum cache age', 'wp-bittorrent');?></label>
            </th>
            <td>
                <input id="<?php esc_attr_e($this->prefix);?>max_cache_age" name="<?php esc_attr_e($this->prefix);?>settings[max_cache_age]" value="<?php esc_attr_e($options['max_cache_age']);?>" placeholder="<?php esc_attr_e('Cache age in seconds', 'wp-bittorrent');?>" />
                <p class="description">
                    <?php esc_html_e('Expiration time of the web seed cache (in seconds). For instance, 86400 means 1 day, or 24 hours. 0 means expire immediately.', 'wp-bittorrent');?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>use_data_uri">
                    <?php esc_html_e('Embed assets in web seeds?', 'wp-bittorrent');?>
                </label>
            </th>
            <td>
                <input type="checkbox" id="<?php esc_attr_e($this->prefix);?>use_data_uri" name="<?php esc_attr_e($this->prefix);?>settings[use_data_uri]" value="1" <?php if (isset($options['use_data_uri'])) { checked($options['use_data_uri'], 1); } ?> />
                <label for="<?php esc_attr_e($this->prefix);?>use_data_uri">
                    <span class="description"><?php esc_html_e('When enabled, page assets like images, style sheets, and JavaScripts are embedded directly into the web seed (using the data URI scheme). This can cause web seeds to be bigger and slower to generate, but can result in a better torrent download and less load on your server, especially for popular content.', 'wp-bittorrent');?> <a href="https://php.net/manual/book.fileinfo.php" target="_blank"><?php esc_html_e('Requires PHP Fileinfo extension.', 'wp-bittorrent');?></a></span>
                </label>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>sigfile"><?php esc_html_e('Distribution signature', 'wp-bittorrent');?></label>
            </th>
            <td>
                <textarea
                    id="<?php esc_attr_e($this->prefix);?>sigfile"
                    name="<?php esc_attr_e($this->prefix);?>settings[sigfile]"
                    style="width: 85%; min-height: 7em;"
                    placeholder="<?php esc_attr_e('Type your signature here.', 'wp-bittorrent');?>"><?php if (isset($options['sigfile'])) { print esc_textarea($options['sigfile']); }?></textarea>
                <p class="description">
                    <?php esc_html_e('Include a distribution note to be included along with your multi-file torrents. This is a good place to say thanks to folks who download your content, and to remind them to please seed for as long as they can.', 'wp-bittorrent');?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>no_ua_detect">
                    <?php esc_html_e('Disable User-Agent detection?', 'wp-bittorrent');?>
                </label>
            </th>
            <td>
                <input type="checkbox" id="<?php esc_attr_e($this->prefix);?>no_ua_detect" name="<?php esc_attr_e($this->prefix);?>settings[no_ua_detect]" value="1" <?php if (isset($options['no_ua_detect'])) { checked($options['no_ua_detect'], 1); } ?> />
                <label for="<?php esc_attr_e($this->prefix);?>no_ua_detect"><span class="description"><?php
        print sprintf(
            esc_html__('Turning off User-Agent detection will mean that visitors must request BitTorrent versions of your pages explicitly. If you disable User-Agent detection, remember to %1$sprovide a link to the webseed version%2$s somewhere in your template. (This setting will not affect RSS feeds.)', 'wp-bittorrent'),
            '<a href="https://wordpress.org/plugins/bittorrent/faq/">', '</a>'
        );
                ?></span></label>
            </td>
        </tr>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>debug">
                    <?php esc_html_e('Enable detailed debugging information?', 'wp-bittorrent');?>
                </label>
            </th>
            <td>
                <input type="checkbox" id="<?php esc_attr_e($this->prefix);?>debug" name="<?php esc_attr_e($this->prefix);?>settings[debug]" value="1" <?php if (isset($options['debug'])) { checked($options['debug'], 1); } ?> />
                <label for="<?php esc_attr_e($this->prefix);?>debug"><span class="description"><?php
        print sprintf(
            esc_html__('Turn this on only if you are experiencing problems using this plugin, or if you were told to do so by someone helping you fix a problem (or if you really know what you are doing). When enabled, extremely detailed technical information is displayed as a WordPress admin notice when you take certain actions. If you have also enabled WordPress\'s built-in debugging (%1$s) and debug log (%2$s) feature, additional information will be sent to a log file (%3$s). This file may contain sensitive information, so turn this off and erase the debug log file when you have resolved the issue.', 'wp-bittorrent'),
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG"><code>WP_DEBUG</code></a>',
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG_LOG"><code>WP_DEBUG_LOG</code></a>',
            '<code>' . content_url() . '/debug.log' . '</code>'
        );
                ?></span></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<?php submit_button();?>
</form>
<?php
        $this->showDonationAppeal();
    } // end public function renderOptionsPage
}

$wp_bittorrent = new WP_BitTorrent();
require_once 'template-functions.php';
