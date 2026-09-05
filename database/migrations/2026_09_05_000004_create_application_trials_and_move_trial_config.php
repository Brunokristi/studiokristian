<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('trial_enabled')->default(false)->after('is_saas');
            $table->unsignedInteger('trial_duration_days')->default(30)->after('trial_enabled');
            $table->unsignedInteger('trial_credits')->default(100)->after('trial_duration_days');
        });

        Schema::create('company_trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->unsignedInteger('credits_allowance')->default(0);
            $table->unsignedInteger('credits_used')->default(0);
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'project_id']);
            $table->index(['project_id', 'status']);
        });

        Schema::table('saas_plans', function (Blueprint $table) {
            $table->dropColumn([
                'trial_enabled',
                'trial_credits',
                'trial_validity_days',
            ]);
        });

        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'trial_started_at',
                'trial_ends_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable()->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
        });

        Schema::table('saas_plans', function (Blueprint $table) {
            $table->boolean('trial_enabled')->default(false)->after('active');
            $table->unsignedInteger('trial_credits')->default(0)->after('trial_enabled');
            $table->unsignedInteger('trial_validity_days')->default(0)->after('trial_credits');
        });

        Schema::dropIfExists('company_trials');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'trial_enabled',
                'trial_duration_days',
                'trial_credits',
            ]);
        });
    }
};
