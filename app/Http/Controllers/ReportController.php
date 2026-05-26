<?php

namespace App\Http\Controllers;


use App\Helper\ReportHelper;
use App\Models\ProviderPayout;
use Illuminate\Http\Request;

class ReportController extends Controller {


    public function providerPayoutReport(Request $request, $id) {
        $query = ProviderPayout::select(['provider_id', 'payment_method', 'description', 'paid_date', 'status', 'amount'])
            ->with('providers', function ($q) {
                return $q->select(['id', 'first_name', 'last_name']);
            })->where('provider_id', $id);

        $filter = $request->filter;

        if ($filter) {
            // Apply filter here.
        }

        $headerMap = [
            'providers.first_name' => 'Provider First Name',
            'providers.last_name' => 'Provider Last Name',
            'payment_method' => 'Payment Method',
            'description' => 'Description',
            'paid_date' => 'Paid Date',
            'status' => 'Status',
            'amount' => 'Amount',
        ];

        $totalAmount = $query->clone()->where(['status' => 'completed'])->sum('amount') ?? 0;
        $totalPending = $query->clone()->where(['status' => 'pending'])->sum('amount') ?? 0;

        $footerRow = [
            ['', '', '', '', '', '', ''],
            ['', '', '', '', '', 'Total Pending', "$totalPending"],
            ['', '', '', '', '', 'Total Completed', "$totalAmount"],
        ];

        $result = ReportHelper::exportToExcel($query, $headerMap, $footerRow);
        if ($result === false) {
            return redirect()->back();
        }
    }

}
