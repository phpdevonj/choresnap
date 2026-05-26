{{--@foreach($buttonTypes as $key => $value)--}}
{{--    {{ $value->value }}--}}
{{--    <button type="button" class="btn btn-primary btn-round btn-sm variable_button mt-2" id="variable_button"--}}
{{--            data-value="{{ '[[ '.$value->value.' ]]' }}">{{ $value->name }}</button>--}}
{{--@endforeach--}}

{{--Custom Added--}}
@php
    $buttonList = [
        // Mail Specific and Booking Specific
        'user_id' => 'User Id',
        'user_name' => 'User Name',
        'customer_name' => 'Customer Name',
        'provider_name' => 'Provider Name',
        'description' => 'Description',
        'booking_id' => 'Booking ID',
        'booking_date' => 'Booking Date',
        'booking_time' => 'Booking Time',
        'booking_services_names' => 'Booking Service Name',
        'booking_duration' => 'Booking Duration',
        'booking_status' => 'Booking Status',
        'venue_address' => 'Venue / Address',
        'cancellation_reason' => 'Cancellation Reason',
        'review_link' => 'Review Link',
        'link' => 'link',
        'total_amount' => 'Total Amount',
        'service_amount' => 'Service Amount',


        // Auth User Specific
//        'employee_name' => 'Staff Name',
        'logged_in_user_fullname' => 'Your Name',
        'logged_in_user_role' => 'Your Role',
        'company_name' => 'Company Name',
        'company_contact_info' => 'Company Contact Info',
        'user_password' => 'User Password',
        'site_url' => 'Site Url',
        ]
@endphp

@foreach($buttonList as $key => $value)
    <button type="button" class="btn btn-primary btn-round btn-sm variable_button mt-2" id="variable_button"
            data-value="{{ '[[ '.$key.' ]]' }}">{{ $value }}
    </button>
@endforeach
