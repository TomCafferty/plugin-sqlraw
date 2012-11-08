<?php
/**
 * Plugin SQLRAW:  executes SQL queries on data not in a database
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Tom Cafferty <tcafferty@glocalfocal.com>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/parserutils.php');

function pullInWikiPage ($dokuPageId) {

if ($_SERVER['SERVER_NAME'] == 'localhost') {
    define('DOC_ROOT_OUT', $_SERVER['DOCUMENT_ROOT'] . '/glocalfocalwiki');
} else {
    define('DOC_ROOT_OUT', $_SERVER['DOCUMENT_ROOT']);
} 
$link_prefix = DOKU_URL . 'dokuwiki_integrated/doc.php?id=';

$dokuwiki_folder =  DOC_ROOT_OUT;
if (auth_quickaclcheck($dokuPageId) == 0)
  return false;

/* Initialization */

define('DOKU_PATH', $dokuwiki_folder);
define('DOKU_INC', DOKU_PATH . '/');
define('DOKU_CONF', DOKU_PATH . '/conf/');

global $conf;
$conf['datadir'] = DOKU_PATH . '/data';
$conf['cachedir'] = DOKU_PATH . '/data/cache';
$conf['mediadir'] = DOKU_PATH . '/data/media';
$conf['metadir'] = DOKU_PATH . '/data/meta';
$conf['maxseclevel'] = 0;	//links to edit sub-content
$conf['target']['extern'] = '';

unset($_REQUEST['purge']); 

require_once (DOKU_PATH . '/inc/parser/parser.php');
require_once DOKU_PATH . '/inc/events.php';
require_once DOKU_PATH . '/inc/mail.php';
require_once DOKU_PATH . '/inc/cache.php';

require_once DOKU_PATH . '/inc/pageutils.php';
require_once DOKU_PATH . '/inc/io.php';
require_once DOKU_PATH . '/inc/confutils.php';
require_once DOKU_PATH . '/inc/init.php';

// from id parameter, build text file path
$pagePath = DOKU_PATH . '/data/pages/'. str_replace(":", "/", $dokuPageId) . '.txt';

// get cached instructions for that file
$cache = new cache_instructions($dokuPageId, $pagePath); 
if ($cache->useCache()){ 
  $instructions = $cache->retrieveCache(); 
} else{ 
  $instructions = p_get_instructions(io_readfile($pagePath)); 
  $cache->storeCache($instructions); 
} 

// create renderer
require_once DOKU_INC . '/inc/parser/xhtml.php';
require_once DOC_ROOT_OUT . '/dokuwiki_integrated/include/Doku_Renderer_xhtml_export.php';
$renderer = new Doku_Renderer_xhtml_export();

// init renderer
$renderer->set_base_url($link_prefix);
$renderer->smileys = getSmileys();
$renderer->notoc();

// set localizable items
global $lang;
$lang['toc'] = "Table of Contents";
$lang['doublequoteopening']  = '“';
$lang['doublequoteclosing']     = '”';

// instructions processing
$pageTitle = "";

foreach ( $instructions as $instruction ) {
	// get first level 1 header (optional)
	if ($pageTitle == "" && $instruction[0] == "header" && $instruction[1][1] == 1)
		$pageTitle = $instruction[1][0];
    // render instruction
	call_user_func_array(array(&$renderer, $instruction[0]),$instruction[1]);
}

// get rendered html
$html = $renderer->doc;

// get metadata infos (optional)
$date_creation = "";
$date_modification = "";
$metadata = p_get_metadata($dokuPageId);
if (isset($metadata)) {
	if (isset($metadata['date'])) {
		$metadata_date = $metadata['date'];
		if (isset($metadata_date['created'])) {
			$date_creation = date("F j, Y, g:i:s A", $metadata_date['created']);
		}
		if (isset($metadata_date['modified'])) {
			$date_modification =date("F j, Y, g:i:s A", $metadata_date['modified']);
		}
	}
}
return $html;
}
