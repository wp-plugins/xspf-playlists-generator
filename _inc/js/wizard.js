jQuery(document).ready(function($){
    
    //show feedback link if feedback exists
    $( ".xspf-wizard-step" ).each(function() {
        var feedback_block = $( this ).find('.feedback-wrapper');
        var feedback_link = $( this ).find('.feedback-link');
        if (feedback_block.length > 0){
            feedback_link.show();
        }
    });
    
    //show or hide feedback
    $('.feedback-link a').click(function(e) {
        e.preventDefault();
        var wrapper = $(this).parents('.xspf-wizard-step');
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
    
});

