<?php

$config = array(
    'type' => 'xpath',
    'xpath' => 'div[@class="bacontent"]',
);

$article = array(
    'link' => 'http://www.berlin.de/polizei/presse-fahndung/archiv/383117/index.html',
    'content' => 'This is the feed content',
    'plugin_data' => '',
);

$doc = new DOMDocument();
$html = file_get_contents($article['link']);
$doc->loadHTML($html);

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