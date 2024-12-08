$(document).ready(function() {
	// Set the slides
	$('.slide').hide();
	$('.slide:first').addClass('slide-active').show();
	$('.slide-bullet:first').addClass('slide-bullet-active').show();
	
	// Testimonials bullets
	$(document).on('click', '.slide-bullet', function() {
		// Get the current element and clean the name
		var currentId = $(this).attr('id');
		var currentId = currentId.replace('slide-bullet', '');
		
		// Prepare the slides and bullets
		$('.slide').hide();
		$('.slide').removeClass('slide-active');
		$('.slide-bullet').removeClass('slide-bullet-active');
		
		// Show the new slide and bullet
		$('#slide'+currentId).addClass('slide-active').fadeIn();
		$('#slide-bullet'+currentId).addClass('slide-bullet-active').show();
		
		// Update the background image
		$('#welcome-background').removeAttr('class');
		$('#welcome-background').attr('class', 'row-welcome content-welcome'+currentId);
	});
	
	// Start the popular slider
	popularTimer = setInterval(popularSlider, 2000);
	
	// On hover, stop the animation
	$('#popular-scroll').hover(function() {
		clearInterval(popularTimer);
	}, function() {
		popularTimer = setInterval(popularSlider, 2000);
	});
});

function popularSlider() {
	// Get the first element
	var first = $('.welcome-track:first');
	first.animate({
		'margin-left':'-=110px'
	}, 1000, function() {
		// Move the first element at the end
		first.insertAfter($('.welcome-track:last'));
		first.removeAttr('style');
	});
}