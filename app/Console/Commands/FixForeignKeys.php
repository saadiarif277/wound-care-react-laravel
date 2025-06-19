<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixForeignKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:foreign-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix duplicate foreign key constraints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing duplicate foreign key constraints...');
        
        // List of foreign keys to fix
        $foreignKeys = [
            [
                'table' => 'product_requests',
                'column' => 'manufacturer_sent_by',
                'constraint' => 'product_requests_manufacturer_sent_by_foreign',
                'references' => 'users',
                'on' => 'id',
                'onDelete' => 'set null'
            ],
            [
                'table' => 'product_requests',
                'column' => 'ivr_bypassed_by',
                'constraint' => 'product_requests_ivr_bypassed_by_foreign',
                'references' => 'users',
                'on' => 'id',
                'onDelete' => 'set null'
            ]
        ];
        
        foreach ($foreignKeys as $fk) {
            $this->info("Checking {$fk['constraint']}...");
            
            // Check if column exists
            if (!Schema::hasColumn($fk['table'], $fk['column'])) {
                $this->warn("Column {$fk['column']} does not exist in {$fk['table']}. Skipping.");
                continue;
            }
            
            // Drop the foreign key if it exists
            try {
                DB::statement("ALTER TABLE {$fk['table']} DROP FOREIGN KEY {$fk['constraint']}");
                $this->info("Dropped existing foreign key: {$fk['constraint']}");
            } catch (\Exception $e) {
                $this->info("Foreign key {$fk['constraint']} doesn't exist (OK)");
            }
            
            // Check if foreign key already exists using information schema
            $exists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$fk['table'], $fk['constraint']]);
            
            if ($exists[0]->count > 0) {
                $this->warn("Foreign key {$fk['constraint']} already exists. Skipping recreation.");
                continue;
            }
            
            // Add the foreign key
            try {
                $onDeleteClause = $fk['onDelete'] === 'set null' ? 'ON DELETE SET NULL' : 'ON DELETE CASCADE';
                DB::statement("
                    ALTER TABLE {$fk['table']} 
                    ADD CONSTRAINT {$fk['constraint']} 
                    FOREIGN KEY ({$fk['column']}) 
                    REFERENCES {$fk['references']}({$fk['on']}) 
                    {$onDeleteClause}
                ");
                $this->info("Created foreign key: {$fk['constraint']}");
            } catch (\Exception $e) {
                $this->error("Failed to create foreign key {$fk['constraint']}: " . $e->getMessage());
            }
        }
        
        $this->info('Foreign key fix completed!');
        $this->info('Now run: php artisan migrate');
        
        return Command::SUCCESS;
    }
}