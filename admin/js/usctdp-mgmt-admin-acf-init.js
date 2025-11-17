(function($){
    acf.addAction('ready', function(){
        var $admin_page_wrapper = $('#usctdp-admin-new-session-wrapper'); 
        acf.do_action('append', $admin_page_wrapper);
    });

})(jQuery);