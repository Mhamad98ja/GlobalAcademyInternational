<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\ClassBookList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClassBookListTransferService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * Copy package definitions to another branch. Books remain in their source
     * branch until a separate stock transfer is completed.
     *
     * @param list<int> $packageIds
     * @return Collection<int, ClassBookList>
     */
    public function copyToBranch(Branch $source, Branch $destination, array $packageIds, User $user, ?string $notes = null): Collection
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages(['destination_branch_id' => 'Choose a different destination branch.']);
        }

        $packageIds = array_values(array_unique(array_map('intval', $packageIds)));
        $packages = ClassBookList::query()
            ->with('items')
            ->whereIn('id', $packageIds)
            ->get();

        if ($packages->count() !== count($packageIds)) {
            throw ValidationException::withMessages(['package_ids' => 'Choose only packages from the current branch.']);
        }

        return DB::transaction(function () use ($packages, $source, $destination, $user, $notes): Collection {
            return $packages->map(function (ClassBookList $package) use ($source, $destination, $user, $notes): ClassBookList {
                $target = ClassBookList::withoutGlobalScopes()
                    ->where('branch_id', $destination->id)
                    ->where('academic_year_id', $package->academic_year_id)
                    ->where('grade_id', $package->grade_id)
                    ->when($package->section_id, fn ($query) => $query->where('section_id', $package->section_id), fn ($query) => $query->whereNull('section_id'))
                    ->where('name', $package->name)
                    ->first();

                $wasCreated = $target === null;
                $target ??= new ClassBookList();
                $target->branch_id = $destination->id;
                $target->fill([
                    'academic_year_id' => $package->academic_year_id,
                    'grade_id' => $package->grade_id,
                    'section_id' => $package->section_id,
                    'name' => $package->name,
                    'is_active' => $package->is_active,
                ]);
                $target->save();

                $target->items()->delete();
                foreach ($package->items as $item) {
                    $target->items()->create([
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                        'is_mandatory' => $item->is_mandatory,
                        'price_type' => $item->price_type,
                        'unit_price' => $item->unit_price,
                        'teacher_child_discount' => $item->teacher_child_discount,
                    ]);
                }

                $this->audit->log('book_package.branch_copied', $package, [], [
                    'destination_branch_id' => $destination->id,
                    'destination_branch' => $destination->name,
                    'destination_package_id' => $target->id,
                    'destination_package_action' => $wasCreated ? 'created' : 'updated',
                    'item_count' => $package->items->count(),
                    'performed_by' => $user->id,
                ], $notes);

                return $target;
            });
        });
    }
}
