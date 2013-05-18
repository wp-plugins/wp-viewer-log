jQuery(document).ready(function($){
	function check_input(){
		if(!$('#r-custom').is(':checked')){
			$('label[for^="wpvl-text-wp-config"]').parent().parent().hide();
		} else {
			$('label[for^="wpvl-text-wp-config"]').parent().parent().show();
		}
	};
	check_input();
	$('input.wpvl-checked').click(function(){
		check_input();
	});
	$('div.wpvl-content').hide();
	$('div.wpvl-content:first').show();
	$('h3.nav-tab-wrapper a').css('outline','none').click(function(e){
		e.preventDefault();
		$('h3.nav-tab-wrapper a').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('div.wpvl-content').hide();
			$($(this).attr('href')).show();
	});
});