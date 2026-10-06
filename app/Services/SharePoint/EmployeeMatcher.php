<?php

namespace App\Services\SharePoint;

use App\Models\Employee;
use Illuminate\Support\Collection;

class EmployeeMatcher
{
    /** @var array<string, Collection<int, Employee>>|null */
    private ?array $index = null;

    public function __construct(private readonly FornitoreMatcher $normalizer) {}

    public function match(string $folderName): SubjectMatch
    {
        $candidates = $this->index()[$this->normalizer->normalize($folderName)] ?? null;

        if ($candidates === null) {
            return new SubjectMatch(null, 'none', 'employee');
        }

        $employee = $candidates->sortBy(fn (Employee $e) => $e->deleted_at !== null)->first();

        return new SubjectMatch($employee, 'exact', 'employee', $employee->name);
    }

    /** @return array<string, Collection<int, Employee>> */
    private function index(): array
    {
        return $this->index ??= Employee::withTrashed()->get()
            ->groupBy(fn (Employee $e) => $this->normalizer->normalize((string) $e->name))
            ->all();
    }
}
