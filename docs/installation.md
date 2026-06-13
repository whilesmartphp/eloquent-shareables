# Installation

You can install the **Eloquent Shareables** package via composer:

```bash
composer require whilesmart/eloquent-shareables
```

## Publishing Assets

After installing the package, you should publish and run the migrations:

```bash
php artisan vendor:publish --tag="shareables-migrations"
php artisan migrate
```

You can optionally publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --tag="shareables-config"
```

This will create a `config/shareables.php` file in your application.
