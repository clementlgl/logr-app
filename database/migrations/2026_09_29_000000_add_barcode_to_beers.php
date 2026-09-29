<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beers', function (Blueprint $table) {
            $table->string('barcode', 32)->nullable()->after('pub_uuid');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('beers', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
