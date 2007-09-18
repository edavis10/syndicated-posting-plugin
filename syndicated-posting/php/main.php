<?php
include ('syndicated-posting.php');
include ('syndicated-post.php');

if (class_exists("SyndicatedPostingPlugin")) {
  $sp_plugin = new SyndicatedPostingPlugin();
 }

// Initialize the admin panel
if (!function_exists("SyndicatedPostingPlugin_admin")) {
  function SyndicatedPostingPlugin_admin() {
    global $sp_plugin;
    if (!isset($sp_plugin)) {
      return;
    }
    if (function_exists('add_management_page')) {
      add_management_page('Syndication Posting', 'Syndication', 9, basename(__FILE__), array(&$sp_plugin, 'printAdminPage'));
    }
  }
 }


// Hook into the Wordpress Actions and Filters
if (isset($sp_plugin)) {
  // Actions
  add_action('activate_syndicated-posting/syndicated-posting.php', array(&$sp_plugin,'init'));
  add_action('admin_menu', 'SyndicatedPostingPlugin_admin');
  // Filters

 }

?>
