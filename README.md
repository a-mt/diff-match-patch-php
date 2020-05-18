# Diff Match Patch

Diff Match Patch is a high-performance library in multiple languages that manipulates plain text.  
PHP transpiling of [@google/diff-match-patch](https://github.com/google/diff-match-patch)

``` php
<?php
require 'DiffMatchPatch.php';

$leftTxt  = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
$rightTxt = 'Lorem ipsum sit amet, conxxsecxtetur adipiscing elit!!!';
$postprocessing = 'semantic';

$dmp  = new DiffMatchPatch();
$diff = $dmp->diff_main($leftTxt, $rightTxt);

switch($postprocessing) {
    case "semantic": $dmp->diff_cleanupSemantic($diff); break;
    case "efficiency": $dmp->diff_cleanupEfficiency($diff); break;
}

echo $dmp->diff_prettyHtml($diff);
```