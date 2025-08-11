<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApiCredential;


class ApiCredentialSeeder extends Seeder
{
    public function run(): void
    {
        ApiCredential::create([
            'cliente_nombre' => 'familyoutlet',
            'nombre' => 'woocommerce',
            'base_url' => 'https://yellowgreen-zebra-732284.hostingersite.com/wp-json/wc/v3',
            'user' => 'ck_8cd343c0109d725c58cab328868f3347e70d5e72',
            'password' => 'cs_1348d8c0d0aa0db253e014c067e69c1175f92537',
            'api_token' => 'e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X', 

        ]);

        ApiCredential::create([
            'cliente_nombre' => 'familyoutlet',
            'nombre' => 'sirett',
            'base_url' => 'https://familyoutletsancarlos.com/webservice.php',
            'user' => '114',
            'password' => 'CODIGO50X',
            'extra' => '0', // BID
            'api_token' => 'e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X', 

        ]);

        ApiCredential::create([
            'cliente_nombre' => 'familyoutlet',
            'nombre' => 'telegram',
            'base_url' => 'https://api.telegram.org',
            'user' => '8440598472:AAGnfd2qZzzbJJqwh53CdN5f0XyTPukRdfU', // token
            'extra' => '8141260436', // chat_id
            'api_token' => 'e43f59b9e28VH2VvH8ZvOIg8fF3KkgXzwUCODIGO50X', 

        ]);

        
    }
}
