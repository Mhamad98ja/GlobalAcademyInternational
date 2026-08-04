@extends('layouts.auth', ['title' => 'Reset password with WhatsApp', 'heading' => 'Enter your reset code', 'subheading' => 'Use the six-digit code sent to your linked WhatsApp number.'])

@section('content')
<form method="POST" action="{{ route('password.whatsapp.update') }}" class="space-y-4" data-submit-lock>
    @csrf
    <div><label class="form-label" for="whatsapp_number">WhatsApp number</label><input id="whatsapp_number" name="whatsapp_number" required value="{{ old('whatsapp_number', $whatsappNumber) }}" placeholder="+961XXXXXXXX" autocomplete="tel"></div>
    <div><label class="form-label" for="otp">Six-digit code</label><input id="otp" name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
    <div><label class="form-label" for="password">New password</label><input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 12 characters"></div>
    <div><label class="form-label" for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="btn btn-primary w-full">Reset password</button>
    <a class="link block text-center text-sm" href="{{ route('password.request') }}">Send another code</a>
</form>
@endsection
