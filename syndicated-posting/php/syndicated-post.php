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

    var $meta_author;
    var $meta_link;
    

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
      /// RSS
      if (!empty($rss['pubdate'])) {
        $this->post_date = $wpdb->escape(date( 'Y-m-d H:i:s',strtotime($rss['pubdate'])));
        $this->post_date_gmt = $wpdb->escape(date( 'Y-m-d H:i:s',strtotime($rss['pubdate'])));
      }

      /// ATOM
      if (!empty($rss['published'])) {
        $this->post_date = $wpdb->escape($rss['published']);
        $this->post_date_gmt = $wpdb->escape($rss['published']);
      }
      
      // Content Link
      if (!empty($rss['link'])) {
        $this->meta_link = $wpdb->escape($rss['link']);
      }


      // Author name
      /// RSS
      if (!empty($rss['dc']['creator'])) {
        $this->meta_author = $wpdb->escape($rss['dc']['creator']);
      }

      /// ATOM
      if (!empty($rss['author_name'])) {
        $this->meta_author = $wpdb->escape($rss['author_name']);
      }

      // RSS feeds use Description
      if (!empty($rss['description'])) {
        $this->post_content = $wpdb->escape($rss['description']);
      }

      // ATOM feeds use content
      if (!empty($rss['atom_content'])) {
        $this->post_content = $wpdb->escape($rss['atom_content']);
      }

    }

  }
}

?>