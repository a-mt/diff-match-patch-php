<?php
// Based on https://github.com/google/diff-match-patch/blob/master/javascript/diff_match_patch_uncompressed.js
namespace DiffMatchPatch;

/**
 * The data structure representing a diff is an array of tuples:
 * [[DIFF_DELETE, 'Hello'], [DIFF_INSERT, 'Goodbye'], [DIFF_EQUAL, ' world.']]
 * which means: delete 'Hello', add 'Goodbye' and keep ' world.'
 */
define('DIFF_DELETE', -1);
define('DIFF_INSERT', 1);
define('DIFF_EQUAL', 0);

/**
 * URL-encodes a string but doesn't encode
 * characters that have special meaning (reserved characters) for a URI
 * @param string $uri
 * @return string
 */
function encodeURI($uri) {
  return preg_replace_callback("{[^0-9a-z_.!~*'();,/?:@&=+$#-]}i", function ($m) {
    return sprintf('%%%02X', ord($m[0]));
  }, $uri);
}

/**
 * Class representing one diff tuple.
 * Attempts to look like a two-element array (which is what this used to be).
 * @param {number} op Operation, one of: DIFF_DELETE, DIFF_INSERT, DIFF_EQUAL.
 * @param {string} text Text to be deleted, inserted, or retained.
 * @constructor
 */
class Diff implements \ArrayAccess {
  public $data;
  
  public function __construct($op, $text) {
    $this->data[0] = $op;
    $this->data[1] = $text;
  }
  
  /**
  * Emulate the output of a two-element array.
  * @return {string} Diff operation as a string.
  */
  public function __toString() {
    return implode(',', $this->data);
  }

  public function offsetExists($key) {
    return isset($this->data[$key]);
  }
  public function offsetGet($key) {
    return $this->data[$key];
  }
  public function offsetSet($key, $value) {
    $this->data[$key] = $value;
  }
  public function offsetUnset($key) {
    unset($this->data[$key]);
  }
}

/**
 * Class containing the diff, match and patch methods.
 * @constructor
 */
class DiffMatchPatch {

  //+------------------------------------------------------
  //|
  //| DEFAULTS
  //| Redefine these in your program to override the defaults.
  //|
  //+------------------------------------------------------

  // Number of seconds to map a diff before giving up (0 for infinity).
  public static $Diff_Timeout = 1.0;
  
  // Cost of an empty edit operation in terms of edit characters.
  public static $Diff_EditCost = 4;
  
  // At what point is no match declared (0.0 = perfection, 1.0 = very loose).
  public static $Match_Threshold = 0.5;
  
  // How far to search for a match (0 = exact location, 1000+ = broad match).
  // A match this many characters away from the expected location will add
  // 1.0 to the score (0.0 is a perfect match).
  public static $Match_Distance = 1000;
  
  // When deleting a large block of text (over ~64 characters), how close do
  // the contents have to be to match the expected contents. (0.0 = perfection,
  // 1.0 = very loose).  Note that Match_Threshold controls how closely the
  // end points of a delete need to match.
  public static $Patch_DeleteThreshold = 0.5;
  
  // Chunk size for context length.
  public static $Patch_Margin = 4;
  
  // The number of bits in an int.
  public static $Match_MaxBits = 32;

  //+------------------------------------------------------
  //|
  //| DIFF FUNCTIONS
  //|
  //+------------------------------------------------------

  // Define some regex patterns for matching boundaries.
  const nonAlphaNumericRegex_ = "/[^a-zA-Z0-9]/";
  const whitespaceRegex_ = "/\\s/";
  const linebreakRegex_ = "/[\\r\\n]/";
  const blanklineEndRegex_ = "/\\n\\r?\\n$/";
  const blanklineStartRegex_ = "/^\\r?\\n\\r?\\n/";

   /**
   * Find the differences between two texts.  Simplifies the problem by stripping
   * any common prefix or suffix off the texts before diffing.
   * @param {string} text1 Old string to be diffed.
   * @param {string} text2 New string to be diffed.
   * @param {boolean=} opt_checklines Optional speedup flag. If present and false,
   *     then don't run a line-level diff first to identify the changed areas.
   *     Defaults to true, which does a faster, slightly less optimal diff.
   * @param {number=} opt_deadline Optional time when the diff should be complete
   *     by.  Used internally for recursive calls.  Users should set DiffTimeout
   *     instead.
   * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
   */
  public function diff_main($text1, $text2, $opt_checklines = null, $opt_deadline = null) {

    // Check for null inputs.
    if ($text1 === null || $text2 === null) {
      throw new Exception('Null input. (diff_main)');
    }
    
    // Check for equality (speedup).
    if ($text1 == $text2) {
      if ($text1) {
        return [new Diff(DIFF_EQUAL, $text1)];
      }
      return [];
    }
    
    // Set a deadline by which time the diff must be complete.
    if($opt_deadline === null) {
      if(DiffMatchPatch::$Diff_Timeout <= 0) {
        $opt_deadline = getrandmax();
      } else {
        $opt_deadline = time() + DiffMatchPatch::$Diff_Timeout * 1000;
      }
    }
    if($opt_checklines === null) {
      $opt_checklines = true;
    }
    
    // Trim off common prefix (speedup).
    $commonlength = $this->diff_commonPrefix($text1, $text2);
    $commonprefix = substr($text1, 0, $commonlength);

    $text1 = substr($text1, $commonlength);
    $text2 = substr($text2, $commonlength);

    // Trim off common suffix (speedup).
    $commonlength = $this->diff_commonSuffix($text1, $text2);
    $commonsuffix = substr($text1, strlen($text1) - $commonlength);
    
    $text1 = substr($text1, 0, strlen($text1) - $commonlength);
    $text2 = substr($text2, 0, strlen($text2) - $commonlength);

    // Compute the diff on the middle block.
    $diffs = $this->diff_compute_($text1, $text2, $opt_checklines, $opt_deadline);

    // Restore the prefix and suffix.
    if ($commonprefix) {
      array_unshift($diffs, new Diff(DIFF_EQUAL, $commonprefix));
    }
    if ($commonsuffix) {
      array_push($diffs, new Diff(DIFF_EQUAL, $commonsuffix));
    }
    $this->diff_cleanupMerge($diffs);
    return $diffs;
  }
   
   /**
   * Find the differences between two texts.  Assumes that the texts do not
   * have any common prefix or suffix.
   * @param {string} text1 Old string to be diffed.
   * @param {string} text2 New string to be diffed.
   * @param {boolean} checklines Speedup flag.  If false, then don't run a
   *     line-level diff first to identify the changed areas.
   *     If true, then run a faster, slightly less optimal diff.
   * @param {number} deadline Time when the diff should be complete by.
   * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
   * @private
   */
  protected function diff_compute_($text1, $text2, $checklines, $deadline) {
    if (!$text1) {
     // Just add some text (speedup).
     return [new Diff(DIFF_INSERT, $text2)];
    }
    if (!$text2) {
     // Just delete some text (speedup).
     return [new Diff(DIFF_DELETE, $text1)];
    }

    $longtext = strlen($text1) > strlen($text2) ? $text1 : $text2;
    $shorttext = strlen($text1) > strlen($text2) ? $text2 : $text1;
    $i = strpos($longtext, $shorttext);

    if($i !== false) {
      // Shorter text is inside the longer text (speedup).
      $diffs = [new Diff(DIFF_INSERT, substr($longtext, 0, $i)),
                new Diff(DIFF_EQUAL, $shorttext),
                new Diff(DIFF_INSERT, substr($longtext, $i + strlen($shorttext)))];
      
      // Swap insertions for deletions if diff is reversed.
      if (strlen($text1) > strlen($text2)) {
        $diffs[0][0] = $diffs[2][0] = DIFF_DELETE;
      }
      return $diffs;
    }
  
    if (strlen($shorttext) == 1) {
      // Single character string.
      // After the previous speedup, the character can't be an equality.
      return [new Diff(DIFF_DELETE, $text1),
             new Diff(DIFF_INSERT, $text2)];
    }
  
    // Check to see if the problem can be split in two.
    $hm = $this->diff_halfMatch_($text1, $text2);

    if ($hm) {
      // A half-match was found, sort out the return data.
      $text1_a = $hm[0];
      $text1_b = $hm[1];
      $text2_a = $hm[2];
      $text2_b = $hm[3];
      $mid_common = $hm[4];
      
      // Send both pairs off for separate processing.
      $diffs_a = $this->diff_main($text1_a, $text2_a, $checklines, $deadline);
      $diffs_b = $this->diff_main($text1_b, $text2_b, $checklines, $deadline);

      // Merge the results.
      return array_merge($diffs_a, [new Diff(DIFF_EQUAL, $mid_common)], $diffs_b);
    }

    if ($checklines && strlen($text1) > 100 && strlen($text2) > 100) {
      return $this->diff_lineMode_($text1, $text2, $deadline);
    }
    return $this->diff_bisect_($text1, $text2, $deadline);
  }

   /**
    * Do a quick line-level diff on both strings, then rediff the parts for
    * greater accuracy.
    * This speedup can produce non-minimal diffs.
    * @param {string} text1 Old string to be diffed.
    * @param {string} text2 New string to be diffed.
    * @param {number} deadline Time when the diff should be complete by.
    * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
    * @private
    */
  protected function diff_lineMode_($text1, $text2, $deadline) {

    // Scan the text on a line-by-line basis first.
    $a = $this->diff_linesToChars_($text1, $text2);

    $text1 = $a['chars1'];
    $text2 = $a['chars2'];
    $linearray = $a['lineArray'];
    
    $diffs = $this->diff_main($text1, $text2, false, $deadline);

    // Convert the diff back to original text.
    $this->diff_charsToLines_($diffs, $linearray);
    // Eliminate freak matches (e.g. blank lines)
    $this->diff_cleanupSemantic($diffs);
  
    // Rediff any replacement blocks, this time character-by-character.
    // Add a dummy entry at the end.
    array_push($diffs, new Diff(DIFF_EQUAL, ''));
  
    $pointer = 0;
    $count_delete = 0;
    $count_insert = 0;
    $text_delete = '';
    $text_insert = '';
  
    while ($pointer < sizeof($diffs)) {
      switch ($diffs[$pointer][0]) {
        case DIFF_INSERT:
          $count_insert++;
          $text_insert .= $diffs[$pointer][1];
          break;
  
        case DIFF_DELETE:
          $count_delete++;
          $text_delete .= $diffs[$pointer][1];
          break;
  
        case DIFF_EQUAL:
          // Upon reaching an equality, check for prior redundancies.
          if ($count_delete >= 1 && $count_insert >= 1) {
  
            // Delete the offending records and add the merged ones.
            array_splice($diffs,
              $pointer - $count_delete - $count_insert,
              $count_delete + $count_insert);
  
            $pointer = $pointer - $count_delete - $count_insert;
            $subDiff = $this->diff_main($text_delete, $text_insert, false, $deadline);
            for ($j = sizeof($subDiff) - 1; $j >= 0; $j--) {
              array_splice($diffs, $pointer, 0, $subDiff[$j]);
            }
            $pointer = $pointer + sizeof($subDiff);
          }
          $count_insert = 0;
          $count_delete = 0;
          $text_delete = '';
          $text_insert = '';
          break;
      }
      $pointer++;
    }
    array_pop($diffs);  // Remove the dummy entry at the end.
  
    return $diffs;
  }

  /**
   * Find the 'middle snake' of a diff, split the problem in two
   * and return the recursively constructed diff.
   * See Myers 1986 paper: An O(ND) Difference Algorithm and Its Variations.
   * @param {string} text1 Old string to be diffed.
   * @param {string} text2 New string to be diffed.
   * @param {number} deadline Time at which to bail if not yet complete.
   * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
   * @private
   */
  protected function diff_bisect_($text1, $text2, $deadline) {
    // Cache the text lengths to prevent multiple calls.
    $text1_length = strlen($text1);
    $text2_length = strlen($text2);

    $max_d = ceil(($text1_length + $text2_length) / 2);
    $v_offset = $max_d;
    $v_length = 2 * $max_d;

    // Setting all elements to -1 is faster than mixing integers and null.
    $v1 = array_fill(0, $v_length, -1);
    $v2 = array_fill(0, $v_length, -1);

    $v1[$v_offset + 1] = 0;
    $v2[$v_offset + 1] = 0;
    $delta = $text1_length - $text2_length;

    // If the total number of characters is odd, then the front path will collide
    // with the reverse path.
    $front = ($delta % 2 != 0);

    // Offsets for start and end of k loop.
    // Prevents mapping of space beyond the grid.
    $k1start = 0;
    $k1end = 0;
    $k2start = 0;
    $k2end = 0;

    for ($d = 0; $d < $max_d; $d++) {
      // Bail out if deadline is reached.
      if (time() > $deadline) {
        break;
      }

      // Walk the front path one step.
      for ($k1 = -$d + $k1start; $k1 <= $d - $k1end; $k1 += 2) {
        $k1_offset = $v_offset + $k1;

        if ($k1 == -$d || ($k1 != $d && $v1[$k1_offset - 1] < $v1[$k1_offset + 1])) {
          $x1 = $v1[$k1_offset + 1];
        } else {
          $x1 = $v1[$k1_offset - 1] + 1;
        }
        $y1 = $x1 - $k1;

        while ($x1 < $text1_length && $y1 < $text2_length &&
               $text1[$x1] == $text2[$y1]) {
          $x1++;
          $y1++;
        }
        $v1[$k1_offset] = $x1;

        if ($x1 > $text1_length) { // Ran off the right of the graph.
          $k1end += 2;

        } else if ($y1 > $text2_length) { // Ran off the bottom of the graph.
          $k1start += 2;

        } else if ($front) {
          $k2_offset = $v_offset + $delta - $k1;

          if ($k2_offset >= 0 && $k2_offset < $v_length && $v2[$k2_offset] != -1) {
            // Mirror x2 onto top-left coordinate system.
            $x2 = $text1_length - $v2[$k2_offset];

            // Overlap detected.
            if ($x1 >= $x2) {
              return $this->diff_bisectSplit_($text1, $text2, $x1, $y1, $deadline);
            }
          }
        }
      } // end for $k1

      // Walk the reverse path one step.
      for ($k2 = -$d + $k2start; $k2 <= $d - $k2end; $k2 += 2) {
        $k2_offset = $v_offset + $k2;

        if ($k2 == -$d || ($k2 != $d && $v2[$k2_offset - 1] < $v2[$k2_offset + 1])) {
          $x2 = $v2[$k2_offset + 1];
        } else {
          $x2 = $v2[$k2_offset - 1] + 1;
        }
        $y2 = $x2 - $k2;

        while ($x2 < $text1_length && $y2 < $text2_length &&
               $text1[$text1_length - $x2 - 1] ==
               $text2[$text2_length - $y2 - 1]) {
          $x2++;
          $y2++;
        }
        $v2[$k2_offset] = $x2;

        if ($x2 > $text1_length) { // Ran off the left of the graph.
          $k2end += 2;

        } else if ($y2 > $text2_length) { // Ran off the top of the graph.
          $k2start += 2;

        } else if (!$front) {
          $k1_offset = $v_offset + $delta - $k2;

          if ($k1_offset >= 0 && $k1_offset < $v_length && $v1[$k1_offset] != -1) {
            $x1 = $v1[$k1_offset];
            $y1 = $v_offset + $x1 - $k1_offset;

            // Mirror x2 onto top-left coordinate system.
            $x2 = $text1_length - $x2;

            // Overlap detected.
            if ($x1 >= $x2) {
              return $this->diff_bisectSplit_($text1, $text2, $x1, $y1, $deadline);
            }
          }
        }
      } // end for $k2
    } // end for $d

    // Diff took too long and hit the deadline or
    // number of diffs equals number of characters, no commonality at all.
    return [new Diff(DIFF_DELETE, $text1),
            new Diff(DIFF_INSERT, $text2)];
  }
  
  /**
   * Given the location of the 'middle snake', split the diff in two parts
   * and recurse.
   * @param {string} text1 Old string to be diffed.
   * @param {string} text2 New string to be diffed.
   * @param {number} x Index of split point in text1.
   * @param {number} y Index of split point in text2.
   * @param {number} deadline Time at which to bail if not yet complete.
   * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
   * @private
   */
  protected function diff_bisectSplit_($text1, $text2, $x, $y, $deadline) {
    $text1a = substr($text1, 0, $x);
    $text2a = substr($text2, 0, $y);
    $text1b = substr($text1, $x);
    $text2b = substr($text2, $y);

    // Compute both diffs serially.
    $diffs  = $this->diff_main($text1a, $text2a, false, $deadline);
    $diffsb = $this->diff_main($text1b, $text2b, false, $deadline);

    return array_merge([], $diffs, $diffsb);
  }

  /**
   * Split two texts into an array of strings.  Reduce the texts to a string of
   * hashes where each Unicode character represents one line.
   * @param {string} text1 First string.
   * @param {string} text2 Second string.
   * @return {{chars1: string, chars2: string, lineArray: !Array.<string>}}
   *     An object containing the encoded text1, the encoded text2 and
   *     the array of unique strings.
   *     The zeroth element of the array of unique strings is intentionally blank.
   * @private
   */
  protected function diff_linesToChars_($text1, $text2) {
    $lineArray = [];  // e.g. lineArray[4] == 'Hello\n'
    $lineHash  = [];   // e.g. lineHash['Hello\n'] == 4

    // '\x00' is a valid character, but various debuggers don't like it.
    // So we'll insert a junk entry to avoid generating a null character.
    $lineArray[0] = '';

    /**
     * Split a text into an array of strings.  Reduce the texts to a string of
     * hashes where each Unicode character represents one line.
     * Modifies linearray and linehash through being a closure.
     * @param {string} text String to encode.
     * @return {string} Encoded string.
     * @private
     */
    $diff_linesToCharsMunge_ = function($text, $maxLines) use(&$lineArray, &$lineHash) {
      $chars = '';

      // Walk the text, pulling out a substring for each line.
      // text.split('\n') would would temporarily double our memory footprint.
      // Modifying text would create many large strings to garbage collect.
      $lineStart = 0;
      $lineEnd   = -1;

      // Keeping our own length variable is faster than looking it up.
      $lineArrayLength = sizeof($lineArray);

      while ($lineEnd < strlen($text) - 1) {
        $lineEnd = strpos($text, "\n", $lineStart);

        if($lineEnd === false) {
          $lineEnd = strlen($text) - 1;
        }
        $line = substr($text, $lineStart, $lineEnd - $lineStart + 1);

        if(isset($lineHash[$line])) {
          $chars .= chr($lineHash[$line]);

        } else {
          if ($lineArrayLength == $maxLines) {
            // Bail out at 65535 because
            // chr(65536) == chr(0)
            $line    = substr($text, $lineStart);
            $lineEnd = strlen($text);
          }
          $chars .= chr($lineArrayLength);
          $lineHash[$line] = $lineArrayLength;
          $lineArray[$lineArrayLength++] = $line;
        }
        $lineStart = $lineEnd + 1;

      } // end while

      return $chars;
    }; // end function

    // Allocate 2/3rds of the space for text1, the rest for text2.
    $chars1 = $diff_linesToCharsMunge_($text1, 40000);
    $chars2 = $diff_linesToCharsMunge_($text2, 65535);

    return [
      'chars1' => $chars1,
      'chars2' => $chars2,
      'lineArray' => $lineArray
    ];
  }

  /**
   * Rehydrate the text in a diff from a string of line hashes to real lines of
   * text.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @param {!Array.<string>} lineArray Array of unique strings.
   * @private
   */
  public function diff_charsToLines_(&$diffs, $lineArray) {
    for ($i = 0; $i < sizeof($diffs); $i++) {
      $chars = $diffs[$i][1];

      $text = [];
      for ($j = 0; $j < strlen($chars); $j++) {
        $text[$j] = $lineArray[ord($chars[$j])];
      }
      $diffs[$i][1] = implode('', $text);
    }
  }

  /**
   * Determine the common prefix of two strings.
   * @param {string} text1 First string.
   * @param {string} text2 Second string.
   * @return {number} The number of characters common to the start of each
   *     string.
   */
  public function diff_commonPrefix($text1, $text2) {

    // Quick check for common null cases.
    if (!$text1 || !$text2 || $text1[0] != $text2[0]) {
      return 0;
    }
    // Binary search.
    // Performance analysis: https://neil.fraser.name/news/2007/10/09/
    $pointermin = 0;
    $pointermax = min(strlen($text1), strlen($text2));
    $pointermid = $pointermax;
    $pointerstart = 0;

    while ($pointermin < $pointermid) {
      if (substr($text1, $pointerstart, $pointermid - $pointerstart) ==
          substr($text2, $pointerstart, $pointermid - $pointerstart)) {
        $pointermin  = $pointermid;
        $pointerstart = $pointermin;
      } else {
        $pointermax = $pointermid;
      }
      $pointermid = floor(($pointermax - $pointermin) / 2 + $pointermin);
    }

    return $pointermid;
  }

  /**
   * Determine the common suffix of two strings.
   * @param {string} text1 First string.
   * @param {string} text2 Second string.
   * @return {number} The number of characters common to the end of each string.
   */
  public function diff_commonSuffix($text1, $text2) {

    // Quick check for common null cases.
    if (!$text1 || !$text2 || $text1[strlen($text1) - 1] != $text2[strlen($text2) - 1]) {
      return 0;
    }

    // Binary search.
    // Performance analysis: https://neil.fraser.name/news/2007/10/09/
    $pointermin = 0;
    $pointermax = min(strlen($text1), strlen($text2));
    $pointermid = $pointermax;
    $pointerend = 0;

    while ($pointermin < $pointermid) {
      $start1 = strlen($text1) - $pointermid;
      $start2 = strlen($text2) - $pointermid;

      if (substr($text1, $start1, strlen($text1) - $pointerend - $start1) ==
          substr($text2, $start2, strlen($text2) - $pointerend - $start2)) {
        $pointermin = $pointermid;
        $pointerend = $pointermin;
      } else {
        $pointermax = $pointermid;
      }
      $pointermid = floor(($pointermax - $pointermin) / 2 + $pointermin);
    }

    return $pointermid;
  }

  /**
   * Determine if the suffix of one string is the prefix of another.
   * @param {string} text1 First string.
   * @param {string} text2 Second string.
   * @return {number} The number of characters common to the end of the first
   *     string and the start of the second string.
   * @private
   */
  protected function diff_commonOverlap_($text1, $text2) {

    // Cache the text lengths to prevent multiple calls.
    $text1_length = strlen($text1);
    $text2_length = strlen($text2);

    // Eliminate the null case.
    if ($text1_length == 0 || $text2_length == 0) {
      return 0;
    }

    // Truncate the longer string.
    if ($text1_length > $text2_length) {
      $text1 = substr($text1, $text1_length - $text2_length);
    } else if ($text1_length < $text2_length) {
      $text2 = substr($text2, 0, $text1_length);
    }

    $text_length = min($text1_length, $text2_length);

    // Quick check for the worst case.
    if ($text1 == $text2) {
      return $text_length;
    }

    // Start by looking for a single character match
    // and increase length until no match is found.
    // Performance analysis: https://neil.fraser.name/news/2010/11/04/
    $best   = 0;
    $length = 1;
    
    while(true) {
      $pattern = substr($text1, $text_length - $length);
      $found   = strpos($text2, $pattern);

      if ($found === false) {
        return $best;
      }
      $length += $found;
      if ($found == 0 || substr($text1, $text_length - $length) == substr($text2, 0, $length)) {
        $best = $length;
        $length++;
      }
    }
  }

  /**
   * Do the two texts share a substring which is at least half the length of the
   * longer text?
   * This speedup can produce non-minimal diffs.
   * @param {string} text1 First string.
   * @param {string} text2 Second string.
   * @return {Array.<string>} Five element Array, containing the prefix of
   *     text1, the suffix of text1, the prefix of text2, the suffix of
   *     text2 and the common middle.  Or null if there was no match.
   * @private
   */
  protected function diff_halfMatch_($text1, $text2) {

    // Don't risk returning a non-optimal diff if we have unlimited time.
    if (DiffMatchPatch::$Diff_Timeout <= 0) {
      return null;
    }

    $longtext  = strlen($text1) > strlen($text2) ? $text1 : $text2;
    $shorttext = strlen($text1) > strlen($text2) ? $text2 : $text1;
    if (strlen($longtext) < 4 || strlen($shorttext) * 2 < strlen($longtext)) {
      return null;  // Pointless.
    }

    /**
     * Does a substring of shorttext exist within longtext such that the substring
     * is at least half the length of longtext?
     * Closure, but does not reference any external variables.
     * @param {string} longtext Longer string.
     * @param {string} shorttext Shorter string.
     * @param {number} i Start index of quarter length substring within longtext.
     * @return {Array.<string>} Five element Array, containing the prefix of
     *     longtext, the suffix of longtext, the prefix of shorttext, the suffix
     *     of shorttext and the common middle.  Or null if there was no match.
     * @private
     */
    $diff_halfMatchI_ = function($longtext, $shorttext, $i) {

      // Start with a 1/4 length substring at position i as a seed.
      $seed = substr($longtext, $i, floor(strlen($longtext) / 4));
      $j = -1;
      $best_common = '';

      while (($j = strpos($shorttext, $seed, $j + 1)) != -false) {
        $prefixLength = $this->diff_commonPrefix(substr($longtext, $i), substr($shorttext, $j));
        $suffixLength = $this->diff_commonSuffix(substr($longtext, 0, $i), substr($shorttext, 0, $j));

        if (strlen($best_common) < $suffixLength + $prefixLength) {
          $best_common = substr($shorttext, $j - $suffixLength, $suffixLength) .
                          substr($shorttext, $j, $prefixLength);

          $best_longtext_a  = substr($longtext, 0, $i - $suffixLength);
          $best_longtext_b  = substr($longtext, $i + $prefixLength);
          $best_shorttext_a = substr($shorttext, 0, $j - $suffixLength);
          $best_shorttext_b = substr($shorttext, $j + $prefixLength);
        }
      } // end while

      if (strlen($best_common) * 2 >= strlen($longtext)) {
        return [$best_longtext_a, $best_longtext_b,
                $best_shorttext_a, $best_shorttext_b, $best_common];
      } else {
        return null;
      }
    }; // end function

    // First check if the second quarter is the seed for a half-match.
    $hm1 = $diff_halfMatchI_($longtext, $shorttext, ceil(strlen($longtext) / 4));

    // Check again based on the third quarter.
    $hm2 = $diff_halfMatchI_($longtext, $shorttext, ceil(strlen($longtext) / 2));

    if (!$hm1 && !$hm2) {
      return null;
    } else if (!$hm2) {
      $hm = $hm1;
    } else if (!$hm1) {
      $hm = $hm2;
    } else {
      // Both matched.  Select the longest.
      $hm = strlen($hm1[4]) > strlen($hm2[4]) ? $hm1 : $hm2;
    }

    // A half-match was found, sort out the return data.
    if (strlen($text1) > strlen($text2)) {
      $text1_a = $hm[0];
      $text1_b = $hm[1];
      $text2_a = $hm[2];
      $text2_b = $hm[3];
    } else {
      $text2_a = $hm[0];
      $text2_b = $hm[1];
      $text1_a = $hm[2];
      $text1_b = $hm[3];
    }
    $mid_common = $hm[4];

    return [$text1_a, $text1_b, $text2_a, $text2_b, $mid_common];
  }

  /**
   * Reduce the number of edits by eliminating semantically trivial equalities.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   */
  public function diff_cleanupSemantic(&$diffs) {
    $changes = false;
    $equalities = [];       // Stack of indices where equalities are found.
    $equalitiesLength = 0;  // Keeping our own length var is faster in JS.
    $lastEquality = null;   // Always equal to diffs[equalities[equalitiesLength - 1]][1]
    $pointer = 0;           // Index of current position.

    // Number of characters that changed prior to the equality.
    $length_insertions1 = 0;
    $length_deletions1 = 0;

    // Number of characters that changed after the equality.
    $length_insertions2 = 0;
    $length_deletions2 = 0;
    
    while ($pointer < sizeof($diffs)) {

      // Equality found.
      if ($diffs[$pointer][0] == DIFF_EQUAL) {
        $equalities[$equalitiesLength++] = $pointer;
        $length_insertions1 = $length_insertions2;
        $length_deletions1  = $length_deletions2;
        $length_insertions2 = 0;
        $length_deletions2  = 0;
        $lastEquality = $diffs[$pointer][1];

      // An insertion or deletion.
      } else {
        if ($diffs[$pointer][0] == DIFF_INSERT) {
          $length_insertions2 += strlen($diffs[$pointer][1]);
        } else {
          $length_deletions2  += strlen($diffs[$pointer][1]);
        }

        // Eliminate an equality that is smaller or equal to the edits on both sides of it.
        if ($lastEquality
              && (strlen($lastEquality) <= max($length_insertions1, $length_deletions1))
              && (strlen($lastEquality) <= max($length_insertions2, $length_deletions2))) {

          // Duplicate record.
          array_splice($diffs, $equalities[$equalitiesLength - 1], 0, [new Diff(DIFF_DELETE, $lastEquality)]);

          // Change second copy to insert.
          $diffs[$equalities[$equalitiesLength - 1] + 1][0] = DIFF_INSERT;

          // Throw away the equality we just deleted.
          $equalitiesLength--;

          // Throw away the previous equality (it needs to be reevaluated).
          $equalitiesLength--;
          $pointer = $equalitiesLength > 0 ? $equalities[$equalitiesLength - 1] : -1;
          $length_insertions1 = 0;  // Reset the counters.
          $length_deletions1  = 0;
          $length_insertions2 = 0;
          $length_deletions2  = 0;
          $lastEquality = null;
          $changes = true;
        }
      }
      $pointer++;
    } // end while

    // Normalize the diff.
    if ($changes) {
      $this->diff_cleanupMerge($diffs);
    }
    $this->diff_cleanupSemanticLossless($diffs);

    // Find any overlaps between deletions and insertions.
    // e.g: <del>abcxxx</del><ins>xxxdef</ins>
    //   -> <del>abc</del>xxx<ins>def</ins>
    // e.g: <del>xxxabc</del><ins>defxxx</ins>
    //   -> <ins>def</ins>xxx<del>abc</del>
    // Only extract an overlap if it is as big as the edit ahead or behind it.
    $pointer = 1;

    while ($pointer < sizeof($diffs)) {
      if ($diffs[$pointer - 1][0] == DIFF_DELETE && $diffs[$pointer][0] == DIFF_INSERT) {
        $deletion  = $diffs[$pointer - 1][1];
        $insertion = $diffs[$pointer][1];

        $overlap_length1 = $this->diff_commonOverlap_($deletion, $insertion);
        $overlap_length2 = $this->diff_commonOverlap_($insertion, $deletion);

        if ($overlap_length1 >= $overlap_length2) {
          if ($overlap_length1 >= strlen($deletion) / 2 || $overlap_length1 >= strlen($insertion) / 2) {
            // Overlap found.  Insert an equality and trim the surrounding edits.

            array_splice($diffs, $pointer, 0, [new Diff(DIFF_EQUAL, substr($insertion, 0, $overlap_length1))]);
            $diffs[$pointer - 1][1] = substr($deletion, 0, strlen($deletion) - $overlap_length1);
            $diffs[$pointer + 1][1] = substr($insertion, $overlap_length1);
            $pointer++;
          }
        } else {
          if ($overlap_length2 >= strlen($deletion) / 2 || $overlap_length2 >= strlen($insertion) / 2) {
            // Reverse overlap found.
            // Insert an equality and swap and trim the surrounding edits.

            array_splice($diffs, $pointer, 0, [new Diff(DIFF_EQUAL, substr($deletion, 0, $overlap_length2))]);
            $diffs[$pointer - 1][0] = DIFF_INSERT;
            $diffs[$pointer - 1][1] = substr($insertion, 0, strlen($insertion) - $overlap_length2);
            $diffs[$pointer + 1][0] = DIFF_DELETE;
            $diffs[$pointer + 1][1] = substr($deletion, $overlap_length2);
            $pointer++;
          }
        }
        $pointer++;

      } // end if DIFF_DELETE / DIFF_INSERT
      $pointer++;

    } // end while
  }

  /**
   * Look for single edits surrounded on both sides by equalities
   * which can be shifted sideways to align the edit to a word boundary.
   * e.g: The c<ins>at c</ins>ame. -> The <ins>cat </ins>came.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   */
  public function diff_cleanupSemanticLossless(&$diffs) {

    /**
     * Given two strings, compute a score representing whether the internal
     * boundary falls on logical boundaries.
     * Scores range from 6 (best) to 0 (worst).
     * Closure, but does not reference any external variables.
     * @param {string} one First string.
     * @param {string} two Second string.
     * @return {number} The score.
     * @private
     */
    $diff_cleanupSemanticScore_ = function($one, $two) {
      if (!$one || !$two) {
        // Edges are the best.
        return 6;
      }

      // Each port of this function behaves slightly differently due to
      // subtle differences in each language's definition of things like
      // 'whitespace'.  Since this function's purpose is largely cosmetic,
      // the choice has been made to use each language's native features
      // rather than force total conformity.
      $char1 = $one[strlen($one) - 1];
      $char2 = $two[0];

      $nonAlphaNumeric1 = preg_match(DiffMatchPatch::nonAlphaNumericRegex_, $char1);
      $nonAlphaNumeric2 = preg_match(DiffMatchPatch::nonAlphaNumericRegex_, $char2);
      $whitespace1      = $nonAlphaNumeric1 && preg_match(DiffMatchPatch::whitespaceRegex_, $char1);
      $whitespace2      = $nonAlphaNumeric2 && preg_match(DiffMatchPatch::whitespaceRegex_, $char2);
      $lineBreak1       = $whitespace1 && preg_match(DiffMatchPatch::linebreakRegex_, $char1);
      $lineBreak2       = $whitespace2 && preg_match(DiffMatchPatch::linebreakRegex_, $char2);
      $blankLine1       = $lineBreak1 && preg_match(DiffMatchPatch::blanklineEndRegex_, $one);
      $blankLine2       = $lineBreak2 && preg_match(DiffMatchPatch::blanklineStartRegex_, $two);

      // Five points for blank lines.
      if ($blankLine1 || $blankLine2) {
        return 5;

      // Four points for line breaks.
      } else if ($lineBreak1 || $lineBreak2) {
        return 4;

      // Three points for end of sentences.
      } else if ($nonAlphaNumeric1 && !$whitespace1 && $whitespace2) {
        return 3;

      // Two points for whitespace.
      } else if ($whitespace1 || $whitespace2) {
        return 2;

      // One point for non-alphanumeric.
      } else if ($nonAlphaNumeric1 || $nonAlphaNumeric2) {
        return 1;
      }
      return 0;
    };

    $pointer = 1;

    // Intentionally ignore the first and last element (don't need checking).
    while ($pointer < sizeof($diffs) - 1) {
      if ($diffs[$pointer - 1][0] == DIFF_EQUAL
          && $diffs[$pointer + 1][0] == DIFF_EQUAL) {

        // This is a single edit surrounded by equalities.
        $equality1 = $diffs[$pointer - 1][1];
        $edit      = $diffs[$pointer][1];
        $equality2 = $diffs[$pointer + 1][1];

        // First, shift the edit as far left as possible.
        $commonOffset = $this->diff_commonSuffix($equality1, $edit);

        if ($commonOffset) {
          $commonString = substr($edit, strlen($edit) - $commonOffset);
          $equality1    = substr($equality1, 0, strlen($equality1) - $commonOffset);
          $edit         = $commonString . substr($edit, 0, strlen($edit) - $commonOffset);
          $equality2    = $commonString . $equality2;
        }

        // Second, step character by character right, looking for the best fit.
        $bestEquality1 = $equality1;
        $bestEdit      = $edit;
        $bestEquality2 = $equality2;
        $bestScore     = $diff_cleanupSemanticScore_($equality1, $edit)
                          + $diff_cleanupSemanticScore_($edit, $equality2);

        while ($edit[0] === $equality2[0]) {
          $equality1 .= $edit[0];
          $edit       = substr($edit, 1) . $equality2[0];
          $equality2  = substr($equality2, 1);

          $score = $diff_cleanupSemanticScore_($equality1, $edit)
                    + $diff_cleanupSemanticScore_($edit, $equality2);

          // The >= encourages trailing rather than leading whitespace on edits.
          if ($score >= $bestScore) {
            $bestScore     = $score;
            $bestEquality1 = $equality1;
            $bestEdit      = $edit;
            $bestEquality2 = $equality2;
          }
        } // end while $edit[0]
        
        if ($diffs[$pointer - 1][1] != $bestEquality1) {
          // We have an improvement, save it back to the diff.
          if ($bestEquality1) {
            $diffs[$pointer - 1][1] = $bestEquality1;
          } else {
            array_splice($diffs, $pointer - 1, 1);
            $pointer--;
          }

          $diffs[$pointer][1] = $bestEdit;
          if ($bestEquality2) {
            $diffs[$pointer + 1][1] = $bestEquality2;
          } else {
            array_splice($diffs, $pointer + 1, 1);
            $pointer--;
          }
        } // end if != $bestEquality
      } // end if DIFF_EQUAL

      $pointer++;
    } // end while $pointer
  }

  /**
   * Reduce the number of edits by eliminating operationally trivial equalities.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   */
  public function diff_cleanupEfficiency(&$diffs) {
    $changes = false;
    $equalities = [];       // Stack of indices where equalities are found.
    $equalitiesLength = 0;  // Keeping our own length var is faster in JS.
    $lastEquality = null;   // Always equal to diffs[equalities[equalitiesLength - 1]][1]
    $pointer = 0;           // Index of current position.

    $pre_ins = false;       // Is there an insertion operation before the last equality.
    $pre_del = false;       // Is there a deletion operation before the last equality.
    $post_ins = false;      // Is there an insertion operation after the last equality.
    $post_del = false;      // Is there a deletion operation after the last equality.

    while ($pointer < sizeof($diffs)) {

      // Equality found.
      if ($diffs[$pointer][0] == DIFF_EQUAL) {

        // Candidate.
        if (strlen($diffs[$pointer][1]) < DiffMatchPatch::$Diff_EditCost && ($post_ins || $post_del)) {
          $equalities[$equalitiesLength++] = $pointer;
          $pre_ins = $post_ins;
          $pre_del = $post_del;
          $lastEquality = $diffs[$pointer][1];

        // Not a candidate, and can never become one.
        } else {
          $equalitiesLength = 0;
          $lastEquality = null;
        }
        $post_ins = $post_del = false;
      
      // An insertion or deletion.
      } else {  
        if ($diffs[$pointer][0] == DIFF_DELETE) {
          $post_del = true;
        } else {
          $post_ins = true;
        }

        /*
         * Five types to be split:
         * <ins>A</ins><del>B</del>XY<ins>C</ins><del>D</del>
         * <ins>A</ins>X<ins>C</ins><del>D</del>
         * <ins>A</ins><del>B</del>X<ins>C</ins>
         * <ins>A</del>X<ins>C</ins><del>D</del>
         * <ins>A</ins><del>B</del>X<del>C</del>
         */
        if ($lastEquality && (
              ($pre_ins && $pre_del && $post_ins && $post_del)
              || ((strlen($lastEquality) < DiffMatchPatch::$Diff_EditCost / 2)
                && ($pre_ins + $pre_del + $post_ins + $post_del) == 3)
            )) {

          // Duplicate record.
          array_splice($diffs, $equalities[$equalitiesLength - 1], 0, [new Diff(DIFF_DELETE, $lastEquality)]);

          // Change second copy to insert.
          $diffs[$equalities[$equalitiesLength - 1] + 1][0] = DIFF_INSERT;

          // Throw away the equality we just deleted;
          $equalitiesLength--;
          $lastEquality = null;

          // No changes made which could affect previous entry, keep going.
          if ($pre_ins && $pre_del) {
            $post_ins = $post_del = true;
            $equalitiesLength = 0;

          // Throw away the previous equality.
          } else {
            $equalitiesLength--; 
            $pointer = $equalitiesLength > 0 ? $equalities[$equalitiesLength - 1] : -1;
            $post_ins = $post_del = false;
          }
          $changes = true;

        } // end if $lastEquality
      } // end if DIFF_EQUAL

      $pointer++;
    } // end while $pointer

    if ($changes) {
      $this->diff_cleanupMerge($diffs);
    }
  }

  /**
   * Reorder and merge like edit sections.  Merge equalities.
   * Any edit section can move as long as it doesn't cross an equality.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   */
  public function diff_cleanupMerge(&$diffs) {

    // Add a dummy entry at the end.
    array_push($diffs, new Diff(DIFF_EQUAL, ''));

    $pointer = 0;
    $count_delete = 0;
    $count_insert = 0;
    $text_delete  = '';
    $text_insert  = '';

    while ($pointer < sizeof($diffs)) {
      switch ($diffs[$pointer][0]) {
        case DIFF_INSERT:
          $count_insert++;
          $text_insert .= $diffs[$pointer][1];
          $pointer++;
          break;

        case DIFF_DELETE:
          $count_delete++;
          $text_delete .= $diffs[$pointer][1];
          $pointer++;
          break;

        case DIFF_EQUAL:

          // Upon reaching an equality, check for prior redundancies.
          if ($count_delete + $count_insert > 1) {
            if ($count_delete !== 0 && $count_insert !== 0) {

              // Factor out any common prefixies.
              $commonlength = $this->diff_commonPrefix($text_insert, $text_delete);

              if ($commonlength !== 0) {
                if (($pointer - $count_delete - $count_insert) > 0
                    && $diffs[$pointer - $count_delete - $count_insert - 1][0] == DIFF_EQUAL) {

                  $diffs[$pointer - $count_delete - $count_insert - 1][1] .= substr($text_insert, 0, $commonlength);
                } else {
                  array_splice($diffs, 0, 0, [new Diff(DIFF_EQUAL, substr($text_insert, 0, $commonlength))]);
                  $pointer++;
                }
                $text_insert = substr($text_insert, $commonlength);
                $text_delete = substr($text_delete, $commonlength);
              }

              // Factor out any common suffixies.
              $commonlength = $this->diff_commonSuffix($text_insert, $text_delete);
              if ($commonlength !== 0) {
                $diffs[$pointer][1] = substr($text_insert, strlen($text_insert) - $commonlength) . $diffs[$pointer][1];
                $text_insert = substr($text_insert, 0, strlen($text_insert) - $commonlength);
                $text_delete = substr($text_delete, 0, strlen($text_delete) - $commonlength);
              }
            } // end if $count_delete !== 0

            // Delete the offending records and add the merged ones.
            $pointer -= $count_delete + $count_insert;
            array_splice($diffs, $pointer, $count_delete + $count_insert);

            if (strlen($text_delete)) {
              array_splice($diffs, $pointer, 0, [new Diff(DIFF_DELETE, $text_delete)]);
              $pointer++;
            }
            if (strlen($text_insert)) {
              array_splice($diffs, $pointer, 0, [new Diff(DIFF_INSERT, $text_insert)]);
              $pointer++;
            }
            $pointer++;

          } else if ($pointer !== 0 && $diffs[$pointer - 1][0] == DIFF_EQUAL) {
            // Merge this equality with the previous one.
            $diffs[$pointer - 1][1] .= $diffs[$pointer][1];
            array_splice($diffs, $pointer, 1);

          } else {
            $pointer++;
          }

          $count_insert = 0;
          $count_delete = 0;
          $text_delete = '';
          $text_insert = '';
          break;

      } // end switch
    } // end while $pointer

    if ($diffs[sizeof($diffs) - 1][1] === '') {
      array_pop($diffs);  // Remove the dummy entry at the end.
    }

    // Second pass: look for single edits surrounded on both sides by equalities
    // which can be shifted sideways to eliminate an equality.
    // e.g: A<ins>BA</ins>C -> <ins>AB</ins>AC
    $changes = false;
    $pointer = 1;
    // Intentionally ignore the first and last element (don't need checking).
    
    while($pointer < sizeof($diffs) - 1) {

      // Single edit surrounded by equalities.
      if ($diffs[$pointer - 1][0] == DIFF_EQUAL && $diffs[$pointer + 1][0] == DIFF_EQUAL) {

        if (substr($diffs[$pointer][1], strlen($diffs[$pointer][1]) - strlen($diffs[$pointer - 1][1]))
            == $diffs[$pointer - 1][1]) {

          // Shift the edit over the previous equality.
          $diffs[$pointer][1] = $diffs[$pointer - 1][1]
                              . substr($diffs[$pointer][1], 0, strlen($diffs[$pointer][1]) - strlen($diffs[$pointer - 1][1]));

          $diffs[$pointer + 1][1] = $diffs[$pointer - 1][1] . $diffs[$pointer + 1][1];
          array_splice($diffs, $pointer - 1, 1);
          $changes = true;

        } else if (substr($diffs[$pointer][1], 0, strlen($diffs[$pointer + 1][1]))
            == $diffs[$pointer + 1][1]) {

          // Shift the edit over the next equality.
          $diffs[$pointer - 1][1] .= $diffs[$pointer + 1][1];
          $diffs[$pointer][1] = substr($diffs[$pointer][1], strlen($diffs[$pointer + 1][1]))
                              . $diffs[$pointer + 1][1];

          array_splice($diffs, $pointer + 1, 1);
          $changes = true;
        }
      } // end if DIFF_EQUAL

      $pointer++;
    } // end while $pointer

    // If shifts were made, the diff needs reordering and another shift sweep.
    if ($changes) {
      $this->diff_cleanupMerge($diffs);
    }
  }

  /**
   * loc is a location in text1, compute and return the equivalent location in
   * text2.
   * e.g. 'The cat' vs 'The big cat', 1->1, 5->8
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @param {number} loc Location within text1.
   * @return {number} Location within text2.
   */
  public function diff_xIndex($diffs, $loc) {
    $chars1 = 0;
    $chars2 = 0;
    $last_chars1 = 0;
    $last_chars2 = 0;

    for ($x = 0; $x < sizeof($diffs); $x++) {
      if ($diffs[$x][0] !== DIFF_INSERT) {  // Equality or deletion.
        $chars1 += strlen($diffs[$x][1]);
      }
      if ($diffs[$x][0] !== DIFF_DELETE) {  // Equality or insertion.
        $chars2 += strlen($diffs[$x][1]);
      }
      if ($chars1 > $loc) {  // Overshot the location.
        break;
      }
      $last_chars1 = $chars1;
      $last_chars2 = $chars2;
    }

    // Was the location was deleted?
    if (sizeof($diffs) != $x && $diffs[$x][0] === DIFF_DELETE) {
      return $last_chars2;
    }

    // Add the remaining character length.
    return $last_chars2 + ($loc - $last_chars1);
  }

  /**
   * Convert a diff array into a pretty HTML report.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @return {string} HTML representation.
   */
  public function diff_prettyHtml($diffs) {
    $html = [];

    for ($x = 0; $x < sizeof($diffs); $x++) {
      $op   = $diffs[$x][0];    // Operation (insert, delete, equal)
      $text = $diffs[$x][1];  // Text of change.

      $text = str_replace(['&', '<', '>', "\n"], ['&amp;', '&lt;', '&gt;', '&para;<br>'], $text);

      switch ($op) {
        case DIFF_INSERT:
          $html[$x] = '<ins style="background:#e6ffe6;">' . $text . '</ins>';
          break;
        case DIFF_DELETE:
          $html[$x] = '<del style="background:#ffe6e6;">' . $text . '</del>';
          break;
        case DIFF_EQUAL:
          $html[$x] = '<span>' . $text . '</span>';
          break;
      }
    }
    return implode('', $html);
  }

  /**
   * Compute and return the source text (all equalities and deletions).
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @return {string} Source text.
   */
  public function diff_text1($diffs) {
    $text = [];
    for ($x = 0; $x < sizeof($diffs); $x++) {
      if ($diffs[$x][0] !== DIFF_INSERT) {
        $text[$x] = $diffs[$x][1];
      }
    }
    return implode('', $text);
  }

  /**
   * Compute and return the destination text (all equalities and insertions).
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @return {string} Destination text.
   */
  public function diff_text2($diffs) {
    $text = [];
    for ($x = 0; $x < sizeof($diffs); $x++) {
      if ($diffs[$x][0] !== DIFF_DELETE) {
        $text[$x] = $diffs[$x][1];
      }
    }
    return implode('', $text);
  }

  /**
   * Compute the Levenshtein distance; the number of inserted, deleted or
   * substituted characters.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @return {number} Number of changes.
   */
  public function diff_levenshtein($diffs) {
    $levenshtein = 0;
    $insertions = 0;
    $deletions = 0;

    for ($x = 0; $x < sizeof($diffs); $x++) {
      $op   = $diffs[$x][0];
      $data = $diffs[$x][1];

      switch ($op) {
        case DIFF_INSERT:
          $insertions += strlen($data);
          break;
        case DIFF_DELETE:
          $deletions += strlen($data);
          break;
        case DIFF_EQUAL:
          // A deletion and an insertion is one substitution.
          $levenshtein += max($insertions, $deletions);
          $insertions = 0;
          $deletions = 0;
          break;
      }
    }
    $levenshtein += max($insertions, $deletions);
    return $levenshtein;
  }

  /**
   * Crush the diff into an encoded string which describes the operations
   * required to transform text1 into text2.
   * E.g. =3\t-2\t+ing  -> Keep 3 chars, delete 2 chars, insert 'ing'.
   * Operations are tab-separated.  Inserted text is escaped using %xx notation.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   * @return {string} Delta text.
   */
  public function diff_toDelta($diffs) {
    $text = [];
    for ($x = 0; $x < sizeof($diffs); $x++) {
      switch ($diffs[$x][0]) {
        case DIFF_INSERT:
          $text[$x] = '+' . encodeURI($diffs[$x][1]);
          break;
        case DIFF_DELETE:
          $text[$x] = '-' . strlen($diffs[$x][1]);
          break;
        case DIFF_EQUAL:
          $text[$x] = '=' . strlen($diffs[$x][1]);
          break;
      }
    }
    return str_replace('%20', ' ', implode("\t", $text));
  }

  /**
   * Given the original text1, and an encoded string which describes the
   * operations required to transform text1 into text2, compute the full diff.
   * @param {string} text1 Source string for the diff.
   * @param {string} delta Delta text.
   * @return {!Array.<!diff_match_patch.Diff>} Array of diff tuples.
   * @throws {!Error} If invalid input.
   */
  public function diff_fromDelta($text1, $delta) {
    $diffs = [];
    $diffsLength = 0;  // Keeping our own length var is faster in JS.
    $pointer = 0;      // Cursor in text1
    $tokens = explode("\t", $delta);
    
    for ($x = 0; $x < sizeof($tokens); $x++) {
      // Each token begins with a one character parameter which specifies the
      // operation of this token (delete, insert, equality).
      $param = substr($tokens[$x], 1);

      switch ($tokens[$x][0]) {
        case '+':
          try {
            $diffs[$diffsLength++] = new Diff(DIFF_INSERT, urldecode($param));
          } catch (Exception $e) {
            // Malformed URI sequence.
            throw new Exception('Illegal escape in diff_fromDelta: '. $e->getMessage());
          }
          break;

        case '-': // Fall through.
        case '=':
          $n = intval($param, 10);

          if (!is_numeric($param) || $n < 0) {
            throw new Exception('Invalid number in diff_fromDelta: ' . $param);
          }
          $text = substr($text1, $pointer, $n);
          $pointer += $n;

          if ($tokens[$x][0] == '=') {
            $diffs[$diffsLength++] = new Diff(DIFF_EQUAL, $text);
          } else {
            $diffs[$diffsLength++] = new Diff(DIFF_DELETE, $text);
          }
          break;

        default:
          // Blank tokens are ok (from a trailing \t).
          // Anything else is an error.
          if ($tokens[$x]) {
            throw new Exception('Invalid diff operation in diff_fromDelta: ' . $tokens[$x]);
          }
      } // end switch
    } // end for

    if ($pointer != strlen($text1)) {
      throw new Exception('Delta length (' . $pointer . ') does not equal source text length (' . strlen($text1) . ').');
    }
    return $diffs;
  }

  //+------------------------------------------------------
  //|
  //| MATCH FUNCTIONS
  //|
  //+------------------------------------------------------

  /**
   * Locate the best instance of 'pattern' in 'text' near 'loc'.
   * @param {string} text The text to search.
   * @param {string} pattern The pattern to search for.
   * @param {number} loc The location to search around.
   * @return {number} Best match index or -1.
   */
  public function match_main($text, $pattern, $loc) {

    // Check for null inputs.
    if ($text === null || $pattern === null || $loc === null) {
      throw new Exception('Null input. (match_main)');
    }
    $loc = max(0, min($loc, strlen($text)));

    // Shortcut (potentially not guaranteed by the algorithm)
    if ($text == $pattern) {
      return 0;

    // Nothing to match.
    } else if (!strlen($text)) {
      return -1;

    // Perfect match at the perfect spot!  (Includes case of null pattern)
    } else if (substr($text, $loc, strlen($pattern)) == $pattern) {
      return $loc;

    // Do a fuzzy compare.
    } else {
      return $this->match_bitap_($text, $pattern, $loc);
    }
  }

  /**
   * Locate the best instance of 'pattern' in 'text' near 'loc' using the
   * Bitap algorithm.
   * @param {string} text The text to search.
   * @param {string} pattern The pattern to search for.
   * @param {number} loc The location to search around.
   * @return {number} Best match index or -1.
   * @private
   */
  protected function match_bitap_($text, $pattern, $loc) {
    if (strlen($pattern) > DiffMatchPatch::$Match_MaxBits) {
      throw new Error('Pattern too long (> Match_MaxBits).');
    }

    // Initialise the alphabet.
    $s = $this->match_alphabet_($pattern);

    /**
     * Compute and return the score for a match with e errors and x location.
     * Accesses loc and pattern through being a closure.
     * @param {number} e Number of errors in match.
     * @param {number} x Location of match.
     * @return {number} Overall score for match (0.0 = good, 1.0 = bad).
     * @private
     */
    $match_bitapScore_ = function($e, $x) use(&$loc, &$pattern) {
      $accuracy  = $e / strlen($pattern);
      $proximity = abs($loc - $x);

      // Dodge divide by zero error.
      if (!DiffMatchPatch::$Match_Distance) {
        return $proximity ? 1.0 : $accuracy;
      }
      return $accuracy + ($proximity / DiffMatchPatch::$Match_Distance);
    }; // end function

    // Highest score beyond which we give up.
    $score_threshold = DiffMatchPatch::$Match_Threshold;

    // Is there a nearby exact match? (speedup)
    $best_loc = strpos($text, $pattern, $loc);
    if ($best_loc !== false) {
      $score_threshold = min($match_bitapScore_(0, $best_loc), $score_threshold);

      // What about in the other direction? (speedup)
      $best_loc = strrpos($text, $pattern, $loc + strlen($pattern));
      if ($best_loc !== false) {
        $score_threshold = min($match_bitapScore_(0, $best_loc), $score_threshold);
      }
    }

    // Initialise the bit arrays.
    $matchmask = 1 << (strlen($pattern) - 1);
    $best_loc = -1;
    $bin_max  = strlen($pattern) + strlen($text);

    for ($d = 0; $d < strlen($pattern); $d++) {
      // Scan for the best match; each iteration allows for one more error.
      // Run a binary search to determine how far from 'loc' we can stray at this
      // error level.
      $bin_min = 0;
      $bin_mid = $bin_max;

      while ($bin_min < $bin_mid) {
        if ($match_bitapScore_($d, $loc + $bin_mid) <= $score_threshold) {
          $bin_min = $bin_mid;
        } else {
          $bin_max = $bin_mid;
        }
        $bin_mid = floor(($bin_max - $bin_min) / 2 + $bin_min);
      }

      // Use the result from this iteration as the maximum for the next.
      $bin_max = $bin_mid;
      $start  = max(1, $loc - $bin_mid + 1);
      $finish = min($loc + $bin_mid, strlen($text)) + strlen($pattern);

      $rd = array_fill(0, $finish + 2, null);
      $rd[$finish + 1] = (1 << $d) - 1;

      for ($j = $finish; $j >= $start; $j--) {
        // The alphabet (s) is a sparse hash, so the following line generates warnings.
        $charMatch = @$s[$text[$j - 1]];

        // First pass: exact match.
        if ($d === 0) {
          $rd[$j] = (($rd[$j + 1] << 1) | 1) & $charMatch;

        // Subsequent passes: fuzzy match.
        } else {
          $rd[$j] = ((($rd[$j + 1] << 1) | 1) & $charMatch) |
                    ((($last_rd[$j + 1] | $last_rd[$j]) << 1) | 1) |
                    $last_rd[$j + 1];
        }

        if ($rd[$j] & $matchmask) {
          $score = $match_bitapScore_($d, $j - 1);

          // This match will almost certainly be better than any existing match.
          // But check anyway.
          if ($score <= $score_threshold) {
            // Told you so.
            $score_threshold = $score;
            $best_loc = $j - 1;

            // When passing loc, don't exceed our current distance from loc.
            if ($best_loc > $loc) {
              $start = max(1, 2 * $loc - $best_loc);

            // Already passed loc, downhill from here on in.
            } else {
              break;
            }
          }
        } // end if $rd[$j]
      } // end for $j

      // No hope for a (better) match at greater error levels.
      if ($match_bitapScore_($d + 1, $loc) > $score_threshold) {
        break;
      }
      $last_rd = $rd;

    } // end for $d
    return $best_loc;
  }

  /**
   * Initialise the alphabet for the Bitap algorithm.
   * @param {string} pattern The text to encode.
   * @return {!Object} Hash of character locations.
   * @private
   */
  protected function match_alphabet_($pattern) {
    $s = [];
    for ($i = 0; $i < strlen($pattern); $i++) {
      $s[$pattern[$i]] = 0;
    }
    for ($i = 0; $i < strlen($pattern); $i++) {
      $s[$pattern[$i]] |= 1 << (strlen($pattern) - $i - 1);
    }
    return $s;
  }

  //+------------------------------------------------------
  //|
  //| PATCH FUNCTIONS
  //|
  //+------------------------------------------------------

  /**
   * Increase the context until it is unique,
   * but don't let the pattern expand beyond Match_MaxBits.
   * @param {!diff_match_patch.patch_obj} patch The patch to grow.
   * @param {string} text Source text.
   * @private
   */
  protected function patch_addContext_(&$patch, $text) {
    if (!$text) {
      return;
    }
    if ($patch->start2 === null) {
      throw Error('patch not initialized');
    }
    $pattern = substr($text, $patch->start2, $patch->length1);
    $padding = 0;

    // Look for the first and last matches of pattern in text.  If two different
    // matches are found, increase the pattern length.
    while (strpos($text, $pattern) != strrpos($text, $pattern)
          && strlen($pattern) < DiffMatchPatch::$Match_MaxBits
                - DiffMatchPatch::$Patch_Margin - DiffMatchPatch::$Patch_Margin) {

      $padding += DiffMatchPatch::$Patch_Margin;
      $pattern = substr($text, $patch->start2 - $padding,
                               $patch->length1 + $padding + $padding);
    }

    // Add one chunk for good luck.
    $padding += DiffMatchPatch::$Patch_Margin;

    // Add the prefix.
    $prefix = substr($text, $patch->start2 - $padding, $padding);
    if ($prefix) {
      array_unshift($patch->diffs, new Diff(DIFF_EQUAL, $prefix));
    }

    // Add the suffix.
    $suffix = substr($text, $patch->start2 + $patch->length1, $padding);
    if ($suffix) {
      array_push($patch->diffs, new Diff(DIFF_EQUAL, $suffix));
    }

    // Roll back the start points.
    $patch->start1 -= strlen($prefix);
    $patch->start2 -= strlen($prefix);

    // Extend the lengths.
    $patch->length1 += strlen($prefix) + strlen($suffix);
    $patch->length2 += strlen($prefix) + strlen($suffix);
  }

  /**
   * Compute a list of patches to turn text1 into text2.
   * Use diffs if provided, otherwise compute it ourselves.
   * There are four ways to call this function, depending on what data is
   * available to the caller:
   * Method 1:
   * a = text1, b = text2
   * Method 2:
   * a = diffs
   * Method 3 (optimal):
   * a = text1, b = diffs
   * Method 4 (deprecated, use method 3):
   * a = text1, b = text2, c = diffs
   *
   * @param {string|!Array.<!diff_match_patch.Diff>} a text1 (methods 1,3,4) or
   * Array of diff tuples for text1 to text2 (method 2).
   * @param {string|!Array.<!diff_match_patch.Diff>=} opt_b text2 (methods 1,4) or
   * Array of diff tuples for text1 to text2 (method 3) or undefined (method 2).
   * @param {string|!Array.<!diff_match_patch.Diff>=} opt_c Array of diff tuples
   * for text1 to text2 (method 4) or undefined (methods 1,2,3).
   * @return {!Array.<!diff_match_patch.patch_obj>} Array of Patch objects.
   */
  public function patch_make($a, $opt_b = null, $opt_c = null){

    // Method 1: text1, text2
    // Compute diffs from text1 and text2.
    if (gettype($a) == 'string' && gettype($opt_b) == 'string' && $opt_c === null) {
      $text1 = $a;
      $diffs = $this->diff_main($text1, $opt, true);

      if (sizeof($diffs) > 2) {
        $this->diff_cleanupSemantic($diffs);
        $this->diff_cleanupEfficiency($diffs);
      }

    // Method 2: diffs
    // Compute text1 from diffs.
    } else if (gettype($a) == 'array' && $opt_b === null && $opt_c === null) {
      $diffs = $a;
      $text1 = $this->diff_text1($diffs);

    // Method 3: text1, diffs
    } else if (gettype($a) == 'string' && gettype($opt_b) == 'array' && $opt_c === null) {
      $text1 = $a;
      $diffs = $opt_b;

    // Method 4: text1, text2, diffs
    // text2 is not used.
    } else if (gettype($a) == 'string' && gettype($opt_b) == 'string' && gettype($opt_c) == 'array') {
      $text1 = $a;
      $diffs = $opt_c;

    } else {
      throw new Exception('Unknown call format to patch_make.');
    }

    if (sizeof($diffs) === 0) {
      return [];  // Get rid of the null case.
    }

    $patches = [];
    $patch = new Patch();
    $patchDiffLength = 0;  // Keeping our own length var is faster in JS.
    $char_count1 = 0;  // Number of characters into the text1 string.
    $char_count2 = 0;  // Number of characters into the text2 string.

    // Start with text1 (prepatch_text) and apply the diffs until we arrive at
    // text2 (postpatch_text).  We recreate the patches one by one to determine
    // context info.
    $prepatch_text  = $text1;
    $postpatch_text = $text1;

    for ($x = 0; $x < sizeof($diffs); $x++) {
      $diff_type = $diffs[$x][0];
      $diff_text = $diffs[$x][1];

      if (!$patchDiffLength && $diff_type !== DIFF_EQUAL) {
        // A new patch starts here.
        $patch->start1 = $char_count1;
        $patch->start2 = $char_count2;
      }
      
      switch ($diff_type) {
        case DIFF_INSERT:
          $patch->diffs[$patchDiffLength++] = $diffs[$x];
          $patch->length2 += strlen($diff_text);
          $postpatch_text = substr($postpatch_text, 0, $char_count2)
                          . $diff_text . substr($postpatch_text, $char_count2);
          break;

        case DIFF_DELETE:
          $patch->length1 += strlen($diff_text);
          $patch->diffs[$patchDiffLength++] = $diffs[$x];
          $postpatch_text = substr($postpatch_text, 0, $char_count2)
                          . substr($postpatch_text, $char_count2 + strlen($diff_text));
          break;

        case DIFF_EQUAL:

          // Small equality inside a patch.
          if (strlen($diff_text) <= 2 * DiffMatchPatch::$Patch_Margin
              && $patchDiffLength
              && sizeof($diffs) != $x + 1) {

            $patch->diffs[$patchDiffLength++] = $diffs[$x];
            $patch->length1 += strlen($diff_text);
            $patch->length2 += strlen($diff_text);

          // Time for a new patch.
          } else if (strlen($diff_text) >= 2 * DiffMatchPatch::$Patch_Margin
              && $patchDiffLength) {

              $this->patch_addContext_($patch, $prepatch_text);
              array_push($patches, $patch);

              $patch = new Patch();
              $patchDiffLength = 0;

              // Unlike Unidiff, our patch lists have a rolling context.
              // https://github.com/google/diff-match-patch/wiki/Unidiff
              // Update prepatch text & pos to reflect the application of the
              // just completed patch.
              $prepatch_text = $postpatch_text;
              $char_count1 = $char_count2;
          }
          break;
      } // end switch

      // Update the current character count.
      if ($diff_type !== DIFF_INSERT) {
        $char_count1 += strlen($diff_text);
      }
      if ($diff_type !== DIFF_DELETE) {
        $char_count2 += strlen($diff_text);
      }
    } // end for $x

    // Pick up the leftover patch if not empty.
    if ($patchDiffLength) {
      $this->patch_addContext_($patch, $prepatch_text);
      array_push($patches, $patch);
    }
    return $patches;
  }

  /**
   * Given an array of patches, return another array that is identical.
   * @param {!Array.<!diff_match_patch.patch_obj>} patches Array of Patch objects.
   * @return {!Array.<!diff_match_patch.patch_obj>} Array of Patch objects.
   */
  public function patch_deepCopy($patches) {
    $patchesCopy = [];

    for ($x = 0; $x < sizeof($patches); $x++) {
      $patch     = $patches[$x];
      $patchCopy = new Patch();
      $patchCopy->diffs = [];

      for ($y = 0; $y < sizeof($patch->diffs); $y++) {
        $patchCopy->diffs[$y] = new Diff($patch->diffs[$y][0], $patch->diffs[$y][1]);
      }
      $patchCopy->start1  = $patch->start1;
      $patchCopy->start2  = $patch->start2;
      $patchCopy->length1 = $patch->length1;
      $patchCopy->length2 = $patch->length2;
      $patchesCopy[$x] = $patchCopy;
    }
    return $patchesCopy;
  }

  /**
   * Merge a set of patches onto the text.  Return a patched text, as well
   * as a list of true/false values indicating which patches were applied.
   * @param {!Array.<!diff_match_patch.patch_obj>} patches Array of Patch objects.
   * @param {string} text Old text.
   * @return {!Array.<string|!Array.<boolean>>} Two element Array, containing the
   *      new text and an array of boolean values.
   */
  public function patch_apply($patches, $text) {
    if (sizeof($patches) == 0) {
      return [$text, []];
    }

    // Deep copy the patches so that no changes are made to originals.
    $patches = $this->patch_deepCopy($patches);
  
    $nullPadding = $this->patch_addPadding($patches);
    $text = $nullPadding . $text . $nullPadding;

    $this->patch_splitMax($patches);

    // delta keeps track of the offset between the expected and actual location
    // of the previous patch.  If there are patches expected at positions 10 and
    // 20, but the first patch was found at 12, delta is 2 and the second patch
    // has an effective expected position of 22.
    $delta = 0;
    $results = [];

    for ($x = 0; $x < sizeof($patches); $x++) {
      $expected_loc = $patches[$x]->start2 + $delta;
      $text1 = $this->diff_text1($patches[$x]->diffs);
      $end_loc = -1;

      if (strlen($text1) > DiffMatchPatch::$Match_MaxBits) {
        // patch_splitMax will only provide an oversized pattern in the case of a monster delete.
        $start_loc = $this->match_main($text, substr($text1, 0, DiffMatchPatch::$Match_MaxBits), $expected_loc);

        if ($start_loc != -1) {
          $end_loc = $this->match_main($text,
                      substr($text1, strlen($text1) - DiffMatchPatch::$Match_MaxBits),
                      $expected_loc + strlen($text1) - DiffMatchPatch::$Match_MaxBits);

          // Can't find valid trailing context.  Drop this patch.
          if ($end_loc == -1 || $start_loc >= $end_loc) {
            $start_loc = -1;
          }
        }
      } else {
        $start_loc = $this->match_main($text, $text1, $expected_loc);
      }

      // No match found.  :(
      if ($start_loc == -1) {
        $results[$x] = false;

        // Subtract the delta for this failed patch from subsequent patches.
        $delta -= $patches[$x]->length2 - $patches[$x]->length1;

      // Found a match.  :)
      } else {
        $results[$x] = true;
        $delta = $start_loc - $expected_loc;

        if ($end_loc == -1) {
          $text2 = substr($text, $start_loc, strlen($text1));
        } else {
          $text2 = substr($text, $start_loc, $end_loc - $start_loc + DiffMatchPatch::$Match_MaxBits);
        }

        // Perfect match, just shove the replacement text in.
        if ($text1 == $text2) {
          $text = substr($text, 0, $start_loc)
                . $this->diff_text2($patches[$x]->diffs)
                . substr($text, $start_loc + strlen($text1));

        // Imperfect match.  Run a diff to get a framework of equivalent indices.
        } else {
          $diffs = $this->diff_main($text1, $text2, false);

          // The end points match, but the content is unacceptably bad.
          if (strlen($text1) > DiffMatchPatch::$Match_MaxBits
              && $this->diff_levenshtein($diffs) / strlen($text1) > DiffMatchPatch::$Patch_DeleteThreshold) {
            $results[$x] = false;
          } else {
            $this->diff_cleanupSemanticLossless($diffs);
            $index1 = 0;

            for ($y = 0; $y < sizeof($patches[$x]->diffs); $y++) {
              $mod = $patches[$x]->diffs[$y];
              if ($mod[0] !== DIFF_EQUAL) {
                $index2 = $this->diff_xIndex($diffs, $index1);
              }

              // Insertion
              if ($mod[0] === DIFF_INSERT) {
                $text = substr($text, 0, $start_loc + $index2)
                      . $mod[1]
                      . substr($text, $start_loc + $index2);

              // Deletion
              } else if ($mod[0] === DIFF_DELETE) {
                $text = substr($text, 0, $start_loc + $index2)
                      . substr($text, $start_loc + $this->diff_xIndex($diffs, $index1 + strlen($mod[1])));
              }

              if ($mod[0] !== DIFF_DELETE) {
                $index1 += strlen($mod[1]);
              }
            } // end for $y
          } // end if strlen($text) > Match_MaxBits
        } // end if $text1 == $text2
      } // end else $start_loc == -1
    } // end for $x

    // Strip the padding off.
    $text = substr($text, strlen($nullPadding), strlen($text) - strlen($nullPadding) - strlen($nullPadding));
    return [$text, $results];
  }

  /**
   * Add some padding on text start and end so that edges can match something.
   * Intended to be called only from within patch_apply.
   * @param {!Array.<!diff_match_patch.patch_obj>} patches Array of Patch objects.
   * @return {string} The padding string added to each side.
   */
  public function patch_addPadding($patches) {
    $paddingLength = DiffMatchPatch::$Patch_Margin;
    $nullPadding   = '';

    for ($x = 1; $x <= $paddingLength; $x++) {
      $nullPadding .= chr($x);
    }

    // Bump all the patches forward.
    for ($x = 0; $x < sizeof($patches); $x++) {
      $patches[$x]->start1 += $paddingLength;
      $patches[$x]->start2 += $paddingLength;
    }

    // Add some padding on start of first diff.
    $patch = $patches[0];
    $diffs = $patch->diffs;

    // Add nullPadding equality.
    if (sizeof($diffs) == 0 || $diffs[0][0] != DIFF_EQUAL) {
      array_unshift($diffs, new Diff(DIFF_EQUAL, $nullPadding));

      $patch->start1 -= $paddingLength;  // Should be 0.
      $patch->start2 -= $paddingLength;  // Should be 0.
      $patch->length1 += $paddingLength;
      $patch->length2 += $paddingLength;

    // Grow first equality.
    } else if ($paddingLength > strlen($diffs[0][1])) {
      $extraLength = $paddingLength - strlen($diffs[0][1]);
      $diffs[0][1] = substr($nullPadding, strlen($diffs[0][1])) . $diffs[0][1];

      $patch->start1 -= $extraLength;
      $patch->start2 -= $extraLength;
      $patch->length1 += $extraLength;
      $patch->length2 += $extraLength;
    }

    // Add some padding on end of last diff.
    $patch = $patches[sizeof($patches) - 1];
    $diffs = $patch->diffs;

    // Add nullPadding equality.
    if (sizeof($diffs) == 0 || $diffs[sizeof($diffs) - 1][0] != DIFF_EQUAL) {
      array_push($diffs, new Diff(DIFF_EQUAL, $nullPadding));

      $patch->length1 += $paddingLength;
      $patch->length2 += $paddingLength;

    // Grow last equality.
    } else if ($paddingLength > strlen($diffs[sizeof($diffs) - 1][1])) {
      $extraLength = $paddingLength - strlen($diffs[sizeof($diffs) - 1][1]);
      $diffs[sizeof($diffs) - 1][1] .= substr($nullPadding, 0, $extraLength);

      $patch->length1 += $extraLength;
      $patch->length2 += $extraLength;
    }
    return $nullPadding;
  }

  /**
   * Look through the patches and break up any which are longer than the maximum
   * limit of the match algorithm.
   * Intended to be called only from within patch_apply.
   * @param {!Array.<!diff_match_patch.patch_obj>} patches Array of Patch objects.
   */
  public function patch_splitMax(&$patches) {
    $patch_size = DiffMatchPatch::$Match_MaxBits;
    
    for ($x = 0; $x < sizeof($patches); $x++) {
      if ($patches[$x]->length1 <= $patch_size) {
        continue;
      }
      $bigpatch = $patches[$x];

      // Remove the big old patch.
      array_splice($patches, $x--, 1);

      $start1 = $bigpatch->start1;
      $start2 = $bigpatch->start2;
      $precontext = '';

      // Create one of several smaller patches.
      while (sizeof($bigpatch->diffs) !== 0) {
        $patch = new Patch();
        $empty = true;
        $patch->start1 = $start1 - strlen($precontext);
        $patch->start2 = $start2 - strlen($precontext);

        if ($precontext !== '') {
          $patch->length1 = $patch->length2 = strlen($precontext);
          array_push($patch->diffs, new Diff(DIFF_EQUAL, $precontext));
        }
        while (sizeof($bigpatch->diffs) !== 0
          && $patch->length1 < $patch_size - DiffMatchPatch::$Patch_Margin) {
            
          $diff_type = $bigpatch->diffs[0][0];
          $diff_text = $bigpatch->diffs[0][1];

          // Insertions are harmless.
          if ($diff_type === DIFF_INSERT) {
            $patch->length2 += strlen($diff_text);
            $start2 += strlen($diff_text);

            array_push($patch->diffs, array_shift($bigpatch->diffs));
            $empty = false;

          // This is a large deletion.  Let it pass in one chunk.
          } else if ($diff_type === DIFF_DELETE
            && sizeof($patch->diffs) == 1
            && $patch->diffs[0][0] == DIFF_EQUAL
            && strlen($diff_text) > 2 * $patch_size) {

            $patch->length1 += strlen($diff_text);
            $start1 += strlen($diff_text);
            $empty = false;
            array_push($patch->diffs, new Diff($diff_type, $diff_text));
            array_shift($bigpatch->diffs);

          // Deletion or equality.  Only take as much as we can stomach.
          } else {
            $diff_text = substr($diff_text, 0, $patch_size - $patch->length1 - DiffMatchPatch::$Patch_Margin);
            $patch->length1 += strlen($diff_text);
            $start1 += strlen($diff_text);

            if ($diff_type === DIFF_EQUAL) {
              $patch->length2 += strlen($diff_text);
              $start2 += strlen($diff_text);
            } else {
              $empty = false;
            }

            array_push($patch->diffs, new Diff($diff_type, $diff_text));
            if ($diff_text == $bigpatch->diffs[0][1]) {
              array_shift($bigpatch->diffs);
            } else {
              $bigpatch->diffs[0][1] = substr($bigpatch->diffs[0][1], strlen($diff_text));
            }
          }
        } // end while 

        // Compute the head context for the next patch.
        $precontext = $this->diff_text2($patch->diffs);
        $precontext = substr($precontext, strlen($precontext) - DiffMatchPatch::$Patch_Margin);

        // Append the end context for this patch.
        $postcontext = substr($this->diff_text1($bigpatch->diffs), 0, DiffMatchPatch::$Patch_Margin);

        if ($postcontext !== '') {
          $patch->length1 += strlen($postcontext);
          $patch->length2 += strlen($postcontext);

          if (sizeof($patch->diffs) !== 0 && $patch->diffs[sizeof($patch->diffs) - 1][0] === DIFF_EQUAL) {
            $patch->diffs[sizeof($patch->diffs) - 1][1] .= $postcontext;
          } else {
            array_push($patch->diffs, new Diff(DIFF_EQUAL, $postcontext));
          }
        }

        if (!$empty) {
          array_splice($patches, ++$x, 0, $patch);
        }

      } // end while $bigpatch->diffs
    } // end for $x
  }

  /**
   * Take a list of patches and return a textual representation.
   * @param {!Array.<!diff_match_patch.patch_obj>} patches Array of Patch objects.
   * @return {string} Text representation of patches.
   */
  public function patch_toText($patches) {
    $text = [];
    for ($x = 0; $x < sizeof($patches); $x++) {
      $text[$x] = $patches[$x];
    }
    return implode('', $text);
  }

  /**
   * Parse a textual representation of patches and return a list of Patch objects.
   * @param {string} textline Text representation of patches.
   * @return {!Array.<!diff_match_patch.patch_obj>} Array of Patch objects.
   * @throws {!Error} If invalid input.
   */
  public function patch_fromText($textline) {
    if (!$textline) {
      return [];
    }
    $patches = [];
    $text = explode("\n", $textline);
    $textPointer = 0;
    $patchHeader = "/^@@ -(\\d+),?(\\d*) \\+(\\d+),?(\\d*) @@$/";

    while ($textPointer < sizeof($text)) {
      if (!preg_match($patchHeader, $text[$textPointer], $m)) {
        throw new Exception('Invalid patch string: ' . $text[$textPointer]);
      }

      $patch = new Patch();
      array_push($patches, $patch);

      $patch->start1 = intval($m[1], 10);
      if (!isset($m[2])) {
        $patch->start1--;
        $patch->length1 = 1;
      } else if ($m[2] == '0') {
        $patch->length1 = 0;
      } else {
        $patch->start1--;
        $patch->length1 = intval($m[2], 10);
      }

      $patch->start2 = intval($m[3], 10);
      if (!isset($m[4])) {
        $patch->start2--;
        $patch->length2 = 1;
      } else if ($m[4] == '0') {
        $patch->length2 = 0;
      } else {
        $patch->start2--;
        $patch->length2 = intval($m[4], 10);
      }

      $textPointer++;
      while ($textPointer < sizeof($text)) {
        $sign = $text[$textPointer][0];

        try {
          $line = urldecode(substr($text[$textPointer], 1));
        } catch (Exception $ex) {
          // Malformed URI sequence.
          throw new Error('Illegal escape in patch_fromText: ' . $line);
        }

        // Deletion.
        if ($sign == '-') {
          array_push($patch->diffs, new Diff(DIFF_DELETE, $line));

        // Insertion.
        } else if ($sign == '+') {
          array_push($patch->diffs, new Diff(DIFF_INSERT, $line));

        // Minor equality.
        } else if ($sign == ' ') {
          array_push($patch->diffs, new Diff(DIFF_EQUAL, $line));

        // Start of next patch.
        } else if ($sign == '@') {
          break;

        // Blank line? Whatever.
        } else if ($sign === '') {

        // WTF?
        } else {
          throw new Exception('Invalid patch mode "' . $sign . '" in: ' . $line);
        }
        $textPointer++;

      } // end while $textPointer (nested)
    } // end while $textPointer
    return $patches;
  }
}

/**
 * Class representing one patch operation.
 * @constructor
 */
class Patch {

  /** @var Array.<!diff_match_patch.Diff> */
  public $diffs = [];

  /** @var number */
  public $start1 = null;

  /** @var number */
  public $start2 = null;

  /** @var number */
  public $length1 = 0;

  /** @var number */
  public $length2 = 0;

  /**
   * Emulate GNU diff's format.
   * Header: @@ -382,8 +481,9 @@
   * Indices are printed as 1-based, not 0-based.
   * @return {string} The GNU diff string.
   */
  public function __toString() {
    if ($this->length1 === 0) {
      $coords1 = $this->start1 . ',0';
    } else if ($this->length1 == 1) {
      $coords1 = $this->start1 + 1;
    } else {
      $coords1 = ($this->start1 + 1) . ',' . $this->length1;
    }

    if ($this->length2 === 0) {
      $coords2 = $this->start2 + ',0';
    } else if ($this->length2 == 1) {
      $coords2 = $this->start2 + 1;
    } else {
      $coords2 = ($this->start2 + 1) . ',' . $this->length2;
    }

    $text = ['@@ -' . $coords1 . ' +' . $coords2 . ' @@' . "\n"];

    // Escape the body of the patch with %xx notation.
    for ($x = 0; $x < sizeof($this->diffs); $x++) {
      switch ($this->diffs[$x][0]) {
        case DIFF_INSERT: $op = '+'; break;
        case DIFF_DELETE: $op = '-'; break;
        case DIFF_EQUAL:  $op = ' '; break;
      }
      $text[$x + 1] = $op . encodeURI($this->diffs[$x][1]) . "\n";
    }
    return str_replace('%20', ' ', implode('', $text));
  }
}
