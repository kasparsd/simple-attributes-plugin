jQuery(document).ready(function($) {
	/*
		Location / map
	*/
	
	$('.sap-location').each(function() {
		var $location = this;
		var $lat = $( '.latitude', this ).attr('title');
		var $lng = $( '.longitude', this ).attr('title');
		
		var $latlng = new google.maps.LatLng($lat, $lng);
		var $map_options = {
				zoom: 10,
				center: $latlng,
				mapTypeId: google.maps.MapTypeId.ROADMAP,
			};
		
		if ( $( '.map', $location ).size() ) {
			var $map = new google.maps.Map( $( '.map', $location )[0], $map_options );
			var $marker = new google.maps.Marker({
					map: $map,
					position: $latlng
				});
		}
	});
	
});

