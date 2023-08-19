<?php
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Reader;
use Maatwebsite\Excel\Writer;
use Maatwebsite\Excel\Exporter;
use Illuminate\Events\Dispatcher;
use Symfony\Component\Mime\Email;
use Illuminate\Support\Collection;
use Illuminate\Validation\Factory;
use Illuminate\Container\Container;
use Maatwebsite\Excel\QueuedWriter;
use Symfony\Component\Mailer\Mailer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Mailer\Transport;
use Maatwebsite\Excel\DefaultValueBinder;
use Illuminate\Session\FileSessionHandler;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Maatwebsite\Excel\Transactions\TransactionHandler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Files\Filesystem as MaatFileSystem;
use Maatwebsite\Excel\Transactions\NullTransactionHandler;

function config($name, $default = null) {
    $config = require dirname(dirname(dirname(__DIR__))).'/config.php';
    return Arr::get($config, $name, $default);
}

function storage_path($path) {
    return dirname(dirname(dirname(__DIR__))).'/'.ltrim($path, '/\\');
}

function app($class) {
    $temporaryFileFactory = new TemporaryFileFactory(
        config('excel.temporary_files.local_path', config('excel.exports.temp_path', storage_path('laravel-excel'))),
        config('excel.temporary_files.remote_disk')
    );
    switch($class) {
        case DefaultValueBinder::class:
            return new DefaultValueBinder;
        case MaatFileSystem::class:
            return new MaatFileSystem(new \Illuminate\Filesystem\FilesystemManager(null));
        case Writer::class:
            return new \Maatwebsite\Excel\Writer($temporaryFileFactory);
        case Reader::class:
            return new Reader($temporaryFileFactory, app(TransactionHandler::class));
        case TransactionHandler::class:
            return new NullTransactionHandler;
        case QueuedWriter::class:
            return new QueuedWriter(app(Writer::class), $temporaryFileFactory);
        case TemporaryFileFactory::class:
            return $temporaryFileFactory;
        case Exporter::class:
            return new \Maatwebsite\Excel\Excel(
                app(Writer::class),
                app(QueuedWriter::class),
                app(Reader::class),
                app(MaatFileSystem::class)
            );
    }
}

function debugMode() {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

if (config('app.debug', false)) {
    debugMode();
}

if (!file_exists(dirname(dirname(__DIR__)).'/.htaccess')) {
    file_put_contents(dirname(dirname(__DIR__)).'/.htaccess', "Order allow,deny\nDeny from all");
}

function session($data = null) {
    if (isset($data) && is_array($data)) {
        foreach ($data as $key => $value) {
            session()->put($key, $value);
        }
        session()->save();
    } else {
        $session = Session::$manager;

        if (isset($session)) return $session;
        
        // if (!isset($_GLOBAL['session'])) {
            $sessionPath = ltrim(config('session.path', 'session'), '\/\\');
            $sessionPath = dirname(dirname(dirname(__DIR__))).'/'.$sessionPath;
            
            $session = new \Illuminate\Session\Store('session', new FileSessionHandler(new Filesystem(), $sessionPath, 60), $_COOKIE['session_id'] ?? null);
            $session->start();
            // $session->put('test', 'abc');
            // $session->save();
            setcookie('session_id', $session->getId());

        //     $_GLOBAL['session'] = $session;
        // }

        // return $_GLOBAL['session'];

        Session::$manager = $session;

        return $session;
    }
}

function response() {
    return new ApplicationResponse;
}

function resetAdmin($username, $password) {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    throw new \Exception('Not yet implemented');
}

function logout() {
    session()->forget('username');
    session()->flush();
    session()->save();
}

function loginAsAdmin($username, $password) {
    if ($username == config('admin.username') && password_verify($password, config('admin.password'))) {
        session(['username' => $username]);
        return true;
    }
    return false;
}

function runInConsole() {
    return PHP_SAPI === 'cli';
}

function validateRecaptcha($value) {
    $recaptchaClass = '';

    switch (config('recaptcha.version')) {
        case 'v3':
            $recaptchaClass = \Biscolab\ReCaptcha\ReCaptchaBuilderV3::class;
            break;
        case 'v2':
            $recaptchaClass = \Biscolab\ReCaptcha\ReCaptchaBuilderV2::class;
            break;
        case 'invisible':
            $recaptchaClass = \Biscolab\ReCaptcha\ReCaptchaBuilderInvisible::class;
            break;
    }

    $recaptchaValidator = new $recaptchaClass(config('recaptcha.api_site_key'), config('recaptcha.api_secret_key'));

    return $recaptchaValidator->validate($value);
}

function reload() {
    return redirect($_SERVER['PHP_SELF']);
}

function redirect($path) {
    return header('Location: '.$path);
}

function request() {
    return new MockedRequest;
}

function renderPaginator($paginator, $next = 'Next', $prev = 'Prev') {
    $parsedUrl = parse_url($_SERVER['REQUEST_URI']);
    $path = basename($parsedUrl['path']);

    $elements = [];

    ob_start();

    require __DIR__.'/pagination.php';

    $htmlContent = ob_get_clean();

    return $htmlContent;
}

function renderView($view, $data) {
    $html = '';
    foreach ($data as $key => $value) {
        $label = str_replace('_', ' ', mb_convert_case($key, MB_CASE_TITLE, 'UTF-8'));
        $html .= '<b>'.$label.'</b>: '.$value.'<br/>';
    }
    return $html;
}

function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $parsedUrl = parse_url($_SERVER['REQUEST_URI']);
    $path = $parsedUrl['path'];
    return $protocol . '://' . $host . $path;
}

function url($relativeUrl) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    
    // Get the current domain
    $domain = $_SERVER['HTTP_HOST'];
    
    // Get the current path
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    
    // Combine protocol, domain, and path to create the base URL
    $baseUrl = "$protocol://$domain$path";
    
    // If the relative URL starts with a slash, remove it
    $relativeUrl = ltrim($relativeUrl, '/');
    
    // Combine the base URL and relative URL to create the absolute URL
    $absoluteUrl = $baseUrl . '/' . $relativeUrl;
    
    return $absoluteUrl;
}

function composeMail() {
    $email = (new Email())
        ->from(config('mail.from', config('mail.username')))
        //->cc('cc@example.com')
        //->bcc('bcc@example.com')
        //->replyTo('fabien@example.com')
        //->priority(Email::PRIORITY_HIGH)
        ->subject(config('email.subject'));

    foreach (config('email.receivers', []) as $address) {
        $email->to($address);
    }

    return $email;
}

function sendMail($email) {
    $mail = config('mail');
    $dsn = 'smtp://'.$mail['username'].':'.$mail['password'].'@'.$mail['host'].':'.$mail['port'];
    // $dsn = 'gmail+smtp://'.$mail['username'].':'.$mail['password'].'@default';
    $transport = Transport::fromDsn($dsn);
    $mailer = new Mailer($transport);

    $mailer->send($email);
}

function exportExcel() {
    $response = (new FormResponsesExport)->download('responses.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    return $response->send();
}

if (config('db.enabled', true)) {
    $capsule = new Capsule;
    $capsule->addConnection(config('db'));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    if (!Capsule::schema()->hasTable('form_responses')) {
        Capsule::schema()->create('form_responses', function($table) {
            $table->id();
            $table->text('data');
            $table->timestamps();
        });
    }
}

\Illuminate\Pagination\LengthAwarePaginator::currentPageResolver(function($pageName) {
    return $_GET[$pageName] ?? 1;
});

class Session
{
    public static $manager;
}

class FormResponse extends Model
{
    protected $guarded = [];
    protected $casts = ['data' => 'array'];
}

class ApplicationResponse
{
    public function download($file, $name = null, array $headers = [], $disposition = 'attachment') {
        
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (! is_null($name)) {
            return $response->setContentDisposition($disposition, $name, $this->fallbackName($name));
        }

        return $response;
    }
    
    protected function fallbackName($name)
    {
        return str_replace('%', '', Str::ascii($name));
    }
}

class MockedRequest {
    public function ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'] ?? '')) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        return $ip;
    }
    
    public function getClientIp() {
        return $this->ip();
    }
}

class Validator
{
    public $lang;
    public $group;
    public $factory;
    public $namespace;

    // Translations root directory
    public $basePath;

    public static $translator;

    protected static $translationRootPath;

    public function __construct($namespace = 'lang', $lang = 'en', $group = 'validation')
    {
        $this->lang = $lang;
        $this->group = $group;
        $this->namespace = $namespace;
        $this->basePath = $this->getTranslationsRootPath();
        $this->factory = new Factory($this->loadTranslator());
    }

    public static function make($data, $rules, $messages = [], $attributes = [])
    {
        $validatorFactory = new self();
        return $validatorFactory->factory->make($data, $rules, $messages, $attributes);
    }

    public static function setTranslationPath($path)
    {
        self::$translationRootPath = $path;
    }

    public function translationsRootPath(string $path = '')
    {
        if (!empty($path)) {
            $this->basePath = $path;
            $this->reloadValidatorFactory();
        }
        return $this;
    }

    private function reloadValidatorFactory()
    {
        $this->factory = new Factory($this->loadTranslator());
        return $this;
    }

    public function getTranslationsRootPath(): string
    {
        return self::$translationRootPath . '/' ?? dirname(__FILE__) . '/';
    }

    public function loadTranslator(): Translator
    {
        $loader = new FileLoader(new Filesystem(), $this->basePath . $this->namespace);
        $loader->addNamespace($this->namespace, $this->basePath . $this->namespace);
        $loader->load($this->lang, $this->group, $this->namespace);
        return static::$translator = new Translator($loader, $this->lang);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->factory, $method], $args);
    }
}

class FormResponsesExport implements FromCollection, WithMapping, WithHeadings
{
    use \Maatwebsite\Excel\Concerns\Exportable;

    protected $columns;

    public function __construct() {
        $this->columns = $this->getColumnsForResponse(FormResponse::first());
    }

    public function collection()
    {
        return FormResponse::all();
    }

    public function headings(): array
    {
        $return = [];
        foreach ($this->columns as $column) {
            $return[] = Str::title(Str::after($column, '.'));
        }
        return $return;
    }
    
    public function map($response): array
    {
        $return = [];
        foreach ($this->columns as $column) {
            $return[] = Arr::get($response, $column, '');
        }
        return $return;
    }

    protected function getColumnsForResponse($response) {
        $columns = config('responses.columns', null);
        if (!isset($columns)) {
            $columns = collect($response->data)->map(function($value, $key) {
                return 'data.'.$key;
            })->toArray();
        }
        $columns = array_merge([
            'id',
            'created_at',
        ], $columns, [
        ]);

        return $columns;
    }
}
