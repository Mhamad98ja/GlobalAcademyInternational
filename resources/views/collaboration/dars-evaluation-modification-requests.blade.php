<section class="dars-evaluation-requests">
    <header class="dars-evaluation-dashboard-header">
        <div><strong>Evaluation Requests</strong><span>Teachers can request access to modify an evaluation already submitted to administration.</span></div>
    </header>
    @if($isEvaluationAdministrator)
        @if($modificationRequests->isNotEmpty())
            <div class="table-wrap"><table class="dars-evaluation-table"><thead><tr><th>Evaluation</th><th>Teacher / class / subject</th><th>Academic term</th><th>Requested by</th><th>Reason</th><th>Status</th><th>Decision</th></tr></thead><tbody>
                @foreach($modificationRequests as $modificationRequest)
                    @php($assessment = $modificationRequest->assessment)
                    <tr><td><strong>{{ $assessment?->name }}</strong><small>{{ str($assessment?->type)->headline() }}</small></td><td>{{ $assessment?->assignment?->teacher?->full_name ?: 'Unassigned teacher' }}<small>{{ $assessment?->assignment?->grade?->name }}{{ $assessment?->assignment?->section ? ' / '.$assessment->assignment->section->name : '' }} · {{ $assessment?->assignment?->subject?->name }}</small></td><td>{{ $assessment?->term?->name ?: 'No term assigned' }}</td><td>{{ $modificationRequest->requestedBy?->name ?: 'Teacher' }}<small>{{ $modificationRequest->created_at?->format('d/m/Y H:i') }}</small></td><td>{{ $modificationRequest->reason }}</td><td><span class="dars-evaluation-status {{ $modificationRequest->status }}">{{ str($modificationRequest->status)->headline() }}</span>@if($modificationRequest->response)<small>{{ $modificationRequest->response }}</small>@endif</td><td>@if($modificationRequest->status === 'pending')<div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('collaboration.modification-requests.approve', $modificationRequest) }}">@csrf<button class="dars-evaluation-primary" type="submit">Approve</button></form><form method="POST" action="{{ route('collaboration.modification-requests.reject', $modificationRequest) }}" class="flex flex-wrap gap-2"><input name="response" required placeholder="Reason for return"><button class="dars-evaluation-secondary" type="submit">Reject</button></form></div>@else<span class="text-xs text-slate-500">{{ $modificationRequest->reviewedBy?->name ?: 'Reviewed' }}</span>@endif</td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="dars-evaluation-request-empty"><strong>No modification requests found</strong></div>
        @endif
    @else
        <div class="table-wrap"><table class="dars-evaluation-table"><thead><tr><th>Evaluation</th><th>Class / subject</th><th>Academic term</th><th>Status</th><th>Request modification</th></tr></thead><tbody>
            @forelse($submittedEvaluations as $evaluation)
                @php($latestRequest = $evaluation->modificationRequests->sortByDesc('created_at')->first())
                <tr><td><strong>{{ $evaluation->name }}</strong><small>{{ str($evaluation->type)->headline() }}</small></td><td>{{ $evaluation->assignment?->grade?->name }}{{ $evaluation->assignment?->section ? ' / '.$evaluation->assignment->section->name : '' }} · {{ $evaluation->assignment?->subject?->name }}</td><td>{{ $evaluation->term?->name ?: 'No term assigned' }}</td><td><span class="dars-evaluation-status {{ $latestRequest?->status ?: 'submitted' }}">{{ $latestRequest ? str($latestRequest->status)->headline() : 'Submitted' }}</span>@if($latestRequest?->response)<small>{{ $latestRequest->response }}</small>@endif</td><td>@if($latestRequest?->status === 'approved')<a href="{{ route('grades.assessments.edit', $evaluation) }}">Modify evaluation</a>@elseif($latestRequest?->status === 'pending')<span class="text-xs text-slate-500">Request pending</span>@else<form method="POST" action="{{ route('collaboration.modification-requests.store') }}" class="grid gap-2"><input type="hidden" name="assessment_id" value="{{ $evaluation->id }}">@csrf<textarea name="reason" rows="2" required placeholder="Explain what needs to be modified"></textarea><button class="dars-evaluation-primary" type="submit">Send request</button></form>@endif</td></tr>
            @empty
                <tr><td colspan="5" class="dars-evaluation-empty-row">No submitted evaluations are available for modification requests.</td></tr>
            @endforelse
        </tbody></table></div>
    @endif
</section>
