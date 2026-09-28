<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Вода и напитки (category_id=1)
            ['category_id' => 1, 'name' => 'Вода питьевая 1.5л',             'price' => 350,  'unit' => '1.5л',    'sort_order' => 1],
            ['category_id' => 1, 'name' => 'Чай чёрный «Принцесса Нури»',    'price' => 900,  'unit' => '25 пак.', 'sort_order' => 2],
            ['category_id' => 1, 'name' => 'Компот вишнёвый 1л',             'price' => 450,  'unit' => '1л',      'sort_order' => 3],
            ['category_id' => 1, 'name' => 'Сок яблочный 1л',                'price' => 550,  'unit' => '1л',      'sort_order' => 4],

            // Табачные изделия (category_id=2)
            ['category_id' => 2, 'name' => 'Сигареты «Marlboro» 1 пачка',    'price' => 800,  'unit' => '1 пач.',  'sort_order' => 1],
            ['category_id' => 2, 'name' => 'Зажигалка BIC',                  'price' => 400,  'unit' => '1 шт.',   'sort_order' => 2],

            // Готовая еда (category_id=3)
            ['category_id' => 3, 'name' => 'Филе цыплёнка с гречкой и овощами',        'price' => 2600, 'unit' => '350г', 'sort_order' => 1],
            ['category_id' => 3, 'name' => 'Тушенка из филе цыплёнка',                 'price' => 3200, 'unit' => '400г', 'sort_order' => 2],
            ['category_id' => 3, 'name' => 'Баранина с гречкой и овощами',             'price' => 2600, 'unit' => '350г', 'sort_order' => 3],
            ['category_id' => 3, 'name' => 'Баранина с перловкой и овощами',           'price' => 2600, 'unit' => '350г', 'sort_order' => 4],
            ['category_id' => 3, 'name' => 'Салат Свекольный',                         'price' => 1500, 'unit' => '500г', 'sort_order' => 5],
            ['category_id' => 3, 'name' => 'Охотничьи колбаски с красной фасолью',     'price' => 3200, 'unit' => '300г', 'sort_order' => 6],
            ['category_id' => 3, 'name' => 'Куурдак по-фермерски',                     'price' => 2800, 'unit' => '350г', 'sort_order' => 7],
            ['category_id' => 3, 'name' => 'Печень куриная с овощами',                 'price' => 2600, 'unit' => '300г', 'sort_order' => 8],
            ['category_id' => 3, 'name' => 'Суп Том Ям по-фермерски',                  'price' => 3000, 'unit' => '500г', 'sort_order' => 9],

            // Кондитерские изделия (category_id=6)
            ['category_id' => 6, 'name' => 'Печенье «Юбилейное» 400г',        'price' => 800,  'unit' => '400г', 'sort_order' => 1],
            ['category_id' => 6, 'name' => 'Карамель «Дюшес» 300г',           'price' => 650,  'unit' => '300г', 'sort_order' => 2],
            ['category_id' => 6, 'name' => 'Торт бисквитный 1кг',             'price' => 3500, 'unit' => '1кг',  'sort_order' => 3],
            ['category_id' => 6, 'name' => 'Вафли «Артек» 200г',              'price' => 450,  'unit' => '200г', 'sort_order' => 4],

            // Бакалея (category_id=7)
            ['category_id' => 7, 'name' => 'Сыр «Российский» 200г',           'price' => 1400, 'unit' => '200г', 'sort_order' => 1],
            ['category_id' => 7, 'name' => 'Масло сливочное 200г',            'price' => 1100, 'unit' => '200г', 'sort_order' => 2],
            ['category_id' => 7, 'name' => 'Хлеб белый нарезной',             'price' => 300,  'unit' => '450г', 'sort_order' => 3],
            ['category_id' => 7, 'name' => 'Молоко 3.2% 1л',                  'price' => 480,  'unit' => '1л',   'sort_order' => 4],

            // Овощи, фрукты (category_id=8)
            ['category_id' => 8, 'name' => 'Яблоки',                          'price' => 600,  'unit' => '1кг',  'sort_order' => 1],
            ['category_id' => 8, 'name' => 'Апельсины',                       'price' => 700,  'unit' => '1кг',  'sort_order' => 2],
            ['category_id' => 8, 'name' => 'Бананы',                          'price' => 550,  'unit' => '1кг',  'sort_order' => 3],
            ['category_id' => 8, 'name' => 'Виноград',                        'price' => 900,  'unit' => '1кг',  'sort_order' => 4],
            ['category_id' => 8, 'name' => 'Финики',                          'price' => 1200, 'unit' => '250г', 'sort_order' => 5],

            // Гигиена и косметика (category_id=9)
            ['category_id' => 9, 'name' => 'Беруши силиконовые STIL 4шт',     'price' => 4000, 'unit' => '4 шт.',  'sort_order' => 1],
            ['category_id' => 9, 'name' => 'Зубная паста «Colgate» 100мл',    'price' => 900,  'unit' => '100мл',  'sort_order' => 2],
            ['category_id' => 9, 'name' => 'Шампунь «Head&Shoulders» 200мл',  'price' => 1500, 'unit' => '200мл',  'sort_order' => 3],
            ['category_id' => 9, 'name' => 'Мыло туалетное 90г',              'price' => 300,  'unit' => '90г',    'sort_order' => 4],

            // Канцтовары (category_id=10)
            ['category_id' => 10, 'name' => 'Ручка шариковая синяя',          'price' => 100,  'unit' => '1 шт.',  'sort_order' => 1],
            ['category_id' => 10, 'name' => 'Тетрадь 96 листов',              'price' => 350,  'unit' => '1 шт.',  'sort_order' => 2],
            ['category_id' => 10, 'name' => 'Конверт почтовый С5 (5 шт.)',    'price' => 200,  'unit' => '5 шт.',  'sort_order' => 3],

            // Хозяйственные товары (category_id=11)
            ['category_id' => 11, 'name' => 'Стиральный порошок 400г',        'price' => 750,  'unit' => '400г',   'sort_order' => 1],
            ['category_id' => 11, 'name' => 'Средство для мытья посуды 500мл','price' => 550,  'unit' => '500мл',  'sort_order' => 2],

            // Одежда (category_id=12)
            ['category_id' => 12, 'name' => 'Носки хлопковые (3 пары)',       'price' => 900,  'unit' => '3 пары', 'sort_order' => 1],
            ['category_id' => 12, 'name' => 'Нательное бельё',                'price' => 2500, 'unit' => '1 шт.',  'sort_order' => 2],

            // Газеты и журналы (category_id=13)
            ['category_id' => 13, 'name' => 'Газета «Аргументы и Факты»',     'price' => 300,  'unit' => '1 шт.',  'sort_order' => 1],
            ['category_id' => 13, 'name' => 'Журнал «За рулём»',              'price' => 500,  'unit' => '1 шт.',  'sort_order' => 2],

            // Телефонные карты (category_id=14)
            ['category_id' => 14, 'name' => 'Карта Beeline 1000 тг',          'price' => 1100, 'unit' => '1 шт.',  'sort_order' => 1],
            ['category_id' => 14, 'name' => 'Карта Kcell 1000 тг',            'price' => 1100, 'unit' => '1 шт.',  'sort_order' => 2],
        ];

        foreach ($products as $product) {
            \App\Models\Product::create($product);
        }
    }
}
