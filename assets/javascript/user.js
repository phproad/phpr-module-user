jQuery(document).ready(function(){

    // Country event
	jQuery('#User_country_id').bind('change', function(){
		$('User_country_id').getForm().sendPhpr(
			'onUpdateStatesList',
			{
				loadIndicator: {show: false}
			}
		)
	});

});