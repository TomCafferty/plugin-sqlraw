<?php

    //download @ http://simplehtmldom.sourceforge.net/
    require_once('simple_html_dom.php');
    $repeatContentIntoSpannedCells = false;


    //--------------------------------------------------------------------------------------------------------------------

    function table2csv($rawHTML,$filename,$repeatContent, $caption) {

        //get rid of sups - they mess up the wmus
        for ($i=1; $i <= 20; $i++) { 
            $rawHTML = str_replace("<sup>".$i."</sup>", "", $rawHTML);
        }

        global $repeatContentIntoSpannedCells;

        $html = str_get_html(trim($rawHTML));
        $repeatContentIntoSpannedCells = $repeatContent;

        //we need to pre-initialize the array based on the size of the table (how many rows vs how many columns)

        //counting rows is easy
        $rowCount = count($html->find('tr'));

        //column counting is a bit trickier, we have to iterate through the rows and basically pull out the max found
        $colCount = 0;
        $rowPos = 0;
        foreach ($html->find('tr') as $element) {
          if ($rowPos!=0 || $caption==0) {

            $tempColCount = 0;

            foreach ($element->find('th') as $cell) {
                $tempColCount++;
            }

            if ($tempColCount == 0) {
                foreach ($element->find('td') as $cell) {
                    $tempColCount++;
                }
            }

            if ($tempColCount > $colCount) $colCount = $tempColCount;
          }
        $rowPos = 1;
        }

        $mdTable = array();

        for ($i=0; $i < $rowCount; $i++) { 
            if ($i!=0 || $caption==0) 
            array_push($mdTable, array_fill(0, $colCount, NULL));
        }

        //////////done predefining array

        $rowPos = 0;
        $fp = fopen($filename, "w");

        foreach ($html->find('tr') as $element) {
          if ($rowPos!=0 || $caption==0) {

            $colPos = 0;

            foreach ($element->find('th') as $cell) {
                if (strpos(trim($cell->class), 'actions') === false && strpos(trim($cell->class), 'checker') === false) {
                    parseCell($cell,$mdTable,$rowPos,$colPos);
                }
                $colPos++;
            }

            foreach ($element->find('td') as $cell) {
                if (strpos(trim($cell->class), 'actions') === false && strpos(trim($cell->class), 'checker') === false) {
                    parseCell($cell,$mdTable,$rowPos,$colPos);
                }
                $colPos++;
            }
          }  

          $rowPos++;
        }


        foreach ($mdTable as $key => $row) {

            //clean the data
            array_walk($mdTable[$key], "cleanCell");
            fputcsv($fp, $row);
        }
        return $mdTable;
    }


    function cleanCell(&$contents,$key) {

        $contents = trim($contents);

        //get rid of pesky &nbsp's (aka: non-breaking spaces)
        $contents = trim($contents,chr(0xC2).chr(0xA0));
        $contents = str_replace("&nbsp;", "", $contents);
    }


    function parseCell(&$cell,&$mdTable,&$rowPos,&$colPos) {

        global $repeatContentIntoSpannedCells;

        //if data has already been set into the cell, skip it
        while (isset($mdTable[$rowPos][$colPos])) {
            $colPos++;
        }

        $mdTable[$rowPos][$colPos] = $cell->plaintext;

        if (isset($cell->rowspan)) {

            for ($i=1; $i <= ($cell->rowspan)-1; $i++) {
                $mdTable[$rowPos+$i][$colPos] = ($repeatContentIntoSpannedCells ? $cell->plaintext : "");
            }
        }

        if (isset($cell->colspan)) {

            for ($i=1; $i <= ($cell->colspan)-1; $i++) {

                $colPos++;
                $mdTable[$rowPos][$colPos] = ($repeatContentIntoSpannedCells ? $cell->plaintext : "");
            }
        }
    }

?>