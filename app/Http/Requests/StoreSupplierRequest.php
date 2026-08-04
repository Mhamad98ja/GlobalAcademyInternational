<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreSupplierRequest extends FormRequest { public function authorize():bool{return $this->user()?->hasPermission('suppliers.manage')??false;} public function rules():array{return ['code'=>['required','string','max:50',Rule::unique('suppliers','code')->ignore($this->route('supplier'))],'name'=>['required','string','max:255'],'contact_person'=>['nullable','string','max:255'],'phone'=>['nullable','string','max:30'],'email'=>['nullable','email','max:255'],'address'=>['nullable','string','max:2000'],'tax_number'=>['nullable','string','max:80'],'payment_terms'=>['nullable','string','max:255'],'opening_balance'=>['nullable','decimal:0,2','min:0'],'is_active'=>['nullable','boolean'],'notes'=>['nullable','string','max:3000']];} }
