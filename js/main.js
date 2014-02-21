$(function(){
    $(window).bind( 'hashchange', function(e) {
        doQuery();
    });
    doQuery();
    
    var hashParams = $.deparam.fragment()
    $('#query').val(hashParams.query);
    $('#query').change(inputChange).keyup(inputChange).click(function( e ){ 
        $(this).select();
    });
    
    $('#submitQuery').click(doQuery);
    
    $('#queryform').submit(function(){
        doQuery();
        return false;
    });
    
    function doQuery(){
        var hashParams = $.deparam.fragment()
        var query = hashParams.query || "";
        console.log(query);
        if(query !== "") {
            loading();
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
        var failtext = "<span class='error'>Sorry<span>, your search <strong>'" + data.query + "'</strong> returned no results";
        var $failtext = $('<p>').html(failtext);
        $('#result').html('').append($failtext);
    }
    
    function loading(){
        $('#result').html('<img class="loading-gif-fancy" src="img/loading-fancy.gif" alt="loading..."/>');
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
            if(data.success === false || data.success === 'false'){
                failure(data);
            } else {
                $('#result').html('');
                $.each(data.bibtex, function(i, bibtex) {
                    $('#result').append(buildResult(bibtex));
                });
                zclipCopyLinks();
            }
        }).fail(function(jqxhr, textStatus, error){
            // error handling
            data = $.parseJSON(jqxhr.responseText);
            $('#gumtree-listings').append(data.message);
        });
    }
    
    function zclipCopyLinks(){
        $('.copy-bibtex-link').each(function(index){
            $(this).zclip({
                path:'ZeroClipboard.swf',
                copy:function(){ return $('textarea#' + $(this).data('cite')).val();}
            });
        });
        $('.copy-cite-link').each(function(index){
            $(this).zclip({
                path:'ZeroClipboard.swf',
                copy:function(){return $(this).data('cite-text');}
            });
        });
    }
    
    function buildResult(bibtex){
        var $container = $('<div>').addClass('bibtex-result-container');
        
        var lines = bibtex.text.split("\n");
        var $textarea = $('<textarea>').attr('rows',lines.length + 1).addClass('bibtex-result').attr('id', bibtex.cite).val(bibtex.text).data("HELLO","HI");
        
        
        var $copyBibtexLink = $('<a>').attr('href', 'javascript:void(0)').html('Copy <i class="bibtex"></i>').addClass('btn').addClass('copy-bibtex-link').attr('id',bibtex.cite + '-copy-bibtex').data('cite', bibtex.cite);
        
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
});