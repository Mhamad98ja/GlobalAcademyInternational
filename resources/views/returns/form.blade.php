@extends('layouts.app', ['title' => 'Return books'])

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Return selected books</h1>
        <p class="page-subtitle">{{ $invoice->invoice_number }} &middot; {{ $invoice->student->full_name }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('returns.index') }}">Cancel</a>
</div>

<form method="POST" action="{{ route('returns.store', $invoice) }}" class="panel" data-submit-lock>
    @csrf
    <div class="border-b border-slate-100 p-5">
        <h2 class="panel-title">Select books to return</h2>
        <p class="mt-1 text-sm text-slate-500">Choose only the books being returned. Each book can be returned in more than one transaction, up to its remaining returnable quantity.</p>
        @error('items')<p class="form-error mt-3">{{ $message }}</p>@enderror
    </div>

    @if ($items->isEmpty())
        <div class="empty">Every book on this invoice has already been returned, so there are no returnable books remaining.</div>
    @else
        <div class="table-wrap">
            <table class="data-table min-w-[900px]">
                <thead><tr><th>Return</th><th>Book</th><th>Distributed</th><th>Available to return</th><th>Return quantity</th><th>Condition</th><th>Reason</th></tr></thead>
                <tbody>
                    @foreach ($items as $index => $item)
                        @php($isSelected = old('items.'.$index.'.distribution_item_id') !== null)
                        <tr data-return-row class="{{ $isSelected ? '' : 'opacity-60' }}">
                            <td><input type="checkbox" data-return-choice @checked($isSelected) aria-label="Return {{ $item->book->title }}"></td>
                            <td class="font-medium">{{ $item->book->title }}<input type="hidden" name="items[{{ $index }}][distribution_item_id]" value="{{ $item->id }}" data-return-field @disabled(! $isSelected)></td>
                            <td>{{ $item->quantity }}</td>
                            <td><span class="font-semibold text-teal-700">{{ $item->returnable_quantity }}</span></td>
                            <td><input type="number" min="1" max="{{ $item->returnable_quantity }}" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', 1) }}" required data-return-field @disabled(! $isSelected)></td>
                            <td><select name="items[{{ $index }}][condition]" required data-return-field @disabled(! $isSelected)><option value="new_resellable" @selected(old('items.'.$index.'.condition') === 'new_resellable')>New and resellable</option><option value="used_acceptable" @selected(old('items.'.$index.'.condition') === 'used_acceptable')>Used but acceptable</option><option value="damaged" @selected(old('items.'.$index.'.condition') === 'damaged')>Damaged</option><option value="lost" @selected(old('items.'.$index.'.condition') === 'lost')>Lost</option><option value="not_eligible" @selected(old('items.'.$index.'.condition') === 'not_eligible')>Not eligible</option></select></td>
                            <td><input name="items[{{ $index }}][reason]" value="{{ old('items.'.$index.'.reason') }}" required placeholder="Return reason" data-return-field @disabled(! $isSelected)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="p-5">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
    </div>
    <div class="flex justify-end border-t border-slate-200 p-5">
        <button class="btn btn-primary" @disabled($items->isEmpty())>Accept selected return</button>
    </div>
</form>

<script>
document.querySelectorAll('[data-return-row]').forEach((row) => {
    const choice = row.querySelector('[data-return-choice]');
    const sync = () => {
        const selected = choice.checked;
        row.classList.toggle('opacity-60', !selected);
        row.querySelectorAll('[data-return-field]').forEach((field) => {
            field.disabled = !selected;
        });
    };

    choice.addEventListener('change', sync);
    sync();
});
</script>
@endsection
