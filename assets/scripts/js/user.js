jQuery(document).ready(function($){
	$('#User_country_id').bind('change', function(){

		$('#User_country_id').phpr().post('on_update_states_list', {
			loadIndicator: { show: false }
		}).send();

	});
});