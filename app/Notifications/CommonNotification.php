<?php

namespace App\Notifications;

use App\Broadcasting\CustomWebhook;
// use App\Broadcasting\OneSingleChannel;
use App\Helper\GoogleFcm;
use App\Mail\MailMailableSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\NotificationTemplate;
use App\Broadcasting\FcmChannel;

class CommonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $type;

    public $data;

    public $subject;

    public $notification;

    public $notification_message;

    public $notification_link;

    public $appData;

    public $custom_webhook;

    /**
     * Create a new notification instance.
     */
    public function __construct($type, $data)
    {

        $this->type = $type;
        $this->data = $data;
        $this->notification_message = $this->data['message'] != '' ? $this->data['message'] : __('messages.default_notification_body');

        $this->notification = NotificationTemplate::where('type', $this->type)->with('defaultNotificationTemplateMap')->first();
        $this->subject = $this->notification->defaultNotificationTemplateMap->subject;
        $this->notification_link = $this->notification->defaultNotificationTemplateMap->notification_link;
        foreach ($this->data as $key => $value) {
            $this->subject = str_replace('[[ '.$key.' ]]', $this->data[$key], $this->subject);
            $this->notification_message = str_replace('[[ '.$key.' ]]', $this->data[$key], $this->notification_message);
            $this->notification_link = str_replace('[[ '.$key.' ]]', $this->data[$key], $this->notification_link);
        }

        $this->subject = $this->subject != '' ? $this->subject : 'None';
        $this->notification_message = $this->notification_message != '' ? $this->notification_message : __('messages.default_notification_body');

        $this->appData = $this->notification->channels;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {

        $notificationSettings = $this->appData;

        $notification_settings = [];
        $notification_access = isset($notificationSettings[$this->type]) ? $notificationSettings[$this->type] : [];
        if (isset($notificationSettings)) {
            foreach ($notificationSettings as $key => $notification) {
                if ($notification) {

                    switch ($key) {

                        case 'PUSH_NOTIFICATION':
                            array_push($notification_settings, FcmChannel::class);

                            break;

                        case 'IS_CUSTOM_WEBHOOK':
                                array_push($notification_settings, CustomWebhook::class);

                            break;

                        case 'IS_MAIL':
                                array_push($notification_settings, 'mail');

                            break;
                    }
                }
            }
        }

        return array_merge($notification_settings, ['database']);
    }


    /**
     * Get mail notification
     *
     * @param  mixed  $notifiable
     * @return MailMailableSend
     */
    public function toMail($notifiable)
    {

        $email = '';

        if (isset($notifiable->email)) {
            $email = $notifiable->email;
        } else {
            $email = $notifiable->routes['mail'];
        }
        return (new MailMailableSend($this->notification, $this->data, $this->type))->to($email)
            ->bcc(isset($this->notification->bcc) ? json_decode($this->notification->bcc) : [])
            ->cc(isset($this->notification->cc) ? json_decode($this->notification->cc) : [])
            ->subject($this->subject);
    }

    public function toFcm($notifiable)
    {

        $localizedData = $this->localizedData($notifiable);
        $msg = $localizedData['message'];
        if (! isset($msg) && $msg == '') {
            $msg = __('message.notification_body');
        }
        $type = 'booking';
        if (isset($this->data['type']) && $this->data['type'] !== '') {
            $type = $this->data['type'];
        }
        $heading = $this->subject;

        logger('send fcm');
        logger('Token: '.$notifiable->fcm_token.'\n');
        logger('Heading: '.$heading.'\n');
        logger('Message: '.$msg.'\n');

        GoogleFcm::sendNotification($notifiable->fcm_token, $heading, $msg);

//        Old Code
//        return fcm([
//            'to' => $notifiable->fcm_token ?? '/topics/user_' . $notifiable->id, // device token preferred
//            'collapse_key' => 'type_a',
//            'notification' => [
//                'body' =>  $msg,
//                'title' => $heading ,
//            ],
//            'data' => [
//            'type' => $this->subject,
//            'additional_data' => $this->data
//            ],
//        ]);

    }



    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->localizedData($notifiable);


    }

    /**
     * Re-render notification data in the recipient's selected language.
     * Currently scoped to the booking status update so no other notification
     * type is affected. Falls back to the originally built data otherwise.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    protected function localizedData($notifiable)
    {
        $data = $this->data;

        if ($this->type === 'update_booking_status' && isset($data['booking_status_value'])) {
            $previousLocale = app()->getLocale();
            $locale = (isset($notifiable->language) && $notifiable->language) ? $notifiable->language : $previousLocale;
            app()->setLocale($locale);

            $statusLabel = $this->translateBookingStatus($data['booking_status_value']);
            $oldStatusLabel = $this->translateBookingStatus($data['old_booking_status_value'] ?? '');

            $data['message'] = __('messages.booking_status_update_message', [
                'id' => $data['booking_id'] ?? '',
                'from' => $oldStatusLabel,
                'to' => $statusLabel,
                'booking_services_name' => $data['booking_services_names'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
            ]);
            $data['booking_status'] = $statusLabel;

            app()->setLocale($previousLocale);
        }

        return $data;
    }

    /**
     * Translate a booking status value using the language files, falling back
     * to the static DB label when no safe translation key exists.
     *
     * @param  string  $value
     * @return string
     */
    protected function translateBookingStatus($value)
    {
        if ($value === '' || $value === null) {
            return '';
        }

        $key = 'messages.status_' . $value;
        $translated = __($key);

        // Missing key (returns the key itself) or a key carrying placeholders
        // is unsafe to use as a plain status label -> fall back to the DB label.
        if ($translated === $key || strpos($translated, ':') !== false) {
            return \App\Models\BookingStatus::bookingStatus($value);
        }

        return $translated;
    }
}
