<?php
    include("getInfo.php");
    
    if(isset($_REQUEST["query"])){
        $results = getBibtex($_REQUEST["query"]);
        // echo"<pre>";
        // var_dump($results);
        // exit();
    } else {
        $results = false;
    }
    
    ?>
<html>
    <head>
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
    </head>
    <body>
        <h1>Input DOI/ISBN/URL</h1>
        <form action="" method="GET">
            <input type="text" name="query" id="query" class="query-input" <? if(!isset($_REQUEST["query"])):?>autofocus <?endif;?>placeholder="DOI/ISBN/URL, e.g 10.1086/377226" />
            <button type="submit" class="btn">Submit</button>
        </form>
        <h1>Scraping Result</h1>
        <?if($results !== false):?>
        <p>From: <a href="<?= $results["url"];?>" target="_blank"><?= $results["url"];?></a></p>
        <?endif;?>
        <? 
        if(isset($_REQUEST["dev"])){
            echo "<pre>";
            if($results !== false){
                print_r($results);
            } else {
                if(isset($_REQUEST["query"]))
                    echo "Invalid DOI/ISBN/URL: " . $_REQUEST["query"] . ", please try again";
                else
                    echo "Please enter a DOI/ISBN/URL";
            }
            echo "</pre>";
        }
        if($results["type"] == "url") {
            $results["bibtex"][0] = $results["bibtex"]["actual"];
        }
        ?>
        <h1><i class="bibtex"></i></h1>
        <a href="#" id="copy-to-clipboard" class="btn">Copy <i class="bibtex"></i></a><a href="#" id="copy-cite-text" data-cite="\cite{<? if($results !== false) echo $results["bibtex"][0]["cite"];?>}" class="btn">Copy <i class="latex"></i> \cite</a>
        <textarea id="result-box" rows=<?php echo ($results !== false) ? count(explode("\n",$results["bibtex"][0]["text"]))+1 : 10;?> onclick="if(selected === 2) this.select(); selected = (selected + 1) % 3;" style="width: 100%">
<?php 
            if($results !== false){
                echo $results["bibtex"][0]["text"];
            } else {
                if(isset($_REQUEST["query"]))
                    echo "Invalid DOI/ISBN/URL: '$doi', please try again";
                else
                    echo "Please enter a DOI/ISBN/URL";
            }?>
</textarea>
        <?if($results["type"] == "url"):?>
        <p>Note: the above bibtex code assumes you are using Biblatex. If you are note using biblatex, please use the code below.</p>
        <textarea rows=<?php echo count(explode("\n",$results["bibtex"]["alt"]["text"]))+1;?> onclick="this.select();" style="width: 100%">
<?echo $results["bibtex"]["alt"]["text"];?>
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
    </body>
</html>