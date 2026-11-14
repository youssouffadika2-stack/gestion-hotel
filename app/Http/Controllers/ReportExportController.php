<?php

namespace App\Http\Controllers;

use App\Livewire\Reports\Index as Reports;
use App\Services\CurrentHotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportExportController extends Controller
{
    /** Export CSV (séparateur « ; » et BOM UTF-8 pour une ouverture directe dans Excel). */
    public function __invoke(Request $request)
    {
        $hotel = app(CurrentHotel::class)->get();
        abort_unless($hotel, 403);
        Gate::authorize('viewFinance', $hotel);

        $period = Reports::clean($request->query('periode'));
        $report = Reports::build($hotel, $period);
        $filename = 'rapport-'.Str::slug($hotel->name).'-'.$period.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Période', 'Recettes (FCFA)', 'Dépenses (FCFA)', 'Net (FCFA)'], ';');
            foreach ($report['rows'] as $r) {
                fputcsv($out, [$r['label'], $r['revenue'], $r['expenses'], $r['net']], ';');
            }
            fputcsv($out, ['TOTAL', $report['totals']['revenue'], $report['totals']['expenses'], $report['totals']['net']], ';');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
