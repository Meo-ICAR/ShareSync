<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlPath = storage_path('app/private/document_types.sql');

        if (File::exists($sqlPath)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared(File::get($sqlPath));
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
