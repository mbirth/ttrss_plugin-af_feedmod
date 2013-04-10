<?php

$ch = curl_init('http://www.heise.de/newsticker/meldung/Fruehjahrspatches-Microsoft-9-Adobe-3-1838175.html/from/atom10');
#curl_setopt($ch, CURLOPT_URL, "http://www.example.com/");
#curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
#curl_setopt($ch, CURLINFO_HEADER_OUT, true);

curl_exec($ch);

var_dump(curl_getinfo($ch,CURLINFO_CONTENT_TYPE));

