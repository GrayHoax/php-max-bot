# Функциональные тесты PHPMaxBot

Тесты работают с реальным API MAX — никаких моков, только живые запросы.

## Подготовка

**1. Создайте конфиг:**
```bash
cp tests/config.example.php tests/config.php
```

**2. Заполните `tests/config.php`:**
```php
define('BOT_TOKEN',   'ваш-токен');
define('TEST_CHAT_ID', 0);   // ID группового чата
define('TEST_USER_ID', 0);   // ID пользователя (опционально)
```

**3. Узнайте TEST_CHAT_ID и TEST_USER_ID** — запустите `updates.php` и напишите боту:
```bash
php tests/updates.php
```
В выводе будут `chat_id` и `user_id` из последних обновлений.

---

## Запуск

| Команда | Что запускает |
|---------|---------------|
| `php tests/run_all.php` | Все тесты последовательно |
| `php tests/updates.php` | Получение обновлений (с чего начать) |
| `php tests/bot_info.php` | Информация о боте |
| `php tests/messages.php` | Отправка, редактирование, удаление сообщений |
| `php tests/chats.php` | Управление чатами |
| `php tests/members.php` | Участники и администраторы |
| `php tests/keyboard.php` | Все типы кнопок + ручной тест onAttachment |
| `php tests/pins.php` | Закреплённые сообщения |
| `php tests/subscriptions.php` | Подписки |
| `php tests/uploads.php` | Загрузка файлов |

---

## Что нужно боту

- Быть **участником** группового чата из `TEST_CHAT_ID`
- Для `members.php` (addChatAdmin/removeChatAdmin) — права **администратора** в чате
- Для `pins.php` — права на **закрепление сообщений**
- Пользователь из `TEST_USER_ID` должен хотя бы раз написать боту (`/start`)

---

## Пример вывода

```
┌─ Сообщения

  ✓ sendMessageToChat — отправка текста в чат
  ✓ sendMessageToUser — отправка текста пользователю
  ✓ sendMessage — с явным chat_id в параметре extra
  ~ sendMessage — с явным user_id в параметре extra (TEST_USER_ID не задан)
  ✓ sendMessageToChat — markdown форматирование
  ✗ editMessage — редактирование текста сообщения
    → Ответ не содержит ключ 'mid'

──────────────────────────────────────────────────
Итого: 5  ✓ 4 пройдено  ✗ 1 провалено  ~ 1 пропущено
```

**Коды завершения:** `0` — всё прошло, `1` — есть провалы (удобно для CI).
