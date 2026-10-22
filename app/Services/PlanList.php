<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Saves the landing page's plans (Admin → Landing page) - the same rows
 * owners pay for. Saved plans keep their id, so the shops and payments that
 * point at them are untouched; a plan left out is deleted and its shops move
 * to the default plan. Exactly one default (checked by the form request).
 */
class PlanList
{
    /** @param list<array<string, mixed>> $plans in order, each with `id` (null = new) */
    public function replace(array $plans): void
    {
        DB::transaction(function () use ($plans) {
            $kept = [];

            foreach (array_values($plans) as $position => $values) {
                $id = $values['id'] ?? null;
                unset($values['id']);
                $plan = $id ? Plan::lockForUpdate()->findOrFail($id) : new Plan;
                // Un-default first, so there's never a moment with two.
                if ($values['is_default'] && ! $plan->is_default) {
                    Plan::where('is_default', true)->update(['is_default' => false]);
                }
                $plan->fill([...$values, 'position' => $position])->save();
                $kept[] = $plan->id;
            }

            $default = Plan::where('is_default', true)->value('id');
            Plan::whereNotIn('id', $kept)->get()->each(function (Plan $plan) use ($default) {
                // One query, so each shop isn't logged - the plan's deletion is.
                Shop::where('plan_id', $plan->id)->update(['plan_id' => $default]);
                $plan->delete();
            });
        });
    }
}
