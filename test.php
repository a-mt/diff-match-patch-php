<?php

$arr = [['a'], ['b'], ['c'], ['d'], ['e']];

array_splice($arr, 2, 1, [['x']]);

var_dump($arr);