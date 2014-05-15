<?php

require_once('../fetch.php');

if (count($argv) <= 2) {
    echo 'USAGE: php fetch.php [mod_file] [article_url]' . PHP_EOL;
    exit(1);
}

$mod = $argv[1];
$article_url = $argv[2];

//
// Getting json config
//

$json = file_get_contents($mod);
$data = json_decode($json, true);

echo "<pre>";
print_r($data);
echo "</pre>";

if (json_last_error() != JSON_ERROR_NONE) {
    echo 'Json error' . PHP_EOL;
    exit(1);
}

$config = $data['config'];

//
// Fetching article
//

$owner_uid = 100;
$article = array( 'link' => $article_url, 'plugin_data' => '' );
echo fetch_article($article, $config)['content'];
