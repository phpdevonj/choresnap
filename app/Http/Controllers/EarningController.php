<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\BookingHandymanMapping;
use App\Models\HandymanPayout;
use App\Models\HandymanType;
use Illuminate\Support\Arr;
use Yajra\DataTables\DataTables;

class EarningController extends Controller {
    public function index() {
        $pageTitle = __('messages.earning');
        return view('earning.index', compact('pageTitle'));
    }

    public function setEarningData(Request $request) {
        $auth_user = authSession();
        $providers = User::with('providertype')->where('user_type', 'provider')->get();
        $earningData = array();

        foreach ($providers as $provider) {
            $effectiveCommission = $provider->getEffectiveCommission();
            
            if ($effectiveCommission) {
                $provider_commission = $effectiveCommission['value'];
                $provider_type = $effectiveCommission['type'];
            } else {
                $provider_commission = optional($provider->providertype)->commission;
                $provider_type = optional($provider->providertype)->type;
            }

            $bookings = Booking::where('provider_id', $provider->id)
                ->where('status', 'completed')
                ->whereNotNull('payment_id')
                ->get();

            $booking_data = get_provider_commission($bookings);

            $providerEarning = ProviderPayout::where('provider_id', $provider->id)->where('status', 'completed')->sum('amount') ?? 0;
            $provider_earning = ProviderPayout::where('provider_id', $provider->id)->where('status', 'completed')->sum('amount') ?? 0;
            if ($booking_data['total_amount'] > 0) {
                $provider_pay_due = ProviderPayout::where('provider_id', $provider->id)->where('status', 'Pending')->sum('amount') ?? 0;
            } else {
                $provider_pay_due = 0;
            }
            $totalprovider_pay_due = getPriceFormat($provider_pay_due);
            // $admin_earning  = calculate_commission($booking_data['total_amount'],$provider_commission,$provider_type, '', $providerEarning,$bookings->count());

            if ($request->is('api/*')) {
                $providerearning = $provider_earning['number_format'] <= 0 ? ($providerEarning) : $provider_earning['number_format'];
                $totalearning = $booking_data['total_amount'];
                $adminearning = $booking_data['final_admin_earning'];

                // $adminearning = $admin_earning['number_format'];
                $final_total_tax = $booking_data['final_total_tax'];

            } else {
                $totalearning = getPriceFormat($booking_data['total_amount']);
                $final_total_tax = getPriceFormat($booking_data['final_total_tax']);
                $adminearning = getPriceFormat($booking_data['final_admin_earning']);
                // $adminearning =$admin_earning['value'];
                if ($provider_earning <= 0) {
                    $providerearning = 0;
                } else {
                    $providerearning = getPriceFormat($provider_earning);
                }
            }
            $earningData[] = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->display_name,
                'email' => $provider->email,
                'commission' => $provider_commission,
                'commission_type' => $provider_type,
                'total_bookings' => $bookings->count(),
                'total_earning' => $totalearning,
                'taxes' => $final_total_tax,
                'provider_pay_due' => $totalprovider_pay_due,
                'taxes_formate' => $final_total_tax,
                'admin_earning' => $adminearning,
                'provider_earning' => $providerearning,
                'provider_earning_formate' => $provider_earning,
            ];


        }


        if ($request->ajax()) {
            return Datatables::of($earningData)
                ->addIndexColumn()
                ->addColumn('provider_name', function ($row) {
                    $provider_id = $row['provider_id'];
                    $provider_name = $row['provider_name'];
                    $email = $row['email'];
                    return view('earning.user', compact('row', 'provider_id', 'provider_name', 'email'));
                })
                ->addColumn('action', function ($row) {
                    $btn = '';
                    $providerPayoutReportButton = '<a title="Download Report" href="' . route('providerPayoutReport', $row['provider_id']) . '" target="_blank"><i class="fas fa-file-excel"></i></a>';
//                    $btn = '-';
//                    $provider_id = $row['provider_id'];
//                    if ($row['provider_earning_formate'] > 0) {
//                        $btn = "<a href=" . route('providerpayout.create', $provider_id) . "><i class='fas fa-money-bill-alt earning-icon'></i></a>";
//                    }
                    return $btn . $providerPayoutReportButton;

                })
                ->rawColumns(['provider_name', 'action'])
                ->make(true);
        }
        if ($request->is('api/*')) {

            $earningData[] = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->display_name,
                'email' => $provider->email,
                'commission' => optional($provider->providertype)->commission,
                'commission_type' => optional($provider->providertype)->type,
                'total_bookings' => $bookings->count(),
                'total_earning' => round($totalearning, 2),
                'taxes' => round($booking_data['tax'], 2),
                'admin_earning' => round($adminearning, 2),
                'provider_earning' => round($providerearning, 2),
                'provider_earning_formate' => round($provider_earning['number_format'], 2),
            ];
            return comman_custom_response($earningData);
        }
    }


    public function handymanEarning() {
        $pageTitle = __('messages.earning');
        return view('earning.handyman', compact('pageTitle'));
    }

    public function handymanEarningData(Request $request) {
        $auth_user = authSession();

        $earningData = array();

        $bookings = Booking::with('handymanAdded')->has('handymanAdded')->where('provider_id', $auth_user->id)->whereNotNull('payment_id')->get();
        $provider_earning = ProviderPayout::where('provider_id', $auth_user->id)->sum('amount') ?? 0;


        $handyman = collect($bookings)->map(function ($handyman) {
            return [$handyman->handymanAdded->pluck('handyman_id')];
        })->toArray();

        $userIds = [];
        foreach ($handyman as $item) {
            $userId = $item[0]->get(0);
            $userIds[] = $userId;

        }

        $user = User::whereIn('id', array_values($userIds))->get();

        foreach ($user as $key => $value) {
            $handymantype_id = !empty($value->handymantype_id) ? $value->handymantype_id : 1;

            $handyman_type = HandymanType::withTrashed()->where('id', $handymantype_id)->first();

            $commission = $handyman_type->commission;

            $commission_type = $handyman_type->type;

            $handyman_bookings = BookingHandymanMapping::with('bookings')->where('handyman_id', $value->id)->whereHas('bookings', function ($q) {
                $q->whereNotNull('payment_id');
            })->get();

            $totalEarning = HandymanPayout::where('handyman_id', $value->id)->sum('amount') ?? 0;

            $all_booking_total = $handyman_bookings->map(function ($booking) {
                return optional($booking->bookings)->total_amount;
            })->toArray();

            $total = array_reduce($all_booking_total, function ($value1, $value2) {
                return $value1 + $value2;
            }, 0);

            $earning = ($commission * count($handyman_bookings));

            if ($commission_type === 'percent') {
                $earning = ($total) * $commission / 100;
            }

            $final_amount = $earning - $totalEarning;


            $earningData[] = [
                'handyman_id' => $value->id,
                'handyman_name' => $value->display_name,
                'commission' => get_handyman_provider_commission($value->handymantype_id),
                'total_bookings' => count($handyman_bookings),
                'total' => getPriceFormat($total),
                'total_earning' => $final_amount <= 0 ? getPriceFormat($earning) : getPriceFormat($final_amount) . ' <span class="text-danger">(' . __("messages.pending") . ')</span>',
                'handyman_earning_formate' => $final_amount,
            ];
        }


        if ($request->ajax()) {
            return Datatables::of($earningData)
                ->addIndexColumn()
                ->addColumn('action', function ($row) use ($provider_earning) {
                    $btn = '-';
                    $handyman_id = $row['handyman_id'];
                    if ($row['handyman_earning_formate'] > 0 && $provider_earning > 0) {
                        $btn = "<a href=" . route('handymanpayout.create', $handyman_id) . "><i class='fas fa-money-bill-alt earning-icon'></i></a>";
                    }
                    return $btn;
                })
                ->rawColumns(['action', 'total_earning'])
                ->make(true);
        }
    }


    public function show($id) {
        //
        $providerdata = User::where('user_type', 'provider')->where('id', $id)->first();
        $pageTitle = __('messages.list_form_title', ['form' => __('messages.providerpayout_list')]);
        $auth_user = authSession();
        $assets = ['datatable'];
        return view('providerpayout.view', compact('pageTitle', 'auth_user', 'assets', 'id', 'providerdata'));
    }
}
