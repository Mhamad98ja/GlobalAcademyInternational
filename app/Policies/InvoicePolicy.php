<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\Invoice;
use App\Models\User;
class InvoicePolicy { public function view(User $user,Invoice $invoice):bool{return $user->hasPermission('invoices.view');} public function cancel(User $user,Invoice $invoice):bool{return $user->hasPermission('invoices.cancel') && $invoice->status!=='cancelled';} }
