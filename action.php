<?php
/**
 * RawDataSql Action Plugin
 *
 *  Provides table sort to rawdatasql plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'action.php';

class action_plugin_sqlraw extends DokuWiki_Action_Plugin {

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'sqlraw_hookjs');
    }

    /**
     * Hook js script into page headers.
     */
    function sqlraw_hookjs(&$event, $param) {
        global $INFO;
        global $ID;  
        $key = 'keywords';       
        $metadata = p_get_metadata($ID, $key, false);
        
        // keyword sqlSort used to include sort javascript files
         if (strpos($metadata, 'sqlRawSort') !== false) {
            $event->data['script'][] = array(
                            'type'    => 'text/javascript',
                            'charset' => 'utf-8',
                            '_data'   => '',
                            'src'     => DOKU_BASE."lib/plugins/sqlraw/sorttable.min.js");
       }
    }
}