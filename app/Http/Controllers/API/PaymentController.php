<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\Wallet;
use App\Models\PaymentHistory;
use App\Models\PaymentGateway;
use App\Http\Resources\API\PaymentResource;
use App\Http\Resources\API\PaymentHistoryResource;
use App\Http\Resources\API\GetCashPaymentHistoryResource;
use App\Traits\NotificationTrait;
use App\Http\Resources\API\PaymentGatewayResource;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use NotificationTrait;

    public function savePayment(Request $request)
    {
        $data = $request->all();
        $data['datetime'] = isset($request->datetime) ? date('Y-m-d H:i:s',strtotime($request->datetime)) : date('Y-m-d H:i:s');
        $payment = Payment::where('txn_id', $request->txn_id)->first();
        if ($payment) {
            $payment->update([
                'payment_status' => $request->payment_status,
                'discount' => $request->discount,
            ]);
            $result = $payment;
        } else {
            $result = Payment::create($data);
        }
        // $result = Payment::create($data);
        $booking = Booking::find($request->booking_id);
        if(!empty($result) && $result->payment_status == 'advanced_paid'){
            $booking->advance_paid_amount  = $request->advance_payment_amount;
            $booking->status  = 'pending';
        }
        $booking->payment_id = $result->id;
        $booking->update();
        $status_code = 200;
        if($request->payment_type == 'wallet'){
            $wallet = Wallet::where('user_id',$booking->customer_id)->first();
            if($wallet !== null){
                $wallet_amount = $wallet->amount;
                if($wallet_amount >= $request->total_amount){
                    $wallet->amount = $wallet->amount - $request->total_amount;
                    $wallet->update();
                    $activity_data = [
                        'activity_type' => 'paid_for_booking',
                        'wallet' => $wallet,
                        'booking_id'=>$request->booking_id,
                        'booking_amount'=>$request->total_amount,
                    ];
                    $this->sendNotification($activity_data);

                }else{
                    $message = __('messages.wallent_balance_error');
                }
            }
        }
        $message = __('messages.payment_completed');
        $activity_data = [
            'activity_type' => 'payment_message_status',
            'payment_status'=>  str_replace("_"," ",ucfirst($data['payment_status'])),
            'booking_id' => $booking->id,
            'booking' => $booking,
        ];
        $this->sendNotification($activity_data);

        $activity_data_provider = [
            'activity_type' => 'payment_message_status_provider',
            'payment_status'=>  str_replace("_"," ",ucfirst($data['payment_status'])),
            'booking_id' => $booking->id,
            'booking' => $booking,
        ];
        $this->sendNotification($activity_data_provider);


        
        if ($result->payment_status !== 'failed' && $result->payment_status !== 'unpaid' && $result->payment_status !== 'failed' && $result->payment_status !== 'stripe') {
            $activity_data_customer = [
                'activity_type' => 'add_booking',
                'booking_id' => $booking->id,
                'booking' => $booking,
            ];
            $this->sendNotification($activity_data_customer);

            $activity_data_provider = [
                'activity_type' => 'add_booking_provider',
                'booking_id' => $booking->id,
                'booking' => $booking,
            ];
            $this->sendNotification($activity_data_provider);
        } else {
            // If payment fails, we cancel the booking so it's not active
            $booking->status = 'cancelled';
            $booking->save();
        }

        if($result->payment_status == 'failed' || $result->payment_status == 'unpaid' || $result->payment_status == 'stripe')
        {
            $status_code = 400;
        }
        return comman_message_response($message,$status_code);
    }

    /**
     * Mark a payment as failed when the customer cancels/abandons the payment
     * flow on the app (e.g. Stripe FailureCode.Canceled). Stripe does not send
     * a reliable webhook for a user-cancel, so the app calls this on the cancel
     * signal. Also cancels the booking so the reserved slot is released.
     *
     * POST /api/payment-failed  { booking_id, payment_id? }
     */
    public function markPaymentFailed(Request $request)
    {
        $request->validate([
            'booking_id' => 'required',
        ]);

        $payment = Payment::where('booking_id', $request->booking_id)
            ->when($request->payment_id, function ($q) use ($request) {
                $q->where('id', $request->payment_id);
            })
            ->latest('id')
            ->first();

        if (!$payment) {
            return comman_message_response(__('messages.record_not_found'), 404);
        }

        // Guard against a race: never overwrite an already-completed payment.
        if (in_array($payment->payment_status, ['paid', 'advanced_paid'])) {
            return comman_message_response(__('messages.update_form', ['form' => __('messages.payment')]));
        }

        $payment->update(['payment_status' => 'failed']);

        // Cancel the abandoned booking so the slot is freed and data stays clean.
        $booking = Booking::find($payment->booking_id);
        if ($booking && !in_array($booking->status, ['completed', 'cancelled'])) {
            $booking->status = 'cancelled';
            $booking->save();
        }

        return comman_message_response(__('messages.update_form', ['form' => __('messages.payment')]));
    }

    public function paymentList(Request $request)
    {
        $payment = Payment::myPayment()->with('booking')
                    ->whereIn('id', function($query) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('payments')
                            ->groupBy('booking_id');
                    });
        if($request->has('booking_id') && !empty($request->booking_id)){
            $payment->where('booking_id',$request->booking_id);
        }
        if($request->has('payment_type') && !empty($request->payment_type)){

            if($request->payment_type == 'cash'){
                $payment->where('payment_type',$request->payment_type);
            }
        }
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }

    public function transferPayment(Request $request){
        $sitesetup = Setting::where('type','site-setup')->where('key', 'site-setup')->first();
        $admin = json_decode($sitesetup->value);
        $data = $request->all();
        $auth_user = authSession();
        $user_id = $auth_user->id;

        date_default_timezone_set( $admin->time_zone ?? 'UTC');
        $data['datetime'] = date('Y-m-d H:i:s');

        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.HANDYMAN_SEND_PROVIDER')){
            $data['text'] = __('messages.payment_transfer',
            ['from' => get_user_name($data['sender_id']),'to' => get_user_name($data['receiver_id']),'amount' => getPriceFormat((float)$data['total_amount']) ]);
        }
        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.PROVIDER_APPROVED_CASH')){
            $data['text'] = __('messages.cash_approved',['amount' => getPriceFormat((float)$data['total_amount']),'name' => get_user_name($data['receiver_id']) ]);
        }
        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.PROVIDER_SEND_ADMIN')){
            $data['text'] =  __('messages.payment_transfer',['from' => get_user_name($data['sender_id']),'to' => get_user_name(admin_id()),
            'amount' => getPriceFormat((float)$data['total_amount']) ]);
        }
        $result = \App\Models\PaymentHistory::create($data);

        if($data['action'] == 'provider_approved_cash' && $data['status'] == 'approved_by_provider' ){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'approved_by_provider';
            $get_parent_history->update();

            $get_main_record =  \App\Models\PaymentHistory::where('id',$request->parent_id)->first();
            $get_main_record->status = 'approved_by_provider';
            $get_main_record->update();
        }
        if($data['action'] == 'provider_send_admin' && $data['status'] == 'pending_by_admin'){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'pending_by_admin';
            $get_parent_history->update();
        }
        if($data['action'] == 'handyman_send_provider' && $data['status'] == 'pending_by_provider'){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'send_to_provider';
            $get_parent_history->update();
        }
        $message = trans('messages.transfer');
        if($request->is('api/*')) {
            return comman_message_response($message);
		}
    }

    public function paymentHistory(Request $request){
        $booking_id = $request->booking_id;
        $payment = PaymentHistory::where('booking_id',$booking_id);

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentHistoryResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);

    }

    public function getCashPaymentHistory(Request $request){
        $payment_id = $request->payment_id;
        $payment = PaymentHistory::where('payment_id',$payment_id)->with('booking');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = GetCashPaymentHistoryResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);

    }


    public function paymentDetail(Request $request){
        $auth_user = authSession();
        $user_id = $auth_user->id;

        $get_all_payments = PaymentHistory::where('receiver_id',$user_id);
        if(!empty($request->status)){
            $get_all_payments = $get_all_payments->where('status',$request->status);
        }

        if(!empty($request->from) && !empty($request->to)){
            $get_all_payments = $get_all_payments->whereDate('datetime', '>=', $request->from)->whereDate('datetime', '<=',  $request->to);
        }
        if (auth()->user()->hasAnyRole(['handyman'])) {
            $get_all_payments = $get_all_payments->where('action' ,'handyman_approved_cash')->where('receiver_id',$user_id);
        }

        if (auth()->user()->hasAnyRole(['provider'])) {
            $get_all_payments = $get_all_payments->where('action' ,'handyman_send_provider')->where('receiver_id',$user_id);
        }


        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $get_all_payments->count();
            }
        }

        $get_all_payments = $get_all_payments->orderBy('id','desc')->paginate($per_page);


        $items = PaymentHistoryResource::collection($get_all_payments);

        $response = [
            'today_cash' => today_cash_total($user_id,$request->to,$request->from),
            'total_cash' => total_cash($user_id),
            'cash_detail' => $items
        ];

        return comman_custom_response($response);
    }

    public function getCashPayment(Request $request)
    {
        $payment = Payment::where('payment_type', 'cash');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }
    public function paymentGateways(Request $request){
        $payment = PaymentGateway::where('status',1)->where('type', '!=', 'razorPayX')->get();
        $payment = PaymentGatewayResource::collection($payment);

        return comman_custom_response($payment);
    }
}
