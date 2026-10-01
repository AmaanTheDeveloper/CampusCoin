<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banks')) {
            return;
        }

        if (! Schema::hasColumn('banks', 'reference_code')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->string('reference_code', 32)->nullable()->after('slug');
            });
        }

        $banks = DB::table('banks')->whereNull('reference_code')->orderBy('id')->get(['id']);
        foreach ($banks as $bank) {
            $code = 'BANK-REQ-'.strtoupper(Str::random(8));
            while (DB::table('banks')->where('reference_code', $code)->exists()) {
                $code = 'BANK-REQ-'.strtoupper(Str::random(8));
            }
            DB::table('banks')->where('id', $bank->id)->update(['reference_code' => $code]);
        }

        $hasUnique = collect(Schema::getIndexes('banks'))->contains(
            fn (array $index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['reference_code']
        );

        if (! $hasUnique) {
            Schema::table('banks', function (Blueprint $table) {
                $table->unique('reference_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('banks') || ! Schema::hasColumn('banks', 'reference_code')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
