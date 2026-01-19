//CARD DEFINITIONS FOR UPRISING
//Card ID range: 26066-26130
setIdentifiers.push('ur');

//Paladin Poemu (26073)
//Anarch Resource: Companion - Virtual, cost 1, influence 3
//When your turn begins and whenever you steal an agenda, place 1 credit on this resource.
//You can spend hosted credits to install non-connection cards.
//When your turn ends, if there are 3 or more hosted credits, trash 1 of your installed cards.
cardSet[26073] = {
  title: "Paladin Poemu",
  imageFile: "26073.png",
  player: runner,
  faction: "Anarch",
  influence: 3,
  cardType: "resource",
  subTypes: ["Companion", "Virtual"],
  installCost: 1,
  unique: true,
  
  //When your turn begins, place 1 credit on this resource.
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      PlaceCredits(this, 1);
      Log(GetTitle(this) + " places 1 credit (turn begins)");
    },
    automatic: true,
  },
  
  //Whenever you steal an agenda, place 1 credit on this resource.
  responseOnStolen: {
    Resolve: function (params) {
      PlaceCredits(this, 1);
      Log(GetTitle(this) + " places 1 credit (agenda stolen)");
    },
    text: "Paladin Poemu: Place 1 credit",
  },
  
  //You can spend hosted credits to install non-connection cards.
  canUseCredits: function (doing, card) {
    if (doing != "installing") return false;
    if (!card) return false;
    //Cannot spend on Connection cards
    if (CheckSubType(card, "Connection")) return false;
    return true;
  },
  
  //When your turn ends, if there are 3 or more hosted credits, trash 1 of your installed cards.
  responseOnRunnerDiscardEnds: {
    Enumerate: function () {
      //Only trigger if 3 or more hosted credits
      if (!this.credits || this.credits < 3) return [];
      //Must have at least one installed card to trash
      var installedCards = InstalledCards(runner);
      if (installedCards.length == 0) return [];
      return [{}];
    },
    Resolve: function () {
      var choices = ChoicesInstalledCards(runner);
      
      //AI decision-making for which card to trash
      if (runner.AI != null) {
        //Prefer to trash cards that are depleted or low value
        var bestTrashIndex = 0;
        var lowestValue = Infinity;
        
        for (var i = 0; i < choices.length; i++) {
          var card = choices[i].card;
          var value = 10; //base value
          
          //Prefer to trash cards with 0 counters that started with counters
          if (typeof card.power !== "undefined" && card.power == 0) {
            value = 1;
          }
          //Prefer to trash resources over programs/hardware
          if (card.cardType == "resource") {
            value -= 2;
          }
          //Don't trash icebreakers
          if (CheckSubType(card, "Icebreaker")) {
            value += 20;
          }
          //Don't trash consoles
          if (CheckSubType(card, "Console")) {
            value += 15;
          }
          //Factor in install cost - cheaper cards are more expendable
          if (typeof card.installCost !== "undefined") {
            value += card.installCost;
          }
          
          if (value < lowestValue) {
            lowestValue = value;
            bestTrashIndex = i;
          }
        }
        
        //Trash the selected card
        var cardToTrash = choices[bestTrashIndex].card;
        Log(GetTitle(this) + " forces trashing of " + GetTitle(cardToTrash));
        Trash(cardToTrash, true);
        return;
      }
      
      //Human player decision
      DecisionPhase(
        runner,
        choices,
        function (params) {
          Log(GetTitle(this) + " forces trashing of " + GetTitle(params.card));
          Trash(params.card, true);
        },
        "Paladin Poemu: Trash 1 of your installed cards",
        "card",
        this
      );
    },
    text: "Paladin Poemu: Trash 1 installed card (3+ hosted credits)",
  },
  
  //AI evaluation functions
  AIEconomyInstall: function () {
    //Good drip economy, moderate priority
    return 2;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Always worth keeping - good cheap drip economy
    return true;
  },
  
  AIPreferredInstallChoice: function (choices) {
    //Good to install early for economy
    //But be mindful of the 3-credit penalty
    return 0; //install it
  },
};