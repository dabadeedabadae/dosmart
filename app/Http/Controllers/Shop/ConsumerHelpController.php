<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConsumerHelpController extends Controller
{
    private const GUIDE_PATH = 'consumer/consumer-guide-ru.docx';

    public function show(Request $request, ?string $topic = null)
    {
        abort_unless(config('consumer.preview') || config('consumer.published'), 404);
        $locale = $request->query('lang') === 'kk' ? 'kk' : 'ru';
        $copy = trans('consumer', locale: $locale);
        abort_if($topic !== null && ! isset($copy['topics'][$topic]), 404);

        $hasGuide = $topic === null && Storage::disk('local')->fileExists(self::GUIDE_PATH);

        return view('shop.consumer-help', compact('topic', 'locale', 'copy', 'hasGuide'));
    }

    public function download()
    {
        abort_unless(config('consumer.preview') || config('consumer.published'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->fileExists(self::GUIDE_PATH), 404);

        return $disk->download(self::GUIDE_PATH, basename(self::GUIDE_PATH), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
