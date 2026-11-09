@extends('admin.layouts.app')
@section('title', 'Заказ ' . $order->order_number)

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="{{ route('admin.orders.index', $listContext) }}" class="icon-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <h1 class="page-title">Заказ {{ $order->order_number }}</h1>
        <a class="btn btn-secondary" href="{{ route('admin.orders.receipt', $order) }}" target="_blank" rel="noopener noreferrer">Печать заказа · 80 мм</a>
        @php
            $cls = match($order->payment_status) {
                'paid'     => 'badge-success',
                'refunded' => 'badge-danger',
                default    => 'badge-warning',
            };
            $lbl = match($order->payment_status) {
                'paid'     => 'Оплачено',
                'refunded' => 'Возврат',
                default    => 'Ожидает оплаты',
            };
            if ($order->status === 'cancelled') { $cls = 'badge-danger'; $lbl = 'Отменён'; }
        @endphp
        <span class="badge {{ $cls }}">
            <span class="badge-dot"></span>{{ $order->status_label }}
@if($order->status === 'pending' && $order->payment_reported_at)<span class="badge badge-warning">Клиент сообщил об оплате — проверить Kaspi</span>@endif
        </span>
    </div>
</div>

<div class="order-columns" style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start">

    <div style="display:flex;flex-direction:column;gap:18px">
        {{-- Состав заказа --}}
        <div class="card">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px">
                Состав заказа
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th style="text-align:center">Кол-во</th>
                            <th style="text-align:right">Цена</th>
                            <th style="text-align:right">Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td style="font-weight:500">{{ $item->product_name }}</td>
                            <td style="text-align:center;color:var(--text-secondary)">{{ $item->quantity }}</td>
                            <td style="text-align:right;color:var(--text-secondary)">{{ number_format($item->price, 0, ',', ' ') }} ₸</td>
                            <td style="text-align:right;font-weight:700">{{ number_format($item->total, 0, ',', ' ') }} ₸</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary)">
                    <span>Товары</span>
                    <span>{{ number_format($order->subtotal, 0, ',', ' ') }} ₸</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary)">
                    <span>Доставка</span>
                    <span>{{ $order->delivery_type === 'unconfirmed' ? 'Не согласована · тариф '.number_format((float) config('pilot.delivery_fee',3000),0,',',' ').' ₸' : number_format($order->delivery_fee, 0, ',', ' ').' ₸' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:800;color:var(--text);margin-top:4px;padding-top:8px;border-top:1px solid var(--border)">
                    <span>{{ $order->delivery_type === 'unconfirmed' ? 'Предварительно с доставкой' : 'Итого с доставкой' }}</span>
                    <span>{{ number_format($order->delivery_type === 'unconfirmed' ? (float) $order->subtotal + (float) config('pilot.delivery_fee', 3000) : $order->total, 0, ',', ' ') }} ₸</span>
                </div>
            </div>
        </div>

        {{-- История статусов --}}
        <div class="card">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px">
                История статусов
            </div>
            @foreach($order->statusHistory as $h)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border)">
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="badge badge-neutral">{{ $h->status }}</span>
                    <span style="font-size:13px;color:var(--text-secondary)">{{ $h->comment }}</span>
                </div>
                <span style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                    {{ $h->created_at->format('d.m.Y H:i') }}
                </span>
            </div>
            @endforeach
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
        {{-- Информация --}}
        <div class="card">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-weight:700;font-size:14px">
                Информация
            </div>
            <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Осуждённый</div>
                    <div style="font-weight:600">{{ $order->prisoner_name }}</div>
                </div>
                @if($order->squad_number)
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Отряд</div>
                    <div style="font-weight:600">{{ $order->squad_number }}</div>
                </div>
                @endif
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Учреждение</div>
                    <div style="font-weight:600">{{ $order->institution_name }}</div>
                </div>
                <div style="border-top:1px solid var(--border);padding-top:12px">
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">WhatsApp родственника</div>
                    <div style="font-weight:600">
                        @if($order->contact_phone)<a href="tel:{{ $order->contact_phone }}" style="color:var(--accent);text-decoration:none">
                            {{ $order->contact_phone }}
                        </a>@else Не указан @endif
                    </div>
                </div>
                @if($order->relative_name)
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Родственник</div>
                    <div style="font-weight:600">{{ $order->relative_name }}</div>
                </div>
                @endif
                <div style="border-top:1px solid var(--border);padding-top:12px">
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Создан</div>
                    <div style="font-size:13px;color:var(--text-secondary)">{{ $order->created_at->format('d.m.Y H:i') }}</div>
                </div>
                @if($order->kaspi_transaction_id)
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:2px">Kaspi ID</div>
                    <div style="font-size:12px;font-family:monospace;color:var(--text-secondary)">{{ $order->kaspi_transaction_id }}</div>
                </div>
                @endif
            </div>
        </div>

        <div class="card" style="padding:20px">
            <h2 style="font-size:16px;margin-bottom:12px">Связаться и организовать оплату</h2>
            @php
                $paymentLink = $order->draft ? route('pilot.payment', $order->draft->code) : null;
                $messageTemplates = app(\App\Services\OrderWhatsAppMessage::class)->templates($order);
                $contactText = $messageTemplates['main']['text'];
            @endphp
            @if($order->status === 'new')<p style="margin-bottom:12px">Данные введены на терминале. Уточните ФИО и учреждение, согласуйте доставку с родственником. Затем выберите «Ожидает оплаты» и тип доставки ниже.</p>@endif
            @if($order->contact_phone)<a class="btn btn-primary" target="_blank" rel="noopener noreferrer" href="https://wa.me/{{ preg_replace('/\D/', '', $order->contact_phone) }}?text={{ rawurlencode($contactText) }}">Открыть WhatsApp родственника</a>@else<p>Телефон не указан. Уточните контакт родственника перед согласованием заказа.</p>@endif
            <p style="font-size:12px;margin-top:8px">Откроется подготовленный текст. Отправку подтверждает сотрудник в WhatsApp.</p>
            <p style="font-size:12px;margin-top:8px">До первого обращения проверьте согласие самого родственника на сообщения DoSmart. Номер, введённый на терминале, не подтверждает это согласие. Открытие WhatsApp не подтверждает отправку или получение ответа.</p>
            <label for="whatsapp-message" style="display:block;margin-top:16px">{{ $messageTemplates['main']['title'] }}</label>
            <p style="font-size:12px;margin-top:8px">Звёздочки выделяют важный текст жирным в WhatsApp. Полный состав доступен по личной ссылке.</p>
            <textarea id="whatsapp-message" class="form-control" rows="7" readonly>{{ $contactText }}</textarea>
            <button type="button" class="btn btn-secondary" style="margin-top:8px" onclick="const field=document.getElementById('whatsapp-message'); field.select(); if(navigator.clipboard){navigator.clipboard.writeText(field.value).then(()=>{this.textContent='Скопировано';}).catch(()=>{this.textContent='Текст выделен — скопируйте вручную';});}else{this.textContent='Текст выделен — скопируйте вручную';}">Скопировать сообщение</button>
            @if(count($messageTemplates) > 1)
            <details style="margin-top:16px"><summary style="cursor:pointer">Дополнительные сообщения</summary>
                @foreach($messageTemplates as $key => $template)
                    @continue($key === 'main')
                    <div style="margin-top:16px">
                        <label for="whatsapp-{{ $key }}">{{ $template['title'] }}</label>
                        <textarea id="whatsapp-{{ $key }}" class="form-control" rows="5" readonly>{{ $template['text'] }}</textarea>
                        <button type="button" class="btn btn-secondary" style="margin-top:8px" onclick="const field=document.getElementById('whatsapp-{{ $key }}'); field.select(); if(navigator.clipboard){navigator.clipboard.writeText(field.value).then(()=>{this.textContent='Скопировано';}).catch(()=>{this.textContent='Текст выделен — скопируйте вручную';});}else{this.textContent='Текст выделен — скопируйте вручную';}">Скопировать</button>
                        @if($order->contact_phone)<a class="btn btn-secondary" style="margin-top:8px" target="_blank" rel="noopener noreferrer" href="https://wa.me/{{ preg_replace('/\D/', '', $order->contact_phone) }}?text={{ rawurlencode($template['text']) }}">Открыть WhatsApp</a>@endif
                    </div>
                @endforeach
            </details>
            @endif
            @if($order->status === 'pending' && ! $order->payment_reported_at && ! $order->changeRequests()->where('status', 'pending')->exists() && $paymentLink)
                <label style="display:block;margin-top:16px">Ссылка на оплату для родственника</label>
                <input aria-label="Ссылка на оплату" class="form-control" readonly value="{{ $paymentLink }}" onclick="this.select()">
                <a href="{{ $paymentLink }}" target="_blank" rel="noopener noreferrer">Открыть экран оплаты</a>
                <p style="font-size:12px;margin-top:8px">Ссылка использует настроенную удалённую оплату Kaspi. После платежа обязательно сверьте его в Kaspi Pay.</p>
            @endif
        </div>

        @include('admin.orders.changes')

        @include('admin.orders.actions')

    </div>

</div>
@endsection
