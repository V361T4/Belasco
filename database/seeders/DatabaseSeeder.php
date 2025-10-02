<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Categories
        $men = Category::create([
            'name' => 'Men',
            'slug' => 'men',
        ]);

        $women = Category::create([
            'name' => 'Women',
            'slug' => 'women',
        ]);

        // Create Products (T-shirts only)
        Product::create([
            'category_id' => $men->id,
            'name' => 'Men\'s Classic T-Shirt',
            'slug' => 'mens-classic-tshirt',
            'description' => 'Comfortable cotton T-shirt for men',
            'price' => 499.00,
            'stock' => 50,
            'image' => 'images/mens-classic-tshirt.jpg'
        ]);

        Product::create([
            'category_id' => $women->id,
            'name' => 'Women\'s Stylish T-Shirt',
            'slug' => 'womens-stylish-tshirt',
            'description' => 'Trendy T-shirt for women',
            'price' => 599.00,
            'stock' => 40,
            'image' => 'images/womens-stylish-tshirt.jpg'
        ]);
    }
}