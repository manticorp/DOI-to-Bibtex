<?php
include("simple_html_dom.php");

$doi = (isset($_REQUEST["doi"]))? $_REQUEST["doi"] : "10.1086/377226";

//next example will recieve all messages for specific conversation
$service_url = 'http://search.crossref.org/dois?q=' . urlencode($doi);
$decoded = json_decode(file_get_contents($service_url));

$validDOI = (count($decoded) == 0) ? false : true;

if($validDOI) {
    $APIresult = objectToArray($decoded[0]);

    /*****************************\
    * Scraping Result
    \*****************************/

    $service_url = 'http://search.crossref.org/?q=' . urlencode($doi);
    $result = scrape($service_url);
    updateLeft($result, $APIresult);
}

?>

<html><head>

<script type="text/javascript" src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
<script type="text/javascript" src="jquery.zclip.min.js"></script>
<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<style>
body{
    font-size: 0.9em;
}

h1,h2,h3,h4,h5,h6, p, input, .btn {
    font-family: 'Droid Sans', sans-serif;
}
p {

}
.btn {
    cursor: pointer;
    display: inline-block;
    padding: 4px 6px;
    border: 1px solid #ddd; 
    border-radius: 4px; 
    background: #eee;
    text-decoration: none;
    color: #333;
    font-size: 0.8em;
    margin: 4px;
}

.bibtex {
    height: 1.2em;
    margin-top: 4px;
    margin-bottom: -4px;
    display: inline-block;
    background-image: url('img/BibTeX_logo.svg');
    background-position: top left;
    background-repeat: no-repeat;
    background-size: contain;
    width: 3.5em;
}

.latex {
    height: 1.2em;
    margin-top: 3px;
    margin-bottom: -3px;
    display: inline-block;
    background-image: url('img/LaTeX_logo.svg');
    background-position: top left;
    background-repeat: no-repeat;
    background-size: contain;
    width: 3em;
}

input {
    display: inline-block;
    padding: 4px 6px;
    border: 1px solid #ddd; 
    border-radius: 4px; 
    background: #eee;
    color: #333;
    margin: 4px;
}

.btn:hover {
    box-shadow: 1px 1px 1px 1px rgba(0,0,0,0.03) inset;
}
</style>
</head><body>
<h1>Input DOI</h1>
<form action="" method="GET">
    <input type="text" name="doi" id="doi" class="doi-input" placeholder="DOI, e.g 10.1086/377226" />
    <button type="submit" class="btn">Submit</button>
</form>
<h1>Scraping Result</h1>
<p><a href="<?= $service_url;?>" target="_blank">From: <?= $service_url;?></a></p>
<pre>
<?php 
if($validDOI){
    print_r($result);
} else {
    echo "Invladid DOI: '$doi', please try again";
}?>
</pre>  
<h1><i class="bibtex"></i></h1>
<a href="#" id="copy-to-clipboard" class="btn">Copy <i class="bibtex"></i></a><a href="#" id="copy-cite-text" data-cite="\cite{<?= trimToCite($doi);?>}" class="btn">Copy <i class="latex"></i> \cite</a>
<textarea id="result-box" rows=20 onclick="if(selected === 2) this.select(); selected = (selected + 1) % 3;" style="width: 100%">
<?php 
if($validDOI){
    echo generateBibtex($result);
} else {
    echo "Invladid DOI: '$doi', please try again";
}?>
</textarea>
<script type="text/javascript">var selected = 0; document.getElementById('result-box').select();</script>
<script type="text/javascript">
    $(document).ready(function(){
        $('a#copy-to-clipboard').zclip({
            path:'ZeroClipboard.swf',
            copy:function(){return $('textarea#result-box').val();}
        });
        $('a#copy-cite-text').zclip({
            path:'ZeroClipboard.swf',
            copy:function(){return $('#copy-cite-text').data( "cite" );}
        });
    });
</script>
</body></html>
<?php
function textToNumber( $text ){
    return intval(preg_replace('/[\)\(, ]/', '', $text));
}

function objectToArray($obj){
    $array = array();
    foreach($obj as $key => $value){
        $array[$key] = $value;
    }
    return $array;
}

function updateLeft(&$array1, $array2){
    foreach($array1 as $key => $value){
        if(isset($array2[$key])) $array1[$key] = $array2[$key];
    }
}

function update(&$array1, $array2){
    foreach($array2 as $key => $value){
        $array1[$key] = $value;
    }
}

function trimToCite($txt){
    return preg_replace('/[^0-9a-zA-Z\/\)(]/',"",str_replace(' ','',substr($txt,0,40)));
}

function scrape($url) {
    global $doi;
    $html = @str_get_html(file_get_contents($url));
    $result = array();
    $listing = $html->find('.container-fluid .span9 table tbody tr td.item-data', 0);
    $result["title"]   = trim($listing->find('p.lead', 0)->plaintext);
    $result["authors"] = trim(str_replace(", ", " and ", preg_replace("/Author[s]?[:]?/i", "", $listing->find('p.expand',0)->plaintext)));
    $result["type"]    = getBibtexType(trim($listing->find('p.extra span', 0)->find('b',0)->plaintext));
    $result["journal"] = trim($listing->find('p.extra span', 1)->find('b',0)->plaintext);
    $result["volume"]  = trim($listing->find('p.extra span', 2)->find('b',0)->plaintext);
    $result["issue"]   = trim($listing->find('p.extra span', 3)->find('b',0)->plaintext);
    $result["pages"]   = trim($listing->find('p.extra span', 4)->find('b',0)->plaintext) . " to " . trim($listing->find('p.extra span', 4)->find('b',1)->plaintext);
    $result["link"]    = trim($listing->find('div.item-links-outer div.item-links a',0)->href);
    $result["year"]    = trim(getNumbersFromString($listing->find('p.extra span', 0)->find('b',1)->plaintext));
    $result["month"]   = trim(removeNumbersFromString($listing->find('p.extra span', 0)->find('b',1)->plaintext));
    $result["DOI"]     = $doi;
    return $result;
}

function getNumbersFromString($str){
    return preg_replace("/[^0-9]/","",$str);
}

function removeNumbersFromString($str){
    return preg_replace("/[0-9]/","",$str);
}

function generateBibtex($input){
    global $doi;
    $default = array("type"=> "article");
    
    update($default, $input);
    
    $result = "@" . $default["type"] . "{" . trimToCite($doi);
    unset($default["type"]);
    foreach($default as $key => $value){
        if($value !== ""){
            $result .= ",\n    " . $key . " = {" . $value . "}";
        }
    }
    $result .= "\n}";
    return $result;
}

function getBibtexType($type){
    switch($type){
        case "Journal Article":
            return "article";
            break;
        default:
            return $type;
    }
}

function sendResponse( $code = 400, $data = array('message' => 'Invalid input'), $responseFormat = "json" ) {
    http_response_code( $code );
    switch( strtolower( $responseFormat ) ){
        // other response formats here if needed.
        case 'json':
        case 'application/json':
        default:
            header('Content-type: application/json');
            echo json_encode($data);
    }
}

function sentenceCase($str) {
   $cap = true;
   $ret='';
   for($x = 0; $x < strlen($str); $x++){
       $letter = substr($str, $x, 1);
       if($letter == "." || $letter == "!" || $letter == "?"){
           $cap = true;
       }elseif($letter != " " && $cap == true){
           $letter = strtoupper($letter);
           $cap = false;
       } 
       $ret .= $letter;
   }
   return $ret;
}