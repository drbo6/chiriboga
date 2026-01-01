//GUIDE FOR CARD DEFINITIONS

//IMPORTANT IMPLEMENTATION NOTE:
//Do not alter card properties directly. Instead use the relevant modifier function. That way effects can stack, be undone, prevented, etc.

//REQUIRED properties:
/*
.title
.player
.cardType //entirely lowercase e.g. agenda
*/

//IMPLEMENTATION properties
/*
.cardLocation
.installOnlyOn //a function(card) that returns true for cards that can host this
.canHost //a function(card) that returns true for cards that can be hosted on this
.recurringCredits //automatically replenished when installed and at start of your turn
.canUseCredits //function(doing,card) which returns true if .credits can be spent (doing is "using","installing","removing tags","trace","rezzing","playing","advancing","trashing","paying trash costs")
.activeForOpponent //has an ability the opponent can use
*/

//COMMON properties:
/*
.requireHumanInput //set true if you want acknowledgement even when there's only one option
.imageFile //if using a GUI i.e. CardRenderer
.advancementRequirement
.canBeAdvanced //will be set true for all agendas if not defined
.agendaPoints
.installCost
.memoryCost
.memoryUnits //makes more available for runner (not to be confused with memoryCost or hostingMU)
.playCost
.rezCost
.strength
.subTypes[] //formatted as printed on card including capital letters and spaces
.trashCost
.unique //if true, any other cards with the same .title will be trashed when this is installed
.link //included in runner's link strength
*/

//DON'T FORGET any custom properties you want to reset (e.g. on trash or return to hand) should be placed in cardPropertyResets
//TODO maybe replace these with generic names

//TRIGGERED callbacks:
//Note that conveniently the globalTriggers phase will call Enumerate and Resolve in the context of the card (rather than context of triggerCallback)
//Each has (unless specified otherwise):
// .Resolve(params) //parameter object will contain all the necessary properties
//And optionally also:
// .Enumerate() //returns array where each element is a legal set of parameters for .Resolve(params), assumed valid if Enumerate omitted
// .text
// .automatic //set true to have this fire before the others i.e. not on the resolution order list (usually used for things that are not actual effects on card, just implementation)
// .availableWhenInactive //set true to have this fire even when not active
//To implement non-automatic trigger phases, use TriggeredResponsePhase. You can achieve automatic during these phases by including the code in Enumerate (be aware it will be called multiple times)
//Player chooses resolution order when multiple trigger simultaneously (e.g. multiple cards have 'when turn begins')
/*
 .abilities[] //for operations and events the .Enumerate, .Resolve and .text are properties of the card itself not in abilities array
 .subroutines[] //unlike the others here, these do not use Enumerate or params (for decisionmaking, implement a pseudophase) and have .visual with y (centre) and h (height)
 
 For more callbacks, see trigger_standardising ods
*/

/**
 * Create a card instance from definition.<br/>New card.location is not set.<br/>Nothing is logged.
 *
 * @method InstanceCard
 * @param {int} setNumber index of the original definition to create instances from
 * @returns {Card} newly created instance
 */
function InstanceCard(
  setNumber,
  backTextures,
  glowTextures,
  strengthTextures = { ice: null, ib: null, broken: null, rc: null, crc: null, ctc: null }
) {
  var cardDefinition = cardSet[setNumber];
  if (typeof cardDefinition == 'undefined') {
	  //maybe it is a tutorial
	  cardDefinition = tutorial[setNumber];
  }
  if (typeof cardDefinition == 'undefined') {
	  //card not found in any set
	  return null;
  }
  var player = cardDefinition.player;
  cardDefinition.player = null; //unset to prevent recursion going nuts
  var card = jQuery.extend(true, {}, cardDefinition);
  cardDefinition.player = player; //restore now that recursion is done
  card.isCard = true;
  card.cardDefinition = cardDefinition; //save in case we need to compare against defaults later
  card.player = player;
  card.setNumber = setNumber;
  //Do some special initialisations
  if (card.cardType == "agenda" && typeof card.canBeAdvanced === "undefined")
    card.canBeAdvanced = true; //agendas can be advanced by default
  var costTexture = null;
  var trashCostTexture = null;
  if (card.player == runner) costTexture = strengthTextures.rc;
  else if (
    card.cardType == "ice" ||
    card.cardType == "asset" ||
    card.cardType == "upgrade"
  ) {
    costTexture = strengthTextures.crc;
    // Show trash cost for assets and upgrades if they have a trashCost
    if ((card.cardType == "asset" || card.cardType == "upgrade") && typeof card.trashCost !== "undefined")
      trashCostTexture = strengthTextures.ctc;
  }
  var strengthInfo = { texture: null, num: 0, ice: false, cost: costTexture, trashCost: trashCostTexture };
  if (typeof (card.strength !== "undefined")) {
    if (card.cardType == "ice")
      strengthInfo = {
        texture: strengthTextures.ice,
        num: card.strength,
        ice: true,
        brokenTexture: strengthTextures.broken,
        cost: costTexture,
        trashCost: trashCostTexture,
      };
    if (card.cardType == "program")
      strengthInfo = {
        texture: strengthTextures.ib,
        num: card.strength,
        ice: false,
        cost: costTexture,
        trashCost: trashCostTexture,
      };
  }

  //Create renderer object if relevant
  if (
    typeof card.imageFile !== "undefined" &&
    typeof backTextures !== "undefined" &&
    typeof glowTextures !== "undefined"
  ) {
    if (typeof cardDefinition.frontTexture === "undefined")
      cardDefinition.frontTexture = cardRenderer.LoadTexture(
        "images/" + ChangeImageFileToJPG(card.imageFile)
      );
    card.renderer = cardRenderer.CreateCard(
      card,
      cardDefinition.frontTexture,
      backTextures,
      glowTextures,
      strengthInfo
    );

    //create all the counters
    for (var i = 0; i < counterList.length; i++) {
      if (typeof card[counterList[i]] !== "undefined") card[counterList[i]] = 0;
      var counter = cardRenderer.CreateCounter(
        countersUI[counterList[i]].texture,
        card,
        counterList[i],
        1,
        true
      );
      counter.SetPosition(card.renderer.sprite.x, card.renderer.sprite.y);
      card.renderer.sprite.addChild(counter.sprite);
      card.renderer.sprite.addChild(counter.richText);
    }
  }
  return card;
}
/**
 * Create card instances from definition and push into an array. Returns an array of cards pushed.<br/>Nothing is logged.
 *
 * @method InstanceCardsPush
 * @param {int} setNumber index of the original definition to create instances from
 * @param {Card[]} destination array to push the Card instances into
 * @param {int} num number of copies of the card to add
 * @returns {Card[]} newly created instances
 */
function InstanceCardsPush(
  setNumber,
  destination,
  num,
  backTextures,
  glowTextures,
  strengthTextures = { ice: null, ib: null, broken: null, rc: null, crc: null, ctc: null }
) {
  var ret = [];
  //push a deep copy num times
  for (var i = 0; i < num; i++) {
    var card = InstanceCard(
      setNumber,
      backTextures,
      glowTextures,
      strengthTextures
    );
    destination.push(card);
    card.cardLocation = destination;
    ret.push(card);
  }
  return ret;
}

/**
 * Print the given array to the console in a human-readable format.<br/>Nothing is logged.
 *
 * @method PrintDeck
 * @param {Card} identity for deck
 * @param {Card[]} deck array to print
 */
function PrintDeck(identity, deck) {
  //group cards
  var sortedDeck = [];
  for (var i = 0; i < deck.length; i++) {
    var entryFound = -1;
    for (var j = 0; j < sortedDeck.length; j++) {
      if (sortedDeck[j].title == deck[i].title) {
        entryFound = j;
        break;
      }
    }
    if (entryFound > -1) sortedDeck[entryFound].count++;
    else sortedDeck.push({ title: deck[i].title, count: 1 });
  }
  //print
  var ret = [identity.title];
  for (var i = 0; i < sortedDeck.length; i++) {
    ret.push(sortedDeck[i].count + " " + sortedDeck[i].title);
  }
  console.log(ret);
}

/**
 * Set up Corp as a test field. Cards given as set indices in SystemGateway<br/>Nothing is logged.
 *
 * @method CorpTestField
 * @param {int} identity Corp identity card
 * @param {int[]} archivesCards cards in archives
 * @param {int[]} rndCards cards in R&D (leave empty to use default/loaded R&D)
 * @param {int[]} hqCards cards in HQ (leave empty to shuffle and draw five cards into HQ)
 * @param {int[]} archivesInstalled cards installed in front of archives or in its root
 * @param {int[]} rndInstalled cards installed in front of R&D or in its root
 * @param {int[]} hqInstalled cards installed in front of HQ or in its root
 * @param {int[][]} remotes remote servers, as cards installed in front or in root
 * @param {int[]} scored cards in Corp's score area
 */
function CorpTestField(
  identity,
  archivesCards,
  rndCards,
  hqCards,
  archivesInstalled,
  rndInstalled,
  hqInstalled,
  remotes,
  scored,
  cardBackTexturesCorp,
  glowTextures,
  strengthTextures
) {
  //hide an old identityCard if for some strange reason one exists (e.g. tutorial/testing)
  if (corp.identityCard) corp.identityCard.renderer.destinationPosition = -1000;
  //now make new one
  corp.identityCard = InstanceCard(
      identity,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
  );
  corp.identityCard.faceUp = true;
  for (var i = 0; i < archivesCards.length; i++) {
    InstanceCardsPush(
      archivesCards[i],
      corp.archives.cards,
      1,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    );
  }
  if (rndCards.length > 0) {
    while (corp.RnD.cards.length > 0) {
      RemoveFromGame(corp.RnD.cards[0]);
    }
    for (var i = 0; i < rndCards.length; i++) {
      InstanceCardsPush(
        rndCards[i],
        corp.RnD.cards,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    }
  }
  if (hqCards.length > 0) {
    for (var i = 0; i < hqCards.length; i++) {
      InstanceCardsPush(
        hqCards[i],
        corp.HQ.cards,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    }
    skipShuffleAndDraw = true;
    ChangePhase(phases.corpStartDraw);
  }
  for (var i = 0; i < archivesInstalled.length; i++) {
    if (cardSet[archivesInstalled[i]].cardType == "ice")
      InstanceCardsPush(
        archivesInstalled[i],
        corp.archives.ice,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    else
      InstanceCardsPush(
        archivesInstalled[i],
        corp.archives.root,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
  }
  for (var i = 0; i < rndInstalled.length; i++) {
    if (cardSet[rndInstalled[i]].cardType == "ice")
      InstanceCardsPush(
        rndInstalled[i],
        corp.RnD.ice,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    else
      InstanceCardsPush(
        rndInstalled[i],
        corp.RnD.root,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
  }
  for (var i = 0; i < hqInstalled.length; i++) {
    if (cardSet[hqInstalled[i]].cardType == "ice")
      InstanceCardsPush(
        hqInstalled[i],
        corp.HQ.ice,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    else
      InstanceCardsPush(
        hqInstalled[i],
        corp.HQ.root,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
  }
  for (var j = 0; j < remotes.length; j++) {
    var newServer = NewServer("Remote " + j, false);
    corp.remoteServers.push(newServer);
    for (var i = 0; i < remotes[j].length; i++) {
      if (cardSet[remotes[j][i]].cardType == "ice")
        InstanceCardsPush(
          remotes[j][i],
          newServer.ice,
          1,
          cardBackTexturesCorp,
          glowTextures,
          strengthTextures
        );
      else
        InstanceCardsPush(
          remotes[j][i],
          newServer.root,
          1,
          cardBackTexturesCorp,
          glowTextures,
          strengthTextures
        );
    }
  }
  for (var i = 0; i < scored.length; i++) {
    var newCard = InstanceCardsPush(
      scored[i],
      corp.scoreArea,
      1,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    )[0];
    newCard.faceUp = true;
  }
}

/**
 * Set up Runner as a test field. Cards given as set indices in SystemGateway<br/>Nothing is logged.
 *
 * @method RunnerTestField
 * @param {int} identity Runner identity card
 * @param {int[]} heapCards cards in heap
 * @param {int[]} stackCards cards in stack (leave empty to use default/loaded stack)
 * @param {int[]} gripCards cards in grip (leave empty to shuffle and draw five cards into grip)
 * @param {int[]} installed cards installed
 * @param {int[]} stolen cards in Runner's score area
 */
function RunnerTestField(
  identity,
  heapCards,
  stackCards,
  gripCards,
  installed,
  stolen,
  cardBackTexturesRunner,
  glowTextures,
  strengthTextures
) {
  if (runner.identityCard.title != "Tutorial") {
		runner.identityCard = InstanceCard(
		  identity,
		  cardBackTexturesRunner,
		  glowTextures,
		  strengthTextures
		);
		runner.identityCard.faceUp = true;
  }
  for (var i = 0; i < heapCards.length; i++) {
    var newCard = InstanceCardsPush(
      heapCards[i],
      runner.heap,
      1,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    )[0];
	newCard.faceUp = true;
  }
  if (stackCards.length > 0) {
    while (runner.stack.length > 0) {
      RemoveFromGame(runner.stack[0]);
    }
    for (var i = 0; i < stackCards.length; i++) {
      InstanceCardsPush(
        stackCards[i],
        runner.stack,
        1,
        cardBackTexturesRunner,
        glowTextures,
        strengthTextures
      );
    }
  }
  if (gripCards.length > 0) {
    for (var i = 0; i < gripCards.length; i++) {
      InstanceCardsPush(
        gripCards[i],
        runner.grip,
        1,
        cardBackTexturesRunner,
        glowTextures,
        strengthTextures
      );
    }
    skipShuffleAndDraw = true;
    ChangePhase(phases.runnerStartResponse);
  }
  for (var i = 0; i < installed.length; i++) {
    var dest = runner.rig.resources;
    if (cardSet[installed[i]].cardType == "program")
      dest = runner.rig.programs;
    else if (cardSet[installed[i]].cardType == "hardware")
      dest = runner.rig.hardware;
    var newCard = InstanceCardsPush(
      installed[i],
      dest,
      1,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    )[0];
    newCard.faceUp = true;
  }
  for (var i = 0; i < stolen.length; i++) {
    var newCard = InstanceCardsPush(
      stolen[i],
      runner.scoreArea,
      1,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    )[0];
    newCard.faceUp = true;
  }
}

//DECKS
var cardBackTexturesCorp = {};
var cardBackTexturesRunner = {};
var glowTextures = {};
var strengthTextures = {};
var specifiedMentor = URIParameter("mentor");
function LoadDecks() {
  //Special variables to store card back textures and strength and install cost textures
  var knownTexture = cardRenderer.LoadTexture("images/known.png");
  cardBackTexturesCorp = {
    back: cardRenderer.LoadTexture("images/Corp_back.png"),
    known: knownTexture,
  };
  cardBackTexturesRunner = {
    back: cardRenderer.LoadTexture("images/Runner_back.png"),
    known: knownTexture,
  };
  var strengthTextureIce = cardRenderer.LoadTexture("images/ice_strength.png");
  var strengthTextureIcebreaker = cardRenderer.LoadTexture(
    "images/ib_strength.png"
  );
  var subroutineBrokenTexture = cardRenderer.LoadTexture("images/broken.png");
  var runnerCostTexture = cardRenderer.LoadTexture("images/runner_cost.png");
  var corpRezCostTexture = cardRenderer.LoadTexture("images/corp_rez_cost.png");
  var corpTrashCostTexture = cardRenderer.LoadTexture("images/trash_cover.png");
  strengthTextures = {
    ice: strengthTextureIce,
    ib: strengthTextureIcebreaker,
    broken: subroutineBrokenTexture,
    rc: runnerCostTexture,
    crc: corpRezCostTexture,
    ctc: corpTrashCostTexture,
  };

  //And glow texture
  glowTextures = {
    zoomed: cardRenderer.LoadTexture("images/glow_white.png"),
    unzoomed: cardRenderer.LoadTexture("images/glow_white_cropped.png"),
    ice: cardRenderer.LoadTexture("images/glow_white_ice.png"),
  };

  //run the intro tutorial, if specified
  if (specifiedMentor != "") { //later, more options?
    runner.identityCard = InstanceCard(
      specifiedMentor,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
    corp.identityCard = InstanceCard(
      30077,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
	//rewind is not available during tutorials
	$('#rewind-select').hide();
	return;
  }

  var deckJson = {};
  $("#randomdeck").attr(
    "onclick",
    "window.location.href='decklauncher.php?"+(viewingPlayer==runner?"r":"c")+"=random';"
  );

  //*RUNNER*
  //LOAD Runner deck, if specified (as an LZ compressed JSON object containing .identity= and .cards=[], with cards specified by number in the set)
  var specifiedRunnerDeck = URIParameter("r");
  if (specifiedRunnerDeck != "") {
    deckJson = JSON.parse(
      LZString.decompressFromEncodedURIComponent(specifiedRunnerDeck)
    );
	//support legacy (gateway) format by looping through .systemGateway and converting to 30000 + set number
	if (typeof deckJson.systemGateway !== 'undefined') {
		if (typeof deckJson.cards == 'undefined') deckJson.cards = [];
		for (var i=0; i<deckJson.systemGateway.length; i++) {
			deckJson.cards.push(30000+parseInt(deckJson.systemGateway[i]));
		}
	}
	//also update the identity if it is legacy
	if (parseInt(deckJson.identity) < 10001) deckJson.identity = parseInt(deckJson.identity) + 30000;
    runner.identityCard = InstanceCard(
      deckJson.identity,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
    for (var i = 0; i < deckJson.cards.length; i++) {
      InstanceCardsPush(
        deckJson.cards[i],
        runner.stack,
        1,
        cardBackTexturesRunner,
        glowTextures,
        strengthTextures
      );
    }
  }
  //RUNNER RANDOM System Gateway Deck
  if (runner.stack.length == 0) {
    var runnerIdentities = [];
	for (var i=0; i<cardSet.length; i++) {
		if (typeof cardSet[i] != 'undefined' &&  typeof cardSet[i].faction != 'undefined') {
			if (cardSet[i].cardType == 'identity') {
				if (cardSet[i].player == runner) runnerIdentities.push(i);
			}
		}
	}
    deckJson.identity =
      runnerIdentities[RandomRange(0, runnerIdentities.length - 1)];
    runner.identityCard = InstanceCard(
      deckJson.identity,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
    deckJson.cards = DeckBuild(
	  runner.identityCard,
	  runner.stack,
      cardBackTexturesRunner,
      glowTextures,
      strengthTextures
    );
  }
  //whichever way the deck is built, update the "Edit this deck" link if the player is viewing as the runner
  if (viewingPlayer == runner) {
    var compressedDeckString = LZString.compressToEncodedURIComponent(
      JSON.stringify(deckJson)
    );
	var opponentDeckString = "";
	if (URIParameter("c")) opponentDeckString = "c="+URIParameter("c")+"&";
    $("#editdeck").attr(
      "onclick",
      "window.location.href='decklauncher.php?p=r&"+opponentDeckString+"r=" + compressedDeckString + "';"
    );
  }
  PrintDeck(runner.identityCard, runner.stack);

  //*CORP*
  //LOAD Corp deck, if specified (as an LZ compressed JSON object containing .identity= and .cards=[], wth cards specified by number in the set)
  deckJson = {};
  var specifiedCorpDeck = URIParameter("c");
  if (specifiedCorpDeck != "") {
    deckJson = JSON.parse(
      LZString.decompressFromEncodedURIComponent(specifiedCorpDeck)
    );
	//support legacy (gateway) format by looping through .systemGateway and converting to 30000 + set number
	if (typeof deckJson.systemGateway !== 'undefined') {
		if (typeof deckJson.cards == 'undefined') deckJson.cards = [];
		for (var i=0; i<deckJson.systemGateway.length; i++) {
			deckJson.cards.push(30000+parseInt(deckJson.systemGateway[i]));
		}
	}
	//also update the identity if it is legacy
	if (parseInt(deckJson.identity) < 10001) deckJson.identity = parseInt(deckJson.identity) + 30000;
    corp.identityCard = InstanceCard(
      deckJson.identity,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
    for (var i = 0; i < deckJson.cards.length; i++) {
      InstanceCardsPush(
        deckJson.cards[i],
        corp.RnD.cards,
        1,
        cardBackTexturesCorp,
        glowTextures,
        strengthTextures
      );
    }
  }
  
  //tutorial deck disables edit deck button
  if (deckJson.identity == 30076 || deckJson.identity == 30077) {
	  $('#editdeck').prop('disabled', true);
	  $('#editdeck').prop('title', "Tutorial decks cannot be edited");
  }
  
  //CORP RANDOM System Gateway Deck
  if (corp.RnD.cards.length == 0) {
    var corpIdentities = [];
	for (var i=0; i<cardSet.length; i++) {
		if (typeof cardSet[i] != 'undefined' &&  typeof cardSet[i].faction != 'undefined') {
			if (cardSet[i].cardType == 'identity') {
				if (cardSet[i].player == corp) corpIdentities.push(i);
			}
		}
	}
    deckJson.identity =
      corpIdentities[RandomRange(0, corpIdentities.length - 1)];
    corp.identityCard = InstanceCard(
      deckJson.identity,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    ); //note that card.location is not set for identity cards
    deckJson.cards = DeckBuild(
	  corp.identityCard,
	  corp.RnD.cards,
      cardBackTexturesCorp,
      glowTextures,
      strengthTextures
    );
  }
  //whichever way the deck is built, update the "Edit this deck" link if the player is viewing as the corp
  if (viewingPlayer == corp) {
    var compressedDeckString = LZString.compressToEncodedURIComponent(
      JSON.stringify(deckJson)
    );
	var opponentDeckString = "";
	if (URIParameter("r")) opponentDeckString = "r="+URIParameter("r")+"&";
    $("#editdeck").attr(
      "onclick",
      "window.location.href='decklauncher.php?p=c&"+opponentDeckString+"c=" + compressedDeckString + "';"
    );
  }
  PrintDeck(corp.identityCard, corp.RnD.cards);

  // Log decoded parameters (r, c, g)
  var decodedR = URIParameter("r");
  var decodedC = URIParameter("c");
  var decodedG = URIParameter("g");
  
  if (decodedR) {
    try {
      console.log("Decoded r parameter:", JSON.parse(LZString.decompressFromEncodedURIComponent(decodedR)));
    } catch(e) {
      console.log("Could not decode r parameter");
    }
  }
  if (decodedC) {
    try {
      console.log("Decoded c parameter:", JSON.parse(LZString.decompressFromEncodedURIComponent(decodedC)));
    } catch(e) {
      console.log("Could not decode c parameter");
    }
  }
  if (decodedG) {
    try {
      console.log("Decoded g parameter:", JSON.parse(LZString.decompressFromEncodedURIComponent(decodedG)));
    } catch(e) {
      console.log("Could not decode g parameter");
    }
  }

  // // PASTE REPLICATION CODE HERE (and/or customise code below)
  // // UNCOMMENTING (use CTRL+/ in VS Code) THE CODE BELOW WILL SET UP A TEST FIELD
  // // SET P=R OR P=C IN THE URL TO VIEW AS RUNNER OR CORP RESPECTIVELY
  // // ----------------------------------------------------------------------------
  // // You can enable the debug menu at the top of init.js

  if (true) { // Use this to easily disable everything below

     debugging = true; //set true to log extra details and pause execution on error
     viewAllFronts = true; //set true to see all card fronts (for testing)
     mainLoopDelay = 50; //for speedy AI vs AI testing (any faster than this and funny things happen at end-of-game)

    // SET UP THE MAIN STATES FOR THE RUNNER AND CORP
    // ----------------------------------------------

    RunnerTestField(1017, //identity
      [30032, 30032, 35009, 35008], //heapCards
      [35015, 35022, 35022, 30033, 35014, 1039, 35030, 35005, 35016, 35034, 35004, 35010, 35007, 35009, 35008, 35029, 35025], //stackCards
      [1030, 1030, 1030, 1030, 1030, 1040, 1035], //gripCards
      [35028, 30015, 35009, 35020], //installed 
      [], //stolen
      cardBackTexturesRunner,glowTextures,strengthTextures
    );

    CorpTestField(35069, //identity
      [30037, 30047,30073,35075,30074], //archivesCards
      [30073,30072,30047,30073,30073,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,30039,35044,35044,35044,35045], //rndCards
      [35072,35040,35082,35037], //hqCards
      [], //archivesInstalled
      [35041], //rndInstalled
      [35042], //hqInstalled
      [[35070,30072,30072,35075, 35063],[30068,35042,35052, 31075],[35070, 35053],[35061, 35053, 35076],[35062, 35079]], //remotes (array of arrays)
      [35070, 30068], //scored
      cardBackTexturesCorp,glowTextures,strengthTextures
    );

    // // REZ ICE
    // // -------

    // corp.archives.ice[0].rezzed=true;
    // corp.RnD.ice[0].rezzed=true;
    // corp.HQ.ice[0].rezzed=true;
    corp.remoteServers[4].root[0].rezzed=true;
    // corp.remoteServers[0].ice[0].rezzed=true;
    // corp.remoteServers[0].ice[1].rezzed=true;
    // corp.remoteServers[0].ice[2].rezzed=true;
    // corp.remoteServers[1].ice[0].rezzed=true;
    // corp.remoteServers[1].ice[2].rezzed=true;
    // corp.remoteServers[2].ice[0].rezzed=true;
    // corp.remoteServers[2].ice[1].rezzed=true;
    // corp.remoteServers[0].root[0].knownToRunner=true;
    // corp.archives.ice[0].rezzed=true;
    
    // // SET ADVANCEMENTS
    // // ----------------

    corp.remoteServers[3].root[0].advancement=2; // root/agenda/asset
    corp.remoteServers[3].ice[1].advancement=3; // ice
    
    // // GIVE EVERYONE SOME CREDITS TO START WITH
    // // ----------------------------------------

    GainCredits(runner,25);
    GainCredits(corp,25);
    
    // // SET THE PHASE
    // // -------------

    ChangePhase(phases.runnerStartResponse); // Runner starts turn
    // ChangePhase(phases.corpStartDraw);    

    // // OTHER STUFF
    // // -----------

    // ChangePhase(phases.runnerEndOfTurn);
    AddTags(3);
    // runner.clickTracker = 0;  
    // runner.rig.resources[0].power = 4;
    // corp.clickTracker = 6;
    // ChangePhase(phases.corpActionMain);
    // ChangePhase(phases.corpDiscardStart);
    // MakeRun(corp.RnD);
    // attackedServer = corp.RnD;
    // ChangePhase(phases.runApproachServer); //i.e. skip all the ice

    // // RUN REMOTE
    // // ----------

    attackedServer = corp.remoteServers[4];    
    MakeRun(corp.remoteServers[4]); // Run remote

    // // INSTALL TROJAN (requires setting it on the ice and then hosting it)
    // // -------------------------------------------------------------------

    corp.remoteServers[0].ice[2].hostedCards = [];
    InstanceCardsPush(30004, corp.remoteServers[0].ice[2].hostedCards, 1, cardBackTexturesCorp, glowTextures, strengthTextures)[0].host = corp.remoteServers[0].ice[2];
    corp.remoteServers[0].ice[2].hostedCards[0].virus = 2;
  
    // // TO FORCE THE AI TO PLAY CARDS / MAKE DECISIONS, set preferred property on runner.AI or corp.AI
    // // ----------------------------------------------------------------------------------------------
    
    // runner.AI.preferred = { 
    //   command: "play",
    //   cardToPlay: runner.grip[0],
    //   nextPrefs: {
    //     chooseServer: corp.remoteServers[0]
    //   }
    // }
  
  }

}
