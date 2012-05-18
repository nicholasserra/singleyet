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