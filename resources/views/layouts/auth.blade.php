<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title ?? 'Sign in' }} · Global Academy International</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="school-auth-body">
    <main class="school-auth-shell">
        <aside class="school-auth-brand">
            <div class="school-auth-lockup"><img src="{{ asset('images/global-academy-international-logo.png') }}" alt="Global Academy International logo"><div><strong>Global Academy</strong><span>International</span></div></div>
            <div class="school-auth-motto"><span>Learn</span><i></i><span>Belong</span><i></i><span>Lead</span></div>
            <div class="school-auth-message"><p>Education is the beginning of every meaningful future.</p><span>One connected space for students, teachers, families, and school leaders.</span></div>
            <div class="school-auth-mark">Est. 2004</div>
        </aside>
        <section class="school-auth-panel">
            <div class="school-auth-panel-inner">
                <div class="school-auth-mobile-lockup"><img src="{{ asset('images/global-academy-international-logo.png') }}" alt=""><strong>Global Academy International</strong></div>
                <div class="school-auth-heading"><h1>{{ $heading ?? 'Welcome back' }}</h1><p>{{ $subheading ?? 'Sign in to Global Academy International.' }}</p></div>
                @include('components.flash')
                @yield('content')
            </div>
        </section>
    </main>
</body>
</html>
