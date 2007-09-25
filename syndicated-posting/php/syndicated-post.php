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
    var $post_type = 'syndicate';

    var $meta_author;
    var $meta_link;
    var $meta_source_title;
    var $meta_source_link;
    

    // Constructor
    function SyndicatedPost() {
      // Use right now as the default date
      $this->post_date = date("Y-m-d H:i:s");
      $this->post_date_gmt = date("Y-m-d H:i:s");
    }

    function cleanTags($content) {
      $clean = (strip_tags($content,'<p>'));
      return $clean;
    }

    function limitParagraphs($content, $number) {
      $limited_content = '';
      $results = explode("<p>",$content);

      for ($index = 0; ( ($index < count($results)) && ($index <= $number)) ; $index++) {

        // Remove it's para tags if there
        $item = trim(str_replace("<p>","",str_replace("</p>","",$results[$index])));
        if ($item != "") {
          $limited_content .= "<p>\n";
          $limited_content .= $item . "\n";
          $limited_content .= "</p>\n";
        }
      }
      return $limited_content;
    }

    function fillFromRss($rss,$defaultAuthor=""){
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
      $this->meta_author = $wpdb->escape($defaultAuthor);
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
        $this->post_content = $wpdb->escape($this->cleanTags($this->limitParagraphs($rss['description'],3)));
      }

      // ATOM feeds use content
      if (!empty($rss['atom_content'])) {
        $this->post_content = $wpdb->escape($this->cleanTags($this->limitParagraphs($rss['atom_content'],3)));
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
