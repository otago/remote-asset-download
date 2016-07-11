<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>Remote Asset Sync</title>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
		<style>h1 {display:none}</style>
		<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
	</head>

	<body>
		<div class="container-fluid">
			<div class="container">
				<h3>Download assets from remote SilverStripe instance</h3>
				<p id="FetchURL" style="display:none">$FetchURL</p>
				<p id="DownloadURL" style="display:none">$DownloadURL</p>
				<p>Downloading to <strong>$ToMachine</strong> from $Target</p>
				<div class="progress">
					<div id="ProgressBar" class="progress-bar" role="progressbar" aria-valuenow="00"
						 aria-valuemin="0" aria-valuemax="100" style="width:00%">
						<span>00% Complete</span>
					</div>
				</div>
				<div class="alert alert-danger" id="Error"></div>
				<div class="alert alert-success" id="Success"></div>
				<div class="alert alert-info" id="Info"></div>

				<ul class="list-group" id="DownloadList">
					<!-- list-group-item-success,list-group-item-info,list-group-item-warning,list-group-item-danger-->
				</ul>
				<ul class="list-group" id="IgnoreList">
					<!-- list-group-item-success,list-group-item-info,list-group-item-warning,list-group-item-danger-->
				</ul>
			</div>
		</div>

	</body>
	<script>
		(function ($) {
			$(document).ready(function () {
				$('#Error').hide();
				$('#Success').hide();
				var downloaditems = [];
				var downloaditemslen = 0;
				var downloaditemscount = 0;

				var movepercentbar = function (myint) {
					$('#ProgressBar').css('width', parseInt(myint) + '%');
					$('#ProgressBar').find('span').html(parseInt(myint) + '% Complete');
					$('#ProgressBar').attr('aria-valuenow', parseInt(myint));
					$('#ProgressBar').addClass('progress-bar-striped').addClass('active');
				};
				var stoppercentbar = function (error) {
					$('#Error').show().html(error);
					$('#Success,#Info').hide();
					$('#ProgressBar').removeClass('progress-bar-striped').removeClass('active');
				};

				movepercentbar(15);
				$('#Info').show().html('Loading list of files, this may take a few minutes...');

				// download a list of items (given an array)
				var downloadfiles = function (items) {
					// when you hit this bit, you're done.
					if (!$.isArray(items) || items.length === 0) {
						$('#Info').hide();
						$('#Success').show().html('Finished!');
						movepercentbar(100);
						$('#ProgressBar').removeClass('progress-bar-striped').removeClass('active');
						return;
					}

					// get this current file to download
					item = items.pop();

					// create new list item
					li = $('<li/>').html(item);
					li.addClass('list-group-item-info');
					$('#DownloadList').append(li);
					$('#Info').show().html('Downloading ' + item);
					
					// do download here...
					$.getJSON($('#DownloadURL').html(), {download: item}, function (data) {
						li.removeClass('list-group-item-info').addClass('list-group-item-success').html(li.html() + ' downloaded');
					}).fail(function () {
						li.removeClass('list-group-item-info').addClass('list-group-item-danger').html(li.html() + ' failed to download');
					}).always(function () {
						downloaditemscount++;
						movepercentbar((.3 + (downloaditemscount / downloaditemslen) / 1.429) * 100);
						downloadfiles(items);
					});

				};


				// download list of files
				$.getJSON($('#FetchURL').html(), function (data) {
					if (!data.download) {
					//	stoppercentbar('<strong>Error!</strong> prasing of file list to download failed');
					//	return;
					}
					if (!data.ignored) {
					//	stoppercentbar('<strong>Error!</strong> prasing of file list to download failed');
					//	return;
					}
					downloaditems = data.download;
					ignoreditems = data.ignored;
					ignoredsynced = data.synced;

					if (!$.isArray(downloaditems)) {
						stoppercentbar('<strong>Error!</strong> downloaded list of items is not an array');
						return;
					}
					if (!$.isNumeric(ignoreditems)) {
						stoppercentbar('<strong>Error!</strong> ignored items is not number');
						return;
					}
					if (!$.isNumeric(ignoredsynced)) {
						stoppercentbar('<strong>Error!</strong> synced items is not a number');
						return;
					}

					// get length of the download and ignore item list.
					downloaditemslen = $.map(downloaditems, function (n, i) {
						return i;
					}).length;

					successstr = 'Successfully loaded file list. Will attempt to download <strong>' + downloaditemslen +
							'</strong> file(s), <strong>' + ignoreditems +
							'</strong> will be ignored, <strong>' + ignoredsynced + '</strong> are already on this server.';
					$('#Success').show().html(successstr);
					$('#Info').hide().html('');

					// ~30% is about done with the list
					movepercentbar(30);

					//
					downloadfiles(downloaditems);

				}).fail(function () {
					stoppercentbar('<strong>Error!</strong> download of file list failed');
				});

			});
		})(jQuery);
	</script>
</html>

