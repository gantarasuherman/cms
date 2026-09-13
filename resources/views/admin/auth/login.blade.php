<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    @if ($captcha->siteKey())
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased">
    <div class="flex min-h-screen">
        {{-- Form column. On the left, as in the reference layout, and the only
             column below lg so the form is never pushed under decoration. --}}
        <main class="flex w-full flex-col px-6 py-8 sm:px-10 lg:w-1/2 xl:w-[45%]">
            <a href="{{ url('/') }}"
               class="inline-flex w-max items-center gap-2 rounded text-sm text-slate-600 hover:text-[#4318ff] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4318ff]">
                <x-icon name="chevron-left" class="h-4 w-4" />
                Kembali ke situs
            </a>

            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                <h1 class="mb-2.5 text-4xl font-bold text-[#1b2559]">Masuk</h1>
                <p class="mb-8 ml-1 text-base text-slate-500">
                    Masukkan email dan kata sandi untuk mengelola situs.
                </p>

                @if ($errors->any())
                    <div role="alert"
                         class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <x-icon name="circle-alert" class="mt-0.5 h-4.5 w-4.5 shrink-0" />
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="ml-1 mb-2 block text-sm font-medium text-[#1b2559]">
                            Email<span class="text-[#4318ff]" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib diisi)</span>
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                               required autofocus autocomplete="username"
                               placeholder="nama@instansi.go.id"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                               class="w-full rounded-xl border bg-white px-4 py-3.5 text-sm placeholder:text-slate-400 focus:outline-2 focus:outline-offset-0 focus:outline-[#4318ff] @error('email') border-rose-400 @else border-slate-200 @enderror">
                        @error('email')
                            <p id="email-error" class="ml-1 mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="ml-1 mb-2 block text-sm font-medium text-[#1b2559]">
                            Kata sandi<span class="text-[#4318ff]" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib diisi)</span>
                        </label>
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="Minimal 8 karakter"
                               class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm placeholder:text-slate-400 focus:outline-2 focus:outline-offset-0 focus:outline-[#4318ff]">
                    </div>

                    @if ($captcha->enabled())
                        @php $challenge = $captcha->challenge(); @endphp

                        @if ($challenge?->isMultipleChoice())
                            {{-- A radio group, not four submit buttons: picking one
                                 of several is exactly what radios mean, so assistive
                                 technology announces "1 of 4" and the arrow keys move
                                 between them. Four submit buttons would also fire the
                                 form on Enter from any field. --}}
                            <fieldset>
                                <legend class="ml-1 mb-2 block text-sm font-medium text-[#1b2559]">
                                    Verifikasi: berapa hasil {{ $challenge->question }}?<span class="text-[#4318ff]" aria-hidden="true">*</span>
                                    <span class="sr-only">(wajib dipilih)</span>
                                </legend>

                                <div class="grid grid-cols-4 gap-2" role="none">
                                    @foreach ($challenge->options as $option)
                                        @php $optionId = $captcha->responseField().'-'.$loop->index; @endphp
                                        <div>
                                            <input type="radio" id="{{ $optionId }}"
                                                   name="{{ $captcha->responseField() }}" value="{{ $option }}"
                                                   required
                                                   @error($captcha->responseField()) aria-describedby="captcha-error" @enderror
                                                   class="peer sr-only">

                                            {{-- The selected state is carried by border
                                                 weight, fill and a check mark, not by
                                                 colour alone. --}}
                                            <label for="{{ $optionId }}"
                                                   class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border-2 border-slate-200 bg-white py-3.5 text-base font-semibold text-[#1b2559] transition hover:border-slate-300
                                                          peer-checked:border-[#4318ff] peer-checked:bg-[#4318ff] peer-checked:text-white
                                                          peer-checked:[&_[data-check]]:opacity-100
                                                          peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#4318ff]">
                                                {{-- A check mark appears on the chosen
                                                     option, so the selection is not
                                                     signalled by colour alone. The
                                                     variant is written on the label
                                                     because peer-checked: only reaches
                                                     siblings of the input. --}}
                                                <span data-check class="opacity-0 transition-opacity" aria-hidden="true">
                                                    <x-icon name="check" class="h-4 w-4" />
                                                </span>
                                                {{ $option }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>

                                <p class="ml-1 mt-2 text-xs text-slate-500">
                                    Pilih jawaban yang benar untuk memastikan pengisian dilakukan manusia.
                                </p>
                            </fieldset>
                        @elseif ($captcha->siteKey())
                            <div class="cf-turnstile" data-sitekey="{{ $captcha->siteKey() }}"></div>
                        @endif

                        @error($captcha->responseField())
                            <p id="captcha-error" class="ml-1 mt-1.5 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    @endif

                    <div class="flex items-center justify-between px-1">
                        <label for="remember" class="flex items-center gap-2.5 text-sm font-medium text-[#1b2559]">
                            <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))
                                   class="h-5 w-5 rounded-md border-slate-300 text-[#4318ff] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4318ff]">
                            Tetap masuk
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-[#4318ff] py-3.5 text-sm font-bold text-white transition hover:bg-[#3311cc] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#4318ff]">
                        Masuk
                    </button>
                </form>

                <p class="mt-6 flex items-start gap-2 text-sm text-slate-500">
                    <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    Akun dibuat oleh administrator. Hubungi administrator bila Anda belum memiliki akses.
                </p>
            </div>

            <footer class="text-center text-xs text-slate-400 lg:text-left">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </footer>
        </main>

        {{-- Decorative half. aria-hidden and hidden below lg: it carries no
             information the form does not already state, so nothing is lost
             when it is not shown. --}}
        <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-[#4318ff] via-[#6a4bff] to-[#868cff] lg:block xl:w-[55%]"
             aria-hidden="true">
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-32 -left-20 h-[28rem] w-[28rem] rounded-full bg-white/5"></div>

            <div class="relative flex h-full flex-col items-center justify-center px-12 text-center text-white">
                <span class="mb-6 grid h-16 w-16 place-items-center rounded-2xl bg-white/15 backdrop-blur">
                    <x-icon name="layout-dashboard" class="h-8 w-8" />
                </span>

                <p class="text-3xl font-bold">{{ $siteName }}</p>
                <p class="mt-3 max-w-md text-white/80">
                    Panel pengelolaan berita, layanan, dokumen, dan seluruh isi situs.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
