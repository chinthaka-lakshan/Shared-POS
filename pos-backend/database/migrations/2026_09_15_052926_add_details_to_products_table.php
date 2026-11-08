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
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('unit')->default('Pieces')->after('description');
            $table->decimal('cost_price', 10, 2)->default(0)->after('unit');
            $table->decimal('selling_price', 10, 2)->default(0)->after('cost_price');
            $table->decimal('stock_quantity', 10, 3)->default(0)->after('selling_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'unit', 'cost_price', 'selling_price', 'stock_quantity']);
        });
    }
};
