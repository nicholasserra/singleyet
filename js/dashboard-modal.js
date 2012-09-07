(function() {
    $('#emailOptModal').modal({
        'keyboard': false,
        'backdrop': 'static'
    }).on('hidden', function(){
        $('#findFriendsModal').modal({
            'backdrop': 'static'
        });
    });

    $('.close-modal').live('click', function(){
        $('#emailOptModal').modal('hide');
    });

    $('.close').live('click', function(){
        $('#findFriendsModal').modal('hide');
    });
})();