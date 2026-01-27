<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verifica se a tabela já existe e tem colunas
        if (Schema::hasTable('transactions')) {
            // Adiciona colunas se não existirem
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'bot_id')) {
                    $table->unsignedBigInteger('bot_id')->after('id');
                }
                if (!Schema::hasColumn('transactions', 'contact_id')) {
                    $table->unsignedBigInteger('contact_id')->after('bot_id');
                }
                if (!Schema::hasColumn('transactions', 'payment_plan_id')) {
                    $table->unsignedBigInteger('payment_plan_id')->after('contact_id');
                }
                if (!Schema::hasColumn('transactions', 'payment_cycle_id')) {
                    $table->unsignedBigInteger('payment_cycle_id')->after('payment_plan_id');
                }
                if (!Schema::hasColumn('transactions', 'gateway')) {
                    $table->string('gateway', 50)->after('payment_cycle_id');
                }
                if (!Schema::hasColumn('transactions', 'gateway_transaction_id')) {
                    $table->string('gateway_transaction_id', 255)->nullable()->after('gateway');
                }
                if (!Schema::hasColumn('transactions', 'amount')) {
                    $table->decimal('amount', 10, 2)->after('gateway_transaction_id');
                }
                if (!Schema::hasColumn('transactions', 'currency')) {
                    $table->string('currency', 3)->default('BRL')->after('amount');
                }
                if (!Schema::hasColumn('transactions', 'status')) {
                    $table->string('status', 50)->default('pending')->after('currency');
                }
                if (!Schema::hasColumn('transactions', 'payment_method')) {
                    $table->string('payment_method', 50)->nullable()->after('status');
                }
                if (!Schema::hasColumn('transactions', 'metadata')) {
                    $table->json('metadata')->nullable()->after('payment_method');
                }
            });

            // Adiciona índices se não existirem
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->hasIndex('transactions', 'transactions_bot_id_index')) {
                    $table->index('bot_id');
                }
                if (!$this->hasIndex('transactions', 'transactions_contact_id_index')) {
                    $table->index('contact_id');
                }
                if (!$this->hasIndex('transactions', 'transactions_status_index')) {
                    $table->index('status');
                }
                if (!$this->hasIndex('transactions', 'transactions_created_at_index')) {
                    $table->index('created_at');
                }
            });

            // Adiciona foreign keys se não existirem
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->hasForeignKey('transactions', 'transactions_bot_id_foreign')) {
                    $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
                }
                if (!$this->hasForeignKey('transactions', 'transactions_contact_id_foreign')) {
                    $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
                }
                if (!$this->hasForeignKey('transactions', 'transactions_payment_plan_id_foreign')) {
                    $table->foreign('payment_plan_id')->references('id')->on('payment_plans')->onDelete('restrict');
                }
                if (!$this->hasForeignKey('transactions', 'transactions_payment_cycle_id_foreign')) {
                    $table->foreign('payment_cycle_id')->references('id')->on('payment_cycles')->onDelete('restrict');
                }
            });
        } else {
            // Cria a tabela se não existir
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bot_id');
                $table->unsignedBigInteger('contact_id');
                $table->unsignedBigInteger('payment_plan_id');
                $table->unsignedBigInteger('payment_cycle_id');
                $table->string('gateway', 50);
                $table->string('gateway_transaction_id', 255)->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('BRL');
                $table->string('status', 50)->default('pending');
                $table->string('payment_method', 50)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('bot_id');
                $table->index('contact_id');
                $table->index('status');
                $table->index('created_at');
                $table->index('gateway_transaction_id');

                $table->foreign('bot_id')->references('id')->on('bots')->onDelete('cascade');
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
                $table->foreign('payment_plan_id')->references('id')->on('payment_plans')->onDelete('restrict');
                $table->foreign('payment_cycle_id')->references('id')->on('payment_cycles')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não remove a tabela, apenas reverte alterações se necessário
        Schema::table('transactions', function (Blueprint $table) {
            if ($this->hasForeignKey('transactions', 'transactions_bot_id_foreign')) {
                $table->dropForeign('transactions_bot_id_foreign');
            }
            if ($this->hasForeignKey('transactions', 'transactions_contact_id_foreign')) {
                $table->dropForeign('transactions_contact_id_foreign');
            }
            if ($this->hasForeignKey('transactions', 'transactions_payment_plan_id_foreign')) {
                $table->dropForeign('transactions_payment_plan_id_foreign');
            }
            if ($this->hasForeignKey('transactions', 'transactions_payment_cycle_id_foreign')) {
                $table->dropForeign('transactions_payment_cycle_id_foreign');
            }
        });
    }

    /**
     * Verifica se um índice existe
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * Verifica se uma foreign key existe
     */
    private function hasForeignKey(string $table, string $fkName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.key_column_usage 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND constraint_name = ?",
            [$databaseName, $table, $fkName]
        );
        
        return $result[0]->count > 0;
    }
};

