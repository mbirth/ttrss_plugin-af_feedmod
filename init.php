<?php

class Af_Feedmod extends Plugin implements IHandler
{
    private $host;

    function about()
    {
        return array(
            1.0,   // version
            'Replace feed contents by contents from the linked page',   // description
            'mbirth',   // author
            false,   // is_system
        );
    }

    function api_version()
    {
        return 2;
    }

    function init($host)
    {
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
        global $fetch_last_content_type;

        $json_conf = $this->host->get($this, 'json_conf');
        $data = json_decode($json_conf, true);
        
        if (!is_array($data)) {
            // no valid JSON or no configuration at all
            return $article;
        }

        foreach ($data as $urlpart=>$config) {
              $articleMarker = "feedmod,".$article['owner_uid'].",".md5($urlpart.print_r($config, true)).":";
            if (strpos($article['link'], $urlpart) === false) continue;   // skip this config if URL not matching
            if (false && strpos($article['plugin_data'], $articleMarker) !== false) {
                // do not process an article more than once
                if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
                break;
            }
            
            $link = trim($article['link']);
            if(is_array($config['reformat'])){
                $link = $this->reformat_url($link, $config['reformat']);
            }
            
            

            if (version_compare(VERSION, '1.7.9', '>=')) {
                $html = fetch_file_contents($link);
                $content_type = $fetch_last_content_type;
            } else {
                // fallback to file_get_contents()
                $html = file_get_contents($link);

                // try to fetch charset from HTTP headers
                $headers = $http_response_header;
                $content_type = false;
                foreach ($headers as $h) {
                    if (substr(strtolower($h), 0, 13) == 'content-type:') {
                        $content_type = substr($h, 14);
                        // don't break here to find LATEST (if redirected) entry
                    }
                }
            }
            
            $charset = false;
            if (!isset($config['force_charset'])) {
                if ($content_type) {
                    preg_match('/charset=(\S+)/', $content_type, $matches);
                    if (isset($matches[1]) && !empty($matches[1])) $charset = $matches[1];
                }
            } else {
                // use forced charset
                $charset = $config['force_charset'];
            }
            
            if ($charset && isset($config['force_unicode']) && $config['force_unicode']) {
                $html = iconv($charset, 'utf-8', $html);
                $charset = 'utf-8';
            }
            
            switch ($config['type']) {
                case 'split':
                    foreach($config['steps'] as $step){
                          if(isset($step['after'])){
                            $result = preg_split ($step['after'], $html);
                              $html = $result[1];
                        }
                        if(isset($step['before'])){
                            $result = preg_split ($step['before'], $html);
                              $html = $result[0];
                        }
                    }
                    if(strlen($html) == 0)
                        break;
                    if(isset($config['cleanup'])){
                       foreach($config['cleanup'] as $cleanup){
                           $html = preg_replace($cleanup, '', $html);
                       }
                    }
                     
                    $article['content'] = $html;
                    $article['plugin_data'] = $articleMarker . $article['plugin_data'];                        
                        
                break;
                
                case 'xpath':
                    $doc = new DOMDocument();
    
                    if ($charset && isset($config['force_unicode']) && $config['force_unicode']) {
                        $html = iconv($charset, 'utf-8', $html);
                        $charset = 'utf-8';
                    }
                    
                    if ($charset) {
                            $html = '<?xml encoding="' . $charset . '">' . $html;
                    }
                    
                    @$doc->loadHTML($html);

                    if ($doc) {
                        $basenode = false;
                        $xpath = new DOMXPath($doc);
                        $entries = $xpath->query('(//'.$config['xpath'].')');   // find main DIV according to config

                        if ($entries->length > 0) $basenode = $entries->item(0);

                        if ($basenode) {
                            // remove nodes from cleanup configuration
                            if (isset($config['cleanup'])) {
                                if (!is_array($config['cleanup'])) {
                                    $config['cleanup'] = array($config['cleanup']);
                                }
                                foreach ($config['cleanup'] as $cleanup) {
                                    $nodelist = $xpath->query('//'.$cleanup, $basenode);
                                    foreach ($nodelist as $node) {
                                        if ($node instanceof DOMAttr) {
                                            $node->ownerElement->removeAttributeNode($node);
                                        }
                                        else {
                                            $node->parentNode->removeChild($node);
                                        }
                                    }
                                }
                            }
                            $article['content'] = $doc->saveXML($basenode);
                            $article['plugin_data'] = $articleMarker . $article['plugin_data'];
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
    
    function reformat_url($url, $options){
        foreach($options as $option){
            switch($option['type']){
                case 'replace':
                    $url = str_replace($option['search'], $option['replace'], $url);
                break;
                case 'regex':
                    $url = preg_replace($option['pattern'], $option['replace'], $url);
                break;
            }
        }
        return $url;
    }

    function hook_prefs_tabs($args)
    {
        print '<div id="feedmodConfigTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=af_feedmod"
            title="' . __('FeedMod') . '"></div>';
    }

    function index()
    {
        $pluginhost = PluginHost::getInstance();
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
