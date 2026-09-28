<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Вода и напитки',        'name_kk' => 'Су және сусындар',              'icon' => 'water_drop',        'color' => '#2196F3', 'sort_order' => 1],
            ['name' => 'Табачные изделия',       'name_kk' => 'Темекі өнімдері',               'icon' => 'smoking_rooms',     'color' => '#9E9E9E', 'sort_order' => 2],
            ['name' => 'Готовая еда',            'name_kk' => 'Дайын тамақ',                   'icon' => 'restaurant',        'color' => '#FF6B35', 'sort_order' => 3],
            ['name' => 'Кафе и рестораны',       'name_kk' => 'Кафе және мейрамханалар',       'icon' => 'local_cafe',        'color' => '#795548', 'sort_order' => 4],
            ['name' => 'Фастфуд',                'name_kk' => 'Фастфуд',                       'icon' => 'fastfood',          'color' => '#FF9800', 'sort_order' => 5],
            ['name' => 'Кондитерские изделия',   'name_kk' => 'Кондитер өнімдері',             'icon' => 'cake',              'color' => '#E91E63', 'sort_order' => 6],
            ['name' => 'Бакалея',                'name_kk' => 'Бакалея',                       'icon' => 'shopping_basket',   'color' => '#4CAF50', 'sort_order' => 7],
            ['name' => 'Овощи, фрукты',          'name_kk' => 'Көкөністер, жемістер',          'icon' => 'eco',               'color' => '#8BC34A', 'sort_order' => 8],
            ['name' => 'Гигиена и косметика',    'name_kk' => 'Гигиена және косметика',        'icon' => 'soap',              'color' => '#9C27B0', 'sort_order' => 9],
            ['name' => 'Канцтовары',             'name_kk' => 'Кеңсе тауарлары',              'icon' => 'edit',              'color' => '#3F51B5', 'sort_order' => 10],
            ['name' => 'Хозяйственные товары',   'name_kk' => 'Шаруашылық тауарлары',         'icon' => 'cleaning_services', 'color' => '#607D8B', 'sort_order' => 11],
            ['name' => 'Одежда',                 'name_kk' => 'Киім',                          'icon' => 'checkroom',         'color' => '#673AB7', 'sort_order' => 12],
            ['name' => 'Газеты и журналы',       'name_kk' => 'Газеттер және журналдар',       'icon' => 'newspaper',         'color' => '#00BCD4', 'sort_order' => 13],
            ['name' => 'Телефонные карты',       'name_kk' => 'Телефон карталары',             'icon' => 'sim_card',          'color' => '#009688', 'sort_order' => 14],
        ];

        foreach ($categories as $cat) {
            \App\Models\Category::create($cat);
        }
    }
}
