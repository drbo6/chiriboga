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

//Barry "Baz" Wong: Tri-Maf Veteran
//Criminal Identity: Cyborg
//Deck: 45, Influence: 15
//Whenever the Corp rezzes a piece of ice, you may install 1 resource or piece of hardware from your grip.
cardSet[35012] = {
  title: 'Barry "Baz" Wong: Tri-Maf Veteran',
  imageFile: "35012.png",
  player: runner,
  faction: "Criminal",
  link: 0,
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 15,
  subTypes: ["Cyborg"],
  
  //Whenever the Corp rezzes a piece of ice, you may install 1 resource or piece of hardware from your grip
  responseOnRez: {
    Enumerate: function(card) {
      //Only trigger when ice is rezzed
      if (!CheckCardType(card, ["ice"])) return [];
      
      //Check if we have resources or hardware in grip
      var installables = [];
      for (var i = 0; i < runner.grip.length; i++) {
        var gripCard = runner.grip[i];
        if (CheckCardType(gripCard, ["resource", "hardware"])) {
          //Check if can afford and install
          if (ChoicesCardInstall(gripCard).length > 0) {
            installables.push({
              card: gripCard,
              label: "Install " + GetTitle(gripCard, true) + " (" + InstallCost(gripCard) + "[c])"
            });
          }
        }
      }
      
      if (installables.length === 0) return [];
      
      //Add decline option
      installables.push({ skip: true, label: "Decline", button: "Decline" });
      
      //**AI code
      if (runner.AI && installables.length > 1) {
        //AI decides whether to install something
        var shouldInstall = this.AIShouldInstallOnRez(card, installables);
        
        if (shouldInstall && installables.length > 1) {
          //Choose best card to install
          var bestChoice = this.AIChooseBestInstall(installables);
          if (bestChoice !== null) {
            runner.AI.preferred = { title: this.title, option: installables[bestChoice] };
          }
        } else {
          //Decline
          runner.AI.preferred = { title: this.title, option: installables[installables.length - 1] };
        }
      }
      
      return installables;
    },
    Resolve: function(params) {
      if (params.skip) return;
      
      //Install the chosen card
      Install(params.card, params.host);
    },
    text: "Install 1 resource or hardware from grip",
  },
  
  //AI helper: Should we install on this rez?
  AIShouldInstallOnRez: function(rezzedIce, installables) {
    //Don't install if low on clicks (need clicks for runs)
    if (runner.clickTracker < 2) return false;
    
    //Don't install if last click
    if (runner.clickTracker < 1) return false;
    
    //Install if the rezzed ice is expensive (Corp is committing resources)
    if (RezCost(rezzedIce) >= 4) return true;
    
    //Install if we have cheap cards (<2 cost) in hand
    for (var i = 0; i < installables.length - 1; i++) { //-1 for Decline
      if (InstallCost(installables[i].card) <= 1) return true;
    }
    
    //Install if we're setting up and have important cards
    var installedCards = InstalledCards(runner);
    if (installedCards.length < 5) {
      //Early game - be aggressive about installing
      return true;
    }
    
    //Otherwise decline (save for better timing)
    return false;
  },
  
  //AI helper: Choose best card to install
  AIChooseBestInstall: function(installables) {
    if (installables.length <= 1) return null; //Only Decline option
    
    //Prioritize by card type and cost
    var consoles = [];
    var cheapResources = [];
    var expensiveResources = [];
    var cheapHardware = [];
    var expensiveHardware = [];
    
    for (var i = 0; i < installables.length - 1; i++) { //-1 for Decline
      var card = installables[i].card;
      var cost = InstallCost(card);
      
      if (CheckSubType(card, "Console")) {
        consoles.push(i);
      } else if (CheckCardType(card, ["resource"])) {
        if (cost <= 2) cheapResources.push(i);
        else expensiveResources.push(i);
      } else if (CheckCardType(card, ["hardware"])) {
        if (cost <= 2) cheapHardware.push(i);
        else expensiveHardware.push(i);
      }
    }
    
    //Priority: Consoles > Cheap resources > Cheap hardware > Expensive resources > Expensive hardware
    if (consoles.length > 0) return consoles[0];
    if (cheapResources.length > 0) return cheapResources[0];
    if (cheapHardware.length > 0) return cheapHardware[0];
    if (expensiveResources.length > 0) return expensiveResources[0];
    if (expensiveHardware.length > 0) return expensiveHardware[0];
    
    return 0; //Default to first option
  },
};

//MuslihaT: Multifarious Marketeer  
//Criminal Identity: Natural
//Deck: 45, Influence: 15
//When your turn begins, look at the top card of your stack. If that card is an icebreaker 
//or a run event, you may reveal it and add it to your grip.
cardSet[35013] = {
  title: "MuslihaT: Multifarious Marketeer",
  imageFile: "35013.png",
  player: runner,
  faction: "Criminal",
  link: 0,
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 15,
  subTypes: ["Natural"],
  
  //When your turn begins, look at the top card of your stack.
  //If that card is an icebreaker or a run event, you may reveal it and add it to your grip.
  responseOnRunnerTurnBegins: {
    Enumerate: function() {
      //Check if stack has cards
      if (runner.stack.length === 0) return [];
      
      var topCard = runner.stack[runner.stack.length - 1];
      
      //Always make the top card visible to Runner (so they can see it)
      topCard.knownToRunner = true;
      
      //Check if it's an icebreaker or run event
      var isIcebreaker = CheckSubType(topCard, "Icebreaker");
      var isRunEvent = CheckCardType(topCard, ["event"]) && CheckSubType(topCard, "Run");
      
      if (!isIcebreaker && !isRunEvent) {
        //Not a qualifying card - Runner sees it but can't reveal it
        //Return empty array (no choices), but card remains knownToRunner
        return [];
      }
      
      //Valid card - offer to reveal and add to grip
      var choices = [
        { id: 0, label: "Reveal and add to grip: " + GetTitle(topCard, true), button: "Reveal" },
        { id: 1, label: "Decline (leave on top)", button: "Decline" }
      ];
      
      //**AI code
      if (runner.AI) {
        //AI evaluates whether to take the card
        var shouldTake = this.AIShouldTakeCard(topCard);
        
        if (shouldTake) {
          runner.AI.preferred = { title: this.title, option: choices[0] };
        } else {
          runner.AI.preferred = { title: this.title, option: choices[1] };
        }
      }
      
      return choices;
    },
    Resolve: function(params) {
      var topCard = runner.stack[runner.stack.length - 1];
      
      if (params.id === 1) {
        //Declined - leave card on top (keep knownToRunner flag)
        Log("MuslihaT: Top card left on stack");
        return;
      }
      
      var cardRef = this;
      
      //Reveal the card (shows to Corp)
      Reveal(topCard, function() {
        //Add to grip
        var idx = runner.stack.indexOf(topCard);
        if (idx > -1) {
          runner.stack.splice(idx, 1);
          runner.grip.push(topCard);
          topCard.cardLocation = runner.grip;
          //knownToRunner flag will persist (doesn't matter once in grip)
          Log(GetTitle(topCard, true) + " added to grip from top of stack");
        }
      }, cardRef);
    },
    text: "Look at top of stack; reveal if icebreaker or run event",
  },
  
  //AI helper: Should we take this card?
  AIShouldTakeCard: function(card) {
    //Always take icebreakers if we don't have a full suite
    if (CheckSubType(card, "Icebreaker")) {
      var installedCards = InstalledCards(runner);
      var hasDecoder = false;
      var hasKiller = false;
      var hasFracter = false;
      
      for (var i = 0; i < installedCards.length; i++) {
        if (CheckSubType(installedCards[i], "Decoder")) hasDecoder = true;
        if (CheckSubType(installedCards[i], "Killer")) hasKiller = true;
        if (CheckSubType(installedCards[i], "Fracter")) hasFracter = true;
      }
      
      //Take if it fills a gap
      if (!hasDecoder && CheckSubType(card, "Decoder")) return true;
      if (!hasKiller && CheckSubType(card, "Killer")) return true;
      if (!hasFracter && CheckSubType(card, "Fracter")) return true;
      
      //Take if we have no breakers at all
      if (!hasDecoder && !hasKiller && !hasFracter) return true;
      
      //Otherwise maybe keep it on top for later
      return false;
    }
    
    //For run events, evaluate based on current needs
    if (CheckCardType(card, ["event"]) && CheckSubType(card, "Run")) {
      //Take if we're low on cards
      if (runner.grip.length < 4) return true;
      
      //Take if we have clicks to use it
      if (runner.clickTracker >= 2) return true;
      
      //Otherwise decline (might want to draw other cards first)
      return false;
    }
    
    return false;
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
      visual: { y: 93, h: 16 },
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

//Card: Madani
//Shaper Hardware: Console
//Cost: 2, Influence: 3
//[click]: Host any number of programs from your grip faceup on this hardware. 
//(They are not installed.)
//Once per turn → 0[credit]: Install 1 hosted program (paying its install cost).
//Limit 1 console per player.
cardSet[35028] = {
  title: "Madani",
  imageFile: "35028.png",
  player: runner,
  faction: "Shaper",
  influence: 3,
  cardType: "hardware",
  subTypes: ["Console"],
  installCost: 2,
  unique: true,
  
  //Initialize hostedCards array on install
  automaticOnInstall: {
    Resolve: function(card) {
      if (card === this) {
        this.hostedCards = [];
      }
    },
  },
  
  //Track "once per turn" for install ability
  usedThisTurn: false,
  
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  responseOnCorpTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  //When Madani is trashed, return hosted programs to grip
  automaticOnTrash: {
    Resolve: function(card) {
      if (card === this && this.hostedCards && this.hostedCards.length > 0) {
        for (var i = 0; i < this.hostedCards.length; i++) {
          var program = this.hostedCards[i];
          program.notInstalled = false; //Clear flag
          program.host = null;
          runner.grip.push(program);
          program.cardLocation = runner.grip;
          Log(GetTitle(program, true) + " returned to grip");
        }
        this.hostedCards = [];
      }
    },
  },
  
  abilities: [
    {
      text: "Host any number of programs from grip",
      Enumerate: function() {
        if (!CheckActionClicks(runner, 1)) return [];
        
        //Get all programs from grip
        var programs = [];
        for (var i = 0; i < runner.grip.length; i++) {
          if (CheckCardType(runner.grip[i], ["program"])) {
            programs.push({
              card: runner.grip[i],
              label: GetTitle(runner.grip[i], true)
            });
          }
        }
        
        if (programs.length === 0) return [];
        
        //Add button choice (just the button, no label so it doesn't show as a choice)
        programs.push({
          id: "hostButton",
          button: "Host 0 programs",
          multiSelectDynamicButtonText: function(numSelected) {
            if (numSelected === 0) return "Host 0 programs";
            if (numSelected === 1) return "Host 1 program";
            return "Host " + numSelected + " programs";
          }
        });
        
        //Set up multi-select array for ALL choices
        for (var i = 0; i < programs.length; i++) {
          programs[i].cards = Array(programs.length - 1).fill(null);
        }
        
        //**AI code
        if (runner.AI) {
          //AI auto-selects programs to host
          var programsByExpensive = [];
          for (var i = 0; i < programs.length - 1; i++) {
            programsByExpensive.push(programs[i]);
          }
          programsByExpensive.sort(function(a, b) {
            return InstallCost(b.card) - InstallCost(a.card);
          });
          
          //Host up to 3 most expensive, or all if grip > 5
          var numToHost = runner.grip.length > 5 ? programsByExpensive.length : Math.min(3, programsByExpensive.length);
          
          //Fill the .cards array
          for (var i = 0; i < numToHost; i++) {
            programs[programs.length - 1].cards[i] = programsByExpensive[i].card;
          }
        }
        
        return programs;
      },
      Resolve: function(params) {
        SpendClicks(runner, 1);
        
        //Collect selected programs
        var selectedPrograms = [];
        if (params.cards) {
          for (var i = 0; i < params.cards.length; i++) {
            if (params.cards[i]) selectedPrograms.push(params.cards[i]);
          }
        }
        
        //Host each program
        for (var i = 0; i < selectedPrograms.length; i++) {
          var program = selectedPrograms[i];
          
          //Initialize hostedCards if needed
          if (typeof this.hostedCards === 'undefined') {
            this.hostedCards = [];
          }
          
          //Remove from grip
          var idx = runner.grip.indexOf(program);
          if (idx > -1) runner.grip.splice(idx, 1);
          
          //Add to hosted
          this.hostedCards.push(program);
          program.cardLocation = this.hostedCards;
          program.notInstalled = true;
          program.faceUp = true;
          program.host = this;
          
          Log(GetTitle(program, true) + " hosted on Madani");
        }
        
        if (selectedPrograms.length === 0) {
          Log("No programs hosted on Madani");
        }
      },
    },
    {
      text: "Install 1 hosted program (once per turn, can use during runs)",
      Enumerate: function() {
        if (this.usedThisTurn) return [];
        if (!this.hostedCards || this.hostedCards.length === 0) return [];
        
        var choices = [];
        for (var i = 0; i < this.hostedCards.length; i++) {
          var card = this.hostedCards[i];
          //Check if can afford install cost
          if (ChoicesCardInstall(card).length > 0) {
            choices.push({ 
              card: card, 
              label: "Install " + GetTitle(card, true) + " (" + InstallCost(card) + "[c])"
            });
          }
        }
        
        //**AI code
        if (runner.AI && choices.length > 0) {
          //AI picks which program to install
          //Prioritize missing breaker types
          var bestChoice = this.AISelectBestProgramToInstall(choices);
          if (bestChoice !== null) {
            runner.AI.preferred = { title: this.title, option: choices[bestChoice] };
          }
        }
        
        return choices;
      },
      Resolve: function(params) {
        this.usedThisTurn = true;
        
        //Clear notInstalled flag before installing
        params.card.notInstalled = false;
        
        //Install the program (pays install cost)
        Install(params.card, null);
      },
    },
  ],
  
  //AI helper: Which program should we install from hosted?
  AISelectBestProgramToInstall: function(choices) {
    if (choices.length === 0) return null;
    
    var installedCards = InstalledCards(runner);
    
    //Check which breaker types we're missing
    var hasDecoder = false;
    var hasKiller = false;
    var hasFracter = false;
    
    for (var i = 0; i < installedCards.length; i++) {
      if (CheckSubType(installedCards[i], "Decoder")) hasDecoder = true;
      if (CheckSubType(installedCards[i], "Killer")) hasKiller = true;
      if (CheckSubType(installedCards[i], "Fracter")) hasFracter = true;
    }
    
    //Prioritize missing breaker types
    for (var i = 0; i < choices.length; i++) {
      var card = choices[i].card;
      if (!hasDecoder && CheckSubType(card, "Decoder")) return i;
      if (!hasKiller && CheckSubType(card, "Killer")) return i;
      if (!hasFracter && CheckSubType(card, "Fracter")) return i;
    }
    
    //Otherwise, install cheapest program first
    var cheapest = 0;
    var cheapestCost = InstallCost(choices[0].card);
    for (var i = 1; i < choices.length; i++) {
      var cost = InstallCost(choices[i].card);
      if (cost < cheapestCost) {
        cheapest = i;
        cheapestCost = cost;
      }
    }
    
    return cheapest;
  },
  
  //AI: Worth keeping?
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Keep if we have hosted programs
    if (this.hostedCards && this.hostedCards.length > 0) return true;
    
    //Keep if we have expensive programs in hand
    var expensivePrograms = 0;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["program"])) {
        if (InstallCost(runner.grip[i]) >= 3) {
          expensivePrograms++;
        }
      }
    }
    if (expensivePrograms >= 2) return true;
    
    //Keep if MU is tight
    if (spareMU <= 1) return true;
    
    //Otherwise not critical
    return false;
  },
  
  //AI: Should we install this?
  AIPreferredInstallChoice: function(choices) {
    //Don't install on last click
    if (runner.clickTracker < 2) return -1;
    
    //Install if we have 2+ expensive programs in hand
    var expensivePrograms = 0;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["program"])) {
        if (InstallCost(runner.grip[i]) >= 3) {
          expensivePrograms++;
        }
      }
    }
    if (expensivePrograms >= 2) return 0;
    
    //Install if MU is tight and we have programs
    var spareMU = MemoryUnits() - InstalledMemoryCost();
    var programsInHand = 0;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["program"])) {
        programsInHand++;
      }
    }
    if (spareMU <= 1 && programsInHand >= 2) return 0;
    
    //Otherwise, maybe later
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

//Card 42: Bling
//Anarch Hardware: Console
//Cost: 2, +1 MU
//Whenever you install a card without spending credits, you may host the top card
//of your stack faceup on this hardware. (It is not installed.)
//You can play or install hosted cards as if they were in your grip.
//When your discard phase ends, trash all hosted cards.
//Limit 1 console per player.
cardSet[35006] = {
  title: "Bling",
  imageFile: "35006.png",
  player: runner,
  faction: "Anarch",
  influence: 3,
  cardType: "hardware",
  subTypes: ["Console"],
  installCost: 2,
  unique: true,
  memoryUnits: 1,
  
  hostedCards: [],
  
  //Whenever you install a card without spending credits, you may host the top card of stack
  responseOnInstall: {
    Enumerate: function(card) {
      //Don't trigger for Bling itself
      if (card === this) return [];
      //Only trigger if install cost was 0
      if (intended.installCostPaid !== 0) return [];
      //Must have cards in stack
      if (runner.stack.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      var cardRef = this;
      var choices = [
        { host: true, label: "Host top card of stack on Bling", button: "Host" },
        { host: false, label: "Decline", button: "Decline" }
      ];
      
      DecisionPhase(
        runner,
        choices,
        function(choiceParams) {
          if (choiceParams.host) {
            //Get top card of stack
            var topCard = runner.stack[runner.stack.length - 1];
            MoveCard(topCard, cardRef.hostedCards);
            topCard.faceUp = true;
            topCard.notInstalled = true; //Don't count towards MU
            Log(GetTitle(topCard, true) + " hosted on Bling");
          }
        },
        "Bling",
        "Host top card of stack?",
        cardRef
      );
    },
    text: "Host top card of stack on Bling",
  },
  
  //You can play or install hosted cards as if they were in your grip
  abilities: [
    {
      text: "Install a hosted card",
      Enumerate: function() {
        if (!CheckActionClicks(runner, 1)) return [];
        var choices = [];
        for (var i = 0; i < this.hostedCards.length; i++) {
          var card = this.hostedCards[i];
          if (CheckCardType(card, ["program", "hardware", "resource"])) {
            //Check if can afford
            if (ChoicesCardInstall(card).length > 0) {
              choices.push({ card: card, label: "Install " + GetTitle(card, true) });
            }
          }
        }
        return choices;
      },
      Resolve: function(params) {
        SpendClicks(runner, 1);
        params.card.notInstalled = false; //Clear flag before installing
        Install(params.card, null);
      },
    },
    {
      text: "Play a hosted event",
      Enumerate: function() {
        if (!CheckActionClicks(runner, 1)) return [];
        var choices = [];
        for (var i = 0; i < this.hostedCards.length; i++) {
          var card = this.hostedCards[i];
          if (CheckCardType(card, ["event"])) {
            if (FullCheckPlay(card)) {
              choices.push({ card: card, label: "Play " + GetTitle(card, true) });
            }
          }
        }
        return choices;
      },
      Resolve: function(params) {
        SpendClicks(runner, 1);
        params.card.notInstalled = false; //Clear flag before playing
        Play(params.card);
      },
    },
  ],
  
  //When your discard phase ends, trash all hosted cards
  responseOnRunnerDiscardEnds: {
    Enumerate: function() {
      if (this.hostedCards.length > 0) return [{}];
      return [];
    },
    Resolve: function() {
      if (this.hostedCards.length === 0) return;
      var numCards = this.hostedCards.length;
      var cardsToTrash = this.hostedCards.slice(); //Copy array
      for (var i = 0; i < cardsToTrash.length; i++) {
        cardsToTrash[i].notInstalled = false; //Clear flag before trashing
        Trash(cardsToTrash[i], false);
      }
      Log("Bling trashed " + numCards + " hosted card(s)");
    },
    automatic: true,
  },
  
  //AI code
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if low on MU
    if (spareMU <= 0) return true;
    return false;
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
  //When it is empty, trash it.
  //This fires when credits are spent via canUseCredits
  onCreditsSpent: function(amount) {
    //Check if empty and still installed
    if (!CheckInstalled(this)) return;
    if (typeof this.credits === 'undefined' || this.credits <= 0) {
      Log(GetTitle(this, true) + " trashed (empty)");
      MoveCard(this, runner.heap);
      this.faceUp = true;
    }
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

//Dewi Subrotoputri: Pedagogical Dhalang
//Shaper Identity: Natural (FLIP IDENTITY)
//Deck: 45, Influence: 15
//Side A: Whenever you make a successful run, if your MU is full, you may flip and gain 1c.
//Side B: Whenever you make a successful run, if you have at least 1 unused MU, you may flip and draw 1 card.
cardSet[35023] = {
  title: "Dewi Subrotoputri: Pedagogical Dhalang",
  imageFile: "35023.jpg",
  player: runner,
  faction: "Shaper",
  link: 0,
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 15,
  subTypes: ["Natural"],
  
  //Track flip state (false = Side A, true = Side B)
  flipped: false,
  
  //Helper function to get current side name
  getCurrentSide: function() {
    return this.flipped ? "Side B" : "Side A";
  },
  
  //Helper function to get current image file based on flip state
  getCurrentImageFile: function() {
    return this.flipped ? "35023-0.jpg" : "35023.jpg";
  },
  
  //Whenever you make a successful run, check conditions and offer flip
  responseOnRunSuccessful: {
    Enumerate: function() {
      var installedMU = InstalledMemoryCost();
      var totalMU = MemoryUnits();
      
      if (!this.flipped) {
        //Side A: if MU is full, may flip and gain 1c
        if (installedMU >= totalMU) {
          var choices = [
            { id: 0, label: "Flip to Side B and gain 1[c]", button: "Flip for 1[c]" },
            { id: 1, label: "Decline", button: "Decline" }
          ];
          
          //**AI code
          if (runner.AI) {
            var shouldFlip = this.AIShouldFlipToB();
            if (shouldFlip) {
              runner.AI.preferred = { title: this.title, option: choices[0] };
            } else {
              runner.AI.preferred = { title: this.title, option: choices[1] };
            }
          }
          
          return choices;
        }
      } else {
        //Side B: if you have at least 1 unused MU, may flip and draw 1 card
        if (installedMU < totalMU) {
          var choices = [
            { id: 0, label: "Flip to Side A and draw 1 card", button: "Flip to draw" },
            { id: 1, label: "Decline", button: "Decline" }
          ];
          
          //**AI code
          if (runner.AI) {
            var shouldFlip = this.AIShouldFlipToA();
            if (shouldFlip) {
              runner.AI.preferred = { title: this.title, option: choices[0] };
            } else {
              runner.AI.preferred = { title: this.title, option: choices[1] };
            }
          }
          
          return choices;
        }
      }
      
      return []; //Conditions not met, no flip offered
    },
    Resolve: function(params) {
      if (params.id === 1) return; //Declined
      
      //Flip the identity
      this.flipped = !this.flipped;
      
      //Update the image file to show flipped side  
      this.imageFile = this.getCurrentImageFile();
      
      //Update the renderer's textures (but not masks - they should stay the same)
      if (typeof this.renderer !== 'undefined') {
        //Load the new texture
        var newFrontTexture = cardRenderer.LoadTexture("images/" + this.imageFile);
        
        //Update texture references
        this.renderer.frontTexture = newFrontTexture;
        this.renderer.loresTexture = newFrontTexture;
        
        //Update the dummy sprite texture
        if (this.renderer.dummy) {
          this.renderer.dummy.texture = newFrontTexture;
        }
        
        //Apply the texture
        this.renderer.SetTextureToFront();
      }
      
      Log("Dewi Subrotoputri flipped to " + this.getCurrentSide());
      
      if (this.flipped) {
        //Just flipped FROM Side A TO Side B
        //Side A gives 1 credit when flipping
        GainCredits(runner, 1);
      } else {
        //Just flipped FROM Side B TO Side A
        //Side B gives 1 card when flipping
        Draw(runner, 1);
      }
      
      //Render to update display
      Render();
    },
    text: "Flip identity based on MU state",
  },
  
  //AI helper: Should we flip from Side A to Side B?
  AIShouldFlipToB: function() {
    //Side A → Side B means: trading "gain 1c when MU full" for "draw 1 when MU not full"
    
    //Flip if: We're about to free up MU and want card draw
    var installedCards = InstalledCards(runner);
    
    //Check if we have a complete breaker suite
    var hasDecoder = false;
    var hasKiller = false;
    var hasFracter = false;
    
    for (var i = 0; i < installedCards.length; i++) {
      if (CheckSubType(installedCards[i], "Decoder")) hasDecoder = true;
      if (CheckSubType(installedCards[i], "Killer")) hasKiller = true;
      if (CheckSubType(installedCards[i], "Fracter")) hasFracter = true;
    }
    
    var hasCompleteBreakers = hasDecoder && hasKiller && hasFracter;
    
    //Flip if: Suite complete, low on cards, want draw more than credits
    if (hasCompleteBreakers && runner.grip.length < 4) return true;
    
    //Flip if: Have lots of credits, need cards
    if (Credits(runner) > 10 && runner.grip.length < 5) return true;
    
    //Otherwise, stay on Side A (credits are valuable)
    return false;
  },
  
  //AI helper: Should we flip from Side B to Side A?
  AIShouldFlipToA: function() {
    //Side B → Side A means: trading "draw 1 when MU not full" for "gain 1c when MU full"
    
    //Flip if: We're about to fill MU and want credits more than cards
    
    //Flip if: Low on credits, have plenty of cards
    if (Credits(runner) < 5 && runner.grip.length > 5) return true;
    
    //Flip if: About to install programs (will fill MU)
    var programsInGrip = 0;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["program"])) {
        programsInGrip++;
      }
    }
    if (programsInGrip >= 2 && Credits(runner) < 8) return true;
    
    //Otherwise, stay on Side B (card draw is valuable)
    return false;
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
  
  //Helper: Set up the strength preservation on a host
  setupHostOverride: function() {
    if (!this.host) return;
    if (!this.host.responseOnEncounterEnds) return;
    
    //Save original trigger if not already saved
    if (!this.originalHostEncounterEnd) {
      this.originalHostEncounterEnd = this.host.responseOnEncounterEnds;
    }
    
    //Replace with no-op for strength reset (strength will be preserved)
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
  
  //Helper: Offer hosting choice using card selection
  offerHostingChoice: function() {
    var validHosts = ChoicesInstalledCards(runner, function(card) {
      if (CheckSubType(card, "Icebreaker") && !CheckSubType(card, "AI")) {
        return true;
      }
      return false;
    });
    
    if (validHosts.length === 0) return;
    
    //Add decline option
    validHosts.push({ card: null, label: "Decline", button: "Decline" });
    
    var cardRef = this;
    
    //**AI code
    if (runner.AI != null) {
      //Prefer to host on the icebreaker with lowest base strength (benefits most from +1)
      var bestHost = null;
      var lowestStr = 999;
      for (var i = 0; i < validHosts.length; i++) {
        if (validHosts[i].card) {
          var baseStr = validHosts[i].card.strength || 0;
          if (baseStr < lowestStr) {
            lowestStr = baseStr;
            bestHost = validHosts[i];
          }
        }
      }
      if (bestHost) {
        validHosts = [bestHost];
      }
    }
    
    DecisionPhase(
      runner,
      validHosts,
      function(decision) {
        if (decision.card) {
          //Clean up old host if any
          if (cardRef.host) {
            cardRef.cleanupHostOverride();
            //Remove from old host's hostedCards
            if (cardRef.host.hostedCards) {
              var idx = cardRef.host.hostedCards.indexOf(cardRef);
              if (idx > -1) cardRef.host.hostedCards.splice(idx, 1);
            }
          }
          
          //Initialize hostedCards on new host if needed
          if (typeof decision.card.hostedCards === 'undefined') {
            decision.card.hostedCards = [];
          }
          
          //Remove from hardware rig if still there
          var hwIdx = runner.rig.hardware.indexOf(cardRef);
          if (hwIdx > -1) runner.rig.hardware.splice(hwIdx, 1);
          
          //Add to new host's hostedCards and update cardLocation
          decision.card.hostedCards.push(cardRef);
          cardRef.cardLocation = decision.card.hostedCards;
          cardRef.host = decision.card;
          
          Log(GetTitle(cardRef) + " hosted on " + GetTitle(decision.card));
          
          //Set up the strength preservation
          cardRef.setupHostOverride();
        }
      },
      "GAMEDRAGON™ Pro",
      "Host on icebreaker?",
      this,
      "host"
    );
  },
  
  //When you install this hardware, may host on icebreaker
  responseOnInstall: {
    Enumerate: function(card) {
      if (card !== this) return [];
      //Check if there are valid hosts
      var validHosts = ChoicesInstalledCards(runner, function(c) {
        if (CheckSubType(c, "Icebreaker") && !CheckSubType(c, "AI")) {
          return true;
        }
        return false;
      });
      if (validHosts.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      this.offerHostingChoice();
    },
  },
  
  //When your turn begins, may host on icebreaker
  responseOnRunnerTurnBegins: {
    Enumerate: function() {
      //Check if there are valid hosts
      var validHosts = ChoicesInstalledCards(runner, function(c) {
        if (CheckSubType(c, "Icebreaker") && !CheckSubType(c, "AI")) {
          return true;
        }
        return false;
      });
      if (validHosts.length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      this.offerHostingChoice();
    },
  },
  
  //Host icebreaker gets +1 strength
  modifyStrength: {
    Resolve: function(card) {
      if (this.host && card === this.host) {
        return 1; //+1 strength
      }
      return 0;
    },
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
        //Remove from host's hostedCards
        if (this.host && this.host.hostedCards) {
          var idx = this.host.hostedCards.indexOf(this);
          if (idx > -1) this.host.hostedCards.splice(idx, 1);
        }
        this.host = null;
      }
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  AIPreferredInstallChoice: function(choices) {
    //Install if we have icebreakers to host on
    var validHosts = ChoicesInstalledCards(runner, function(c) {
      if (CheckSubType(c, "Icebreaker") && !CheckSubType(c, "AI")) {
        return true;
      }
      return false;
    });
    if (validHosts.length > 0) return 0;
    return -1; //wait until we have icebreakers
  },
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if we have or might get icebreakers
    return true;
  },
};

//Ryō "Phoenix" Ōno: Out of the Ashes
//The first time each turn a run becomes successful after a subroutine resolved 
//during that run, gain 1 credit and the Corp trashes 1 card from HQ.
cardSet[35001] = {
  title: "Ryō \"Phoenix\" Ōno: Out of the Ashes",
  imageFile: "35001.png",
  player: runner,
  faction: "Anarch",
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 17,
  subTypes: ["G-mod"],
  link: 0,
  
  //Tracking flags
  usedThisTurn: false,
  subroutineResolvedThisRun: false,
  
  //Reset "first time each turn" at turn begin
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  //Reset subroutine tracking at run begin
  automaticOnRunBegins: {
    Resolve: function(server) {
      this.subroutineResolvedThisRun = false;
    },
  },
  
  //Track when subroutines resolve by detecting entry into subroutine phase
  //with unbroken subroutines. By the time we're in "Run Subroutines" phase,
  //the runner has had their chance to break - any unbroken subs WILL fire.
  //This avoids false positives from bypassed ice (bypass skips this phase).
  automaticOnAnyChange: {
    Resolve: function() {
      //Skip if already tracked or not in a run
      if (this.subroutineResolvedThisRun) return;
      if (!attackedServer) return;
      
      //Check if we're in the subroutine resolution phase
      if (currentPhase && currentPhase.identifier === "Run Subroutines") {
        //Check if there are any unbroken subroutines on the encountered ice
        if (typeof approachIce !== 'undefined' && approachIce >= 0 && 
            attackedServer.ice && approachIce < attackedServer.ice.length) {
          var ice = attackedServer.ice[approachIce];
          if (ice && ice.subroutines) {
            for (var i = 0; i < ice.subroutines.length; i++) {
              if (!ice.subroutines[i].broken) {
                //Found an unbroken subroutine - it will resolve
                this.subroutineResolvedThisRun = true;
                return;
              }
            }
          }
        }
      }
    },
  },
  
  //Trigger on successful run if a subroutine resolved during this run
  responseOnRunSuccessful: {
    Enumerate: function() {
      if (this.usedThisTurn) return [];
      if (!this.subroutineResolvedThisRun) return [];
      return [{}];
    },
    Resolve: function() {
      this.usedThisTurn = true;
      
      //Gain 1 credit
      GainCredits(runner, 1);
      
      //Corp trashes 1 card from HQ
      if (corp.HQ.cards.length > 0) {
        var choices = ChoicesArrayCards(corp.HQ.cards);
        DecisionPhase(
          corp,
          choices,
          function(params) {
            Trash(params.card, false); //unpreventable
          },
          "Ryō \"Phoenix\" Ōno",
          "Trash 1 card from HQ",
          this,
          "discard"
        );
        
        //**AI code
        if (corp.AI != null) {
          //Trash lowest value card (prefer operations, then non-ice)
          var bestChoice = choices[0];
          var bestValue = 999;
          for (var i = 0; i < choices.length; i++) {
            var card = choices[i].card;
            var value = 10; //default
            if (CheckCardType(card, ["operation"])) value = 3;
            else if (CheckCardType(card, ["asset", "upgrade"])) value = 5;
            else if (CheckCardType(card, ["ice"])) value = 7;
            else if (CheckCardType(card, ["agenda"])) value = 15; //never trash agendas if avoidable
            if (value < bestValue) {
              bestValue = value;
              bestChoice = choices[i];
            }
          }
          corp.AI.preferred = { title: "Ryō \"Phoenix\" Ōno", option: bestChoice };
        }
      }
    },
    text: "Gain 1 credit, Corp trashes 1 card from HQ",
  },
  
  //**AI code for runner - this identity benefits from successful runs through ice
  AIPerformRun: function(server) {
    //No special run behavior - standard run logic applies
    return null;
  },
};

//Topan: Ormas Leader
//Once per turn -> [click]: Install 1 card from your grip, paying 2[c] less.
//When you install that card, suffer 1 meat damage.
cardSet[35002] = {
  title: "Topan: Ormas Leader",
  imageFile: "35002.png",
  player: runner,
  faction: "Anarch",
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 12,
  link: 0,
  
  //Track which card is being installed via Topan's ability
  topanInstallingCard: null,
  topanAbilityActive: false,
  usedThisTurn: false,
  
  //Reset "once per turn" flag
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  responseOnCorpTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  //Once per turn -> [click]: Install 1 card from your grip, paying 2[c] less
  abilities: [
    {
      text: "Install 1 card from grip, paying 2[c] less",
      Enumerate: function() {
        if (this.usedThisTurn) return [];
        if (!CheckActionClicks(runner, 1)) return [];
        
        return [{}];
      },
      Resolve: function(params) {
        this.usedThisTurn = true;
        SpendClicks(runner, 1);
        
        //NOW activate the ability and enable discount
        this.topanAbilityActive = true;
        this.modifyInstallCost.availableWhenInactive = true;
        
        //Get choices with discount active
        var choices = ChoicesHandInstall(runner);
        
        if (choices.length === 0) {
          //No valid choices, cleanup
          this.topanAbilityActive = false;
          this.modifyInstallCost.availableWhenInactive = false;
          return;
        }
        
        var cardRef = this;
        
        DecisionPhase(
          runner,
          choices,
          function(installParams) {
            //Mark this specific card for damage tracking
            cardRef.topanInstallingCard = installParams.card;
            
            //Install the card
            Install(
              installParams.card, 
              installParams.host, 
              false, 
              null, 
              true,
              function() {
                //On install complete - cleanup happens in automaticOnInstall
              },
              cardRef,
              function() {
                //On cancel: cleanup
                cardRef.topanInstallingCard = null;
                cardRef.topanAbilityActive = false;
                cardRef.modifyInstallCost.availableWhenInactive = false;
              }
            );
          },
          "Topan: Ormas Leader",
          "Install 1 card from grip (paying 2[c] less)",
          this,
          "install"
        );
      },
    },
  ],
  
  //Provide 2 credit discount for card installed via ability
  modifyInstallCost: {
    Resolve: function(card) {
      //Only apply discount when Topan's ability is active
      if (!this.topanAbilityActive) return 0;
      
      //Discount all cards in grip for display
      if (runner.grip.includes(card)) {
        if (CheckCardType(card, ["program", "hardware", "resource"])) {
          return -2;
        }
      }
      //Also discount the specific card being installed
      if (card == this.topanInstallingCard) {
        return -2;
      }
      return 0;
    },
    automatic: true,
    availableWhenInactive: false, //toggled dynamically
  },
  
  //When you install that card, suffer 1 meat damage
  automaticOnInstall: {
    Resolve: function(card) {
      if (card == this.topanInstallingCard) {
        //Cleanup before damage
        this.topanInstallingCard = null;
        this.topanAbilityActive = false;
        this.modifyInstallCost.availableWhenInactive = false;
        
        //Damage can be prevented
        Damage("meat", 1, true);
      }
    },
  },
  
  //**AI code
  AIWouldTrigger: function() {
    //Use if we have installable cards and would save credits
    var installedRunnerCards = InstalledCards(runner);
    for (var i = 0; i < runner.grip.length; i++) {
      var card = runner.grip[i];
      if (CheckCardType(card, ["program", "hardware", "resource"])) {
        var installCost = InstallCost(card);
        //Worth using if saves at least 2 credits and we can afford even with discount
        if (installCost >= 2) {
          var discountedCost = Math.max(0, installCost - 2);
          if (CheckCredits(runner, discountedCost, "installing", card)) {
            //Check if we can afford to lose a card to meat damage
            if (runner.grip.length > 2) return true;
          }
        }
      }
    }
    return false;
  },
};

//Charm Offensive
//Run Archives. When that run ends, you may trash 1 rezzed copy of a card 
//you accessed in Archives during that run.
cardSet[35003] = {
  title: "Charm Offensive",
  imageFile: "35003.png",
  player: runner,
  faction: "Anarch",
  influence: 2,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 0,
  
  //Tracking
  runningWithThis: false,
  accessedTitlesThisRun: [],
  
  //Run Archives
  Enumerate: function() {
    return [{ server: corp.archives, label: "Archives" }];
  },
  Resolve: function(params) {
    this.runningWithThis = true;
    this.accessedTitlesThisRun = [];
    MakeRun(corp.archives);
  },
  
  //Track accessed card titles during this run (only in Archives)
  automaticOnAccess: {
    Resolve: function(card) {
      if (!this.runningWithThis) return;
      //Only track cards accessed from Archives
      if (attackedServer === corp.archives) {
        var title = GetTitle(card);
        if (this.accessedTitlesThisRun.indexOf(title) === -1) {
          this.accessedTitlesThisRun.push(title);
        }
      }
    },
  },
  
  //When that run ends, may trash 1 rezzed copy
  responseOnRunEnds: {
    Enumerate: function() {
      if (!this.runningWithThis) return [];
      if (this.accessedTitlesThisRun.length === 0) return [];
      
      //Find rezzed cards that match accessed titles
      var validTargets = [];
      var installedCorpCards = InstalledCards(corp);
      for (var i = 0; i < installedCorpCards.length; i++) {
        var card = installedCorpCards[i];
        if (card.rezzed) {
          //Check if card's modifyCannot prevents trashing
          if (card.modifyCannot && typeof card.modifyCannot.Resolve === "function") {
            if (card.modifyCannot.Resolve.call(card, "trash", card)) {
              continue; //skip this card
            }
          }
          var title = GetTitle(card);
          if (this.accessedTitlesThisRun.indexOf(title) !== -1) {
            validTargets.push(card);
          }
        }
      }
      
      if (validTargets.length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      //Find rezzed cards that match accessed titles - clickable cards, no footer buttons
      var choices = [];
      var installedCorpCards = InstalledCards(corp);
      for (var i = 0; i < installedCorpCards.length; i++) {
        var card = installedCorpCards[i];
        if (card.rezzed) {
          //Check if card's modifyCannot prevents trashing
          if (card.modifyCannot && typeof card.modifyCannot.Resolve === "function") {
            if (card.modifyCannot.Resolve.call(card, "trash", card)) {
              continue; //skip this card
            }
          }
          var title = GetTitle(card);
          if (this.accessedTitlesThisRun.indexOf(title) !== -1) {
            choices.push({ card: card, label: "Trash " + GetTitle(card, true) });
          }
        }
      }
      
      //Add decline option - only this shows in footer
      choices.push({ card: null, label: "Decline", button: "Decline Charm Offensive" });
      
      DecisionPhase(
        runner,
        choices,
        function(params) {
          if (params.card) {
            //Trash the card (ability effect, not paying trash cost)
            Trash(params.card, true);
          }
        },
        "Charm Offensive",
        "Trash 1 rezzed copy?",
        this,
        "trash"
      );
      
      //**AI code
      if (runner.AI != null) {
        //Prefer trashing ice, then upgrades, then assets
        var bestChoice = choices[choices.length - 1]; //decline by default
        var bestValue = 0;
        for (var i = 0; i < choices.length - 1; i++) {
          var card = choices[i].card;
          var value = 1;
          if (CheckCardType(card, ["ice"])) value = 5; //ice is high value target
          else if (CheckCardType(card, ["upgrade"])) value = 3;
          else if (CheckCardType(card, ["asset"])) value = 2;
          if (value > bestValue) {
            bestValue = value;
            bestChoice = choices[i];
          }
        }
        runner.AI.preferred = { title: "Charm Offensive", option: bestChoice };
      }
    },
    text: "Trash 1 rezzed copy of an accessed card",
  },
  
  //Reset flag when run ends
  automaticOnRunEnds: {
    Resolve: function() {
      this.runningWithThis = false;
    },
  },
  
  //**AI code
  AIPlayPriority: function() {
    //Check if there are any cards in Archives that have rezzed copies
    for (var i = 0; i < corp.archives.cards.length; i++) {
      var archivedTitle = GetTitle(corp.archives.cards[i]);
      var installedCorpCards = InstalledCards(corp);
      for (var j = 0; j < installedCorpCards.length; j++) {
        if (installedCorpCards[j].rezzed && GetTitle(installedCorpCards[j]) === archivedTitle) {
          //Found a potential target - especially good if it's ice
          if (CheckCardType(installedCorpCards[j], ["ice"])) return 3;
          return 2;
        }
      }
    }
    //No good targets, but Archives run is still free economy info
    return 1;
  },
};

//Detente
//+1 MU
//The first time each turn you make a successful run on HQ, you may host 1 card 
//from HQ at random faceup on this hardware. (It is not installed or rezzed.)
//[click], add 2 hosted cards to HQ: The Runner may access 1 card in HQ at random. 
//Any player can use this ability.
//Limit 1 console per player.
cardSet[35018] = {
  title: "Detente",
  imageFile: "35018.png",
  player: runner,
  faction: "Criminal",
  influence: 3,
  cardType: "hardware",
  subTypes: ["Console"],
  installCost: 3,
  unique: true,
  memoryUnits: 1,
  
  //Tracking for "first time each turn"
  usedThisTurn: false,
  
  //Initialize hostedCards on install (not at card definition level to avoid persistence between games)
  //Cards hosted on Detente use the notInstalled flag so engine doesn't treat them as installed
  automaticOnInstall: {
    Resolve: function(card) {
      if (card === this) {
        this.hostedCards = [];
      }
    },
  },
  
  //When Detente is trashed, return held cards to HQ
  automaticOnTrash: {
    Resolve: function(card) {
      if (card === this && this.hostedCards && this.hostedCards.length > 0) {
        for (var i = 0; i < this.hostedCards.length; i++) {
          var heldCard = this.hostedCards[i];
          heldCard.faceUp = false;
          heldCard.notInstalled = false; //clear the flag
          heldCard.host = null;
          corp.HQ.cards.push(heldCard);
          heldCard.cardLocation = corp.HQ.cards;
          Log(GetTitle(heldCard, true) + " returned to HQ");
        }
        this.hostedCards = [];
      }
    },
  },
  
  //Reset at runner turn begin
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  //The first time each turn you make a successful run on HQ, 
  //you may host 1 card from HQ at random faceup
  responseOnRunSuccessful: {
    Enumerate: function() {
      if (this.usedThisTurn) return [];
      if (attackedServer !== corp.HQ) return [];
      if (corp.HQ.cards.length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      this.usedThisTurn = true;
      
      //Ask runner if they want to host a card
      var cardRef = this;
      var choices = [
        { id: 0, label: "Host 1 card from HQ", button: "Host on Detente" },
        { id: 1, label: "Decline", button: "Decline" }
      ];
      
      //**AI code
      if (runner.AI != null) {
        //Always host if possible - builds up for the ability
        choices = [choices[0]];
      }
      
      DecisionPhase(
        runner,
        choices,
        function(params) {
          if (params.id === 0 && corp.HQ.cards.length > 0) {
            //Pick random card from HQ
            var randomIndex = Math.floor(Math.random() * corp.HQ.cards.length);
            var cardToHost = corp.HQ.cards[randomIndex];
            
            //Initialize hostedCards if needed (safety check)
            if (typeof cardRef.hostedCards === 'undefined') {
              cardRef.hostedCards = [];
            }
            
            //Move card to hostedCards (faceup but NOT installed or rezzed per card text)
            var idx = corp.HQ.cards.indexOf(cardToHost);
            if (idx > -1) corp.HQ.cards.splice(idx, 1);
            cardRef.hostedCards.push(cardToHost);
            cardToHost.cardLocation = cardRef.hostedCards;
            cardToHost.faceUp = true;
            cardToHost.rezzed = false;
            cardToHost.notInstalled = true; //engine flag to exclude from InstalledCards()
            cardToHost.host = cardRef;
            
            Log(GetTitle(cardToHost, true) + " hosted on " + GetTitle(cardRef));
            
            //AI learns about this card
            if (runner.AI != null) {
              runner.AI.LoseInfoAboutHQCards(cardToHost);
            }
          }
        },
        "Detente",
        "Host 1 card from HQ at random?",
        this
      );
    },
    text: "Host 1 card from HQ at random",
  },
  
  //[click], add 2 hosted cards to HQ: The Runner may access 1 card in HQ at random.
  //Any player can use this ability.
  activeForOpponent: true, //allows corp to see and use abilities on this card
  abilities: [
    {
      //Runner's version
      text: "Add 2 hosted cards to HQ: Access 1 card in HQ",
      Enumerate: function() {
        if (typeof this.hostedCards === 'undefined' || this.hostedCards.length < 2) return [];
        if (!CheckActionClicks(runner, 1)) return [];
        return [{}];
      },
      Resolve: function(params) {
        this.resolveDetente(runner);
      },
    },
    {
      //Corp's version
      text: "Add 2 hosted cards to HQ: Runner accesses 1 card in HQ",
      opponentOnly: true,
      Enumerate: function() {
        if (typeof this.hostedCards === 'undefined' || this.hostedCards.length < 2) return [];
        if (!CheckActionClicks(corp, 1)) return [];
        return [{}];
      },
      Resolve: function(params) {
        this.resolveDetente(corp);
      },
    },
  ],
  
  //Shared resolve function for both abilities
  resolveDetente: function(spendingPlayer) {
    var cardRef = this;
    SpendClicks(spendingPlayer, 1);
    
    //Return 2 hosted cards to HQ
    var cardsToReturn = cardRef.hostedCards.splice(0, 2);
    for (var i = 0; i < cardsToReturn.length; i++) {
      cardsToReturn[i].faceUp = false;
      cardsToReturn[i].notInstalled = false; //clear the flag
      cardsToReturn[i].host = null;
      corp.HQ.cards.push(cardsToReturn[i]);
      cardsToReturn[i].cardLocation = corp.HQ.cards;
      Log(GetTitle(cardsToReturn[i], true) + " returned to HQ");
    }
    
    //Runner may access 1 card in HQ at random
    if (corp.HQ.cards.length === 0) {
      Log("HQ is empty, no card to access");
      return;
    }
    
    //Ask runner if they want to access
    var accessChoices = [
      { id: 0, label: "Access 1 card in HQ at random", button: "Access" },
      { id: 1, label: "Decline", button: "Decline" }
    ];
    
    //**AI code
    if (runner.AI != null) {
      //Always access
      accessChoices = [accessChoices[0]];
    }
    
    DecisionPhase(
      runner,
      accessChoices,
      function(decision) {
        if (decision.id === 0 && corp.HQ.cards.length > 0) {
          //Pick random card from HQ
          var randomIndex = Math.floor(Math.random() * corp.HQ.cards.length);
          var cardToAccess = corp.HQ.cards[randomIndex];
          
          //Show the card
          cardToAccess.renderer.canView = true;
          cardToAccess.renderer.ToggleZoom();
          Log("Accessing " + GetTitle(cardToAccess, true) + " from HQ");
          
          //Fire "on access" triggers (for ambushes like Snare!, Urtica, etc.)
          AutomaticTriggers("automaticOnAccess", [cardToAccess]);
          
          //Use TriggeredResponsePhase for "when accessed" abilities (like Snare!'s optional damage)
          TriggeredResponsePhase(corp, "responseOnAccess", [cardToAccess], function() {
            //After access triggers resolve, let runner decide what to do
            cardRef.resolveDetenteAccess(cardToAccess);
          }, "Accessed");
        }
      },
      "Detente",
      "Access 1 card in HQ at random?",
      cardRef
    );
  },
  
  //Helper function to resolve the access after triggers
  resolveDetenteAccess: function(cardToAccess) {
    var cardRef = this;
    
    //Check if card is still in HQ (might have been trashed by ambush, etc.)
    if (corp.HQ.cards.indexOf(cardToAccess) === -1) {
      cardToAccess.renderer.canView = false;
      return;
    }
    
    //Build choices for what to do with the accessed card
    var actionChoices = [];
    
    //Can steal if it's an agenda and stealing is allowed (CheckSteal handles Film Critic, etc.)
    //Temporarily set accessingCard for CheckSteal to work properly
    var oldAccessingCard = accessingCard;
    accessingCard = cardToAccess;
    
    if (CheckSteal()) {
      actionChoices.push({ id: "steal", card: cardToAccess, label: "Steal " + GetTitle(cardToAccess, true), button: "Steal" });
    }
    
    //Can trash if it has a trash cost and runner can afford
    if (typeof cardToAccess.trashCost !== "undefined" && CheckTrash(cardToAccess)) {
      var trashCost = TrashCost(cardToAccess);
      if (CheckCredits(runner, trashCost, "paying trash costs", cardToAccess)) {
        actionChoices.push({ id: "trash", card: cardToAccess, cost: trashCost, label: "Trash for " + trashCost + "[c]", button: "Trash (" + trashCost + "[c])" });
      }
    }
    
    //Restore accessingCard
    accessingCard = oldAccessingCard;
    
    //Can always continue (unless it's an agenda that must be stolen)
    if (actionChoices.length === 0 || actionChoices[0].id !== "steal" || !CheckCardType(cardToAccess, ["agenda"])) {
      actionChoices.push({ id: "continue", card: cardToAccess, label: "Continue", button: "Continue" });
    }
    
    //**AI code
    if (runner.AI != null) {
      //Steal agendas, trash expensive assets, otherwise continue
      for (var i = 0; i < actionChoices.length; i++) {
        if (actionChoices[i].id === "steal") {
          actionChoices = [actionChoices[i]];
          break;
        }
      }
    }
    
    DecisionPhase(
      runner,
      actionChoices,
      function(action) {
        if (action.id === "steal") {
          SetHistoryThumbnail(action.card.imageFile, "Steal");
          MoveCard(action.card, runner.scoreArea);
          action.card.faceUp = true;
          if (runner.AI != null) runner.AI.LoseInfoAboutHQCards(action.card);
          Log(GetTitle(action.card, true) + " stolen");
          action.card.renderer.canView = false;
          //Fire stolen triggers
          TriggeredResponsePhase(playerTurn, "responseOnStolen", [], function() {
            action.card.advancement = 0;
          }, "Stolen");
        } else if (action.id === "trash") {
          SpendCredits(runner, action.cost, "paying trash costs", action.card, function() {
            SetHistoryThumbnail(action.card.imageFile, "Trash");
            Trash(action.card, true);
            action.card.renderer.canView = false;
          }, cardRef);
        } else {
          //Continue - just hide the card
          action.card.renderer.canView = false;
        }
      },
      "Detente: Access",
      "Accessed " + GetTitle(cardToAccess, true),
      cardRef
    );
  },
  
  //**AI code
  AIWorthKeeping: function(installedRunnerCards) {
    //Console that provides MU - always worth keeping
    return true;
  },
};

//Card 21: Humanoid Resources
//Haas-Bioroid Asset
//Cost: 1, Trash: 1
//[click][click][click], [trash]: Gain 4 credits and draw 3 cards. Install up to 2 cards from HQ (one at a time). You may play 1 operation from HQ.
cardSet[35039] = {
  title: "Humanoid Resources",
  imageFile: "35039.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 2,
  cardType: "asset",
  rezCost: 1,
  trashCost: 1,
  
  abilities: [
    {
      text: "[click][click][click], [trash]: Gain 4[c] and draw 3 cards. Install up to 2 cards. You may play 1 operation.",
      Enumerate: function() {
        if (!this.rezzed) return [];
        if (!CheckActionClicks(corp, 3)) return [];
        if (!CheckTrash(this)) return [];
        return [{}];
      },
      Resolve: function() {
        var cardRef = this;
        SpendClicks(corp, 3);
        
        //Gain 4 credits immediately (before trash/draw so they can be used)
        GainCredits(corp, 4);
        Log(GetTitle(cardRef) + " gains Corp 4[c]");
        
        //Trash is async, put rest in callback (false = cannot prevent)
        Trash(this, false, function(cardsTrashed) {
          //Draw 3 cards, then proceed to installs
          Draw(corp, 3, function() {
            //Now install up to 2 cards (one at a time) then optionally play operation
            humanoidResourcesInstall(cardRef, 0);
          }, cardRef);
        }, this);
      },
    },
  ],
};

//Helper function for Humanoid Resources - chained installation
function humanoidResourcesInstall(cardRef, installCount) {
  var installChoices = ChoicesHandInstall(corp); //respect install costs
  
  //Build choice list
  var choices = [];
  for (var i = 0; i < installChoices.length; i++) {
    choices.push(installChoices[i]);
  }
  
  var skipLabel = installCount < 2 ? "Skip install" : "Done installing";
  choices.push({ card: null, label: skipLabel, button: skipLabel });
  
  DecisionPhase(
    corp,
    choices,
    function(params) {
      if (params.card !== null) {
        Install(params.card, params.server);
        installCount++;
        //Continue to next install or operation
        if (installCount < 2) {
          humanoidResourcesInstall(cardRef, installCount);
        } else {
          humanoidResourcesPlayOp(cardRef);
        }
      } else {
        //Skipped install, move to operation
        humanoidResourcesPlayOp(cardRef);
      }
    },
    "Humanoid Resources",
    "Install card from HQ (" + installCount + "/2 installed)",
    cardRef,
    "install"
  );
}

//Helper function for Humanoid Resources - play operation
function humanoidResourcesPlayOp(cardRef) {
  //Build list of operations in HQ
  var opChoices = [];
  for (var i = 0; i < corp.HQ.cards.length; i++) {
    var card = corp.HQ.cards[i];
    if (card.cardType === "operation") {
      //Check if playable (cost check)
      if (AvailableCredits(corp, "playing", card) >= PlayCost(card)) {
        //No button property - card will show as clickable card, not footer button
        opChoices.push({ card: card, label: card.title });
      }
    }
  }
  
  //Only Skip appears in footer
  opChoices.push({ card: null, label: "Skip Playing 1 Operation from HQ", button: "Skip Playing 1 Operation from HQ" });
  
  DecisionPhase(
    corp,
    opChoices,
    function(params) {
      if (params.card !== null) {
        //Play the operation
        var opCard = params.card;
        SpendCredits(corp, PlayCost(opCard), "playing", opCard, function() {
          Play(opCard);
        }, cardRef);
      }
      //Otherwise done
    },
    "Humanoid Resources",
    "Play operation from HQ?",
    cardRef
  );
}

//Card 22: Scatter Field
//Haas-Bioroid Ice - Code Gate
//Cost: 3, Strength: 0
//While this ice is the only piece of ice protecting this server, it gets +4 strength.
//↳ You may install 1 card from HQ.
//↳ End the run.
cardSet[35042] = {
  title: "Scatter Field",
  imageFile: "35042.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 2,
  cardType: "ice",
  subTypes: ["Code Gate"],
  rezCost: 3,
  strength: 0,
  
  //While this ice is the only piece of ice protecting this server, it gets +4 strength.
  modifyStrength: {
    Resolve: function(card) {
      if (card === this) {
        var server = GetServer(this);
        if (server && server.ice && server.ice.length === 1) {
          return 4;
        }
      }
      return 0;
    },
  },
  
  subroutines: [
    {
      text: "You may install 1 card from HQ.",
      Resolve: function() {
        var installChoices = ChoicesHandInstall(corp); //respect install costs
        
        if (installChoices.length === 0) {
          Log("No cards to install from HQ");
          return;
        }
        
        //Add decline option
        installChoices.push({ card: null, label: "Decline", button: "Decline" });
        
        DecisionPhase(
          corp,
          installChoices,
          function(params) {
            if (params.card !== null) {
              Install(params.card, params.server);
            }
          },
          "Scatter Field",
          "Install card from HQ?",
          this,
          "install"
        );
      },
      visual: { y: 95, h: 16 },
    },
    {
      text: "End the run.",
      Resolve: function() {
        EndTheRun();
      },
      visual: { y: 115, h: 16 },
    },
  ],
  
  //**AI code
  AIRezReasons: function() {
    return { facecheck: true, etr: true };
  },
};

//Card 23: Project Ingatan
//Haas-Bioroid Agenda - Research
//Advancement: 3, Points: 2
//Dividends 1 (When you score this agenda, place 1 agenda counter on it for each excess advancement counter.)
//When your discard phase ends, you may remove 1 hosted agenda counter to install 1 card from Archives, ignoring all costs.
cardSet[35038] = {
  title: "Project Ingatan",
  imageFile: "35038.png",
  player: corp,
  faction: "Haas-Bioroid",
  cardType: "agenda",
  subTypes: ["Research"],
  agendaPoints: 2,
  advancementRequirement: 3,
  
  //Dividends 1: When scored, place 1 agenda counter for each excess advancement
  responseOnScored: {
    Resolve: function() {
      //Calculate excess advancement (advancement - requirement)
      var excess = this.advancement - this.advancementRequirement;
      if (excess > 0) {
        //Dividends 1 means 1 counter per excess
        AddCounters(this, "agenda", excess);
        Log(GetTitle(this) + " places " + excess + " agenda counter" + (excess > 1 ? "s" : "") + " (Dividends)");
      }
    },
    automatic: true,
  },
  
  //When your discard phase ends, may remove 1 agenda counter to install from Archives
  responseOnCorpDiscardEnds: {
    Enumerate: function() {
      //Need at least 1 agenda counter
      if (!CheckCounters(this, "agenda", 1)) return [];
      //Need installable cards in Archives (use ChoicesArrayCards since we're ignoring costs)
      var archivesChoices = [];
      for (var i = 0; i < corp.archives.cards.length; i++) {
        var card = corp.archives.cards[i];
        //Can install assets, upgrades, ice, and agendas (not operations)
        if (card.cardType !== "operation") {
          archivesChoices.push({ card: card, label: card.title });
        }
      }
      if (archivesChoices.length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      var cardRef = this;
      
      //Build choices list for installable cards
      var archivesChoices = [];
      for (var i = 0; i < corp.archives.cards.length; i++) {
        var card = corp.archives.cards[i];
        if (card.cardType !== "operation") {
          archivesChoices.push({ card: card, label: card.title });
        }
      }
      
      //Add decline option
      archivesChoices.push({ card: null, label: "Decline", button: "Decline" });
      
      DecisionPhase(
        corp,
        archivesChoices,
        function(params) {
          if (params.card !== null) {
            //Pass cardRef to helper so it can remove counter after install
            projectIngatanInstall(cardRef, params.card);
          }
        },
        "Project Ingatan",
        "Install card from Archives? (ignoring all costs)",
        cardRef
      );
    },
    text: "Remove 1 agenda counter to install 1 card from Archives",
  },
  
  //**AI code
  AIOverAdvance: function() {
    //Worth over-advancing for the dividend counters
    return 2; //up to 2 extra advancements
  },
};

//Helper function for Project Ingatan - install with server selection
function projectIngatanInstall(cardRef, cardToInstall) {
  //Build server choices based on card type
  var serverChoices = [];
  
  if (cardToInstall.cardType === "ice") {
    //Ice can protect any server including new remotes
    serverChoices.push({ server: corp.HQ, label: "HQ" });
    serverChoices.push({ server: corp.RnD, label: "R&D" });
    serverChoices.push({ server: corp.archives, label: "Archives" });
    for (var j = 0; j < corp.remoteServers.length; j++) {
      serverChoices.push({
        server: corp.remoteServers[j],
        label: corp.remoteServers[j].serverName
      });
    }
    serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  } else if (cardToInstall.cardType === "upgrade") {
    //Upgrades can go in root of any server or new remote
    serverChoices.push({ server: corp.HQ, label: "HQ" });
    serverChoices.push({ server: corp.RnD, label: "R&D" });
    serverChoices.push({ server: corp.archives, label: "Archives" });
    for (var j = 0; j < corp.remoteServers.length; j++) {
      serverChoices.push({
        server: corp.remoteServers[j],
        label: corp.remoteServers[j].serverName
      });
    }
    serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  } else {
    //Assets and agendas go in remotes only
    for (var j = 0; j < corp.remoteServers.length; j++) {
      serverChoices.push({
        server: corp.remoteServers[j],
        label: corp.remoteServers[j].serverName
      });
    }
    serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  }
  
  DecisionPhase(
    corp,
    serverChoices,
    function(params) {
      //Remove the agenda counter now that install is confirmed
      RemoveCounters(cardRef, "agenda", 1);
      //Create new server if needed, otherwise Install handles it
      var targetServer = params.server;
      //Install with ignoreAllCosts = true
      //Pass null for new remote - Install will create the server and trigger responseOnCreateServer
      Install(cardToInstall, targetServer, true);
    },
    "Project Ingatan",
    "Choose where to install " + cardToInstall.title,
    cardRef,
    "server"
  );
}

//Card 24: Kessleroid
//Weyland Ice - Barrier
//Cost: 2, Strength: 1
//The Runner cannot trash this ice (while it is rezzed).
//↳ End the run.
//↳ End the run.
cardSet[35075] = {
  title: "Kessleroid",
  imageFile: "35075.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 1,
  cardType: "ice",
  subTypes: ["Barrier"],
  rezCost: 2,
  strength: 1,
  
  //The Runner cannot trash this ice (while it is rezzed)
  modifyCannot: {
    Resolve: function(str, card) {
      //Only protect this specific ice, only while rezzed, only from Runner
      if (str === "trash" && card === this && this.rezzed && activePlayer === runner) {
        return true; //cannot trash
      }
      return false;
    },
    availableWhenInactive: true,
  },
  
  subroutines: [
    {
      text: "End the run.",
      Resolve: function() {
        EndTheRun();
      },
      visual: { y: 93, h: 16 },
    },
    {
      text: "End the run.",
      Resolve: function() {
        EndTheRun();
      },
      visual: { y: 114, h: 16 },
    },
  ],
  
  //**AI code
  AIRezReasons: function() {
    return { facecheck: true, etr: true };
  },
};

//Card 25: Bumi 1.0
//Haas-Bioroid Ice - Sentry - Bioroid - AP - Destroyer
//Cost: 3, Strength: 3
//When you rez this ice during a run against this server, you may trash 1 installed trojan program.
//Lose [click]: Break 1 subroutine on this ice. Only the Runner can use this ability.
//↳ Trash 1 installed program.
//↳ Do 1 core damage.
cardSet[35041] = {
  title: "Bumi 1.0",
  imageFile: "35041.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 1,
  cardType: "ice",
  subTypes: ["Sentry", "Bioroid", "AP", "Destroyer"],
  rezCost: 3,
  strength: 3,
  
  //When you rez this ice during a run against this server, you may trash 1 installed trojan program
  responseOnRez: {
    //Helper to find all trojan programs (including those hosted on ice)
    _findTrojans: function() {
      var trojans = [];
      //Check runner's rig
      for (var i = 0; i < runner.rig.programs.length; i++) {
        var card = runner.rig.programs[i];
        if (CheckSubType(card, "Trojan") && CheckTrash(card)) {
          trojans.push(card);
        }
      }
      //Check programs hosted on corp ice (like Botulus, Chisel, etc.)
      var allIce = corp.HQ.ice.concat(corp.RnD.ice).concat(corp.archives.ice);
      for (var i = 0; i < corp.remoteServers.length; i++) {
        allIce = allIce.concat(corp.remoteServers[i].ice);
      }
      for (var i = 0; i < allIce.length; i++) {
        if (typeof allIce[i].hostedCards !== "undefined") {
          for (var j = 0; j < allIce[i].hostedCards.length; j++) {
            var card = allIce[i].hostedCards[j];
            if (card.player === runner && CheckCardType(card, ["program"]) && CheckSubType(card, "Trojan") && CheckTrash(card)) {
              trojans.push(card);
            }
          }
        }
      }
      return trojans;
    },
    Enumerate: function(card) {
      if (card !== this) return [];
      //Must be during a run against this server
      if (attackedServer === null) return [];
      if (attackedServer !== GetServer(this)) return [];
      //Check for installed trojan programs
      var trojans = this.responseOnRez._findTrojans();
      if (trojans.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      var cardRef = this;
      //Get trojan programs
      var trojans = this.responseOnRez._findTrojans();
      var choices = [];
      for (var i = 0; i < trojans.length; i++) {
        choices.push({ card: trojans[i], label: "Trash " + trojans[i].title });
      }
      choices.push({ card: null, label: "Decline", button: "Decline" });
      
      DecisionPhase(
        corp,
        choices,
        function(chosenParams) {
          if (chosenParams.card) {
            Trash(chosenParams.card, true); //true = can be prevented
          }
        },
        "Bumi 1.0",
        "You may trash 1 installed trojan program",
        cardRef,
        "trash"
      );
    },
    text: "Trash 1 installed trojan program",
  },
  
  //Lose [click]: Break 1 subroutine on this ice. Only the Runner can use this ability.
  abilities: [
    {
      text: "Break 1 subroutine on this ice",
      Enumerate: function() {
        if (!CheckClicks(runner, 1)) return [];
        if (activePlayer !== runner) return [];
        if (!encountering) return [];
        if (GetApproachEncounterIce() !== this) return [];
        var choices = [];
        for (var i = 0; i < this.subroutines.length; i++) {
          var subroutine = this.subroutines[i];
          if (!subroutine.broken) {
            choices.push({
              subroutine: subroutine,
              label: 'Lose [click]: Break "' + subroutine.text + '"',
            });
          }
        }
        return choices;
      },
      Resolve: function(params) {
        SpendClicks(runner, 1);
        Break(params.subroutine);
      },
      opponentOnly: true,
    },
  ],
  activeForOpponent: true,
  
  subroutines: [
    {
      text: "Trash 1 installed program.",
      Resolve: function() {
        var choices = ChoicesInstalledCards(runner, function(card) {
          return CheckCardType(card, ["program"]) && CheckTrash(card);
        });
        if (choices.length === 0) {
          Log("No programs to trash");
          return;
        }
        DecisionPhase(
          corp,
          choices,
          function(params) {
            Trash(params.card, true); //true = can be prevented
          },
          "Bumi 1.0",
          "Trash 1 installed program",
          this,
          "trash"
        );
      },
      visual: { y: 145, h: 16 },
    },
    {
      text: "Do 1 core damage.",
      Resolve: function() {
        Damage("core", 1, true); //true = can be prevented
      },
      visual: { y: 167, h: 16 },
    },
  ],
  
  //**AI code
  AIRezReasons: function() {
    return { facecheck: true, program_trash: true, damage: true };
  },
};

//Card 26: Mahkota Langit Grid
//Neutral Corp Upgrade - Region
//Cost: 2, Trash: 2
//2 recurring credits. You can spend hosted credits to rez assets in the root of this server and ice protecting this server.
//Persistent → The trash cost of each asset in the root of this server is increased by 2 credits.
//Limit 1 region per server.
cardSet[35082] = {
  title: "Mahkota Langit Grid",
  imageFile: "35082.png",
  player: corp,
  faction: "Neutral",
  influence: 0,
  cardType: "upgrade",
  subTypes: ["Region"],
  rezCost: 2,
  trashCost: 2,
  
  //2 recurring credits - added on rez and refilled before Corp turn
  credits: 0,
  
  //When you rez this upgrade, refill to 2 hosted credits
  automaticOnRez: {
    Resolve: function(card) {
      if (card === this) {
        this.credits = 2;
      }
    },
  },
  
  //Before your turn begins, refill to 2 hosted credits (only fires when rezzed/active)
  responseOnCorpTurnBegins: {
    Resolve: function() {
      this.credits = 2;
    },
    automatic: true,
  },
  
  //You can spend hosted credits to rez assets in the root of this server and ice protecting this server
  canUseCredits: function(doing, card) {
    if (doing !== "rezzing") return false;
    if (!card) return false;
    var myServer = GetServer(this);
    var cardServer = GetServer(card);
    if (myServer !== cardServer) return false;
    //Assets in root or ice protecting this server
    if (CheckCardType(card, ["asset"]) || CheckCardType(card, ["ice"])) return true;
    return false;
  },
  
  //Persistent → The trash cost of each asset in the root of this server is increased by 2 credits
  //Store server in case card is trashed
  serverThisWasInstalledIn: null,
  automaticOnRunBegins: {
    Resolve: function(server) {
      //Store the server in case this is trashed
      this.serverThisWasInstalledIn = GetServer(this);
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //Persistent: If the runner trashes this card while accessing it, this ability still applies for the remainder of the run
  automaticOnWouldTrash: {
    Resolve: function(cards) {
      if (cards.includes(this) && this.rezzed && this === accessingCard) {
        this.modifyTrashCost.availableWhenInactive = true;
      }
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  modifyTrashCost: {
    Resolve: function(card) {
      if (!CheckCardType(card, ["asset"])) return 0;
      //Check current server, or stored server if trashed
      var myServer = GetServer(this);
      if (myServer === null) myServer = this.serverThisWasInstalledIn;
      var cardServer = GetServer(card);
      if (myServer === cardServer) return 2;
      return 0;
    },
  },
  
  //End persistence after run ends
  responseOnRunEnds: {
    Resolve: function(params) {
      this.modifyTrashCost.availableWhenInactive = false;
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //**AI code
  AIWouldRez: function() {
    //Rez if we have assets to protect or ice to rez cheaply
    var myServer = GetServer(this);
    if (!myServer) return false;
    //Check if there are assets in root that benefit from protection
    for (var i = 0; i < myServer.root.length; i++) {
      if (CheckCardType(myServer.root[i], ["asset"])) return true;
    }
    //Check if there's unrezzed ice we might want to rez
    for (var i = 0; i < myServer.ice.length; i++) {
      if (!myServer.ice[i].rezzed) return true;
    }
    return false;
  },
};

//Card 27: Mercia B4LL4RD
//Haas-Bioroid Upgrade - Bioroid - Academic
//Cost: 2, Trash: 2, Unique
//When your action phase ends, you may install 1 piece of ice from HQ, paying 1 credit less.
//If you do, move this upgrade to the root of the server that piece of ice is protecting.
cardSet[35045] = {
  title: "Mercia B4LL4RD",
  imageFile: "35045.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 2,
  cardType: "upgrade",
  subTypes: ["Bioroid", "Academic"],
  rezCost: 2,
  trashCost: 2,
  unique: true,
  
  //Track which ice we're installing for cost reduction and movement
  merciaInstallingIce: null,
  
  //When your action phase ends, you may install 1 piece of ice from HQ, paying 1 credit less
  responseOnCorpActionPhaseEnds: {
    Enumerate: function() {
      //Return all valid ice+server combinations (like ChoicesCardInstall does)
      //This allows drag-to-install with both card and server highlighting
      var choices = [];
      for (var i = 0; i < corp.HQ.cards.length; i++) {
        var card = corp.HQ.cards[i];
        if (CheckCardType(card, ["ice"])) {
          //Add each server option for this ice, checking affordability per server
          //Ice install cost = number of existing ice on that server, minus 1 for Mercia's discount
          
          //Centrals
          var servers = [
            { server: corp.HQ, label: "HQ" },
            { server: corp.RnD, label: "R&D" },
            { server: corp.archives, label: "Archives" }
          ];
          //Remotes
          for (var j = 0; j < corp.remoteServers.length; j++) {
            servers.push({
              server: corp.remoteServers[j],
              label: corp.remoteServers[j].serverName
            });
          }
          //New remote (cost = 0 ice, minus 1 = 0)
          servers.push({ server: null, label: "new server" });
          
          for (var k = 0; k < servers.length; k++) {
            var serverInfo = servers[k];
            //Calculate install cost for this server (ice cost = number of existing ice)
            var installCost;
            if (serverInfo.server === null) {
              installCost = 0; //new server has no ice
            } else {
              installCost = serverInfo.server.ice.length;
            }
            //Apply Mercia's -1 discount
            installCost = installCost - 1;
            if (installCost < 0) installCost = 0;
            
            if (CheckCredits(corp, installCost, "installing", card)) {
              choices.push({
                card: card,
                server: serverInfo.server,
                label: card.title + " -> " + serverInfo.label
              });
            }
          }
        }
      }
      //Add decline option
      if (choices.length > 0) {
        choices.push({ decline: true, label: "Decline", button: "Decline" });
      }
      return choices;
    },
    Resolve: function(params) {
      //Check for decline
      if (params.decline) return;
      
      //Mark this ice for cost reduction
      this.merciaInstallingIce = params.card;
      
      //Install the ice to the selected server
      Install(params.card, params.server, false);
    },
    text: "Install 1 piece of ice from HQ, paying 1[c] less",
  },
  
  //Reduce install cost by 1 for ice installed via this card's ability
  modifyInstallCost: {
    Resolve: function(card) {
      if (card === this.merciaInstallingIce) return -1;
      return 0;
    },
  },
  
  //After the ice is installed, move Mercia to that server
  automaticOnInstall: {
    Resolve: function(installedCard) {
      if (installedCard === this.merciaInstallingIce) {
        var targetServer = GetServer(installedCard);
        if (targetServer !== null) {
          MoveCard(this, targetServer.root);
          Log(GetTitle(this) + " moves to " + ServerName(targetServer));
        }
        this.merciaInstallingIce = null;
      }
    },
  },
  
  //**AI code
  AIWouldRez: function() {
    //Rez if we have ice in HQ to install
    for (var i = 0; i < corp.HQ.cards.length; i++) {
      if (CheckCardType(corp.HQ.cards[i], ["ice"])) return true;
    }
    return false;
  },
};

//Card 28: Anthill Excavation Contract
//Weyland Asset - Industrial
//Rez: 3, Trash: 1
//When you rez this asset, load 8 credits onto it. When it is empty, trash it.
//When your turn begins, take 4 credits from this asset and draw 1 card.
cardSet[35072] = {
  title: "Anthill Excavation Contract",
  imageFile: "35072.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 2,
  cardType: "asset",
  subTypes: ["Industrial"],
  rezCost: 3,
  trashCost: 1,
  
  //When you rez this asset, load 8 credits onto it.
  automaticOnRez: {
    Resolve: function(card) {
      if (card === this) LoadCredits(this, 8);
    },
  },
  
  //When your turn begins, take 4 credits from this asset and draw 1 card.
  responseOnCorpTurnBegins: {
    Enumerate: function() {
      //Won't trigger with less than 4 credits (doesn't say "take up to")
      if (!CheckCounters(this, "credits", 4)) return [];
      return [{}];
    },
    Resolve: function(params) {
      TakeCredits(corp, this, 4);
      Draw(corp, 1);
      //When it is empty, trash it.
      if (!CheckCounters(this, "credits", 1)) {
        Trash(this, true); //true = can be prevented
      }
    },
  },
  
  //Rez at end of Runner turn for maximum value
  RezUsability: function() {
    if (currentPhase.identifier === "Runner 2.2") return true;
    return false;
  },
};

//Card 29: Otto Campaign
//Haas-Bioroid Asset - Advertisement
//Rez: 2, Trash: 2
//When you rez this asset, load 6 credits onto it. When it is empty, trash it and gain [click][click].
//When your turn begins, take 2 credits from this asset.
cardSet[35040] = {
  title: "Otto Campaign",
  imageFile: "35040.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 3,
  cardType: "asset",
  subTypes: ["Advertisement"],
  rezCost: 2,
  trashCost: 2,
  
  //When you rez this asset, load 6 credits onto it.
  automaticOnRez: {
    Resolve: function(card) {
      if (card === this) LoadCredits(this, 6);
    },
  },
  
  //When your turn begins, take 2 credits from this asset.
  responseOnCorpTurnBegins: {
    Enumerate: function() {
      //Won't trigger with less than 2 credits (doesn't say "take up to")
      if (!CheckCounters(this, "credits", 2)) return [];
      return [{}];
    },
    Resolve: function(params) {
      TakeCredits(corp, this, 2);
      //When it is empty, trash it and gain [click][click].
      if (!CheckCounters(this, "credits", 1)) {
        Trash(this, true, function(cardsTrashed) {
          GainClicks(corp, 2);
        }, this);
      }
    },
  },
  
  //Rez at end of Runner turn for maximum value
  RezUsability: function() {
    if (currentPhase.identifier === "Runner 2.2") return true;
    return false;
  },
};

//Card 30: Nanomanagement
//Haas-Bioroid Operation
//Cost: 4
//Gain [click][click].
cardSet[35043] = {
  title: "Nanomanagement",
  imageFile: "35043.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 4,
  cardType: "operation",
  playCost: 4,
  
  //Gain [click][click].
  Resolve: function(params) {
    GainClicks(corp, 2);
  },
  
  //**AI code
  AIFastAdvance: true, //is a card for fast advancing
};

//Card 31: Top-Down Solutions
//Haas-Bioroid Operation
//Cost: 2
//Draw 2 cards. Install up to 2 cards from HQ (one at a time).
cardSet[35044] = {
  title: "Top-Down Solutions",
  imageFile: "35044.png",
  player: corp,
  faction: "Haas-Bioroid",
  influence: 2,
  cardType: "operation",
  playCost: 2,
  
  //Draw 2 cards. Install up to 2 cards from HQ (one at a time).
  Resolve: function(params) {
    var cardRef = this;
    Draw(corp, 2, function() {
      //Now install up to 2 cards (one at a time)
      topDownSolutionsInstall(cardRef, 0);
    }, this);
  },
};

//Helper function for Top-Down Solutions - chained installation
function topDownSolutionsInstall(cardRef, installCount) {
  var installChoices = ChoicesHandInstall(corp); //respect install costs
  
  //Build choice list
  var choices = [];
  for (var i = 0; i < installChoices.length; i++) {
    choices.push(installChoices[i]);
  }
  
  var skipLabel = installCount < 1 ? "Skip remaining installs" : "Done installing";
  choices.push({ skip: true, label: skipLabel, button: skipLabel });
  
  DecisionPhase(
    corp,
    choices,
    function(params) {
      if (!params.skip) {
        Install(params.card, params.server);
        installCount++;
        //Continue to next install if allowed
        if (installCount < 2) {
          topDownSolutionsInstall(cardRef, installCount);
        }
        //Otherwise done
      }
      //Skipped - done
    },
    "Top-Down Solutions",
    "Install card from HQ (" + installCount + "/2 installed)",
    cardRef,
    "install"
  );
}

//Card 32: Poétrï Luxury Brands: All the Rage
//Haas-Bioroid Identity - Division
//Deck: 45, Influence: 15
//Whenever you score an agenda, look at the top 3 cards of R&D. You may install 1 non-agenda card from among them.
//Whenever an agenda is stolen, you may install 1 non-agenda card from HQ.
cardSet[35036] = {
  title: "Poétrï Luxury Brands: All the Rage",
  imageFile: "35036.png",
  player: corp,
  faction: "Haas-Bioroid",
  cardType: "identity",
  subTypes: ["Division"],
  deckSize: 45,
  influenceLimit: 15,
  
  //Whenever you score an agenda, look at the top 3 cards of R&D. You may install 1 non-agenda card from among them.
  responseOnScored: {
    Enumerate: function() {
      //Trigger if there are cards in R&D to look at
      if (corp.RnD.cards.length > 0) return [{}];
      return [];
    },
    Resolve: function(params) {
      //Look at top 3 cards of R&D
      var numToLook = Math.min(3, corp.RnD.cards.length);
      var allTopCards = [];
      var installableCards = [];
      
      for (var i = 0; i < numToLook; i++) {
        //Top card is at length-1, so top 3 are length-1, length-2, length-3
        var card = corp.RnD.cards[corp.RnD.cards.length - 1 - i];
        allTopCards.push(card);
        //Only ice, assets, upgrades are installable (not agendas or operations)
        if (!CheckCardType(card, ["agenda", "operation"])) {
          installableCards.push(card);
        }
      }
      
      //Make all cards face up so they display
      for (var i = 0; i < allTopCards.length; i++) {
        allTopCards[i].faceUp = true;
      }
      
      //Build choices from installable cards only (these will glow)
      var choices = [];
      for (var i = 0; i < installableCards.length; i++) {
        var card = installableCards[i];
        choices.push({ card: card, label: card.title });
      }
      choices.push({ skip: true, label: "Decline", button: "Decline" });
      
      var cardRef = this;
      DecisionPhase(
        corp,
        choices,
        function(choiceParams) {
          //Restore faceUp status for cards still in R&D
          for (var i = 0; i < allTopCards.length; i++) {
            if (allTopCards[i].cardLocation === corp.RnD.cards) {
              allTopCards[i].faceUp = false;
            }
          }
          viewingGrid = null;
          
          if (!choiceParams.skip) {
            //Install the chosen card with server selection
            poetriInstallCard(choiceParams.card);
          }
        },
        "Poétrï Luxury Brands",
        "Install 1 non-agenda card from top of R&D?",
        cardRef
      );
      
      //After DecisionPhase sets up, add non-installable cards to viewingGrid
      //They will appear but not glow (since they're not in validOptions)
      if (!viewingGrid) viewingGrid = [];
      for (var i = 0; i < allTopCards.length; i++) {
        if (!viewingGrid.includes(allTopCards[i])) {
          viewingGrid.push(allTopCards[i]);
        }
      }
    },
    text: "Look at top 3 cards of R&D, may install 1 non-agenda",
  },
  
  //Whenever an agenda is stolen, you may install 1 non-agenda card from HQ.
  responseOnStolen: {
    Enumerate: function() {
      //Check for installable non-agenda cards in HQ (not operations)
      for (var i = 0; i < corp.HQ.cards.length; i++) {
        if (!CheckCardType(corp.HQ.cards[i], ["agenda", "operation"])) {
          return [{}];
        }
      }
      return [];
    },
    Resolve: function(params) {
      //Build choices from installable non-agenda cards in HQ
      var choices = [];
      for (var i = 0; i < corp.HQ.cards.length; i++) {
        var card = corp.HQ.cards[i];
        if (!CheckCardType(card, ["agenda", "operation"])) {
          choices.push({ card: card, label: card.title });
        }
      }
      choices.push({ skip: true, label: "Decline", button: "Decline" });
      
      DecisionPhase(
        corp,
        choices,
        function(choiceParams) {
          if (!choiceParams.skip) {
            //Install the chosen card with server selection
            poetriInstallCard(choiceParams.card);
          }
        },
        "Poétrï Luxury Brands",
        "Install 1 non-agenda card from HQ?",
        this
      );
    },
    text: "Install 1 non-agenda card from HQ",
  },
};

//Helper function for Poétrï - install card with server selection
function poetriInstallCard(cardToInstall) {
  //Build server choices based on card type
  var serverChoices = [];
  
  if (cardToInstall.cardType === "ice" || cardToInstall.cardType === "upgrade") {
    //Ice and upgrades can go on any server
    serverChoices.push({ server: corp.HQ, label: "HQ" });
    serverChoices.push({ server: corp.RnD, label: "R&D" });
    serverChoices.push({ server: corp.archives, label: "Archives" });
    for (var j = 0; j < corp.remoteServers.length; j++) {
      serverChoices.push({
        server: corp.remoteServers[j],
        label: corp.remoteServers[j].serverName
      });
    }
    serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  } else {
    //Assets go in remotes only
    for (var j = 0; j < corp.remoteServers.length; j++) {
      serverChoices.push({
        server: corp.remoteServers[j],
        label: corp.remoteServers[j].serverName
      });
    }
    serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  }
  
  DecisionPhase(
    corp,
    serverChoices,
    function(serverParams) {
      Install(cardToInstall, serverParams.server);
    },
    "Poétrï Luxury Brands",
    "Choose where to install " + cardToInstall.title,
    null,
    "server"
  );
}

//Card 33: Mycoweb
//Jinteki Ice - Code Gate
//Rez: 8, Strength: 5
//↳ You may install 1 piece of ice from Archives, ignoring all costs.
//↳ You may rez 1 installed piece of ice, paying 2 credits less.
//↳ Resolve 1 subroutine on a rezzed sentry.
//↳ Resolve 1 subroutine on another rezzed code gate.
cardSet[35053] = {
  title: "Mycoweb",
  imageFile: "35053.png",
  player: corp,
  faction: "Jinteki",
  influence: 2,
  cardType: "ice",
  subTypes: ["Code Gate"],
  rezCost: 8,
  strength: 5,
  
  subroutines: [
    {
      //↳ You may install 1 piece of ice from Archives, ignoring all costs.
      text: "You may install 1 piece of ice from Archives, ignoring all costs.",
      Resolve: function(params) {
        //Check for ice in Archives
        var iceChoices = [];
        for (var i = 0; i < corp.archives.cards.length; i++) {
          var card = corp.archives.cards[i];
          if (CheckCardType(card, ["ice"])) {
            iceChoices.push({ card: card, label: card.title });
          }
        }
        if (iceChoices.length === 0) return; //no ice to install
        iceChoices.push({ skip: true, label: "Decline", button: "Decline" });
        
        DecisionPhase(
          corp,
          iceChoices,
          function(iceParams) {
            if (iceParams.skip) return;
            mycowebInstallIce(iceParams.card);
          },
          "Mycoweb",
          "Install ice from Archives?",
          this
        );
      },
      visual: { y: 58, h: 31 },
    },
    {
      //↳ You may rez 1 installed piece of ice, paying 2 credits less.
      text: "You may rez 1 installed piece of ice, paying 2[c] less.",
      Resolve: function(params) {
        var iceChoices = [];
        var installedCards = InstalledCards(corp);
        for (var i = 0; i < installedCards.length; i++) {
          var card = installedCards[i];
          if (CheckCardType(card, ["ice"]) && !card.rezzed) {
            //Check if Corp can afford with 2 credit discount
            var rezCost = RezCost(card) - 2;
            if (rezCost < 0) rezCost = 0;
            if (CheckCredits(corp, rezCost, "rezzing", card)) {
              iceChoices.push({ card: card, rezCost: rezCost, label: card.title + " (rez cost: " + rezCost + "[c])" });
            }
          }
        }
        if (iceChoices.length === 0) return; //no ice to rez
        iceChoices.push({ skip: true, label: "Decline", button: "Decline" });
        
        DecisionPhase(
          corp,
          iceChoices,
          function(iceParams) {
            if (iceParams.skip) return;
            //Rez with 2 credit discount
            SpendCredits(corp, iceParams.rezCost, "rezzing", iceParams.card, function() {
              Rez(iceParams.card);
            });
          },
          "Mycoweb",
          "Rez ice (paying 2[c] less)?",
          this
        );
      },
      visual: { y: 95, h: 31 },
    },
    {
      //↳ Resolve 1 subroutine on a rezzed sentry.
      text: "Resolve 1 subroutine on a rezzed sentry.",
      Resolve: function(params) {
        var cardRef = this;
        mycowebResolveSubroutine(cardRef, "Sentry", false); //false = don't exclude self
      },
      visual: { y: 128, h: 31 },
    },
    {
      //↳ Resolve 1 subroutine on another rezzed code gate.
      text: "Resolve 1 subroutine on another rezzed code gate.",
      Resolve: function(params) {
        var cardRef = this;
        mycowebResolveSubroutine(cardRef, "Code Gate", true); //true = exclude self
      },
      visual: { y: 166, h: 31 },
    },
  ],
  
  //AI code
  AIRezReasons: function() {
    return { facecheck: true };
  },
};

//Helper function for Mycoweb - install ice from Archives with server selection
function mycowebInstallIce(iceToInstall) {
  //Build server choices
  var serverChoices = [];
  serverChoices.push({ server: corp.HQ, label: "HQ" });
  serverChoices.push({ server: corp.RnD, label: "R&D" });
  serverChoices.push({ server: corp.archives, label: "Archives" });
  for (var j = 0; j < corp.remoteServers.length; j++) {
    serverChoices.push({
      server: corp.remoteServers[j],
      label: corp.remoteServers[j].serverName
    });
  }
  serverChoices.push({ server: null, label: "New remote server", button: "New remote" });
  
  DecisionPhase(
    corp,
    serverChoices,
    function(serverParams) {
      Install(iceToInstall, serverParams.server, true); //ignoreAllCosts = true
    },
    "Mycoweb",
    "Choose server for " + iceToInstall.title,
    null,
    "server"
  );
}

//Helper function for Mycoweb - resolve subroutine on rezzed ice of given subtype
function mycowebResolveSubroutine(cardRef, subtype, excludeSelf) {
  //Step 1: Choose a rezzed ice of the given subtype
  var iceChoices = ChoicesInstalledCards(corp, function(card) {
    if (excludeSelf && card === cardRef) return false;
    if (card.rezzed && CheckSubType(card, subtype) && CheckCardType(card, ["ice"])) {
      if (card.subroutines && card.subroutines.length > 0) return true;
    }
    return false;
  });
  
  if (iceChoices.length === 0) return;
  
  DecisionPhase(
    corp,
    iceChoices,
    function(iceParams) {
      //Step 2: Choose a subroutine on that ice
      var subChoices = [];
      for (var i = 0; i < iceParams.card.subroutines.length; i++) {
        var sub = iceParams.card.subroutines[i];
        subChoices.push({
          card: iceParams.card,
          subroutine: sub,
          label: sub.text
        });
      }
      
      DecisionPhase(
        corp,
        subChoices,
        function(subParams) {
          //Step 3: Get choices for that subroutine and resolve it
          var srChoices = ChoicesSubroutine(subParams.card, subParams.subroutine);
          
          DecisionPhase(
            corp,
            srChoices,
            function(srParams) {
              //Fire the chosen subroutine
              Trigger(srParams.card, srParams.ability, srParams.choice, "Firing");
            },
            subParams.card.title,
            "Choose option for: " + subParams.subroutine.text,
            subParams.card
          );
        },
        iceParams.card.title,
        "Choose subroutine to resolve",
        iceParams.card
      );
    },
    "Mycoweb",
    "Choose rezzed " + subtype.toLowerCase() + " ice",
    cardRef
  );
}

//Card 34: Aggressive Trendsetting
//Haas-Bioroid Agenda: Initiative
//Advancement: 3, Points: 1
//The first time the Runner trashes an installed Corp card during each of their turns,
//they may spend [click]. If they do not, you get +1 allotted [click] for your next turn.
cardSet[35037] = {
  title: "Aggressive Trendsetting",
  imageFile: "35037.png",
  player: corp,
  faction: "Haas-Bioroid",
  cardType: "agenda",
  subTypes: ["Initiative"],
  agendaPoints: 1,
  advancementRequirement: 3,
  
  triggeredThisTurn: false,
  pendingTrigger: false, //set by automaticOnWouldTrash, used by responseOnTrash
  
  //Reset the trigger flag at the start of each Runner turn
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.triggeredThisTurn = false;
    },
    automatic: true,
  },
  
  //Detect when an installed Corp card is about to be trashed during Runner's turn
  //This fires BEFORE the card moves to Archives, so we can check cardLocation
  automaticOnWouldTrash: {
    Resolve: function(cards) {
      //Only trigger during Runner's turn
      if (playerTurn !== runner) return;
      //Only trigger once per turn
      if (this.triggeredThisTurn) return;
      
      //Check if any of the trashed cards was an installed Corp card
      for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
        if (card.player !== corp) continue;
        
        //Check if it was installed (not in HQ, R&D, or Archives piles)
        var loc = card.cardLocation;
        if (loc === corp.HQ.cards) continue;
        if (loc === corp.RnD.cards) continue;
        if (loc === corp.archives.cards) continue;
        
        //It was an installed Corp card - set flag to trigger in responseOnTrash
        this.pendingTrigger = true;
        return;
      }
    },
  },
  
  //Present the Runner's choice after the trash completes
  responseOnTrash: {
    Enumerate: function(cards) {
      if (!this.pendingTrigger) return [];
      return [{}];
    },
    Resolve: function(params) {
      this.pendingTrigger = false;
      this.triggeredThisTurn = true;
      
      var cardRef = this;
      var choices = [];
      
      //Runner may spend click if they have one
      if (CheckClicks(runner, 1)) {
        choices.push({ spend: true, label: "Spend [click]", button: "Spend [click]" });
      }
      choices.push({ spend: false, label: "Decline (Corp gets +1 click next turn)", button: "Decline" });
      
      DecisionPhase(
        runner,
        choices,
        function(choiceParams) {
          if (choiceParams.spend) {
            SpendClicks(runner, 1);
            Log("Runner spends [click] to prevent Aggressive Trendsetting");
          } else {
            AddTempBonusClicks(corp, 1);
          }
        },
        "Aggressive Trendsetting",
        "Spend [click] or Corp gets +1 allotted click next turn?",
        cardRef
      );
    },
    text: "Runner may spend [click] or Corp gets +1 allotted click",
  },
};

//Card 35: Transfer of Wealth
//Criminal Event: Run
//Cost: 0
//Run HQ. If successful, take 1 tag and the Corp loses 3 credits.
//Gain 2 credits for each credit lost this way.
cardSet[35017] = {
  title: "Transfer of Wealth",
  imageFile: "35017.png",
  player: runner,
  faction: "Criminal",
  influence: 4,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 0,
  
  runningWithThis: false,
  
  Enumerate: function() {
    return [{}]; //Always run HQ
  },
  Resolve: function(params) {
    this.runningWithThis = true;
    MakeRun(corp.HQ);
  },
  
  responseOnRunSuccessful: {
    Resolve: function() {
      if (!this.runningWithThis) return;
      //Take 1 tag
      AddTags(1);
      //Corp loses 3 credits (or as many as they have)
      var creditsToLose = Math.min(3, Credits(corp));
      var creditsLost = LoseCredits(corp, creditsToLose);
      //Gain 2 credits for each credit lost
      var creditsToGain = creditsLost * 2;
      if (creditsToGain > 0) {
        GainCredits(runner, creditsToGain);
      }
    },
    automatic: true,
  },
  
  responseOnRunEnds: {
    Resolve: function() {
      this.runningWithThis = false;
    },
    automatic: true,
  },
  
  //AI code
  AIRunEventExtraPotential: function(server, potential) {
    if (server !== corp.HQ) return 0;
    //Require successful run
    if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
    //Value depends on Corp's credits - at 3+ credits, we get 6 credits for 1 tag
    var corpCreds = Math.min(3, Credits(corp));
    var netGain = (corpCreds * 2) - 0; //0 play cost
    if (corpCreds >= 2) return 0.3; //Worth it if Corp has money
    return 0;
  },
};

//Card 36: Maglectric Rapid (748 Mod)
//Criminal Hardware: Weapon
//Cost: 1
//Whenever you make a successful run on HQ, you may trash this hardware
//to derez 1 installed Corp card.
cardSet[35019] = {
  title: "Maglectric Rapid (748 Mod)",
  imageFile: "35019.png",
  player: runner,
  faction: "Criminal",
  influence: 2,
  cardType: "hardware",
  subTypes: ["Weapon"],
  installCost: 1,
  unique: true,
  
  responseOnRunSuccessful: {
    Enumerate: function() {
      //Only trigger on HQ runs
      if (attackedServer !== corp.HQ) return [];
      //Check for rezzed Corp cards to derez
      var rezzedCards = ChoicesInstalledCards(corp, function(card) {
        return card.rezzed;
      });
      if (rezzedCards.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      var cardRef = this;
      //Get rezzed Corp cards
      var rezzedCards = ChoicesInstalledCards(corp, function(card) {
        return card.rezzed;
      });
      rezzedCards.push({ skip: true, label: "Decline", button: "Decline" });
      
      DecisionPhase(
        runner,
        rezzedCards,
        function(choiceParams) {
          if (choiceParams.skip) return;
          //Trash this hardware
          Trash(cardRef, false, function() {
            //Derez the chosen card
            Derez(choiceParams.card);
          });
        },
        "Maglectric Rapid (748 Mod)",
        "Trash to derez a Corp card?",
        cardRef
      );
    },
    text: "Trash to derez 1 installed Corp card",
  },
  
  //AI code
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Worth keeping if HQ runs are possible
    if (runner.AI._getCachedCost(corp.HQ) !== Infinity) return true;
    return false;
  },
};

//Card 37: Sang Kancil
//Criminal Program: Icebreaker - Decoder
//Cost: 4, MU: 1, Strength: 1
//Interface -> 1c: Break 1 code gate subroutine.
//3c: +2 strength. If a run event is active, this costs 2c less.
cardSet[35020] = {
  title: "Sang Kancil",
  imageFile: "35020.png",
  player: runner,
  faction: "Criminal",
  influence: 2,
  cardType: "program",
  subTypes: ["Icebreaker", "Decoder"],
  memoryCost: 1,
  installCost: 3,
  strength: 2,
  
  strengthBoost: 0,
  modifyStrength: {
    Resolve: function(card) {
      if (card === this) return this.strengthBoost;
      return 0;
    },
  },
  
  //Helper to check if a run event is active
  runEventIsActive: function() {
    for (var i = 0; i < runner.resolvingCards.length; i++) {
      var card = runner.resolvingCards[i];
      if (CheckCardType(card, ["event"]) && CheckSubType(card, "Run")) {
        return true;
      }
    }
    return false;
  },
  
  abilities: [
    {
      text: "Break 1 code gate subroutine",
      Enumerate: function() {
        if (!CheckEncounter()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Code Gate")) return [];
        if (!CheckCredits(runner, 1, "using", this)) return [];
        if (!CheckStrength(this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function(params) {
        SpendCredits(runner, 1, "using", this, function() {
          Break(params.subroutine);
        }, this);
      },
    },
    {
      text: "+2 strength",
      Enumerate: function() {
        if (!CheckEncounter()) return [];
        if (CheckStrength(this)) return [];
        if (!CheckUnbrokenSubroutines()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Code Gate")) return [];
        //Cost is 3c, or 1c if run event is active
        var cost = this.runEventIsActive() ? 1 : 3;
        if (!CheckCredits(runner, cost, "using", this)) return [];
        return [{}];
      },
      Resolve: function(params) {
        var cost = this.runEventIsActive() ? 1 : 3;
        SpendCredits(runner, cost, "using", this, function() {
          BoostStrength(this, 2);
        }, this);
      },
    },
  ],
  
  responseOnEncounterEnds: {
    Resolve: function() {
      this.strengthBoost = 0;
    },
    automatic: true,
  },
  
  //AI code
  AIImplementIcebreaker: function() {
    //Cost to break one subroutine
    return { breakCost: 1, boostCost: 3, boostAmount: 2 };
  },
};

//Card 38: Fransofia Ward
//Criminal Resource: Connection
//Cost: 3
//The rez cost of each piece of ice is increased by 1c.
//Whenever you encounter a piece of ice, if the Corp has 15c or more,
//you may trash this resource to bypass that ice.
cardSet[35021] = {
  title: "Fransofia Ward",
  imageFile: "35021.png",
  player: runner,
  faction: "Criminal",
  influence: 3,
  cardType: "resource",
  subTypes: ["Connection"],
  installCost: 3,
  unique: true,
  
  //The rez cost of each piece of ice is increased by 1c
  modifyRezCost: {
    Resolve: function (card) {
      if (CheckCardType(card, ["ice"])) return 1;
      return 0;
    },
  },
  
  //Whenever you encounter a piece of ice, if the Corp has 15c or more,
  //you may trash this resource to bypass that ice
  responseOnEncounter: {
    Enumerate: function (card) {
      //Only trigger if Corp has 15+ credits
      if (Credits(corp) < 15) return [];
      
      var choices = [
        { id: 0, label: "Trash to bypass", button: "Trash to bypass", alt: "fransofia_bypass" },
        { id: 1, label: "Decline", button: "Decline", alt: "continue" }
      ];
      
      //**AI code
      if (runner.AI) {
        //Evaluate if bypass is worth trashing this resource
        var iceCard = attackedServer.ice[approachIce];
        var shouldBypass = this.AIShouldBypass(iceCard);
        
        if (shouldBypass) {
          runner.AI.preferred = { title: this.title, option: choices[0] };
        } else {
          runner.AI.preferred = { title: this.title, option: choices[1] };
        }
      }
      
      return choices;
    },
    Resolve: function (params) {
      if (params.id == 0) {
        var cardRef = this;
        Trash(cardRef, false, function() {
          Bypass();
        }, cardRef);
      }
    },
  },
  
  //AI helper: Should we bypass this ice?
  AIShouldBypass: function(iceCard) {
    //Don't bypass if we have other good options
    var installedCards = InstalledCards(runner);
    
    //Check if we have a strong matching breaker
    var hasMatchingBreaker = false;
    for (var i = 0; i < installedCards.length; i++) {
      var card = installedCards[i];
      if (CheckSubType(card, "Icebreaker")) {
        if (typeof card.AIMatchingBreakerInstalled === "function") {
          if (card.AIMatchingBreakerInstalled(iceCard)) {
            hasMatchingBreaker = true;
            break;
          }
        } else if (BreakerMatchesIce(card, iceCard)) {
          hasMatchingBreaker = true;
          break;
        }
      }
    }
    
    //If we have a good breaker and plenty of credits, save Fransofia for later
    if (hasMatchingBreaker && Credits(runner) > 10) return false;
    
    //Calculate the value of this run
    var runPotential = 0;
    if (runner.AI) {
      runPotential = runner.AI._getCachedPotential(attackedServer);
    }
    
    //High-value runs (>2.0): consider bypass if ice is expensive
    if (runPotential > 2.0) {
      //Expensive ice (rez cost 4+): worth bypassing
      if (RezCost(iceCard) >= 4) return true;
      
      //Unknown ice when low on credits: bypass to be safe
      if (Credits(runner) < 5 && !iceCard.rezzed) return true;
    }
    
    //Critical situations: low clicks remaining
    if (!CheckClicks(runner, 2) && runPotential > 1.5) {
      //Bypass to ensure success
      return true;
    }
    
    //Very expensive ice (rez cost 6+): almost always bypass
    if (RezCost(iceCard) >= 6 && runPotential > 1.0) return true;
    
    //Default: don't waste the resource
    return false;
  },
  
  //AI: Include bypass option in run calculator
  AIImplementBreaker: function(rc, result, point, server, cardStrength, iceAI, iceStrength, clicksLeft, creditsLeft) {
    //Don't reuse if already trashed
    if (!rc.PersistentsUse(point, this)) {
      //Check if Corp has 15+ credits in the simulation
      var corpCreditsInSim = creditsLeft.corp !== undefined ? creditsLeft.corp : Credits(corp);
      
      if (corpCreditsInSim >= 15) {
        //Evaluate if bypass is worthwhile
        var runPotential = runner.AI._getCachedPotential(server);
        var iceRezCost = RezCost(iceAI.ice);
        
        //Only offer bypass for valuable runs or expensive ice
        if (runPotential > 1.5 || iceRezCost >= 4) {
          var pointCopy = rc.CopyPoint(point);
          pointCopy.persistents = pointCopy.persistents.concat([
            {use: this, target: iceAI.ice, iceIdx: point.iceIdx, action: "bypass", alt: "fransofia_bypass"}
          ]);
          //Mark as serious consideration (higher priority than normal breaks)
          pointCopy.effects = pointCopy.effects.concat([["misc_serious", "misc_serious"]]);
          result = result.concat([pointCopy]);
        }
      }
    }
    return result;
  },
  
  //AI: Worth keeping?
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    //Keep if Corp is rich (15+ credits)
    if (Credits(corp) >= 15) return true;
    
    //Keep if Corp is approaching 15 credits
    if (Credits(corp) >= 12) return true;
    
    //Keep if Corp has expensive ice that we struggle with
    var allIce = [];
    for (var i = 0; i < corp.remoteServers.length; i++) {
      allIce = allIce.concat(corp.remoteServers[i].ice);
    }
    allIce = allIce.concat(corp.HQ.ice);
    allIce = allIce.concat(corp.RnD.ice);
    allIce = allIce.concat(corp.archives.ice);
    
    for (var i = 0; i < allIce.length; i++) {
      if (RezCost(allIce[i]) >= 5) return true;
    }
    
    //Otherwise, not critical
    return false;
  },
  
  //AI: Should we install this?
  AIPreferredInstallChoice: function(choices) {
    //Don't install on last click
    if (runner.clickTracker < 2) return -1;
    
    //Install if Corp is rich or approaching rich
    if (Credits(corp) >= 12) return 0;
    
    //Install if we see expensive ice
    var allIce = [];
    for (var i = 0; i < corp.remoteServers.length; i++) {
      allIce = allIce.concat(corp.remoteServers[i].ice);
    }
    allIce = allIce.concat(corp.HQ.ice);
    allIce = allIce.concat(corp.RnD.ice);
    allIce = allIce.concat(corp.archives.ice);
    
    var expensiveIceCount = 0;
    for (var i = 0; i < allIce.length; i++) {
      if (RezCost(allIce[i]) >= 4) expensiveIceCount++;
    }
    
    if (expensiveIceCount >= 2) return 0;
    
    //Otherwise, maybe later
    return -1;
  },
};

//Card 39: Principia
//Shaper Program: Icebreaker - Fracter
//Cost: 6, MU: 1, Strength: 2
//This program costs 1c less to install for each other installed icebreaker.
//Interface -> 1c: Break 1 barrier subroutine.
//2c: +2 strength.
cardSet[35032] = {
  title: "Principia",
  imageFile: "35032.png",
  player: runner,
  faction: "Shaper",
  influence: 1,
  cardType: "program",
  subTypes: ["Icebreaker", "Fracter"],
  memoryCost: 1,
  installCost: 4,
  strength: 2,
  
  modifyInstallCost: {
    Resolve: function(card) {
      if (card !== this) return 0;
      if (CheckInstalled(card)) return 0; //Already installed
      //Count other installed icebreakers
      var installedCards = InstalledCards(runner);
      var icebreakerCount = 0;
      for (var i = 0; i < installedCards.length; i++) {
        if (CheckSubType(installedCards[i], "Icebreaker")) {
          icebreakerCount++;
        }
      }
      return -icebreakerCount; //1c less per icebreaker
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  strengthBoost: 0,
  modifyStrength: {
    Resolve: function(card) {
      if (card === this) return this.strengthBoost;
      return 0;
    },
  },
  
  abilities: [
    {
      text: "Break 1 barrier subroutine",
      Enumerate: function() {
        if (!CheckEncounter()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Barrier")) return [];
        if (!CheckCredits(runner, 1, "using", this)) return [];
        if (!CheckStrength(this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function(params) {
        SpendCredits(runner, 1, "using", this, function() {
          Break(params.subroutine);
        }, this);
      },
    },
    {
      text: "+2 strength",
      Enumerate: function() {
        if (!CheckEncounter()) return [];
        if (CheckStrength(this)) return [];
        if (!CheckUnbrokenSubroutines()) return [];
        if (!CheckSubType(attackedServer.ice[approachIce], "Barrier")) return [];
        if (!CheckCredits(runner, 2, "using", this)) return [];
        return [{}];
      },
      Resolve: function(params) {
        SpendCredits(runner, 2, "using", this, function() {
          BoostStrength(this, 2);
        }, this);
      },
    },
  ],
  
  responseOnEncounterEnds: {
    Resolve: function() {
      this.strengthBoost = 0;
    },
    automatic: true,
  },
  
  //AI code
  AIImplementIcebreaker: function() {
    return { breakCost: 1, boostCost: 2, boostAmount: 2 };
  },
};

//Card 40: Devadatta Drone
//Shaper Program
//Cost: 1, MU: 1
//When you install this program, place 2 power counters on it.
//Whenever you breach R&D, you may remove 1 hosted power counter
//to access 1 additional card.
cardSet[35031] = {
  title: "Devadatta Drone",
  imageFile: "35031.png",
  player: runner,
  faction: "Shaper",
  influence: 1,
  cardType: "program",
  memoryCost: 1,
  installCost: 1,
  power: 0,
  
  //When installed, place 2 power counters
  automaticOnInstall: {
    Resolve: function(card) {
      if (card == this) AddCounters(this, "power", 2);
    },
  },
  
  //Track whether we're using the ability this run
  usingThisRun: false,
  
  //Reset at run end
  responseOnRunEnds: {
    Resolve: function() {
      this.usingThisRun = false;
    },
    automatic: true,
  },
  
  //When breaching R&D, offer to use a counter
  responseOnBreach: {
    Enumerate: function(server) {
      if (server !== corp.RnD) return [];
      if (Counters(this, "power") < 1) return [];
      return [{}];
    },
    Resolve: function(params) {
      var cardRef = this;
      var choices = [
        { use: true, label: "Remove 1 power counter to access 1 additional card", button: "Use counter" },
        { use: false, label: "Decline", button: "Decline" }
      ];
      
      DecisionPhase(
        runner,
        choices,
        function(choiceParams) {
          if (choiceParams.use) {
            RemoveCounters(cardRef, "power", 1);
            cardRef.usingThisRun = true;
          }
        },
        "Devadatta Drone",
        "Remove counter for +1 R&D access?",
        cardRef
      );
    },
    text: "Remove 1 power counter to access 1 additional card",
  },
  
  //Add extra access if we used the ability
  modifyBreachAccess: {
    Resolve: function() {
      if (attackedServer !== corp.RnD) return 0;
      if (this.usingThisRun) return 1;
      return 0;
    },
  },
  
  //AI code
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    if (runner.AI._getCachedCost(corp.RnD) !== Infinity) return true;
    return false;
  },
};

//Card 41: "Knickknack" O'Brian
//Shaper Resource: Connection
//Cost: 2
//The first time each turn a run begins, you may trash 1 of your other installed cards.
//If you do, gain credits equal to its printed install cost and draw 1 card.
cardSet[35033] = {
  title: "\"Knickknack\" O'Brian",
  imageFile: "35033.png",
  player: runner,
  faction: "Shaper",
  influence: 3,
  cardType: "resource",
  subTypes: ["Connection"],
  installCost: 2,
  unique: true,
  
  triggeredThisTurn: false,
  
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.triggeredThisTurn = false;
    },
    automatic: true,
  },
  
  responseOnCorpTurnBegins: {
    Resolve: function() {
      this.triggeredThisTurn = false;
    },
    automatic: true,
  },
  
  //When a run begins
  responseOnRunBegins: {
    Enumerate: function(server) {
      if (this.triggeredThisTurn) return [];
      //Check for other installed cards to trash
      var otherCards = ChoicesInstalledCards(runner, function(card) {
        return card !== this;
      }.bind(this));
      if (otherCards.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      this.triggeredThisTurn = true;
      var cardRef = this;
      
      //Get other installed cards
      var trashChoices = ChoicesInstalledCards(runner, function(card) {
        return card !== cardRef;
      });
      trashChoices.push({ card: null, label: "Decline", button: "Decline" });
      
      DecisionPhase(
        runner,
        trashChoices,
        function(choiceParams) {
          if (choiceParams.card === null) return;
          var printedCost = choiceParams.card.installCost || 0;
          //Trash the chosen card
          Trash(choiceParams.card, false, function() {
            //Gain credits equal to printed install cost
            if (printedCost > 0) {
              GainCredits(runner, printedCost);
            }
            //Draw 1 card
            Draw(runner, 1);
          });
        },
        "\"Knickknack\" O'Brian",
        "Trash an installed card for credits and a card?",
        cardRef,
        "trash"
      );
    },
    text: "Trash another installed card to gain credits and draw",
  },
  
  //AI code
  AIWorthKeeping: function(installedRunnerCards, spareMU) {
    return true; //Generally useful economy/card draw
  },
};

//Card 42: Illumination
//Shaper Event: Run
//Cost: 2
//Run R&D. If successful, install up to 3 cards from your grip (one at a time),
//paying 1c less for each.
cardSet[35025] = {
  title: "Illumination",
  imageFile: "35025.png",
  player: runner,
  faction: "Shaper",
  influence: 3,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 0,
  
  runningWithThis: false,
  installsRemaining: 0,
  
  Enumerate: function() {
    return [{}]; //Always run R&D
  },
  
  Resolve: function(params) {
    this.runningWithThis = true;
    this.installsRemaining = 0;
    MakeRun(corp.RnD);
  },
  
  responseOnRunSuccessful: {
    Enumerate: function() {
      if (!this.runningWithThis) return [];
      return [{}];
    },
    Resolve: function() {
      this.installsRemaining = 3;
      this._illuminationInstallLoop();
    },
    automatic: true,
  },
  
  responseOnRunEnds: {
    Resolve: function() {
      this.runningWithThis = false;
      this.installsRemaining = 0;
      this.modifyInstallCost.availableWhenInactive = false;
    },
    automatic: true,
  },
  
  //Install loop as a method on the card
  _illuminationInstallLoop: function() {
    if (this.installsRemaining <= 0) {
      //Cleanup
      this.modifyInstallCost.availableWhenInactive = false;
      return;
    }
    
    var cardRef = this;
    
    //Enable discount for affordability check and installs
    this.modifyInstallCost.availableWhenInactive = true;
    var choices = ChoicesHandInstall(runner);
    
    if (choices.length === 0) {
      //Cleanup
      this.modifyInstallCost.availableWhenInactive = false;
      return;
    }
    
    choices.push({ skip: true, label: "Done installing", button: "Done" });
    
    DecisionPhase(
      runner,
      choices,
      function(params) {
        if (params.skip) {
          cardRef.installsRemaining = 0;
          cardRef.modifyInstallCost.availableWhenInactive = false;
          return;
        }
        
        //Install the card (discount applied via modifyInstallCost checking installingCards)
        Install(params.card, params.host);
        
        //Continue loop
        cardRef.installsRemaining--;
        cardRef._illuminationInstallLoop();
      },
      "Illumination",
      "Install a card (paying 1[c] less)? (" + this.installsRemaining + " remaining)",
      this,
      "install"
    );
  },
  
  modifyInstallCost: {
    Resolve: function(card) {
      //Discount any grip card or card currently being installed
      if (runner.grip.includes(card) || runner.installingCards.includes(card)) {
        if (CheckCardType(card, ["program", "hardware", "resource"])) {
          return -1;
        }
      }
      return 0;
    },
    automatic: true,
    availableWhenInactive: false, //toggled dynamically
  },
  
  //AI code
  AIRunEventExtraPotential: function(server, potential) {
    if (server !== corp.RnD) return 0;
    if (runner.AI._rootKnownToContainCopyOfCard(server, "Crisium Grid")) return 0;
    //Value based on installable cards in grip
    var installableCount = 0;
    for (var i = 0; i < runner.grip.length; i++) {
      if (CheckCardType(runner.grip[i], ["program", "hardware", "resource"])) {
        installableCount++;
      }
    }
    if (installableCount > 0) return 0.2 * Math.min(installableCount, 3);
    return 0;
  },
};

//Magdalene Keino-Chemutai: Cryptarchitect
//Shaper Identity: Cyborg
//Deck: 45, Influence: 15
//Whenever you discard cards to reach your maximum hand size, you may install 1 program 
//or piece of hardware from among those cards.
cardSet[35024] = {
  title: "Magdalene Keino-Chemutai: Cryptarchitect",
  imageFile: "35024.png",
  player: runner,
  faction: "Shaper",
  link: 0,
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 15,
  subTypes: ["Cyborg"],
  
  //Whenever you discard cards to reach your maximum hand size, you may install 1 program 
  //or piece of hardware from among those cards.
  responseOnDiscardToMaxHandSize: {
    Enumerate: function(discardedCards) {
      //Filter for programs and hardware that are still in the heap
      //(they should be, but just in case something moved them)
      var installableCards = [];
      for (var i = 0; i < discardedCards.length; i++) {
        var card = discardedCards[i];
        //Check if it's a program or hardware
        if (!CheckCardType(card, ["program", "hardware"])) continue;
        //Check if it's still in the heap
        if (!runner.heap.includes(card)) continue;
        //Check if we can afford to install it (MU for programs, credits, etc.)
        //We'll build choices for each installable card
        var installChoices = ChoicesCardInstall(card);
        if (installChoices.length > 0) {
          installableCards.push({
            card: card,
            label: "Install " + GetTitle(card, true) + " (" + InstallCost(card) + "[c])",
            installChoices: installChoices
          });
        }
      }
      
      if (installableCards.length === 0) return [];
      
      //Add decline option
      installableCards.push({ skip: true, label: "Decline", button: "Decline" });
      
      //**AI code
      if (runner.AI && installableCards.length > 1) {
        var bestChoice = this.AIChooseBestInstall(installableCards, discardedCards);
        if (bestChoice !== null) {
          runner.AI.preferred = { title: this.title, option: installableCards[bestChoice] };
        } else {
          //Decline
          runner.AI.preferred = { title: this.title, option: installableCards[installableCards.length - 1] };
        }
      }
      
      return installableCards;
    },
    Resolve: function(params) {
      if (params.skip) return;
      
      var cardRef = this;
      var cardToInstall = params.card;
      
      //Move the card from heap to grip temporarily for the install process
      //This is needed because Install expects cards to be in a valid location
      MoveCard(cardToInstall, runner.grip);
      
      //Bring the card sprite to the front so it's visible during install
      //This fixes z-index issues when installing from heap
      if (cardToInstall.renderer && cardToInstall.renderer.sprite && cardToInstall.renderer.sprite.parent) {
        var parent = cardToInstall.renderer.sprite.parent;
        parent.removeChild(cardToInstall.renderer.sprite);
        parent.addChild(cardToInstall.renderer.sprite);
      }
      
      //If there are multiple install choices (e.g., host options), let the player choose
      if (params.installChoices && params.installChoices.length > 1) {
        DecisionPhase(
          runner,
          params.installChoices,
          function(choiceParams) {
            Install(cardToInstall, choiceParams.host);
          },
          "Magdalene",
          "Choose install destination",
          cardRef,
          "install"
        );
      } else {
        //Single install option, just do it
        var host = null;
        if (params.installChoices && params.installChoices.length === 1) {
          host = params.installChoices[0].host;
        }
        Install(cardToInstall, host);
      }
    },
    text: "Install 1 program or hardware",
  },
  
  //AI helper: Choose best card to install from discards
  AIChooseBestInstall: function(installableCards, discardedCards) {
    if (installableCards.length <= 1) return null; //Only Decline option
    
    var bestIndex = null;
    var bestScore = -1;
    
    for (var i = 0; i < installableCards.length - 1; i++) { //-1 for Decline
      var card = installableCards[i].card;
      var cost = InstallCost(card);
      var score = 0;
      
      //Prioritize by card type and value
      if (CheckCardType(card, ["program"])) {
        //Programs are generally higher priority
        if (CheckSubType(card, "Icebreaker")) {
          //Icebreakers are very valuable
          score = 100;
          //Extra value if we don't have this type of breaker installed
          var breakerTypes = ["Fracter", "Decoder", "Killer"];
          for (var j = 0; j < breakerTypes.length; j++) {
            if (CheckSubType(card, breakerTypes[j])) {
              var hasType = false;
              var installedCards = InstalledCards(runner);
              for (var k = 0; k < installedCards.length; k++) {
                if (CheckSubType(installedCards[k], breakerTypes[j])) {
                  hasType = true;
                  break;
                }
              }
              if (!hasType) score += 50;
              break;
            }
          }
        } else {
          //Other programs
          score = 50;
        }
        
        //Check MU availability
        var memoryCost = card.memoryCost || 0;
        var availableMU = MemoryUnits() - InstalledMemoryCost();
        if (memoryCost > availableMU) {
          score = -10; //Can't install without trashing something
        }
      } else if (CheckCardType(card, ["hardware"])) {
        if (CheckSubType(card, "Console")) {
          //Console is valuable if we don't have one
          var hasConsole = false;
          var installedCards = InstalledCards(runner);
          for (var j = 0; j < installedCards.length; j++) {
            if (CheckSubType(installedCards[j], "Console")) {
              hasConsole = true;
              break;
            }
          }
          score = hasConsole ? 5 : 80; //Low value if we already have a console
        } else {
          score = 40;
        }
      }
      
      //Adjust by cost (higher cost cards are better value to get installed this way)
      score += cost * 2;
      
      //Check affordability
      if (!CheckCredits(runner, cost, "installing", card)) {
        score = -20; //Can't afford
      }
      
      if (score > bestScore) {
        bestScore = score;
        bestIndex = i;
      }
    }
    
    //Only return an index if the score is positive (worth installing)
    if (bestScore > 0) return bestIndex;
    return null;
  },
};

//Empiricist
//Jinteki Ice: Code Gate - AP
//Rez: 7, Strength: 5
//[sub] Draw 1 card. You may add 1 card from HQ to the top of R&D.
//[sub] Do 1 net damage. Give the Runner 1 tag.
//[sub] Do 2 net damage.
cardSet[35052] = {
  title: "Empiricist",
  imageFile: "35052.png",
  player: corp,
  faction: "Jinteki",
  influence: 3,
  cardType: "ice",
  subTypes: ["Code Gate", "AP"],
  rezCost: 7,
  strength: 5,
  subroutines: [
    {
      text: "Draw 1 card. You may add 1 card from HQ to the top of R&D.",
      Resolve: function() {
        var cardRef = this;
        Draw(corp, 1, function() {
          //After drawing, offer to add card from HQ to top of R&D
          if (corp.HQ.cards.length === 0) return;
          
          var choices = ChoicesHandCards(corp);
          
          //Add server to each option for drag-and-drop on R&D
          choices.forEach(function(item) {
            item.server = corp.RnD;
          });
          
          //Add decline option as a regular choice
          choices.push({ card: null, label: "Decline", button: "Decline" });
          
          //**AI code
          if (corp.AI != null) {
            //AI prefers to put back agendas if HQ is vulnerable, or low-value cards
            var bestChoice = null;
            var agendaInHQ = null;
            for (var i = 0; i < choices.length - 1; i++) {
              var card = choices[i].card;
              if (CheckCardType(card, ["agenda"])) {
                agendaInHQ = choices[i];
              }
            }
            //Put agenda on R&D if runner might access HQ soon
            if (agendaInHQ && corp.HQ.ice.length < 2) {
              bestChoice = agendaInHQ;
            }
            if (bestChoice) {
              choices = [bestChoice];
            } else {
              //Decline by default
              choices = [{ card: null, label: "Decline", button: "Decline" }];
            }
          }
          
          DecisionPhase(
            corp,
            choices,
            function(params) {
              if (params.card !== null) {
                //Move card from HQ to top of R&D using MoveCard
                var cardTitle = GetTitle(params.card);
                MoveCard(params.card, corp.RnD.cards); //top of R&D (end of array)
                Log(cardTitle + " added to top of R&D");
              }
            },
            "Empiricist",
            "Drag a card to R&D or click Decline",
            cardRef
          );
        });
      },
      visual: { y: 67, h: 32 },
    },
    {
      text: "Do 1 net damage. Give the Runner 1 tag.",
      Resolve: function() {
        Damage("net", 1, true, function() {
          AddTags(1);
        });
      },
      visual: { y: 102, h: 32 },
    },
    {
      text: "Do 2 net damage.",
      Resolve: function() {
        Damage("net", 2, true);
      },
      visual: { y: 129, h: 16 },
    },
  ],
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    //Sub 1: draw + optional R&D top (misc benefit)
    //Sub 2: 1 net damage + 1 tag
    //Sub 3: 2 net damage
    result.sr = [
      [["misc_moderate"]],
      [["netDamage"], ["tag"]],
      [["netDamage", "netDamage"]]
    ];
    return result;
  },
  AIRezReasons: function() {
    return { facecheck: true, damage: true };
  },
};

//Doomscroll
//NBN Ice: Sentry
//Rez: 3, Strength: 3
//[sub] Give the Runner 1 tag.
//[sub] Do 1 net damage.
//[sub] Do 2 net damage if the Runner has at least 2 tags.
cardSet[35063] = {
  title: "Doomscroll",
  imageFile: "35063.png",
  player: corp,
  faction: "NBN",
  influence: 2,
  cardType: "ice",
  subTypes: ["Sentry"],
  rezCost: 3,
  strength: 3,
  subroutines: [
    {
      text: "Give the Runner 1 tag.",
      Resolve: function() {
        AddTags(1);
      },
      visual: { y: 59, h: 16 },
    },
    {
      text: "Do 1 net damage.",
      Resolve: function() {
        Damage("net", 1, true);
      },
      visual: { y: 79, h: 16 },
    },
    {
      text: "Do 2 net damage if the Runner has at least 2 tags.",
      Resolve: function() {
        if (CheckTags(2)) {
          Damage("net", 2, true);
        } else {
          Log("Runner does not have 2 tags");
        }
      },
      visual: { y: 107, h: 32 },
    },
  ],
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    //Sub 1: tag
    //Sub 2: 1 net damage
    //Sub 3: conditional 2 net damage (if 2+ tags)
    //If runner already tagged, sub 3 is very dangerous
    if (CheckTags(2)) {
      result.sr = [
        [["tag"]],
        [["netDamage"]],
        [["netDamage", "netDamage"]]
      ];
    } else if (CheckTags(1)) {
      //Runner has 1 tag - if sub 1 fires first, sub 3 becomes active
      result.sr = [
        [["tag"]],
        [["netDamage"]],
        [["netDamage", "netDamage"]]
      ];
    } else {
      //Runner not tagged - sub 3 won't fire unless sub 1 does first
      result.sr = [
        [["tag"]],
        [["netDamage"]],
        [["misc_minor"]]  //conditional, may not fire
      ];
    }
    return result;
  },
  AIRezReasons: function() {
    //Better value if runner is already tagged
    if (CheckTags(1)) {
      return { facecheck: true, damage: true, tag: true };
    }
    return { facecheck: true, tag: true };
  },
};

//Greenmail
//Weyland Agenda: Security
//Advancement: 2, Points: 1
//When you score this agenda, gain 2[credit].
//When you forfeit this agenda, gain 4[credit].
cardSet[35070] = {
  title: "Greenmail",
  imageFile: "35070.png",
  player: corp,
  faction: "Weyland Consortium",
  cardType: "agenda",
  subTypes: ["Security"],
  agendaPoints: 1,
  advancementRequirement: 2,
  //When you score this agenda, gain 2[credit].
  responseOnScored: {
    Enumerate: function() {
      if (intended.score == this) return [{}];
      return [];
    },
    Resolve: function() {
      GainCredits(corp, 2, "", this);
    },
  },
  //When you forfeit this agenda, gain 4[credit].
  //Uses responseOnForfeit trigger added to Forfeit() in mechanics.js
  responseOnForfeit: {
    Enumerate: function(card) {
      //Only trigger if this card was forfeited
      if (card !== this) return [];
      return [{}];
    },
    Resolve: function(params) {
      GainCredits(corp, 4, "", this);
    },
  },
  //AI: Consider forfeiting if desperately need credits for a key rez
  AIWouldTrigger: function() {
    //Forfeit if very low on credits and have important ice to rez
    if (Credits(corp) < 3) {
      //Check if there's unrezzed ice that could be rezzed with 4 more credits
      var allServers = [corp.HQ, corp.RnD, corp.archives].concat(corp.remoteServers);
      for (var s = 0; s < allServers.length; s++) {
        for (var i = 0; i < allServers[s].ice.length; i++) {
          var ice = allServers[s].ice[i];
          if (!ice.rezzed && RezCost(ice) <= Credits(corp) + 4) {
            return true;
          }
        }
      }
    }
    //Forfeit if we have many scored agendas and this is just 1 point
    if (AgendaPoints(corp) >= 5 && corp.scoreArea.length >= 3) {
      return true;
    }
    return false;
  },
};

//Off the Books
//Weyland Agenda: Initiative
//Advancement: 3, Points: 1
//Dividends 1 (When you score this agenda, place 1 agenda counter on it for each excess advancement counter.)
//When your discard phase ends, you may remove 1 hosted agenda counter to search R&D for 1 card 
//and reveal it. You may install that card, ignoring all costs. If you do not, add it to HQ.
cardSet[35071] = {
  title: "Off the Books",
  imageFile: "35071.png",
  player: corp,
  faction: "Weyland Consortium",
  cardType: "agenda",
  subTypes: ["Initiative"],
  agendaPoints: 2,
  advancementRequirement: 3,
  advancement: 0,
  
  //Dividends 1: When you score, place agenda counters for excess advancement
  responseOnScored: {
    Resolve: function(params) {
      if (intended.score == this) {
        var advancementOverThree = this.advancement - 3;
        if (advancementOverThree > 0) AddCounters(this, "agenda", advancementOverThree);
      }
    },
    automatic: true,
  },
  
  //When your discard phase ends, you may remove 1 hosted agenda counter to search R&D
  responseOnCorpDiscardEnds: {
    Enumerate: function() {
      if (CheckCounters(this, "agenda", 1)) {
        return [{}];
      }
      return [];
    },
    Resolve: function(params) {
      var cardRef = this;
      
      //Build choices from R&D cards
      var rndChoices = ChoicesArrayCards(corp.RnD.cards);
      
      //Add decline option
      rndChoices.push({ card: null, label: "Decline", button: "Decline" });
      
      //**AI code
      if (corp.AI != null) {
        //AI prefers agendas and valuable cards
        var bestChoice = null;
        var bestScore = -1;
        for (var i = 0; i < rndChoices.length - 1; i++) {
          var card = rndChoices[i].card;
          var score = 0;
          if (CheckCardType(card, ["agenda"])) {
            score = (card.agendaPoints || 0) * 10 + 5; //high value for agendas
          } else if (CheckCardType(card, ["ice"])) {
            score = 3;
          } else if (CheckCardType(card, ["asset", "upgrade"])) {
            score = 2;
          } else if (CheckCardType(card, ["operation"])) {
            score = 1;
          }
          if (score > bestScore) {
            bestScore = score;
            bestChoice = rndChoices[i];
          }
        }
        if (bestChoice) {
          rndChoices = [bestChoice];
        } else {
          rndChoices = [{ card: null, label: "Decline", button: "Decline" }];
        }
      }
      
      DecisionPhase(
        corp,
        rndChoices,
        function(searchParams) {
          if (searchParams.card !== null) {
            var foundCard = searchParams.card;
            
            //Reveal the card
            Reveal(foundCard, function() {
              //Now offer to install it or add to HQ
              //Operations cannot be installed, only added to HQ
              var installChoices = [];
              if (CheckCardType(foundCard, ["agenda", "asset", "upgrade", "ice"])) {
                installChoices.push({ action: "install", label: "Install (ignoring all costs)", button: "Install" });
              }
              installChoices.push({ action: "hq", label: "Add to HQ", button: "Add to HQ" });
              
              //**AI code
              if (corp.AI != null) {
                //Prefer to install if valuable (and installable)
                var shouldInstall = false;
                if (CheckCardType(foundCard, ["agenda"])) {
                  shouldInstall = true; //always install agendas
                } else if (CheckCardType(foundCard, ["ice"])) {
                  shouldInstall = true; //prefer to install ice
                } else if (CheckCardType(foundCard, ["asset", "upgrade"])) {
                  shouldInstall = true; //install assets/upgrades if found
                }
                if (shouldInstall) {
                  installChoices = [{ action: "install", label: "Install", button: "Install" }];
                } else {
                  installChoices = [{ action: "hq", label: "Add to HQ", button: "Add to HQ" }];
                }
              }
              
              DecisionPhase(
                corp,
                installChoices,
                function(installParams) {
                  RemoveCounters(cardRef, "agenda", 1);
                  
                  if (installParams.action === "install") {
                    //Build server choices manually (card is still in R&D so ChoicesCardInstall won't work properly)
                    var serverChoices = [];
                    
                    //All Corp cards can go to new remote or existing remotes
                    serverChoices.push({ server: null, label: GetTitle(foundCard, true) + " -> new server" });
                    for (var j = 0; j < corp.remoteServers.length; j++) {
                      serverChoices.push({
                        server: corp.remoteServers[j],
                        label: GetTitle(foundCard, true) + " -> " + corp.remoteServers[j].serverName
                      });
                    }
                    
                    //Ice and upgrades can also go to centrals
                    if (foundCard.cardType === "ice" || foundCard.cardType === "upgrade") {
                      serverChoices.push({ server: corp.HQ, label: GetTitle(foundCard, true) + " -> HQ" });
                      serverChoices.push({ server: corp.RnD, label: GetTitle(foundCard, true) + " -> R&D" });
                      serverChoices.push({ server: corp.archives, label: GetTitle(foundCard, true) + " -> Archives" });
                    }
                    
                    //**AI code
                    if (corp.AI != null && serverChoices.length > 1) {
                      //AI picks first available server option
                      serverChoices = [serverChoices[0]];
                    }
                    
                    DecisionPhase(
                      corp,
                      serverChoices,
                      function(serverParams) {
                        //Install the card ignoring all costs to the chosen server
                        Install(foundCard, serverParams.server, true, null, true, null, cardRef);
                        Log(GetTitle(foundCard, true) + " installed from R&D via Off the Books");
                        //Shuffle R&D
                        ShuffleArray(corp.RnD.cards);
                      },
                      "Off the Books",
                      "Choose install destination",
                      cardRef,
                      "server"
                    );
                  } else {
                    //Add to HQ
                    MoveCard(foundCard, corp.HQ.cards);
                    Log(GetTitle(foundCard, true) + " added to HQ from R&D via Off the Books");
                    //Shuffle R&D
                    ShuffleArray(corp.RnD.cards);
                  }
                },
                "Off the Books",
                "Install or add to HQ?",
                cardRef
              );
            }, cardRef);
          } else {
            //Declined - still remove the counter since we checked it
            RemoveCounters(cardRef, "agenda", 1);
          }
        },
        "Off the Books",
        "Search R&D for 1 card",
        cardRef
      );
    },
  },
  
  AIOverAdvance: true, //good to over-advance for extra counters
  AIAdvancementLimit: function() {
    var maxThisTurn = Math.min(corp.AI._clicksLeft(), Credits(corp)) + this.advancement;
    return Math.max(3, maxThisTurn); //want to over-advance for the counters
  },
};

//Idiosyncresis
//NBN Asset
//Rez: 1, Trash: 2
//You can advance this asset.
//When your turn begins, you may trash this asset. If you do, for each hosted 
//advancement counter, gain 3[credit] and the Runner loses 2[credit].
cardSet[35061] = {
  title: "Idiosyncresis",
  imageFile: "35061.png",
  player: corp,
  faction: "NBN",
  influence: 2,
  cardType: "asset",
  rezCost: 1,
  trashCost: 2,
  canBeAdvanced: true,
  advancement: 0,
  
  //When your turn begins, you may trash this asset for credits
  responseOnCorpTurnBegins: {
    Enumerate: function() {
      //Only offer if there's at least 1 advancement counter
      if (CheckCounters(this, "advancement", 1)) return [{}];
      return [];
    },
    Resolve: function(params) {
      var advCounters = Counters(this, "advancement");
      var creditsToGain = advCounters * 3;
      var creditsToLose = advCounters * 2;
      
      var cardRef = this;
      var binaryChoices = BinaryDecision(
        corp,
        "Gain " + creditsToGain + "[c], Runner loses " + creditsToLose + "[c]",
        "Decline",
        "Idiosyncresis",
        this,
        function() {
          Trash(
            cardRef,
            false,  //cannot be prevented (it's a cost)
            function(cardsTrashed) {
              GainCredits(corp, creditsToGain);
              LoseCredits(runner, creditsToLose);
            },
            cardRef
          );
        }
      );
      
      //**AI code
      if (corp.AI != null) {
        var choice = binaryChoices[0]; //activate by default
        if (!cardRef.AIWouldTrigger()) choice = binaryChoices[1];
        corp.AI.preferred = { title: "Idiosyncresis", option: choice };
      }
    },
    text: "Trash for credits",
  },
  
  //AI: Should we trigger the ability?
  AIWouldTrigger: function() {
    var advCounters = Counters(this, "advancement");
    //Always trigger if we have advancement counters (net gain of 3 per counter is great)
    if (advCounters >= 1) return true;
    return false;
  },
  
  //AI: How many advancement counters to put on this
  AIAdvancementLimit: function() {
    //Cap at a reasonable number - diminishing returns after a point
    //3-4 counters = 9-12 credits gain, 6-8 runner credit loss
    return 4;
  },
  
  //AI: Should we over-advance past the limit?
  AIOverAdvance: false,
  
  //AI: Rush to finish advancing if possible
  AIRushToFinish: function() {
    //Rush if the server is well-protected
    return (corp.AI._serverToProtect() != GetServer(this));
  },
  
  RezUsability: function() {
    //Can rez during runner's turn for defense
    if (currentPhase.identifier == "Runner 2.2") return true;
    return false;
  },
};

//Public Access Plaza
//NBN Asset
//Rez: 1, Trash: 2
//When your turn begins, gain 1[credit].
//Threat 2 → When the Runner trashes this asset (while it is rezzed), give them 1 tag.
cardSet[35062] = {
  title: "Public Access Plaza",
  imageFile: "35062.png",
  player: corp,
  faction: "NBN",
  influence: 1,
  cardType: "asset",
  rezCost: 1,
  trashCost: 2,
  
  //When your turn begins, gain 1[credit].
  responseOnCorpTurnBegins: {
    Resolve: function(params) {
      GainCredits(corp, 1);
    },
    automatic: true,
  },
  
  //Track if card was rezzed when trashed (for the tag condition)
  wasRezzedWhenTrashed: false,
  wasBeingAccessed: false,
  
  automaticOnWouldTrash: {
    Resolve: function(cards) {
      if (cards.includes(this)) {
        this.wasRezzedWhenTrashed = this.rezzed;
        //Check if this card was being accessed when trashed
        this.wasBeingAccessed = (typeof accessingCard !== 'undefined' && accessingCard === this);
      }
    },
  },
  
  //Threat 2 → When the Runner trashes this asset (while it is rezzed), give them 1 tag.
  responseOnTrash: {
    Resolve: function() {
      //wasRezzedWhenTrashed and wasBeingAccessed are set by automaticOnWouldTrash
      //Check threat level (max of both players' agenda points >= 2)
      var threatLevel = Math.max(AgendaPoints(corp), AgendaPoints(runner));
      if (threatLevel < 2) return;
      //Check if it was rezzed when trashed
      if (!this.wasRezzedWhenTrashed) return;
      //Check if Runner trashed it (was being accessed)
      if (!this.wasBeingAccessed) return;
      AddTags(1);
      Log("Public Access Plaza gives the Runner 1 tag (Threat 2)");
    },
    automatic: true,
    availableWhenInactive: true, //Card is in Archives when this fires
  },
  
  RezUsability: function() {
    if (currentPhase.identifier == "Runner 2.2") return true;
    return false;
  },
  
  AIAvoidInstallingOverThis: true,
  
  //AI: Worth installing as economy
  AIWorthInstalling: function(emptyProtectedRemotes) {
    //Install if we need economy
    if (!corp.AI._sufficientEconomy(false) || this.cardLocation == corp.archives.cards) {
      //Choose the first non-scoring server
      for (var j = 0; j < emptyProtectedRemotes.length; j++) {
        if (!corp.AI._isAScoringServer(emptyProtectedRemotes[j])) return j;
      }
      return emptyProtectedRemotes.length;
    }
    return -1;
  },
};

//Syailendra
//Weyland Ice: Barrier
//Rez: 4, Strength: 5
//You can advance this ice.
//When the Runner encounters this ice, if it has 3 or more hosted advancement counters, 
//you may place 1 advancement counter on an installed card you can advance.
//[sub] You may place 1 advancement counter on an installed card you can advance.
//[sub] The Runner loses 2[credit].
//[sub] Do 1 net damage.
cardSet[35076] = {
  title: "Syailendra",
  imageFile: "35076.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 2,
  cardType: "ice",
  subTypes: ["Barrier"],
  rezCost: 4,
  strength: 5,
  canBeAdvanced: true,
  advancement: 0,
  
  //When the Runner encounters this ice, if it has 3+ advancement counters,
  //you may place 1 advancement counter on an installed card you can advance.
  responseOnEncounter: {
    Enumerate: function(card) {
      if (card !== this) return [];
      if (!CheckCounters(this, "advancement", 3)) return [];
      //Check if there are advanceable cards
      var advanceableCards = ChoicesInstalledCards(corp, function(c) {
        return CheckAdvance(c);
      });
      if (advanceableCards.length === 0) return [];
      return [{}];
    },
    Resolve: function(params) {
      var cardRef = this;
      var advanceableCards = ChoicesInstalledCards(corp, function(c) {
        return CheckAdvance(c);
      });
      
      //Add decline option
      advanceableCards.push({ card: null, label: "Decline", button: "Decline" });
      
      //**AI code
      if (corp.AI != null) {
        //Prefer to advance agendas that can be scored soon
        var bestTarget = null;
        for (var i = 0; i < advanceableCards.length - 1; i++) {
          var target = advanceableCards[i].card;
          if (CheckCardType(target, ["agenda"])) {
            if (corp.AI._cardShouldBeFastAdvanced(target)) {
              bestTarget = advanceableCards[i];
              break;
            }
          }
        }
        if (bestTarget) {
          advanceableCards = [bestTarget];
        } else {
          //Decline if no good target
          advanceableCards = [{ card: null, label: "Decline", button: "Decline" }];
        }
      }
      
      DecisionPhase(
        corp,
        advanceableCards,
        function(targetParams) {
          if (targetParams.card !== null) {
            AddCounters(targetParams.card, "advancement", 1);
            Log("Syailendra places 1 advancement counter on " + GetTitle(targetParams.card, true));
          }
        },
        "Syailendra",
        "Place advancement counter?",
        cardRef
      );
    },
    text: "Place advancement counter (3+ counters)",
  },
  
  subroutines: [
    {
      text: "You may place 1 advancement counter on an installed card you can advance.",
      Resolve: function() {
        var cardRef = this;
        var advanceableCards = ChoicesInstalledCards(corp, function(c) {
          return CheckAdvance(c);
        });
        
        if (advanceableCards.length === 0) {
          Log("No advanceable cards installed");
          return;
        }
        
        //Add decline option
        advanceableCards.push({ card: null, label: "Decline", button: "Decline" });
        
        //**AI code
        if (corp.AI != null) {
          var bestTarget = null;
          for (var i = 0; i < advanceableCards.length - 1; i++) {
            var target = advanceableCards[i].card;
            if (CheckCardType(target, ["agenda"])) {
              if (corp.AI._cardShouldBeFastAdvanced(target)) {
                bestTarget = advanceableCards[i];
                break;
              }
            }
          }
          //Also consider advancing this ice if no good agenda target
          if (!bestTarget) {
            for (var i = 0; i < advanceableCards.length - 1; i++) {
              if (advanceableCards[i].card === cardRef) {
                bestTarget = advanceableCards[i];
                break;
              }
            }
          }
          if (bestTarget) {
            advanceableCards = [bestTarget];
          } else {
            advanceableCards = [{ card: null, label: "Decline", button: "Decline" }];
          }
        }
        
        DecisionPhase(
          corp,
          advanceableCards,
          function(targetParams) {
            if (targetParams.card !== null) {
              AddCounters(targetParams.card, "advancement", 1);
              Log("Syailendra places 1 advancement counter on " + GetTitle(targetParams.card, true));
            }
          },
          "Syailendra",
          "Place advancement counter?",
          cardRef
        );
      },
      visual: { y: 145, h: 32 },
    },
    {
      text: "The Runner loses 2[credit].",
      Resolve: function() {
        LoseCredits(runner, 2);
      },
      visual: { y: 169, h: 16 },
    },
    {
      text: "Do 1 net damage.",
      Resolve: function() {
        Damage("net", 1, true);
      },
      visual: { y: 188, h: 16 },
    },
  ],
  
  //AI: How many advancement counters to put on this
  AIAdvancementLimit: function() {
    //Only advance once rezzed to preserve secrecy
    if (!this.rezzed) return 0;
    //Aim for 3 counters to activate the encounter ability
    return 3;
  },
  
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    //Sub 1: advancement placement (minor benefit)
    //Sub 2: Runner loses 2 credits
    //Sub 3: 1 net damage
    result.sr = [
      [["misc_minor"]],
      [["loseCredits", "loseCredits"]],
      [["netDamage"]]
    ];
    //If fully advanced, encounter effect adds value
    if (CheckCounters(this, "advancement", 3)) {
      result.encounterEffects = [["misc_moderate"]];
    }
    return result;
  },
  
  AIRezReasons: function() {
    return { facecheck: true, etr: false, taxing: true };
  },
};

//The Zwicky Group: Invisible Hands
//Weyland Identity: Megacorp
//Deck: 45, Influence: 15
//The first time each turn you gain credits through an ability on an agenda or operation, 
//you may draw 1 card.
cardSet[35069] = {
  title: "The Zwicky Group: Invisible Hands",
  imageFile: "35069.png",
  player: corp,
  faction: "Weyland Consortium",
  cardType: "identity",
  deckSize: 45,
  influenceLimit: 15,
  subTypes: ["Megacorp"],
  
  //Track if ability has been used this turn
  usedThisTurn: false,
  
  responseOnCorpTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  responseOnRunnerTurnBegins: {
    Resolve: function() {
      this.usedThisTurn = false;
    },
    automatic: true,
  },
  
  //The first time each turn you gain credits through an ability on an agenda or operation,
  //you may draw 1 card.
  //Uses responseOnGainCreditsFromCard trigger - fired by GainCredits() when sourceCard is provided
  responseOnGainCreditsFromCard: {
    Enumerate: function(num, sourceCard) {
      if (this.usedThisTurn) return [];
      
      //**AI code
      if (corp.AI != null) {
        if (PlayerHand(corp).length < MaxHandSize(corp)) {
          return [{ id: 0, label: "Draw 1 card", button: "Draw 1" }];
        } else {
          return []; //decline
        }
      }
      
      return [
        { id: 0, label: "Draw 1 card", button: "Draw 1" },
        { id: 1, label: "Decline", button: "Decline" }
      ];
    },
    Resolve: function(params) {
      this.usedThisTurn = true;
      if (params.id === 0) {
        Draw(corp, 1);
      }
    },
    text: "Draw 1 card?",
  },
};
//Peer Review
//Jinteki Operation: Transaction
//Cost: 4, Influence: 2
//Reveal all but 1 card in HQ. Gain 7[credit]. You may install 1 card from HQ in the root of a remote server.

//Peer Review
//Jinteki Operation: Transaction
//Cost: 4, Influence: 2
//Reveal all but 1 card in HQ. Gain 7[credit]. You may install 1 card from HQ in the root of a remote server.
cardSet[35055] = {
  title: "Peer Review",
  imageFile: "35055.png",
  player: corp,
  faction: "Jinteki",
  influence: 2,
  cardType: "operation",
  subTypes: ["Transaction"],
  playCost: 4,
  
  //Reveal all but 1 card in HQ.
  //Gain 7[credit].
  //You may install 1 card from HQ in the root of a remote server.
  Enumerate: function () {
    //Multi-select: player chooses which cards to reveal (all but 1)
    var thisCard = this;
    var choices = ChoicesHandCards(corp, function(card) {
      return card !== thisCard;
    });
    
    var numToReveal = choices.length - 1; //reveal all but 1
    
    //If 0 or 1 other cards, numToReveal is 0 or less - just return single option (no reveal needed)
    if (numToReveal <= 0) {
      return [{ cards: [] }]; //reveal nothing, still gain credits and may install
    }
    
    //**AI code
    if (corp.AI) {
      //Decide whether to use - need reasonable hand size
      if (choices.length < 3) return [{ cards: [] }]; //small hand, just take the credits
      
      //Pick which cards to reveal (hide the most valuable)
      //Sort by "want to hide" score descending
      var scored = choices.map(function(item) {
        var card = item.card;
        var hideScore = 0;
        if (CheckCardType(card, ["agenda"])) {
          hideScore = 100 + (card.agendaPoints || 0) * 10;
        } else if (CheckCardType(card, ["asset"]) && CheckSubType(card, "Ambush")) {
          hideScore = 50;
        } else if (CheckCardType(card, ["ice"])) {
          hideScore = 20;
        } else if (CheckCardType(card, ["operation"])) {
          hideScore = 5; //safe to reveal
        }
        return { item: item, hideScore: hideScore };
      });
      scored.sort(function(a, b) { return b.hideScore - a.hideScore; });
      
      //Check if safe to play (don't reveal too many agendas)
      var agendasToReveal = 0;
      for (var i = 1; i < scored.length; i++) { //skip first (hidden card)
        if (CheckCardType(scored[i].item.card, ["agenda"])) agendasToReveal++;
      }
      if (agendasToReveal > 1) return []; //too many agendas would be revealed
      
      //Return cards to reveal (all except the one we want to hide)
      var cardsToReveal = [];
      for (var i = 1; i < scored.length; i++) {
        cardsToReveal.push(scored[i].item.card);
      }
      return [{ cards: cardsToReveal }];
    }
    
    //Human player: multi-select UI
    var requiredCount = numToReveal;
    choices.push({
      id: choices.length,
      label: "Reveal",
      multiSelectDynamicButtonText: function(numSelected) {
        return "Reveal " + numSelected + "/" + requiredCount + " card" + (numSelected == 1 ? '' : 's');
      },
      multiSelectDynamicButtonEnabler: function(numSelected) {
        return numSelected === requiredCount;
      },
      button: "Reveal 0/" + numToReveal + " cards",
    });
    for (var i = 0; i < choices.length; i++) {
      choices[i].cards = Array(numToReveal).fill(null);
    }
    return choices;
  },
  
  ResolveCommon: function(toReveal, original, callback) {
    //Reveal cards recursively
    if (toReveal.length < 1) {
      original.forEach(function(item) {
        item.faceUp = false;
      });
      if (runner.AI != null) runner.AI.GainInfoAboutHQCards(original);
      if (typeof callback === "function") callback();
      return;
    }
    var cardRef = this;
    Reveal(toReveal[0], function() {
      toReveal[0].faceUp = true;
      toReveal.splice(0, 1);
      cardRef.ResolveCommon(toReveal, original, callback);
    }, this);
  },
  
  Resolve: function (params) {
    //Create a list containing no nulls
    var toReveal = [];
    if (params.cards) {
      params.cards.forEach(function(item) {
        if (item) toReveal.push(item);
      });
    }
    
    var cardRef = this;
    
    if (toReveal.length < 1) {
      Log("No cards revealed");
      GainCredits(corp, 7, "", this);
      this._offerInstall();
      return;
    }
    
    //Reveal them all, then gain credits and offer install
    this.ResolveCommon(toReveal, toReveal.concat([]), function() {
      GainCredits(corp, 7, "", cardRef);
      cardRef._offerInstall();
    });
  },
  
  _offerInstall: function() {
    //You may install 1 card from HQ in the root of a remote server
    var thisCard = this;
    
    //Use ChoicesCardInstall for each installable card - gives drag-and-drop to servers
    var choices = [];
    var handCards = PlayerHand(corp).filter(function(card) {
      if (card === thisCard) return false;
      //Can install assets, agendas, or upgrades in root (not ice)
      return CheckCardType(card, ["asset", "agenda", "upgrade"]);
    });
    
    for (var i = 0; i < handCards.length; i++) {
      var cardChoices = ChoicesCardInstall(handCards[i]);
      //Filter to only remote servers (not central servers)
      for (var j = 0; j < cardChoices.length; j++) {
        var choice = cardChoices[j];
        //server === null means new remote, otherwise check it's not a central
        if (choice.server === null || 
            (choice.server !== corp.HQ && choice.server !== corp.RnD && choice.server !== corp.archives)) {
          choices.push(choice);
        }
      }
    }
    
    if (choices.length === 0) {
      //Reset faceUp on any revealed cards still in hand
      PlayerHand(corp).forEach(function(card) {
        card.faceUp = false;
      });
      return;
    }
    
    //Add decline option
    choices.push({
      card: null,
      label: "Decline",
      button: "Decline"
    });
    
    //**AI code
    if (corp.AI != null) {
      //Usually want to install an agenda or ambush if we have one
      var bestChoice = null;
      var bestScore = 0;
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].card === null) continue;
        var card = choices[i].card;
        var server = choices[i].server;
        var score = 0;
        if (CheckCardType(card, ["agenda"])) {
          score = 80;
          //Prefer new server for agendas
          if (server === null) score += 10;
        } else if (CheckCardType(card, ["asset"]) && CheckSubType(card, "Ambush")) {
          score = 70;
        } else if (CheckCardType(card, ["asset"])) {
          score = 40;
        } else if (CheckCardType(card, ["upgrade"])) {
          score = 30;
          //Prefer existing server with agenda for upgrades
          if (server !== null) {
            var hasAgenda = server.root.some(function(c) {
              return CheckCardType(c, ["agenda"]);
            });
            if (hasAgenda) score += 20;
          }
        }
        if (score > bestScore) {
          bestScore = score;
          bestChoice = choices[i];
        }
      }
      if (bestChoice && bestScore > 0) {
        choices = [bestChoice];
      } else {
        choices = [{ card: null, label: "Decline", button: "Decline" }];
      }
    }
    
    var cardRef = this;
    DecisionPhase(
      corp,
      choices,
      function(installParams) {
        //Reset faceUp on revealed cards first
        PlayerHand(corp).forEach(function(card) {
          card.faceUp = false;
        });
        
        if (installParams.card === null) {
          return; //declined
        }
        
        //Install in the chosen server
        var targetServer = installParams.server;
        if (targetServer === null) {
          targetServer = NewRemoteServer();
        }
        Install(installParams.card, targetServer);
      },
      "Peer Review",
      "Drag a card to a remote server or click Decline",
      this
    );
  },
};

//Plutus
//Weyland Asset: Deep Net
//Rez: 0, Trash: 3, Influence: 3, Unique
//As an additional cost to rez this asset, forfeit 1 agenda or reveal and trash 3 cards from HQ.
//When your turn begins, you may play 1 transaction operation from Archives. After it resolves, remove it from the game.
cardSet[35073] = {
  title: "Plutus",
  imageFile: "35073.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 3,
  cardType: "asset",
  subTypes: ["Deep Net"],
  rezCost: 0,
  trashCost: 3,
  unique: true,
  
  //As an additional cost to rez this asset, forfeit 1 agenda or reveal and trash 3 cards from HQ.
  //This custom property is handled in mechanics.js Rez function
  additionalRezCostForfeitOrTrashThree: true,
  
  //When your turn begins, you may play 1 transaction operation from Archives.
  //After it resolves, remove it from the game.
  responseOnCorpTurnBegins: {
    Enumerate: function() {
      if (!this.rezzed) return [];
      //Check if there are any transaction operations in Archives that we can afford
      var transactions = corp.archives.cards.filter(function(card) {
        return card.cardType === "operation" && 
               CheckSubType(card, "Transaction") &&
               AvailableCredits(corp, "playing", card) >= PlayCost(card);
      });
      if (transactions.length === 0) return [];
      return [{}];
    },
    Resolve: function() {
      var cardRef = this;
      
      //Build list of playable transaction operations in Archives
      var transactionChoices = [];
      for (var i = 0; i < corp.archives.cards.length; i++) {
        var card = corp.archives.cards[i];
        if (card.cardType === "operation" && CheckSubType(card, "Transaction")) {
          if (AvailableCredits(corp, "playing", card) >= PlayCost(card)) {
            //No button property - card shows as clickable in modal
            transactionChoices.push({ card: card, label: card.title });
          }
        }
      }
      
      //Add decline option with button (shows in footer)
      transactionChoices.push({ 
        card: null, 
        id: transactionChoices.length,
        label: "Decline", 
        button: "Decline" 
      });
      
      //**AI code
      if (corp.AI != null) {
        //Play the best transaction (usually highest credit gain)
        var bestChoice = null;
        var bestValue = 0;
        for (var i = 0; i < transactionChoices.length; i++) {
          if (transactionChoices[i].card === null) continue;
          var opCard = transactionChoices[i].card;
          //Simple heuristic: prefer cards that net more credits
          var value = 10 - PlayCost(opCard); //lower cost = better
          if (opCard.title === "Hedge Fund") value += 5;
          if (opCard.title === "Beanstalk Royalties") value += 3;
          if (value > bestValue) {
            bestValue = value;
            bestChoice = transactionChoices[i];
          }
        }
        if (bestChoice) {
          transactionChoices = [bestChoice];
        } else {
          transactionChoices = [{ card: null, label: "Decline", button: "Decline" }];
        }
      }
      
      DecisionPhase(
        corp,
        transactionChoices,
        function(params) {
          if (params.card !== null) {
            var opCard = params.card;
            //Mark that this card should be removed from game after playing
            opCard._removeFromGameAfterResolve = true;
            
            //Use the standard Play function with a callback
            //The card will be removed from game instead of going to Archives
            //because of the _removeFromGameAfterResolve flag (checked in phase.js)
            SpendCredits(corp, PlayCost(opCard), "playing", opCard, function() {
              //Override the log message
              var customLogMessage = 'Played "' + GetTitle(opCard, true) + '" from Archives via Plutus';
              
              //Move from Archives to resolving
              MoveCard(opCard, corp.resolvingCards);
              opCard.faceUp = true;
              
              Log(customLogMessage);
              AutomaticTriggers("automaticOnPlay", [opCard]);
              
              //Get choices from Enumerate if available
              var choices = [{}];
              if (typeof opCard.Enumerate === "function") {
                choices = opCard.Enumerate.call(opCard);
              }
              
              if (choices.length === 0) {
                //No valid choices - operation can't resolve
                Log("No valid targets for " + GetTitle(opCard));
                RemoveFromGame(opCard);
                delete opCard._removeFromGameAfterResolve;
                return;
              }
              
              //If multiple choices, let player choose (for human) or pick first (for AI)
              if (choices.length === 1 || corp.AI != null) {
                //Single choice or AI - just resolve
                opCard.Resolve.call(opCard, choices[0]);
                //After resolving, remove from game (card is still in resolvingCards)
                RemoveFromGame(opCard);
                delete opCard._removeFromGameAfterResolve;
              } else {
                //Multiple choices - let player decide via DecisionPhase
                var instruction = GetTitle(opCard, true);
                if (typeof opCard.text !== "undefined") instruction = opCard.text;
                
                DecisionPhase(
                  corp,
                  choices,
                  function(resolveParams) {
                    opCard.Resolve.call(opCard, resolveParams);
                    //After resolving, remove from game
                    RemoveFromGame(opCard);
                    delete opCard._removeFromGameAfterResolve;
                  },
                  "Playing " + GetTitle(opCard, true),
                  instruction,
                  opCard
                );
              }
            }, cardRef);
          }
        },
        "Plutus",
        "Play a transaction operation from Archives?",
        this
      );
    },
    text: "Plutus",
  },
  
  //Rez at end of Runner turn
  RezUsability: function() {
    if (currentPhase.identifier === "Runner 2.2") return true;
    return false;
  },
  
  //**AI code
  AIWorthInstalling: function(emptyProtectedRemotes) {
    //Check if we can rez it (need scored agenda OR 3+ cards in HQ)
    if (corp.scoreArea.length === 0 && corp.HQ.cards.length < 3) return -1;
    
    //Check if there are transactions in Archives or likely to be
    var transactionsInArchives = corp.archives.cards.filter(function(card) {
      return card.cardType === "operation" && CheckSubType(card, "Transaction");
    }).length;
    
    //Worth installing if we have transactions in archives or a reasonable deck
    if (transactionsInArchives > 0 || corp.RnD.cards.length > 10) {
      for (var j = 0; j < emptyProtectedRemotes.length; j++) {
        if (!corp.AI._isAScoringServer(emptyProtectedRemotes[j])) return j;
      }
      return emptyProtectedRemotes.length;
    }
    return -1;
  },
};

//Biawak
//Weyland Ice: Sentry - Destroyer
//Rez: 14, Strength: 6, Influence: 3
//You can forfeit 1 agenda as you rez this ice to pay for 10 credits of its rez cost.
//Sub: Trash 1 installed program or end the run.
//Sub: Trash 1 installed resource or end the run.
//Sub: End the run.
cardSet[35074] = {
  title: "Biawak",
  imageFile: "35074.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 3,
  cardType: "ice",
  rezCost: 14,
  strength: 6,
  subTypes: ["Sentry", "Destroyer"],
  
  //You can forfeit 1 agenda as you rez this ice to pay for 10 credits of its rez cost.
  optionalForfeitRezReduction: 10,
  
  //Helper for AI decisions
  AIWouldTrashProgram: function() {
    var thisServer = GetServer(this);
    if (corp.AI._agendasInServer(thisServer) > 0) {
      if (Math.random() < 0.5) return false;
    }
    return true;
  },
  AIWouldTrashResource: function() {
    var thisServer = GetServer(this);
    if (corp.AI._agendasInServer(thisServer) > 0) {
      if (Math.random() < 0.5) return false;
    }
    return true;
  },
  
  subroutines: [
    {
      text: "Trash 1 installed program or end the run.",
      Resolve: function () {
        var iceCard = this;
        var choicesA = [];
        var choicesB = ChoicesInstalledCards(runner, function (card) {
          if (CheckCardType(card, ["program"]) && CheckTrash(card)) return true;
          return false;
        });
        if (choicesB.length > 0)
          choicesA.push({
            id: 0,
            label: "Trash 1 program",
            button: "Trash 1 program",
          });
        choicesA.push({ id: 1, label: "End the run", button: "End the run" });
        var decisionCallbackA = function (params) {
          if (params.id == 0) {
            var decisionCallbackB = function (params) {
              Trash(params.card, true);
            };
            DecisionPhase(
              corp,
              choicesB,
              decisionCallbackB,
              "Biawak",
              "Trash a program",
              iceCard,
              "trash"
            );
          } else EndTheRun();
        };
        DecisionPhase(
          corp,
          choicesA,
          decisionCallbackA,
          "Biawak",
          "Biawak",
          this
        );
        //**AI code
        if (corp.AI != null && choicesA.length > 1) {
          var choice = choicesA[0]; //trash program by default
          if (!this.AIWouldTrashProgram()) choice = choicesA[1]; //end the run
          corp.AI.preferred = { title: "Biawak", option: choice };
        }
      },
      visual: { y: 94, h: 16 },
    },
    {
      text: "Trash 1 installed resource or end the run.",
      Resolve: function () {
        var iceCard = this;
        var choicesA = [];
        var choicesB = ChoicesInstalledCards(runner, function (card) {
          if (CheckCardType(card, ["resource"]) && CheckTrash(card)) return true;
          return false;
        });
        if (choicesB.length > 0)
          choicesA.push({
            id: 0,
            label: "Trash 1 resource",
            button: "Trash 1 resource",
          });
        choicesA.push({ id: 1, label: "End the run", button: "End the run" });
        var decisionCallbackA = function (params) {
          if (params.id == 0) {
            var decisionCallbackB = function (params) {
              Trash(params.card, true);
            };
            DecisionPhase(
              corp,
              choicesB,
              decisionCallbackB,
              "Biawak",
              "Trash a resource",
              iceCard,
              "trash"
            );
          } else EndTheRun();
        };
        DecisionPhase(
          corp,
          choicesA,
          decisionCallbackA,
          "Biawak",
          "Biawak",
          this
        );
        //**AI code
        if (corp.AI != null && choicesA.length > 1) {
          var choice = choicesA[0]; //trash resource by default
          if (!this.AIWouldTrashResource()) choice = choicesA[1]; //end the run
          corp.AI.preferred = { title: "Biawak", option: choice };
        }
      },
      visual: { y: 114, h: 16 },
    },
    {
      text: "End the run.",
      Resolve: function () {
        EndTheRun();
      },
      visual: { y: 133, h: 16 },
    },
  ],
  
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    var installedPrograms = ChoicesInstalledCards(runner, function (card) {
      return CheckCardType(card, ["program"]);
    });
    var installedResources = ChoicesInstalledCards(runner, function (card) {
      return CheckCardType(card, ["resource"]);
    });
    
    var sub1 = installedPrograms.length > 0 ? [["misc_serious"]] : [["endTheRun"]];
    var sub2 = installedResources.length > 0 ? [["misc_serious"]] : [["endTheRun"]];
    var sub3 = [["endTheRun"]];
    
    result.sr = [sub1, sub2, sub3];
    return result;
  },
};

//Measured Response
//Weyland Operation: Black Ops
//Cost: 5, Influence: 4
//Play only if the threat level is 4 or greater, and only if the Runner made a successful run during their last turn.
//Do 4 meat damage unless the Runner pays 8 credits.
cardSet[35078] = {
  title: "Measured Response",
  imageFile: "35078.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 4,
  cardType: "operation",
  subTypes: ["Black Ops"],
  playCost: 5,
  
  //Track successful runs from last turn (same pattern as Public Trail)
  successfulRunLastTurn: false,
  responseOnRunnerTurnBegins: {
    Resolve: function () {
      this.successfulRunLastTurn = false;
    },
    automatic: true,
    availableWhenInactive: true,
  },
  responseOnRunSuccessful: {
    Resolve: function () {
      this.successfulRunLastTurn = true;
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //Play only if threat level >= 4 and Runner made successful run last turn
  Enumerate: function () {
    //Check threat level (max of both players' agenda points)
    var threatLevel = Math.max(AgendaPoints(corp), AgendaPoints(runner));
    if (threatLevel < 4) return [];
    
    //Check if Runner made successful run last turn
    if (!this.successfulRunLastTurn) return [];
    
    return [{}];
  },
  
  //Do 4 meat damage unless the Runner pays 8 credits
  Resolve: function (params) {
    var choices = [];
    if (CheckCredits(runner, 8)) {
      choices.push({ id: 0, label: "Pay 8[c]", button: "Pay 8[c]" });
    }
    choices.push({ id: 1, label: "Take 4 meat damage", button: "Take 4 damage" });
    
    //**AI code
    if (runner.AI != null) {
      //Pay if we can afford it and it would save us from death/significant damage
      if (choices.length > 1 && choices[0].id === 0) {
        var handSize = PlayerHand(runner).length;
        //Pay to avoid damage if it would kill us or leave us very vulnerable
        if (handSize <= 4 || Credits(runner) > 12) {
          runner.AI.preferred = { title: "Measured Response", option: choices[0] };
        } else {
          runner.AI.preferred = { title: "Measured Response", option: choices[choices.length - 1] };
        }
      }
    }
    
    function decisionCallback(params) {
      if (params.id == 0) {
        SpendCredits(runner, 8);
      } else {
        Damage("meat", 4, true); //true = can be prevented
      }
    }
    DecisionPhase(
      runner,
      choices,
      decisionCallback,
      "Measured Response",
      "Measured Response",
      this
    );
  },
  
  //AI: Play if Runner made successful run and we can threaten lethal or significant damage
  AIPriority: function (cardToInstall) {
    var handSize = PlayerHand(runner).length;
    //High priority if it could kill or leave Runner very low
    if (handSize <= 4) return 20;
    if (handSize <= 6) return 10;
    return 5;
  },
};

//Lamplighter
//Neutral Ice: Sentry - Observer
//Rez: 2, Strength: 3, Influence: 0
//When an agenda is scored or stolen from this server or its root, trash this ice.
//Sub: Give the Runner 1 tag unless they pay 3 credits.
//Sub: End the run if the Runner is tagged.

//Lamplighter
//Neutral Ice: Sentry - Observer
//Rez: 2, Strength: 3, Influence: 0
//When an agenda is scored or stolen from this server or its root, trash this ice.
//Sub: Give the Runner 1 tag unless they pay 3 credits.
//Sub: End the run if the Runner is tagged.
cardSet[35080] = {
  title: "Lamplighter",
  imageFile: "35080.png",
  player: corp,
  faction: "Neutral",
  influence: 0,
  cardType: "ice",
  rezCost: 2,
  strength: 3,
  subTypes: ["Sentry", "Observer"],
  
  //When an agenda is stolen from this server, trash this ice
  //During stealing, attackedServer is still set to the server being run
  responseOnStolen: {
    Resolve: function(params) {
      if (!this.rezzed) return;
      
      var myServer = GetServer(this);
      if (!myServer) return;
      
      //During a run, attackedServer tells us where the steal happened
      if (attackedServer !== myServer) return;
      
      Log("Lamplighter trashed (agenda stolen from this server)");
      Trash(this, false); //false = cannot be prevented
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //When an agenda is scored from this server, trash this ice
  //We use responseOnScored and check intended.score's original location
  //Since intended.score is set before MoveCard, we store the server in automaticOnBeginScore
  _agendaScoredFromMyServer: false,
  
  //Track when an agenda in our server is about to be scored
  //This fires at start of corp turn and when actions begin
  responseOnCorpActionPhaseBegins: {
    Resolve: function() {
      this._agendaScoredFromMyServer = false;
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //Check right before score happens
  responsePreventableScore: {
    Resolve: function() {
      if (!this.rezzed) return;
      
      //Check if the agenda being scored is in our server
      var myServer = GetServer(this);
      if (!myServer) return;
      if (!intended.score) return;
      
      var agendaServer = GetServer(intended.score);
      if (agendaServer === myServer) {
        this._agendaScoredFromMyServer = true;
      }
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  responseOnScored: {
    Resolve: function(params) {
      if (!this.rezzed) return;
      
      if (!this._agendaScoredFromMyServer) return;
      
      this._agendaScoredFromMyServer = false;
      Log("Lamplighter trashed (agenda scored from this server)");
      Trash(this, false); //false = cannot be prevented
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  subroutines: [
    {
      text: "Give the Runner 1 tag unless they pay 3[c].",
      Resolve: function () {
        var choices = [];
        if (CheckCredits(runner, 3)) {
          choices.push({ id: 0, label: "Pay 3[c]", button: "Pay 3[c]" });
        }
        choices.push({ id: 1, label: "Take 1 tag", button: "Take 1 tag" });
        
        //**AI code
        if (runner.AI != null) {
          //Pay to avoid tag if we can afford it
          if (choices.length > 1 && choices[0].id === 0) {
            runner.AI.preferred = { title: "Lamplighter", option: choices[0] };
          }
        }
        
        function decisionCallback(params) {
          if (params.id == 0) {
            SpendCredits(runner, 3);
          } else {
            AddTags(1);
          }
        }
        DecisionPhase(
          runner,
          choices,
          decisionCallback,
          "Lamplighter",
          "Lamplighter",
          this
        );
      },
      visual: { y: 102, h: 36 },
    },
    {
      text: "End the run if the Runner is tagged.",
      Resolve: function () {
        if (runner.tags > 0) {
          EndTheRun();
        }
      },
      visual: { y: 128, h: 16 },
    },
  ],
  
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    //Sub 1: Tag unless pay 3
    //Sub 2: ETR if tagged
    var sub1 = [["tag", "misc_minor"]]; //tag or lose 3 credits
    var sub2 = runner.tags > 0 ? [["endTheRun"]] : [["nothing"]];
    
    result.sr = [sub1, sub2];
    return result;
  },
};

//Semak-samun
//Jinteki Ice: Barrier - AP
//Rez: 3, Strength: 3, Influence: 1
//The Runner cannot break the printed subroutine on this ice except using a fracter.
//Sub: End the run unless the Runner suffers 3 net damage.
cardSet[35054] = {
  title: "Semak-samun",
  imageFile: "35054.png",
  player: corp,
  faction: "Jinteki",
  influence: 1,
  cardType: "ice",
  rezCost: 3,
  strength: 3,
  subTypes: ["Barrier", "AP"],
  
  //The Runner cannot break the printed subroutine on this ice except using a fracter
  //This blocks AI breakers and non-fracter breakers
  canOnlyBreakUsingFracter: true,
  cannotBreakUsingAIPrograms: true, //AI breakers check this directly in their Enumerate
  
  subroutines: [
    {
      text: "End the run unless the Runner suffers 3 net damage.",
      Resolve: function () {
        var choices = [
          { id: 0, label: "Suffer 3 net damage", button: "Take 3 damage" },
          { id: 1, label: "End the run", button: "End the run" }
        ];
        
        //**AI code
        if (runner.AI != null) {
          var handSize = PlayerHand(runner).length;
          //Take damage if we have enough cards to survive, otherwise ETR
          if (handSize > 3) {
            runner.AI.preferred = { title: "Semak-samun", option: choices[0] };
          } else {
            runner.AI.preferred = { title: "Semak-samun", option: choices[1] };
          }
        }
        
        function decisionCallback(params) {
          if (params.id == 0) {
            Damage("net", 3, true); //true = can be prevented
          } else {
            EndTheRun();
          }
        }
        DecisionPhase(
          runner,
          choices,
          decisionCallback,
          "Semak-samun",
          "Semak-samun",
          this
        );
      },
      visual: { y: 117, h: 32 },
    },
  ],
  
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    //Runner chooses: 3 net damage or ETR
    //If hand size > 3, they'll likely take damage
    var handSize = PlayerHand(runner).length;
    if (handSize > 3) {
      result.sr = [[["netDamage", "netDamage", "netDamage"]]];
    } else {
      result.sr = [[["endTheRun"]]];
    }
    return result;
  },
};

//N-Pot
//NBN Ice: Code Gate
//Rez: 4, Strength: 4, Influence: 2
//3 credits: Break 1 subroutine on this ice. Only the Runner can use this ability.
//Sub: End the run.
//Sub: If the threat level is 2 or greater, end the run.
//Sub: If the threat level is 4 or greater, end the run.
cardSet[35064] = {
  title: "N-Pot",
  imageFile: "35064.png",
  player: corp,
  faction: "NBN",
  influence: 2,
  cardType: "ice",
  rezCost: 4,
  strength: 4,
  subTypes: ["Code Gate"],
  
  //Runner ability to break subroutines on this ice
  //This is a special case - a Corp card with a Runner-only ability
  runnerAbilities: [
    {
      text: "Break 1 subroutine on N-Pot",
      runnerAbility: true, //Flag to indicate this ability is used by Runner, not card owner
      Enumerate: function () {
        //Only available during encounter with this ice
        if (!CheckEncounter()) return [];
        if (GetApproachEncounterIce() !== this) return [];
        if (!CheckCredits(runner, 3, "using", this)) return [];
        return ChoicesEncounteredSubroutines();
      },
      Resolve: function (params) {
        runner.creditPool -= 3;
        Log("Runner spent 3[c] to break subroutine on N-Pot");
        Break(params.subroutine);
      },
    },
  ],
  
  subroutines: [
    {
      text: "End the run.",
      Resolve: function () {
        EndTheRun();
      },
      visual: { y: 94, h: 16 },
    },
    {
      text: "If the threat level is 2 or greater, end the run.",
      Resolve: function () {
        var threatLevel = Math.max(AgendaPoints(corp), AgendaPoints(runner));
        if (threatLevel >= 2) {
          EndTheRun();
        }
      },
      visual: { y: 122, h: 29 },
    },
    {
      text: "If the threat level is 4 or greater, end the run.",
      Resolve: function () {
        var threatLevel = Math.max(AgendaPoints(corp), AgendaPoints(runner));
        if (threatLevel >= 4) {
          EndTheRun();
        }
      },
      visual: { y: 156, h: 29 },
    },
  ],
  
  AIImplementIce: function(rc, result, maxCorpCred, incomplete) {
    var threatLevel = Math.max(AgendaPoints(corp), AgendaPoints(runner));
    
    //Sub 1 always ETR
    var sub1 = [["endTheRun"]];
    //Sub 2 ETR if threat >= 2
    var sub2 = threatLevel >= 2 ? [["endTheRun"]] : [["nothing"]];
    //Sub 3 ETR if threat >= 4
    var sub3 = threatLevel >= 4 ? [["endTheRun"]] : [["nothing"]];
    
    result.sr = [sub1, sub2, sub3];
    return result;
  },
};

//Key Performance Indicators
//Weyland Operation: Transaction
//Cost: 1, Influence: 2
//Resolve 2 of the following in any order:
// - Draw 1 card. Shuffle 1 card from HQ into R&D.
// - Install 1 piece of ice from HQ, ignoring all costs.
// - Place 1 advancement counter on an installed card you can advance.
// - Gain 2 credits.
cardSet[35077] = {
  title: "Key Performance Indicators",
  imageFile: "35077.png",
  player: corp,
  faction: "Weyland Consortium",
  influence: 2,
  cardType: "operation",
  subTypes: ["Transaction"],
  playCost: 1,
  
  Resolve: function(params) {
    var cardRef = this;
    cardRef._usedEffects = [];
    cardRef._chooseEffect(1);
  },
  
  _getAvailableChoices: function() {
    var choices = [];
    
    //Effect 1: Draw 1 card, shuffle 1 card from HQ into R&D
    if (!this._usedEffects.includes(1)) {
      choices.push({
        id: 1,
        label: "Draw 1 card, shuffle 1 card from HQ into R&D"
      });
    }
    
    //Effect 2: Install 1 piece of ice from HQ, ignoring all costs
    if (!this._usedEffects.includes(2)) {
      var hasIceInHQ = false;
      for (var i = 0; i < corp.HQ.cards.length; i++) {
        if (CheckCardType(corp.HQ.cards[i], ["ice"])) {
          hasIceInHQ = true;
          break;
        }
      }
      if (hasIceInHQ) {
        choices.push({
          id: 2,
          label: "Install 1 piece of ice from HQ (ignoring all costs)"
        });
      }
    }
    
    //Effect 3: Place 1 advancement counter on an installed card you can advance
    if (!this._usedEffects.includes(3)) {
      var hasAdvanceableCards = false;
      var installedCards = InstalledCards(corp);
      for (var i = 0; i < installedCards.length; i++) {
        if (CheckAdvance(installedCards[i])) {
          hasAdvanceableCards = true;
          break;
        }
      }
      if (hasAdvanceableCards) {
        choices.push({
          id: 3,
          label: "Place 1 advancement counter on an installed card"
        });
      }
    }
    
    //Effect 4: Gain 2 credits (always available)
    if (!this._usedEffects.includes(4)) {
      choices.push({
        id: 4,
        label: "Gain 2[c]"
      });
    }
    
    return choices;
  },
  
  _chooseEffect: function(effectNumber) {
    var cardRef = this;
    var choices = cardRef._getAvailableChoices();
    
    //If no choices available, we're done
    if (choices.length === 0) return;
    
    //**AI code
    if (corp.AI != null && choices.length > 0) {
      var preferredId = cardRef._AIChooseEffect(choices);
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].id === preferredId) {
          corp.AI.preferred = { title: "Key Performance Indicators", option: choices[i] };
          break;
        }
      }
    }
    
    DecisionPhase(
      corp,
      choices,
      function(choiceParams) {
        cardRef._usedEffects.push(choiceParams.id);
        cardRef._executeEffect(choiceParams.id, effectNumber);
      },
      "Key Performance Indicators",
      "Choose effect " + effectNumber + " of 2",
      cardRef
    );
  },
  
  _executeEffect: function(effectId, effectNumber) {
    var cardRef = this;
    
    if (effectId === 1) {
      //Draw 1 card, shuffle 1 card from HQ into R&D
      Draw(corp, 1);
      
      //Now choose a card to shuffle into R&D
      var hqChoices = ChoicesArrayCards(corp.HQ.cards);
      
      //Set server for drag target
      for (var i = 0; i < hqChoices.length; i++) {
        hqChoices[i].server = corp.RnD;
      }
      
      //Add cancel option
      hqChoices.push({ cancel: true, label: "Cancel", button: "Cancel" });
      
      //**AI code
      if (corp.AI != null && hqChoices.length > 1) {
        //Shuffle the least useful card (prefer non-agendas, non-ice)
        var worstCard = hqChoices[0];
        for (var i = 0; i < hqChoices.length - 1; i++) {
          if (!CheckCardType(hqChoices[i].card, ["agenda", "ice"])) {
            worstCard = hqChoices[i];
            break;
          }
        }
        corp.AI.preferred = { title: "Key Performance Indicators", option: worstCard };
      }
      
      var shufflePhase = DecisionPhase(
        corp,
        hqChoices,
        function(shuffleParams) {
          if (shuffleParams.cancel) {
            //Undo the draw by putting the top card of HQ back on top of R&D
            var drawnCard = corp.HQ.cards[corp.HQ.cards.length - 1];
            MoveCard(drawnCard, corp.RnD.cards);
            //Remove this effect from used effects so player can choose again
            var idx = cardRef._usedEffects.indexOf(1);
            if (idx > -1) cardRef._usedEffects.splice(idx, 1);
            //Return to effect selection
            cardRef._chooseEffect(effectNumber);
            return;
          }
          MoveCard(shuffleParams.card, corp.RnD.cards);
          Shuffle(corp.RnD.cards);
          Log(GetTitle(shuffleParams.card) + " shuffled into R&D");
          if (effectNumber === 1) {
            cardRef._chooseEffect(2);
          }
        },
        "Key Performance Indicators",
        "Drag card to R&D",
        cardRef
      );
      shufflePhase.targetServerCardsOnly = true;
    }
    else if (effectId === 2) {
      //Install 1 piece of ice from HQ, ignoring all costs
      var iceChoices = ChoicesArrayInstall(corp.HQ.cards, true, function(card) {
        return CheckCardType(card, ["ice"]);
      });
      
      //Add cancel option
      iceChoices.push({ cancel: true, label: "Cancel", button: "Cancel" });
      
      //**AI code
      if (corp.AI != null && iceChoices.length > 1) {
        corp.AI.preferred = { title: "Key Performance Indicators", option: iceChoices[0] };
      }
      
      DecisionPhase(
        corp,
        iceChoices,
        function(installParams) {
          if (installParams.cancel) {
            //Remove this effect from used effects so player can choose again
            var idx = cardRef._usedEffects.indexOf(2);
            if (idx > -1) cardRef._usedEffects.splice(idx, 1);
            //Return to effect selection
            cardRef._chooseEffect(effectNumber);
            return;
          }
          //Install in the chosen server
          var targetServer = installParams.server;
          if (targetServer === null) {
            targetServer = NewRemoteServer();
          }
          Install(installParams.card, targetServer, true); //true = ignore cost
          if (effectNumber === 1) {
            cardRef._chooseEffect(2);
          }
        },
        "Key Performance Indicators",
        "Choose ice to install",
        cardRef
      );
    }
    else if (effectId === 3) {
      //Place 1 advancement counter on an installed card you can advance
      var advanceableCards = ChoicesInstalledCards(corp, function(card) {
        return CheckAdvance(card);
      });
      
      //**AI code
      if (corp.AI != null && advanceableCards.length > 0) {
        corp.AI.preferred = { title: "Key Performance Indicators", option: advanceableCards[0] };
      }
      
      DecisionPhase(
        corp,
        advanceableCards,
        function(advanceParams) {
          Advance(advanceParams.card);
          if (effectNumber === 1) {
            cardRef._chooseEffect(2);
          }
        },
        "Key Performance Indicators",
        "Choose card to advance",
        cardRef
      );
    }
    else if (effectId === 4) {
      //Gain 2 credits
      GainCredits(corp, 2);
      if (effectNumber === 1) {
        cardRef._chooseEffect(2);
      }
    }
  },
  
  //AI priority
  _AIChooseEffect: function(choices) {
    //Priority: Install ice > Advance > Draw/Shuffle > Gain credits
    var priorities = [2, 3, 1, 4];
    for (var i = 0; i < priorities.length; i++) {
      for (var j = 0; j < choices.length; j++) {
        if (choices[j].id === priorities[i]) {
          return priorities[i];
        }
      }
    }
    return choices[0].id;
  },
  
  AIWouldPlay: function() {
    //Always worth playing - flexible and cheap
    return true;
  },
};

//Petty Cash
//Neutral Corp Operation: Transaction
//Cost: 3
//Play only if you have not finished an action yet this turn.
//Gain 5[c]. If you played this operation from anywhere except HQ, gain [click].
//[click]: Play this operation from Archives. After it resolves, remove it from the game.
cardSet[35081] = {
  title: "Petty Cash",
  imageFile: "35081.png",
  player: corp,
  faction: "Neutral",
  influence: 0,
  cardType: "operation",
  subTypes: ["Transaction"],
  playCost: 3,
  
  //Track where this card was played from
  _playedFromArchives: false,
  
  //Play only if you have not finished an action yet this turn
  //This means no actions have been completed yet (actionsCompletedThisTurn === 0)
  Enumerate: function() {
    //Check play restriction: no actions finished this turn
    if (corp.actionsCompletedThisTurn > 0) return [];
    return [{}];
  },
  
  //Gain 5[c]. If you played this operation from anywhere except HQ, gain [click].
  Resolve: function(params) {
    GainCredits(corp, 5);
    //Check if played from not-HQ (i.e., from Archives)
    if (this._playedFromArchives) {
      GainClicks(corp, 1);
      Log("Petty Cash grants 1 additional click (played from Archives)");
    }
    //Reset flag after use
    this._playedFromArchives = false;
  },
  
  //[click]: Play this operation from Archives. After it resolves, remove it from the game.
  //This is implemented as a special ability that works from Archives
  archivesClickAbility: {
    text: "Play Petty Cash from Archives",
    availableWhenInactive: true,
    
    Enumerate: function() {
      //Only available when card is in Archives
      if (this.cardLocation !== corp.archives.cards) return [];
      //Check if we have clicks
      if (!CheckActionClicks(corp, 1)) return [];
      //Check the play restriction: no actions finished this turn
      if (corp.actionsCompletedThisTurn > 0) return [];
      //Check if we can afford the play cost
      if (!CheckCredits(corp, PlayCost(this), "playing", this)) return [];
      return [{}];
    },
    
    Resolve: function(params) {
      var cardRef = this;
      
      //Mark that we're playing from Archives
      cardRef._playedFromArchives = true;
      cardRef._removeFromGameAfterResolve = true;
      
      //Spend click for the ability
      SpendClicks(corp, 1);
      
      //Pay the play cost
      SpendCredits(corp, PlayCost(cardRef), "playing", cardRef, function() {
        //Move from Archives to resolving
        MoveCard(cardRef, corp.resolvingCards);
        cardRef.faceUp = true;
        
        Log('Played "Petty Cash" from Archives');
        AutomaticTriggers("automaticOnPlay", [cardRef]);
        
        //Resolve the operation
        cardRef.Resolve.call(cardRef, {});
        
        //Remove from game after resolving
        RemoveFromGame(cardRef);
        delete cardRef._removeFromGameAfterResolve;
        cardRef._playedFromArchives = false;
        
      }, cardRef);
    },
  },
  
  //**AI code
  AIWouldPlay: function() {
    //Play restriction: must be first action of turn
    if (corp.actionsCompletedThisTurn > 0) return false;
    //Good economy card - nets +2 credits
    return true;
  },
  
  //AI: Consider playing from Archives
  AIWouldTriggerArchivesAbility: function() {
    //Check if in Archives
    if (this.cardLocation !== corp.archives.cards) return false;
    //Check play restriction
    if (corp.actionsCompletedThisTurn > 0) return false;
    //Check if we can afford it
    if (!CheckCredits(corp, PlayCost(this), "playing", this)) return false;
    //Worth playing - nets +2 credits and we get the click back
    return true;
  },
};