ttrss_plugin-af_feedmod
=======================

Installation
--------------------

This is a plugin for Tiny Tiny RSS (tt-rss). It allows you to replace an article's contents by the contents of an element on the linked URL's page.

Checkout the directory into your plugins folder like this (from tt-RSS root directory):

```sh
$ cd /var/www/ttrss
$ git clone git://github.com/mbirth/ttrss_plugin-af_feedmod.git plugins/af_feedmod
```

Then enable the plugin in preferences.


Configuration
--------------------

The configuration is done in JSON format. In the preferences, you'll find a new tab called *FeedMod*. Use the large field to enter/modify the configuration data and click the **Save** button to store it.

A configuration looks like this:

```json
{

"heise.de": {
    "type": "xpath",
    "xpath": "div[@class='meldung_wrapper']"
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
}

}
```

The *array key* is part of the URL of the article links(!). You'll notice the `golem0Bde0C` in the last entry: That's because all their articles link to something like `http://rss.feedsportal.com/c/33374/f/578068/p/1/s/3f6db44e/l/0L0Sgolem0Bde0Cnews0Cthis0Eis0Ean0Eexample0A10Erss0Bhtml/story01.htm` and to have the plugin match that URL and not interfere with other feeds using *feedsportal.com*, I used the part `golem0Bde0C`.

The **type** has to be `xpath` for now. Maybe there will be more types in the future.

The **xpath** value is the actual Xpath-element to fetch from the linked page.


If you get an error about "Invalid JSON!", you can use [JSONLint](http://jsonlint.com/) to locate the erroneous part.
