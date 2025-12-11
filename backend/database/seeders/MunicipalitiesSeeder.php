<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MunicipalitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../manuais/Municipios.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->error("Arquivo Municipios.sql não encontrado em: {$sqlFile}");
            return;
        }

        $this->command->info("Lendo arquivo de municípios...");
        
        // Lê o arquivo linha por linha para economizar memória
        $handle = fopen($sqlFile, 'r');
        if (!$handle) {
            $this->command->error("Não foi possível abrir o arquivo Municipios.sql");
            return;
        }

        $municipalities = [];
        $batchSize = 500;
        $total = 0;

        while (($line = fgets($handle)) !== false) {
            // Procura por linhas de INSERT
            if (preg_match("/Insert into Municipio \(Codigo, Nome, Uf\) values \('(\d+)','([^']+)', '([A-Z]{2})'\);/i", $line, $matches)) {
                $municipalities[] = [
                    'codigo' => (int) $matches[1],
                    'nome' => str_replace("''", "'", $matches[2]), // Corrige aspas duplas
                    'uf' => $matches[3],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Insere em lotes
                if (count($municipalities) >= $batchSize) {
                    DB::table('municipalities')->insert($municipalities);
                    $total += count($municipalities);
                    $this->command->info("Inseridos {$total} municípios...");
                    $municipalities = [];
                }
            }
        }

        // Insere os restantes
        if (!empty($municipalities)) {
            DB::table('municipalities')->insert($municipalities);
            $total += count($municipalities);
        }

        fclose($handle);
        
        $this->command->info("Inseridos {$total} municípios com sucesso!");
    }
}
