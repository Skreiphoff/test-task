Здравствуйте, {!! $order->customer_name !!}!

Заказ №{!! $order->number !!} передан в доставку.

@if ($order->shipment_carrier)
Служба доставки: {!! $order->shipment_carrier !!}
@endif
@if ($order->shipment_tracking_number)
Трек-номер: {!! $order->shipment_tracking_number !!}
@endif
@if ($order->shipment_tracking_url)
Отследить посылку: {!! $order->shipment_tracking_url !!}
@endif
