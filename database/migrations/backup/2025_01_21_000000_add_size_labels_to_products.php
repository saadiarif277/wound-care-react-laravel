<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('msc_products', function (Blueprint $table) {
            // Add new columns for size labels
            $table->json('size_options')->nullable()->comment('Available size labels like ["2x2", "2x4", "4x4"]');
            $table->json('size_pricing')->nullable()->comment('Price per size like {"2x2": 4, "2x4": 8, "4x4": 16}');
            $table->string('size_unit')->default('in')->comment('Unit for sizes: in (inches) or cm (centimeters)');
        });

        // Migrate existing numeric sizes to size labels
        $products = DB::table('msc_products')->whereNotNull('available_sizes')->get();

        foreach ($products as $product) {
            $sizes = json_decode($product->available_sizes, true);
            if (!empty($sizes) && is_array($sizes)) {
                $sizeOptions = [];
                $sizePricing = [];

                foreach ($sizes as $size) {
                    if (is_numeric($size)) {
                        // Convert square cm to size labels
                        $sideLength = sqrt($size);
                        $label = $this->generateSizeLabel($sideLength);
                        $sizeOptions[] = $label;
                        $sizePricing[$label] = $size; // Store the original square cm value
                    }
                }

                DB::table('msc_products')
                    ->where('id', $product->id)
                    ->update([
                        'size_options' => json_encode($sizeOptions),
                        'size_pricing' => json_encode($sizePricing),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msc_products', function (Blueprint $table) {
            $table->dropColumn(['size_options', 'size_pricing', 'size_unit']);
        });
    }

    /**
     * Generate a size label from side length in cm
     */
    private function generateSizeLabel($sideLength): string
    {
        // Convert to inches for display (1 inch = 2.54 cm)
        $inches = $sideLength / 2.54;

        // Common wound care sizes
        $standardSizes = [
            1 => "1x1",
            2 => "2x2",
            3 => "3x3",
            4 => "4x4",
            5 => "5x5",
            6 => "6x6",
            8 => "8x8",
            10 => "10x10",
            12 => "12x12"
        ];

        // Find closest standard size
        $closest = 1;
        foreach ($standardSizes as $size => $label) {
            if (abs($inches - $size) < abs($inches - $closest)) {
                $closest = $size;
            }
        }

        return $standardSizes[$closest];
    }
};
