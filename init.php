<?php

class Af_Feedmod extends Plugin implements IHandler
{
    private $host;
    private $debug;
    private $charset;

    function about()
    {
        return array(
            1.0,   // version
            'Replace feed contents by contents from the linked page',   // description
            'mbirth/m42e',   // author
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

    private function _log($msg) {
       if ($this->debug) trigger_error($msg, E_USER_WARNING);
    } 

    function hook_article_filter($article)
    {
        if (($config = $this->getConfigSection($article['link'])) !== FALSE){
            $articleMarker = "feedmod,".$article['owner_uid'].",".md5(print_r($config, true)).":";
            if (strpos($article['plugin_data'], $articleMarker) !== false) {
                // do not process an article more than once
                if (isset($article['stored']['content'])) $article['content'] = $article['stored']['content'];
                return $article;
            }
            
            $link = $this->reformatUrl($article['link'], $config);
                     
            $article['content'] = $this->getNewContent($link, $config);
            $article['plugin_data'] = $articleMarker . $article['plugin_data'];                        
        }

        return $article;
    }

    function getConfigSection($url){
        $data = $this->getConfig(); 
        if(is_array($data)){
           foreach ($data as $urlpart=>$config) {
              if (strpos($url, $urlpart) === false) continue;   // skip this config if URL not matching
              return $config;
           }
        }
        return FALSE;
    }

    function getConfig(){
       $json_conf = $this->host->get($this, 'json_conf');
       $data = json_decode($json_conf, true);
       $this->debug = isset($data['debug']) && $data['debug'];
       return $data;
    }

    function reformatUrl($url, $config){
       $link = trim($url);
       if(is_array($config['reformat'])){
          $link = $this->reformat($link, $config['reformat']);
       }
       return $link;
    }
    
    function reformat($string, $options){
        foreach($options as $option){
            switch($option['type']){
                case 'replace':
                    $string = str_replace($option['search'], $option['replace'], $string);
                break;
                case 'regex':
                    $string = preg_replace($option['pattern'], $option['replace'], $string);
                break;
            }
        }
        return $string;
    }

    function getNewContent($link, $config){
       $html = $this->getArticleContent($link, $config);
       $html = $this->processArticle($html, $config);
       return $html;

    }
    function getArticleContent($link, $config){
        global $fetch_last_content_type;
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

       $this->charset = false;
       if (!isset($config['force_charset'])) {
          if ($content_type) {
             preg_match('/charset=(\S+)/', $content_type, $matches);
             if (isset($matches[1]) && !empty($matches[1])) $this->charset = $matches[1];
          }
       } else {
          // use forced charset
          $this->charset = $config['force_charset'];
       }

       if ($this->charset && isset($config['force_unicode']) && $config['force_unicode']) {
          $html = iconv($this->charset, 'utf-8', $html);
          $this->charset = 'utf-8';
       }
       return $html;
    }
    function processArticle($html, $config){
       switch ($config['type']) {
       case 'split':
          $html = $this->performSplit($html, $config);
          break;

       case 'xpath':
          $html = $this->performXpath($html, $config);
          break;

       default:
          continue;
       }
       if(is_array($config['modify'])){
          $html = $this->reformat($html, $config['modify']);
       }
       return $html;
    }

    function performSplit($html, $config){
         $orig_html = $html;
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
             return $orig_html;
          if(isset($config['cleanup'])){
             foreach($config['cleanup'] as $cleanup){
                $html = preg_replace($cleanup, '', $html);
             }
          }
          return $html;
    }

    function performXpath($html, $config){
       $doc = new DOMDocument();

       if ($this->charset) {
          $html = '<?xml encoding="' . $this->charset . '">' . $html;
       }

       @$doc->loadHTML($html);

       if ($doc) {
          $basenode = false;
          $xpath = new DOMXPath($doc);
          $entries = $xpath->query('(//'.$config['xpath'].')');   // find main DIV according to config

          if ($entries->length > 0) $basenode = $entries->item(0);

          if (!$basenode) return $html;

          // remove nodes from cleanup configuration
          $basenode = $this->cleanupNode($xpath, $basenode, $config);
          $html = $doc->saveXML($basenode);
       }
       return $html;
    }

    function cleanupNode($xpath, $basenode, $config){
       if(($cconfig = $this->getCleanupConfig($config))!== FALSE){
          foreach ($cconfig as $cleanup) {
             if(strpos($cleanup, "./") !== 0){
                $cleanup = '//'.$cleanup;
             }
             $nodelist = $xpath->query($cleanup, $basenode);
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
       return $basenode;
    }

    function getCleanupConfig($config){
       $cconfig = false;
       
       if (isset($config['cleanup'])) {
          $cconfig = $config['cleanup'];
          if (!is_array($cconfig)) {
             $cconfig = array($cconfig);
          }
       }
       return $cconfig;
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
        print "<form dojoType=\"dijit.form.Form\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
            evt.preventDefault();
            dojo.query('#test_result').attr('innerHTML', '');
            new Ajax.Request('backend.php', {
                parameters: dojo.objectToQuery(this.getValues()),
                onComplete: function(transport) {
                    if (transport.responseText.indexOf('error')>=0 && transport.responseText.indexOf('error') <= 10) notify_error(transport.responseText);
                    else
                       dojo.query('#test_result').attr('innerHTML', transport.responseText);
                }
            });
            </script>";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"test\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_feedmod\">";

        print "<table width='100%'><tr><td>";
        print "<input dojoType=\"dijit.form.TextBox\" name=\"test_url\" style=\"font-size: 12px; width: 99%;\" />";
        print "</td></tr></table>";
        print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Test")."</button>";
        print "</form>";
        print "<div id='test_result'></div>";
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

    function test(){
       $test_url = $_POST['test_url'];
       $config = $this->getConfigSection($test_url);
       $test_url = $this->reformatUrl($test_url, $config);
       if($config === FALSE) 
          echo "error: URL did not match";
       else
       {
          echo "<h1>RESULT:</h1>";
          echo $this->getNewContent($test_url, $config); 
       }
    }

}
