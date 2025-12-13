//CARD DEFINITIONS FOR ELEVATION
setIdentifiers.push('elev');

cardSet[35005] = {
  title: "Shred",
  imageFile: "35005.png",
  player: runner,
  faction: "Anarch",
  influence: 1,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 1,
  //Run any server. The first time the Corp would end that run, prevent the run from ending 
  //unless the Corp reveals and trashes X cards from HQ at random. X is equal to the number 
  //of cards in the root of the attacked server.
  usedThisRun: false,
  Enumerate: function () {
    return ChoicesExistingServers();
  },
  Resolve: function (params) {
    this.usedThisRun = false;
    MakeRun(params.server);
  },
  responseOnRunEnds: {
    Resolve: function () {
      this.usedThisRun = false;
    },
    automatic: true,
  },
  responsePreventableEndRun: {
    Enumerate: function () {
      //only fire once per run, and only if there's an attacked server
      if (this.usedThisRun) return [];
      if (attackedServer === null) return [];
      return [{}];
    },
    Resolve: function (params) {
      this.usedThisRun = true;
      var cardsInRoot = attackedServer.root.length;
      
      //if no cards in root, just prevent the end (Corp can't pay the "cost")
      if (cardsInRoot === 0) {
        Log("Shred prevents the run from ending");
        intended.endRun = false;
        return;
      }
      
      //if Corp doesn't have enough cards in HQ, they can't pay
      if (corp.HQ.cards.length < cardsInRoot) {
        Log("Shred prevents the run from ending (Corp cannot trash " + cardsInRoot + " cards from HQ)");
        intended.endRun = false;
        return;
      }
      
      //Corp chooses: let run continue OR trash X cards from HQ
      var choices = [
        { id: 0, label: "Let run continue", button: "Let run continue" },
        { id: 1, label: "Trash " + cardsInRoot + " cards from HQ", button: "Trash " + cardsInRoot + " from HQ" }
      ];
      
      var cardRef = this;
      function decisionCallback(decision) {
        if (decision.id === 0) {
          //Corp lets run continue
          Log("Shred prevents the run from ending");
          intended.endRun = false;
        } else {
          //Corp trashes X random cards from HQ
          var copyOfHQ = corp.HQ.cards.concat([]);
          Shuffle(copyOfHQ);
          var cardsToTrash = copyOfHQ.slice(0, cardsInRoot);
          
          //Reveal and trash each card
          var revealAndTrashNext = function(index) {
            if (index >= cardsToTrash.length) {
              //All done - run ends (intended.endRun stays true)
              Log("Corp trashed " + cardsInRoot + " cards from HQ; run ends");
              return;
            }
            Reveal(cardsToTrash[index], function() {
              Trash(cardsToTrash[index], false, function() {
                revealAndTrashNext(index + 1);
              }, cardRef);
            }, cardRef);
          };
          revealAndTrashNext(0);
        }
      }
      
      DecisionPhase(
        corp,
        choices,
        decisionCallback,
        "Shred",
        "Shred",
        this
      );
      
      //**AI code
      if (corp.AI != null) {
        //Generally prefer to let run continue unless:
        //- There are no agendas in HQ
        //- OR the cards to trash is small (1-2) and server is important
        var agendaInHQ = false;
        for (var i = 0; i < corp.HQ.cards.length; i++) {
          if (CheckCardType(corp.HQ.cards[i], ["agenda"])) {
            agendaInHQ = true;
            break;
          }
        }
        
        var choice = choices[0]; //default: let run continue
        
        //If no agenda in HQ and trashing is cheap, might as well end the run
        if (!agendaInHQ && cardsInRoot <= 2) {
          choice = choices[1];
        }
        //If this server has something valuable (agenda in root), really want to end the run
        var agendaInRoot = false;
        for (var i = 0; i < attackedServer.root.length; i++) {
          if (CheckCardType(attackedServer.root[i], ["agenda"])) {
            agendaInRoot = true;
            break;
          }
        }
        if (agendaInRoot && !agendaInHQ) {
          choice = choices[1]; //trash to protect the agenda
        }
        
        corp.AI.preferred = { title: "Shred", option: choice };
      }
    },
  },
  //AI: consider this for runs on remote servers with cards in root
  AIRunEventExtraPotential: function(server, potential) {
    //Most valuable against remotes with cards in root
    var cardsInRoot = server.root.length;
    if (cardsInRoot > 0) {
      //Extra value: can potentially get through one ETR
      return 0.3 + (0.1 * cardsInRoot);
    }
    //Still some value for central servers (protection against ETR)
    return 0.1;
  },
};

cardSet[35011] = {
  title: "Rent Rioters",
  imageFile: "35011.png",
  player: runner,
  faction: "Anarch",
  influence: 1,
  cardType: "resource",
  subTypes: ["Connection", "Seedy"],
  installCost: 2,
  //[click][click][click],[trash]: Gain 9[c].
  abilities: [
    {
      text: "Gain 9[c]",
      Enumerate: function () {
        if (!CheckActionClicks(runner, 3)) return [];
        return [{}];
      },
      Resolve: function (params) {
        SpendClicks(runner, 3);
        //false means trash cannot be prevented (because it's a cost)
        Trash(this, false, function(cardsTrashed) {
          GainCredits(runner, 9);
        }, this);
      },
    },
  ],
  AIWouldTrigger: function () {
    //trigger if we need money and have 3 clicks to spare
    if (runner.clickTracker >= 3 && Credits(runner) < 6) return true;
    //trigger on last turn opportunity (3 clicks remaining)
    if (runner.clickTracker == 3) return true;
    return false;
  },
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //always worth keeping as economy option
    return true;
  },
  AIEconomyInstall: function() {
    return 1; //priority 1 (yes install but there are better options)
  },
  AIEconomyTrigger: 2, //priority 2 (moderate - good payout but requires 3 clicks)
  AIPreferredInstallChoice: function (choices) {
    //don't install if this is last click or second-to-last click
    //since we need 3 clicks to use it after installing
    if (runner.clickTracker < 4) return -1; //don't install
    return 0; //do install
  },
};

cardSet[35034] = {
  title: "Side Hustle",
  imageFile: "35034.png",
  player: runner,
  faction: "Neutral",
  influence: 0,
  cardType: "resource",
  subTypes: ["Job"],
  installCost: 2,
  //When you install this resource and whenever a run begins, place 1[credit] on this resource.
  //When there are 6 or more hosted credits, take all credits from this resource, trash it, and draw 1 card.
  
  //Helper function to check and trigger the 6+ credits condition
  checkAndTriggerPayout: function() {
    if (Counters(this, "credits") >= 6) {
      var creditsToTake = this.credits;
      Log("Side Hustle pays out " + creditsToTake + " credits");
      TakeCredits(runner, this, creditsToTake);
      //Trash cannot be prevented since it's part of the triggered effect
      Trash(this, true, function(cardsTrashed) {
        Draw(runner, 1);
      }, this);
    }
  },
  
  automaticOnInstall: {
    Resolve: function (card) {
      if (card == this) {
        PlaceCredits(this, 1);
        this.checkAndTriggerPayout();
      }
    },
  },
  
  automaticOnRunBegins: {
    Resolve: function (server) {
      PlaceCredits(this, 1);
      this.checkAndTriggerPayout();
    },
  },
  
  AIEconomyInstall: function() {
    //Good early game economy card
    return 2; //priority 2 (moderate - it takes time to pay off but is efficient)
  },
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if we plan to run a lot
    //Check if we already have one installed
    for (var i = 0; i < runner.rig.resources.length; i++) {
      if (runner.rig.resources[i].title == "Side Hustle") {
        return false; //already have one, don't need another in hand
      }
    }
    return true;
  },
  AIPreferredInstallChoice: function (choices) {
    //Good to install early, but not on last click
    if (runner.clickTracker < 2) return -1; //don't install
    return 0; //do install
  },
};

cardSet[35079] = {
  title: "Flyswatter",
  imageFile: "35079.png",
  player: corp,
  faction: "Neutral",
  influence: 0,
  cardType: "ice",
  subTypes: ["Code Gate"],
  rezCost: 2,
  strength: 0,
  //When you rez this ice during a run against this server, purge virus counters.
  responseOnRez: {
    Enumerate: function (card) {
      if (card == this) {
        if (attackedServer !== null) {
          if (attackedServer == GetServer(this)) return [{}];
        }
      }
      return [];
    },
    Resolve: function (params) {
      Purge();
    },
  },
  //Subroutine: End the run.
  subroutines: [
    {
      text: "End the run.",
      Resolve: function () {
        EndTheRun();
      },
      visual: { y: 102, h: 16 },
    },
  ],
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    result.sr = [[["endTheRun"]]];
    return result;
  },
};