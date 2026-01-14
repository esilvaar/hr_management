<?php

/**
 * Plugin Name: IP2Location Country Blocker
 * Plugin URI: https://ip2location.com/resources/wordpress-ip2location-country-blocker
 * Description: Block visitors from accessing your website or admin area by their country.
 * Version: 2.41.2
 * Requires PHP: 7.4
 * Author: IP2Location
 * Author URI: https://www.ip2location.com
 * Text Domain: ip2location-country-blocker.
 */
defined('FS_METHOD') || define('FS_METHOD', 'direct');
defined('IP2LOCATION_DIR') || define('IP2LOCATION_DIR', str_replace(['/', '\\'], \DIRECTORY_SEPARATOR, wp_upload_dir()['basedir']) . \DIRECTORY_SEPARATOR . 'ip2location' . \DIRECTORY_SEPARATOR);
define('IPLCB_ROOT', __DIR__ . \DIRECTORY_SEPARATOR);

// For development usage.
if (isset($_SERVER['DEV_MODE'])) {
	$_SERVER['REMOTE_ADDR'] = '3.125.220.18';
}

require_once IPLCB_ROOT . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';

// Initial IP2LocationCountryBlocker class.
$ip2location_country_blocker = new IP2LocationCountryBlocker();

register_activation_hook(__FILE__, [$ip2location_country_blocker, 'set_defaults']);

add_action('init', [$ip2location_country_blocker, 'check_block'], 1);
add_action('admin_enqueue_scripts', [$ip2location_country_blocker, 'plugin_enqueues']);
add_action('admin_init', [$ip2location_country_blocker, 'admin_init']);
add_action('admin_notices', [$ip2location_country_blocker, 'show_notice']);
add_action('wp_ajax_ip2location_country_blocker_update_ip2location_database', [$ip2location_country_blocker, 'update_ip2location_database']);
add_action('wp_ajax_ip2location_country_blocker_update_ip2proxy_database', [$ip2location_country_blocker, 'update_ip2proxy_database']);
add_action('wp_ajax_ip2location_country_blocker_validate_token', [$ip2location_country_blocker, 'validate_token']);
add_action('wp_ajax_ip2location_country_blocker_validate_api_key', [$ip2location_country_blocker, 'validate_api_key']);
add_action('wp_ajax_ip2location_country_blocker_restore', [$ip2location_country_blocker, 'restore']);
add_action('wp_footer', [$ip2location_country_blocker, 'footer']);
add_action('wp_ajax_ip2location_country_blocker_submit_feedback', [$ip2location_country_blocker, 'submit_feedback']);
add_action('admin_footer_text', [$ip2location_country_blocker, 'admin_footer_text']);
add_action('ip2location_country_blocker_hourly_event', [$ip2location_country_blocker, 'hourly_event']);

class IP2LocationCountryBlocker
{
	private $session = [
		'country'     => '??',
		'is_proxy'    => '??',
		'proxy_type'  => '??',
		'lookup_mode' => '??',
		'cache'       => false,
	];

	private $allowed_options = [
		'access_email_notification', 'api_key', 'backend_auto_block_threshold', 'backend_banlist', 'backend_block_mode', 'backend_block_proxy', 'backend_bots_list', 'backend_enabled', 'backend_error_page', 'backend_ip_blacklist', 'backend_ip_whitelist', 'backend_option', 'backend_redirect_url', 'backend_skip_bots', 'bypass_code', 'database', 'debug_log_enabled', 'detect_forwarder_ip', 'download_ipv4_only', 'email_notification', 'frontend_auto_block_threshold', 'frontend_banlist', 'frontend_block_mode', 'frontend_block_proxy', 'frontend_block_proxy_type', 'frontend_bots_list', 'frontend_enabled', 'frontend_error_page', 'frontend_ip_blacklist', 'frontend_ip_whitelist', 'frontend_option', 'frontend_redirect_url', 'frontend_skip_bots', 'frontend_whitelist_logged_user', 'log_enabled', 'lookup_mode', 'px_api_key', 'px_database', 'px_lookup_mode', 'real_ip_header', 'session_message', 'token',
	];

	private $countries = ['AF' => 'Afghanistan', 'AX' => 'Aland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia (Plurinational State of)', 'BQ' => 'Bonaire, Sint Eustatius and Saba', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic of the)', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote D\'ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CW' => 'Curacao', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard Island and Mcdonald Islands', 'VA' => 'Holy See', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran (Islamic Republic of)', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea (Democratic People\'s Republic of)', 'KR' => 'Korea (Republic of)', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'North Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia (Federated States of)', 'MD' => 'Moldova (Republic of)', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine, State of', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena, Ascension and Tristan da Cunha', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'MF' => 'Saint Martin (French Part)', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and The Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SX' => 'Sint Maarten (Dutch Part)', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and The South Sandwich Islands', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Eswatini', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan (Province of China)', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom of Great Britain and Northern Ireland', 'US' => 'United States', 'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela (Bolivarian Republic of)', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (U.S.)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'];

	private $country_groups = [
		'APAC'  => ['AS', 'AU', 'BD', 'BN', 'BT', 'CC', 'CK', 'CN', 'CX', 'FJ', 'FM', 'GN', 'GU', 'HK', 'ID', 'IN', 'JP', 'KH', 'KI', 'KP', 'KR', 'LA', 'LK', 'MH', 'MM', 'MN', 'MO', 'MP', 'MV', 'MY', 'NC', 'NF', 'NP', 'NR', 'NU', 'NZ', 'PF', 'PH', 'PK', 'PN', 'PW', 'RU', 'SB', 'SG', 'TH', 'TK', 'TL', 'TO', 'TV', 'TW', 'VN', 'VU', 'WF', 'WS'],
		'ASEAN' => ['BN', 'CN', 'ID', 'JP', 'KH', 'KR', 'LA', 'MM', 'MY', 'PH', 'SG', 'TH', 'VN'],
		'BRIC'  => ['BR', 'CN', 'IN', 'RU'],
		'BRICS' => ['BR', 'CN', 'IN', 'RU', 'ZA'],
		'EAC'   => ['BI', 'KE', 'RW', 'SD', 'TZ', 'UG'],
		'EFTA'  => ['CH', 'IS', 'LI', 'NO'],
		'EMEA'  => ['AD', 'AE', 'AL', 'AM', 'AO', 'AT', 'AX', 'AZ', 'BA', 'BE', 'BG', 'BH', 'BI', 'BJ', 'BW', 'BY', 'CF', 'CG', 'CH', 'CI', 'CM', 'CV', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DZ', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FO', 'FR', 'GA', 'GB', 'GE', 'GG', 'GH', 'GI', 'GM', 'GN', 'GR', 'HR', 'HU', 'IE', 'IL', 'IM', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JO', 'KE', 'KM', 'KW', 'KZ', 'LB', 'LI', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MG', 'MK', 'ML', 'MR', 'MT', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NL', 'NO', 'OM', 'PL', 'PT', 'QA', 'RE', 'RS', 'RU', 'RW', 'SA', 'SC', 'SD', 'SE', 'SH', 'SI', 'SK', 'SL', 'SM', 'SN', 'ST', 'SY', 'SZ', 'TD', 'TG', 'TN', 'TR', 'TZ', 'UA', 'UG', 'VA', 'YE', 'YT', 'ZA', 'ZM', 'ZW'],
		'EU'    => ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'OM', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'],
	];

	private $robots = [
		'applebot'                    => 'Apple',
		'ahrefs'                      => 'AhrefsBot',
		'baidu'                       => 'Baidu',
		'bingbot'                     => 'Bing',
		'blekkobot'                   => 'Blekko',
		'duckduckgo'                  => 'DuckDuckGo',
		'dotbot'                      => 'MozLink',
		'exalead'                     => 'ExaLead',
		'exabot'                      => 'Exabot',
		'facebookexternalhit'         => 'Facebook',
		'feedburner'                  => 'FeedBurner',
		'gigablast'                   => 'Gigablast',
		'gptbot'                      => 'GPTBot',
		'google'                      => 'Google',
		'petalbot'                    => 'Huawei',
		'archive.org_bot|ia_archiver' => 'Internet Archive',
		'linkedinbot'                 => 'LinkedIn',
		'msnbot'                      => 'MSN',
		'mj12bot'                     => 'Majestic',
		'perplexity'                  => 'Perplexity',
		'pinterest'                   => 'Pinterest',
		'semrushbot'                  => 'SemrushBot',
		'sogou'                       => 'Sogou',
		'whatsapp'                    => 'WhatsApp',
		'twitterbot'                  => 'Twitter/X',
		'slurp'                       => 'Yahoo',
		'yandex'                      => 'Yandex',
	];

	private $proxy_types = [
		'VPN', 'TOR', 'DCH', 'PUB', 'WEB', 'SES', 'RES', 'CPN', 'EPN',
	];

	private $debug_log = '';

	public function __construct()
	{
		// Set priority
		$this->set_priority();

		// Check for IP2Location BIN directory.
		if (!file_exists(IP2LOCATION_DIR)) {
			wp_mkdir_p(IP2LOCATION_DIR);
		}

		// Check for cache directory.
		if (!file_exists(IP2LOCATION_DIR . 'caches')) {
			wp_mkdir_p(IP2LOCATION_DIR . 'caches');
		}

		$this->debug_log = 'debug_' . md5($this->get_option('private_key') . get_site_url() . get_option('admin_email')) . '.log';

		add_action('admin_menu', [$this, 'add_admin_menu']);
	}

	public function admin_init()
	{
		if ($this->post('action') == 'download_ip2location_country_blocker_backup') {
			if (!current_user_can('administrator')) {
				exit;
			}

			check_admin_referer('backup', '__nonce');

			$results = $this->wpdb_get_results("SELECT option_name, option_value FROM {$GLOBALS['wpdb']->prefix}options WHERE option_name LIKE 'ip2location_country_blocker_%'");

			if ($results) {
				$options = [];

				foreach ($results as $result) {
					$options[$result->option_name] = $result->option_value;
				}

				ob_end_flush();
				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename="ip2location_country_blocker.json"');
				exit(json_encode($options));
			}
		}
	}

	public function frontend_page()
	{
		$cache_warning = '';
		if (($name = $this->cache_plugin_detected()) !== false) {
			$cache_warning = '
			<div class="error">
				<p>
					It appears that you are currently using <strong>' . $name . '</strong>, which is not fully compatible with the IP2Location Country Blocker. This may lead to unintended issues. We recommend disabling and uninstalling the cache plugin.
				</p>
			</div>';
		}

		if (!$this->is_setup_completed()) {
			return $this->settings_page();
		}

		$frontend_status = '';

		// Default values
		$enable_frontend = $this->is_checked('enable_frontend', $this->get_option('frontend_enabled'));
		$frontend_block_mode = $this->post('frontend_block_mode', $this->get_option('frontend_block_mode'));
		$frontend_ban_list = $this->post('frontend_ban_list', $this->get_option('frontend_banlist'));
		$frontend_ban_list = (!is_array($frontend_ban_list)) ? [$frontend_ban_list] : $frontend_ban_list;
		$frontend_option = $this->post('frontend_option', $this->get_option('frontend_option'));
		$frontend_error_page = $this->post('frontend_error_page', $this->get_option('frontend_error_page'));
		$frontend_auto_block_threshold = $this->post('frontend_auto_block_threshold', $this->get_option('frontend_auto_block_threshold'));
		$frontend_redirect_url = $this->post('frontend_redirect_url', $this->get_option('frontend_redirect_url'));
		$frontend_ip_blacklist = $this->post('frontend_ip_blacklist', $this->get_option('frontend_ip_blacklist'));
		$frontend_ip_whitelist = $this->post('frontend_ip_whitelist', $this->get_option('frontend_ip_whitelist'));
		$enable_frontend_logged_user_whitelist = $this->is_checked('enable_frontend_logged_user_whitelist', $this->get_option('frontend_whitelist_logged_user'));
		$frontend_skip_bots = $this->is_checked('frontend_skip_bots', $this->get_option('frontend_skip_bots'));
		$frontend_bots_list = $this->post('frontend_bots_list', $this->get_option('frontend_bots_list'));
		$frontend_bots_list = (!is_array($frontend_bots_list)) ? [$frontend_bots_list] : $frontend_bots_list;
		$frontend_block_proxy = $this->is_checked('frontend_block_proxy', $this->get_option('frontend_block_proxy'));
		$frontend_block_proxy_type = $this->post('frontend_block_proxy_type', $this->get_option('frontend_block_proxy_type'));

		// Sanitize inputs
		if (!empty($frontend_ip_whitelist)) {
			$frontend_ip_whitelist = $this->sanitize_list($frontend_ip_whitelist);
		}

		if (!empty($frontend_ip_blacklist)) {
			$frontend_ip_blacklist = $this->sanitize_list($frontend_ip_blacklist);
		}

		if ($this->post('reset')) {
			$this->wpdb_query('TRUNCATE TABLE ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_frontend_rate_limit_log');

			$frontend_status = '
				<div class="updated">
					<p>' . __('Frontend blacklist log has been reset.', 'ip2location-country-blocker') . '</p>
				</div>';
		}

		if ($this->post('submit')) {
			check_admin_referer('save_frontend');

			if (!empty($frontend_auto_block_threshold) && !preg_match('/^[0-9]+$/', $frontend_auto_block_threshold)) {
				$frontend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Auto block threshold has to be a number.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} elseif ($frontend_option == 2 && !filter_var($frontend_error_page, \FILTER_VALIDATE_URL)) {
				$frontend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Please choose a custom error page.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} elseif ($frontend_option == 3 && !filter_var($frontend_redirect_url, \FILTER_VALIDATE_URL)) {
				$frontend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Please provide a valid URL for redirection.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} else {
				// Remove country that existed in group to prevent duplicated lookup.
				$removed_list = [];
				if (($groups = $this->get_group_from_list($frontend_ban_list)) !== false) {
					foreach ($groups as $group) {
						foreach ($frontend_ban_list as $country_code) {
							if ($this->is_in_array($country_code, $this->country_groups[$group])) {
								if (($key = array_search($country_code, $frontend_ban_list)) !== false) {
									$removed_list[] = $this->get_country_name($country_code);
									unset($frontend_ban_list[$key]);
								}
							}
						}
					}
				}

				$this->update_option('frontend_enabled', $enable_frontend);
				$this->update_option('frontend_block_mode', $frontend_block_mode);
				$this->update_option('frontend_banlist', $frontend_ban_list);
				$this->update_option('frontend_option', $frontend_option);
				$this->update_option('frontend_redirect_url', $frontend_redirect_url);
				$this->update_option('frontend_error_page', $frontend_error_page);
				$this->update_option('frontend_ip_blacklist', $frontend_ip_blacklist);
				$this->update_option('frontend_auto_block_threshold', $frontend_auto_block_threshold);
				$this->update_option('frontend_ip_whitelist', $frontend_ip_whitelist);
				$this->update_option('frontend_whitelist_logged_user', $enable_frontend_logged_user_whitelist);
				$this->update_option('frontend_skip_bots', $frontend_skip_bots);
				$this->update_option('frontend_bots_list', $frontend_bots_list);
				$this->update_option('frontend_block_proxy', $frontend_block_proxy);
				$this->update_option('frontend_block_proxy_type', $frontend_block_proxy_type);

				$frontend_status = '
				<div class="updated">
					<p>' . __('Changes saved.', 'ip2location-country-blocker') . '</p>
					' . ((!empty($removed_list)) ? ('<p>' . implode(', ', $removed_list) . ' has been removed from your list as part of country group.</p>') : '') . '
				</div>';
			}
		}

		if ($this->get_option('lookup_mode') == 'bin' && !is_file(IP2LOCATION_DIR . $this->get_option('database'))) {
			$frontend_status .= '
			<div class="error">
				<p>' . sprintf(__('%1$sERROR:%2$s Unable to find the IP2Location BIN database! Please %3$sdownload the BIN database%4$s in Settings page.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<a href="#bin_download">', '</a>') . '</p>
			</div>';
		}

		echo '
		<div class="wrap">
			<h1>' . __('Frontend Settings', 'ip2location-country-blocker') . '</h1>
			' . $cache_warning . '
			' . $frontend_status . '

			<form method="post" novalidate="novalidate">
				' . wp_nonce_field('save_frontend') . '
				<div style="margin-top:20px">
					<label for="enable_frontend">
						<input type="checkbox" name="enable_frontend" id="enable_frontend"' . (($enable_frontend) ? ' checked' : '') . '>
						' . __('Enable Frontend Blocking', 'ip2location-country-blocker') . '
					</label>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
				<table class="form-table" style="margin-left:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Block By Country', 'ip2location-country-blocker') . '</h2>
					<tr>
						<th scope="row">
							<label>' . __('Block by country', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span>' . __('Blocking Mode', 'ip2location-country-blocker') . '</span></legend>
								<label><input type="radio" name="frontend_block_mode" value="1"' . (($frontend_block_mode == 1) ? ' checked' : '') . ' class="input-field" /> ' . __('Block countries listed below.', 'ip2location-country-blocker') . '</label><br />
								<label><input type="radio" name="frontend_block_mode" value="2"' . (($frontend_block_mode == 2) ? ' checked' : '') . ' class="input-field" /> ' . sprintf(__('Block all countries %1$sexcept%2$s countries listed below.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</label>
							</fieldset>
							<select name="frontend_ban_list[]" id="frontend_ban_list" data-placeholder="' . __('Choose Country...', 'ip2location-country-blocker') . '" multiple="true" class="chosen input-field">';
								foreach ($this->country_groups as $group_name => $countries) {
									echo '
									<option value="' . esc_attr($group_name) . '"' . (($this->is_in_array($group_name, $frontend_ban_list)) ? ' selected' : '') . '> ' . esc_html($group_name) . ' Countries</option>';
								}

								foreach ($this->countries as $country_code => $country_name) {
									echo '
									<option value="' . esc_attr($country_code) . '"' . (($this->is_in_array($country_code, $frontend_ban_list)) ? ' selected' : '') . '> ' . esc_html($country_name) . '</option>';
								}
								echo '
								</select>
							<p>' . sprintf(__('%1$sNote:%2$s For EU, APAC and other country groupings, please visit %3$sGeoDataSource Country Grouping Terminology%4$s for details.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<a href="https://github.com/geodatasource/country-grouping-terminology" target="_blank">', '</a>') . '</p>
						</td>
					</tr>
					</table>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Block By Proxy', 'ip2location-country-blocker') . '</h2>

					<table class="form-table" style="margin-left:20px;">
						<tr>
							<th scope="row">
								<label>' . __('Block by proxy IP', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<label for="frontend_block_proxy">
									<input type="checkbox" name="frontend_block_proxy" id="frontend_block_proxy"' . (($frontend_block_proxy) ? ' checked' : '') . ' class="input-field">
									' . __('Block proxy IP.', 'ip2location-country-blocker') . '
									<p class="description">
										' . __('IP2Proxy Lookup Mode is required for this option. You can enable/disable the IP2Proxy Lookup Mode at the Settings tab.', 'ip2location-country-blocker') . '
									</p>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Block by proxy type', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<label for="frontend_block_proxy_type">
									' . __('Block following proxy type.', 'ip2location-country-blocker') . '
								</label>
								<div style="margin-top:10px">
									<select name="frontend_block_proxy_type[]" id="frontend_block_proxy_type" data-placeholder="' . __('Choose Proxy Type...', 'ip2location-country-blocker') . '" multiple="true" class="chosen input-field">';
										foreach ($this->proxy_types as $proxy_type) {
											echo '
											<option value="' . esc_attr($proxy_type) . '"' . (($this->is_in_array($proxy_type, $frontend_block_proxy_type)) ? ' selected' : '') . '> ' . esc_html($proxy_type) . '</option>';
										}

										echo '
									</select>
									<p class="description">
										' . sprintf(__('This feature only works with %1$sIP2Proxy Commercial%2$s database.', 'ip2location-country-blocker'), '<a href="https://www.ip2location.com/database/ip2proxy#wordpress-wzdicb" target="_blank">', '</a>') . '
									</p>
								</div>
							</td>
						</tr>
					</table>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Other Settings', 'ip2location-country-blocker') . '</h2>

					<table class="form-table" style="margin-left:20px;">
					<tr>
						<th scope="row">
							<label>' . __('Block by bot', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<label for="frontend_skip_bots">
								<input type="checkbox" name="frontend_skip_bots" id="frontend_skip_bots"' . (($frontend_skip_bots) ? ' checked' : '') . ' class="input-field">
								' . __('Do not block the below bots and crawlers.', 'ip2location-country-blocker') . '
							</label>

							<div style="margin-top:10px;">
								<select name="frontend_bots_list[]" id="frontend_bots_list" data-placeholder="' . __('Choose Robot...', 'ip2location-country-blocker') . '" multiple="true" class="chosen input-field">';
									foreach ($this->robots as $robot_code => $robot_name) {
										echo '
										<option value="' . esc_attr($robot_code) . '"' . (($this->is_in_array($robot_code, $frontend_bots_list)) ? ' selected' : '') . '> ' . esc_html($robot_name) . '</option>';
									}
									echo '
								</select>
							</div>
						</td>
					</tr>


					<tr>
						<th scope="row">
							<label>' . __('Display page when visitor is blocked', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<div style="margin-bottom:10px;">
								<strong>' . __('Show the following page when visitor is blocked.', 'ip2location-country-blocker') . '</strong>
							</div>

							<fieldset>
								<legend class="screen-reader-text"><span>' . __('Error Option', 'ip2location-country-blocker') . '</span></legend>

								<label>
									<input type="radio" name="frontend_option" id="frontend_option_1" value="1"' . (($frontend_option == 1) ? ' checked' : '') . ' class="input-field">
									' . __('Default Error 403 Page', 'ip2location-country-blocker') . '
								</label>
								<br />
								<label>
									<input type="radio" name="frontend_option" id="frontend_option_2" value="2"' . (($frontend_option == 2) ? ' checked' : '') . ' class="input-field">
									' . __('Custom Error Page: ', 'ip2location-country-blocker') . '
									<select name="frontend_error_page" id="frontend_error_page" class="input-field">';
										$pages = get_pages(['post_status' => 'publish,private']);

										foreach ($pages as $page) {
											echo '
											<option value="' . esc_attr($page->guid) . '"' . (($frontend_error_page == $page->guid) ? ' selected' : '') . '>' . esc_html($page->post_title) . '</option>';
										}
										echo '
									</select>
								</label>
								<br />
								<label>
									<input type="radio" name="frontend_option" id="frontend_option_3" value="3"' . (($frontend_option == 3) ? ' checked' : '') . ' class="input-field" />
									' . __('URL: ', 'ip2location-country-blocker') . '
									<input type="text" name="frontend_redirect_url" id="frontend_redirect_url" value="' . esc_attr($frontend_redirect_url) . '" class="regular-text code input-field" />
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Blacklist IP addresses', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span>' . __('Blacklist', 'ip2location-country-blocker') . '</span></legend>
								<input type="text" name="frontend_ip_blacklist" id="frontend_ip_blacklist" value="' . esc_attr($frontend_ip_blacklist) . '" class="regular-text ip-address-list" />
								<p class="description">' . __('Use asterisk (*) for wildcard matching. E.g.: 8.8.8.* will match IP from 8.8.8.0 to 8.8.8.255. CIDR format also supported.', 'ip2location-country-blocker') . '</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Blacklist Threshold', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<input type="text" name="frontend_auto_block_threshold" id="frontend_auto_block_threshold" maxlength="20" placeholder="100" value="' . esc_attr($frontend_auto_block_threshold) . '" class="regular-text code input-field" />
							<a href="javascript:;" id="link-reset">Reset</a>
							<p class="description">' . __('Automatically add client IP into blacklist if client keep hitting front pages more than this threshold within 24 hours.', 'ip2location-country-blocker') . '</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Whitelist IP addresses', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span>Blacklist</span></legend>
								<input type="text" name="frontend_ip_whitelist" id="frontend_ip_whitelist" value="' . esc_attr($frontend_ip_whitelist) . '" class="regular-text ip-address-list" />
								<p class="description">' . __('Use asterisk (*) for wildcard matching. E.g.: 8.8.8.* will match IP from 8.8.8.0 to 8.8.8.255. CIDR format also supported.', 'ip2location-country-blocker') . '</p>
							</fieldset>
						</td>
					</tr>
				</table>
				<label for="enable_frontend_logged_user_whitelist">
					<input type="checkbox" name="enable_frontend_logged_user_whitelist" id="enable_frontend_logged_user_whitelist"' . (($enable_frontend_logged_user_whitelist) ? ' checked' : '') . ' class="input-field">
						' . __('Bypass blocking for logged in user.', 'ip2location-country-blocker') . '
				</label>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Changes', 'ip2location-country-blocker') . '" />
				</p>
			</form>

			<div class="clear"></div>
			<input type="hidden" id="support_proxy" value="' . (($this->get_option('px_lookup_mode')) ? 1 : 0) . '">
		</div>';
	}

	public function backend_page()
	{
		$cache_warning = '';
		if (($name = $this->cache_plugin_detected()) !== false) {
			$cache_warning = '
			<div class="error">
				<p>
					' . sprintf(__('This IP2Location Country Blocker plugin does not work well with the %1$s%2$s%3$s cache plugin. To avoid unexpected results, we will strongly recommend you to deactivate the cache plugin.', 'ip2location-country-blocker'), '<strong>', $name, '</strong>') . '
				</p>
			</div>';
		}

		if (!$this->is_setup_completed()) {
			return $this->settings_page();
		}

		$backend_status = '';

		$enable_backend = $this->is_checked('enable_backend', $this->get_option('backend_enabled'));
		$backend_block_mode = $this->post('backend_block_mode', $this->get_option('backend_block_mode'));
		$backend_ban_list = $this->post('backend_ban_list', $this->get_option('backend_banlist'));
		$backend_ban_list = (!is_array($backend_ban_list)) ? [$backend_ban_list] : $backend_ban_list;
		$backend_option = $this->post('backend_option', $this->get_option('backend_option'));
		$backend_error_page = $this->post('backend_error_page', $this->get_option('backend_error_page'));
		$backend_redirect_url = $this->post('backend_redirect_url', $this->get_option('backend_redirect_url'));
		$bypass_code = $this->post('bypass_code', $this->get_option('bypass_code'));
		$backend_ip_blacklist = $this->post('backend_ip_blacklist', $this->get_option('backend_ip_blacklist'));
		$backend_auto_block_threshold = $this->post('backend_auto_block_threshold', $this->get_option('backend_auto_block_threshold'));
		$backend_ip_whitelist = $this->post('backend_ip_whitelist', $this->get_option('backend_ip_whitelist'));
		$backend_skip_bots = $this->is_checked('backend_skip_bots', $this->get_option('backend_skip_bots'));
		$backend_bots_list = $this->post('backend_bots_list', $this->get_option('backend_bots_list'));
		$backend_bots_list = (!is_array($backend_bots_list)) ? [$backend_bots_list] : $backend_bots_list;
		$backend_block_proxy = $this->is_checked('backend_block_proxy', $this->get_option('backend_block_proxy'));
		$backend_block_proxy_type = $this->post('backend_block_proxy_type', $this->get_option('backend_block_proxy_type'));
		$email_notification = $this->post('email_notification', $this->get_option('email_notification'));
		$access_email_notification = $this->post('access_email_notification', $this->get_option('access_email_notification'));

		// Sanitize inputs
		if (!empty($backend_ip_whitelist)) {
			$backend_ip_whitelist = $this->sanitize_array($backend_ip_whitelist);
		}

		if (!empty($backend_ip_blacklist)) {
			$backend_ip_blacklist = $this->sanitize_array($backend_ip_blacklist);
		}

		$result = $this->get_location($this->ip());
		$my_country_code = $result['country_code'];
		$my_country_name = $result['country_name'];

		if ($this->post('reset')) {
			$this->wpdb_query('TRUNCATE TABLE ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log');

			$frontend_status = '
				<div class="updated">
					<p>' . __('Backend blacklist log has been reset.', 'ip2location-country-blocker') . '</p>
				</div>';
		}

		if ($this->post('submit')) {
			check_admin_referer('save_backend');

			if (!empty($backend_auto_block_threshold) && !preg_match('/^[0-9]+$/', $backend_auto_block_threshold)) {
				$backend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Auto block threshold has to be a number.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} elseif ($backend_option == 2 && !filter_var($backend_error_page, \FILTER_VALIDATE_URL)) {
				$backend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Please choose a custom error page.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} elseif ($backend_option == 3 && !filter_var($backend_redirect_url, \FILTER_VALIDATE_URL)) {
				$backend_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Please provide a valid URL for redirection.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} else {
				// Remove country that existed in group to prevent duplicated lookup.
				$removed_list = [];
				if (($groups = $this->get_group_from_list($backend_ban_list)) !== false) {
					foreach ($groups as $group) {
						foreach ($backend_ban_list as $country_code) {
							if ($this->is_in_array($country_code, $this->country_groups[$group])) {
								if (($key = array_search($country_code, $backend_ban_list)) !== false) {
									$removed_list[] = $this->get_country_name($country_code);
									unset($backend_ban_list[$key]);
								}
							}
						}
					}
				}

				$this->update_option('backend_enabled', $enable_backend);
				$this->update_option('backend_block_mode', $backend_block_mode);
				$this->update_option('backend_banlist', $backend_ban_list);
				$this->update_option('backend_option', $backend_option);
				$this->update_option('backend_redirect_url', $backend_redirect_url);
				$this->update_option('backend_error_page', $backend_error_page);
				$this->update_option('bypass_code', $bypass_code);
				$this->update_option('backend_ip_blacklist', $backend_ip_blacklist);
				$this->update_option('backend_auto_block_threshold', $backend_auto_block_threshold);
				$this->update_option('backend_ip_whitelist', $backend_ip_whitelist);
				$this->update_option('backend_skip_bots', $backend_skip_bots);
				$this->update_option('backend_bots_list', $backend_bots_list);
				$this->update_option('backend_block_proxy', $backend_block_proxy);
				$this->update_option('backend_block_proxy_type', $backend_block_proxy_type);
				$this->update_option('access_email_notification', $access_email_notification);
				$this->update_option('email_notification', $email_notification);

				if ($backend_auto_block_threshold) {
					$this->create_table();
				}

				$backend_status = '
				<div class="updated">
					<p>' . __('Changes saved.', 'ip2location-country-blocker') . '</p>
					' . ((!empty($removed_list)) ? ('<p>' . implode(', ', $removed_list) . ' has been removed from your list as part of country group.</p>') : '') . '
				</div>';
			}
		}

		if ($this->get_option('lookup_mode') == 'bin' && !is_file(IP2LOCATION_DIR . $this->get_option('database'))) {
			$backend_status .= '
			<div class="error">
				<p>' . sprintf(__('%1$sERROR:%2$s Unable to find the IP2Location BIN database! Please download the database at at %3$sIP2Location commercial database%4$s | %5$sIP2Location LITE database (free edition)%6$s.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<a href="https://www.ip2location.com/?r=wordpress" target="_blank">', '</a>', '<a href="https://lite.ip2location.com/?r=wordpress" target="_blank">', '</a>') . '</p>
			</div>';
		}

		echo '
		<div class="wrap">
			<h1>' . __('Backend Settings', 'ip2location-country-blocker') . '</h1>
			' . $cache_warning . '
			' . $backend_status . '

			<form id="form_backend_settings" method="post" novalidate="novalidate">
				' . wp_nonce_field('save_backend') . '
				<input type="hidden" name="my_country_code" id="my_country_code" value="' . esc_attr($my_country_code) . '" />
				<input type="hidden" name="my_country_name" id="my_country_name" value="' . esc_attr($my_country_name) . '" />
				<div style="margin-top:20px;">
					<label for="enable_backend">
						<input type="checkbox" name="enable_backend" id="enable_backend"' . (($enable_backend) ? ' checked' : '') . '>
						' . __('Enable Backend Blocking', 'ip2location-country-blocker') . '
					</label>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Block By Country', 'ip2location-country-blocker') . '</h2>

					<table class="form-table" style="margin-left:20px;">
						<tr>
							<th scope="row">
								<label>' . __('Block by country', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span>Blocking Mode</span></legend>
									<label><input type="radio" name="backend_block_mode" value="1"' . (($backend_block_mode == 1) ? ' checked' : '') . ' class="input-field" /> ' . __('Block countries listed below.', 'ip2location-country-blocker') . '</label><br />
									<label><input type="radio" name="backend_block_mode" value="2"' . (($backend_block_mode == 2) ? ' checked' : '') . ' class="input-field" /> ' . sprintf(__('Block all countries %1$sexcept%2$s countries listed below.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</label>
								</fieldset>

								<select name="backend_ban_list[]" id="backend_ban_list" data-placeholder="Choose Country..." multiple="true" class="chosen input-field">';
									foreach ($this->country_groups as $group_name => $countries) {
										echo '
										<option value="' . esc_attr($group_name) . '"' . (($this->is_in_array($group_name, $backend_ban_list)) ? ' selected' : '') . '> ' . esc_html($group_name) . ' Countries</option>';
									}

									foreach ($this->countries as $country_code => $country_name) {
										echo '
										<option value="' . esc_attr($country_code) . '"' . (($this->is_in_array($country_code, $backend_ban_list)) ? ' selected' : '') . '> ' . esc_html($country_name) . '</option>';
									}

									echo '
								</select>

								<p>' . sprintf(__('%1$sNote:%2$s For EU, APAC and other country groupings, please visit %3$sGeoDataSource Country Grouping Terminology%4$s for details.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<a href="https://github.com/geodatasource/country-grouping-terminology" target="_blank">', '</a>') . '</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Block By Proxy', 'ip2location-country-blocker') . '</h2>

					<table class="form-table" style="margin-left:20px;">
						<tr>
							<th scope="row">
								<label>' . __('Block by proxy IP', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<label for="backend_block_proxy">
									<input type="checkbox" name="backend_block_proxy" id="backend_block_proxy"' . (($backend_block_proxy) ? ' checked' : '') . ' class="input-field">
									' . __('Block proxy IP.', 'ip2location-country-blocker') . '
								</label>
								<p class="description">
									' . __('IP2Proxy Lookup Mode is required for this option. You can enable/disable the IP2Proxy Lookup Mode at the Settings tab.', 'ip2location-country-blocker') . '
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Block by proxy type', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<label for="backend_block_proxy_type">
									' . __('Block following proxy type.', 'ip2location-country-blocker') . '
								</label>
								<div style="margin-top:10px">
									<select name="backend_block_proxy_type[]" id="backend_block_proxy_type" data-placeholder="Choose Proxy Type..." multiple="true" class="chosen input-field">';
										foreach ($this->proxy_types as $proxy_type) {
											echo '
											<option value="' . esc_attr($proxy_type) . '"' . (($this->is_in_array($proxy_type, $backend_block_proxy_type)) ? ' selected' : '') . '> ' . esc_html($proxy_type) . '</option>';
										}

										echo '
									</select>

									<p class="description">
										' . sprintf(__('This feature only works with %1$sIP2Proxy Commercial%2$s database.', 'ip2location-country-blocker'), '<a href="https://www.ip2location.com/database/ip2proxy#wordpress-wzdicb" target="_blank">', '</a>') . '
									</p>
								</div>
							</td>
						</tr>
					</table>
				</div>

				<div class="postbox" style="margin-top:20px;padding-left:15px;padding-right:15px;padding-bottom:20px;">
					<h2 class="title" style="padding-bottom:5px">' . __('Other Settings', 'ip2location-country-blocker') . '</h2>

					<table class="form-table" style="margin-left:20px;">
						<tr>
							<th scope="row">
								<label>' . __('Block by bot', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<label for="backend_skip_bots">
									<input type="checkbox" name="backend_skip_bots" id="backend_skip_bots"' . (($backend_skip_bots) ? ' checked' : '') . ' class="input-field">
									' . __('Do not block the below bots and crawlers.', 'ip2location-country-blocker') . '
								</label>
								<div style="margin-top:10px">
									<select name="backend_bots_list[]" id="backend_bots_list" data-placeholder="Choose Robot..." multiple="true" class="chosen input-field">';
										foreach ($this->robots as $robot_code => $robot_name) {
											echo '
											<option value="' . esc_attr($robot_code) . '"' . (($this->is_in_array($robot_code, $backend_bots_list)) ? ' selected' : '') . '> ' . esc_html($robot_name) . '</option>';
										}

										echo '
									</select>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label>' . __('Display page when visitor is blocked', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<p>
									<strong>' . __('Show the following page when a visitor is blocked.', 'ip2location-country-blocker') . '</strong>
								</p>

								<fieldset>
									<legend class="screen-reader-text"><span>' . __('Error Option', 'ip2location-country-blocker') . '</span></legend>

									<label>
										<input type="radio" name="backend_option" id="backend_option_1" value="1"' . (($backend_option == 1) ? ' checked' : '') . ' class="input-field">
										' . __('Default Error 403 Page', 'ip2location-country-blocker') . '
									</label>
									<br />
									<label>
										<input type="radio" name="backend_option" id="backend_option_2" value="2"' . (($backend_option == 2) ? ' checked' : '') . ' class="input-field">
										' . __('Custom Error Page: ', 'ip2location-country-blocker') . '
										<select name="backend_error_page" id="backend_error_page" class="input-field">';
											$pages = get_pages(['post_status' => 'publish,private']);

											foreach ($pages as $page) {
												echo '
												<option value="' . esc_attr($page->guid) . '"' . (($backend_error_page == $page->guid) ? ' selected' : '') . '>' . esc_html($page->post_title) . '</option>';
											}

											echo '
										</select>
									</label>
									<br />
									<label>
										<input type="radio" name="backend_option" id="backend_option_3" value="3"' . (($backend_option == 3) ? ' checked' : '') . ' class="input-field">
										' . __('URL: ', 'ip2location-country-blocker') . '
										<input type="text" name="backend_redirect_url" id="backend_redirect_url" value="' . esc_attr($backend_redirect_url) . '" class="regular-text code input-field" />
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Secret code to bypass blocking (Max 20 characters)', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<input type="text" name="bypass_code" id="bypass_code" maxlength="20" value="' . esc_attr($bypass_code) . '" class="regular-text code input-field" />
								<p class="description">
									' . sprintf(__('This is the secret code used to bypass all blockings to backend pages. It take precedence over all block settings configured. To bypass, you just need to append the %1$ssecret_code%2$s parameter with above value to the wp-login.php page. For example, https://www.example.com/wp-login.php%3$s?secret_code=1234567%4$s. If you add in %5s&action=emergency_stop%6s, both frontend and backend blocking will be disabled immediately.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<code>', '</code>', '<code>', '</code>') . '
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Blacklist IP addresses', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span>' . __('Blacklist', 'ip2location-country-blocker') . '</span></legend>
									<input type="text" name="backend_ip_blacklist" id="backend_ip_blacklist" value="' . esc_attr($backend_ip_blacklist) . '" class="regular-text ip-address-list" />
									<p class="description">' . __('Use asterisk (*) for wildcard matching. E.g.: 8.8.8.* will match IP from 8.8.8.0 to 8.8.8.255. CIDR format also supported.', 'ip2location-country-blocker') . '</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Automatic Blacklist', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<input type="text" name="backend_auto_block_threshold" id="backend_auto_block_threshold" maxlength="20" placeholder="100" value="' . esc_attr($backend_auto_block_threshold) . '" class="regular-text code input-field" />
								<a href="javascript:;" id="link-reset">Reset</a>
								<p class="description">' . __('Automatically add client IP into blacklist if client keep hitting admin area more than this threshold within 24 hours.', 'ip2location-country-blocker') . '</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>' . __('Whitelist IP addresses', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><span>' . __('Blacklist', 'ip2location-country-blocker') . '</span></legend>
									<input type="text" name="backend_ip_whitelist" id="backend_ip_whitelist" value="' . esc_attr($backend_ip_whitelist) . '" class="regular-text ip-address-list" />
									<p class="description">' . __('Use asterisk (*) for wildcard matching. E.g.: 8.8.8.* will match IP from 8.8.8.0 to 8.8.8.255. CIDR format also supported.', 'ip2location-country-blocker') . '</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="email_notification">' . __('Email Notification', 'ip2location-country-blocker') . '</label>
							</th>
							<td>
								<select name="email_notification">
									<option value="none"> ' . __('None', 'ip2location-country-blocker') . '</option>';
										$users = get_users(['role' => 'administrator']);

										foreach ($users as $user) {
											echo '
											<option value="' . esc_attr($user->user_email) . '"' . (($user->user_email == $email_notification) ? ' selected' : '') . '>' . esc_html($user->display_name) . '</option>';
										}

										echo '
								</select>

								<p class="description">
									' . __('Send email notification to selected recipient when a visitor is blocked.', 'ip2location-country-blocker') . '
								</p>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Changes', 'ip2location-country-blocker') . '" />
				</p>
			</form>
			<div class="clear"></div>
			<input type="hidden" id="support_proxy" value="' . (($this->get_option('px_lookup_mode')) ? 1 : 0) . '">
		</div>';
	}

	public function statistics_page()
	{
		if (!$this->is_setup_completed()) {
			return $this->settings_page();
		}

		if ($this->post('purge')) {
			check_admin_referer('purge_logs');

			$this->wpdb_query('TRUNCATE TABLE ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log');
		}

		// Remove logs older than 30 days.
		$this->wpdb_query('DELETE FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log WHERE date_created <= %s',
			[date('Y-m-d H:i:s', strtotime('-30 days'))]
		);

		$lines = [];
		for ($d = 30; $d > 0; --$d) {
			$lines[date('Y-m-d', strtotime('-' . $d . ' days'))][1] = 0;
			$lines[date('Y-m-d', strtotime('-' . $d . ' days'))][2] = 0;
		}

		// Prepare logs for last 30 days.
		$results = $this->wpdb_get_results('SELECT DATE_FORMAT(date_created, "%Y-%m-%d") AS date, side, COUNT(*) AS total FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log GROUP BY date, side ORDER BY date');

		if (!empty($results)) {
			foreach ($results as $result) {
				$lines[$result->date][$result->side] = $result->total;
			}
		}

		ksort($lines);

		$labels = [];
		$frontend_access = [];
		$backend_access = [];

		foreach ($lines as $date => $value) {
			$labels[] = $date;
			$frontend_access[] = $value[1] ?? 0;
			$backend_access[] = $value[2] ?? 0;
		}

		$frontends = ['countries' => [], 'colors' => [], 'totals' => []];
		$backends = ['countries' => [], 'colors' => [], 'totals' => []];

		// Prepare blocked countries.
		$results = $this->wpdb_get_results('SELECT side, country_code, COUNT(*) AS total FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log GROUP BY country_code, side ORDER BY total DESC');

		foreach ($results as $result) {
			if ($result->side == 1) {
				$frontends['countries'][] = $this->get_country_name($result->country_code);
				$frontends['colors'][] = 'get_color()';
				$frontends['totals'][] = $result->total;
			} else {
				$backends['countries'][] = $this->get_country_name($result->country_code);
				$backends['colors'][] = 'get_color()';
				$backends['totals'][] = $result->total;
			}
		}

		echo '
		<div class="wrap">
			<h1>' . __('Statistics (Past 30 Days)', 'ip2location-country-blocker') . '</h1>

			' . (($this->get_option('log_enabled')) ? '' : '<div class="update-message notice inline notice-warning notice-alt">' . sprintf(__('Visitor log is disabled. Please enable it in %1$sSettings%2$s page to collect statistics data.', 'ip2location-country-blocker'), '<a href="admin.php?page=ip2location-country-blocker-settings">', '</a>') . '</div>') . '

			<p>
				<canvas id="line_chart" style="width:100%;height:400px"></canvas>
			</p>

			<p>
				<div style="float:left;width:400px;margin-right:150px">
					<h3>' . __('Frontend', 'ip2location-country-blocker') . '</h3>';

					if (empty($frontends['countries'])) {
						echo '
						<div style="border:1px solid #E1E1E1;padding:10px;background-color:#fff">' . __('No data available.', 'ip2location-country-blocker') . '</div>';
					} else {
						echo '
						<div style="width:350px">
							<canvas id="pie_chart_frontend"></canvas>
						</div>

						<h4>' . __('Top 10 IP Address Blocked', 'ip2location-country-blocker') . '</h4>

						<table class="wp-list-table widefat striped">
							<thead>
								<tr>
									<th>' . __('IP Address', 'ip2location-country-blocker') . '</th>
									<th><div align="center">' . __('Country Code', 'ip2location-country-blocker') . '</div></th>
									<th><div align="right">' . __('Total', 'ip2location-country-blocker') . '</div></th>
								</tr>
							</thead>
							<tbody>';
								$results = $this->wpdb_get_results('SELECT ip_address, country_code, COUNT(*) AS total FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log WHERE side = "1" GROUP BY ip_address ORDER BY total DESC LIMIT 10');

								if (!empty($results)) {
									foreach ($results as $result) {
										echo '
										<tr>
											<td>' . esc_html($result->ip_address) . '</td>
											<td align="center">' . esc_html($result->country_code) . '</td>
											<td align="right">' . esc_html($result->total) . '</td>
										</tr>';
									}
								}

								echo '
							</tbody>
						</table>';
		}

		echo '
				</div>

				<div style="float:left;width:400px">
					<h3>' . __('Backend', 'ip2location-country-blocker') . '</h3>';

					if (empty($backends['countries'])) {
						echo '
						<div style="border:1px solid #E1E1E1;padding:10px;background-color:#fff">' . __('No data available.', 'ip2location-country-blocker') . '</div>';
					} else {
						echo '
						<div style="width:350px">
							<canvas id="pie_chart_backend"></canvas>
						</div>

						<h4>Top 10 IP Address Blocked</h4>

							<table class="wp-list-table widefat striped">
								<thead>
									<tr>
										<th>' . __('IP Address', 'ip2location-country-blocker') . '</th>
										<th><div align="center">' . __('Country Code', 'ip2location-country-blocker') . '</div></th>
										<th><div align="right">' . __('Total', 'ip2location-country-blocker') . '</div></th>
									</tr>
								</thead>
								<tbody>';

								$results = $this->wpdb_get_results('SELECT ip_address, country_code, COUNT(*) AS total FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log WHERE side = "2" GROUP BY ip_address ORDER BY total DESC LIMIT 10');

								foreach ($results as $result) {
									echo '
									<tr>
										<td>' . esc_html($result->ip_address) . '</td>
										<td align="center">' . esc_html($result->country_code) . '</td>
										<td align="right">' . esc_html($result->total) . '</td>
									</tr>';
								}

								echo '
								</tbody>
							</table>';
		}

		echo '
				</div>
			</p>

			<div class="clear"></div>

			<p>
				<form id="form-purge" method="post">
					' . wp_nonce_field('purge_logs') . '
					<input type="hidden" name="purge" value="true">
					<input type="submit" name="submit" id="btn-purge" class="button button-primary" value="' . __('Purge All Logs', 'ip2location-country-blocker') . '" />
				</form>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($){
			function get_color(){
				var r = Math.floor(Math.random() * 200);
				var g = Math.floor(Math.random() * 200);
				var b = Math.floor(Math.random() * 200);

				return \'rgb(\' + r + \', \' + g + \', \' + b + \', 0.4)\';
			}

			var ctx = document.getElementById(\'line_chart\').getContext(\'2d\');
			var line = new Chart(ctx, {
				type: \'line\',
				data: {
					labels: ' . wp_json_encode($labels) . ',
					datasets: [{
						label: \'Frontend\',
						data: ' . wp_json_encode($frontend_access) . ',
						backgroundColor: get_color()
					}, {
						label: \'Backend\',
						data: ' . wp_json_encode($backend_access) . ',
						backgroundColor: get_color()
					}]
				},
				options: {
					title: {
						display: true,
						text: \'Access Blocked\'
					},
					scales: {
						y: {
							suggestedMin: 0,
							suggestedMax: 10
						}
					}
				}
			});';

		if (!empty($frontends['countries'])) {
			echo '
				var ctx = document.getElementById(\'pie_chart_frontend\').getContext(\'2d\');
				var pie = new Chart(ctx, {
					type: \'pie\',
					data: {
						labels: ' . wp_json_encode($frontends['countries']) . ',
						datasets: [{
							backgroundColor: [' . implode(',', $frontends['colors']) . '],
							data: [' . implode(',', $frontends['totals']) . '],
						}]
					},
					options: {
						title: {
							display: true,
							text: \'Access Blocked By Country\'
						}
					}
				});';
		}

		if (!empty($backends['countries'])) {
			echo '
				var ctx = document.getElementById(\'pie_chart_backend\').getContext(\'2d\');
				var pie = new Chart(ctx, {
					type: \'pie\',
					data: {
						labels: ' . wp_json_encode($backends['countries']) . ',
						datasets: [{
							backgroundColor: [' . implode(',', $backends['colors']) . '],
							data: [' . implode(',', $backends['totals']) . '],
						}]
					},
					options: {
						title: {
							display: true,
							text: \'Access Blocked By Country\'
						}
					}
				});';
		}

		echo '
		});
		</script>';
	}

	public function ip_lookup_page()
	{
		if (!$this->is_setup_completed()) {
			return $this->settings_page();
		}

		$ip_lookup_status = '';
		$ip_address = $this->post('ip_address', $this->ip());

		if ($this->post('submit')) {
			check_admin_referer('ip_lookup');

			$this->cache_flush();

			if (!filter_var($ip_address, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
				$ip_lookup_status = '
				<div class="error">
					<p>' . sprintf(__('%1$sERROR:%2$s Please enter an IP address.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
				</div>';
			} else {
				$result = $this->get_location($ip_address);

				if (empty($result['country_code'])) {
					$ip_lookup_status = '
					<div class="error">
						<p>' . sprintf(__('%1$sERROR:%2$s Unable to lookup IP address %3$s%4$s%5$s.', 'ip2location-country-blocker'), '<strong>', '</strong>', '<strong>', esc_html($ip_address), '</strong>') . '</p>
					</div>';
				} else {
					$ip_lookup_status = '
					<div class="updated">
						<p>' . sprintf(__('IP address %1$s%2$s%3$s belongs to %4$s%5$s (%6$s)%7$s.', 'ip2location-country-blocker'), '<code>', esc_html($ip_address), '</code>', '<strong>', esc_html($result['country_name']), esc_html($result['country_code']), '<strong>') . '</p>
					</div>';

					if (!empty($result['is_proxy'])) {
						$ip_lookup_status .= '
						<div class="updated">
							<p>Proxy: ' . ucwords(strtolower($result['is_proxy'])) . '</p>
						</div>';
					}
				}
			}
		}

		echo '
		<div class="wrap">
			<h1>' . __('IP Lookup', 'ip2location-country-blocker') . '</h1>

			' . $ip_lookup_status . '

			<form method="post" novalidate="novalidate">
				' . wp_nonce_field('ip_lookup') . '
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ip_address">' . __('IP Address', 'ip2location-country-blocker') . '</label></th>
						<td>
							<input name="ip_address" type="text" id="ip_address" value="' . esc_attr($ip_address) . '" class="regular-text" />
							<p class="description">' . __('Enter a valid IP address to lookup for country information.', 'ip2location-country-blocker') . '</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Lookup', 'ip2location-country-blocker') . '" />
				</p>
			</form>

			<div class="clear"></div>
		</div>';
	}

	public function settings_page()
	{
		$disabled = (!$this->is_setup_completed());

		$settings_status = '';

		$real_ip_headers = [
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_INCAP_CLIENT_IP',
			'HTTP_X_FORWARDED',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_X_SUCURI_CLIENTIP',
		];

		$lookup_mode = $this->post('lookup_mode', $this->get_option('lookup_mode'));
		$px_lookup_mode = $this->post('px_lookup_mode', $this->get_option('px_lookup_mode'));
		$api_key = $this->post('api_key', $this->get_option('api_key'));
		$px_api_key = $this->post('px_api_key', $this->get_option('px_api_key'));
		$download_token = $this->post('token', $this->get_option('token'));
		$download_ipv4_only = $this->is_checked('download_ipv4_only', $this->get_option('download_ipv4_only'));
		$detect_forwarder_ip = $this->is_checked('detect_forwarder_ip', $this->get_option('detect_forwarder_ip'));
		$enable_log = $this->is_checked('enable_log', $this->get_option('log_enabled'));
		$enable_debug_log = $this->is_checked('enable_debug_log', $this->get_option('debug_log_enabled'));
		$real_ip_header = $this->post('real_ip_header', $this->get_option('real_ip_header'));

		if (!in_array($real_ip_header, array_values($real_ip_headers))) {
			$real_ip_header = '';
		}

		if ($this->post('cache_nonce')) {
			check_admin_referer('cache', 'cache_nonce');

			$this->cache_flush();

			$settings_status = '
			<div class="updated">
				<p>' . __('All cache has been flushed.', 'ip2location-country-blocker') . '</p>
			</div>';
		}

		if ($this->post('lookup_mode')) {
			check_admin_referer('settings');

			if ($lookup_mode == 'ws') {
				if (empty($api_key)) {
					$settings_status = '
					<div class="error">
						<p>' . sprintf(__('%1$sERROR:%2$s Invalid IP2Location API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
					</div>';
				} else {
					$response = wp_remote_get('https://api.ip2location.io/?' . http_build_query([
						'key' => $api_key,
						'src' => 'wordpress-wzdicb',
					]), ['timeout' => 3]);

					$json = json_decode($response['body']);

					if (isset($json->error)) {
						$response = wp_remote_get('https://api.ip2location.com/v2/?' . http_build_query([
							'key'   => $api_key,
							'check' => 1,
						]), ['timeout' => 3]);

						$json = json_decode($response['body']);

						if (empty($json)) {
							$settings_status = '
							<div class="error">
								<p>' . sprintf(__('%1$sERROR:%2$s Error when accessing IP2Location web service gateway.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
							</div>';
						} else {
							if (!preg_match('/^[0-9]+$/', $json->response)) {
								$settings_status = '
								<div class="error">
									<p>' . sprintf(__('%1$sERROR:%2$s Invalid IP2Location API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
								</div>';
							} else {
								$this->update_option('api_key', $api_key);
							}
						}
					} else {
						if (!preg_match('/^[0-9]+$/', $json->response)) {
							$settings_status = '
							<div class="error">
								<p>' . sprintf(__('%1$sERROR:%2$s Invalid IP2Location API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
							</div>';
						} else {
							$this->update_option('api_key', $api_key);
						}
					}
				}
			}

			if ($px_lookup_mode == 'px_ws') {
				if (empty($px_api_key)) {
					$settings_status .= '
					<div class="error">
						' . sprintf(__('%1$sERROR:%2$s Invalid IP2Proxy API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
					</div>';
				} else {
					$response = wp_remote_get('https://api.ip2location.io/?' . http_build_query([
						'key' => $px_api_key,
						'src' => 'wordpress-wzdicb',
					]), ['timeout' => 3]);

					$json = json_decode($response['body']);

					if (isset($json->error)) {
						$response = wp_remote_get('https://api.ip2proxy.com/?' . http_build_query([
							'key'   => $px_api_key,
							'check' => 1,
						]), ['timeout' => 3]);

						$json = json_decode($response['body']);

						if (empty($json)) {
							$settings_status = '
							<div class="error">
								<p>' . sprintf(__('%1$sERROR:%2$s Error when accessing IP2Proxy web service gateway.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
							</div>';
						} else {
							if (!preg_match('/^[0-9]+$/', $json->response)) {
								$settings_status .= '
								<div class="error">
									<p>' . sprintf(__('%1$sERROR:%2$s Invalid IP2Proxy API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
								</div>';
							} else {
								$this->update_option('px_api_key', $px_api_key);
							}
						}
					} else {
						if (!preg_match('/^[0-9]+$/', $json->response)) {
							$settings_status .= '
							<div class="error">
								<p>' . sprintf(__('%1$sERROR:%2$s Invalid IP2Proxy API key.', 'ip2location-country-blocker'), '<strong>', '</strong>') . '</p>
							</div>';
						} else {
							$this->update_option('px_api_key', $px_api_key);
						}
					}
				}
			}

			if (empty($settings_status)) {
				if ($enable_log) {
					$this->create_table();
				}

				if (!$enable_debug_log) {
					if (file_exists(IPLCB_ROOT . $this->debug_log)) {
						wp_delete_file(IPLCB_ROOT . $this->debug_log);
					}
				} else {
					if (!$this->get_option('private_key')) {
						add_option('ip2location_country_blocker_private_key', hash('sha256', microtime(true) . get_site_url() . get_option('admin_email')));
					}
				}

				$this->update_option('lookup_mode', $lookup_mode);
				$this->update_option('px_lookup_mode', $px_lookup_mode);
				$this->update_option('token', $download_token);
				$this->update_option('detect_forwarder_ip', $detect_forwarder_ip);
				$this->update_option('log_enabled', $enable_log);
				$this->update_option('debug_log_enabled', $enable_debug_log);
				$this->update_option('real_ip_header', $real_ip_header);

				$settings_status = '
				<div class="updated">
					<p>' . __('Changes saved.', 'ip2location-country-blocker') . '</p>
				</div>';
			}
		}

		$date = $this->get_database_date();
		$px_date = $this->get_px_database_date();

		echo '
		<div class="wrap">
			<h1>' . __('Settings', 'ip2location-country-blocker') . '</h1>

			' . $settings_status;

		if ($this->get_option('session_message')) {
			echo '
			<div class="updated">
				<p>' . $this->get_option('session_message') . '</p>
			</div>';

			$this->update_option('session_message', '');
		}

		echo '
			<form action="' . get_admin_url() . 'admin.php?page=ip2location-country-blocker-settings" method="post" novalidate="novalidate">
				' . wp_nonce_field('settings') . '
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="lookup_mode">' . __('IP2Location Lookup Mode', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<select name="lookup_mode" id="lookup_mode"' . (($disabled) ? ' disabled' : '') . '>
								<option value="bin"' . (($lookup_mode == 'bin') ? ' selected' : '') . '> ' . __('Local BIN Database', 'ip2location-country-blocker') . '</option>
								<option value="ws"' . (($lookup_mode == 'ws') ? ' selected' : '') . '> ' . __('API Web Service', 'ip2location-country-blocker') . '</option>
							</select>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<div id="bin_database"' . (($lookup_mode == 'ws') ? ' style="display:none"' : '') . '>
								<div class="iplcb-panel">
									<table class="form-table">
										<tr>
											<th scope="row">
												<label for="download_token">' . __('Download Token', 'ip2location-country-blocker') . '</label>
											</th>
											<td>
												<input type="text" name="download_token" id="download_token" value="' . esc_attr($download_token) . '" class="regular-text code input-field"' . (($disabled) ? ' disabled' : '') . ' />
												<p class="description">
													' . __('Enter your IP2Location download token.', 'ip2location-country-blocker') . '
												</p>
											</td>
										</tr>
										<tr>
											<td></td>
											<td>
												<label for="download_ipv4_only">
													<input type="checkbox" name="download_ipv4_only" id="download_ipv4_only" value="true"' . (($download_ipv4_only) ? ' checked' : '') . (($disabled) ? ' disabled' : '') . '> ' . __('Download IPv4 database only', 'ip2location-country-blocker') . '
												</label>

												<p class="description">
													' . __('Download a smaller database which is faster in lookup speed. Perfect for website with only IPv4 supported.', 'ip2location-country-blocker') . '
												</p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>' . __('Database File', 'ip2location-country-blocker') . '</label>
											</th>
											<td>
												<div>' . ((!is_file(IP2LOCATION_DIR . $this->get_option('database'))) ? '<span class="dashicons dashicons-warning" title="Database file not found."></span>' : '') . esc_html($this->get_option('database')) . '
												' . ((preg_match('/LITE/', $this->get_option('database'))) ? '<p class="description">' . sprintf(__('If you are looking for high accuracy result, you should consider using the commercial version of %1$sIP2Location BIN database%2$s.', 'ip2location-country-blocker'), '<a href="https://www.ip2location.com/database/db1-ip-country#wordpress-wzdicb" target="_blank">', '</a>') . '</p>' : '') . '
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>' . __('Database Path', 'ip2location-country-blocker') . '</label>
											</th>
											<td>
												<div>' . IP2LOCATION_DIR . '</div>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>' . __('Database Date', 'ip2location-country-blocker') . '</label>
											</th>
											<td>
												' . esc_html(($date) ?: '-') . '
											</td>
										</tr>
										<tr>
											<td></td>
											<td id="update_status"><td>
										</tr>
										<tr>
											<td></td>
											<td><button id="update_ip2location_database" type="button" class="button button-secondary"' . (($disabled) ? ' disabled' : '') . '>' . __('Update Database', 'ip2location-country-blocker') . '</button></td>
										</tr>
									</table>
								</div>
							</div>
							<div id="api_web_service"' . (($lookup_mode == 'bin') ? ' style="display:none"' : '') . '>
								<div class="iplcb-panel">
									<table class="form-table">';

		$legacyApiIpl = false;

		if (!empty($api_key) && preg_match('/^[0-9A-Z]{10}$/', $api_key)) {
			$response = wp_remote_get('https://api.ip2location.com/v2/?' . http_build_query([
				'key'   => $api_key,
				'check' => 1,
			]), ['timeout' => 3]);

			$json = json_decode($response['body']);

			if (!empty($json)) {
				$legacyApiIpl = true;
			}
		}

		echo '
									<tr>
										<th scope="row">
											<label for="api_key">API Key</label>
										</th>
										<td>
											<input name="api_key" type="text" id="api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';

		if ($legacyApiIpl) {
			echo '
											<strong><em>(Legacy API)</em></strong>';
		}

		echo '
											<p class="description">Your IP2Location <a href="https://www.ip2location.io/pricing" target="_blank">Geolocation</a> API key.</p>
										</td>
									</tr>';

		if ($legacyApiIpl) {
			if (!empty($json)) {
				if (preg_match('/^[0-9]+$/', $json->response)) {
					echo '
									<tr>
										<th scope="row">
											<label for="available_credit">Available Credit</label>
										</th>
										<td>
											' . number_format($json->response, 0, '', ',') . '
										</td>
									</tr>';
				}
			}
		}

		echo '
									</table>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="px_lookup_mode">' . __('IP2Proxy Lookup Mode', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<select name="px_lookup_mode" id="px_lookup_mode"' . (($disabled) ? ' disabled' : '') . '>
								<option value=""' . (($px_lookup_mode == '') ? ' selected' : '') . '> ' . __('Disabled', 'ip2location-country-blocker') . '</option>
								<option value="px_bin"' . (($px_lookup_mode == 'px_bin') ? ' selected' : '') . '> ' . __('Local BIN Database', 'ip2location-country-blocker') . '</option>
								<option value="px_ws"' . (($px_lookup_mode == 'px_ws') ? ' selected' : '') . '> ' . __('API Web Service', 'ip2location-country-blocker') . '</option>
							<select>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<div id="px_bin_database"' . (($px_lookup_mode == 'px_ws' || $px_lookup_mode == '') ? ' style="display:none"' : '') . '>
								<div class="iplcb-panel">
									<table class="form-table">
										<tr>
											<th scope="row">
												<label for="download_token">' . __('Download Token', 'ip2location-country-blocker') . '</label>
											</th>
											<td>
												<input type="text" name="px_download_token" id="px_download_token" value="' . esc_attr($download_token) . '" class="regular-text code input-field"' . (($disabled) ? ' disabled' : '') . ' />
												<p class="description">
													' . __('Enter your IP2Location download token.', 'ip2location-country-blocker') . '
												</p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>Database File</label>
											</th>
											<td>
												<div>' . ((!is_file(IP2LOCATION_DIR . $this->get_option('px_database'))) ? '<span class="dashicons dashicons-warning" title="Database file not found."></span>' : '') . esc_html($this->get_option('px_database')) . '
												' . ((preg_match('/LITE/', $this->get_option('px_database'))) ? '<p class="description">If you are looking for high accuracy result, you should consider using the commercial version of <a href="https://www.ip2location.com/database/px1-ip-country#wordpress-wzdicb" target="_blank">IP2Proxy BIN database</a></p>' : '') . '
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>Database Path</label>
											</th>
											<td>
												<div>' . IP2LOCATION_DIR . '</div>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label>Database Date</label>
											</th>
											<td>
												' . esc_html(($px_date) ?: '-') . '
											</td>
										</tr>
										<tr>
											<td></td>
											<td id="px_update_status"><td>
										</tr>
										<tr>
											<td></td>
											<td><button id="update_ip2proxy_database" type="button" class="button button-secondary"' . (($disabled) ? ' disabled' : '') . '>Update Database</button></td>
										</tr>
									</table>
								</div>
							</div>
							<div id="px_api_web_service"' . (($px_lookup_mode == 'px_bin' || $px_lookup_mode == '') ? ' style="display:none"' : '') . '>
								<div class="iplcb-panel">
									<table class="form-table">';

		$legacyApiIpx = false;
		if (!empty($px_api_key)) {
			$response = wp_remote_get('https://api.ip2proxy.com/?' . http_build_query([
				'key'   => $px_api_key,
				'check' => 1,
			]), ['timeout' => 3]);

			$json = json_decode($response['body']);

			if (!empty($json)) {
				$legacyApiIpx = true;
			}
		}

		echo '
									<tr>
										<th scope="row">
											<label for="api_key">API Key</label>
										</th>
										<td>
											<input name="px_api_key" type="text" id="px_api_key" value="' . esc_attr($px_api_key) . '" class="regular-text" />';

		if ($legacyApiIpx) {
			echo ' <strong><i>(legacy API)</i></strong>';
		}

		echo '
											<p class="description">Your IP2Proxy <a href="https://www.ip2location.io/pricing" target="_blank">Web service</a> API key.</p>
										</td>
									</tr>';

		if (!empty($px_api_key)) {
			if (!empty($json)) {
				if (preg_match('/^[0-9]+$/', $json->response)) {
					echo '
									<tr>
										<th scope="row">
											<label for="available_credit">Available Credit</label>
										</th>
										<td>
											' . number_format($json->response, 0, '', ',') . '
										</td>
									</tr>';
				}
			}
		}

		echo '
									</table>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="detect_forwarder_ip">' . __('Detect Forwarder IP', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<label for="detect_forwarder_ip">
								<input type="checkbox" name="detect_forwarder_ip" id="detect_forwarder_ip" value="1"' . (($detect_forwarder_ip == 1) ? ' checked' : '') . ' /> ' . __('Enable', 'ip2location-country-blocker') . '
								<p class="description">
									' . __('Enable this option to try detecting the IP address behind the Forwarder (such as CDN provider).', 'ip2location-country-blocker') . '
								</p>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="enable_log">' . __('Visitor Logs', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<label for="enable_log">
								<input type="checkbox" name="enable_log" id="enable_log" value="1"' . (($enable_log == 1) ? ' checked' : '') . ' /> ' . __('Enable Logging', 'ip2location-country-blocker') . '
								<p class="description">
									' . __('No statistics will be available if this option is disabled.', 'ip2location-country-blocker') . '
								</p>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="enable_debug_log">' . __('Debugging Logs', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<label for="enable_debug_log">
								<input type="checkbox" name="enable_debug_log" id="enable_debug_log" value="1"' . (($enable_debug_log == 1) ? ' checked' : '') . ' /> ' . __('Enable Debugging Log', 'ip2location-country-blocker') . '
								<p class="description">
									' . sprintf(__('Debug log will store under "%1s".', 'ip2location-country-blocker'), IP2LOCATION_DIR . $this->debug_log) . '
									<br>
									<strong>For security concerns, please disable this option after completed debugging process.</strong>
								</p>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Real IP Detection', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<select name="real_ip_header" id="real_ip_header">
								<option value=""' . ((empty($real_ip_header)) ? ' selected' : '') . '> No Override</option>';
		foreach ($real_ip_headers as $value) {
			echo '
								<option value="' . $value . '"' . (($real_ip_header == $value) ? ' selected' : '') . '> ' . $value . '</option>';
		}

		echo '				</select>
							<p class="description">
								' . __('If your WordPress is installed behind a reverse proxy or load balancer, the real IP address of the visitors may not forwarded correctly and causing inaccurate country results. Use this option to override the IP detected by IP2Location.', 'ip2location-country-blocker') . '
							</p>
							<p class="description">
								' . __('Detected IP: <strong>' . esc_html($this->ip()) . '</strong>.', 'ip2location-country-blocker') . '
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Backup', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<button id="btn_download_backup" type="button" class="button button-secondary">' . __('Download Backup', 'ip2location-country-blocker') . '</button>
							<p class="description">
								' . __('Download all settings to restore your configuration on new installation.', 'ip2location-country-blocker') . '
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Restore', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<div id="restore_file"></div>
							<p class="description">
								' . __('Restore settings from previous installation.', 'ip2location-country-blocker') . '
							</p>
							<input type="hidden" id="restore_nonce" value="' . wp_create_nonce('restore') . '">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label>' . __('Cache', 'ip2location-country-blocker') . '</label>
						</th>
						<td>
							<button id="btn_clear_cache" type="button" class="button button-danger">' . __('Clear Cache', 'ip2location-country-blocker') . ' (' . $this->display_bytes($this->cache_size()) . ')</button>
							<p class="description">
								' . __('Clear all cached data.', 'ip2location-country-blocker') . '
							</p>
							<input type="hidden" id="cache_nonce" value="' . wp_create_nonce('cache') . '">
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Changes', 'ip2location-country-blocker') . '" />
				</p>
			</form>
			<div class="clear"></div>
		</div>

		<form id="form_download_backup" method="post">
			<input type="hidden" id="backup_nonce" name="__nonce" value="' . wp_create_nonce('backup') . '">
			<input type="hidden" name="action" value="download_ip2location_country_blocker_backup">
		</form>

		<div id="download-database-modal" class="ip2location-modal">
			<div class="ip2location-modal-content">
				<span class="ip2location-close">&times;</span>

				<h3>Database Download</h3>

				<div id="download_status"></div>
			</div>
		</div>';

		if (!$this->is_setup_completed()) {
			echo '
			<div id="modal-get-started" class="ip2location-modal" style="display:block">
				<div class="ip2location-modal-content">
					<div align="center" style="margin:10px auto;">
						<img src="' . plugins_url('/assets/img/logo.png', __FILE__) . '" width="200" height="24" align="center" alt="IP2Location"><br>
						<img src="' . plugins_url('/assets/img/get-started.png', __FILE__) . '" width="160" height="125" align="center" style="margin-top:5px;" alt="IP2Location Country Blocker">
					</div>
					<p style="margin-top:0;">
						' . sprintf(__('%1$sIP2Location Country Blocker%2$s is a plugin designed to restrict visitors or traffic based on their geolocation determined by their IP address', 'ip2location-country-blocker'), '<strong>', '</strong>') . '
					</p>
					<p>
						' . __('Please follow these steps to complete the setup.', 'ip2location-country-blocker') . '
					</p>';

			if (!extension_loaded('bcmath')) {
				echo '
					<span class="dashicons dashicons-warning"></span> ' . sprintf(__('IP2Location requires %1$s PHP extension enabled. Please enable this extension in your %2$s.', 'ip2location-country-blocker'), '<strong>bcmath</strong>', '<strong>php.ini</strong>') . '
					<p style="text-align:center;margin-top:25px">
						<button class="button button-primary" style="padding:3px 18px;" disabled>' . __('Get Started', 'ip2location-country-blocker') . '</button>
					</p>';
			} else {
				echo '
					<p style="text-align:center;margin-top:25px">
						<button class="button button-primary" id="btn-get-started" style="padding:3px 18px;">' . __('Get Started', 'ip2location-country-blocker') . '</button>
					</p>';
			}

			echo '
				</div>
			</div>

			<div id="modal-step-1" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div class="ip2location-sel-form">
						<div class="ip2location-sel-con">
							<h1 style="line-height:1.2;font-size:23px;text-align:center;margin-bottom:25px;">' . __('Choose Query Method', 'ip2location-country-blocker') . '</h1>
							<div class="ip2location-sel-img-div">
								<input width="100" type="radio" name="ipl-sel" id="db" value="db" checked>
								<label for="db">
									<span class="ip2location-sel-img">
										<img src="' . plugins_url('/assets/img/db.png', __FILE__) . '" width="90" height="90" align="center" alt="IP2Location BIN Database">
									</span>
								</label>
								<h4 style="margin-bottom:0;">' . __('IP2Location BIN Database (Local Query)', 'ip2location-country-blocker') . '</h4>
								<p style="margin-top:8px;">' . __('Free geolocation database download', 'ip2location-country-blocker') . '</p>
							</div>
							<div class="ip2location-sel-img-div">
								<input type="radio" name="ipl-sel" id="api" value="api" >
								<label for="api">
									<span class="ip2location-sel-img">
										<img src="' . plugins_url('/assets/img/api.png', __FILE__) . '" width="90" height="90" align="center" alt="IP2Location.io IP Geolocation API">
									</span>
								</label>
								<h4 style="margin-bottom:0;">' . __('IP2Location.io IP Geolocation API (Remote Query)', 'ip2location-country-blocker') . '</h4>
								<p style="margin-top:8px;">' . __('Free 30K IP geolocation queries per month', 'ip2location-country-blocker') . '</p>
							</div>
						</div>
					</div>
					<p style="text-align:right;margin-top:15px">
						<button id="btn-to-step-1" class="button button-primary" style="padding:3px 18px;">' . __('Next', 'ip2location-country-blocker') . ' &raquo;</button>
					</p>
				</div>
			</div>

			<!-- db -->
			<div id="modal-db-step-1" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div align="center">
						<h1 style="line-height:1.2;font-size:23px;margin-bottom:25px;">' . __('Set Up IP2Location LITE BIN Database', 'ip2location-country-blocker') . '</h1>
						<table class="setup ip2location-steps" width="200">
							<tr>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-1-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 1"><br>
									' . __('Step 1', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-2.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 2"><br>
									' . __('Step 2', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-3.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 3"><br>
									' . __('Step 3', 'ip2location-country-blocker') . '
								</td>
							</tr>
						</table>
						<div class="ip2location-line"></div>
					</div>
					<form>
						<p>
							<label>' . __('Enter IP2Location LITE download token', 'ip2location-country-blocker') . '</label>
							<input type="text" id="setup_token" class="regular-text code" maxlength="64" style="width:100%; margin-top: 10px; margin-bottom: 4px;">
						</p>
						<p class="description">
							' . sprintf(__('Don\'t have an account yet? Sign up a %1$s free IP geolocation account%2$s to obtain your download token.', 'ip2location-country-blocker'), '<a href="https://lite.ip2location.com/sign-up#wordpress-wzdicb" target="_blank">', '</a>') . '
						</p>
						<p id="token_status" style="margin-top:20px;margin-bottom:20px;">&nbsp;</p>
					</form>
					<p style="text-align:right;margin-top:15px">
						<button id="btn-to-db-step-0" class="button button-secondary" style="padding:3px 18px;margin-right:8px;" >&laquo; ' . __('Previous', 'ip2location-country-blocker') . '</button>
						<button id="btn-to-db-step-2" class="button button-primary" style="padding:3px 18px;" disabled>' . __('Next', 'ip2location-country-blocker') . ' &raquo;</button>
					</p>
				</div>
			</div>

			<div id="modal-db-step-2" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div align="center">
						<h1 style="line-height:1.2;font-size:23px;margin-bottom:25px;">' . __('Download IP2Location BIN Database', 'ip2location-country-blocker') . '</h1>
						<table class="setup ip2location-steps" width="200">
							<tr>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-1-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 1"><br>
									' . __('Step 1', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-2-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 2"><br>
									' . __('Step 2', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-3.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 3"><br>
									' . __('Step 3', 'ip2location-country-blocker') . '
								</td>
							</tr>
						</table>
						<div class="ip2location-line"></div>
					</div>

					<form style="height:140px">
						<p id="ip2location_download_status"></p>
					</form>
					<p style="text-align:right;margin-top:30px">
						<button id="btn-to-db-step-1" class="button button-secondary" style="padding:3px 18px;margin-right:8px;" disabled>&laquo; ' . __('Previous', 'ip2location-country-blocker') . '</button>
						<button id="btn-to-db-step-3" class="button button-primary" style="padding:3px 18px;" disabled>' . __('Next', 'ip2location-country-blocker') . ' &raquo;</button>
					</p>
				</div>
			</div>

			<div id="modal-db-step-3" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div align="center">
						<h1 style="line-height:1.2;font-size:23px;margin-bottom:25px;">' . __('Configure The Rules', 'ip2location-country-blocker') . '</h1>
						<table class="setup ip2location-steps" width="200">
							<tr>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-1-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 1"><br>
									' . __('Step 1', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-2-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 2"><br>
									' . __('Step 2', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-3-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 3"><br>
									' . __('Step 3', 'ip2location-country-blocker') . '
								</td>
							</tr>
						</table>
						<div class="ip2location-line"></div>
					</div>

					<form style="height:75px;">
						<p>
							' . __('Please click the “Finish” button to start configuring your rules.', 'ip2location-country-blocker') . '
						</p>
					</form>
					<p style="text-align:right;margin-top:30px">
						<button class="button button-primary" style="padding:3px 18px;" onclick="window.location.href=\'' . admin_url('admin.php?page=ip2location-country-blocker') . '\';">' . __('Finish', 'ip2location-country-blocker') . '</button>
					</p>
				</div>
			</div>

			<!-- api -->
			<div id="modal-api-step-1" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div align="center">
						<h1 style="line-height:1.2;font-size:23px;margin-bottom:25px;">' . __('Set Up IP2Location.io IP Geolocation Service', 'ip2location-country-blocker') . '</h1>
						<table class="setup ip2location-steps" width="200">
							<tr>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-1-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 1"><br>
									' . __('Step 1', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-3.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 2"><br>
									' . __('Step 2', 'ip2location-country-blocker') . '
								</td>
							</tr>
						</table>
						<div class="ip2location-api-line"></div>
					</div>
					<form>
						<p>
							<label>' . __('Enter IP2Location.io IP Geolocation API key', 'ip2location-country-blocker') . '</label>
							<input type="text" id="setup_api_key" class="regular-text code" maxlength="32" style="width:100%;margin-top: 10px; margin-bottom: 4px;">
						</p>
						<p class="description">
							' . sprintf(__('Don\'t have an account yet? Sign up a %1$s free IP geolocation plan%2$s to obtain your API key.', 'ip2location-country-blocker'), '<a href="https://www.ip2location.io/sign-up?ref=wp_icb" target="_blank">', '</a>') . '
						</p>
						<p id="api_status">&nbsp;</p>
					</form>
					<p style="text-align:right;margin-top:30px">
						<button id="btn-to-api-step-0" class="button button-secondary" style="padding:3px 18px;margin-right:8px;">&laquo; ' . __('Previous', 'ip2location-country-blocker') . '</button>
						<button id="btn-to-api-step-2" class="button button-primary" style="padding:3px 18px;" >' . __('Next', 'ip2location-country-blocker') . ' &raquo;</button>
					</p>
				</div>
			</div>

			<div id="modal-api-step-2" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<div align="center">
						<h1 style="line-height:1.2;font-size:23px;margin-bottom:25px;">' . __('Configure The Rules', 'ip2location-country-blocker') . '</h1>
						<table class="setup ip2location-steps" width="200">
							<tr>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-1-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 1"><br>
									' . __('Step 1', 'ip2location-country-blocker') . '
								</td>
								<td align="center">
									<img src="' . plugins_url('/assets/img/step-3-selected.png', __FILE__) . '" width="36" height="36" align="center" alt="Wizard Step 2"><br>
									' . __('Step 2', 'ip2location-country-blocker') . '
								</td>
							</tr>
						</table>
						<div class="ip2location-api-line"></div>
					</div>

					<form style="height:75px;">
						<p>
							' . __('Please click the “Finish” button to start configuring your rules.', 'ip2location-country-blocker') . '
						</p>
					</form>
					<p style="text-align:right;margin-top:30px">
						<button class="button button-primary" style="padding:3px 18px;" onclick="window.location.href=\'' . admin_url('admin.php?page=ip2location-country-blocker') . '\';">' . __('Finish', 'ip2location-country-blocker') . '</button>
					</p>
				</div>
			</div>
			';
		}

		echo '<input type="hidden" id="validate_token_nonce" value="' . wp_create_nonce('validate-token') . '">';
		echo '<input type="hidden" id="validate_api_key_nonce" value="' . wp_create_nonce('validate-api-key') . '">';
		echo '<input type="hidden" id="update_nonce" value="' . wp_create_nonce('update-database') . '">';
	}

	public function admin_page()
	{
		if (!is_admin()) {
			return;
		}

		// Clear cache older than 3 days
		$this->cache_clear(3);

		// add_action('wp_enqueue_script', 'load_jquery');
		// wp_enqueue_style('iplcb-custom-css', plugins_url('/assets/css/customs.css', __FILE__), [], null);
	}

	public function check_block()
	{
		if (preg_replace('/https?:\/\//', '', $this->get_current_url()) == preg_replace('/https?:\/\//', '', (string) $this->get_option('frontend_error_page'))) {
			return;
		}

		if (preg_replace('/https?:\/\//', '', $this->get_current_url()) == preg_replace('/https?:\/\//', '', (string) $this->get_option('backend_error_page'))) {
			return;
		}

		// Disable redirection on administrator session
		if (current_user_can('administrator')) {
			return;
		}

		if (is_admin()) {
			return;
		}

		// Ignore internal XHR & cron
		if (isset($_SERVER['SCRIPT_NAME'])) {
			if (in_array(basename($_SERVER['SCRIPT_NAME']), ['admin-ajax.php', 'ajax.php', 'cron.php', 'wp-cron.php'])) {
				return;
			}
		}

		// Ignore content fetcher
		if (preg_match('/facebookexternalhit/', $this->user_agent())) {
			return;
		}

		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');

		// Backend
		if ($this->is_backend_page()) {
			if (!$this->get_option('backend_enabled')) {
				$this->write_debug_log('Backend blocking is disabled.');

				return;
			}

			if (preg_match('/(page|post)_id=([0-9]+)/', (string) $this->get_option('backend_error_page'), $matches)) {
				if ($this->get_current_url() == get_permalink($matches[2])) {
					return;
				}
			}

			$result = $this->get_location($this->ip());

			if (empty($result['country_code'])) {
				$this->write_debug_log('Cannot identify visitor country.');

				return;
			}

			if ($this->in_array($this->ip(), 'backend_ip_whitelist')) {
				$this->write_debug_log('IP is in whitelist.');

				return;
			}

			if ($this->get_option('backend_skip_bots') && $this->is_bot('backend')) {
				$this->write_debug_log('Web crawler detected.');

				return;
			}

			$cached_secret_code = $this->cache_get($this->ip() . '_secret_code');

			$secret_code = $this->get('secret_code', ($cached_secret_code !== null) ? $cached_secret_code : md5(microtime()));
			$action = $this->get('action');

			$this->cache_add($this->ip() . '_secret_code', $secret_code);

			$bypass_code = ($this->get_option('bypass_code')) ?: md5(microtime());

			// Stop validation if bypass code is provided.
			if (!empty($bypass_code) && hash_equals($bypass_code, $secret_code)) {
				$this->write_debug_log('Bypassed with secret code.');

				if ($action == 'emergency_stop') {
					$this->write_debug_log('Emergency stop.');

					$this->update_option('backend_enabled', 0);
					$this->update_option('frontend_enabled', 0);
				}

				return;
			}

			if ($this->in_array($this->ip(), 'backend_ip_blacklist')) {
				$this->write_debug_log('IP is in blacklist.', 'BLOCKED');

				$this->block_backend($result['country_code'], false);

				return;
			}

			$ban_list = $this->get_option('backend_banlist');

			if (is_array($ban_list)) {
				$ban_list = $this->expand_ban_list($ban_list);

				if ($this->check_list($result['country_code'], $ban_list, $this->get_option('backend_block_mode'))) {
					$this->write_debug_log('Country ' . (($this->get_option('backend_block_mode') == 1) ? 'is' : 'not') . ' in the list.');

					$this->block_backend($result['country_code']);

					return;
				}

				$this->write_debug_log('Access is allowed.');
			}

			if ($this->get_option('backend_block_proxy') && $result['is_proxy'] == 'YES') {
				$this->write_debug_log('IP is an anonymous proxy.', 'BLOCKED');
				$this->block_backend($result['country_code']);

				return;
			}

			$proxy_type_list = $this->get_option('backend_block_proxy_type');

			if (is_array($proxy_type_list)) {
				if (in_array($result['proxy_type'], $proxy_type_list)) {
					$this->write_debug_log('IP is a ' . $result['proxy_type'] . ' proxy.', 'BLOCKED');

					$this->block_backend($result['country_code']);

					return;
				}
			}

			if ($this->get_option('backend_auto_block_threshold')) {
				$this->wpdb_query('INSERT INTO ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log (ip_address, date_created) VALUES (%s, %s)', [
					$this->ip(),
					date('Y-m-d H:i:s'),
				]);

				$total = $this->wpdb_get_value(
					'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log WHERE ip_address = %s AND date_created >= %s', [
					$this->ip(),
					date('Y-m-d H:i:s', strtotime('-24 hour'))
				]);

				if ($total >= $this->get_option('backend_auto_block_threshold')) {
					// Add client IP into blacklist
					$this->update_option('backend_ip_blacklist', trim($this->get_option('backend_ip_blacklist') . ';' . $this->ip(), ';'));
				}

				$this->wpdb_query('DELETE FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log WHERE ip_address = %s AND date_created < %s', [
					$this->ip(),
					date('Y-m-d H:i:s', strtotime('-24 hour'))
				]);
			}
		}

		// Frontend
		else {
			if (!$this->get_option('frontend_enabled')) {
				$this->write_debug_log('Frontend blocking is disabled.');

				return;
			}

			if (preg_match('/(page|post)_id=([0-9]+)/', $this->get_option('frontend_error_page'), $matches)) {
				if ($this->get_current_url() == get_permalink($matches[2])) {
					return;
				}
			}

			if ($this->in_array($this->ip(), 'frontend_ip_whitelist')) {
				$this->write_debug_log('IP is in whitelist.');

				return;
			}

			if (is_user_logged_in()) {
				if ($this->get_option('frontend_whitelist_logged_user') == false || $this->get_option('frontend_whitelist_logged_user') == 1) {
					$this->write_debug_log('User is logged in.');

					return;
				}
			}

			if ($this->get_option('frontend_skip_bots') && $this->is_bot('frontend')) {
				$this->write_debug_log('Web crawler detected.');

				return;
			}

			$result = $this->get_location($this->ip());

			if (empty($result['country_code'])) {
				$this->write_debug_log('Unable to identify visitor country.');

				return;
			}

			if ($this->in_array($this->ip(), 'frontend_ip_blacklist')) {
				$this->write_debug_log('IP is in blacklist', 'BLOCKED');
				$this->block_frontend($result['country_code'], false);

				return;
			}

			$ban_list = $this->get_option('frontend_banlist');

			if (is_array($ban_list)) {
				$ban_list = $this->expand_ban_list($ban_list);

				if ($this->check_list($result['country_code'], $ban_list, $this->get_option('frontend_block_mode'))) {
					$this->write_debug_log('Country ' . (($this->get_option('frontend_block_mode') == 1) ? 'is' : 'not') . ' in the list.', 'BLOCKED');
					$this->block_frontend($result['country_code']);

					return;
				}
				$this->write_debug_log('Access is allowed.');
			}

			if ($this->get_option('frontend_block_proxy') && $result['is_proxy'] == 'YES') {
				$this->write_debug_log('IP is an anonymous proxy.', 'BLOCKED');
				$this->block_frontend($result['country_code']);

				return;
			}

			$proxy_type_list = $this->get_option('frontend_block_proxy_type');

			if (is_array($proxy_type_list)) {
				if (in_array($result['proxy_type'], $proxy_type_list)) {
					$this->write_debug_log('IP is a ' . $result['proxy_type'] . ' proxy.', 'BLOCKED');

					$this->block_frontend($result['country_code']);

					return;
				}
			}

			if ($this->get_option('frontend_auto_block_threshold')) {
				$this->wpdb_query('INSERT INTO ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_frontend_rate_limit_log (ip_address, date_created) VALUES (%s, %s)', [
					$this->ip(),
					date('Y-m-d H:i:s')
				]);

				$total = $this->wpdb_get_value(
					'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_frontend_rate_limit_log WHERE ip_address = %s AND date_created >= %s', [
					$this->ip(),
					date('Y-m-d H:i:s', strtotime('-24 hour'))
				]);

				if ($total >= $this->get_option('frontend_auto_block_threshold')) {
					// Add client IP into blacklist
					$this->update_option('frontend_ip_blacklist', trim($this->get_option('frontend_ip_blacklist') . ';' . $this->ip(), ';'));
				}

				$this->wpdb_query('DELETE FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log WHERE ip_address = %s AND date_created < %s', [
					$this->ip(),
					date('Y-m-d H:i:s', strtotime('-24 hour'))
				]);
			}
		}
	}

	public function add_admin_menu()
	{
		add_menu_page(__('Country Blocker', 'ip2location-country-blocker'), __('Country Blocker', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker', [$this, 'frontend_page'], 'dashicons-admin-ip2location', 30);
		add_submenu_page('ip2location-country-blocker', __('Frontend', 'ip2location-country-blocker'), __('Frontend', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker', [$this, 'frontend_page']);
		add_submenu_page('ip2location-country-blocker', __('Backend', 'ip2location-country-blocker'), __('Backend', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker-backend', [$this, 'backend_page']);
		add_submenu_page('ip2location-country-blocker', __('Statistics', 'ip2location-country-blocker'), __('Statistics', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker-statistics', [$this, 'statistics_page']);
		add_submenu_page('ip2location-country-blocker', __('IP Lookup', 'ip2location-country-blocker'), __('IP Lookup', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker-ip-lookup', [$this, 'ip_lookup_page']);
		add_submenu_page('ip2location-country-blocker', __('Settings', 'ip2location-country-blocker'), __('Settings', 'ip2location-country-blocker'), 'manage_options', 'ip2location-country-blocker-settings', [$this, 'settings_page']);
	}

	public function set_defaults()
	{
		add_option('ip2location_country_blocker_access_email_notification', 'none');
		add_option('ip2location_country_blocker_api_key', '');
		add_option('ip2location_country_blocker_backend_auto_block_threshold', '');
		add_option('ip2location_country_blocker_backend_banlist', '');
		add_option('ip2location_country_blocker_backend_block_mode', '1');
		add_option('ip2location_country_blocker_backend_block_proxy', '0');
		add_option('ip2location_country_blocker_backend_bots_list', '');
		add_option('ip2location_country_blocker_backend_enabled', '0');
		add_option('ip2location_country_blocker_backend_error_page', '');
		add_option('ip2location_country_blocker_backend_ip_blacklist', '');
		add_option('ip2location_country_blocker_backend_ip_whitelist', '');
		add_option('ip2location_country_blocker_backend_option', '1');
		add_option('ip2location_country_blocker_backend_redirect_url', '');
		add_option('ip2location_country_blocker_backend_skip_bots', '1');
		add_option('ip2location_country_blocker_bypass_code', '');
		add_option('ip2location_country_blocker_database', '');
		add_option('ip2location_country_blocker_debug_log_enabled', '0');
		add_option('ip2location_country_blocker_detect_forwarder_ip', '1');
		add_option('ip2location_country_blocker_download_ipv4_only', '0');
		add_option('ip2location_country_blocker_email_notification', 'none');
		add_option('ip2location_country_blocker_frontend_auto_block_threshold', '');
		add_option('ip2location_country_blocker_frontend_banlist', '');
		add_option('ip2location_country_blocker_frontend_block_mode', '1');
		add_option('ip2location_country_blocker_frontend_block_proxy', '0');
		add_option('ip2location_country_blocker_frontend_block_proxy_type', '');
		add_option('ip2location_country_blocker_frontend_bots_list', '');
		add_option('ip2location_country_blocker_frontend_enabled', '0');
		add_option('ip2location_country_blocker_frontend_error_page', '');
		add_option('ip2location_country_blocker_frontend_ip_blacklist', '');
		add_option('ip2location_country_blocker_frontend_ip_whitelist', '');
		add_option('ip2location_country_blocker_frontend_option', '1');
		add_option('ip2location_country_blocker_frontend_redirect_url', '');
		add_option('ip2location_country_blocker_frontend_skip_bots', '1');
		add_option('ip2location_country_blocker_frontend_whitelist_logged_user', '1');
		add_option('ip2location_country_blocker_log_enabled', '0');
		add_option('ip2location_country_blocker_lookup_mode', 'bin');
		add_option('ip2location_country_blocker_px_api_key', '');
		add_option('ip2location_country_blocker_px_database', '');
		add_option('ip2location_country_blocker_px_lookup_mode', '');
		add_option('ip2location_country_blocker_real_ip_header', '');
		add_option('ip2location_country_blocker_session_message', '');
		add_option('ip2location_country_blocker_token', '');

		$this->create_table();

		// Create scheduled task
		if (!wp_next_scheduled('ip2location_country_blocker_hourly_event')) {
			wp_schedule_event(time(), 'hourly', 'ip2location_country_blocker_hourly_event');
		}
	}

	public function update_ip2location_database()
	{
		@set_time_limit(300);

		check_admin_referer('update-database', '__nonce');

		header('Content-Type: application/json');

		if (!current_user_can('administrator')) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Permission denied.', 'ip2location-country-blocker'),
			]));
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		try {
			$token = $this->post('token', $this->get_option('token'));
			$ipv4_only = $this->post('ipv4_only') === 'true';
			$ipv6 = ($ipv4_only) ? '' : 'IPV6';
			$code = 'DB1BIN' . $ipv6;

			$working_dir = IP2LOCATION_DIR . 'working' . \DIRECTORY_SEPARATOR;
			$zip_file = $working_dir . 'database.zip';

			// Remove existing working directory
			$wp_filesystem->delete($working_dir, true);

			// Create working directory
			$wp_filesystem->mkdir($working_dir);

			if (!class_exists('WP_Http')) {
				include_once ABSPATH . WPINC . '/class-http.php';
			}

			$request = new WP_Http();

			// Check download permission
			$response = $request->request('https://www.ip2location.com/download-info?' . http_build_query([
				'package' => $code,
				'token'   => $token,
				'source'  => 'wp_country_blocker',
			]));

			$parts = explode(';', $response['body']);

			if ($parts[0] != 'OK') {
				// Download LITE version
				$code = 'DB1LITEBIN' . $ipv6;

				$response = $request->request('https://www.ip2location.com/download-info?' . http_build_query([
					'package' => $code,
					'token'   => $token,
					'source'  => 'wp_country_blocker',
				]));

				$parts = explode(';', $response['body']);

				if ($parts[0] != 'OK') {
					exit(json_encode([
						'status'  => 'ERROR',
						'message' => __('You do not have permission to download this database.', 'ip2location-country-blocker'),
					]));
				}
			}

			// Start downloading BIN database from IP2Location website
			$response = $request->request('https://www.ip2location.com/download?' . http_build_query([
				'file'   => $code,
				'token'  => $token,
				'source' => 'wp_country_blocker',
			]), [
				'timeout'          => 300,
				'follow_redirects' => true,
			]);

			if ((isset($response->errors)) || (!in_array('200', $response['response']))) {
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Connection timed out while downloading database.', 'ip2location-country-blocker'),
				]));
			}

			// Save downloaded package.
			$fp = fopen($zip_file, 'w');

			if (!$fp) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('No permission to write into file system.', 'ip2location-country-blocker'),
				]));
			}

			fwrite($fp, $response['body']);
			fclose($fp);

			if (filesize($zip_file) < 51200) {
				$message = file_get_contents($zip_file);
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Downloaded database is corrupted. Please try again later.', 'ip2location-country-blocker'),
				]));
			}

			// Unzip the package to working directory
			$result = unzip_file($zip_file, $working_dir);

			// Once extracted, delete the package.
			unlink($zip_file);

			if (is_wp_error($result)) {
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('There is problem when decompress the database.', 'ip2location-country-blocker'),
				]));
			}

			// File the BIN database
			$bin_database = '';
			$files = scandir($working_dir);

			foreach ($files as $file) {
				$file = basename($file);

				if (strtoupper(substr($file, -4)) == '.BIN') {
					$bin_database = $file;
					break;
				}
			}

			// Move file to IP2Location directory
			$wp_filesystem->move($working_dir . $bin_database, IP2LOCATION_DIR . $bin_database, true);

			$this->update_option('lookup_mode', 'bin');
			$this->update_option('database', $bin_database);
			$this->update_option('token', $token);
			$this->update_option('download_ipv4_only', ($ipv4_only) ? 1 : 0);

			// Remove working directory
			$wp_filesystem->delete($working_dir, true);

			// Flush caches
			$this->cache_flush();

			exit(json_encode([
				'status'  => 'OK',
				'message' => '',
			]));
		} catch (Exception $e) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => $e->getMessage(),
			]));
		}
	}

	public function update_ip2proxy_database()
	{
		@set_time_limit(300);

		check_admin_referer('update-database', '__nonce');

		header('Content-Type: application/json');

		if (!current_user_can('administrator')) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Permission denied.', 'ip2location-country-blocker'),
			]));
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		try {
			$token = $this->post('token');
			$code = 'PX2BIN';

			$working_dir = IP2LOCATION_DIR . 'working' . \DIRECTORY_SEPARATOR;
			$zip_file = $working_dir . 'database.zip';

			// Remove existing working directory
			$wp_filesystem->delete($working_dir, true);

			// Create working directory
			$wp_filesystem->mkdir($working_dir);

			// Check download permission
			$response = wp_remote_get('https://www.ip2location.com/download-info?' . http_build_query([
				'package' => $code,
				'token'   => $token,
				'source'  => 'wp_country_blocker',
			]));

			$parts = explode(';', $response['body']);

			if ($parts[0] != 'OK') {
				// Download LITE version
				$code = 'PX2LITEBIN';

				$response = wp_remote_get('https://www.ip2location.com/download-info?' . http_build_query([
					'package' => $code,
					'token'   => $token,
					'source'  => 'wp_country_blocker',
				]));

				$parts = explode(';', $response['body']);

				if ($parts[0] != 'OK') {
					exit(json_encode([
						'status'  => 'ERROR',
						'message' => __('You do not have permission to download this database.', 'ip2location-country-blocker'),
					]));
				}
			}

			// Start downloading BIN database from IP2Location website
			$tmp_file = download_url('https://www.ip2location.com/download?' . http_build_query([
				'file'   => $code,
				'token'  => $token,
				'source' => 'wp_country_blocker',
			]));

			if ((isset($response->errors)) || (!in_array('200', $response['response']))) {
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Connection timed out while downloading database.', 'ip2location-country-blocker'),
				]));
			}

			// Save downloaded package.
			copy($tmp_file, $zip_file);
			wp_delete_file($tmp_file);

			if (filesize($zip_file) < 51200) {
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __(file_get_contents($zip_file), 'ip2location-country-blocker'),
				]));
			}

			// Unzip the package to working directory
			$result = unzip_file($zip_file, $working_dir);

			// Once extracted, delete the package.
			wp_delete_file($zip_file);

			if (is_wp_error($result)) {
				$wp_filesystem->delete($working_dir, true);

				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('There is problem when decompress the database.', 'ip2location-country-blocker'),
				]));
			}

			// File the BIN database
			$bin_database = '';
			$files = scandir($working_dir);

			foreach ($files as $file) {
				$file = basename($file);

				if (strtoupper(substr($file, -4)) == '.BIN') {
					$bin_database = $file;
					break;
				}
			}

			// Move file to IP2Location directory
			$wp_filesystem->move($working_dir . $bin_database, IP2LOCATION_DIR . $bin_database, true);

			$this->update_option('px_lookup_mode', 'px_bin');
			$this->update_option('px_database', $bin_database);
			$this->update_option('token', $token);
			$this->update_option('download_ipv4_only', ($ipv4_only) ? 1 : 0);

			// Remove working directory
			$wp_filesystem->delete($working_dir, true);

			// Flush caches
			$this->cache_flush();

			exit(json_encode([
				'status'  => 'OK',
				'message' => '',
			]));
		} catch (Exception $e) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => $e->getMessage(),
			]));
		}
	}

	public function validate_token()
	{
		header('Content-Type: application/json');

		if (!current_user_can('administrator')) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Permission denied.', 'ip2location-country-blocker'),
			]));
		}

		check_admin_referer('validate-token', '__nonce');

		try {
			$token = $this->post('token');

			// Check download permission
			$response = wp_remote_get('https://www.ip2location.com/download-info?' . http_build_query([
				'package' => 'DB1BIN',
				'token'   => $token,
				'source'  => 'wp_country_blocker',
			]));

			if (is_wp_error($response)) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => $response->get_error_message(),
				]));
			}

			if (isset($response['errors'])) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => 'Unable to reach remote URL. Please try again later.',
				]));
			}

			$parts = explode(';', $response['body']);

			if ($parts[0] != 'OK') {
				$response = wp_remote_get('https://www.ip2location.com/download-info?' . http_build_query([
					'package' => 'DB1LITEBIN',
					'token'   => $token,
					'source'  => 'wp_country_blocker',
				]));

				if (is_wp_error($response)) {
					exit(json_encode([
						'status'  => 'ERROR',
						'message' => $response->get_error_message(),
					]));
				}

				$parts = explode(';', $response['body']);

				if ($parts[0] != 'OK') {
					exit(json_encode([
						'status'  => 'ERROR',
						'message' => __('Invalid download token.', 'ip2location-country-blocker'),
					]));
				}
			}

			$this->update_option('token', $token);

			exit(json_encode([
				'status'  => 'OK',
				'message' => '',
			]));
		} catch (Exception $e) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => $e->getMessage(),
			]));
		}
	}

	public function validate_api_key()
	{
		header('Content-Type: application/json');

		if (!current_user_can('administrator')) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Permission denied.', 'ip2location-country-blocker'),
			]));
		}

		check_admin_referer('validate-api-key', '__nonce');

		try {
			$apiKey = $this->post('key');

			if (empty($apiKey)) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Invalid API key.', 'ip2location-country-blocker'),
				]));
			}

			// Check download permission
			$response = wp_remote_get('https://api.ip2location.io/?' . http_build_query([
				'key'    => $apiKey,
				'source' => 'wp_country_blocker',
			]));

			if (is_wp_error($response)) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => $response->get_error_message(),
				]));
			}

			if (!isset($response['response']['code'])) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Remote server is not responding. Please try again later.', 'ip2location-country-blocker'),
				]));
			}

			if ($response['response']['code'] != 200) {
				exit(json_encode([
					'status'  => 'ERROR',
					'message' => __('Invalid API key.', 'ip2location-country-blocker'),
				]));
			}

			$this->update_option('lookup_mode', 'ws');
			$this->update_option('api_key', $apiKey);

			exit(json_encode([
				'status'  => 'OK',
				'message' => '',
			]));
		} catch (Exception $e) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => $e->getMessage(),
			]));
		}
	}

	public function restore()
	{
		header('Content-Type: application/json');

		if (!current_user_can('administrator')) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Permission denied.', 'ip2location-country-blocker'),
			]));
		}

		check_admin_referer('restore', '__nonce');

		if (!isset($_FILES['restore_file']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('File upload error.', 'ip2location-country-blocker')
			]));
		}

		$rows = json_decode(file_get_contents($_FILES['restore_file']['tmp_name']));

		if ($rows === null) {
			exit(json_encode([
				'status'  => 'ERROR',
				'message' => __('Invalid file format.', 'ip2location-country-blocker'),
			]));
		}

		foreach ($rows as $key => $value) {
			// Skip invalid options
			if (!in_array(str_replace('ip2location_country_blocker_', '', $key), $this->allowed_options)) {
				continue;
			}

			update_option($key, ((is_array($value)) ? $this->sanitize_array($value) : sanitize_text_field($value)));
		}

		$this->update_option('session_message', 'Restore completed.');

		exit(json_encode([
			'status' => 'OK',
		]));
	}

	// Add notice in plugin page.
	public function show_notice()
	{
		if ($this->is_setup_completed()) {
			return;
		}

		echo '
		<div class="error">
			<p>
				' . sprintf(__('IP2Location Country Blocker requires the IP2Location BIN database to work. %1$sSetup your database%2$s now.', 'ip2location-country-blocker'), '<a href="' . get_admin_url() . 'admin.php?page=ip2location-country-blocker-settings">', '</a>') . '
			</p>
		</div>';
	}

	// Enqueue the script.
	public function plugin_enqueues($hook)
	{
		wp_enqueue_style('iplcb-styles-css', untrailingslashit(plugins_url('/', __FILE__)) . '/assets/css/styles.css', []);

		if (!$this->is_setup_completed() && $hook != 'country-blocker_page_ip2location-country-blocker-settings') {
			wp_enqueue_script('iplcb-settings-js', plugins_url('/assets/js/settings.js', __FILE__), ['jquery'], null, true);
		}

		switch ($hook) {
			case 'plugins.php':
				wp_enqueue_script('jquery-ui-dialog');
				wp_enqueue_style('wp-jquery-ui-dialog');

				wp_enqueue_script('iplcb-feedback-js', plugins_url('/assets/js/feedback.js', __FILE__), ['jquery'], null, true);

				break;

			case 'toplevel_page_ip2location-country-blocker':
				add_action('wp_enqueue_script', 'load_jquery');

				wp_enqueue_script('iplcb-frontend-js', plugins_url('/assets/js/frontend.js', __FILE__), ['jquery'], null, true);
				wp_enqueue_script('iplcb-tagsinput-js', plugins_url('/assets/js/jquery.tagsinput.min.js', __FILE__), [], null, true);
				wp_enqueue_script('iplcb-chosen-js', plugins_url('/assets/js/chosen.jquery.min.js', __FILE__), [], null, true);

				wp_enqueue_style('iplcb-customs-css', plugins_url('/', __FILE__) . '/assets/css/customs.css', []);
				wp_enqueue_style('iplcb-tagsinput-css', plugins_url('/', __FILE__) . '/assets/css/jquery.tagsinput.min.css', []);
				wp_enqueue_style('iplcb-chosen-css', plugins_url('/', __FILE__) . '/assets/css/chosen.min.css', []);

				break;

			case 'country-blocker_page_ip2location-country-blocker-backend':
				add_action('wp_enqueue_script', 'load_jquery');

				wp_enqueue_script('iplcb-frontend-js', plugins_url('/assets/js/backend.js', __FILE__), ['jquery'], null, true);
				wp_enqueue_script('iplcb-tagsinput-js', plugins_url('/assets/js/jquery.tagsinput.min.js', __FILE__), [], null, true);
				wp_enqueue_script('iplcb-chosen-js', plugins_url('/assets/js/chosen.jquery.min.js', __FILE__), [], null, true);

				wp_enqueue_style('iplcb-tagsinput-css', plugins_url('/', __FILE__) . '/assets/css/jquery.tagsinput.min.css', []);
				wp_enqueue_style('iplcb-chosen-css', plugins_url('/', __FILE__) . '/assets/css/chosen.min.css', []);

				break;

			case 'country-blocker_page_ip2location-country-blocker-statistics':
				wp_enqueue_script('iplcb-chart-js', plugins_url('/assets/js/chart-js.min.js', __FILE__), ['jquery'], null, true);
				wp_enqueue_script('iplcb-statistics-js', plugins_url('/assets/js/statistics.js', __FILE__), [], null, true);
				break;

			case 'country-blocker_page_ip2location-country-blocker-settings':
				wp_register_script('iplcb-jquery-upload-file-js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-file-upload/4.0.11/jquery.uploadfile.min.js', null, null, true);
				wp_enqueue_script('iplcb-jquery-upload-file-js');

				wp_register_style('iplcb-jquery-upload-file-css', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-file-upload/4.0.11/uploadfile.min.css');
				wp_enqueue_style('iplcb-jquery-upload-file-css');

				wp_enqueue_script('iplcb-settings-js', plugins_url('/assets/js/settings.js', __FILE__), ['jquery'], null, true);
				break;
		}
	}

	public function footer()
	{
		echo "<!--\n";
		echo "The IP2Location Country Blocker is using IP2Location LITE geolocation database. Please visit https://lite.ip2location.com for more information.\n";
		echo "-->\n";
	}

	public function write_debug_log($message, $action = 'ABORTED')
	{
		if (!$this->get_option('debug_log_enabled')) {
			return;
		}

		error_log(json_encode([
			'time'       => gmdate('Y-m-d H:i:s'),
			'client_ip'  => $this->ip(),
			'country'    => $this->session['country'],
			'is_proxy'   => $this->session['is_proxy'],
			'proxy_type' => $this->session['proxy_type'],
			'lookup_by'  => $this->session['lookup_mode'],
			'cache'      => $this->session['cache'],
			'uri'        => $this->get_current_url(),
			'message'    => $message,
			'action'     => $action,
		]) . "\n", 3, IP2LOCATION_DIR . $this->debug_log);
	}

	public function admin_footer_text($footer_text)
	{
		$plugin_name = substr(basename(__FILE__), 0, strpos(basename(__FILE__), '.'));
		$current_screen = get_current_screen();

		if ($current_screen && strpos($current_screen->id, $plugin_name) !== false) {
			$footer_text .= sprintf(
				__('Enjoyed %1$s? Please leave us a %2$s rating. A huge thanks in advance!', $plugin_name),
				'<strong>' . __('IP2Location Country Blocker', $plugin_name) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/' . $plugin_name . '/reviews/?filter=5/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		if ($current_screen->id == 'plugins') {
			return $footer_text . '
			<div id="ip2location-country-blocker-feedback-modal" class="ip2location-modal">
				<div class="ip2location-modal-content">
					<span class="ip2location-close">&times;</span>

					<p>
						<h3>Would you mind sharing with us the reason to deactivate the plugin?</h3>
					</p>
					<span id="ip2location-country-blocker-feedback-response"></span>
					<p>
						<label>
							<input type="radio" name="ip2location-country-blocker-feedback" value="1"> I no longer need the plugin
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="ip2location-country-blocker-feedback" value="2"> I couldn\'t get the plugin to work
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="ip2location-country-blocker-feedback" value="3"> The plugin doesn\'t meet my requirements
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="ip2location-country-blocker-feedback" value="5"> The plugin doesn\'t work with Cache plugin
						</label>
					</p>
					<p>
						<label>
							<input type="radio" name="ip2location-country-blocker-feedback" value="4"> Other concerns
							<br><br>
							<textarea id="ip2location-country-blocker-feedback-other" style="display:none;width:100%"></textarea>
						</label>
					</p>
					<p>
						<div style="float:left">
							<input type="button" id="ip2location-country-blocker-submit-feedback-button" class="button button-danger" value="Submit & Deactivate" />
						</div>
						<div style="float:right">
							<a href="#">Skip & Deactivate</a>
						</div>
						<div style="clear:both"></div>
					</p>
				</div>
				<input type="hidden" id="ip2location_country_blocker_feedback_nonce" value="' . wp_create_nonce('submit-feedback') . '">
			</div>';
		}

		return $footer_text;
	}

	public function submit_feedback()
	{
		if (!current_user_can('deactivate_plugins')) {
			exit;
		}

		check_admin_referer('submit-feedback', '__nonce');

		$feedback = $this->post('feedback');
		$others = $this->post('others');

		$options = [
			1 => 'I no longer need the plugin',
			2 => 'I couldn\'t get the plugin to work',
			3 => 'The plugin doesn\'t meet my requirements',
			4 => 'Other concerns' . (($others) ? (' - ' . $others) : ''),
			5 => 'The plugin doesn\'t work with Cache plugin',
		];

		if (isset($options[$feedback])) {
			wp_remote_get('https://www.ip2location.com/wp-plugin-feedback?' . http_build_query([
				'name'    => 'ip2location-country-blocker',
				'message' => $options[$feedback],
			]), ['timeout' => 5]);
		}
	}

	public function hourly_event()
	{
		$this->cache_clear();
		$this->set_priority();
	}

	private function wpdb_query($query, ...$args)
	{
		if (count(func_get_args()) > 1) {
			return $GLOBALS['wpdb']->query($GLOBALS['wpdb']->prepare($query, ...$args));
		}

		return $GLOBALS['wpdb']->query($query);
	}

	private function wpdb_get_value($query, ...$args)
	{
		if (count(func_get_args()) > 1) {
			return $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare($query, ...$args));
		}

		return $GLOBALS['wpdb']->get_var($query);
	}

	private function wpdb_get_results($query, ...$args)
	{
		if (count(func_get_args()) > 1) {
			return $GLOBALS['wpdb']->get_results($GLOBALS['wpdb']->prepare($query, ...$args));
		}

		return $GLOBALS['wpdb']->get_results($query);
	}

	private function post($key, $default = '', $checkbox = false)
	{
		return (isset($_POST[$key])) ? ((is_array($_POST[$key])) ? $this->sanitize_array($_POST[$key]) : sanitize_text_field($_POST[$key])) : $default;
	}

	private function get($key, $default = '', $checkbox = false)
	{
		return (isset($_GET[$key])) ? ((is_array($_GET[$key])) ? $this->sanitize_array($_GET[$key]) : sanitize_text_field($_GET[$key])) : $default;
	}

	private function is_checked($key, $default = 0)
	{
		return ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST[$key])) ? 0 : ((isset($_POST[$key])) ? 1 : (int) $default);
	}

	private function update_option($key, $value)
	{
		if (!in_array($key, $this->allowed_options)) {
			return false;
		}

		return update_option('ip2location_country_blocker_' . $key, $value);
	}

	private function get_option($key)
	{
		return get_option('ip2location_country_blocker_' . $key);
	}

	private function cache_plugin_detected()
	{
		$plugins = [
			'Breeze'           => 'breeze/breeze.php',
			'Cache Enabler'    => 'cache-enabler/cache-enabler.php',
			'LiteSpeed Cache'  => 'litespeed-cache/litespeed-cache.php',
			'Super Page Cache' => 'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php',
			'W3 Total Cache'   => 'w3-total-cache/w3-total-cache.php',
			'WP Fastest Cache' => 'wp-fastest-cache/wpFastestCache.php',
			'WP Optimizer'     => 'wp-optimize/wp-optimize.php',
			'WP Rocket'        => 'wp-rocket/wp-rocket.php',
			'WP Super Cache'   => 'wp-super-cache/wp-cache.php',
		];

		foreach ($plugins as $name => $path) {
			if (is_plugin_active($path)) {
				return $name;
			}
		}

		return false;
	}

	private function set_priority()
	{
		global $pagenow;

		// Do not do this in plugins page to prevent deactivation issues.
		if ($pagenow != 'plugins.php') {
			// Make sure this plugin loaded as first priority.
			$this_plugin = plugin_basename(trim(preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR . '/$2', __FILE__)));
			$active_plugins = get_option('active_plugins');
			$this_plugin_key = array_search($this_plugin, $active_plugins);

			if ($this_plugin_key) {
				array_splice($active_plugins, $this_plugin_key, 1);
				array_unshift($active_plugins, $this_plugin);
				update_option('active_plugins', $active_plugins);
			}
		}
	}

	private function is_backend_page()
	{
		if (preg_match('/wp-admin|(wp-)?login/', $_SERVER['SCRIPT_NAME'])) {
			return true;
		}

		return $GLOBALS['pagenow'] == trim(strtolower(parse_url(wp_login_url('', true), \PHP_URL_PATH)), '/');
	}

	private function block_backend($country_code, $email = true)
	{
		if ($email) {
			$this->send_email();
		}

		$this->write_statistics_log(2, $country_code);

		if ($this->get_option('backend_option') == 1) {
			$this->deny(null, 'BLOCKED');
		} elseif ($this->get_option('backend_option') == 2) {
			$this->deny($this->get_option('backend_error_page'));
		} else {
			$this->redirect($this->get_option('backend_redirect_url'));
		}
	}

	private function block_frontend($country_code, $email = true)
	{
		// if ($email) {
		// 	$this->send_email();
		// }

		$this->write_statistics_log(1, $country_code);

		if ($this->get_option('frontend_option') == 1) {
			$this->deny(null, 'BLOCKED');
		} elseif ($this->get_option('frontend_option') == 2) {
			$this->deny($this->get_option('frontend_error_page'));
		} else {
			$this->redirect($this->get_option('frontend_redirect_url'));
		}
	}

	private function write_statistics_log($side, $country_code)
	{
		$table_name = $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log';

		if ($this->get_option('log_enabled') && $this->wpdb_get_value("SHOW TABLES LIKE %s", $table_name) == $table_name) {
			$this->wpdb_query('INSERT INTO ' . $table_name . ' (ip_address, country_code, side, page, date_created) VALUES (%s, %s, %d, %s, %s)', [
				$this->ip(),
				$country_code,
				$side,
				basename(home_url(add_query_arg(null, null))),
				date('Y-m-d H:i:s'),
			]);
		}
	}

	private function ip()
	{
		if ($this->get_option('real_ip_header')) {
			return $_SERVER[$this->get_option('real_ip_header')] ?? $_SERVER['REMOTE_ADDR'];
		}

		// Possible using CloudFlare service
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
			// Make sure originated IP is coming from CloudFlare network
			$networks = [
				'173.245.48.0/20',
				'103.21.244.0/22',
				'103.22.200.0/22',
				'103.31.4.0/22',
				'141.101.64.0/18',
				'108.162.192.0/18',
				'190.93.240.0/20',
				'188.114.96.0/20',
				'197.234.240.0/22',
				'198.41.128.0/17',
				'162.158.0.0/15',
				'104.16.0.0/13',
				'104.24.0.0/14',
				'172.64.0.0/13',
				'131.0.72.0/22',
				'2400:cb00::/32',
				'2606:4700::/32',
				'2803:f800::/32',
				'2405:b500::/32',
				'2405:8100::/32',
				'2a06:98c0::/29',
				'2c0f:f248::/32',
			];

			foreach ($networks as $network) {
				if ($this->cidr_match($_SERVER['REMOTE_ADDR'], $network)) {
					return $_SERVER['HTTP_CF_CONNECTING_IP'];
				}
			}
		}

		// Possible Securi Firewall
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			// Make sure originated IP is coming from Securi network
			$networks = [
				'192.88.134.0/23',
				'185.93.228.0/22',
				'66.248.200.0/22',
				'208.109.0.0/22',
				'2a02:fe80::/29',
			];

			foreach ($networks as $network) {
				if ($this->cidr_match($_SERVER['REMOTE_ADDR'], $network)) {
					return $_SERVER['HTTP_X_FORWARDED_FOR'];
				}
			}
		}

		// Possible local reverse proxy server
		if (!filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			if (isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
				return $_SERVER['HTTP_X_REAL_IP'];
			}

			if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				// Get server IP address
				$server_ip = (isset($_SERVER['SERVER_ADDR'])) ? $_SERVER['SERVER_ADDR'] : '';

				$ip = trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));

				if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) && $ip != $server_ip) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'];
	}

	private function is_bot($interface = 'frontend')
	{
		$is_bot = preg_match('/baidu|bingbot|googlebot|-google|ia_archiver|msnbot|naverbot|perplexity|pingdom|seznambot|slurp|teoma|twitter|yandex|yeti|feedburner/i', $this->user_agent());

		$list = $this->get_option('' . (($interface == 'frontend') ? 'frontend' : 'backend') . '_bots_list');

		if (is_array($list)) {
			foreach ($list as $bot) {
				if (empty($bot)) {
					continue;
				}

				if (preg_match('/' . $bot . '/i', $this->user_agent())) {
					return true;
				}
			}
		}

		return $is_bot;
	}

	private function send_email()
	{
		if (filter_var($this->get_option('email_notification'), \FILTER_VALIDATE_EMAIL)) {
			$message = [];

			$message[] = 'Hi,';

			$occurrence = $this->wpdb_get_value('SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log WHERE ip_address = %s AND date_created >= %s', [
				$this->ip(),
				date('Y-m-d H:i:s', strtotime('-1 hour'))
			]);

			$message[] = 'IP2Location Country Blocker has detected and blocked the visitor from accessing your admin page:';
			$message[] = '';
			$message[] = 'IP Address: ' . $this->ip();
			$message[] = 'Total Occurrence in past 1 hour: ' . $occurrence;
			$message[] = 'URL: ' . $this->get_current_url();
			$message[] = '';
			$message[] = str_repeat('-', 100);
			$message[] = 'Get a free IP2Location LITE database at https://lite.ip2location.com.';
			$message[] = 'Get an accurate IP2Location commercial database at https://www.ip2location.com.';
			$message[] = str_repeat('-', 100);
			$message[] = '';
			$message[] = '';
			$message[] = 'Regards,';
			$message[] = 'IP2Location Country Blocker';
			$message[] = 'www.ip2location.com';

			$this->write_debug_log('Send notification email.');

			wp_mail($this->get_option('email_notification'), 'IP2Location Country Blocker Alert', implode("\n", $message));
		}
	}

	private function get_page_id($url = '')
	{
		if ($url) {
			$parts = parse_url($url);

			$queries = [];

			if (isset($parts['query'])) {
				parse_str($parts['query'], $queries);
			}

			if (isset($queries['page_id'])) {
				return $queries['page_id'];
			}

			if (isset($parts['path'])) {
				$post_name = $parts['path'];
			} else {
				$post_name = '';
			}
		} else {
			$post_name = preg_replace('/\/?\?.+$/', '', trim($_SERVER['REQUEST_URI'], '/'));
		}

		if (strrpos($post_name, '/') !== false) {
			$post_name = substr($post_name, strrpos($post_name, '/') + 1);
		}

		$results = $this->wpdb_get_results("SELECT ID FROM {$GLOBALS['wpdb']->prefix}posts WHERE post_name = %s", [
			$post_name
		]);

		return ($results) ? $results[0]->ID : null;
	}

	private function user_agent()
	{
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	private function redirect($url)
	{
		$current_url = preg_replace('/^https?:\/\//', '', home_url(add_query_arg(null, null)));
		$new_url = preg_replace('/^https?:\/\//', '', $url);

		// Prevent infinite redirection.
		if ($new_url == $current_url) {
			return;
		}

		if ($this->get_page_id() !== null && $this->get_page_id() == $this->get_page_id($new_url)) {
			return;
		}

		$this->write_debug_log('Redirected to: "' . $url . '".', 'REDIRECTED');

		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . $url);
		exit;
	}

	private function build_url($scheme, $host, $path, $queries)
	{
		return $scheme . '://' . $host . (($path) ?: '/') . (($queries) ? ('?' . http_build_query($queries)) : '');
	}

	private function get_current_url($add_query = true)
	{
		if (!isset($_SERVER)) {
			return '';
		}

		if (!isset($_SERVER['HTTP_HOST'])) {
			return '';
		}

		$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$parts = parse_url($current_url);

		$queries = [];

		if (isset($parts['query'])) {
			parse_str($parts['query'], $queries);
		}

		return $this->build_url($parts['scheme'], $parts['host'], (isset($parts['path'])) ? $parts['path'] : '', ($add_query) ? $queries : []);
	}

	private function deny($url = '')
	{
		if (empty($url)) {
			header('HTTP/1.1 403 Forbidden');

			echo '
			<html>
				<head>
					<meta http-equiv="content-type" content="text/html;charset=utf-8">
					<title>Error 403: Access Denied</title>
					<style>
						body{font-family:arial,sans-serif}
					</style>
				</head>
				<body>
					<div style="margin:30px; text-align:center;">
						<img src="data:image/png;base64,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" style="max-width:100%; width:450px;height:auto;" width="450" height="338" align="center" alt="Access Denied">
						<div style="padding: 0px 30px; 10px 30px">
							<h1>' . __('Access Denied', 'ip2location-country-blocker') . '</h1>
							<div>' . __('Please contact web administrator for assistance.', 'ip2location-country-blocker') . '</div>
						</div>
						<div style="padding:10px 0;margin:60px 0 0 0;text-align:center;font-size:13px;">IP Geolocation Powered by <a href="https://wordpress.org/plugins/ip2location-country-blocker/" target="_blank">IP2Location Country Blocker</a></div>
					</div>
				</body>
			</html>';

			$this->write_debug_log('Access denied.');

			exit;
		}

		$this->redirect($url);
	}

	private function check_list($country_code, $ban_list, $mode = 1)
	{
		return ($mode == 1) ? $this->is_in_array($country_code, $ban_list) : !$this->is_in_array($country_code, $ban_list);
	}

	private function expand_ban_list($ban_list)
	{
		if (!is_array($ban_list)) {
			return $ban_list;
		}

		$groups = [];

		foreach ($ban_list as $item) {
			if ($this->is_in_array($item, array_keys($this->country_groups))) {
				$groups = array_merge($groups, $this->country_groups[$item]);

				if (($key = array_search($item, $ban_list)) !== false) {
					unset($ban_list[$key]);
				}
			}
		}

		return array_merge($ban_list, $groups);
	}

	private function get_group_from_list($ban_list)
	{
		$groups = [];

		foreach ($ban_list as $item) {
			if ($this->is_in_array($item, array_keys($this->country_groups))) {
				$groups[] = $item;
			}
		}

		return (empty($groups)) ? false : $groups;
	}

	private function is_in_array($needle, $array)
	{
		if (!is_array($array)) {
			return false;
		}

		foreach (array_values($array) as $key) {
			$return[$key] = 1;
		}

		return isset($return[$needle]);
	}

	private function get_location($ip)
	{
		// Read result from cache to prevent duplicate lookup.
		if ($data = $this->cache_get($ip)) {
			$this->session['country'] = $data->country_code;
			$this->session['country_name'] = $data->country_name;
			$this->session['is_proxy'] = $data->is_proxy;
			$this->session['proxy_type'] = $data->proxy_type;
			$this->session['cache'] = true;

			return [
				'country_code' => $data->country_code,
				'country_name' => $data->country_name,
				'is_proxy'     => $data->is_proxy,
				'proxy_type'   => $data->proxy_type,
			];
		}

		$caches = [
			'country_code' => '',
			'country_name' => '',
			'is_proxy'     => '',
			'proxy_type'   => '',
		];

		switch ($this->get_option('lookup_mode')) {
			// IP2Location Web Service
			case 'ws':
				$this->session['lookup_mode'] = 'WS';

				if (preg_match('/^[0-9A-Z]{32}$/', $this->get_option('api_key'))) {
					$response = wp_remote_get('https://api.ip2location.io/?' . http_build_query([
						'key'    => $this->get_option('api_key'),
						'ip'     => $ip,
						'source' => 'wp-country-blocker',
					]), ['timeout' => 5]);

					$json = json_decode($response['body']);

					if ($json === null) {
						$this->write_debug_log('Web service timed out.', 'ERROR');

						return $caches;
					} elseif (isset($json->error)) {
						$this->write_debug_log($json->error->error_message, 'ERROR');

						return $caches;
					} else {
						$caches['country_code'] = $json->country_code;
						$caches['country_name'] = $json->country_name;
					}
				} else {
					$response = wp_remote_get('https://api.ip2location.com/v2/?' . http_build_query([
						'key'     => $this->get_option('api_key'),
						'ip'      => $ip,
						'package' => 'WS1',
					]), ['timeout' => 5]);

					$json = json_decode($response['body']);

					if ($json === null) {
						$this->write_debug_log('Web service timed out.', 'ERROR');

						return $caches;
					} elseif ($json->response != 'OK') {
						$this->write_debug_log($json->response, 'ERROR');

						return $caches;
					} else {
						$caches['country_code'] = $json->country_code;
						$caches['country_name'] = $this->get_country_name($json->country_code);
					}
				}

				break;

				// Local BIN database
			default:
				$this->session['lookup_mode'] = 'BIN';

				// Make sure IP2Location database is exist.
				if (!is_file(IP2LOCATION_DIR . $this->get_option('database'))) {
					$this->write_debug_log('Database not found.', 'ERROR');

					return $caches;
				}

				// Create IP2Location object.
				$db = new \IP2Location\Database(IP2LOCATION_DIR . $this->get_option('database'), \IP2Location\Database::FILE_IO);

				// Get geolocation by IP address.
				$response = $db->lookup($ip, \IP2Location\Database::ALL);

				if (isset($response['countryCode'])) {
					// Store result into cache for later use.
					$caches['country_code'] = $response['countryCode'];
					$caches['country_name'] = $response['countryName'];
				}
				break;
		}

		switch ($this->get_option('px_lookup_mode')) {
			// IP2Location Web Service
			case 'px_ws':
				$this->session['lookup_mode'] = 'WS';

				if (preg_match('/^[0-9A-Z]{32}$/', $this->get_option('api_key'))) {
					$response = wp_remote_get('https://api.ip2location.io/?' . http_build_query([
						'key'    => $this->get_option('px_api_key'),
						'ip'     => $ip,
						'source' => 'wp-country-blocker',
					]), ['timeout' => 3]);

					$json = json_decode($response['body']);

					if (empty($json)) {
						$this->write_debug_log('Web service timed out.', 'ERROR');

						return $caches;
					} elseif (isset($json->error)) {
						$this->write_debug_log($json->error->error_message, 'ERROR');

						return $caches;
					} else {
						// Store result into cache for later use.
						$caches = [
							'country_code' => $json->country_code,
							'country_name' => $json->country_name,
							'is_proxy'     => ($json->is_proxy) ? 'YES' : 'NO',
							'proxy_type'   => (isset($json->proxy_type)) ? $json->proxy_type : null,
						];
					}
				} else {
					$response = wp_remote_get('https://api.ip2proxy.com/?' . http_build_query([
						'key'     => $this->get_option('px_api_key'),
						'ip'      => $ip,
						'package' => 'PX2',
					]), ['timeout' => 3]);

					$json = json_decode($response['body']);

					if (empty($json)) {
						$this->write_debug_log('Web service timed out.', 'ERROR');

						return $caches;
					} elseif ($json->response != 'OK') {
						$this->write_debug_log($json->response, 'ERROR');

						return $caches;
					} else {
						// Store result into cache for later use.
						$caches = [
							'country_code' => $json->countryCode,
							'country_name' => $json->countryName,
							'is_proxy'     => ($json->isProxy) ? 'YES' : 'NO',
							'proxy_type'   => $json->proxyType,
						];
					}
				}
				break;

				// Local BIN database
			case 'px_bin':
				if (!$this->get_option('px_database')) {
					break;
				}

				$this->session['lookup_mode'] = 'BIN';

				// Make sure IP2Location database is exist.
				if (!is_file(IP2LOCATION_DIR . $this->get_option('px_database'))) {
					$this->write_debug_log('Database not found.', 'ERROR');

					return $caches;
				}

				// Create IP2Location object.
				$db = new \IP2Proxy\Database(IP2LOCATION_DIR . $this->get_option('px_database'), \IP2Location\Database::FILE_IO);

				// Get geolocation by IP address.
				$response = $db->lookup($ip, \IP2Proxy\Database::ALL);

				// Store result into cache for later use.
				$caches['is_proxy'] = ($response['countryCode'] == '-') ? 'NO' : (($response['countryCode'] != '-' && !in_array($response['proxyType'], ['SES', 'DCH', 'CDN'])) ? 'YES' : 'NO');
				$caches['proxy_type'] = $response['proxyType'];

				break;
		}

		$this->cache_add($ip, $caches);
		$this->session['country'] = $caches['country_code'];
		$this->session['is_proxy'] = $caches['is_proxy'];
		$this->session['proxy_type'] = $caches['proxy_type'];

		return $caches;
	}

	private function get_country_name($code)
	{
		return (isset($this->countries[$code])) ? $this->countries[$code] : '';
	}

	private function in_array($ip, $list_name)
	{
		// Expand IPv6
		if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
			$ip = implode(':', str_split(unpack('H*0', inet_pton($ip))[0], 4));
		}

		$rows = explode(';', $this->get_option('' . $list_name));

		if (count($rows) > 0) {
			foreach ($rows as $row) {
				// Expand IPv6
				if (filter_var($row, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
					$row = implode(':', str_split(unpack('H*0', inet_pton($row))[0], 4));
				}

				if ($row == $ip) {
					return true;
				}

				if (strpos($row, '/') !== false) {
					if ($this->cidr_match($ip, $row)) {
						return true;
					}
				} elseif (preg_match('/^' . str_replace(['.', '*'], ['\\.', '.+'], $row) . '$/', $ip)) {
					return true;
				}
			}
		}

		return false;
	}

	private function get_database_date()
	{
		if (!is_file(IP2LOCATION_DIR . $this->get_option('database'))) {
			return;
		}

		$obj = new \IP2Location\Database(IP2LOCATION_DIR . $this->get_option('database'), \IP2Location\Database::FILE_IO);

		return date('Y-m-d', strtotime(str_replace('.', '-', $obj->getDatabaseVersion())));
	}

	private function get_px_database_date()
	{
		if (!is_file(IP2LOCATION_DIR . $this->get_option('px_database'))) {
			return;
		}

		$db = new \IP2Proxy\Database(IP2LOCATION_DIR . $this->get_option('px_database'), \IP2PROXY\Database::FILE_IO);

		return date('Y-m-d', strtotime(str_replace('.', '-', $db->getDatabaseVersion())));
	}

	private function cache_add($key, $value)
	{
		file_put_contents(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . md5($key . '_ip2location_country_blocker') . '.json', json_encode([
			$key => $value,
		]));
	}

	private function cache_get($key)
	{
		if (file_exists(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . md5($key . '_ip2location_country_blocker') . '.json')) {
			$json = json_decode(file_get_contents(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . md5($key . '_ip2location_country_blocker') . '.json'));

			return $json->{$key};
		}

		return null;
	}

	private function cache_clear($day = 1)
	{
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$now = time();
		$files = scandir(IP2LOCATION_DIR . 'caches');

		foreach ($files as $file) {
			if (substr($file, -5) == '.json') {
				if ($now - filemtime(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . $file) >= 60 * 60 * 24 * $day) {
					$wp_filesystem->delete(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . $file);
				}
			}
		}
	}

	private function cache_size()
	{
		$size = 0;

		$files = scandir(IP2LOCATION_DIR . 'caches');

		foreach ($files as $file) {
			if (substr($file, -5) == '.json') {
				$size += filesize(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . $file);
			}
		}

		return $size;
	}

	private function cache_flush()
	{
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$files = scandir(IP2LOCATION_DIR . 'caches');

		foreach ($files as $file) {
			if (substr($file, -5) == '.json') {
				$wp_filesystem->delete(IP2LOCATION_DIR . 'caches' . \DIRECTORY_SEPARATOR . $file);
			}
		}
	}

	private function get_memory_limit()
	{
		$memory_limit = ini_get('memory_limit');

		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
			if ($matches[2] == 'G') {
				$memory_limit = $matches[1] * 1024 * 1024 * 1024;
			} elseif ($matches[2] == 'M') {
				$memory_limit = $matches[1] * 1024 * 1024;
			} elseif ($matches[2] == 'K') {
				$memory_limit = $matches[1] * 1024;
			}
		}

		return $memory_limit;
	}

	private function is_setup_completed()
	{
		if ($this->get_option('lookup_mode') == 'ws' && $this->get_option('api_key')) {
			return true;
		}

		if ($this->get_option('lookup_mode') == 'bin' && is_file(IP2LOCATION_DIR . $this->get_option('database'))) {
			return true;
		}

		return false;
	}

	private function sanitize_array($array)
	{
		if (is_string($array)) {
			return sanitize_text_field($array);
		}
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = $this->sanitize_array($value);
			} else {
				$array[$key] = sanitize_text_field($value);
			}
		}

		return $array;
	}

	private function sanitize_list($list)
	{
		if (strpos($list, ';') === false && !filter_var(str_replace('*', '0', $list), \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
			return;
		}

		$items = [];
		$parts = explode(';', $list);

		sort($parts);

		foreach ($parts as $part) {
			if (strpos($part, '/') !== false) {
				list($ip, $range) = explode('/', $part);

				// Skip invalid IP address
				if (!filter_var($ip, \FILTER_VALIDATE_IP)) {
					continue;
				}

				// Invalid IPv4 range
				if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) && ((int) $range < 1 || (int) $range > 32)) {
					continue;
				}

				// Invalid IPv6 range
				if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) && ((int) $range < 1 || (int) $range > 128)) {
					continue;
				}
			} elseif (!filter_var(str_replace('*', '0', $part), \FILTER_VALIDATE_IP)) {
				continue;
			}

			$items[] = $part;
		}

		return implode(';', $items);
	}

	private function create_table()
	{
		$this->wpdb_query('
		CREATE TABLE IF NOT EXISTS ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_log (
			`log_id` INT(11) NOT NULL AUTO_INCREMENT,
			`ip_address` VARCHAR(39) NOT NULL COLLATE \'utf8_bin\',
			`country_code` CHAR(2) NOT NULL COLLATE \'utf8_bin\',
			`side` CHAR(1) NOT NULL COLLATE \'utf8_bin\',
			`page` VARCHAR(100) NOT NULL COLLATE \'utf8_bin\',
			`date_created` DATETIME NOT NULL,
			PRIMARY KEY (`log_id`),
			INDEX `idx_country_code` (`country_code`),
			INDEX `idx_side` (`side`),
			INDEX `idx_date_created` (`date_created`),
			INDEX `idx_ip_address` (`ip_address`)
		) COLLATE=\'utf8_bin\'');

		$this->wpdb_query('
		CREATE TABLE IF NOT EXISTS ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_frontend_rate_limit_log (
			`log_id` INT(11) NOT NULL AUTO_INCREMENT,
			`ip_address` VARCHAR(39) NOT NULL COLLATE \'utf8_bin\',
			`date_created` DATETIME NOT NULL,
			PRIMARY KEY (`log_id`),
			INDEX `idx_date_created` (`date_created`),
			INDEX `idx_ip_address` (`ip_address`)
		) COLLATE=\'utf8_bin\'');

		$this->wpdb_query('
		CREATE TABLE IF NOT EXISTS ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_backend_rate_limit_log (
			`log_id` INT(11) NOT NULL AUTO_INCREMENT,
			`ip_address` VARCHAR(39) NOT NULL COLLATE \'utf8_bin\',
			`date_created` DATETIME NOT NULL,
			PRIMARY KEY (`log_id`),
			INDEX `idx_date_created` (`date_created`),
			INDEX `idx_ip_address` (`ip_address`)
		) COLLATE=\'utf8_bin\'');

		$this->wpdb_query('DROP TABLE IF EXISTS ' . $GLOBALS['wpdb']->prefix . 'ip2location_country_blocker_rate_limit_log');
	}

	private function cidr_match($ip, $cidr)
	{
		list($subnet, $mask) = explode('/', $cidr);

		if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) && filter_var($subnet, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
			return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet);
		} elseif (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) && filter_var($subnet, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
			$ip = inet_pton($ip);
			$subnet = inet_pton($subnet);

			$binMask = str_repeat('f', round($mask / 4));
			switch ($mask % 4) {
				case 0:
					break;
				case 1:
					$binMask .= '8';
					break;
				case 2:
					$binMask .= 'c';
					break;
				case 3:
					$binMask .= 'e';
					break;
			}

			$binMask = str_pad($binMask, 32, '0');
			$binMask = pack('H*', $binMask);

			return ($ip & $binMask) == $subnet;
		}

		return false;
	}

	private function display_bytes($bytes)
	{
		$ext = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

		$index = 0;
		for (; $bytes > 1024; ++$index) {
			$bytes /= 1024;
		}

		return number_format((float) $bytes, 0, '.', ',') . ' ' . $ext[$index];
	}
}
