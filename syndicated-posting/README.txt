# Syndicated Posting Plugin #

## About ##

This plugin gives WordPress the ability to read a set of web feeds and
post their content online as a blog post. The feeds are stored locally
and can be filtered using a set of search terms. You can find the
panel under "Manage" > "Syndication".

## How to setup ##

Once the plugin is installed and activated, administrators will have
two new panels that can be used with this plugin.  The first is the
Options panel which can be found under "Options" > "Syndication" and
the second is the "Syndication Panel" found under "Manage" >
"Syndication".

The Options panel is used to configure some global settings for the
plugin.  "Show at most x prospects" can be set so the results of the
searches are spread across multiple pages.  For example if "30" is
used and there is 63 prospects, the "Syndication Panel" will have 3
pages (2 pages of 30 and 1 page of 3).  Each prospect is stored in the
database until they are older than the second setting, "Keep unposted
prospects for x days".  Daily a Wordpress cron job is run to go
through older prospects and purge them from the database.  It will
**not** remove any prospect that has been syndicated (turned into a
post).

The Syndication Panel also needs some things configured before it will
work.  You must enter the url to the XML web feeds in the area "Feed
URLs".  They can be deimited by a newline or commas.  Optionally you
can enter "Search Phrases" in the other area on this panel.  Search
phrases are used as an OR filter on the prospect title and content and
shold be used to show the most revelant prospects.  Each set of search
terms is stored for a Wordpress category.

## How to use it ##

The are two distinct workflows for this plugin.  The first is that of
the prospect generator.  This workflow is scheduled by the Wordpress
cron to run hourly and is meant to run in the background without any
user interaction.  This generator will take the set of feed URLs and
will poll them to see if there is any new content.  When it finds some
new content, it will add the new content to the Prospects list for an
administrator to use.

The other workflow is that of the administrator who is wanting to syndicate a prospect.

1. First they need to the Syndication Panel to find a prospect they like.
2. Search terms and categories should be used to filter the results down to an area of interest.
3. Once a good prospect is found, the administrator will click the "Syndicate" link for the prospect.
4. The System will create a new draft post with the content and metadata for the prospect.
5. The administrator will be redirected to the edit page for the post where they can assign it to categories or add extra content.
6. Once the administrator has completed any modification, the post should be "Published" which will make the post public.
7. The post is then displayed like every other post with two exceptions:
  * the peramalink is linked to the source peramalink for the post.
  * the content of the post is prefixed with a short message showing where the post was originally published at.


## Credits ##

Syndicated Posting was co-developed by Eric Davis of Little Stream 
Software and Peter Chester of Shane and Peter Inc.

New post icon provided by Tom M:
   http://strawbee.com/2005/11/06/tiny-little-icons/
