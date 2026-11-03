# Installation

Requires PHP 8.4 and Laravel 12.

```bash
composer require whilesmart/eloquent-shareables:^2.0
php artisan migrate
```

Package migrations load automatically. They create `shares` for link constraints and creator tracking, and `share_audiences` for typed recipients and per-recipient abilities.

Configuration is optional:

```bash
php artisan vendor:publish --tag=shareables-config
```

The published `config/shareables.php` supports custom subclasses of the share and audience models. Recipient models implement the `ShareAudience` admission contract; shareable resources use the `Shareable` trait.
