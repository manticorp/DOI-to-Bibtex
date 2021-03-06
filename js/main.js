$(function(){
    var loading = {
        event: null,
        number: 0,
        texts: [
            "Dispatching Gremlins",
            "Applying Magic Powder",
            "Buttering Unicorns",
            "Marinating API Result",
            "BBQing BiBTeX",
            "Roasting Gnome Hats"
        ],
        start: function(){
            this.stop();
            $loadingbox = $('<div class="loadingbox">').append($('<p id="loadingText"></p>')).append($('<img class="loading-gif-fancy" src="img/loading-fancy.gif" alt="loading..."/>'));
            $('#result').html('').append($loadingbox);
            var that = this;
            $('#result #loadingText').text(this.texts[0] + "...");
            this.event = setInterval(function(){
                $('#result #loadingText').text(that.randomText() + "...");
            },750);
        },
        stop: function(){
            clearInterval(this.event);
        },
        randomText: function(){
            return this.texts[Math.floor(Math.random()*this.texts.length)];
        }
    };
    $(window).bind( 'hashchange', function(e) {
        doQuery();
    });
    doQuery();
    
    var hashParams = $.deparam.fragment()
    $('#query').val(hashParams.query);
    $('#query').change(inputChange).keyup(inputChange).click(function( e ){ 
        $(this).select();
    });
    
    $('#submitQuery').click(function(){
        doQuery();
    });
    
    $('#queryform').submit(function(){
        doQuery();
        return false;
    });
    
    function doQuery(){
        var hashParams = $.deparam.fragment()
        var query = hashParams.query || "";
        if(query !== "") {
            loading.start();
            getAndDisplayBibtex(query);
        } else {
            noQuery();
        }
    }
    
    var changeTimeout;
    function inputChange(e){
        clearTimeout(changeTimeout);
        var val = $(this).val();
        changeTimeout = setTimeout(function(){
            $.bbq.pushState({ query: val });
        },500);
    }
    
    function failure(data){
        var failtext = "<span class='error-head'>Sorry</span>, your search <strong class='query-term'>'" + data.query + "'</strong> returned no results.";
        var $failtext = $('<div class="error">').append($('<p>').html(failtext));
        if(typeof data.message !== "undefined") $failtext.append($("<p>").html("Message from server: <span class='query-term'>" + data.message + "</span>"));
        if(data.url) $failtext.append($("<p>").html("Url used to query: <a href='"+data.url+"'>"+data.url+"</a>"));
        $('#result').html('').append($failtext);
    }
    
    function noQuery(){
        $('#result').html('<p>Please enter a DOI/ISBN/URL to be processed</p>');
    }
    
    function getAndDisplayBibtex(query){
        var data = {};
        
        // update with query value if it exists
        data.query  = query;
        data.format = "json";
        
        // our AJAX url
        var url = "getInfo.php";
        
        // get JSON from url
        $.getJSON(url, data, function(data){
            loading.stop();
            if(data.success === false || data.success === 'false'){
                failure(data);
            } else {
                $('#result').html('');
                if(data.message){
                    $('#result').append("<p class='message'>" + data.message + "</p>");
                }
                $.each(data.bibtex, function(i, bibtex) {
                    $('#result').append(buildResult(bibtex, i));
                });
                $('textarea').select();
                zclipCopyLinks();
            }
        }).fail(function(jqxhr, textStatus, error){
            // error handling
            loading.stop();
            data = $.parseJSON(jqxhr.responseText);
            console.log(data);
            failure(data);
        });
    }
    
    $.zeroclipboard({
        moviePath: 'ZeroClipboard.swf',
        activeClass: 'active',
        hoverClass: 'hover'
    });
    
    function zclipCopyLinks(){
        $('.copy-bibtex-link').each(function(index){
            $(this).zeroclipboard({
                dataRequested: function (event, setText) {
                    // In order to dynamically set the text to copy to the clipboard
                    // at the time the mouse clicks the button
                    // NOTE: this function is called within the execution context of the flash movie,
                    // therefore any exception might be silently ignored.
                    // NOTE 2: the function "setText" should be called during the execution of this
                    // callback otherwise the text copied on the clipboard will not be correct.
                    // Therefore any AJAX call should be configured to be SYNCHRONOUS
                    var taid = "#" + $(this).data('ta');
                    setText($(taid).val());
                },
                complete: function () {
                    // Do something after the text has been copied to the system clipboard
                    // (like notifying the user)
                    copiedWarning($(this));
                }
            });
        });
        $('.copy-cite-link').each(function(index){
            $(this).zeroclipboard({
                dataRequested: function (event, setText) {
                    setText($(this).data('cite-text'));
                },
                complete: function () {
                    copiedWarning($(this));
                }
            });
        });
    }
    
    function copiedWarning($element, text){
        text = text || "Copied...";
        var position = $element.position();
        var $copied = $element.clone();
        $copied.addClass('disappearing-btn');
        $copied.text("Copied");
        $copied.css('left', position.left).css('top', position.top);
        $copied.height($element.height()).width($element.width());
        $copied.addClass('disappearing-btn-active');
        $copied.insertAfter($element);
        $copied.animate({top: (position.top - 30) + "px", opacity: 0},500);
    }
    
    function buildResult(bibtex, index){
        index = (typeof index == "undefined") ? 0 : index;
        var $container = $('<div>').addClass('bibtex-result-container');
        
        var taid = (citeToId(bibtex.cite) + index);
        
        var lines = bibtex.text.split("\n");
        var $textarea = $('<textarea>').attr('rows',lines.length + 1).addClass('bibtex-result').attr('id', taid).val(bibtex.text);
        
        var $copyBibtexLink = $('<a>')
        .attr('href', 'javascript:void(0)')
        .html('Copy <i class="bibtex"></i>')
        .addClass('btn')
        .addClass('copy-bibtex-link')
        .attr('id',citeToId(bibtex.cite) + '-copy-bibtex')
        .data('cite', bibtex.cite)
        .data('ta', taid);
        
        var $copyCiteLink = $('<a>')
        .attr('href', 'javascript:void(0)')
        .html('Copy <i class="latex"></i> \cite')
        .addClass('btn')
        .addClass('copy-cite-link')
        .attr('id',bibtex.cite + '-copy-cite')
        .data('cite-text',"\\cite{" + bibtex.cite + "}")
        .data('cite', bibtex.cite);
        
        $container.append($copyBibtexLink).append($copyCiteLink).append($textarea);
        
        return $container;
    }
    
    function citeToId(cite){
        return cite.replace(/[^a-zA-Z0-9]/gi, "");
    }
});