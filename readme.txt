=== VK Link Target Controller ===
Contributors: vektor-inc,kurudrive,dswebstudio,bizvektor
Donate link:
Tags: redirection,link,recent posts,list,page,post
Requires at least: 3.8
Tested up to: 4.3.1
Stable tag: 1.2.2
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Redirect your visitors to another page than the post content when they click on the post title.

== Description ==

= Plugin presentation =

VK Link Target Controller enables to redirect your visitors to another page than the post content when they click on the post title that displays on the Recent Posts list or the Archives Page.

= Example of use =

Let's say you have a new product for sale on eBay or Etsy. 
You find it annoying to write a complete post entry on your blog (or WordPress powered website) to explain you have a new product to sell there and would like your visitors to access directly the product page.

With VK Link Target Controller your visitors will access directly that product page when clicking on the post title.
Fast redirection to the product you want to sell!.

= GitHub repository =

VK Link Target Controller official repository on GitHub.
[https://github.com/kurudrive/vk-link-target-controller](https://github.com/kurudrive/vk-link-target-controller)
Latest plugin version is always on GitHub.

== Installation ==

= Installation and default settings =

1. Install the plugin and activate it like other WordPress plugins.
2. Go to VK Link Target Controller settings screen (`Settings > Link Target Controller`).
3. Select the post types where you want to use VK Link Target Controller.

Notes:

* By default **none of your post types are selected**
* VK Link Target Controller supports custom post types

= Add a link for redirection =

VK Link Target Controller adds a meta box to your posts edit screen under the main content editing area.
If you need a redirection for this post just fill in the field with the destination url.

VK Link Target Controller supports both

* external links like `http://bizvektor.com/en/` (will link to `http://bizvektor.com/en/`)
* internal links like `/theme-documentation/bizvektor-quick-start/` (will link to `http://thisdomain.com/theme-documentation/bizvektor-quick-start/`)

For external (or absolute) urls both http:// and trailing slash are optional.
For internal (relative) urls you need to add a slash "/" at the beginning as shown on example above (see also Screenshots).

If you want the link to open in a new window then check the corresponding option.

== Frequently Asked Questions ==

= Are custom post types supported? =

Yes.

= Where do I add my link? =

You can add your link for redirection on the post edit screen.
Just look for the "URL to redirect to" section under the main edit screen.

= What are the options for the link? =

The plugin supports both external and internal links.
You can choose to open the links in a new window if you need.
Please refer to the Installation tab or the Screenshots tab for more information.

= Can I add URLs with non-Latin characters? = 

VK Link Target Controller supports Japanese in URLs so probably you can add other non-Latin characters too.

= My link won't open on a new window. =

VK Link Target Controller adds a filter on the `the_permalink()` WordPress function, which means the redirection won't work if your theme uses another function, for example `get_permalink()` to display the links.
In order to have the link opened in a new window VK Link Target Controller needs a theme with the post id as id on the `<a>` parent element.

Your theme probably has it if it follows the WordPress Theme recommendations.

Example:
`
<div class="post-item post-block front-page-list" id="post-<?php the_ID(); ?>">
	<a href="<?php the permalink(); ?>">
 		<?php the_title(); ?>
	</a>
</div>
`

= Any chance to get the plugin translated in my language? =

For now the plugin is available in English and Japanese only.
But we have a .pot file available so feel free to translate it in your language if you have some time.

== Screenshots ==

1. Localisation for VK Link Target Controller settings page. 
2. VK Link Target Controller settings page. Choose the post types where the plugin should be activated.
3. An absolute url to an external link with open the link in a separate window option selected (both http:// and trailing slash are optional). 
4. A relative url that refer to a page of your website: **note slash "/" at the beginning**.

== Changelog ==

= 1.2 =
* Support link to the file.

= 1.1 =
* Support post type "page".

= 1.0 =
* Publication on WordPress.org
* Stable version.

== Upgrade Notice ==

= 1.0.1 =
* [ bug fix ] Remove robots noindex,nofollow from front-page header.