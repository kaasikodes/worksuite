<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;


class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => $this->faker->numerify('PO-####'),
            'vendor' => $this->faker->company,
            'amount' => $this->faker->randomFloat(2, 100, 10000),
        ];
    }
}
