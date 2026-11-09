<?php

namespace App\Services;

use App\Models\Order;

class OrderWhatsAppMessage
{
    public function build(Order $order): string
    {
        return $this->templates($order)['main']['text'];
    }

    /** Only offer messages that are true for the order's current state. */
    public function templates(Order $order): array
    {
        $money = fn ($amount) => number_format((float) $amount, 2, ',', ' ').' ₸';
        $header = '*Dosmart · заявка № '.$order->order_number.'*';
        $recipient = trim(preg_replace('/\s+/u', ' ', (string) $order->prisoner_name));
        if ($recipient !== '') {
            $header .= "\nПолучатель: *".$recipient.'*';
        }
        $link = $order->draft ? route('pilot.payment', $order->draft->code) : null;
        $page = $link ? "\n\nСостав и статус: ".$link."\nЛичная ссылка — не передавайте посторонним." : '';
        $delivery = 'Доставка — *'.number_format((float) config('pilot.delivery_fee', 3000), 0, ',', ' ').' ₸*.';
        $initialTotal = (float) $order->subtotal + (float) config('pilot.delivery_fee', 3000);
        $message = fn ($title, $text) => ['title' => $title, 'text' => $header."\n\n".$text
            .(! in_array($order->status, ['cancelled', 'refunded'], true) && ! str_contains($text, 'Доставка:') ? "\n\n".$delivery : '')];
        $pendingChanges = $order->changeRequests()->where('status', 'pending')->exists();
        $unpaid = $order->payment_status === 'unpaid';
        $templates = [];

        if ($order->status === 'cancelled') {
            $main = $message('Заявка отменена', 'Заявка отменена. Не оплачивайте её. Если деньги уже отправлены, сообщите нам в этом чате.');
        } elseif ($order->status === 'refunded') {
            $main = $message('Возврат', 'Возврат выполнен. Общая возвращённая сумма: *'.$money($order->refund_amount).'*.' );
        } elseif ($pendingChanges) {
            $main = $message('Изменения на согласовании', 'Изменения заявки рассматриваются сотрудником. *Пока не оплачивайте*; дождитесь подтверждения состава и суммы.'.$page);
        } elseif ($order->status === 'new') {
            $main = $message('Первое обращение', 'Здравствуйте! Ваш родственник оставил заявку в Dosmart.' . "\n\n" . '*Итого с доставкой: '.$money($initialTotal).'*' . "\n" . 'Доставка: *'.$money(config('pilot.delivery_fee', 3000)).'* — включена в сумму.' . "\n\n" . 'Подтвердите состав и доставку. Сумма предварительная; *пока оплачивать ничего не нужно*.'.$page);
            $templates['reminder'] = $message('Напомнить о согласовании', 'Подскажите, актуальна ли заявка? Ждём вашего ответа для согласования. До подтверждения заказа оплачивать не нужно.');
        } elseif ($order->status === 'pending' && $order->payment_reported_at) {
            $main = $message('Проверяем оплату', 'Вы сообщили об оплате. Проверяем поступление денег. *Повторно платить не нужно*.' . "\n" . 'Если ещё не отправили квитанцию, прикрепите её *в WhatsApp сотруднику* и укажите *номер заявки и ФИО получателя*.');
        } elseif ($order->status === 'pending' && $unpaid) {
            $kaspiUrl = (string) config('pilot.kaspi_url');
            $validKaspi = filter_var($kaspiUrl, FILTER_VALIDATE_URL) && parse_url($kaspiUrl, PHP_URL_SCHEME) === 'https';
            if ($link && $validKaspi) {
                $main = $message('Согласовано — оплата', '*К оплате: '.$money($order->total).'*' . "\n" . 'Доставка: *'.$money($order->delivery_fee).'*.' . "\n\n" . 'Kaspi: '.$kaspiUrl . "\n" . 'Введите точную сумму. Квитанцию отправьте *в WhatsApp сотруднику* и укажите *номер заявки и ФИО получателя*.' . "\n" . 'Затем нажмите *«Я оплатил»* на странице заказа. Проверим поступление денег.'.$page);
                $templates['payment_help'] = $message('Как оплатить и отправить квитанцию', '1. В Kaspi введите *'.$money($order->total).'*.' . "\n" . '2. Если доступен комментарий к платежу, укажите *номер заявки и ФИО получателя*.' . "\n" . '3. Квитанцию отправьте *в WhatsApp сотруднику* вместе с этими данными.' . "\n" . '4. На странице заказа нажмите *«Я оплатил»*. Дождитесь проверки поступления денег.'.$page);
                $templates['reminder'] = $message('Напомнить об оплате', 'Заказ согласован. Ожидаем оплату *'.$money($order->total).'*. Если уже оплатили, отправьте квитанцию — сначала проверим платёж, повторно не платите.'.$page);
            } else {
                $main = $message('Уточнить оплату', 'Порядок оплаты уточнит сотрудник. Пока не переводите деньги — дождитесь инструкции.');
            }
        } elseif ($order->status === 'delivering') {
            $main = $message('Заказ в доставке', 'Заказ передан в доставку. Сообщим после фактической передачи.'.$page);
        } elseif ($order->status === 'delivered') {
            $main = $message('Заказ передан', 'Заказ передан. Спасибо за обращение в Dosmart!'.$page);
        } elseif ($order->payment_status === 'paid') {
            $main = $message('Оплата подтверждена', 'Оплата *'.$money($order->total).'* подтверждена. Заказ '.($order->status === 'processing' ? 'собирается' : 'готовится к сборке').'. *Повторно платить не нужно*.'.$page);
        } else {
            $main = $message('Текущий статус', 'Статус: '.$order->status_label.'. По вопросам ответьте в этот чат.'.$page);
        }

        if ($link && in_array($order->status, ['new', 'pending'], true) && $unpaid && ! $order->payment_reported_at) {
            $templates['changes'] = $message('Как изменить состав', 'Для изменения состава откройте страницу заявки и выберите «Изменить заявку». Отправьте пожелания и дождитесь нашего подтверждения. До согласования изменений не оплачивайте.'.$page);
        }
        if (! in_array($order->status, ['cancelled', 'refunded', 'delivered'], true)) {
            $templates['hours'] = $message('Время обработки', 'Заявки обрабатываются с '.config('pilot.service_hours').' по времени Казахстана (UTC+5). Ответим в рабочее время.');
        }

        return ['main' => $main] + $templates;
    }
}
