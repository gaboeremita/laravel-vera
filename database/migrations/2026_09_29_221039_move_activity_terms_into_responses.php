<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->json('responses')->nullable();
        });

        DB::table('activity_terms')->orderBy('id')->each(function (object $terms): void {
            DB::table('activity_terms')->where('id', $terms->id)->update(['responses' => json_encode($this->responsesFor($terms))]);
        });

        Schema::table('activity_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('required_item_id');
            $table->dropConstrainedForeignId('reveals_fact_id');
            $table->dropColumn(['consumes_required', 'cost', 'gives_credits', 'gives_items', 'requirement', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_terms', function (Blueprint $table) {
            $table->foreignId('required_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->boolean('consumes_required')->default(false);
            $table->unsignedInteger('cost')->default(0);
            $table->unsignedInteger('gives_credits')->default(0);
            $table->json('gives_items')->nullable();
            $table->text('requirement')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('reveals_fact_id')->nullable()->constrained('facts')->nullOnDelete();
            $table->dropColumn('responses');
        });
    }

    /**
     * The one response that does what the terms' columns did: the required
     * item and the narrator's requirement become its condition, the rest its effects.
     *
     * @return array<int, array{condition: ?array, effects: array<int, array{type: string}>}>
     */
    private function responsesFor(object $terms): array
    {
        $givesItems = json_decode($terms->gives_items ?? '[]', true) ?: [];
        $leaves = [];
        $effects = [];

        if ($terms->required_item_id !== null) {
            $leaves[] = ['has' => ['item' => (int) $terms->required_item_id, 'atLeast' => 1]];
        }
        if (filled($terms->requirement) || filled($terms->outcome) || $terms->reveals_fact_id !== null) {
            $leaves[] = ['narrator' => ['requirement' => (string) $terms->requirement, 'outcome' => (string) $terms->outcome]];
        }
        if ($terms->cost > 0) {
            $effects[] = ['type' => 'takeCredits', 'amount' => (int) $terms->cost];
        }
        if ($terms->consumes_required && $terms->required_item_id !== null) {
            $effects[] = ['type' => 'takeItems', 'items' => [['itemId' => (int) $terms->required_item_id, 'quantity' => 1]]];
        }
        if ($terms->gives_credits > 0) {
            $effects[] = ['type' => 'giveCredits', 'amount' => (int) $terms->gives_credits];
        }
        if ($givesItems !== []) {
            $effects[] = ['type' => 'giveItems', 'items' => $givesItems];
        }
        if ($terms->reveals_fact_id !== null) {
            $effects[] = ['type' => 'revealFact', 'fact' => (int) $terms->reveals_fact_id];
        }

        if ($leaves === [] && $effects === []) {
            return [];
        }

        return [['condition' => match (count($leaves)) {
            0 => null,
            1 => $leaves[0],
            default => ['all' => $leaves],
        }, 'effects' => $effects]];
    }
};
