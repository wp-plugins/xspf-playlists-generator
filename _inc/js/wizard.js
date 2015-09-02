jQuery(document).ready(function($){
    //rouspetards
    $("#xspf-wizard-tabs").tabs();

    //regex
    $('.xspfpl-wizard-step-content a.regex-link').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('tr');
        var regex_row = selector_row.next();
        regex_row.show();
    });
    //default show regex
    $('.xspfpl-wizard-step-content a.regex-link').each(function() {
        var selector_row = $(this).parents('tr');
        var regex_row = selector_row.next();
        regex_row.addClass('regex-row');
        var input = regex_row.find('input');
        if (input.val() != ""){
            regex_row.addClass('has-regex');
        }
    });
    
    //display wizard content
    $('#xspfpl-wizard-metabox .inside').slideDown();
    
});

