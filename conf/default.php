<?php
$conf['debugfilepath'] = 'sqlraw.csv';
$conf['tempdb'] = 'mysql://user:password@hostname/database';
$conf['mysqlDisallow'] = array (
  0 => '%',
  1 => '(',
  2 => ')'
  );
$conf['mysqlReplace'] = array (
  0 => 'percent',
  1 => '_', 
  2 => ''
  );
$conf['restrict_names'] = 1;
