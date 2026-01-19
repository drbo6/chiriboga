//CARD DEFINITIONS FOR REBELLION WITHOUT REHEARSAL
//Card ID range: 34066-34130
setIdentifiers.push('rwr');

//Physarum Entangler (34082)
//Criminal Program: Virus - Trojan, cost 0, MU 1, influence 3
//Install only on a piece of ice.
//Whenever you encounter host ice, if it is not a barrier, you may pay 1 credit for each subroutine it has. If you do, bypass that ice.
//When the Corp purges virus counters, trash this program.
cardSet[34082] = {
  title: "Physarum Entangler",
  imageFile: "34082.png",
  player: runner,
  faction: "Criminal",
  influence: 3,
  cardType: "program",
  subTypes: ["Virus", "Trojan"],
  installCost: 0,
  memoryCost: 1,
  
  //Install only on a piece of ice.
  installOnlyOn: function (card) {
    if (!CheckCardType(card, ["ice"])) return false;
    return true;
  },
  
  //Whenever you encounter host ice, if it is not a barrier, you may pay 1 credit for each subroutine it has. If you do, bypass that ice.
  responseOnEncounter: {
    Enumerate: function (card) {
      //Only trigger when encountering host ice
      if (!CheckEncounter()) return [];
      if (attackedServer.ice[approachIce] != this.host) return [];
      //Must not be a barrier
      if (CheckSubType(this.host, "Barrier")) return [];
      //Calculate cost (1 per subroutine)
      var numSubs = this.host.subroutines ? this.host.subroutines.length : 0;
      if (numSubs == 0) return []; //No subroutines, no point
      //Check if runner can afford the cost
      if (!CheckCredits(runner, numSubs, "using", this)) return [];
      return [{}]; //Trigger is available
    },
    Resolve: function (params) {
      var hostIce = this.host;
      var numSubs = hostIce.subroutines ? hostIce.subroutines.length : 0;
      var cardRef = this;
      
      //Give player the choice to pay or not
      var choices = [
        { id: 0, label: "Pay " + numSubs + "[c] to bypass " + GetTitle(hostIce), button: "Pay to bypass" },
        { id: 1, label: "Don't bypass", button: "Don't bypass" }
      ];
      
      //**AI decision
      if (runner.AI != null) {
        //AI decides based on whether bypass is worth the cost
        var shouldBypass = true;
        
        //Don't bypass if it would leave us too poor
        if (Credits(runner) - numSubs < 2) {
          shouldBypass = false;
        }
        
        //Always bypass expensive-to-break ice
        if (numSubs <= 2) {
          shouldBypass = true;
        }
        
        if (shouldBypass) {
          SpendCredits(runner, numSubs, "using", cardRef, function() {
            Log(GetTitle(cardRef) + " bypasses " + GetTitle(hostIce));
            Bypass();
          }, cardRef);
        }
        return;
      }
      
      //Human player decision
      DecisionPhase(
        runner,
        choices,
        function (decision) {
          if (decision.id === 0) {
            SpendCredits(runner, numSubs, "using", cardRef, function() {
              Log(GetTitle(cardRef) + " bypasses " + GetTitle(hostIce));
              Bypass();
            }, cardRef);
          }
          //If id === 1, do nothing (continue encounter normally)
        },
        "Physarum Entangler",
        null,
        this
      );
    },
    text: "Physarum Entangler: Pay to bypass host ice",
  },
  
  //When the Corp purges virus counters, trash this program.
  responseOnPurge: {
    Enumerate: function (numPurged) {
      return [{}]; //Always triggers when purge happens
    },
    Resolve: function (params) {
      Log(GetTitle(this) + " trashed (virus counters purged)");
      Trash(this, true); //true means it can be prevented
    },
    text: "Physarum Entangler trashed (purge)",
    automatic: true,
  },
  
  //AI: Marks as a special breaker (non-icebreaker that deals with ice)
  AISpecialBreaker: true,
  
  //AI: Check if Physarum can handle specific ice (will bypass on encounter)
  AIMatchingBreakerInstalled: function (iceCard) {
    if (this.host && this.host == iceCard) {
      //Check if this ice is not a barrier
      if (!CheckSubType(iceCard, "Barrier")) {
        //Check if we can afford to bypass
        var numSubs = iceCard.subroutines ? iceCard.subroutines.length : 0;
        if (numSubs > 0 && Credits(runner) >= numSubs) {
          return this; //Physarum can bypass this ice
        }
      }
    }
    return null;
  },
  
  //AI: Prefer to install on high-value non-barrier ice
  AIPreferredInstallChoice: function (choices) {
    //Don't install on ice that already has special breakers hosted
    var excludeIce = runner.AI._iceHostingSpecialBreakers();
    excludeIce.push(this); //exclude self from consideration
    var htsi = runner.AI._highestThreatScoreIce(excludeIce);
    
    //Find the best target
    var bestIndex = -1;
    var bestValue = 0;
    
    for (var i = 0; i < choices.length; i++) {
      var targetIce = choices[i].host;
      if (!targetIce) continue;
      
      //Skip barriers - Physarum can't bypass them
      if (CheckSubType(targetIce, "Barrier")) continue;
      
      //Calculate value based on ice properties
      var value = 0;
      
      //Prefer ice we know about (rezzed or known)
      if (targetIce.rezzed || targetIce.knownToRunner) {
        var numSubs = targetIce.subroutines ? targetIce.subroutines.length : 0;
        var iceCost = RezCost(targetIce);
        
        //Value based on how hard the ice is to deal with
        //But penalize ice with many subroutines (expensive to bypass)
        value = iceCost - numSubs;
        
        //Bonus for code gates and sentries (our primary targets)
        if (CheckSubType(targetIce, "Code Gate") || CheckSubType(targetIce, "Sentry")) {
          value += 3;
        }
        
        //Bonus for expensive ice with few subs (efficient bypass)
        if (iceCost >= 5 && numSubs <= 2) {
          value += 4;
        }
        
        //Penalty for ice with many subroutines (expensive bypass)
        if (numSubs >= 4) {
          value -= 3;
        }
      } else {
        //Unknown ice - moderate value, assume it's not a barrier
        value = 2;
      }
      
      //Prefer the highest threat score ice if available
      if (targetIce == htsi && !CheckSubType(targetIce, "Barrier")) {
        value += 5;
      }
      
      if (value > bestValue) {
        bestValue = value;
        bestIndex = i;
      }
    }
    
    //Only install if we found a reasonable target
    if (bestValue >= 2) {
      return bestIndex;
    }
    
    return -1; //don't install if no good target
  },
  
  AIWorthKeeping: function (installedRunnerCards, spareMU) {
    //Worth keeping if there's non-barrier rezzed ice to target
    var installedCorpCards = InstalledCards(corp);
    for (var i = 0; i < installedCorpCards.length; i++) {
      var card = installedCorpCards[i];
      if (CheckCardType(card, ["ice"]) && (card.rezzed || card.knownToRunner)) {
        if (!CheckSubType(card, "Barrier")) {
          return true;
        }
      }
    }
    //Also worth keeping if there's any ice at all (might be non-barrier)
    for (var i = 0; i < installedCorpCards.length; i++) {
      if (CheckCardType(installedCorpCards[i], ["ice"])) {
        return true;
      }
    }
    return false;
  },
  
  //AI: Calculate bypass cost for run calculator
  AIBypassCost: function (iceCard) {
    if (this.host == iceCard && !CheckSubType(iceCard, "Barrier")) {
      var numSubs = iceCard.subroutines ? iceCard.subroutines.length : 0;
      return numSubs;
    }
    return Infinity; //can't bypass
  },
  
  //AI: Provide encounter options for run calculator
  AIEncounterOptions: function (iceIdx, iceAI) {
    //Only works on host ice
    if (this.host != iceAI.ice) return [];
    //Only works on non-barriers
    if (CheckSubType(iceAI.ice, "Barrier")) return [];
    
    var numSubs = iceAI.ice.subroutines ? iceAI.ice.subroutines.length : 0;
    if (numSubs == 0) return [];
    
    //Include option to bypass by paying credits
    var effects = [{ credits: -numSubs, source: this }];
    var persistents = [{ use: this, target: iceAI.ice, iceIdx: iceIdx, action: "bypass" }];
    return [{ effects: effects, persistents: persistents }];
  },
};