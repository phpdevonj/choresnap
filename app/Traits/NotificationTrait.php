<?php

namespace App\Traits;

use App\Currency\CurrencyChange;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationTemplate;
use App\Helper\GoogleFcm;

trait NotificationTrait {
    function sendNotification($data) {
        $sitesetup = \App\Models\Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
        $app_setting = $sitesetup ? json_decode($sitesetup->value) : null;
        date_default_timezone_set($app_setting->time_zone ?? 'UTC');
        $data['datetime'] = date('Y-m-d H:i:s');
        $admin = User::where('user_type', 'admin')->first();
        $notification_type = $data['activity_type'];

        if (isset($data['booking'])) {
            $booking = $data['booking'];
            $id = $booking->id;
            $providerId = [$booking->provider_id];
            $userId = $booking->customer_id;
        } else if (isset($data['wallet'])) {
            $id = $data['wallet']->id;
            $user_id = $data['wallet']->user_id;
            $user = User::find($user_id);
            if ($user->user_type == 'provider') {
                $providerId = [$user_id];
            } else if ($user->user_type == 'handyman') {
                $handymanId = [$user_id];
            } else if ($user->user_type == 'user') {
                $userId = $user_id;
            }
            $data['user_id'] = $user_id;
        } else if (isset($data['bid_data'])) {
            $bid_data = $data['bid_data'];
            $id = $bid_data->id;
            $userId = $bid_data->customer_id;
        } else if (isset($data['post_job'])) {
            $post_job = $data['post_job'];
            $id = $post_job->id;
            $providerId = [$post_job->provider_id];
            $userId = $post_job->customer_id;
        }


        switch ($data['activity_type']) {
            case "add_booking":
//                $customer_name = $booking->customer->display_name;
//
//                $data['activity_message'] = __('messages.booking_added', ['name' => $customer_name]);
//                $data['activity_type'] = __('messages.add_booking');
//                $activity_data = [
//                    'service_id' => $booking->service_id,
//                    'service_name' => isset($booking->service) ? $booking->service->name : '',
//                    'customer_id' => $booking->customer_id,
//                    'customer_name' => isset($booking->customer) ? $booking->customer->display_name : '',
//                    'provider_id' => $booking->provider_id,
//                    'provider_name' => isset($booking->provider) ? $booking->provider->display_name : '',
//                ];
                $provider_name = isset($booking->provider) ? $booking->provider->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_message'] = __('messages.booking_added', ['booking_services_name' => $booking_services_name, 'provider_name' => $provider_name]);
                $data['activity_type'] = __('messages.add_booking');
                $activity_data = [
                    'service_id' => $booking->service_id,
                    'service_name' => isset($booking->service) ? $booking->service->name : '',
                    'customer_id' => $booking->customer_id,
                    'customer_name' => isset($booking->customer) ? $booking->customer->display_name : '',
                    'provider_id' => $booking->provider_id,
                    'provider_name' => isset($booking->provider) ? $booking->provider->display_name : '',
                ];
                break;

            case "add_booking_provider":
//                $customer_name = $booking->customer->display_name;
//
//                $data['activity_message'] = __('messages.booking_added', ['name' => $customer_name]);
//                $data['activity_type'] = __('messages.add_booking_provider');
//                $activity_data = [
//                    'service_id' => $booking->service_id,
//                    'service_name' => isset($booking->service) ? $booking->service->name : '',
//                    'customer_id' => $booking->customer_id,
//                    'customer_name' => isset($booking->customer) ? $booking->customer->display_name : '',
//                    'provider_id' => $booking->provider_id,
//                    'provider_name' => isset($booking->provider) ? $booking->provider->display_name : '',
//                ];
                $customer_name = $booking->customer->display_name;
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_message'] = __('messages.booking_added_provider', ['booking_services_name' => $booking_services_name, 'customer_name' => $customer_name]);
                $data['activity_type'] = __('messages.add_booking_provider');
                $activity_data = [
                    'service_id' => $booking->service_id,
                    'service_name' => isset($booking->service) ? $booking->service->name : '',
                    'customer_id' => $booking->customer_id,
                    'customer_name' => isset($booking->customer) ? $booking->customer->display_name : '',
                    'provider_id' => $booking->provider_id,
                    'provider_name' => isset($booking->provider) ? $booking->provider->display_name : '',
                ];
                break;

            case "assigned_booking":
//                $assigned_handyman = handymanNames($booking->handymanAdded);
//                $data['activity_message'] = __('messages.booking_assigned', ['name' => $assigned_handyman, 'id' => $booking->id]);
//                $data['activity_type'] = __('messages.assigned_booking');
//                $handymanId = $booking->handymanAdded->pluck('handyman_id');
//
//                $activity_data = [
//                    'handyman_id' => $booking->handymanAdded->pluck('handyman_id'),
//                    'handyman_name' => $booking->handymanAdded,
//                ];
                $provider_name = isset($booking->provider) ? $booking->provider->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_message'] = __('messages.booking_assigned', ['provider_name' => $provider_name, 'booking_services_name' => $booking_services_name]);
                $data['activity_type'] = __('messages.assigned_booking');
                $handymanId = $booking->handymanAdded->pluck('handyman_id');

                $activity_data = [
                    'handyman_id' => $booking->handymanAdded->pluck('handyman_id'),
                    'handyman_name' => $booking->handymanAdded,
                    'provider_name' => $provider_name,
                    'booking_services_name' => $booking_services_name,
                ];

                break;

            case "assigned_booking_provider":
//                $assigned_handyman = handymanNames($booking->handymanAdded);
//                $data['activity_message'] = __('messages.booking_assigned', ['name' => $assigned_handyman, 'id' => $booking->id]);
//                $data['activity_type'] = __('messages.assigned_booking_provider');
//                $handymanId = $booking->handymanAdded->pluck('handyman_id');
//
//                $activity_data = [
//                    'handyman_id' => $booking->handymanAdded->pluck('handyman_id'),
//                    'handyman_name' => $booking->handymanAdded,
//                ];
                $customer_name = $booking->customer->display_name;
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_message'] = __('messages.booking_assigned_provider', ['customer_name' => $customer_name, 'booking_services_name' => $booking_services_name]);
                $data['activity_type'] = __('messages.assigned_booking_provider');
                $handymanId = $booking->handymanAdded->pluck('handyman_id');

                $activity_data = [
                    'handyman_id' => $booking->handymanAdded->pluck('handyman_id'),
                    'handyman_name' => $booking->handymanAdded,
                    'customer_name' => $customer_name,
                    'booking_services_name' => $booking_services_name,
                ];

                break;

            case "transfer_booking":
                $assigned_handyman = handymanNames($booking->handymanAdded);

                $data['activity_type'] = __('messages.transfer_booking');
                $data['activity_message'] = __('messages.booking_transfer', ['name' => $assigned_handyman]);
                $handymanId = $booking->handymanAdded->pluck('handyman_id');
                $activity_data = [
                    'handyman_id' => $booking->handymanAdded->pluck('handyman_id'),
                    'handyman_name' => $booking->handymanAdded,
                ];
                break;

            case "update_booking_status":
                $status = \App\Models\BookingStatus::bookingStatus($booking->status);
                $old_status = \App\Models\BookingStatus::bookingStatus($booking->old_status);

                $statusKey = 'status_' . strtolower($status);
                $oldStatusKey = 'status_' . strtolower($old_status);

                $status = __('messages.'.$statusKey);
                $old_status = __('messages.'.$oldStatusKey);

                $data['activity_type'] = __('messages.update_booking_status');
//                $data['activity_message'] = __('messages.booking_status_update', ['id' => $booking->id, 'from' => $old_status, 'to' => $status]);
                $customer_name = $booking->customer->display_name;
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_message'] = __('messages.booking_status_update_message', ['id' => $booking->id, 'from' => $old_status, 'to' => $status, 'booking_services_name' => $booking_services_name, 'customer_name' => $customer_name]);
                // Raw status values so the message can be re-localized per recipient (see CommonNotification)
                $data['booking_status_value'] = $booking->status;
                $data['old_booking_status_value'] = $booking->old_status;
                $handymanId = $booking->handymanAdded ? $booking->handymanAdded->pluck('handyman_id') : null;
                $activity_data = [
                    'reason' => $booking->reason,
                    'status' => $booking->status,
                    'status_label' => $status,
                    'old_status' => $booking->old_status,
                    'old_status_label' => $old_status,
                    'booking_services_name' => $booking_services_name,
                    'customer_name' => $customer_name,
                ];

                break;

            case "cancel_booking":
//                $status = \App\Models\BookingStatus::bookingStatus($booking->status);
//                $old_status = \App\Models\BookingStatus::bookingStatus($booking->old_status);
//                $data['activity_type'] = __('messages.cancel_booking');
//                $data['activity_message'] = __('messages.cancel_booking');
//                $handymanId = $booking->handymanAdded ? $booking->handymanAdded->pluck('handyman_id') : null;
//                $activity_data = [
//                    'reason' => $booking->reason,
//                    'status' => $booking->status,
//                    'status_label' => \App\Models\BookingStatus::bookingStatus($booking->status),
//                ];
                $provider_name = isset($booking->provider) ? $booking->provider->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $status = \App\Models\BookingStatus::bookingStatus($booking->status);
                $old_status = \App\Models\BookingStatus::bookingStatus($booking->old_status);
                $data['activity_type'] = __('messages.cancel_booking');
                $data['activity_message'] = __('messages.cancel_booking_message', ['booking_services_name' => $booking_services_name,'provider_name' => $provider_name]);
                $handymanId = $booking->handymanAdded ? $booking->handymanAdded->pluck('handyman_id') : null;
                $activity_data = [
                    'reason' => $booking->reason,
                    'status' => $booking->status,
                    'status_label' => \App\Models\BookingStatus::bookingStatus($booking->status),
                    'booking_services_name' => $booking_services_name,
                    'provider_name' => $provider_name,
                ];
                break;

            case "payment_message_status":
//                $data['activity_type'] = __('messages.payment_message_status');
//                $data['activity_message'] = __('messages.payment_message', ['status' => $data['payment_status']]);
//                $activity_data = [
//                    'activity_type' => $data['activity_type'],
//                    'payment_status' => $data['payment_status'],
//                    'booking_id' => $data['booking_id'],
//                ];

                $provider_name = isset($booking->provider) ? $booking->provider->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_type'] = __('messages.payment_message_status', ['status' => $data['payment_status']]);
                $data['activity_message'] = __('messages.payment_message_status_message', ['booking_services_name' => $booking_services_name,'provider_name' => $provider_name]);
                $activity_data = [
                    'activity_type' => $data['activity_type'],
                    'payment_status' => $data['payment_status'],
                    'booking_id' => $data['booking_id'],
                    'booking_services_name' => $booking_services_name,
                    'provider_name' => $provider_name,
                ];
                break;

            case "payment_message_status_provider":
//                $data['activity_type'] = __('messages.payment_message_status_provider');
//                $data['activity_message'] = __('messages.payment_message', ['status' => $data['payment_status']]);
                $customer_name = isset($booking->customer) ? $booking->customer->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_type'] = __('messages.payment_message_status_provider', ['status' => $data['payment_status']]);
                $data['activity_message'] = __('messages.payment_message_status_provider_message', ['booking_services_name' => $booking_services_name,'customer_name' => $customer_name]);

                $activity_data = [
                    'activity_type' => $data['activity_type'],
                    'payment_status' => $data['payment_status'],
                    'booking_id' => $data['booking_id'],
                    'booking_services_name' => $booking_services_name,
                    'customer_name' => $customer_name,
                ];
                break;


            case "add_wallet":
                $data['activity_message'] = __('messages.wallet_added', ['amount' => getPriceFormat($data['wallet']->amount)]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'provider_name' => isset($data['wallet']->provider) ? $data['wallet']->provider->display_name : '',
                    'amount' => $data['wallet']->amount,
                    'credit_debit_amount' => $data['wallet']->amount,
                ];
                break;

            case "update_wallet":
                $data['activity_message'] = trans('messages.wallet_top_up', ['amount' => getPriceFormat($data['added_amount'])]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'provider_name' => isset($data['wallet']->provider) ? $data['wallet']->provider->display_name : '',
                    'amount' => $data['wallet']->amount,
                    'credit_debit_amount' => (float)$data['added_amount'],
                ];
                break;

            case "wallet_payout_transfer":
                $data['activity_message'] = __('messages.wallet_amount', ['value' => getPriceFormat($data['transfer_amount'])]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'provider_name' => isset($data['wallet']->provider) ? $data['wallet']->provider->display_name : '',
                    'amount' => $data['wallet']->amount,
                    'credit_debit_amount' => (float)$data['transfer_amount'],
                ];
                break;

            case "wallet_top_up":

                $data['activity_message'] = trans('messages.wallet_top_up', ['amount' => getPriceFormat($data['top_up_amount'])]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'provider_name' => isset($data['wallet']->provider) ? $data['wallet']->provider->display_name : '',
                    'amount' => $data['wallet']->amount,
                    'transaction_id' => $data['transaction_id'],
                    'transaction_type' => $data['transaction_type'],
                    'credit_debit_amount' => (float)$data['top_up_amount'],
                ];


                break;

            case "wallet_refund":
                $data['activity_message'] = trans('messages.wallet_refund', ['value' => $data['booking_id']]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'amount' => $data['wallet']->amount,
                    'credit_debit_amount' => $data['refund_amount'],
                    'transaction_type' => __('messages.credit'),
                ];
                break;

            case "paid_for_booking":
                $data['activity_message'] = trans('messages.paid_for_booking', ['value' => $data['booking_id']]);
                $activity_data = [
                    'title' => $data['wallet']->title,
                    'user_id' => $data['wallet']->user_id,
                    'amount' => $data['wallet']->amount,
                    'credit_debit_amount' => $data['booking_amount'],
                    'transaction_type' => __('messages.debit'),
                ];
                break;


            case "job_requested":
                $data['activity_message'] = __('messages.post_request_message', ['name' => $post_job->customer->display_name,]);
                $data['activity_type'] = __('messages.post_request_title');

                $customerLatitude = 50.930557;
                $customerLongitude = -102.80777;
                $radius = 50;
                $providers = \App\Models\ProviderAddressMapping::selectRaw("id, provider_id, address, latitude, longitude,
                                ( 6371 * acos( cos( radians($customerLatitude) ) *
                                cos( radians( latitude ) )
                                * cos( radians( longitude ) - radians($customerLongitude)
                                ) + sin( radians($customerLatitude) ) *
                                sin( radians( latitude ) ) )
                                ) AS distance")
                    ->having("distance", "<=", $radius)
                    ->orderBy("distance", 'asc')
                    ->get();
                $providerId = $providers->pluck('providers.id')->toArray();

                $activity_data = [
                    'post_request_id' => $post_job->post_request_id,
                    'post_job_name' => $post_job->title,
                    'customer_id' => $post_job->customer_id,
                    'customer_name' => isset($post_job->customer) ? $post_job->customer->display_name : '',
                ];
                break;
            case "user_accept_bid":
                $data['activity_message'] = __('messages.bid_accepted_message', ['name' => $post_job->customer->display_name,]);
                $data['activity_type'] = __('messages.bid_accepted_title');
                $activity_data = [
                    'post_request_id' => $post_job->post_request_id,
                    'customer_id' => $post_job->customer_id,
                    'customer_name' => isset($post_job->customer) ? $post_job->customer->display_name : '',
                ];
                break;
            case "provider_send_bid":
                $data['activity_message'] = __('messages.incomming_bid_message', ['name' => $bid_data->provider->display_name, 'price' => getPriceFormat($bid_data->price)]);
                $data['activity_type'] = __('messages.incomming_bid_title', ['name' => $bid_data->provider->display_name]);
                $activity_data = [
                    'post_request_id' => $bid_data->post_request_id,
                    'provider_id' => $bid_data->provider_id,
                    'provider_name' => isset($bid_data->provider) ? $bid_data->provider->display_name : '',
                ];
                break;


            case "provider_payout":
                $id = $data['id'];
                $providerId = [$data['user_id']];

                $data['activity_message'] = __('messages.payout_paid', ['type' => 'Admin', 'amount' => getPriceFormat($data['amount'])]);

                $activity_data = [
                    'user_id' => $data['user_id'],
                    'amount' => $data['amount'],
                ];

                break;
            case "handyman_payout":
                $id = $data['id'];
                $handymanId = [$data['user_id']];

                $data['activity_message'] = __('messages.payout_paid', ['type' => 'Provider', 'amount' => getPriceFormat($data['amount'])]);
                $activity_data = [
                    'user_id' => $data['user_id'],
                    'amount' => $data['amount'],
                ];

                break;
            case "subscription_add":
                $id = $data['subscription_data']->id;
                $providerId = [$data['subscription_data']->user_id];
                $data['activity_message'] = __('messages.subscription_added');
                $activity_data = [
                    'user_id' => [$data['subscription_data']->user_id],
                    'title' => $data['subscription_data']->title,
                ];
                break;
            case "resgister":
                $id = $data['user_id'];
                $data['activity_message'] = __('messages.registeration_msg');
                if ($data['user_type'] == 'provider') {
                    $providerId = [$data['user_id']];
                } else if ($data['user_type'] == 'handyman') {
                    $handymanId = [$data['user_id']];
                } else if ($data['user_type'] == 'user') {
                    $userId = $data['user_id'];
                }
                $activity_data = [
                    'user_id' => $data['user_id'],
                    'user_type' => $data['user_type'],
                ];
                break;

            case "rejecte_booking":
//                $activity_data = [];
//                $data['activity_message'] = __('messages.rejecte_booking');
                $provider_name = isset($booking->provider) ? $booking->provider->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_type'] = __('messages.rejecte_booking');
                $data['activity_message'] = __('messages.rejecte_booking_message', ['booking_services_name' => $booking_services_name,'provider_name' => $provider_name]);
                $activity_data = [
                    'activity_type' => $data['activity_type'],
                    'activity_message' => $data['activity_message'],
                    'booking_id' => $data['booking_id'],
                    'booking_services_name' => $booking_services_name,
                    'provider_name' => $provider_name,
                ];
                break;

            case "rejecte_booking_provider":
//                $activity_data = [];
//                $data['activity_message'] = __('messages.rejecte_booking_provider');
                $customer_name = isset($booking->customer) ? $booking->customer->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_type'] = __('messages.rejecte_booking');
                $data['activity_message'] = __('messages.rejecte_booking_provider_message', ['booking_services_name' => $booking_services_name,'customer_name' => $customer_name]);
                $activity_data = [
                    'activity_type' => $data['activity_type'],
                    'activity_message' => $data['activity_message'],
                    'booking_id' => $data['booking_id'],
                    'booking_services_name' => $booking_services_name,
                    'customer_name' => $customer_name,
                ];
                break;

            case "cancel_booking_provider":
//                $activity_data = [];
//                $data['activity_message'] = __('messages.cancel_booking_provider');
                $customer_name = isset($booking->customer) ? $booking->customer->display_name : '';
                $booking_services_name = isset($booking->service) ? $booking->service->name : '';
                $data['activity_type'] = __('messages.cancel_booking');
                $data['activity_message'] = __('messages.cancel_booking_provider_message', ['booking_services_name' => $booking_services_name,'customer_name' => $customer_name]);
                $handymanId = $booking->handymanAdded ? $booking->handymanAdded->pluck('handyman_id') : null;
                $activity_data = [
                    'booking_services_name' => $booking_services_name,
                    'customer_name' => $customer_name,
                ];
                break;


            default:
                $activity_data = [];
                break;
        }
        $data['activity_data'] = json_encode($activity_data);
        if (isset($data['booking']) || isset($data['bid_data']) || isset($data['post_job'])) {
            \App\Models\BookingActivity::create($data);
        } else if (isset($data['wallet'])) {
            \App\Models\WalletHistory::create($data);
        }
        $generalsetting = \App\Models\Setting::where('type', 'general-setting')->where('key', 'general-setting')->first();
        $generalsetting = json_decode($generalsetting->value);
        // The switch above already translates $data['activity_type'] for some
        // activity types (e.g. "Boekingsstatus Update") while leaving others as
        // a raw key (e.g. "add_wallet"). Only translate when the key actually
        // exists; __() returns the key itself when missing, so fall back to the
        // already-translated activity_type instead of storing "messages.xxx".
        $typeKey = 'messages.' . $data['activity_type'];
        $typeTranslated = __($typeKey);
        $notification_data = [
            'id' => $id,
            'type' => $typeTranslated === $typeKey ? $data['activity_type'] : $typeTranslated,
//            'booking_status' => $data['activity_type'],
            'message' => $data['activity_message'],
            "ios_badgeType" => "Increase",
            "ios_badgeCount" => 1,
            "notification-type" => $notification_type,

            'logged_in_user_fullname' => $admin ? $admin['display_name'] ?: default_user_name() : '',
            'logged_in_user_role' => $admin ? ucfirst($admin->user_type) ?? '-' : '',
            'company_name' => config('app.name'),
            'company_contact_info' => implode('', [
                $generalsetting->helpline_number . PHP_EOL,
                $generalsetting->inquriy_email,
            ]),
            'site_url' => $generalsetting->website ?? ''
        ];

        if (isset($booking)) {
            $booking_datetime = $booking->date;
            if (!empty($booking->timezone)) {
                $tzOffset = trim(str_replace(['UTC', 'utc', ' '], '', $booking->timezone));
                if (!empty($tzOffset)) {
                    try {
                        $booking_datetime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $booking->date, 'UTC')->setTimezone($tzOffset)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Timezone conversion error: ' . $e->getMessage());
                    }
                }
            }
            list($date, $time) = explode(' ', $booking_datetime);

            $notification_data['customer_name'] = isset($booking->customer) ? $booking->customer->display_name : '';
            $notification_data['provider_name'] = isset($booking->provider) ? $booking->provider->display_name : '';
            $notification_data['description'] = $booking->description ?? '';
            $notification_data['booking_id'] = $booking->id ?? '';
            $notification_data['booking_date'] = $date;
            $notification_data['booking_time'] = $time;
            $status = \App\Models\BookingStatus::bookingStatus($booking->status);
            $statusText = $status ? ucfirst($status) : null;
            $notification_data['booking_status'] = $statusText ?? ucfirst(str_replace('_', ' ', $booking->status));
            $notification_data['booking_services_names'] = isset($booking->service) ? $booking->service->name : '';

            $currencyChange = new CurrencyChange();
            $notification_data['cancellation_reason'] = $booking->reason;
            $notification_data['total_amount'] = $currencyChange->defaultSymbol() . ' ' . $booking->total_amount;
            $serviceAmount = $booking->amount;
            if (!empty($booking->final_total_service_price)) {
                $serviceAmount = $booking->final_total_service_price;
            } else if ($booking->quantity > 1) {
                $serviceAmount = $booking->amount * $booking->quantity;
            }

            $discount_percentage = 0;
            if (isset($booking->service) && $booking->service->discount > 0) {
                $discount_percentage = $booking->service->discount;
            } elseif (isset($booking->discount) && $booking->discount > 0) {
                $discount_percentage = $booking->discount;
            }

            if ($discount_percentage > 0) {
                $discountAmount = ($serviceAmount * $discount_percentage) / 100;
                $serviceAmount = $serviceAmount - $discountAmount;
            }

            $notification_data['service_amount'] = $serviceAmount;


            $notification_data['venue_address'] = $booking->address;
            $notification_data['check_booking_type'] = 'booking';

            $duration = '';
            try {
                if ($booking->service) {
                    $duration_diff = 0;
                    if ($booking->service->type === 'hourly') {
                        $duration_diff = ($booking->quantity > 0 ? $booking->quantity : 1) * 60;
                    } else if ($booking->service->duration) {
                        $durationParts = explode(':', $booking->service->duration);
                        if (count($durationParts) >= 2) {
                            $duration_diff = (int)$durationParts[0] * 60 + (int)$durationParts[1];
                        } else {
                            $duration_diff = (int)$booking->service->duration * 60;
                        }

                        if ($booking->quantity > 1) {
                            $duration_diff = $duration_diff * $booking->quantity;
                        }
                    }

                    if ($duration_diff > 0) {
                        $hours = floor($duration_diff / 60);
                        $minutes = $duration_diff % 60;
                        $duration = "$hours hr $minutes min";
                    } else if ($booking->service->duration) {
                        $durationParts = explode(':', $booking->service->duration);
                        if (count($durationParts) >= 2) {
                            $duration = (int)$durationParts[0] . " hr " . (int)$durationParts[1] . " min";
                        } else {
                            $duration = $booking->service->duration . " min";
                        }
                    }
                }
            } catch (\Exception $exception) {

            }

            $notification_data['booking_duration'] = $duration;

            $notification_data['review_link'] = 'N/A';
            $notification_data['link'] = 'N/A';

        }

        $notification_data = array_merge($data, $notification_data);

        $mailable = NotificationTemplate::where('type', $notification_type)->with('defaultNotificationTemplateMap')->first();

        if ($mailable != null && $mailable->to != null) {
            $mails = json_decode($mailable->to);

            foreach ($mails as $key => $mailTo) {

                switch ($mailTo) {
                    case 'admin':

                        $admin = \App\Models\User::role('admin')->first();

                        if (isset($admin->email)) {
                            try {

                                $userInfo = ['use_id' => $admin->id, 'user_name' => $admin->display_name];
                                $notification_data = array_merge($notification_data, $userInfo);

                                $admin->notify(new \App\Notifications\CommonNotification($notification_type, $notification_data));
                            } catch (\Exception $e) {
                                Log::error($e);
                            }
                        }

                        break;

                    case 'provider':
                        if (isset($providerId)) {
                            foreach ($providerId as $id) {
                                $employee = \App\Models\User::find($id);
                                if (isset($employee->email)) {
                                    try {
                                        $userInfo = ['use_id' => $employee->id, 'user_name' => $employee->display_name];
                                        $notification_data = array_merge($notification_data, $userInfo);

//                                        $result = GoogleFcm::sendNotification($employee->fcm_token, $notification_data['type'], $notification_data['message']);

                                        $employee->notify(new \App\Notifications\CommonNotification($notification_type, $notification_data));
                                    } catch (\Exception $e) {
                                        Log::error($e);
                                    }
                                }
                            }
                        }
                        break;

                    case 'handyman':
                        if (isset($handymanId)) {
                            foreach ($handymanId as $id) {
                                $employee = \App\Models\User::find($id);
                                if (isset($employee->email) && $employee->user_type == 'handyman') {
                                    try {
                                        $userInfo = ['use_id' => $employee->id, 'user_name' => $employee->display_name];
                                        $notification_data = array_merge($notification_data, $userInfo);
                                        $employee->notify(new \App\Notifications\CommonNotification($notification_type, $notification_data));
                                    } catch (\Exception $e) {
                                        Log::error($e);
                                    }
                                }
                            }
                        }
                        break;

                    case 'user':
                        if (isset($userId)) {
                            $user = \App\Models\User::find($userId);
                            try {
                                $userInfo = ['use_id' => $user->id, 'user_name' => $user->display_name];
                                $notification_data = array_merge($notification_data, $userInfo);
                                $user->notify(new \App\Notifications\CommonNotification($notification_type, $notification_data));
                            } catch (\Exception $e) {
                                Log::error($e);
                            }
                        }
                        break;
                }
            }
        }
    }
}
