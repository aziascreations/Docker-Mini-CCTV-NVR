<?php
// Grabbing the camera's info

$camsInfo = [];  // Format: [[camId, camName], ...]

foreach ($_ENV as $envKey => $envValue) {
	if (strpos($envKey, 'NP_CAM_') === 0) {
		array_push($camsInfo, [substr($envKey, strlen('NP_CAM_')), $envValue]);
	}
}

// Grabbing the other env variables.
$pageTitle = $_ENV['NP_TITLE'] ?? 'NibblePoker\'s Mini CCTV NVR';
$pageFooter = $_ENV['NP_FOOTER'] ?? 'Made by <a href="https://github.com/aziascreations">BOZET Herwin</a> on <a href="https://github.com/aziascreations/Docker-Mini-CCTV-NVR">Github</a>';

// Root location of all recordings.  (Not used yet)
$rootLocation = "./data/";

// Grabbing the requested cam's ID
// The id should be the same as the sub-folder into which this camera's recordings are located.
$camId = $_GET['cam'] ?? null;

// Determining if the ID is valid and the name that should be shown.
// If the ID is invalid, it is set back to `null`.
if(is_null($camId)) {
	$camName = "None";
} else {
	$isCamValid = false;
	
	foreach ($camsInfo as $singleCamInfo) {
		if($singleCamInfo[0] == $camId) {
			$camName = $singleCamInfo[1];
			$isCamValid = true;
			break;
		}
	}
	
	if(!$isCamValid) {
		$camName = "Unknown";
		$camId = null;
	}
}

// Grabbing the list of recordings if needed.
if(is_null($camId)) {
	// No cam selected, we just use empty variables.
	$basePath = "./";
	$files = [];
} else {
	$basePath = "/data/".$camId."/";
	$files = array_values(array_diff(scandir("./data/".$camId."/"), array('.', '..')));
	// Removing the newest one as it is highly likely to currently being written to by ffmpeg.
	array_pop($files);
}

// If we only need to send the JSON, we send it and don't go further.
$returnJsonOnly = !is_null($_GET['json'] ?? null);
if($returnJsonOnly) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($files);
	exit();
}

// Function used to calculate the disk space taken by recordings later on.
function folderSize($dir){
	$count_size = 0;
	$count = 0;
	$dir_array = scandir($dir);
	foreach($dir_array as $key=>$filename){
		if($filename!=".." && $filename!="."){
			if(is_dir($dir."/".$filename)){
				$new_foldersize = foldersize($dir."/".$filename);
				$count_size = $count_size+ $new_foldersize;
			} else if(is_file($dir."/".$filename)) {
				$count_size = $count_size + filesize($dir."/".$filename);
				$count++;
			}
		}
	}
	return $count_size;
}

// Function used to format the disk space taken by recordings later on.
function sizeFormat($bytes) {
	$kb = 1024;
	$mb = $kb * 1024;
	$gb = $mb * 1024;
	$tb = $gb * 1024;
	if (($bytes >= 0) && ($bytes < $kb)) {
		return $bytes . ' B';
	} elseif (($bytes >= $kb) && ($bytes < $mb)) {
		return ceil($bytes / $kb) . ' KiB';
	} elseif (($bytes >= $mb) && ($bytes < $gb)) {
		return ceil($bytes / $mb) . ' MiB';
	} elseif (($bytes >= $gb) && ($bytes < $tb)) {
		return ceil($bytes / $gb) . ' GiB';
	} elseif ($bytes >= $tb) {
		return ceil($bytes / $tb) . ' TiB';
	} else {
		return $bytes . ' B';
	}
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport"
		  content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title><?php echo($pageTitle); ?></title>
	<link rel="stylesheet" href="/css/simplette.all.min.css">
	<style>
		#video-selector {
			min-width: 95%;
		}
		video {
			max-height: 60vh;
			max-width: 95vw;
			border-radius: 0.5em;
		}
		#skippers a {
			margin-left: 1em;
			margin-right: 1em;
			user-select: none;
		}
		input[type=range] {
			box-shadow: none;
		}
		#video-caching {
			display: none;
		}
	</style>
</head>
<body>
	<nav class="margin-container">
		<ul class="link-list">
			<li><a href="/">Home</a></li>
			<li><a href="/data">Raw recordings</a></li>
			<?php
			// Adding the cameras in the navbar
			foreach ($camsInfo as $singleCamInfo) {
				echo("<li><a href=\"/?cam=" . $singleCamInfo[0] . "\">" . $singleCamInfo[1] . "</a></li>");
			}
			?>
		</ul>
	</nav>
	<header><h1><b><?php echo($pageTitle); ?></b></h1></header>
	<hr><hr>
	<div class="margin-container auto-paragraph-margin">
		<table style="width: 100%;">
			<tr>
				<td>
					<h3 style="width: 100%;">Camera: <i><?php echo($camName); ?></i></h3>
				</td>
				<td>
					<span style="float: right;"><?php
						// Printing the space taken by all cams and the current one if possible.
						$totalSize = sizeFormat(folderSize("./data/"));
						if(is_null($camId)) {
							echo($totalSize);
						} else {
							echo(sizeFormat(folderSize("./data/" . $camId . "/")) . " / " . $totalSize);
						}
						?></span>
				</td>
			</tr>
		</table>
	</div>
	<hr>
	<div class="margin-container">
	<?php
	if(is_null($camId)) {
		// No camera selected.
		echo("<p class=\"h5\" style=\"margin-bottom: 0.5em;\">Select one camera:</p>");
		echo("<ul class=\"link-list h5 indent-container\">");
		
		// Adding the cameras in the URL list
		foreach ($camsInfo as $singleCamInfo) {
			echo("<li><a href=\"/?cam=" . $singleCamInfo[0] . "\">" . $singleCamInfo[1] . "</a></li>");
		}
		
		echo("</ul>");
	} else {
		// We have selected one, we add the video, slider, jumpers and filename placeholder.
		echo("<center>");
		echo("<video id=\"cctv-out\" controls></video>");
		echo("<br>");
		echo("<input type=\"range\" id=\"video-selector\" min=\"0\" max=\"" . count($files) . "\" value=\"0\">");
		echo("<br>");
		echo("<p id=\"skippers\">");
		echo("<a id=\"skip-minus-25\">&lt;&lt;&lt;&lt; 25</a>");
		echo("<a id=\"skip-minus-10\">&lt;&lt;&lt; 10</a>");
		echo("<a id=\"skip-minus-5\">&lt;&lt; 5</a>");
		echo("<a id=\"skip-minus-1\">&lt; 1</a>");
		echo("<a id=\"skip-plus-1\">1 &gt;</a>");
		echo("<a id=\"skip-plus-5\">5 &gt;&gt;</a>");
		echo("<a id=\"skip-plus-10\">10 &gt;&gt;&gt;</a>");
		echo("<a id=\"skip-plus-25\">25 &gt;&gt;&gt;&gt;</a>");
		echo("</p>");
		echo("<br>");
		echo("<p>File: <a id=\"url-video\" href=\"#\">Non définis</a> (<span id=\"vid-count-current\">0</span>/<span id=\"vid-count-total\">0</span>)</p>");
		echo("</center>");
		echo("<br>");
	}
	?>
	</div>
	<hr><hr>
	<footer>
		<p><?php echo($pageFooter); ?></p>
	</footer>
	<script>
		<?php
		// Adding the base path and initial file listing as JS variables.
		echo("const basePath = \"" . $basePath . "\";");
		echo("let files = " . json_encode($files) . ";");
		?>
		
		const videoCaching = document.createElement('video');
		const videoListUpdateIntervalMs = 10 * 1000;
		
		const startOffset = 2;
		let iCurrentVideo = files.length - startOffset;
		if(iCurrentVideo < 0) {
			iCurrentVideo = 0;
		}
		document.addEventListener('DOMContentLoaded', function() {
			videoCaching.preload = 'auto';
			videoCaching.id = 'video-caching';
			
			const eVideo = document.getElementById("cctv-out");
			const eVideoSelector = document.getElementById("video-selector");
			
			// If we have a video element in the DOM, we set it up.
			if(eVideo !== null) {
				// Handles every change of video
				const playNextVideo = () => {
					if(files.length > 0 && iCurrentVideo <= files.length) {
						const newSource = basePath + files[iCurrentVideo];
						eVideo.src = newSource;
						
						// Setting the MIME type on the visible player, just in case.
						if(newSource.endsWith(".mkv")) {
							eVideo.type = 'video/x-matroska';
						} else if(newSource.endsWith(".mp4")) {
							eVideo.type = 'video/mp4';
						} else {
							eVideo.type = '';
						}
						
						eVideoSelector.value = iCurrentVideo;
						document.getElementById("url-video").href = newSource;
						document.getElementById("url-video").text = newSource;
						document.getElementById("vid-count-current").textContent = iCurrentVideo;
						eVideo.play();
						
						// If there is a next video in the list, we attempt to cache it via a hidden player.
						if(iCurrentVideo + 1 < files.length) {
							videoCaching.preload = 'auto';
							videoCaching.src = basePath + files[iCurrentVideo + 1];
							
							// Setting the MIME type on the caching player, just in case.
							if(files[iCurrentVideo + 1].endsWith(".mkv")) {
								videoCaching.type = 'video/x-matroska';
							} else if(files[iCurrentVideo + 1].endsWith(".mp4")) {
								videoCaching.type = 'video/mp4';
							} else {
								videoCaching.type = '';
							}
						}
					}
				};
				
				// Repeated function that updates the list of available videos every now and then.
				const updateVideoList = () => {
					fetch(window.location + "&json=1")
						.then(response => {
							if(!response.ok) {
								throw new Error('Network response was not ok');
							}
							return response.json();
						})
						.then(data => {
							let newIndex = data.indexOf(files[iCurrentVideo]);
							files = data;
							if(newIndex === -1) {
								newIndex = files.length - startOffset;
							}
							iCurrentVideo = newIndex;
							eVideoSelector.value = iCurrentVideo;
							eVideoSelector.max = files.length;
							document.getElementById("vid-count-current").textContent = iCurrentVideo;
							document.getElementById("vid-count-total").textContent = files.length;
							setTimeout(updateVideoList, videoListUpdateIntervalMs);
						})
						.catch(error => {
							setTimeout(updateVideoList, videoListUpdateIntervalMs);
						});
				};
				
				// Trigerred when a video ends.
				eVideo.addEventListener("ended", () => {
					iCurrentVideo++;
					playNextVideo();
				});
				
				// Trigerred every time a video plays.
				// Used to keep the video's frame at a constant size.
				// It looks like ass otherwise since it "flickers" between 2 sizes.
				eVideo.addEventListener("playing", function() {
					eVideo.width = eVideo.offsetWidth;
					eVideo.height = eVideo.offsetHeight;
					eVideo.style.minWidth = eVideo.offsetWidth+"px";
					eVideo.style.minHeight = eVideo.offsetHeight+"px";
				});
				
				// Changes the "current video" number when moving the slider.
				eVideoSelector.oninput = function() {
					document.getElementById("vid-count-current").textContent = eVideoSelector.value;
				};
				
				// Plays the correct video once the slider is released.
				eVideoSelector.onchange = function() {
					iCurrentVideo = eVideoSelector.value;
					playNextVideo();
				};
				
				// Quick jumps
				document.getElementById('skip-minus-25').addEventListener('click', function() {
					iCurrentVideo = Math.max(0, iCurrentVideo - 25);
					playNextVideo();
				});
				document.getElementById('skip-minus-10').addEventListener('click', function() {
					iCurrentVideo = Math.max(0, iCurrentVideo - 10);
					playNextVideo();
				});
				document.getElementById('skip-minus-5').addEventListener('click', function() {
					iCurrentVideo = Math.max(0, iCurrentVideo - 5);
					playNextVideo();
				});
				document.getElementById('skip-minus-1').addEventListener('click', function() {
					iCurrentVideo = Math.max(0, iCurrentVideo - 1);
					playNextVideo();
				});
				document.getElementById('skip-plus-1').addEventListener('click', function() {
					iCurrentVideo = Math.min(files.length - 1, iCurrentVideo + 1);
					playNextVideo();
				});
				document.getElementById('skip-plus-5').addEventListener('click', function() {
					iCurrentVideo = Math.min(files.length - 1, iCurrentVideo + 5);
					playNextVideo();
				});
				document.getElementById('skip-plus-10').addEventListener('click', function() {
					iCurrentVideo = Math.min(files.length - 1, iCurrentVideo + 10);
					playNextVideo();
				});
				document.getElementById('skip-plus-25').addEventListener('click', function() {
					iCurrentVideo = Math.min(files.length - 1, iCurrentVideo + 25);
					playNextVideo();
				});
				
				// Starting up the player, the list updater loop and setting the currently played vid number.
				document.getElementById("vid-count-total").textContent = files.length;
				playNextVideo();
				setTimeout(updateVideoList, videoListUpdateIntervalMs);
			}
		});
	</script>
</body>
</html>