<footer class="footer text-white">
    @php
    $settings = App\Models\Setting::whereIn('type', ['general-setting', 'social-media', 'site-setup'])
        ->whereIn('key', ['general-setting', 'social-media', 'site-setup'])
        ->get()
        ->keyBy('type');
    $generalsetting = $settings->has('general-setting') ? json_decode($settings['general-setting']->value) : null;
    $socialmedia = $settings->has('social-media') ? json_decode($settings['social-media']->value) : null;
    $appsetting = $settings->has('site-setup') ? json_decode($settings['site-setup']->value) : null;
        $copyright_text = $appsetting ? $appsetting->site_copyright : null;
        $position = strpos($copyright_text, 'by');
        if ($position !== false) {
            $first_part = substr($copyright_text, 0, $position + 2);
            $second_part = substr($copyright_text, $position + 2);
        } else {
            $first_part = $copyright_text;
            $second_part = '';
        }
    @endphp    
    <div class="footer-bottom py-3 position-relative">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start text-center">
                    <p class="mb-0 text-white">{{ $first_part }}
                    <a target="_blank" href="https://iqonic.design/">{{ $second_part }} </a>
                    </p>
                </div>
                <div class="col-md-6 text-md-end text-center">
                    <span class="d-inline-flex align-items-center gap-3 flex-wrap">
                        <a target="_blank" href="{{ route('user.term_conditions') }}" class="text-body link-primary">{{__('landingpage.terms_conditions')}}</a>
                        <a target="_blank" href="{{ route('user.privacy_policy') }}" class="text-body link-primary">{{__('landingpage.privacy_policy')}}</a>
                        <a target="_blank" href="{{ route('user.help_support') }}" class="text-body link-primary">{{__('landingpage.help_support')}}</a>
                        <a target="_blank" href="{{ route('user.refund_policy') }}" class="text-body link-primary">{{__('landingpage.refund_policy')}}</a>
                        <a target="_blank" href="{{ route('user.cookie_policy') }}" class="text-body link-primary">{{__('landingpage.cookie_policy')}}</a>
                        <a target="_blank" href="{{ route('user.payment_policy') }}" class="text-body link-primary">{{__('landingpage.payment_policy')}}</a>
                        <a target="_blank" href="{{ route('user.data_deletion_request') }}" class="text-body link-primary">{{__('landingpage.data_deletion_request')}}</a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</footer>
