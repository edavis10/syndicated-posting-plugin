<?php
if (!class_exists("SyndicatedPost")) {
  class SyndicatedPost {
    var $post_content;
    var $post_excerpt;
    var $post_title;
    var $post_category;
    var $post_status;
    var $post_name; // url
    var $comment_status;
    var $ping_status;
    // TODO: Check
    var $post_type = 'syndicate';
    

    // Constructor
    function SyndicatedPost() {
      
    }

    function fillFromRss($rss){
      // Title
      if (!empty($rss['title'])) {
        $this->post_title = $rss['title'];
      }
      
      // Link -> source link Article Url

      // SOurce Pub title

      // Description TODO: ATOM feed data also
      if (!empty($rss['description'])) {
        $this->post_content = $rss['description'];
      }

      // GUID
      if (!empty($rss['guid'])) {
        $this->post_content = $rss['guid'];
      }

    }

  }
}

?>