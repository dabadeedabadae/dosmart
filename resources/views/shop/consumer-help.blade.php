@extends('shop.layout')
@section('title', ($copy['topics'][$topic] ?? $copy['title']).' — Dosmart')
@section('content')
<article class="form-card" lang="{{ $locale }}" style="max-width:760px;margin:auto;line-height:1.7;overflow-wrap:anywhere">
    <nav aria-label="RU / KK" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
        @foreach(['ru' => 'Русский', 'kk' => 'Қазақша'] as $language => $label)
            <a class="btn btn-ghost" lang="{{ $language }}" href="{{ route('shop.help', ['topic' => $topic, 'lang' => $language]) }}"
                @if($language === $locale) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if($topic)<a href="{{ route('shop.help', ['lang' => $locale]) }}">← {{ $copy['back'] }}</a>@endif
    <h1 style="margin:10px 0;font-size:clamp(25px,5vw,36px)">{{ $copy['topics'][$topic] ?? $copy['title'] }}</h1>
    @if(config('consumer.preview') && !config('consumer.published'))
        <p class="form-hint" style="margin-bottom:12px">{{ $copy['preview'] }}</p>
    @endif
    @if(!$topic)
        <p>{{ $copy['intro'] }}</p>
        <ul style="display:grid;gap:8px;list-style:none;padding:0;margin-top:16px">
            @foreach($copy['topics'] as $key => $label)
                <li><a class="btn btn-ghost btn-block" href="{{ route('shop.help', ['topic' => $key, 'lang' => $locale]) }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    @else
        @php
            $seller = config('consumer.seller');
            $details = config('consumer.details');
            $fee = number_format((float) config('pilot.delivery_fee', 3000), 0, ',', ' ');
            $fields = match ($topic) {
                'seller' => ['name' => $seller['name'], 'registration' => $seller['registration'], 'address' => $seller['address_'.$locale], 'phone' => $seller['phone'], 'email' => $seller['email'], 'hours' => config('pilot.service_hours'), 'disputes' => $details['disputes_'.$locale]],
                'delivery' => ['delivery' => $details['delivery_'.$locale], 'receipt' => $details['receipt_'.$locale]],
                'terms' => ['acceptance' => $details['acceptance_'.$locale], 'delivery' => $details['delivery_'.$locale], 'refusal' => $details['refusal_'.$locale]],
                'returns' => ['email' => $seller['email'], 'address' => $seller['address_'.$locale], 'refusal' => $details['refusal_'.$locale]],
                'privacy' => ['name' => $seller['name'], 'email' => $seller['email'], 'data' => $details['data_'.$locale]],
            };
        @endphp
        @foreach($copy[$topic] ?? [] as $paragraph)
            <p style="margin:10px 0">{{ str_replace(':fee', $fee, $paragraph) }}</p>
        @endforeach
        <dl style="display:grid;gap:4px;margin-top:18px">
            @foreach($fields as $key => $value)
                @continue(blank($value) && !config('consumer.preview'))
                <dt style="font-weight:700">{{ $copy['fields'][$key] }}</dt>
                <dd style="margin:0 0 10px;white-space:pre-line">{{ $value ?: $copy['pending'] }}</dd>
            @endforeach
        </dl>
        @if(in_array($topic, ['seller', 'returns'], true))
            <p>{{ $copy['appeal'] }}</p>
            <p style="margin-top:8px"><a href="https://www.gov.kz/org/mti-kzpp/press/news/details/428097?lang={{ $locale }}">{{ $copy['authority'] }}</a></p>
        @endif
    @endif
</article>
@endsection
