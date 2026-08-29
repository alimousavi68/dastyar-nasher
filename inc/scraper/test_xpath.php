<?php
$html = '<aside class="others-read-inline"><h2>آنچه دیگران می‌خوانند</h2></aside>';
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);
$query = "//aside[contains(concat(' ', normalize-space(@class), ' '), ' others-read-inline ')]";
$nodes = $xpath->query($query);
echo "Nodes found: " . ($nodes ? $nodes->length : 0) . "\n";
