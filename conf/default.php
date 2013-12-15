<?php
$conf['sqlraw_debugfilepath'] = '';
$conf['sqlraw_tempdb'] = 'mysql://user:password@hostname/database';
$conf['sqlraw_mysqlDisallow'] = array (
  0 => '%',
  1 => '(',
  2 => ')',
  3 => ',',
  4 => '.',
  5 => '[',
  6 => ']'
  );
$conf['sqlraw_mysqlReplace'] = array (
  0 => 'percent',
  1 => '_', 
  2 => '',
  3 => '_',
  4 => '_',
  5 => '_',
  6 => '_'
  );
$conf['sqlraw_restrict_names'] = 1;
