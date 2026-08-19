<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice-exam callbacks identify a student by phone number alone, so the column has
     * to be normalised and unique before it can be used as an identifier.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('phone')->orderBy('id')
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'phone' => PhoneNumber::normalize($user->phone),
                ]);
            });

        // Blank out any duplicates left over so the unique index can be created.
        $duplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicates as $phone) {
            DB::table('users')
                ->where('phone', $phone)
                ->whereNotIn('id', [DB::table('users')->where('phone', $phone)->min('id')])
                ->update(['phone' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
