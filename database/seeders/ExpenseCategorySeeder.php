<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Renta', 'Planilla', 'Servicios', 'Transporte', 'Marketing', 'Otros'] as $name) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
