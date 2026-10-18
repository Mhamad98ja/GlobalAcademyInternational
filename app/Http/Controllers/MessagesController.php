<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolMessage;
use App\Models\SchoolMessageRecipient;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MessagesController extends Controller
{
    public function index(Request $request, BranchContext $branches): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $branchId = $branches->id();
        $canSeeAllPeople = $user->hasGlobalBranchAccess();
        $activeAcademicYearId = AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->value('id');
        $recipients = User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->when($branchId && ! $canSeeAllPeople, fn ($query) => $query->where(fn ($users) => $users
                ->where('has_all_branch_access', true)
                ->orWhereHas('branches', fn ($assignedBranches) => $assignedBranches->whereKey($branchId))))
            ->with([
                'roles:id,name',
                'student:id,first_name,middle_name,last_name',
                'student.registrations' => fn ($query) => $query
                    ->with('grade')
                    ->where('status', 'active')
                    ->when($activeAcademicYearId, fn ($registrations) => $registrations->where('academic_year_id', $activeAcademicYearId))
                    ->latest('registered_on')
                    ->latest('id'),
            ])
            ->orderBy('name')
            ->get();

        $recipientGroups = $recipients
            ->groupBy(function (User $recipient): string {
                $roleNames = $recipient->roles->pluck('name');
                if ($roleNames->contains(fn (string $role): bool => in_array($role, ['parent', 'guardian', 'responsible-person', 'responsible_person'], true))) {
                    return 'Parents / guardians';
                }
                if ($recipient->student_id || $roleNames->contains('student')) {
                    $gradeName = $recipient->student?->registrations->first()?->grade?->name;

                    return $gradeName ? 'Students - '.$gradeName : 'Students';
                }
                if ($recipient->teacher_id || $roleNames->contains('teacher')) {
                    return 'Teachers';
                }

                return $recipient->is_controller_administrator || $roleNames->contains('super-administrator')
                    ? 'Administrators'
                    : 'Staff';
            })
            ->map(fn ($group) => $group->sortBy(fn (User $recipient): string => mb_strtolower($recipient->name))->values());
        $groupOrder = ['Parents / guardians' => 1, 'Teachers' => 2, 'Students' => 3, 'Administrators' => 4, 'Staff' => 5];
        $recipientGroups = $recipientGroups->sortBy(fn ($group, string $label): string => sprintf('%02d-%s', $groupOrder[preg_replace('/ - .+$/u', '', $label)] ?? 9, $label));
        $selectedRecipientIds = collect(old('recipient_ids', []))->map(fn ($id): int => (int) $id)->all();

        $search = trim((string) $request->query('q', ''));
        $inbox = SchoolMessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->where('is_archived', false)
            ->when($search !== '', fn ($query) => $query->whereHas('message', fn ($messages) => $messages->withoutGlobalScopes()
                ->whereInsensitiveLike('subject', $search)
                ->orWhereInsensitiveLike('body', $search)))
            ->with(['message' => fn ($messages) => $messages->withoutGlobalScopes()->with('sender')])
            ->latest('id')
            ->paginate(20, ['*'], 'inbox_page');

        $sent = SchoolMessage::withoutGlobalScopes()
            ->where('sender_id', $user->id)
            ->withCount('recipients')
            ->latest('sent_at')
            ->take(12)
            ->get();

        return view('messages.index', compact('inbox', 'sent', 'recipients', 'recipientGroups', 'selectedRecipientIds', 'search'));
    }

    public function store(Request $request, BranchContext $branches, AuditService $audit): RedirectResponse
    {
        $sender = $request->user();
        abort_unless($sender instanceof User, 403);
        $data = $request->validate([
            'recipient_ids' => ['required', 'array', 'min:1', 'max:100'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $branchId = $branches->id();
        $canSendToAllPeople = $sender->hasGlobalBranchAccess();
        $recipients = User::query()
            ->whereIn('id', $data['recipient_ids'])
            ->where('is_active', true)
            ->whereKeyNot($sender->id)
            ->when($branchId && ! $canSendToAllPeople, fn ($query) => $query->where(fn ($users) => $users
                ->where('has_all_branch_access', true)
                ->orWhereHas('branches', fn ($assignedBranches) => $assignedBranches->whereKey($branchId))))
            ->get(['id']);
        abort_unless($recipients->count() === count($data['recipient_ids']), 403);

        $message = DB::transaction(function () use ($data, $sender, $recipients): SchoolMessage {
            $message = SchoolMessage::create([
                'sender_id' => $sender->id,
                'subject' => $data['subject'],
                'body' => $data['body'],
                'sent_at' => now(),
            ]);
            $message->recipients()->createMany($recipients->map(fn (User $recipient) => ['recipient_id' => $recipient->id])->all());

            return $message;
        });
        $audit->log('school_message.sent', $message, [], ['subject' => $message->subject, 'recipient_count' => $recipients->count()]);

        return back()->with('success', 'Message sent.');
    }

    public function markRead(Request $request, SchoolMessageRecipient $recipient): RedirectResponse
    {
        abort_unless($recipient->recipient_id === $request->user()?->id, 403);
        $recipient->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        SchoolMessageRecipient::query()
            ->where('recipient_id', $request->user()?->id)
            ->where('is_archived', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All messages marked as read.');
    }
}
