<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\Book;
use App\Models\BookDistribution;
use App\Models\DeferredPaymentApproval;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StudentRegistration;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;
class DashboardController extends Controller
{
    public function __invoke(Request $request):View{$year=$request->integer('academic_year_id');$books=Book::query()->active()->get();$invoiceQuery=Invoice::query()->when($year,fn($q)=>$q->where('academic_year_id',$year));return view('dashboard',[ 'activeStudents'=>StudentRegistration::query()->where('status','active')->when($year,fn($q)=>$q->where('academic_year_id',$year))->count(),'stock'=>$books->sum('stock_on_hand'),'lowStock'=>$books->filter(fn(Book $b)=>$b->stock_on_hand<=$b->minimum_stock)->sortBy('stock_on_hand')->take(6),'collected'=>Payment::query()->where('status','valid')->whereDate('payment_date',today())->sum('amount'),'outstanding'=>$invoiceQuery->sum('balance_due'),'todayDistributions'=>BookDistribution::query()->whereDate('distributed_on',today())->count(),'overdueDeferred'=>DeferredPaymentApproval::query()->whereIn('status',['approved','active','partially_settled'])->whereDate('expected_payment_date','<',today())->count(),'recentActivity'=>StockMovement::query()->with('book','user')->latest()->take(6)->get(),'academicYears'=>\App\Models\AcademicYear::query()->orderByDesc('starts_on')->get()]);}
}
