<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>Remote Asset Download</title>
		<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
        <style>h1 {display:none}</style>
		<script
  src="https://code.jquery.com/jquery-3.4.1.min.js"
  integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
  crossorigin="anonymous"></script>
  </head>

	<body>
		<div class="container-fluid">
			<div class="container">
				<h3>Download assets from remote SilverStripe instance</h3>
				<p id="RemoteAssetReadFilesControllerURL" style="display:none">$RemoteAssetReadFilesControllerURL</p>
				<p id="RemoteAssetDownloadFilesControllerURL" style="display:none">$RemoteAssetDownloadFilesControllerURL</p>
				<p>Downloading to <strong>$ToMachine</strong> from $Target</p>
                <!--
				<div class="progress">
					<div id="ProgressBar" class="progress-bar" role="progressbar" aria-valuenow="00"
						 aria-valuemin="0" aria-valuemax="100" style="width:00%">
						<span>00% Complete</span>
					</div>
				</div>-->
                <br>
				<div class="alert alert-info" id="Info"></div>
				<div class="alert alert-success" id="Success"></div>
				<div class="alert alert-danger" id="Error"></div>

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

            // requests the server to download a list of files given a resulting graphql response.
            var RemoteAssetReadFiles = function(startpage) {
                let RemoteAssetReadFilesControllerURL = $('#RemoteAssetReadFilesControllerURL').html();
                let RemoteAssetDownloadFilesControllerURL = $('#RemoteAssetDownloadFilesControllerURL').html();
                let downloadarray = new Object();

                $('#Info').show().html('Retrieving remote list of newest files on page '+startpage+'...');

                var jqxhr = $.ajax( RemoteAssetReadFilesControllerURL+'/'+startpage, {
                    dataType :"json"
                })
                .done(function(data) {
                    $('#Info').show().html('Received batch of files.');
                    
                    if(data.info) {
                        $('#Info').show().html(data.info);
                        return;
                    }

                    // try in case the graphql is flakey...
                    try {
				        $('#Info').show().html('Requesting server batch download '+data.data.readFiles.edges.length+' files.');
                    } catch(e) { }


                    try {
                        data.data.readFiles.edges.forEach(function (item) {
                            downloadarray[item.node['id']] = item.node;
                        });
                    } catch(e) {
                        $('#Info').show().html('Failure to fetch lastest files from target server');
                        return;
                    }
                    
                    $.ajax({
                        type: "POST",
                        url: RemoteAssetDownloadFilesControllerURL,
                        data: downloadarray,
                        success: function(result) {
				            $('#Info').show().html('done burger :D');
                            var finished = false;
                            try {
                                result.forEach(function (item) {
                                    if(item.code === 200) {
                                        $('#Success').show().append('<p>Success. '+item.success+'</p>');
                                    } else {
                                        $('#Error').show().append('<p>Failure. '+item.error+'</p>');
                                    }

                                    // you're done! you've started to match existing files
                                    if(item.finishquery) {
                                        finished = true;
                                    }
                                });
                            } catch(e) {
                                $('#Info').show().html('Failure to resolve bulk download request');
                                return;
                            }
                            
                            if(finished) {
                               // $('#Info').show().html("Finished! you've reached new files that allready exist");
                               // return;
                            }
                            RemoteAssetReadFiles(startpage+10);
                        },
                        error: function (result) {
				            $('#Info').show().html('Failure to store files locally. End.');
                        }
                    });
                })
                .fail(function(e) {
                    $('#Info').show().html(e);
                })
                .always(function() {
                    //$('#Info').show().html('Completed request');
                });
                


				/*$.getJSON(RemoteAssetReadFilesControllerURL+'/'+startpage, function (data) {
				    $('#Info').show().html('Received batch of files.');
                    
                    if(data.info) {
                        $('#Info').show().html(data.info);
                        return;
                    }

                    // try in case the graphql is flakey...
                    try {
				        $('#Info').show().html('Requesting server batch download '+data.data.readFiles.edges.length+' files.');
                    } catch(e) { }


                    try {
                        data.data.readFiles.edges.forEach(function (item) {
                            downloadarray[item.node['id']] = item.node;
                        });
                    } catch(e) {
                        $('#Info').show().html('Failure');
                        return;
                    }
                    
                    $.ajax({
                        type: "POST",
                        url: RemoteAssetDownloadFilesControllerURL,
                        data: downloadarray,
                        success: function(result) {
				            $('#Info').show().html('done burger :D');
                            var finished = false;
                            try {
                                result.forEach(function (item) {
                                    console.log(item);
                                    if(item.code === 200) {
                                        $('#Success').show().append('<p>Success. '+item.success+'</p>');
                                    } else {
                                        $('#Error').show().append('<p>Failure. '+item.error+'</p>');
                                    }

                                    // you're done! you've started to match existing files
                                    if(item.finishquery) {
                                        finished = true;
                                    }
                                });
                            } catch(e) {
                                $('#Info').show().html('Failure to resolve bulk download request');
                                return;
                            }
                            console.log(finished);
                            if(finished) {
                               // $('#Info').show().html("Finished! you've reached new files that allready exist");
                               // return;
                            }
                            RemoteAssetReadFiles(startpage+1);
                        }
                    });
                })
                .error(function(e) {
                console.log(e);
                    $('#Info').show().html(e);
                });*/
            }

			$(document).ready(function () {
                $('#Error').hide();
				$('#Success').hide();

                // recursively ask for files to download
                RemoteAssetReadFiles(0);
			});
		})(jQuery);
	</script>
</html>

