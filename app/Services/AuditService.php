<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
final class AuditService
{
    public function log(string $event, ?Model $subject = null, array $old = [], array $new = [], ?string $reason = null): void
    {
        AuditLog::create(['user_id'=>Auth::id(),'event'=>$event,'auditable_type'=>$subject ? $subject::class : null,'auditable_id'=>$subject?->getKey(),'old_values'=>$old ?: null,'new_values'=>$new ?: null,'reason'=>$reason,'ip_address'=>request()?->ip(),'user_agent'=>substr((string) request()?->userAgent(),0,1000)]);
    }
}
