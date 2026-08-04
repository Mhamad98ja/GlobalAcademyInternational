<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\ClassBookList;
use App\Models\Grade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassBookListsController extends Controller
{
    public function index(): View
    {
        return view('class-lists.index', [
            'lists' => ClassBookList::with('academicYear', 'grade', 'section', 'items.book')->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return $this->form(new ClassBookList());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $list = DB::transaction(function () use ($data): ClassBookList {
            $this->ensureUniqueScope($data);
            $list = ClassBookList::create($this->attributes($data));
            $this->replaceItems($list, $data['items']);

            return $list;
        });

        return redirect()->route('class-lists.edit', $list)->with('success', 'Book package created.');
    }

    public function edit(ClassBookList $classBookList): View
    {
        $classBookList->load('items');

        return $this->form($classBookList);
    }

    public function update(Request $request, ClassBookList $classBookList): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($classBookList, $data): void {
            $this->ensureUniqueScope($data, $classBookList);
            $classBookList->update($this->attributes($data));
            $this->replaceItems($classBookList, $data['items']);
        });

        return redirect()->route('class-lists.edit', $classBookList)->with('success', 'Book package updated.');
    }

    private function form(ClassBookList $classBookList): View
    {
        return view('class-lists.form', [
            'list' => $classBookList,
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            'grades' => Grade::with('sections')->orderBy('sort_order')->get(),
            'books' => Book::active()->orderBy('title')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'grade_id' => ['required', 'exists:grades,id'],
            'section_id' => [
                'nullable',
                Rule::exists('sections', 'id')->where('grade_id', $request->input('grade_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'distinct', 'exists:books,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.is_mandatory' => ['nullable', 'boolean'],
            'items.*.price_type' => ['required', 'in:free,discounted,paid'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'min:0'],
            'items.*.teacher_child_discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'academic_year_id' => $data['academic_year_id'],
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'] ?? null,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function ensureUniqueScope(array $data, ?ClassBookList $current = null): void
    {
        $query = ClassBookList::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('name', $data['name'])
            ->when($data['section_id'] ?? null, fn ($lists, $sectionId) => $lists->where('section_id', $sectionId), fn ($lists) => $lists->whereNull('section_id'));

        if ($current) {
            $query->where('id', '!=', $current->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A package with this name already exists for the selected academic year, grade, and section.',
            ]);
        }
    }

    private function replaceItems(ClassBookList $list, array $items): void
    {
        $list->items()->delete();
        foreach ($items as $item) {
            $list->items()->create([
                'book_id' => $item['book_id'],
                'quantity' => $item['quantity'],
                'is_mandatory' => (bool) ($item['is_mandatory'] ?? false),
                'price_type' => $item['price_type'],
                'unit_price' => $item['price_type'] === 'discounted' ? $item['unit_price'] : '0.00',
                'teacher_child_discount' => $item['teacher_child_discount'] ?? 0,
            ]);
        }
    }
}
