<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContractType;

class ContractTypesTableSeeder extends Seeder
{
    public function run()
    {
        $contracts = [
            'CDI',
            'CDD',
            'Stage',
        ];

        foreach ($contracts as $contract) {
            ContractType::create(['name' => $contract]);
        }
    }
}