<?php

use App\Support\LandingPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yearly subscription plans: the landing page's pricing cards and the
        // plans owners pay for are the same rows (Admin → Landing page). The
        // default plan is given to every new shop, with its own free trial.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            // {currency code: "2999"} - the yearly price as typed, per currency.
            $table->json('prices');
            $table->json('features')->nullable();
            // How the landing page's card looks.
            $table->string('note', 120)->nullable();
            $table->string('badge', 40)->nullable();
            $table->boolean('highlighted')->default(false);
            $table->string('cta_label', 30)->default('Start Free');
            $table->string('period', 30)->nullable();
            $table->string('billing_note', 60)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(30);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('lost_reason')->constrained()->nullOnDelete();
            // Paid up to the end of this day (null = never paid: on the free trial).
            $table->timestamp('subscription_ends_at')->nullable()->after('plan_id');
        });

        // The plans the landing page shows now - the admin's saved pricing,
        // moved word for word (else the default content) - the first as the
        // default. Production can't run seeders.
        $saved = json_decode((string) DB::table('settings')->where('key', 'landing_page')->value('value'), true);
        $plans = is_array($saved['plans'] ?? null) && $saved['plans'] !== [] ? $saved['plans'] : LandingPage::defaults()['plans'];
        $now = now();

        foreach (array_values($plans) as $i => $plan) {
            DB::table('plans')->insert([
                'name' => mb_substr((string) ($plan['name'] ?? 'Plan '.($i + 1)), 0, 60),
                'description' => $plan['description'] ?? null,
                'prices' => json_encode((object) ($plan['prices'] ?? [])),
                'features' => json_encode(array_values($plan['features'] ?? [])),
                'note' => $plan['note'] ?? null,
                'badge' => $plan['badge'] ?? null,
                'highlighted' => (bool) ($plan['highlighted'] ?? false),
                'cta_label' => $plan['cta_label'] ?? 'Start Free',
                'period' => $plan['period'] ?? null,
                'billing_note' => $plan['billing_note'] ?? null,
                'trial_days' => (int) config('loyalty.trial_days', 30),
                'is_default' => $i === 0,
                'is_active' => true,
                'position' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Every existing shop starts on the default plan (its trial end is unchanged).
        DB::table('shops')->whereNull('plan_id')->update([
            'plan_id' => DB::table('plans')->where('is_default', true)->value('id'),
        ]);

        // The plans now live only in the table: out of the landing page setting.
        if (is_array($saved) && array_key_exists('plans', $saved)) {
            unset($saved['plans']);
            DB::table('settings')->where('key', 'landing_page')->update(['value' => json_encode($saved), 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Back into the landing page setting (only if it has none: never over the admin's own).
        $saved = json_decode((string) DB::table('settings')->where('key', 'landing_page')->value('value'), true);
        if (is_array($saved) && ! array_key_exists('plans', $saved)) {
            $saved['plans'] = DB::table('plans')->orderBy('position')->orderBy('id')->get()->map(fn ($plan) => [
                'name' => $plan->name,
                'description' => $plan->description,
                'note' => $plan->note,
                'badge' => $plan->badge,
                'highlighted' => (bool) $plan->highlighted,
                'cta_label' => $plan->cta_label,
                'period' => $plan->period,
                'billing_note' => $plan->billing_note,
                'prices' => json_decode($plan->prices, true),
                'features' => json_decode((string) $plan->features, true) ?? [],
            ])->all();
            DB::table('settings')->where('key', 'landing_page')->update(['value' => json_encode($saved)]);
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('subscription_ends_at');
        });

        Schema::dropIfExists('plans');
    }
};
