<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create products table if it doesn't exist
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('product_name');
                $table->string('product_code')->unique();
                $table->string('q_code')->nullable();
                $table->foreignId('manufacturer_id')->constrained('manufacturers');
                $table->decimal('price', 10, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['manufacturer_id', 'is_active']);
                $table->index('q_code');
            });
        }

        // Seed some example products only if manufacturers exist
        $manufacturerIds = DB::table('manufacturers')->pluck('id')->toArray();

        $products = [
            // Advanced Solution products
            ['product_name' => 'PermeaDerm B', 'product_code' => 'PDB-001', 'q_code' => 'Q4162', 'manufacturer_id' => 2],
            ['product_name' => 'PermeaDerm Glove', 'product_code' => 'PDG-001', 'q_code' => 'Q4163', 'manufacturer_id' => 2],

            // ACZ products
            ['product_name' => 'Keramatrix', 'product_code' => 'KMX-001', 'q_code' => 'Q4165', 'manufacturer_id' => 1],
            ['product_name' => 'Kerasorb', 'product_code' => 'KSB-001', 'q_code' => 'Q4166', 'manufacturer_id' => 1],

            // Biowound products
            ['product_name' => 'Biowound Plus', 'product_code' => 'BWP-001', 'q_code' => 'Q4185', 'manufacturer_id' => 3],

            // Extremity Care products
            ['product_name' => 'Coll-e-Derm', 'product_code' => 'CED-001', 'q_code' => 'Q4193', 'manufacturer_id' => 4],

            // Centurion products
            ['product_name' => 'AmnioBand', 'product_code' => 'AMB-001', 'q_code' => 'Q4168', 'manufacturer_id' => 16],
            ['product_name' => 'Allopatch', 'product_code' => 'ALP-001', 'q_code' => 'Q4169', 'manufacturer_id' => 16],
        ];

        // Only insert products whose manufacturers exist
        $validProducts = array_filter($products, function($product) use ($manufacturerIds) {
            return in_array($product['manufacturer_id'], $manufacturerIds);
        });

        if (!empty($validProducts)) {
            DB::table('products')->insert($validProducts);
        }
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
