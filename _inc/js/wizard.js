jQuery(document).ready(function($){

    //show or hide feedback
    $('.feedback-link a').click(function(e) {
        e.preventDefault();
        var wrapper = $(this).parents('.feedback-wrapper');
        var content = wrapper.find('.feedback-content');
        content.slideToggle();
    });
    
    //regex
    $('a.regex-link').click(function(e) {
        e.preventDefault();
        var wrapper = $(this).parents('.track-info');
        var content = wrapper.find('.regex-wrapper');
        content.slideToggle();
    });
    //default show regex
    $('.regex-wrapper').each(function() {
        var input = $( this ).find('input.regex');
        if (input.val() != ""){
            $(this).addClass('has-regex');
        }
    });
    
});

