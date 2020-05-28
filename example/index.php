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

?>
<!DOCTYPE html>
<html>
  <head>
    <title>Diff Match Patch</title>
    <link rel="stylesheet" href="style.css">
    <script>
      function diff(e) {
        e.preventDefault();

        var data = new FormData();
        data.append("leftTxt", this.elements.left.value);
        data.append("rightTxt", this.elements.right.value);
        data.append("postprocessing", this.elements.postprocessing.value);

        window.fetch(window.location.pathname, {
            method: "POST",
            body: data
        })
        .then(res => res.text())
        .then(data => {
            document.getElementById('output').innerHTML = data;
        });
      } 
    </script>
  </head>
  <body>

    <form id="form" onSubmit="return diff.call(this, event)">
      <div id="input">
        <textarea name="left" rows="10">Lorem ipsum dolor sit amet, consectetur adipiscing elit.
      Phasellus pulvinar sem eu aliquet pharetra.
      Mauris vel justo dolor.</textarea>
        <textarea name="right" rows="10">Lorem ipsum sit amet, conxxsecxtetur adipiscing elit!!!
      Mauris vel justo dolor.
      Donec aliquam bibendum ex, vitae pulvinar tellus bibendum non. </textarea>
      </div>
      <div class="form-row">
        <div>
          <input type="radio" id="semantic" name="postprocessing" value="semantic" checked>
          <label for="semantic">Semantic Cleanup</label>
        </div>
        <div>
          <input type="radio" id="efficiency" name="postprocessing" value="efficiency">
          <label for="efficiency">Efficiency Cleanup</label>
        </div>
        <div>
          <input type="radio" id="raw" name="postprocessing" value="raw">
          <label for="raw">No Cleanup</label>
        </div>
      </div>
      <button type="submit">Compute Diff</button>
    </form>
    <div id="output" style="white-space: pre"></div>

  </body>
</html>