<?php
$conf['sqlraw_debugfilepath'] = 'sqlraw.csv';
$conf['sqlraw_tempdb'] = 'mysql://user:password@hostname/database';
$conf['sqlraw_mysqlDisallow'] = array (
  0 => '%',
  1 => '(',
  2 => ')',
  3 => ',',
  4 => '.'
  );
$conf['sqlraw_mysqlReplace'] = array (
  0 => 'percent',
  1 => '_', 
  2 => '',
  3 => '_',
  4 => '_'
  );
$conf['sqlraw_restrict_names'] = 1;
$conf['sqlraw_caption']  = 0;
$conf['sqlraw_debugTableScrape'] = 0;
