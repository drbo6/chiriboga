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
		<?php echo '<script src="jquery/jquery-3.2.1.min.js?' . filemtime('jquery/jquery-3.2.1.min.js') . '"></script>'; ?>
		<?php echo '<script src="cardrenderer/pixi.min.js?' . filemtime('cardrenderer/pixi.min.js') . '"></script>'; ?>
		<?php echo '<script src="cardrenderer/pixi-particles.min.js?' . filemtime('cardrenderer/pixi-particles.min.js') . '"></script>'; ?>
		<?php
		echo '<script src="cardrenderer/particlesystems.js?' . filemtime('cardrenderer/particlesystems.js') . '"></script>';
		echo '<script src="cardrenderer/cardrenderer.js?' . filemtime('cardrenderer/cardrenderer.js') . '"></script>';
		include 'cardrenderer/webfont.php';
		?>
		<?php echo '<script src="deck/lz-string.min.js?' . filemtime('deck/lz-string.min.js') . '"></script>'; ?>
		<?php echo '<script src="deck/seedrandom.min.js?' . filemtime('deck/seedrandom.min.js') . '"></script>'; ?>
		<script>
			var cardSet = []; //prepare to receive card definitions
			var setIdentifiers = []; //set identifiers
		</script>
		<script>var accessibilityMode="default";</script>
		<?php
		echo '<link rel="stylesheet" type="text/css" href="style.css?' . filemtime('style.css') . '" />';
		$jsfiles = array('init','phase', 'command', 'checks', 'mechanics', 'utility', 'config');

		// Load card sets
		$sets = ["coreset","systemgateway","systemupdate2021","midnightsun","elevation"];
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
		<script>
		// Hostile Takeover modal functions for gauntlet perk display
		var hostileTakeoverCallback = null;
		var hostileTakeoverShown = false;
		
		// Convert perk number to display name
		function getPerkName(perkNum) {
			var perkNames = {
				1: 'Additional Funds',
				2: 'Pre-Installed Neutral Ice',
				3: 'Perk 3', // Placeholder
				4: 'Boss Perk 4', // Placeholder
				5: 'Boss Perk 5', // Placeholder
				6: 'Boss Perk 6'  // Placeholder
			};
			return perkNames[perkNum] || ('Unknown Perk ' + perkNum);
		}
		
		function showHostileTakeoverModal(perks, callback) {
			// perks is an array of perk numbers to display
			if (!perks || perks.length === 0) {
				if (callback) callback();
				return;
			}
			
			hostileTakeoverCallback = callback;
			hostileTakeoverShown = true;
			
			// Build the perks display - each perk on its own line with name
			var perksHtml = '';
			for (var i = 0; i < perks.length; i++) {
				perksHtml += '<div class="hostile-takeover-perk">' + getPerkName(perks[i]) + '</div>';
			}
			
			$('#hostile-takeover-perks').html(perksHtml);
			$('#hostile-takeover-modal').css('display', 'flex');
		}
		
		function dismissHostileTakeoverModal() {
			$('#hostile-takeover-modal').css('display', 'none');
			if (hostileTakeoverCallback) {
				var cb = hostileTakeoverCallback;
				hostileTakeoverCallback = null;
				cb();
			}
		}
		
		// Function to check and show hostile takeover modal on game start
		// Returns the array of perks to show, or null if none
		function checkHostileTakeoverOnStart(gauntletState, currentOpponentIndex) {
			// currentOpponentIndex is 0-based (opponent 1 = index 0)
			// Only show modal for opponent 2+ (index 1+)
			if (currentOpponentIndex < 1) return null;
			
			// Collect all perks from previous opponents plus current opponent's own perk
			var allPerks = [];
			for (var i = 0; i <= currentOpponentIndex; i++) {
				var opponent = gauntletState.opponents[i];
				if (opponent && typeof opponent.startingPerk === 'number' && opponent.startingPerk > 0) {
					allPerks.push(opponent.startingPerk);
				}
			}
			
			return allPerks.length > 0 ? allPerks : null;
		}
		
		// Auto-check for hostile takeover when game initializes
		// This hooks into the existing flow by checking the g parameter
		function tryShowHostileTakeover(callback) {
			if (hostileTakeoverShown) {
				if (callback) callback();
				return;
			}
			
			// Get the gauntlet state from URL parameter 'g'
			var gParam = '';
			try {
				var results = new RegExp('[?&]g=([^&#]*)').exec(window.location.href);
				gParam = results ? decodeURIComponent(results[1]) : '';
			} catch(e) {}
			
			if (!gParam) {
				if (callback) callback();
				return;
			}
			
			try {
				var gauntletState = JSON.parse(LZString.decompressFromEncodedURIComponent(gParam));
				var currentOpponentIndex = gauntletState.defeated || 0;
				var perks = checkHostileTakeoverOnStart(gauntletState, currentOpponentIndex);
				
				if (perks && perks.length > 0) {
					showHostileTakeoverModal(perks, callback);
				} else {
					if (callback) callback();
				}
			} catch(e) {
				console.log('Could not parse gauntlet state for hostile takeover check:', e);
				if (callback) callback();
			}
		}
		
		// Get gauntlet state from URL parameter
		function getGauntletState() {
			var gParam = '';
			try {
				var results = new RegExp('[?&]g=([^&#]*)').exec(window.location.href);
				gParam = results ? decodeURIComponent(results[1]) : '';
			} catch(e) {}
			
			if (!gParam) return null;
			
			try {
				return JSON.parse(LZString.decompressFromEncodedURIComponent(gParam));
			} catch(e) {
				console.log('Could not parse gauntlet state:', e);
				return null;
			}
		}
		
		// Get all active perks for the current opponent
		function getActivePerks() {
			var gauntletState = getGauntletState();
			if (!gauntletState) return [];
			
			var currentOpponentIndex = gauntletState.defeated || 0;
			var activePerks = [];
			
			for (var i = 0; i <= currentOpponentIndex; i++) {
				var opponent = gauntletState.opponents[i];
				if (opponent && typeof opponent.startingPerk === 'number' && opponent.startingPerk > 0) {
					activePerks.push(opponent.startingPerk);
				}
			}
			
			return activePerks;
		}
		
		// Find all neutral ice from allowed sets
		function findNeutralIce(allowedSets) {
			var neutralIce = [];
			
			for (var cardId in cardSet) {
				var card = cardSet[cardId];
				if (!card) continue;
				if (card.cardType !== 'ice') continue;
				if (card.player !== corp) continue;
				if (card.faction !== 'Neutral') continue;
				
				// Check if card is from an allowed set
				if (allowedSets && allowedSets.length > 0) {
					var cardIdNum = parseInt(cardId);
					var isAllowed = false;
					for (var i = 0; i < allowedSets.length; i++) {
						var setCode = allowedSets[i];
						// Determine card ID range for each set
						// sg (System Gateway): 30000-30999
						// su21 (System Update 2021): 31000-31999
						// ms (Midnight Sun): 33000-33999
						// el (Elevation): 34000-34999
						if (setCode === 'sg' && cardIdNum >= 30000 && cardIdNum < 31000) isAllowed = true;
						else if (setCode === 'su21' && cardIdNum >= 31000 && cardIdNum < 32000) isAllowed = true;
						else if (setCode === 'ms' && cardIdNum >= 33000 && cardIdNum < 34000) isAllowed = true;
						else if (setCode === 'el' && cardIdNum >= 34000 && cardIdNum < 35000) isAllowed = true;
					}
					if (!isAllowed) continue;
				}
				
				neutralIce.push(parseInt(cardId));
			}
			
			return neutralIce;
		}
		
		// Apply perk 2: Pre-Installed Neutral Ice
		function applyPerk2_PreInstalledNeutralIce() {
			var gauntletState = getGauntletState();
			var allowedSets = gauntletState ? gauntletState.allowedSets : [];
			
			// Find all neutral ice from allowed sets
			var neutralIceIds = findNeutralIce(allowedSets);
			if (neutralIceIds.length === 0) {
				console.log('Perk 2: No neutral ice found in allowed sets');
				return;
			}
			
			// Pick a random neutral ice
			var randomIceId = neutralIceIds[Math.floor(Math.random() * neutralIceIds.length)];
			
			// Use InstanceCard to properly create the card (this sets isCard, setNumber, cardDefinition, etc.)
			var iceCard = InstanceCard(
				randomIceId,
				cardBackTexturesCorp,
				glowTextures,
				strengthTextures
			);
			
			if (!iceCard) {
				console.log('Perk 2: Failed to create ice card');
				return;
			}
			
			// Pick a random central server (HQ, R&D, Archives)
			var centralServers = [corp.HQ, corp.RnD, corp.archives];
			var targetServer = centralServers[Math.floor(Math.random() * centralServers.length)];
			
			// Install the ice on the server (facedown, not rezzed)
			iceCard.cardLocation = targetServer.ice;
			iceCard.faceUp = false;
			iceCard.rezzed = false;
			targetServer.ice.push(iceCard);
			
			Log('Perk: ' + iceCard.title + ' pre-installed protecting ' + ServerName(targetServer));
		}
		
		// Apply perk 1: Additional Funds (Corp gets 5 extra credits)
		function applyPerk1_AdditionalFunds() {
			GainCredits(corp, 5);
			Log('Perk: Corp gains 5 additional credits');
		}
		
		// Apply all active perks
		function applyGauntletPerks() {
			var activePerks = getActivePerks();
			if (activePerks.length === 0) return;
			
			console.log('Applying gauntlet perks:', activePerks);
			
			for (var i = 0; i < activePerks.length; i++) {
				var perk = activePerks[i];
				switch (perk) {
					case 1:
						applyPerk1_AdditionalFunds();
						break;
					case 2:
						applyPerk2_PreInstalledNeutralIce();
						break;
					// Future perks will be added here
					default:
						console.log('Unknown perk:', perk);
				}
			}
		}
		</script>
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
					   <label class="toggle-item"><input type="checkbox" id="largerhistory"> Larger history</label>
					   <label class="toggle-item"><input type="checkbox" id="debugmenu-toggle"> Debug Menu</label>
					   <div class="toggle-item" style="display:flex;align-items:center;gap:12px;justify-content:flex-start;">
						   <span style="min-width:50px;color:var(--crt-green-muted);">SPEED:</span>
						   <label style="display:flex;align-items:center;gap:4px;margin:0;">
							   <input type="checkbox" id="speed-1" onchange="debugSetSpeedPreset(1000)">
							   <span style="width:12px;text-align:center;color:var(--crt-green-muted);">1</span>
						   </label>
						   <label style="display:flex;align-items:center;gap:4px;margin:0;">
							   <input type="checkbox" id="speed-2" checked onchange="debugSetSpeedPreset(350)">
							   <span style="width:12px;text-align:center;color:var(--crt-green-muted);">2</span>
						   </label>
						   <label style="display:flex;align-items:center;gap:4px;margin:0;">
							   <input type="checkbox" id="speed-3" onchange="debugSetSpeedPreset(100)">
							   <span style="width:12px;text-align:center;color:var(--crt-green-muted);">3</span>
						   </label>
					   </div>
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
					<div class="debug-card-group">
						<label>AI Speed (ms delay)</label>
					<input type="number" id="debug-ai-speed" min="75" max="1050" step="50" value="350" style="width:100%;padding:8px;margin:5px 0;background-color:#0a0a0a;color:#33ff33;border:2px solid #147014;border-radius:3px;font-family:monospace;font-size:14px;box-sizing:border-box;text-align:center;" onchange="debugSetAISpeed()">
					<style>
						#debug-ai-speed::-webkit-outer-spin-button,
						#debug-ai-speed::-webkit-inner-spin-button {
							-webkit-appearance: none;
							appearance: none;
							background-color: #147014;
							color: #33ff33;
							border: 1px solid #0a7c0a;
							cursor: pointer;
						}
						#debug-ai-speed::-webkit-outer-spin-button:hover,
						#debug-ai-speed::-webkit-inner-spin-button:hover {
							background-color: #1a8c1a;
						}
					</style>
						<button class="button" onclick="debugSetAISpeed()">Apply Speed</button>
					</div>
					<button class="button" onclick="debugWinGame()">Win the Game</button>
					<button class="button" onclick="debugLoseGame()">Lose the Game</button>
				</div>
			</div>
		</div>
		<div id="hostile-takeover-modal" class="modal">
			<div class="solo-menu">
				<div class="solo-logo">
					<h1 class="logo-text">HOSTILE TAKEOVER</h1>
				</div>
				<div id="hostile-takeover-content">
					<p>You weakened your last opponent enough for this Corp to buy up its assets and become stronger. It starts with the following perks:</p>
					<div id="hostile-takeover-perks"></div>
				</div>
				<div class="hostile-takeover-buttons">
					<button class="button" onclick="dismissHostileTakeoverModal();">CONTINUE</button>
				</div>
			</div>
		</div>
	</body>
</html>