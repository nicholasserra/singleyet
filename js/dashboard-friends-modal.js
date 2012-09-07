(function() {
    $('#findFriendsModal').modal({
        'backdrop': 'static'
    });

    $('.close').live('click', function(){
        $('#findFriendsModal').modal('hide');
    });
})();