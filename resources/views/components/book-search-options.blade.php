@props(['id', 'books'])

<datalist id="{{ $id }}">
    @foreach ($books as $book)
        <option value="{{ $book->barcode }}">{{ $book->title }}</option>
        <option value="{{ $book->title }}">{{ $book->barcode }}</option>
    @endforeach
</datalist>
