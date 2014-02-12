<?php
include("simple_html_dom.php");

$doi = (isset($_REQUEST["doi"]))? $_REQUEST["doi"] : "10.1086/377226";

//next example will recieve all messages for specific conversation
$service_url = 'http://search.crossref.org/dois?q=' . urlencode($doi);
$service_url = 'http://search.crossref.org/?q=' . urlencode($doi);
try{
    $html = str_get_html( file_get_contents( $service_url ) );
    echo $html->find('body',0)->plaintext;
} catch(Exception $e){
    echo "So this is it...";
}

?>