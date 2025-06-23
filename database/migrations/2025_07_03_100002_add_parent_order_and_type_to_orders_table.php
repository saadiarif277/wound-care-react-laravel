<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'parent_order_id')) {
                $table->unsignedBigInteger('parent_order_id')->nullable()->after('episode_id');
                $table->index('parent_order_id');
                $table->foreign('parent_order_id')
                      ->references('id')
                      ->on('orders')
                      ->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'type')) {
                $table->enum('type', ['initial', 'follow_up'])
                      ->default('initial')
                      ->after('parent_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $table->dropForeign(['parent_order_id']);
                $table->dropIndex(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            }
        });
    }
};