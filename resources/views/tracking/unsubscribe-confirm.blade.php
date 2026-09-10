<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Unsubscribe</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950">
    <div class="max-w-md w-full text-center bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-2xl">
        @if (! $email)
            <div class="w-16 h-16 rounded-full bg-slate-500/20 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold text-white">Link Not Recognised</h2>
            <p class="text-sm text-slate-400 mt-2">
                This unsubscribe link is invalid or has expired. If you are still receiving mail you did not ask for, reply to the message directly.
            </p>
        @elseif ($alreadyDone)
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold text-white">Already Unsubscribed</h2>
            <p class="text-sm text-slate-400 mt-2">
                <strong class="text-white">{{ $email }}</strong> is already removed from this sender's mailing list.
            </p>
        @else
            <div class="w-16 h-16 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold text-white">Unsubscribe?</h2>
            <p class="text-sm text-slate-400 mt-2">
                Confirm that <strong class="text-white">{{ $email }}</strong> should stop receiving mail from this sender.
            </p>
            <form method="POST" action="{{ route('unsubscribe.confirm', $token) }}" class="mt-6">
                <button type="submit"
                        class="w-full px-5 py-3 bg-rose-600 hover:bg-rose-500 text-white font-bold text-sm rounded-2xl shadow-lg transition">
                    Yes, unsubscribe me
                </button>
            </form>
            <p class="text-xs text-slate-500 mt-4">
                Nothing changes until you confirm.
            </p>
        @endif
    </div>
</body>
</html>
