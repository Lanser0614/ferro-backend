# Ferro Backend

Интеграционный сервис, который хранит локальную историю заказов и синхронизирует сделки и контакты Bitrix24 с SUP CRM / SAP.  
Проект развёрнут на Laravel и предоставляет ограниченный набор внутренних API-эндпоинтов, которые вызываются вебхуками Bitrix.

## Быстрый старт

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate   # если база данных используется локально
php artisan serve
```

Не забудьте заполнить переменные окружения из блока ниже перед запуском обработчиков.

## Ключевые переменные `.env`

| Переменная | Назначение |
|-----------|------------|
| `FERRO_API_BASE_URL`, `FERRO_API_TOKEN` | Настройки Ferro API, откуда подтягиваются заказы. |
| `FERRO_SITE_BACKEND_API_BASE_URL`, `FERRO_SITE_BACKEND_API_TOKEN` | Доступ к Ferro Site Backend для получения и маппинга заказов. |
| `BITRIX_WEBHOOK_DOMAIN` | Домен Bitrix24, из которого приходят вебхуки (используется как логин Basic Auth и проверка вебхук-пакетов). |
| `BITRIX_WEBHOOK_APPLICATION_TOKEN` | Токен приложения Bitrix24 (используется как пароль Basic Auth и проверка вебхук-пакетов). |

Значения `BITRIX_*` применяются сразу в двух местах:

1. **API-мидлвар `bitrix.webhook`** — проверяет, что webhook содержит `auth.domain` и `auth.application_token`, совпадающие с `.env`. При несовпадении возвращается `401`.
2. **Basic Auth для документации** — страница `/docs/api` и schema `/docs/openapi.yaml` закрыты простейшей авторизацией. Используйте домен как username и токен как password.

## API-эндпоинты

Файл `routes/api.php` содержит два рабочих маршрута внутри middleware `bitrix.webhook`:

- `POST /api/contact/history/order/{contactId}` — синхронизирует историю заказов SUP в таймлайн контакта Bitrix и сохраняет ID заказов в таблице `ferro_sup_orders`.
- `POST /api/sup/orders/create/{dealId}` — создаёт заказ в SUP по сделке Bitrix (использует Ferro Site Backend для маппинга и после успешной синхронизации проставляет `UF_CRM_1765651317145`).

Оба маршрута объявлены как `Route::any`, но в документации рекомендуем использовать POST, так как операции изменяют состояние.

### Пример входящего вебхука от Bitrix

```json
{
  "event": "ONCRMDEALUPDATE",
  "data": { "FIELDS": { "ID": "30" } },
  "ts": "1765654070",
  "auth": {
    "domain": "bitrix24.ferro.uz",
    "client_endpoint": "https://bitrix24.ferro.uz/rest/",
    "application_token": "d74dykccgbxzy4kgoaf3ol1dkw4qjz2w"
  }
}
```

В обработчике используйте `data.FIELDS.ID` для вызова `/api/sup/orders/create/{dealId}`.

## Документация (Redoc)

- OpenAPI схема: `docs/openapi.yaml` (описана на русском языке).
- UI: `resources/views/docs/redoc.blade.php`.

Для просмотра:

```bash
php artisan serve
# затем в браузере перейти на http://localhost:8000/docs/api
# появится окно Basic Auth -> username = BITRIX_WEBHOOK_DOMAIN, password = BITRIX_WEBHOOK_APPLICATION_TOKEN
```

## Консольная команда синхронизации

`app:sync-order-from-ferro-site-command` (`app/Console/Commands/SyncOrderFromFerroSiteCommand.php`) поднимает список заказов с сайта ferro.uz за последние 3 дня через `FerroSiteBackEndHttpService`, после чего вызывает `BitrixOrderSyncUseCase` для каждого заказа и прокидывает его в Bitrix24.

Команду стоит запускать каждый час:

```bash
php artisan app:sync-order-from-ferro-site-command
```

В production-среде добавьте её в шедулер Laravel:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('app:sync-order-from-ferro-site-command')->hourly();
}
```

и настройте cron:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```


## Логгирование вебхуков

`Route::any('/api', ...)` в `routes/api.php` логирует каждое входящее сообщение целиком (`Log::info('data', $request->all())`). Используйте это для отладки интеграций.

## Тестирование

```bash
php artisan test
```

Перед пушем обязательно прогоните тесты и убедитесь, что критические env переменные заполнены.

## Лицензия

MIT (см. `LICENSE`), как и оригинальный Laravel skeleton.
