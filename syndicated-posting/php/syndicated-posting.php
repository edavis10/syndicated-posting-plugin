<?php
if (!class_exists("SyndicatedPostingPlugin")) {
  class SyndicatedPostingPlugin {
    var $adminOptionsName = "syndicated_posting_admin_options";
    var $options = array();

    // Constructor
    function SyndicatedPostingPlugin() {
      
    }

    // Called when the plugin is activated/installed
    function init(){
      $this->getAdminOptions();
    }

    // Returns: Array of admin options
    function getAdminOptions() {
      $this->options = get_option($this->adminOptionsName);

      // Set defaults if empty
      if (empty($this->options)){
        $this->options = array(
                               'feed_urls' => '',
                               'search_phrases' => '');
      }
      update_option($this->adminOptionsName,$this->options);
    }

    function printAdminPage() {
      $this->getAdminOptions();
      $spOptions = $this->options;
      if (isset($_POST['update_syndicatedPostingPluginSettings'])) {
        if (isset($_POST['spFeedUrls'])) {
// TODO: escape values / apply_filters?
          $spOptions['feed_urls'] = apply_filters('contant_save_pre', $_POST['spFeedUrls']);
        }   
        if (isset($_POST['spSearchPhrases'])) {
// TODO: escape values / apply_filters?
          $spOptions['search_phrases'] = apply_filters('content_save_pre', $_POST['spSearchPhrases']);
        }   
        update_option($this->adminOptionsName, $spOptions);

        ?>
<div class="updated">
  <p>
    <strong>
      <?php _e("Settings Updated.", "SyndicatedPostingPlugin");?>
    </strong>
  </p>
</div>
<?php 
   } ?>

<div class=wrap>
  <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
    <h2>Syndicated Posting</h2>
    <h3>Feeds &amp; Search Terms</h3>
    <p>
      <textarea name="spFeedUrls" style="width: 80%; height: 100px;"><?php _e(apply_filters('format_to_edit',$spOptions['feed_urls']), 'SyndicatedPostingPlugin') ?></textarea>
    </p>
    <p>
      <textarea name="spSearchPhrases" style="width: 80%; height: 100px;"><?php _e(apply_filters('format_to_edit',$spOptions['search_phrases']), 'SyndicatedPostingPlugin') ?></textarea>
    </p>


    <div class="submit">
      <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Settings', 'SyndicatedPostingPlugin') ?>" /></div>
  </form>
</div>
 <?php

    }
  } // End class
 }

?>
