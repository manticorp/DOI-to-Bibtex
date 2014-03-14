<html>
    <head>
        <title>DOI/ISBN/URL to BibTeX Converter<?if(isset($_REQUEST["query"])){ echo " - " . $_REQUEST["query"];};?></title>
        <link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
        <link href='css/style.css' rel='stylesheet' type='text/css'>
        
        <link rel="apple-touch-icon" sizes="57x57" href="apple-touch-icon-57x57.png">
        <link rel="apple-touch-icon" sizes="114x114" href="apple-touch-icon-114x114.png">
        <link rel="apple-touch-icon" sizes="72x72" href="apple-touch-icon-72x72.png">
        <link rel="apple-touch-icon" sizes="144x144" href="apple-touch-icon-144x144.png">
        <link rel="apple-touch-icon" sizes="60x60" href="apple-touch-icon-60x60.png">
        <link rel="apple-touch-icon" sizes="120x120" href="apple-touch-icon-120x120.png">
        <link rel="apple-touch-icon" sizes="76x76" href="apple-touch-icon-76x76.png">
        <link rel="apple-touch-icon" sizes="152x152" href="apple-touch-icon-152x152.png">
        <link rel="icon" type="image/png" href="favicon-196x196.png" sizes="196x196">
        <link rel="icon" type="image/png" href="favicon-160x160.png" sizes="160x160">
        <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96">
        <link rel="icon" type="image/png" href="favicon-16x16.png" sizes="16x16">
        <link rel="icon" type="image/png" href="favicon-32x32.png" sizes="32x32">
        <meta name="msapplication-TileColor" content="#da532c">
        <meta name="msapplication-TileImage" content="mstile-144x144.png">
    </head>
    <body>
        <h1><img src="favicon-32x32.png" alt="Bibtex Converter Logo" id="logo" />DOI/ISBN/URL to Bibtex converter</h1>
        <p>Input any DOI, URL or ISBN number into the box below and our server gremlins will try to find what you're referring to and generate a bibtex entry for that content.</p>
        <p>Please note, you can enter an <strong>arxiv.org</strong> url (or similar) in the box above and it will automatically fetch DOI data. Similarly, you can put an <strong>amazon</strong> link to a book in and it will fetch ISBN data.</p>
        <h2>Input DOI/ISBN/URL</h2>
        <form method="POST" action="" id="queryform" onsubmit="return false;" >
            <input type="text" name="query" id="query" class="query-input" placeholder="DOI/ISBN/URL, e.g 10.1086/377226" />
            <button type="submit" id="submit-query" class="btn">Submit</button>
        </form>
        <h2>Result</h2>
        <div id="result"></div>
        <script type="text/javascript" src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
        <script type="text/javascript" src="js/ZeroClipboard.min.js"></script>
        <script type="text/javascript" src="js/jquery-zeroclipboard.js"></script>
        <script type="text/javascript" src="js/jquery.ba-bbq.min.js"></script>
        <script type="text/javascript" src="js/main.js"></script>
    </body>
</html>