<?php
/**
 * Plugin SQLRAW:  executes SQL queries on data not in a database
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Slim Amamou <slim.amamou@gmail.com>
 * @author     Tom Cafferty <tcafferty@glocalfocal.com>
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once('DB.php');
require_once( DOKU_INC.'lib/plugins/sqlraw/curl_http_client.php');
require_once('wikiPage.php');
    
function propertyRaw($prop, $xml) {
	$pattern = $prop ."='([^']*)'";
	if (ereg($pattern, $xml, $matches)) {
		return $matches[1];
	}
	$pattern = $prop .'="([^"]*)"';
	if (ereg($pattern, $xml, $matches)) {
		return $matches[1];
	}
    return FALSE;
}

function scrapeTable($url, $startMarker, $dbfile, $specialChars, $specialReplace, $restrictNames) {
    $csv_data = '';
    if(preg_match('/^(http|https)?:\/\//i',$url)){
        $raw = file_get_contents($url);
    } else {
        $raw = pullInWikiPage($url);
        if ($raw == false) 
            return false;
    }
    $newlines = array("\t","\n","\r","\x20\x20","\0","\x0B");
    $spaceCodes = array("&nbsp;","<br />");
    $numberStuff = array(",","+");

    $numbs = array(",",".","+","-","0","1","2","3","4","5","6","7","8","9");
    $content = str_replace($newlines, "", $raw);
    $content = str_replace($spaceCodes, "_", ($content));
    $content = preg_replace("/&#?[a-z0-9]+;/i","",$content);

    if ($dbfile != '') 
      $debug = TRUE;
    else
      $debug = FALSE;
       
    if ($startMarker != '') {
        $start = strpos($content,$startMarker);
        $content = substr($content,$start);
    }

    $start = strpos($content,'<table ');
    $end = strpos($content,'</table>',$start) + 8;
    $table = substr($content,$start,$end-$start);
   
    preg_match_all("|<tr(.*)</tr>|U",$table,$rows);
    
    if ($debug == TRUE) 
      $fp = fopen($dbfile, 'w');
    $row_index=0;
    $numHeadings = 0;
    foreach ($rows[0] as $row){
        if ($restrictNames && ($row_index==0)) $row = str_replace($specialChars, $specialReplace, $row);
        if (strpos($row,'<th')===false)  
          preg_match_all("|<td(.*)</td>|U",$row,$cells);
        else {
		  $numHeadings = preg_match_all("|<t(.*)</t(.*)>|U",$row,$cells);
        }

    	if ($row_index == 0) 
    	  $numCols = $numHeadings;
		  
		$cell_index=0;
		foreach ($cells[0] as $cell) {
            $test = strip_tags(trim(str_replace($numbs, "", $cell)));
    		if (strlen($test)==0)
    		  $cell = str_replace($numberStuff, '', $cell);
  		  
    		$mycells[$row_index][$cell_index] = trim(strip_tags($cell));
    		++$cell_index;
    	}
    	if ($mycells[$row_index] != '') {
        	if ($debug == TRUE) 
        	  fputcsv($fp, $mycells[$row_index]);
        	$csv_data .= strputcsv($mycells[$row_index], $numCols-1);
        }
    	++$row_index;
    }
    if ($debug == TRUE) 
      fclose($fp);
    return $csv_data;
}

    function strputcsv($fields = array(), $numheadings, $delimiter = ',', $enclosure = '"') {
        $i = 0;
        $csvline = '';
        $escape_char = '\\';
        $field_cnt = count($fields)-1;
        $enc_is_quote = in_array($enclosure, array('"',"'"));
        reset($fields);

        foreach( $fields AS $field ) {
            /* enclose a field that contains a delimiter, an enclosure character, or a newline */
            if( is_string($field) && (
                strpos($field, $delimiter)!==false ||
                strpos($field, $enclosure)!==false ||
                strpos($field, $escape_char)!==false ||
                strpos($field, "\n")!==false ||
                strpos($field, "\r")!==false ||
                strpos($field, "\t")!==false ||
                strpos($field, ' ')!==false ) ) {

                $field_len = strlen($field);
                $escaped = 0;
                $csvline .= $enclosure;
                for( $ch = 0; $ch < $field_len; $ch++ )    {
                    if( $field[$ch] == $escape_char && $field[$ch+1] == $enclosure && $enc_is_quote ) {
                        continue;
                    }elseif( $field[$ch] == $escape_char ) {
                        $escaped = 1;
                    }elseif( !$escaped && $field[$ch] == $enclosure ) {
                        $csvline .= $enclosure;
                    }else{
                        $escaped = 0;
                    }
                    $csvline .= $field[$ch];
                }
                $csvline .= $enclosure;
            } else {
                $csvline .= $field;
            }
            if( $i++ != $field_cnt ) {
                $csvline .= $delimiter;
            }
        }
		if ($field_cnt < $numheadings) {
    		for ($i=$field_cnt+1; $i<=$numheadings;  $i++) {
        		$csvline .= $delimiter;
    		}
		}
        $csvline .= "\n";
        return $csvline;
    }
   
    function sqlRaw__handleLink($url, $source='csvfile', $startMarker, $dbfile, $disallow, $use, $restrictNames){
        global $ID;
        $delim = ',';
        $opt = array('content' => '');
        if ($source == 'csvfile') {
            if(preg_match('/^(http|https)?:\/\//i',$url)){
                // load file data
                $curl = new Curl_HTTP_Client();
                $useragent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
                $curl->set_user_agent($useragent);
                $opt['content'] = $curl->fetch_url($url);
            } else {
                $opt['file'] = cleanID($url);
                if(!strlen(getNS($opt['file'])))
                      $opt['file'] = $INFO['namespace'].':'.$opt['file'];
                $renderer->info['cache'] = false; //no caching
                if (auth_quickaclcheck(getNS($opt['file']).':*') < AUTH_READ) {
                    $renderer->cdata('Access denied to CSV data');
                    return true;
                } else {
                    $file = mediaFN($opt['file']);
                    $opt['content'] = io_readFile($file);
                    // if not valid UTF-8 is given we assume ISO-8859-1
                    if(!utf8_check($opt['content'])) $opt['content'] = utf8_encode($opt['content']);
                }
            }
            if(!$opt['content']){
                printf("Failed to fetch remote CSV data.\n");
                return true;
            }
            $content =& $opt['content'];
        } elseif ($source == 'scrapeUrl') {
            $content =& scrapeTable(strtolower($url), $startMarker, $dbfile, $disallow, $use, $restrictNames);
            if ($content == false) {
                msg("You do not have permission to access the requested page of ".$url."\n",-1);
                return false;
            }
        } else {
            msg("No valid source url provided.\n");
            return false;
        }

        // clear any trailing or leading empty lines from the data set
        $content = preg_replace("/[\r\n]*$/","",$content);

        $content = preg_replace("/^\s*[\r\n]*/","",$content);
        if(!trim($content)){
            printf("No csv data found.\n");
            return false;
        }
       
        // get each row
        $rows = array();
        $maxcol=0;
        $maxrow=0;
        while($content != "") {
          $thisrow = sqlRaw__csv_explode_row($content,$delim);
          if($maxcol < count($thisrow))
              $maxcol = count($thisrow);
          array_push($rows, $thisrow);
          $maxrow++;
        }
        
        // process headers and determine max field sizes
        $row = 1;
        foreach($rows as $fields) {
    	  if ($row === 1) {
    		foreach ($fields as $field) {
        		if ($restrictNames) $field = str_replace($disallow, $use, $field);
    			$headers[] = strtolower(str_ireplace(' ', '_', $field));
    		}
		  } else {
			foreach ($fields as $key=>$value) {
				if (!isset($max_field_lengths[$key])) {
					$max_field_lengths[$key] = 0;
				}			
				if (strlen($value) > $max_field_lengths[$key]) {
					$max_field_lengths[$key] = strlen($value);
				}
				$field++;
			}
		  }
		  $row++;
        }
        $myResult['headers'] = $headers;
        $myResult['rows'] = $rows;
        $myResult['lengths'] = $max_field_lengths;
        return $myResult;
    }
    
    // Explode CSV string, consuming it as we go
    // Careful, there could be both embedded newlines, commas and quotes
    // One thing to remember is that a row must end with a newline
    function sqlRaw__csv_explode_row(&$str, $delim = ',', $qual = "\"") {
        $len = strlen($str);
        $inside = false;
        $word = '';
        for ($i = 0; $i < $len; ++$i) {
            $next = $i+1;
            if ($str[$i]==$delim && !$inside) {
                $out[] = $word;
                $word = '';
            } elseif ($str[$i] == $qual && (!$inside || $next == $len || $str[$next] == $delim || $str[$next] == "\r" || $str[$next] == "\n")) {
                $inside = !$inside;
            } elseif ($str[$i] == $qual && $next != $len && $str[$next] == $qual) {
                $word .= $str[$i];
                $i++;
            } elseif ($str[$i] == "\n") {
                if ($inside) {
                    $word .= '\\\\';
                } else {
                    $str = substr($str, $next);
                    $out[] = $word;
                    return $out;
                }
            } else {
                $word .= $str[$i];
            }
        }
        $str = substr($str, $next);
        $out[] = $word;
        return $out;
    }
    
    function sqlRaw__drop_temp_db($database) {
        $table = 'temptable';     
        // Drop the table      
        $query = 'DROP TEMPORARY TABLE IF EXISTS '.$table;
        $result =& $database->query ($query);
        if (DB::isError ($result)) {
			$renderer->doc .= '<div class="error">DROP TABLE failed for query: '. $query .'the error: '. $result->getMessage() .'</div>';
            return false;
        }
        return true;
    }
       
    function sqlRaw__create_temp_db($database,$headers,$rows,$max_field_lengths) {
        $badChars = array(".", ":", "-");
        $table = 'temptable';
        
        // Create the table      
        $query = 'CREATE TEMPORARY TABLE '.$table . ' (id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,';
        foreach ($headers as $key=>$header) {
            $query .= ''. str_replace($badChars,'_',trim($header)).' VARCHAR('.$max_field_lengths[$key].') NOT NULL COMMENT \'#db_Filter\',';
        }
        $query .= 'PRIMARY KEY (id)) DEFAULT CHARACTER SET \'utf8\'';
        $result =& $database->query ($query);
        if (DB::isError ($result)) {
			$renderer->doc .= '<div class="error">CREATE TABLE failed for query: '. $query .'the error: '. $result->getMessage() .'</div>';
            return false;
        }
        
        // Insert the records
        $row = 1;
        foreach($rows as $fields) {
    	  if ($row !== 1) {
			$sql = 'INSERT INTO `'.$table.'` VALUES(null, ';
			foreach ($fields as $field) {
				$sql .= '\''.$database->escapeSimple($field).'\', ';
	        }
			$sql = rtrim($sql, ', ');
			$sql .= ');';
			$result =& $database->query ($sql);
			if (DB::isError ($result)) {
			    printf("INSERT INTO TABLE failed for query ". $sql . ' the error: ' . $result->getMessage () . "\n");
			    return false;
			}
		  }
//		  printf($sql.'<br />');
		  $row++;
		}
		return true;
    }
     
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_sqlraw extends DokuWiki_Syntax_Plugin {
    var $databases = array();
	var $display_inline = FALSE;
	var $vertical_position = FALSE;
	var $table_class = 'inline';

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }
	 
    /**
     * Where to sort in?
     */ 
    function getSort(){
        return 555;
    }
 
    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
      $this->Lexer->addEntryPattern('<sqlraw [^>]*>',$mode,'plugin_sqlraw');
    }
	
    function postConnect() {
      $this->Lexer->addExitPattern('</sqlraw>','plugin_sqlraw');
    }
 
    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        switch ($state) {
          case DOKU_LEXER_ENTER : 
			$link = propertyRaw('link',$match);
			$startMarker = propertyRaw('startMarker',$match);
			$display = propertyRaw('display', $match);
			$position = propertyRaw('position', $match);
			$tableid = propertyRaw('id', $match);					
			$class = propertyRaw('class', $match);
			$title = propertyRaw('title', $match);			
			$source = propertyRaw('source', $match);				
			return array('display' => $display, 'position' => $position, 'id' => $tableid, 'class' => $class, 'title' => $title, 'link' => $link, 'source' => $source, 'startMarker' => $startMarker);
            break;
          case DOKU_LEXER_UNMATCHED :
			$queries = explode(';', $match);
			if (trim(end($queries)) == "") {
				array_pop($queries);
			}
			return array('sql' => $queries);
            break;
          case DOKU_LEXER_EXIT :
			$this->display_inline = FALSE;
			$this->vertical_position = FALSE;
			$this->table_class = 'inline';
			$this->source = '';
			$this->startMarker = '';
			return array('display' => 'block', 'position' => 'horizontal', 'class' => 'inline', 'id' => ' ', 'source' => ' ', 'startMarker' => '');
            break;
        }
        return array();
    }
 
    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $conf;

		$renderer->info['cache'] = false;
        if($mode == 'xhtml'){		
            if ($data['id'] != FALSE) 
				$this->tableId = $data['id'];			
            if ($data['source'] != FALSE) 
				$this->source = $data['source'];			
            if ($data['startMarker'] != FALSE) 
				$this->startMarker = $data['startMarker'];			
           if ($data['class'] != FALSE) 
				$this->table_class = $data['class'];			
            if ($data['title'] != FALSE) 
				$this->title = $data['title'];								
			if ($data['display'] == 'inline') {
				$this->display_inline = TRUE;
			} else if ($data['display'] == 'block') {
				$this->display_inline = FALSE;
			}
			if ($data['position'] == 'vertical') {
				$this->vertical_position = TRUE;
			} else if ($data['position'] == 'horizontal') {
				$this->vertical_position = FALSE;
			}
			$debugfile = $this->getConf('debugfilepath');
			$tempdb = $this->getConf('tempdb');
			if ($data['link'] != "") {
				$db =& DB::connect($tempdb);
				if (DB::isError($db)) {
					$error = $db->getMessage();
					$renderer->doc .= '<div class="error"> The database error is '. $error .'</div>';
					return TRUE;
				}
				else {
					array_push($this->databases, $db);
				}
				$this->datalink = $data['link'];								
				$disallow = $this->getConf('mysqlDisallow');
				$use = $this->getConf('mysqlReplace');
				$restrictNames = $this->getConf('restrict_names');
    			$theResult = sqlRaw__handleLink($data['link'], $this->source, $this->startMarker, $debugfile, $disallow, $use, $restrictNames);
    			if ($theResult != "") {
        			$db =& array_pop($this->databases);
        			if (!empty($db)) {
            			sqlRaw__create_temp_db ($db,$theResult['headers'],$theResult['rows'],$theResult['lengths']);
            			array_push($this->databases, $db);
        			}
			    }
			    return true;
			}
			elseif (!empty($data['sql'])) {
			    $db =& array_pop($this->databases);
				if (!empty($db)) {
					foreach ($data['sql'] as $query) {
						$db->setFetchMode(DB_FETCHMODE_ASSOC);
						$result =& $db->getAll($query);
						if (DB::isError($result)) {
							$error = $result->getMessage();
							$renderer->doc .= '<div class="error">'. $error .'</div>';
							return TRUE;
						}
						elseif ($result == DB_OK or empty($result)) {
						}
						else {
            				$temp = array_keys($result[0]);
    						if ($this->tableId != ' ') {
        						$id_string = 'id="'.$this->tableId.'" ';
    						} else {
         						$id_string = '';
         					}
							if (! $this->vertical_position) {
								if ($this->display_inline) {
									$renderer->doc .= '<table '.$id_string.'class="'.$this->table_class.'" style="display:inline">';
								} else {
									$renderer->doc .= '<table '.$id_string.'class="'.$this->table_class.'">';
								}
								if ($this->title != '')
								    $renderer->doc .= '<caption class="sqlplugin__title">'.$this->title.'</caption><tbody>';
								$renderer->doc .= '<tr>';
								foreach ($temp as $header) {
									$renderer->doc .= '<th class="row0">';
									$renderer->cdata($header);
									$renderer->doc .= '</th>';
								}
								$renderer->doc .= "</tr>\n";
								foreach ($result as $row) {
									$renderer->doc .= '<tr>';
									foreach ($row as $cell) {
										$renderer->doc .= '<td>';
										$renderer->cdata($cell);
										$renderer->doc .= '</td>';
									}
									$renderer->doc .= "</tr>\n";
								}
								$renderer->doc .= '</tbody></table>';
								sqlRaw__drop_temp_db ($db);
							} else {
								foreach ($result as $row) {
									$renderer->doc .= '<table '.$id_string.'class="'.$this->table_class.'">';
								    if ($this->title != '')
								        $renderer->doc .= '<caption class="sqlplugin__title">'.$this->title.'</caption><tbody>';
									foreach ($row as $name => $cell) {
										$renderer->doc .= '<tr>';
										$renderer->doc .= "<th class='row0'>$name</th>";
										$renderer->doc .= '<td>';
										$renderer->cdata($cell);
										$renderer->doc .= '</td>';
										$renderer->doc .= "</tr>\n";
									}
									$renderer->doc .= '</tbody></table>';
								    sqlRaw__drop_temp_db ($db);
								}
							}
						}
					}
				}
			}
            return true;
        }
        return false;
    }

}