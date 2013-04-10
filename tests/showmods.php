<?php

$mods = glob('../mods/*.json');

foreach ($mods as $mod) {
    $json = file_get_contents($mod);
    $data = json_decode($json, true);
    echo $mod . ': ' . $data['name'] . PHP_EOL;
}
