$(window).scroll(function(){
    if($(window).scrollTop() == $(document).height() - $(window).height()){
        var $friends = $('.friend.hidden').slice(0, 16);
        $friends.each(function(){
            var $div = $(this).children('div'),
                img = $div.data('img');

            console.log($div);
            console.log(img);

            $(this).removeClass('hidden');
            $div.css({'background-image':'url('+img+')'});
        });
    }
});

(function() {
    $('.add_friend').live('click', function(e){
        e.preventDefault();

        var $t = $(this),
            id = $t.data('id');

        if(!id){
            return;
        }

        $.ajax({
            url: '/friends/add/',
            type: 'POST',
            data: {'id': id},
            dataType: 'json',
            success: function(){
                $t.removeClass('add_friend btn-success');
                $t.addClass('remove_friend btn-danger');
                $t.text('Remove');
            }
        });
    });

    $('.remove_friend').live('click', function(e){
        e.preventDefault();

        var $t = $(this),
            id = $t.data('id');

        if(!id){
            return;
        }

        $.ajax({
            url: '/friends/remove/',
            type: 'POST',
            data: {'id': id},
            dataType: 'json',
            success: function(){
                $t.removeClass('remove_friend btn-danger');
                $t.addClass('add_friend btn-success');
                $t.text('Add');
            }
        });
    });
})();