jQuery(document).ready(function($){
	$('div.wpvl-content').hide();
	$('div.wpvl-content:first').show();
	$('h3.nav-tab-wrapper a').css('outline','none').click(function(e){
		e.preventDefault();
		$('h3.nav-tab-wrapper a').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('div.wpvl-content').hide();
		$($(this).attr('href')).show();
	});
	function multi_check(){
		$('table.form-table tr').each(function(i, e) {
			if( $(e).find('input.disabled').is(':checked') )
				$('table.form-table tr').eq(i+1).hide();
			else
				$('table.form-table tr').eq(i+1).show();
		});
	}
	multi_check();
	$('input.wpvl-checked').click(function(){
		multi_check();
	});
});
