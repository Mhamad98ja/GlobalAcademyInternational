<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $books = DB::table('books')
                ->select('id', 'isbn', 'barcode')
                ->orderBy('id')
                ->get();

            /** @var array<string, list<object>> $booksByIsbn */
            $booksByIsbn = [];
            foreach ($books as $book) {
                $isbn = trim((string) $book->isbn);
                if ($isbn !== '') {
                    $booksByIsbn[$isbn][] = $book;
                }
            }

            // An ISBN can be repeated in the source list for distinct catalogue
            // entries. Since barcodes must remain unique, only a unique ISBN is
            // safe to make an exact barcode during this one-time correction.
            $targetBarcodes = [];
            foreach ($booksByIsbn as $isbn => $isbnBooks) {
                if (count($isbnBooks) === 1) {
                    $targetBarcodes[$isbnBooks[0]->id] = $isbn;
                }
            }

            $barcodeOwners = [];
            foreach ($books as $book) {
                $barcodeOwners[(string) $book->barcode] = $book->id;
            }

            foreach ($targetBarcodes as $bookId => $barcode) {
                $ownerId = $barcodeOwners[$barcode] ?? null;
                if ($ownerId !== null && $ownerId !== $bookId && ! isset($targetBarcodes[$ownerId])) {
                    // Do not replace a barcode that belongs to a book whose ISBN
                    // cannot safely become its barcode.
                    unset($targetBarcodes[$bookId]);
                }
            }

            // Temporarily move every changed barcode first. This avoids a unique
            // index collision when an existing barcode is another book's ISBN.
            foreach ($targetBarcodes as $bookId => $barcode) {
                $currentBarcode = (string) $books->firstWhere('id', $bookId)->barcode;
                if ($currentBarcode !== $barcode) {
                    DB::table('books')
                        ->where('id', $bookId)
                        ->update(['barcode' => "ISBN-MIGRATION-{$bookId}"]);
                }
            }

            foreach ($targetBarcodes as $bookId => $barcode) {
                DB::table('books')
                    ->where('id', $bookId)
                    ->update(['barcode' => $barcode]);
            }
        });
    }

    public function down(): void
    {
        // The original generated barcodes are not recoverable after this data correction.
    }
};
