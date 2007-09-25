<?php
if (!class_exists("SyndicatedPostingPlugin")) {
  class SyndicatedPostingPlugin {
    var $adminOptionsName = "syndicated_posting_admin_options";
    var $paginationCount = 30;
    var $options = array();
    // TODO: later.  Un hard code this url for the pagination code in printAdminPage()
    var $url = 'edit.php?page=main.php';

    // Constructor
    function SyndicatedPostingPlugin() {
      // Add a custom action WP can use for scheduling.  This is the reference that WP will call
      add_action('wp_syndicated-posting_poll_feeds_hook', array(&$this, 'pollFeeds'));
      
    }

    // Called when the plugin is activated/installed
    function init(){
      $this->getAdminOptions();
    }

    /// Action to add anything we need to the <head> tag of the HTML
    function addHtmlHead() {
      echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/syndicated-posting/css/syndicated-posting.css" />' . "\n";
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

    function getSettings($option) {
      $raw_settings = array_unique(split("\n",str_replace(',',"\n",$this->options[$option])));

      $finals = array();
      // Remove empty values
      foreach ($raw_settings as $setting) {
        if (trim($setting) != "") {
          $finals[] = trim($setting);
        }
      }

      return $finals;
    }

    function getFeeds() {
      return $this->getSettings('feed_urls');
    }

    function getSearches() {
      return $this->getSettings('search_phrases');
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

    function getCountOfFeedItems() {
      global $wpdb;
      $query = "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'syndicate'" . $this->buildSearchString();
      $count = $wpdb->get_var($query);
      return $count;
    }

    function buildSearchString() {
      global $wpdb;

      $query = " ";
      $phrases = $this->getSearches();

      if (!empty($phrases) && is_array($phrases)) {
        $query .= " AND ( ";
        foreach ($phrases as $phrase) {
          // Filter out empty strings
          if ( strlen($phrase) > 0 ) {
            $query .= "post_content LIKE '%" . $wpdb->escape($phrase) . "%' OR ";
            $query .= "post_title LIKE '%" . $wpdb->escape($phrase) . "%' OR ";
          }
        }
        // Hack for the final OR
        $query .= " 0) ";
      }
      return $query;
    }

    function getFeedItems($limit_row=0) {
      global $wpdb;

      $query = "SELECT * FROM wp_posts WHERE post_type = 'syndicate' ";
      
      $query .= $this->buildSearchString();

      // Add on the final ORDER
      $query .= " ORDER BY post_date DESC ";

      // Limits
      $query .= " LIMIT " . $limit_row . ", " . $this->paginationCount;

      $posts = $wpdb->get_results($query, ARRAY_A);
      return $posts;
    }

    function getFeedCount() {
      return $this->getCountOfFeedItems();
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

    function copyFeedItemToPost($post_id) {
      global $wpdb;
      $feed_post = $wpdb->get_row("SELECT * FROM wp_posts WHERE id = (" . $wpdb->escape($post_id) . ");", ARRAY_A);
      $feed_meta = $this->getFeedItemMeta($post_id);

      $post = new SyndicatedPost();
      $post->fillFromPost($feed_post,$feed_meta);

      $post_id = wp_insert_post($post);
      add_post_meta($post_id,'syndicated_author',$post->meta_author,true);
      add_post_meta($post_id,'syndicated_link',$post->meta_link,true);
      add_post_meta($post_id,'syndicated_source_title',$post->meta_source_title,true);
      add_post_meta($post_id,'syndicated_source_link',$post->meta_source_link,true);
      return $post_id;
    }

    /// Sets the post_type to be `syndicated`
    function markFeedItemAsSyndicated($post_id) {
      global $wpdb;
      return $wpdb->query("UPDATE $wpdb->posts SET post_type = 'syndicated' WHERE ID = (" . $post_id .");");
    }

    /// Sets the post_type to be `syndicate_deleted`
    function deleteFeedItem($post_id) {
      global $wpdb;
      return $wpdb->query("UPDATE $wpdb->posts SET post_type = 'syndicated_deleted' WHERE ID = (" . $post_id .");");
    }

    function syndicateFeedItem($post_id) {
      // Copy the feed item to a post with metadata
      $new_post_id = $this->copyFeedItemToPost($post_id);
      // Mark the feed item as syndicated
      $this->markFeedItemAsSyndicated($post_id);
      // Redirect to the new post
      // TODO: Hack
      $redirect = get_option('siteurl') . '/wp-admin/post.php?action=edit&post=' . $new_post_id;
      ?>
        <a href="<?php echo $redirect ?>">Redirecting to your post</a>
        <script type="text/javascript">
          <!-- 
               window.location = "<?php echo $redirect ?>"
      
            -->
        </script>
        <?php

      
    }

    function updateSettings() {
      if (isset($_POST['spFeedUrls'])) {
        $this->options['feed_urls'] = apply_filters('content_save_pre', str_replace(',',"\n",$_POST['spFeedUrls']));
      }   
      if (isset($_POST['spSearchPhrases'])) {
        $this->options['search_phrases'] = apply_filters('content_save_pre', str_replace(',',"\n",$_POST['spSearchPhrases']));
      }   
      update_option($this->adminOptionsName, $this->options);

      // Re-poll feeds because the the settings changes.
      $this->pollFeeds();

    }
    
    // Function to check the request to see if an item is to be deleted
    function itemDeleted() {
      if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && preg_match("/\d+/",$_GET['id'])) {
        return true;
          } else {
        return false;
          }
    }

    // Function to check the request to see if a paginated page is requested
    function paginatedPageRequested() {
      if (isset($_GET['action']) && $_GET['action'] == 'show' && isset($_GET['syndication-page']) && preg_match("/\d+/",$_GET['syndication-page'])) {
        return true;
          } else {
        return false;
          }
    }

    function showUpdatedMessage($message) {
      ?>
<div class="updated">
  <p>
    <strong>
      <?php _e($message, "SyndicatedPostingPlugin");?>
    </strong>
  </p>
</div>
        <?php
    }

    /// Checks the metadata to see if this post is from a syndicated post.
    /// If so it will return the metadata, if not it will return false
    function isSyndicatedPost($post_id) {
      $meta = $this->getFeedItemMeta($post_id);
      if (!empty($meta['syndicated_source_link']) ||
          !empty($meta['syndicated_source_title']) ||
          !empty($meta['syndicated_link'])) {
        return $meta;
      } else {
        return false;
      }
    }

    /// Filter for the_content()
    function addOriginalSource($content) {
      global $id;

      if (!empty($id) && $meta = $this->isSyndicatedPost($id)) {
        $c = '';
        // Only display if both of these actully have content
        if (!empty($meta['syndicated_source_link']) && !empty($meta['syndicated_source_title'])) {
          $c .= '<p class="original-publisher">Originally Published by <a href="' . $meta['syndicated_source_link'] . '" target="_blank">' .$meta['syndicated_source_title'] . '</a></em></p>';
        }

        $c .= $content;

        // Only display if both of these actully have content
        if (!empty($meta['syndicated_link']) && !empty($meta['syndicated_source_link'])) {
          $c .= '<p>Read the rest of the article on <a href="' . $meta['syndicated_link'] . '" target="_blank">' .$meta['syndicated_source_title'] . '</a>.</p>';
        }
      } else {
        $c = $content;
      }
      return $c;
    }

    /// Filter for the admin panel for posted content
    function addAdminSourceInformation($content='') {
      if (isset($_GET['post'])) {
        $id = $_GET['post'];
      } elseif (isset($_POST['post'])) {
        $id = $_POST['post'];
      } else {
        $id = '';
      }

      if (!empty($id) && $meta = $this->isSyndicatedPost($id)) {
        $c = $content;
        $c .= "  <p>";
        $c .= "    <label for='syndicated_author'>Author</label><br />";
        $c .= "    <input id='syndicated_author' type='text' value='". $meta['syndicated_author']."' name='syndicated_author' class='syndication-input' />";
        $c .= "  </p>";
        $c .= "  <p>";
        $c .= "    <label for='syndicated_source_title'>Source Publication Title</label><br />";
        $c .= "    <input id='syndicated_source_title' type='text' value='". $meta['syndicated_source_title']."' name='syndicated_source_title' class='syndication-input' />";
        $c .= "  </p>";
        $c .= "  <p>";
        $c .= "    <label for='syndicated_source_link'>Source Publication URL</label><br />";
        $c .= "    <input id='syndicated_source_link' type='text' value='". $meta['syndicated_source_link']."' name='syndicated_source_link' class='syndication-input' />";
        $c .= "  <p>";
        $c .= "  <p>";
        $c .= "    <label for='syndicated_link'>Article URL</label><br />";
        $c .= "    <input id='syndicated_link' type='text' value='". $meta['syndicated_link'] ."' name='syndicated_link' class='syndication-input' />";
        $c .= "  </p>";
      } else {

      }

      return $c;
    }

    /// Called after a post is saved to save the meta info
    function saveMetaFromEdit($id) {
      if (isset($_POST['syndicated_source_title'])) {
        update_post_meta($id, 'syndicated_source_title', $_POST['syndicated_source_title']);
      }
      if (isset($_POST['syndicated_link'])) {
        update_post_meta($id, 'syndicated_link', $_POST['syndicated_link']);
      }
      if (isset($_POST['syndicated_author'])) {
        update_post_meta($id, 'syndicated_author', $_POST['syndicated_author']);
      }
      if (isset($_POST['syndicated_source_link'])) {
        update_post_meta($id, 'syndicated_source_link', $_POST['syndicated_source_link']);
      }


    }

   function printAdminPage() {
      $this->getAdminOptions();

      // Check if the page is calling itself from a syndicate action and has a numeric id set
      // We need to check POST because when WP published a post it will redirect to the referer
      //  thus calling this again and re-Posting the item
      if (isset($_POST['action']) && 
          $_POST['action'] == 'syndicate' &&
          isset($_POST['id']) &&
          preg_match("/\d+/",$_POST['id'])) {
        $this->syndicateFeedItem($_POST['id']);
      } else {


        // Settings updated
        if (isset($_POST['update_syndicatedPostingPluginSettings'])) {
          $this->updateSettings();
          $this->showUpdatedMessage('Settings updated');
        }

        // Item deleted
        if ($this->itemDeleted()) {
          $this->deleteFeedItem($_GET['id']);
          $this->showUpdatedMessage('Prospect removed');
        }
        

        // Pagination check
        if ($this->paginatedPageRequested()) {
          $pagination = $_GET['syndication-page'];
        } else {
          $pagination = 1;
        }

 ?>

<div class="wrap">
    <h2>Feeds &amp; Search Terms</h2>
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" style="width:50%; float:left;">
      <fieldset>
        <legend>Enter <strong>feed URLs</strong>, one per line or comma-separated</legend>
        <textarea name="spFeedUrls" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$this->options['feed_urls']), 'SyndicatedPostingPlugin') ?></textarea>

        <div class="submit" style="text-align:left">
          <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Database', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>

    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>"  style="width:50%; float:left;">
      <fieldset>
        <legend>Enter <strong>search phrases</strong>, one per line or comma-separated</legend>
        <textarea name="spSearchPhrases" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$this->options['search_phrases']), 'SyndicatedPostingPlugin') ?></textarea>

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
                      $content_pages = ceil($this->getFeedCount('') / $this->paginationCount);

      $feed_posts = $this->getFeedItems(($this->paginationCount * $pagination) - $this->paginationCount);
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
	  <td><a class="edit" rel="permalink" href='<?php echo $post_meta['syndicated_link']?>' target="_blank">View</a></td>
          <td>
            <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" name="syndicate<?php echo $post['ID']; ?>">
              <input type="hidden" name="action" value="syndicate" />
              <input type="hidden" name="id" value="<?php echo $post['ID']; ?>" />
              <?PHP // TODO: try to get a non-js action working without the WP redirectes ?>
              <a class="edit" href="javascript:document.syndicate<?php echo $post['ID']; ?>.submit()">
                Syndicate
              </a>
            </form>
          </td> 
	  <td><a class="delete" href="<?php echo $_SERVER["REQUEST_URI"] . '&action=delete&id=' . $post['ID'] ; ?>">Delete</a></td>
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

<?php
        // Pagination
        if ($content_pages > 1) {
          echo "<p id='syndication-pages'>Page ";
          for ($content_page = 1; $content_page <= $content_pages; $content_page++)
            {
              // No link needed for the current page
              if ($pagination == $content_page) {
                echo "<strong>" . $content_page . "</strong> ";
              } else {
                echo "<a href='" . $this->url ."&action=show&syndication-page=".$content_page."'>" .$content_page . "</a> ";
              }
            }
          echo "</p>";
        }
?>

</div>

 <?php

        } // END syndication action check
    }
  } // End class
 }

?>
