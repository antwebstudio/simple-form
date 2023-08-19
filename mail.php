<?php

// You may either copy this page or just require it.

require_once 'vendor/autoload.php';

if (!runInConsole()  && config('app.maintenance', false)) {
    die('maintenance');
}

use Illuminate\Support\Arr;

if (runInConsole()) {
    sendMail(composeMail()
        ->text('This is an testing email'));
}

$validator = Validator::make($_POST, [
    'email' => 'email',
    'recaptcha' => [
        'required',
        function ($attribute, $value, $fail) {
            if (!validateRecaptcha($value)) {
                $fail('Invalid recaptcha value');
            }
        }
    ],
], [
    'required' => ':attribute is required',
	'email' => ':attribute is invalid email',
], [
]);

$store = Arr::except($validator->valid(), ['recaptcha', 'client', 'tnc']);

if ($validator->fails()) {
    $jsonString = json_encode([
        'errors' => $validator->errors(),
        'old' => $_POST,
    ]);

    die( str_replace('{{ $jsonResponse }}', $jsonString, file_get_contents(config('path.form', 'register.html'))) );
} else {
    if (config('db.enabled', true)) {
        FormResponse::create([
            'data' => $store,
        ]);
    }

    sendMail(composeMail()
        // ->text('Sending emails is fun again!')
        ->html(renderView('mail', $store)));

	redirect(config('path.thankyou', 'register.html?thank'));
}
