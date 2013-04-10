<?php

$config = array(
    'type' => 'xpath',
    'xpath' => 'div[@class="meldung_wrapper"]',
);

// http://www.heise.de/newsticker/heise-atom.xml

$article = array(
    'link' => 'http://www.heise.de/newsticker/meldung/Fruehjahrspatches-Microsoft-9-Adobe-3-1838175.html/from/atom10',
    'content' => 'This is the feed content',
    'plugin_data' => '',
);

$doc = new DOMDocument();

$html = file_get_contents($article['link']);

// BEGIN --- New code
$headers = $http_response_header;
$content_type = false;
foreach ($headers as $h) {
    if (substr(strtolower($h), 0, 13) == 'content-type:') {
        $content_type = substr($h, 14);
        // don't break here to find LATEST (if redirected) entry
    }
}

$charset = false;
if ($content_type) {
    preg_match('/charset=(\S+)/', $content_type, $matches);
    if (isset($matches[1]) && !empty($matches[1])) $charset = $matches[1];
}

// END --- New code

echo 'CHARSET: ' . $charset . PHP_EOL;


$doc->loadHTML('<?xml encoding="' . $charset . '">' . $html);

echo 'ENCODING: ' . $doc->encoding . PHP_EOL;

if ($doc) {
    $basenode = false;
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('(//'.$config['xpath'].')');   // find main DIV according to config

    var_dump($entries);

    if ($entries->length > 0) $basenode = $entries->item(0);

    if ($basenode) {
       $article['content'] = $doc->saveXML($basenode);
       $article['plugin_data'] = "feedmod,$owner_uid:" . $article['plugin_data'];
    }
}

print_r($article);
