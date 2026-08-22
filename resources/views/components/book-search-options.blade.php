@props(['id', 'books'])

<datalist id="{{ $id }}">
    @foreach ($books as $book)
        <option value="{{ $book->barcode }}">{{ $book->title }}</option>
        @if ($book->isbn)<option value="{{ $book->isbn }}">{{ $book->title }}</option>@endif
        <option value="{{ $book->title }}">{{ $book->barcode }}</option>
    @endforeach
</datalist>
