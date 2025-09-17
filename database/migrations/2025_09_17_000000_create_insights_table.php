<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		if (Schema::hasTable('insights')) {
			return;
		}

		Schema::create('insights', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
			$table->string('word');
			$table->string('topic');
			$table->string('tone')->nullable();
			$table->text('content');
			$table->timestamps();
			$table->index(['user_id', 'created_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('insights');
	}
};


