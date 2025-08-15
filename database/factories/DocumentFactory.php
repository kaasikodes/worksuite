<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'file_name' => $this->faker->uuid . '.pdf',
            'invoice_number' => $this->faker->numerify('INV-####'),
            'vendor' => $this->faker->company,
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'po_number' => $this->faker->numerify('PO-####'),
            'status' => 'pending', // can be pending, processing, processed, failed
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'processed',
            'invoice_number' => $this->faker->numerify('INV-####'),
            'vendor' => $this->faker->company,
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'po_number' => $this->faker->numerify('PO-####'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
        ]);
    }
}
