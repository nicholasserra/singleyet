(function() {
    $(window).on('scroll touchend', function(){
        if($(window).scrollTop() >= $(document).height() - $(window).height()){
            var $friends = $('.friend.hidden').slice(0, 24);
            $friends.each(function(){
                var $div = $(this).children('.friend_avatar'),
                    img = $div.data('img');

                $(this).removeClass('hidden');
                $div.css({'background-image':'url('+img+')'});
            });
        }
    });

    $('.add_friend').live('click', function(e){
        e.preventDefault();

        var $t = $(this),
            id = $t.data('id');

        if(!id){
            return;
        }

        $t.button('loading');

        $.ajax({
            url: '/friends/add/',
            type: 'POST',
            data: {'id': id},
            dataType: 'json',
            success: function(){
                $t.removeClass('add_friend btn-success');
                $t.addClass('remove_friend btn-danger');
                $t.button('reset');
                $t.html('<i class="icon-white icon-eye-close"></i> Unfollow');
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

        $t.button('loading');

        $.ajax({
            url: '/friends/remove/',
            type: 'POST',
            data: {'id': id},
            dataType: 'json',
            success: function(){
                $t.removeClass('remove_friend btn-danger');
                $t.addClass('add_friend btn-success');
                $t.button('reset');
                $t.html('<i class="icon-white icon-eye-open"></i> Follow');
            }
        });
    });
})();