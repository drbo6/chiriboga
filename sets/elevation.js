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
  runningWithThis: false,
  usedThisRun: false,
  Enumerate: function () {
    return ChoicesExistingServers();
  },
  Resolve: function (params) {
    this.runningWithThis = true;
    this.usedThisRun = false;
    MakeRun(params.server);
  },
  responseOnRunEnds: {
    Resolve: function () {
      this.runningWithThis = false;
      this.usedThisRun = false;
    },
    automatic: true,
  },
  responsePreventableEndRun: {
    Enumerate: function () {
      //only fire for the Shred that initiated this run
      if (!this.runningWithThis) return [];
      //only fire once per run
      if (this.usedThisRun) return [];
      if (attackedServer === null) return [];
      return [{}];
    },
    Resolve: function (params) {
      this.usedThisRun = true;
      var cardsInRoot = attackedServer.root.length;
      
      //if no cards in root, X=0, Corp trivially satisfies condition by trashing nothing
      if (cardsInRoot === 0) {
        //intended.endRun stays true, run ends normally
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
              //Check card is still in HQ before trashing (could have moved due to another effect)
              if (corp.HQ.cards.includes(cardsToTrash[index])) {
                Trash(cardsToTrash[index], false, function() {
                  revealAndTrashNext(index + 1);
                }, cardRef);
              } else {
                //Card already moved, continue to next
                revealAndTrashNext(index + 1);
              }
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
      //Trash is unpreventable since it's part of the mandatory triggered effect
      Trash(this, false, function(cardsTrashed) {
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

cardSet[35014] = {
  title: "Clean Getaway",
  imageFile: "35014.png",
  player: runner,
  faction: "Criminal",
  influence: 2,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 3,
  //Run any server. If successful, gain 6[credit].
  runningWithThis: false,
  Enumerate: function () {
    return ChoicesExistingServers();
  },
  Resolve: function (params) {
    this.runningWithThis = true;
    MakeRun(params.server);
  },
  responseOnRunSuccessful: {
    Resolve: function () {
      if (!this.runningWithThis) return;
      GainCredits(runner, 6);
    },
    automatic: true,
  },
  responseOnRunEnds: {
    Resolve: function () {
      this.runningWithThis = false;
    },
    automatic: true,
  },
  //AI: use for easy runs where success is likely
  AIRunEventExtraPotential: function(server, potential) {
    //Requires successful run - don't use if Crisium Grid is known
    if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
    //Only use if there are no unrezzed ice (to ensure success)
    for (var i = 0; i < server.ice.length; i++) {
      if (!server.ice[i].rezzed) return 0; //unrezzed ice might end the run
    }
    //Net gain is 3 credits (pay 3, gain 6) - slightly better than Dirty Laundry
    return 0.6; //slightly higher than Dirty Laundry's 0.5
  },
};

cardSet[35030] = {
  title: "Chromatophores",
  imageFile: "35030.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "program",
  subTypes: ["Trojan"],
  installCost: 1,
  memoryCost: 1,
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  //Host ice gains barrier, code gate, and sentry
  modifySubTypes: {
    Resolve: function (card) {
      if (card == this.host) return { add: ["Barrier", "Code Gate", "Sentry"] };
      return {}; //no modification to subtypes
    },
    automatic: true,
  },
  AIPreferredInstallChoice: function (choices) {
    //Prefer to install on ice that we can't currently break
    var iceToExclude = [];
    var installedCards = InstalledCards(corp);
    for (var i = 0; i < installedCards.length; i++) {
      var iceCard = installedCards[i];
      if (CheckCardType(iceCard, ["ice"]) && PlayerCanLook(runner, iceCard)) {
        //Exclude ice we can already break
        if (runner.AI._matchingBreakerInstalled(iceCard, [this])) {
          iceToExclude.push(iceCard);
        }
      }
    }
    
    //Target the highest threat ice that doesn't already have a special breaker hosted
    var htsi = runner.AI._highestThreatScoreIce([this].concat(runner.AI._iceHostingSpecialBreakers()).concat(iceToExclude));
    if (htsi) {
      //Find it in the choices list
      for (var i = 0; i < choices.length; i++) {
        if (htsi == choices[i].host) return i;
      }
    }
    return -1; //don't install
  },
  //Acts like an icebreaker but doesn't have that subtype
  AISpecialBreaker: true,
  AIOkToTrash: function() {
    //Ok to trash if it has lost its abilities
    if (this.host) {
      if (this.host.AIDisablesHostedPrograms) return true;
    }
    //Trash if a matching breaker exists without Chromatophores' help
    var storedModifySubTypes = this.modifySubTypes;
    this.modifySubTypes = { Resolve: function (card) { return {}; }, automatic: true };
    var ret = runner.AI._matchingBreakerInstalled(this.host, [this]);
    this.modifySubTypes = storedModifySubTypes;
    return ret;
  },
};

cardSet[35016] = {
  title: "Maintenance Access",
  imageFile: "35016.png",
  player: runner,
  faction: "Criminal",
  influence: 3,
  cardType: "event",
  subTypes: ["Run", "Double"],
  playCost: 0,
  //As an additional cost to play this event, spend [click].
  //Run Archives. When you would approach Archives (after passing all ice), 
  //instead change the attacked server to HQ and approach HQ.
  runningWithThis: false,
  Enumerate: function () {
    return [{}];
  },
  Resolve: function (params) {
    this.runningWithThis = true;
    MakeRun(corp.archives);
  },
  //Redirect happens BEFORE approach server step (like Sneakdoor's redirect before success)
  //This allows HQ approach triggers (Manegarm) to fire, while Archives ones don't
  automaticOnWouldApproachServer: {
    Resolve: function (server) {
      if (!this.runningWithThis) return;
      if (server !== corp.archives) return;
      
      this.runningWithThis = false;
      Log("Attacked server changed to HQ");
      attackedServer = corp.HQ;
      //Phase continues - now approaching HQ (HQ ice is skipped)
    },
  },
  responseOnRunEnds: {
    Resolve: function () {
      this.runningWithThis = false;
    },
    automatic: true,
  },
  responseOnRunUnsuccessful: {
    Resolve: function () {
      this.runningWithThis = false;
    },
    automatic: true,
  },
  //AI: use when HQ has higher potential than Archives and/or HQ is better protected
  AIRunEventExtraPotential: function(server, potential) {
    //Only works when targeting Archives
    if (server !== corp.archives) return 0;
    
    //Check for Crisium Grid on Archives - doesn't prevent redirect (happens before success)
    //But if HQ has Crisium, success won't be declared there
    if (runner.AI._rootKnownToContainCopyOfCard(corp.HQ, "Crisium Grid")) return 0;
    
    //Get HQ potential
    var HQpotential = runner.AI._getCachedPotential(corp.HQ);
    
    //Valuable if HQ has higher potential than Archives
    if (HQpotential > potential) {
      //Extra bonus if Archives has less ice than HQ (we're bypassing HQ's protection)
      var bonus = 0.1;
      if (corp.archives.ice.length < corp.HQ.ice.length) {
        bonus += 0.2 * (corp.HQ.ice.length - corp.archives.ice.length);
      }
      return HQpotential - potential + bonus;
    }
    return 0;
  },
};

cardSet[35026] = {
  title: "Ritual",
  imageFile: "35026.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "event",
  playCost: 0,
  //Draw 1 card for each [click] you have remaining.
  Resolve: function (params) {
    var clicksRemaining = runner.clickTracker;
    if (clicksRemaining > 0) {
      Draw(runner, clicksRemaining);
    } else {
      Log("No clicks remaining; no cards drawn");
    }
  },
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Keep if need card draw
    if (runner.grip.length < 3) return true;
    return false;
  },
  AIWouldPlay: function() {
    //Only play if we have clicks remaining (otherwise draws nothing)
    if (runner.clickTracker < 1) return false;
    //Strongly prefer playing first click (draws 3) over later clicks
    //Don't play on last click unless desperate for cards
    if (runner.clickTracker == 1 && runner.grip.length > 1) return false;
    //Check overdraw - drawing clickTracker cards
    var cardsToDraw = runner.clickTracker;
    if (runner.AI._currentOverDraw() + (cardsToDraw - 2) < runner.AI._maxOverDraw()) return true;
    return false;
  },
  AIPlayToDraw: 3, //priority 3 (excellent draw when played first click)
};

cardSet[35004] = {
  title: "Scrounge",
  imageFile: "35004.png",
  player: runner,
  faction: "Anarch",
  influence: 1,
  cardType: "event",
  subTypes: ["Double"],
  playCost: 1,
  //As an additional cost to play this event, spend [click].
  //Install 1 program from your heap. You may add 1 program from your heap to the bottom of your stack.
  pendingAddToStack: false,
  Enumerate: function () {
    //Must have at least one program in heap that can be installed
    var installablePrograms = ChoicesArrayInstall(runner.heap, false, function(card) {
      return CheckCardType(card, ["program"]);
    });
    if (installablePrograms.length < 1) return [];
    return [{}];
  },
  Resolve: function (params) {
    var cardRef = this;
    //Step 1: Choose and install a program from heap
    var installChoices = ChoicesArrayInstall(runner.heap, false, function(card) {
      return CheckCardType(card, ["program"]);
    });
    
    //**AI code for install choice
    if (runner.AI != null && installChoices.length > 1) {
      var preferredCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.heap, InstalledCards(runner));
      if (preferredCard) {
        for (var i = 0; i < installChoices.length; i++) {
          if (installChoices[i].card == preferredCard) {
            installChoices = [installChoices[i]];
            break;
          }
        }
      }
    }
    
    DecisionPhase(
      runner,
      installChoices,
      function(installParams) {
        //Mark that we need to do add-to-stack after install
        cardRef.pendingAddToStack = true;
        //Install the chosen program (paying costs)
        Install(installParams.card, installParams.host, false, null, true);
      },
      "Scrounge",
      "Install program from heap",
      this,
      "install"
    );
  },
  //Use responseOnInstall to trigger the add-to-stack phase
  //Non-automatic so the trigger system handles phases properly
  responseOnInstall: {
    Enumerate: function(card) {
      if (this.pendingAddToStack) {
        return [{}];
      }
      return [];
    },
    Resolve: function(params) {
      this.pendingAddToStack = false;
      //Set up and switch to our custom phase
      //Set player dynamically to avoid circular reference issues
      this.AddToStackPhase.player = runner;
      this.AddToStackPhase.next = currentPhase;
      ChangePhase(this.AddToStackPhase);
    },
    availableWhenInactive: true,
  },
  AddToStackPhase: {
    //player: runner, //set dynamically to avoid "too much recursion" error
    title: "Scrounge",
    identifier: "Scrounge Add to Stack",
    Enumerate: {
      add: function() {
        var programsInHeap = ChoicesArrayCards(runner.heap, function(card) {
          return CheckCardType(card, ["program"]);
        });
        //Add option to decline
        programsInHeap.push({ card: null, label: "Done", button: "Done" });
        
        //**AI code for add-to-stack choice
        if (runner.AI != null) {
          var preferredCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.heap, InstalledCards(runner).concat(runner.grip));
          if (preferredCard) {
            for (var i = 0; i < programsInHeap.length; i++) {
              if (programsInHeap[i].card == preferredCard) {
                return [programsInHeap[i]];
              }
            }
          }
          //No valuable card, decline
          return [{ card: null, label: "Done", button: "Done" }];
        }
        return programsInHeap;
      },
    },
    Resolve: {
      add: function(params) {
        if (params.card === null) {
          //Done, no card added
          IncrementPhase();
          return;
        }
        //Add chosen card to bottom of stack (position 0)
        var cardTitle = GetTitle(params.card);
        MoveCard(params.card, runner.stack, 0);
        Log(cardTitle + " added to bottom of stack");
        IncrementPhase();
      },
    },
    questionText: "Add program to bottom of stack?",
  },
  AIWouldPlay: function() {
    //Play if there's a valuable program in heap worth installing
    var installedRunnerCards = InstalledCards(runner);
    var targetCard = runner.AI._icebreakerInPileNotInHandOrArray(runner.heap, installedRunnerCards);
    if (targetCard) {
      //Check if we can afford it
      var installCost = InstallCost(targetCard);
      if (Credits(runner) >= installCost + 1) { //+1 for Scrounge's cost
        return true;
      }
    }
    return false;
  },
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if there's something valuable in heap
    if (runner.AI._icebreakerInPileNotInHandOrArray(runner.heap, installedRunnerCards)) {
      return true;
    }
    return false;
  },
};

cardSet[35010] = {
  title: "Cacophony",
  imageFile: "35010.png",
  player: runner,
  faction: "Anarch",
  influence: 4,
  cardType: "resource",
  subTypes: ["Virtual"],
  installCost: 3,
  unique: true,
  //The first time each turn you steal or trash a Corp card, place 1 power counter on this resource.
  //When your action phase ends, you may remove 2 hosted power counters to sabotage 3.
  placedCounterThisTurn: false,
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      this.placedCounterThisTurn = false;
    },
    automatic: true,
  },
  //Track stealing - checks must be in Resolve for automatic triggers
  responseOnStolen: {
    Resolve: function (params) {
      if (this.placedCounterThisTurn) return;
      this.placedCounterThisTurn = true;
      AddCounters(this, "power", 1);
      Log("Cacophony gains 1 power counter");
    },
    automatic: true,
  },
  //Track trashing Corp cards (only when Runner trashes during access, not Corp self-trash)
  //Checks must be in Resolve for automatic triggers
  //Note: automatic triggers don't receive parameters, so we check the global accessingCard
  responseOnTrash: {
    Resolve: function () {
      if (this.placedCounterThisTurn) return;
      //Only trigger if this is during an access (Runner trashing, not Corp self-trashing like Sabotage)
      //accessingCard is a global that's set when accessing a card
      if (typeof accessingCard === 'undefined' || accessingCard === null) return;
      //Verify it's a corp card being accessed/trashed
      if (accessingCard.player !== corp) return;
      this.placedCounterThisTurn = true;
      AddCounters(this, "power", 1);
      Log("Cacophony gains 1 power counter");
    },
    automatic: true,
  },
  //When action phase ends, may sabotage
  //NOTE: Requires phase.js modification to add responseOnRunnerActionPhaseEnds
  //Alternative: Use responseOnRunnerDiscardEnds (fires after discard phase instead)
  responseOnRunnerActionPhaseEnds: {
    Enumerate: function () {
      if (CheckCounters(this, "power", 2)) return [{}];
      return [];
    },
    Resolve: function (params) {
      var cardRef = this;
      var choices = [
        { id: 0, label: "Sabotage 3", button: "Sabotage 3" },
        { id: 1, label: "Decline", button: "Decline" }
      ];
      
      //**AI code
      if (runner.AI != null) {
        //Usually want to sabotage if we can
        choices = [choices[0]];
      }
      
      DecisionPhase(
        runner,
        choices,
        function(decision) {
          if (decision.id === 0) {
            RemoveCounters(cardRef, "power", 2);
            Sabotage(3);
          }
        },
        "Cacophony",
        "Cacophony",
        this
      );
    },
  },
  AIEconomyInstall: function() {
    //Not really economy, but install if we plan to run and trash/steal
    return 1;
  },
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if we're aggressive
    return true;
  },
};

cardSet[35029] = {
  title: "Azimat",
  imageFile: "35029.png",
  player: runner,
  faction: "Shaper",
  influence: 1,
  cardType: "program",
  memoryCost: 2,
  installCost: 1,
  //2 recurring credits. You can spend hosted credits to pay trash costs.
  recurringCredits: 2,
  canUseCredits: function (doing, card) {
    if (doing == "paying trash costs") return true;
    return false;
  },
  AIInstallBeforeRun: function(server, potential, useRunEvent, runCreditCost, runClickCost) {
    //Install before run if we might want to trash
    return 1;
  },
  AIReducesTrashCost: function(card) {
    var cardTC = TrashCost(card);
    if (cardTC < this.credits) return cardTC;
    return this.credits;
  },
  AIPreferredInstallChoice: function(choices) {
    //Install if we have spare MU
    if (MemoryUnits() - InstalledMemoryCost() >= 2) return 0;
    return -1;
  },
};

cardSet[35008] = {
  title: "Hantu",
  imageFile: "35008.png",
  player: runner,
  faction: "Anarch",
  influence: 2,
  cardType: "program",
  subTypes: ["Icebreaker", "Killer", "Virus"],
  memoryCost: 1,
  installCost: 3,
  strength: 2,
  //When you install this program, place 2 virus counters on it.
  //Interface → 1 credit: Break 1 sentry subroutine.
  //Hosted virus counter: +2 strength.
  strengthBoost: 0,
  automaticOnInstall: {
    Resolve: function(card) {
      if (card == this) {
        AddCounters(this, "virus", 2);
      }
    },
  },
  modifyStrength: {
    Resolve: function (card) {
      if (card == this) return this.strengthBoost;
      return 0;
    },
  },
  abilities: [
    {
      text: "Break 1 sentry subroutine",
      Enumerate: function () {
        if (!CheckEncounter()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Sentry")) return [];
        if (!CheckCredits(runner, 1, "using", this)) return [];
        if (!CheckStrength(this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function (params) {
        SpendCredits(
          runner,
          1,
          "using",
          this,
          function () {
            Break(params.subroutine);
          },
          this
        );
      },
    },
    {
      text: "+2 strength (spend virus counter)",
      Enumerate: function () {
        if (!CheckEncounter()) return [];
        if (CheckStrength(this)) return [];
        if (!CheckUnbrokenSubroutines()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Sentry")) return [];
        if (!CheckCounters(this, "virus", 1)) return [];
        return [{}];
      },
      Resolve: function (params) {
        RemoveCounters(this, "virus", 1);
        BoostStrength(this, 2);
      },
    },
  ],
  responseOnEncounterEnds: {
    Resolve: function () {
      this.strengthBoost = 0;
    },
    automatic: true,
  },
  AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    //Calculate how many virus counters needed for strength
    var virusCounters = Counters(this, "virus");
    var strengthNeeded = iceStrength - cardStrength;
    var virusNeeded = Math.max(0, Math.ceil(strengthNeeded / 2)); //ensure non-negative
    if (virusNeeded > virusCounters) return result; //Can't boost enough
    
    var breakCost = iceAI.numSubs; //1 credit per sub
    
    if (creditsLeft >= breakCost) {
      result = result.concat(
        rc.ImplementIcebreaker(
          point,
          this,
          cardStrength + (virusNeeded * 2),
          iceAI,
          iceStrength,
          ["Sentry"],
          0, //costToUpStr (using counters instead)
          2, //amtToUpStr
          1, //costToBreak
          1, //amtToBreak
          creditsLeft
        )
      );
    }
    return result;
  },
};

cardSet[35009] = {
  title: "Rising Tide",
  imageFile: "35009.png",
  player: runner,
  faction: "Anarch",
  influence: 2,
  cardType: "program",
  subTypes: ["Icebreaker", "Fracter"],
  memoryCost: 1,
  installCost: 1,
  strength: 1,
  //This program gets +1 strength for each fracter in your heap.
  //Interface → 1 credit: Break 1 barrier subroutine.
  //1 credit: +1 strength.
  strengthBoost: 0,
  modifyStrength: {
    Resolve: function (card) {
      if (card == this) {
        //Count fracters in heap
        var heapBonus = 0;
        for (var i = 0; i < runner.heap.length; i++) {
          if (CheckSubType(runner.heap[i], "Fracter")) {
            heapBonus++;
          }
        }
        return this.strengthBoost + heapBonus;
      }
      return 0;
    },
  },
  abilities: [
    {
      text: "Break 1 barrier subroutine",
      Enumerate: function () {
        if (!CheckEncounter()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Barrier")) return [];
        if (!CheckCredits(runner, 1, "using", this)) return [];
        if (!CheckStrength(this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function (params) {
        SpendCredits(
          runner,
          1,
          "using",
          this,
          function () {
            Break(params.subroutine);
          },
          this
        );
      },
    },
    {
      text: "+1 strength",
      Enumerate: function () {
        if (!CheckEncounter()) return [];
        if (CheckStrength(this)) return [];
        if (!CheckUnbrokenSubroutines()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Barrier")) return [];
        if (!CheckCredits(runner, 1, "using", this)) return [];
        return [{}];
      },
      Resolve: function (params) {
        SpendCredits(
          runner,
          1,
          "using",
          this,
          function () {
            BoostStrength(this, 1);
          },
          this
        );
      },
    },
  ],
  responseOnEncounterEnds: {
    Resolve: function () {
      this.strengthBoost = 0;
    },
    automatic: true,
  },
  AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    //cardStrength already includes heap bonus via modifyStrength, so use it directly
    result = result.concat(
      rc.ImplementIcebreaker(
        point,
        this,
        cardStrength,
        iceAI,
        iceStrength,
        ["Barrier"],
        1, //costToUpStr
        1, //amtToUpStr
        1, //costToBreak
        1, //amtToBreak
        creditsLeft
      )
    );
    return result;
  },
};

cardSet[35007] = {
  title: "Gourmand",
  imageFile: "35007.png",
  player: runner,
  faction: "Anarch",
  influence: 2,
  cardType: "program",
  memoryCost: 1,
  installCost: 0,
  //Access → [trash]: Trash the non-agenda card you are accessing. If you do, draw 1 card.
  abilities: [
    {
      text: "Trash Gourmand: Trash the non-agenda card you are accessing. If you do, draw 1 card.",
      Enumerate: function () {
        if (!CheckAccessing()) return [];
        //Must be accessing a non-agenda card
        if (CheckCardType(accessingCard, ["agenda"])) return [];
        //Card must be trashable
        if (!CheckTrash(accessingCard)) return [];
        return [{}];
      },
      Resolve: function (params) {
        var cardRef = this;
        var cardToTrash = accessingCard; //store reference before trashing Gourmand
        //Trash Gourmand as the cost (unpreventable)
        Trash(this, false, function(gourmandTrashed) {
          //Now trash the accessed card (preventable)
          if (PlayerCanLook(corp, cardToTrash)) cardToTrash.faceUp = true;
          SetHistoryThumbnail(cardToTrash.imageFile, "Trash");
          Trash(cardToTrash, true, function(cardsTrashed) {
            //Check if the accessed card was actually trashed ("If you do")
            if (cardsTrashed.includes(cardToTrash)) {
              Draw(runner, 1);
            }
            ResolveAccess();
          }, cardRef);
        }, this);
      },
    },
  ],
  AIPreferredInstallChoice: function(choices) {
    //Install if we have spare MU - cheap program to have around
    if (MemoryUnits() - InstalledMemoryCost() >= 1) return 0;
    return -1;
  },
  AIInstallBeforeRun: function(server, potential, useRunEvent, runCreditCost, runClickCost) {
    //Install before run - it's free and might be useful
    return 1;
  },
  AIAccessTriggerPriority: function(optionList) {
    //Use Gourmand if:
    //1. No trash cost option available, OR
    //2. Trash cost is high (more than 3 credits)
    //Don't use if card has low trash cost - save Gourmand for expensive cards
    if (!optionList.includes("trash")) return 3; //priority > 2: preferred over paying
    if (TrashCost(accessingCard) > 3) return 3;
    //Otherwise, prefer paying trash cost to preserve Gourmand
    return 0;
  },
  AIReducesTrashCost: function(card) {
    //Gourmand can effectively reduce trash cost to 0 by sacrificing itself
    //But only for non-agenda cards
    if (CheckCardType(card, ["agenda"])) return 0;
    return TrashCost(card);
  },
};

cardSet[35015] = {
  title: "Lie Low",
  imageFile: "35015.png",
  player: runner,
  faction: "Criminal",
  influence: 1,
  cardType: "event",
  subTypes: ["Double"],
  playCost: 1,
  //As an additional cost to play this event, spend [click].
  //Resolve 1 of the following:
  //• Draw 4 cards.
  //• Remove up to 2 tags.
  Enumerate: function () {
    //Can always play if you have the clicks (even if 0 tags, draw is always valid)
    return [{}];
  },
  Resolve: function (params) {
    var choices = [
      { id: 0, label: "Draw 4 cards", button: "Draw 4 cards" }
    ];
    
    //Only offer tag removal if tagged
    if (runner.tags >= 1) {
      if (runner.tags >= 2) {
        choices.push({ id: 2, label: "Remove 2 tags", button: "Remove 2 tags" });
      }
      choices.push({ id: 1, label: "Remove 1 tag", button: "Remove 1 tag" });
    }
    
    //**AI code
    if (runner.AI != null) {
      //Prefer removing tags if tagged, otherwise draw
      if (runner.tags >= 2) {
        choices = [{ id: 2, label: "Remove 2 tags", button: "Remove 2 tags" }];
      } else if (runner.tags >= 1) {
        choices = [{ id: 1, label: "Remove 1 tag", button: "Remove 1 tag" }];
      } else {
        choices = [choices[0]]; //draw 4
      }
    }
    
    DecisionPhase(
      runner,
      choices,
      function(decision) {
        if (decision.id === 0) {
          Draw(runner, 4);
        } else if (decision.id === 1) {
          RemoveTags(1);
        } else if (decision.id === 2) {
          RemoveTags(2);
        }
      },
      "Lie Low",
      "Lie Low",
      this
    );
  },
  AIWouldPlay: function() {
    //Play if tagged (to remove tags) or if need cards
    if (runner.tags >= 1) return true;
    //Check overdraw for 4 cards
    if (runner.AI._currentOverDraw() + 2 < runner.AI._maxOverDraw()) return true;
    return false;
  },
  AIPlayToDraw: 3, //priority 3 (good draw potential)
  AIPlayToRemoveTags: function() {
    if (runner.tags < 1) return 0;
    if (runner.tags >= 2) return 2; //removes 2 tags
    return 1; //removes 1 tag
  },
};

cardSet[35022] = {
  title: "Open Market",
  imageFile: "35022.png",
  player: runner,
  faction: "Criminal",
  influence: 2,
  cardType: "resource",
  subTypes: ["Job", "Location"],
  installCost: 2,
  //When you install this resource, load 6 credits onto it. When it is empty, trash it.
  automaticOnInstall: {
    Resolve: function (card) {
      if (card == this) LoadCredits(this, 6);
    },
  },
  //You can spend hosted credits to install connection and job resources.
  canUseCredits: function (doing, card) {
    if (!card) return false;
    if (doing == "installing") {
      if (CheckCardType(card, ["resource"])) {
        if (CheckSubType(card, "Connection") || CheckSubType(card, "Job")) {
          return true;
        }
      }
    }
    return false;
  },
  //When your turn begins, take 1 credit from this resource.
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      if (CheckCounters(this, "credits", 1)) {
        TakeCredits(runner, this, 1);
        //When it is empty, trash it
        if (!CheckCounters(this, "credits", 1)) {
          Trash(this, false);
        }
      }
    },
    automatic: true,
  },
  //When it is empty, trash it.
  //This catches credits being spent via canUseCredits (SpendCredits doesn't fire triggers)
  automaticOnAnyChange: {
    Resolve: function() {
      //Check if empty and still installed (avoid double-trash)
      if (!CheckInstalled(this)) return;
      if (typeof this.credits === 'undefined' || this.credits <= 0) {
        Trash(this, false);
      }
    },
  },
  AIEconomyInstall: function() {
    //Good drip economy, especially if you have Connection/Job resources to install
    var connectionOrJobInGrip = false;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["resource"])) {
        if (CheckSubType(runner.grip[i], "Connection") || CheckSubType(runner.grip[i], "Job")) {
          connectionOrJobInGrip = true;
          break;
        }
      }
    }
    if (connectionOrJobInGrip) return 2; //priority 2 (moderate - good synergy)
    return 1; //priority 1 (still provides drip)
  },
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping as economy
    return true;
  },
};

cardSet[35027] = {
  title: "GAMEDRAGON™ Pro",
  imageFile: "35027.png",
  player: runner,
  faction: "Shaper",
  influence: 2,
  cardType: "hardware",
  subTypes: ["Mod"],
  installCost: 2,
  unique: true,
  //When you install this hardware and when your turn begins, you may host this hardware 
  //on an installed non-AI icebreaker. Host icebreaker gets +1 strength. Abilities that 
  //increase its strength last for the remainder of the run (instead of any shorter duration).
  
  //Store reference to original host trigger so we can restore it
  originalHostEncounterEnd: null,
  
  //Helper: Get valid icebreaker hosts
  getValidHosts: function() {
    var validHosts = [];
    for (var i = 0; i < runner.rig.programs.length; i++) {
      var prog = runner.rig.programs[i];
      if (CheckSubType(prog, "Icebreaker") && !CheckSubType(prog, "AI")) {
        validHosts.push(prog);
      }
    }
    return validHosts;
  },
  
  //Helper: Set up the strength preservation on a host
  setupHostOverride: function() {
    if (!this.host) return;
    if (!this.host.responseOnEncounterEnds) return;
    
    //Save original trigger if not already saved
    if (!this.originalHostEncounterEnd) {
      this.originalHostEncounterEnd = this.host.responseOnEncounterEnds;
    }
    
    //Replace with no-op for strength reset (strength will be preserved)
    //Keep automatic: true so the trigger system handles it the same way
    this.host.responseOnEncounterEnds = {
      Resolve: function() {
        //Don't reset strengthBoost - GAMEDRAGON preserves it
      },
      automatic: true,
    };
  },
  
  //Helper: Restore original host trigger and reset strength
  cleanupHostOverride: function() {
    if (!this.host) return;
    
    //Restore original trigger
    if (this.originalHostEncounterEnd) {
      this.host.responseOnEncounterEnds = this.originalHostEncounterEnd;
      this.originalHostEncounterEnd = null;
    }
    
    //Reset strength boost (the original duration has expired)
    if (typeof this.host.strengthBoost !== 'undefined') {
      this.host.strengthBoost = 0;
    }
  },
  
  //Helper: Offer hosting choice
  offerHostingChoice: function() {
    var validHosts = this.getValidHosts();
    if (validHosts.length === 0) return;
    
    var choices = [{ id: -1, label: "Decline", button: "Decline" }];
    for (var i = 0; i < validHosts.length; i++) {
      choices.push({
        id: i,
        host: validHosts[i],
        label: "Host on " + GetTitle(validHosts[i]),
        button: GetTitle(validHosts[i]),
      });
    }
    
    var cardRef = this;
    
    //**AI code
    if (runner.AI != null) {
      //Prefer to host on the icebreaker with lowest base strength (benefits most from +1)
      var bestHost = null;
      var lowestStr = 999;
      for (var i = 0; i < validHosts.length; i++) {
        var baseStr = validHosts[i].strength || 0;
        if (baseStr < lowestStr) {
          lowestStr = baseStr;
          bestHost = validHosts[i];
        }
      }
      if (bestHost) {
        for (var i = 0; i < choices.length; i++) {
          if (choices[i].host === bestHost) {
            choices = [choices[i]];
            break;
          }
        }
      }
    }
    
    DecisionPhase(
      runner,
      choices,
      function(decision) {
        if (decision.id >= 0 && decision.host) {
          //Clean up old host if any
          if (cardRef.host) {
            cardRef.cleanupHostOverride();
          }
          //Move to new host
          MoveCard(cardRef, decision.host.hostedCards);
          cardRef.host = decision.host;
          Log(GetTitle(cardRef) + " hosted on " + GetTitle(decision.host));
          //Set up the strength preservation
          cardRef.setupHostOverride();
        }
      },
      "GAMEDRAGON™ Pro",
      "Host on icebreaker?",
      this
    );
  },
  
  //When you install this hardware, may host on icebreaker
  responseOnInstall: {
    Enumerate: function(card) {
      if (card !== this) return [];
      if (this.getValidHosts().length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      this.offerHostingChoice();
    },
  },
  
  //When your turn begins, may host on icebreaker
  responseOnRunnerTurnBegins: {
    Enumerate: function() {
      //Only offer if not already hosted, or if we want to switch hosts
      if (this.getValidHosts().length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      this.offerHostingChoice();
    },
    //Not automatic - player should choose whether to rehost
  },
  
  //Host icebreaker gets +1 strength
  modifyStrength: {
    Resolve: function(card) {
      if (this.host && card === this.host) {
        return 1; //+1 strength
      }
      return 0;
    },
    automatic: true,
  },
  
  //At run end, reset host's strengthBoost (the "remainder of the run" has ended)
  responseOnRunEnds: {
    Resolve: function() {
      if (this.host && typeof this.host.strengthBoost !== 'undefined') {
        this.host.strengthBoost = 0;
      }
    },
    automatic: true,
  },
  
  //When trashed, restore original host behavior
  automaticOnTrash: {
    Resolve: function(cards) {
      if (cards.includes(this)) {
        this.cleanupHostOverride();
      }
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  AIPreferredInstallChoice: function(choices) {
    //Install if we have icebreakers to host on
    if (this.getValidHosts().length > 0) return 0;
    return -1; //wait until we have icebreakers
  },
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if we have or might get icebreakers
    return true;
  },
};