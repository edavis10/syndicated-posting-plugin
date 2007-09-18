<?php
include ('syndicated-posting.php');

if (class_exists("SyndicatedPostingPlugin")) {
  $sp_plugin = new SyndicatedPostingPlugin();
 }

// Hook into the Wordpress Actions and Filters
if (isset($sp_plugin)) {
  // Actions

  // Filters

 }

?>