<?php
/**
 * Plugin Name: WP Prosperent Plugin
 * Plugin URI:  http://www.wpprosperent.com/
 * Description: WP Prosperent Plugin plugin for WordPress.
 * Version:     1.2.3
 * Author:      WPProsperent.com
 * Author URI:  http://www.wpprosperent.com/
 *
 * Copyright (c) 2010 WPProsperent.com
 */

require_once 'library/Prosperent/Api.php';
require_once 'library/WPProsperent/ApiWrapper.php';
require_once 'library/WPProsperent/Exception.php';
require_once 'library/WPProsperent/Options.php';
require_once 'library/WPProsperent/View.php';
require_once 'library/WPProsperent.php';

WPProsperent::init(__FILE__);