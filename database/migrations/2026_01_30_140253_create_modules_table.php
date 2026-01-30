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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('key', 100)->unique();
            $table->string('title', 200);
            $table->string('route_name', 200)->nullable();
            $table->string('href', 500)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('section', 100)->default('General');
            $table->integer('ordering')->default(0);
            $table->char('active', 1)->default('S');
            $table->text('description')->nullable();
            $table->json('permissions_required')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('parent_id')
                ->references('id')
                ->on('modules')
                ->onDelete('cascade');

            // Indexes
            $table->index(['active', 'section', 'ordering']);
            $table->index(['parent_id', 'ordering']);
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
