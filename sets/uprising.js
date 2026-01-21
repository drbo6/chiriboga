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

//Daily Casts (26094)
//Neutral Resource, cost 3, influence 0
//When you install this resource, load 8 credits onto it. When it is empty, trash it.
//When your turn begins, take 2 credits from this resource.
cardSet[26094] = {
  title: "Daily Casts",
  imageFile: "26094.png",
  player: runner,
  faction: "Neutral",
  influence: 0,
  cardType: "resource",
  subTypes: [],
  installCost: 3,
  
  //When you install this resource, load 8 credits onto it.
  automaticOnInstall: {
    Resolve: function (card) {
      if (card == this) LoadCredits(this, 8);
    },
  },
  
  //When your turn begins, take 2 credits from this resource.
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      if (CheckCounters(this, "credits", 2)) {
        TakeCredits(runner, this, 2);
        //When it is empty, trash it (use MoveCard to avoid phase change in automatic trigger)
        if (!CheckCounters(this, "credits", 1)) {
          Log(GetTitle(this, true) + " trashed (empty)");
          MoveCard(this, runner.heap);
          this.faceUp = true;
        }
      }
    },
    automatic: true,
  },
  
  //AI evaluation functions
  AIEconomyInstall: function () {
    //Excellent drip economy: pay 3, get 8 over 4 turns = 5 credit profit
    //High priority economy card
    return 3;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Always worth keeping - excellent economy card
    return true;
  },
};

//Self-modifying Code (26090)
//Shaper Program, cost 0, MU 2, influence 3
//2 credits, trash: Search your stack for 1 program. Install it. (Shuffle your stack after searching it.)
cardSet[26090] = {
  title: "Self-modifying Code",
  imageFile: "26090.png",
  player: runner,
  faction: "Shaper",
  influence: 3,
  cardType: "program",
  subTypes: [],
  installCost: 0,
  memoryCost: 2,
  
  abilities: [
    {
      text: "2[c], [trash]: Search your stack for 1 program. Install it.",
      Enumerate: function () {
        //Check if we can pay 2 credits
        if (!CheckCredits(runner, 2, "using", this)) return [];
        //Check if this card can be trashed (not prevented somehow)
        if (!CheckTrash(this)) return [];
        //Check if there are any programs in stack that can be installed
        var choices = ChoicesArrayInstall(runner.stack, false, function (card) {
          return CheckCardType(card, ["program"]);
        });
        if (choices.length == 0) return [];
        return [{}];
      },
      Resolve: function (params) {
        var cardRef = this;
        //Pay 2 credits as cost
        SpendCredits(runner, 2, "using", this, function () {
          //Trash self as cost (unpreventable since it's a cost)
          Trash(cardRef, false, function (cardsTrashed) {
            //Get installable programs from stack
            var choices = ChoicesArrayInstall(runner.stack, false, function (card) {
              return CheckCardType(card, ["program"]);
            });
            
            //**AI code
            if (runner.AI != null) {
              //Prefer icebreakers we don't have installed
              var installedRunnerCards = InstalledCards(runner);
              var preferredCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, installedRunnerCards);
              if (preferredCard) {
                for (var i = 0; i < choices.length; i++) {
                  if (choices[i].card == preferredCard) {
                    choices = [choices[i]];
                    break;
                  }
                }
              }
            }
            
            DecisionPhase(
              runner,
              choices,
              function (paramsB) {
                Shuffle(runner.stack);
                Log("Stack shuffled");
                //Install the selected program (paying normal costs, disallow cancel)
                Install(paramsB.card, paramsB.host, false, null, true, null, cardRef, null, null, false);
              },
              "Self-modifying Code",
              "Self-modifying Code",
              cardRef,
              "install"
            );
          }, cardRef);
        }, cardRef);
      },
      //AI: Determine when to use this ability
      AIWouldUse: function () {
        //Don't use outside of a run unless we really need a program
        if (!CheckRunning()) {
          //Only use outside run if we're desperate for a specific program
          return -1; //don't use outside of runs
        }
        
        //During a run, check if we need a breaker for current ice
        if (CheckEncounter()) {
          var ice = attackedServer.ice[approachIce];
          if (ice && ice.rezzed) {
            //Check if we can already break this ice
            var dominated = runner.AI._iceDominated(ice);
            if (!dominated) {
              //We can't break this ice - check if SMC can help
              var installedRunnerCards = InstalledCards(runner);
              var neededBreaker = runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, installedRunnerCards);
              if (neededBreaker) {
                //Check if the needed breaker can handle this ice
                return 5; //high priority - we need this breaker now!
              }
            }
          }
        }
        
        //During approach, might want to preemptively fetch
        if (CheckApproaching()) {
          var ice = attackedServer.ice[approachIce];
          if (ice && ice.rezzed) {
            var dominated = runner.AI._iceDominated(ice);
            if (!dominated) {
              return 4; //fetch before encounter
            }
          }
        }
        
        return -1; //don't use if not needed
      },
    },
  ],
  
  //AI evaluation functions
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if there are icebreakers in stack we might need
    if (spareMU >= 2) {
      var targetCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, installedRunnerCards);
      if (targetCard) return true;
    }
    return false;
  },
  
  //Get list of icebreakers that AI might tutor by this
  AIIcebreakerTutor: function (installedRunnerCards) {
    return runner.AI._icebreakerInPileNotInHandOrArray(runner.stack, installedRunnerCards);
  },
  
  //AI: Prefer to install SMC early to have tutor available
  AIPreferredInstallChoice: function (choices) {
    //Install if we have spare MU
    if (MemoryUnits() - InstalledMemoryCost() >= 2) return 0;
    return -1;
  },
  
  //AI: Can use this during runs to fetch needed breakers
  AIInstallBeforeRun: function (server, potential, useRunEvent, runCreditCost, runClickCost) {
    //Install before run if we might need to fetch a breaker mid-run
    if (MemoryUnits() - InstalledMemoryCost() >= 2) return 1;
    return 0;
  },
};

//DreamNet (26095)
//Neutral Resource: Virtual, cost 3, influence 0, unique
//The first time each turn you make a successful run, draw 1 card.
//If your identity is digital or you have at least 2 link, also gain 1 credit.
cardSet[26095] = {
  title: "DreamNet",
  imageFile: "26095.png",
  player: runner,
  faction: "Neutral",
  influence: 0,
  cardType: "resource",
  subTypes: ["Virtual"],
  installCost: 3,
  unique: true,
  
  //Track if triggered this turn
  triggeredThisTurn: false,
  
  //Reset flag at start of each turn
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      this.triggeredThisTurn = false;
    },
    automatic: true,
  },
  responseOnCorpTurnBegins: {
    Resolve: function () {
      this.triggeredThisTurn = false;
    },
    automatic: true,
  },
  
  //The first time each turn you make a successful run, draw 1 card.
  //If your identity is digital or you have at least 2 link, also gain 1 credit.
  responseOnRunSuccessful: {
    Resolve: function (params) {
      //Only trigger once per turn (check here since automatic ignores Enumerate)
      if (this.triggeredThisTurn) return;
      this.triggeredThisTurn = true;
      
      //Draw 1 card
      Draw(runner, 1);
      
      //Check for digital identity or 2+ link
      var isDigital = CheckSubType(runner.identityCard, "Digital");
      var hasEnoughLink = Link() >= 2;
      
      if (isDigital || hasEnoughLink) {
        GainCredits(runner, 1, "", this);
      }
    },
    automatic: true,
  },
  
  //AI evaluation functions
  AIEconomyInstall: function () {
    //Good drip card draw (and potentially credits)
    //Higher priority if we have 2+ link or digital identity
    var isDigital = CheckSubType(runner.identityCard, "Digital");
    var hasEnoughLink = Link() >= 2;
    if (isDigital || hasEnoughLink) return 3; //high priority - draw + credit
    return 2; //moderate priority - just draw
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Always worth keeping - solid card draw for runners who run
    return true;
  },
};