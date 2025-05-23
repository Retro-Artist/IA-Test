# PHP 8.4.7 Docker Development Environment

A clean and maintainable development environment for PHP 8.4.7 applications, running on Nginx with MySQL database and phpMyAdmin.

## Features

- **Modern PHP Environment**: PHP 8.4.7 with all essential extensions pre-installed
- **Complete Development Stack**: Nginx, PHP-FPM, MySQL, and phpMyAdmin configured and ready to use
- **Docker-based**: Consistent environment across all development machines
- **Ready for Any Framework**: Use any PHP framework or build your own from scratch
- **Minimal Starting Point**: Clean slate to build your application your way

## Project Structure

```
.
├── public/                 # Web root directory
│   └── index.php           # Application entry point
├── .env                    # Environment configuration
├── .env.example            # Environment template
├── .gitignore              # Git ignore patterns
├── Dockerfile              # PHP-FPM configuration
├── nginx.conf              # Nginx server settings
├── composer.json           # PHP dependencies configuration
└── docker-compose.yml      # Container orchestration
```

## Requirements

- Docker and Docker Compose
- Git (optional)

## Quick Start

1. Clone the repository:

```bash
git clone https://github.com/Retro-Artist/php83-docker-env.git
cd docker-template
```

2. Set up environment configuration:

```bash
mv .env.example .env
```

3. Start the Docker containers:

```bash
docker-compose up -d
```

4. Install Composer dependencies (optional):

```bash
docker-compose exec app composer install
```

5. Run the database migration script:

```bash
docker-compose exec app php database/migrate.php
```

6. Access your development environment:

- **Application**: [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin**: [http://localhost:8081](http://localhost:8081)
  - Server: localhost
  - Username: root
  - Password: root_password

## Development

This environment gives you complete freedom to structure your PHP application however you want.

### Development Workflow

1. Edit files in the `public/` directory or create your own structure
2. Changes are immediately available at [http://localhost:8080](http://localhost:8080)
3. Manage your database through phpMyAdmin at [http://localhost:8081](http://localhost:8081)
4. Install additional PHP packages using Composer

### Directory Structure Freedom

You can:
- Build a flat application within the `public/` directory
- Create your own directories for models, views, controllers, etc.
- Install and use any PHP framework
- Implement any architecture pattern (MVC, ADR, etc.)

### Database Connection

Use these credentials to connect to MySQL from your PHP application:

```php
$host = 'localhost';     // Container service name
$dbname = 'simple_php';  // Default database (configurable in .env)
$username = 'root';
$password = 'root_password';
```

## Deployment to Production

To deploy to a Linux production server:

1. Clone the repository on the server.
2. Configure your production Nginx server to point to the `public` directory.
3. Set up the MySQL database and update the `.env` file with the production credentials.
4. Install Composer dependencies with `composer install --no-dev`.
5. Run the database migration script.

## License

This project is open-sourced software licensed under the MIT license.