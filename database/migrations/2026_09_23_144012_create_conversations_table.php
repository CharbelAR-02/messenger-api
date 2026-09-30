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
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['private', 'group']);
            $table->string('name')->nullable(); // null for private, set for group
            $table->string('avatar_path')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete(); // nullondelete instead of cascadeondelete() so if a creator of a group deletes his account , the conversation and its message history will not vanish
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
