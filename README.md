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
| Админка (список и карточка заказа) | http://localhost:8088/admin/orders |
| Mailpit (письма) | http://localhost:8025 |

Порты можно сменить, если заняты: `APP_PORT=9000 MAILPIT_PORT=9025 docker compose up -d --wait`
(по умолчанию 8088 и 8025).

Сервисы: `app` (php-fpm), `web` (nginx), `queue` (воркер очереди), `db` (PostgreSQL), `mailpit`.

Контейнеры rootless: ни один процесс не работает от root (`app`/`queue` — пользователь `app`, `web` — `nginx-unprivileged`
на порту 8080 внутри, `db` — `postgres`, `mailpit` — `nobody`), у всех `cap_drop: ALL` и `no-new-privileges`.
На Linux, чтобы файлы проекта принадлежали вам, передайте свой UID при сборке:
`APP_UID=$(id -u) docker compose up -d --build --wait` (на macOS подходит значение по умолчанию 1000).
Если каталоги `storage` и `bootstrap/cache` остались от запуска под root, один раз выполните
`sudo chown -R $(id -u):$(id -g) storage bootstrap/cache`.

Скрипт `docker/init-testing-db.sql` создаёт тестовую БД `app_testing` только при первом создании тома; если том
создан раньше, выполните `docker compose down -v`.

Секрет подписи вебхуков — переменная `MARKETPLACE_WEBHOOK_SECRET` (в `.env.example` стоит тестовый
`whsec_test_5f1d8c2a9b` из примера подписи). Допустимый сдвиг timestamp — `MARKETPLACE_WEBHOOK_TOLERANCE`, 300 с.

## Эндпоинты

- `POST /api/webhooks/marketplace` — приём событий MarketHub (подпись обязательна).
- `GET /api/orders?status=&email=&page=&per_page=` — список, сначала новые.
- `GET /api/orders/{id}` — карточка заказа.

Контракт — [`task/openapi.yaml`](task/openapi.yaml).

Страницы `/admin/orders` и `/admin/orders/{id}` — простая админка только для чтения (фильтры по статусу и
email, позиции и история статусов), сделана для ручной проверки. Авторизации нет, как и в API.

**Тестовые кнопки** (включены `ADMIN_TEST_TOOLS=true`, в `.env.example` так и стоит): «Создать тестовый заказ» в списке;
в карточке — переходы в `paid/shipped/delivered/cancelled`, «Дубль последнего события» (ожидаемо `duplicate`)
и «Устаревший снимок» (ожидаемо `stale`). Кнопки отправляют настоящее подписанное событие через маршрут вебхука
(подпись, дедупликация, обработка и письма — как у MarketHub); результат показан сверху страницы.
Кнопки включены, только если одновременно `ADMIN_TEST_TOOLS=true`, `APP_DEBUG=true` и `APP_ENV` не `production`;
при выключенном дебаге или в продакшене маршруты не регистрируются, даже если флаг стоит.

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

## Продакшен

`.env.example` рассчитан на локальный стенд: `APP_DEBUG=true`, `ADMIN_TEST_TOOLS=true`, тестовый `MARKETPLACE_WEBHOOK_SECRET`.
В продакшене их нужно переопределить (`APP_ENV=production`, `APP_DEBUG=false`, настоящий секрет); тестовые кнопки при этом
выключатся сами.

## Остановка

```bash
docker compose down        # остановить
docker compose down -v     # и удалить данные БД
```
