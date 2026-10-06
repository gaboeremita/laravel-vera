<?php

namespace App\Actions\Quests;

use App\Models\Fact;
use App\Models\Quest;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Support\Collection;

/**
 * Checks a quest definition against its shape and the world it belongs to:
 * every problem is reported with the path it sits at, so the editor can show
 * it on the field.
 */
class ValidateQuestDefinition
{
    public const ID_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const FLAG_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';

    private const START_MODES = ['auto', 'condition', 'offer'];

    private const OUTCOMES = ['ended', 'completed', 'failed', 'abandoned'];

    private const QUEST_STATES = ['offered', 'active', 'declined', 'abandoned'];

    /** Leaves about the turn with the giver, which only mean something in offerWhen. */
    public const OFFER_ONLY_LEAVES = ['messagesWith', 'giverIn', 'othersInTheZone'];

    private string $key = '';

    private bool $inOfferWhen = false;

    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var Collection<int, Region> */
    private Collection $regions;

    /** @var Collection<int, WorldResident> */
    private Collection $residents;

    /** @var array<int, int> */
    private array $itemIds;

    /** @var array<int, string> */
    private array $sentimentNames;

    /** @var array<int, int> */
    private array $factIds;

    /** @var Collection<string, Quest> */
    private Collection $otherQuests;

    /** @var array<string, array<int, string>> quest keys by campaign key */
    private array $campaignQuests;

    /** @var array<int, string> */
    private array $beatIds;

    /** @var array<int, string> */
    private array $questionIds;

    /** @var array<int, string> */
    private array $grantedFlags;

    /**
     * @param  mixed  $definition  the decoded definition, of any shape
     * @return array{errors: array<string, array<int, string>>, warnings: array<int, string>}
     */
    public function handle(World $world, string $key, mixed $definition, ?Quest $quest = null, ?User $user = null): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->key = $key;
        $this->load($world, $quest);

        if (! is_array($definition) || array_is_list($definition)) {
            $this->error('', 'The definition must be a JSON object.');

            return $this->result();
        }

        $beats = $this->list($definition, 'beats', required: true);
        $this->beatIds = collect($beats)->pluck('id')->filter(fn ($id) => is_string($id))->values()->all();
        $this->questionIds = collect($beats)->flatMap(fn ($beat) => is_array($beat) ? ($beat['questions'] ?? []) : [])->pluck('id')->filter(fn ($id) => is_string($id))->values()->all();
        $this->grantedFlags = collect($beats)->flatMap(fn ($beat) => is_array($beat) ? ($beat['grants'] ?? []) : [])->pluck('flag')->filter(fn ($flag) => is_string($flag))->values()->all();

        if (! is_string($definition['description'] ?? null)) {
            $this->error('description', 'Write a description.');
        }
        if (isset($definition['repeatable']) && ! is_bool($definition['repeatable'])) {
            $this->error('repeatable', 'Must be true or false.');
        }

        $this->start($definition['start'] ?? null);
        $this->requirements($key, $this->list($definition, 'requires'));
        $this->beats($beats);
        foreach (['complete', 'fail'] as $field) {
            if (($definition[$field] ?? null) !== null) {
                $this->condition($definition[$field], $field);
            }
        }
        $this->rubric($definition['rubric'] ?? null);
        if (($definition['reward'] ?? null) !== null) {
            $this->reward($definition['reward']);
        }

        if (! isset($this->errors['beats'])) {
            $this->beatCycles($beats);
        }
        if (! isset($this->errors['requires'])) {
            $this->questCycles($key, $definition);
        }
        if ($user !== null) {
            $this->toolWarnings($beats, $user);
            $this->giverToolWarning($definition['start'] ?? null, $user);
        }

        return $this->result();
    }

    private function load(World $world, ?Quest $quest): void
    {
        $this->regions = $world->regions()->get(['id', 'name', 'layout'])->keyBy('id');
        $this->residents = $world->residents()->with('assistant')->get()->keyBy('id');
        $this->itemIds = $world->items()->pluck('id')->all();
        $this->sentimentNames = $world->sentimentNames();
        $this->factIds = Fact::whereHas('holder', fn ($query) => $query->where('world_id', $world->id))->pluck('id')->all();
        $this->otherQuests = $world->quests()->when($quest !== null, fn ($query) => $query->whereKeyNot($quest->id))->get()->keyBy('key');
        $this->campaignQuests = $world->campaigns()->with('quests:id,campaign_id,key')->get()
            ->mapWithKeys(fn ($campaign) => [$campaign->key => $campaign->quests->pluck('key')->all()])
            ->all();
    }

    /**
     * @return array{errors: array<string, array<int, string>>, warnings: array<int, string>}
     */
    private function result(): array
    {
        return ['errors' => $this->errors, 'warnings' => array_values(array_unique($this->warnings))];
    }

    private function error(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }

    /**
     * @param  array<string, mixed>  $parent
     * @return array<int, mixed>
     */
    private function list(array $parent, string $field, bool $required = false, string $prefix = ''): array
    {
        $path = ltrim("{$prefix}.{$field}", '.');
        $value = $parent[$field] ?? null;
        if ($value === null && ! $required) {
            return [];
        }
        if (! is_array($value) || ! array_is_list($value)) {
            $this->error($path, 'Must be a list.');

            return [];
        }
        if ($required && $value === []) {
            $this->error($path, 'Add at least one.');
        }

        return $value;
    }

    private function start(mixed $start): void
    {
        if (! is_array($start) || ! in_array($start['mode'] ?? null, self::START_MODES, true)) {
            $this->error('start.mode', 'Choose how the quest starts: with the session, on a condition, or offered by a resident.');

            return;
        }
        if ($start['mode'] === 'condition') {
            isset($start['when']) ? $this->condition($start['when'], 'start.when') : $this->error('start.when', 'Choose the condition that starts the quest.');
        }
        if ($start['mode'] === 'offer') {
            $this->resident($start['giver'] ?? null, 'start.giver');
        }

        foreach (['offerWhen', 'offerQuestion'] as $field) {
            if (($start[$field] ?? null) !== null && $start['mode'] !== 'offer') {
                $this->error("start.{$field}", 'Only a quest that starts by offer can have an offer condition.');
            }
        }
        if ($start['mode'] !== 'offer') {
            return;
        }
        if (($start['offerWhen'] ?? null) !== null) {
            $this->inOfferWhen = true;
            $this->condition($start['offerWhen'], 'start.offerWhen');
            $this->inOfferWhen = false;
        }
        if (array_key_exists('offerQuestion', $start) && $start['offerQuestion'] !== null && (! is_string($start['offerQuestion']) || trim($start['offerQuestion']) === '')) {
            $this->error('start.offerQuestion', 'Write the question, or leave it out.');
        }
    }

    /**
     * @param  array<int, mixed>  $requirements
     */
    private function requirements(string $key, array $requirements): void
    {
        foreach ($requirements as $index => $requirement) {
            $path = "requires.{$index}";
            if (! is_array($requirement) || isset($requirement['quest']) === isset($requirement['campaign'])) {
                $this->error($path, 'Name either a quest or a campaign.');

                continue;
            }

            $tiers = [];
            if (isset($requirement['quest'])) {
                $required = $this->otherQuests->get($requirement['quest']);
                if ($required === null) {
                    $this->error("{$path}.quest", $requirement['quest'] === $key ? 'A quest can\'t require itself.' : "There is no quest with the key \"{$this->text($requirement['quest'])}\" in this world.");
                } else {
                    $tiers = $required->rubric()['tiers'] ?? [];
                }
            } elseif (! array_key_exists($requirement['campaign'], $this->campaignQuests)) {
                $this->error("{$path}.campaign", "There is no campaign with the key \"{$this->text($requirement['campaign'])}\" in this world.");
            }

            $this->outcome($requirement['outcome'] ?? null, "{$path}.outcome", $tiers, isset($requirement['campaign']));
        }
    }

    /**
     * @param  array<int, string>  $tiers  the named quest's tiers, when known
     */
    private function outcome(mixed $outcome, string $path, array $tiers, bool $campaign): void
    {
        if (is_string($outcome) && str_starts_with($outcome, 'tier:')) {
            $tier = substr($outcome, 5);
            if ($tier === '' || (! $campaign && $tiers !== [] && ! in_array($tier, $tiers, true))) {
                $this->error($path, "That quest has no tier called \"{$tier}\".");
            }

            return;
        }
        if (! in_array($outcome, self::OUTCOMES, true)) {
            $this->error($path, 'Choose an outcome: ended, completed, failed, abandoned, or a tier.');
        }
    }

    /**
     * @param  array<int, mixed>  $beats
     */
    private function beats(array $beats): void
    {
        $seenBeats = [];
        $seenQuestions = [];

        foreach ($beats as $index => $beat) {
            $path = "beats.{$index}";
            if (! is_array($beat)) {
                $this->error($path, 'Each beat must be an object.');

                continue;
            }

            $id = $beat['id'] ?? null;
            if (! is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1) {
                $this->error("{$path}.id", 'Use lowercase letters, digits and dashes.');
            } elseif (in_array($id, $seenBeats, true)) {
                $this->error("{$path}.id", "Another beat already uses \"{$id}\".");
            } else {
                $seenBeats[] = $id;
            }

            if (! is_string($beat['text'] ?? null) || trim($beat['text']) === '') {
                $this->error("{$path}.text", 'Write the text the player sees.');
            }
            if (isset($beat['hidden']) && ! is_bool($beat['hidden'])) {
                $this->error("{$path}.hidden", 'Must be true or false.');
            }

            foreach ($this->list($beat, 'requires', prefix: $path) as $requiredIndex => $required) {
                if (! in_array($required, $this->beatIds, true) || $required === $id) {
                    $this->error("{$path}.requires.{$requiredIndex}", $required === $id ? 'A beat can\'t require itself.' : "There is no beat \"{$this->text($required)}\" in this quest.");
                }
            }

            isset($beat['when']) ? $this->condition($beat['when'], "{$path}.when") : $this->error("{$path}.when", 'Choose what finishes this beat.');

            foreach ($this->list($beat, 'knowledge', prefix: $path) as $knowledgeIndex => $knowledge) {
                $this->resident($knowledge['resident'] ?? null, "{$path}.knowledge.{$knowledgeIndex}.resident");
                if (! is_string($knowledge['prose'] ?? null) || trim($knowledge['prose']) === '') {
                    $this->error("{$path}.knowledge.{$knowledgeIndex}.prose", 'Write what this resident knows.');
                }
            }

            foreach ($this->list($beat, 'grants', prefix: $path) as $grantIndex => $grant) {
                $this->resident($grant['resident'] ?? null, "{$path}.grants.{$grantIndex}.resident");
                if (! is_string($grant['flag'] ?? null) || preg_match(self::FLAG_PATTERN, $grant['flag']) !== 1) {
                    $this->error("{$path}.grants.{$grantIndex}.flag", 'Name the flag with letters and digits, starting with a letter.');
                }
            }

            foreach ($this->list($beat, 'questions', prefix: $path) as $questionIndex => $question) {
                $questionPath = "{$path}.questions.{$questionIndex}";
                $questionId = $question['id'] ?? null;
                if (! is_string($questionId) || preg_match(self::ID_PATTERN, $questionId) !== 1) {
                    $this->error("{$questionPath}.id", 'Use lowercase letters, digits and dashes.');
                } elseif (in_array($questionId, $seenQuestions, true)) {
                    $this->error("{$questionPath}.id", "Another question already uses \"{$questionId}\".");
                } else {
                    $seenQuestions[] = $questionId;
                }
                if (! is_string($question['text'] ?? null) || trim($question['text']) === '') {
                    $this->error("{$questionPath}.text", 'Write the question.');
                }
                $residents = $this->list($question, 'residents', required: true, prefix: $questionPath);
                foreach ($residents as $residentIndex => $residentId) {
                    $this->resident($residentId, "{$questionPath}.residents.{$residentIndex}");
                }
            }
        }
    }

    private function condition(mixed $node, string $path): void
    {
        if (! is_array($node) || count($node) !== 1 || array_is_list($node)) {
            $this->error($path, 'Each condition must be an object with exactly one key.');

            return;
        }

        $kind = array_key_first($node);
        $value = $node[$kind];

        if (in_array($kind, self::OFFER_ONLY_LEAVES, true) && ! $this->inOfferWhen) {
            $this->error($path, 'This condition only works in Offer when.');

            return;
        }
        if (in_array($kind, ['beat', 'question'], true) && $this->inOfferWhen) {
            $this->error($path, 'Offer when can\'t depend on this quest\'s own beats or questions.');

            return;
        }

        match ($kind) {
            'all', 'any' => $this->group($value, "{$path}.{$kind}"),
            'not' => $this->condition($value, "{$path}.not"),
            'enterRegion' => $this->region($value, "{$path}.enterRegion"),
            'enterZone' => $this->zone($value, "{$path}.enterZone"),
            'talkTo' => $this->resident($value, "{$path}.talkTo"),
            'use' => $this->activity($value, "{$path}.use"),
            'residentDid' => $this->residentActivity($value, "{$path}.residentDid"),
            'has' => $this->has($value, "{$path}.has"),
            'credits' => $this->atLeast($value, "{$path}.credits", 0),
            'knows' => $this->fact($value, "{$path}.knows"),
            'acknowledged' => $this->acknowledged($value, "{$path}.acknowledged"),
            'flag' => $this->flag($value, "{$path}.flag"),
            'question' => $this->known($value, $this->questionIds, "{$path}.question", 'question'),
            'beat' => $this->known($value, $this->beatIds, "{$path}.beat", 'beat'),
            'sentiment' => $this->sentiment($value, "{$path}.sentiment"),
            'questState' => $this->questState($value, "{$path}.questState"),
            'declinedTimes' => [$this->anyQuest($value['quest'] ?? null, "{$path}.declinedTimes.quest"), $this->atLeast($value, "{$path}.declinedTimes", 1)],
            'gaveTo' => $this->gaveTo($value, "{$path}.gaveTo"),
            'spentWith', 'messagesWith' => [$this->resident($value['resident'] ?? null, "{$path}.{$kind}.resident"), $this->atLeast($value, "{$path}.{$kind}", 1)],
            'giverIn' => $this->zone($value, "{$path}.giverIn"),
            'othersInTheZone' => $this->othersInTheZone($value, "{$path}.othersInTheZone"),
            default => $this->error($path, "\"{$kind}\" isn't a condition."),
        };
    }

    private function group(mixed $conditions, string $path): void
    {
        if (! is_array($conditions) || ! array_is_list($conditions) || $conditions === []) {
            $this->error($path, 'Add at least one condition to the group.');

            return;
        }
        foreach ($conditions as $index => $condition) {
            $this->condition($condition, "{$path}.{$index}");
        }
    }

    private function region(mixed $regionId, string $path): ?Region
    {
        $region = is_int($regionId) ? $this->regions->get($regionId) : null;
        if ($region === null) {
            $this->error($path, 'Choose a region of this world.');
        }

        return $region;
    }

    private function zone(mixed $value, string $path): void
    {
        $region = $this->region($value['region'] ?? null, "{$path}.region");
        if ($region !== null && ! collect($region->layout['zones'] ?? [])->contains('id', $value['zone'] ?? null)) {
            $this->error("{$path}.zone", "{$region->name} has no zone \"{$this->text($value['zone'] ?? '')}\".");
        }
    }

    private function activity(mixed $value, string $path): void
    {
        $region = $this->region($value['region'] ?? null, "{$path}.region");
        if ($region === null) {
            return;
        }
        $objectId = $value['object'] ?? null;
        if (! is_string($objectId) || $region->layoutObject($objectId) === null) {
            $this->error("{$path}.object", "{$region->name} has no object \"{$this->text($objectId ?? '')}\".");

            return;
        }
        if (! array_key_exists($value['activity'] ?? '', $region->objectActivities($objectId))) {
            $this->error("{$path}.activity", "That object has no activity \"{$this->text($value['activity'] ?? '')}\".");
        }
    }

    private function residentActivity(mixed $value, string $path): void
    {
        $this->resident($value['resident'] ?? null, "{$path}.resident");
        $this->activity($value, $path);
    }

    private function has(mixed $value, string $path): void
    {
        if (! is_int($value['item'] ?? null) || ! in_array($value['item'], $this->itemIds, true)) {
            $this->error("{$path}.item", 'Choose an item of this world.');
        }
        $this->atLeast($value, $path, 1);
    }

    private function atLeast(mixed $value, string $path, int $minimum): void
    {
        if (! is_int($value['atLeast'] ?? null) || $value['atLeast'] < $minimum) {
            $this->error("{$path}.atLeast", "Must be a whole number of at least {$minimum}.");
        }
    }

    private function sentiment(mixed $value, string $path): void
    {
        $this->resident($value['resident'] ?? null, "{$path}.resident");
        if (! in_array($value['kind'] ?? null, $this->sentimentNames, true)) {
            $this->error("{$path}.kind", 'Choose one of the world\'s sentiments.');
        }

        $bounds = array_intersect_key(is_array($value) ? $value : [], ['atLeast' => true, 'atMost' => true]);
        if ($bounds === []) {
            $this->error($path, 'Give at least one bound.');

            return;
        }
        foreach ($bounds as $bound => $number) {
            if ((! is_int($number) && ! is_float($number)) || $number < -10 || $number > 10) {
                $this->error("{$path}.{$bound}", 'Must be from -10 to 10.');
            }
        }
        if (is_numeric($bounds['atLeast'] ?? null) && is_numeric($bounds['atMost'] ?? null) && $bounds['atLeast'] > $bounds['atMost']) {
            $this->error("{$path}.atMost", 'Must be at least the lower bound.');
        }
    }

    private function questState(mixed $value, string $path): void
    {
        $this->anyQuest($value['quest'] ?? null, "{$path}.quest");
        if (! in_array($value['state'] ?? null, self::QUEST_STATES, true)) {
            $this->error("{$path}.state", 'Choose offered, active, declined or abandoned.');
        }
    }

    /**
     * A quest of this world, this one included.
     */
    private function anyQuest(mixed $questKey, string $path): void
    {
        if (! is_string($questKey) || ($questKey !== $this->key && ! $this->otherQuests->has($questKey))) {
            $this->error($path, 'Choose a quest of this world.');
        }
    }

    private function gaveTo(mixed $value, string $path): void
    {
        $this->resident($value['resident'] ?? null, "{$path}.resident");
        if (! is_int($value['item'] ?? null) || ! in_array($value['item'], $this->itemIds, true)) {
            $this->error("{$path}.item", 'Choose an item of this world.');
        }
        $this->atLeast($value, $path, 1);
    }

    private function othersInTheZone(mixed $value, string $path): void
    {
        $nobody = is_array($value) && ($value['nobody'] ?? null) === true;
        if (! is_array($value) || $nobody === array_key_exists('resident', $value)) {
            $this->error($path, 'Choose a named resident, or no one besides the giver.');

            return;
        }
        if (! $nobody) {
            $this->resident($value['resident'], "{$path}.resident");
        }
    }

    private function fact(mixed $factId, string $path): void
    {
        if (! is_int($factId) || ! in_array($factId, $this->factIds, true)) {
            $this->error($path, 'Choose a fact of this world.');
        }
    }

    private function acknowledged(mixed $value, string $path): void
    {
        $this->fact($value['fact'] ?? null, "{$path}.fact");
        $this->resident($value['resident'] ?? null, "{$path}.resident");
    }

    private function flag(mixed $value, string $path): void
    {
        if (is_string($value)) {
            if (preg_match(self::FLAG_PATTERN, $value) !== 1) {
                $this->error($path, 'Name the flag with letters and digits, starting with a letter.');
            } elseif (! in_array($value, $this->grantedFlags, true)) {
                $this->warnings[] = "No beat lets a resident grant the flag \"{$value}\"; only creator mode can set it.";
            }

            return;
        }

        $quest = is_array($value) && is_string($value['quest'] ?? null) ? $this->otherQuests->get($value['quest']) : null;
        if ($quest === null) {
            $this->error("{$path}.quest", 'Choose another quest of this world.');
        }
        if (! is_string($value['name'] ?? null) || preg_match(self::FLAG_PATTERN, $value['name']) !== 1) {
            $this->error("{$path}.name", 'Name the flag with letters and digits, starting with a letter.');
        }
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function known(mixed $id, array $ids, string $path, string $what): void
    {
        if (! is_string($id) || ! in_array($id, $ids, true)) {
            $this->error($path, "There is no {$what} \"{$this->text($id ?? '')}\" in this quest.");
        }
    }

    private function resident(mixed $residentId, string $path): void
    {
        if (! is_int($residentId) || ! $this->residents->has($residentId)) {
            $this->error($path, 'Choose a resident of this world.');
        }
    }

    private function reward(mixed $reward): void
    {
        if (! is_array($reward) || array_is_list($reward)) {
            $this->error('reward', 'Must be an object with who gives it and what they are told.');

            return;
        }
        if (! is_string($reward['prose'] ?? null) || trim($reward['prose']) === '') {
            $this->error('reward.prose', 'Write what the giver is told to do once the quest is complete.');
        }

        $from = $reward['from'] ?? null;
        if (! is_array($from) || isset($from['resident']) === isset($from['object'])) {
            $this->error('reward.from', 'Choose either a resident or an object to give the reward.');

            return;
        }
        if (isset($from['resident'])) {
            $this->resident($from['resident'], 'reward.from.resident');

            return;
        }

        $region = $this->region($from['object']['region'] ?? null, 'reward.from.object.region');
        $objectId = $from['object']['object'] ?? null;
        if ($region !== null && (! is_string($objectId) || $region->layoutObject($objectId) === null)) {
            $this->error('reward.from.object.object', "{$region->name} has no object \"{$this->text($objectId ?? '')}\".");
        }
    }

    private function rubric(mixed $rubric): void
    {
        if (! is_array($rubric)) {
            $this->error('rubric', 'Write the rubric the ending is judged by.');

            return;
        }
        if (isset($rubric['guidance']) && ! is_string($rubric['guidance'])) {
            $this->error('rubric.guidance', 'Must be text.');
        }

        $names = [];
        foreach ($this->list($rubric, 'dimensions', required: true, prefix: 'rubric') as $index => $dimension) {
            $name = $dimension['name'] ?? null;
            if (! is_string($name) || trim($name) === '') {
                $this->error("rubric.dimensions.{$index}.name", 'Name the dimension.');
            } elseif (in_array($name, $names, true)) {
                $this->error("rubric.dimensions.{$index}.name", "Another dimension is already called \"{$name}\".");
            } else {
                $names[] = $name;
            }
        }

        $tiers = $this->list($rubric, 'tiers', prefix: 'rubric');
        foreach ($tiers as $index => $tier) {
            if (! is_string($tier) || trim($tier) === '') {
                $this->error("rubric.tiers.{$index}", 'Name the tier.');
            } elseif (array_search($tier, $tiers, true) !== $index) {
                $this->error("rubric.tiers.{$index}", "\"{$tier}\" is listed twice.");
            }
        }
    }

    /**
     * @param  array<int, mixed>  $beats
     */
    private function beatCycles(array $beats): void
    {
        $graph = collect($beats)->filter(fn ($beat) => is_array($beat) && is_string($beat['id'] ?? null))
            ->mapWithKeys(fn (array $beat) => [$beat['id'] => array_values(array_filter($beat['requires'] ?? [], 'is_string'))])
            ->all();

        $cycle = $this->findCycle($graph);
        if ($cycle !== null) {
            $this->error('beats', 'These beats require each other in a circle: '.implode(' → ', $cycle).'.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function questCycles(string $key, array $definition): void
    {
        $edges = fn (array $requirements): array => collect($requirements)
            ->flatMap(fn ($requirement) => match (true) {
                isset($requirement['quest']) => [$requirement['quest']],
                isset($requirement['campaign']) => $this->campaignQuests[$requirement['campaign']] ?? [],
                default => [],
            })
            ->values()
            ->all();

        $graph = $this->otherQuests->map(fn (Quest $quest) => $edges($quest->requirements()))->all();
        $graph[$key] = $edges($definition['requires'] ?? []);

        $cycle = $this->findCycle($graph, $key);
        if ($cycle !== null) {
            $this->error('requires', 'These quests require each other in a circle: '.implode(' → ', $cycle).'.');
        }
    }

    /**
     * A depth-first search for a cycle, from one node or from all of them.
     *
     * @param  array<string, array<int, string>>  $graph
     * @return ?array<int, string> the nodes of the first cycle found, closing on its first node
     */
    private function findCycle(array $graph, ?string $from = null): ?array
    {
        $done = [];
        $visit = function (string $node, array $trail) use (&$visit, &$done, $graph): ?array {
            if (in_array($node, $trail, true)) {
                return [...array_slice($trail, array_search($node, $trail, true)), $node];
            }
            if (isset($done[$node])) {
                return null;
            }
            foreach ($graph[$node] ?? [] as $next) {
                $cycle = $visit($next, [...$trail, $node]);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
            $done[$node] = true;

            return null;
        };

        foreach ($from !== null ? [$from] : array_keys($graph) as $start) {
            $cycle = $visit((string) $start, []);
            if ($cycle !== null) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $beats
     */
    private function toolWarnings(array $beats, User $user): void
    {
        collect($beats)->filter(fn ($beat) => is_array($beat))
            ->flatMap(fn (array $beat) => [
                ...collect($beat['grants'] ?? [])->pluck('resident'),
                ...collect($beat['questions'] ?? [])->pluck('residents')->flatten(),
            ])
            ->filter(fn ($id) => is_int($id))
            ->unique()
            ->map(fn (int $id) => $this->residents->get($id))
            ->filter()
            ->reject(fn (WorldResident $resident) => $resident->canCallToolsFor($user))
            ->each(function (WorldResident $resident): void {
                $this->warnings[] = "{$resident->assistant->name}'s model can't call tools, so they can't grant flags or signal questions until it can.";
            });
    }

    /**
     * The giver weighs offer conditions and signals the offerQuestion with
     * tools, so a giver whose model can't call them never checks anything.
     */
    private function giverToolWarning(mixed $start, User $user): void
    {
        if (! is_array($start) || ($start['mode'] ?? null) !== 'offer' || (($start['offerWhen'] ?? null) === null && ($start['offerQuestion'] ?? null) === null)) {
            return;
        }

        $giver = is_int($start['giver'] ?? null) ? $this->residents->get($start['giver']) : null;
        if ($giver !== null && ! $giver->canCallToolsFor($user)) {
            $this->warnings[] = "{$giver->assistant->name}'s model can't call tools, so they can't check what the quest asks before offering it.";
        }
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
