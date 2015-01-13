<?php
/**
 * Plugin Name: BitTorrent my Blog
 * Plugin URI: https://github.com/meitar/wp-bittorrent
 * Description: Publish your blog as a BitTorrent seed. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WP-BitTorrent&amp;item_number=WP-BitTorrent&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of WP-BitTorrent">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.1
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: http://maymay.net/
 * Text Domain: wp-bittorrent
 * Domain Path: /languages
 */

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
    private $current_url;    //< The URL of the current request.
    private $cache;          //< Filename of the web seed cache file.
    private $max_cache_age;  //< Seconds until filesystem cache is considered expired.

    public function __construct () {
        require_once dirname(__FILE__) . '/lib/Torrent-RW/Torrent.php';

        $options = get_option($this->prefix . 'settings');
        $this->max_cache_age = (!isset($options['max_cache_age'])) ? $this->default_settings['max_cache_age'] : $options['max_cache_age'];

        $this->seed_cache_dir = WP_CONTENT_DIR . '/' . $this->prefix . 'seeds';

        if (!is_dir($this->seed_cache_dir) && !mkdir($this->seed_cache_dir)) {
            die(sprintf(
                esc_html__('Failed to create the torrent seed cache directory. Make sure your WordPress content directory (%s) is writeable by your webserver', 'wp-bittorrent'),
                WP_CONTENT_DIR
            ));
        }

        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));
        add_action('template_redirect', array($this, 'process'));
    }

    public function registerL10n () {
        load_plugin_textdomain('wp-bittorrent', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function process () {
        // TODO: Make this work with pretty permalinks?
        //       Perhaps see https://codex.wordpress.org/Custom_Queries
        if (!isset($_GET[$this->prefix . 'seed'])) { return; }

        global $wp;
        $this->current_url = add_query_arg($wp->query_string, '', home_url($wp->request));

        $this->cache = $this->seed_cache_dir . '/web-seed-'
            . hash('sha512', get_current_user_id() . urlencode($this->current_url))
            . '.html';
        if (is_readable($this->cache) && $this->max_cache_age > time() - filemtime($this->cache)) {
            $this->sendTorrent($this->cache);
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
     * @return string $buffer The current PHP output buffer.
     * @return string Always returns an empty string, the buffer is hijacked.
     */
    public function hijackOutput ($buffer) {
        $this->buffer = $buffer;
        // TODO: Fetch images and insert them as data URIs inline?
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
        if (false !== ($s = file_put_contents($this->cache, $this->buffer))) {
            $this->sendTorrent($this->cache);
        }
    }

    public function sendTorrent ($file) {
        $file_url = content_url() . '/' . basename($this->seed_cache_dir) . '/' . basename($file);
        $p = parse_url($this->current_url);

        $torrent = new Torrent($file);
        $torrent->announce($this->getTrackers());
        // TODO: Option to make the torrent folder structure mimic
        //       the website paths...?
        $torrent->name(basename($p['path']) . '.html');
        $torrent->httpseeds($file_url);
        $torrent->url_list(array($file_url));
        $torrent->send(); // also exits
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
                case 'max_cache_age':
                case 'use_data_uri':
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