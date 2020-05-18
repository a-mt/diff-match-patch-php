<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

if(isset($_POST['leftTxt'])) {
  require __DIR__ . '/../DiffMatchPatch.php';
  
  $leftTxt  = str_replace("\r", '', $_POST['leftTxt']);
  $rightTxt = str_replace("\r", '', $_POST['rightTxt']);
  $postprocessing = $_POST['postprocessing'];

  $dmp  = new DiffMatchPatch();
  $diff = $dmp->diff_main($leftTxt, $rightTxt);

  // Post-processing
  switch($postprocessing) {
    case "semantic": $dmp->diff_cleanupSemantic($diff); break;
    case "efficiency": $dmp->diff_cleanupEfficiency($diff); break;
  }
  
  $html = "";
  for($i=0; $i < sizeof($diff); $i++) {
    list($state, $txt) = $diff[$i];
    
    if($state == -1) {
      $html .= '<del class="diff">' . $txt . '</del>';  
    } else if($state == 1) {
      $html .= '<ins class="diff">' . $txt . '</ins>';  
    } else {
      $html .= $txt;
    }
  }
  echo $html;
  die;
}

readfile(__DIR__ . '/tpl.html');