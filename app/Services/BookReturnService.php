<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\BookDistributionItem;
use App\Models\BookReturn;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class BookReturnService
{
    public function __construct(private readonly InventoryService $inventory,private readonly InvoiceService $invoices,private readonly AuditService $audit){}
    public function accept(Invoice $invoice,array $items,User $user,?string $notes=null):BookReturn
    {
        return DB::transaction(function()use($invoice,$items,$user,$notes):BookReturn{$invoice=Invoice::query()->lockForUpdate()->findOrFail($invoice->id);if($invoice->status==='cancelled')throw ValidationException::withMessages(['invoice'=>'Cancelled invoices cannot receive a return.']);$return=BookReturn::create(['student_id'=>$invoice->student_id,'invoice_id'=>$invoice->id,'returned_on'=>today(),'refund_total'=>'0.00','status'=>'accepted','notes'=>$notes,'accepted_by'=>$user->id]);$refund=0;foreach($items as $row){$distributed=BookDistributionItem::query()->with('book')->lockForUpdate()->findOrFail($row['distribution_item_id']);if($distributed->distribution->invoice_id!==$invoice->id)throw ValidationException::withMessages(['items'=>'The distributed item does not belong to this invoice.']);$qty=(int)$row['quantity'];$previous=\App\Models\BookReturnItem::query()->where('book_distribution_item_id',$distributed->id)->sum('quantity');if($qty<1||$qty+$previous>$distributed->quantity)throw ValidationException::withMessages(['items'=>'Returned quantity exceeds distributed quantity.']);$restock=($row['condition']??'')==='new_resellable';$amount=Money::cents((string)$distributed->unit_price)*$qty;$return->items()->create(['book_distribution_item_id'=>$distributed->id,'book_id'=>$distributed->book_id,'quantity'=>$qty,'condition'=>$row['condition'],'reason'=>$row['reason'],'restock'=>$restock,'refund_amount'=>Money::decimal($amount)]);if($restock)$this->inventory->change($distributed->book,$qty,'student_return',$return,$invoice->registration->academicYear,$user,'Accepted student return',$notes);$refund+=$amount;}$return->update(['refund_total'=>Money::decimal($refund)]);$this->audit->log('book_return.accepted',$return,[],['refund_total'=>$return->refund_total]);return $return;});
    }
}
