<?php
if (!class_exists("SyndicatedPostingPlugin")) {
  class SyndicatedPostingPlugin {
    var $adminOptionsName = "Syndicated_Posting_Admin_Options";

    // Constructor
    function SyndicatedPostingPlugin() {
      
    }

    // Called when the plugin is activated/installed
    function init(){
      $this->getAdminOptions();
    }

    // Returns: Array of admin options
    function getAdminOptions() {
      // Default options
      $syndicatedPostingAdminOptions = array(
                                             'feed_urls' => '',
                                             'search_phrases' => '',
                                             'prospects' => array());
      // Get options from DB
      $dbOptions = get_option($this->adminOptionsName);
      
      // Set options
      if (!empty($dbOptions)){
        foreach($devOptions as $key => $option) {
          $syndicatedPostingAdminOptions[$key] = $options;
        }
      }
      update_option($this->adminOptionsName,$syndicatedPostingAdminOptions);
      return $syndicatedPostingAdminOptions;
    }
  } // End class
 }

?>
