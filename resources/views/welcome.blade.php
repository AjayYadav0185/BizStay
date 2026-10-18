<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BizStay — PG Management System for Gurgaon | Properties, Tenants, Rent & More</title>
<meta name="description" content="BizStay is a PG management software for Gurgaon. Manage properties, rooms & beds, tenants & KYC, inquiries, rent collection, expenses and complaints — with manager dashboard + Android app.">
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/bizstay-logo-180.png') }}">
<meta property="og:title" content="BizStay — PG Management System for Gurgaon">
<meta property="og:description" content="Properties, rooms & beds, tenants & KYC, rent, expenses and complaints — with manager dashboard + Android app.">
<meta property="og:image" content="{{ asset('images/bizstay-og.png') }}">
<meta name="theme-color" content="#1C1133">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    fontFamily: { sans: ['Plus Jakarta Sans','ui-sans-serif','system-ui','sans-serif'] },
    colors: { navy: {900:'#102a43',800:'#243b53',700:'#334e68',600:'#486581',100:'#d9e2ec',50:'#f0f4f8'}, brand:{500:'#f43f5e',600:'#e11d48',700:'#be123c'} }
  }}
}
</script>
<style>
html{scroll-behavior:smooth}
html,body{overflow-x:clip}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif}
img,video{max-width:100%}
h1,h2{text-wrap:balance}
a,button{-webkit-tap-highlight-color:transparent}
a:focus-visible,button:focus-visible,summary:focus-visible{outline:2px solid #f43f5e;outline-offset:2px;border-radius:.5rem}
.hero-grid{background-image:linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:44px 44px}
.glass{background:rgba(255,255,255,.08);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.14)}
.float-anim{animation:floaty 6s ease-in-out infinite}
@keyframes floaty{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
.marquee{display:flex;gap:2.5rem;animation:scrollx 22s linear infinite;width:max-content}
@keyframes scrollx{to{transform:translateX(-50%)}}
.no-scrollbar::-webkit-scrollbar{display:none}
.no-scrollbar{scrollbar-width:none;-webkit-overflow-scrolling:touch}
@media (max-width:639px){.float-anim{animation:none}}
@media (prefers-reduced-motion:reduce){.float-anim,.marquee{animation:none}html{scroll-behavior:auto}}
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

<!-- TOP BAR -->
<div class="bg-navy-900 text-white text-xs sm:text-sm">
<div class="max-w-7xl mx-auto px-4 py-2 flex items-center justify-center sm:justify-between gap-2">
<p class="truncate text-center sm:text-left">🏠 Now managing PGs across <span class="font-semibold">Sector 44 • Sector 56 • DLF Phase 3<span class="hidden md:inline"> • Golf Course Road</span></span></p>
<a href="#download-app" class="hidden sm:inline-flex items-center gap-1 bg-brand-500 hover:bg-brand-600 transition rounded-full px-4 py-1 font-semibold">📱 Get Android App</a>
</div>
</div>

<!-- NAV -->
<header class="sticky top-0 z-50 bg-white/85 backdrop-blur border-b border-slate-200">
<nav class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-2">
<a href="/" class="flex items-center gap-2.5 min-w-0">
<img src="{{ asset('images/bizstay-logo.png') }}" alt="BizStay logo" class="w-10 h-10 rounded-2xl shadow object-cover shrink-0" width="40" height="40">
<span class="leading-tight min-w-0"><span class="block font-extrabold text-navy-900 text-base sm:text-lg truncate">BizStay</span><span class="hidden min-[420px]:block text-[11px] tracking-widest uppercase text-slate-500 font-semibold truncate">PG Management • Gurgaon</span></span>
</a>
<div class="hidden lg:flex items-center gap-7 text-sm font-semibold text-slate-600">
<a href="#features" class="hover:text-navy-900">Features</a>
<a href="#properties" class="hover:text-navy-900">Properties</a>
<a href="#how" class="hover:text-navy-900">How it works</a>
<a href="#dashboard" class="hover:text-navy-900">Dashboard</a>
<a href="#pricing" class="hover:text-navy-900">Pricing</a>
<a href="#faq" class="hover:text-navy-900">FAQ</a>
</div>
<div class="flex items-center gap-2 shrink-0">
<a href="/admin" class="hidden md:inline-flex px-4 py-2.5 rounded-xl border border-slate-300 font-bold text-sm text-navy-900 hover:border-navy-900">Admin Login</a>
<a href="#download-app" class="px-3 sm:px-4 py-2.5 rounded-xl bg-navy-900 text-white font-bold text-xs sm:text-sm hover:bg-navy-800 whitespace-nowrap">⬇ Download APK</a>
<button id="menuBtn" type="button" aria-label="Open menu" aria-expanded="false" class="lg:hidden w-10 h-10 inline-flex items-center justify-center rounded-xl border border-slate-300 text-navy-900 text-lg font-extrabold">☰</button>
</div>
</nav>
<div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 bg-white">
<div class="max-w-7xl mx-auto px-4 py-3 grid gap-1 text-sm font-bold text-slate-700">
<a href="#features" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">Features</a>
<a href="#properties" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">Properties</a>
<a href="#how" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">How it works</a>
<a href="#dashboard" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">Dashboard</a>
<a href="#pricing" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">Pricing</a>
<a href="#faq" class="px-3 py-2.5 rounded-xl hover:bg-slate-100">FAQ</a>
<a href="/admin" class="px-3 py-2.5 rounded-xl bg-navy-900 text-white text-center">Admin Login</a>
</div>
</div>
</header>
<!-- HERO -->
<section class="relative overflow-hidden bg-[#102a43] text-white">
<div aria-hidden="true" class="absolute inset-0 hero-grid opacity-60 pointer-events-none"></div>
<div aria-hidden="true" class="absolute -top-32 -right-32 w-72 h-72 sm:w-[480px] sm:h-[480px] rounded-full bg-[#f43f5e]/25 blur-3xl pointer-events-none"></div>
<div aria-hidden="true" class="absolute -bottom-40 -left-24 w-64 h-64 sm:w-[420px] sm:h-[420px] rounded-full bg-sky-400/20 blur-3xl pointer-events-none"></div>
<div class="relative max-w-7xl mx-auto px-4 pt-10 pb-12 sm:pt-14 sm:pb-16 lg:pt-20 lg:pb-24 grid gap-10 lg:gap-12 lg:grid-cols-2 items-center">
<div class="min-w-0">
<div class="inline-flex max-w-full flex-wrap items-center gap-2 glass rounded-full px-4 py-1.5 text-xs font-semibold mb-5">Built for Gurgaon PG owners <span class="bg-emerald-400 text-[#102a43] rounded-full px-2 py-0.5 font-extrabold">NEW</span></div>
<h1 class="text-[2rem] leading-[1.1] sm:text-5xl lg:text-6xl font-extrabold sm:leading-[1.05]">Run your entire PG business from <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-300 to-amber-200">one dashboard.</span></h1>
<p class="mt-4 sm:mt-5 text-slate-300 text-[15px] sm:text-lg leading-relaxed">BizStay is PG management software built with <strong class="text-white">Laravel + Filament</strong>. Properties, rooms and beds, tenants and KYC, inquiries, rent, expenses and complaints — with live occupancy.</p>
<div class="mt-6 sm:mt-7 grid gap-3 sm:flex sm:flex-wrap">
<a href="#download-app" class="inline-flex items-center justify-center gap-2 bg-[#f43f5e] hover:bg-[#e11d48] transition font-extrabold px-6 py-3.5 rounded-2xl shadow-lg min-h-[48px]">Download Android App <span class="text-xs bg-white/20 rounded-full px-2 py-0.5">APK</span></a>
<a href="/admin" class="inline-flex items-center justify-center gap-2 bg-white text-[#102a43] font-extrabold px-6 py-3.5 rounded-2xl hover:bg-slate-100 transition min-h-[48px]">Live Demo -&gt;</a>
</div>
<p class="mt-4 text-xs text-slate-400 break-words">Demo login: <code class="bg-white/10 rounded px-1.5 py-0.5 text-white">admin@bizstay.in / password</code> - SQLite by default, MySQL ready</p>
<div class="mt-6 sm:mt-8 grid grid-cols-3 gap-2 sm:gap-3 max-w-md">
<div class="glass rounded-2xl p-2.5 sm:p-3 text-center min-w-0"><p class="text-xl sm:text-2xl font-extrabold">3+</p><p class="text-[10px] sm:text-[11px] text-slate-300 font-semibold uppercase tracking-wide">Properties</p></div>
<div class="glass rounded-2xl p-2.5 sm:p-3 text-center min-w-0"><p class="text-xl sm:text-2xl font-extrabold">24</p><p class="text-[10px] sm:text-[11px] text-slate-300 font-semibold uppercase tracking-wide">Beds live</p></div>
<div class="glass rounded-2xl p-2.5 sm:p-3 text-center min-w-0"><p class="text-xl sm:text-2xl font-extrabold">87%</p><p class="text-[10px] sm:text-[11px] text-slate-300 font-semibold uppercase tracking-wide">Occupancy</p></div>
</div>
</div>
<div class="relative min-w-0">
<div class="float-anim bg-white text-slate-800 rounded-3xl shadow-2xl overflow-hidden">
<div class="flex items-center justify-between gap-2 px-5 py-3 border-b border-slate-100">
<p class="text-xs font-bold text-slate-500 truncate">MANAGER DASHBOARD - TODAY</p><span class="text-[11px] font-extrabold bg-emerald-100 text-emerald-700 rounded-full px-2 py-0.5 shrink-0">LIVE</span>
</div>
<div class="p-4 sm:p-5 grid grid-cols-1 min-[420px]:grid-cols-2 gap-3">
<div class="rounded-2xl bg-[#102a43] text-white p-4 min-w-0"><p class="text-[11px] uppercase tracking-widest text-slate-300 font-bold">Occupancy</p><p class="text-3xl font-extrabold mt-1">87%</p><div class="mt-2 h-2 rounded-full bg-white/15 overflow-hidden"><div class="h-full w-[87%] rounded-full bg-gradient-to-r from-emerald-400 to-teal-300"></div></div></div>
<div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 min-w-0"><p class="text-[11px] uppercase tracking-widest text-slate-500 font-bold">Collected / Expected</p><p class="text-xl font-extrabold mt-1 text-[#102a43] break-words">Rs 1.42L <span class="text-slate-400 font-semibold text-sm">/ 1.68L</span></p><p class="text-xs font-bold text-emerald-600 mt-1">84% this month</p></div>
<div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 min-w-0"><p class="text-[11px] uppercase tracking-widest text-amber-700 font-bold">Rent Due</p><p class="text-2xl font-extrabold text-amber-800 break-words">Rs 26,500</p><p class="mt-2 text-xs font-extrabold text-amber-700">Collect button -&gt;</p></div>
<div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 min-w-0"><p class="text-[11px] uppercase tracking-widest text-rose-700 font-bold">Open complaints</p><p class="text-2xl font-extrabold text-rose-700">6</p><p class="text-xs text-rose-600 font-semibold mt-1">Electrical, Plumbing, Food</p></div>
</div>
</div>
</div>
</div>
</section>
<!-- LOGOS -->
<section class="bg-white border-b border-slate-200">
<div class="max-w-7xl mx-auto px-4 py-6 flex gap-x-8 gap-y-3 sm:gap-x-10 text-[11px] sm:text-xs font-extrabold tracking-widest text-slate-400 overflow-x-auto no-scrollbar whitespace-nowrap sm:flex-wrap sm:justify-center sm:whitespace-normal">
<span>SECTOR 44</span><span>SECTOR 56</span><span>DLF PHASE 3</span><span>GOLF COURSE ROAD</span><span>CYBER HUB</span><span>UDYOG VIHAR</span><span>NOBROKER</span><span>99ACRES</span><span>MAGICBRICKS</span>
</div>
</section>

<!-- FEATURES -->
<section id="features" class="max-w-7xl mx-auto px-4 py-14 sm:py-16 lg:py-24 scroll-mt-24">
<div class="text-center max-w-2xl mx-auto px-1">
<p class="text-xs font-extrabold tracking-widest text-[#e11d48] uppercase">Everything a PG manager needs</p>
<h2 class="mt-2 text-[1.65rem] leading-tight sm:text-4xl font-extrabold text-[#102a43]">8 modules. Zero spreadsheets.</h2>
<p class="mt-3 text-slate-500 text-[15px] sm:text-base">From walk-in inquiry to rent receipt — BizStay tracks every bed, tenant, rupee and complaint.</p>
</div>
<div class="mt-8 sm:mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-[#102a43] text-white flex items-center justify-center text-xl font-bold">H</div><h3 class="mt-4 font-extrabold text-[#102a43]">PG Properties</h3><p class="mt-2 text-sm text-slate-500">Boys / Girls / Co-living with locality, amenities, deposit and notice-period rules, live occupancy.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-rose-500 text-white flex items-center justify-center text-xl font-bold">R</div><h3 class="mt-4 font-extrabold text-[#102a43]">Rooms and Beds</h3><p class="mt-2 text-sm text-slate-500">1/2/3/4 sharing, rent, AC and bathroom flags. Beds auto-created, status synced: vacant / occupied / blocked.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xl font-bold">T</div><h3 class="mt-4 font-extrabold text-[#102a43]">Tenants and KYC</h3><p class="mt-2 text-sm text-slate-500">Full profile with Aadhaar / PAN / Passport / DL, company, emergency contact, one-click vacate workflow.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl font-bold">L</div><h3 class="mt-4 font-extrabold text-[#102a43]">Inquiries and Bookings</h3><p class="mt-2 text-sm text-slate-500">Lead pipeline from NoBroker, 99acres, MagicBricks. Follow-ups, overdue filter, one-click Convert-to-Tenant.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-sky-500 text-white flex items-center justify-center text-xl font-bold">P</div><h3 class="mt-4 font-extrabold text-[#102a43]">Payments</h3><p class="mt-2 text-sm text-slate-500">Rent, deposits, utilities. Pending / overdue tracking, Mark Paid with method and UTR, Rent Due widget.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-violet-500 text-white flex items-center justify-center text-xl font-bold">E</div><h3 class="mt-4 font-extrabold text-[#102a43]">Expenses</h3><p class="mt-2 text-sm text-slate-500">Electricity (DHBVN), water, internet, housekeeping, repairs, gas and salary — tracked per property.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-orange-500 text-white flex items-center justify-center text-xl font-bold">C</div><h3 class="mt-4 font-extrabold text-[#102a43]">Complaints</h3><p class="mt-2 text-sm text-slate-500">Electrical, plumbing, food, security categories with priorities, staff assignment and resolve workflow.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6 hover:shadow-xl hover:-translate-y-1 transition"><div class="w-11 h-11 rounded-xl bg-teal-500 text-white flex items-center justify-center text-xl font-bold">D</div><h3 class="mt-4 font-extrabold text-[#102a43]">Manager Dashboard</h3><p class="mt-2 text-sm text-slate-500">Occupancy %, active tenants, collected vs expected, pending dues, complaints, inquiries, bed-status chart.</p></div>
</div>
</section>
<!-- PROPERTIES -->
<section id="properties" class="bg-white border-y border-slate-200 scroll-mt-24">
<div class="max-w-7xl mx-auto px-4 py-14 sm:py-16 lg:py-20">
<div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end sm:justify-between gap-3 sm:gap-4">
<div><p class="text-xs font-extrabold tracking-widest text-[#e11d48] uppercase">Live in Gurgaon</p><h2 class="mt-2 text-[1.65rem] leading-tight sm:text-3xl font-extrabold text-[#102a43]">Seeded demo properties</h2></div>
<a href="/admin" class="text-sm font-extrabold text-[#e11d48] py-1">Open admin panel -&gt;</a>
</div>
<div class="mt-6 sm:mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
<div class="rounded-2xl overflow-hidden border border-slate-200 hover:shadow-xl transition"><div class="h-32 sm:h-36 bg-gradient-to-br from-[#102a43] to-[#486581] p-5 text-white flex flex-col justify-end"><span class="text-[11px] font-bold bg-white/20 self-start rounded-full px-2 py-0.5">CO-LIVING</span><h3 class="mt-2 font-extrabold text-lg leading-snug">BizStay Homes — Sector 44</h3></div><div class="p-5 text-sm text-slate-600"><p>10 rooms / 24 beds demo setup. Near HUDA Staff Colony, ideal for Cyber Hub commuters.</p><div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs font-bold"><span class="text-emerald-600">9/10 beds filled</span><span class="text-slate-400">Rs 7,500+ / bed</span></div></div></div>
<div class="rounded-2xl overflow-hidden border border-slate-200 hover:shadow-xl transition"><div class="h-32 sm:h-36 bg-gradient-to-br from-[#e11d48] to-[#fb7185] p-5 text-white flex flex-col justify-end"><span class="text-[11px] font-bold bg-white/20 self-start rounded-full px-2 py-0.5">GIRLS</span><h3 class="mt-2 font-extrabold text-lg leading-snug">BizStay Girls Nest — Sector 56</h3></div><div class="p-5 text-sm text-slate-600"><p>Warden-managed girls PG with strict entry, CCTV and food included options.</p><div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs font-bold"><span class="text-emerald-600">7/8 beds filled</span><span class="text-slate-400">Rs 8,500+ / bed</span></div></div></div>
<div class="rounded-2xl overflow-hidden border border-slate-200 hover:shadow-xl transition sm:col-span-2 lg:col-span-1"><div class="h-32 sm:h-36 bg-gradient-to-br from-amber-500 to-orange-600 p-5 text-white flex flex-col justify-end"><span class="text-[11px] font-bold bg-white/20 self-start rounded-full px-2 py-0.5">BOYS + CO-LIVING</span><h3 class="mt-2 font-extrabold text-lg leading-snug">Comfort Stay — DLF Phase 3</h3></div><div class="p-5 text-sm text-slate-600"><p>Premium rooms on Golf Course Road corridor, AC rooms, single / double sharing.</p><div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs font-bold"><span class="text-amber-600">4/6 beds filled</span><span class="text-slate-400">Rs 9,500+ / bed</span></div></div></div>
</div>
</div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="max-w-7xl mx-auto px-4 py-14 sm:py-16 lg:py-24 scroll-mt-24">
<div class="text-center max-w-2xl mx-auto px-1"><p class="text-xs font-extrabold tracking-widest text-[#e11d48] uppercase">Workflow</p><h2 class="mt-2 text-[1.65rem] leading-tight sm:text-4xl font-extrabold text-[#102a43]">Walk-in to rent receipt in 4 steps</h2></div>
<div class="mt-8 sm:mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
<div class="bg-white rounded-2xl border border-slate-200 p-6"><span class="text-4xl font-extrabold text-slate-200">01</span><h3 class="mt-2 font-extrabold text-[#102a43]">Capture inquiry</h3><p class="mt-2 text-sm text-slate-500">Walk-ins and portal leads (NoBroker, 99acres, MagicBricks) with follow-up dates.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6"><span class="text-4xl font-extrabold text-slate-200">02</span><h3 class="mt-2 font-extrabold text-[#102a43]">Convert to tenant</h3><p class="mt-2 text-sm text-slate-500">One click allocates a vacant bed, sets rent, deposit, joining date and KYC.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6"><span class="text-4xl font-extrabold text-slate-200">03</span><h3 class="mt-2 font-extrabold text-[#102a43]">Collect rent</h3><p class="mt-2 text-sm text-slate-500">Monthly rent, utilities, deposits. Mark Paid with method and UTR.</p></div>
<div class="bg-white rounded-2xl border border-slate-200 p-6"><span class="text-4xl font-extrabold text-slate-200">04</span><h3 class="mt-2 font-extrabold text-[#102a43]">Resolve and retain</h3><p class="mt-2 text-sm text-slate-500">Complaints to staff, expenses per property, notice and vacate flow.</p></div>
</div>
</section>
<!-- DASHBOARD TECH -->
<section id="dashboard" class="bg-[#102a43] text-white scroll-mt-24">
<div class="max-w-7xl mx-auto px-4 py-14 sm:py-16 grid gap-8 lg:gap-10 lg:grid-cols-2 items-center">
<div class="min-w-0">
<p class="text-xs font-extrabold tracking-widest text-rose-300 uppercase">Under the hood</p>
<h2 class="mt-2 text-[1.65rem] leading-tight sm:text-3xl font-extrabold">Laravel + Filament 3. Tested.</h2>
<p class="mt-3 text-slate-300 text-[15px] sm:text-sm leading-relaxed">PHP 8.3+ enums for all statuses. SQLite by default, one-switch to MySQL on MAMP. PHPUnit covers dashboard widgets and all 8 resource pages.</p>
<ul class="mt-5 space-y-2 text-sm text-slate-200">
<li>✓ migrate --seed, serve — live in minutes</li>
<li class="break-words">✓ Admin at /admin — admin@bizstay.in / password</li>
<li>✓ Printable invoice / receipt view included</li>
<li>✓ Bed service keeps status always in sync</li>
</ul>
<div class="mt-6 bg-black/40 border border-white/10 rounded-2xl p-4 font-mono text-xs text-slate-200 overflow-x-auto whitespace-nowrap no-scrollbar">composer install<br>php artisan migrate --seed<br>php artisan serve</div>
</div>
<div class="bg-white text-slate-800 rounded-3xl p-5 sm:p-6 shadow-2xl min-w-0">
<h3 class="font-extrabold text-[#102a43]">Manager sees everything</h3>
<div class="mt-4 space-y-3 text-sm">
<div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 border rounded-xl px-4 py-3"><span class="font-bold text-slate-500">Active tenants</span><span class="font-extrabold">8 tenants</span></div>
<div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 border rounded-xl px-4 py-3"><span class="font-bold text-slate-500">Pending dues</span><span class="font-extrabold text-amber-600">Rs 26,500</span></div>
<div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 border rounded-xl px-4 py-3"><span class="font-bold text-slate-500">Open complaints</span><span class="font-extrabold text-rose-600">6 open</span></div>
<div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 border rounded-xl px-4 py-3"><span class="font-bold text-slate-500">New inquiries</span><span class="font-extrabold text-sky-600">4 (2 overdue)</span></div>
</div>
<a href="/admin" class="mt-5 block text-center bg-[#102a43] text-white font-extrabold rounded-xl py-3.5 min-h-[48px]">Open live dashboard</a>
</div>
</div>
</section>
<!-- PRICING -->
<section id="pricing" class="max-w-7xl mx-auto px-4 py-14 sm:py-16 scroll-mt-24">
<div class="text-center max-w-xl mx-auto px-1"><p class="text-xs font-extrabold tracking-widest text-[#e11d48] uppercase">Pricing</p><h2 class="mt-2 text-[1.65rem] leading-tight sm:text-3xl font-extrabold text-[#102a43]">Simple plans for every PG size</h2></div>
<div class="mt-6 sm:mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
<div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7"><h3 class="font-extrabold text-[#102a43]">Single PG</h3><p class="mt-1 text-3xl font-extrabold">Rs 999<span class="text-sm text-slate-400 font-bold">/mo</span></p><ul class="mt-4 space-y-2 text-sm text-slate-600"><li>✓ 1 property, 50 beds</li><li>✓ Rent + expenses + complaints</li><li>✓ Manager dashboard</li></ul><a href="#download-app" class="mt-6 block text-center border border-slate-300 font-extrabold rounded-xl py-3 min-h-[48px]">Choose Single</a></div>
<div class="bg-[#102a43] text-white rounded-3xl p-6 sm:p-7 shadow-2xl relative mt-4 sm:mt-0 sm:col-span-2 lg:col-span-1"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-[#f43f5e] text-xs font-extrabold rounded-full px-3 py-1 whitespace-nowrap">MOST POPULAR</span><h3 class="font-extrabold">Multi Property</h3><p class="mt-1 text-3xl font-extrabold">Rs 2,499<span class="text-sm text-slate-300 font-bold">/mo</span></p><ul class="mt-4 space-y-2 text-sm text-slate-200"><li>✓ Up to 5 properties</li><li>✓ Inquiry pipeline + conversion</li><li>✓ Android app + staff logins</li></ul><a href="#download-app" class="mt-6 block text-center bg-[#f43f5e] font-extrabold rounded-xl py-3 min-h-[48px]">Choose Multi</a></div>
<div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-7 sm:col-span-2 lg:col-span-1"><h3 class="font-extrabold text-[#102a43]">Enterprise</h3><p class="mt-1 text-3xl font-extrabold">Custom</p><ul class="mt-4 space-y-2 text-sm text-slate-600"><li>✓ Unlimited properties</li><li>✓ MySQL + custom reports</li><li>✓ Onboarding + support</li></ul><a href="#faq" class="mt-6 block text-center border border-slate-300 font-extrabold rounded-xl py-3 min-h-[48px]">Talk to us</a></div>
</div>
</section>
<!-- FAQ -->
<section id="faq" class="bg-white border-y border-slate-200 scroll-mt-24">
<div class="max-w-3xl mx-auto px-4 py-14 sm:py-16">
<h2 class="text-[1.65rem] leading-tight sm:text-3xl font-extrabold text-[#102a43] text-center">Questions, answered</h2>
<div class="mt-6 sm:mt-8 space-y-3">
<details class="bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4"><summary class="font-extrabold text-[#102a43] cursor-pointer py-1">Do I need MySQL to start?</summary><p class="mt-2 text-sm text-slate-600">No. BizStay runs on SQLite with zero config. Switch to MySQL on MAMP later by setting DB_CONNECTION=mysql in .env and running migrate --seed.</p></details>
<details class="bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4"><summary class="font-extrabold text-[#102a43] cursor-pointer py-1">What is the demo login?</summary><p class="mt-2 text-sm text-slate-600 break-words">Open /admin and sign in with admin@bizstay.in / password. Demo data includes 3 properties, 10 rooms, 24 beds, 8 tenants, payments, inquiries, expenses and complaints.</p></details>
<details class="bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4"><summary class="font-extrabold text-[#102a43] cursor-pointer py-1">How does Convert-to-Tenant work?</summary><p class="mt-2 text-sm text-slate-600">From any inquiry, one click allocates a vacant bed, creates the tenant profile with KYC, rent and deposit, and flips bed status to occupied.</p></details>
<details class="bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4"><summary class="font-extrabold text-[#102a43] cursor-pointer py-1">Where is the Android APK?</summary><p class="mt-2 text-sm text-slate-600 break-words">Scroll to the download section below. Place your built APK at public/downloads/bizstay.apk on the server, then the button serves it directly.</p></details>
</div>
</div>
</section>
<!-- APP DOWNLOAD / APK -->
<section id="download-app" class="relative overflow-hidden bg-gradient-to-br from-[#e11d48] via-[#be123c] to-[#881337] text-white scroll-mt-24">
<div aria-hidden="true" class="absolute inset-0 hero-grid opacity-40 pointer-events-none"></div>
<div class="relative max-w-7xl mx-auto px-4 py-14 sm:py-16 lg:py-20 grid gap-8 lg:gap-10 lg:grid-cols-2 items-center">
<div class="min-w-0">
<p class="text-xs font-extrabold tracking-widest uppercase text-rose-200">BizStay Android app</p>
<h2 class="mt-2 text-[1.65rem] leading-tight sm:text-4xl font-extrabold">Take your PG with you. Collect rent from your phone.</h2>
<p class="mt-3 text-rose-100 text-[15px] sm:text-base">Warden and manager app: live occupancy, rent-due list, one-tap Mark Paid, new inquiry alerts, complaint photos. Works with the same admin login.</p>
<div class="mt-5 grid grid-cols-3 gap-2 sm:gap-3 max-w-md text-center">
<div class="bg-white/10 border border-white/20 rounded-2xl p-2.5 sm:p-3 min-w-0"><p class="font-extrabold text-base sm:text-lg">v1.0</p><p class="text-[10px] sm:text-[11px] text-rose-200 font-bold">ANDROID 8+</p></div>
<div class="bg-white/10 border border-white/20 rounded-2xl p-2.5 sm:p-3 min-w-0"><p class="font-extrabold text-base sm:text-lg">~18 MB</p><p class="text-[10px] sm:text-[11px] text-rose-200 font-bold">APK SIZE</p></div>
<div class="bg-white/10 border border-white/20 rounded-2xl p-2.5 sm:p-3 min-w-0"><p class="font-extrabold text-base sm:text-lg">Hindi + EN</p><p class="text-[10px] sm:text-[11px] text-rose-200 font-bold">LANGUAGE</p></div>
</div>
</div>
<div class="bg-white text-slate-800 rounded-3xl p-5 sm:p-8 shadow-2xl min-w-0">
<div class="flex items-center gap-4 min-w-0">
<img src="{{ asset('images/bizstay-logo.png') }}" alt="BizStay app icon" class="w-14 h-14 rounded-2xl shadow object-cover shrink-0" width="56" height="56">
<div class="min-w-0"><h3 class="font-extrabold text-lg text-[#102a43]">BizStay.apk</h3><p class="text-xs text-slate-500 font-semibold">For managers, wardens and staff • Free with plan</p></div>
</div>
@if (file_exists(public_path('downloads/bizstay.apk')))
<a href="{{ asset('downloads/bizstay.apk') }}" download class="mt-6 flex items-center justify-center gap-2 w-full bg-[#102a43] hover:bg-[#243b53] text-white font-extrabold rounded-2xl py-4 min-h-[52px] transition">Download APK — {{ number_format(filesize(public_path('downloads/bizstay.apk')) / 1048576, 1) }} MB</a>
<p class="mt-2 text-center text-xs text-slate-400">By downloading you agree to allow Install from Unknown Sources for BizStay.</p>
@else
<div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-5 text-center">
<p class="font-extrabold text-[#102a43]">APK uploading soon</p>
<p class="mt-1 text-xs text-slate-500 break-words">Place your build at <code class="bg-slate-200 rounded px-1">public/downloads/bizstay.apk</code> and this button auto-activates. Meanwhile try the web demo:</p>
<a href="/admin" class="mt-4 inline-block bg-[#f43f5e] text-white font-extrabold rounded-xl px-6 py-3 min-h-[48px]">Open web demo instead</a>
</div>
@endif
<div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-slate-500 font-semibold"><span>✓ Virus scanned</span><span>•</span><span>✓ Auto-updates</span><span>•</span><span>✓ Same login as web</span></div>
<ol class="mt-4 space-y-1.5 text-xs text-slate-500"><li><strong class="text-slate-700">1.</strong> Tap Download APK on your Android phone.</li><li><strong class="text-slate-700">2.</strong> Allow "Install unknown apps" for Chrome when asked.</li><li><strong class="text-slate-700">3.</strong> Open BizStay and sign in (manager / warden login).</li></ol>
</div>
</div>
</section>
<!-- CTA + FOOTER -->
<section class="max-w-7xl mx-auto px-4 py-10 sm:py-14">
<div class="rounded-3xl bg-[#102a43] text-white px-5 py-10 sm:px-8 sm:py-12 text-center relative overflow-hidden">
<div aria-hidden="true" class="absolute inset-0 hero-grid opacity-50 pointer-events-none"></div>
<div class="relative">
<h2 class="text-[1.65rem] leading-tight sm:text-3xl font-extrabold">Fill every bed. Miss no rent.</h2>
<p class="mt-2 text-slate-300 text-[15px] sm:text-sm">Join Gurgaon PG owners running on BizStay. Setup takes 10 minutes.</p>
<div class="mt-6 grid gap-3 sm:flex sm:flex-wrap sm:justify-center">
<a href="#download-app" class="bg-[#f43f5e] hover:bg-[#e11d48] font-extrabold px-7 py-3.5 rounded-2xl min-h-[48px] inline-flex items-center justify-center">Get the App</a>
<a href="/admin" class="bg-white text-[#102a43] font-extrabold px-7 py-3.5 rounded-2xl min-h-[48px] inline-flex items-center justify-center">Try Admin Demo</a>
</div>
</div>
</div>
</section>
<footer class="bg-[#0b1f33] text-slate-300">
<div class="max-w-7xl mx-auto px-4 py-10 sm:py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
<div><div class="flex items-center gap-2"><img src="{{ asset('images/bizstay-logo.png') }}" alt="BizStay logo" class="w-9 h-9 rounded-xl object-cover" width="36" height="36"><span class="font-extrabold text-white">BizStay</span></div><p class="mt-3 text-slate-400">PG Management System for Gurgaon. Laravel + Filament. Sector 44, Sector 56, DLF Phase 3, Golf Course Road.</p></div>
<div><p class="font-extrabold text-white mb-3">Product</p><ul class="space-y-2"><li><a href="#features" class="hover:text-white">Features</a></li><li><a href="#properties" class="hover:text-white">Properties</a></li><li><a href="#pricing" class="hover:text-white">Pricing</a></li><li><a href="#download-app" class="hover:text-white">Android APK</a></li></ul></div>
<div><p class="font-extrabold text-white mb-3">Company</p><ul class="space-y-2"><li><a href="#how" class="hover:text-white">How it works</a></li><li><a href="#dashboard" class="hover:text-white">Dashboard</a></li><li><a href="#faq" class="hover:text-white">FAQ</a></li><li><a href="/admin" class="hover:text-white">Admin Login</a></li></ul></div>
<div><p class="font-extrabold text-white mb-3">Contact</p><ul class="space-y-2 text-slate-400 break-words"><li>hello@bizstay.in</li><li>+91 880-2929-885 (Gurugram)</li><li>Gurugram</li></ul></div>
</div>
<div class="border-t border-white/10"><div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row sm:flex-wrap sm:justify-between gap-1.5 sm:gap-2 text-xs text-slate-500"><span>© {{ date('Y') }} BizStay. All rights reserved.</span><span>Laravel {{ app()->version() }} • PHP {{ PHP_VERSION }}</span></div></div>
</footer>
<script>
(function(){
  var btn = document.getElementById('menuBtn');
  var menu = document.getElementById('mobileMenu');
  if(!btn || !menu) return;
  btn.addEventListener('click', function(){
    var open = menu.classList.toggle('hidden');
    btn.setAttribute('aria-expanded', String(!open));
    btn.textContent = open ? '☰' : '✕';
  });
  menu.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){
      menu.classList.add('hidden');
      btn.setAttribute('aria-expanded', 'false');
      btn.textContent = '☰';
    });
  });
})();
</script>
</body>
</html>