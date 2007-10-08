# Syndicated Posting Plugin #

## About ##

This plugin gives WordPress the ability to read a set of web feeds and
post their content online as a blog post. The feeds are stored locally
and can be filtered using a set of search terms and categories.

## How to setup ##

1. Download the plugin to your Wordpress install.
2. Activate the plugin using the Wordpress administration panel.
3. Configure the options in the "Options" > "Syndication" panel.
4. Add your Feed URLs to the Syndication panel ("Manage" > "Syndication").

## How to use the Panels ##

Once the plugin is installed and activated, administrators will have
two new panels that can be used with this plugin.  The first is the
Options panel which can be found under "Options" > "Syndication" and
the second is the "Syndication Panel" found under "Manage" >
"Syndication".

### Options panel ###

The Options panel is used to configure some global settings for the
plugin.

"Show at most x prospects" can be set so the results of the searches
are spread across multiple pages.  For example if "30" is used and
there is 63 prospects, the "Syndication Panel" will have 3 pages (2
pages of 30 and 1 page of 3).

Each prospect is stored in the database until they are older than the
second setting, "Keep unposted prospects for x days".  Daily, a
Wordpress cron job is run to go through older prospects and purge them
from the database.  It will **not** remove any prospect that has been
syndicated (turned into a post).

### Syndication Panel ###

The Syndication Panel has two configuration areas, the Feed URLs and
the search terms.

You must enter the url to the XML web feeds in the area "Feed URLs".
They can be delimited by a newline or commas.  These URLs will be
polled for content and will be added to the database as prospects.
Both RSS and Atom feeds are supported.

"Search Phrases" can be used to filter the prospect title and content
and should be used to show the most relevant prospects.  Each set of
search terms is stored for a Wordpress category.  If a post is
syndicated while looking at a category, that category will be
automatically assigned to the new post.

## Workflows  ##

The are two distinct workflows for this plugin, the prospect generator
and the administrator syndicating a post.

The prospect generator is scheduled by the Wordpress cron to run
hourly and will run in the background without any user interaction.
It will:

1. This generator will take the set of feed URLs from the database.
2. Will poll each of them to see if there is any new content.
3. When it finds some new content, it will add the new content to the Prospects list for an
administrator to use.

The second workflow is that of the administrator who is wanting to syndicate a prospect:

1. First they need to use the Syndication Panel to find a prospect they like.
2. Search terms and categories should be used to filter the results down to an area of interest.
3. Once a good prospect is found, the administrator will click the "Syndicate" link for the prospect.
4. The System will create a new draft post with the content and metadata for the prospect.
5. The administrator will be redirected to the edit page for the post where they can assign it to categories or add extra content.
6. Once the administrator has completed any modification, the post should be "Published" which will make the post public.
7. The post is then displayed like every other post with two exceptions:
  * the peramalink is linked to the source peramalink for the post.
  * the content of the post is prefixed with a short message showing where the post was originally published at.


## Credits ##

Syndicated Posting was co-developed by [Eric Davis of Little Stream 
Software](http://www.littlestreamsoftware.com) and [Peter Chester of Shane and Peter Inc.](http://www.shaneandpeter.com)

New post icon provided by [Tom M](http://strawbee.com/2005/11/06/tiny-little-icons/)
