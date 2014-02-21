<?php
include("simple_html_dom.php");
define("DOI_API_URL", "http://search.crossref.org/dois?q=");
define("DOI_SEARCH_URL", "http://search.crossref.org/?q=");
define("ISBN_API_URL", "https://www.googleapis.com/books/v1/volumes?q=isbn:");

/***************/
if(isset($_REQUEST["query"]) && isset($_REQUEST["format"]) && $_REQUEST["format"] === "json"){
    header('Content-type: application/json');
    $result = getBibtex($_REQUEST["query"]);
    echo json_encode($result);
}
/***************/

function getBibtex($query){
    $valid = false;
    if(filter_var($query, FILTER_VALIDATE_URL)){
        return getBibtexFromURL($query);
    } else {
        if( testDOI( $query ) ){
            return getBibtexFromDOI($query);
        } else {
            $isbn_url    = ISBN_API_URL . urlencode(cleanISBN($query));
            $isbn_result = json_decode(file_get_contents($isbn_url));
            if( $isbn_result->totalItems > 0 ){
                return getBibtexFromISBN($query);
            }
        }
    }
    return returnStructure(false, false, $query, false, false);
}

function getBibtexFromURL($url){
    return generateBibtex(getDataFromURL($url));;
}

function getBibtexFromDOI($doi){
    return generateBibtex(getDataFromDOI($doi));
}

function getBibtexFromISBN($isbn){
    return generateBibtex(getDataFromISBN($isbn));
}

function getDataFromDOI($doi){
    $url = DOI_SEARCH_URL . urlencode($doi);
    $html = getHTML( $url );
    $result = array();
    $listing = $html->find('.container-fluid .span9 table tbody tr td.item-data', 0);
    $result["title"]   = trim($listing->find('p.lead', 0)->plaintext);
    if($listing->find('p.expand',0) !== null)
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
    return returnStructure("doi", array($result), $doi, $url);
}

function getDataFromISBN($isbn){
    $url = ISBN_API_URL . cleanISBN($isbn);
    $results["isbn"] = json_decode(file_get_contents($url));
    $result = gbooksToArray($results["isbn"]->items[0]);
    return returnStructure("isbn", array($result), $isbn, $url);
}

function getDataFromURL($url){
    if(strpos($url, "arxiv.org") !== false) {
        if(strpos($url, "arxiv.org/pdf/")){
            $url = str_replace("http://arxiv.org/pdf/","http://arxiv.org/abs/",$url);
        }
        $html = getHTML($url);
        return returnStructure("arxiv", array(arxivToData($html)), $url, $url);
    }
    $html = getHTML( $url );
    $result = array("actual" => array(), "alt" => array());
    if($html->find("meta[property=og:site_name]",0) !== null && $html->find("meta[property=og:site_name]",0)->content == "YouTube"){
        $result["actual"] = array_merge($result["actual"], youtubeToData($html));
    } else {
        foreach(array("description", "author", "keywords") as $tag){
            if($html->find("meta[name=".$tag."]",0) != null){
                $result["actual"][$tag] = trim($html->find("meta[name=".$tag."]",0)->content);
            }
        }
        $result["actual"]["title"] = trim($html->find("title",0)->plaintext);
    }
    
    $result["alt"] = $result["actual"];
    
    $result["actual"]["type"] = "ONLINE";
    $result["actual"]["url"] = $url;
    $date = new DateTime();
    $result["actual"]["urldate"] = $date->format("Y-m-d");
    
    $result["alt"]["type"] = "misc";
    $result["alt"]["howpublished"] = "\url{" . $url . "}";
    $date = new DateTime();
    $result["alt"]["note"] = "Accessed: " . $date->format("Y-m-d h:i:s");
    
    return returnStructure("url", $result, $url, $url);
}

function getHTML($url){
    $html = @file_get_html( $url );
    if(!$html){
        $page = get_web_page($url);
        $html = str_get_html($page['content']);
    }
    return $html;
}

function testDOI( $doi ){
    $doi_url    = DOI_API_URL . urlencode($doi);
    $doi_result = json_decode(file_get_contents($doi_url));
    return (count($doi_result) !== 0 );
}

function cleanISBN( $isbn ){ return preg_replace("/[^0-9a-zA-Z]*/","",$isbn); }

function returnStructure($type, $data, $query, $url = null, $success = true){ return array("type" => $type, "data" => $data, "url" => $url,"query" => $query, "success" => $success); }

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

function arxivToData( $html ){
    $result = array();
    if($html->find("td.doi",0) !== null){
        $doi = $html->find("td.doi a",0)->plaintext;
        $doi_data = getDataFromDOI($doi);
        $result = $doi_data["data"][0];
    }
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
        throw new Exception("Oops, not a success!");
        exit();
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