<?php

namespace App\Observers;

use App\Models\Transaction;

class TransactionObserver
{

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // Verifica se o status mudou para 'approved' ou 'paid'
        if ($transaction->wasChanged('status')) {
            $oldStatus = $transaction->getOriginal('status');
            $newStatus = $transaction->status;

            $groupManagementService = app(\App\Services\GroupManagementService::class);

            // Se pagamento foi aprovado, adiciona membro ao grupo
            if (in_array($newStatus, ['approved', 'paid', 'completed']) && 
                !in_array($oldStatus, ['approved', 'paid', 'completed'])) {
                $groupManagementService->addMemberAfterPayment($transaction);
            }

            // Se pagamento foi cancelado ou expirado, remove membro do grupo
            if (in_array($newStatus, ['cancelled', 'expired', 'refunded']) && 
                in_array($oldStatus, ['approved', 'paid', 'completed'])) {
                $groupManagementService->removeMemberAfterPaymentExpiry($transaction);
            }
        }
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Se a transação já foi criada com status aprovado, adiciona membro
        if (in_array($transaction->status, ['approved', 'paid', 'completed'])) {
            $groupManagementService = app(\App\Services\GroupManagementService::class);
            $groupManagementService->addMemberAfterPayment($transaction);
        }
    }
}

