<?php
////
//// Constants
////
if ( !defined('SYNDICATED_COOKIE')) {
  define('SYNDICATED_COOKIE','wordpress_syndicated_posting_' . COOKIEHASH);
 }

////
//// Class to hold the logic of this plugin
////
if (!class_exists("SyndicatedPostingPlugin")) {
  class SyndicatedPostingPlugin {
    var $adminOptionsName = "syndicated_posting_admin_options";
    var $options = array();
    // TODO: later.  Un hard code this url for the pagination code in printAdminPage()
    var $url = 'edit.php?page=syndicated-posting.php';
    var $optionUrl = 'options-general.php?page=syndicated-posting.php';

    var $numberOfPages;
    var $digitRegex = "#[0-9]+#";
    var $lastPostId = 0;
    // USE the getter/setter provided
    var $category = '';

    // Constructor
    function SyndicatedPostingPlugin() {
      // Add a custom action WP can use for scheduling.  This is the reference that WP will call
      add_action('wp_syndicated-posting_poll_feeds_hook', array(&$this, 'pollFeeds'));
      add_action('wp_syndicated-posting_purge_old_items_hook', array(&$this, 'purgeOldFeedItems'));
      
    }

    /////
    //// Getter / Setters
    ////
    function getCategory() {
      return $this->category;
    }

    function setCategory($cat_id) {
      $this->category = "c" . $cat_id;
      return $this->getCategory();
    }

    // Getter for category but will only return the number for it
    function getCategoryRawId() {
      return substr($this->getCategory(),1);
    }


    ////
    //// Request Handler
    ////

    /// Master loop.  It will check the parameters and will either:
    ///   * Syndicate a feed item and redirect to posting
    ///   * Create admin page with pagination and any messages
    ///
    function handleRequest() {
      $this->getAdminOptions();
      $this->setCategory($this->getCategoryIdFromRequest());

      // Check if we are going to show the admin page or another page (only syndication page)
      if ($this->syndicatedPageRequested()) {
        // Another page
        $this->syndicateFeedItem($_GET['id']);

      } elseif ($this->syndicatedNewItemRequested()){
        // Check if a new syndication was added
        $this->syndicateNewItem($_POST);
      } else {
        // Admin page will be shown

        $currentPage = 1; // Default

        // Check if there are special requests for the page
        if ( $this->itemDeleted()) {
          $this->deleteFeedItem($_GET['id']);
          $this->showUpdatedMessage('Prospect removed');
          
        } elseif ($this->paginatedPageRequested()) {
          $currentPage = $_GET['syndication-page'];

        } elseif (isset($_POST['update_syndicatedPostingPluginSettings'])) {
          $this->updateSettings();
          $this->showUpdatedMessage('Settings updated');

        } elseif ( $this->bulkDeleteRequested()) {
          if ($this->bulkDeleteFeedItems()) {
            // Only print message if items are deleted
            $this->showUpdatedMessage('Prospects removed');
          }

        } else {
          // Nothing
        }
        $this->getLastSeenFromCookie();
        $this->printAdminPage($currentPage);  
      } // END page check
    }

    function handleOptionRequest() {
      $this->getAdminOptions();
      if (isset($_POST['update_syndicatedPostingPluginOptions'])) {
          $this->updateSettings();
          $this->showUpdatedMessage('Options updated');
      }
      $this->printOptionPage();

    }

    ////
    //// Wordpress Actions and Filters
    ////

    /// Called when the plugin is activated/installed
    function init(){
      $this->getAdminOptions();
    }

    /// Action to add anything we need to the <head> tag of the HTML
    function addHtmlHead() {
      echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/syndicated-posting/css/syndicated-posting.css" />' . "\n";
    }

    /// Called after a post is saved in order to save the meta info
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

    /// Filter for `the_content` so the custom syndicated data is displayed
    function addOriginalSource($content) {
      global $id;

      if (!empty($id) && $meta = $this->isSyndicatedPost($id)) {
        $c = '';
        // Only display if both of these actully have content
        if (!empty($meta['syndicated_source_link']) && !empty($meta['syndicated_source_title'])) {
          $c .= '<p class="original-publisher">Originally Published by <a href="' . $meta['syndicated_source_link'] . '" target="_blank">' .$meta['syndicated_source_title'] . '</a></em></p>';
        }

        $c .= $content;

      } else {
        $c = $content;
      }
      return $c;
    }

    /// Filter for post_link to use the origional source link for syndicated posts
    function changeTitleLink($link) {
      global $id;
        
      if (!empty($id) && $meta = $this->isSyndicatedPost($id)) {
        $source_link = $link;

        if (!empty($meta['syndicated_link'])) {
          $source_link = $meta['syndicated_link'];          
        }
        return $source_link;
      } else {
        return $link;
        }
    }

    /// Filter for the admin panel for posted content to add the input boxes to change
    ///  the custom data
    function addAdminSourceInformation($content='') {
      if (isset($_GET['post'])) {
        $id = $_GET['post'];
      } elseif (isset($_POST['post'])) {
        $id = $_POST['post'];
      } else {
        $id = '';
      }

      $c = $content;

      if (!empty($id) && $meta = $this->isSyndicatedPost($id)) {
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
      }

      return $c;
    }

    /// Add a cookie to track the last post a user has seens
    function addCookie() {
      global $user;
      $user_data = get_option("syndicated_user_" . $user->ID);
      if (empty($user_data)) {
        $user_data = array( "last_post" => 0 );
      }
      $this->lastPostId = $user_data['last_post'];

      setcookie(SYNDICATED_COOKIE, $this->lastPostId, time() + 31536000);
      $user_data['last_post'] = $this->getTopId();
      update_option("syndicated_user_" . $user->ID, $user_data);
    }

    /// Remove the cookie
    function removeCookie() {
      setcookie(SYNDICATED_COOKIE, '', time() - 10*365*24*60*60);
    }

    ////
    //// Database functions
    ////

    /// Gets the settings that are stored in the database
    function getAdminOptions() {
      $this->options = get_option($this->adminOptionsName);

      // Set defaults if empty
      if (empty($this->options)){
        $this->options = array(
                               'feed_urls' => '',
                               'search_phrases' => array (''),
                               'per_page' => 30,
                               'days_to_keep' => 30);
      }
      update_option($this->adminOptionsName,$this->options);
    }

    /// Builds the SQL search string that is used to filter out feed items
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

    /// Gets the feed items from the database starting at the `$limit_row` record
    function getFeedItems($limit_row=0) {
      global $wpdb;

      $query = "SELECT * FROM wp_posts WHERE post_type = 'syndicate' ";
      
      $query .= $this->buildSearchString();

      // Add on the final ORDER
      $query .= " ORDER BY post_date DESC ";

      // Limits
      $query .= " LIMIT " . $limit_row . ", " . $this->options['per_page'];

      $posts = $wpdb->get_results($query, ARRAY_A);
      return $posts;
    }

    /// Adds the feed item to the wp_posts database as a SyndicatedPost and attaches
    ///  the metadata for it
    function addPost($rss, $title,$link, $author){
      $post = new SyndicatedPost();
      $post->fillFromRss($rss, $author);
      $post_id = wp_insert_post($post);
      add_post_meta($post_id,'syndicated','true',true);
      add_post_meta($post_id,'syndicated_author',$post->meta_author,true);
      add_post_meta($post_id,'syndicated_link',$post->meta_link,true);
      add_post_meta($post_id,'syndicated_source_title',$title,true);
      add_post_meta($post_id,'syndicated_source_link',$link,true);

    }

    /// Returns all the metadata for `$post_id` as an associate array
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

    /// Copies the feed item and its metadata to a new post item
    function copyFeedItemToPost($post_id) {
      global $wpdb;
      $feed_post = $wpdb->get_row("SELECT * FROM wp_posts WHERE id = (" . $wpdb->escape($post_id) . ");", ARRAY_A);
      $feed_meta = $this->getFeedItemMeta($post_id);

      $post = new SyndicatedPost();
      $post->fillFromPost($feed_post,$feed_meta);

      $new_post_id = wp_insert_post($post);
      add_post_meta($new_post_id,'syndicated','true',true);
      add_post_meta($new_post_id,'syndicated_author',$post->meta_author,true);
      add_post_meta($new_post_id,'syndicated_link',$post->meta_link,true);
      add_post_meta($new_post_id,'syndicated_source_title',$post->meta_source_title,true);
      add_post_meta($new_post_id,'syndicated_source_link',$post->meta_source_link,true);
      return $new_post_id;
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

    /// Checks if a post has been syndicated
    function hasPostBeenSyndicated($post_id) {
      global $wpdb;
      $post = $wpdb->get_row("SELECT * FROM wp_posts WHERE id = (" . $wpdb->escape($post_id) . ");", ARRAY_A);
      if ($post['post_type'] == "syndicated") {
        return true;
      } else {
        return false;
      }
    }


    /// Sets many posts to have the post_type to be `syndicate_deleted`
    function bulkDeleteFeedItems() {
      if (isset($_POST['delete']) && !empty($_POST['delete'])) {
        foreach ($_POST['delete'] as $post_id) {
          // Check input
          if (preg_match($this->digitRegex, $post_id)) {
            $this->deleteFeedItem($post_id);
          }
        }
        return true;
      } else {
        return false;
      }
    }

    ////
    //// Feed functions
    ////

    /// Poll the feeds using the built in Magpie RSS parser in Wordpress and
    ///  add them to application
    function pollFeeds() {

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

            // Used in case the items don't have author set
            $feed_author = $this->extractFeedAuthor($feed);

            foreach ($feed->items as $item ) {
              if ($this->newFeedItem($item) <= 0) {
                $this->addPost($item, $feed_title, $feed_link, $feed_author);
              } else {
                // Skip item
              }
            }
          }
        }
    }

    /// Purge feed items that are over the limit to keep
    function purgeOldFeedItems () {
      global $wpdb;

      $this->getAdminOptions();

      // Get the cutoff date for purging
      $expiry = time() - ($this->options['days_to_keep'] * 24 * 60 * 60);

      // Find all the feed items that are old
      $query = "Select * from wp_posts WHERE post_type = 'syndicate' AND post_date < ('" . strftime('%Y-%m-%d %H:%M:%S',$expiry) ."')";

      $results = $wpdb->get_results($query, ARRAY_A);
      
      // Iterate through all the feed items and purge them from the database including metadata
      if (!empty($results)) {
        foreach ($results as $post) {
          wp_delete_post($post['ID']);
        }
      }
    }

    /// Returns the count of feed items that match the search terms
    function getFeedCount() {
      global $wpdb;
      $query = "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'syndicate'" . $this->buildSearchString();
      $count = $wpdb->get_var($query);
      return $count;
    }

    /// Gets the author of the feed object
    function extractFeedAuthor($feed) {
      // RSS 0.91 uses managingEditor
      if (!empty($feed->channel['managingeditor'])) {
        return $feed->channel['managingeditor'];
      }

      // ATOM uses author and potentially name
      if (!empty($feed->channel['author_name'])) {
        return $feed->channel['author_name'];
      }
      
    }

    ////
    //// Settings functions
    ////

    /// Returns the settings for the feed_urls
    function getFeeds() {
      return $this->getSettings('feed_urls');
    }

    /// Returns the settings for the search_phrases
    // TODO: Refactor so we can use getSettings as a base
    function getSearches() {
      $raw_settings = array_unique(preg_split('/[,|\n]/',$this->options['search_phrases'][$this->getCategory()]));

      $finals = array();
      // Remove empty values
      foreach ($raw_settings as $setting) {
        if (trim($setting) != "") {
          $finals[] = trim($setting);
        }
      }

      return $finals;
    }

    /// Returns the settings for `$option` key
    function getSettings($option) {
      $raw_settings = array_unique(preg_split('/[,|\n]/',$this->options[$option]));

      $finals = array();
      // Remove empty values
      foreach ($raw_settings as $setting) {
        if (trim($setting) != "") {
          $finals[] = trim($setting);
        }
      }

      return $finals;
    }

    /// Updates the settings from a user change
    function updateSettings() {
      if (isset($_POST['spFeedUrls'])) {
        $this->options['feed_urls'] = apply_filters('content_save_pre', $_POST['spFeedUrls']);
      }   
      if (isset($_POST['spSearchPhrases']) && isset($_POST['category']) && preg_match($this->digitRegex, $_POST['category'])) {
        $this->options['search_phrases']['c' . $_POST['category']] = apply_filters('content_save_pre', $_POST['spSearchPhrases']);
      }   
      // From options panel
      if (isset($_POST['prospects_per_page']) && preg_match($this->digitRegex, $_POST['prospects_per_page'])) {
        $this->options['per_page'] = apply_filters('content_save_pre', $_POST['prospects_per_page']);
      }   
      if (isset($_POST['days_to_keep']) && preg_match($this->digitRegex, $_POST['days_to_keep'])) {
        $this->options['days_to_keep'] = apply_filters('content_save_pre', $_POST['days_to_keep']);
      }   

      update_option($this->adminOptionsName, $this->options);

      // Re-poll feeds because the the settings changes.
      $this->pollFeeds();

    }

    function getLastSeenFromCookie() {
      foreach($_COOKIE as $name => $value) {
        // Look for the cookie
        if ($name == (SYNDICATED_COOKIE)){
          $this->lastPostId = $value;
        }

      }
    }

    function getTopId(){
      global $wpdb;
      $top_id = $wpdb->get_var("SELECT id FROM wp_posts ORDER BY id DESC LIMIT 1");

      return $top_id;
    }

    ////
    //// HTML functions
    ////


    /// Prints the admin page
    function printAdminPage($currentPage) {
      $this->printProspects($currentPage);
      $this->printAdd();
      $this->printSettings();
    } 

    /// Prints the option page
    function printOptionPage() {
 ?>
<div class="wrap">
    <h2>Syndicated Posting Options</h2>
    <form method="post" action="<?php echo $this->optionUrl; ?>">
      <fieldset class="options">
        <table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform"> 
          <tbody>
            <tr valign="top"> 
              <th width="33%" scope="row">Show at most:</th> 
              <td>
                <input type="text" size="3" value="<?php echo $this->options['per_page'];?>" id="prospects_per_page" name="prospects_per_page"/> prospects</td> 
            </tr> 
            <tr valign="top"> 
              <th width="33%" scope="row">Keep unposted prospects for:</th> 
              <td>
                <input type="text" size="3" value="<?php echo $this->options['days_to_keep'];?>" id="days_to_keep" name="days_to_keep"/> days</td> 
            </tr> 
          </tbody>
        </table>
        
        <div class="submit" style="text-align:right">
          <input type="submit" name="update_syndicatedPostingPluginOptions" value="<?php _e('Update Options »', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>

<br style="clear: both;"/>

</div>
<?php  

    }

    /// Syndicates a feed item into a post and redirects to the post's edit page
    function syndicateFeedItem($post_id) {
      // Check if it was syndicated alredy to prevent double posts from WP's redirection
      if ($this->hasPostBeenSyndicated($post_id)) {
        // Redirect to main plugin page
            ?>
        <a href="<?php echo $this->url . "&category=" . $this->getCategoryRawId();  ?>">Redirecting..</a>
        <script type="text/javascript">
          <!-- 
               window.location = "<?php echo $this->url . "&category=" . $this->getCategoryRawId();  ?>"
      
            -->
        </script>
        <?php
      } else {
        // Copy the feed item to a post with metadata
        $new_post_id = $this->copyFeedItemToPost($post_id);
        // Set the category
        wp_set_post_categories($new_post_id, array($this->getCategoryRawId()));

        // Mark the feed item as syndicated
        $this->markFeedItemAsSyndicated($post_id);
        // Redirect to the new post
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
    }

    function syndicateNewItem($data) {
      $post = new SyndicatedPost();

      if (isset($data['syndicated_source_title'])) {
        $post->post_title = $data['syndicated_source_title'];
        $post->meta_source_title = $data['syndicated_source_title'];
      }
      if (isset($data['syndicated_link'])) {
        $post->meta_link = $data['syndicated_link'];
      }
      if (isset($data['syndicated_author'])) {
        $post->meta_author = $data['syndicated_author'];
      }
      if (isset($data['syndicated_source_link'])) {
        $post->meta_source_link = $data['syndicated_source_link'];
      }

      $post_id = wp_insert_post($post);
      add_post_meta($post_id,'syndicated','true',true);
      add_post_meta($post_id,'syndicated_author',$post->meta_author,true);
      add_post_meta($post_id,'syndicated_link',$post->meta_link,true);
      add_post_meta($post_id,'syndicated_source_title',$post->meta_source_title,true);
      add_post_meta($post_id,'syndicated_source_link',$post->meta_source_link,true);

      // Set the category
      wp_set_post_categories($post_id, array($this->getCategoryRawId()));

      // Redirect to the new post
      $redirect = get_option('siteurl') . '/wp-admin/post.php?action=edit&post=' . $post_id;
      ?>
        <a href="<?php echo $redirect ?>">Redirecting to your post</a>
           <script type="text/javascript">
             window.location = "<?php echo $redirect ?>"
           </script>
           <?php
    }

    /// Gets the prospects and prints each one in a table
    function printProspects($currentPage) {
      echo '<div class="wrap">';

      $this->printProspectsHead();

      $feed_posts = $this->getFeedItems($this->itemLimit($currentPage));

      if (!empty($feed_posts) && is_array($feed_posts)) {
        // Found posts
        $css_class = '';
        foreach ($feed_posts as $post) {

          // Swap CSS class between 'alternate' and ''
          if($css_class == 'alternate') { $css_class = ''; } else { $css_class = 'alternate'; }

          $post_meta = $this->getFeedItemMeta($post['ID']);
          
          $this->printFeedItem($post, $post_meta, $css_class);
        }
      } else {
        // No posts
        echo '<tr class="">';
        echo '  <td colspan="7">No Prospects found.</td>';
        echo '</tr>';
      }
      
      // Close the prospects table
      $this->printProspectsFoot();

      $this->setNumberOfContentPages();

      // Print pages if there are some
      if ($this->numberOfPages > 1) {
        
        $this->printPagination($currentPage, $this->numberOfPages);
      }
      echo "</div>"; // Closes the syndication items 'wrap' div
    }

    /// Print the opening of the Prospects table
    function printProspectsHead() {
?>
  <h2>Syndication Prospects</h2>
  <script type="text/javascript">
        <?php $this->printBulkDeleteJavaScript(); ?>
  </script>
<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" name="bulk-delete">
  <input type="hidden" name="action" value="bulk-delete" />
   <div class="submit" style="text-align:left">
    <input type="submit" value="Delete Checked" name="delete_checked" />
  </div>
  <table class="widefat" id="prospects">
    <thead>
      <tr>
	<th scope="col">
          <input type="checkbox" name="delete_all" onClick="checkAll(document.forms['bulk-delete'].elements['delete[]'],this)" />
        </th>
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
        }

    /// Print the closing of the Prospects table
    function printProspectsFoot() {
      echo "    </tbody>";
      echo "  </table>";
      echo '  <div class="submit" style="text-align:left">';
      echo '    <input type="submit" value="Delete Checked" name="delete_checked" />';
      echo '  </div>';
      echo "</form>";

    }

    /// Print a single feed item
    function printFeedItem($post, $post_meta, $css_class) {

?>        
        <tr class="<?php echo $css_class;?>" id="post-<?php echo $post['ID'] ?>" <?php if ($this->lastPostId < $post['ID']) { echo "style='background-color:#99ff99;'";}?>>
          <td><input type="checkbox" name="delete[]" value="<?php echo $post['ID'];?>" /></td>
          <td style="font-weight:bold">
             <a href='<?php echo $post_meta['syndicated_source_link'] ?>' target="_blank">
               <?php echo $post_meta['syndicated_source_title'] ?>
             </a>
          </td>
          <td><?php echo $post['post_date'] ?></td>
	  <td>
          <?php if ($this->lastPostId < $post['ID']) { 
             echo '<img src="' . get_bloginfo('wpurl') . '/wp-content/plugins/syndicated-posting/images/new-post.png" alt="(New Post)" title="(New Post)" />';
          }?> 
            <a href='<?php echo $post_meta['syndicated_link'] ?>' target="_blank"><?php echo $post['post_title'] ?></a>
          </td>
	  <td><?php echo $post_meta['syndicated_author'] ?></td>
	  <td><a class="edit" rel="permalink" href='<?php echo $post_meta['syndicated_link']?>' target="_blank">View</a></td>
          <td>
            <a class="edit" href="<?php echo $this->url;?>&action=syndicate&id=<?php echo $post['ID']; ?>&category=<?php echo $this->getCategoryRawId();?>">
              Syndicate
            </a>
          </td> 
	  <td><a class="delete" href="<?php echo $_SERVER["REQUEST_URI"] . '&action=delete&id=' . $post['ID'] ; ?>">Delete</a></td>
        </tr>
<?php
    }

    /// Prints the pagination links
    function printPagination($pagination, $content_pages) {
        // Pagination
          echo "<p id='syndication-pages'>Page ";
          for ($content_page = 1; $content_page <= $content_pages; $content_page++)
            {
              // No link needed for the current page
              if ($pagination == $content_page) {
                echo "<strong>" . $content_page . "</strong> ";
              } else {
                echo "<a href='" . $this->url ."&action=show&syndication-page=".$content_page."&category=" . $this->getCategoryRawId() . "'>" .$content_page . "</a> ";
              }
            }
          echo "</p>";
    }

    /// Prints the form to manually add a feed item
    function printAdd() {
?>
<script type="text/javascript">
function validate(form) {
    if(form.syndicated_source_title.value.replace(/\s/g,'') == '') {
        window.alert("You need to enter a title in order to syndicate this post.");
        form.syndicated_source_title.focus();
        return false;
    }
    return true;
}
</script>
<div id="add-feed-item" class="wrap">
    <h2>Syndicate Single Prospect</h2>
    <form method="post" action="<?php echo $this->url;?>" style="width:100%;" onsubmit="return validate(this)">
      <fieldset>
          <p>
            <label for='syndicated_source_title'><strong>Source Publication Title</strong> (Required) </label><br />
            <input id='syndicated_source_title' type='text' value='' name='syndicated_source_title' class='syndication-input' />
          </p>
          <p>
            <label for='syndicated_source_link'>Source Publication URL</label><br />
            <input id='syndicated_source_link' type='text' value='' name='syndicated_source_link' class='syndication-input' />
          </p>
          <p>
            <label for='syndicated_author'>Author</label><br />
            <input id='syndicated_author' type='text' value='' name='syndicated_author' class='syndication-input' />
          </p>
          <p>
            <label for='syndicated_link'>Article URL</label><br />
            <input id='syndicated_link' type='text' value='' name='syndicated_link' class='syndication-input' />
          </p>
          <input type='hidden' value='syndicateAdd' name='action' />
          <input type='hidden' value='<?php echo $this->getCategoryRawId();?>' name='category' />

        <div class="submit" style="text-align:left">
          <input type="submit" name="AddNewItem" value="<?php _e('Syndicate this post', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>
  </div>
<?php
    }

    /// Prints the settings form for the search terms and feeds
    function printSettings() {
 ?>
<div class="wrap">
  <h2>Settings</h2>
  <a onclick="$('feed-settings').toggle()" style="cursor:pointer">Show Feeds</a>
  <a onclick="$('search-settings').toggle()" style="cursor:pointer">Show Search</a>
</div>
<?php
      if (strlen($this->options['feed_urls']) > 0) {
        $feedStyle = "display:none;"; // Hide if not empty
      } else {
        $feedStyle = '';
      }
?>
<div id="feed-settings" class="wrap" style="<?php echo $feedStyle;?>">
    <h2>Feeds</h2>
    <form method="post" action="<?php echo $this->url; ?>" style="width:100%;">
      <fieldset>
        <label for="spFeedUrls">Enter <strong>feed URLs</strong>, one per line or comma-separated</label>
        <textarea name="spFeedUrls" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$this->options['feed_urls']), 'SyndicatedPostingPlugin') ?></textarea>

        <div class="submit" style="text-align:left">
          <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Database', 'SyndicatedPostingPlugin') ?>" />
        </div>
      </fieldset>
    </form>
  </div>
<?php
      if (strlen($this->searchPhrasesForCategory()) > 0) {
        $searchStyle = "display:none;"; // Hide if not empty
      } else {
        $searchStyle = '';
      }
?>
<div id="search-settings" class="wrap" style="<?php echo $searchStyle ?>">
  <h2>Search Terms</h2>
    <form method="post" action="<?php echo $this->url; ?>"  style="width:100%;">
      <fieldset>
        <label for="spSearchPhrases">Enter <strong>search phrases</strong>, one per line or comma-separated</label>
        <textarea name="spSearchPhrases" style="width: 100%; height: 100px;"><?php _e(apply_filters('format_to_edit',$this->searchPhrasesForCategory()), 'SyndicatedPostingPlugin') ?></textarea>

        <table width="100%">
          <tr>
            <td width="30%" align="left">
              <div class="submit" style="display:inline;text-align:left;">
                <input type="submit" name="update_syndicatedPostingPluginSettings" value="<?php _e('Update Search', 'SyndicatedPostingPlugin') ?>" />
              </div>
            </td>
            <td width="70%" align="right">
              <label for="category" style="font-weight:bold">Search category:</label>
              <select name="category" id="category" onchange="javascript:this.form.submit();">
                <?php $this->printCategorySelect($this->getCategoryRawId()); ?>
              </select>
              <input type="hidden" name="current_category" value="<?php echo $this->getCategory(); ?>" />
            </td>
          </tr>
        </table>
      </fieldset>
    </form>
</div>
<?php  
    }

    function printBulkDeleteJavaScript() {
?>
      function checkAll(group,nameOfAllCheckbox) {
        for (index = 0; index < group.length; index++) {
          group[index].checked = nameOfAllCheckbox.checked? true:false
        }
      }
<?php
    }

    function printCategorySelect($selected_id) {
      // Get list of category id and names
      $cat_ids = get_all_category_ids();

      foreach ($cat_ids as $cat) {
        $name = get_cat_name($cat);
        if ($selected_id == $cat) { $selected = "selected='selected'";} else { $selected = "";}
        echo "  <option value='" . $cat . "' " . $selected . " >" . $name . "</option>\n";
      }

    }

    /// Displays a message div with `$message`
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


    ////
    //// Helper functions
    ////

    /// Check if the feed item is new to us based off the title
    function newFeedItem($rss){
      global $wpdb;
      $post = $wpdb->get_var("SELECT COUNT(*) FROM wp_posts WHERE post_title = ('" . $wpdb->escape($rss['title']) . "') AND post_type LIKE ('%syndicate%');");
      return $post;
    }

    /// Check the request to see if an item is to be deleted
    function itemDeleted() {
      if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && preg_match($this->digitRegex,$_GET['id'])) {
        return true;
          } else {
        return false;
          }
    }

    /// Check the request to see if a paginated page was requested
    function paginatedPageRequested() {
      if (isset($_GET['action']) && $_GET['action'] == 'show' && isset($_GET['syndication-page']) && preg_match($this->digitRegex,$_GET['syndication-page'])) {
        return true;
      } else {
        return false;
      }
    }

    /// Check the request to see if an item is to be syndicated
    function syndicatedPageRequested() {
      if (isset($_GET['action']) && $_GET['action'] == 'syndicate' && isset($_GET['id']) && preg_match($this->digitRegex,$_GET['id'])) {
        return true;
      } else {
        return false;
      }
    }

    /// Check the request to see if a new item is being added by hand
    function syndicatedNewItemRequested() {
      if (isset($_POST['action']) && $_POST['action'] == 'syndicateAdd' && strlen($_POST['syndicated_source_title']) > 0) {
        return true;
      } else {
        return false;
      }
    }


    /// Check the request to see if a bulk delete action is requested
    function bulkDeleteRequested() {
      if (isset($_POST['action']) && $_POST['action'] == 'bulk-delete') {
        return true;
      } else {
        return false;
      }
    }

    /// Checks the metadata to see if this post is from a syndicated post.
    /// Will return the metadata with true, or the bool false.
    function isSyndicatedPost($post_id) {
      $meta = $this->getFeedItemMeta($post_id);
      if (!empty($meta['syndicated'])) {
        return $meta;
      } else {
        return false;
      }
    }

    /// Sets the variable to the number of content pages that need to be displayed based off the 
    ///  pagination settings
    function setNumberOfContentPages() {
      $this->numberOfPages = ceil($this->getFeedCount('') / $this->options['per_page']);
    }

    /// Finds the SQL limit for this page.  Used by the pagination so item 31-60 are
    ///  shown on page two (if 30 items a page).
    function itemLimit($pageNumber) {
      return ($this->options['per_page'] * $pageNumber) - $this->options['per_page'];
    }

    /// Gets the category id from the passed in data or the 'Uncategorized' category if no
    ///  data was found.  Parameter is $_GET or $_POST
    function getCategoryIdFromRequest() {
      if (isset($_GET['category']) && preg_match($this->digitRegex,$_GET['category'])) {
        // GET called
        return $_GET['category'];

      } elseif  (isset($_POST['category']) && preg_match($this->digitRegex,$_POST['category'])) {
        // POST called
        return $_POST['category'];

      } else {
        return get_cat_ID('Uncategorized');
      }
    }

    function searchPhrasesForCategory() {
      return $this->options['search_phrases'][$this->getCategory()];
    }

    /// Template tag functions
    function getSyndicatedPosts() {
          global $wpdb;

          $query = " 
          SELECT wposts.* 
          FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
          WHERE wposts.ID = wpostmeta.post_id 
            AND wpostmeta.meta_key = 'syndicated' 
            AND wpostmeta.meta_value = 'true' 
            AND wposts.post_status = 'publish' 
            AND wposts.post_type = 'post' 
            AND wposts.post_date <= NOW() 
          ORDER BY wposts.post_date DESC
          LIMIT 10;
          ";

          return $wpdb->get_results($query, OBJECT);
    }

    function getNonSyndicatedPosts($badCats=NULL) {
          global $wpdb;

          if (!empty($badCats)) {
            $filterOut = "
              AND p.id NOT IN
                (SELECT post_id 
                 FROM `wp_post2cat`
                 WHERE category_id IN (".join(',',$badCats)."))";
          } else {
            $filterOut = '';
          }

          $query = " 
          SELECT DISTINCT p.* FROM `wp_posts` as p 
            LEFT JOIN `wp_postmeta` as m 
                ON p.ID = m.post_id
          WHERE p.post_status = 'publish' 
            AND p.post_type = 'post' 
            AND p.post_date <= NOW() 
            AND p.id NOT IN
              (SELECT m.post_id
              FROM `wp_posts` as p 
              LEFT JOIN `wp_postmeta` as m 
                ON p.ID = m.post_id
              WHERE 
                p.post_status = 'publish' 
                AND p.post_type = 'post' 
                AND p.post_date <= NOW() 
                AND m.meta_key = 'syndicated' 
                AND m.meta_value = 'true')" . $filterOut ."
          ORDER BY p.post_date DESC 
          LIMIT 5;";
          return $wpdb->get_results($query, OBJECT);
    }

  } // End class
 }


//// 
//// Logic to hook into Wordpress as a plugin
//// 

include ('syndicated-post.php');

if (class_exists("SyndicatedPostingPlugin")) {
  $sp_plugin = new SyndicatedPostingPlugin();
 }

/// Initialize the admin panel
if (!function_exists("SyndicatedPostingPlugin_admin")) {
  function SyndicatedPostingPlugin_admin() {
    global $sp_plugin;
    if (!isset($sp_plugin)) {
      return;
    }
    if (function_exists('add_management_page')) {
      // Level 7 so Admins and Editors can use this
      add_management_page('Syndication Posting', 'Syndication', 7, basename(__FILE__), array(&$sp_plugin, 'handleRequest'));
    }
    if (function_exists('add_options_page')) {
      // Level 7 so Admins and Editors can use this
      add_options_page('Syndication Posting', 'Syndication', 7, basename(__FILE__), array(&$sp_plugin, 'handleOptionRequest'));
    }

    wp_enqueue_script('prototype');

  }
 }

/// Initialize the scheduling
if (!wp_next_scheduled('wp_syndicated-posting_poll_feeds_hook')) {
  wp_schedule_event(time(), 'hourly', 'wp_syndicated-posting_poll_feeds_hook');
 }
if (!wp_next_scheduled('wp_syndicated-posting_purge_old_items_hook')) {
  wp_schedule_event(time(), 'daily', 'wp_syndicated-posting_purge_old_items_hook');
 }


/// Hook into the Wordpress Actions and Filters
if (isset($sp_plugin)) {
  // Actions
  add_action('activate_syndicated-posting/syndicated-posting.php', array(&$sp_plugin,'init'));
  add_action('admin_menu', 'SyndicatedPostingPlugin_admin');
  add_action('admin_head',  array(&$sp_plugin,'addHtmlHead'));
  add_action('save_post',  array(&$sp_plugin,'saveMetaFromEdit'));
  add_action('wp_login',  array(&$sp_plugin,'addCookie'));
  add_action('wp_logout',  array(&$sp_plugin,'removeCookie'));
  // Filters
  add_filter('the_content', array(&$sp_plugin,'addOriginalSource'));
  add_filter('post_link', array(&$sp_plugin,'changeTitleLink'));
  add_filter('the_editor', array(&$sp_plugin,'addAdminSourceInformation'));
 }

/// Template tags
function the_syndicated_posts() {
  if (!isset($sp_plugin) && class_exists("SyndicatedPostingPlugin")) {
    $sp_plugin = new SyndicatedPostingPlugin();
  }
  return $sp_plugin->getSyndicatedPosts();
}

function the_nonsyndicated_posts() {
  if (!isset($sp_plugin) && class_exists("SyndicatedPostingPlugin")) {
    $sp_plugin = new SyndicatedPostingPlugin();
  }
  return $sp_plugin->getNonSyndicatedPosts();
}
?>
