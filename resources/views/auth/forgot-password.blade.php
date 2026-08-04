@extends('layouts.auth', ['title' => 'Reset password', 'heading' => 'Reset your password', 'subheading' => 'Choose email or WhatsApp to receive your reset instructions.'])

@section('content')
<form method="POST" action="{{ route('password.email') }}" class="space-y-6" data-submit-lock>
    @csrf
    <div>
        <label class="form-label" for="email">Account email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com">
        <button class="btn btn-primary mt-3 w-full" name="delivery_method" value="email">Send reset link by email</button>
    </div>

    <div class="relative py-1 text-center text-xs text-slate-400 before:absolute before:inset-x-0 before:top-1/2 before:border-t before:border-slate-200"><span class="relative bg-white px-3">or</span></div>

    <div>
        <label class="form-label" for="whatsapp_number">Linked WhatsApp number</label>
        <input id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number') }}" inputmode="tel" placeholder="+961XXXXXXXX" aria-describedby="whatsapp-help">
        <p id="whatsapp-help" class="mt-1 text-xs text-slate-500">Use the international format saved on your user account.</p>
        <button class="btn btn-secondary mt-3 w-full" name="delivery_method" value="whatsapp">Send six-digit code by WhatsApp</button>
    </div>

    <a class="link block text-center text-sm" href="{{ route('login') }}">Back to sign in</a>
</form>
@endsection
