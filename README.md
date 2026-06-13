# Eloquent Shareables

[![Latest Version on Packagist](https://img.shields.io/packagist/v/whilesmart/eloquent-shareables.svg?style=flat-square)](https://packagist.org/packages/whilesmart/eloquent-shareables)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/whilesmart/eloquent-shareables/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/whilesmart/eloquent-shareables/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/whilesmart/eloquent-shareables.svg?style=flat-square)](https://packagist.org/packages/whilesmart/eloquent-shareables)

**Eloquent Shareables** is a lightweight Laravel package that allows you to easily share Eloquent models with different audiences and owners. It supports assigning specific access levels, managing unique sharing tokens, and querying shared models.

---

## Installation

You can install the package via Composer:

```bash
composer require whilesmart/eloquent-shareables
```

You should publish and run the migrations:

```bash
php artisan vendor:publish --tag="shareables-migrations"
php artisan migrate
```

You can optionally publish the config file:

```bash
php artisan vendor:publish --tag="shareables-config"
```

This will create a `config/sharables.php` file in your application directory.

---

## Usage

### Preparing Your Models

Add the `Shareable` trait to any Eloquent model you want to share:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Shareables\Traits\Shareable;

class Post extends Model
{
    use Shareable;
}
```

### 1. Sharing Models

To share a model, call the `share()` method:

```php
// Share a post with read-only access to a specific audience, owned by the current user
$share = $post->share('read', $user, 'team_123');

// Share a post publicly with a custom or automatically generated token
$publicShare = $post->share('view', $user);
// A unique token (e.g. Str::random(40)) is generated if none is passed.
```

### 2. Checking Share Status

You can check if a model is currently shared with an audience or via a specific token:

```php
// Check if shared with an audience (e.g. team_123)
if ($post->isSharedWith('team_123')) {
    // Shared!
}

// Check if shared via a specific access token
if ($post->isSharedViaToken($token)) {
    // Shared!
}
```

### 3. Revoking Shares

Shares can be revoked either individually (for an audience) or all at once:

```php
// Revoke sharing for a specific audience
$post->revokeShare('team_123');

// Revoke all shares for this model
$post->revokeAllShares();
```

### 4. Querying Shared Models

Use the `sharedWith` query scope to retrieve models shared with a specific audience:

```php
$posts = Post::sharedWith('team_123')->get();
```

---

## Testing

You can run the package test suite using:

```bash
composer test
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
