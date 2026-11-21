<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\RentalReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request, RentalReportService $service): View
    {
        $data = $request->validate([
            'view' => ['nullable', 'in:'.implode(',', array_keys(RentalReportService::VIEWS))],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $view = $data['view'] ?? 'monthly';
        $year = (int) ($data['year'] ?? now()->year);
        $month = isset($data['month']) ? (int) $data['month'] : null;

        $report = $service->build($view, $year, $month);
        $firstYear = Rental::min('start_date') ? (int) substr((string) Rental::min('start_date'), 0, 4) : now()->year;

        return view('admin.reports.index', [
            'views' => RentalReportService::VIEWS,
            'view' => $view,
            'year' => $year,
            'month' => $view === 'daily' ? ($month ?? (int) now()->month) : $month,
            'years' => range(max(now()->year, $year), min($firstYear, $year)),
            'report' => $report,
        ]);
    }
}
