(function() {
    function get_news_feed(){
        $('#timeline').append($('<li />').text('Loading...'));
        $.getJSON('ajax/newsfeed/', function(data){
            if(data.success){
                $('#timeline').html(data.result.html);
            }
        });
    }

    get_news_feed();

    $('#get_news_feed').live('click', function(e){
        e.preventDefault();
        get_news_feed();
    });
})();