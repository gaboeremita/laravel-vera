# Quickstart: Items, Inventory and Credits

## Automated

```bash
php artisan test --compact tests/Feature/Api/ItemControllerTest.php tests/Feature/Api/StartingInventoryControllerTest.php tests/Feature/Api/ActivityTermsControllerTest.php tests/Feature/Api/InventoryPlayControllerTest.php tests/Feature/TransferInventoryTest.php tests/Feature/InventoryWorldToolsTest.php tests/Feature/NarrateTest.php
```

```bash
node --test tests/Unit/InventoryChanges.test.js
```

The narrator and resident tools are tested with the LLM faked, as the existing agent loop tests do.

## Manual (in a world with at least one region and one resident on a tool-calling model)

1. **Items and starting stock** (US1): in the world's configuration, add "Bread" (base price 2) and "Iron key". Give the player 50 credits and 1 bread; give a resident unlimited bread, marked for sale, and 100 credits. Start a new session: the HUD shows 50 credits, the inventory shows 1 bread.
2. **Giving** (US2): talk to the resident, give them 10 credits. The HUD shows 40, a toast shows "-10 credits", and their reply mentions the credits.
3. **Vendor and requests** (US3): the conversation panel lists bread for sale at 2. Ask to buy 3 bread; when they ask for credits, decline once (they react), then accept. The bread arrives and the credit history lists the payment.
4. **Taking** (US4): give an object 2 takeable potions in the region's configuration. In play, its inspect card offers the potions; take three times; the third is no longer offered. A new session offers 2 again.
5. **Activity terms** (US5): require "Iron key" and 5 credits for an object's activity that gives 1 bread. Try without the key (refused, key named), then with the key and 3 credits (refused, cost named), then with both (starts; key kept; -5 credits, +1 bread).
6. **Plain-language terms** (US5): give an activity the requirement "opens for anyone who shows proof of Guild membership". Try it with nothing (narrated refusal), then holding an item called "Guild signet" with the attempt "I show the signet" (narrated success).
7. **Items that hold or need something** (US6): define a letter with contents and a lockbox with a use requirement and released credits. Examine the letter; try the lockbox with a wrong code, then the right one.
8. **Refused model** (FR-002a): put a resident on a model without tool support and try to give them starting credits; the configuration refuses and says why.
