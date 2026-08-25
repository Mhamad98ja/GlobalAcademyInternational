<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreDistributionRequest extends FormRequest { public function authorize():bool{return $this->user()?->hasPermission('distributions.create')??false;} public function rules():array{return ['student_registration_id'=>['required','exists:student_registrations,id'],'class_book_list_id'=>['nullable','exists:class_book_lists,id'],'items'=>['required','array','min:1','max:50'],'items.*.barcode'=>['required','string','max:100'],'items.*.quantity'=>['required','integer','min:1','max:100'],'payment.amount'=>['nullable','decimal:0,2','min:0.01'],'payment.payment_date'=>['nullable','date'],'payment.method'=>['nullable',Rule::in(['cash','card','bank_transfer','check','other'])],'payment.cashbox'=>['nullable','string','max:100'],'payment.reference_number'=>['nullable','string','max:100'],'payment.idempotency_key'=>['nullable','uuid'],'notes'=>['nullable','string','max:3000']];} }
