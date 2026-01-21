//CARD DEFINITIONS FOR THE AUTOMATA INITIATIVE
setIdentifiers.push('tai');

//Saci (34017)
//Criminal Program: Trojan, cost 1, MU 1, influence 1
//Install only on a piece of ice.
//Whenever host ice is rezzed or derezzed, gain 3 credits.
cardSet[34017] = {
  title: "Saci",
  imageFile: "34017.png",
  player: runner,
  faction: "Criminal",
  influence: 1,
  cardType: "program",
  subTypes: ["Trojan"],
  installCost: 1,
  memoryCost: 1,
  
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  
  //Whenever host ice is rezzed, gain 3 credits.
  automaticOnRez: {
    Resolve: function (card) {
      //Only trigger when host ice is rezzed
      if (!this.host) return;
      if (card != this.host) return;
      Log(GetTitle(this) + " triggers");
      GainCredits(runner, 3, "", this);
    },
  },
  
  //Whenever host ice is derezzed, gain 3 credits.
  automaticOnDerez: {
    Resolve: function (card) {
      //Only trigger when host ice is derezzed
      if (!this.host) return;
      if (card != this.host) return;
      Log(GetTitle(this) + " triggers");
      GainCredits(runner, 3, "", this);
    },
  },
  
  //Tell Corp AI that this trojan is not a serious threat - Corp should still rez ice
  //Unlike Tranquilizer which derezzes ice, Saci only gives Runner 3c which is acceptable
  AIHostedDoesNotPreventRez: true,
  
  //AI: Prefer to install on unrezzed ice (to get credit when it rezzes)
  AIPreferredInstallChoice: function (choices) {
    //Find the best target - prefer unrezzed ice on servers we want to run
    var bestIndex = -1;
    var bestValue = 0;
    
    for (var i = 0; i < choices.length; i++) {
      var targetIce = choices[i].host;
      if (!targetIce) continue;
      
      var value = 1; //base value
      
      //Prefer unrezzed ice (we'll get credits when it rezzes)
      if (!targetIce.rezzed) {
        value += 5;
      }
      
      //Prefer ice on central servers (more likely to run there)
      var server = GetServer(targetIce);
      if (server == corp.HQ || server == corp.RnD || server == corp.archives) {
        value += 2;
      }
      
      //Prefer outermost ice (more likely to be encountered/rezzed)
      if (server && server.ice) {
        var iceIndex = server.ice.indexOf(targetIce);
        if (iceIndex == server.ice.length - 1) {
          value += 1; //outermost
        }
      }
      
      if (value > bestValue) {
        bestValue = value;
        bestIndex = i;
      }
    }
    
    //Install if we found any reasonable target
    if (bestValue >= 1) {
      return bestIndex;
    }
    
    return -1;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if there's unrezzed ice to target
    var installedCorpCards = InstalledCards(corp);
    for (var i = 0; i < installedCorpCards.length; i++) {
      var card = installedCorpCards[i];
      if (CheckCardType(card, ["ice"]) && !card.rezzed) {
        return true;
      }
    }
    //Less valuable but still ok if there's rezzed ice (derez effects exist)
    for (var i = 0; i < installedCorpCards.length; i++) {
      if (CheckCardType(installedCorpCards[i], ["ice"])) {
        return true;
      }
    }
    return false;
  },
  
  //Low priority for economy since it's conditional
  AIEconomyInstall: 1,
};

//Bahia Bands (34030)
//Neutral Event: Run, cost 2, influence 1
//Run any server. If successful, resolve 2 of the following in any order:
// - Draw 2 cards.
// - Install 1 card from your grip, paying 1 credit less.
// - Remove 1 tag.
// - Place 4 credits on this event. You can spend hosted credits to pay trash costs for the remainder of this run.
cardSet[34030] = {
  title: "Bahia Bands",
  imageFile: "34030.png",
  player: runner,
  faction: "Neutral",
  influence: 1,
  cardType: "event",
  subTypes: ["Run"],
  playCost: 2,
  
  //Track state for the run and choices
  runningWithThis: false,
  _usedEffects: [],
  
  //Run any server.
  Enumerate: function () {
    return ChoicesExistingServers();
  },
  
  Resolve: function (params) {
    this.runningWithThis = true;
    this._usedEffects = [];
    this._usingThisToInstallCard = null;
    MakeRun(params.server);
  },
  
  //If successful, resolve 2 of the following (triggers on successful run, not run end,
  //so the trash credits can be used during access)
  responseOnRunSuccessful: {
    Enumerate: function () {
      //Only trigger for the run initiated by this card
      if (this.runningWithThis) return [{}];
      return [];
    },
    Resolve: function (params) {
      this._usedEffects = [];
      this._chooseEffect(1);
    },
    availableWhenInactive: true,
  },
  
  //Reset state when run ends
  responseOnRunEnds: {
    Resolve: function () {
      this.runningWithThis = false;
      this.modifyInstallCost.availableWhenInactive = false;
    },
    automatic: true,
    availableWhenInactive: true,
  },
  
  //Get available choices based on what hasn't been used yet
  _getAvailableChoices: function() {
    var choices = [];
    
    //Effect 1: Draw 2 cards
    if (!this._usedEffects.includes(1)) {
      choices.push({
        id: 1,
        label: "Draw 2 cards"
      });
    }
    
    //Effect 2: Install 1 card from your grip, paying 1 credit less
    if (!this._usedEffects.includes(2)) {
      //Pre-simulate the discount to check if any cards can be installed
      this.modifyInstallCost.availableWhenInactive = true;
      var installChoices = ChoicesHandInstall(runner);
      this.modifyInstallCost.availableWhenInactive = false;
      
      if (installChoices.length > 0) {
        choices.push({
          id: 2,
          label: "Install 1 card from your grip, paying 1[c] less"
        });
      }
    }
    
    //Effect 3: Remove 1 tag (only if tagged)
    if (!this._usedEffects.includes(3)) {
      if (CheckTags(1)) {
        choices.push({
          id: 3,
          label: "Remove 1 tag"
        });
      }
    }
    
    //Effect 4: Place 4 credits on this event
    if (!this._usedEffects.includes(4)) {
      choices.push({
        id: 4,
        label: "Place 4[c] on this event (for trash costs)"
      });
    }
    
    return choices;
  },
  
  _chooseEffect: function(effectNumber) {
    var cardRef = this;
    var choices = cardRef._getAvailableChoices();
    
    //If no choices available, we're done
    if (choices.length === 0) return;
    
    //If we've already chosen 2 effects, we're done
    if (effectNumber > 2) return;
    
    //**AI code
    if (runner.AI != null && choices.length > 0) {
      var preferredId = cardRef._AIChooseEffect(choices, effectNumber);
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].id === preferredId) {
          runner.AI.preferred = { title: "Bahia Bands", option: choices[i] };
          break;
        }
      }
    }
    
    DecisionPhase(
      runner,
      choices,
      function(choiceParams) {
        cardRef._usedEffects.push(choiceParams.id);
        cardRef._executeEffect(choiceParams.id, effectNumber);
      },
      "Bahia Bands",
      "Choose effect " + effectNumber + " of 2",
      cardRef
    );
  },
  
  _executeEffect: function(effectId, effectNumber) {
    var cardRef = this;
    
    if (effectId === 1) {
      //Draw 2 cards
      Draw(runner, 2, function() {
        if (effectNumber === 1) {
          cardRef._chooseEffect(2);
        }
      }, cardRef);
    }
    else if (effectId === 2) {
      //Install 1 card from your grip, paying 1 credit less
      cardRef.modifyInstallCost.availableWhenInactive = true;
      var installChoices = ChoicesHandInstall(runner);
      
      //Add cancel option
      installChoices.push({ cancel: true, label: "Cancel", button: "Cancel" });
      
      //**AI code
      if (runner.AI != null && installChoices.length > 1) {
        //Prefer installing expensive cards to maximize discount
        var bestChoice = installChoices[0];
        var bestCost = 0;
        for (var i = 0; i < installChoices.length - 1; i++) {
          var card = installChoices[i].card;
          if (card && typeof card.installCost !== "undefined" && card.installCost > bestCost) {
            bestCost = card.installCost;
            bestChoice = installChoices[i];
          }
        }
        runner.AI.preferred = { title: "Bahia Bands", option: bestChoice };
      }
      
      DecisionPhase(
        runner,
        installChoices,
        function(installParams) {
          if (installParams.cancel) {
            //Remove this effect from used effects so player can choose again
            var idx = cardRef._usedEffects.indexOf(2);
            if (idx > -1) cardRef._usedEffects.splice(idx, 1);
            cardRef.modifyInstallCost.availableWhenInactive = false;
            //Return to effect selection
            cardRef._chooseEffect(effectNumber);
            return;
          }
          //Install the card (discount applied via modifyInstallCost checking installingCards)
          //Keep availableWhenInactive true - will be cleared by responseOnRunEnds
          Install(installParams.card, installParams.host);
          
          //Continue to second effect choice after install completes
          if (effectNumber === 1) {
            cardRef._chooseEffect(2);
          }
        },
        "Bahia Bands",
        "Install 1 card, paying 1[c] less",
        cardRef,
        "install"
      );
    }
    else if (effectId === 3) {
      //Remove 1 tag
      RemoveTags(1);
      if (effectNumber === 1) {
        cardRef._chooseEffect(2);
      }
    }
    else if (effectId === 4) {
      //Place 4 credits on this event
      PlaceCredits(cardRef, 4);
      if (effectNumber === 1) {
        cardRef._chooseEffect(2);
      }
    }
  },
  
  //Cost reduction for install effect
  modifyInstallCost: {
    Resolve: function (card) {
      //Discount any grip card or card currently being installed
      if (runner.grip.includes(card) || runner.installingCards.includes(card)) {
        return -1;
      }
      return 0;
    },
    automatic: true,
    availableWhenInactive: false, //toggled dynamically
  },
  
  //You can spend hosted credits to pay trash costs for the remainder of this run.
  canUseCredits: function (doing, card) {
    //Only usable during the run initiated by this card
    if (!this.runningWithThis) return false;
    if (doing == "paying trash costs") return true;
    return false;
  },
  
  //AI priority for choosing effects
  _AIChooseEffect: function(choices, effectNumber) {
    //Priority depends on game state
    
    //If tagged, prioritize removing tag
    if (CheckTags(1)) {
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].id === 3) return 3;
      }
    }
    
    //If we have cards to install and credits are tight, prioritize install discount
    if (runner.grip.length > 3) {
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].id === 2) return 2;
      }
    }
    
    //If we don't have many cards, prioritize draw
    if (runner.grip.length <= 2) {
      for (var i = 0; i < choices.length; i++) {
        if (choices[i].id === 1) return 1;
      }
    }
    
    //If run might continue with accesses (credits for trashing), take the credits
    //This is useful if there are cards in the accessed server
    for (var i = 0; i < choices.length; i++) {
      if (choices[i].id === 4) return 4;
    }
    
    //Default priority: Draw > Install > Credits > Tag removal
    var priorities = [1, 2, 4, 3];
    for (var i = 0; i < priorities.length; i++) {
      for (var j = 0; j < choices.length; j++) {
        if (choices[j].id === priorities[i]) {
          return priorities[i];
        }
      }
    }
    
    return choices[0].id;
  },
  
  //AI evaluation for run events
  AIRunEventExtraPotential: function(server, potential) {
    //This card is expensive (4 credits) but very flexible
    //Good for servers with cards to trash (place 4 credits option)
    //Good if we need cards or are tagged
    
    //Require some potential
    if (potential < 0.5) return 0;
    
    //Bonus if we're tagged (can remove tag)
    if (CheckTags(1)) return 0.4;
    
    //Bonus if server has cards we might want to trash
    if (server && server.root && server.root.length > 0) return 0.3;
    
    //Moderate value for the flexibility
    return 0.2;
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if we can afford it and have a use for the effects
    if (Credits(runner) < 6) return false; //need credits for play cost + something
    return true;
  },
};