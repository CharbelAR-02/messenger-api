<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
        $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        $table->enum('role', ['admin', 'member'])->default('member');
        //$table->foreignUuid('last_read_message_id')->nullable()
             // ->constrained('messages')->nullOnDelete();
        $table->timestamp('muted_at')->nullable();
        $table->timestamp('pinned_at')->nullable();
        $table->timestamp('archived_at')->nullable();
        $table->timestamp('joined_at')->useCurrent();
        $table->timestamp('left_at')->nullable(); // if a user leaves we dont delete his participant row so other users in the same group can still see his messages 
        $table->timestamps();

        $table->unique(['conversation_id', 'user_id']);//stops a user from being added to the same conversation twice.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
