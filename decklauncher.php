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
		<?php
		echo '<script src="utility.js?' . filemtime('utility.js') . '"></script>';
		
		$sets = ["systemgateway","systemupdate2021","midnightsun"];
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
				UpdateDeckTextareaFromCounts();
				Parse();
			}
			function RemoveCardFromDeck(id) {
				if (typeof deckCounts[id] === 'undefined' || deckCounts[id]===0) return;
				deckCounts[id]--;
				//remove one occurrence from json.cards
				var idx = json.cards.indexOf(id);
				if (idx>-1) json.cards.splice(idx,1);
				UpdateDeckTextareaFromCounts();
				Parse();
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
				
				$('#lightbox').addClass('active');
			}
			function HideLightbox() { $('#lightbox').removeClass('active'); }
			$(document).on('click','#lightbox-close',HideLightbox);
			$(document).on('click','#lightbox',function(e){ if(e.target.id==='lightbox') HideLightbox(); });

			var showingOnlySelected = false;
			function ToggleOtherCards() {
				if (!showingOnlySelected) {
					// Hide cards with count 0
					$('#cardcontainer .card-item').each(function(){
						var id = parseInt($(this).find('.count-badge').attr('data-id'));
						var ct = deckCounts[id] || 0;
						if (ct === 0) $(this).hide();
					});
					$('#togglecards').text('Show other cards');
					showingOnlySelected = true;
				} else {
					// Show all cards
					$('#cardcontainer .card-item').show();
					$('#togglecards').text('Hide other cards');
					showingOnlySelected = false;
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
			if (URIParameter("sets") !== "") setStr = "sets="+URIParameter("sets")+"&";

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
			  var string = JSON.stringify(json);
			  var compressed = LZString.compressToEncodedURIComponent(string);
			  var launchAddress = "engine.php?p=" + dC + "&" + setStr + opponentdeckstr + dC + "=" + compressed;
			  var opponentAddress = "decklauncher.php?p=" + oC + "&" + setStr + oC + "=random&" + dC + "=" + compressed;
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
			  } //create a random deck for this identity
			  else {
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
			  $("#deck").on("input propertychange paste", Parse);
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

						  $('#importdeck').off('click').on('click', ImportDeckFromNRDB);
			  for (var i = 0; i < playerIdentities.length; i++) {
				$("#identityselect").append(
				  "<option value=" +
					playerIdentities[i] +
					">" +
					cardSet[playerIdentities[i]].title +
					"</option>\n"
				);
			  }
			  
			  // Clicking the identity image opens the lightbox for that identity
			  $('#identity').off('click').on('click', function() {
				  if (json && json.identity) {
					  ShowLightbox(parseInt(json.identity));
				  }
			  });
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
			}

			function Normalise(src) {
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
		  var splitText = $("#deck").val().replace(/'/g,"'").replace(/ʼ/g,"'").split("\n");
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
		  $("#launch").html("Play using this deck");
		  UpdateLaunchStrings();
		  //update opponent image
		  if (opponentdeckimg != "") {
			  $("#opponentid").html('Opponent:<br><img src="'+opponentdeckimg+'"/>').show();
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
				<button id="launch" class="button" onclick="window.location.href=$(this).prop('href');">Play using this deck</button>
				<button id="opponent" class="button" onclick="window.location.href=$(this).prop('href');">Set as opponent</button>
				<button id="togglecards" onclick="ToggleOtherCards();" class="button">Hide other cards</button>
				<button id="exittomenu" onclick="window.location.href='index.php';" class="button">Back to Menu</button>
			</div>
				<div class="leftrow toprow">
					<textarea id="deck" spellcheck="false" cols="30" style="width:100%;"></textarea>
					<div style="margin-top:8px;">
						<button id="importdeck" class="button" type="button">Import Deck</button>
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
				<span id="lightbox-close">&times;</span>
				<div id="lightbox-body">
					<img id="lightbox-img" src="" alt="Card"/>
					<div id="lightbox-text"></div>
				</div>
			</div>
		</div>
	</body>
</html>
