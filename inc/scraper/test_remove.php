<?php
$html = '<div class="body-content">
  <p>Hello world</p>
  <aside class="others-read-inline"><h2>آنچه دیگران می‌خوانند</h2></aside>
</div>';
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

$esc_query = "//aside[contains(concat(' ', normalize-space(@class), ' '), ' others-read-inline ')]";
$esc_nodes = $xpath->query($esc_query);
for ($i = $esc_nodes->length - 1; $i >= 0; $i--) {
    $n = $esc_nodes->item($i);
    $n->parentNode->removeChild($n);
}

$body_nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' body-content ')]");
$body_node = $body_nodes->item(0);

$innerHTML = "";
foreach ($body_node->childNodes as $child) {
    $innerHTML .= $body_node->ownerDocument->saveHTML($child);
}
echo "Result:\n" . trim($innerHTML) . "\n";
