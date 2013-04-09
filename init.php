<?php

class Af_Feedmod extends Plugin implements IHandler
{
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

        $host->add_hook($host::HOOK_PREFS_TABS, $this);
# only allowed for system plugins:        $host->add_handler('pref-feedmod', '*', $this);
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }

    function csrf_ignore($method)
    {
        $csrf_ignored = array("index", "edit");
        return array_search($method, $csrf_ignored) !== false;
    }

    function before($method)
    {
        if ($_SESSION["uid"]) {
            return true;
        }
        return false;
    }

    function after()
    {
        return true;
    }

    function hook_article_filter($article)
    {
        $json_conf = $this->host->get($this, 'json_conf');
        $owner_uid = $article['owner_uid'];
        $data = json_decode($json_conf, true);

        foreach ($data as $urlpart=>$config) {
            if (strpos($article['link'], $urlpart) === false) continue;   // skip this config if URL not matching
            if (strpos($article['plugin_data'], "feedmod,$owner_uid:") !== false) {
                // do not process an article more than once
                if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
                break;
            }

            switch ($config['type']) {
                case 'xpath':
                    $doc = new DOMDocument();
                    @$doc->loadHTML(fetch_file_contents($article['link']));

                    if ($doc) {
                        $basenode = false;
                        $xpath = new DOMXPath($doc);
                        $entries = $xpath->query('(//'.$config['xpath'].')');   // find main DIV according to config

                        if ($entries->length > 0) $basenode = $entries->item(0);

                        if ($basenode) {
                            $article['content'] = $doc->saveXML($basenode);
                            $article['plugin_data'] = "feedmod,$owner_uid:" . $article['plugin_data'];
                        }
                    }
                    break;

                default:
                    // unknown type or invalid config
                    continue;
            }

            break;   // if we got here, we found the correct entry in $data, do not process more
        }

        return $article;
    }

    function hook_prefs_tabs($args)
    {
        print '<div id="instanceConfigTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=af_feedmod"
            title="' . __('FeedMod') . '"></div>';
    }

    function index()
    {
        global $pluginhost;
        $json_conf = $pluginhost->get($this, 'json_conf');

        print "<form dojoType=\"dijit.form.Form\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
            evt.preventDefault();
            if (this.validate()) {
                new Ajax.Request('backend.php', {
                    parameters: dojo.objectToQuery(this.getValues()),
                    onComplete: function(transport) {
                        if (transport.responseText.indexOf('error')>=0) notify_error(transport.responseText);
                            else notify_info(transport.responseText);
                    }
                });
                //this.reset();
            }
            </script>";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_feedmod\">";

        print "<table width='100%'><tr><td>";
        print "<textarea dojoType=\"dijit.form.SimpleTextarea\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
        print "</td></tr></table>";

        print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button>";

        print "</form>";
    }

    function save()
    {
        $json_conf = $_POST['json_conf'];

        if (is_null(json_decode($json_conf))) {
            echo __("error: Invalid JSON!");
            return false;
        }

        $this->host->set($this, 'json_conf', $json_conf);
        echo __("Configuration saved.");
    }

}
