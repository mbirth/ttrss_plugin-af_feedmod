<?php

//
// Main global function for fetching and processing articles
//
function fetch_article($article, $config) {

	// the only config type supported now is 'xpath'
	if ($config['type'] != 'xpath')
		return false;

	$doc = new DOMDocument();
	$link = trim($article['link']);

	if (defined('VERSION') && version_compare(VERSION, '1.7.9', '>=')) {
		$html = fetch_file_contents($link);
		$content_type = $fetch_last_content_type;
	} 
	else {
		// fallback to file_get_contents()
		$html = file_get_contents($link);

		// try to fetch charset from HTTP headers
		$headers = $http_response_header;
		$content_type = false;
		foreach ($headers as $h) {
			if (substr(strtolower($h), 0, 13) == 'content-type:') {
				$content_type = substr($h, 14);
				// don't return here to find LATEST (if redirected) entry
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
			return $doc->saveXML($basenode);
		}
	}

	return $article;
}