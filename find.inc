<?php


// This file is in the public domain.  Use at your own risk.


ini_set ('error_reporting', E_ALL);
ini_set ('display_errors', true);


header ("Content-type: text/plain");


function find ($path) {
  if (is_dir ($path)) {
    $glob = glob ("$path/*");
    foreach ($glob as $child)  { find ($child); } }
  elseif (is_file ($path)) {
    if ($stat = stat ($path)) {
      printf ( "%12d %12d %s\n", $stat['mtime'], $stat['size'], $path );
      ;;; } } }


$glob = glob ('*');
foreach ($glob as $path)  { find ($path); }


?>
