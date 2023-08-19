<?php

// You may either copy this page or just require it.

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

require_once 'vendor/autoload.php';

if (!config('db.enabled', true)) {
    die('Database is disabled');
}

if (isset($_POST['username']) && isset($_POST['password'])) {
    loginAsAdmin($_POST['username'], $_POST['password']);
    reload();
} else if (isset($_POST['logout'])) {
    logout();
    reload();
}

$logged = session()->get('username');
if ($logged) {
    $responses = FormResponse::latest('created_at')->paginate(25);

    $columns = [];
    if ($responses->isNotEmpty()) {
        $columns = config('responses.columns');
        if (!isset($columns)) {
            $columns = collect($responses->first()->data)->map(function($value, $key) {
                return 'data.'.$key;
            })->toArray();
        }
    }
    $columns = array_merge([
        'id',
        'created_at',
    ], $columns, [

    ]);
}
?>
<?php if ($logged ?? false): ?>
    <!doctype html>
    <html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 p-8">
        <form method="post"><input type="hidden" name="logout" /><button>Logout</button></form>
        
        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-200">
                        <?php foreach ($columns as $handle): ?>
                            <th class="py-2 px-4 text-left"><?= Str::title(Str::after($handle, '.')) ?></th>
                        <?php endforeach ?>
                        <?php if (config('responses.show_raw')): ?>
                            <th class="py-2 px-4 text-left">Raw</th>
                        <?php endif ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responses as $row): ?>
                        <tr class="border-t">
                            <?php foreach ($columns as $handle): ?>
                                <td class="py-2 px-4"><?= Arr::get($row, $handle, null) ?></td>
                            <?php endforeach ?>
                            <?php if (config('responses.show_raw')): ?>
                                <td class="py-2 px-4"><?= json_encode($row['data']) ?></td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr class="border-t">
                        <td><?= renderPaginator($responses) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </body>
    </html>
<?php else: ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Page</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <div class="bg-white p-8 rounded shadow-md w-96">
            <h1 class="text-2xl font-semibold mb-4">Login</h1>
            <form method="post">
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" class="mt-1 px-3 py-2 block w-full rounded-md border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="mt-1 px-3 py-2 block w-full rounded-md border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">Login</button>
            </form>
        </div>
    </body>
    </html>
<?php endif ?>