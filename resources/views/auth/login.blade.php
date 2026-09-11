@php $branding = \App\Models\Setting::branding(); @endphp
<!DOCTYPE html>
<html lang="pt-BR" class="{{ $branding['theme_default'] === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Painel IA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center bg-cover bg-center"
    @if($branding['login_bg_url']) style="background-image: linear-gradient(rgba(15,23,42,.55), rgba(15,23,42,.55)), url('{{ $branding['login_bg_url'] }}')" @endif>
    <div class="w-full max-w-sm">
        <form action="/login" method="POST" class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-4">
            <div class="text-center mb-2">
                @php $loginLogo = $branding['theme_default'] === 'dark' ? ($branding['logo_dark_url'] ?? $branding['logo_light_url']) : ($branding['logo_light_url'] ?? $branding['logo_dark_url']); @endphp
                @if($loginLogo)
                    <img src="{{ $loginLogo }}" alt="Logotipo" class="w-24 h-24 object-contain mx-auto mb-3">
                @endif
                <div class="w-14 h-14 bg-indigo-700 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                    </svg>
                </div>
                <h1 class="text-lg font-bold text-gray-800 dark:text-gray-100">Painel IA</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Acesso restrito à equipe</p>
            </div>

            @csrf

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Senha</label>
                <input type="password" name="password" required
                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>

            <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                <input type="checkbox" name="remember" class="rounded border-gray-300">
                Manter conectado
            </label>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg text-sm transition shadow-sm">
                Entrar
            </button>
        </form>

        <p class="text-center text-[11px] mt-4 {{ $branding['login_bg_url'] ? 'text-white/80 drop-shadow' : 'text-gray-400 dark:text-gray-500' }}">
            {{ $branding['footer_name'] }} {{ $branding['footer_version'] }} &middot; &copy; {{ $branding['footer_year'] }} {{ $branding['footer_company'] }}
        </p>
    </div>
</body>
</html>
