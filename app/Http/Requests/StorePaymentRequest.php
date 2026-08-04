<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StorePaymentRequest extends FormRequest { public function authorize():bool{return $this->user()?->hasPermission('payments.create')??false;} public function rules():array{return ['amount'=>['required','decimal:0,2','min:0.01'],'payment_date'=>['required','date'],'method'=>['required',Rule::in(['cash','card','bank_transfer','check','other'])],'cashbox'=>['nullable','string','max:100'],'reference_number'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:3000'],'idempotency_key'=>['required','uuid']];} }
