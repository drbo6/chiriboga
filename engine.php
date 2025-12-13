<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex">
		<title>Chiriboga</title>
		<link href="images/favicon.ico" rel="icon">
		<?php
		echo '<link rel="stylesheet" type="text/css" href="style.css?' . filemtime('style.css') . '" />';
		?> 
		<link rel="manifest" href="manifest.json">
		<script src="jquery/jquery-3.2.1.min.js"></script>
		<script src="cardrenderer/pixi.min.js"></script>
		<script src="cardrenderer/pixi-particles.min.js"></script>
		<?php
		echo '<script src="cardrenderer/particlesystems.js?' . filemtime('cardrenderer/particlesystems.js') . '"></script>';
		echo '<script src="cardrenderer/cardrenderer.js?' . filemtime('cardrenderer/cardrenderer.js') . '"></script>';
		include 'cardrenderer/webfont.php';
		?>
		<script src="deck/lz-string.min.js"></script>
		<script src="deck/seedrandom.min.js"></script>
		<script>
			var cardSet = []; //prepare to receive card definitions
			var setIdentifiers = []; //set identifiers
		</script>
		<script>var accessibilityMode="default";</script>
		<?php
		echo '<link rel="stylesheet" type="text/css" href="style.css?' . filemtime('style.css') . '" />';
		$jsfiles = array('init','phase', 'command', 'checks', 'mechanics', 'utility');
		$sets = ["systemgateway","systemupdate2021","midnightsun"];
		if (isset($_GET['sets'])) {
			$sets = explode("-",preg_replace( "/[^a-zA-Z0-9-]/", "", $_GET['sets'] )); 
		}
		foreach ($sets as $set) {
			array_push($jsfiles, 'sets/'.$set);
		}
		$jsfiles = array_merge($jsfiles, array('sets/tutorial', 'decks', 'runcalculator', 'ai_corp', 'ai_runner'));
		$maxfilemtime = 0;
		foreach ($jsfiles as $jsfile) {
			$thisfilemtime = filemtime($jsfile.'.js');
			echo '<script src="'.$jsfile.'.js?' . $thisfilemtime . '"></script>';
			if ($thisfilemtime > $maxfilemtime) {
				$maxfilemtime = $thisfilemtime;
			}
		}
		echo '<script>var versionReference=' . $maxfilemtime . ';</script>';
		?> 
	</head>

	<body id="body" onload="Init();">
		<div id="contentcontainer" class="content">
			<div class="netrunner-bg-watermark"></div> <!-- default watermark text, will be replaced -->
			<!-- ...existing code... -->
			<div id="crt-overlay"></div>
			<div id="output"></div>
			<form id="cmdform">
				<input type="submit" value="Submit">
				<span id="turnphase"></span>
				<input id="command" type="text" value="">
			</form>
		</div>
	<div id="menubar">
		<button class="menu-trigger" onclick="$('#menu').css('display','flex'); $('.fullscreen-button').show();">MENU</button>
		<button class="deck-info-button" onclick="ShowDeckInfo(); $('#help-modal').css('display','flex');"></button>
		<button class="rulebook-button" onclick="window.open('https://nullsignal.games/players/learn-to-play/', '_blank');"></button>
		<button class="debug-menu-button" style="display:none; margin-left:6px;" onclick="debugPopulateCardDropdown(); $('#debug-modal').css('display','flex');">
			<img src="images/debug.svg" alt="Debug" class="icon" style="width:32px;height:32px;vertical-align:middle;" />
		</button>
	</div>
	<div id="header"></div>
	<button class="fullscreen-button" onclick="document.getElementById('body').requestFullscreen({ navigationUI: 'hide' });"></button>
		<div id="fps"></div>
		<div id="footer"></div>
		<div id="modal" class="modal">
			<div id="modalcontent" class="modal-content"></div>
		</div>
		<div id="history-wrapper">
			<div id="history"></div>
		</div>
		<div id="loading" class="modal" style="display:flex;">
			<div class="modal-content-inactive"><h1 id="loading-text">DECKBUILDING...</h1></div>
		</div>
		<div id="menu" class="modal">
			<div id="menucontent" class="solo-menu">
				<span id="menu-close" class="menu-close" onclick="$('#menu').css('display','none');">✕</span>
				<div class="solo-logo">
					<h1 class="logo-text">NETRUNNER</h1>
					<div class="subtitle-line"><span class="subtitle-text">$0LØ MOÐ3</span></div>
				</div>
				<div class="menu-options">
					<button id="exittomenu" onclick="window.location.href='index.php';" class="button">EXIT TO MAIN MENU</button>
					<button onclick="DownloadCapturedLog();" class="button">DOWNLOAD DEBUG LOG</button>
					<select id="rewind-select" disabled class="button">
						<option value="">UNDO</option>
					</select>
				</div>
				   <div class="toggle-options">
					   <div class="toggle-grid">
						   <label class="toggle-item"><input type="checkbox" id="narration"> Narrate AI</label>
						   <label class="toggle-item"><input type="checkbox" id="slowerai"> Slower AI</label>
						   <label class="toggle-item"><input type="checkbox" id="largerhistory"> Larger history</label>
						   <label class="toggle-item"><input type="checkbox" id="debugmenu-toggle"> Debug Menu</label>
					   </div>
				   </div>
			</div>
		</div>
		<div id="help-modal" class="modal">
			<div class="solo-menu">
				<span class="menu-close" onclick="$('#help-modal').css('display','none');">✕</span>
				<div class="solo-logo">
					<h1 class="logo-text">DECK INFO</h1>
				</div>
				<div id="help-content" style="color:#33ff33; font-family:monospace; font-size:14px; text-align:left; max-height:400px; overflow-y:auto; padding:20px;">
					<p>Loading deck information...</p>
				</div>
			</div>
		</div>
		<div id="debug-modal" class="modal">
			<div class="solo-menu">
				<span class="menu-close" onclick="$('#debug-modal').css('display','none');">✕</span>
				<div class="solo-logo">
					<h1 class="logo-text">DEBUG MENU</h1>
				</div>
				<div class="menu-options">
					<button class="button" onclick="debugAddClick()">Add a Click</button>
					<button class="button" onclick="debugAddCredit()">Add a Credit</button>
					<button class="button" onclick="debugDrawCard()">Draw Another Card</button>
					<div class="debug-card-group">
						<label>Add Card to Hand</label>
						<select id="debug-card-select">
							<option value="">-- Select a card --</option>
						</select>
						<button class="button" onclick="debugAddCardToHand()">Add Selected Card</button>
					</div>
					<button class="button" onclick="debugWinGame()">Win the Game</button>
					<button class="button" onclick="debugLoseGame()">Lose the Game</button>
				</div>
			</div>
		</div>
	</body>
</html>