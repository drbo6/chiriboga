<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Chiriboga Deck Launcher</title>
		<link href="images/favicon.ico" rel="icon">
		<link rel="stylesheet" href="jquery/jquery-ui.css" />
		<link rel="stylesheet" href="style.css" />
		<link rel="manifest" href="manifest.json">
		<?php
		include 'cardrenderer/webfont.php';
		?>
		<script src="jquery/jquery-3.2.1.min.js"></script>
		<script src="jquery/jquery-ui.min.js"></script>
		<script src="jquery/textarea-helper.js"></script>
		<script src="deck/lz-string.min.js"></script>
		<script src="deck/seedrandom.min.js"></script>
		<script>
			//create some variables so we can load the card definitions
			var runner = {};
			var corp = {};
			var cardSet = []; //prepare to receive card definitions
			var setIdentifiers = []; //set identifiers
			//new globals for visual builder
			var deckCounts = {}; //cardId -> count
			var allCardIdsForPlayer = []; //cache of non-identity cards for current side
			var cardData = null; //loaded from carddata.json for lightbox display
		</script>

		<script>
		// Restore opponent accordion interactions
		(function(){
			// Lightbox for opponent deck list
			function ShowOpponentDeckLightbox(){
				if (!opponentdeckstr) return;
				var eqIdx = opponentdeckstr.indexOf('=');
				if (eqIdx < 0) return;
				var ampIdx = opponentdeckstr.indexOf('&', eqIdx+1);
				var compressed = opponentdeckstr.substring(eqIdx+1, ampIdx > -1 ? ampIdx : opponentdeckstr.length);
				if (!compressed || compressed === 'random') {
					$('#lightbox-img').attr('src', opponentdeckimg || 'images/glow_outline.png');
					$('#lightbox-text').html('<div class="card-text-info"><h2>Opponent Deck</h2><p>Deck will be generated at game start.</p></div>');
					$('#lightbox').addClass('active');
					return;
				}
				var oppJSON;
				try { oppJSON = JSON.parse(LZString.decompressFromEncodedURIComponent(compressed)); } catch(e) {}
				if (!oppJSON || !oppJSON.identity || !Array.isArray(oppJSON.cards)) return;
				var identImg = 'images/'+ChangeImageFileToJPG(cardSet[oppJSON.identity].imageFile);
				$('#lightbox-img').attr('src', identImg);
				var counts = {};
				for (var i=0;i<oppJSON.cards.length;i++) counts[oppJSON.cards[i]] = (counts[oppJSON.cards[i]]||0)+1;
				var grouped = {};
				for (var cid in counts) { if (cardSet[cid]) { var ct = cardSet[cid].cardType||'other'; (grouped[ct]=grouped[ct]||[]).push(cid);} }
				var order = ['identity','agenda','operation','event','asset','upgrade','hardware','program','resource','ice','other'];
				var lines = [];
				for (var gi=0; gi<order.length; gi++) { var g=order[gi]; if (!grouped[g]) continue; grouped[g].sort(function(a,b){return cardSet[a].title.localeCompare(cardSet[b].title);}); lines.push('['+g.toUpperCase()+']'); for (var k=0;k<grouped[g].length;k++){ var id=grouped[g][k]; lines.push(counts[id]+' '+cardSet[id].title);} lines.push(''); }
				var html = '<div class="card-text-info"><h2>'+cardSet[oppJSON.identity].title+'</h2><p class="card-type">Total Cards: '+oppJSON.cards.length+'</p><pre style="white-space:pre-wrap; font-size:13px; line-height:1.4;">'+lines.join('\n')+'</pre></div>';
				$('#lightbox-text').html(html);
				$('#lightbox').addClass('active');
			}
			$(document).on('click','#opponentid .opponent-body', ShowOpponentDeckLightbox);
			$(document).on('click','#opponentid .opponent-header', function(){
				var box = $('#opponentid'); box.toggleClass('collapsed');
			});
		})();
		</script>
		<?php
		echo '<script src="utility.js?' . filemtime('utility.js') . '"></script>';
		
		$sets = ["systemgateway","systemupdate2021","midnightsun","elevation"];
		if (isset($_GET['sets'])) {
			$sets = explode("-",preg_replace( "/[^a-zA-Z0-9-]/", "", $_GET['sets'] )); 
		}
		foreach ($sets as $set) {
			echo '<script src="sets/'.$set.'.js?' . filemtime('sets/'.$set.'.js') . '"></script>';
		}
		
		?>
		<script>
			var json = {};
			var opponentdeckstr = "";
			var opponentdeckimg = "";
			var uid = 0;
			var preconDecks = []; // List of precon decks loaded from files
			var deckModified = true; // true when user has edited deck so metadata (name/notes/url) should be stripped from URI
			
			// Function to register a precon deck
			function registerPrecon(deck) {
				preconDecks.push(deck);
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
			// Load card data from JSON
			$.getJSON('carddata/carddata.json', function(data) {
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
				var lines = [];
				for (var i=0;i<allCardIdsForPlayer.length;i++) {
					var id = allCardIdsForPlayer[i];
					var ct = deckCounts[id] || 0;
					if (ct>0) lines.push(ct+" "+cardSet[id].title);
				}
				$("#deck").val(lines.join("\n"));
			}

			function AddCardToDeck(id) {
				if (typeof deckCounts[id] === 'undefined') deckCounts[id]=0;
				deckCounts[id]++;
				json.cards.push(id);
				deckModified = true;
				UpdateDeckTextareaFromCounts();
				Parse();
				if (showingOnlySelected) ApplyFilter();
			}
			function RemoveCardFromDeck(id) {
				if (typeof deckCounts[id] === 'undefined' || deckCounts[id]===0) return;
				deckCounts[id]--;
				//remove one occurrence from json.cards
				var idx = json.cards.indexOf(id);
				if (idx>-1) json.cards.splice(idx,1);
				deckModified = true;
				UpdateDeckTextareaFromCounts();
				Parse();
				if (showingOnlySelected) ApplyFilter();
			}

			// Sort state and functions
			var currentSort = 'id'; // 'id', 'name', 'type', 'faction'

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

			function SortCardContainer() {
				var $container = $('#cardcontainer');
				var $cards = $container.children('.card-item').detach();
				
				   $cards.sort(function(a, b) {
					   var idA = parseInt($(a).attr('data-id'));
					   var idB = parseInt($(b).attr('data-id'));
					   var cardA = cardSet[idA];
					   var cardB = cardSet[idB];
					   if (!cardA || !cardB) return 0;
					   if (currentSort === 'id') {
						   return idA - idB;
					   } else if (currentSort === 'name') {
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
					   }
					   return 0;
				   });
				
				$container.append($cards);
			}

			function CycleSort() {
				if (currentSort === 'id') {
					currentSort = 'name';
					$('#sortdeck').html('SORT BY:<br>NAME');
				} else if (currentSort === 'name') {
					currentSort = 'type';
					$('#sortdeck').html('SORT BY:<br>TYPE');
				} else if (currentSort === 'type') {
					currentSort = 'faction';
					$('#sortdeck').html('SORT BY:<br>FACTION');
				} else {
					currentSort = 'id';
					$('#sortdeck').html('SORT BY:<br>ID');
				}
				SortCardContainer();
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
				for (var i=0;i<cardSet.length;i++) {
					if (typeof cardSet[i] !== 'undefined' && cardSet[i].player == deckPlayer && cardSet[i].cardType !== 'identity') {
						allCardIdsForPlayer.push(i);
						var imgSrc = 'images/'+ChangeImageFileToJPG(cardSet[i].imageFile);
						var cardHtml = '<div class="card-item" data-id="'+i+'">'
							+'<div class="count-badge" data-id="'+i+'">0</div>'
							+'<img class="card-image" loading="lazy" src="'+imgSrc+'" alt="'+cardSet[i].title+'" />'
							+'<div class="card-title">'+cardSet[i].title+'</div>'
							+'<div class="card-controls">'
								+'<button type="button" class="remove-btn" data-id="'+i+'">-</button>'
								+'<button type="button" class="add-btn" data-id="'+i+'">+</button>'
							+'</div>'
						+'</div>';
						$("#cardcontainer").append(cardHtml);
					}
				}
				AttachCardListEvents();
				UpdateCardCountsUI();
				// Apply current sort after rendering
				SortCardContainer();
			}

			function AttachCardListEvents() {
				$("#cardcontainer .add-btn").off('click').on('click',function(){ AddCardToDeck(parseInt($(this).attr('data-id'))); });
				$("#cardcontainer .remove-btn").off('click').on('click',function(){ RemoveCardFromDeck(parseInt($(this).attr('data-id'))); });
				$("#cardcontainer .card-item img").off('click').on('click',function(e){ 
					e.stopPropagation(); 
					var cardId = parseInt($(this).closest('.card-item').attr('data-id'));
					ShowLightbox(cardId); 
				});
			}

			function UpdateCardCountsUI() {
				$("#cardcontainer .count-badge").each(function(){
					var id = parseInt($(this).attr('data-id'));
					var ct = deckCounts[id] || 0;
					$(this).text(ct);
					$(this).toggleClass('has-copies', ct>0);
					// Apply darkening to cards not in deck
					$(this).closest('.card-item').toggleClass('not-in-deck', ct === 0);
				});
				// Keep filters in sync after counts change
				if (showingOnlySelected) {
					// Re-apply current pack filter first
					ApplyFilter();
					// Then hide any visible cards that have count 0
					$('#cardcontainer .card-item').each(function(){
						if ($(this).is(':visible')) {
							var id = parseInt($(this).find('.count-badge').attr('data-id'));
							var ct = deckCounts[id] || 0;
							if (ct === 0) $(this).hide();
						}
					});
				}
			}

			function ShowLightbox(cardId) {
				if (!cardSet[cardId]) return;
				
				var card = cardSet[cardId];
				var imgSrc = 'images/' + ChangeImageFileToJPG(card.imageFile);
				$('#lightbox-img').attr('src', imgSrc);
				
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
				
			// Card text - replace bracketed words with images (NSG SVG format)
			if (cardInfo.text) {
				var cardText = cardInfo.text.replace(/\[([^\]]+)\]/g, function(match, word) {
					var iconName = word.toUpperCase();
					// Special case: [trash] maps to TRASH_ABILITY
					if (iconName === 'TRASH') iconName = 'TRASH_ABILITY';
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
				var visible = GetVisibleCardIds();
				if (!visible.length) return;
				var idx = visible.indexOf(window.currentLightboxCardId);
				if (idx === -1) idx = 0; else idx = (idx + delta + visible.length) % visible.length;
				var nextId = visible[idx];
				if (typeof nextId !== 'undefined') {
					ShowLightbox(nextId);
				}
			}

			$(document).on('click', '#lightbox-prev', function(e){ e.stopPropagation(); NavigateLightbox(-1); });
			$(document).on('click', '#lightbox-next', function(e){ e.stopPropagation(); NavigateLightbox(1); });
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
				$("#deck").val('');
				UpdateCardCountsUI();
				deckModified = true;
				Parse();
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

			var deckPlayer = corp;
			if (URIParameter("r") !== "" && URIParameter("p") !== "c") {
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
			var setStr = "";
			// Default to all core sets including Midnight Sun if none specified
			if (URIParameter("sets") !== "") {
				setStr = "sets="+URIParameter("sets")+"&";
			} else {
				setStr = "sets=systemgateway-systemupdate2021-midnightsun-elevation&";
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
			  var launchAddress = "engine.php?p=" + dC + "&" + setStr + opponentdeckstr + dC + "=" + compressed;
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
				opponentAddress = "decklauncher.php?p=" + oC + "&" + setStr + oC + "=" + existingOpponentCompressed + "&" + dC + "=" + compressed;
			  } else {
				// No opponent deck yet: keep previous behavior (random opponent) while passing current deck as opposite param
				opponentAddress = "decklauncher.php?p=" + oC + "&" + setStr + oC + "=random&" + dC + "=" + compressed;
			  }
			  $("#launch").prop("href", launchAddress);
			  $("#opponent").prop("href", opponentAddress);
			  history.replaceState(
				null,
				"Chiriboga",
				"decklauncher.php?" + setStr + opponentdeckstr + dC + "=" + compressed
			  );
			}

			var mouseDownCallback = function (ev) {
			  if (ev.which == 3) {
				//right
				var id = parseInt($(this).attr("data-id"));
				var line = parseInt($(this).attr("data-line"));
				var deckListArray = $("#deck").val().split("\n");
				var thisLineArray = deckListArray[line].split(" ");
				//if (thisLineArray[0] < 3) //limit to 3 of each
				//{
				thisLineArray[0]++;
				deckListArray[line] = thisLineArray.join(" ");
				$("#deck").val(deckListArray.join("\n"));
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
				var deckListArray = $("#deck").val().split("\n");
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
				$("#deck").val(deckListArray.join("\n"));
				json.cards.splice(json.cards.indexOf(id), 1); //remove the first-found is fine
				Parse();
			  }
			};

			// Helper: Select a precon for the given identity with priority order
			function SelectPreconForIdentity(identityId) {
				// Priority 1: Find all default precons matching identity
				var defaultPrecons = [];
				for (var i = 0; i < preconDecks.length; i++) {
					if (String(preconDecks[i].identity) === String(identityId) && preconDecks[i].default === true) {
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
					if (String(preconDecks[i].identity) === String(identityId) && preconDecks[i].default !== true) {
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
				  "images/" + ChangeImageFileToJPG(cardSet[json.identity].imageFile)
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

			  //print into textarea
			  var deckText = "";
			  var numRows = 0;
			  for (var i = 0; i < countSoFar.length; i++) {
				if (countSoFar[i] > 0) {
				  if (numRows > 0) deckText += "\n";
				  deckText += countSoFar[i] + " " + cardSet[playerCards[i]].title;
				  numRows++;
				}
			  }
			  $("#deck").val(deckText);
			  $("#deck").prop("rows", numRows); //resize textarea height to fit
			  $("#deck").on("input propertychange paste", function(){ deckModified = true; Parse(); });
			  //initial deckCounts from generated deck
			  deckCounts = {};
			  for (var i=0;i<playerCards.length;i++) {
				var id = playerCards[i];
				deckCounts[id] = countSoFar[i];
			  }
			  json.cards = [];
			  for (var id in deckCounts) {
				for (var j=0;j<deckCounts[id];j++) json.cards.push(parseInt(id));
			  }
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
			  
			  //identity select will regenerate a deck if changed
			  $("#identityselect").change(function () {
								json.identity = $("select#identityselect option:checked").val();
								$("#identity").prop(
									"src",
									"images/" + ChangeImageFileToJPG(cardSet[json.identity].imageFile)
								);
								// Update deckPlayer to match selected identity's side
								deckPlayer = cardSet[json.identity].player;
								history.pushState(null, "Chiriboga", "decklauncher.php"); //so a random deck is generated
								GenerateDeck();
								// Repopulate precon dropdown to only show matching decks (if loaded)
								if (typeof window.PopulatePreconDropdownForIdentity === 'function') {
									window.PopulatePreconDropdownForIdentity(json.identity);
								}
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
									
									var lines = [];
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

								// Convert cards map to lines (skip identities)
								for (var code in cardsObj) {
									var qty = cardsObj[code];
									var info = window.cardCodeLookup ? window.cardCodeLookup[code] : null;
									if (!info) continue;
									if (info.type_code === 'identity') continue;
									// Normalize apostrophes (ʼ to ')
									var title = info.title.replace(/ʼ/g, "'");
									lines.push(qty + ' ' + title);
								}
									// Fill textarea and trigger parse (this validates without generating new deck)
									$("#deck").val(lines.join("\n"));
									$("#deck").prop("rows", lines.length); //resize textarea height to fit
									Parse();
								} catch(e) {
									console.error(e);
									alert('Error importing deck');
								}
							 })
							 .fail(function(){ alert('Failed to fetch decklist from NRDB'); });
						  }

						  // Bind NRDB import to the unified id used in UI
						  $('#importnrdb, #importdeck').off('click').on('click', ImportDeckFromNRDB);

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
															lines.push('    default: false,');
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
															Parse();
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
				$('#identity').prop('src', 'images/' + ChangeImageFileToJPG(cardSet[identityIdx].imageFile));
				json.identity = identityIdx;
				deckPlayer = cardSet[identityIdx].player;
				
				// Refresh card list for new side
				RenderAllCardsList();
				
				// Build deck from cards
				var lines = [];
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
						lines.push(qty + ' ' + cardSet[cardIdx].title);
					}
				}
				
				// Fill textarea and trigger parse
				$('#deck').val(lines.join('\n'));
				$('#deck').prop('rows', lines.length);
				Parse();
				UpdateCardCountsUI();
				
				// Reset dropdown
				$('#preconselect').val('-1');
				// Add metadata and mark unmodified so URI includes it
				json.name = precon.name || '';
				if (precon.Notes) json.notes = precon.Notes;
				if (precon.URL) json.url = precon.URL;
				deckModified = false;
				UpdateLaunchStrings();
			}
			
			$('#preconselect').off('change').on('change', function() {
				if ($(this).val() !== '-1') {
					LoadPrecon();
				}
			});
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
					shortTitle +
					"</option>\n"
				);
			  }
			  
			  // Clicking the identity image opens the lightbox for that identity
			  $('#identity').off('click').on('click', function() {
				  if (json && json.identity) {
					  ShowLightbox(parseInt(json.identity));
				  }
			  });

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
			  //choose an identity at random, unless a load string was specified
			  var specifiedPlayerDeck = URIParameter(dC);
			  if (specifiedPlayerDeck == "" || specifiedPlayerDeck == "random") {
				var randomIdentity =
				  playerIdentities[RandomRange(0, playerIdentities.length - 1)];
				$("#identityselect option[value=" + randomIdentity + "]")
				  .prop("selected", "selected")
				  .change(); //calling change also means a deck will be generated
			  } else GenerateDeck(); //this will recognise the input string and load it
			  //Render full card list for visual deck building
			  RenderAllCardsList();
			  UpdateCardCountsUI();
			  // After identities and UI are ready, populate precons for current identity
			  try {
			    var currentIdSel = $('#identityselect').val();
			    var effectiveId = currentIdSel || (json && json.identity);
			    if (effectiveId && typeof window.PopulatePreconDropdownForIdentity === 'function') {
			      window.PopulatePreconDropdownForIdentity(effectiveId);
			    }
			  } catch(e) { /* ignore */ }
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

			  //read the textarea and create the deck
			  var validDeck = true;
			  var totalCards = 0;
			  var totalInfluence = 0;
			  var totalAgendaPoints = 0; //only for corp
			  var outputLine = 0;
			  json.cards = [];
			  deckCounts = {};
		  var pre = $("#deck").val();
		  // Normalize common apostrophes/quotes in pasted text before parsing
		  pre = pre
			.replace(/\u2019|\u2032|\u02BC|\uFF07/g, "'")
			.replace(/\u201C|\u201D|\u2033|\uFF02/g, '"')
			.replace(/\u00A0/g, ' ');
		  var splitText = pre.split("\n");
			  for (var i = 0; i < splitText.length; i++) {
				var cardCount = 0;
				var cardTitle = "";
				var splitLine = splitText[i].split(" ");
				if (splitLine.length == 1) {
				  cardCount = 1;
				  cardTitle = splitLine[0];
				} else if (splitLine.length > 1) {
				  cardCount = parseInt(splitLine[0]);
				  cardTitle = splitLine.slice(1).join(" ");
				}
				if (cardCount > 0 && cardTitle != "") {
				  var id = GetCardIdFromTitle(cardTitle);
					if (id > -1) {
						if (cardSet[id].player == deckPlayer) {
							//update counts only
							deckCounts[id] = (deckCounts[id]||0) + cardCount;
							for (var j=0;j<cardCount;j++) {
								json.cards.push(id);
								totalCards++;
								if (cardSet[id].faction !== cardSet[json.identity].faction) totalInfluence += cardSet[id].influence;
								if (deckPlayer == corp && typeof cardSet[id].agendaPoints !== 'undefined') totalAgendaPoints += cardSet[id].agendaPoints;
							}
						} else {
							if (deckPlayer == runner) $("#output").append(cardTitle + " is not a Runner card<br/>");
							else $("#output").append(cardTitle + " is not a Corp card<br/>");
							validDeck = false;
						}
					} else {
						$("#output").append(cardTitle + " not found<br/>");
						validDeck = false;
					}
				}
			  }
			  UpdateCardCountsUI();
			  //done checking, permit launch if valid
			  if (validDeck) {
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
				validityOutput += '</div>';
				$("#output").html(validityOutput);
				$("#launch").prop("disabled", false);
			  }
		  $("#launch").html("PLAY<br>DECK");
		  UpdateLaunchStrings();
		  //update opponent image (accordion restored)
		  if (opponentdeckimg != "") {
			  var oppHTML = '' +
				'<div class="opponent-header"><span class="opponent-title">Opponent Deck</span><span class="opponent-arrow" aria-hidden="true">&#9660;</span></div>' +
				'<div class="opponent-body"><img src="'+opponentdeckimg+'"/></div>';
			  $("#opponentid").html(oppHTML).addClass('collapsed').show();
		  } else {
			  $("#opponentid").hide();
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


	<body onload="Init();">
		<div id="contentcontainer">
			<div id="dataentry">
				<div class="leftrow toprow">
					<select id="identityselect"></select>
					<img id="identity" src="images/glow_outline.png">
					<div class="rightpart">
						<div id="output">
						</div>
						<div id="opponentid"></div>
					</div>
				</div>
			<div class="leftrow buttons">
				<button id="launch" class="button button-red" onclick="window.location.href=$(this).prop('href');">PLAY<br>DECK</button>
				<button id="opponent" class="button" onclick="window.location.href=$(this).prop('href');">SET AS OPPONENT</button>
				<button id="randomdeck" onclick="GenerateRandomDeck();" class="button">RANDOM<br>DECK</button>
				<button id="cleardeck" onclick="ClearDeck();" class="button">CLEAR<br>DECK</button>
				<button id="sortdeck" onclick="CycleSort();" class="button">SORT BY:<br>ID</button>
				<button id="filterdeck" onclick="CycleFilter();" class="button">FILTER:<br>ALL CARDS</button>
				<button id="togglecards" onclick="ToggleOtherCards();" class="button">HIDE UNSELECTED</button>
				<button id="exittomenu" onclick="window.location.href='index.php';" class="button">BACK TO MENU</button>
			</div>
				<div class="leftrow toprow">
					<div class="deck-heading">CURRENT DECK:</div>
					<textarea id="deck" spellcheck="false" cols="30" style="width:100%;"></textarea>
					<div style="margin-top:8px;">
						<button id="importdeck" class="button" type="button">Import Deck from NRDB</button>
					</div>
					<div style="margin-top:8px;">
						<select id="preconselect" class="button" style="width:85%; display:block; margin:0 auto;">
							<option value="-1">Load Precon Deck</option>
						</select>
					</div>
					<div style="margin-top:8px;">
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
				<span id="lightbox-prev" aria-label="Previous">&#8249;</span>
				<span id="lightbox-next" aria-label="Next">&#8250;</span>
				<div id="lightbox-body">
					<img id="lightbox-img" src="" alt="Card"/>
					<div id="lightbox-text"></div>
				</div>
			</div>
		</div>
	</body>
</html>