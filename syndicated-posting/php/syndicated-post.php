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
    var $post_date;
    var $post_date_gmt;
    // TODO: Check
    var $post_type = 'syndicate';
    

    // Constructor
    function SyndicatedPost() {
      
    }

    function fillFromRss($rss){
      global $wpdb;

      // Title
      if (!empty($rss['title'])) {
        $this->post_title = $wpdb->escape($rss['title']);
      }

      // Post date
      if (!empty($rss['published'])) {
        $this->post_date = $wpdb->escape($rss['published']);
        $this->post_date_gmt = $wpdb->escape($rss['published']);
      }
      
      // Link -> source link Article Url
      //['link']

      // SOurce Pub title

      // Author name
      // ['author_name']

      // RSS feeds use Description
      if (!empty($rss['description'])) {
        $this->post_content = $wpdb->escape($rss['description']);
      }

      // ATOM feeds use content
      if (!empty($rss['atom_content'])) {
        $this->post_content = $wpdb->escape($rss['atom_content']);
      }

      // GUID
      if (!empty($rss['guid'])) {
        //        $this->post_content = $rss['guid'];
      }

    }

  }
}

?>