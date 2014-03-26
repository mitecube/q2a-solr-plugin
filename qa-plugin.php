<?php
/**
 * Created by JetBrains PhpStorm.
 * User: fdellutri
 * Date: 19/04/13
 * Time: 10:41
 * To change this template use File | Settings | File Templates.
 */

/*
	Plugin Name: Solr Integration
	Plugin URI:
	Plugin Description: Replaces search engine with Apache Solr
	Plugin Version: 1.0
	Plugin Date: 2013-04-19
	Plugin Author: Fabio Dellutri
	Plugin Author URI: http://www.mitecube.com/
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI:
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

qa_register_plugin_module('search', 'qa_solr_search.php', 'qa_solr_search', 'Solr Integration');