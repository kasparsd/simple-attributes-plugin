jQuery(document).ready(function($) {
	/*
		Location / map
	*/

	$('.sap-location-map').gmap().bind('init', function(ev, map) {
		var $thismap = this;
		var $map_options = {}

		var $lat = $('.latitude', $(this).siblings('.geo')).attr('title');
		var $lng = $('.longitude', $(this).siblings('.geo')).attr('title');

		if ( $lat && $lng ) {
			var $pos = new google.maps.LatLng($lat, $lng);
			$.extend($map_options, { position: $pos, center: $pos });
		}

		console.log($map_options);
	});

});

