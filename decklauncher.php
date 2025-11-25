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
				$("#cardcontainer .card-item img").off('click').on('click',function(e){ e.stopPropagation(); ShowLightbox($(this).attr('src')); });
			}

			function UpdateCardCountsUI() {
				$("#cardcontainer .count-badge").each(function(){
					var id = parseInt($(this).attr('data-id'));
					var ct = deckCounts[id] || 0;
					$(this).text(ct);
					$(this).toggleClass('has-copies', ct>0);
				});
			}

			function ShowLightbox(src) { $('#lightbox-img').attr('src',src); $('#lightbox').addClass('active'); }
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
				history.pushState(null, "Chiriboga", "decklauncher.php"); //so a random deck is generated
				GenerateDeck();
			  });

			  //set up identity select
			  for (var i = 0; i < playerIdentities.length; i++) {
				$("#identityselect").append(
				  "<option value=" +
					playerIdentities[i] +
					">" +
					cardSet[playerIdentities[i]].title +
					"</option>\n"
				);
			  }
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
			  var splitText = $("#deck").val().replace("’","'").split("\n");
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
		<style>
		/* Card builder styles */
		    #cardcontainer { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,150px)); gap:12px; padding:16px; justify-content:start; overflow-x:auto; min-width:min-content; grid-auto-rows:min-content; }
			#cardcontainer .card-item { background:#1e2730; border:1px solid #2e3b46; border-radius:10px; padding:8px; position:relative; box-shadow:0 2px 4px rgba(0,0,0,.4); transition:box-shadow .15s; cursor:pointer; display:grid; grid-template-rows: auto minmax(30px, auto) auto; align-content:start; }
			#cardcontainer .card-item:hover { box-shadow:0 4px 10px rgba(0,0,0,.6); }
			#cardcontainer .card-item img { width:100%; height:auto; border-radius:6px; display:block; position:relative; }
			#cardcontainer .card-title { font-size:12px; line-height:1.2; margin-top:6px; color:#ddd; min-height:30px; max-height:44px; overflow:hidden; text-align:center; display:flex; align-items:center; justify-content:center; word-break:break-word; align-self:start; }
			#cardcontainer .card-controls { display:flex; justify-content:space-between; margin-top:6px; align-self:start; }
			#cardcontainer .card-controls button { flex:1; margin:0 2px; padding:8px 0; font-size:16px; cursor:pointer; background:#f1f1f1; color:#000; border:1px solid grey; border-radius:5px; font-weight:bold; transition:background .2s; }
			#cardcontainer .card-controls button:hover { background:grey; color:white; }
			#cardcontainer .count-badge { position:absolute; top:calc(50% - 15px); left:50%; transform:translate(-50%,-50%); background:rgba(0,0,0,0.85); color:#fff; padding:8px 14px; font-size:24px; font-weight:bold; border-radius:50%; min-width:50px; min-height:50px; display:flex; align-items:center; justify-content:center; pointer-events:none; opacity:0; transition:opacity .2s; z-index:2; }
			#cardcontainer .count-badge.has-copies { opacity:1; background:#1976d2; box-shadow:0 0 12px rgba(25,118,210,0.6); }
			/* Lightbox styles */
			#lightbox { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; }
			#lightbox.active { display:flex; }
			#lightbox-content { max-width:90%; max-height:90%; position:relative; }
			#lightbox-content img { max-width:100%; max-height:90vh; border-radius:10px; box-shadow:0 0 30px rgba(0,0,0,0.8); }
			#lightbox-close { position:absolute; top:-40px; right:0; font-size:36px; color:#fff; cursor:pointer; font-weight:bold; }
			#lightbox-close:hover { color:#ccc; }
			
			body {
			  background:#354149;
			  background-image: url('images/bg.jpg');
			  background-size:cover;
			  padding:0px;
			  margin:0px;
			}
			
			.button {
			  background: linear-gradient(135deg,#0a1f33 0%, #093b5e 50%, #0a1f33 100%);
			  border: 1px solid #0d6ea5;
			  color: #9fdcff;
			  padding: 12px 16px;
			  text-align: center;
			  text-decoration: none;
			  display: inline-block;
			  font-size: 15px;
			  margin: 4px 2px;
			  cursor: pointer;
			  border-radius: 8px;
			  letter-spacing: .06em;
			  font-weight:600;
			  text-transform:uppercase;
			  box-shadow:0 0 6px #0d6ea5, inset 0 0 12px rgba(13,110,165,0.4);
			  transition: box-shadow .25s, transform .15s, background .4s, color .3s;
			}
			.button:hover {
			  background: linear-gradient(135deg,#0c2843 0%, #0d507f 50%, #0c2843 100%);
			  color:#c7eeff;
			  box-shadow:0 0 12px #25b4ff, 0 0 30px rgba(37,180,255,.6), inset 0 0 22px rgba(37,180,255,0.55);
			  transform:translateY(-2px);
			}
			.button:active {
			  transform:translateY(1px);
			  box-shadow:0 0 6px #25b4ff, inset 0 0 14px rgba(37,180,255,.55);
			}
			.button:disabled {
			  background:linear-gradient(135deg,#1a2d42 0%, #1e3d58 60%, #1a2d42 100%);
			  color:#4d6d7f;
			  border-color:#1e3d58;
			  box-shadow:none;
			  cursor: default;
			}
			
		#contentcontainer {
		  display:flex;
		  flex-wrap:wrap;
		  gap:0;
		  width:100%;
		}
		
		#dataentry {
		  flex: 0 0 auto;
		  width: 340px;
		  min-width: 280px;
		  max-width: 100%;
		  box-sizing:border-box;
		}			#identityselect {
			  width:100%;
			  background:#162938;
			  border:1px solid #25b4ff;
			  color:#e6f8ff;
			  padding:10px 12px;
			  border-radius:8px;
			  font-size:15px;
			  box-shadow:0 0 6px #132a3a, inset 0 0 12px rgba(37,180,255,0.25);
			  box-sizing:border-box;
			}
			#identityselect:focus { box-shadow:0 0 12px #25b4ff, 0 0 30px rgba(37,180,255,.6), inset 0 0 22px rgba(37,180,255,0.55); outline:none; }
			#identityselect option { background:#162938; color:#e6f8ff; }

			#identity { width:100%; display:block; margin:12px 0 4px 0; border-radius:8px; box-shadow:0 0 12px rgba(37,180,255,.4); }

			#deck {
			  width:100%;
			  background: linear-gradient(135deg,#0a1f33 0%, #093b5e 50%, #0a1f33 100%);
			  border:1px solid #0d6ea5;
			  color:#9fdcff;
			  padding:10px 12px;
			  border-radius:8px;
			  font-size:14px;
			  line-height:1.3;
			  box-sizing:border-box;
			  resize:vertical;
			  box-shadow:0 0 6px #0d6ea5, inset 0 0 12px rgba(13,110,165,0.4);
			}
			#deck:focus { box-shadow:0 0 12px #25b4ff, 0 0 30px rgba(37,180,255,.6), inset 0 0 22px rgba(37,180,255,0.55); outline:none; }
			
			.leftrow.buttons {
				margin-bottom: 10px;
				display:flex;
				flex-wrap:wrap;
				gap:8px;
			}
			.leftrow.buttons .button {
				flex:1;
				min-width:140px;
			}
			
			.cardgroup {
				cursor:pointer;
			}
			
		.leftrow {
			padding:10px;
			width:100%;
			box-sizing:border-box;
		}			.toprow {
				padding-top:20px;
			}
			
			.rightpart {
				display:block;
				vertical-align:top;
				width:100%;
				padding-top:20px;
				color:#9fdcff;
				font-size:13px;
			}

			#cardcontainer { background:transparent; flex:1; min-width:0; }
			#cardcontainer .card-item { background:#0b141c; border:1px solid #132a3a; box-shadow:0 0 6px #132a3a, inset 0 0 10px rgba(19,42,58,0.4); }
			#cardcontainer .card-item:hover { box-shadow:0 0 12px #25b4ff, 0 0 26px rgba(37,180,255,.5), inset 0 0 16px rgba(37,180,255,0.45); }
			#cardcontainer .card-title { color:#cfe9f7; }
			#cardcontainer .card-controls button { background:linear-gradient(135deg,#0a1f33,#093b5e); border:1px solid #0d6ea5; color:#9fdcff; box-shadow:0 0 6px #0d6ea5, inset 0 0 12px rgba(13,110,165,0.4); }
			#cardcontainer .card-controls button:hover { background:linear-gradient(135deg,#0c2843,#0d507f); color:#c7eeff; box-shadow:0 0 12px #25b4ff, 0 0 30px rgba(37,180,255,.6), inset 0 0 22px rgba(37,180,255,0.55); }
			#cardcontainer .count-badge.has-copies { background:#0d507f; box-shadow:0 0 14px #25b4ff; }
			
			.ui-widget-content {
				background: #113;
			}

			/* Deck stats (cards / influence / agenda) */
			.deck-stats { margin-top:10px; background:linear-gradient(135deg,#0a1f33 0%, #093b5e 55%, #0a1f33 100%); border:1px solid #0d6ea5; padding:10px 14px; border-radius:10px; font-size:13px; box-shadow:0 0 6px #0d6ea5, inset 0 0 12px rgba(13,110,165,0.35); letter-spacing:.04em; }
			.deck-stats { width:100%; box-sizing:border-box; }
			.deck-stat { margin:4px 0; color:#9fdcff; font-weight:600; display:flex; justify-content:space-between; align-items:center; }
			.deck-stat.bad { color:#ff4d73; text-shadow:0 0 6px rgba(255,77,115,0.7), 0 0 12px rgba(255,77,115,0.35); }
			.deck-stat .stat-label { font-weight:700; opacity:.9; }
			
			#opponentid {
				display:none;
				margin-top: 20px;
				background:linear-gradient(135deg,#050f1a 0%, #041f33 55%, #050f1a 100%);
				border:1px solid #0a5580;
				padding:10px 14px;
				border-radius:10px;
				box-shadow:0 0 6px #0a5580, inset 0 0 12px rgba(10,85,128,0.35);
				color:#7ac5e8;
				font-weight:600;
				letter-spacing:.04em;
				text-align:center;
			}
			
			#opponentid img {
				width: 50px;
				margin: 8px 0 0 0;
				vertical-align: middle;
				border-radius:6px;
				box-shadow:0 0 8px rgba(122,197,232,0.3);
				display:block;
				margin-left:auto;
				margin-right:auto;
			}
			
		@media (max-width: 1024px) {
		  #cardcontainer { grid-template-columns:repeat(auto-fit,minmax(110px,140px)); gap:10px; padding:12px; }
		}
		
		@media (max-width: 768px) {
		  #contentcontainer { flex-direction: column; }
		  #dataentry { width:100%; max-width: 100%; float:none; }
		  #cardcontainer { grid-template-columns:repeat(auto-fit,minmax(100px,130px)); gap:8px; padding:10px; max-height:none !important; }
		  .leftrow.buttons .button { min-width:120px; font-size:13px; padding:10px 12px; }
		  #deck { font-size:13px; }
		  .deck-stats { font-size:12px; padding:8px 10px; }
		  #cardcontainer .card-title { font-size:11px; min-height:25px; }
		  #cardcontainer .count-badge { font-size:20px; min-width:42px; min-height:42px; padding:6px 10px; }
		}
		
		@media (max-width: 480px) {
		  #cardcontainer { grid-template-columns:repeat(auto-fit,minmax(90px,120px)) !important; gap:2px !important; padding:3px !important; row-gap:1px !important; grid-template-rows: repeat(2, min-content) !important; overflow-y:auto !important; max-height:calc(100vh - 20px) !important; }
		  .leftrow { padding:8px; }
		  .leftrow.buttons { gap:6px; }
		  .leftrow.buttons .button { min-width:100px; font-size:12px; padding:8px 10px; }
		  #identityselect, #deck { font-size:12px; padding:8px 10px; }
		  .deck-stats { font-size:11px; padding:6px 8px; }
		  #cardcontainer .card-title { font-size:9px !important; min-height:12px !important; max-height:20px !important; margin-top:1px !important; line-height:1.0 !important; padding:0 !important; }
		  #cardcontainer .card-item { padding:1px !important; grid-template-rows: auto 12px auto !important; }
		  #cardcontainer .count-badge { font-size:16px; min-width:34px; min-height:34px; padding:4px 6px; }
		  #cardcontainer .card-controls button { flex:1; margin:0 1px !important; padding:4px 0 !important; font-size:13px !important; min-height:24px !important; }
		  #cardcontainer .card-controls { margin-top:1px !important; gap:1px !important; }
		  #opponentid { font-size:12px; padding:8px 10px; }
		  #opponentid img { width:40px; }
		}
		
		@media (max-width: 360px) {
		  #dataentry { width:100%; min-width:100%; }
		  #cardcontainer { grid-template-columns:repeat(auto-fit,minmax(80px,110px)); gap:1px; padding:2px; row-gap:1px; }
		  .leftrow { padding:6px; }
		  .leftrow.buttons .button { min-width:80px; font-size:11px; padding:6px 8px; }
		  #identityselect, #deck { font-size:11px; padding:6px 8px; }
		  .deck-stats { font-size:10px; padding:4px 6px; }
		  #cardcontainer .card-title { font-size:8px; min-height:16px; max-height:28px; margin-top:2px; line-height:1.1; }
		  #cardcontainer .card-item { padding:2px; border-radius:6px; }
		  #cardcontainer .card-item img { border-radius:4px; }
		  #cardcontainer .count-badge { font-size:14px; min-width:28px; min-height:28px; padding:3px 5px; }
		  #cardcontainer .card-controls button { font-size:12px; padding:3px 0; border-radius:3px; }
		  #cardcontainer .card-controls { margin-top:2px; gap:1px; }
		}		</style>
	</head>


	<body onload="Init();">
		<div id="contentcontainer">
			<div id="dataentry" style="max-height: 100vh; overflow:auto;">
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
				<button id="exittomenu" onclick="window.location.href='index.php';" class="button">Exit</button>
			</div>
				<div class="leftrow toprow">
					<textarea id="deck" spellcheck="false" cols="30" style="width:100%;"></textarea><br/>
				</div>
				<br/>
			</div>
			<div id="cardcontainer">
			</div>
		</div>
		<!-- Lightbox container for enlarged card view -->
		<div id="lightbox"><div id="lightbox-content"><span id="lightbox-close">&times;</span><img id="lightbox-img" src="" alt="Card"/></div></div>
	</body>
</html>
