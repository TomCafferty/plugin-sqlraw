<?php
/**
 * Plugin SQLRAW:  executes SQL queries on data not in a database
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Steven Danz <steven-danz@kc.rr.com>
 * @author     Gert
 * @author     Andreas Gohr <gohr@cosmocode.de>
 * @author     Jerry G. Geiger <JerryGeiger@web.de> 
 * @author     Slim Amamou <slim.amamou@gmail.com>
 * @author     Tom Cafferty <tcafferty@glocalfocal.com>
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once('DB.php');
require_once('simple_html_dom.php');
     
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_sqlraw extends DokuWiki_Syntax_Plugin {
    var $databases = array();
	var $display_inline = FALSE;
	var $vertical_position = FALSE;
	var $table_class = 'inline';
    var $colCount = 0;

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
			$link        = $this->_propertyRaw('link',$match);
			$startMarker = $this->_propertyRaw('startMarker',$match);
			$tableNumber = $this->_propertyRaw('tableNumber',$match);
			$display     = $this->_propertyRaw('display', $match);
			$position    = $this->_propertyRaw('position', $match);
			$tableid     = $this->_propertyRaw('id', $match);					
			$class       = $this->_propertyRaw('class', $match);
			$title       = $this->_propertyRaw('title', $match);			
			$source      = $this->_propertyRaw('source', $match);				
			$caption     = $this->_propertyRaw('caption', $match);				
			$fixTable    = $this->_propertyRaw('fixTable', $match);				
			return array('display' => $display, 'position' => $position, 'id' => $tableid, 'class' => $class, 'title' => $title, 'link' => $link, 'source' => $source, 'startMarker' => $startMarker, 'tableNumber' => $tableNumber, 'caption' => $caption, 'fixTable' => $fixTable);
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
			$this->tableNumber = 1;
			return array('display' => 'block', 'position' => 'horizontal', 'class' => 'inline', 'id' => ' ', 'source' => ' ', 'startMarker' => '', 'tableNumber' => 1);
            break;
        }
        return array();
    }
 
    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $conf;
        global $db;

		$renderer->info['cache'] = false;
        if($mode == 'xhtml'){		
            //
            // Get input parameters and configuration data
            //	
            if ($data['id'] != FALSE) 
				$this->tableId = $data['id'];			
            if ($data['source'] != FALSE) 
				$this->source = $data['source'];			
            if ($data['startMarker'] != FALSE) 
				$this->startMarker = $data['startMarker'];			
            if ($data['tableNumber'] != FALSE) 
                $this->tableNumber = $data['tableNumber'];
			else
				$this->tableNumber = 1;
           if ($data['class'] != FALSE) 
				$this->table_class = $data['class'];			
            if ($data['title'] != FALSE) 
				$this->title = $data['title'];								
			if (isset($data['caption'])) 
			    $this->caption = $data['caption'];
			else
			    $this->caption = 0;
			if (isset($data['fixTable'])) 
			    $this->fixTable = $data['fixTable'];
			else
			    $this->fixTable = 0;
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
			$debugfile = $this->getConf('sqlraw_debugfilepath');
			$tempdb = $this->getConf('sqlraw_tempdb');
			if ($data['link'] != "") {
    			//
    			// First try to connect to the temporary database
    			//
				$db =& DB::connect($tempdb);
				if (DB::isError($db)) {
					$error = $db->getMessage();
					$renderer->doc .= '<div class="error"> The database error is '. $error .'</div>';
					return TRUE;
				} else {
    				//
    				// Good, save that temporary database pointer
    				//
					array_push($this->databases, $db);
				}
				//
				// Process the link to get the data
				//
				$this->datalink = $data['link'];								
				$disallow      = $this->getConf('sqlraw_mysqlDisallow');
				$use           = $this->getConf('sqlraw_mysqlReplace');
				$restrictNames = $this->getConf('sqlraw_restrict_names');
    			$theResult = $this->_sqlRaw__handleLink($data['link'], &$renderer, $this->source, $this->startMarker, $this->tableNumber, $debugfile, $disallow, $use, $restrictNames, $this->caption, $this->fixTable);
    			if ($theResult != "") {
        			//
        			// Good we have data, now retrieve the temporary database pointer and create a temporary table
        			//
        			$db =& array_pop($this->databases);
        			if (!empty($db)) {
            			$success = $this->_sqlRaw__create_temp_db ($db, $theResult['headers'], $theResult['rows'], $theResult['lengths'], &$renderer);
            			array_push($this->databases, $db);
        			}
			    }
			    //
			    // Done for now
			    //
			    return true;
			    
			} elseif (!empty($data['sql'])) {
    			//
    			// This pass thru we have already setup the temporary database table. 
    			// So now process the supplied MySQL query on the temporary database table.
    			//
			    $db =& array_pop($this->databases);
				if (!empty($db)) {
					$db->setFetchMode(DB_FETCHMODE_ASSOC);
					foreach ($data['sql'] as $query) {
						$result =& $db->getAll($query);
						if (DB::isError($result)) {
							$error = $result->getMessage();
							$renderer->doc .= '<div class="error">'. $error .'</div>';
			                $db->disconnect();
							return TRUE;
						} elseif ($result == DB_OK or empty($result)) {
    						//
    						// Do nothing
    						//
						} else {
    						//
    						// Display the result as a table
    						//
            				$temp = array_keys($result[0]);
    						if ($this->tableId != ' ') {
        						$id_string = 'id="'.$this->tableId.'" ';
    						} else {
         						$id_string = '';
         					}
							if (! $this->vertical_position) {
    							//
    							// Display vertical table
    							//
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
								$count=1;
								foreach ($result as $row) {
									$renderer->doc .= '<tr class="row'.$count.'">';
									foreach ($row as $cell) {
										$renderer->doc .= '<td>';
										$renderer->cdata($cell);
										$renderer->doc .= '</td>';
									}
									$renderer->doc .= "</tr>\n";
									if ($this->table_class != "sortable") $count++;
								}
								$renderer->doc .= '</tbody></table>';
								$this->_sqlRaw__drop_temp_db ($db, &$renderer);
							} else {
    							//
    							// Display horizontal table
    							//
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
								    $this->_sqlRaw__drop_temp_db ($db, &$renderer);
								}
							}
						}
					}
				}
			}
			$db->disconnect();
            return true;
        }
        return false;
    }
   
    //
    // Function: sqlRaw__handleLink
    // Purpose:  Determine where the data will come from and process 
    // Input:
    //   $url           - url to csv or table
    //   $source        - what is being processed a 'csvfile' or 'scrapeUrl'
    //   $startMarker   - a marker of text to start looking for the next table (optional)
    //   $tableNumber   - the number of the table to scrape on a page (eg 1,2,3...) (optional)
    //   $dbfile        - debug file to write the csv to when scraping a table
    //   $disallow      - Character(s) that will not be allowed for column headings
    //   $use           - Character(s) that will replace the corresponding disallowed characters 
    //   $restrictNames - Boolean denotes if disallow characters will be replaced (1=replace)
    // Returns:
    //   $myResult - multidimensional array of table headings, rows of data, and size of each cell
    //   false on error
    //
    function _sqlRaw__handleLink($url, &$renderer, $source, $startMarker, $tableNumber, $dbfile, $disallow, $use, $restrictNames, $caption, $fixTable){
        global $ID;
        global $colCount;
        $delim = ',';
        $opt = array('content' => '');
        if ($source == 'csvfile') {
            //
            // Process a csv file
            //
            if(strpos($url, 'http') !==false) {
                $http = new DokuHTTPClient();
                $opt['content'] = $http->get($url);
            } else {
                //
                // load the file from a local dokuwiki namespace
                //
                $opt['file'] = cleanID($url);
                if(!strlen(getNS($opt['file'])))
                      $opt['file'] = $INFO['namespace'].':'.$opt['file'];
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
            //
            // if nothing there print error message and quit
            //
            if(!$opt['content']){
                printf("Failed to fetch remote CSV data.\n");
                return false;
            }
            $content =& $opt['content'];
            
        } elseif ($source == 'scrapeUrl') {
            //
            // Scrape a Table
            //
            $content =& $this->_scrapeTable(strtolower($url), $startMarker,  $tableNumber, $dbfile, $disallow, $use, $restrictNames, $caption, $fixTable);
            if ($content == false) {
                msg("No data for the requested page of ".$url."\n",-1);
                return false;
            }
            
        } else {
            //
            // Neither is an error
            //
            msg("No valid source url provided.\n");
            return false;
        }
        //
        // clear any trailing or leading empty lines from the data set
        //
        $content = preg_replace("/[\r\n]*$/","",$content);
        $content = preg_replace("/^\s*[\r\n]*/","",$content);
        if($content == "") {
            printf("No csv data found.\n");
            return false;
        }
        //
        // get each row
        //
        $rows = array();
        $maxcol=0;
        $maxrow=0;
        while($content != "") {
          $thisrow = $this->_sqlRaw__csv_explode_row($content,$delim);
          if($maxcol < count($thisrow))
              $maxcol = count($thisrow);
          array_push($rows, $thisrow);
          $maxrow++;
        }
        //
        // process headers and determine max field sizes
        //
        $row = 1;
        foreach($rows as $fields) {
    	  if ($row === 1) {
            $colCount=0;
    		foreach ($fields as $field) {
        		if ($restrictNames) 
        		    $field = str_replace($disallow, $use, $field);
    			$headers[] = strtolower(str_ireplace(' ', '_', $field));
    			$colCount++;
    		}
		  } else {
			foreach ($fields as $key=>$value) {
				if (!isset($max_field_lengths[$key]))
					$max_field_lengths[$key] = 0;
				if (strlen($value) > $max_field_lengths[$key])
					$max_field_lengths[$key] = strlen($value);
				if ($max_field_lengths[$key] == 0)
					$max_field_lengths[$key] = 1;			
				$field++;
			}
		  }
		  $row++;
        }
        //
        // Set up return data as multidimensional array of headers, data and sizes
        //
        $myResult['headers'] = $headers;
        $myResult['rows'] = $rows;
        $myResult['lengths'] = $max_field_lengths;
        return $myResult;
    }

    //
    // Function: scrapeTable
    // Purpose:  Scrape a webpage to obtain only a specific table  
    // Input:
    //   $url            - the web page as either a url or a dokuwiki page id
    //   $startMarker    - (optional) marker of text to start looking after for the table. 
    //                     If null it will take the first table,
    //   $tableNumber    - (optional) the table number to scrape. 
    //                     If null it will take the first table,
    //   $dbfile         - (optional) A filepath and filename to write the table to as csv.
    //                     If null the table is not saved to a file
    //   $specialChars   - Character(s) that will not be allowed for column headings
    //   $specialReplace - Character(s) that will replace the corresponding specialChar for column headiings
    //   $restrictNames  - Boolean denotes if specialChars will be replaced (1=replace)
    // Returns:
    //   $csv_data - a string of the table in csv format containing only headings and cell content
    //   false     - if a dokuwiki id was supplied and no data was obtained from that page
    // Notes
    //
    function _scrapeTable($url, $startMarker, $tableNumber, $dbfile, $specialChars, $specialReplace, $restrictNames, $caption, $fixTable) {
    require_once('test2.php');
    global $colCount;
    
    $csv_data = '';
    if(preg_match('/^(http|https)?:\/\//i',$url)){
        $raw = file_get_contents($url);
    } else {
        $raw = $this->_pullInWikiPage($url);
        if ($raw == false) 
            return false;
    }
    $newlines = array("\t","\n","\r","\x20\x20","\0","\x0B","<br />");
    $spaceCodes = array("&nbsp;");
    $numberStuff = array(",","+");
    $numbs       = array(",",".","+","-","0","1","2","3","4","5","6","7","8","9");

    $content = str_replace($newlines, "", $raw);
    $content = str_replace($spaceCodes, "_", ($content));
    $content = preg_replace("/&#?[a-z0-9]+;/i","",$content);

    $debug = FALSE;
    if ($dbfile != '') {
      $debug = TRUE;
      $fp    = fopen($dbfile, 'w');
    } 
       
    if ($startMarker != '') {
        // Start looking at the marker
        $start = strpos($content,$startMarker);
        $content = substr($content,$start);
    }

    $end = 0;
    for ($x = 1; $x <= $tableNumber; $x++) {
    // Pull out the table
        $start = strpos($content,'<table ', $end);
    $end = strpos($content,'</table>',$start) + 8;
    $table = '';
    $table = substr($content,$start,$end-$start);
    }
   
    // Get column count and fix any missing </td>s
    $table = $this->_fixTable($table);
    
    // Handle row/col spans and captions
    if ($fixTable == 1){
        $mdTable = table2csv($table,'tom.csv',false, $caption);
        foreach ($mdTable as $key => $row) {
            //clean the data
            array_walk($mdTable[$key], "cleanCell");
        	if ($debug == TRUE) 
        	  fputcsv($fp, $mdTable[$key]);
        	$csv_data .= $this->_strputcsv($row, $colCount-1);
        }
    } else {
      // Simple table
    // Pull out the rows
    preg_match_all("|<tr(.*)</tr>|U",$table,$rows);
    
    $row_index=0;
    $numHeadings = 0;
    foreach ($rows[0] as $row){
	  $newCells = false;
      if ($row_index!=0 || $caption==0) {
            
        if ($restrictNames && ($row_index==0))
            // 
            // clean the headings
            //
            $row = str_replace($specialChars, $specialReplace, $row);
                
        if (strpos($row,'<th')===false)  {
          //
          // pull out the cells 
          // 
          preg_match_all("|<td(.*)</td>|U",$row,$cells);
		  $newCells = true;
        }
        else 
          //
          // pull out the cells and count the number of them
          // 
          if ( $numHeadings == 0) {
		      $numHeadings = preg_match_all("|<t(.*)</t(.*)>|U",$row,$cells);
		      $newCells = true;
	      }
		        
    	if (($row_index == 0) || ($row_index == 1 && $caption==1)) 
    	  //
    	  // the 1st row gives the number of columns
    	  // 
    	  $numCols = $numHeadings;
    	      $numCols = $colCount;
		  
    	//
    	// store the cells by [row][cell] after you clean it
		//  
		if ($newCells === true) {
		$cell_index=0;
		foreach ($cells[0] as $cell) {
            $test = strip_tags(trim(str_replace($numbs, "", $cell)));
    		if (strlen($test)==0)
    		  //
    		  // if all number remove extraneous characters ('+', ',')
    		  //
    		  $cell = str_replace($numberStuff, '', $cell);
    		//
    		// strip html and php tags
    		//    (Test for table error of too many cells)
    	    if ($cell_index < $numHeadings) {
    		  $mycells[$row_index][$cell_index] = trim(strip_tags($cell));
    		  ++$cell_index;
    	    }
    	  }
	    }
    	if ($mycells[$row_index] != '') {
        	if ($debug == TRUE) 
        	  fputcsv($fp, $mycells[$row_index]);
        	$csv_data .= $this->_strputcsv($mycells[$row_index], $numCols-1);
        }
        }
        //
        // repeat for each row
        //
    	++$row_index;
    }
    if ($debug == TRUE) 
      fclose($fp);
   }
   $csv_data = strip_tags($csv_data);
   $csv_data = ltrim($csv_data, " ,");
    return $csv_data;
}
    
    //
    // The rest are helper functions
    //
    
    //
    // Function: fixTable
    // Purpose:  Correct errors in table if possible  
    // Input:
    //   $tableIn - input table
    // Returns:
    //   $tableIn - fixed table
    //
    function _fixTable ($tableIn) {
        global $colCount;
        $tempColCount = 0;
        $colCount = 0;
        
        // Add missing </td>s
        $length = strlen ($tableIn);
        $pos = 0;
        while($pos <= $length-1) {
            $tdPosOpen     = stripos($tableIn, '<td', $pos);
            $tdPosClose    = stripos($tableIn, '</td>',$tdPosOpen+4);
            $tdPosOpenNext = stripos($tableIn, '<td', $tdPosOpen+4);
            if ($tdPosOpenNext < $pos) {
                $pos = $length;
            } else {
                if ($tdPosOpenNext !== false && $tdPosOpenNext < $tdPosClose) {
                    $tableIn = substr_replace($tableIn, '</td>', $tdPosOpenNext-1, 0); 
                    $length += 5;
                }
                $pos = $tdPosClose+5;
            }
        }   
        
        // Get the column count
        // we have to iterate through the rows and pull out the max found
        $html = str_get_html(trim($tableIn));
        foreach ($html->find('tr') as $element) {
            $tempColCount = 0;
            foreach ($element->find('th') as $cell) {
                $tempColCount++;
            }
            if ($tempColCount > $colCount) $colCount = $tempColCount;
        }
        return $tableIn;
    }
    
       
    //
    // Function: pullInWikiPage
    // Purpose:  Read a dokuwiki page  
    // Input:
    //   $dokuPageId - the dokuwiki page id
    // Returns:
    //   $html - the rendered html for the page
    //
    function _pullInWikiPage ($dokuPageId) {
        $file = wikiFN($dokuPageId);
        $data = io_readWikiPage($file, $dokuPageId, $rev=false);
        $html = p_render('xhtml',p_get_instructions($data),$info);
        return $html;
    }

    //
    // Function: propertyRaw
    // Purpose:  search a string for the parameter value  
    // Input:
    //   $prop - the parameter name
    //   $xml  - the string to search
    // Returns:
    //   $match - the matched parameter value
    //   false  - on no match found
    // Notes
    //   It will attempt the search looking for single or double quotes
    //      surrounding the parameter value
    //
    function _propertyRaw($prop, $xml) {
	$pattern = $prop ."='([^']*)'";
	if (ereg($pattern, $xml, $matches)) 
		return $matches[1];
	$pattern = $prop .'="([^"]*)"';
	if (ereg($pattern, $xml, $matches)) 
		return $matches[1];
    return FALSE;
}

    //
    // Function: strputcsv
    // Purpose:  converts array elements into a csv string  
    // Input:
    //   $fields      - array of elements
    //   $numheadings - number of column headings
    //   $delimiter   - (optional) the delimiter to use. Defaults to a comma.
    //   $enclosure   - (optional) the enclosure character to use. Defaults to a double quote.
    // Returns:
    //   $csvline - the csv string
    //
    function _strputcsv($fields = array(), $numheadings, $delimiter = ',', $enclosure = '"') {
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
    
    //
    // Function: sqlRaw__csv_explode_row
    // Purpose:  Explode CSV string, consuming it as we go 
    // History:   
    //   Dokuwiki CSV Plugin function csv_explode_row
    // Author:
    //   Steven Danz <steven-danz@kc.rr.com>
    //   Andreas Gohr <gohr@cosmocode.de>
    //   Jerry G. Geiger <JerryGeiger@web.de>
    // Input:
    //   $database - database identifier
    // Returns:
    //   $out - an array of the csv string elements
    // Notes:
    // Careful, there could be both embedded newlines, commas and quotes
    // One thing to remember is that a row must end with a newline
    //   RFC 4180 claims that a CSV is allowed to have a cell enclosed in ""
    //     that embeds a newline. Convert those newlines to \\ (trying to keep
    //     to the DokuWiki syntax) which we will key off of later in render()
    //     as an embedded newline.    
    function _sqlRaw__csv_explode_row(&$str, $delim = ',', $qual = "\"") {
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
    
    //
    // Function: sqlRaw__drop_temp_db
    // Purpose:  Drop a temporary database table 
    // Input:
    //   $database - database identifier
    // Returns:
    //   true on success
    //   false on error
    //
    function _sqlRaw__drop_temp_db($database, &$renderer) {
        $table = 'temptable';     
        $query = 'DROP TEMPORARY TABLE IF EXISTS '.$table;
        $result =& $database->query ($query);
        if (DB::isError ($result)) {
			$renderer->doc .= '<div class="error">DROP TABLE failed for query: '. $query .'the error: '. $result->getMessage() .'</div>';
			$db->disconnect();
            return false;
        }
        return true;
    }
       
    //
    // Function: sqlRaw__create_temp_db
    // Purpose:  Create a temporary database table 
    // Input:
    //   $database - database identifier
    //   $headers  - array of column headings
    //   $rows     - array of data records
    //   $max_field_lengths - array of maximum field sizes
    // Returns:
    //   true on success
    //   false on error
    //
    function _sqlRaw__create_temp_db($database, $headers, $rows, $max_field_lengths, &$renderer) {
        global $colCount;
        global $db;
        $badChars = array(".", ":", "-","/");
        $table = 'temptable';
        
        // Create the table      
        $query = 'CREATE TEMPORARY TABLE '.$table . ' (';
        
        foreach ($headers as $key=>$header) {
            if ($header != "")
            $query .= str_replace($badChars,'_',trim($header)).' VARCHAR('.$max_field_lengths[$key].'), ';
        }
        $query = rtrim($query,', ');
        $query .= ') DEFAULT CHARACTER SET \'utf8\'';
        $result =& $database->query ($query);
        if (DB::isError ($result)) {
			$renderer->doc .= '<div class="error">CREATE TABLE failed for query: '. $query .'the error: '. $result->getMessage() .'</div>';
            return false;
        }
        
        // Insert the records
        $row = 1;
        foreach($rows as $fields) {
    	  if ($row !== 1) {
        	if ($this->caption==0 || ($this->caption==1 && $row > 2)) {
			$sql = 'INSERT INTO `'.$table.'` VALUES(';
            $col_index=0;
			foreach ($fields as $field) {
              if ($col_index < $colCount) {
				$sql .= '\''.$database->escapeSimple($field).'\', ';
				$col_index++ ;
	          }
	        }
			$sql = rtrim($sql, ', ');
			$sql .= ');';
			$result =& $database->query ($sql);
			if (DB::isError ($result)) {
			    $renderer->doc .= '<div class="error">INSERT INTO TABLE failed for query: '. $sql .'the error: '. $result->getMessage() .'</div>';
			    $db->disconnect();
			    return false;
			}
		  }
	  }
		  $row++;
		}
		return true;
    }
}