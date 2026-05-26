<x-master-layout>
        <main class="main-area">
        <div class="main-content">
            <div class="container-fluid">
                @include('partials._provider')
                <div class="card mb-30">
                    <div class="card-body p-30">
                        <div class="col-lg-12">
                            <div class="card overview-detail mb-0">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="mb-3">{{ __('messages.default_commission') }}</h5>
                                        </div>
                                        <div class="form-group col-md-4">
                                            {{ Form::label('type',trans('messages.type'),['class'=>'form-control-label'], false ) }}
                                            <input type="text" class="form-control" value="{{optional(optional($providerdata)->providertype)['type'] }}" readonly>
                                        </div>
                                        <div class="form-group col-md-4">
                                            {{ Form::label('commission',trans('messages.commission'),['class'=>'form-control-label'], false ) }}
                                            <input type="text" class="form-control" value="{{ floatval(optional(optional($providerdata)->providertype)['commission']) }}" readonly>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                    {{ Form::model($providerdata, ['route' => ['provider.commission.update', $providerdata->id], 'method' => 'POST']) }}
                                    @endif
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="mb-3">{{ __('messages.custom_commission') }}</h5>
                                        </div>
                                        <div class="form-group col-md-4">
                                            {{ Form::label('custom_commission_type', __('messages.commission_type'), ['class' => 'form-control-label']) }}
                                            @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                            {{ Form::select('custom_commission_type', [
                                                '' => __('messages.select_commission_type'),
                                                'percentage' => __('messages.percentage'),
                                                'fixed' => __('messages.fixed')
                                            ], old('custom_commission_type'), ['class' => 'form-control select2js']) }}
                                            @else
                                            <input type="text" class="form-control" value="{{ $providerdata->custom_commission_type ? ucfirst($providerdata->custom_commission_type) : '-' }}" readonly>
                                            @endif
                                        </div>
                                        
                                        <div class="form-group col-md-4">
                                            {{ Form::label('custom_commission_value', __('messages.commission_value'), ['class' => 'form-control-label']) }}
                                            @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                            {{ Form::number('custom_commission_value', old('custom_commission_value'), [
                                                'class' => 'form-control',
                                                'placeholder' => __('messages.enter_commission_value'),
                                                'step' => '0.01',
                                                'min' => '0',
                                                'max' => '999999'
                                            ]) }}
                                            @else
                                            <input type="text" class="form-control" value="{{ $providerdata->custom_commission_value ? floatval($providerdata->custom_commission_value) : '-' }}" readonly>
                                            @endif
                                        </div>
                                        
                                        @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                        <div class="form-group col-md-4 d-flex align-items-end">
                                            {{ Form::submit(__('messages.save'), ['class' => 'btn btn-primary']) }}
                                            <a href="{{ route('provider.commission.clear', $providerdata->id) }}" class="btn btn-secondary ml-2">{{ __('messages.clear') }}</a>
                                        </div>
                                        @endif
                                    </div>
                                    @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                    <div class="row">
                                        <div class="col-md-12">
                                            <small class="text-muted">{{ __('messages.custom_commission_help') }}</small>
                                        </div>
                                    </div>
                                    @endif
                                    @if(auth()->user()->hasAnyRole(['admin', 'demo_admin']))
                                    {{ Form::close() }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>
    
    @section('bottom_script')
    <script>
    $(document).ready(function() {
        // Format number to remove unnecessary decimals
        function formatNumber(num) {
            return parseFloat(num) == parseInt(num) ? parseInt(num) : parseFloat(num);
        }
        
        // Format commission value input
        $('#custom_commission_value').on('blur', function() {
            var value = $(this).val();
            if (value) {
                $(this).val(formatNumber(value));
            }
        });
    });
    </script>
    @endsection
</x-master-layout>