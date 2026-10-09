<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 180);
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['branch_id', 'sent_at']);
            $table->index(['sender_id', 'sent_at']);
        });

        Schema::create('school_message_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['school_message_id', 'recipient_id'], 'school_message_recipient_unique');
            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_message_recipients');
        Schema::dropIfExists('school_messages');
    }
};
