<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DataImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(),
        ]);
    }

    public function students(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workbook' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'registered_on' => ['required', 'date'],
        ]);

        return $this->runImport('students:import-list', $request, [
            '--year' => $data['year'],
            '--registered-on' => $data['registered_on'],
            '--branch' => 'MAIN',
            '--status' => 'completed',
        ]);
    }

    public function books(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workbook' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        return $this->runImport('books:import-list', $request, [
            '--year' => $data['year'],
            '--branch' => 'MAIN',
        ]);
    }

    private function runImport(string $command, Request $request, array $arguments): RedirectResponse
    {
        $storedPath = $request->file('workbook')->store('private/imports');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $exitCode = Artisan::call($command, ['path' => $absolutePath, ...$arguments]);
            $output = trim(Artisan::output());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['workbook' => 'The import could not be completed. Please verify the workbook and try again.']);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }

        if ($exitCode !== Command::SUCCESS) {
            return back()->withErrors([
                'workbook' => Str::limit($output !== '' ? $output : 'The workbook could not be imported.', 2000),
            ]);
        }

        return redirect()->route('admin.imports')->with([
            'success' => 'Import completed in the Main Branch.',
            'import_output' => Str::limit($output, 4000),
        ]);
    }
}
