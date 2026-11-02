<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('share_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained()->cascadeOnDelete();
            $table->string('audience_type')->nullable();
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->string('ability');
            $table->timestamps();
            $table->unique(['share_id', 'audience_type', 'audience_id'], 'share_audience_unique');
            $table->index(['audience_type', 'audience_id', 'share_id'], 'share_audience_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_audiences');
    }
};
