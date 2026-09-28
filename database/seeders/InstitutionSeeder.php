<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = [
            ['name' => 'АК-159/2', 'city' => 'Алматы', 'address' => 'г. Алматы'],
            ['name' => 'АК-159/3', 'city' => 'Алматы', 'address' => 'г. Алматы'],
            ['name' => 'ЛА-155/8', 'city' => 'Алматы', 'address' => 'г. Алматы'],
            ['name' => 'ЕЦ-166/1', 'city' => 'Алматы', 'address' => 'г. Алматы'],
            ['name' => 'УГ-157/4', 'city' => 'Алматы', 'address' => 'г. Алматы'],
        ];

        foreach ($institutions as $inst) {
            \App\Models\Institution::create($inst);
        }
    }
}
