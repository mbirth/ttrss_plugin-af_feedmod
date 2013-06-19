ttrss_plugin-af_feedmod
=======================

This is a plugin for Tiny Tiny RSS (tt-rss). It allows you to replace an article's contents by the contents of an element on the linked URL's page, i.e. create a "full feed".


Installation
------------

Checkout the directory into your plugins folder like this (from tt-RSS root directory):

```sh
$ cd /var/www/ttrss
$ git clone git://github.com/mbirth/ttrss_plugin-af_feedmod.git plugins/af_feedmod
```

Then enable the plugin in preferences.


Configuration
-------------

The configuration is done in JSON format. In the preferences, you'll find a new tab called *FeedMod*. Use the large field to enter/modify the configuration data and click the **Save** button to store it.

A configuration looks like this:

```json
{

"heise.de": {
    "type": "xpath",
    "xpath": "div[@class='meldung_wrapper']",
    "force_charset": "utf-8"
},
"berlin.de/polizei": {
    "type": "xpath",
    "xpath": "div[@class='bacontent']"
},
"n24.de": {
    "type": "xpath",
    "xpath": "div[@class='news']"
},
"golem0Bde0C": {
    "type": "xpath",
    "xpath": "article"
},
"oatmeal": {
    "type": "xpath",
    "xpath": "div[@id='comic']"
},
"blog.beetlebum.de": {
    "type": "xpath",
    "xpath": "div[@class='entry-content']",
    "cleanup": [ "header", "footer" ],
}

}
```

The *array key* is part of the URL of the article links(!). You'll notice the `golem0Bde0C` in the last entry: That's because all their articles link to something like `http://rss.feedsportal.com/c/33374/f/578068/p/1/s/3f6db44e/l/0L0Sgolem0Bde0Cnews0Cthis0Eis0Ean0Eexample0A10Erss0Bhtml/story01.htm` and to have the plugin match that URL and not interfere with other feeds using *feedsportal.com*, I used the part `golem0Bde0C`.

**type** has to be `xpath` for now. Maybe there will be more types in the future.

The **xpath** value is the actual Xpath-element to fetch from the linked page. Omit the leading `//` - they will get prepended automatically.

If **type** was set to `xpath` there is an additional option **cleanup** available. Its an array of Xpath-elements (relative to the fetched node) to remove from the fetched node. Omit the leading `//` - they will get prepended automatically.

**force_charset** allows to override automatic charset detection. If it is omitted, the charset will be parsed from the HTTP headers or loadHTML() will decide on its own.


If you get an error about "Invalid JSON!", you can use [JSONLint](http://jsonlint.com/) to locate the erroneous part.


XPath
-----

### Tools

To test your XPath expressions, you can use these Chrome extensions:

* [XPath Helper](https://chrome.google.com/webstore/detail/xpath-helper/hgimnogjllphhhkhlmebbmlgjoejdpjl)
* [xPath Viewer](https://chrome.google.com/webstore/detail/xpath-viewer/oemacabgcknpcikelclomjajcdpbilpf)
* [xpathOnClick](https://chrome.google.com/webstore/detail/xpathonclick/ikbfbhbdjpjnalaooidkdbgjknhghhbo)


### Examples

Some XPath expressions you could need (the `//` is automatically prepended and must be omitted in the FeedMod configuration):

##### HTML5 &lt;article&gt; tag

```html
<article>…article…</article>
```

```xslt
//article
```

##### DIV inside DIV

```html
<div id="content"><div class="box_content">…article…</div></div>`
```

```xslt
//div[@id='content']/div[@class='box_content']
```

##### Multiple classes

```html
<div class="post-body entry-content xh-highlight">…article…</div>
```

```xslt
//div[starts-with(@class ,'post-body')]
```
or
```xslt
//div[contains(@class, 'entry-content')]
```
