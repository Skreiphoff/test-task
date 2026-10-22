# Заказы MarketHub: вебхуки, письма, API для админки

Laravel 12 / PHP 8.3 / PostgreSQL 16. Сервис принимает вебхуки MarketHub, хранит заказы,
отправляет клиентам письма и отдаёт заказы админке. Условия задания — в [`task/README.md`](task/README.md),
принятые решения — в [`DECISIONS.md`](DECISIONS.md).

## Запуск с нуля

Нужны только Docker и Docker Compose v2.

```bash
git clone <url-репозитория> && cd <папка>
docker compose up -d --build --wait
```

Первый запуск занимает несколько минут: собирается образ, ставятся зависимости Composer.
Команда сама создаёт `.env` из `.env.example`, генерирует `APP_KEY` и выполняет миграции
(повторно вручную: `docker compose exec app php artisan migrate`).

| Что | Адрес |
| --- | --- |
| Приложение / API | http://localhost:8088 |
| Mailpit (письма) | http://localhost:8025 |

Сервисы: `app` (php-fpm), `web` (nginx), `queue` (воркер очереди), `db` (PostgreSQL), `mailpit`.

Секрет подписи вебхуков — переменная `MARKETPLACE_WEBHOOK_SECRET` (в `.env.example` стоит тестовый
`whsec_test_5f1d8c2a9b` из примера подписи). Допустимый сдвиг timestamp — `MARKETPLACE_WEBHOOK_TOLERANCE`, 300 с.

## Эндпоинты

- `POST /api/webhooks/marketplace` — приём событий MarketHub (подпись обязательна).
- `GET /api/orders?status=&email=&page=&per_page=` — список, сначала новые.
- `GET /api/orders/{id}` — карточка заказа.

Контракт — [`task/openapi.yaml`](task/openapi.yaml).

## Проверка вручную

Отправить пример события (нужны локальные PHP 8.2+ и curl):

```bash
export MARKETPLACE_WEBHOOK_SECRET=whsec_test_5f1d8c2a9b
for e in created updated paid shipped cancelled; do
  php task/examples/send-event.php http://localhost:8088/api/webhooks/marketplace task/examples/order-$e.json
done

curl -s http://localhost:8088/api/orders
curl -s http://localhost:8088/api/orders/ord_7Q2KX9M4
```

Письма появятся в Mailpit: «оплачен» и «передан в доставку» для `MH-1048213`, «отменён» для `MH-1048377`.

## Тесты

```bash
docker compose exec app php artisan test
```

Тесты идут на отдельной БД `app_testing` (создаётся вместе с контейнером `db`). Покрыто: подпись
(в т.ч. документированный пример), идемпотентность и повторы, события не по порядку, ручной откат статуса,
письма (без дублей, текст про возврат, пропуск устаревших), фильтры и формат API.

## Остановка

```bash
docker compose down        # остановить
docker compose down -v     # и удалить данные БД
```
