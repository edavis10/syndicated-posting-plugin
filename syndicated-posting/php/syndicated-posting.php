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

    function getFeeds() {
      $feeds = split(",",$this->options['feed_urls']);      
      return $feeds;
    }

    function pollFeeds() {
      // Use the built in Magpie RSS parser that is in Wordpress
      require(ABSPATH . WPINC . '/rss.php');

      $this->getAdminOptions();

      // Get all the feeds
      $feed_urls = $this->getFeeds();

      foreach ($feed_urls as $feed_url)
        {
          $feed = fetch_rss(trim($feed_url));

          // Feed good?
          if (!$feed == false) {
            $feed_title = $feed->channel['title'];
            $feed_link = $feed->channel['link'];
            foreach ($feed->items as $item ) {
              if ($this->newFeedItem($item) <= 0) {
                $this->addPost($item, $feed_title,$feed_link);
              } else {
                // Skip item
              }
            }
          }
        }
    }

    // Add the feed item to the wp_posts database as a SyndicatedPost
    function addPost($rss, $title,$link){
      $post = new SyndicatedPost();
      $post->fillFromRss($rss);
      $post_id = wp_insert_post($post);
      add_post_meta($post_id,'syndicated_author',$post->meta_author,true);
      add_post_meta($post_id,'syndicated_link',$post->meta_link,true);
      add_post_meta($post_id,'syndicated_source_title',$title,true);
      add_post_meta($post_id,'syndicated_source_link',$link,true);

    }

    // Check if the feed item is new to us based off the title
    function newFeedItem($rss){
      global $wpdb;
      $post = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts WHERE post_title = ('" . $wpdb->escape($rss['title']) . "');");
      return $post;
    }

    function getFeedItems() {
      global $wpdb;
      $posts = $wpdb->get_results("SELECT * FROM wp_posts WHERE post_type = 'syndicate' ORDER BY post_date DESC", ARRAY_A);
      return $posts;
    }
    
    function getFeedItemMeta($post_id) {
      global $wpdb;
      $post_meta = array();

      $metas = $wpdb->get_results("SELECT * FROM wp_postmeta WHERE post_id = (" . $wpdb->escape($post_id) . ");", ARRAY_A);
      if (!empty($metas)) {
        foreach ($metas as $meta) {
          $post_meta[$meta['meta_key']] = $meta['meta_value'];
        }
      }
      return $post_meta;
    }

    function printAdminPage() {
      $this->getAdminOptions();
      // TODO: remove from here and schedule
      $this->pollFeeds();
      $spOptions = $this->options;
      if (isset($_POST['update_syndicatedPostingPluginSettings'])) {
        if (isset($_POST['spFeedUrls'])) {
          $spOptions['feed_urls'] = apply_filters('content_save_pre', $_POST['spFeedUrls']);
        }   
        if (isset($_POST['spSearchPhrases'])) {
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

<div class="wrap">
    <h2>Feeds &amp; Search Terms</h2>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" style="width:50%; float:left;">
      <fieldset>
        <legend>Enter <strong>feed URLs</strong>, one per line or comma-separated</legend>
        <textarea name="spFeedUrls" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$spOptions['feed_urls']), 'SyndicatedPostingPlugin') ?></textarea>

        <div class="submit" style="text-align:left">
          <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Database', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>

    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>"  style="width:50%; float:left;">
      <fieldset>
        <legend>Enter <strong>search phrases</strong>, one per line or comma-separated</legend>
        <textarea name="spSearchPhrases" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$spOptions['search_phrases']), 'SyndicatedPostingPlugin') ?></textarea>

        <div class="submit" style="text-align:left">
          <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Search', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>

<br style="clear: both;"/>

</div>

<div class="wrap">
  <h2>Syndication Prospects</h2>
  <table class="widefat">
    <thead>
      <tr>
	<th scope="col">Source</th>
	<th scope="col">Pubdate</th>
	<th scope="col">Title</th>
	<th scope="col">Author</th>
	<th scope="col"/>
	<th scope="col"/>
	<th scope="col"/>

      </tr>
    </thead>
    <tbody id="the-list">
<?php
                      $feed_posts = $this->getFeedItems();
      if (!empty($feed_posts) && is_array($feed_posts)) {
        // Found posts
        $css_class = '';
        foreach ($feed_posts as $post) {
          // TODO: Check boundries, e.g. no author name so print an empty cell
          if($css_class == 'alternate') { $css_class = ''; } else { $css_class = 'alternate'; }
          $post_meta = $this->getFeedItemMeta($post['ID']);
?>        
          <tr class="<?php echo $css_class;?>" id="post-<?php echo $post['ID'] ?>">
          <td style="font-weight:bold">
             <a href='<?php echo $post_meta['syndicated_source_link'] ?>' target="_blank">
               <?php echo $post_meta['syndicated_source_title'] ?>
             </a>
          </td>
          <td><?php echo $post['post_date'] ?></td>
	  <td><a href='<?php echo $post_meta['syndicated_link'] ?>' target="_blank"><?php echo $post['post_title'] ?></a></td>
	  <td><?php echo $post_meta['syndicated_author'] ?></td>
	  <td><a class="edit" rel="permalink" href='<?php echo $post_meta['syndicated_link']?>'>View</a></td>
	  <td><a class="edit" href="post.php?action=edit&post=<?php echo $post['ID']?>">Syndicate</a></td>
	  <td><a onclick="return deleteSomething( 'post', 53, 'You are about to delete this post \'"Guns, Germs and Steel" by Jared Diamond\'.\n\'OK\' to delete, \'Cancel\' to stop.' );" class="delete" href="post.php?action=delete&post=53&_wpnonce=43b533e904">Delete</a></td>
        </tr>
<?php
            }
        } else {
          // No posts
?>
      <tr class=""  id="post-54">
	<td colspan="7">No Prospects found.</td>
      </tr>
<?php
        }
?>
    </tbody>
</table>
</div>

 <?php

    }
  } // End class
 }

?>
