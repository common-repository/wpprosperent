<?php
/**
 * @package WordPress
 * @subpackage WPProsperent
 *
 * Copyright (c) 2010 WPProsperent.com
 *
 * This file is part of WP Prosperent Plugin
 */

/**
 * WP Prosperent Plugin
 */
class WPProsperent
{
	/**
	 * Plugin version
	 */
	const VERSION = '1.2.3';
	
	/**
	 * The lookup key used to locate the options record in the wp_options table
	 */
	const OPTIONS_KEY = 'wp-prosperent-options';
	
	/**
	 * The hook to be used in all variable actions and filters
	 */
	const HOOK = 'wp-prosperent';
	
	/**
	 * The maximum number of products
	 */
	const LIMIT_MAX = 500;
	
	/**
	 * The default number of products on page
	 */
	const LIMIT_PAGE = 20;
	
	/**
	 * Grid: the default number of columns
	 */
	const GRID_COLUMNS_DEFAULT = 4;
	
	/**
	 * Grid: the maximum number of columns
	 */
	const GRID_COLUMNS_MAX = 10;
	
	/**
	 * Singleton instance
	 * @var WPProsperent
	 */
	protected static $_instance = null;
	
	/**
	 * An instance of the options structure containing all options for this plugin
	 * @var WPProsperent_Options
	 */
	protected $_options = null;
	
	/**
	 * View object
	 * @var WPProsperent_View
	 */
	protected $_view = null;
	
	/**
	 * Singleton pattern implementation makes "new" unavailable
	 *
	 * @return void
	 */
	protected function __construct()
	{}
	
	/**
	 * Singleton pattern implementation makes "clone" unavailable
	 *
	 * @return void
	 */
	protected function __clone()
	{}
	
	/**
	 * Returns an instance of WP Prosperent Plugin
	 *
	 * Singleton pattern implementation
	 *
	 * @return WPProsperent
	 */
	public static function getInstance()
	{
		if (null === self::$_instance)
		{
			self::$_instance = new self();
			self::$_instance->_options = new WPProsperent_Options(self::OPTIONS_KEY);
			self::$_instance->_view = new WPProsperent_View();
		}
		
		return self::$_instance;
	}
	
	/**
	 * Initializes singleton instance and assigns hooks callbacks
	 *
	 * @param string $bootstrapFile The full path to the plugin bootstrap file
	 * @return WPProsperent
	 */
	public static function init($bootstrapFile)
	{
		$instance = self::getInstance();
		
		// Activation
		register_activation_hook($bootstrapFile, array($instance, 'activate'));
		
		// Backend hooks and action callbacks
		if (is_admin())
		{
			add_action('admin_menu', array($instance, 'registerOptionsPages'));
			add_action('admin_menu', array($instance, 'registerShortcodeConstructor'));
			add_action(self::HOOK . '_saveGeneralOptions', array($instance, 'saveGeneralOptions'));
			add_action('in_admin_footer', array($instance, 'renderAdminFooter'));
		}
		// Frontend hooks and action callbacks
		else
		{
			add_action('init', array($instance, 'enqueueFrontendScriptsAndStyles'));
			add_action('wp_head', array($instance, 'renderCustomStyles'));
			
			// Link cloaking
			add_action('template_redirect', array($instance, 'redirectProsperentLink'));
			
			// Shortcode
			add_shortcode('wpp', array($instance, 'processShortcode'));
		}
		
		return $instance;
	}
	
	/**
	 * Performs installation action if necessary
	 *
	 * @return void
	 */
	public function activate()
	{
		$installedVersion = $this->_options->getOption('version');
		
		// Not installed?
		if (null === $installedVersion)
		{
			$this->_install();
		}
	}
	
	/**
	 * Performs installation when plugin is activated for the first time
	 *
	 * @return void
	 */
	protected function _install()
	{
		$defaults = array (
			'api_confirmed' => false,
			'api_key' => null,
			'channel_id' => null,
			'debug_mode' => false,
			'keyword' => '',
			'keyword_use_search_referrer' => true,
			'keyword_use_title' => true,
			'keyword_use_title_as_backup' => false,
			'keyword_append_global' => false,
			'template' => 'list',
			'grid_columns' => self::GRID_COLUMNS_DEFAULT,
			'use_replace_price' => false,
			'replace_price_text' => '',
			'use_pagination' => false,
			'limit_page' => self::LIMIT_PAGE,
			'custom_css' => '/* Custom Template */
#wpp-products ul.custom a {
	color: #f00;
}

#wpp-products ul.custom li {
	border-bottom: 1px solid #ddd;
	padding: 0 0 20px;
	margin-top: 20px;
}',
			'custom_template' => '<div><strong>Affiliate:</strong> <a href="{affiliate_url}">{affiliate_url}</a></div>
<div><strong>Image:</strong> <img src="{image_url}" alt="" /></div>
<div><strong>Thumb:</strong> <img src="{image_thumb_url}" alt="" /></div>
<div><strong>Keyword:</strong> {keyword}</div>
<div><strong>Description:</strong> <p>{description}</p></div>
<div><strong>Category:</strong> {category}</div>
<div><strong>Price:</strong> {price}</div>
<div><strong>Sale:</strong> {price_sale}</div>
<div><strong>Currency:</strong> {currency}</div>
<div><strong>Merchant:</strong> {merchant}</div>
<div><strong>Brand:</strong> {brand}</div>
<div><strong>UPC:</strong> {upc}</div>
<div><strong>ISBN:</strong> {isbn}</div>
<div><strong>Sales:</strong> {sales}</div>',
			'link_no_follow' => true,
			'link_new_page' => true,
			'link_use_cloaking' => false,
			'link_cloak_dir' => 'store',
			'version' => self::VERSION
		);
		
		foreach ($defaults as $option => $value)
		{
			$this->_options->setOption($option, $value);
		}
		
		$this->_options->save();
	}
	
	/**
	 * Adds menu navigation items for this plugin
	 *
	 * @return void
	 */
	public function registerOptionsPages()
	{
		$groupFile = self::HOOK . '_general-options';
		add_object_page('WP Prosperent Plugin Options', 'WP Prosperent', 'manage_options', $groupFile, array($this, 'renderGeneralOptions'));
		
		// Options
		$page = add_submenu_page($groupFile, 'General Options', 'General Options', 'manage_options', $groupFile, array($this, 'renderGeneralOptions'));
		add_action('admin_print_scripts-' . $page, array($this, 'enqueueAdminScripts'));
		add_action('admin_print_styles-' . $page, array($this, 'enqueueAdminStyles'));
	}
	
	/**
	 * Shortcode constructor metabox & scripts
	 * 
	 * @return void
	 */
	public function registerShortcodeConstructor()
	{
		// Metabox
		add_meta_box('wpProsperentShortcode', 'Generate WP Prosperent Token', array($this, 'renderShortcodeMetabox'), 'post', 'normal', 'high');
		add_meta_box('wpProsperentShortcode', 'Generate WP Prosperent Token', array($this, 'renderShortcodeMetabox'), 'page', 'normal', 'high');
		
		// Scripts & styles
		add_action('admin_print_scripts', array($this, 'enqueueShortcodeScripts'));
		add_action('admin_print_styles', array($this, 'enqueueShortcodeStyles'));
	}
	
	/**
	 * Enqueues any Javascript needed for this plugin
	 *
	 * @return void
	 */
	public function enqueueAdminScripts()
	{
		wp_enqueue_script('postbox');
		wp_enqueue_script('dashboard');
		wp_enqueue_script('jquery-ui-draggable');
		wp_enqueue_script('jquery-ui-droppable');
	}
	
	/**
	 * Enqueues Shortcode constructor scripts
	 * 
	 * @return void
	 */
	public function enqueueShortcodeScripts()
	{
		if (!empty($GLOBALS['editing']))
		{
			$pluginDirUrl = $this->_getPluginDirUrl();
			wp_enqueue_script(self::HOOK . '-admin-js', $pluginDirUrl . '/js/wp-prosperent-admin.js', array('jquery'), self::VERSION);
		}
	}
	
	/**
	 * Enqueues Shortcode constructor styles
	 * 
	 * @return void
	 */
	public function enqueueShortcodeStyles()
	{
		if (!empty($GLOBALS['editing']))
		{
			$pluginDirUrl = $this->_getPluginDirUrl();
			wp_enqueue_style(self::HOOK . '-admin-css', $pluginDirUrl . '/css/wp-prosperent-admin.css');
		}
	}
	
	/**
	 * Enqueues CSS files required for this plugin
	 *
	 * @return void
	 */
	public function enqueueAdminStyles()
	{
		$pluginDirUrl = $this->_getPluginDirUrl();
		wp_enqueue_style(self::HOOK . '-admin-css', $pluginDirUrl . '/css/wp-prosperent-options.css');
	}
	
	/**
	 * Enqueues any Javascript and CSS files required for this plugin
	 *
	 * @return void
	 */
	public function enqueueFrontendScriptsAndStyles()
	{
		$pluginDirUrl = $this->_getPluginDirUrl();
		wp_enqueue_style(self::HOOK . '-css', $pluginDirUrl . '/css/wp-prosperent.css');
		wp_enqueue_script(self::HOOK . '-js', $pluginDirUrl . '/js/wp-prosperent.js', array('jquery'), self::VERSION);
	}
	
	/**
	 * Renders the custom CSS
	 * 
	 * @return void
	 */
	public function renderCustomStyles()
	{
		$customCss = $this->_options->getOption('custom_css');
		
		if (strlen($customCss))
		{
			$this->_view
				->setTemplate('head-custom-css')
				->assign('gridColumns', $this->_options->getOption('grid_columns'))
				->assign('customCss', $customCss)
				->render();
		}
	}
	
	/**
	 * Save the general options
	 *
	 * @return void
	 */
	public function saveGeneralOptions()
	{
		if (!isset($_POST['action']) || 'save' != $_POST['action'])
		{
			return;
		}
		
		// Protection
		check_admin_referer(self::OPTIONS_KEY);
		
		if (!current_user_can('manage_options'))
		{
			exit('You cannot change WP Prosperent Plugin options.');
		}
		
		// Check if API key has changed
		$apiKey = filter_input(INPUT_POST, 'api_key');
		
		if (strcmp($apiKey, $this->_options->getOption('api_key')) != 0)
		{
			// Check if API key is valid
			$apiCheckResult = WPProsperent_ApiWrapper::checkApiKey($apiKey);
			
			if ($apiCheckResult === true)
			{
				$this->_options->setOption('api_confirmed', true);
			}
			else
			{
				$this->_options->setOption('api_confirmed', false);
				$this->_view->messageHelper('API Response: ' . $apiCheckResult, 'error');
			}
		}
		
		// Prepare options values
		$channelId = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
		$debugMode = (bool) filter_input(INPUT_POST, 'debug_mode', FILTER_VALIDATE_BOOLEAN);
		
		$keyword = filter_input(INPUT_POST, 'keyword');
		$keywordUseSearchReferrer = (bool) filter_input(INPUT_POST, 'keyword_use_search_referrer', FILTER_VALIDATE_BOOLEAN);
		$keywordUseTitle = (bool) filter_input(INPUT_POST, 'keyword_use_title', FILTER_VALIDATE_BOOLEAN);
		$keywordUseTitleAsBackup = (bool) filter_input(INPUT_POST, 'keyword_use_title_as_backup', FILTER_VALIDATE_BOOLEAN);
		$keywordAppendGlobal = (bool) filter_input(INPUT_POST, 'keyword_append_global', FILTER_VALIDATE_BOOLEAN);
		
		$template = filter_input(INPUT_POST, 'template');
		$gridColumns = filter_input(INPUT_POST, 'grid_columns', FILTER_VALIDATE_INT, array (
			'options' => array (
				'default' => self::GRID_COLUMNS_DEFAULT, 
				'min_range' => 1,
				'max_range' => self::GRID_COLUMNS_MAX
			)
		));
		$useReplacePrice = (bool) filter_input(INPUT_POST, 'use_replace_price', FILTER_VALIDATE_BOOLEAN);
		$replacePriceText = filter_input(INPUT_POST, 'replace_price_text', FILTER_SANITIZE_STRING);
		$customCss = filter_input(INPUT_POST, 'custom_css', FILTER_SANITIZE_STRING);
		$custom_template = filter_input(INPUT_POST, 'custom_template');
		
		$usePagination = (bool) filter_input(INPUT_POST, 'use_pagination', FILTER_VALIDATE_BOOLEAN);
		$limitPage = filter_input(INPUT_POST, 'limit_page', FILTER_VALIDATE_INT, array (
			'options' => array (
				'default' => self::LIMIT_PAGE, 
				'min_range' => 1,
				'max_range' => self::LIMIT_MAX
			)
		));
		
		$linkNoFollow = (bool) filter_input(INPUT_POST, 'link_no_follow', FILTER_VALIDATE_BOOLEAN);
		$linkNewPage = (bool) filter_input(INPUT_POST, 'link_new_page', FILTER_VALIDATE_BOOLEAN);
		$linkUseCloaking = (bool) filter_input(INPUT_POST, 'link_use_cloaking', FILTER_VALIDATE_BOOLEAN);
		$linkCloakDir = preg_replace('/[^a-zA-Z0-9\-_]/', '', filter_input(INPUT_POST, 'link_cloak_dir'));
		
		// Save options
		$this->_options
			->setOption('api_key', $apiKey)
			->setOption('channel_id', $channelId)
			->setOption('debug_mode', $debugMode)
			->setOption('keyword', $keyword)
			->setOption('keyword_use_search_referrer', $keywordUseSearchReferrer)
			->setOption('keyword_use_title', $keywordUseTitle)
			->setOption('keyword_use_title_as_backup', $keywordUseTitleAsBackup)
			->setOption('keyword_append_global', $keywordAppendGlobal)
			->setOption('template', $template)
			->setOption('grid_columns', $gridColumns)
			->setOption('use_replace_price', $useReplacePrice)
			->setOption('replace_price_text', $replacePriceText)
			->setOption('use_pagination', $usePagination)
			->setOption('limit_page', $limitPage)
			->setOption('custom_css', $customCss)
			->setOption('custom_template', $custom_template)
			->setOption('link_no_follow', $linkNoFollow)
			->setOption('link_new_page', $linkNewPage)
			->setOption('link_use_cloaking', $linkUseCloaking)
			->setOption('link_cloak_dir', $linkCloakDir)
			->save();
		
		// Render the message
		$this->_view->messageHelper('Options have been saved.');
	}
	
	/**
	 * Renders the general options page.
	 * Fires saveGeneralOptions action hook.
	 *
	 * @return void
	 */
	public function renderGeneralOptions()
	{
		do_action(self::HOOK . '_saveGeneralOptions');
		
		// View setup and render
		if (!$this->_options->getOption('api_confirmed'))
		{
			$this->_view->messageHelper('Please provide your Prosperent API key!', 'error');
		}
		
		$this->_view
			->setTemplate('options-page-general')
			->assign('heading', 'General Options')
			->assign('api_key', $this->_options->getOption('api_key'))
			->assign('channel_id', $this->_options->getOption('channel_id'))
			->assign('debug_mode', $this->_options->getOption('debug_mode'))
			->assign('keyword', $this->_options->getOption('keyword'))
			->assign('keyword_use_search_referrer', $this->_options->getOption('keyword_use_search_referrer'))
			->assign('keyword_use_title', $this->_options->getOption('keyword_use_title'))
			->assign('keyword_use_title_as_backup', $this->_options->getOption('keyword_use_title_as_backup'))
			->assign('keyword_append_global', $this->_options->getOption('keyword_append_global'))
			->assign('supportedTemplates', $this->_getSupportedTemplates())
			->assign('template', $this->_options->getOption('template'))
			->assign('supportedGridColumns', $this->_getSupportedGridColumns())
			->assign('grid_columns', $this->_options->getOption('grid_columns'))
			->assign('use_replace_price', $this->_options->getOption('use_replace_price'))
			->assign('replace_price_text', $this->_options->getOption('replace_price_text'))
			->assign('use_pagination', $this->_options->getOption('use_pagination'))
			->assign('limit_page', $this->_options->getOption('limit_page'))
			->assign('custom_css', $this->_options->getOption('custom_css'))
			->assign('custom_template', $this->_options->getOption('custom_template'))
			->assign('link_no_follow', $this->_options->getOption('link_no_follow'))
			->assign('link_new_page', $this->_options->getOption('link_new_page'))
			->assign('link_use_cloaking', $this->_options->getOption('link_use_cloaking'))
			->assign('link_cloak_dir', $this->_options->getOption('link_cloak_dir'))
			->assign('onceAction', self::OPTIONS_KEY)
			->render();
	}
	
	/**
	 * The list of supported templates
	 * 
	 * @return array
	 */
	protected function _getSupportedTemplates()
	{
		$templates = array (
			'list'		=> 'List',
			'grid'		=> 'Grid',
			'classic'	=> 'Classic',
			'custom'	=> 'Custom'
		);
		
		return $templates;
	}
	
	/**
	 * The list of supported grid columns
	 * 
	 * @return array
	 */
	protected function _getSupportedGridColumns()
	{
		$values = range(1, self::GRID_COLUMNS_MAX, 1);
		return array_combine($values, $values);
	}
	
	/**
	 * Renders plugin information into the admin footer
	 *
	 * @return void
	 */
	public function renderAdminFooter()
	{
		$this->_view
			->setTemplate('options-footer')
			->assign('pluginHref', 'http://www.wpprosperent.com/')
			->assign('pluginText', 'WP Prosperent Plugin')
			->assign('pluginVersion', self::VERSION)
			->assign('authorHref', 'http://www.wpprosperent.com/')
			->assign('authorText', 'WPProsperent.com')
			->render();
	}
	
	/**
	 * Renders shortcode metabox
	 * 
	 * @return void
	 */
	public function renderShortcodeMetabox()
	{
		$this->_view
			->setTemplate('shortcode-metabox')
			->assign('channel_id', $this->_options->getOption('channel_id'))
			->assign('debug_mode', $this->_options->getOption('debug_mode'))
			->assign('keyword', $this->_options->getOption('keyword'))
			->assign('keyword_use_search_referrer', $this->_options->getOption('keyword_use_search_referrer'))
			->assign('keyword_use_title', $this->_options->getOption('keyword_use_title'))
			->assign('keyword_use_title_as_backup', $this->_options->getOption('keyword_use_title_as_backup'))
			->assign('keyword_append_global', $this->_options->getOption('keyword_append_global'))
			->assign('supportedTemplates', $this->_getSupportedTemplates())
			->assign('template', $this->_options->getOption('template'))
			->assign('use_replace_price', $this->_options->getOption('use_replace_price'))
			->assign('replace_price_text', $this->_options->getOption('replace_price_text'))
			->assign('use_pagination', $this->_options->getOption('use_pagination'))
			->assign('limit_page', $this->_options->getOption('limit_page'))
			->assign('link_no_follow', $this->_options->getOption('link_no_follow'))
			->assign('link_new_page', $this->_options->getOption('link_new_page'))
			->render();
	}
	
	/**
	 * Returns the base directory of plugin
	 *
	 * @return string
	 */
	protected function _getPluginDir()
	{
		static $pluginDir;
		
		if (empty($pluginDir))
		{
			$pluginDir = plugin_basename(__FILE__);
			$pluginDir = substr($pluginDir, 0, stripos($pluginDir, '/'));
		}
		
		return $pluginDir;
	}
	
	/**
	 * Returns the URL directory path for plugin
	 *
	 * @return string
	 */
	protected function _getPluginDirUrl()
	{
		static $pluginUrl;
		
		if (empty($pluginUrl))
		{
			$pluginDir = $this->_getPluginDir();
			$pluginUrl = plugin_dir_url($pluginDir) . $pluginDir;
		}
		
		return $pluginUrl;
	}
	
	/**
	 * Validates the [wpp] shortcode parameters
	 * 
	 * @param array $atts User defined attributes in shortcode tag
	 * @return array
	 */
	protected function _validateShortcodeAttributes($atts)
	{
		$atts = (array) $atts;
		
		// Restore quotes
		foreach ($atts as $key => $value)
		{
			$atts[$key] = str_replace('{quot}', '"', $value);
		}
		
		// Validate booleans
		$booleans = array (
			'debug_mode',
			'keyword_use_search_referrer',
			'keyword_use_title',
			'keyword_use_title_as_backup',
			'keyword_append_global',
			'use_replace_price',
			'use_pagination',
			'link_no_follow',
			'link_new_page'
		);
		
		foreach ($booleans as $key)
		{
			if (isset($atts[$key]))
			{
				// Replace string values: Yes = true, No = false
				$value = (strcasecmp($atts[$key], 'yes') == 0 || $atts[$key] == '1');
				$atts[$key] = $value;
			}
		}
		
		// Validate advanced options
		$advanced = array (
			'template' => '_getSupportedTemplates'
		);
		
		foreach ($advanced as $key => $method)
		{
			$supported = $this->$method();
			
			if (isset($atts[$key]) && !isset($supported[$atts[$key]]))
			{
				reset($supported);
				$atts[$key] = key($supported);
			}
		}
		
		// Validate multiple templates
		if (isset($atts['templates']))
		{
			$supportedTemplates = $this->_getSupportedTemplates();
			$templates = array();
			
			foreach (explode('|', $atts['templates']) as $template)
			{
				$nameValue = explode(':', $template);
				$name = $nameValue[0];
				$value = (int) (isset($nameValue[1]) ? $nameValue[1] : 1);
				
				if (!isset($supportedTemplates[$name]))
				{
					reset($supportedTemplates);
					$name = key($supportedTemplates);
				}
				
				if (!$value || $value < 1)
				{
					$value = 1;
				}
				
				$templates[$name] = $value;
			}
			
			$atts['templates'] = $templates;
		}
		
		// Validate pagination
		if (isset($atts['limit_page']))
		{
			$atts['limit_page'] = filter_var($atts['limit_page'], FILTER_VALIDATE_INT, array('options' => array(
				'default' => self::LIMIT_PAGE, 
				'min_range' => 1,
				'max_range' => self::LIMIT_MAX
			)));
		}
		
		if (isset($_POST['wpp_page']) && isset($_POST['wpp_post_id']))
		{
			$postId = filter_input(INPUT_POST, 'wpp_post_id', FILTER_VALIDATE_INT);
			if (isset($GLOBALS['post']->ID) && $postId == $GLOBALS['post']->ID)
			{
				$atts['page_number'] = filter_input(INPUT_POST, 'wpp_page', FILTER_VALIDATE_INT, array('options' => array('default' => 1, 'min_range' => 1)));
			}
		}
		
		// Get defaults
		$defaults = array (
			'channel_id' => $this->_options->getOption('channel_id'),
			'debug_mode' => $this->_options->getOption('debug_mode'),
			'keyword' => '',
			'keyword_use_search_referrer' => $this->_options->getOption('keyword_use_search_referrer'),
			'keyword_use_title' => $this->_options->getOption('keyword_use_title'),
			'keyword_use_title_as_backup' => $this->_options->getOption('keyword_use_title_as_backup'),
			'keyword_append_global' => $this->_options->getOption('keyword_append_global'),
			'template' => $this->_options->getOption('template'),
			'templates' => array(),
			'use_replace_price' => $this->_options->getOption('use_replace_price'),
			'replace_price_text' => $this->_options->getOption('replace_price_text'),
			'page_number' => 1,
			'use_pagination' => $this->_options->getOption('use_pagination'),
			'limit_page' => $this->_options->getOption('limit_page'),
			'link_no_follow' => $this->_options->getOption('link_no_follow'),
			'link_new_page' => $this->_options->getOption('link_new_page')
		);
		
		// Process attributes
		return shortcode_atts($defaults, $atts);
	}
	
	/**
	 * Fetches the parsed data from Prosperent API
	 * 
	 * @param array $values
	 * @return mixed
	 */
	protected function _fetchShortcodeData(array $values)
	{
		/*
		 * API request
		 */
		$api = new WPProsperent_ApiWrapper(array(
			'api_key' => $this->_options->getOption('api_key'),
			'debugMode' => $values['debug_mode'],
			'channel_id' => $values['channel_id'],
			'page' => $values['page_number'],
			'limit' => $values['limit_page']
		));
		
		// Query builder
		$data = null;
		$globalKeyword = $this->_options->getOption('keyword');
		
		// Try the SERP query first
		if ($values['keyword_use_search_referrer'])
		{
			$additionalKeyword = ($values['keyword_append_global']) ? $globalKeyword : '';
			$data = $api->fetchDataFromSerp($additionalKeyword);
		}
		
		// Try the custom query
		if (!$data && strlen($values['keyword']))
		{
			$keyword = $values['keyword'];
			
			if ($values['keyword_append_global'])
			{
				$keyword .= ' ' . $globalKeyword;
			}
			
			$data = $api->fetchData($keyword);
		}
		
		// Try the post title
		if (!$data && !empty($GLOBALS['post']->post_title) && ($values['keyword_use_title'] || $values['keyword_use_title_as_backup']))
		{
			$keyword = $GLOBALS['post']->post_title;
			
			if ($values['keyword_append_global'])
			{
				$keyword .= ' ' . $globalKeyword;
			}
			
			$data = $api->fetchData($keyword);			
		}
		
		// Try the global / site wide query
		if (!$data && strlen($globalKeyword))
		{
			$data = $api->fetchData($globalKeyword);
		}
		
		
		return $data;
	}
	
	/**
	 * Cloaks the affiliate urls
	 * 
	 * @param array $data
	 * @return array
	 */
	protected function _cloakAffiliateUrls(array $data)
	{
		$useCloaking = $this->_options->getOption('link_use_cloaking');
		$cloakDir = $this->_options->getOption('link_cloak_dir');
		
		if ($useCloaking && $cloakDir)
		{
			$baseUrl = trailingslashit(get_bloginfo('url')) . $cloakDir;
			
			foreach ($data as $key => $row)
			{
				$affiliateUrl = $baseUrl . str_replace('http://prosperent.com/store', '', $row['affiliate_url']);
				$data[$key]['affiliate_url'] = $affiliateUrl;
			}	
		}

		return $data;
	}
	
	/**
	 * Processes the [wpp] shortcode
	 * 
	 * @param array $atts User defined attributes in shortcode tag
	 * @return string Processed shortcode string
	 */
	public function processShortcode($atts)
	{
		// Prepare
		$values = $this->_validateShortcodeAttributes($atts);
		$data = $this->_fetchShortcodeData($values);
		
		// Render
		$renderedDebugInfo = $this->_view
			->setTemplate('shortcode-debug-info')
			->assign('debug_mode', $values['debug_mode'])
			->assign('values', $values)
			->fetch();
		
		if (!empty($data) && is_array($data))
		{
			$items = $this->_cloakAffiliateUrls($data['data']);
			
			$linkAttributes = $this->_view
				->setTemplate('shortcode-link-attributes')
				->assign('link_no_follow', $values['link_no_follow'])
				->assign('link_new_page', $values['link_new_page'])
				->fetch();
			
			// Single template
			if (empty($values['templates']))
			{
				$renderedTemplate = $this->_view
					->setTemplate('shortcode-template-' . $values['template'])
					->assign('baseUrl', $this->_getPluginDirUrl())
					->assign('linkAttributes', $linkAttributes)
					->assign('gridColumns', $this->_options->getOption('grid_columns'))
					->assign('customTemplate', $this->_options->getOption('custom_template'))
					->assign('useReplacePrice', $values['use_replace_price'])
					->assign('replacePriceText', $values['replace_price_text'])
					->assign('data', $items)
					->fetch();
			}
			// Multiple templtes
			else
			{
				$renderedTemplate = '';
				
				foreach ($values['templates'] as $template => $itemsNumber)
				{
					$templateItems = array_splice($items, 0, $itemsNumber);
					
					$renderedTemplate .= $this->_view
						->setTemplate('shortcode-template-' . $template)
						->assign('baseUrl', $this->_getPluginDirUrl())
						->assign('linkAttributes', $linkAttributes)
						->assign('gridColumns', $this->_options->getOption('grid_columns'))
						->assign('customTemplate', $this->_options->getOption('custom_template'))
						->assign('useReplacePrice', $values['use_replace_price'])
						->assign('replacePriceText', $values['replace_price_text'])
						->assign('data', $templateItems)
						->fetch();
				}
			}
			
			if ($values['use_pagination'])
			{
				$renderedPagination = $this->_view
					->setTemplate('shortcode-pagination')
					->assign('pageNumber', $values['page_number'])
					->assign('totalRecords', $data['totalRecords'])
					->assign('itemsPerPage', $values['limit_page'])
					->fetch();
			}
			else
			{
				$renderedPagination = '';
			}
			
			$result = $renderedDebugInfo . $renderedTemplate . $renderedPagination;
		}
		else
		{
			$result = $renderedDebugInfo;
		}
		
		return $result;
	}
	
	/**
	 * Performs redirect for masked urls
	 * 
	 * @return void
	 */
	public function redirectProsperentLink()
	{
		if (is_404() && '' != $_SERVER['REQUEST_URI'])
		{
			$useCloaking = $this->_options->getOption('link_use_cloaking');
			$cloakDir = $this->_options->getOption('link_cloak_dir');
			
			if ($useCloaking && $cloakDir)
			{
				$requestUrl = $_SERVER['REQUEST_URI'];
				$baseUrl = '/' . $cloakDir . '/';
				
				// Test url
				if (strpos($requestUrl, $baseUrl) === 0)
				{
					$location = 'http://prosperent.com/store/' . substr($requestUrl, strlen($baseUrl));
					wp_redirect($location, 302);
					exit;
				}
			}
		}
	}
}