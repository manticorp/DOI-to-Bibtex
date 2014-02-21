<html>
    <head>
        <title>DOI/ISBN/URL to BibTeX Converter<?if(isset($_REQUEST["query"])){ echo " - " . $_REQUEST["query"];};?></title>
        <script type="text/javascript" src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
        <script type="text/javascript" src="js/jquery.zclip.min.js"></script>
        <script type="text/javascript" src="js/jquery.ba-bbq.js"></script>
        <script type="text/javascript" src="js/main.js"></script>
        <link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
        <link href='css/style.css' rel='stylesheet' type='text/css'>
    </head>
    <body>
        <h1>DOI/ISBN/URL to Bibtex converter</h1>
        <p>Input any DOI, URL or ISBN number into the box below and our server gremlins will try to find what you're referring to and generate a bibtex entry for that content</p>
        <h2>Input DOI/ISBN/URL</h2>
        <form method="POST" action="" id="queryform" onsubmit="return false;" >
            <input type="text" name="query" id="query" class="query-input" placeholder="DOI/ISBN/URL, e.g 10.1086/377226" />
            <button type="submit" id="submit-query" class="btn">Submit</button>
        </form>
        <h2>Result</h2>
        <div id="result"></div>
    </body>
</html>