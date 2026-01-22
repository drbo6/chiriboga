//CARD DEFINITIONS FOR PARHELION
setIdentifiers.push('ph');

cardSet[33092] = {
  title: "Dr. Nuka Vrolyck",
  imageFile: "33092.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "resource",
  subTypes: ["Connection"],
  installCost: 1,
  unique: true,
  //When you install this resource, load 2 power counters onto it. 
  //When it is empty, trash it.
  //[click], hosted power counter: Draw 3 cards.
  
  automaticOnInstall: {
    Resolve: function (card) {
      if (card == this) {
        AddCounters(this, "power", 2);
      }
    },
  },
  
  //Click ability: spend 1 power counter to draw 3 cards
  abilities: [
    {
      text: "Draw 3 cards.",
      Enumerate: function () {
        if (!CheckActionClicks(runner, 1)) return [];
        if (!CheckCounters(this, "power", 1)) return [];
        return [{}];
      },
      Resolve: function (params) {
        SpendClicks(runner, 1);
        RemoveCounters(this, "power", 1);
        Draw(runner, 3, function() {
          //Check if empty and trash
          if (!CheckCounters(this, "power", 1)) Trash(this);
        }, this);
      },
    },
  ],
  
  //AI helper functions
  AIWouldUse: function () {
    //Use if we have counters and could benefit from cards
    if (!CheckCounters(this, "power", 1)) return -1;
    //Good to use if we have few cards in hand
    if (runner.grip.length <= 3) return 3;
    //Moderate priority otherwise
    return 1;
  },
  
  AIEconomyInstall: function () {
    //Good card draw, moderate priority
    return 2;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping - cheap card draw
    return true;
  },
};

cardSet[33087] = {
  title: "Flux Capacitor",
  imageFile: "33087.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "program",
  subTypes: ["Trojan"],
  installCost: 0,
  memoryCost: 1,
  unique: true,
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  //The first time you break a subroutine during each encounter with host ice,
  //you may charge 1 of your installed cards.
  //(Add 1 power counter to a card that already has one.)
  responseOnSubroutineBroken: {
    Enumerate: function (subroutine) {
      //Only during encounter with host ice
      if (!CheckEncounter()) return [];
      if (attackedServer.ice[approachIce] != this.host) return [];
      //Only first break this encounter
      if (this.hasTriggeredThisEncounter) return [];
      //Must have cards that can be charged
      var chargeChoices = ChoicesCharge(runner);
      if (chargeChoices.length == 0) return [];
      return [{}];
    },
    Resolve: function (params) {
      //Mark as triggered this encounter
      this.hasTriggeredThisEncounter = true;
      //Choose a card to charge
      var chargeChoices = ChoicesCharge(runner);
      DecisionPhase(
        runner,
        chargeChoices,
        function (params) {
          ChargeCard(params.card);
        },
        "Charge 1 of your installed cards",
        "card",
        this
      );
    },
    text: "Charge 1 of your installed cards",
  },
  //Reset flag when encounter ends
  responseOnEncounterEnds: {
    Resolve: function() {
      this.hasTriggeredThisEncounter = false;
    },
    availableWhenInactive: false,
    automatic: true,
  },
  AIPreferredInstallChoice: function (choices) {
    //Install on ice that doesn't already have a special breaker hosted
    var htsi = runner.AI._highestThreatScoreIce([this].concat(runner.AI._iceHostingSpecialBreakers()));
    for (var i = 0; i < choices.length; i++) {
      if (choices[i].host == htsi) return i;
    }
    return -1; //don't install if no good target
  },
  AIWorthKeeping: function() {
    //Worth keeping if we have cards with power counters
    var installedCards = InstalledCards(runner);
    for (var i = 0; i < installedCards.length; i++) {
      if (Counters(installedCards[i], "power") >= 1) return true;
    }
    return false;
  },
};

//Num (33073)
//Anarch Program: Icebreaker - Killer, cost 4, MU 1, strength 8, influence 3
//Interface → 2 credits: Break 1 sentry subroutine.
cardSet[33073] = {
  title: "Num",
  imageFile: "33073.png",
  player: runner,
  faction: "Anarch",
  influence: 3,
  cardType: "program",
  subTypes: ["Icebreaker", "Killer"],
  memoryCost: 1,
  installCost: 4,
  strength: 8,
  
  //Interface → 2 credits: Break 1 sentry subroutine.
  abilities: [
    {
      text: "Break 1 sentry subroutine.",
      Enumerate: function () {
        if (!CheckEncounter()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Sentry")) return [];
        if (!CheckCredits(runner, 2, "using", this)) return [];
        if (!CheckStrength(this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function (params) {
        SpendCredits(
          runner,
          2,
          "using",
          this,
          function () {
            Break(params.subroutine);
          },
          this
        );
      },
    },
  ],
  
  //AI: Fixed strength breaker - no boost ability
  AIFixedStrength: true,
  
  AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    //Args for ImplementIcebreaker: point, card, cardStrength, iceAI, iceStrength, iceSubTypes, costToUpStr, amtToUpStr, costToBreak, amtToBreak, creditsLeft
    result = result.concat(
      rc.ImplementIcebreaker(
        point,
        this,
        cardStrength,
        iceAI,
        iceStrength,
        ["Sentry"],
        999, //cost to boost strength (can't boost)
        0,   //amount to boost strength
        2,   //cost to break
        1,   //amount to break
        creditsLeft
      )
    );
    return result;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping - efficient fixed-strength killer
    //Check if we already have a killer
    for (var i = 0; i < installedRunnerCards.length; i++) {
      if (CheckSubType(installedRunnerCards[i], "Killer")) {
        //Already have a killer, less important
        return spareMU >= 2;
      }
    }
    //No killer yet - definitely keep
    return true;
  },
};

//K2CP Turbine (33090)
//Shaper Program, cost 4, MU 1, influence 4
//Each installed non-AI icebreaker gets +2 strength.
cardSet[33090] = {
  title: "K2CP Turbine",
  imageFile: "33090.png",
  player: runner,
  faction: "Shaper",
  influence: 4,
  cardType: "program",
  subTypes: [],
  memoryCost: 1,
  installCost: 4,
  
  //Each installed non-AI icebreaker gets +2 strength.
  modifyStrength: {
    Resolve: function (card) {
      //Only affect installed icebreakers that aren't AI
      if (card.player !== runner) return 0;
      if (!CheckInstalled(card)) return 0;
      if (!CheckSubType(card, "Icebreaker")) return 0;
      if (CheckSubType(card, "AI")) return 0;
      return 2;
    },
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if we have non-AI icebreakers installed or in hand
    var hasBreakers = false;
    for (var i = 0; i < installedRunnerCards.length; i++) {
      if (CheckSubType(installedRunnerCards[i], "Icebreaker") && 
          !CheckSubType(installedRunnerCards[i], "AI")) {
        hasBreakers = true;
        break;
      }
    }
    //Check hand for breakers too
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckSubType(runner.grip[i], "Icebreaker") && 
          !CheckSubType(runner.grip[i], "AI")) {
        hasBreakers = true;
        break;
      }
    }
    if (hasBreakers && spareMU >= 1) return true;
    return false;
  },
};