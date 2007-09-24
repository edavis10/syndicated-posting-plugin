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
    var $meta_source_title;
    var $meta_source_link;
    

    // Constructor
    function SyndicatedPost() {
      
    }

    function cleanTags($content) {
      
      $clean = nl2br(strip_tags(str_replace("<p","\n",$content)));
      return $clean;
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
      if (!empty($rss['author'])) {
        $this->meta_author = $wpdb->escape($rss['author']);
      }

      /// ATOM
      if (!empty($rss['author_name'])) {
        $this->meta_author = $wpdb->escape($rss['author_name']);
      }

      // RSS feeds use Description
      if (!empty($rss['description'])) {
        $this->post_content = $wpdb->escape($this->cleanTags($rss['description']));
      }

      // ATOM feeds use content
      if (!empty($rss['atom_content'])) {
        $this->post_content = $wpdb->escape($this->cleanTags($rss['atom_content']));
      }

    }


    function fillFromPost($post,$meta){
      global $wpdb;

      // Title
      if (!empty($post['post_title'])) {
        $this->post_title = $wpdb->escape($post['post_title']);
      }

      // Content
      if (!empty($post['post_content'])) {
        $this->post_content = $wpdb->escape($post['post_content']);
      }

      // Syndicated Author
      if (!empty($meta['syndicated_author'])) {
        $this->meta_author = $wpdb->escape($meta['syndicated_author']);
      }

      // Syndicated Link
      if (!empty($meta['syndicated_link'])) {
        $this->meta_link = $wpdb->escape($meta['syndicated_link']);
      }

      // Syndicated Source Title
      if (!empty($meta['syndicated_source_title'])) {
        $this->meta_source_title = $wpdb->escape($meta['syndicated_source_title']);
      }

      // Syndicated Source Link
      if (!empty($meta['syndicated_source_link'])) {
        $this->meta_source_link = $wpdb->escape($meta['syndicated_source_link']);
      }

      $this->post_type = 'post';
    }

  }
}

?>
