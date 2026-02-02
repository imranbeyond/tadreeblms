@extends('backend.layouts.app')
@section('title', __('labels.backend.smtp_settings.title').' | '.app_name())

@section('content')
    <div class="row">
        <div class="col-12">
            {{-- SMTP Configuration Section --}}
            {!! Form::open(['route' => 'admin.smtp-settings.save', 'method' => 'POST', 'class' => 'form-horizontal']) !!}

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-5">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-envelope-open-text mr-2"></i>
                                {{ __('labels.backend.smtp_settings.title') }}
                            </h4>
                        </div>
                    </div>

                    <hr/>

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong><i class="fas fa-exclamation-triangle mr-2"></i>{{ __('labels.backend.smtp_settings.error') }}</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="row mt-4 mb-4">
                        <div class="col">
                            {{-- Mail Mailer --}}
                            <div class="form-group row">
                                {!! Form::label('mail_mailer', __('labels.backend.smtp_settings.mail_mailer').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::select('mail_mailer', [
                                        'smtp' => 'SMTP',
                                        'sendmail' => 'Sendmail',
                                        'mailgun' => 'Mailgun',
                                        'ses' => 'Amazon SES',
                                        'postmark' => 'Postmark',
                                        'log' => 'Log (for testing)',
                                    ], old('mail_mailer', $smtpSettings['mail_mailer']), ['class' => 'form-control', 'required' => 'required']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_mailer_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail Host --}}
                            <div class="form-group row">
                                {!! Form::label('mail_host', __('labels.backend.smtp_settings.mail_host').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::text('mail_host', old('mail_host', $smtpSettings['mail_host']), ['class' => 'form-control', 'placeholder' => 'smtp.gmail.com', 'required' => 'required']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_host_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail Port --}}
                            <div class="form-group row">
                                {!! Form::label('mail_port', __('labels.backend.smtp_settings.mail_port').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::number('mail_port', old('mail_port', $smtpSettings['mail_port']), ['class' => 'form-control', 'placeholder' => '587', 'required' => 'required', 'min' => 1, 'max' => 65535]) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_port_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail Username --}}
                            <div class="form-group row">
                                {!! Form::label('mail_username', __('labels.backend.smtp_settings.mail_username'), ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::text('mail_username', old('mail_username', $smtpSettings['mail_username']), ['class' => 'form-control', 'placeholder' => 'your-email@gmail.com']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_username_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail Password (Masked) --}}
                            <div class="form-group row">
                                {!! Form::label('mail_password', __('labels.backend.smtp_settings.mail_password'), ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    <div class="input-group">
                                        {!! Form::password('mail_password', ['class' => 'form-control', 'id' => 'mail_password', 'placeholder' => $smtpSettings['mail_password'] ? '************' : __('labels.backend.smtp_settings.mail_password_placeholder')]) !!}
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye" id="toggleIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_password_note') }}</span>
                                    @if($smtpSettings['mail_password'])
                                        <span class="text-muted small d-block mt-1">
                                            <i class="fas fa-info-circle"></i> {{ __('labels.backend.smtp_settings.password_exists') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Mail Encryption --}}
                            <div class="form-group row">
                                {!! Form::label('mail_encryption', __('labels.backend.smtp_settings.mail_encryption'), ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::select('mail_encryption', [
                                        'tls' => 'TLS',
                                        'ssl' => 'SSL',
                                        'null' => 'None',
                                    ], old('mail_encryption', $smtpSettings['mail_encryption']), ['class' => 'form-control']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_encryption_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail From Address --}}
                            <div class="form-group row">
                                {!! Form::label('mail_from_address', __('labels.backend.smtp_settings.mail_from_address').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::email('mail_from_address', old('mail_from_address', $smtpSettings['mail_from_address']), ['class' => 'form-control', 'placeholder' => 'noreply@example.com', 'required' => 'required']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_from_address_note') }}</span>
                                </div>
                            </div>

                            {{-- Mail From Name --}}
                            <div class="form-group row">
                                {!! Form::label('mail_from_name', __('labels.backend.smtp_settings.mail_from_name').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::text('mail_from_name', old('mail_from_name', $smtpSettings['mail_from_name']), ['class' => 'form-control', 'placeholder' => 'Your Application Name', 'required' => 'required']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.mail_from_name_note') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer clearfix">
                    <div class="row">
                        <div class="col">
                            {{ form_cancel(route('admin.smtp-settings'), __('buttons.general.cancel')) }}
                        </div>
                        <div class="col text-right">
                            {{ form_submit(__('buttons.general.crud.update')) }}
                        </div>
                    </div>
                </div>
            </div>

            {!! Form::close() !!}

            {{-- Test Email Section --}}
            {!! Form::open(['route' => 'admin.smtp-settings.test', 'method' => 'POST', 'class' => 'form-horizontal']) !!}

            <div class="card mt-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-5">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-paper-plane mr-2"></i>
                                {{ __('labels.backend.smtp_settings.test_email_title') }}
                            </h4>
                        </div>
                    </div>

                    <hr/>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle mr-2"></i>
                        {{ __('labels.backend.smtp_settings.test_email_info') }}
                    </div>

                    <div class="row mt-4 mb-4">
                        <div class="col">
                            <div class="form-group row">
                                {!! Form::label('test_email', __('labels.backend.smtp_settings.test_email_address').'*', ['class' => 'col-md-2 form-control-label']) !!}
                                <div class="col-md-10">
                                    {!! Form::email('test_email', old('test_email', auth()->user()->email), ['class' => 'form-control', 'placeholder' => 'test@example.com', 'required' => 'required']) !!}
                                    <span class="help-text font-italic">{{ __('labels.backend.smtp_settings.test_email_note') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer clearfix">
                    <div class="row">
                        <div class="col text-right">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-paper-plane mr-2"></i>
                                {{ __('labels.backend.smtp_settings.send_test_email') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {!! Form::close() !!}
        </div>
    </div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        // Toggle password visibility
        $('#togglePassword').on('click', function() {
            var passwordField = $('#mail_password');
            var toggleIcon = $('#toggleIcon');

            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                toggleIcon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordField.attr('type', 'password');
                toggleIcon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    });
</script>
@endpush
