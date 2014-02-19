<?php
include("simple_html_dom.php");
$validDOI  = false;
$validISBN = false;
$validURL  = false;
$valid = false;
$results = array();

if(isset($_REQUEST["query"])){
    $doi = $_REQUEST["query"];
    $responseFormat = 'json';
    $service_url = 'http://search.crossref.org/dois?q=' . urlencode($doi);
    $isbn_url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode(preg_replace("/[^0-9a-zA-Z]*/","",$doi));
    
    if(filter_var($doi, FILTER_VALIDATE_URL)){
        $validURL = true;
        $results["url"] = scrapeURL($doi);
    } else {
        //next example will recieve all messages for specific conversation
        $results["doi"] = json_decode(file_get_contents($service_url));
        $validDOI = (count($results["doi"]) !== 0 && isset($_REQUEST["query"]));
        
        $results["isbn"] = json_decode(file_get_contents($isbn_url));
        $validISBN = $results["isbn"]->totalItems > 0;
    }
    
    $valid = $validDOI || $validISBN || $validURL;
    
    // echo"<pre>";
    // var_dump(array("DOI"=>$validDOI,"ISBN"=>$validISBN,"overall"=>$valid));
    // var_dump($results);
    // exit();
}

if($validDOI === true) {

    $APIresult = objectToArray($results["doi"]);
    
    /*****************************\
    * Scraping Result
    \*****************************/

    $service_url = 'http://search.crossref.org/?q=' . urlencode($doi);
    $result = scrapeDOI($service_url);
    updateLeft($result, $APIresult);
    
} else if ($validISBN === true) {
    $result = gbooksToArray($results["isbn"]->items[0]);
} else if ($validURL === true) {
    $result = $results["url"]["actual"];
} else {
}

?>

<html><head>
<title>DOI/ISBN/URL to BibTeX Converter<?if(isset($_REQUEST["query"])){ echo " - " . $_REQUEST["query"];};?></title>
<script type="text/javascript" src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
<script type="text/javascript" src="jquery.zclip.min.js"></script>
<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<style>
body{
    font-size: 0.9em;
    max-width: 100%;
}

pre {
    display: block;
    overflow: scroll;
    text-wrap: normal;
    max-width: 100%;
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

.query-input{
    min-width: 300px;
}

.btn:hover {
    box-shadow: 1px 1px 1px 1px rgba(0,0,0,0.03) inset;
}
</style>
</head><body>
<h1>Input DOI/ISBN/URL</h1>
<form action="" method="GET">
    <input type="text" name="query" id="query" class="query-input" <? if(!isset($_REQUEST["query"])):?>autofocus <?endif;?>placeholder="DOI/ISBN/URL, e.g 10.1086/377226" />
    <button type="submit" class="btn">Submit</button>
</form>
<h1>Scraping Result</h1>
<?if(isset($_REQUEST["query"])):?>
    <?if($validDOI):?>
    <p>From: <a href="<?= $service_url;?>" target="_blank"><?= $service_url;?></a></p>
    <?elseif($validISBN):?>
    <p>From: <a href="<?= $isbn_url;?>" target="_blank"><?= $isbn_url;?></a></p>
    <?elseif($validURL):?>
    <p>From: <a href="<?= $doi;?>" target="_blank"><?= $doi;?></a></p>
    <?endif;?>
<?endif;?>
<pre>
<?php 
if($valid === true){
    print_r($result);
} else {
    if(isset($_REQUEST["query"]))
        echo "Invalid DOI/ISBN/URL: '$doi', please try again";
    else
        echo "Please enter a DOI/ISBN/URL";
}?>
</pre>  
<h1><i class="bibtex"></i></h1>
<a href="#" id="copy-to-clipboard" class="btn">Copy <i class="bibtex"></i></a><a href="#" id="copy-cite-text" data-cite="\cite{<?= trimToCite(($validURL && isset($result["title"])) ? $result["title"] : $doi);?>}" class="btn">Copy <i class="latex"></i> \cite</a>
<textarea id="result-box" rows=<?php echo (isset($result)) ? count(explode("\n",generateBibtex($result)))+1 : 10;?> onclick="if(selected === 2) this.select(); selected = (selected + 1) % 3;" style="width: 100%">
<?php 
if($valid === true){
    echo generateBibtex($result);
} else {
    if(isset($_REQUEST["query"]))
        echo "Invalid DOI/ISBN/URL: '$doi', please try again";
    else
        echo "Please enter a DOI/ISBN/URL";
}?>
</textarea>
<?if($validURL):?>
<p>Note: the above bibtex code assumes you are using Biblatex. If you are note using biblatex, please use the code below.</p>
<textarea rows=<?php echo count(explode("\n",generateBibtex($results["url"]["alt"])))+1;?> onclick="this.select();" style="width: 100%">
<?echo generateBibtex($results["url"]["alt"]);?>
</textarea>
<?endif;?>
<?if(isset($_REQUEST["query"])):?>
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
<?endif;?>
</body></html>
<?php
function gbooksToArray( $gb ){
    $vi = $gb->volumeInfo;
    $result = array();
    $result["title"]        = trim($vi->title);
    $result["authors"]      = trim(implode(" and ", $vi->authors));
    $result["type"]         = "book";
    $result["link"]         = trim($gb->selfLink);
    $result["categories"]   = trim(implode(", ", $vi->categories));
    $result["description"]  = trim($vi->description);
    $result["publisher"]    = trim($vi->publisher);
    $date = new DateTime($vi->publishedDate);
    $result["year"]         = trim($date->format("Y"));
    $result["month"]        = trim($date->format("m"));
    $result["day"]          = trim($date->format("d"));
    foreach($vi->industryIdentifiers as $identifier){
        $result[str_replace("_","",strtolower($identifier->type))] = $identifier->identifier;
    }
    return $result;
}

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

function scrapeURL($url){
    global $responseFormat;
    global $doi;
    $html = @file_get_html( $url );
    if(!$html){
        $page = get_web_page($url);
        $html = str_get_html($page['content']);
    }
    if( true === false ){ // if it didn't work...
        $notice = $html->find('#main-content .notice', 0);
        if($notice !== null){
            sendResponse( 400, array('message' => $notice->plaintext, 'plain' => $notice->plaintext), $responseFormat );
        } else {
            sendResponse( 400, array('message' => "Unknown error - possible url failure. Is you URL a page that returns results?"), $responseFormat );
        }
        exit();
    }
    $result = array("actual", "alt");
    $result["actual"]["title"] = trim($html->find("title",0)->plaintext);
    $date = new DateTime();
    $result["actual"]["note"] = "Accessed: " . $date->format("Y-m-d h:i:s");
    foreach(array("description", "author", "keywords") as $tag){
        if($html->find("meta[name=".$tag."]",0) != null){
            $result["actual"][$tag] = trim($html->find("meta[name=".$tag."]",0)->content);
        }
    }
    $result["alt"] = $result["actual"];
    $result["actual"]["type"] = "ONLINE";
    $result["actual"]["url"] = $doi;
    $result["actual"]["urldate"] = $date->format("Y-m-d");
    $result["alt"]["type"] = "misc";
    $result["alt"]["howpublished"] = "\url{" . $doi . "}";
    return $result;
}

function scrapeDOI($url) {
    global $responseFormat;
    global $doi;
    $html = @file_get_html( $url );
    if(!$html){
        $page = get_web_page($url);
        $html = str_get_html($page['content']);
    }
    if( true === false ){ // if it didn't work...
        $notice = $html->find('#main-content .notice', 0);
        if($notice !== null){
            sendResponse( 400, array('message' => $notice->plaintext, 'plain' => $notice->plaintext), $responseFormat );
        } else {
            sendResponse( 400, array('message' => "Unknown error - possible url failure. Is you URL a page that returns results?"), $responseFormat );
        }
        exit();
    }
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
    global $validURL;
    $default = array("type"=> "article");
    
    update($default, $input);
    
    $cite = trimToCite(($validURL && isset($input["title"])) ? $input["title"] : $doi);
    
    $result = "@" . $default["type"] . "{" . $cite;
    unset($default["type"]);
    foreach($default as $key => $value){
        if($value !== "" && $value !== null && isset($value) && !empty($value)){
            if($value[0] == '\\' || $value[0] == '{'){
                $result .= ",\n    " . $key . " = " . $value;
            } else {
                $result .= ",\n    " . $key . " = {" . $value . "}";
            }
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


function get_web_page( $url )
{
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );

    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}