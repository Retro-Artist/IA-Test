<?php

/**
 * Simple PHP Initialization
 * A minimal Docker environment for PHP 8.4.7 development
 */

// Setup database connection and fetch necessary data
function setupDatabaseConnection()
{
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_DATABASE') ?: 'simple_php',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: 'root_password',
    ];

    $result = [
        'connection' => null,
        'connected' => false,
        'error' => null,
        'notes' => [],
        'tableExists' => false,
        'mysqlVersion' => null,
        'dbname' => $config['dbname']
    ];

    try {
        // Try to connect (max 3 attempts)
        for ($i = 0; $i < 3; $i++) {
            try {
                $dsn = "mysql:host={$config['host']};dbname={$config['dbname']}";
                $result['connection'] = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $result['connected'] = true;

                // Check for notes table and fetch data
                if ($result['connection']->query("SHOW TABLES LIKE 'notes'")->rowCount() > 0) {
                    $result['tableExists'] = true;
                    $result['notes'] = $result['connection']->query("SELECT * FROM notes ORDER BY created_at DESC")
                        ->fetchAll(PDO::FETCH_ASSOC);
                }

                // Get MySQL version
                $result['mysqlVersion'] = $result['connection']->query('SELECT version()')->fetchColumn();
                break;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    $result['error'] = "Database \"{$config['dbname']}\" does not exist yet!";
                    break;
                }

                if ($i === 2) throw $e; // Last attempt failed
                sleep(1); // Wait before retry
            }
        }
    } catch (PDOException $e) {
        $result['error'] = 'Database connection failed: ' . $e->getMessage();
    }

    return $result;
}

// Get database connection and data
$db = setupDatabaseConnection();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple PHP Initialization</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Font Awesome for icons -->
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-php-50 text-php-800">
    <div class="max-w-5xl mx-auto p-6">
        <header class="pb-4 border-b border-php-200 mb-6">
            <h1 class="text-3xl font-bold text-php-900">Simple PHP Initialization</h1>
            <p class="text-lg text-php-600">Your PHP <?= phpversion() ?> environment is ready!</p>
        </header>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Environment Info -->
            <div class="bg-white rounded-md shadow-sm border border-php-200">
                <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                    <i class="fas fa-circle-info mr-2"></i> Environment Information
                </div>
                <ul class="divide-y divide-php-100 p-0">
                    <li class="p-3 text-php-700">PHP Version: <span class="text-php-900 font-medium"><?= phpversion() ?></span></li>
                    <li class="p-3 text-php-700">Web Server: <span class="text-php-900 font-medium"><?= $_SERVER['SERVER_SOFTWARE'] ?></span></li>
                    <li class="p-3 text-php-700">Document Root: <span class="text-php-900 font-medium"><?= $_SERVER['DOCUMENT_ROOT'] ?></span></li>
                    <li class="p-3 text-php-700">Server Protocol: <span class="text-php-900 font-medium"><?= $_SERVER['SERVER_PROTOCOL'] ?></span></li>
                </ul>
            </div>

            <!-- Database Connection -->
            <div class="bg-white rounded-md shadow-sm border border-php-200">
                <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                    <i class="fas fa-database mr-2"></i> Database Connection
                </div>
                <div class="p-4">
                    <?php if ($db['connected']): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 p-3 rounded-md mb-3">
                            Database connection successful!
                        </div>
                        <p class="text-php-700">Connected to database: <strong class="text-php-900"><?= htmlspecialchars($db['dbname']) ?></strong></p>
                        <?php if ($db['mysqlVersion']): ?>
                            <p class="text-php-700">MySQL version: <strong class="text-php-900"><?= htmlspecialchars($db['mysqlVersion']) ?></strong></p>
                        <?php endif; ?>
                    <?php elseif (isset($db['error']) && strpos($db['error'], 'does not exist yet') !== false): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-3">
                           <?= htmlspecialchars($db['error']) ?>
                        </div>
                        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-md">
                            <h4 class="font-medium mb-3 text-red-800 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2 text-red-600 opacity-75"></i>
                                Database Needs Initialization
                            </h4>
                            <p class="text-red-700 mb-4">Choose one of the following options to initialize your database:</p>

                            <div class="space-y-3">
                                <div class="bg-white border border-red-100 rounded-md overflow-hidden">
                                    <div class="bg-gradient-to-r from-red-100 to-red-50 p-3 border-b border-red-100">
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 bg-red-200 text-red-700 rounded-full flex items-center justify-center text-sm font-medium mr-3">1</div>
                                            <span class="font-medium text-red-800">Migration Script</span>
                                        </div>
                                    </div>
                                    <div class="p-4 space-y-3">
                                        <div class="flex items-start space-x-3">
                                            <span class="text-slate-500 text-sm font-medium min-w-[4rem]">Docker:</span>
                                            <code class="bg-slate-50 text-slate-700 px-3 py-1.5 rounded-md text-sm flex-1 block">
                                                docker-compose exec app php database/migrate.php
                                            </code>
                                        </div>
                                        <div class="flex items-start space-x-3">
                                            <span class="text-slate-500 text-sm font-medium min-w-[4rem]">Standard:</span>
                                            <code class="bg-slate-50 text-slate-700 px-3 py-1.5 rounded-md text-sm flex-1 block">
                                                php database/migrate.php
                                            </code>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white border border-red-100 rounded-md overflow-hidden">
                                    <div class="bg-gradient-to-r from-red-100 to-red-50 p-3 border-b border-red-100">
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 bg-red-200 text-red-700 rounded-full flex items-center justify-center text-sm font-medium mr-3">2</div>
                                            <span class="font-medium text-red-800">Manual Import</span>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <div class="space-y-2">
                                            <div class="flex items-center space-x-3">
                                                <span class="w-1.5 h-1.5 bg-red-300 rounded-full"></span>
                                                <span class="text-slate-600 text-sm">Access phpMyAdmin at <a href="http://localhost:8081" class="text-red-600 hover:text-red-700 underline decoration-red-200 hover:decoration-red-300">localhost:8081</a></span>
                                            </div>
                                            <div class="flex items-center space-x-3">
                                                <span class="w-1.5 h-1.5 bg-red-300 rounded-full"></span>
                                                <span class="text-slate-600 text-sm">Create database <strong class="text-slate-700"><?= htmlspecialchars($db['dbname']) ?></strong></span>
                                            </div>
                                            <div class="flex items-center space-x-3">
                                                <span class="w-1.5 h-1.5 bg-red-300 rounded-full"></span>
                                                <span class="text-slate-600 text-sm">Import <code class="bg-slate-50 text-slate-700 px-2 py-0.5 rounded text-xs">database/database.sql</code></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-md mb-3">
                            <?= htmlspecialchars($db['error']) ?>
                        </div>
                        <p class="mt-2 text-php-700">Check your database connection settings in the .env file</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div class="bg-white rounded-md shadow-sm border border-php-200 mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                <i class="fas fa-puzzle-piece mr-2"></i> Available PHP Extensions
            </div>
            <div class="p-4">
                <div class="grid md:grid-cols-3 gap-4">
                    <?php
                    $extensions = get_loaded_extensions();
                    sort($extensions);
                    $chunks = array_chunk($extensions, ceil(count($extensions) / 3));

                    foreach ($chunks as $chunk) {
                        echo '<ul class="divide-y divide-php-100 text-sm">';
                        foreach ($chunk as $ext) {
                            echo '<li class="py-1.5 text-php-700">' . htmlspecialchars($ext) . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Sample Notes Display -->
        <?php if ($db['tableExists'] && count($db['notes']) > 0): ?>
            <div class="bg-white rounded-md shadow-sm border border-php-200 mb-6">
                <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                    <i class="fas fa-sticky-note mr-2"></i> Sample Notes from Database
                </div>
                <div class="p-4 space-y-3">
                    <?php foreach ($db['notes'] as $note): ?>
                        <div class="border border-php-200 rounded-md">
                            <div class="bg-php-200 p-2 border-b border-php-200 font-medium text-php-800">
                                <?= htmlspecialchars($note['title']) ?>
                            </div>
                            <div class="p-3">
                                <p class="text-php-700"><?= htmlspecialchars($note['content']) ?></p>
                                <div class="text-php-500 text-xs mt-2">Created: <?= htmlspecialchars($note['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!$db['tableExists'] && $db['connected']): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-3 mb-6 rounded-md">
                <h4 class="font-semibold mb-1 text-yellow-900">Database exists but tables are missing</h4>
                <p class="text-yellow-800">Regenerate the database with the migration script to test and visualize the assigned tables:</p>
                <pre class=" text-yellow-800 p-2 mt-1 rounded border border-yellow-200 text-sm">docker-compose exec app php database/migrate.php</pre>
            </div>
        <?php endif; ?>

        <!-- Next Steps Cards -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-md shadow-sm border border-php-200">
                <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                    <i class="fas fa-code mr-2"></i> Standard Environment Setup
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">1</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Initialize your database</p>
                                <div class="space-y-2 text-sm text-php-700">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-1.5 h-1.5 bg-php-400 rounded-full"></span>
                                        <span>Manually import: <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs">database/database.sql</code></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="w-1.5 h-1.5 bg-php-400 rounded-full"></span>
                                        <span>Or run migration: <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs">php database/migrate.php</code></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">2</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Install packages: <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs ml-1">composer install</code></p>
                            </div>
                        </div>


                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">4</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Switch your project's root directory To <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs ml-1">htdocs/public/</code> directory</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">3</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Start building in <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs ml-1">public/</code> directory</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-md shadow-sm border border-php-200">
                <div class="bg-gradient-to-r from-php-500 to-php-600 text-white p-3 rounded-t-md">
                    <i class="fab fa-docker mr-2"></i> Docker Environment Setup
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">1</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Run database migration</p>
                                <code class="bg-php-100 text-php-800 px-2 py-1 rounded border border-php-200 text-xs block">docker-compose exec app php database/migrate.php</code>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">2</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Access phpMyAdmin at <a href="http://localhost:8081" class="text-php-600 hover:text-php-800 underline decoration-php-300 hover:decoration-php-400">localhost:8081</a></p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">3</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Install packages</p>
                                <code class="bg-php-100 text-php-800 px-2 py-1 rounded border border-php-200 text-xs block">docker-compose exec app composer install</code>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-php-100 text-php-700 rounded-full flex items-center justify-center text-sm font-medium">4</div>
                            <div class="flex-1">
                                <p class="text-php-800 font-medium mb-1">Start building in <code class="bg-php-100 text-php-800 px-2 py-0.5 rounded border border-php-200 text-xs ml-1">public/</code> directory</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="pt-4 my-md-5 pt-md-5 border-t border-php-200">
            <div class="row">
                <div class="col-12 col-md">
                    <small class="d-block mb-3 text-php-500">&copy; <?= date('Y') ?> Simple PHP Initialization</small>
                </div>
            </div>
        </footer>
    </div>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'php': {
                            50: '#f8f9fc',
                            100: '#f1f2f8',
                            200: '#e2e5f0',
                            300: '#c8cde3',
                            400: '#9ba5d1',
                            500: '#7881bf',
                            600: '#4e5b93',
                            700: '#414d7a',
                            800: '#363f64',
                            900: '#2d3553',
                        }
                    }
                }
            }
        }
    </script>
</body>

</html>