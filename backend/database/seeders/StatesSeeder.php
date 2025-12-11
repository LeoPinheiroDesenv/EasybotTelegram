<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../manuais/Estados.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->error("Arquivo Estados.sql não encontrado em: {$sqlFile}");
            return;
        }

        $sql = File::get($sqlFile);
        
        // Remove comentários e quebras de linha desnecessárias
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        // Extrai apenas os INSERTs
        preg_match_all("/Insert into Estado \(CodigoUf, Nome, Uf, Regiao\) values \((\d+), '([^']+)', '([A-Z]{2})', (\d+)\);/i", $sql, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            $this->command->error("Nenhum estado encontrado no arquivo SQL");
            return;
        }

        $states = [];
        foreach ($matches as $match) {
            $states[] = [
                'codigo_uf' => (int) $match[1],
                'nome' => $match[2],
                'uf' => $match[3],
                'regiao' => (int) $match[4],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insere em lotes para melhor performance
        DB::table('states')->insert($states);
        
        $this->command->info("Inseridos " . count($states) . " estados com sucesso!");
    }
}
