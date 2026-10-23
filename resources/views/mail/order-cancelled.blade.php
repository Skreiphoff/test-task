Здравствуйте, {!! $order->customer_name !!}!

Заказ №{!! $order->number !!} отменён.
@if ($order->cancel_reason)

Причина: {!! $order->cancel_reason !!}
@endif
@if ($order->paid_at)

Деньги за заказ будут возвращены в течение 10 дней.
@endif
