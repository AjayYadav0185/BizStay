@php
    use App\Support\IndianCurrency as Rs;
    use App\Support\Ncr;
    $whatsapp = \App\Models\Guest::toWhatsappNumber($property->whatsapp_number ?: $property->contact_phone);
    $totalFree = $roomTypes->sum('available');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $property->name }} — {{ $property->type?->getLabel() }} in {{ $property->locality ? $property->locality.', ' : '' }}{{ $property->city }}</title>
<meta name="description" content="{{ $property->type?->getLabel() }} in {{ $property->locality }}, {{ $property->city }}. {{ $totalFree }} bed(s) available now. Rooms from {{ Rs::format((float) ($roomTypes->min('from') ?? 0), 0) }}/month.">
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
<meta name="theme-color" content="#102a43">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>
tailwind.config = { theme: { extend: {
    fontFamily: { sans: ['Plus Jakarta Sans','ui-sans-serif','system-ui','sans-serif'] },
    colors: { navy: {900:'#102a43',800:'#243b53',700:'#334e68',600:'#486581',100:'#d9e2ec',50:'#f0f4f8'}, brand:{500:'#f43f5e',600:'#e11d48',700:'#be123c'} }
}}}
</script>
<style>html,body{overflow-x:clip}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid #f43f5e;outline-offset:2px}</style>
</head>
<body class="bg-navy-50 text-navy-900 antialiased">

<header class="bg-navy-900 text-white">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:py-14">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">
            {{ $property->type?->getLabel() }} · {{ $property->locality ? $property->locality.', ' : '' }}{{ $property->city }}
        </span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl">{{ $property->name }}</h1>
        <p class="mt-3 max-w-2xl text-navy-100">{{ $property->full_address }}</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="#enquire" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-bold text-white shadow hover:bg-brand-600">Book a visit</a>
            @if ($whatsapp)
                <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hi, I am interested in a bed at '.$property->name.'. Is it available?') }}" target="_blank" rel="noopener" class="rounded-xl bg-white/10 px-5 py-3 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/20">WhatsApp us</a>
            @endif
            @if ($property->contact_phone)
                <a href="tel:{{ $property->contact_phone }}" class="rounded-xl bg-white/10 px-5 py-3 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/20">Call {{ $property->contact_phone }}</a>
            @endif
        </div>
    </div>
</header>

<main class="mx-auto max-w-5xl px-4 py-10 space-y-12">

    @if (session('enquiry_sent'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-800">
            <strong>Thank you!</strong> We have your enquiry and will call you back shortly.
        </div>
    @endif

    <section aria-labelledby="rooms-heading">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="rooms-heading" class="text-2xl font-extrabold">Rooms &amp; rent</h2>
            <p class="text-sm font-semibold {{ $totalFree > 0 ? 'text-emerald-700' : 'text-navy-600' }}">
                {{ $totalFree > 0 ? $totalFree.' bed(s) available right now' : 'Currently full — join the waitlist below' }}
            </p>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($roomTypes as $type)
                <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-navy-100">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-lg font-bold">{{ $type['sharing'] }}</h3>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $type['ac'] ? 'bg-sky-50 text-sky-700' : 'bg-navy-50 text-navy-700' }}">{{ $type['ac'] ? 'AC' : 'Non-AC' }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold">{{ Rs::format($type['from'], 0) }}<span class="text-sm font-medium text-navy-600"> /bed /month</span></p>
                    @if ($type['nightly'] > 0)
                        <p class="text-sm text-navy-600">Short stay from {{ Rs::format($type['nightly'], 0) }}/night</p>
                    @endif
                    <p class="mt-3 text-sm font-semibold {{ $type['available'] > 0 ? 'text-emerald-700' : 'text-navy-600' }}">
                        {{ $type['available'] > 0 ? $type['available'].' available' : 'Full' }}
                    </p>
                </article>
            @empty
                <p class="text-navy-600">Room details coming soon.</p>
            @endforelse
        </div>
        <p class="mt-4 text-sm text-navy-600">
            Security deposit: {{ $property->security_deposit_months }} month(s) rent · Notice period: {{ $property->notice_period_days }} days
            @if ((float) $property->meal_charge_per_day > 0) · Meals available @endif
        </p>
    </section>

    @if (! empty($property->amenities))
        <section aria-labelledby="amenities-heading">
            <h2 id="amenities-heading" class="text-2xl font-extrabold">Amenities</h2>
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($property->amenities as $amenity)
                    <li class="rounded-full bg-white px-4 py-2 text-sm font-medium shadow-sm ring-1 ring-navy-100">{{ $amenity }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section id="enquire" aria-labelledby="enquire-heading" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-navy-100 sm:p-8">
        <h2 id="enquire-heading" class="text-2xl font-extrabold">Book a visit / enquire</h2>
        <p class="mt-1 text-sm text-navy-600">Leave your number — we usually call back within a few hours.</p>

        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('stay.enquire') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <label class="block text-sm font-semibold">Full name
                <input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
            </label>
            <label class="block text-sm font-semibold">Mobile number
                <input name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" placeholder="98XXXXXXXX" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
            </label>
            <label class="block text-sm font-semibold">Email (optional)
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
            </label>
            <label class="block text-sm font-semibold">Gender
                <select name="gender" required class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
                    <option value="male" @selected(old('gender') === 'male')>Male</option>
                    <option value="female" @selected(old('gender') === 'female')>Female</option>
                    <option value="other" @selected(old('gender') === 'other')>Other</option>
                </select>
            </label>
            <label class="block text-sm font-semibold">Looking for
                <select name="interested_in" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
                    <option value="">Any room</option>
                    @foreach ($roomTypes->pluck('sharing')->unique() as $sharing)
                        <option value="{{ $sharing }}" @selected(old('interested_in') === $sharing)>{{ $sharing }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold">Budget per month (₹)
                <input name="budget" type="number" min="0" step="500" value="{{ old('budget') }}" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
            </label>
            <label class="block text-sm font-semibold">Move-in date
                <input name="preferred_move_in" type="date" min="{{ now()->toDateString() }}" value="{{ old('preferred_move_in') }}" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">
            </label>
            <label class="block text-sm font-semibold sm:col-span-2">Anything else? (office location, food preference…)
                <textarea name="message" rows="3" maxlength="1000" class="mt-1 w-full rounded-xl border border-navy-100 px-4 py-3 font-normal">{{ old('message') }}</textarea>
            </label>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full rounded-xl bg-brand-500 px-6 py-3 font-bold text-white hover:bg-brand-600 sm:w-auto">Send enquiry</button>
            </div>
        </form>
    </section>

    <section class="text-sm text-navy-600">
        <p>Check-in {{ $property->check_in_time }} · Check-out {{ $property->check_out_time }}. Valid government ID and police verification are required for all residents as per {{ Ncr::policePortalFor($property->city)['name'] }} rules.</p>
    </section>
</main>

<footer class="border-t border-navy-100 py-6 text-center text-xs text-navy-600">
    {{ $property->name }} · Managed with BizStay
</footer>
</body>
</html>
