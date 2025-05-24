<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SupabaseRLSService
{
    public function enableRLS(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
    }

    public function createPolicy(string $table, string $policyName, string $operation, string $condition): void
    {
        $sql = "CREATE POLICY \"{$policyName}\" ON {$table} FOR {$operation} USING ({$condition})";
        DB::statement($sql);
    }

    public function setupBasicRLS(): void
    {
        $tables = [
            'orders',
            'order_items',
            'order_items_history',
            'order_items_history_items',
            'order_items_history_items_history',
        ];

        foreach ($tables as $table) {
            $this->enableRLS($table);
        }
    }
}
