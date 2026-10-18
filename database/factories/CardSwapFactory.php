<?php

namespace Database\Factories;

use App\Models\CardSwap;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CardSwap> */
class CardSwapFactory extends Factory
{
    public function definition(): array
    {
        return ['company_id' => Company::factory(), 'created_by' => User::factory(), 'submission_key' => (string) Str::uuid(), 'reference' => 'IY-'.Str::ulid(), 'status' => 'draft', 'step' => 1, 'swap_date' => today()->toDateString()];
    }
}
