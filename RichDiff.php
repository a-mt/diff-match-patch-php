<?php
require_once __DIR__ . '/DiffMatchPatch.php';

/**
 * Locate the position of a subarray in a given array
 * @param array $haystack
 * @param array $needle
 * @param integer $start
 * @return integer|false
 */
function arrpos($haystack, $needle, $start = 0) {
  if(!$needle || sizeof($needle) > sizeof($haystack)) {
    return false;
  }
  if($start) {
    $haystack = array_slice($haystack, $start);
  }
  $k = array_search($needle[0], $haystack, true);
  if($k === false) {
    return false;
  }
  for($i=1; $i<sizeof($needle); $i++) {
    if($needle[$i] != @$haystack[$i+$k]) {
      return false;
    }
  }
  return $k+$start;
}

//+------------------------------------------------------
//|
//| END HELPERS
//|
//+------------------------------------------------------

/**
 * Class containing the diff, match and patch methods.
 * @constructor
 */
class DiffArray extends DiffMatchPatch {

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
    $commonprefix = array_slice($text1, 0, $commonlength);

    $text1 = array_slice($text1, $commonlength);
    $text2 = array_slice($text2, $commonlength);

    // Trim off common suffix (speedup).
    $commonlength = $this->diff_commonSuffix($text1, $text2);
    $commonsuffix = array_slice($text1, sizeof($text1) - $commonlength);
    
    $text1 = array_slice($text1, 0, sizeof($text1) - $commonlength);
    $text2 = array_slice($text2, 0, sizeof($text2) - $commonlength);

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

    $longtext = sizeof($text1) > sizeof($text2) ? $text1 : $text2;
    $shorttext = sizeof($text1) > sizeof($text2) ? $text2 : $text1;
    $i = arrpos($longtext, $shorttext);

    if($i !== false) {
      // Shorter text is inside the longer text (speedup).
      $diffs = [new Diff(DIFF_INSERT, array_slice($longtext, 0, $i)),
                new Diff(DIFF_EQUAL, $shorttext),
                new Diff(DIFF_INSERT, array_slice($longtext, $i + sizeof($shorttext)))];
      
      // Swap insertions for deletions if diff is reversed.
      if (sizeof($text1) > sizeof($text2)) {
        $diffs[0][0] = $diffs[2][0] = DIFF_DELETE;
      }
      return $diffs;
    }
  
    if (sizeof($shorttext) == 1) {
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
    return $this->diff_bisect_($text1, $text2, $deadline);
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
    $text1_length = sizeof($text1);
    $text2_length = sizeof($text2);

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
    $text1a = array_slice($text1, 0, $x);
    $text2a = array_slice($text2, 0, $y);
    $text1b = array_slice($text1, $x);
    $text2b = array_slice($text2, $y);

    // Compute both diffs serially.
    $diffs  = $this->diff_main($text1a, $text2a, false, $deadline);
    $diffsb = $this->diff_main($text1b, $text2b, false, $deadline);

    return array_merge([], $diffs, $diffsb);
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
    $pointermax = min(sizeof($text1), sizeof($text2));
    $pointermid = $pointermax;
    $pointerstart = 0;

    while ($pointermin < $pointermid) {
      if (array_slice($text1, $pointerstart, $pointermid - $pointerstart) ==
          array_slice($text2, $pointerstart, $pointermid - $pointerstart)) {
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
    if (!$text1 || !$text2 || $text1[sizeof($text1) - 1] != $text2[sizeof($text2) - 1]) {
      return 0;
    }

    // Binary search.
    // Performance analysis: https://neil.fraser.name/news/2007/10/09/
    $pointermin = 0;
    $pointermax = min(sizeof($text1), sizeof($text2));
    $pointermid = $pointermax;
    $pointerend = 0;

    while ($pointermin < $pointermid) {
      $start1 = sizeof($text1) - $pointermid;
      $start2 = sizeof($text2) - $pointermid;

      if (array_slice($text1, $start1, sizeof($text1) - $pointerend - $start1) ==
          array_slice($text2, $start2, sizeof($text2) - $pointerend - $start2)) {
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

    $longtext  = sizeof($text1) > sizeof($text2) ? $text1 : $text2;
    $shorttext = sizeof($text1) > sizeof($text2) ? $text2 : $text1;
    if (sizeof($longtext) < 4 || sizeof($shorttext) * 2 < sizeof($longtext)) {
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
      $seed = array_slice($longtext, $i, floor(sizeof($longtext) / 4));
      $j = -1;
      $best_common = [];

      while (($j = arrpos($shorttext, $seed, $j + 1)) != -false) {
        $prefixLength = $this->diff_commonPrefix(array_slice($longtext, $i), array_slice($shorttext, $j));
        $suffixLength = $this->diff_commonSuffix(array_slice($longtext, 0, $i), array_slice($shorttext, 0, $j));

        if (sizeof($best_common) < $suffixLength + $prefixLength) {
          $best_common = array_merge(array_slice($shorttext, $j - $suffixLength, $suffixLength),
                          array_slice($shorttext, $j, $prefixLength));

          $best_longtext_a  = array_slice($longtext, 0, $i - $suffixLength);
          $best_longtext_b  = array_slice($longtext, $i + $prefixLength);
          $best_shorttext_a = array_slice($shorttext, 0, $j - $suffixLength);
          $best_shorttext_b = array_slice($shorttext, $j + $prefixLength);
        }
      } // end while

      if (sizeof($best_common) * 2 >= sizeof($longtext)) {
        return [$best_longtext_a, $best_longtext_b,
                $best_shorttext_a, $best_shorttext_b, $best_common];
      } else {
        return null;
      }
    }; // end function

    // First check if the second quarter is the seed for a half-match.
    $hm1 = $diff_halfMatchI_($longtext, $shorttext, ceil(sizeof($longtext) / 4));

    // Check again based on the third quarter.
    $hm2 = $diff_halfMatchI_($longtext, $shorttext, ceil(sizeof($longtext) / 2));

    if (!$hm1 && !$hm2) {
      return null;
    } else if (!$hm2) {
      $hm = $hm1;
    } else if (!$hm1) {
      $hm = $hm2;
    } else {
      // Both matched.  Select the longest.
      $hm = sizeof($hm1[4]) > sizeof($hm2[4]) ? $hm1 : $hm2;
    }

    // A half-match was found, sort out the return data.
    if (sizeof($text1) > sizeof($text2)) {
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
   * Reorder and merge like edit sections.  Merge equalities.
   * Any edit section can move as long as it doesn't cross an equality.
   * @param {!Array.<!diff_match_patch.Diff>} diffs Array of diff tuples.
   */
  public function diff_cleanupMerge(&$diffs) {

    // Add a dummy entry at the end.
    array_push($diffs, new Diff(DIFF_EQUAL, ['']));

    $pointer = 0;
    $count_delete = 0;
    $count_insert = 0;
    $text_delete  = [];
    $text_insert  = [];

    while ($pointer < sizeof($diffs)) {
      switch ($diffs[$pointer][0]) {
        case DIFF_INSERT:
          $count_insert++;
          $text_insert = array_merge($text_insert, $diffs[$pointer][1]);
          $pointer++;
          break;

        case DIFF_DELETE:
          $count_delete++;
          $text_delete = array_merge($text_delete, $diffs[$pointer][1]);
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

                  $diffs[$pointer - $count_delete - $count_insert - 1][1] .= array_slice($text_insert, 0, $commonlength);
                } else {
                  array_splice($diffs, 0, 0, [new Diff(DIFF_EQUAL, array_slice($text_insert, 0, $commonlength))]);
                  $pointer++;
                }
                $text_insert = array_slice($text_insert, $commonlength);
                $text_delete = array_slice($text_delete, $commonlength);
              }

              // Factor out any common suffixies.
              $commonlength = $this->diff_commonSuffix($text_insert, $text_delete);
              if ($commonlength !== 0) {
                $diffs[$pointer][1] = array_slice($text_insert, sizeof($text_insert) - $commonlength) . $diffs[$pointer][1];
                $text_insert = array_slice($text_insert, 0, sizeof($text_insert) - $commonlength);
                $text_delete = array_slice($text_delete, 0, sizeof($text_delete) - $commonlength);
              }
            } // end if $count_delete !== 0

            // Delete the offending records and add the merged ones.
            $pointer -= $count_delete + $count_insert;
            array_splice($diffs, $pointer, $count_delete + $count_insert);

            if (sizeof($text_delete)) {
              array_splice($diffs, $pointer, 0, [new Diff(DIFF_DELETE, $text_delete)]);
              $pointer++;
            }
            if (sizeof($text_insert)) {
              array_splice($diffs, $pointer, 0, [new Diff(DIFF_INSERT, $text_insert)]);
              $pointer++;
            }
            $pointer++;

          } else if ($pointer !== 0 && $diffs[$pointer - 1][0] == DIFF_EQUAL) {
            // Merge this equality with the previous one.
            $diffs[$pointer - 1][1] = array_merge($diffs[$pointer - 1][1], $diffs[$pointer][1]);
            array_splice($diffs, $pointer, 1);

          } else {
            $pointer++;
          }

          $count_insert = 0;
          $count_delete = 0;
          $text_delete = [];
          $text_insert = [];
          break;

      } // end switch
    } // end while $pointer

    if ($diffs[sizeof($diffs) - 1][1] === ['']) {
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

        if (array_slice($diffs[$pointer][1], sizeof($diffs[$pointer][1]) - sizeof($diffs[$pointer - 1][1]))
            == $diffs[$pointer - 1][1]) {

          // Shift the edit over the previous equality.
          $diffs[$pointer][1] = array_merge($diffs[$pointer - 1][1],
                                array_slice($diffs[$pointer][1], 0, sizeof($diffs[$pointer][1]) - sizeof($diffs[$pointer - 1][1])));

          $diffs[$pointer + 1][1] = array_merge($diffs[$pointer - 1][1], $diffs[$pointer + 1][1]);
          array_splice($diffs, $pointer - 1, 1);
          $changes = true;

        } else if (array_slice($diffs[$pointer][1], 0, sizeof($diffs[$pointer + 1][1]))
            == $diffs[$pointer + 1][1]) {

          // Shift the edit over the next equality.
          $diffs[$pointer - 1][1] = array_merge($diffs[$pointer - 1][1], $diffs[$pointer + 1][1]);
          $diffs[$pointer][1] = array_merge(array_slice($diffs[$pointer][1], sizeof($diffs[$pointer + 1][1])),
                                 $diffs[$pointer + 1][1]);

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
}

//+------------------------------------------------------
//|
//| RICH DIFF
//|
//+------------------------------------------------------

class Tag {
  public $txt = '';
  public $tag = '';
  public $changes = '';

  public function __construct($txt, $tag='') {
    $this->txt = $txt;
    $this->tag = $tag;
  }
  public function __toString() {
    return $this->txt;
  }
}

class RichDiff extends DiffArray{

  /**
   * @param string $html
   * @return array
   */
  protected function get_tags($html) {
    try {
      $tags = [];

      $doc = new DomDocument();
      $doc->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>', LIBXML_NOWARNING | LIBXML_NOERROR);
      $body = $doc->documentElement->firstChild;

      if ($body->hasChildNodes()) {
        foreach ($body->childNodes as $child) {
          $txt = trim($doc->saveHtml($child), "\n");

          if(!$txt) {
              continue;
          }

          // Keep tags as-is (ie <p>)
          if($child->nodeType == 1) {
            $tags[] = new Tag($txt, $child->tagName);

          // Split text nodes into words
          // Ignore meta tags (comments, preprocessing instructions, etc)
          } else if($child->nodeType < 7) {
            while(preg_match('/\s+/', $txt, $m, PREG_OFFSET_CAPTURE)) {
              $k = $m[0][1];
              $l = strlen($m[0][0]);
    
              if($k) {
                $tags[] = new Tag(substr($txt, 0, $k));
              }
              $tags[] = new Tag(substr($txt, $k, $l));
              $txt = substr($txt, $k+$l);
            }
            if($txt) {
              $tags[] = new Tag($txt);
            }
          }
        }
      }
      return $tags;
  
    } catch(Exception $e) {
      return [$html];
    }
  }

  /**
   * @param Tag $left
   * @param Tag $right
   * @return boolean
   */
  protected function are_similar_tr($left, $right) {
    $arrLeft  = preg_split('/<\/?t[^>]+>\n?/', $left->txt, null, PREG_SPLIT_NO_EMPTY);
    $arrRight = preg_split('/<\/?t[^>]+>\n?/', $right->txt, null, PREG_SPLIT_NO_EMPTY);

    if(sizeof($arrLeft) != sizeof($arrRight)) {
      return false;
    }
    $n = sizeof($arrLeft);
    $threshold = $n/2;
    $c = 0;

    for($i=0; $i<$n; $i++) {
      if($this->are_similar($arrLeft[$i], $arrRight[$i])) {
        $c++;
        if($c >= $threshold) {
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Checks if two strings are at least 50% similar
   * @param Tag|string $left
   * @param Tag|string $right
   * @return boolean
   */
  protected function are_similar($left, $right) {
    if($left == $right) {
      return true;
    }
    if(gettype($left) == 'object') {
      if(!$left->tag || $left->tag != $right->tag) {
        return false;
      }
      if($left->tag == 'td' || $left->tag == 'th') {
        return true;
      }
      if($left->tag == 'tr') {
        return $this->are_similar_tr($left, $right);
      }
    }
    $left  = strip_tags($left);
    $right = strip_tags($right);

    if(!$left || !$right) {
      return false;
    }

    $left_length  = strlen($left);
    $right_length = strlen($right);

    if($right_length < $left_length) {
      list($left_length, $right_length) = array($right_length, $left_length);
    }
    $is_similar_length = $right_length / $left_length <= 2;

    if(!$is_similar_length) {
      return false;
    }
    $left   = substr($left, 0, 100);
    $right  = substr($right, 0, 100);
    $length = (strlen($left) + strlen($right))/2;

    return levenshtein($left, $right)/$length < 1;
  }

  /**
   * Locate the position of a subarray in a given array
   * Where the values of the subarray are't necessarily
   * equal but at least 50% similar to those of the referred array
   * 
   * @param array $haystack
   * @param aray $needle
   * @return boolean
   */
  protected function similar_arrpos($haystack, $needle) {
    $k = false;

    for($i=0; $i<sizeof($haystack); $i++) {
      if($this->are_similar($haystack[$i], $needle[0])) {
        $k = $i;
        break;
      }
    }
    if($k === false) {
      return false;
    }
    $haystack[$i]->changes = $needle[0];

    for($i=1; $i<sizeof($needle); $i++) {
      if(!isset($haystack[$i+$k])) {
        return false;
      }
      if(!$this->are_similar($haystack[$i+$k], $needle[$i])) {
        $k++;
        $i--;
      } else {
        $haystack[$i+$k]->changes = $needle[$i];
      }
    }
    return true;
  }

  /**
   * Adds the right "rich-diff" div
   * @param integer $state
   * @param array $data
   * @return string
   */
  protected function get_html($state, $data) {
    if(!$data) {
      return '';
    }
    $html = '';
    $txt = implode('', $data);

    foreach($data as $txt) {
      if(!$state) {
       $html .= $txt;

      } else if(preg_match('/^<(?:p|table|h\d+|ul|ol|dl|blockquote|hr|details)(?: |>)/', $txt)) {
        switch($state) {
          case -1: $html .= '<div class="rich-diff del">' . $txt . '</div>' . "\n"; break;
          case 1:  $html .= '<div class="rich-diff ins">' . $txt . '</div>' . "\n"; break;
          case 2:  $html .= '<div class="rich-diff changed">' . $txt . '</div>' . "\n"; break;
        }

      } else if(preg_match('/^<(?:tr|th|td)(?: |>)/', $txt, $m, PREG_OFFSET_CAPTURE)) {
        $k = $m[0][1] + strlen($m[0][0]) - 1;

        switch($state) {
          case -1: $class = 'del'; break;
          case 1:  $class = 'ins'; break;
          case 2:  $class = 'changed'; break;
        }
        $html .= substr($txt, 0, $k) . ' class="' . $class . '"' . substr($txt, $k);

      } else {
        switch($state) {
          case -1: $html .= '<del class="diff">' . $txt . '</del>'; break;
          case 1:  $html .= '<ins class="diff">' . $txt . '</ins>'; break;
          default: $html .= $txt; break;
        }
      }
    }
    return $html;
  }

  /**
   * @param string $html
   * @return array
   */
  protected function get_innerHTML($html) {
    $openingTag = '';
    $closingTag = '';

    if(preg_match('/^<[^>]+>/', $html, $m)) {
      $openingTag = $m[0];
      $html       = substr($html, strlen($openingTag));
    }
    if(preg_match('/<[^>]+>$/', $html, $m)) {
      $closingTag = $m[0];
      $html       = substr($html, 0, strlen($html) - strlen($closingTag));
    }
    return [$openingTag, $closingTag, $html];
  }

  /**
   * @param Tag $tagLeft
   * @param Tag $tagRight
   */
  protected function get_changes($tagLeft, $tagRight) {
    if($tagLeft->txt == $tagRight->txt) {
      return $this->get_html(0, [$tagLeft]);
    }
    list(,, $txtLeft) = $this->get_innerHTML($tagLeft->txt);

    list($openingTag, $closingTag, $txtRight) = $this->get_innerHTML($tagRight->txt);

    $changes = $this->rich_diff($txtLeft, $txtRight);

    return $this->get_html(2, [$openingTag . $changes . $closingTag]);
  }

  /**
   * @param string $leftTxt
   * @param string $rightTxt
   * @return string
   */
  public function rich_diff($leftTxt, $rightTxt) {

    // Speedup: the two are identical, no need to check
    if($leftTxt == $rightTxt) {
      return $leftTxt;
    }
  
    // Convert the text to array
    $leftTxt  = $this->get_tags($leftTxt);
    $rightTxt = $this->get_tags($rightTxt);

    $diff = $this->diff_main($leftTxt, $rightTxt);
    $html = "";
    $n    = sizeof($diff) - 1;

    for($i=0; $i <= $n; $i++) {
      list($state, $data) = $diff[$i];
  
      $changes = false;
  
      if($state != 0 && $i != $n && $diff[$i+1][0] == -$state) {
        $a = $diff[$i][1];
        $b = $diff[$i+1][1];

        if(sizeof($a) < sizeof($b)) {
          if($this->similar_arrpos($b, $a)) {
            $changes = true;

            foreach($b as $it) {
              if($it->changes) {
                $html .= $this->get_changes($it->changes, $it);
              } else {
                $html .= $this->get_html(-$state, [$it]);
              }
            }
          }

        } else {
          if($this->similar_arrpos($a, $b)) {
            $changes = true;

            foreach($a as $it) {
              if($it->changes) {
                $html .= $this->get_changes($it, $it->changes);
              } else {
                $html .= $this->get_html($state, [$it]);
              }
            }
          }
        }
      }
      if($changes) {
        $i++;
      } else {
        $html .= $this->get_html($state, $data);
      }
    }
    return $html;
  }
}
