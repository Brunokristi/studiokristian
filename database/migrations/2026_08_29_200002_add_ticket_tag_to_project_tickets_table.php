<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 20)->default('accent');
            $table->timestamps();
        });

        Schema::create('project_ticket_tag', function (Blueprint $table) {
            $table->foreignId('project_ticket_id')
                ->constrained('project_tickets')
                ->cascadeOnDelete();

            $table->foreignId('ticket_tag_id')
                ->constrained('ticket_tags')
                ->cascadeOnDelete();

            $table->primary([
                'project_ticket_id',
                'ticket_tag_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ticket_tag');
        Schema::dropIfExists('ticket_tags');
    }
};