(function( $ ) {
	'use strict';
	$(document).ready(function($){
		$( ".valitorpay-advanced-settings a" ).click(function(e) {
			var $this = $(this),
				$title = $this.parent();
			e.preventDefault();
			e.stopPropagation();
			$title.next('table').slideToggle( "slow", function() {
				$this.toggleClass('advanced-settings-showed');
			});

			if( !$this.hasClass('advanced-settings-showed') ){
				$([document.documentElement, document.body]).animate({
					scrollTop: $title.offset().top
				}, 2000);
			}
		});
	});
})( jQuery );
