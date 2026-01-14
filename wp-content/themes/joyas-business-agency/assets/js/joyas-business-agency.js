;(function($) {
'use strict'
// Dom Ready
var trapFocusInsiders = function(elem) {
	var tabbable = elem.find('select, input, textarea, button, a').filter(':visible');
	
	var firstTabbable = tabbable.first();
	var lastTabbable = tabbable.last();
	/*set focus on first input*/
	firstTabbable.focus();
	
	/*redirect last tab to first input*/
	lastTabbable.on('keydown', function (e) {
	   if ((e.which === 9 && !e.shiftKey)) {
		   e.preventDefault();
		   
		   firstTabbable.focus();
		  
	   }
	});
	
	/*redirect first shift+tab to last input*/
	firstTabbable.on('keydown', function (e) {
		if ((e.which === 9 && e.shiftKey)) {
			e.preventDefault();
			lastTabbable.focus();
		}
	});
	
	/* allow escape key to close insiders div */
	elem.on('keyup', function(e){
	  if (e.keyCode === 27 ) {
		elem.hide();
	  };
	});
	
};
	$(function() {
		if( $(".searchbar-action").length ){
			$(".searchbar-action").on("click", function(e) {
				$('.search-bar-modal').addClass('active').find('input').focus();
				trapFocusInsiders( $(".search-bar-modal") );
			});

			$(".appw-modal-close-button").on("click", function(e) {
			    $('.search-bar-modal').removeClass('active');

			    setTimeout(function() {
			        $('.searchbar-action').focus();
			    }, 50); // Wait until the modal class is removed
			});
		}
		AOS.init();
	});
})(jQuery);