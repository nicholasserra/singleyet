$('a:not(.no_follow)').on('click', function(e){
    e.preventDefault();
    window.location.href = $(this).attr('href');
});