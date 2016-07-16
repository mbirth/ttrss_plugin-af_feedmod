<?php

require_once('fetch.php');

class Af_Feedmod extends Plugin implements IHandler
{
	private $host;

	private $mods;
	private $mods_loaded = false;

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

		$owner_uid = $article['owner_uid'];

		//
		// Load mods if they are not already loaded
		//
		if (!$this->mods_loaded) {
			//
			// Reading mod files
			//

			$this->mods = array();
			// bad (!) hardcoded path
			$mod_files = glob('plugins/af_feedmod/mods/*.json');
			foreach ($mod_files as $file) {
			    $json = file_get_contents($file);
			    $mod = json_decode($json, true);
			    if (json_last_error() != JSON_ERROR_NONE)
			    	continue;
			    
			    if (!isset($mod['match']) || !isset($mod['config']))
			    	continue;

			    $this->mods[$mod['match']] = $mod['config'];
			}

			// 
			// User mods 
			//

			$json_conf = $this->host->get($this, 'json_conf');
			$user_mods = json_decode($json_conf, true);
			if (is_array($user_mods))
				$this->mods = array_merge($this->mods, $user_mods);

			$this->mods_loaded = true;
		}

		// article is already fetched
		if (strpos($article['plugin_data'], "feedmod,$owner_uid:") !== false && isset($article['stored']['content'])) 
		{
			$article['content'] = $article['stored']['content'];
			return $article;
		}

		foreach ($this->mods as $urlpart=>$config) {
			if (strpos($article['link'], $urlpart) === false) continue;

			$content = fetch_article($article, $config);
			if (!$content)
				break;

			$article['content'] = $content;
			$article['plugin_data'] = "feedmod,$owner_uid:" . $article['plugin_data'];
		}

		return $article;
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
