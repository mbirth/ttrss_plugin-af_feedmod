<?php

class Af_Feedmod extends Plugin {

    private $link;
    private $host;

    function about()
    {
        return array(
            0.9,   // version
            'Replace feed contents by contents from the linked page',   // description
            'mbirth',   // author
            false,   // is_system
        );
    }

    function init($host)
    {
        $this->link = $host->get_link();
        $this->host = $host;

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_TAB, $this);
    }

    function hook_article_filter($article)
    {
        $sometext = $this->host->get($this, "sometext");
        
        $owner_uid = $article['owner_uid'];

        if (strpos($article['link'], 'heise.de') !== FALSE) {   // only process heise.de articles
            if (strpos($article['plugin_data'], "feedmod,$owner_uid:") === FALSE) {   // do not process an article more than once
                $doc = new DOMDocument();
                @$doc->loadHTML(fetch_file_contents($article['link']));

                $basenode = false;

                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//div.meldung_wrapper)');   // find main DIV

                    $matches = array();
                    foreach ($entries as $entry) {
                        $basenode = $entry;
                        break;
                    }

                    if ($basenode) {
                        $article['content'] = $doc->saveXML($basenode);
                        $article['plugin_data'] = "feedmod,$owner_uid:" . $article['plugin_data'];
                    }
                }
            } else if (isset($article['stored']['content'])) {
                $article['content'] = $article['stored']['content'];
            }
        }
        return $article;
    }

    function hook_prefs_tab($args)
    {
        if ($args != "prefPrefs") return;

        print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("FeedMod Plugin")."\">";

        print "<br/>";

        $sometext = $this->host->get($this, "sometext");

        print "<form dojoType=\"dijit.form.Form\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
            evt.preventDefault();
            if (this.validate()) {
                new Ajax.Request('backend.php', {
                    parameters: dojo.objectToQuery(this.getValues()),
                    onComplete: function(transport) {
                        notify_info(transport.responseText);
                    }
                });
                //this.reset();
            }
            </script>";
            
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"feedmod\">";

            print "<table width=\"100%\" class=\"prefPrefsList\">";

            print "<tr><td width=\"40%\">".__("Some text")."</td>";
            print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"sometext\" value=\"$sometext\"></td></tr>";

            print "</table>";

            print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
                __("Save")."</button>";

            print "</form>";

            print "</div>"; #pane
    }
            
    function save()
    {
        $sometext = explode(",", db_escape_string($this->link, $_POST["sometext"]));
        $sometext = array_map("trim", $sometext);
        $sometext = array_map("mb_strtolower", $sometext);
        $sometext = join(", ", $sometext);

        $this->host->set($this, "sometext", $sometext);

        echo __("Configuration saved.");
    }

}
