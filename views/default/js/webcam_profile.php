<?php
if (false) {
	?><script><?php
}
?>

elgg.provide('elgg.avatar');

elgg.avatar.options = {
	width: 480,
	height: 0
};

/**
 * Normalize the vendor-prefixed media objects
 *
 * @type @exp;navigator@pro;mozGetUserMedia|@exp;navigator@pro;webkitGetUserMedia|@exp;navigator@pro;msGetUserMedia|@exp;navigator@pro;getUserMedia
 */
elgg.avatar.getMedia = (
	navigator.getUserMedia ||
	navigator.webkitGetUserMedia ||
	navigator.mozGetUserMedia ||
	navigator.msGetUserMedia
);

elgg.avatar.init = function() {
	$(document).on('click', '.avatar-tabs a', elgg.avatar.changeTab);
	$(document).on('submit', '.elgg-form-avatar-upload', elgg.avatar.submit);
	$(document).on('submit', '.elgg-form-register', elgg.avatar.submit);

	if (elgg.avatar.getMedia) {
		elgg.avatar.initHtml5();
	} else if (elgg.avatar.hasFlash()) {
		elgg.avatar.initFlash();
	} else {
		$('#avatar-upload-tab > a').click();
		$('#avatar-acquire-tab').hide();
	}
};

elgg.avatar.hasFlash = function() {
	try {
		var fo = new ActiveXObject('ShockwaveFlash.ShockwaveFlash');
		if (fo) {
			return true;
		}
	} catch (e) {
		if (navigator.mimeTypes
			&& navigator.mimeTypes['application/x-shockwave-flash'] != undefined
			&& navigator.mimeTypes['application/x-shockwave-flash'].enabledPlugin) {
			return true;
		}
	}

	return false;
}

elgg.avatar.initHtml5 = function() {
	$(document).on('click', '#webcam-video', elgg.avatar.capturePicture);

	// must be called in the context of navigator or window, depending on browser
	elgg.avatar.getMedia.call(navigator || window,
		{
			video: true,
			audio: false
		},

		function(stream) {
			var video = $('#webcam-video').get(0);

			if (navigator.mozGetUserMedia) {
				video.mozSrcObject = stream;
			} else {
				var vendorURL = window.URL || window.webkitURL;
				video.src = vendorURL.createObjectURL(stream);
			}

			video.play();
		},

		function(err) {
			elgg.register_error(elgg.echo('webcam:webcam_error'));
		}
	);
};

elgg.avatar.shutterSound = function() {
	var audio = document.createElement('audio');
	audio.src = elgg.get_site_url() + 'mod/webcam/haxe/shutter.mp3';

	return {
		play: function() {
			audio.play();
		}
	};
}

elgg.avatar.initFlash = function() {
	var html = '<div id="flashContent">'
		+ '<object type="application/x-shockwave-flash" data="' + elgg.get_site_url()
			// @todo make these dynamic
			+ 'mod/webcam/haxe/take_picture_profile.swf" width="480" height="360">'
		+ '<param name="movie" value="take_picture_profile.swf" />'
		+ '<param name="quality" value="high" />'
		+ '<param name="bgcolor" value="#ffffff" />'
		+ '<param name="play" value="true" />'
		+ '<param name="loop" value="true" />'
		+ '<param name="wmode" value="transparent" />'
		+ '<param name="scale" value="noscale" />'
		+ '<param name="menu" value="true" />'
		+ '<param name="devicefont" value="false" />'
		+ '<param name="salign" value="" />'
		+ '<param name="allowScriptAccess" value="sameDomain" />'
		+ '<a href="http://www.adobe.com/go/getflash">'
		+ '	<img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" />'
		+ '</a>'
		+ '</object>'
		+ '</div>';

	$('#webcam').html(html);
};

/**
 * Normalize resolutions
 */
elgg.avatar.setRes = function(ev) {
	var $video = $('video');
	video = $video.get(0);

	// normalize the resolutions
	var canvas = $('#webcam-canvas');

	elgg.avatar.options.height = video.videoHeight / (video.videoWidth / elgg.avatar.options.width);
	$video.attr('width', elgg.avatar.options.width);
	$video.attr('height', elgg.avatar.options.height);
	canvas.attr('width', elgg.avatar.options.width);
	canvas.attr('height', elgg.avatar.options.height);

	$video.data('streaming', true);
};

/**
 * Capture a picture and save as base64 input data
 *
 * @param Obj ev Event
 * @returns {undefined}
 */
elgg.avatar.capturePicture = function(ev) {
	ev.preventDefault();

	var canvas = $('#webcam-canvas').get(0),
		video = this;

	// if clicked on while paused, unpause
	if (video.paused) {
		video.play();
		$(video).removeClass('has-photo');
		elgg.avatar.removeBase64Input();
		return;
	}

	// @todo just make this return the resolution
	elgg.avatar.setRes();

	var width = elgg.avatar.options.width,
		height = elgg.avatar.options.height;

	elgg.avatar.shutterSound().play();
	video.pause();
	$(video).addClass('has-photo');
	canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(video, 0, 0, width, height);

    // the data url format is data:<mime_type>;base64,<data>
	var data = canvas.toDataURL().split(',')[1];
	elgg.avatar.saveBase64Input(data, $(this).closest('form'));
};

elgg.avatar.saveBase64Input = function(data, formElement) {
	var html = "<input id='webcam-image-base64' type='hidden' name='webcam-image-base64'>";
	$(formElement).prepend(html);
	$('#webcam-image-base64').attr('value', data);
};

elgg.avatar.removeBase64Input = function() {
	$('#webcam-image-base64').remove();
}

elgg.avatar.changeTab = function(ev) {
	ev.preventDefault();
	var $this = $(this);
	var $li = $this.closest('li');
	var $ul = $this.closest('ul');

	// change tab
	$ul.find('li').removeClass('elgg-state-selected');
	$li.addClass('elgg-state-selected');

	// change content
	$("#avatar-options > div").hide();
	$('#' + $li.attr('id').replace('-tab', '')).show();
};

elgg.avatar.submit = function(ev) {
	// prevent if no data at all
	if (!$('#webcam-image-base64').val()
		&& !$('input[name=avatar]').val()
		&& !$('input[name=avatar_url]').val()
	) {
		elgg.register_error(elgg.echo('webcam:no_avatar_selected'));
		ev.preventDefault();
	}
};

elgg.register_hook_handler('init', 'system', elgg.avatar.init);
