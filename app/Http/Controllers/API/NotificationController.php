<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Http\Resources\API\NotificationResource;

class NotificationController extends Controller {
    public function notificationList(Request $request) {
        $user = auth()->user();

        $user->update(['last_notification_seen' => now()]);

        date_default_timezone_set(getTimeZone());
        $type = isset($request->type) ? $request->type : null;
        $id = $request->id ? $request->id : null;
        if ($type == "markas_read") {
            if ($id) {
                $notification = $user->unreadNotifications()->where('id', $id)->first();
                if ($notification) {
                    $notification->markAsRead();
                }
            } else {
                if (count($user->unreadNotifications) > 0) {
                    $user->unreadNotifications->markAsRead();
                }
            }
        }

        $page = 1;
        $limit = 100;

        //$notifications = $user->Notifications->sortByDesc('created_at')->forPage($page,$limit);
        // We are filtering notifications to include only bookings with a valid payment_id.
        // This change ensures that bookings without advance payment are NOT shown in the
        // provider's notifications, so the provider cannot accept or decline unpaid bookings.
        $notifications = $user->notifications()
            ->whereIn('data->id', function ($query) {
                $query->select('id')
                    ->from('bookings')
                    ->whereNotNull('payment_id');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $all_unread_count = isset($user->unreadNotifications) ? $user->unreadNotifications->count() : 0;

        $items = NotificationResource::collection($notifications);


        $all_unread_count = $user->unreadNotifications()
            ->where(function ($query) {
                $query->whereIn('data->id', function ($subQuery) {
                    $subQuery->select('id')
                        ->from('bookings')
                        ->whereNotNull('payment_id');
                });
            })
            ->count();

        $response = [
            'notification_data' => $items,
            'all_unread_count' => $all_unread_count,
        ];
        return comman_custom_response($response);
    }

}
