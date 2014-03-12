<?php
/**
function returnStructure($type, $data, $query, $url = null, $success = true, $message = null){ 
    return array(
        "type" => $type, 
        "data" => $data, 
        "url" => $url,
        "query" => $query, 
        "success" => $success, 
        "message"=>$message
    ); 
}
**/
include("simple_html_dom.php");
define("DOI_API_URL", "http://search.crossref.org/dois?q=");
define("DOI_SEARCH_URL", "http://search.crossref.org/?q="); //Because the API sucks and doesn't have any info in it.
define("ISBN_API_URL", "https://www.googleapis.com/books/v1/volumes?q=isbn:");

/******** Handle JSON request *******/
if(isset($_REQUEST["query"]) && isset($_REQUEST["format"]) && $_REQUEST["format"] === "json"){
    header('Content-type: application/json');
    try{
        $result = getBibtex($_REQUEST["query"]);
        if($result["success"] === true){
            sendResponse( 200, $result );
        } else {
            sendResponse( 400, $result );
        }
    } catch(Exception $e) {
        sendResponse( 400, array("query" => $_REQUEST["query"], "message"=> $e->getMessage()));
    }
}
/***************/

// Sorts through the query to see if it matches any of these patters
// (DOI, ISBN, URL)
function getBibtex($query){
    $valid = false;
    if( filter_var( $query, FILTER_VALIDATE_URL ) ) {
        return getBibtexFromURL($query);
    } else if( testISBN( $query ) ) {
        return getBibtexFromISBN($query);
    } else if( testDOI( $query ) ) {
        return getBibtexFromDOI($query);
    }
    return returnStructure(false, null, $query, false, false, "Query identified as not a valid DOI, ISBN or URL.");
}

// These three functions just call the appropriate function...
// bit pointless but could be useful later.
function getBibtexFromURL($url){
    return generateBibtex(getDataFromURL($url));;
}

function getBibtexFromDOI($doi){
    return generateBibtex(getDataFromDOI($doi));
}

function getBibtexFromISBN($isbn){
    return generateBibtex(getDataFromISBN($isbn));
}

// Simply scraps the crossref search site for info and returns it.
function getDataFromDOI($doi){
    // defined above
    $url = DOI_SEARCH_URL . urlencode($doi);
    //fetch HTML
    $html = getHTML( $url );
    $result = array();
    $listing = $html->find('.container-fluid .span9 table tbody tr td.item-data', 0);
    $result["title"]   = trim($listing->find('p.lead', 0)->plaintext);
    if($listing->find('p.expand',0) !== null){
        $result["authors"] = trim(str_replace(", ", " and ", preg_replace("/Author[s]?[:]?/i", "", $listing->find('p.expand',0)->plaintext)));
    }
    $result["type"]    = getBibtexType(trim($listing->find('p.extra span', 0)->find('b',0)->plaintext));
    $result["journal"] = trim($listing->find('p.extra span', 1)->find('b',0)->plaintext);
    $result["volume"]  = trim($listing->find('p.extra span', 2)->find('b',0)->plaintext);
    $result["issue"]   = trim($listing->find('p.extra span', 3)->find('b',0)->plaintext);
    if($listing->find('p.extra span', 4) !== null)
        $result["pages"]   = trim($listing->find('p.extra span', 4)->find('b',0)->plaintext) . " to " . trim($listing->find('p.extra span', 4)->find('b',1)->plaintext);
    $result["link"]    = trim($listing->find('div.item-links-outer div.item-links a',0)->href);
    
    // Because of the peculiar/poor formatting of the crossref site, we have to do some tricks to scrape the date.
    // First, load it into the DateTime object
    $date = new DateTime($listing->find('p.extra span', 0)->find('b',1)->plaintext);
    //Now they all have a year, so this part is easy.
    $result["year"]    = $date->format("Y");
    //Now we need to see if a month is listed by removing all numbers from the string and seeing if there's anything left.
    if(trim(removeNumbersFromString($listing->find('p.extra span', 0)->find('b',1)->plaintext)) !== ""){
        $result["month"]   = $date->format("m");
        // Only check for a day if the month was listed.
        // In this case, a day is listed if the first character of the date part is numeric
        $found = trim($listing->find('p.extra span', 0)->find('b',1)->plaintext);
        if(is_numeric(substr($found,0,1))){
            $result["day"]   = $date->format("d");
        }
    }
    $result["DOI"]     = $doi;
    return returnStructure("doi", array($result), $doi, $url);
}

function getDataFromISBN($isbn){
    // defined above.
    $url = ISBN_API_URL . urlencode(cleanISBN($isbn));
    $results["isbn"] = json_decode(file_get_contents($url));
    $book = $results["isbn"]->items[0];
    // selfLinks contain more/better info.
    if(isset($book->selfLink)) {
        $book = json_decode(file_get_contents($results["isbn"]->items[0]->selfLink));
    }
    $result = gbooksToArray($book);
    return returnStructure("isbn", array($result["result"]), $isbn, $url);
}

function getDataFromURL($url){
    // Detect some special cases
    // Arxiv links
    $message = null;
    $result = array();
    if(strpos($url, "arxiv.org") !== false) {
        $url = str_replace("arxiv.org/pdf/","arxiv.org/abs/",$url);
        if(strpos($url, "arxiv.org/abs/") !== false){
            $html = getHTML($url);
            return returnStructure("arxiv", array(arxivToData($html)), $url, $url);
        }
    } else if(strpos($url, ".amazon.") !== false) {
        // Amazon links
        $html = getHTML($url);
        if($html->find("#nav-subnav",0) !== null){
            // Checks if it's a book
            if(strtolower(trim($html->find("#nav-subnav li",0)->find('a',0)->plaintext)) === "books"){
                $buyingbox = $html->find("#ps-content .buying", 0);
                if($buyingbox !== null){
                    foreach($buyingbox->find("span") as $key => $val){
                        if(strpos($val->plaintext, "ISBN-10") !== false) break;
                    }
                    $isbn = trim($buyingbox->find("span", $key+1)->plaintext);
                    // If it is a book, use the ISBN number instead.
                    if(testISBN($isbn)) return getDataFromISBN($isbn);
                    else {
                        $message = "An ISBN was detected but could not retrieve book information from google books API. Attempted to scrap book data from Amazon.";
                        return getBookDataAmazon($html, $isbn, $url, $message);
                    }
                }
            }
        }
    }
    if(!isset($html)){
        $html = getHTML( $url );
    }
    // Youtube case
    if($html->find("meta[property=og:site_name]",0) !== null && $html->find("meta[property=og:site_name]",0)->content == "YouTube"){
        $result[0] = array_merge($result[0], youtubeToData($html));
    } else {
        // Otherwise, just scrape generic meta data.
        foreach(array("description", "author", "keywords") as $tag){
            if($html->find("meta[name=".$tag."]",0) != null){
                $result[0][$tag] = trim($html->find("meta[name=".$tag."]",0)->content);
            }
        }
        if($html->find("title",0) !== null) {
            $result[0]["title"] = trim($html->find("title",0)->plaintext);
        }
    }
/**
function returnStructure($type, $data, $query, $url = null, $success = true, $message = null){ 
    return array(
        "type" => $type, 
        "data" => $data, 
        "url" => $url,
        "query" => $query, 
        "success" => $success, 
        "message"=>$message
    ); 
}
**/
    if(!isset($result[0]) || empty($result[0])){
        if(substr($url,-4) === ".pdf"){
            return returnStructure("url", null, $url, $url, false, "Cannot currently parse PDF files.");
        } else {
            return returnStructure("url", null, $url, $url, false, "Nothing found on page");
        }
    }
    // we need to return two arrays, one for old bibtex and one for biblatex ([1] and [0] respectively)
    $result[1] = $result[0];
    
    $result[0]["type"] = "ONLINE";
    $result[0]["url"] = $url;
    $date = new DateTime();
    $result[0]["urldate"] = $date->format("Y-m-d");
    
    $result[1]["type"] = "misc";
    $result[1]["howpublished"] = "\url{" . $url . "}";
    $date = new DateTime();
    $result[1]["note"] = "Accessed: " . $date->format("Y-m-d h:i:s");
    
    return returnStructure("url", $result, $url, $url, true, $message);
}

function getBookDataAmazon($html, $isbn, $url, $message = null){
    $result = array();
    $result["type"] = "book";
    foreach(array("description", "author", "keywords", "title") as $tag){
        if($html->find("meta[name=".$tag."]",0) != null){
            $result[$tag] = trim($html->find("meta[name=".$tag."]",0)->content);
        }
    }
    $tag = "#handleBuy div.buying";
    $found = $html->find($tag,2);
    if($found != null){
        $authors = Array();
        $span = $found->find("span",3);
        if($span !== null && strpos($span->plaintext,"(Author)") !== false){
            $result["authors"] = trim(str_replace(" (Author)",", ",$span->plaintext));
        }
        $result["authors"] = substr($result["authors"],0,-1);
    }
    // $tag = "";
    // if($html->find($tag,0) != null){
        // $result[""] = trim($html->find($tag,0)->plaintext)
    // }
    
    return returnStructure("isbn", array($result), $isbn, $url, true, $message);
}


//Just fetches the HTML in simple_html_dom format
function getHTML($url){
    $html = @file_get_html( $url );
    if(!$html){
        $page = get_web_page($url);
        $html = str_get_html($page['content']);
    }
    return $html;
}

// Tests if $doi is a DOI or not
function testDOI( $doi ){
    $doi_url    = DOI_API_URL . urlencode($doi);
    $doi_result = json_decode(file_get_contents($doi_url));
    return (count($doi_result) === 1 );
}

function testISBN( $isbn ){
    if((validateISBN(cleanISBN($isbn)))){
        $isbn_url    = ISBN_API_URL . urlencode(cleanISBN($isbn));
        $isbn_result = json_decode(file_get_contents($isbn_url));
        return ($isbn_result->totalItems > 0 );
    }
    return false;
}

// Cleans ISBNs of uneeded characters (slashes, hyphens etc))
function cleanISBN($isbn){
    $isbn = preg_replace("/ISBN(-10|-13)?(:)?( )?/i","", trim($isbn));
    return preg_replace("/[^0-9X]/","",$isbn);
}

function validateISBN( $isbn ) {
    $isbn = trim($isbn);
    $last = substr($isbn, -1);
    $stripped = preg_replace( '/[^0-9]/', '', $isbn );
    $length = strlen($stripped);
    $sum = 0;

    if( $length == 9 and ( $last == 'x' or $last == 'X' )  ) {
	$stripped = $stripped . 'X' ;
	$length = 10;
    }

    if( $length == 10 ) {
        // ISBN-10

	for( $i = 0 ; $i < 10 ; $i++ ) {
	    $value = substr( $stripped, $i, 1 );
            $value = ( $value == 'X' ) ? 10 : (int) $value ;
	    $sum += ( 10 - $i ) * $value ;
        }

        $remainder = $sum % 11;

        return empty( $remainder ) ;

    } elseif( $length == 13 ) {
        // ISBN-13

        for( $i = 0 ; $i < 12 ; $i++ ) {
            $j = $i % 2;
            $digit = (int) substr( $stripped, $i, 1 );

            if( empty($j) ) {
                $sum += $digit;
            } else {
                $sum += 3 * $digit;
            }
        }

        $remainder = $sum % 10 ;

        $checkdigit = empty($remainder) ? 0 : 10 - $remainder ;

        $lastdigit = substr( $stripped, -1, 1 );

        return ( $lastdigit == $checkdigit ) ;

    } else {
        // Invalid
        return false;
    }
}

// Defines a common return structure for our data
function returnStructure($type, $data, $query, $url = null, $success = true, $message = null){ 
    return array(
        "type" => $type, 
        "data" => $data, 
        "url" => $url,
        "query" => $query, 
        "success" => $success, 
        "message"=>$message
    ); 
}

//converts a gbooks result into a useable array
function gbooksToArray( $gb ){
    $vi = $gb->volumeInfo;
    $result = array();
    $result["title"]        = trim($vi->title);
    $result["authors"]      = trim(implode(" and ", $vi->authors));
    $result["type"]         = "book";
    // $result["link"]         = trim($gb->selfLink);
    if(isset($result["categories"]))
        $result["categories"]   = trim(implode(", ", $vi->categories));
    // $result["description"]  = trim($vi->description);
    if(isset($result["publisher"]))
        $result["publisher"]    = trim($vi->publisher);
    $date = new DateTime($vi->publishedDate);
    $result["year"]         = trim($date->format("Y"));
    $result["month"]        = trim($date->format("m"));
    $result["day"]          = trim($date->format("d"));
    $result["pagecount"]    = trim($vi->pageCount);
    foreach($vi->industryIdentifiers as $identifier){
        if($identifier->type === "ISBN_10" || $identifier->type === "ISBN_13") $result["isbn"] = $identifier->identifier;
    }
    return array("result"=>$result);
}

// Scrapes arxiv page for data
function arxivToData( $html ){
    $result = array();
    // If a DOI is present, also scrape that data
    if($html->find("td.doi",0) !== null){
        $doi = $html->find("td.doi a",0)->plaintext;
        $doi_data = getDataFromDOI($doi);
        $result = $doi_data["data"][0];
    }
    // ... but replace it with arxiv data where possible.
    foreach(array("keywords", "description", "title") as $tag){
        if($html->find("meta[name=citation_".$tag."]",0) != null){
            $result[$tag] = trim($html->find("meta[name=citation_".$tag."]",0)->content);
        }
    }
    if($html->find("meta[name=citation_date]",0) != null){
        $date = new DateTime(trim($html->find("meta[name=citation_date]",0)->content));
        $result["year"]  = $date->format("Y");
        $result["month"] = $date->format("m");
        $result["day"]   = $date->format("d");
    }
    if($html->find("meta[name=citation_arxiv_id]",0) != null){
        $result["eprint"] = $result["arxivid"] = trim($html->find("meta[name=citation_arxiv_id]",0)->content);
    }
    $authors = array();
    foreach($html->find("meta[name=citation_author]") as $author){
        $authors[] = $author->content;
    }
    if(count($authors) > 0){
        $result["authors"] = implode(" and ", $authors);
    }
    return $result;
}

function youtubeToData($html){
    $result = array();
    foreach(array("keywords", "description", "title") as $tag){
        if($html->find("meta[name=".$tag."]",0) != null){
            $result[$tag] = trim($html->find("meta[name=".$tag."]",0)->content);
        }
    }
    $result["author"] = trim($html->find("meta[name=attribution]",0)->content);
    $result["howpublished"] = trim($html->find("meta[property=og:type]",0)->content);
    if($html->find("div#watch7-user-header a.yt-user-name",0) !== null){
        $result["author"] = trim($html->find("div#watch7-user-header a.yt-user-name",0)->plaintext);
    }
    if($html->find("span#eow-date",0) !== null){
        $date = new DateTime($html->find("span#eow-date",0)->plaintext);
        $result["year"] = $date->format("Y");
        $result["month"] = $date->format("m");
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

function getNumbersFromString($str){
    return preg_replace("/[^0-9]/","",$str);
}

function removeNumbersFromString($str){
    return preg_replace("/[0-9]/","",$str);
}

function generateBibtex($data){
    if($data["success"] === false){
        return $data;
    }
    $type   = $data["type"];
    $inputs = $data["data"];
    $query  = $data["query"];
    
    $results = array();
    
    foreach($inputs as $inputkey => $input){
        $default = array("type"=> "article");
        update($default, $input);
        
        if($type === "url" || $type === "arxiv"){
            if(isset($input["doi"])){
                $cite = $input["doi"];
            } else if(isset($input["DOI"])){
                $cite = $input["DOI"];
            } else if(isset($input["title"])) {
                $cite = $input["title"];
            } else {
                $cite = $query;
            }
        } else {
            $cite = $query;
        }
        
        $cite = trimToCite($cite);
        
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
        $results[$inputkey]["text"] = $result;
        $results[$inputkey]["cite"] = $cite;
    }
    $data["bibtex"] = $results;
    return $data;
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