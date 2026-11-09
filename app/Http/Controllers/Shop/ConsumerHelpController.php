<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConsumerHelpController extends Controller
{
    public function show(Request $request, ?string $topic = null)
    {
        abort_unless(config('consumer.preview') || config('consumer.published'), 404);
        $locale = $request->query('lang') === 'kk' ? 'kk' : 'ru';
        $copy = trans('consumer', locale: $locale);
        abort_if($topic !== null && ! isset($copy['topics'][$topic]), 404);

        return view('shop.consumer-help', compact('topic', 'locale', 'copy'));
    }
}
