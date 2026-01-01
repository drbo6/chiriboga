<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Chiriboga Deck Launcher</title>
		<link href="images/favicon.ico" rel="icon">
		<?php echo '<link rel="stylesheet" href="jquery/jquery-ui.css?' . filemtime('jquery/jquery-ui.css') . '" />'; ?>
		<?php echo '<link rel="stylesheet" href="style.css?' . filemtime('style.css') . '" />'; ?>
		<link rel="manifest" href="manifest.json">
		<?php
		include 'cardrenderer/webfont.php';
		echo '<script src="jquery/jquery-3.2.1.min.js?' . filemtime('jquery/jquery-3.2.1.min.js') . '"></script>';
		echo '<script src="jquery/jquery-ui.min.js?' . filemtime('jquery/jquery-ui.min.js') . '"></script>';
		echo '<script src="jquery/textarea-helper.js?' . filemtime('jquery/textarea-helper.js') . '"></script>';
		echo '<script src="deck/lz-string.min.js?' . filemtime('deck/lz-string.min.js') . '"></script>';
		echo '<script src="deck/seedrandom.min.js?' . filemtime('deck/seedrandom.min.js') . '"></script>';
		?>
		<script>
			//create some variables so we can load the card definitions
			var runner = {};
			var corp = {};
			var cardSet = []; //prepare to receive card definitions
			var setIdentifiers = []; //set identifiers
			var cardIdToSet = {}; //map card IDs to their set codes (populated by each set file)
			//new globals for visual builder
			var deckCounts = {}; //cardId -> count
			var allCardIdsForPlayer = []; //cache of non-identity cards for current side
			var cardData = null; //loaded from carddata.json for lightbox display
		</script>

		<script>
		// Helper function to get image path with hi-res support if enabled
		function GetImagePath(imageFile) {
			var useHiRes = false;
			try {
				var savedJson = localStorage.getItem('chiriboga-settings');
				if (savedJson) {
					var savedSettings = JSON.parse(savedJson);
					if (savedSettings && typeof savedSettings.enableHiRes === 'boolean') {
						useHiRes = savedSettings.enableHiRes;
					} else if (typeof gauntletConfig !== 'undefined' && typeof gauntletConfig.enableHiRes === 'boolean') {
						useHiRes = gauntletConfig.enableHiRes;
					}
				} else if (typeof gauntletConfig !== 'undefined' && typeof gauntletConfig.enableHiRes === 'boolean') {
					useHiRes = gauntletConfig.enableHiRes;
				}
			} catch (e) { /* ignore JSON parse/localStorage errors */ }
			
			var basePath = useHiRes ? 'images/hires/' : 'images/';
			return basePath + ChangeImageFileToJPG(imageFile);
		}

		// Restore opponent accordion interactions
		(function(){
			// Lightbox for opponent identity card info only
			function ShowOpponentIdentityLightbox(identityId){
				if (!identityId || !cardSet[identityId]) return;
				
				var card = cardSet[identityId];
				var identImg = GetImagePath(card.imageFile);
				$('#lightbox-img').attr('src', identImg);
				
				// Find matching card in cardData by title
				var cardInfo = null;
				if (cardData && cardData.data) {
					for (var i = 0; i < cardData.data.length; i++) {
						if (cardData.data[i].title === card.title || cardData.data[i].stripped_title === card.title) {
							cardInfo = cardData.data[i];
							break;
						}
					}
				}
				
				// Build card text display
				if (cardInfo) {
					var infoHTML = '<div class="card-text-info">';
					infoHTML += '<h2>' + cardInfo.title + '</h2>';
					
					// Faction
					var factionDisplay = '';
					if (cardInfo.faction_code === 'nbn') {
						factionDisplay = 'NBN';
					} else if (cardInfo.faction_code === 'hb') {
						factionDisplay = 'HB';
					} else if (cardInfo.faction_code === 'neutral-corp' || cardInfo.faction_code === 'neutral-runner') {
						factionDisplay = 'Neutral';
					} else {
						var fc = cardInfo.faction_code || '';
						factionDisplay = fc.charAt(0).toUpperCase() + fc.slice(1).toLowerCase();
					}
					infoHTML += '<p class="card-faction">' + factionDisplay + '</p>';
					
					// Type line
					var typeCode = cardInfo.type_code || '';
					var typeLine = typeCode.charAt(0).toUpperCase() + typeCode.slice(1).toLowerCase();
					if (cardInfo.keywords) {
						typeLine += ': ' + cardInfo.keywords;
					}
					infoHTML += '<p class="card-type">' + typeLine + '</p>';
					
					// Card text
					if (cardInfo.text) {
						var cardText = cardInfo.text.replace(/\[([^\]]+)\]/g, function(match, word) {
							var iconName = word.toUpperCase();
							if (iconName === 'TRASH') iconName = 'TRASH_ABILITY';
							iconName = iconName.replace(/-/g, '_');
							return '<img src="images/nsg/NSG_' + iconName + '.svg" class="card-icon" alt="' + word + '">';
						});
						cardText = cardText.replace(/\\n/g, '<br>').replace(/\n/g, '<br>');
						infoHTML += '<div class="card-text">' + cardText + '</div>';
					}
					
					// Flavor text
					if (cardInfo.flavor) {
						var flavorText = cardInfo.flavor.replace(/\\n/g, '<br>').replace(/\n/g, '<br>');
						infoHTML += '<p class="card-flavor">' + flavorText + '</p>';
					}
					
					infoHTML += '</div>';
					$('#lightbox-text').html(infoHTML);
				} else {
					$('#lightbox-text').html('<div class="card-text-info"><h2>' + card.title + '</h2><p>Card data not found</p></div>');
				}
				
				$('#lightbox').addClass('active');
			}
			$(document).on('click','#opponentid .opponent-identity-img:not(.defeated)', function(){
				var identityId = $(this).data('identity-id');
				if (identityId) ShowOpponentIdentityLightbox(identityId);
			});
			$(document).on('click','#opponentid .opponent-header', function(){
				var box = $('#opponentid'); box.toggleClass('collapsed');
			});
			// Collection stat collapsible handler (no visual triangle)
			$(document).on('click','.collection-stat-wrapper .collection-stat', function(){
				$(this).closest('.collection-stat-wrapper').toggleClass('collapsed');
			});
			// Right-click handlers for sort and filter buttons
			$(document).on('contextmenu','#sortbydeck', function(e){
				e.preventDefault();
				CycleSortReverse();
				return false;
			});
			$(document).on('contextmenu','#sortdeck', function(e){
				e.preventDefault();
				CycleTypeFilterReverse();
				return false;
			});
		})();
		</script>
		<?php
		echo '<script src="utility.js?' . filemtime('utility.js') . '"></script>';
			echo '<script src="config.js?' . filemtime('config.js') . '"></script>';
		// Load card sets
		$sets = ["coreset", "midnightsun", "systemgateway","systemupdate2021","elevation"];
		foreach ($sets as $set) {
			echo '<script src="sets/'.$set.'.js?' . filemtime('sets/'.$set.'.js') . '"></script>';
		}
		
		?>
		<script>
			var json = { cards: [] };
			var opponentdeckstr = "";
			var opponentdeckimg = "";
			var uid = 0;
			var preconDecks = []; // List of precon decks loaded from files
			var deckModified = true; // true when user has edited deck so metadata (name/notes/url) should be stripped from URI
			// DRBO6: Gauntlet Mode variables
			var gauntletCardIds = []; // Set of 40 different runner cards available in gauntlet
			var gauntletCardCounts = {}; // Count of each card in gauntlet (typically 3 or 1)
			var gauntletCredits = 0; // Credits from gauntlet state
			var gauntletOpponents = []; // Array of opponent data from gauntlet state
			var shopDisplayCardIds = []; // Cards currently displayed in shop modal for lightbox cycling
			
			// Function to register a precon deck
			function registerPrecon(deck) {
				preconDecks.push(deck);
			}

			// Helper function to get image path with hi-res support if enabled
			function GetImagePath(imageFile) {
				var useHiRes = false;
				try {
					var savedJson = localStorage.getItem('chiriboga-settings');
					if (savedJson) {
						var savedSettings = JSON.parse(savedJson);
						if (savedSettings && typeof savedSettings.enableHiRes === 'boolean') {
							useHiRes = savedSettings.enableHiRes;
						} else if (typeof gauntletConfig !== 'undefined' && typeof gauntletConfig.enableHiRes === 'boolean') {
							useHiRes = gauntletConfig.enableHiRes;
						}
					} else if (typeof gauntletConfig !== 'undefined' && typeof gauntletConfig.enableHiRes === 'boolean') {
						useHiRes = gauntletConfig.enableHiRes;
					}
				} catch (e) { /* ignore JSON parse/localStorage errors */ }
				
				var basePath = useHiRes ? 'images/hires/' : 'images/';
				return basePath + ChangeImageFileToJPG(imageFile);
			}

			// Function to show gauntlet welcome modal
			function ShowGauntletWelcomeModal(gauntletLength) {
				var welcomeHtml = '<div class="solo-menu" style="display: flex; flex-direction: column; align-items: center;">';
				welcomeHtml += '<div class="solo-logo" style="width: 100%;">';
				welcomeHtml += '<h1 class="logo-text" style="color: var(--crt-red); text-shadow: 0 0 5px var(--crt-red), 0 0 15px var(--glow-red), 0 0 35px var(--glow-red-dark);">WELCOME TO<br>THE GAUNTLET</h1>';
				welcomeHtml += '</div>';
				welcomeHtml += '<div style="color: var(--crt-red); font-family: monospace; padding: 20px; text-align: center; width: 100%; max-width: 500px;">';
				welcomeHtml += '<p>In this mode, you will face ' + gauntletLength + ' randomly selected decks.</p>';
				welcomeHtml += '<p style="margin-top: 20px;">Build a deck from a randomized limited card pool and defeat them to complete the Gauntlet.</p>';
				welcomeHtml += '<p style="margin-top: 20px;">Every agenda point that you steal wins you more credits, but every agenda point that the Corp scores costs you some of those credits.</p>';
				welcomeHtml += '<p style="margin-top: 20px;">After your first game, you can no longer change your identity.</p>';							
				welcomeHtml += '<p style="margin-top: 20px;">To save your progress, simply create a bookmark in your browser.</p>';	
				welcomeHtml += '<p style="margin-top: 20px;">Start by buying card packs from the shop to build your deck. Good luck!</p>';
				welcomeHtml += '</div>';
				welcomeHtml += '<div style="display: flex; justify-content: center; margin-top: 0px; width: 100%;"><button class="button" onclick="CloseGauntletWelcomeModal();">CONTINUE</button></div>';
				welcomeHtml += '</div>';

				var modal = document.getElementById('gauntlet-welcome-modal');
				if (!modal) {
					modal = document.createElement('div');
					modal.id = 'gauntlet-welcome-modal';
					modal.className = 'modal';
					modal.style.display = 'flex';
					modal.style.zIndex = '10000';
					document.body.appendChild(modal);
				}
				
				modal.innerHTML = welcomeHtml;
				modal.style.display = 'flex';
			}

			function ShowCongratulationsModal(creditsWon, creditsWonText) {
				var congratsHtml = '<div class="solo-menu" style="display: flex; flex-direction: column; align-items: center;">';
				congratsHtml += '<div class="solo-logo" style="width: 100%;">';
				congratsHtml += '<h1 class="logo-text" style="color: var(--crt-red); text-shadow: 0 0 5px var(--crt-red), 0 0 15px var(--glow-red), 0 0 35px var(--glow-red-dark);">NICE JOB!</h1>';
				congratsHtml += '</div>';
				congratsHtml += '<div style="color: var(--crt-green); font-family: monospace; padding: 20px; text-align: left; width: 100%; max-width: 400px;">';
				
				// Parse and display the breakdown
				if (creditsWonText && creditsWonText.length > 0) {
					var lines = creditsWonText.split('\n');
					congratsHtml += '<div style="border: 1px solid var(--crt-green); padding: 15px; margin-bottom: 15px; background: rgba(0, 40, 0, 0.3);">';
					for (var i = 0; i < lines.length; i++) {
						var line = lines[i];
						var isTotal = line.indexOf('Total:') === 0;
						var isMinimum = line.indexOf('(Minimum') === 0;
						
						if (isTotal) {
							// Total line - make it stand out
							congratsHtml += '<div style="border-top: 1px solid var(--crt-green); margin-top: 10px; padding-top: 10px; font-size: 16px; font-weight: bold; color: var(--crt-green);">' + line + '</div>';
						} else if (isMinimum) {
							// Minimum credits note
							congratsHtml += '<div style="font-size: 12px; color: var(--crt-green-muted); font-style: italic;">' + line + '</div>';
						} else {
							// Regular line - check for positive/negative
							var color = 'var(--crt-green)';
							if (line.indexOf(': -') !== -1) {
								color = 'var(--crt-red)';
							} else if (line.indexOf(': +') !== -1) {
								color = 'var(--crt-green)';
							}
							congratsHtml += '<div style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 14px;">';
							var parts = line.split(': ');
							if (parts.length === 2) {
								congratsHtml += '<span style="color: var(--crt-green-muted);">' + parts[0] + '</span>';
								congratsHtml += '<span style="color: ' + color + '; font-weight: bold;">' + parts[1] + '</span>';
							} else {
								congratsHtml += '<span style="color: ' + color + ';">' + line + '</span>';
							}
							congratsHtml += '</div>';
						}
					}
					congratsHtml += '</div>';
				} else {
					// Fallback if no breakdown text
					congratsHtml += '<p style="font-size: 18px; font-weight: bold; text-align: center;">You have acquired ' + creditsWon + ' credits</p>';
				}
				
				congratsHtml += '</div>';
				congratsHtml += '<div style="display: flex; justify-content: center; margin-top: 0px; width: 100%;"><button class="button" onclick="CloseCongratulationsModal();">CONTINUE</button></div>';
				congratsHtml += '</div>';

				var modal = document.getElementById('gauntlet-congratulations-modal');
				if (!modal) {
					modal = document.createElement('div');
					modal.id = 'gauntlet-congratulations-modal';
					modal.className = 'modal';
					modal.style.display = 'flex';
					modal.style.zIndex = '10000';
					document.body.appendChild(modal);
				}
				
				modal.innerHTML = congratsHtml;
				modal.style.display = 'flex';
			}

			// Function to close the congratulations modal and add credits
			function CloseCongratulationsModal() {
				var modal = document.getElementById('gauntlet-congratulations-modal');
				if (modal) {
					modal.style.display = 'none';
				}
			}

			// Function to close the welcome modal
			function CloseGauntletWelcomeModal() {
				var modal = document.getElementById('gauntlet-welcome-modal');
				if (modal) {
					modal.style.display = 'none';
				}
			}

			// Function to show select opponent modal
			function ShowSelectOpponentModal() {
				if (!gauntletOpponents || gauntletOpponents.length === 0) {
					// No gauntlet opponents, just launch directly
					var launchHref = $('#launch').prop('href');
					if (launchHref) window.location.href = launchHref;
					return;
				}
				
				var modalHtml = '<div class="solo-menu" style="display: flex; flex-direction: column; align-items: center;">';
				modalHtml += '<div class="solo-logo" style="width: 100%;">';
				modalHtml += '<h1 class="logo-text" style="color: var(--crt-red); text-shadow: 0 0 5px var(--crt-red), 0 0 15px var(--glow-red), 0 0 35px var(--glow-red-dark); font-size: 28px;">SELECT NEXT OPPONENT</h1>';
				modalHtml += '</div>';
				modalHtml += '<div class="select-opponent-gallery" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; padding: 20px; max-width: 500px;">';
				
				for (var i = 0; i < gauntletOpponents.length; i++) {
					var opp = gauntletOpponents[i];
					var oppIdentityId = opp.identity;
					var oppImgSrc = 'images/glow_outline.png';
					var oppName = 'Unknown';
					if (cardSet[oppIdentityId]) {
						if (cardSet[oppIdentityId].imageFile) {
							oppImgSrc = GetImagePath(cardSet[oppIdentityId].imageFile);
						}
						oppName = cardSet[oppIdentityId].title || 'Unknown';
					}
					
					var isDefeated = opp.hasbeendefeated === true;
					var defeatedClass = isDefeated ? ' defeated' : '';
					var clickHandler = isDefeated ? '' : ' onclick="SelectOpponent(' + i + ');"';
					var cursorStyle = isDefeated ? 'cursor: default;' : 'cursor: pointer;';
					
					modalHtml += '<div class="select-opponent-item' + defeatedClass + '" style="display: flex; flex-direction: column; align-items: center; ' + cursorStyle + '"' + clickHandler + '>';
					modalHtml += '<img class="select-opponent-img' + defeatedClass + '" src="' + oppImgSrc + '" style="width: 90px; height: auto; border-radius: 6px; transition: transform 0.2s ease, box-shadow 0.2s ease;"/>';
					modalHtml += '<span class="select-opponent-name" style="margin-top: 6px; font-size: 11px; color: var(--crt-green); text-align: center; max-width: 90px; word-wrap: break-word; line-height: 1.2;">' + oppName + '</span>';
					modalHtml += '</div>';
				}
				
				modalHtml += '</div>';
				modalHtml += '<div style="display: flex; justify-content: center; margin-top: 10px; width: 100%;">';
				modalHtml += '<button class="button" onclick="CloseSelectOpponentModal();">CANCEL</button>';
				modalHtml += '</div>';
				modalHtml += '</div>';
				
				var modal = document.getElementById('select-opponent-modal');
				if (!modal) {
					modal = document.createElement('div');
					modal.id = 'select-opponent-modal';
					modal.className = 'modal';
					modal.style.display = 'flex';
					modal.style.zIndex = '10000';
					document.body.appendChild(modal);
				}
				
				modal.innerHTML = modalHtml;
				modal.style.display = 'flex';
			}
			
			// Function to close the select opponent modal
			function CloseSelectOpponentModal() {
				var modal = document.getElementById('select-opponent-modal');
				if (modal) {
					modal.style.display = 'none';
				}
			}
			
			// Function to select an opponent and launch the game
			function SelectOpponent(opponentIndex) {
				if (!gauntletOpponents || opponentIndex < 0 || opponentIndex >= gauntletOpponents.length) return;
				
				var selectedOpp = gauntletOpponents[opponentIndex];
				if (selectedOpp.hasbeendefeated) return; // Can't select defeated opponent
				
				// Build the opponent deck object
				var oppDeckObj = {
					identity: selectedOpp.identity,
					cards: selectedOpp.cards || []
				};
				
				// Compress the opponent deck
				var oppCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(oppDeckObj));
				
				// Build the player deck
				var deckForUri;
				if (deckModified) {
					deckForUri = { identity: json.identity, cards: json.cards.slice() };
				} else {
					deckForUri = {};
					for (var k in json) { if (Object.prototype.hasOwnProperty.call(json,k)) deckForUri[k] = json[k]; }
				}
				var playerCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(deckForUri));
				
				// Update gauntlet state with selected opponent index
				var gauntletParam = URIParameter("g");
				var updatedGauntletParam = gauntletParam;
				
				if (gauntletParam !== "") {
					try {
						var gauntletState = JSON.parse(LZString.decompressFromEncodedURIComponent(gauntletParam));
						gauntletState.shopPurchaseCount = shopPurchaseCount;
						gauntletState.credits = gauntletCredits;
						gauntletState.subset = gauntletCardCounts;
						gauntletState.creditsWon = 0;
						gauntletState.currentOpponentIndex = opponentIndex; // Track which opponent was selected
						updatedGauntletParam = LZString.compressToEncodedURIComponent(JSON.stringify(gauntletState));
					} catch (e) {
						console.error("Failed to update gauntlet state:", e);
					}
				}
				
				// Build launch address
				var launchAddress = "engine.php?p=r&c=" + oppCompressed + "&r=" + playerCompressed;
				if (updatedGauntletParam !== "") {
					launchAddress += "&g=" + updatedGauntletParam;
				}
				
				// Close modal and navigate
				CloseSelectOpponentModal();
				window.location.href = launchAddress;
			}

			// Function to check if there are cards to sell
			function HasCardsToSell() {
				for (var cardId in gauntletCardCounts) {
					if (gauntletCardCounts[cardId] > 3) {
						return true;
					}
				}
				return false;
			}

			// Function to refresh the pack buttons in the modal
			function RefreshShopPackButtons() {
				var modal = document.getElementById('buy-cards-modal');
				if (!modal) return; // Modal not open
				
				// Find the buttons container
				var buttonsDiv = document.getElementById('shop-buttons');
				if (!buttonsDiv) return;
				
				// Rebuild pack button HTML
				var newButtonsHtml = '';
				
				// SELL ALL EXTRA CARDS button - disable if no cards to sell
				var hasCards = HasCardsToSell();
				var sellDisabled = hasCards ? '' : ' disabled';
				var sellStyle = hasCards ? 'width: 100%;' : 'width: 100%; opacity: 0.6; border-color: var(--border-red-dark); color: var(--crt-red-muted); cursor: default; background-color: rgba(12,24,12,0.7) !important; pointer-events: none;';
				newButtonsHtml += '<button class="button" onclick="SellExtraCards();"' + sellDisabled + ' style="' + sellStyle + '">SELL ALL EXTRA CARDS</button>';
				
				// Add buttons for selected packs
				for (var i = 0; i < selectedShopPacks.length; i++) {
					var pack = selectedShopPacks[i];
					var canAfford = gauntletCredits >= pack.cost;
					var packDisabled = canAfford ? '' : ' disabled';
					var packStyle = canAfford ? 'width: 100%;' : 'width: 100%; opacity: 0.6; border-color: var(--border-red-dark); color: var(--crt-red-muted); cursor: default; background-color: rgba(12,24,12,0.7) !important; pointer-events: none;';
					var iconFilter = canAfford ? 'invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg)' : 'brightness(0) saturate(100%) invert(0.6) sepia(80%) hue-rotate(8deg) saturate(0.7)';
					newButtonsHtml += '<button class="button" onclick="BuyCardPack(' + i + ');"' + packDisabled + ' style="' + packStyle + '">BUY ' + pack.name.toUpperCase() + ': ' + pack.cost + '<img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="margin-left: 2px; margin-bottom: 2px; height: 16px; display: inline-block; vertical-align: sub; filter: ' + iconFilter + ';"></button>';
				}
				
				newButtonsHtml += '<button class="button" onclick="CloseBuyCardsModal();" style="width: 100%;">CLOSE</button>';
				
				buttonsDiv.innerHTML = newButtonsHtml;
			}

			// Function to show the Buy/Sell Cards modal
			function ShowBuyCardsModal() {
				// Packs are already selected on page load
				var buycardsHtml = '<div class="solo-menu" style="display: flex; flex-direction: column; align-items: center; width: 600px;">';
				buycardsHtml += '<h1 class="logo-text" style="color: var(--crt-red); text-shadow: 0 0 5px var(--crt-red), 0 0 15px var(--glow-red), 0 0 35px var(--glow-red-dark); margin: 20px 0;">AESOP\'S PAWN SHOP</h1>';
				buycardsHtml += '<div style="color: var(--crt-red); font-family: monospace; padding: 20px; text-align: center; width: 100%;">';
				buycardsHtml += '<p>Current Credits: <span id="shop-credits">' + gauntletCredits + '</span><img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="margin-left: 0px; margin-bottom: 2px; height: 16px; display: inline-block; vertical-align: sub; filter: invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg);"></p>';
				buycardsHtml += '</div>';
				buycardsHtml += '<div id="shop-buttons" style="display: flex; flex-direction: column; justify-content: center; gap: 10px; width: 100%; padding: 20px; min-height: 200px;">';
				
				// SELL ALL EXTRA CARDS button - disable if no cards to sell
				var hasCards = HasCardsToSell();
				var sellDisabled = hasCards ? '' : ' disabled';
				var sellStyle = hasCards ? 'width: 100%;' : 'width: 100%; opacity: 0.6; border-color: var(--border-red-dark); color: var(--crt-red-muted); cursor: default; background-color: rgba(12,24,12,0.7) !important; pointer-events: none;';
				buycardsHtml += '<button class="button" onclick="SellExtraCards();"' + sellDisabled + ' style="' + sellStyle + '">SELL ALL EXTRA CARDS</button>';
				
				// Add buttons for selected packs
				for (var i = 0; i < selectedShopPacks.length; i++) {
					var pack = selectedShopPacks[i];
					var canAfford = gauntletCredits >= pack.cost;
					var packDisabled = canAfford ? '' : ' disabled';
					var packStyle = canAfford ? 'width: 100%;' : 'width: 100%; opacity: 0.6; border-color: var(--border-red-dark); color: var(--crt-red-muted); cursor: default; background-color: rgba(12,24,12,0.7) !important; pointer-events: none;';
					var iconFilter = canAfford ? 'invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg)' : 'brightness(0) saturate(100%) invert(0.6) sepia(80%) hue-rotate(8deg) saturate(0.7)';
					buycardsHtml += '<button class="button" onclick="BuyCardPack(' + i + ');"' + packDisabled + ' style="' + packStyle + '">BUY ' + pack.name.toUpperCase() + ': ' + pack.cost + '<img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="margin-left: 2px; margin-bottom: 2px; height: 16px; display: inline-block; vertical-align: sub; filter: ' + iconFilter + ';"></button>';
				}
				
				buycardsHtml += '<button class="button" onclick="CloseBuyCardsModal();" style="width: 100%;">CLOSE</button>';
				buycardsHtml += '</div>';
				buycardsHtml += '</div>';

				var modal = document.getElementById('buy-cards-modal');
				if (!modal) {
					modal = document.createElement('div');
					modal.id = 'buy-cards-modal';
					modal.className = 'modal';
					modal.style.display = 'flex';
					modal.style.zIndex = '10000';
					document.body.appendChild(modal);
				}
				
				modal.innerHTML = buycardsHtml;
				modal.style.display = 'flex';
			}

			// Function to close the Buy/Sell Cards modal
			function CloseBuyCardsModal() {
				var modal = document.getElementById('buy-cards-modal');
				if (modal) {
					modal.style.display = 'none';
				}
			}

			// Function to go back to shop from purchase view
			function BackToShop() {
				RefreshShopPackButtons();
			}

			// Function to sell all extra cards (cards with more than 3 copies)
			function SellExtraCards() {
				var cardsToRemove = []; // Array of {cardId, count}
				var totalCredits = 0;
				
				// Find all cards with more than 3 copies
				for (var cardId in gauntletCardCounts) {
					if (gauntletCardCounts[cardId] > 3) {
						var excess = gauntletCardCounts[cardId] - 3;
						cardsToRemove.push({
							cardId: parseInt(cardId),
							excess: excess
						});
						totalCredits += excess;
					}
				}
				
				if (cardsToRemove.length === 0) {
					var buttonsDiv = document.getElementById('shop-buttons');
					buttonsDiv.innerHTML = '<p style="color: var(--crt-red);">No cards with more than 3 copies available to sell.</p><button class="button" onclick="BackToShop();" style="width: 100%; margin-top: 20px;">BACK TO SHOP</button>';
					return;
				}
				
				// Sort by card title alphabetically
				cardsToRemove.sort(function(a, b) {
					var titleA = (cardSet[a.cardId].title || '').toLowerCase();
					var titleB = (cardSet[b.cardId].title || '').toLowerCase();
					return titleA.localeCompare(titleB);
				});
				
				// Build display list
				var listHtml = '<div style="color: var(--crt-red); font-family: monospace; text-align: center; display: inline-block; margin: 0;">';
				
				for (var i = 0; i < cardsToRemove.length; i++) {
					var item = cardsToRemove[i];
					var cardTitle = cardSet[item.cardId].title || 'Unknown Card';
					listHtml += '<p style="margin: 5px 0;">' + item.excess + 'x ' + cardTitle + '</p>';
				}
				
				listHtml += '<p style="margin-top: 20px; color: var(--glow-red);"><strong>Sold for ' + totalCredits + ' <img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="margin-left: 0px; height: 16px; display: inline-block; vertical-align: sub; filter: invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg);"></strong></p>';
				listHtml += '</div>';
				
				// Find the buttons container and replace with sale info and BACK TO SHOP button
				var buttonsDiv = document.getElementById('shop-buttons');
				buttonsDiv.innerHTML = listHtml + '<button class="button" onclick="BackToShop();" style="width: 100%; margin-top: 20px;">BACK TO SHOP</button>';
				
				// Remove excess cards from gauntletCardCounts
				for (var i = 0; i < cardsToRemove.length; i++) {
					gauntletCardCounts[cardsToRemove[i].cardId] = 3;
				}
				
				// Add credits
				gauntletCredits += totalCredits;
				
				// Update the credits display in the modal
				document.getElementById('shop-credits').innerHTML = gauntletCredits;
				
				// Update the UI
				UpdateCardCountsUI();
				RenderAllCardsList();
				Parse(); // Update deck stats including credits display
				
				// Reapply current sort and filters
				SortCardsBySort();
				ApplyTypeFilter();
				if (showingOnlySelected) ApplyFilter();
			}

			// Global variables for shop
			var gauntletSeed = '';
			var gauntletAllowedSets = [];
			var selectedShopPacks = [];
			var shopPurchaseCount = 0; // Track number of purchases for deterministic but varying pack selection

			// Function to select unique random packs
			function SelectRandomShopPacks() {
				if (!gauntletConfig || !gauntletConfig.cardPacks) return [];
				
				// When shopPurchaseCount is 0, always return the three faction packs (Anarch, Criminal, Shaper)
				if (shopPurchaseCount === 0) {
					var factionPacks = [];
					var factionNames = ['Anarch', 'Criminal', 'Shaper'];
					for (var f = 0; f < factionNames.length; f++) {
						for (var p = 0; p < gauntletConfig.cardPacks.length; p++) {
							var pack = gauntletConfig.cardPacks[p];
							if (pack.name && pack.name.indexOf(factionNames[f]) !== -1) {
								factionPacks.push(pack);
								break;
							}
						}
					}
					// If we found all three faction packs, return them
					if (factionPacks.length === 3) {
						return factionPacks;
					}
					// Fallback: if faction packs not found by name, continue with random selection
				}
				
				// Seed the RNG using the gauntlet seed with purchase count for deterministic but varying results
				if (gauntletSeed && gauntletSeed.length > 0 && typeof Math.seedrandom === 'function') {
					var packSelectionSeed = gauntletSeed + '_packs_' + shopPurchaseCount;
					Math.seedrandom(packSelectionSeed);
				}
				
				var packIndices = [];
				var totalPacks = gauntletConfig.cardPacks.length;
				
				// Select 3 unique random pack indices
				while (packIndices.length < 3 && packIndices.length < totalPacks) {
					var randomIndex = Math.floor(Math.random() * totalPacks);
					if (packIndices.indexOf(randomIndex) === -1) {
						packIndices.push(randomIndex);
					}
				}
				
				// Return the actual pack objects
				var packs = [];
				for (var i = 0; i < packIndices.length; i++) {
					packs.push(gauntletConfig.cardPacks[packIndices[i]]);
				}
				return packs;
			}

			// Function to generate cards from a pack based on factors
			function GeneratePackCards(packConfig) {
				if (!packConfig) return [];
				
				var cards = [];
				
				// Build weighted pool of all eligible cards
				// Each card's weight = typeWeight * factionWeight
				var weightedPool = []; // Array of {cardId, weight}
				
				for (var cardId = 0; cardId < cardSet.length; cardId++) {
					var card = cardSet[cardId];
					if (!card) continue;
					if (card.player !== runner) continue;
					if (card.cardType === 'identity') continue;
					
					// Check allowed sets
					if (gauntletAllowedSets && gauntletAllowedSets.length > 0) {
						var cardSetCode = cardIdToSet[cardId] || '';
						if (gauntletAllowedSets.indexOf(cardSetCode) === -1) continue;
					}
					
					// Get type weight
					var cardType = (card.cardType || '').toLowerCase();
					var typeWeight = packConfig.typeFactors[cardType] || 0;
					if (typeWeight <= 0) continue;
					
					// Get faction weight
					var cardFaction = (card.faction || '').toLowerCase();
					var factionKey = null;
					if (cardFaction === 'anarch' || cardFaction === 'anarch-runner') factionKey = 'anarch';
					else if (cardFaction === 'criminal' || cardFaction === 'criminal-runner') factionKey = 'criminal';
					else if (cardFaction === 'shaper' || cardFaction === 'shaper-runner') factionKey = 'shaper';
					else if (cardFaction === 'neutral' || cardFaction === 'neutral-runner') factionKey = 'neutral';
					
					var factionWeight = factionKey ? (packConfig.factionFactors[factionKey] || 0) : 0;
					if (factionWeight <= 0) continue;
					
					// Combined weight
					var combinedWeight = typeWeight * factionWeight;
					weightedPool.push({ cardId: cardId, weight: combinedWeight });
				}
				
				if (weightedPool.length === 0) {
					console.warn("No eligible cards for pack:", packConfig.name);
					return [];
				}
				
				// Calculate total weight
				var totalWeight = 0;
				for (var i = 0; i < weightedPool.length; i++) {
					totalWeight += weightedPool[i].weight;
				}
				
				// Generate cardQuantity cards using weighted selection
				for (var cardIndex = 0; cardIndex < packConfig.cardQuantity; cardIndex++) {
					var roll = Math.random() * totalWeight;
					var accum = 0;
					
					for (var i = 0; i < weightedPool.length; i++) {
						accum += weightedPool[i].weight;
						if (roll <= accum) {
							cards.push(weightedPool[i].cardId);
							break;
						}
					}
				}
				
				return cards;
			}

			// Function to buy a card pack
			function BuyCardPack(packIndex) {
				if (packIndex < 0 || packIndex >= selectedShopPacks.length) return;
				
				var pack = selectedShopPacks[packIndex];
				if (!pack) return;
				
				// Check if player has enough credits
				if (gauntletCredits < pack.cost) {
					alert('Not enough credits! You have ' + gauntletCredits + ' but this pack costs ' + pack.cost + '.');
					return;
				}
				
				// Generate cards from pack
				var cardsGenerated = GeneratePackCards(pack);
				
				// Add cards to deck
				for (var i = 0; i < cardsGenerated.length; i++) {
					var cardId = cardsGenerated[i];
					// Validate card ID
					if (cardId < 0 || cardId >= cardSet.length || !cardSet[cardId]) {
						console.error("Invalid card ID generated:", cardId);
						continue;
					}
					if (gauntletCardCounts[cardId]) {
						gauntletCardCounts[cardId]++;
					} else {
						gauntletCardCounts[cardId] = 1;
						// Add new card to the gauntletCardIds subset
						gauntletCardIds.push(cardId);
					}
				}
				
				// Deduct credits
				gauntletCredits -= pack.cost;
				
				// Build card list for display, counting duplicates
				var cardCounts = {};
				for (var i = 0; i < cardsGenerated.length; i++) {
					var cardId = cardsGenerated[i];
					if (cardCounts[cardId]) {
						cardCounts[cardId]++;
					} else {
						cardCounts[cardId] = 1;
					}
				}
				
				// Sort cards by title alphabetically
				var cardIds = Object.keys(cardCounts).map(function(id) { return parseInt(id); });
				cardIds.sort(function(a, b) {
					var titleA = (cardSet[a].title || '').toLowerCase();
					var titleB = (cardSet[b].title || '').toLowerCase();
					return titleA.localeCompare(titleB);
				});
				
				// Build display with card thumbnails
				var listHtml = '<div style="text-align: center;">';
				listHtml += '<p style="margin: 5px 0; color: var(--glow-red); font-weight: bold; font-size: 14px;">Added from ' + pack.name + ':</p>';
				listHtml += '<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin: 15px 0;">';
				
				// Reset shop display cards
				shopDisplayCardIds = [];
				
				// Sort the generated cards for consistent display
				var sortedCardsGenerated = cardsGenerated.slice().sort(function(a, b) {
					var titleA = (cardSet[a].title || '').toLowerCase();
					var titleB = (cardSet[b].title || '').toLowerCase();
					return titleA.localeCompare(titleB);
				});
				
				// Display each card copy individually
				for (var i = 0; i < sortedCardsGenerated.length; i++) {
					var cardId = sortedCardsGenerated[i];
					shopDisplayCardIds.push(cardId);
					var cardTitle = cardSet[cardId].title || 'Unknown Card';
					var imgSrc = GetImagePath(cardSet[cardId].imageFile);
					
					listHtml += '<div style="position: relative; text-align: center; cursor: pointer;" onclick="ShowLightbox(' + cardId + ');">';
					listHtml += '<img src="' + imgSrc + '" alt="' + cardTitle + '" style="width: 120px; height: auto; border: 2px solid var(--glow-green); border-radius: 4px; transition: transform 0.2s; opacity: 0.9;" onmouseover="this.style.transform=\'scale(1.05)\'; this.style.opacity=\'1\';" onmouseout="this.style.transform=\'scale(1)\'; this.style.opacity=\'0.9\';">';
					listHtml += '</div>';
				}
				
				listHtml += '</div>';
				listHtml += '<p style="margin-top: 15px; color: var(--glow-red);"><strong>Purchased for ' + pack.cost + ' <img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="margin-left: 0px; height: 16px; display: inline-block; vertical-align: sub; filter: invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg);"></strong></p>';
				listHtml += '</div>';
				
				// Find the buttons container and replace with purchase info and BACK TO SHOP button
				var buttonsDiv = document.getElementById('shop-buttons');
				buttonsDiv.innerHTML = listHtml + '<button class="button" onclick="BackToShop();" style="width: 100%; margin-top: 20px;">BACK TO SHOP</button>';
				
				// Update the credits display in the modal
				document.getElementById('shop-credits').innerHTML = gauntletCredits;
				
				// Re-randomize the three shop packs after purchase (do this BEFORE Parse so it gets saved)
				shopPurchaseCount++;
				selectedShopPacks = SelectRandomShopPacks();
				console.log("Shop packs re-randomized (purchase #" + shopPurchaseCount + "):", selectedShopPacks.map(function(p) { return p.name; }));
				
				// Update the UI
				UpdateCardCountsUI();
				RenderAllCardsList();
				Parse(); // Update deck stats including credits display - this saves shopPurchaseCount to URL
				
				// Reapply current sort and filters
				SortCardsBySort();
				ApplyTypeFilter();
				if (showingOnlySelected) ApplyFilter();
				
				// Run pool verification after buying a pack
				VerifyCardPool();
			}
		</script>
		<?php
		// Load preconstructed decks
		$preconDir = 'precons';
		if (is_dir($preconDir)) {
			$preconFiles = glob($preconDir . '/*.js');
			foreach ($preconFiles as $preconFile) {
				echo '<script src="' . $preconFile . '?' . filemtime($preconFile) . '"></script>';
			}
		}
		?>
		<script>
			// Map faction names to unicode icons for displaying in identity dropdown
			function GetFactionIcon(faction) {
				if (!faction) return '⚪ ';
				var f = faction.toLowerCase().replace(/[^a-z]/g,'');
				switch(f) {
					case 'anarch': return '🟠 ';
					case 'criminal': return '🔵 ';
					case 'shaper': return '🟢 ';
					case 'haasbioroid': return '🟣 ';
					case 'jinteki': return '🔴 ';
					case 'weylandconsortium': return '🟢 ';
					case 'nbn': return '🟡 ';
					case 'neutral': return '⚪ ';
					default: return '⚪ ';
				}
			}

			// DRBO6: Gauntlet Mode - Build playerIdentities and populate dropdown after card sets load
			function PopulateIdentityDropdown() {
				// Rebuild playerIdentities for current deckPlayer
				playerIdentities = [];
				for (var i=0; i<cardSet.length; i++) {
					if (typeof cardSet[i] != 'undefined' &&  typeof cardSet[i].faction != 'undefined') {
						if (cardSet[i].cardType == 'identity') {
							if (deckPlayer == cardSet[i].player) {
								// Filter by allowed sets if configured
								if (gauntletAllowedSets && gauntletAllowedSets.length > 0) {
									var identitySetCode = cardIdToSet[i] || '';
									if (gauntletAllowedSets.indexOf(identitySetCode) === -1) {
										continue; // Skip identities not in allowed sets
									}
								}
								playerIdentities.push(i);
							}
						}
					}
				}
				
				// Sort identities alphabetically by display title
				playerIdentities.sort(function(a, b) {
					var fullTitleA = cardSet[a].title || '';
					var fullTitleB = cardSet[b].title || '';
					var shortTitleA = fullTitleA;
					var shortTitleB = fullTitleB;
					
					if (deckPlayer === corp) {
						var colonIdxA = fullTitleA.indexOf(': ');
						if (colonIdxA > -1) shortTitleA = fullTitleA.substring(colonIdxA + 2).trim();
						var colonIdxB = fullTitleB.indexOf(': ');
						if (colonIdxB > -1) shortTitleB = fullTitleB.substring(colonIdxB + 2).trim();
					} else {
						// Runner
						if (fullTitleA.indexOf(':') > -1) shortTitleA = fullTitleA.split(':')[0].trim();
						if (fullTitleB.indexOf(':') > -1) shortTitleB = fullTitleB.split(':')[0].trim();
					}
					
					return shortTitleA.localeCompare(shortTitleB);
				});
				
				// Clear and populate the dropdown
				$("#identityselect").empty();
				for (var i = 0; i < playerIdentities.length; i++) {
					var fullTitle = cardSet[playerIdentities[i]].title || '';
					var shortTitle = fullTitle; // fallback
					// Determine shortening based on side: runner before colon, corp after colon+space
					if (deckPlayer === corp) {
						var colonIdx = fullTitle.indexOf(': ');
						if (colonIdx > -1) shortTitle = fullTitle.substring(colonIdx + 2).trim();
					} else {
						// Runner
						if (fullTitle.indexOf(':') > -1) shortTitle = fullTitle.split(':')[0].trim();
					}
					$("#identityselect").append(
					  "<option value=" +
						playerIdentities[i] +
						">" +
						GetFactionIcon(cardSet[playerIdentities[i]].faction) + shortTitle +
						"</option>\n"
					);
				}
			}

			// Load card data from JSON
			$.getJSON('carddata/carddata.json?<?php echo filemtime('carddata/carddata.json'); ?>', function(data) {
				cardData = data;
				// Build quick lookup by code for import
				try {
					window.cardCodeLookup = {};
					if (cardData && cardData.data) {
						for (var i = 0; i < cardData.data.length; i++) {
							var c = cardData.data[i];
							window.cardCodeLookup[c.code] = { title: c.title || c.stripped_title, type_code: c.type_code, side_code: c.side_code };
						}
					}
				} catch(e) { /* ignore */ }
				// Sort by name
				preconDecks.sort(function(a, b) {
					return a.name.localeCompare(b.name);
				});
				// Helper: populate precon dropdown only for current identity
				// Expose dropdown population globally so identity change handler can call it
				window.PopulatePreconDropdownForIdentity = function(identityId) {
					var dropdown = $('#preconselect');
					dropdown.empty();
					dropdown.append('<option value="-1">Load Precon Deck</option>');
					for (var i = 0; i < preconDecks.length; i++) {
						var deck = preconDecks[i];
						if (String(identityId) === String(deck.identity)) {
							dropdown.append('<option value="' + i + '">' + deck.name + '</option>');
						}
					}
				}
				// Initial populate: use current identity if selected in dropdown, else placeholder
				var selectedIdentity = $('#identityselect').val();
				if (selectedIdentity) {
					window.PopulatePreconDropdownForIdentity(selectedIdentity);
				} else if (json && json.identity) {
					window.PopulatePreconDropdownForIdentity(json.identity);
				} else {
					$('#preconselect').empty().append('<option value="-1">Load Precon Deck</option>');
				}

				// DRBO6: Gauntlet Mode - Load or generate gauntlet state
				function LoadOrGenerateGauntletState() {
					var gauntletParam = URIParameter("g");
					
					// Build cardIdToSet mapping from cardData.json pack_code
					// This must happen before identity filtering
					var packCodeToSet = {
						'core': 'core',
						'sg': 'sg',
						'su21': 'su21',
						'ms': 'ms',
						'elev': 'elev'
					};
					
					if (cardData && cardData.data) {
						for (var i = 0; i < cardData.data.length; i++) {
							var c = cardData.data[i];
							var cardId = c.code;
							// Only map if card exists in cardSet
							if (cardSet[cardId]) {
								var packCode = c.pack_code || '';
								var setCode = packCodeToSet[packCode] || packCode;
								cardIdToSet[cardId] = setCode;
							}
						}
					}
					
					// Fallback: Map cards by ID range for cards not in cardData.json
					for (var cardId in cardSet) {
						if (!cardSet[cardId]) continue;
						if (cardIdToSet[cardId]) continue; // Already mapped
						var cardIdInt = parseInt(cardId);
						var cardIdStr = String(cardId);
						
						// Core Set uses 1xxx range (1000-1999)
						if (cardIdInt >= 1000 && cardIdInt <= 1999) {
							cardIdToSet[cardId] = 'core';
						} else {
							var prefix = cardIdStr.substring(0, 2);
							var idRangeMap = {
								'30': 'sg',      // System Gateway (30000-30999)
								'31': 'su21',    // System Update 2021 (31000-31999)
								'33': 'ms',      // Midnight Sun (33000-33999)
								'35': 'elev'     // Elevation (35000-35999)
							};
							if (idRangeMap[prefix]) {
								cardIdToSet[cardId] = idRangeMap[prefix];
							}
						}
					}
					
					if (gauntletParam && gauntletParam !== "") {
						// Load gauntlet state from parameter
						try {
							var gauntletState = JSON.parse(LZString.decompressFromEncodedURIComponent(gauntletParam));
							
							// Extract allowed sets from gauntlet state (must happen before identity dropdown population)
							gauntletAllowedSets = gauntletState.allowedSets || [];
							
							if (gauntletState && gauntletState.subset) {
								gauntletCardCounts = {};
								// Rebuild gauntletCardIds from counts and validate card IDs
								gauntletCardIds = [];
								for (var cardId in gauntletState.subset) {
									var cardIdInt = parseInt(cardId);
									// Validate that this card exists in cardSet
									if (cardIdInt >= 0 && cardIdInt < cardSet.length && cardSet[cardIdInt]) {
										var qty = gauntletState.subset[cardId];
										gauntletCardCounts[cardIdInt] = qty;
										for (var i = 0; i < qty; i++) {
											gauntletCardIds.push(cardIdInt);
										}
									} else {
										console.warn("Invalid card ID in gauntlet subset:", cardId, "- skipping");
									}
								}
							}
						} catch(e) {
							console.error("Failed to parse gauntlet state:", e);
							GenerateGauntletCardSet();
						}
					} else {
						// Generate the gauntlet card set now that cardData is loaded
						GenerateGauntletCardSet();
					}
				}
				
				// This must happen AFTER cardData is loaded so filtering works correctly
				function GenerateGauntletCardSet() {
					gauntletCardIds = [];
					gauntletCardCounts = {};
					
					// Helper function to check if a card matches subtype requirements
					function CardMatchesRequirement(cardId, matchSubtypes, excludeSubtypes) {
						if (!cardSet[cardId] || !cardSet[cardId].subTypes) return false;
						
						var cardSubtypes = cardSet[cardId].subTypes || [];
						
						// Check exclude list first
						if (excludeSubtypes && excludeSubtypes.length > 0) {
							for (var i = 0; i < excludeSubtypes.length; i++) {
								if (cardSubtypes.indexOf(excludeSubtypes[i]) !== -1) {
									return false; // Card has excluded subtype
								}
							}
						}
						
						// If no match requirements, card passes (not excluded)
						if (!matchSubtypes || matchSubtypes.length === 0) return true;
						
						// Check if card has all required subtypes
						for (var i = 0; i < matchSubtypes.length; i++) {
							if (cardSubtypes.indexOf(matchSubtypes[i]) === -1) {
								return false; // Card missing a required subtype
							}
						}
						return true;
					}
					
					// Add fixed cards first
					if (gauntletConfig && gauntletConfig.fixedCards) {
						for (var i = 0; i < gauntletConfig.fixedCards.length; i++) {
							var fixedCard = gauntletConfig.fixedCards[i];
							var cardId = fixedCard.id;
							var quantity = fixedCard.quantity || 1;
							gauntletCardCounts[cardId] = (gauntletCardCounts[cardId] || 0) + quantity;
							
							for (var j = 0; j < quantity; j++) {
								gauntletCardIds.push(cardId);
							}
						}
					}
					
					// Add random cards based on config requirements
					if (gauntletConfig && gauntletConfig.randomCardRequirements) {
						for (var req = 0; req < gauntletConfig.randomCardRequirements.length; req++) {
							var requirement = gauntletConfig.randomCardRequirements[req];
							var quantity = requirement.quantity || 0;
							var cardType = requirement.cardType;
							var matchSubtypes = requirement.matchSubtypes || [];
							var excludeSubtypes = requirement.excludeSubtypes || [];
							
							// Find all cards that match this requirement
							var matchingCards = [];
							for (var cardId in cardSet) {
								if (!cardSet[cardId]) continue;
								if (cardSet[cardId].player !== runner) continue;
								if (cardSet[cardId].cardType !== cardType) continue;
								if (cardSet[cardId].cardType === 'identity') continue; // Skip identities
								if (CardMatchesRequirement(cardId, matchSubtypes, excludeSubtypes)) {
									matchingCards.push(parseInt(cardId));
								}
							}
							
							// Randomly select quantity cards from matching pool (cards can be selected multiple times)
							var selected = 0;
							while (selected < quantity && matchingCards.length > 0) {
								var randomIdx = Math.floor(Math.random() * matchingCards.length);
								var selectedCard = matchingCards[randomIdx];
								
								gauntletCardIds.push(selectedCard);
								// Add 1 copy (increment if card already selected, initialize if first time)
								gauntletCardCounts[selectedCard] = (gauntletCardCounts[selectedCard] || 0) + 1;
								
								// Don't remove from matchingCards - allow same card to be selected again
								selected++;
							}
						}
					}
				}

				// Load or generate the gauntlet card set now that cardData is loaded
				LoadOrGenerateGauntletState();

				// Rebuild identity dropdown now that deckPlayer is runner
				PopulateIdentityDropdown();

			// Load runner deck from URL parameter (r) - the player's deck
			var specifiedRunnerDeck = URIParameter("r");
			if (specifiedRunnerDeck != "" && specifiedRunnerDeck != "random") {
				json = JSON.parse(
					LZString.decompressFromEncodedURIComponent(specifiedRunnerDeck)
				);
				if (typeof json.cards == 'undefined') json.cards = [];
				
				// Check if the identity from the URL is available in the filtered dropdown
				var identityInDropdown = $("#identityselect option[value=" + json.identity + "]").length > 0;
				
				if (identityInDropdown) {
					// Update identity dropdown and image
					$("#identityselect option[value=" + json.identity + "]").prop("selected", "selected");
					$("#identity").prop("src", "images/" + ChangeImageFileToJPG(cardSet[json.identity].imageFile));
				} else {
					// Identity not available (set not allowed), fall back to first available identity
					var firstIdentity = $("#identityselect option:first").val();
					if (firstIdentity && cardSet[firstIdentity]) {
						json.identity = parseInt(firstIdentity);
						$("#identityselect").val(firstIdentity);
						$("#identity").prop("src", "images/" + ChangeImageFileToJPG(cardSet[firstIdentity].imageFile));
						console.warn("Identity from URL not in allowed sets, falling back to:", cardSet[firstIdentity].title);
					}
				}
			} else {
				// No runner deck in URL - set default identity from first dropdown option
				var firstIdentity = $("#identityselect option:first").val();
				if (firstIdentity && cardSet[firstIdentity]) {
					json.identity = parseInt(firstIdentity);
					$("#identity").prop("src", GetImagePath(cardSet[firstIdentity].imageFile));
				}
			}
			
			// Load corp deck from URL parameter (c) - the opponent's deck
			var specifiedCorpDeck = URIParameter("c");
			if (specifiedCorpDeck != "" && specifiedCorpDeck != "random") {
				var oppjson = JSON.parse(
					LZString.decompressFromEncodedURIComponent(specifiedCorpDeck)
				);
				opponentdeckstr = "c=" + specifiedCorpDeck + "&";
				opponentdeckimg = "images/" + ChangeImageFileToJPG(cardSet[oppjson.identity].imageFile);
			}
			
			// Debug: Log decoded parameters
			var decodedR = URIParameter("r");
			var decodedC = URIParameter("c");
			var decodedG = URIParameter("g");
			
			if (decodedR) {
				console.log("Decoded r parameter:", JSON.parse(LZString.decompressFromEncodedURIComponent(decodedR)));
			}
			if (decodedC) {
				console.log("Decoded c parameter:", JSON.parse(LZString.decompressFromEncodedURIComponent(decodedC)));
			}
			if (decodedG) {
				var gauntletState = JSON.parse(LZString.decompressFromEncodedURIComponent(decodedG));
				console.log("Decoded g parameter:", gauntletState);
				
				// Disable identity dropdown if player has already defeated opponents
				if (gauntletState.defeated > 0) {
					$("#identityselect").prop("disabled", true).prop("title", "You cannot change identities after your first win.");
				}
				
				// Store credits from gauntlet state
				gauntletCredits = gauntletState.credits || 0;
				// Store seed from gauntlet state
				gauntletSeed = gauntletState.seed || '';
				// Store allowed sets from gauntlet state
				gauntletAllowedSets = gauntletState.allowedSets || [];
				
				// Build cardIdToSet mapping from cardData.json pack_code
				// Only map cards that exist in both cardData.json and the .js files
				var packCodeToSet = {
					'core': 'core',
					'sg': 'sg',
					'su21': 'su21',
					'ms': 'ms',
					'elev': 'elev'
				};
				
				if (cardData && cardData.data) {
					for (var i = 0; i < cardData.data.length; i++) {
						var c = cardData.data[i];
						var cardId = c.code;
						// Only map if card exists in cardSet
						if (cardSet[cardId]) {
							var packCode = c.pack_code || '';
							var setCode = packCodeToSet[packCode] || packCode;
							cardIdToSet[cardId] = setCode;
						}
					}
				}
				
				// Fallback: Map cards by ID range for cards not in cardData.json
				for (var cardId in cardSet) {
					if (!cardSet[cardId]) continue;
					if (cardIdToSet[cardId]) continue; // Already mapped
					var cardIdInt = parseInt(cardId);
					var cardIdStr = String(cardId);
					
					// Core Set uses 1xxx range (1000-1999)
					if (cardIdInt >= 1000 && cardIdInt <= 1999) {
						cardIdToSet[cardId] = 'core';
					} else {
						var prefix = cardIdStr.substring(0, 2);
						var idRangeMap = {
							'30': 'sg',      // System Gateway (30000-30999)
							'31': 'su21',    // System Update 2021 (31000-31999)
							'33': 'ms',      // Midnight Sun (33000-33999)
							'35': 'elev'     // Elevation (35000-35999)
						};
						if (idRangeMap[prefix]) {
							cardIdToSet[cardId] = idRangeMap[prefix];
						}
					}
				}
				
				// Note: cardIdToSet mapping is now populated from cardData.json
				console.log("cardIdToSet mapping ready:", Object.keys(cardIdToSet).length, "cards mapped");
				console.log("gauntletAllowedSets:", gauntletAllowedSets);
				
				// Debug: Show breakdown of mapped cards by set
				var setBreakdown = {};
				for (var cardId in cardIdToSet) {
					var setCode = cardIdToSet[cardId];
					setBreakdown[setCode] = (setBreakdown[setCode] || 0) + 1;
				}
				console.log("Cards per set:", setBreakdown);
				
				// Restore shop purchase count from gauntlet state for persistence across reloads
				shopPurchaseCount = gauntletState.shopPurchaseCount || 0;
				
				// Store opponents globally for opponent display
				gauntletOpponents = gauntletState.opponents || [];
				
				// Log opponent names and URLs
				if (gauntletState.opponents && gauntletState.opponents.length > 0) {
					console.log("Gauntlet Opponents:");
					for (var i = 0; i < gauntletState.opponents.length; i++) {
						var opponentName = gauntletState.opponents[i].name || 'Unknown Opponent';
						var opponentFaction = gauntletState.opponents[i].faction || 'Unknown Faction';
						var opponentURL = gauntletState.opponents[i].URL || 'No URL';
						var opponentDefeated = gauntletState.opponents[i].hasbeendefeated || false;
						console.log((i + 1) + ". " + opponentName + " (" + opponentFaction + ") - URL: " + opponentURL + " - Defeated: " + opponentDefeated);
					}
				}
				
				// Select random packs on page load so they're deterministic
				selectedShopPacks = SelectRandomShopPacks();
				console.log("Shop packs selected:", selectedShopPacks.map(function(p) { return p.name; }));
				
				// Show welcome modal if this is the start of a gauntlet (defeated === 0)
				if (gauntletState.defeated === 0) {
					ShowGauntletWelcomeModal(gauntletState.gauntletLength);
				} else if (gauntletState.defeated > 0) {
					// Show congratulations modal for returning victories
					var creditsWon = gauntletState.creditsWon || 0;
					var creditsWonText = gauntletState.creditsWonText || "";
					gauntletCredits += creditsWon; // Add won credits to available credits
					gauntletState.creditsWon = 0; // Reset creditsWon since they've been applied
					gauntletState.creditsWonText = ""; // Reset creditsWonText
					ShowCongratulationsModal(creditsWon, creditsWonText);
				}
			}
			
			// Display deck output and opponent info (matching decklauncher.php pattern)
			RecalculateDeckCounts();
			
			// Display deck stats
			var deckSizeTarget = cardSet[json.identity].deckSize;
			var influenceLimit = cardSet[json.identity].influenceLimit;
			var totalCards = json.cards.length;
			var totalInfluence = 0;
			for (var i = 0; i < json.cards.length; i++) {
				var cardId = json.cards[i];
				if (cardSet[cardId].faction !== cardSet[json.identity].faction) {
					totalInfluence += cardSet[cardId].influence;
				}
			}
			
			var validityOutput = '<div class="deck-stats">';
			// Cards stat
			var cardsClass = 'deck-stat';
			if (totalCards < deckSizeTarget) cardsClass += ' bad';
			validityOutput += '<div class="'+cardsClass+'"><span class="stat-label">Cards:</span> '+totalCards+' / '+deckSizeTarget+'</div>';
			// Influence stat
			var infClass = 'deck-stat';
			if (totalInfluence > influenceLimit) infClass += ' bad';
			validityOutput += '<div class="'+infClass+'"><span class="stat-label">Influence:</span> '+totalInfluence+' / '+influenceLimit+'</div>';
			// Collection stat - count unique cards in gauntlet subset with faction breakdown
			var collectionSize = 0;
			var factionCounts = { 'Anarch': 0, 'Criminal': 0, 'Shaper': 0, 'Neutral': 0 };
			for (var cardId in gauntletCardCounts) {
				collectionSize += gauntletCardCounts[cardId];
				if (cardSet[cardId]) {
					var faction = (cardSet[cardId].faction || '').toLowerCase();
					if (faction === 'anarch' || faction === 'anarch-runner') factionCounts['Anarch'] += gauntletCardCounts[cardId];
					else if (faction === 'criminal' || faction === 'criminal-runner') factionCounts['Criminal'] += gauntletCardCounts[cardId];
					else if (faction === 'shaper' || faction === 'shaper-runner') factionCounts['Shaper'] += gauntletCardCounts[cardId];
					else if (faction === 'neutral' || faction === 'neutral-runner') factionCounts['Neutral'] += gauntletCardCounts[cardId];
				}
			}
			var collectionDetailsHtml = '<div class="collection-details"><div><span>&gt; Anarch:</span><span>' + factionCounts['Anarch'] + ' cards</span></div><div><span>&gt; Criminal:</span><span>' + factionCounts['Criminal'] + ' cards</span></div><div><span>&gt; Shaper:</span><span>' + factionCounts['Shaper'] + ' cards</span></div><div><span>&gt; Neutral:</span><span>' + factionCounts['Neutral'] + ' cards</span></div></div>';
			validityOutput += '<div class="collection-stat-wrapper collapsed"><div class="deck-stat collection-stat"><span class="stat-label">Collection:</span> '+collectionSize+' cards</div>' + collectionDetailsHtml + '</div>';
			// Credits stat
			validityOutput += '<div class="deck-stat"><span class="stat-label">Credits:</span> '+gauntletCredits+'</div>';
			validityOutput += '</div>';
			$("#output").html(validityOutput);
			
			// Update opponent display to show all opponent identity images
			if (gauntletOpponents && gauntletOpponents.length > 0) {
				var oppHTML = '' +
					'<div class="opponent-header"><span class="opponent-title">Opponents</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
					'<div class="opponent-body opponent-gallery">';
				for (var i = 0; i < gauntletOpponents.length; i++) {
					var opp = gauntletOpponents[i];
					var oppIdentityId = opp.identity;
					var oppImgSrc = 'images/glow_outline.png';
					if (cardSet[oppIdentityId] && cardSet[oppIdentityId].imageFile) {
						oppImgSrc = GetImagePath(cardSet[oppIdentityId].imageFile);
					}
					var defeatedClass = opp.hasbeendefeated ? ' defeated' : '';
					oppHTML += '<img class="opponent-identity-img' + defeatedClass + '" src="' + oppImgSrc + '" data-identity-id="' + oppIdentityId + '"/>';
				}
				oppHTML += '</div>';
				$("#opponentid").html(oppHTML).addClass('collapsed').show();
			} else if (opponentdeckimg != "") {
				// Fallback for non-gauntlet mode with single opponent
				var oppHTML = '' +
					'<div class="opponent-header"><span class="opponent-title">Opponent Deck</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
					'<div class="opponent-body"><img src="'+opponentdeckimg+'"/></div>';
				$("#opponentid").html(oppHTML).addClass('collapsed').show();
			} else {
				$("#opponentid").hide();
			}
			
			// Render cards from gauntlet subset
			RenderAllCardsList();
			UpdateDeckTextareaFromCounts(); // Populate deck display from loaded deck
			UpdateCardCountsUI();
			UpdateLaunchStrings();
			UpdatePlayDeckButtonState();
			
				try {
					var currentIdSel = $('#identityselect').val();
					var effectiveId = currentIdSel || (json && json.identity);
					if (effectiveId && typeof window.PopulatePreconDropdownForIdentity === 'function') {
						window.PopulatePreconDropdownForIdentity(effectiveId);
					}
				} catch(e) { /* ignore */ }
			});

			//UTILITY: ensure deckCounts matches json.cards
			function RecalculateDeckCounts() {
				deckCounts = {};
				for (var i=0;i<json.cards.length;i++) {
					var id = json.cards[i];
					if (typeof deckCounts[id] === 'undefined') deckCounts[id] = 1; else deckCounts[id]++;
				}
				UpdateCardCountsUI();
			}

			function UpdateDeckTextareaFromCounts() {
				// Group cards by category
				var categories = {
					'Event': [],
					'Hardware': [],
					'Icebreaker': [],
					'Program': [],
					'Resource': []
				};
				
				// Corp categories (if needed)
				var corpCategories = {
					'Agenda': [],
					'Asset': [],
					'Ice': [],
					'Operation': [],
					'Upgrade': []
				};
				
				var useCategories = (deckPlayer === runner) ? categories : corpCategories;
				
				for (var i = 0; i < allCardIdsForPlayer.length; i++) {
					var id = allCardIdsForPlayer[i];
					var ct = deckCounts[id] || 0;
					if (ct > 0) {
						var card = cardSet[id];
						var cardType = (card.cardType || '').toLowerCase();
						var subTypes = card.subTypes || [];
						var isIcebreaker = subTypes.indexOf('Icebreaker') !== -1;
						
						if (deckPlayer === runner) {
							if (cardType === 'event') {
								categories['Event'].push({ id: id, count: ct, title: card.title });
							} else if (cardType === 'hardware') {
								// Check for Console subtype
								var isConsole = subTypes.indexOf('Console') !== -1;
								categories['Hardware'].push({ id: id, count: ct, title: card.title, isConsole: isConsole });
							} else if (cardType === 'program' && isIcebreaker) {
								// Determine icebreaker subtype(s)
								var breakerTypes = [];
								if (subTypes.indexOf('Fracter') !== -1) breakerTypes.push('Fracter');
								if (subTypes.indexOf('Decoder') !== -1) breakerTypes.push('Decoder');
								if (subTypes.indexOf('Killer') !== -1) breakerTypes.push('Killer');
								if (subTypes.indexOf('AI') !== -1) breakerTypes.push('AI');
								var breakerTypeStr = breakerTypes.length > 0 ? breakerTypes.join('/') : '';
								categories['Icebreaker'].push({ id: id, count: ct, title: card.title, breakerType: breakerTypeStr });
							} else if (cardType === 'program') {
								// Check for Trojan subtype
								var isTrojan = subTypes.indexOf('Trojan') !== -1;
								categories['Program'].push({ id: id, count: ct, title: card.title, isTrojan: isTrojan });
							} else if (cardType === 'resource') {
								categories['Resource'].push({ id: id, count: ct, title: card.title });
							}
						} else {
							// Corp cards
							if (cardType === 'agenda') {
								corpCategories['Agenda'].push({ id: id, count: ct, title: card.title });
							} else if (cardType === 'asset') {
								corpCategories['Asset'].push({ id: id, count: ct, title: card.title });
							} else if (cardType === 'ice') {
								corpCategories['Ice'].push({ id: id, count: ct, title: card.title });
							} else if (cardType === 'operation') {
								corpCategories['Operation'].push({ id: id, count: ct, title: card.title });
							} else if (cardType === 'upgrade') {
								corpCategories['Upgrade'].push({ id: id, count: ct, title: card.title });
							}
						}
					}
				}
				
				// Build HTML output
				var html = '';
				var categoryOrder = (deckPlayer === runner) 
					? ['Event', 'Hardware', 'Icebreaker', 'Program', 'Resource']
					: ['Agenda', 'Asset', 'Ice', 'Operation', 'Upgrade'];
				
				for (var ci = 0; ci < categoryOrder.length; ci++) {
					var catName = categoryOrder[ci];
					var cards = useCategories[catName];
					if (cards.length === 0) continue;
					
					// Sort cards alphabetically
					cards.sort(function(a, b) {
						return a.title.localeCompare(b.title);
					});
					
					// Count total cards in category
					var totalInCategory = 0;
					for (var j = 0; j < cards.length; j++) {
						totalInCategory += cards[j].count;
					}
					
					// Category header
					html += '<div class="deck-category">';
					html += '<div class="deck-category-header">' + catName + ' (' + totalInCategory + ')</div>';
					html += '<div class="deck-category-cards">';
					
					// Card entries
					for (var k = 0; k < cards.length; k++) {
						var cardEntry = cards[k];
						html += '<div class="deck-card-entry" data-card-id="' + cardEntry.id + '" onclick="ShowLightbox(' + cardEntry.id + ');">';
						html += '<span class="deck-card-info"><span class="deck-card-count">' + cardEntry.count + 'x</span> ';
						html += '<span class="deck-card-title">' + cardEntry.title + '</span></span>';
						// Show icebreaker subtype if available
						if (cardEntry.breakerType) {
							html += '<span class="deck-card-subtype">[' + cardEntry.breakerType + ']</span>';
						}
						// Show Console indicator for hardware
						if (cardEntry.isConsole) {
							html += '<span class="deck-card-subtype">[Console]</span>';
						}
						// Show Trojan indicator for programs
						if (cardEntry.isTrojan) {
							html += '<span class="deck-card-subtype">[Trojan]</span>';
						}
						html += '</div>';
					}
					
					html += '</div></div>';
				}
				
				$("#deck").html(html);
			}

			function AddCardToDeck(id) {
				if (typeof json.cards === 'undefined') json.cards = [];
				if (typeof deckCounts[id] === 'undefined') deckCounts[id]=0;
				// Check if adding another copy would exceed the 3-copy maximum
				if (deckCounts[id] >= 3) {
					return; // Cannot add more than 3 copies of the same card
				}
				// Check if adding another copy would exceed the gauntlet card limit
				var maxCopies = gauntletCardCounts[id] || 0;
				if (deckCounts[id] >= maxCopies) {
					return; // Cannot add more copies than available in gauntlet
				}
				deckCounts[id]++;
				json.cards.push(id);
				deckModified = true;
				UpdateDeckTextareaFromCounts();
				UpdateCardCountsUI();
				Parse();
			if (showingOnlySelected) ApplyFilter();
			UpdatePlayDeckButtonState();
			}
			function RemoveCardFromDeck(id) {
				if (typeof json.cards === 'undefined') json.cards = [];
				if (typeof deckCounts[id] === 'undefined' || deckCounts[id]===0) return;
				deckCounts[id]--;
				//remove one occurrence from json.cards
				var idx = json.cards.indexOf(id);
				if (idx>-1) json.cards.splice(idx,1);
				deckModified = true;
				UpdateDeckTextareaFromCounts();
				UpdateCardCountsUI();
				Parse();
			
				// Reapply current sort and filters
				SortCardsBySort();
				ApplyTypeFilter();
				UpdatePlayDeckButtonState();
			}

			// Show confirmation modal for selling a card (separate from removing it from deck)
			function ShowSellConfirmModal(id) {
				pendingSellCardId = id;
				pendingSellQuantity = 1; // Reset to default
				var cardName = cardSet[id] ? cardSet[id].title : 'Unknown Card';
			var imgSrc = GetImagePath(cardSet[id].imageFile);
			var availableCount = gauntletCardCounts[id] || 0;
				
				var modalHtml = '<div class="solo-menu" style="display: flex; flex-direction: column; align-items: center; max-width: 400px;">';
				modalHtml += '<div class="solo-logo" style="width: 100%;">';
				modalHtml += '<h1 class="logo-text" style="color: var(--crt-red); text-shadow: 0 0 5px var(--crt-red), 0 0 15px var(--glow-red), 0 0 35px var(--glow-red-dark); font-size: 1.5em;">SELL CARD</h1>';
				modalHtml += '</div>';
				modalHtml += '<div style="text-align: center; margin: 10px 0;">';
				modalHtml += '<img src="' + imgSrc + '" style="max-height: 200px; border-radius: 8px;" />';
				modalHtml += '</div>';
				modalHtml += '<div style="color: var(--crt-red); font-family: monospace; padding: 10px; text-align: center;">';
				modalHtml += '<p style="font-size: 16px;"><strong>' + cardName + '</strong></p>';
				modalHtml += '<p class="sell-modal-text">How many would you like to sell?</p>';
				modalHtml += '</div>';
				// Quantity stepper
				modalHtml += '<div class="settings-stepper sell-modal-stepper">';
				modalHtml += '<button type="button" class="stepper-btn" onclick="AdjustSellQuantity(-1);" id="sell-qty-minus">-</button>';
				modalHtml += '<span class="stepper-value sell-modal-qty" id="sell-qty-value">1</span>';
				modalHtml += '<button type="button" class="stepper-btn" onclick="AdjustSellQuantity(1);" id="sell-qty-plus"' + (availableCount <= 1 ? ' disabled' : '') + '>+</button>';
				modalHtml += '</div>';
				// Credit display
				modalHtml += '<p class="sell-modal-credit">You will receive <span id="sell-credit-value" class="sell-modal-credit-value">1</span> <img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit" style="filter: invert(1) brightness(0.5) sepia(1) saturate(5) hue-rotate(80deg);"></p>';
				// Buttons
				modalHtml += '<div style="display: flex; justify-content: center; gap: 20px; margin-top: 15px; width: 100%;">';
				modalHtml += '<button class="button" onclick="CloseSellConfirmModal();">CANCEL</button>';
				modalHtml += '<button class="button button-red" onclick="ConfirmSellCard();">SELL</button>';
				modalHtml += '</div>';
				modalHtml += '</div>';

				var modal = document.getElementById('sell-confirm-modal');
				if (!modal) {
					modal = document.createElement('div');
					modal.id = 'sell-confirm-modal';
					modal.className = 'modal';
					modal.style.display = 'flex';
					modal.style.zIndex = '10000';
					document.body.appendChild(modal);
				}
				
				modal.innerHTML = modalHtml;
				modal.style.display = 'flex';
				UpdateSellQuantityButtons();
			}

		function AdjustSellQuantity(delta) {
			var availableCount = gauntletCardCounts[pendingSellCardId] || 0;
			pendingSellQuantity = Math.max(1, Math.min(availableCount, pendingSellQuantity + delta));
			document.getElementById('sell-qty-value').textContent = pendingSellQuantity;
			document.getElementById('sell-credit-value').textContent = pendingSellQuantity;
			UpdateSellQuantityButtons();
		}

		function UpdateSellQuantityButtons() {
			var availableCount = gauntletCardCounts[pendingSellCardId] || 0;
			document.getElementById('sell-qty-minus').disabled = (pendingSellQuantity <= 1);
			document.getElementById('sell-qty-plus').disabled = (pendingSellQuantity >= availableCount);
		}

		function CloseSellConfirmModal() {
			pendingSellCardId = null;
			pendingSellQuantity = 1;
			var modal = document.getElementById('sell-confirm-modal');
			if (modal) modal.style.display = 'none';
		}

		function ConfirmSellCard() {
			if (pendingSellCardId !== null) {
				for (var i = 0; i < pendingSellQuantity; i++) {
					SellCard(pendingSellCardId);
				}
			}
			CloseSellConfirmModal();
		}

		function SellCard(id) {
			// Check if there are any copies in the gauntlet to sell
			if (typeof gauntletCardCounts[id] === 'undefined' || gauntletCardCounts[id] <= 0) return;
			
			// If the card is in the deck, remove it from the deck first
			if (typeof deckCounts[id] !== 'undefined' && deckCounts[id] > 0) {
				RemoveCardFromDeck(id);
			}
			
			// Remove one copy from the gauntlet collection
			gauntletCardCounts[id]--;
			
			// Also remove from gauntletCardIds array (remove one occurrence)
			var idx = gauntletCardIds.indexOf(id);
			if (idx > -1) gauntletCardIds.splice(idx, 1);
			
			// If no copies left, remove from gauntletCardCounts and remove card from display
			if (gauntletCardCounts[id] <= 0) {
				delete gauntletCardCounts[id];
				// Remove the card element from the display
				$('#cardcontainer .card-item[data-id="' + id + '"]').remove();
				// Remove from allCardIdsForPlayer array for lightbox navigation
				var playerIdx = allCardIdsForPlayer.indexOf(id);
				if (playerIdx > -1) allCardIdsForPlayer.splice(playerIdx, 1);
			}
			
			// Add 1 credit
			gauntletCredits++;
			
			deckModified = true;
			UpdateCardCountsUI();
			Parse();
			
			// Reapply current sort and filters
			SortCardsBySort();
			ApplyTypeFilter();
			if (showingOnlySelected) ApplyFilter();
			UpdatePlayDeckButtonState();
			
			// Run pool verification after selling a card
			VerifyCardPool();
		}

		// Helper function to get the cheapest pack cost from config
		function GetCheapestPackCost() {
			if (!gauntletConfig || !gauntletConfig.cardPacks || gauntletConfig.cardPacks.length === 0) {
				return Infinity; // No packs available
			}
			var cheapest = Infinity;
			for (var i = 0; i < gauntletConfig.cardPacks.length; i++) {
				var packCost = gauntletConfig.cardPacks[i].cost || 0;
				if (packCost < cheapest) {
					cheapest = packCost;
				}
			}
			return cheapest;
		}

		// Pool verification function to check if a legal deck can still be built
		function VerifyCardPool() {
			// Remove any existing alert message
			$('#pool-verification-alert').remove();
			
			// Get the current runner identity
			if (!json || !json.identity || !cardSet[json.identity]) {
				return true; // No identity selected, skip verification
			}
			
			var identity = cardSet[json.identity];
			var deckSize = identity.deckSize || 45;
			var influenceLimit = identity.influenceLimit || 15;
			var identityFaction = (identity.faction || '').toLowerCase();
			
			// Count neutral cards and faction-matching cards
			var neutralsAndFactionCardCount = 0;
			var outOfFactionCards = []; // Array of {cardId, influence}
			var countedCardIds = []; // Array to track all counted card IDs for debug
			
			for (var cardId in gauntletCardCounts) {
				var card = cardSet[parseInt(cardId)];
				if (!card) continue;
				
				var cardFaction = (card.faction || '').toLowerCase();
				var isNeutral = cardFaction === 'neutral' || cardFaction === 'neutral-runner' || cardFaction === 'neutral-corp';
				var matchesFaction = cardFaction === identityFaction;
				
				var cardCount = gauntletCardCounts[cardId];
				// Limit each card to max 3 copies for deck building
				var usableCount = Math.min(cardCount, 3);
				
				if (isNeutral || matchesFaction) {
					neutralsAndFactionCardCount += usableCount;
					// Add each copy to countedCardIds
					for (var i = 0; i < usableCount; i++) {
						countedCardIds.push(parseInt(cardId));
					}
				} else {
					// Out of faction card - add each copy separately for sorting
					var cardInfluence = card.influence || 0;
					for (var i = 0; i < usableCount; i++) {
						outOfFactionCards.push({
							cardId: parseInt(cardId),
							influence: cardInfluence
						});
					}
				}
			}
			
			// Check if we have enough with just neutrals and faction cards
			if (neutralsAndFactionCardCount >= deckSize) {
				console.log("Pool Verification - PASSED (neutrals + faction only)", {
					result: "PASSED",
					neutralsAndFactionCardCount: neutralsAndFactionCardCount,
					deckSize: deckSize,
					influenceLimit: influenceLimit,
					influenceUsed: 0,
					influenceCardCount: 0,
					totalAvailable: neutralsAndFactionCardCount,
					countedCardIds: countedCardIds
				});
				return true; // Verification passed
			}
			
			// Sort out-of-faction cards by influence in ascending order
			outOfFactionCards.sort(function(a, b) {
				return a.influence - b.influence;
			});
			
			// Count out-of-faction cards that fit within influence limit
			var influenceCardCount = 0;
			var influenceCount = 0;
			
			for (var i = 0; i < outOfFactionCards.length; i++) {
				var cardInfluence = outOfFactionCards[i].influence;
				if (influenceCount + cardInfluence <= influenceLimit) {
					influenceCount += cardInfluence;
					influenceCardCount++;
					// Add this out-of-faction card to countedCardIds
					countedCardIds.push(outOfFactionCards[i].cardId);
				} else {
					// Adding this card would exceed influence limit, stop here
					break;
				}
			}
			
			// Check total cards available
			var totalAvailable = neutralsAndFactionCardCount + influenceCardCount;
			var passed = totalAvailable >= deckSize;
			
			// Log all counted card IDs and comparison numbers for debug
			console.log("Pool Verification - " + (passed ? "PASSED" : "FAILED"), {
				result: passed ? "PASSED" : "FAILED",
				neutralsAndFactionCardCount: neutralsAndFactionCardCount,
				influenceCardCount: influenceCardCount,
				totalAvailable: totalAvailable,
				deckSize: deckSize,
				influenceUsed: influenceCount,
				influenceLimit: influenceLimit,
				countedCardIds: countedCardIds
			});
			
			if (passed) {
				return true; // Verification passed
			}
			
			// Verification failed - check if player can afford cheapest pack
			var cheapestPackCost = GetCheapestPackCost();
			
			console.log("Pool Verification - Alert Check", {
				verificationPassed: false,
				gauntletCredits: gauntletCredits,
				cheapestPackCost: cheapestPackCost,
				canAffordPack: gauntletCredits >= cheapestPackCost,
				showingAlert: gauntletCredits < cheapestPackCost
			});
			
			if (gauntletCredits < cheapestPackCost) {
				// Show red alert message - can't afford any packs
				var alertHtml = '<div id="pool-verification-alert" class="pool-verification-alert">';
				alertHtml += 'ALERT! - You currently do not have enough cards to build a legal deck with this runner. You may not be able to continue the Gauntlet. Sorry. Please let yourself out.';
				alertHtml += '</div>';
				
				// Insert the alert between the runner image and the card stats (#output)
				$('#identity').after(alertHtml);
			} else {
				// Show orange warning message - still have credits to buy packs
				var warningHtml = '<div id="pool-verification-alert" class="pool-verification-warning">';
				warningHtml += 'WARNING! You currently do not have enough cards to build a legal deck with this runner. Use your remaining credits wisely.';
				warningHtml += '</div>';
				
				// Insert the warning between the runner image and the card stats (#output)
				$('#identity').after(warningHtml);
			}
			
			return false; // Verification failed
		}

		function AddNonInfluence() {
			// Get the player identity's faction
			var identity = cardSet[json.identity];
			if (!identity) return;
			var identityFaction = (identity.faction || '').toLowerCase();
			
			// Add all cards from the gauntlet subset that match the faction or are neutral
			for (var cardId in gauntletCardCounts) {
				var card = cardSet[parseInt(cardId)];
				if (!card) continue;
				
				var cardFaction = (card.faction || '').toLowerCase();
				var isNeutral = cardFaction === 'neutral' || cardFaction === 'neutral-runner' || cardFaction === 'neutral-corp';
				var matchesFaction = cardFaction === identityFaction;
				
				// Add cards that are neutral or match the identity faction
				if (isNeutral || matchesFaction) {
					// Add up to the maximum available copies
					var maxCopies = gauntletCardCounts[cardId] || 0;
					var currentCount = deckCounts[cardId] || 0;
					for (var i = currentCount; i < maxCopies; i++) {
						AddCardToDeck(parseInt(cardId));
					}
				}
			}
		}

		// Sort state and functions
		var currentSort = 'name'; // 'name', 'influence', 'type', 'faction'

		function GetFactionOrder(cardId) {
			var card = cardSet[cardId];
			if (!card) return 999;
			var faction = (card.faction || '').toLowerCase();
			if (deckPlayer === runner) {
				// Runner faction order: Anarch->Criminal->Shaper->Neutral
				if (faction === 'anarch') return 0;
				if (faction === 'criminal') return 1;
				if (faction === 'shaper') return 2;
				if (faction === 'neutral' || faction === 'neutral-runner') return 3;
			} else {
				// Corp faction order: HB->Jinteki->NBN->Weyland->Neutral
				if (faction === 'haas-bioroid' || faction === 'hb') return 0;
				if (faction === 'jinteki') return 1;
				if (faction === 'nbn') return 2;
				if (faction === 'weyland-consortium' || faction === 'weyland') return 3;
				if (faction === 'neutral' || faction === 'neutral-corp') return 4;
			}
			return 999;
		}

		function GetTypeOrder(cardId) {
			var card = cardSet[cardId];
			if (!card) return 999;
			var cardType = (card.cardType || '').toLowerCase();
			
			if (deckPlayer === runner) {
				// Runner type order: Event->Program->Hardware->Resource
				if (cardType === 'event') return 0;
				if (cardType === 'program') return 1;
				if (cardType === 'hardware') return 2;
				if (cardType === 'resource') return 3;
			} else {
				// Corp type order: agenda->asset->ice->operation->upgrade
				if (cardType === 'agenda') return 0;
				if (cardType === 'asset') return 1;
				if (cardType === 'ice') return 2;
				if (cardType === 'operation') return 3;
				if (cardType === 'upgrade') return 4;
			}
			return 999;
		}

		function SortCardsBySort() {
			var $container = $('#cardcontainer');
			var $cards = $container.children('.card-item').detach();
			
			   $cards.sort(function(a, b) {
				   var idA = parseInt($(a).attr('data-id'));
				   var idB = parseInt($(b).attr('data-id'));
				   var cardA = cardSet[idA];
				   var cardB = cardSet[idB];
				   if (!cardA || !cardB) return 0;
				   if (currentSort === 'name') {
					   return (cardA.title || '').localeCompare(cardB.title || '');
				   } else if (currentSort === 'influence') {
					   var influenceA = cardA.influence || 0;
					   var influenceB = cardB.influence || 0;
					   if (influenceA !== influenceB) return influenceA - influenceB;
					   // Secondary: alphabetical by title
					   return (cardA.title || '').localeCompare(cardB.title || '');
				   } else if (currentSort === 'type') {
					   var typeOrderA = GetTypeOrder(idA);
					   var typeOrderB = GetTypeOrder(idB);
					   if (typeOrderA !== typeOrderB) return typeOrderA - typeOrderB;
					   // Secondary: subtype (array or string, fallback to empty string)
					   var subA = (cardA.subTypes && cardA.subTypes.length) ? cardA.subTypes.join(',') : (cardA.subtype || '');
					   var subB = (cardB.subTypes && cardB.subTypes.length) ? cardB.subTypes.join(',') : (cardB.subtype || '');
					   if (subA < subB) return -1;
					   if (subA > subB) return 1;
					   // Tertiary: alphabetical by title
					   return (cardA.title || '').localeCompare(cardB.title || '');
				   } else if (currentSort === 'faction') {
					   var factionOrderA = GetFactionOrder(idA);
					   var factionOrderB = GetFactionOrder(idB);
					   if (factionOrderA !== factionOrderB) return factionOrderA - factionOrderB;
					   // Alphabetical within faction
					   return (cardA.title || '').localeCompare(cardB.title || '');
				   } else if (currentSort === 'quantity') {
					   var qtyA = gauntletCardCounts[idA] || 0;
					   var qtyB = gauntletCardCounts[idB] || 0;
					   if (qtyA !== qtyB) return qtyB - qtyA; // Descending (highest qty first)
					   // Secondary: alphabetical by title
					   return (cardA.title || '').localeCompare(cardB.title || '');
				   }
				   return 0;
			   });
			
			$container.append($cards);
		}

		function CycleSort() {
			if (currentSort === 'name') {
				currentSort = 'quantity';
				$('#sortbydeck').html('SORT BY:<br>QUANTITY');
			} else if (currentSort === 'quantity') {
				currentSort = 'influence';
				$('#sortbydeck').html('SORT BY:<br>INFLUENCE');
			} else if (currentSort === 'influence') {
				currentSort = 'faction';
				$('#sortbydeck').html('SORT BY:<br>FACTION');
			} else if (currentSort === 'faction') {
				currentSort = 'type';
				$('#sortbydeck').html('SORT BY:<br>TYPE');
			} else {
				currentSort = 'name';
				$('#sortbydeck').html('SORT BY:<br>NAME');
			}
			SortCardsBySort();
		}

		function CycleSortReverse() {
			if (currentSort === 'name') {
				currentSort = 'type';
				$('#sortbydeck').html('SORT BY:<br>TYPE');
			} else if (currentSort === 'type') {
				currentSort = 'faction';
				$('#sortbydeck').html('SORT BY:<br>FACTION');
			} else if (currentSort === 'faction') {
				currentSort = 'influence';
				$('#sortbydeck').html('SORT BY:<br>INFLUENCE');
			} else if (currentSort === 'influence') {
				currentSort = 'quantity';
				$('#sortbydeck').html('SORT BY:<br>QUANTITY');
			} else {
				currentSort = 'name';
				$('#sortbydeck').html('SORT BY:<br>NAME');
			}
			SortCardsBySort();
		}

		// Type filter state and functions
		var currentTypeFilter = 'none'; // 'none', 'influence', 'anarch', 'criminal', 'shaper', 'neutral', 'event', 'hardware', 'program', 'resource'

		function SortCardContainer() {
				var $container = $('#cardcontainer');
				var $cards = $container.children('.card-item').detach();
				
				$cards.sort(function(a, b) {
					var idA = parseInt($(a).attr('data-id'));
					var idB = parseInt($(b).attr('data-id'));
					var cardA = cardSet[idA];
					var cardB = cardSet[idB];
					
					if (!cardA || !cardB) return 0;
					
				// Sort by influence (ascending) then alphabetically when influence filter is active
				if (currentTypeFilter === 'influence') {
					var influenceA = cardA.influence || 0;
					var influenceB = cardB.influence || 0;
					if (influenceA !== influenceB) {
						return influenceA - influenceB;
					}
				}
				
				// Always sort alphabetically by card title as secondary sort
				return (cardA.title || '').localeCompare(cardB.title || '');
			});
			
			$container.append($cards);
		}
		
		function ApplyTypeFilter() {
			var $cards = $('#cardcontainer').children('.card-item');
			
			// Get the player identity's faction for influence filtering
			var identityFaction = '';
			if (currentTypeFilter === 'influence') {
				var identity = cardSet[json.identity];
				if (identity) {
					identityFaction = (identity.faction || '').toLowerCase();
				}
			}
			
			$cards.each(function() {
				var id = parseInt($(this).attr('data-id'));
				var card = cardSet[id];
				
				if (!card) {
					$(this).hide();
					return;
				}
				
				var isVisible = false;
				
				if (currentTypeFilter === 'none') {
					isVisible = true;
				} else if (currentTypeFilter === 'influence') {
					// Show cards that are NOT in the identity's faction and NOT neutral
					var cardFaction = (card.faction || '').toLowerCase();
					var isNeutral = cardFaction === 'neutral' || cardFaction === 'neutral-runner' || cardFaction === 'neutral-corp';
					var matchesFaction = cardFaction === identityFaction;
					isVisible = !isNeutral && !matchesFaction;
				} else if (currentTypeFilter === 'anarch') {
					var cardFaction = (card.faction || '').toLowerCase();
					isVisible = (cardFaction === 'anarch' || cardFaction === 'anarch-runner');
				} else if (currentTypeFilter === 'criminal') {
					var cardFaction = (card.faction || '').toLowerCase();
					isVisible = (cardFaction === 'criminal' || cardFaction === 'criminal-runner');
				} else if (currentTypeFilter === 'shaper') {
					var cardFaction = (card.faction || '').toLowerCase();
					isVisible = (cardFaction === 'shaper' || cardFaction === 'shaper-runner');
				} else if (currentTypeFilter === 'neutral') {
					var cardFaction = (card.faction || '').toLowerCase();
					isVisible = (cardFaction === 'neutral' || cardFaction === 'neutral-runner' || cardFaction === 'neutral-corp');
				} else {
					var cardType = (card.cardType || '').toLowerCase();
					isVisible = (currentTypeFilter === cardType);
				}
				
				$(this).toggle(isVisible);
			});
		}

		function CycleTypeFilter() {
			if (currentTypeFilter === 'none') {
				currentTypeFilter = 'influence';
				$('#sortdeck').html('FILTER:<br>INFLUENCE');
			} else if (currentTypeFilter === 'influence') {
				currentTypeFilter = 'anarch';
				$('#sortdeck').html('FILTER:<br>ANARCH');
			} else if (currentTypeFilter === 'anarch') {
				currentTypeFilter = 'criminal';
				$('#sortdeck').html('FILTER:<br>CRIMINAL');
			} else if (currentTypeFilter === 'criminal') {
				currentTypeFilter = 'shaper';
				$('#sortdeck').html('FILTER:<br>SHAPER');
			} else if (currentTypeFilter === 'shaper') {
				currentTypeFilter = 'neutral';
				$('#sortdeck').html('FILTER:<br>NEUTRAL');
			} else if (currentTypeFilter === 'neutral') {
				currentTypeFilter = 'event';
				$('#sortdeck').html('FILTER:<br>EVENT');
			} else if (currentTypeFilter === 'event') {
				currentTypeFilter = 'hardware';
				$('#sortdeck').html('FILTER:<br>HARDWARE');
			} else if (currentTypeFilter === 'hardware') {
				currentTypeFilter = 'program';
				$('#sortdeck').html('FILTER:<br>PROGRAM');
			} else if (currentTypeFilter === 'program') {
				currentTypeFilter = 'resource';
				$('#sortdeck').html('FILTER:<br>RESOURCE');
			} else {
				currentTypeFilter = 'none';
				$('#sortdeck').html('FILTER:<br>ALL');
			}
			SortCardsBySort();
			ApplyTypeFilter();
		}

		function CycleTypeFilterReverse() {
			if (currentTypeFilter === 'none') {
				currentTypeFilter = 'resource';
				$('#sortdeck').html('FILTER:<br>RESOURCE');
			} else if (currentTypeFilter === 'resource') {
				currentTypeFilter = 'program';
				$('#sortdeck').html('FILTER:<br>PROGRAM');
			} else if (currentTypeFilter === 'program') {
				currentTypeFilter = 'hardware';
				$('#sortdeck').html('FILTER:<br>HARDWARE');
			} else if (currentTypeFilter === 'hardware') {
				currentTypeFilter = 'event';
				$('#sortdeck').html('FILTER:<br>EVENT');
			} else if (currentTypeFilter === 'event') {
				currentTypeFilter = 'neutral';
				$('#sortdeck').html('FILTER:<br>NEUTRAL');
			} else if (currentTypeFilter === 'neutral') {
				currentTypeFilter = 'shaper';
				$('#sortdeck').html('FILTER:<br>SHAPER');
			} else if (currentTypeFilter === 'shaper') {
				currentTypeFilter = 'criminal';
				$('#sortdeck').html('FILTER:<br>CRIMINAL');
			} else if (currentTypeFilter === 'criminal') {
				currentTypeFilter = 'anarch';
				$('#sortdeck').html('FILTER:<br>ANARCH');
			} else if (currentTypeFilter === 'anarch') {
				currentTypeFilter = 'influence';
				$('#sortdeck').html('FILTER:<br>INFLUENCE');
			} else {
				currentTypeFilter = 'none';
				$('#sortdeck').html('FILTER:<br>ALL');
			}
			SortCardsBySort();
			ApplyTypeFilter();
		}

		// Random Deck function
		function GenerateRandomDeck() {
			// Use the existing DeckBuild function from utility.js to generate a valid random deck
			var cardsChosen = DeckBuild(cardSet[json.identity]);
			
			// Clear current deck
			json.cards = [];
			deckCounts = {};
			
			// Convert generated deck into counts
			for (var i = 0; i < cardsChosen.length; i++) {
				var cardId = cardsChosen[i];
				if (typeof deckCounts[cardId] === 'undefined') {
					deckCounts[cardId] = 1;
				} else {
					deckCounts[cardId]++;
				}
				json.cards.push(cardId);
			}
			
			deckModified = true;
			UpdateDeckTextareaFromCounts();
			Parse();
			UpdateCardCountsUI();
		}

			function RenderAllCardsList() {
				$("#cardcontainer").empty();
				allCardIdsForPlayer = [];
				// DRBO6: In gauntlet mode, only show cards available in gauntlet
				var cardsToShow = [];
				if (gauntletCardIds.length > 0) {
					// Gauntlet mode: use the subset - get unique card IDs
					var uniqueCardIds = {};
					for (var i = 0; i < gauntletCardIds.length; i++) {
						uniqueCardIds[gauntletCardIds[i]] = true;
					}
					cardsToShow = Object.keys(uniqueCardIds).map(function(x) { return parseInt(x); });
				} else {
					// Fallback: collect all non-identity cards for the current player
					for (var i=0; i<cardSet.length; i++) {
						if (typeof cardSet[i] !== 'undefined' && cardSet[i].player == deckPlayer && cardSet[i].cardType !== 'identity') {
							cardsToShow.push(i);
						}
					}
				}
				
				// Build all card HTML first, then insert in one batch (helps mobile paint)
				var allCardsHtml = '';
				for (var i=0;i<cardsToShow.length;i++) {
					var cardId = cardsToShow[i];
					if (typeof cardSet[cardId] !== 'undefined' && cardSet[cardId].player == deckPlayer && cardSet[cardId].cardType !== 'identity') {
						allCardIdsForPlayer.push(cardId);
						var imgSrc = 'images/'+ChangeImageFileToJPG(cardSet[cardId].imageFile);
						var gauntletQty = gauntletCardCounts[cardId] || 0;
						var deckQty = deckCounts[cardId] || 0;
						// Show both quantities in format: gauntlet | deck
						// Mobile fix: loading="eager" prevents browser lazy-loading, decoding="sync" ensures immediate decode
						allCardsHtml += '<div class="card-item" data-id="'+cardId+'">'
							+'<div class="count-badge" data-id="'+cardId+'">'+gauntletQty+' | '+deckQty+'</div>'
							+'<img class="card-image" src="'+imgSrc+'" alt="'+cardSet[cardId].title+'" loading="eager" decoding="sync" />'
							+'<div class="card-title">'+cardSet[cardId].title+'</div>'
							+'<div class="card-controls">'
								+'<button type="button" class="remove-btn" data-id="'+cardId+'">-</button>'
								+'<button type="button" class="sell-btn" data-id="'+cardId+'"><img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="sell" style="height: 14px; filter: brightness(0) saturate(100%) invert(76%) sepia(85%) saturate(2206%) hue-rotate(81deg) brightness(118%) contrast(119%);" loading="eager" /></button>'
								+'<button type="button" class="add-btn" data-id="'+cardId+'">+</button>'
							+'</div>'
						+'</div>';
					}
				}
				// Insert all cards at once
				$("#cardcontainer").html(allCardsHtml);
				
				AttachCardListEvents();
				UpdateCardCountsUI();
				// Apply current sort after rendering
				SortCardContainer();
				
				// Mobile fix: Force repaint after async rendering to ensure cards display
				// without requiring user to scroll. Use multiple strategies for different browsers.
				setTimeout(function() {
					var container = document.getElementById('cardcontainer');
					if (!container) return;
					
					// Strategy 1: Force layout recalculation
					container.offsetHeight;
					document.body.offsetHeight;
					
					// Strategy 2: Touch each card to force paint (helps with WebKit)
					var cards = container.querySelectorAll('.card-item');
					for (var i = 0; i < cards.length; i++) {
						cards[i].offsetHeight;
					}
					
					// Strategy 3: Brief visibility toggle to force repaint
					container.style.visibility = 'hidden';
					container.offsetHeight; // Force sync reflow
					container.style.visibility = '';
					
					// Strategy 4: Scroll micro-adjustment to trigger paint
					var scrollY = window.scrollY || window.pageYOffset;
					window.scrollTo(0, scrollY + 1);
					requestAnimationFrame(function() {
						window.scrollTo(0, scrollY);
					});
				}, 10);
				
				// Secondary repaint after images may have loaded
				setTimeout(function() {
					var container = document.getElementById('cardcontainer');
					if (container) {
						container.style.opacity = '0.99';
						requestAnimationFrame(function() {
							container.style.opacity = '';
						});
					}
				}, 100);
			}

			function AttachCardListEvents() {
				$("#cardcontainer .add-btn").off('click').on('click',function(){ AddCardToDeck(parseInt($(this).attr('data-id'))); });
				$("#cardcontainer .remove-btn").off('click').on('click',function(){ RemoveCardFromDeck(parseInt($(this).attr('data-id'))); });
				$("#cardcontainer .sell-btn").off('click').on('click',function(){ ShowSellConfirmModal(parseInt($(this).attr('data-id'))); });
				$("#cardcontainer .card-image").off('click').on('click',function(e){ 
					e.stopPropagation(); 
					var cardId = parseInt($(this).closest('.card-item').attr('data-id'));
					ShowLightbox(cardId); 
				});
				
				// Mobile fix: Use IntersectionObserver to force repaint when cards become visible
				// This handles the case where cards don't paint until scrolled
				if ('IntersectionObserver' in window) {
					var mobileRepaintObserver = new IntersectionObserver(function(entries) {
						entries.forEach(function(entry) {
							if (entry.isIntersecting) {
								// Force repaint by briefly toggling a property
								var el = entry.target;
								el.style.opacity = '0.999';
								requestAnimationFrame(function() {
									el.style.opacity = '';
								});
							}
						});
					}, {
						rootMargin: '50px', // Start loading slightly before visible
						threshold: 0.01
					});
					
					// Observe all card items
					document.querySelectorAll('#cardcontainer .card-item').forEach(function(card) {
						mobileRepaintObserver.observe(card);
					});
				}
			}

			function UpdateCardCountsUI() {
				// DRBO6: Handle dual quantities for gauntlet mode (deck/subset)
				$("#cardcontainer .count-badge").each(function(){
					var id = parseInt($(this).attr('data-id'));
					var gaCt = gauntletCardCounts[id] || 0;
					var deckCt = deckCounts[id] || 0;
					$(this).text(deckCt + '/' + gaCt);
					// In gauntlet mode, show badges when there are cards in the gauntlet subset
					$(this).toggleClass('has-copies', gaCt > 0);
					// Apply darkening to cards not in deck
					$(this).closest('.card-item').toggleClass('not-in-deck', deckCt === 0);
				});
				// Keep filters in sync after counts change
				if (showingOnlySelected) {
					// Re-apply current pack filter first
					ApplyFilter();
					// Then hide any visible cards that have count 0 in deck
					$('#cardcontainer .card-item').each(function(){
						if ($(this).is(':visible')) {
							var id = parseInt($(this).find('.count-badge').attr('data-id'));
							var deckCt = deckCounts[id] || 0;
							if (deckCt === 0) $(this).hide();
						}
					});
				}
			}

			function ShowLightbox(cardId) {
				if (!cardSet[cardId]) return;
				
				var card = cardSet[cardId];
			var imgSrc = GetImagePath(card.imageFile);
				
				// Find matching card in cardData by title
				var cardInfo = null;
				if (cardData && cardData.data) {
					for (var i = 0; i < cardData.data.length; i++) {
						if (cardData.data[i].title === card.title || cardData.data[i].stripped_title === card.title) {
							cardInfo = cardData.data[i];
							break;
						}
					}
				}
				
			// Build card text display
			if (cardInfo) {
				var infoHTML = '<div class="card-text-info">';
				infoHTML += '<h2>' + cardInfo.title + '</h2>';
				
				// Faction - special handling for NBN, HB, and neutral
				var factionDisplay = '';
				if (cardInfo.faction_code === 'nbn') {
					factionDisplay = 'NBN';
				} else if (cardInfo.faction_code === 'hb') {
					factionDisplay = 'HB';
				} else if (cardInfo.faction_code === 'neutral-corp' || cardInfo.faction_code === 'neutral-runner') {
					factionDisplay = 'Neutral';
				} else {
					factionDisplay = capitalizeFirst(cardInfo.faction_code || '');
				}
				infoHTML += '<p class="card-faction">' + factionDisplay + '</p>';

				// Helper function to capitalize first letter only
				function capitalizeFirst(str) {
					if (!str) return '';
					return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
				}
				
				// Type and keywords - capitalize first letter only
				var typeLine = capitalizeFirst(cardInfo.type_code || '');
				if (cardInfo.keywords) {
					typeLine += ': ' + capitalizeFirst(cardInfo.keywords || '');
				}
				
				// Handle cost display based on card type
				if (cardInfo.type_code === 'agenda') {
					// For agendas: advancement_cost/agenda_points followed by agenda icon
					if (cardInfo.advancement_cost !== undefined && cardInfo.agenda_points !== undefined) {
						typeLine += ' · ' + cardInfo.advancement_cost + '/' + cardInfo.agenda_points + ' <img src="images/nsg/NSG_AGENDA.svg" class="card-icon" alt="agenda points">';
					}
				} else {
					// For non-agendas: cost followed by credit icon
					if (cardInfo.cost !== undefined && cardInfo.cost !== null) {
						typeLine += ' · ' + cardInfo.cost + '<img src="images/nsg/NSG_CREDIT.svg" class="card-icon" alt="credit">';
					}
				}
				
				infoHTML += '<p class="card-type">' + typeLine + '</p>';
				
				// Influence - only display if it exists and is greater than 0
				if (cardInfo.faction_cost !== undefined && cardInfo.faction_cost !== null && cardInfo.faction_cost > 0) {
					infoHTML += '<p class="card-influence">Influence: ' + cardInfo.faction_cost + '</p>';
				}
				
			// Card text - replace bracketed words with images (NSG SVG format)
			if (cardInfo.text) {
				var cardText = cardInfo.text.replace(/\[([^\]]+)\]/g, function(match, word) {
					var iconName = word.toUpperCase();
					// Special case: [trash] maps to TRASH_ABILITY
					if (iconName === 'TRASH') iconName = 'TRASH_ABILITY';
					// Replace all hyphens with underscores in icon name
					iconName = iconName.replace(/-/g, '_');
					return '<img src="images/nsg/NSG_' + iconName + '.svg" class="card-icon" alt="' + word + '">';
				});
				// Replace newlines with <br> tags (handle both literal \n and actual newlines)
				cardText = cardText.replace(/\\n/g, '<br>').replace(/\n/g, '<br>');
				infoHTML += '<div class="card-text">' + cardText + '</div>';
			}
			
			// Flavor text
			if (cardInfo.flavor) {
				var flavorText = cardInfo.flavor.replace(/\\n/g, '<br>').replace(/\n/g, '<br>');
				infoHTML += '<p class="card-flavor">' + flavorText + '</p>';
			}				infoHTML += '</div>';
					$('#lightbox-text').html(infoHTML);
				} else {
					$('#lightbox-text').html('<div class="card-text-info"><p>Card data not found</p></div>');
				}
				
				// Track which card is shown in lightbox
				window.currentLightboxCardId = cardId;
				console.log('Setting card lightbox image to:', imgSrc);
				$('#lightbox-img').attr('src', imgSrc);
				$('#lightbox').addClass('active');
			}
			function HideLightbox() { $('#lightbox').removeClass('active'); }
			$(document).on('click','#lightbox-close',HideLightbox);
			$(document).on('click','#lightbox',function(e){ if(e.target.id==='lightbox') HideLightbox(); });

			// Lightbox navigation over currently visible cards (respects filters)
			function GetVisibleCardIds() {
				var ids = [];
				$('#cardcontainer .card-item:visible .count-badge').each(function(){
					var id = parseInt($(this).attr('data-id'));
					if (!isNaN(id)) ids.push(id);
				});
				return ids;
			}

			function NavigateLightbox(delta) {
				if (typeof window.currentLightboxCardId === 'undefined' || window.currentLightboxCardId === null) return;
				
				// Use shop cards if viewing from shop, otherwise use main card list
				var visible = shopDisplayCardIds.length > 0 ? shopDisplayCardIds : GetVisibleCardIds();
				if (!visible.length) return;
				var idx = visible.indexOf(window.currentLightboxCardId);
				if (idx === -1) idx = 0; else idx = (idx + delta + visible.length) % visible.length;
				var nextId = visible[idx];
				if (typeof nextId !== 'undefined') {
					ShowLightbox(nextId);
				}
			}

			$(document).on('keydown', function(e){
				if (!$('#lightbox').hasClass('active')) return;
				if (e.key === 'ArrowLeft') { e.preventDefault(); NavigateLightbox(-1); }
				else if (e.key === 'ArrowRight') { e.preventDefault(); NavigateLightbox(1); }
				else if (e.key === 'Escape') { e.preventDefault(); HideLightbox(); }
			});

			var showingOnlySelected = false;
			var currentFilter = 'all'; // 'all', 'systemgateway', 'systemupdate2021'

			function ClearDeck() {
				json.cards = [];
				deckCounts = {};
				$("#deck").html('');
				UpdateCardCountsUI();
				deckModified = true;
				Parse();
				UpdatePlayDeckButtonState();
			}

			function CycleFilter() {
				if (currentFilter === 'all') {
					currentFilter = 'systemgateway';
					$('#filterdeck').html('FILTER:<br>SG');
				} else if (currentFilter === 'systemgateway') {
					currentFilter = 'systemupdate2021';
					$('#filterdeck').html('FILTER:<br>SU21');
				} else if (currentFilter === 'systemupdate2021') {
					currentFilter = 'elevation';
					$('#filterdeck').html('FILTER:<br>ELEV');
				} else {
					currentFilter = 'all';
					$('#filterdeck').html('FILTER:<br>ALL CARDS');
				}
				ApplyFilter();
			}

		function ApplyFilter() {
			$('#cardcontainer .card-item').each(function(){
				var cardId = parseInt($(this).find('.count-badge').attr('data-id'));
				var card = cardSet[cardId];
				if (!card) return;
				
				if (currentFilter === 'all') {
					$(this).show();
				} else {
					// Look up the card in cardData by code (cardId is the code)
					var cardCode = String(cardId);
					var cardInfo = null;
					if (cardData && cardData.data) {
						for (var i = 0; i < cardData.data.length; i++) {
							if (cardData.data[i].code === cardCode) {
								cardInfo = cardData.data[i];
								break;
							}
						}
					}
					
					if (cardInfo) {
						var packCode = cardInfo.pack_code || '';
						if (currentFilter === 'systemgateway' && packCode === 'sg') {
							$(this).show();
						} else if (currentFilter === 'systemupdate2021' && packCode === 'su21') {
							$(this).show();
						} else if (currentFilter === 'elevation' && packCode === 'elev') {
							$(this).show();							
						} else {
							$(this).hide();
						}
					} else {
						// If not found in cardData, hide it when filtering
						$(this).hide();
					}
				}
			});				// If also showing only selected cards, re-apply that filter
				if (showingOnlySelected) {
					$('#cardcontainer .card-item').each(function(){
						if ($(this).is(':visible')) {
							var id = parseInt($(this).find('.count-badge').attr('data-id'));
							var ct = deckCounts[id] || 0;
							if (ct === 0) $(this).hide();
						}
					});
				}
			}

			function ToggleOtherCards() {
				if (!showingOnlySelected) {
					// Hide cards with count 0 (respect current pack filter)
					ApplyFilter();
					$('#cardcontainer .card-item').each(function(){
						var id = parseInt($(this).find('.count-badge').attr('data-id'));
						var ct = deckCounts[id] || 0;
						if (ct === 0) $(this).hide();
					});
					$('#togglecards').text('SHOW UNSELECTED');
					showingOnlySelected = true;
				} else {
					// Show all cards allowed by current pack filter
					showingOnlySelected = false; // update flag BEFORE applying filter so UpdateCardCountsUI won't re-hide
					ApplyFilter(); // Re-apply the current filter
					$('#togglecards').text('HIDE UNSELECTED');
				}
			}

			function IdentityImageFromDeckString(compressed) {
				var oppjson = JSON.parse(
				  LZString.decompressFromEncodedURIComponent(compressed)
				);
				opponentdeckimg = "images/"+ChangeImageFileToJPG(cardSet[oppjson.identity].imageFile);
			}

			// DRBO6: Gauntlet mode - force runner as the player
			deckPlayer = runner;
			// Disable parameter-based player selection for gauntlet mode
			if (false && URIParameter("r") !== "" && URIParameter("p") !== "c") {
				deckPlayer = runner;
				var uric = URIParameter("c");
				if (uric) {
					opponentdeckstr = "c="+uric+"&";
					IdentityImageFromDeckString(uric);
				}					
			} else {
				var urir = URIParameter("r")
				if (urir) {
					opponentdeckstr = "r="+urir+"&";
					IdentityImageFromDeckString(urir);
				}
			}

			// If no opponent deck parameter was provided, choose a random precon from the opposite side
			if (opponentdeckstr === "") {
				var oppositePlayer = (deckPlayer === runner) ? corp : runner;
				var candidatePrecons = [];
				for (var pi=0; pi<preconDecks.length; pi++) {
					var pre = preconDecks[pi];
					var identCode = parseInt(pre.identity);
					if (cardSet[identCode] && cardSet[identCode].player === oppositePlayer) {
						candidatePrecons.push(pre);
					}
				}
				if (candidatePrecons.length > 0) {
					var chosen = candidatePrecons[Math.floor(Math.random() * candidatePrecons.length)];
					var oppDeckObj = { identity: parseInt(chosen.identity), cards: [] };
					for (var cc in chosen.cards) {
						if (!chosen.cards.hasOwnProperty(cc)) continue;
						var qty = chosen.cards[cc];
						for (var q=0; q<qty; q++) oppDeckObj.cards.push(parseInt(cc));
					}
					var oppCompressed = LZString.compressToEncodedURIComponent(JSON.stringify(oppDeckObj));
					var paramKey = (cardSet[oppDeckObj.identity].player === corp) ? "c" : "r";
					opponentdeckstr = paramKey + "=" + oppCompressed + "&";
					opponentdeckimg = "images/" + ChangeImageFileToJPG(cardSet[oppDeckObj.identity].imageFile);
				}
			}

			//generate available titles
			var titles = [];
			for (var i=0; i<cardSet.length; i++) {
				if (typeof cardSet[i] !== "undefined") {
					if (cardSet[i].player == deckPlayer && cardSet[i].cardType != 'identity') { 
						var setCardTitle = cardSet[i].title;
						titles.push(setCardTitle);
					}
				}
			}
			titles.sort();

			function WordAtCursor(remove=false) {
				var text = $(this).val();
				var start = $(this)[0].selectionStart - 1;
				var end = $(this)[0].selectionEnd;
				while (start > 0) {
					if (text[start] != "\n") {
						--start;
					} else {
						break;
					}                        
				}
				if (start > 0) ++start;
				while (end < text.length) {
					if (text[end] != "\n") {
						++end;
					} else {
						break;
					}
				}
				var currentWord = text.substr(start, end - start);
				if (remove) {
					$(this).val(text.slice(0, start)+text.slice(end));
					$(this)[0].selectionStart = start;
				}
				return currentWord;
			}

			function extractTerm( term ) {
			  //extract the term at current position
			  var wordAtCursor = WordAtCursor.call($("#deck"));
			  var justTheWord = wordAtCursor.match(/(\d* *)(.*)/)[2]; //actually the word can be multiple words
			  return justTheWord;
			}

			var dC = "r"; //deckchar is r for runner
			var oC = "c"; //opponentchar is c for corp
			if (deckPlayer == corp) {
				dC = "c"; //deckchar is c for corp
				oC = "r"; //opponentchar is r for runner
			}
			var playerIdentities = [];
			for (var i=0; i<cardSet.length; i++) {
				if (typeof cardSet[i] != 'undefined' &&  typeof cardSet[i].faction != 'undefined') {
					if (cardSet[i].cardType == 'identity') {
						if (deckPlayer == cardSet[i].player) playerIdentities.push(i);
					}
				}
			}

			function UpdateLaunchStrings() {
			  //console.log(json);
			  // Build deck object for URI: include metadata only if deck not modified
			  var deckForUri;
			  if (deckModified) {
				deckForUri = { identity: json.identity, cards: json.cards.slice() };
			  } else {
				deckForUri = {};
				for (var k in json) { if (Object.prototype.hasOwnProperty.call(json,k)) deckForUri[k] = json[k]; }
			  }
			  var string = JSON.stringify(deckForUri);
			  var compressed = LZString.compressToEncodedURIComponent(string);
			  var gauntletParam = URIParameter("g");
			  var updatedGauntletParam = gauntletParam;
			  
			  // Update gauntletState with current shop state if gauntlet mode is active
			  if (gauntletParam !== "") {
				try {
				  var gauntletState = JSON.parse(LZString.decompressFromEncodedURIComponent(gauntletParam));
				  gauntletState.shopPurchaseCount = shopPurchaseCount;
				  gauntletState.credits = gauntletCredits;
				  gauntletState.subset = gauntletCardCounts;
				  gauntletState.creditsWon = 0; // Reset creditsWon after applying to credits
				  updatedGauntletParam = LZString.compressToEncodedURIComponent(JSON.stringify(gauntletState));
				} catch (e) {
				  console.error("Failed to update gauntlet state with shop data:", e);
				  // Fall back to original parameter
				  updatedGauntletParam = gauntletParam;
				}
			  }
			  
			  var launchAddress = "engine.php?p=" + dC + "&" + opponentdeckstr + dC + "=" + compressed;
			  // Add gauntlet parameter if present
			  if (updatedGauntletParam !== "") {
				launchAddress += "&g=" + updatedGauntletParam;
			  }
			  // Build address for switching sides. If we already have an opponent deck, make it the active deck; otherwise use random
			  var existingOpponentCompressed = "";
			  if (opponentdeckstr !== "") {
				// opponentdeckstr format: sideKey=COMPRESSED&  (e.g. c=abcd123&)
				var eqIdx = opponentdeckstr.indexOf('=');
				var ampIdx = opponentdeckstr.indexOf('&');
				if (eqIdx > -1) {
				  existingOpponentCompressed = opponentdeckstr.substring(eqIdx+1, ampIdx > -1 ? ampIdx : opponentdeckstr.length);
				}
			  }
			  var opponentAddress;
			  if (existingOpponentCompressed) {
				// We are transitioning: new active side oC gets opponent deck; old active side becomes opponent
				opponentAddress = "decklauncher.php?p=" + oC + "&" + oC + "=" + existingOpponentCompressed + "&" + dC + "=" + compressed;
			  } else {
				// No opponent deck yet: keep previous behavior (random opponent) while passing current deck as opposite param
				opponentAddress = "decklauncher.php?p=" + oC + "&" + oC + "=random&" + dC + "=" + compressed;
			  }
			  $("#launch").prop("href", launchAddress);
			  $("#opponent").prop("href", opponentAddress);
			  var historyUrl = "gauntlet.php?" + opponentdeckstr + dC + "=" + compressed;
			  if (updatedGauntletParam !== "") {
				historyUrl += "&g=" + updatedGauntletParam;
			  }
			  history.replaceState(
				null,
				"Chiriboga",
				historyUrl
			  );
			}

			function UpdatePlayDeckButtonState() {
				// Calculate deck validity based on current state
				if (!json || !json.identity || !cardSet[json.identity]) {
					$('#launch').prop('disabled', true).attr('title', 'Select a valid identity first');
					return;
				}

				var deckSizeTarget = cardSet[json.identity].deckSize;
				var influenceLimit = cardSet[json.identity].influenceLimit;
				var totalCards = json.cards.length;
				var totalInfluence = 0;

				for (var i = 0; i < json.cards.length; i++) {
					var cardId = json.cards[i];
					if (cardSet[cardId].faction !== cardSet[json.identity].faction) {
						totalInfluence += cardSet[cardId].influence;
					}
				}

				var isValid = (totalCards >= deckSizeTarget) && (totalInfluence <= influenceLimit);
				var tooltipText = '';

				if (!isValid) {
					if (totalCards < deckSizeTarget) {
						tooltipText = 'Need ' + (deckSizeTarget - totalCards) + ' more card(s)';
					}
					if (totalInfluence > influenceLimit) {
						if (tooltipText) tooltipText += '\n';
						tooltipText += 'Over influence by ' + (totalInfluence - influenceLimit);
					}
				}

				$('#launch').prop('disabled', !isValid).attr('title', tooltipText);
			}

			var mouseDownCallback = function (ev) {
			  if (ev.which == 3) {
				//right
				var id = parseInt($(this).attr("data-id"));
				var line = parseInt($(this).attr("data-line"));
				var deckListArray = $("#deck").text().split("\n");
				var thisLineArray = deckListArray[line].split(" ");
				//if (thisLineArray[0] < 3) //limit to 3 of each
				//{
				thisLineArray[0]++;
				deckListArray[line] = thisLineArray.join(" ");
				$("#deck").text(deckListArray.join("\n"));
				$(this).append(
				  '<img src="images/' +
					ChangeImageFileToJPG(cardSet[id].imageFile) +
					'" style="margin-left: -120px; transform:rotate(' +
					(Math.random() * 10 - 5) +
					'deg);">'
				);
				json.cards.push(id); //just on the end is fine
				Parse();
				//}
			  }
			  if (ev.which == 1) {
				//left
				var id = parseInt($(this).attr("data-id"));
				var line = parseInt($(this).attr("data-line"));
				$(this).children().last().remove();
				var deckListArray = $("#deck").text().split("\n");
				var thisLineArray = deckListArray[line].split(" ");
				thisLineArray[0]--;
				if (thisLineArray[0] < 1) {
				  //none left, this will invalidate some data-line
				  $(".cardgroup").each(function () {
					var thisLine = parseInt($(this).attr("data-line"));
					if (thisLine > line) $(this).attr("data-line", thisLine - 1);
				  });
				  deckListArray.splice(line, 1);
				} else deckListArray[line] = thisLineArray.join(" ");
				$("#deck").text(deckListArray.join("\n"));
				json.cards.splice(json.cards.indexOf(id), 1); //remove the first-found is fine
				Parse();
			  }
			};

			// Helper: Select a precon for the given identity with priority order
			function SelectPreconForIdentity(identityId) {
				// Priority 1: Find all default precons matching identity
				var defaultPrecons = [];
				for (var i = 0; i < preconDecks.length; i++) {
					if (String(preconDecks[i].identity) === String(identityId) && preconDecks[i].useAsCustomDefault === true) {
						defaultPrecons.push(i);
					}
				}
				if (defaultPrecons.length > 0) {
					// Pick one randomly if multiple exist
					var randomIdx = defaultPrecons[RandomRange(0, defaultPrecons.length - 1)];
					return randomIdx;
				}
				
				// Priority 2: Find all non-default precons matching identity
				var nonDefaultPrecons = [];
				for (var i = 0; i < preconDecks.length; i++) {
					if (String(preconDecks[i].identity) === String(identityId) && preconDecks[i].useAsCustomDefault !== true) {
						nonDefaultPrecons.push(i);
					}
				}
				if (nonDefaultPrecons.length > 0) {
					// Pick one randomly if multiple exist
					var randomIdx = nonDefaultPrecons[RandomRange(0, nonDefaultPrecons.length - 1)];
					return randomIdx;
				}
				
				// Priority 3: No matching precon found
				return -1;
			}

			function GenerateDeck() {
			  var playerCards = [];
			  var countSoFar = []; //of each card (by index in playerCards)

			  //LOAD deck, if specified (as an LZ compressed JSON object containing .identity= and .cards=[], wth cards specified by number in the set)
			  var specifiedPlayerDeck = URIParameter(dC);
			  if (specifiedPlayerDeck != "" && specifiedPlayerDeck != "random") {
				json = JSON.parse(
				  LZString.decompressFromEncodedURIComponent(specifiedPlayerDeck)
				);
				if (typeof json.cards == 'undefined') json.cards = [];
				//support legacy (gateway) format by looping through .systemGateway and converting to 30000 + set number
				if (typeof json.systemGateway !== 'undefined') {
					for (var i=0; i<json.systemGateway.length; i++) {
						json.cards.push(30000+parseInt(json.systemGateway[i]));
					}
				}
				//also update the identity if it is legacy
				if (parseInt(json.identity) < 10001) json.identity = parseInt(json.identity) + 30000;
				//update select
				$("#identityselect option[value=" + json.identity + "]").prop(
				  "selected",
				  "selected"
				);
				$("#identity").prop(
				  "src",
				  GetImagePath(cardSet[json.identity].imageFile)
				);
				for (var i = 0; i < json.cards.length; i++) {
			      //increment count, add to playerCards if not present yet
			      var pci = playerCards.indexOf(json.cards[i]);
				  if (pci < 0) {
					pci = playerCards.length;
					playerCards.push(json.cards[i]);
					countSoFar[pci] = 1;
				  }
				  else countSoFar[pci]++;
				}
			  } //create a deck for this identity - try precon first, then random generation
			  else {
				// Try to find a matching precon with priority order
				var preconIdx = SelectPreconForIdentity(json.identity);
				if (preconIdx >= 0) {
					// Load from selected precon
					var precon = preconDecks[preconIdx];
					for (var cardCode in precon.cards) {
						var qty = precon.cards[cardCode];
						var cardIdx = parseInt(cardCode);
						if (typeof cardSet[cardIdx] !== 'undefined') {
							var pci = playerCards.indexOf(cardIdx);
							if (pci < 0) {
								pci = playerCards.length;
								playerCards.push(cardIdx);
								countSoFar[pci] = qty;
							} else {
								countSoFar[pci] += qty;
							}
						}
					}
				} else {
					// No matching precon - use random generation
					var cardsChosen = DeckBuild(cardSet[json.identity]);
					//convert generated deck into counts
					for (var i = 0; i < cardsChosen.length; i++) {
					  var pci = playerCards.indexOf(cardsChosen[i]);
					  if (pci < 0) {
						pci = playerCards.length;
						playerCards.push(cardsChosen[i]);
						countSoFar[pci] = 1;
					  }
					  else countSoFar[pci]++;
					}
				}
			  }

			  //populate deckCounts from generated deck
			  deckCounts = {};
			  for (var i=0;i<playerCards.length;i++) {
				var id = playerCards[i];
				if (countSoFar[i] > 0) {
					deckCounts[id] = countSoFar[i];
				}
			  }
			  json.cards = [];
			  for (var id in deckCounts) {
				for (var j=0;j<deckCounts[id];j++) json.cards.push(parseInt(id));
			  }
			  UpdateDeckTextareaFromCounts();
			  Parse();
			  UpdateCardCountsUI();
			}

			function Init() {
			  
			  //set up autosuggest
			  var autoMinLen = 1;
			  $("#deck").on("keydown", function (event) {
				if (event.which == 13) {
					//enter key
					$(this).autocomplete("option", "minLength", Infinity);
					return;
				}
				//known issue: this leaps to strange places when it is too large to fit onscreen...
				var newY = $(this).textareaHelper('caretPos').top + (parseInt($(this).css('font-size'), 10) * 1.5);
				var newX = $(this).textareaHelper('caretPos').left;
				var posString = "left+" + newX + "px top+" + newY + "px";
				$(this).autocomplete("option", "position", {
					my: "left top",
					at: posString,
					of: $(this),
				});
				var wordAtCursor = WordAtCursor.call($("#deck"));
				var minLen = $(this).val().length - wordAtCursor.length + autoMinLen; //since length check tests shole textarea
				var coefficient = wordAtCursor.match(/(\d* *)(.*)/)[1];
				if (coefficient) minLen += coefficient.length;
				$(this).autocomplete("option", "minLength", minLen);
			  });

			  $("#deck").autocomplete({
				minLength: autoMinLen,
				open: function( event, ui ) {
					//prevent up/down arrows from opening the menu
					return false;
				},
				source: function( request, response ) {
				  // delegate back to autocomplete, but extract the relevant term
				  response( $.ui.autocomplete.filter(
					titles, extractTerm( request.term ) ).slice(0,4) ); //limit number of results
				},
				select: function( event, ui ) {
					var wordAtCursor = WordAtCursor.call($("#deck"),true); //true removes it
					var coefficient = wordAtCursor.match(/(\d* *)(.*)/)[1];
					if (!coefficient) coefficient = "";
					var originalText = $(this).val();
					var curPos = $(this)[0].selectionStart;
					var backPart = originalText.slice(curPos);
					if ( backPart.length == 0 || backPart[0] != "\n") {
						backPart = "\n" + backPart;
					}
					$(this).val(originalText.slice(0, curPos)+coefficient+ui.item.value+backPart);
					Parse();
					return false; //prevent default action (would replace whole area)
				},
				autoFocus:true,
				focus: function( event, ui ) {
					return false; //prevent default action (would replace whole area)
				},
				delay:0,
			  });
			  
			  // Overrides the default autocomplete filter function to search only from the beginning of the string
			  $.ui.autocomplete.filter = function (array, term) {
				    term = Normalise(term);
					if (term.length < autoMinLen) array = []; //enforce min length
					var matcher = new RegExp("^" + $.ui.autocomplete.escapeRegex(term), "i");
					return $.grep(array, function (value) {
						value = Normalise(value);
						return matcher.test(value.label || value.value || value);
					});
			  };
			  
			  //click into list should close the autocomplete
			  $("#deck").on("click",function() {
				  $("#deck").autocomplete("close");
			  });
			  
			  //identity select will update stats but keep current deck
			  $("#identityselect").change(function () {
					json.identity = $("select#identityselect option:checked").val();
					$("#identity").prop(
						"src",
						GetImagePath(cardSet[json.identity].imageFile)
					);
					// In gauntlet mode, keep deckPlayer as runner (don't allow side switching)
					// The selected identity should always be a runner identity
					deckPlayer = runner;
					// Parse and update deck stats without changing the deck
					Parse();
					UpdatePlayDeckButtonState();
					// Update influence filter if it's currently active
					if (currentTypeFilter === 'influence') {
						ApplyTypeFilter();
					}
					// Run pool verification when identity changes
					VerifyCardPool();
			  });

			  //set up identity select
					  // Import deck from NetrunnerDB
					  function ImportDeckFromNRDB() {
						var url = prompt('Paste NetrunnerDB deck URL');
						if (!url) return;
						var m = url.match(/decklist\/([0-9a-f\-]+)/i);
						if (!m) { alert('Could not extract deck UUID from URL'); return; }
						var uuid = m[1];
						var apiUrl = 'https://netrunnerdb.com/api/2.0/public/decklist/' + uuid;
						$.getJSON(apiUrl)
							.done(function(resp){
								try {
									console.log('NRDB Response:', resp);
									var entry = (resp && resp.data && resp.data[0]) ? resp.data[0] : null;
									if (!entry) { alert('Decklist not found'); return; }
									console.log('Entry:', entry);
									var cardsObj = entry.cards || {};
									console.log('Cards object:', cardsObj);
									
									// Check if imported deck side matches current launcher mode
									// We're in corp mode if p=c OR if r parameter is empty (default is corp)
									var pParam = URIParameter("p");
									var rParam = URIParameter("r");
									var currentMode = (pParam === "c" || rParam === "") ? "corp" : "runner";
									var firstCardCode = Object.keys(cardsObj)[0];
									if (firstCardCode && window.cardCodeLookup && window.cardCodeLookup[firstCardCode]) {
										var importedSide = window.cardCodeLookup[firstCardCode].side_code;
										console.log('Current mode:', currentMode, 'Imported side:', importedSide);
										if ((currentMode === "corp" && importedSide === "runner") || 
										    (currentMode === "runner" && importedSide === "corp")) {
											alert('Cannot import ' + importedSide + ' deck. You are in ' + currentMode + ' mode. Click on "Set as Opponent" to switch sides.');
											return;
										}
									}
									
									// Find identity from cards - it should be in the cardsObj with type_code === 'identity'
									var identityCode = null;
									for (var code in cardsObj) {
										if (window.cardCodeLookup && window.cardCodeLookup[code] && window.cardCodeLookup[code].type_code === 'identity') {
											identityCode = code;
											break;
										}
									}
									console.log('Identity code found:', identityCode);
									
									// If identity present, switch identity in builder
									if (identityCode && window.cardCodeLookup && window.cardCodeLookup[identityCode]) {
										// find matching cardSet index for identity title
										var identTitle = window.cardCodeLookup[identityCode].title;
										console.log('Raw identity title from lookup:', identTitle);
										console.log('Character codes:', Array.from(identTitle).map(function(c){return c.charCodeAt(0);}).join(','));
										// Robust normalization function for titles
										function normalizeTitle(str) {
											if (!str) return '';
											try { str = str.normalize ? str.normalize('NFKC') : str; } catch(e) {}
											// Replace common smart quotes/apostrophes and whitespace variants
											str = str
												.replace(/\u2019|\u2032|\u02BC|\uFF07/g, "'") // apostrophes/primes
												.replace(/\u201C|\u201D|\u2033|\uFF02/g, '"') // double quotes
												.replace(/\u00A0/g, ' ') // non-breaking space to space
												.trim();
											return str;
										}
										// Normalize identity title
										identTitle = normalizeTitle(identTitle);
										console.log('Normalized identity:', identTitle);
										var foundIdentity = false;
										for (var i=0;i<cardSet.length;i++) {
											if (typeof cardSet[i] !== 'undefined' && cardSet[i].cardType === 'identity') {
												var cardTitle = normalizeTitle(cardSet[i].title);
												if (cardTitle === identTitle) {
													console.log('Found identity at index:', i, 'with title:', cardSet[i].title);
													// Set identity without triggering change yet
													$("#identityselect").val(i);
													$("#identity").prop("src", "images/" + ChangeImageFileToJPG(cardSet[i].imageFile));
													json.identity = i;
													deckPlayer = cardSet[i].player;
													// Refresh card list for new side
													RenderAllCardsList();
													// Repopulate precon dropdown to show matching decks for new identity
													if (typeof window.PopulatePreconDropdownForIdentity === 'function') {
														window.PopulatePreconDropdownForIdentity(i);
													}
													foundIdentity = true;
													break;
												}
											}
										}
										if (!foundIdentity) {
											console.log('Identity not found in cardSet. Available identities:');
											for (var i=0;i<cardSet.length;i++) {
												if (typeof cardSet[i] !== 'undefined' && cardSet[i].cardType === 'identity') {
													console.log('  -', cardSet[i].title);
												}
											}
										}
									}

								// Convert cards to deckCounts (skip identities)
								deckCounts = {};
								for (var code in cardsObj) {
									var qty = cardsObj[code];
									var info = window.cardCodeLookup ? window.cardCodeLookup[code] : null;
									if (!info) continue;
									if (info.type_code === 'identity') continue;
									// Normalize apostrophes (ʼ to ')
									var title = info.title.replace(/ʼ/g, "'");
									var cardId = GetCardIdFromTitle(title);
									if (cardId > -1) {
										deckCounts[cardId] = (deckCounts[cardId] || 0) + qty;
									}
								}
									// Fill deck display and trigger parse (this validates without generating new deck)
									UpdateDeckTextareaFromCounts();
									Parse();
								} catch(e) {
									console.error(e);
									alert('Error importing deck');
								}
							 })
							 .fail(function(){ alert('Failed to fetch decklist from NRDB'); });
						  }

						  // Bind NRDB import to the unified id used in UI
						  $('#importnrdb, #importdeckfromNRDB').off('click').on('click', ImportDeckFromNRDB);

													// Export current deck as a JS precon and download
													function ExportJSDeck() {
														try {
															if (!json || !json.identity || !Array.isArray(json.cards)) {
																alert('No deck loaded to export.');
																return;
															}
															var name = prompt('Enter a name for this JS deck:', 'My Precon Deck');
															if (!name) return;

															var counts = {};
															for (var i=0;i<json.cards.length;i++) {
																var id = json.cards[i];
																counts[id] = (counts[id]||0)+1;
															}

															var lines = [];
															lines.push('// Exported preconstructed deck');
															lines.push('registerPrecon({');
															lines.push('    name: ' + JSON.stringify(name) + ',');
															lines.push('    identity: ' + JSON.stringify(String(json.identity)) + ',');
															lines.push('    useAsCustomDefault: false,');
															lines.push('    deck_set: "none",');
															lines.push('    cards: {');
															var keys = Object.keys(counts).sort(function(a,b){return parseInt(a)-parseInt(b);} );
															for (var k=0;k<keys.length;k++) {
																var cardId = keys[k];
																var count = counts[cardId];
																var comment = '';
																if (cardSet[parseInt(cardId)] && cardSet[parseInt(cardId)].title) {
																	comment = '  // ' + cardSet[parseInt(cardId)].title;
																}
																lines.push('        ' + JSON.stringify(String(cardId)) + ': ' + count + ',' + comment);
															}
															// Remove trailing comma from the last card line
															for (var i=lines.length-1;i>=0;i--) {
																if (/^\s*\d/.test(lines[i]) || /:\s*\d+,/.test(lines[i])) {
																	lines[i] = lines[i].replace(/,\s*(\/\/.*)?$/,'$1');
																	break;
																}
															}
															lines.push('    }');
															lines.push('});');
															var content = lines.join('\n');

															var blob = new Blob([content], {type: 'application/javascript'});
															var a = document.createElement('a');
															var safeName = name.replace(/[^A-Za-z0-9 _.-]/g,'').trim() || 'deck';
															a.download = safeName + '.js';
															a.href = URL.createObjectURL(blob);
															document.body.appendChild(a);
															a.click();
															document.body.removeChild(a);
															URL.revokeObjectURL(a.href);
														} catch(e) {
															console.error(e);
															alert('Failed to export JS deck: ' + e.message);
														}
													}

													// Import a JS precon file and set it as the current deck (no eval)
													function ImportJSDeckFromText(text) {
														try {
															// Basic size guard
															if (!text || text.length > 200000) { // ~200KB limit
																alert('Deck file too large or empty.');
																return;
															}

															// Strip BOM and normalize line endings
															text = String(text).replace(/^\uFEFF/, '').replace(/\r\n?|\n/g, '\n');

															// Very conservative parse of registerPrecon({ ... }) without executing
															// 1) Find the registerPrecon call and capture the object literal body
															var callMatch = text.match(/registerPrecon\s*\(\s*\{([\s\S]*?)\}\s*\)\s*;?/);
															if (!callMatch) { alert('Invalid JS deck file format.'); return; }
															var body = callMatch[1];

															// 2) Extract name and identity as string or number
															var nameMatch = body.match(/\bname\s*:\s*(["'])([^"']*)\1/);
															var identityMatch = body.match(/\bidentity\s*:\s*(["']?)(\d+)\1/);
															var cardsMatch = body.match(/\bcards\s*:\s*\{([\s\S]*?)\}/);
															if (!identityMatch || !cardsMatch) { alert('Missing identity or cards in deck file.'); return; }
															var deckName = nameMatch ? nameMatch[2].trim() : 'Imported Deck';
															var identityId = parseInt(identityMatch[2], 10);
															var cardsBlock = cardsMatch[1];

															// 3) Parse cards block of lines like "30002": 1, // comment
															var cards = {};
															var lineRegex = /(["']?)(\d+)\1\s*:\s*(\d+)\s*,?/g;
															var m;
															var totalEntries = 0;
															while ((m = lineRegex.exec(cardsBlock)) !== null) {
																var cardId = parseInt(m[2], 10);
																var qty = parseInt(m[3], 10);
																if (qty > 0 && qty <= 99) { // reasonable bound
																	cards[cardId] = qty;
																	totalEntries++;
																	if (totalEntries > 1000) { alert('Too many card entries.'); return; }
																}
															}
															if (!totalEntries) { alert('No cards found in deck file.'); return; }

															// Validate identity exists
															if (!cardSet[identityId] || cardSet[identityId].cardType !== 'identity') {
																alert('Identity in deck file is not recognized.');
																return;
															}

															// Apply imported deck safely
															json = { identity: identityId, cards: [] };
															deckPlayer = cardSet[identityId].player;
															deckCounts = {};
															Object.keys(cards).forEach(function(idStr){
																var idNum = parseInt(idStr, 10);
																var qty = cards[idNum] || 0;
																// Ignore unknown card ids
																if (!cardSet[idNum]) return;
																deckCounts[idNum] = qty;
																for (var i=0;i<qty;i++) json.cards.push(idNum);
															});

															// Update identity select, image, and card list
															$('#identityselect').val(identityId);
															$('#identity').prop('src', 'images/' + ChangeImageFileToJPG(cardSet[identityId].imageFile));
															RenderAllCardsList();

															UpdateDeckTextareaFromCounts();
			UpdateCardCountsUI();
															UpdateCardCountsUI();
															alert('JS deck imported and loaded: ' + deckName);
														} catch(e) {
															console.error(e);
															alert('Failed to import JS deck: ' + e.message);
														}
													}

													// Wire the new buttons and file input
													(function(){
														var exportBtn = document.getElementById('exportjs');
														if (exportBtn) exportBtn.addEventListener('click', ExportJSDeck);
													})();

			// Load preconstructed deck
			function LoadPrecon() {
				var selectedIdx = parseInt($('#preconselect').val());
				if (selectedIdx < 0 || selectedIdx >= preconDecks.length) return;
				
				var precon = preconDecks[selectedIdx];
				
				// Convert identity code to cardSet index (code is used as index)
				var identityIdx = parseInt(precon.identity);
				
				if (typeof cardSet[identityIdx] === 'undefined') {
					alert('Identity not found in cardSet');
					return;
				}
				
				// Set identity
				$('#identityselect').val(identityIdx);
				$('#identity').prop('src', GetImagePath(cardSet[identityIdx].imageFile));
				json.identity = identityIdx;
				deckPlayer = cardSet[identityIdx].player;
				
				// Refresh card list for new side
				RenderAllCardsList();
				
				// Build deck from cards
				json.cards = [];
				deckCounts = {};
				
				for (var cardCode in precon.cards) {
					var qty = precon.cards[cardCode];
					var cardIdx = parseInt(cardCode); // Code is the index
					
					if (typeof cardSet[cardIdx] !== 'undefined') {
						deckCounts[cardIdx] = qty;
						for (var j = 0; j < qty; j++) {
							json.cards.push(cardIdx);
			}
		}
		}
		
		// Fill deck display and trigger parse
		UpdateDeckTextareaFromCounts();
				UpdateCardCountsUI();
				
				// Reset dropdown
				$('#preconselect').val('-1');
				// Add metadata and mark unmodified so URI includes it
				json.name = precon.name || '';
				if (precon.notes) json.notes = precon.notes;
				if (precon.URL) json.url = precon.URL;
				deckModified = false;
				UpdateLaunchStrings();
			}
			
			// DRBO6: Gauntlet Mode - disable precon dropdown for gauntlet
			if (false) { // Disabled for gauntlet
				$('#preconselect').off('change').on('change', function() {
					if ($(this).val() !== '-1') {
						LoadPrecon();
				}
			});
			}
		  // Call it initially (will be called again after setting runner mode)
		  // PopulateIdentityDropdown(); // DRBO6: Already called in $.getJSON callback
			  // Hide Export JS Deck by default; reveal via secret gesture
			  (function(){
				var exportBtn = document.getElementById('exportjs');
				if (exportBtn) exportBtn.style.display = 'none';
				var clickCount = 0;
				// When lightbox image is clicked, if it's the identity, count up to 6
				$(document).off('click._expsec','#lightbox-img').on('click._expsec','#lightbox-img', function(){
					var shownId = window.currentLightboxCardId;
					if (json && parseInt(json.identity) === shownId) {
						clickCount++;
						if (clickCount >= 6) {
							if (exportBtn) exportBtn.style.display = '';
						}
					} else {
						// Reset if not identity card
						clickCount = 0;
					}
				});
				// Reset counter when closing lightbox
				$(document).off('click._expsec_close','#lightbox-close, #lightbox').on('click._expsec_close','#lightbox-close, #lightbox', function(e){
					// Only reset if actually closing
					if (e.target.id === 'lightbox' || e.target.id === 'lightbox-close') {
						clickCount = 0;
					}
				});
			  })();
			  
			  // Mobile fix: Force a repaint after initial render to ensure all elements are visible
			  // Some mobile browsers skip painting absolute-positioned elements until scroll
			  setTimeout(function() {
			    // Force layout recalculation
			    var container = document.getElementById('cardcontainer');
			    if (container) {
			      var _ = container.offsetHeight; // Trigger layout
			      // Now force a repaint
			      $('#cardcontainer').css('opacity', '0.99');
			      requestAnimationFrame(function() {
			        $('#cardcontainer').css('opacity', '1');
			      });
			    }
			  }, 150);
		}

		function Normalise(src) {
			if (!src) return "";
			try { src = src.normalize ? src.normalize('NFKC') : src; } catch(e) {}
			// Map smart quotes/apostrophes and whitespace to plain ASCII
			src = src
				.replace(/\u2019|\u2032|\u02BC|\uFF07/g, "'") // apostrophes/primes
				.replace(/\u201C|\u201D|\u2033|\uFF02/g, '"') // double quotes
				.replace(/\u00A0/g, ' '); // non-breaking space
			return src.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
		}

			function GetCardIdFromTitle(title) {
			  var soughtCardTitle = Normalise(title);
			  //seek backwards so as to get the most recent version
			  for (var i=cardSet.length; i>-1; i--) {
				if (typeof cardSet[i] !== "undefined") {
				  var setCardTitle = Normalise(cardSet[i].title);
				  if (setCardTitle.length >= soughtCardTitle.length) {
					if (setCardTitle.substring(0,soughtCardTitle.length) === soughtCardTitle) {
						return i;
					}
				  }
				}
			  }
			  return -1;
			}

			function Parse() {
			  //disable launch while checking
			  $("#launch").prop("disabled", "disabled");
			  $("#launch").html("Checking...");
			  $("#output").html("");
			  //visual deck preview retired

			  //read from deckCounts (source of truth) and rebuild json.cards
			  var validDeck = true;
			  var totalCards = 0;
			  var totalInfluence = 0;
			  var totalAgendaPoints = 0; //only for corp
			  json.cards = [];
			  
			  for (var id in deckCounts) {
				var cardCount = deckCounts[id];
				if (cardCount > 0 && cardSet[id]) {
					if (cardSet[id].player == deckPlayer) {
						for (var j = 0; j < cardCount; j++) {
							json.cards.push(parseInt(id));
							totalCards++;
							if (cardSet[id].faction !== cardSet[json.identity].faction) totalInfluence += cardSet[id].influence;
							if (deckPlayer == corp && typeof cardSet[id].agendaPoints !== 'undefined') totalAgendaPoints += cardSet[id].agendaPoints;
						}
					} else {
						if (deckPlayer == runner) $("#output").append(cardSet[id].title + " is not a Runner card<br/>");
						else $("#output").append(cardSet[id].title + " is not a Corp card<br/>");
						validDeck = false;
					}
				}
			  }
			  UpdateCardCountsUI();
			  //done checking, permit launch if valid
			  var deckSizeTarget = cardSet[json.identity].deckSize;
			  var influenceLimit = cardSet[json.identity].influenceLimit;
			  var validityOutput = '<div class="deck-stats">';
			  // Cards stat
			  var cardsClass = 'deck-stat';
			  if (totalCards < deckSizeTarget) cardsClass += ' bad';
			  validityOutput += '<div class="'+cardsClass+'"><span class="stat-label">Cards:</span> '+totalCards+' / '+deckSizeTarget+'</div>';
			  // Influence stat
			  var infClass = 'deck-stat';
			  if (totalInfluence > influenceLimit) infClass += ' bad';
			  validityOutput += '<div class="'+infClass+'"><span class="stat-label">Influence:</span> '+totalInfluence+' / '+influenceLimit+'</div>';
			  // Corp agenda points
			  if (deckPlayer == corp) {
				var agendaMin = 2 * Math.floor(Math.max(totalCards,deckSizeTarget) / 5) + 2;
				var agendaMax = agendaMin + 1;
				var agClass = 'deck-stat';
				if (totalAgendaPoints < agendaMin || totalAgendaPoints > agendaMax) agClass += ' bad';
				validityOutput += '<div class="'+agClass+'"><span class="stat-label">Agenda Pts:</span> '+totalAgendaPoints+' ('+agendaMin+'-'+agendaMax+' required)</div>';
			  }
			  // Collection stat - count unique cards in gauntlet subset with faction breakdown
			  var collectionSize = 0;
			  var factionCounts = { 'Anarch': 0, 'Criminal': 0, 'Shaper': 0, 'Neutral': 0 };
			  for (var cardId in gauntletCardCounts) {
				collectionSize += gauntletCardCounts[cardId];
				if (cardSet[cardId]) {
					var faction = (cardSet[cardId].faction || '').toLowerCase();
					if (faction === 'anarch' || faction === 'anarch-runner') factionCounts['Anarch'] += gauntletCardCounts[cardId];
					else if (faction === 'criminal' || faction === 'criminal-runner') factionCounts['Criminal'] += gauntletCardCounts[cardId];
					else if (faction === 'shaper' || faction === 'shaper-runner') factionCounts['Shaper'] += gauntletCardCounts[cardId];
					else if (faction === 'neutral' || faction === 'neutral-runner') factionCounts['Neutral'] += gauntletCardCounts[cardId];
				}
			  }
			  var collectionDetailsHtml = '<div class="collection-details"><div><span>&gt; Anarch:</span><span>' + factionCounts['Anarch'] + ' cards</span></div><div><span>&gt; Criminal:</span><span>' + factionCounts['Criminal'] + ' cards</span></div><div><span>&gt; Shaper:</span><span>' + factionCounts['Shaper'] + ' cards</span></div><div><span>&gt; Neutral:</span><span>' + factionCounts['Neutral'] + ' cards</span></div></div>';
			  validityOutput += '<div class="collection-stat-wrapper collapsed"><div class="deck-stat collection-stat"><span class="stat-label">Collection:</span> '+collectionSize+' cards</div>' + collectionDetailsHtml + '</div>';
			  // Credits stat
			  validityOutput += '<div class="deck-stat"><span class="stat-label">Credits:</span> '+gauntletCredits+'</div>';
			  validityOutput += '</div>';
			  if (validDeck) {
				$("#output").html(validityOutput);
				$("#launch").prop("disabled", false);
			  } else {
				$("#output").html(validityOutput + $("#output").html());
			  }
		  $("#launch").html("PLAY<br>DECK");
		  UpdateLaunchStrings();
		  //update opponent display to show all opponent identity images
		  if (gauntletOpponents && gauntletOpponents.length > 0) {
			  var oppHTML = '' +
				'<div class="opponent-header"><span class="opponent-title">Opponents</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
				'<div class="opponent-body opponent-gallery">';
			  for (var i = 0; i < gauntletOpponents.length; i++) {
				  var opp = gauntletOpponents[i];
				  var oppIdentityId = opp.identity;
				  var oppImgSrc = 'images/glow_outline.png';
				  if (cardSet[oppIdentityId] && cardSet[oppIdentityId].imageFile) {
					  oppImgSrc = GetImagePath(cardSet[oppIdentityId].imageFile);
				  }
				  var defeatedClass = opp.hasbeendefeated ? ' defeated' : '';
				  oppHTML += '<img class="opponent-identity-img' + defeatedClass + '" src="' + oppImgSrc + '" data-identity-id="' + oppIdentityId + '"/>';
			  }
			  oppHTML += '</div>';
			  $("#opponentid").html(oppHTML).addClass('collapsed');
		  } else if (opponentdeckimg != "") {
			  var oppHTML = '' +
				'<div class="opponent-header"><span class="opponent-title">Opponent Deck</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
				'<div class="opponent-body"><img src="'+opponentdeckimg+'"/></div>';
			  $("#opponentid").html(oppHTML).addClass('collapsed');
		  } else {
			  var emptyOppHTML = '' +
				'<div class="opponent-header"><span class="opponent-title">Opponent Deck</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
				'<div class="opponent-body"><img src="images/glow_outline.png"/></div>';
			  $("#opponentid").html(emptyOppHTML).addClass('collapsed');
		  }
		}			//function for testing and debugging
			function TestGeneration(seed=0) {
				Math.seedrandom(seed);
				$('#identityselect').change();
				console.log(json.cards);
			}
			function TestGenerationBulk(start=0, end=100) {
				for (var j=start; j<=end; j++) {
					TestGeneration(j);
					//convert generated deck into counts
					var counts = {};
					for (var i = 0; i < json.cards.length; i++) {
						if (typeof counts[json.cards[i]] == 'undefined') counts[json.cards[i]]=1;
						else {
							counts[json.cards[i]]++;
							//report any over-amounts
							if (counts[json.cards[i]] > 3) console.log(j);
						}
					}
				}
			}
		</script>

	</head>


	<body class="deck-builder-page" onload="Init();">
		<div id="contentcontainer">
			<div id="dataentry">
				<div class="leftrow toprow">
					<select id="identityselect"></select>
					<img id="identity" src="images/glow_outline.png" onclick="if(json.identity) ShowLightbox(json.identity);" style="cursor:pointer;">
					<div class="rightpart">
						<div id="output">
						</div>
						<div id="opponentid"></div>
					</div>
				</div>
			<div class="leftrow buttons">
				<button id="launch" class="button button-red" onclick="if(!$(this).prop('disabled')) ShowSelectOpponentModal();">PLAY<br>DECK</button>
				<button id="buycards" onclick="ShowBuyCardsModal();" class="button">BUY/SELL<br>CARDS</button>
				<button id="addnoninfluence" onclick="AddNonInfluence();" class="button">ADD IN-<br>FACTION</button>
				<button id="cleardeck" onclick="ClearDeck();" class="button">CLEAR<br>DECK</button>
				<button id="sortbydeck" onclick="CycleSort();" class="button">SORT BY:<br>NAME</button>
				<button id="sortdeck" onclick="CycleTypeFilter();" class="button">FILTER:<br>ALL</button>
				<button id="togglecards" onclick="ToggleOtherCards();" class="button">HIDE UNSELECTED</button>
				<button id="exittomenu" onclick="window.location.href='index.php';" class="button">BACK TO MENU</button>
				<!-- DRBO6: Gauntlet Mode - hide Set as Opponent, Random Deck, Import NRDB, Load Precon -->
				<button id="opponent" class="button" onclick="window.location.href=$(this).prop('href');" style="display:none;">SET AS OPPONENT</button>
				<button id="randomdeck" onclick="GenerateRandomDeck();" class="button" style="display:none;">RANDOM<br>DECK</button>
			</div>
				<div class="leftrow toprow">
					<div class="deck-heading">CURRENT DECK:</div>
					<div id="deck" style="width:100%; border:1px solid #ccc; padding:10px; min-height:100px; overflow-y:auto; white-space:pre-wrap; word-break:break-word; background-color:#f9f9f9; font-family:monospace;"></div>
					<div style="margin-top:8px;">
						<button id="importdeckfromNRDB" class="button" type="button" style="display:none;">Import Deck from NRDB</button>
					</div>
					<div style="margin-top:8px; display:none;">
						<select id="preconselect" class="button" style="width:85%; display:block; margin:0 auto;">
							<option value="-1">Load Precon Deck</option>
						</select>
					</div>
	                <!-- DRBO6: Gauntlet Mode - hide export button -->
                <div style="margin-top:8px; display:none;">
                    <button id="exportjs" class="button" type="button" style="width:85%; display:block; margin:0 auto;">Export JS Deck</button>
                </div>
					<br/>
				</div>
				<br/>
			</div>
			<div id="cardcontainer">
			</div>
		</div>
		<!-- Lightbox container for enlarged card view -->
		<div id="lightbox">
			<div id="lightbox-content">
				<span id="lightbox-close">[CLOSE]</span>
				<div id="lightbox-body">
					<img id="lightbox-img" src="" alt="Card"/>
					<div id="lightbox-text"></div>
				</div>
			</div>
		</div>
	</body>
</html>