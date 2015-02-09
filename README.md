# VK Link Target Controller
=========================

新着情報一覧などのリストで、タイトルのリンクをクリックした際に詳細ページにリンクするのではなく、特定のページや外部のページにリンク出来るようにするWordPress用プラグインです。

=========================

#VK Link Target Controller

##Plugin presentation

VK Link Target Controller enables to redirect your visitors to another content than the post content when they click on the post title that displays on the Recent Posts list or the Archives Page.

## Example of use

Let's say you have a new product for sale on eBay or Etsy. 

You find it annoying to write a complete post entry on your blog (or WordPress powered website) to explain you have a new product to sell there and would like your visitors to access directly the product page.

With VK Link Target Controller your visitors will access directly that product page when clicking on the post title.
Fast redirection to the product you want to sell!

## Installation and default settings

1. Install the plugin and activate it like other WordPress plugins.
2. Go to VK Link Target Controller settings screen (Settings > Link Target Controller).
3. Select the post types where you want to use VK Link Target Controller.

By default **none of your post types are selected**.

VK Link Target Controller supports custom post types.

## Adding a link for redirection

VK Link Target Controller adds a meta box to your posts edit screen under the main content editing area.

If you need a redirection for this post just fill in the field with the destination url.

VK Link Target Controller supports both
* external links like http://bizvektor.com/en/ (will link to http://bizvektor.com/en/)
* internal links like /theme-documentation/bizvektor-quick-start/ (will link to http://mywebsite.com/theme-documentation/bizvektor-quick-start/)

For external (or absolute) urls both http:// and trailing slash are optional.

For internal (relative) urls you need to add a slash "/" at the beginning as shown on example above.

If you want the link to open in a new window then check the corresponding option.

## Additional information

Japanese characters in urls are supported.

##Theme compatibility

VK Link Target Controller adds a filter on the `the_permalink()` WordPress function, which means the redirection won't work if your theme uses another function, for example `get_permalink()` to display the links.

In order to have the link opened in a new window VK Link Target Controller needs a theme with the post id as id on the <a> parent element.

Your theme probably has it if it follows the WordPress Theme recommendations.

Example:
```
<div class="post-item post-block front-page-list" id="post-<?php the_ID(); ?>">
 <a href="<?php the permalink(); ?>">
  <?php the_title(); ?>
 </a>
</div>
```