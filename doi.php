<?php
include("simple_html_dom.php");

$doi = (isset($_REQUEST["doi"]))? $_REQUEST["doi"] : "10.1086/377226";

//next example will recieve all messages for specific conversation
$service_url = 'http://search.crossref.org/dois?q=' . urlencode($doi);
echo file_get_contents($service_url);