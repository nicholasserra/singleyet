(function() {
    $('#myModal').modal({
        'keyboard': false,
        'backdrop': 'static'
    });

    $('.close-modal').live('click', function(){
        $('.modal').modal('hide');
    });
})();